const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const sites = [
    {
        file: 'anezca.html',
        url: 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
        waitFor: null,
    },
    {
        file: 'puntopatagonia.html',
        url: 'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler',
        waitFor: '.prop-desc'
    },
    {
        file: 'puntosurpropiedades.html',
        url: 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado',
        waitFor: null,
    },
    {
        file: 'rioazulpropiedades.html',
        url: 'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1',
        waitFor: null,
    },
    {
        file: 'inmobiliariadelagua.html',
        url: 'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent',
        waitFor: '.item',
    }
];
(async () => {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium-browser', // o el path correcto en tu server
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
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

            const cleanBody = await page.evaluate(() => {
                // Eliminar estilos embebidos y hojas de estilo externas
                document.querySelectorAll('style, link[rel="stylesheet"]').forEach(el => el.remove());

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

                // 🧹 Remover todos los atributos de las etiquetas (excepto href)
                const removeAttributes = (node) => {
                    // console.log('limpiando', node.tagName);
                    // Incluye el nodo raíz también
                    const allElements = [node, ...node.querySelectorAll('*')];

                    for (const el of allElements) {
                        const attrs = Array.from(el.attributes).map(attr => attr.name);
                        for (const attrName of attrs) {
                            if (attrName.toLowerCase() !== 'href') {
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
                        !el.innerText.trim() &&
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
                return tempContainer.innerHTML.replace(/\s+/g, ' ').trim();

            });

            saveCapture(site.file, cleanBody);
            console.log(`✅ HTML limpio guardado en captures/${site.file}`);
        } catch (err) {
            console.error(`❌ Error con ${site.url}:`, err.message);
        }
    }));

    await browser.close();
})();

function saveCapture(fileName, html) {
    const filePath = path.join(__dirname, 'captures', fileName);
    const dir = path.dirname(filePath);

    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }

    fs.writeFileSync(filePath, html, 'utf-8');
}
