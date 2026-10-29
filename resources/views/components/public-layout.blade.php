{{-- Public pages without login (enquiry, onboarding): logo on top, one wide card --}}
@props(['title' => config('app.name')])
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex">
        @include('layouts._pwa-head')
        <title>{{ $title }} · Mega Techzy</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen px-4 py-8 pt-safe">
            <div class="flex flex-col items-center gap-2 text-center">
                <x-application-logo class="w-16 h-auto" />
                <span class="text-xl font-bold tracking-tight">Mega<span class="text-brand-600">Techzy</span></span>
                <h1 class="mt-2 text-2xl font-bold tracking-tight">{{ $title }}</h1>
            </div>
            <div class="w-full max-w-3xl mx-auto mt-6">
                {{ $slot }}
            </div>
            <p class="mt-8 text-center text-xs text-gray-500">megatechzy@gmail.com · +91 70201 62163 · www.MegaTechzy.com</p>
        </div>
    </body>
</html>
