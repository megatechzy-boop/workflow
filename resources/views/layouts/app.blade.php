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
    @php($nav = App\Support\Navigation::items(auth()->user()))
    @php($tabs = App\Support\Navigation::primary(auth()->user()))
    <body class="font-sans antialiased bg-gray-50 text-gray-900" x-data="{ more: false }">
        {{-- Desktop sidebar --}}
        <aside class="hidden lg:flex lg:flex-col fixed inset-y-0 left-0 w-64 bg-white border-r border-gray-200 z-30">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-5 h-16 border-b border-gray-100">
                <x-application-logo class="w-9 h-auto" />
                <span class="font-bold text-lg tracking-tight">Mega<span class="text-brand-600">Techzy</span></span>
            </a>
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1" aria-label="Main">
                @foreach ($nav as $item)
                    @php($on = request()->routeIs($item['active']))
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition {{ $on ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                       @if ($on) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" class="w-5 h-5 {{ $on ? 'text-brand-600' : 'text-gray-400' }}" />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="min-w-[20px] px-1.5 rounded-full bg-accent-500 text-white text-xs text-center">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-gray-100 p-3">
                <div class="mb-2">@include('layouts._theme-toggle')</div>
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-gray-50">
                    <span class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-semibold">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium truncate">{{ auth()->user()->name }}</span>
                        <span class="block text-xs text-gray-500">{{ auth()->user()->role->label() }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="w-full flex items-center gap-3 px-3 py-2 mt-1 rounded-xl text-sm text-gray-600 hover:bg-gray-50">
                        <x-icon name="logout" class="w-5 h-5 text-gray-400" /> Log out
                    </button>
                </form>
            </div>
        </aside>

        <div class="lg:pl-64 min-h-screen flex flex-col">
            {{-- Top bar (phone: logo + bell; desktop: bell) --}}
            <div class="sticky top-0 z-20 bg-white/85 backdrop-blur border-b border-gray-200 pt-safe">
                <div class="flex items-center justify-between h-14 px-4 lg:px-8">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 lg:hidden">
                        <x-application-logo class="w-8 h-auto" />
                        <span class="font-bold tracking-tight">Mega<span class="text-brand-600">Techzy</span></span>
                    </a>
                    <div class="hidden lg:block text-sm text-gray-500">{{ now()->format('l, d M Y') }}</div>
                    <div class="flex items-center gap-1">
                        @include('layouts._bell')
                    </div>
                </div>
            </div>

            {{-- Page title --}}
            @isset($header)
                <header class="px-4 sm:px-6 lg:px-8 pt-5 pb-1">
                    <div class="max-w-7xl mx-auto">{{ $header }}</div>
                </header>
            @endisset

            {{-- Page content (bottom space for the phone tab bar) --}}
            <main class="flex-1 px-4 sm:px-0 pb-28 lg:pb-10">
                {{ $slot }}
            </main>
        </div>

        {{-- Phone tab bar --}}
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-gray-200 pb-safe" aria-label="Tabs">
            <div class="grid grid-cols-5">
                @foreach ($tabs as $item)
                    @php($on = request()->routeIs($item['active']))
                    <a href="{{ route($item['route']) }}" class="relative flex flex-col items-center gap-0.5 pt-2 pb-1 text-[11px] font-medium {{ $on ? 'text-brand-600' : 'text-gray-500' }}" @if ($on) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" class="w-6 h-6" />
                        {{ $item['label'] }}
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="absolute top-1 left-1/2 ms-2 min-w-[18px] px-1 rounded-full bg-accent-500 text-white text-[10px] leading-[18px] text-center">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
                <button type="button" @click="more = true" class="flex flex-col items-center gap-0.5 pt-2 pb-1 text-[11px] font-medium text-gray-500">
                    <x-icon name="menu" class="w-6 h-6" />
                    More
                </button>
            </div>
        </nav>

        {{-- Phone "More" sheet --}}
        <div x-show="more" x-cloak class="lg:hidden fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="More">
            <div class="absolute inset-0 bg-black/30" @click="more = false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl p-4 pb-safe shadow-xl" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="mx-auto w-10 h-1.5 rounded-full bg-gray-200 mb-4"></div>
                <div class="flex items-center gap-3 px-1 mb-4">
                    <span class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center font-semibold">{{ Str::upper(Str::substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold truncate">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-gray-500">{{ auth()->user()->role->label() }}</div>
                    </div>
                    <button type="button" @click="more = false" class="p-2 rounded-full hover:bg-gray-100" aria-label="Close"><x-icon name="x" class="w-5 h-5" /></button>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    @foreach ($nav as $item)
                        <a href="{{ route($item['route']) }}" class="flex flex-col items-center gap-1.5 p-3 rounded-2xl text-xs font-medium {{ request()->routeIs($item['active']) ? 'bg-brand-50 text-brand-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <x-icon :name="$item['icon']" class="w-6 h-6" />
                            <span class="text-center leading-tight">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                    <a href="{{ route('profile.edit') }}" class="flex flex-col items-center gap-1.5 p-3 rounded-2xl text-xs font-medium text-gray-700 hover:bg-gray-50">
                        <x-icon name="user" class="w-6 h-6" /> Profile
                    </a>
                </div>
                <div class="mt-3">@include('layouts._theme-toggle')</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="w-full py-3 rounded-2xl bg-gray-100 text-sm font-semibold text-gray-700">Log out</button>
                </form>
            </div>
        </div>

        @include('layouts._push-gate')
    </body>
</html>
