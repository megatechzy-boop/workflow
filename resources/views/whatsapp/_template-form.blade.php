{{-- Approved template (+ PDF / photo when the template has one): first message, or after the 24-hour window --}}
@if (! $templates)
    <p class="text-sm text-gray-500">No approved templates yet. Make one in WhatsApp Manager (Message templates) and wait for Meta's approval.</p>
@else
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-2" x-data="{ key: @js(old('template', '')), list: @js($templates) }">
        @csrf
        @if ($withNumber)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <input name="number" value="{{ old('number') }}" required inputmode="tel" placeholder="Number, e.g. 98908 40101" aria-label="Number"
                    class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                <input name="name" value="{{ old('name') }}" placeholder="Name (optional)" aria-label="Name" maxlength="80"
                    class="border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
            </div>
        @endif
        <select name="template" x-model="key" required aria-label="Template" class="block w-full border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
            <option value="">Pick a template</option>
            @foreach ($templates as $t)
                <option value="{{ $t['key'] }}">{{ $t['name'] }} ({{ $t['language'] }}, {{ strtolower($t['category']) }})</option>
            @endforeach
        </select>
        <template x-for="t in list.filter(t => t.key === key)" :key="t.key">
            <div class="space-y-2">
                <p class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-xl p-3" x-text="t.body"></p>
                <template x-for="n in t.params" :key="n">
                    <input :name="'params[' + (n - 1) + ']'" required maxlength="500" :placeholder="'Blank ' + '{' + '{' + n + '}' + '}'"
                        class="block w-full border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                </template>
                <template x-if="t.header === 'DOCUMENT' || t.header === 'IMAGE'">
                    <label class="block text-sm text-gray-700">
                        <span x-text="t.header === 'DOCUMENT' ? 'PDF to send' : 'Photo to send'"></span>
                        <input type="file" name="file" required :accept="t.header === 'DOCUMENT' ? '.pdf' : 'image/*'" class="block w-full text-sm mt-1">
                    </label>
                </template>
                <p class="text-xs text-gray-500" x-show="t.category === 'MARKETING'">Marketing template: Meta charges about ₹0.86 + GST. Utility templates cost about ₹0.12.</p>
            </div>
        </template>
        <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold min-h-[44px]" onclick="if (this.form.checkValidity()) this.textContent = 'Sending…'">Send template</button>
    </form>
@endif
