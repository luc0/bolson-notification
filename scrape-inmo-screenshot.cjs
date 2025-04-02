const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const SCREENSHOT_DIR = './captures';

(async () => {
    if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR);

    const browser = await puppeteer.launch({
        headless: false,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const page = await browser.newPage();

    await page.setUserAgent(
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) ' +
        'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 ' +
        'Mobile/15E148 Safari/604.1'
    );

    await page.setViewport({
        width: 1200,
        height: 800, // puede ser cualquier valor razonable
        deviceScaleFactor: 2
    });

    await page.addStyleTag({
        content: `
    * {
      font-size: 20px !important;
      line-height: 1.6 !important;
      font-family: "Verdana", "Arial", sans-serif !important;
    }
  `
    });

    async function captureSite(url, fileName) {
        console.log(`🌐 Abriendo ${url}`);
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

        const filePath = path.join(SCREENSHOT_DIR, fileName);
        await page.screenshot({ path: filePath, fullPage: true });

        console.log(`✅ Captura guardada: ${filePath}`);
    }

    await captureSite(
        'https://anezcapropiedades.com.ar/properties?property_type_id=&operation_id=2&location_id=1396&currency_id=&price_min=&price_max=&bathrooms=&bedrooms=&order=',
        'anezca_full.jpg'
    );

    await captureSite(
        'https://www.inmobiliariapuntopatagonia.com.ar/Alquiler',
        'puntopatagonia_full.jpg'
    );

    await captureSite(
        'https://puntosurpropiedades.ar/web/index.php?search_tipo_de_propiedad=1&search_locality=El%20Bols%C3%B3n&search_tipo_de_operacion=2#listado',
        'puntosurpropiedades_full.jpg'
    );

    await captureSite(
        'https://www.rioazulpropiedades.com/Buscar?operation=2&locations=40933&o=2,2&1=1',
        'rioazulpropiedades_full.jpg'
    );

    await captureSite(
        'https://inmobiliariadelagua.com.ar/s/alquiler////?business_type%5B%5D=for_rent',
        'inmobiliariadelagua_full.jpg'
    );

    await browser.close();
})();
