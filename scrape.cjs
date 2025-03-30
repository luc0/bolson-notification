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
