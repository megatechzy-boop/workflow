<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Google Business</h2>
            @if ($ready)
                <div class="flex flex-wrap gap-2">
                    @if ($account)
                        <form method="POST" action="{{ route('admin.google.refresh') }}">
                            @csrf
                            <button class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 min-h-[44px]">Refresh locations</button>
                        </form>
                    @endif
                    <a href="{{ route('admin.google.connect') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">{{ $account ? 'Reconnect Google' : 'Connect Google' }}</a>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-800 text-sm">{{ $errors->first() }}</div>
            @endif

            @unless ($ready)
                <div class="card p-4 text-sm text-gray-700">Put <code>GOOGLE_CLIENT_ID</code> and <code>GOOGLE_CLIENT_SECRET</code> in the server <code>.env</code> first.</div>
            @endunless

            <p class="text-sm text-gray-600">
                Log in with the Google account that manages the clients' Business Profiles. Every location comes in here;
                pick the client for each one. Reviews and GBP posts use this link.
                @if ($account)
                    <span class="block mt-1 text-gray-500">Connected {{ $account->created_at->format('d M Y') }}.</span>
                @endif
            </p>

            <div class="card divide-y">
                @forelse ($locations as $location)
                    <form method="POST" action="{{ route('admin.google.link', $location) }}" class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 text-sm">
                        @csrf
                        @method('PUT')
                        <div class="flex-1 min-w-0">
                            <div class="font-medium truncate">{{ $location->title }}</div>
                            <div class="text-gray-500 truncate">{{ collect([$location->city, $location->phone])->filter()->join(' · ') ?: $location->location_name }}</div>
                        </div>
                        <select name="client_id" onchange="this.form.submit()" class="w-full sm:w-56 border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Client for {{ $location->title }}">
                            <option value="">Not linked</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected($location->client_id === $client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                        <noscript><button class="px-3 py-2 border rounded-xl">Save</button></noscript>
                    </form>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No locations yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
