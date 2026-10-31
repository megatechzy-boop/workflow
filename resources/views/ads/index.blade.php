<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Ads</h2>
            <a href="{{ route('ads.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">+ New ad</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <div class="card divide-y">
                @forelse ($ads as $ad)
                    <div class="p-4 flex flex-wrap items-start gap-3">
                        <div class="flex-1 min-w-[14rem]">
                            <div class="font-semibold">{{ $ad->name }}</div>
                            <div class="text-xs text-gray-500">{{ $ad->client->name }} · {{ App\Models\MetaAd::GOALS[$ad->goal] }} · ₹{{ number_format($ad->daily_budget) }}/day · {{ $ad->start_on->format('d M') }}–{{ $ad->end_on->format('d M') }}</div>
                            <div class="text-xs text-gray-500">{{ $ad->audienceLabel() }}</div>
                            @if ($ad->error)<div class="text-xs text-red-600 mt-1">{{ $ad->error }}</div>@endif
                            @if ($ad->isAbTest())
                                @php($versions = $ab[$ad->id] ?? collect())
                                @php($cost = fn ($s) => ($s['results'] ?? 0) > 0 ? $s['spend'] / $s['results'] : null)
                                {{-- Winner: lower cost per result once both versions have results; "leading" until the end date --}}
                                @php($winner = $versions->filter(fn ($s) => $cost($s) !== null)->count() === 2 ? $versions->sortBy(fn ($s) => $cost($s))->keys()->first() : null)
                                <div class="mt-2 grid grid-cols-2 gap-2 max-w-md text-xs">
                                    @foreach (['A', 'B'] as $v)
                                        @php($s = $versions[$v] ?? null)
                                        <div class="rounded-lg border p-2 {{ $winner === $v ? 'border-brand-600 bg-brand-50' : 'border-gray-200' }}">
                                            <div class="font-semibold">Version {{ $v }}
                                                @if ($winner === $v)<span class="text-brand-700">· {{ $ad->end_on->isPast() ? 'Winner' : 'Leading' }}</span>@endif
                                            </div>
                                            @if ($s && $s['spend'] !== null)
                                                ₹{{ number_format($s['spend']) }} · {{ number_format($s['results']) }} results
                                                · {{ $cost($s) !== null ? '₹'.number_format($cost($s), 2).' each' : '—' }}
                                            @else
                                                <span class="text-gray-500">No results yet</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs {{ ['active' => 'bg-green-100 text-green-800', 'paused' => 'bg-gray-100 text-gray-700', 'failed' => 'bg-red-100 text-red-700'][$ad->status] }}">{{ ucfirst($ad->status) }}</span>
                        @if ($ad->ad_id)
                            <form method="POST" action="{{ route('ads.status', $ad) }}" @if ($ad->status !== 'active') onsubmit="return confirm('Start this ad? The client\'s ad account will be charged.')" @endif>
                                @csrf
                                <input type="hidden" name="status" value="{{ $ad->status === 'active' ? 'paused' : 'active' }}">
                                <button class="px-3 py-1.5 rounded-xl text-xs font-semibold {{ $ad->status === 'active' ? 'border border-gray-300 text-gray-700' : 'bg-brand-600 text-white' }}">{{ $ad->status === 'active' ? 'Pause' : 'Start' }}</button>
                            </form>
                            <a href="{{ $ad->adsManagerUrl() }}" target="_blank" rel="noopener" class="text-xs text-brand-600 hover:underline self-center">Ads Manager</a>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No ads yet.</div>
                @endforelse
            </div>

            {{ $ads->links() }}
        </div>
    </div>
</x-app-layout>
