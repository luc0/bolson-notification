<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link rel="icon" type="image/png" href="/images/favicon.png">
        <link rel="apple-touch-icon" href="/images/logo.png">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
            <div>
                <a href="/">
                    <x-application-logo class="w-32 h-32 fill-current text-gray-500" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-4 px-6 text-center">
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Alquileres en la Comarca</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Alquileres de las inmobiliarias de El Bolsón y Lago Puelo. Te avisamos cuando hay uno nuevo.</p>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
        <x-app-version />
    </body>
</html>
