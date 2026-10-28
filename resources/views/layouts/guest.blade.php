<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('layouts._pwa-head')

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gradient-to-b from-brand-50 to-white">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-10 pt-safe">
            <a href="/" class="flex flex-col items-center gap-3">
                <x-application-logo class="w-20 h-auto" />
                <span class="text-2xl font-bold tracking-tight">Mega<span class="text-brand-600">Techzy</span></span>
                <span class="text-sm text-gray-500 -mt-2">Innovative Digital Solutions. <span class="text-accent-600">Real Impact.</span></span>
            </a>

            <div class="w-full sm:max-w-md mt-8 card p-6 sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
