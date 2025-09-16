import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// export default defineConfig({
//     plugins: [
//         laravel({
//             input: ['resources/css/app.css', 'resources/js/app.js'],
//             refresh: true,
//         }),
//     ],
// });
//
// import { defineConfig } from 'vite'
// import laravel from 'laravel-vite-plugin'

export default defineConfig({
    server: {
        host: true,                 // escucha en 0.0.0.0
        port: 5173,
        hmr: {
            host: 'https://f2b840f31172.ngrok-free.app',
            // host: '192.168.18.13',
            protocol: 'wss',
            clientPort: 443,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
})
