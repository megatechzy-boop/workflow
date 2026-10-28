{{-- System / Light / Dark (saved on this device) --}}
<div x-data="{ t: window.themeGet() }" class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-gray-100" role="radiogroup" aria-label="Theme">
    @foreach (['system' => ['System', 'computer'], 'light' => ['Light', 'sun'], 'dark' => ['Dark', 'moon']] as $value => [$label, $icon])
        <button type="button" role="radio" :aria-checked="t === '{{ $value }}'" @click="t = '{{ $value }}'; themeSet(t)"
                :class="t === '{{ $value }}' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                class="flex items-center justify-center gap-1.5 py-1.5 rounded-lg text-xs font-medium">
            <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
        </button>
    @endforeach
</div>
