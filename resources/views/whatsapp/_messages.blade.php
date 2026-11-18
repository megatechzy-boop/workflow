@forelse ($messages as $m)
    @php($out = $m->direction === 'out')
    <div class="flex {{ $out ? 'justify-end' : 'justify-start' }}">
        <div class="max-w-[85%] sm:max-w-[70%] rounded-2xl px-3 py-2 text-sm shadow-sm {{ $out ? 'bg-brand-50 text-gray-900 rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm border border-gray-100' }}">
            @if ($out)
                <div class="text-[11px] font-semibold {{ $m->source === 'phone' ? 'text-gray-500' : 'text-brand-700' }}">{{ $m->senderLabel() }}</div>
            @endif
            @if ($m->hasFile())
                @if ($m->type === 'image' || $m->type === 'sticker')
                    <a href="{{ route('whatsapp.media', $m) }}" target="_blank" rel="noopener">
                        <img src="{{ route('whatsapp.media', $m) }}" alt="Photo" loading="lazy" class="rounded-xl max-h-64 my-1">
                    </a>
                @else
                    <a href="{{ route('whatsapp.media', [$m, 'download' => 1]) }}" class="flex items-center gap-2 my-1 px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 min-h-[44px]">
                        <x-icon name="paperclip" class="w-4 h-4 shrink-0" />
                        <span class="truncate">{{ $m->file_name ?: ucfirst($m->type) }}</span>
                    </a>
                @endif
            @endif
            @if ($m->body !== null && $m->body !== '')
                <p class="whitespace-pre-line break-words">{{ $m->body }}</p>
            @endif
            <div class="flex items-center justify-end gap-1 text-[11px] text-gray-500 mt-0.5">
                @if ($m->type === 'template')<span>Template ·</span>@endif
                <span>{{ $m->sent_at->isToday() ? $m->sent_at->format('h:i A') : $m->sent_at->format('d M, h:i A') }}</span>
                @if ($out && $m->source === 'app')
                    <span class="{{ $m->status === 'read' ? 'text-sky-600' : '' }} {{ $m->status === 'failed' ? 'text-red-600' : '' }}">
                        {{ ['sent' => '✓', 'delivered' => '✓✓', 'read' => '✓✓', 'failed' => 'Failed'][$m->status] ?? '' }}
                    </span>
                @endif
            </div>
            @if ($m->status === 'failed' && $m->error)
                <p class="text-xs text-red-700">{{ $m->error }}</p>
            @endif
        </div>
    </div>
@empty
    <p class="text-center text-sm text-gray-500 py-6">No messages yet.</p>
@endforelse
