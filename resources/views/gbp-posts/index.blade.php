<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">GBP Posts</h2>
            <a href="{{ route('gbp-posts.create', array_filter(['client' => $filters['client'] ?? null])) }}" class="inline-flex items-center px-4 py-2 bg-brand-600 rounded-xl text-sm font-semibold text-white hover:bg-brand-700 min-h-[44px]">New post</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Client">
                    <option value="">All clients</option>
                    @foreach ($clients as $id => $name)
                        <option value="{{ $id }}" @selected(($filters['client'] ?? null) == $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\GbpPost::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="month" name="month" value="{{ $filters['month'] ?? '' }}" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Month">
                <noscript><button class="px-3 py-2 border rounded-xl">Filter</button></noscript>
            </form>

            <div class="space-y-3">
                @forelse ($posts as $post)
                    <div class="card p-3 flex gap-3">
                        <img src="{{ route('gbp-posts.image', $post) }}" alt="" loading="lazy" class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl object-cover shrink-0 bg-gray-100">
                        <div class="flex-1 min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="font-semibold text-gray-900">{{ $post->client->name }}</span>
                                <span class="text-gray-500">{{ \App\Models\GbpPost::TYPES[$post->post_type] }}</span>
                                <span class="px-2 py-0.5 rounded-full {{ match ($post->status) {
                                    'published' => 'bg-green-50 text-green-700', 'failed' => 'bg-red-50 text-red-700',
                                    'publishing' => 'bg-blue-50 text-blue-700', default => 'bg-gray-100 text-gray-700' } }}">{{ \App\Models\GbpPost::STATUSES[$post->status] }}</span>
                            </div>
                            <p class="text-sm text-gray-800 line-clamp-2 break-words">{{ $post->caption }}</p>
                            <div class="text-xs text-gray-500">
                                @if ($post->published_at)
                                    Published {{ $post->published_at->format('d M Y, h:i A') }}
                                @else
                                    {{ $post->scheduled_at->format('d M Y, h:i A') }}
                                    @if ($post->status === 'scheduled' && $post->attempts) · try {{ $post->attempts + 1 }} of {{ \App\Support\GbpPublisher::MAX_ATTEMPTS }} at {{ $post->retry_at?->format('h:i A') }} @endif
                                @endif
                            </div>
                            @if ($post->error && $post->status !== 'published')
                                <p class="text-xs text-red-700 break-words">Google said: {{ $post->error }}</p>
                            @endif
                            <div class="flex flex-wrap gap-2 pt-1">
                                @if ($post->search_url)
                                    <a href="{{ $post->search_url }}" target="_blank" rel="noopener" class="text-sm text-brand-600 hover:underline">View on Google</a>
                                @endif
                                @if ($post->editable())
                                    <a href="{{ route('gbp-posts.edit', $post) }}" class="text-sm text-brand-600 hover:underline">{{ $post->status === 'failed' ? 'Fix & retry' : 'Edit' }}</a>
                                    <form method="POST" action="{{ route('gbp-posts.destroy', $post) }}" onsubmit="return confirm('Delete this post?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm text-red-600 hover:underline">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card px-4 py-6 text-center text-gray-500">No GBP posts yet.</div>
                @endforelse
            </div>

            {{ $posts->links() }}
        </div>
    </div>
</x-app-layout>
