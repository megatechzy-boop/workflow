<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-2xl text-gray-900 tracking-tight">WhatsApp</h2>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or number" aria-label="Search"
                    class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px] w-44">
                <select name="show" onchange="this.form.submit()" class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]" aria-label="Show">
                    <option value="">All chats</option>
                    <option value="unread" @selected(($filters['show'] ?? '') === 'unread')>Unread</option>
                    <option value="open" @selected(($filters['show'] ?? '') === 'open')>24h window open</option>
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif
            @if (! $ready)
                <div class="p-3 rounded bg-amber-50 text-amber-800 text-sm">WhatsApp is not connected yet (Settings &gt; WhatsApp).</div>
            @else
                <details class="card p-4" @if ($errors->any()) open @endif>
                    <summary class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold cursor-pointer min-h-[44px]">
                        <x-icon name="send" class="w-4 h-4" /> New WhatsApp
                    </summary>
                    <div class="mt-3 space-y-2">
                        <p class="text-xs text-gray-500">A first message (or one after 24 hours of silence) must be an approved template, e.g. details + PDF after a call.
                            Once the client replies, you can chat freely for 24 hours.</p>
                        @include('whatsapp._template-form', ['action' => route('whatsapp.new'), 'withNumber' => true])
                    </div>
                </details>
            @endif

            <div class="card divide-y">
                @forelse ($chats as $chat)
                    @php($last = $chat->lastMessage)
                    <a href="{{ route('whatsapp.show', $chat) }}" class="flex items-center gap-3 p-4 hover:bg-gray-50 min-h-[64px]">
                        <div class="w-10 h-10 rounded-full bg-brand-50 text-brand-700 flex items-center justify-center font-semibold shrink-0">
                            {{ mb_strtoupper(mb_substr($chat->name ?: '#', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-gray-900 truncate">{{ $chat->title() }}</span>
                                <span class="text-xs text-gray-500 shrink-0">{{ $chat->last_message_at?->isToday() ? $chat->last_message_at->format('h:i A') : $chat->last_message_at?->format('d M') }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm text-gray-600 truncate">
                                    @if ($last && $last->direction === 'out')
                                        <span class="text-gray-400">{{ $last->source === 'phone' ? 'Phone' : ($last->user?->name ?? 'Team') }}:</span>
                                    @endif
                                    {{ $last ? ($last->body ?: ($last->file_name ?: ucfirst($last->type))) : '' }}
                                </span>
                                <span class="flex items-center gap-1 shrink-0">
                                    @if ($chat->windowEndsAt())
                                        <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-green-50 text-green-700">24h open</span>
                                    @endif
                                    @if ($chat->unread)
                                        <span class="text-[11px] min-w-[20px] text-center px-1.5 py-0.5 rounded-full bg-brand-600 text-white">{{ $chat->unread }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-6 text-center text-gray-500">No chats yet. Messages to the company number show up here.</div>
                @endforelse
            </div>

            {{ $chats->links() }}
        </div>
    </div>
</x-app-layout>
