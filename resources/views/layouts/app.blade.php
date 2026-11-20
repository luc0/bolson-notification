<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
        <meta name="push-subscriptions-url" content="{{ route('push-subscriptions.store', absolute: false) }}">

        <!-- Load manifest sirve para webapp, para que sea instalable como PWA -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
        <meta name="theme-color" content="#0d6efd">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link rel="icon" type="image/png" href="/images/favicon.png">
        <link rel="apple-touch-icon" href="/images/logo.png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            (() => {
                const APP_VERSION = @json(\App\Support\AppVersion::current());
                let registration = null;

                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => {
                        // updateViaCache 'none': the browser always checks the server for a new service worker.
                        navigator.serviceWorker.register('/service-worker.js', { updateViaCache: 'none' })
                            .then(r => { registration = r; })
                            .catch(console.error);
                    });
                }

                // The installed app resumes the page it had open (even days later) instead of loading it again:
                // when it comes back to the foreground, reload if a new version was deployed meanwhile.
                async function reloadIfOutdated() {
                    registration?.update().catch(() => {});
                    try {
                        const response = await fetch(@json(route('app.version', absolute: false)), { cache: 'no-store', headers: { Accept: 'application/json' } });
                        if (!response.ok) return;
                        const { version } = await response.json();
                        const isOutdated = version && version !== APP_VERSION;
                        if (isOutdated) location.reload();
                    } catch (error) {
                        // Offline or session expired: keep the current page.
                    }
                }

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') reloadIfOutdated();
                });
                window.addEventListener('pageshow', (event) => {
                    if (event.persisted) reloadIfOutdated();
                });
            })();
        </script>
    </head>
    <body class="font-sans antialiased">
        <script>
            // Theme switcher functionality
            (function() {
                const theme = localStorage.getItem('theme') || 'light';
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
        <x-app-version />
        @stack('scripts')
        
        <script>
            // Theme switcher functions
            function toggleTheme() {
                const html = document.documentElement;
                const currentTheme = localStorage.getItem('theme') || 'light';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                
                if (newTheme === 'dark') {
                    html.classList.add('dark');
                } else {
                    html.classList.remove('dark');
                }
                
                localStorage.setItem('theme', newTheme);
                
                // Update theme color meta tag
                const metaThemeColor = document.querySelector('meta[name="theme-color"]');
                if (metaThemeColor) {
                    metaThemeColor.content = newTheme === 'dark' ? '#1f2937' : '#0d6efd';
                }
                
                // Update mobile theme text
                const themeText = document.getElementById('theme-text');
                if (themeText) {
                    themeText.textContent = newTheme === 'light' ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro';
                }
            }
            
            function getCurrentTheme() {
                return localStorage.getItem('theme') || 'light';
            }
            
            // Initialize theme text on page load
            document.addEventListener('DOMContentLoaded', function() {
                const currentTheme = getCurrentTheme();
                const themeText = document.getElementById('theme-text');
                if (themeText) {
                    themeText.textContent = currentTheme === 'light' ? 'Cambiar a modo oscuro' : 'Cambiar a modo claro';
                }
            });
        </script>
    </body>
</html>
