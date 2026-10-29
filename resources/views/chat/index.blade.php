<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight flex items-center gap-2"><x-icon name="chat" class="w-7 h-7 text-brand-600" /> Chat</h2>
    </x-slot>

    @php($roomIcon = fn ($key) => ['team' => 'users', 'client' => 'tag', 'internal' => 'lock'][strtok($key, ':')] ?? 'chat')

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="md:card md:grid md:grid-cols-[300px_1fr] md:h-[75vh] md:overflow-hidden">
                {{-- Groups (phone: its own screen until a group is opened) --}}
                <nav class="{{ $picked ? 'hidden md:flex' : 'flex card md:rounded-none md:border-0 md:shadow-none' }} flex-col md:border-r min-h-0 overflow-hidden" aria-label="Chat groups">
                    <div class="p-3 border-b border-gray-100">
                        <label class="flex items-center gap-2 px-3 rounded-full bg-gray-100 text-gray-500">
                            <x-icon name="search" class="w-4 h-4 shrink-0" />
                            <input type="search" id="group-filter" placeholder="Search groups"
                                   class="flex-1 bg-transparent border-0 focus:ring-0 py-2 px-0 text-sm text-gray-900">
                        </label>
                    </div>
                    <div class="overflow-y-auto">
                        @foreach ($rooms as $key => $label)
                            @php($n = ($key === $room && $picked) ? 0 : ($unread[$key] ?? 0))
                            @php($kind = strtok($key, ':'))
                            <a href="{{ route('chat.index', ['room' => $key]) }}" data-name="{{ mb_strtolower($label) }}"
                               class="group-row flex items-center gap-3 pl-3 {{ $key === $room && $picked ? 'bg-gray-100' : 'hover:bg-gray-50 active:bg-gray-100' }}">
                                <span class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 text-white
                                    {{ ['team' => 'bg-brand-600', 'client' => 'bg-accent-500', 'internal' => 'bg-gray-600'][$kind] ?? 'bg-brand-600' }}">
                                    <x-icon :name="$roomIcon($key)" class="w-6 h-6" />
                                </span>
                                <span class="flex-1 min-w-0 py-3 pr-4 border-b border-gray-100">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="truncate text-[16px] font-semibold text-gray-900">{{ $label }}</span>
                                        @if (in_array($key, $mutedRooms, true))
                                            <x-icon name="bell-slash" class="w-4 h-4 text-gray-400 shrink-0" />
                                        @endif
                                        <span class="flex-1"></span>
                                        <span class="text-xs shrink-0 {{ $n ? 'text-brand-600 font-semibold' : 'text-gray-500' }}">{{ $last[$key]['when'] ?? '' }}</span>
                                    </span>
                                    <span class="flex items-center justify-between gap-2 mt-0.5">
                                        <span class="truncate text-sm {{ $n ? 'text-gray-900 font-medium' : 'text-gray-500' }}">
                                            @isset($last[$key])
                                                {{ $last[$key]['who'] }}: {{ $last[$key]['text'] }}
                                            @else
                                                No messages yet
                                            @endisset
                                        </span>
                                        @if ($n)
                                            <span class="min-w-[22px] h-[22px] px-1.5 rounded-full bg-brand-600 text-white text-xs font-semibold leading-[22px] text-center shrink-0">{{ $n > 99 ? '99+' : $n }}</span>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </nav>

                {{-- Conversation (phone: full screen over the app bars) --}}
                <section id="chat-screen" class="{{ $picked ? 'fixed inset-0 z-40 flex' : 'hidden md:flex' }} md:static md:z-auto flex-col bg-white min-h-0">
                    <header class="flex items-center gap-2 px-2 py-2 border-b bg-white pt-safe">
                        <a href="{{ route('chat.index') }}" class="md:hidden p-2 -ml-1 rounded-full text-gray-600 hover:bg-gray-100" aria-label="Back to groups">
                            <x-icon name="arrow-left" class="w-5 h-5" />
                        </a>
                        <span class="w-9 h-9 rounded-full bg-brand-50 text-brand-700 flex items-center justify-center shrink-0">
                            <x-icon :name="$roomIcon($room)" class="w-5 h-5" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900 truncate">{{ $rooms[$room] }}</div>
                            <div class="hidden sm:block text-xs text-gray-500 truncate">Tap a message to reply</div>
                        </div>
                        <button type="button" id="media-open" class="p-2 rounded-full text-gray-600 hover:bg-gray-100" aria-label="Photos and files">
                            <x-icon name="photo" class="w-5 h-5" />
                        </button>
                        <button type="button" id="mute-toggle" class="p-2 rounded-full hover:bg-gray-100 {{ in_array($room, $mutedRooms, true) ? 'text-red-500' : 'text-gray-600' }}"
                                aria-label="Mute notifications" aria-pressed="{{ in_array($room, $mutedRooms, true) ? 'true' : 'false' }}"
                                title="{{ in_array($room, $mutedRooms, true) ? 'Muted: tap to unmute' : 'Mute this group' }}">
                            <x-icon name="bell-slash" class="w-5 h-5 {{ in_array($room, $mutedRooms, true) ? '' : 'hidden' }}" />
                            <x-icon name="bell" class="w-5 h-5 {{ in_array($room, $mutedRooms, true) ? 'hidden' : '' }}" />
                        </button>
                        <button type="button" id="search-toggle" class="p-2 rounded-full text-gray-600 hover:bg-gray-100" aria-label="Search messages">
                            <x-icon name="search" class="w-5 h-5" />
                        </button>
                    </header>

                    {{-- Pinned message --}}
                    <div id="pin-bar" class="hidden items-center gap-2 px-4 py-2 border-b bg-white text-sm">
                        <x-icon name="pin" class="w-4 h-4 text-brand-600 shrink-0" />
                        <button type="button" id="pin-text" class="flex-1 min-w-0 text-left truncate text-gray-700"></button>
                        @if ($canPin)
                            <button type="button" id="pin-remove" class="p-1 text-gray-400 hover:text-gray-700" aria-label="Unpin"><x-icon name="x" class="w-4 h-4" /></button>
                        @endif
                    </div>

                    <form id="search-form" method="GET" action="{{ route('chat.index') }}" class="{{ $search === '' ? 'hidden' : 'flex' }} items-center gap-2 px-3 py-2 border-b bg-gray-50">
                        <input type="hidden" name="room" value="{{ $room }}">
                        <input type="search" name="q" value="{{ $search }}" placeholder="Search in this group…"
                               class="flex-1 rounded-full border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @if ($search !== '')
                            <a href="{{ route('chat.index', ['room' => $room]) }}" class="text-sm text-brand-700 font-medium px-2">Clear</a>
                        @endif
                    </form>

                    <div class="relative flex-1 min-h-0">
                        <div id="chat-list" class="absolute inset-0 overflow-y-auto px-3 py-4 space-y-1 bg-[#efeae2]" aria-live="polite">
                            @if ($search !== '')
                                <p class="text-center text-xs text-gray-600 mb-3">{{ $messages->count() }} result(s) for "{{ $search }}"</p>
                            @endif
                        </div>
                        {{-- Photos & files of this group --}}
                        <div id="media-panel" class="hidden absolute inset-0 z-10 bg-white flex-col">
                            <div class="flex items-center gap-2 px-3 py-2 border-b">
                                <button type="button" id="media-close" class="p-2 rounded-full text-gray-600 hover:bg-gray-100" aria-label="Close"><x-icon name="arrow-left" class="w-5 h-5" /></button>
                                <span class="font-semibold text-gray-900">Photos &amp; files</span>
                            </div>
                            <div class="flex-1 overflow-y-auto p-3 space-y-4">
                                <div id="media-grid" class="grid grid-cols-3 sm:grid-cols-4 gap-1"></div>
                                <div id="media-files" class="divide-y rounded-xl border"></div>
                                <p id="media-empty" class="hidden text-center text-sm text-gray-500 py-8">No photos or files yet.</p>
                            </div>
                        </div>
                        @if ($canLinkPosts)
                            {{-- Pick which post this message is about --}}
                            <div id="posts-panel" class="hidden absolute inset-0 z-10 bg-white flex-col">
                                <div class="flex items-center gap-2 px-3 py-2 border-b">
                                    <button type="button" id="posts-close" class="p-2 rounded-full text-gray-600 hover:bg-gray-100" aria-label="Close"><x-icon name="arrow-left" class="w-5 h-5" /></button>
                                    <span class="font-semibold text-gray-900">Which post is this about?</span>
                                </div>
                                <div id="posts-list" class="flex-1 overflow-y-auto divide-y divide-gray-100"></div>
                            </div>
                        @endif
                        <button type="button" id="new-below" class="hidden absolute bottom-3 left-1/2 -translate-x-1/2 px-4 py-2 rounded-full bg-brand-600 text-white text-sm shadow-lg">
                            New messages ↓
                        </button>
                    </div>

                    <template id="clip-icon"><x-icon name="paperclip" class="w-4 h-4 shrink-0" /></template>
                    <template id="film-icon"><x-icon name="film" class="w-4 h-4 shrink-0 text-accent-600" /></template>
                    <form id="chat-form" class="{{ $search !== '' ? 'hidden' : '' }} border-t bg-gray-50 px-2 py-2 pb-safe space-y-2">
                        <div id="reply-bar" class="hidden items-start gap-2 mx-1 px-3 py-2 rounded-xl bg-white border-l-4 border-brand-500">
                            <div class="flex-1 min-w-0 text-xs">
                                <div id="reply-user" class="font-semibold text-brand-700"></div>
                                <div id="reply-text" class="text-gray-600 truncate"></div>
                            </div>
                            <button type="button" id="reply-cancel" class="p-1 text-gray-500" aria-label="Cancel reply"><x-icon name="x" class="w-4 h-4" /></button>
                        </div>
                        <div id="content-chip" class="{{ $attach ? 'flex' : 'hidden' }} items-center gap-2 mx-1 px-3 py-2 rounded-xl bg-white border-l-4 border-accent-500 text-sm text-gray-700">
                            <x-icon name="film" class="w-4 h-4 shrink-0 text-accent-600" />
                            <span class="flex-1 truncate">About: <strong id="content-label">{{ $attach['label'] ?? '' }}</strong></span>
                            <input type="hidden" name="content_id" id="content-id" value="{{ $attach['id'] ?? '' }}">
                            <button type="button" id="content-cancel" class="p-1 text-gray-500" aria-label="Remove post link"><x-icon name="x" class="w-4 h-4" /></button>
                        </div>
                        <div id="file-chip" class="hidden items-center gap-2 mx-1 px-3 py-2 rounded-xl bg-white text-sm text-gray-700">
                            <x-icon name="paperclip" class="w-4 h-4 shrink-0" />
                            <span id="chat-file-name" class="flex-1 truncate"></span>
                            <button type="button" id="file-cancel" class="p-1 text-gray-500" aria-label="Remove file"><x-icon name="x" class="w-4 h-4" /></button>
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1 flex items-end bg-white rounded-3xl border border-gray-200 px-1">
                                <label class="cursor-pointer p-2.5 text-gray-500 hover:text-brand-700" title="Photo / file">
                                    <x-icon name="paperclip" class="w-5 h-5" />
                                    <input type="file" name="file" id="chat-file" class="sr-only"
                                        accept="image/*,video/mp4,video/quicktime,application/pdf,.docx,.xlsx,.pptx">
                                </label>
                                @if ($canLinkPosts)
                                    <button type="button" id="posts-open" class="p-2.5 -ml-2 text-gray-500 hover:text-accent-600" title="Link a post" aria-label="Link a post">
                                        <x-icon name="film" class="w-5 h-5" />
                                    </button>
                                @endif
                                <textarea name="body" id="chat-body" rows="1" placeholder="Message (@name to mention)"
                                    class="flex-1 resize-none border-0 focus:ring-0 py-2.5 px-1 text-[15px] max-h-32 bg-transparent"></textarea>
                                <span id="rec-time" class="hidden self-center pr-3 text-sm font-medium text-red-600"></span>
                            </div>
                            <input type="hidden" name="reply_to_id" id="reply-to">
                            <button type="button" id="chat-mic" class="hidden w-12 h-12 rounded-full bg-brand-600 text-white flex items-center justify-center shrink-0" aria-label="Record voice note">
                                <x-icon name="mic" class="w-5 h-5" />
                            </button>
                            <button type="submit" id="chat-send" class="w-12 h-12 rounded-full bg-brand-600 text-white flex items-center justify-center shrink-0 disabled:opacity-60" aria-label="Send">
                                <x-icon name="send" class="w-5 h-5" />
                            </button>
                        </div>
                        <p id="chat-error" class="text-sm text-red-600 hidden px-2"></p>
                    </form>
                </section>
            </div>
        </div>
    </div>

    {{-- Full-screen photo viewer --}}
    <div id="lightbox" class="hidden fixed inset-0 z-50 bg-black/90 items-center justify-center p-4" role="dialog" aria-label="Photo">
        <img id="lightbox-img" class="max-h-full max-w-full rounded" alt="">
    </div>

    <script>
        (() => {
            const pollUrl = @js(route('chat.poll', ['room' => $room]));
            const sendUrl = @js(route('chat.store', ['room' => $room]));
            const msgUrl = @js(url('chat/message')) + '/';
            const searching = @js($search !== '');
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const $ = (id) => document.getElementById(id);
            const list = $('chat-list'), form = $('chat-form'), body = $('chat-body'), file = $('chat-file'), err = $('chat-error');
            const mic = $('chat-mic'), send = $('chat-send'), replyTo = $('reply-to');
            const clipSvg = $('clip-icon').innerHTML, filmSvg = $('film-icon').innerHTML;
            const canPin = @js($canPin), mediaUrl = @js(route('chat.media', ['room' => $room])), muteUrl = @js(route('chat.mute', ['room' => $room]));
            const rows = {};
            let lastId = 0, lastDay = null, lastUser = null, voice = null;

            if (window.matchMedia('(min-width: 768px)').matches === false && @js($picked)) document.body.classList.add('overflow-hidden');

            // User text always goes through textContent (never HTML)
            const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
            const show = (node, on, display = 'flex') => { node.classList.toggle('hidden', !on); node.classList.toggle(display, on); };

            function fill(bubble, m, grouped) {
                bubble.replaceChildren();
                if (!m.mine && !grouped) {
                    const who = el('div', 'text-xs font-semibold text-brand-700', m.user);
                    who.append(el('span', 'font-normal text-gray-400', ' · ' + m.role));
                    bubble.append(who);
                }
                if (m.reply && !m.deleted) {
                    const q = el('button', 'block w-full text-left mt-1 mb-1 px-2 py-1 rounded-lg bg-gray-900/5 border-l-4 border-brand-500 text-xs');
                    q.type = 'button';
                    q.append(el('div', 'font-semibold text-brand-700', m.reply.user), el('div', 'text-gray-600 truncate', m.reply.text));
                    q.addEventListener('click', (e) => { e.stopPropagation(); rows[m.reply.id]?.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
                    bubble.append(q);
                }
                if (m.content && !m.deleted) {
                    // "About this post" card: opens the content page
                    const c = el(m.content.url ? 'a' : 'div', 'flex items-center gap-2 mt-1 mb-1 px-2 py-1.5 rounded-lg bg-accent-50 border-l-4 border-accent-500 text-xs text-gray-800');
                    if (m.content.url) c.href = m.content.url;
                    c.innerHTML = filmSvg; c.append(el('span', 'font-semibold truncate', m.content.label));
                    bubble.append(c);
                }
                if (m.deleted) {
                    bubble.append(el('div', 'italic text-gray-500', 'This message was deleted'));
                } else {
                    if (m.file?.image) {
                        const img = el('img', 'mt-1 rounded-xl max-h-72 cursor-zoom-in'); img.src = m.file.url; img.alt = m.file.name; img.loading = 'lazy';
                        img.addEventListener('click', (e) => { e.stopPropagation(); $('lightbox-img').src = m.file.url; show($('lightbox'), true); });
                        bubble.append(img);
                    } else if (m.file?.audio) {
                        const au = el('audio', 'mt-1 w-64 max-w-full'); au.controls = true; au.preload = 'metadata'; au.src = m.file.url;
                        bubble.append(au);
                    } else if (m.file) {
                        const card = el('a', 'mt-1 flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-900/5 text-sm'); card.href = m.file.download;
                        card.innerHTML = clipSvg; card.append(el('span', 'truncate', m.file.name)); // icon is static markup, name stays text
                        bubble.append(card);
                    }
                    if (m.body) bubble.append(el('div', 'whitespace-pre-line break-words', m.body));
                }
                bubble.append(el('div', 'text-[11px] text-gray-500 text-right -mb-0.5', m.time));
            }

            function render(m) {
                if (m.id <= lastId) return;
                lastId = m.id;
                if (m.day !== lastDay) {
                    const sep = el('div', 'flex justify-center py-2');
                    sep.append(el('span', 'px-3 py-1 rounded-lg bg-white/90 text-xs text-gray-600 shadow-sm', m.day));
                    list.append(sep);
                    lastDay = m.day; lastUser = null;
                }
                const grouped = lastUser === m.user;
                lastUser = m.user;

                const row = el('div', 'flex items-end gap-2 ' + (m.mine ? 'justify-end' : 'justify-start') + (grouped ? '' : ' pt-2'));
                if (!m.mine) row.append(grouped ? el('span', 'w-8 shrink-0') : el('span', 'w-8 h-8 rounded-full bg-accent-100 text-accent-600 text-xs font-bold flex items-center justify-center shrink-0', m.initials));
                const wrap = el('div', 'max-w-[82%] md:max-w-[65%] flex flex-col ' + (m.mine ? 'items-end' : 'items-start'));
                const bubble = el('div', 'px-3 pt-1.5 pb-1 rounded-2xl shadow-sm text-[15px] text-gray-900 cursor-pointer select-text '
                    + (m.mine ? 'bg-brand-100 ' + (grouped ? '' : 'rounded-tr-md') : 'bg-white ' + (grouped ? '' : 'rounded-tl-md')));
                fill(bubble, m, grouped);
                bubble.addEventListener('click', (e) => { if (!e.target.closest('a,audio,button,img')) menu(wrap, m); });
                wrap.append(bubble);
                row.append(wrap);
                rows[m.id] = row;
                row._m = m; row._bubble = bubble; row._grouped = grouped;
                list.append(row);
            }

            // Tap a message: Reply / Seen by / Delete (two taps, no browser dialog)
            function menu(wrap, m) {
                const open = wrap.querySelector('.msg-menu');
                document.querySelectorAll('.msg-menu').forEach((n) => n.remove());
                if (open || m.deleted) return;
                const bar = el('div', 'msg-menu flex flex-wrap gap-1 mt-1 text-xs');
                const btn = (label, fn, cls = 'text-gray-700') => { const b = el('button', 'px-3 py-1.5 rounded-full bg-white shadow-sm ' + cls, label); b.type = 'button'; b.addEventListener('click', fn); bar.append(b); return b; };
                btn('Reply', () => {
                    replyTo.value = m.id; $('reply-user').textContent = m.user; $('reply-text').textContent = m.body || m.file?.name || '';
                    show($('reply-bar'), true); bar.remove(); body.focus();
                });
                if (canPin) {
                    btn(pinnedId === m.id ? 'Unpin' : 'Pin', async () => { bar.remove(); await togglePin(m.id); });
                }
                if (m.mine) {
                    btn('Seen by', async (e) => {
                        const res = await fetch(msgUrl + m.id + '/seen', { headers: { Accept: 'application/json' } });
                        const names = res.ok ? await res.json() : [];
                        e.target.replaceWith(el('span', 'px-3 py-1.5 text-gray-600', names.length ? 'Seen by ' + names.join(', ') : 'Not seen yet'));
                    });
                }
                if (m.canDelete) {
                    const del = btn('Delete', async () => {
                        if (!del.dataset.sure) { del.dataset.sure = 1; del.textContent = 'Tap again to delete for everyone'; return; }
                        const res = await fetch(msgUrl + m.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                        if (res.ok) markDeleted(m.id); else { del.textContent = 'Can no longer delete'; }
                    }, 'text-red-600');
                }
                wrap.append(bar);
            }

            // Pinned message bar
            let pinnedId = null;
            function setPinned(p) {
                pinnedId = p ? p.id : null;
                show($('pin-bar'), !!p);
                if (p) $('pin-text').textContent = p.user + ': ' + (p.body || p.content?.label || p.file?.name || '');
            }
            async function togglePin(id) {
                const res = await fetch(msgUrl + id + '/pin', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                if (res.ok) setPinned((await res.json()).pinned);
            }
            $('pin-text').addEventListener('click', () => rows[pinnedId]?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            $('pin-remove')?.addEventListener('click', () => pinnedId && togglePin(pinnedId));
            setPinned(@js($pinned));

            // Mute this group (no phone push)
            $('mute-toggle').addEventListener('click', async (e) => {
                const b = e.currentTarget;
                const res = await fetch(muteUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
                if (!res.ok) return;
                const muted = (await res.json()).muted;
                b.setAttribute('aria-pressed', muted); b.title = muted ? 'Muted: tap to unmute' : 'Mute this group';
                b.classList.toggle('text-red-500', muted); b.classList.toggle('text-gray-600', !muted);
                const [slash, bell] = b.querySelectorAll('svg');
                slash.classList.toggle('hidden', !muted); bell.classList.toggle('hidden', muted);
            });

            // Photos & files panel
            $('media-open').addEventListener('click', async () => {
                show($('media-panel'), true);
                const res = await fetch(mediaUrl, { headers: { Accept: 'application/json' } });
                const items = res.ok ? await res.json() : [];
                const grid = $('media-grid'), files = $('media-files');
                grid.replaceChildren(); files.replaceChildren();
                items.forEach((f) => {
                    if (f.image) {
                        const img = el('img', 'w-full aspect-square object-cover rounded cursor-zoom-in'); img.src = f.url; img.alt = f.name; img.loading = 'lazy';
                        img.addEventListener('click', () => { $('lightbox-img').src = f.url; show($('lightbox'), true); });
                        grid.append(img);
                    } else {
                        const a = el('a', 'flex items-center gap-2 px-3 py-2.5 text-sm hover:bg-gray-50'); a.href = f.download;
                        a.innerHTML = clipSvg;
                        a.append(el('span', 'flex-1 truncate', f.name), el('span', 'text-xs text-gray-500 shrink-0', (f.audio ? 'Voice · ' : '') + f.day));
                        files.append(a);
                    }
                });
                files.classList.toggle('hidden', !files.children.length);
                $('media-empty').classList.toggle('hidden', items.length > 0);
            });
            $('media-close').addEventListener('click', () => show($('media-panel'), false));

            // SMM: link a post to the next message
            $('posts-open')?.addEventListener('click', async () => {
                show($('posts-panel'), true);
                const list2 = $('posts-list');
                list2.replaceChildren(el('p', 'text-center text-sm text-gray-500 py-8', 'Loading…'));
                const res = await fetch(@js($canLinkPosts ? route('chat.posts', ['room' => $room]) : ''), { headers: { Accept: 'application/json' } });
                const posts = res.ok ? await res.json() : [];
                list2.replaceChildren();
                if (!posts.length) list2.append(el('p', 'text-center text-sm text-gray-500 py-8', 'No posts for this brand yet.'));
                posts.forEach((p) => {
                    const b = el('button', 'w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50'); b.type = 'button';
                    const ic = el('span', 'w-10 h-10 rounded-full bg-accent-50 flex items-center justify-center shrink-0'); ic.innerHTML = filmSvg;
                    const txt = el('span', 'flex-1 min-w-0');
                    txt.append(el('span', 'block font-medium text-gray-900 truncate', p.label), el('span', 'block text-xs text-gray-500', p.status));
                    b.append(ic, txt);
                    b.addEventListener('click', () => {
                        $('content-id').value = p.id; $('content-label').textContent = p.label;
                        show($('content-chip'), true); show($('posts-panel'), false); body.focus();
                    });
                    list2.append(b);
                });
            });
            $('posts-close')?.addEventListener('click', () => show($('posts-panel'), false));

            function markDeleted(id) {
                const row = rows[id];
                if (!row || row._m.deleted) return;
                row._m = { ...row._m, deleted: true, canDelete: false };
                fill(row._bubble, row._m, row._grouped);
                row.querySelector('.msg-menu')?.remove();
            }

            const atBottom = () => list.scrollHeight - list.scrollTop - list.clientHeight < 80;
            const toBottom = () => { list.scrollTop = list.scrollHeight; show($('new-below'), false, 'block'); };
            list.addEventListener('scroll', () => { if (atBottom()) show($('new-below'), false, 'block'); });
            $('new-below').addEventListener('click', toBottom);

            (@js($messages)).forEach(render);
            toBottom();

            async function poll() {
                if (document.hidden) return;
                try {
                    const stick = atBottom();
                    const res = await fetch(pollUrl + '?after=' + lastId, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    data.messages.forEach(render);
                    data.deleted.forEach(markDeleted);
                    setPinned(data.pinned);
                    if (stick) toBottom();
                    else if (data.messages.some((m) => !m.mine)) show($('new-below'), true, 'block');
                } catch (e) { /* network hiccup: retry next tick */ }
            }
            if (!searching) setInterval(poll, 3000);

            // Filter the group list
            $('group-filter').addEventListener('input', (e) => {
                const q = e.target.value.trim().toLowerCase();
                document.querySelectorAll('.group-row').forEach((r) => r.classList.toggle('hidden', !r.dataset.name.includes(q)));
            });

            // Search, photo viewer
            $('search-toggle').addEventListener('click', () => { show($('search-form'), $('search-form').classList.contains('hidden')); $('search-form').q.focus(); });
            $('lightbox').addEventListener('click', () => show($('lightbox'), false));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') show($('lightbox'), false); });

            // Composer: auto-grow, mic when empty / send when there is something to send
            const canRecord = !!(window.MediaRecorder && navigator.mediaDevices);
            function refresh() {
                const has = body.value.trim() !== '' || file.files.length > 0;
                show(send, has || !canRecord); show(mic, !has && canRecord);
            }
            body.addEventListener('input', () => { body.style.height = 'auto'; body.style.height = Math.min(body.scrollHeight, 128) + 'px'; refresh(); });
            file.addEventListener('change', () => { $('chat-file-name').textContent = file.files[0]?.name || ''; show($('file-chip'), file.files.length > 0); refresh(); });
            $('file-cancel').addEventListener('click', () => { file.value = ''; show($('file-chip'), false); refresh(); });
            const clearContent = () => { $('content-id').value = ''; show($('content-chip'), false); };
            $('content-cancel').addEventListener('click', clearContent);
            $('reply-cancel').addEventListener('click', () => { replyTo.value = ''; show($('reply-bar'), false); });
            body.addEventListener('keydown', (e) => {
                // Enter sends on a computer; on a phone Enter is a new line (like WhatsApp)
                if (e.key === 'Enter' && !e.shiftKey && matchMedia('(pointer: fine)').matches) { e.preventDefault(); form.requestSubmit(); }
            });
            refresh();

            // Voice note: tap mic to record, tap again to send (max 5 min)
            let rec = null, timer = null;
            mic.addEventListener('click', async () => {
                if (rec) { rec.stop(); return; }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const chunks = [];
                    rec = new MediaRecorder(stream);
                    rec.ondataavailable = (ev) => chunks.push(ev.data);
                    rec.onstop = () => {
                        stream.getTracks().forEach((t) => t.stop());
                        clearInterval(timer);
                        const type = rec.mimeType.split(';')[0] || 'audio/webm';
                        voice = new File(chunks, 'Voice note ' + new Date().toLocaleTimeString() + (type.includes('mp4') ? '.m4a' : '.webm'), { type });
                        rec = null;
                        mic.classList.replace('bg-red-600', 'bg-brand-600');
                        show($('rec-time'), false, 'inline');
                        form.requestSubmit();
                    };
                    rec.start();
                    const start = Date.now();
                    mic.classList.replace('bg-brand-600', 'bg-red-600');
                    show($('rec-time'), true, 'inline');
                    timer = setInterval(() => {
                        const s = Math.floor((Date.now() - start) / 1000);
                        $('rec-time').textContent = 'Recording ' + Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0') + ' · tap to send';
                        if (s >= 300) rec.stop();
                    }, 250);
                } catch (e) {
                    err.textContent = 'Microphone not allowed on this browser.';
                    err.classList.remove('hidden');
                }
            });

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                err.classList.add('hidden');
                if (!body.value.trim() && !file.files.length && !voice) return;
                send.disabled = true;
                const fd = new FormData(form);
                if (voice) { fd.set('file', voice); voice = null; }
                try {
                    // XHR (not fetch) so a photo / video / voice note shows its upload %
                    const hasFile = fd.get('file') instanceof File && fd.get('file').size > 0;
                    const xhr = await window.sendWithProgress(sendUrl, fd, {
                        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                        onProgress: hasFile ? (l, t) => { err.textContent = 'Uploading ' + Math.round(l / t * 100) + '%'; err.classList.remove('hidden', 'text-red-600'); } : null,
                    });
                    err.classList.add('hidden', 'text-red-600');
                    const res = { ok: xhr.status >= 200 && xhr.status < 300 };
                    const data = (() => { try { return JSON.parse(xhr.responseText); } catch (e3) { return { message: xhr.status === 413 ? 'File is too big for the server.' : 'Message not sent.' }; } })();
                    if (!res.ok) {
                        err.textContent = Object.values(data.errors || {}).flat().join(' ') || data.message || 'Message not sent.';
                        err.classList.remove('hidden');
                        return;
                    }
                    render(data);
                    toBottom();
                    form.reset();
                    replyTo.value = ''; // reset() does not clear hidden inputs
                    clearContent();
                    body.style.height = 'auto';
                    show($('file-chip'), false); show($('reply-bar'), false);
                } catch (e2) {
                    err.textContent = 'Network problem, please try again.';
                    err.classList.remove('hidden');
                } finally {
                    send.disabled = false;
                    refresh();
                    body.focus();
                }
            });
        })();
    </script>
</x-app-layout>
