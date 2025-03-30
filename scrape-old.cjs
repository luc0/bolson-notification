// // scrape.cjs
// // const puppeteer = require('puppeteer');
// // const fs = require('fs');
// //
// // (async () => {
// //     // 'https://www.facebook.com/groups/615188652452832'
// //     const facebookGroup = 'https://www.facebook.com/groups/468752996900341'
// //     const scrollWait = 3000
// //     const scrollCount = 4
// //
// //     const browser = await puppeteer.launch({
// //         headless: false,
// //         args: ['--no-sandbox', '--disable-setuid-sandbox']
// //     });
// //
// //     const page = await browser.newPage();
// //
// //     // Cargar cookies desde archivo
// //     const cookies = JSON.parse(fs.readFileSync('cookies.json', 'utf8'));
// //     await page.setCookie(...cookies);
// //
// //     // await page.goto(facebookGroup, {
// //     //     waitUntil: 'networkidle2'
// //     // });
// //     await page.goto(facebookGroup, {
// //         waitUntil: 'domcontentloaded',
// //         timeout: 60000 // 60 segundos
// //     });
// //
// //     const url = page.url();
// //     if (url.includes('login')) {
// //         console.error('⚠️ Estás viendo la página de login. Probablemente las cookies están vencidas o mal cargadas.');
// //         process.exit(1);
// //     }
// //
// //     console.error('Ingresado al grupo.');
// //     console.error('Esperando feed...');
// //     await page.waitForSelector('[role="feed"]');
// //
// //     // Scroll para cargar más publicaciones
// //     for (let i = 0; i < scrollCount; i++) {
// //         console.error('Scroleando...');
// //         await page.evaluate(() => window.scrollBy(0, window.innerHeight));
// //         await new Promise(resolve => setTimeout(resolve, scrollWait));
// //     }
// //
// //     // Extraer textos de publicaciones
// //     // const posts = await page.evaluate(() => {
// //     //     const elements = document.querySelectorAll('[role="feed"] [data-ad-preview="message"]');
// //     //     return Array.from(elements).map(el => el.innerText);
// //     // });
// //
// //     // extraer message, fecha y user
// //     const posts = await page.evaluate(() => {
// //         // const articles = document.querySelectorAll('[role="feed"] [role="article"]');
// //         const articles = document.querySelectorAll('[role="feed"] div');
// //
// //         return Array.from(articles).map(article => {
// //             return {
// //                 text: article.innerText,        // Texto visible del post (ideal para análisis)
// //                 html: article.innerHTML         // HTML completo por si querés parsear más tarde
// //             };
// //         });
// //     });
// //
// //     fs.writeFileSync('posts.json', JSON.stringify(posts, null, 2));
// //     console.error('✅ Posts guardados.');
// //
// //     await browser.close();
// // })();
// //
//
//
//
//
// /* Scrapping mejorado */
//
//
// const puppeteer = require('puppeteer');
// const fs = require('fs');
//
// (async () => {
//     const browser = await puppeteer.launch({
//         headless: false, // ⚠️ Usar headless: false para evitar detección
//         args: ['--no-sandbox', '--disable-setuid-sandbox']
//     });
//
//     const page = await browser.newPage();
//
//     // Cargar cookies
//     const cookies = JSON.parse(fs.readFileSync('cookies.json', 'utf8'));
//     await page.setCookie(...cookies);
//
//     console.log('🧭 Navegando al grupo...');
//
//     await page.goto('https://www.facebook.com/groups/615188652452832', {
//         waitUntil: 'domcontentloaded',
//         timeout: 60000
//     });
//
//     if (page.url().includes('login')) {
//         console.error('❌ Redirigido a login. Las cookies están vencidas.');
//         process.exit(1);
//     }
//
//     // const posts = await page.evaluate(() => {
//     //     return Array.from(document.querySelectorAll('[data-ad-rendering-role="story_message"]')).map(a => a.innerText.slice(0, 200));
//     // });
//     // console.log(posts);
//
//
//     await page.waitForSelector('[role="article"]', { timeout: 10000 });
//
//     // Scroll para cargar más publicaciones
//     console.log('📜 Scrolleando...');
//     for (let i = 0; i < 5; i++) {
//         await page.evaluate(() => window.scrollBy(0, window.innerHeight));
//         await new Promise(resolve => setTimeout(resolve, 2000 + Math.random() * 1000)); // pausa aleatoria
//     }
//
//     // Clic en botones "Ver más" si existen
//     console.log('🧩 Expandir mensajes colapsados...');
//     await page.evaluate(() => {
//         const buttons = Array.from(document.querySelectorAll('div[role="button"]')).filter(btn =>
//             btn.innerText.toLowerCase().includes('ver más') ||
//             btn.innerText.toLowerCase().includes('ver más comentarios')
//         );
//         buttons.forEach(btn => btn.click());
//     });
//
//     await new Promise(resolve => setTimeout(resolve, 3000)); // Esperar después de expandir
//
//     console.log('🔎 Extrayendo publicaciones...');
//     const posts = await page.evaluate(() => {
//         const articles = document.querySelectorAll('[role="article"]');
//
//         return Array.from(articles).map(article => {
//             const messageParts = article.querySelectorAll('[data-ad-preview="message"] span, [data-ad-preview="message"] div');
//             const message = Array.from(messageParts).map(el => el.innerText).join(' ').trim();
//
//             const user = article.querySelector('h2 span span')?.innerText
//                 || article.querySelector('h2 span')?.innerText
//                 || 'Desconocido';
//
//             const timeElement = article.querySelector('a abbr, a span');
//             const date = timeElement?.getAttribute('title') || timeElement?.innerText || 'Sin fecha';
//
//             return { user, date, message };
//         }).filter(post => post.message.length > 0);
//     });
//
//     console.log(`✅ ${posts.length} publicaciones encontradas.`);
//     fs.writeFileSync('posts.json', JSON.stringify(posts, null, 2));
//
//     await browser.close();
// })();



