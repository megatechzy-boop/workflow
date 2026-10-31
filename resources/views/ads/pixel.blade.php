<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Pixel</h2>
            @if ($clients->count() > 1)
                <form method="GET">
                    <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm" aria-label="Client">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected($client && $c->id === $client->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($error)
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">Meta said: {{ $error }}</div>
            @endif

            @if (! $client)
                <div class="card p-6 text-sm text-gray-700">No client is ready for ads yet. In Clients &gt; Edit, pick a Facebook Page and an ad account.</div>
            @elseif (! $connected)
                <div class="card p-6 text-sm text-gray-700">Facebook is not connected yet (Admin &gt; Facebook / IG).</div>
            @elseif (! $client->meta_pixel_id)
                <form method="POST" action="{{ route('ads.pixel.store', $client) }}" class="card p-5 space-y-4">
                    @csrf
                    <h3 class="font-semibold text-gray-900">Set up {{ $client->name }}'s pixel</h3>
                    <p class="text-sm text-gray-600">The pixel tells Meta who visited the client's website and what they did (enquiry, booking, purchase). Ads can then reach those visitors again.</p>
                    @if ($pixels)
                        <div>
                            <x-input-label for="pixel_id" value="Use a pixel already on the ad account" />
                            <select id="pixel_id" name="pixel_id" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                                <option value="">— Make a new pixel —</option>
                                @foreach ($pixels as $p)
                                    <option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['id'] }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <x-input-label for="name" value="New pixel name" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="$client->name.' website'" />
                    </div>
                    <div class="flex justify-end"><button class="px-5 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold">Save pixel</button></div>
                </form>
            @else
                <section class="card p-5 space-y-3" x-data="{ copied: false }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-gray-900">Website code · pixel {{ $client->meta_pixel_id }}</h3>
                        <button type="button" class="px-3 py-1.5 rounded-xl border border-gray-300 text-xs font-semibold" @click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 2000)" x-text="copied ? 'Copied' : 'Copy code'">Copy code</button>
                    </div>
                    <p class="text-sm text-gray-600">Paste this inside <code>&lt;head&gt;</code> on every page of the client's website (WordPress: a header-code plugin).</p>
                    <pre x-ref="code" class="text-xs bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto">{{ $code }}</pre>
                    <p class="text-sm text-gray-600">On the thank-you / success page also add one event, for example:
                        <code class="text-xs">&lt;script&gt;fbq('track', 'Lead');&lt;/script&gt;</code>
                        (or <code class="text-xs">'Contact'</code>, <code class="text-xs">'Schedule'</code>, <code class="text-xs">'Purchase', {value: 999, currency: 'INR'}</code>). No website change? Use a conversion below.</p>
                </section>

                <section class="card p-5 space-y-3">
                    <h3 class="font-semibold text-gray-900">Last 7 days</h3>
                    @forelse ($stats as $event => $count)
                        <div class="flex justify-between text-sm border-b border-gray-100 py-1.5"><span>{{ $event }}</span><span class="font-semibold tabular-nums">{{ number_format($count) }}</span></div>
                    @empty
                        <p class="text-sm text-gray-500">No events yet. After the code is on the website, visits show here within an hour.</p>
                    @endforelse
                </section>

                <section class="card p-5 space-y-4">
                    <h3 class="font-semibold text-gray-900">Conversions</h3>
                    @foreach ($conversions as $c)
                        <div class="text-sm flex justify-between border-b border-gray-100 py-1.5">
                            <span>{{ $c['name'] }}</span>
                            <span class="text-gray-500">{{ App\Http\Controllers\PixelController::EVENTS[$c['custom_event_type'] ?? 'OTHER'] ?? $c['custom_event_type'] }}</span>
                        </div>
                    @endforeach
                    <form method="POST" action="{{ route('ads.pixel.conversion', $client) }}" class="grid sm:grid-cols-4 gap-3 items-end">
                        @csrf
                        <div>
                            <x-input-label for="c_name" value="Name" />
                            <x-text-input id="c_name" name="name" class="mt-1 block w-full" placeholder="Enquiry sent" required />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="url_contains" value="When the page link contains" />
                            <x-text-input id="url_contains" name="url_contains" class="mt-1 block w-full" placeholder="thank-you" required />
                        </div>
                        <div>
                            <x-input-label for="event" value="Count it as" />
                            <select id="event" name="event" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                                @foreach (App\Http\Controllers\PixelController::EVENTS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-4 flex justify-end"><button class="px-5 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-semibold">Add conversion</button></div>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
