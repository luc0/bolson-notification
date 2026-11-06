const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

// Sites and destination come from Laravel (config/sources.php) through stdin:
// { "capturePath": "/abs/path", "sites": [{ "key": "anezca", "url": "https://...", "waitFor": ".item" }] }
const { capturePath, sites } = JSON.parse(fs.readFileSync(0, 'utf-8'));

(async () => {
    let browser;

    browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    await Promise.allSettled(sites.map(async (site) => {
        try {
            const page = await browser.newPage();
            await page.goto(site.url, {
                waitUntil: 'networkidle0',
                timeout: 60000
            });

            // Sirve para sitios que cargan dinamicamente con js.
            if (site['waitFor']) {
                await page.waitForSelector(site['waitFor'], { timeout: 15000 });
            }

            // Sirve para hacer log de lo que haga dentro de page.evaluate
            page.on('console', msg => {
                for (let i = 0; i < msg.args().length; ++i) {
                    msg.args()[i].jsonValue().then(val => {
                        console.log(`[browser log]`, val);
                    });
                }
            });

            const capture = await page.evaluate(() => {
                // Eliminar estilos embebidos y hojas de estilo externas
                document.querySelectorAll('style, link[rel="stylesheet"]').forEach(el => el.remove());

                // Save the rendered width of each image before cloning (clones aren't rendered),
                // to drop icons. Lazy images not loaded yet have width 0 and are kept.
                document.querySelectorAll('img').forEach(img => {
                    img.setAttribute('data-rendered-width', String(img.naturalWidth || img.width || 0));
                });

                const bodyClone = document.body.cloneNode(true);

                // Remover scripts
                bodyClone.querySelectorAll('script').forEach(s => s.remove());

                // Remover el <header>
                const header = bodyClone.querySelector('header');
                if (header) header.remove();

                // Remover comentarios HTML recursivamente
                const removeComments = (node) => {
                    for (let i = node.childNodes.length - 1; i >= 0; i--) {
                        const child = node.childNodes[i];
                        if (child.nodeType === Node.COMMENT_NODE) {
                            node.removeChild(child);
                        } else if (child.nodeType === Node.ELEMENT_NODE) {
                            removeComments(child);
                        }
                    }
                };
                removeComments(bodyClone);

                // 🖼️ Replace each image src with a short id ("#img-3") so the AI can return the main
                // photo without copying long URLs. The real URLs are saved next to the capture.
                const images = [];
                const replaceImageSources = (node) => {
                    for (const img of node.querySelectorAll('img')) {
                        const lazySrc = img.getAttribute('data-src') || img.getAttribute('data-lazy-src');
                        const rawSrc = lazySrc || img.getAttribute('src') || '';
                        const isHttpImage = rawSrc !== '' && !rawSrc.startsWith('data:');
                        const renderedWidth = Number(img.getAttribute('data-rendered-width'));
                        const isIcon = renderedWidth > 0 && renderedWidth < 80;
                        if (!isHttpImage || isIcon) {
                            img.remove();
                            continue;
                        }
                        images.push(new URL(rawSrc, document.baseURI).href);
                        img.setAttribute('src', `#img-${images.length - 1}`);
                    }
                };
                replaceImageSources(bodyClone);

                // 🧹 Remover todos los atributos de las etiquetas (excepto href y src de imágenes)
                const removeAttributes = (node) => {
                    // Incluye el nodo raíz también
                    const allElements = [node, ...node.querySelectorAll('*')];

                    for (const el of allElements) {
                        const attrs = Array.from(el.attributes).map(attr => attr.name.toLowerCase());
                        for (const attrName of attrs) {
                            const isKeptAttribute = attrName === 'href' || (el.tagName === 'IMG' && attrName === 'src');
                            if (!isKeptAttribute) {
                                el.removeAttribute(attrName);
                            }
                        }
                    }
                };
                removeAttributes(bodyClone);

                // 🗑️ Remover nodos vacíos recursivamente
                const removeEmptyNodes = (node) => {
                    const isEmpty = (el) =>
                        el.nodeType === Node.ELEMENT_NODE &&
                        el.tagName !== 'IMG' &&
                        !(el.textContent || '').trim() &&
                        el.children.length === 0;

                    for (let i = node.children.length - 1; i >= 0; i--) {
                        const child = node.children[i];
                        removeEmptyNodes(child);
                        if (isEmpty(child)) {
                            child.remove();
                        }
                    }
                };
                removeEmptyNodes(bodyClone);

                // ✅ Asegurar que se procesen atributos del nodo raíz también
                removeAttributes(bodyClone);

                // 🧼 Serializar todo el body con sus cambios
                const tempContainer = document.createElement('div');
                tempContainer.appendChild(bodyClone);

                // Devolver outerHTML para incluir el nodo raíz limpio
                return {
                    html: tempContainer.innerHTML.replace(/\s+/g, ' ').trim(),
                    images,
                };

            });

            saveCapture(`${site.key}.html`, capture.html);
            saveCapture(`${site.key}.images.json`, JSON.stringify(capture.images, null, 1));
            console.log(`✅ ${site.key} captured`);
        } catch (err) {
            console.error(`❌ Error con ${site.url}:`, err.message);
        }
    }));

    await browser.close();
})();

function saveCapture(fileName, html) {
    const filePath = path.join(capturePath, fileName);
    const dir = path.dirname(filePath);

    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    fs.writeFileSync(filePath, html, 'utf-8');
}
