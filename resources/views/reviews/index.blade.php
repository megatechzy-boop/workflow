<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Google reviews</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Client">
                    <option value="">All clients</option>
                    @foreach ($clients as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['client'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="stars" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Stars">
                    <option value="">All stars</option>
                    @foreach ([5, 4, 3, 2, 1] as $stars)
                        <option value="{{ $stars }}" @selected(($filters['stars'] ?? null) == $stars)>{{ $stars }} star{{ $stars > 1 ? 's' : '' }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Status">
                    <option value="open" @selected($filters['status'] === 'open')>Not replied yet</option>
                    @foreach (\App\Models\GbpReview::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button class="px-3 py-2 border rounded-xl">Filter</button></noscript>
            </form>

            <div class="space-y-3">
                @forelse ($reviews as $review)
                    <div class="card p-4 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-0.5" aria-label="{{ $review->rating }} stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-icon name="star" class="w-5 h-5 {{ $i <= $review->rating ? 'text-amber-400 fill-amber-400' : 'text-gray-300' }}" />
                                @endfor
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full {{ match ($review->status) {
                                'replied' => 'bg-green-50 text-green-700', 'draft' => 'bg-amber-50 text-amber-700',
                                'scheduled' => 'bg-blue-50 text-blue-700', default => 'bg-gray-100 text-gray-700' } }}">{{ \App\Models\GbpReview::STATUSES[$review->status] }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $review->client->name }} · {{ $review->reviewer_name ?? 'A Google user' }} · {{ $review->reviewed_at?->format('d M Y') }}
                        </div>
                        @if ($review->comment)
                            <p class="text-sm text-gray-800 whitespace-pre-line break-words">{{ $review->comment }}</p>
                        @else
                            <p class="text-sm text-gray-400 italic">Rating only, no text.</p>
                        @endif

                        @if ($review->status === 'replied')
                            <div class="ml-2 pl-3 border-l-2 border-brand-200 text-sm text-gray-700 whitespace-pre-line break-words">
                                {{ $review->reply }}
                                @php($log = $review->logs->last())
                                <div class="text-xs text-gray-500">
                                    {{ $log ? ($log->approver?->name ?? 'Auto reply') : 'Replied on Google' }} · {{ $review->replied_at?->diffForHumans() }}
                                </div>
                            </div>
                        @else
                            @if ($review->status === 'scheduled')
                                <p class="text-xs text-blue-700">Auto reply goes out {{ $review->reply_due_at?->format('h:i A') }}. Send now to change it.</p>
                            @elseif ($review->status === 'draft')
                                <p class="text-xs text-amber-700">3 stars: check the draft, edit if needed, then approve.</p>
                            @elseif ($review->rating <= 2)
                                <p class="text-xs text-red-700">Low rating: reply personally. No auto reply.</p>
                            @endif
                            @if ($review->error)
                                <p class="text-xs text-red-700">Auto reply failed: {{ $review->error }}</p>
                            @endif
                            @if ($review->client->is_healthcare)
                                <p class="text-xs text-gray-500">Healthcare client: no patient name, condition or treatment details in the reply.</p>
                            @endif
                            <details @if ($review->draft) open @endif>
                                <summary class="inline-flex items-center px-4 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 cursor-pointer min-h-[44px]">Reply</summary>
                                <form method="POST" action="{{ route('reviews.reply', $review) }}" class="mt-2 space-y-2">
                                    @csrf
                                    <textarea name="reply" rows="4" required maxlength="4000" class="block w-full border-gray-300 rounded-xl shadow-sm text-sm" aria-label="Reply">{{ $review->draft }}</textarea>
                                    <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold min-h-[44px]">{{ $review->status === 'draft' ? 'Approve & post' : 'Post reply' }}</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <div class="card px-4 py-6 text-center text-gray-500">No reviews here. New reviews come in every 15 minutes.</div>
                @endforelse
            </div>

            {{ $reviews->links() }}
        </div>
    </div>
</x-app-layout>
