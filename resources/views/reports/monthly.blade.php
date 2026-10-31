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
<x-report-sheet title="Monthly report" kicker="Monthly social media report" slug="report" :brands="$brands" :client="$client" :month="$month">
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
</x-report-sheet>
