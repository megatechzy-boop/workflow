@php
    $search = $numbers ? $numbers['BUSINESS_IMPRESSIONS_MOBILE_SEARCH'] + $numbers['BUSINESS_IMPRESSIONS_DESKTOP_SEARCH'] : 0;
    $maps = $numbers ? $numbers['BUSINESS_IMPRESSIONS_MOBILE_MAPS'] + $numbers['BUSINESS_IMPRESSIONS_DESKTOP_MAPS'] : 0;
    $mobile = $numbers ? $numbers['BUSINESS_IMPRESSIONS_MOBILE_SEARCH'] + $numbers['BUSINESS_IMPRESSIONS_MOBILE_MAPS'] : 0;
    $views = $search + $maps;
    $average = $reviews->count() ? round($reviews->avg('rating'), 1) : null;
@endphp
<x-report-sheet title="Google report" kicker="Google Business Profile report" slug="google" :brands="$brands" :client="$client" :month="$month">
    @if (! $location)
        <p class="py-10 text-center text-gray-500">No Google Business location is linked to {{ $client->name }}.</p>
    @else
        <section class="space-y-3">
            <h3 class="text-sm font-bold uppercase tracking-[0.1em] text-gray-500">Google profile</h3>
            @if ($error)
                <p class="text-sm text-gray-600">Google did not give the profile numbers: {{ Str::limit($error, 200) }}
                    @if (auth()->user()->isAdmin())<br><span class="text-xs">If it says the API is not enabled: Google Cloud project → enable "Business Profile Performance API".</span>@endif
                </p>
            @elseif ($numbers)
                <div class="grid grid-cols-4 gap-3">
                    <div class="rounded-xl bg-gray-900 text-white p-4">
                        <div class="text-3xl font-bold tabular-nums">{{ number_format($views) }}</div>
                        <div class="text-xs text-white/70 mt-1">Profile views</div>
                    </div>
                    @foreach (['CALL_CLICKS' => 'Calls', 'BUSINESS_DIRECTION_REQUESTS' => 'Direction requests', 'WEBSITE_CLICKS' => 'Website clicks'] as $key => $label)
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ number_format($numbers[$key]) }}</div>
                            <div class="text-xs text-gray-600 mt-1">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
                @if ($views)
                    <div class="space-y-2 text-sm">
                        @foreach (['Google Search' => $search, 'Google Maps' => $maps, 'On phones' => $mobile] as $label => $count)
                            <div class="flex items-center gap-3">
                                <span class="w-32">{{ $label }}</span>
                                <span class="flex-1 h-2.5 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full bg-brand-600" style="width: {{ $count / $views * 100 }}%"></span></span>
                                <span class="w-16 text-right tabular-nums font-semibold">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
                <p class="text-xs text-gray-500">Google adds these numbers 2–3 days late, so the last days of the month may still grow.</p>
            @else
                <p class="text-sm text-gray-500">This month has not started yet.</p>
            @endif
        </section>

        <section class="space-y-3">
            <h3 class="text-sm font-bold uppercase tracking-[0.1em] text-gray-500">Reviews this month</h3>
            <div class="grid grid-cols-4 gap-3">
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $reviews->count() }}</div>
                    <div class="text-xs text-gray-600 mt-1">New reviews</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $average ?? '—' }}</div>
                    <div class="text-xs text-gray-600 mt-1">Average stars</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $reviews->where('status', 'replied')->count() }}</div>
                    <div class="text-xs text-gray-600 mt-1">Replied</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4">
                    <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ $posts }}</div>
                    <div class="text-xs text-gray-600 mt-1">Google posts</div>
                </div>
            </div>
            @if ($reviews->isNotEmpty())
                <div class="space-y-2 text-sm">
                    @foreach ($stars as $star => $count)
                        <div class="flex items-center gap-3">
                            <span class="w-32">{{ $star }} star{{ $star > 1 ? 's' : '' }}</span>
                            <span class="flex-1 h-2.5 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full bg-accent-500" style="width: {{ $count / max(1, $stars->max()) * 100 }}%"></span></span>
                            <span class="w-16 text-right tabular-nums font-semibold">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</x-report-sheet>
