<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Comments</h2>
            <div class="flex gap-1 text-sm">
                <a href="{{ route('meta.comments') }}" class="px-3 py-1.5 rounded-xl {{ $open ? 'bg-brand-600 text-white' : 'border border-gray-300 text-gray-700' }}">To reply</a>
                <a href="{{ route('meta.comments', ['show' => 'all']) }}" class="px-3 py-1.5 rounded-xl {{ $open ? 'border border-gray-300 text-gray-700' : 'bg-brand-600 text-white' }}">All</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="card divide-y">
                @forelse ($comments as $comment)
                    <div class="p-4 space-y-2">
                        <div class="text-xs text-gray-500">
                            {{ $clients[$comment->client_id] ?? $comment->metaPost->page->name }} · {{ ucfirst($comment->platform) }}
                            · <a href="{{ route('contents.show', $comment->metaPost->content_id) }}" class="text-brand-600 hover:underline">post</a>
                            · {{ $comment->commented_at?->diffForHumans() }}
                        </div>
                        <div><span class="font-semibold">{{ $comment->author ?? 'Someone' }}</span> {{ $comment->message }}</div>
                        @if ($comment->replied_at)
                            <div class="ml-4 pl-3 border-l-2 border-brand-200 text-sm text-gray-700">
                                {{ $comment->reply }}
                                <div class="text-xs text-gray-500">{{ $comment->replier?->name }} · {{ $comment->replied_at->diffForHumans() }}</div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('meta.comments.reply', $comment) }}" class="flex gap-2">
                                @csrf
                                <input name="reply" required maxlength="2000" placeholder="Reply as the page…" class="flex-1 border-gray-300 rounded-xl shadow-sm text-sm">
                                <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold">Reply</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">{{ $open ? 'All comments answered.' : 'No comments yet.' }} New comments come in every 10 minutes.</div>
                @endforelse
            </div>

            {{ $comments->links() }}
        </div>
    </div>
</x-app-layout>