// v2 - captura
//
// const puppeteer = require('puppeteer');
// const fs = require('fs');
//
// // 🔧 Configuraciones
// const SCROLL_COUNT = 8;
// const WAIT_TIME_AFTER_SCROLL = 4000; // ms
// const SCREENSHOT_PATH = 'screenshot.jpg';
// const GROUP_URL = 'https://m.facebook.com/groups/468752996900341';
//
// (async () => {
//     const browser = await puppeteer.launch({
//         headless: false,
//         args: ['--no-sandbox', '--disable-setuid-sandbox']
//     });
//
//     const page = await browser.newPage();
//
//     // Emular un dispositivo móvil (iPhone)
//     await page.setUserAgent(
//         'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) ' +
//         'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 ' +
//         'Mobile/15E148 Safari/604.1'
//     );
//
//     // Cargar cookies si existen
//     if (fs.existsSync('cookies.json')) {
//         const cookies = JSON.parse(fs.readFileSync('cookies.json', 'utf8'));
//         await page.setCookie(...cookies);
//     }
//
//     console.log('🧭 Navegando al grupo (mobile)...');
//     await page.goto(GROUP_URL, {
//         waitUntil: 'domcontentloaded',
//         timeout: 60000
//     });
//
//     if (page.url().includes('login')) {
//         console.error('❌ Redirigido a login. Las cookies están vencidas.');
//         process.exit(1);
//     }
//
//     // Scroll para cargar más publicaciones
//     console.log('📜 Scrolleando...');
//     for (let i = 0; i < SCROLL_COUNT; i++) {
//         await page.evaluate(() => {
//             window.scrollBy({ top: window.innerHeight, behavior: 'smooth' });
//         });
//         await new Promise(resolve => setTimeout(resolve, WAIT_TIME_AFTER_SCROLL));
//     }
//
//     // Captura de pantalla de todo el cuerpo del documento
//     console.log('📸 Tomando screenshot...');
//     const bodyHandle = await page.$('body');
//     await bodyHandle.screenshot({ path: SCREENSHOT_PATH, type: 'jpeg', quality: 90 });
//     await bodyHandle.dispose();
//
//     console.log(`✅ Screenshot guardado como ${SCREENSHOT_PATH}`);
//     await browser.close();
// })();


