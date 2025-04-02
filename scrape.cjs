const puppeteer = require('puppeteer');
const fs = require('fs');

const sites = [
    {
        file: 'anezca.html',
        url: 'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order='
    },
    {
        file: 'puntopatagonia.html',
        url: 'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler'
    },
    {
        file: 'puntosurpropiedades.html',
        url: 'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado'
    },
    {
        file: 'rioazulpropiedades.html',
        url: 'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1'
    },
    {
        file: 'inmobiliariadelagua.html',
        url: 'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent'
    }
];

(async () => {
    const browser = await puppeteer.launch({ headless: 'new' });

    await Promise.allSettled(sites.map(async (site) => {
        try {
            const page = await browser.newPage();
            await page.goto(site.url, {
                waitUntil: 'networkidle0',
                timeout: 60000
            });

            // await page.waitForTimeout(2000); // Espera por si carga algo con js

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

                // 🧹 Remover todos los atributos de las etiquetas
                // const removeAttributes = (node) => {
                //     if (node.nodeType === Node.ELEMENT_NODE) {
                //         // Copiar primero todos los nombres de atributos
                //         const attrs = Array.from(node.attributes).map(attr => attr.name);
                //
                //         for (const attrName of attrs) {
                //             if (attrName.toLowerCase() !== 'href') {
                //                 node.removeAttribute(attrName);
                //             }
                //         }
                //
                //         // Procesar hijos
                //         for (const child of node.children) {
                //             removeAttributes(child);
                //         }
                //     }
                // };

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

                // Obtener HTML limpio y reducir espacios
                let html = bodyClone.innerHTML;
                html = html.replace(/\s+/g, ' ').trim();

                return html;
            });

            fs.writeFileSync(`captures/${site.file}`, cleanBody, 'utf-8');
            console.log(`✅ HTML limpio guardado en captures/${site.file}`);
        } catch (err) {
            console.error(`❌ Error con ${site.url}:`, err.message);
        }
    }));

    await browser.close();
})();
