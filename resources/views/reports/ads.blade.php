@php($num = fn ($n) => $n === null ? '—' : number_format($n))
@php($cost = fn ($spend, $results) => $spend !== null && $results ? '₹'.number_format($spend / $results, 2) : '—')
<x-report-sheet title="Ad report" kicker="Ads report" slug="ads" :brands="$brands" :client="$client" :month="$month">
    <section class="grid grid-cols-5 gap-3">
        <div class="rounded-xl bg-gray-900 text-white p-4">
            <div class="text-3xl font-bold tabular-nums">₹{{ number_format($totals['spend']) }}</div>
            <div class="text-xs text-white/70 mt-1">Spent</div>
        </div>
        @foreach (['reach' => 'Reach', 'clicks' => 'Clicks', 'results' => 'Results'] as $key => $label)
            <div class="rounded-xl border border-gray-200 p-4">
                <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ number_format($totals[$key]) }}</div>
                <div class="text-xs text-gray-600 mt-1">{{ $label }}</div>
            </div>
        @endforeach
        <div class="rounded-xl border border-gray-200 p-4">
            <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $cost($totals['spend'], $totals['results']) }}</div>
            <div class="text-xs text-gray-600 mt-1">Per result</div>
        </div>
    </section>

    <section>
        <h3 class="text-sm font-bold uppercase tracking-[0.1em] text-gray-500 mb-3">Ads and boosts</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wider text-gray-500 border-b-2 border-gray-200">
                    <th class="py-2 pr-3">Ad</th>
                    <th class="py-2 pr-3 text-right">Spent</th>
                    <th class="py-2 pr-3 text-right">Reach</th>
                    <th class="py-2 pr-3 text-right">Clicks</th>
                    <th class="py-2 pr-3 text-right">Results</th>
                    <th class="py-2 text-right">Per result</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="align-top border-b border-gray-100">
                        <td class="py-3 pr-3"><div class="font-medium text-gray-900">{{ $row['name'] }}</div><div class="text-xs text-gray-500">{{ $row['goal'] }}</div></td>
                        <td class="py-3 pr-3 text-right tabular-nums">{{ $row['stats']['spend'] === null ? '—' : '₹'.number_format($row['stats']['spend']) }}</td>
                        <td class="py-3 pr-3 text-right tabular-nums">{{ $num($row['stats']['reach']) }}</td>
                        <td class="py-3 pr-3 text-right tabular-nums">{{ $num($row['stats']['clicks']) }}</td>
                        <td class="py-3 pr-3 text-right tabular-nums">{{ $num($row['stats']['results']) }}</td>
                        <td class="py-3 text-right tabular-nums">{{ $cost($row['stats']['spend'], $row['stats']['results']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-gray-500">{{ $connected ? 'No ads ran this month.' : 'Facebook is not connected yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
        <p class="mt-3 text-xs text-gray-500">Results: WhatsApp / Messenger chats started, calls, website clicks, people reached or post engagement, by the ad's goal. Numbers refresh every 3 hours.</p>
    </section>
</x-report-sheet>
