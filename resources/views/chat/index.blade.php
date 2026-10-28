<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">💬 Chat</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg grid md:grid-cols-[280px_1fr] min-h-[70vh]">
                {{-- Groups --}}
                <nav class="border-b md:border-b-0 md:border-r overflow-y-auto max-h-[30vh] md:max-h-[75vh]" aria-label="Chat groups">
                    @foreach ($rooms as $key => $label)
                        <a href="{{ route('chat.index', ['room' => $key]) }}"
                           class="flex items-center justify-between gap-2 px-4 py-3 text-sm border-b {{ $key === $room ? 'bg-indigo-50 font-semibold text-indigo-800' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span class="truncate">{{ $label }}</span>
                            @if (($unread[$key] ?? 0) && $key !== $room)
                                <span class="min-w-[20px] px-1.5 rounded-full bg-red-600 text-white text-xs text-center">{{ $unread[$key] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                {{-- Messages --}}
                <section class="flex flex-col min-h-[60vh] md:max-h-[75vh]">
                    <div class="px-4 py-3 border-b font-semibold text-gray-800">{{ $rooms[$room] }}</div>
                    <div id="chat-list" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50" aria-live="polite"></div>

                    <form id="chat-form" class="border-t p-3 flex flex-wrap items-end gap-2">
                        <textarea name="body" id="chat-body" rows="2" placeholder="Type a message… (@name to mention, Enter = send, Shift+Enter = new line)"
                            class="flex-1 min-w-[200px] border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                        <label class="cursor-pointer px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-700 hover:bg-gray-50" title="Photo / file attach">
                            📎 <span id="chat-file-name">File</span>
                            <input type="file" name="file" id="chat-file" class="sr-only"
                                accept="image/*,video/mp4,video/quicktime,application/pdf,.docx,.xlsx,.pptx">
                        </label>
                        <x-primary-button id="chat-send">Send</x-primary-button>
                        <p id="chat-error" class="w-full text-sm text-red-600 hidden"></p>
                    </form>
                </section>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const pollUrl = @js(route('chat.poll', ['room' => $room]));
            const sendUrl = @js(route('chat.store', ['room' => $room]));
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const list = document.getElementById('chat-list');
            const form = document.getElementById('chat-form');
            const body = document.getElementById('chat-body');
            const file = document.getElementById('chat-file');
            const err = document.getElementById('chat-error');
            let lastId = 0;

            // User text always goes through textContent (never HTML)
            const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };

            function render(m) {
                if (m.id <= lastId) return;
                lastId = m.id;
                const row = el('div', 'flex ' + (m.mine ? 'justify-end' : 'justify-start'));
                const bubble = el('div', 'max-w-[80%] rounded-lg px-3 py-2 text-sm shadow-sm ' + (m.mine ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800'));
                bubble.append(el('div', 'text-xs ' + (m.mine ? 'text-indigo-100' : 'text-gray-500'), m.user + ' · ' + m.role + ' · ' + m.time));
                if (m.body) bubble.append(el('div', 'whitespace-pre-line break-words mt-0.5', m.body));
                if (m.file) {
                    if (m.file.image) {
                        const a = el('a'); a.href = m.file.url; a.target = '_blank'; a.rel = 'noopener';
                        const img = el('img', 'mt-2 rounded max-h-60'); img.src = m.file.url; img.alt = m.file.name; img.loading = 'lazy';
                        a.append(img); bubble.append(a);
                    }
                    const dl = el('a', 'block mt-1 underline text-xs', '📎 ' + m.file.name); dl.href = m.file.download;
                    bubble.append(dl);
                }
                row.append(bubble);
                list.append(row);
            }

            const atBottom = () => list.scrollHeight - list.scrollTop - list.clientHeight < 80;
            const toBottom = () => { list.scrollTop = list.scrollHeight; };

            (@js($messages)).forEach(render);
            toBottom();

            async function poll() {
                if (document.hidden) return;
                try {
                    const stick = atBottom();
                    const res = await fetch(pollUrl + '?after=' + lastId, { headers: { Accept: 'application/json' } });
                    if (res.ok) { (await res.json()).forEach(render); if (stick) toBottom(); }
                } catch (e) { /* network hiccup: retry next tick */ }
            }
            setInterval(poll, 5000);

            file.addEventListener('change', () => {
                document.getElementById('chat-file-name').textContent = file.files[0] ? file.files[0].name : 'File';
            });
            body.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                err.classList.add('hidden');
                if (!body.value.trim() && !file.files.length) return;
                const btn = document.getElementById('chat-send');
                btn.disabled = true;
                try {
                    const res = await fetch(sendUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        body: new FormData(form),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        err.textContent = Object.values(data.errors || {}).flat().join(' ') || data.message || 'Message not sent.';
                        err.classList.remove('hidden');
                        return;
                    }
                    render(data);
                    toBottom();
                    form.reset();
                    document.getElementById('chat-file-name').textContent = 'File';
                } catch (e2) {
                    err.textContent = 'Network problem, please try again.';
                    err.classList.remove('hidden');
                } finally {
                    btn.disabled = false;
                    body.focus();
                }
            });
        })();
    </script>
</x-app-layout>
