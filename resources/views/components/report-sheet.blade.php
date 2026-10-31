@props(['title', 'kicker', 'slug', 'brands', 'client', 'month'])
{{-- Shared frame for the customer reports: tabs, brand + month filter, PDF button and the A4-like sheet --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="report" class="w-7 h-7 text-brand-600" /> {{ $title }}</h2>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                @if ($brands->count() > 1)
                    <select name="client" class="border-gray-300 rounded-xl text-sm focus:border-brand-500 focus:ring-brand-500" aria-label="Brand">
                        @foreach ($brands as $b)
                            <option value="{{ $b->id }}" @selected($b->id === $client->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                @endif
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="border-gray-300 rounded-xl text-sm focus:border-brand-500 focus:ring-brand-500" aria-label="Month">
                <button class="px-3 py-2 min-h-[40px] rounded-xl border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Show</button>
                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-xl bg-brand-600 text-white text-sm font-semibold disabled:opacity-60"
                        onclick="this.disabled = true; downloadDocPdf(document.getElementById('report'), @js(Str::slug($client->name).'-'.$month->format('Y-m').'-'.$slug.'.pdf')).finally(() => this.disabled = false)">
                    <x-icon name="download" class="w-4 h-4" /> Download PDF
                </button>
            </form>
        </div>
        <nav class="mt-3 flex gap-1 overflow-x-auto text-sm" aria-label="Reports">
            @foreach (['reports.monthly' => 'Monthly', 'reports.posting' => 'Posting', 'reports.ads' => 'Ads', 'reports.leads' => 'Leads'] as $route => $label)
                <a href="{{ route($route, ['client' => $client->id, 'month' => $month->format('Y-m')]) }}"
                   class="px-3 py-1.5 rounded-xl whitespace-nowrap {{ request()->routeIs($route) ? 'bg-brand-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            {{-- The sheet keeps an A4-like width (same PDF on every device); on phones it scrolls sideways inside this box --}}
            <div class="overflow-x-auto rounded-2xl border border-gray-200/70 shadow-sm">
                <div id="report" class="paper min-w-[680px] overflow-hidden">
                    <header class="bg-brand-600 text-white px-8 py-6 flex items-center justify-between gap-6">
                        <div class="flex items-center gap-4 min-w-0">
                            <span class="w-14 h-14 rounded-xl bg-white flex items-center justify-center shrink-0">
                                <x-application-logo class="w-9 h-auto" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-white/75">{{ $kicker }}</p>
                                <h1 class="text-[28px] leading-tight font-bold truncate">{{ $client->name }}</h1>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-block px-3 py-1 rounded-full bg-accent-500 text-gray-900 text-sm font-bold">{{ $month->format('F Y') }}</span>
                            <p class="text-xs text-white/75 mt-2">Prepared {{ now()->format('d M Y') }}</p>
                        </div>
                    </header>

                    <div class="px-8 py-7 space-y-7">
                        {{ $slot }}
                    </div>

                    <footer class="px-8 py-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                        <span class="flex items-center gap-2"><x-application-logo class="w-5 h-auto" /> Prepared by MegaTechzy</span>
                        <span>{{ $client->name }} · {{ $month->format('F Y') }}</span>
                    </footer>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
