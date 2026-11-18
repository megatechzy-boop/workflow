<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('whatsapp.index') }}" class="p-2 -ml-2 rounded-xl hover:bg-gray-100 min-h-[44px] min-w-[44px] flex items-center justify-center" aria-label="Back">
                <x-icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div class="flex-1 min-w-0">
                <h2 class="font-bold text-xl text-gray-900 truncate">{{ $chat->title() }}</h2>
                <p class="text-xs text-gray-500">+{{ $chat->wa_id }}</p>
            </div>
            {{-- free replies from a browser where the company WhatsApp is scanned (up to 4 linked devices) --}}
            <a href="https://web.whatsapp.com/send?phone={{ $chat->wa_id }}" target="whatsapp-web" rel="noopener"
                class="px-3 py-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 min-h-[44px] flex items-center shrink-0">WhatsApp Web</a>
        </div>
    </x-slot>

    @php($window = $chat->windowEndsAt())
    <div class="py-4" x-data="waChat(@js(route('whatsapp.poll', $chat)), @js($window?->toIso8601String()))">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-3">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <div x-ref="list" class="space-y-2 bg-gray-50 rounded-2xl p-3 h-[60vh] overflow-y-auto">
                @include('whatsapp._messages', ['messages' => $messages])
            </div>

            <div x-show="open" @if (! $window) style="display: none" @endif class="space-y-2">
                <p class="text-xs text-green-700" x-text="'24-hour window open: free replies until ' + endsText"></p>
                <form method="POST" action="{{ route('whatsapp.send', $chat) }}" enctype="multipart/form-data" class="card p-3 space-y-2">
                    @csrf
                    <textarea name="text" rows="2" maxlength="4000" placeholder="Type a message (with a file it is the caption)" aria-label="Message"
                        class="block w-full border-gray-300 rounded-xl shadow-sm text-sm">{{ old('text') }}</textarea>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-300 text-sm text-gray-700 cursor-pointer min-h-[44px] max-w-[60%]">
                            <x-icon name="paperclip" class="w-4 h-4 shrink-0" />
                            <span class="truncate" x-text="file || 'PDF / photo'"></span>
                            <input type="file" name="file" class="hidden" accept=".pdf,image/*,.doc,.docx,.xls,.xlsx" @change="file = $event.target.files[0]?.name">
                        </label>
                        <button class="ml-auto px-5 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold min-h-[44px]" onclick="this.textContent = 'Sending…'">Send</button>
                    </div>
                </form>
            </div>

            <div x-show="! open" @if ($window) style="display: none" @endif class="card p-4 space-y-2">
                <p class="text-sm text-gray-700">24-hour window closed: no message from {{ $chat->title() }} in the last 24 hours.
                    Send an approved template here, or reply free from the WhatsApp app / Web.</p>
                @include('whatsapp._template-form', ['action' => route('whatsapp.template', $chat), 'withNumber' => false])
            </div>
        </div>
    </div>

    <script>
        function waChat(pollUrl, windowEnds) {
            return {
                sig: null, file: null, ends: windowEnds ? new Date(windowEnds) : null, now: new Date(),
                get open() { return !! this.ends && this.ends > this.now; },
                get endsText() {
                    if (! this.ends) return '';
                    const time = this.ends.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    return this.ends.toDateString() === this.now.toDateString() ? time : time + ' tomorrow';
                },
                init() {
                    const list = this.$refs.list;
                    list.scrollTop = list.scrollHeight;
                    setInterval(() => this.poll(), 8000);
                    setInterval(() => { this.now = new Date(); }, 30000);
                },
                async poll() {
                    if (document.hidden) return;
                    const url = pollUrl + (this.sig ? '?sig=' + encodeURIComponent(this.sig) : '');
                    const res = await fetch(url, { headers: { Accept: 'application/json' } }).catch(() => null);
                    if (! res || ! res.ok) return;
                    const data = await res.json();
                    if (this.sig !== null && data.html !== undefined) {
                        const list = this.$refs.list;
                        const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
                        list.innerHTML = data.html;
                        if (atBottom) list.scrollTop = list.scrollHeight;
                    }
                    if (data.window !== undefined) this.ends = data.window ? new Date(data.window) : null;
                    this.sig = data.sig;
                },
            };
        }
    </script>
</x-app-layout>
