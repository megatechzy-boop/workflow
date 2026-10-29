@php
    // One colour per status: the bar, the tiles and the legend use the same ones
    $tones = [
        'Published' => 'bg-brand-600',
        'Scheduled' => 'bg-blue-500',
        'Approved' => 'bg-green-500',
        'In progress' => 'bg-accent-500',
    ];
    $planned = $stats['Planned posts'];
    $live = $stats['Published'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="report" class="w-7 h-7 text-brand-600" /> Monthly report</h2>
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
                        onclick="this.disabled = true; downloadDocPdf(document.getElementById('report'), @js(Str::slug($client->name).'-'.$month->format('Y-m').'-report.pdf')).finally(() => this.disabled = false)">
                    <x-icon name="download" class="w-4 h-4" /> Download PDF
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            {{-- The sheet keeps an A4-like width (same PDF on every device); on phones it scrolls sideways inside this box --}}
            <div class="overflow-x-auto rounded-2xl border border-gray-200/70 shadow-sm">
                <div id="report" class="paper min-w-[680px] overflow-hidden">
                    {{-- Brand band --}}
                    <header class="bg-brand-600 text-white px-8 py-6 flex items-center justify-between gap-6">
                        <div class="flex items-center gap-4 min-w-0">
                            <span class="w-14 h-14 rounded-xl bg-white flex items-center justify-center shrink-0">
                                <x-application-logo class="w-9 h-auto" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-white/75">Monthly social media report</p>
                                <h1 class="text-[28px] leading-tight font-bold truncate">{{ $client->name }}</h1>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-block px-3 py-1 rounded-full bg-accent-500 text-gray-900 text-sm font-bold">{{ $month->format('F Y') }}</span>
                            <p class="text-xs text-white/75 mt-2">Prepared {{ now()->format('d M Y') }}</p>
                        </div>
                    </header>

                    <div class="px-8 py-7 space-y-7">
                        {{-- Headline + progress --}}
                        <section class="space-y-3">
                            <div class="flex items-baseline justify-between gap-4">
                                <p class="text-lg text-gray-700">
                                    <span class="text-3xl font-bold text-gray-900 tabular-nums">{{ $live }}</span>
                                    of <span class="font-semibold text-gray-900 tabular-nums">{{ $planned }}</span> planned posts are live
                                </p>
                                <span class="text-sm font-semibold text-brand-700 tabular-nums">{{ $planned ? round($live / $planned * 100) : 0 }}%</span>
                            </div>
                            <div class="flex h-3 rounded-full overflow-hidden bg-gray-100" role="img" aria-label="Posts by status">
                                @foreach ($tones as $label => $tone)
                                    @if ($planned && $stats[$label] > 0)
                                        <div class="{{ $tone }}" style="width: {{ $stats[$label] / $planned * 100 }}%"></div>
                                    @endif
                                @endforeach
                            </div>
                        </section>

                        {{-- Tiles --}}
                        <section class="grid grid-cols-5 gap-3">
                            <div class="rounded-xl bg-gray-900 text-white p-4">
                                <div class="text-3xl font-bold tabular-nums">{{ $planned }}</div>
                                <div class="text-xs text-white/70 mt-1">Planned posts</div>
                            </div>
                            @foreach ($tones as $label => $tone)
                                <div class="rounded-xl border border-gray-200 p-4">
                                    <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $stats[$label] }}</div>
                                    <div class="text-xs text-gray-600 mt-1 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full {{ $tone }}"></span>{{ $label }}</div>
                                </div>
                            @endforeach
                        </section>

                        {{-- Posts --}}
                        <section>
                            <h3 class="text-sm font-bold uppercase tracking-[0.1em] text-gray-500 mb-3">Content calendar</h3>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wider text-gray-500 border-b-2 border-gray-200">
                                        <th class="py-2 pr-3 w-20">Date</th>
                                        <th class="py-2 pr-3">Format · Platforms</th>
                                        <th class="py-2 pr-3">Topic</th>
                                        <th class="py-2 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $item)
                                        @php($content = $item->task?->contents->first())
                                        <tr class="align-top border-b border-gray-100 {{ $loop->even ? 'bg-gray-50' : '' }}">
                                            <td class="py-3 pr-3">
                                                <div class="text-xl font-bold leading-none text-gray-900 tabular-nums">{{ $item->post_date->format('d') }}</div>
                                                <div class="text-[11px] uppercase text-gray-500 mt-1 whitespace-nowrap">{{ $item->post_date->format('M · D') }}</div>
                                            </td>
                                            <td class="py-3 pr-3">
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($item->formats() as $f)
                                                        <span class="px-2 py-0.5 rounded-md bg-brand-50 text-brand-800 text-xs font-medium">{{ $item->formatLabel($f) }}</span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="py-3 pr-3 text-gray-700">{{ Str::limit(strtok((string) ($item->brief ?: $item->creative_copy), "\n"), 90) ?: '—' }}</td>
                                            <td class="py-3 text-right">
                                                <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap {{ $content?->status->color() ?? 'bg-gray-100 text-gray-600' }}">{{ $content?->status->label() ?? 'Planned' }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="py-10 text-center text-gray-500">No posts planned this month.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </section>
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
