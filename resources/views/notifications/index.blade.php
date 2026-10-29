<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Notifications</h2>
            <form method="POST" action="{{ route('notifications.readAll') }}">
                @csrf
                <button class="text-sm text-brand-600 hover:underline">Mark all as read</button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="card divide-y">
                @forelse ($notifications as $n)
                    <a href="{{ route('notifications.open', $n->id) }}" class="block px-4 py-3 hover:bg-gray-50 {{ $n->read_at ? '' : 'bg-brand-50/60' }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-gray-800">{{ $n->data['title'] ?? '' }}</span>
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $n->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-sm text-gray-600 mt-0.5">{{ Str::limit($n->data['message'] ?? '', 160) }}</div>
                    </a>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No notifications.</div>
                @endforelse
            </div>

            {{ $notifications->links() }}
        </div>
    </div>
</x-app-layout>