// v3 capturas unidas
const puppeteer = require('puppeteer');
const fs = require('fs');
const sharp = require('sharp');
const path = require('path');

const SCROLL_TIMES = 5;
const SCROLL_DELAY = 2000;
const VIEWPORT_WIDTH = 400;
const VIEWPORT_HEIGHT = 700;
const SCREENSHOT_DIR = './captures';
const FINAL_IMAGE = 'posts.jpg';

(async () => {
    if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR);

    const browser = await puppeteer.launch({
        headless: false, // 🧪 Usá false para ver si realmente se carga bien
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });


    const page = await browser.newPage();
    await page.setViewport({ width: VIEWPORT_WIDTH, height: VIEWPORT_HEIGHT });

    await page.setUserAgent(
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) ' +
        'AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 ' +
        'Mobile/15E148 Safari/604.1'
    );

    const cookies = JSON.parse(fs.readFileSync('cookies.json', 'utf8'));
    await page.setCookie(...cookies);

    console.log('🧭 Navegando a la versión mobile...');
    await page.goto('https://m.facebook.com/groups/615188652452832/?sorting_setting=RECENT_ACTIVITY', {
        waitUntil: 'networkidle2',
        timeout: 60000
    });

    if (page.url().includes('login')) {
        console.error('❌ Redirigido a login. Las cookies están vencidas.');
        await browser.close();
        process.exit(1);
    }

    console.log('⏳ Esperando que cargue el feed...');
    await Promise.race([
        page.waitForSelector('img'),
        page.waitForSelector('[role="feed"]'),
        page.waitForSelector('[role="article"]'),
    ]);

    console.log('📸 Capturando scrolls...');
    for (let i = 0; i < SCROLL_TIMES; i++) {
        const filePath = path.join(SCREENSHOT_DIR, `frame-${i}.jpg`);

        // Asegurar que la parte visible tenga contenido
        await new Promise(res => setTimeout(res, 2000));

        await page.screenshot({
            path: filePath,
            type: 'jpeg',
            quality: 90,
            fullPage: false
        });

        console.log(`✅ Captura ${i + 1} guardada`);
        await page.evaluate(() => window.scrollBy(0, window.innerHeight));
        await new Promise(res => setTimeout(res, SCROLL_DELAY));
    }

    console.log('🧵 Uniendo imágenes con sharp...');
    const images = Array.from({ length: SCROLL_TIMES }, (_, i) =>
        path.join(SCREENSHOT_DIR, `frame-${i}.jpg`)
    );

    const sharpImages = images.map(img => sharp(img));
    const heights = await Promise.all(sharpImages.map(img => img.metadata().then(meta => meta.height)));

    const joined = sharp({
        create: {
            width: VIEWPORT_WIDTH,
            height: heights.reduce((acc, h) => acc + h, 0),
            channels: 3,
            background: '#ffffff'
        }
    });

    let top = 0;
    const composites = await Promise.all(images.map(async (imgPath, index) => {
        const buffer = await sharp(imgPath).toBuffer();
        const result = { input: buffer, top, left: 0 };
        top += heights[index];
        return result;
    }));

    await joined.composite(composites).jpeg({ quality: 90 }).toFile(FINAL_IMAGE);
    console.log(`🖼️ Imagen final generada: ${FINAL_IMAGE}`);

    await browser.close();
})();
