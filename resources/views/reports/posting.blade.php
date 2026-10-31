@php($num = fn ($n) => $n === null ? '—' : number_format($n))
<x-report-sheet title="Posting report" kicker="Posting report" slug="posting" :brands="$brands" :client="$client" :month="$month">
    <section class="grid grid-cols-5 gap-3">
        <div class="rounded-xl bg-gray-900 text-white p-4">
            <div class="text-3xl font-bold tabular-nums">{{ $published }}<span class="text-lg text-white/60">{{ $quota ? ' / '.$quota : '' }}</span></div>
            <div class="text-xs text-white/70 mt-1">{{ $quota ? 'Posted vs package' : 'Posted' }}</div>
        </div>
        @foreach ($totals as $label => $value)
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ number_format($value) }}</div>
                <div class="text-xs text-gray-600 mt-1">{{ $label }}</div>
            </div>
        @endforeach
    </section>

    <section>
        <h3 class="text-sm font-bold uppercase tracking-[0.1em] text-gray-500 mb-3">Posts</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wider text-gray-500 border-b-2 border-gray-200">
                    <th class="py-2 pr-3 w-20">Date</th>
                    <th class="py-2 pr-3">Post</th>
                    <th class="py-2 pr-3">Platform</th>
                    <th class="py-2 pr-3 text-right">Reach</th>
                    <th class="py-2 pr-3 text-right">Likes</th>
                    <th class="py-2 pr-3 text-right">Comments</th>
                    <th class="py-2 text-right">Shares</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php($content = $item->task?->contents->first())
                    @php($rows = $content ? ($posts[$content->id] ?? collect()) : collect())
                    @foreach ($rows->isEmpty() ? [null] : $rows as $row)
                        <tr class="align-top border-b border-gray-100">
                            @if ($loop->first)
                                <td class="py-3 pr-3" rowspan="{{ max(1, $rows->count()) }}">
                                    <div class="text-xl font-bold leading-none text-gray-900 tabular-nums">{{ $item->post_date->format('d') }}</div>
                                    <div class="text-[11px] uppercase text-gray-500 mt-1 whitespace-nowrap">{{ $item->post_date->format('M · D') }}</div>
                                </td>
                                <td class="py-3 pr-3 text-gray-700" rowspan="{{ max(1, $rows->count()) }}">
                                    <div class="flex flex-wrap gap-1 mb-1">
                                        @foreach ($item->formats() as $f)
                                            <span class="px-2 py-0.5 rounded-md bg-brand-50 text-brand-800 text-xs font-medium">{{ $item->formatLabel($f) }}</span>
                                        @endforeach
                                    </div>
                                    {{ Str::limit(strtok((string) ($item->brief ?: $item->creative_copy), "\n"), 60) ?: '—' }}
                                </td>
                            @endif
                            @if ($row)
                                <td class="py-3 pr-3">{{ ucfirst($row['post']->platform) }}</td>
                                <td class="py-3 pr-3 text-right tabular-nums">{{ $num($row['stats']['reach']) }}</td>
                                <td class="py-3 pr-3 text-right tabular-nums">{{ $num($row['stats']['likes']) }}</td>
                                <td class="py-3 pr-3 text-right tabular-nums">{{ $num($row['stats']['comments']) }}</td>
                                <td class="py-3 text-right tabular-nums">{{ $num($row['stats']['shares']) }}</td>
                            @else
                                <td class="py-3 text-gray-500" colspan="5">{{ $content?->status->label() ?? 'Planned' }}</td>
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-gray-500">No posts this month.</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">Numbers are from Facebook and Instagram and refresh every 3 hours. "—" means Meta did not share that number.</p>
    </section>
</x-report-sheet>
