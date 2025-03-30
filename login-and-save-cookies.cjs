// login-and-save-cookies.cjs
const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const browser = await puppeteer.launch({
        headless: false, // Abrir navegador visible para que puedas loguearte
        defaultViewport: null
    });

    const page = await browser.newPage();

    console.log('Abriendo Facebook... Logueate manualmente y luego navegá al grupo.');

    await page.goto('https://www.facebook.com/login', {
        waitUntil: 'networkidle2'
    });

    // Esperamos a que el usuario se loguee y llegue al grupo
    console.log('Esperando a que llegues al grupo...');
    await page.waitForNavigation({ waitUntil: 'networkidle2' });

    // Cuando llegues al grupo (o cualquier página de Facebook), guardamos cookies
    await new Promise(resolve => {
        console.log('\nPresioná ENTER en la terminal cuando estés logueado y en el grupo.');
        process.stdin.once('data', resolve);
    });

    const cookies = await page.cookies();
    fs.writeFileSync('cookies.json', JSON.stringify(cookies, null, 2));
    console.log('✅ Cookies guardadas en cookies.json');

    await browser.close();
})();
