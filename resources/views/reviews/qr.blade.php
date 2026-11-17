<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Review QR</h2>
            <div class="flex flex-wrap items-center gap-2">
                @if ($clients->count() > 1)
                    <form method="GET">
                        <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Brand">
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}" @selected($c->id === $client->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                @if ($location->review_url)
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold disabled:opacity-60"
                        onclick="this.disabled = true; downloadDocPdf(document.getElementById('qr-sheet'), @js(Str::slug($client->name).'-review-qr.pdf')).finally(() => this.disabled = false)">
                        <x-icon name="download" class="w-4 h-4" /> Download PDF
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto px-4 sm:px-6 space-y-4">
            @if (! $location->review_url)
                <div class="card p-4 text-sm text-gray-700">
                    Google has not given the review link for {{ $client->name }} yet.
                    @if (auth()->user()->isAdmin())
                        Open <a href="{{ route('admin.google.index') }}" class="text-brand-600 hover:underline">Admin &gt; Google Business</a> and press "Refresh locations".
                    @else
                        Ask the Admin to press "Refresh locations" in Admin &gt; Google Business.
                    @endif
                </div>
            @else
                {{-- Printed and kept on the counter: brand, short line, big QR, the link as text --}}
                <div id="qr-sheet" class="paper rounded-2xl border border-gray-200 shadow-sm overflow-hidden text-center">
                    <div class="bg-brand-600 text-white px-6 py-5">
                        <div class="text-2xl font-bold">{{ $client->name }}</div>
                        @if ($location->city)<div class="text-sm text-white/80">{{ $location->city }}</div>@endif
                    </div>
                    <div class="px-6 py-6 space-y-4 bg-white">
                        <div class="flex justify-center gap-1 text-accent-500" aria-hidden="true">
                            @for ($i = 0; $i < 5; $i++)<x-icon name="star" class="w-7 h-7 fill-current" />@endfor
                        </div>
                        <div class="text-xl font-bold text-gray-900">Happy with us? Review us on Google</div>
                        <div class="text-sm text-gray-600">Scan the code with your phone camera. It takes less than a minute.</div>
                        <img id="review-qr" alt="QR code for the Google review link" class="mx-auto w-64 h-64">
                        <div class="text-xs text-gray-500 break-all">{{ $location->review_url }}</div>
                    </div>
                </div>
                <script>document.addEventListener('DOMContentLoaded', () => renderQr(document.getElementById('review-qr'), @js($location->review_url), 600));</script>

                <div class="card p-4 space-y-2 text-sm">
                    <div class="font-semibold text-gray-900">Share the link</div>
                    <p class="text-gray-600">Send it on WhatsApp after a visit, or put it on the website.</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="https://wa.me/?text={{ rawurlencode('Thank you for visiting '.$client->name.'! Please share your experience on Google: '.$location->review_url) }}"
                            target="_blank" rel="noopener" class="px-3 min-h-[44px] inline-flex items-center rounded-xl border text-sm">WhatsApp message</a>
                        <button type="button" class="px-3 min-h-[44px] rounded-xl border text-sm"
                            onclick="navigator.clipboard.writeText(@js($location->review_url)).then(() => { this.textContent = 'Copied' })">Copy link</button>
                        <a href="{{ $location->review_url }}" target="_blank" rel="noopener" class="px-3 min-h-[44px] inline-flex items-center rounded-xl border text-sm">Open link</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
