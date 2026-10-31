<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Facebook &amp; Instagram</h2>
            @if ($ready)
                <a href="{{ route('admin.meta.connect') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">{{ $pages->isEmpty() ? 'Connect Facebook' : 'Reconnect / add Pages' }}</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            @unless ($ready)
                <div class="card p-4 text-sm text-gray-700">Put <code>META_APP_ID</code> and <code>META_APP_SECRET</code> in the server <code>.env</code> first.</div>
            @endunless

            <p class="text-sm text-gray-600">
                Log in with the Facebook account that manages the clients' Pages. Every Page you allow comes in here with its Instagram business account.
                Then open each client (Clients &gt; Edit) and pick its Page. Approved posts get a "Publish to Facebook / Instagram" button.
            </p>

            <div class="card divide-y">
                @forelse ($pages as $page)
                    <div class="px-4 py-3 flex items-center justify-between gap-3 text-sm">
                        <div class="font-medium">{{ $page->name }}</div>
                        <div class="text-gray-500">{{ $page->ig_username ? 'Instagram @'.$page->ig_username : 'No Instagram linked' }}</div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No Pages connected yet.</div>
                @endforelse
            </div>

            <h3 class="font-semibold text-gray-900 pt-2">Ad accounts (for boosting posts)</h3>
            <div class="card divide-y">
                @forelse ($adAccounts as $adAccount)
                    <div class="px-4 py-3 flex items-center justify-between gap-3 text-sm">
                        <div class="font-medium">{{ $adAccount->name }}</div>
                        <div class="text-gray-500">{{ $adAccount->account_id }} · {{ $adAccount->currency }}</div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No ad accounts yet. Reconnect Facebook and allow ads access; clients share their ad account with Mega Techzy as a partner.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
