<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Load manifest sirve para webapp, para que sea instalable como PWA -->
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0d6efd">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <script>
            if ('serviceWorker' in navigator) {
                // navigator.serviceWorker.register('/service-worker.js');
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/service-worker.js')
                        .then(r => console.log('SW scope:', r.scope))
                        .catch(console.error);
                });
            }
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
        @stack('scripts')
        
        @livewireScripts
        
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
