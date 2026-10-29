{{-- Notifications are required: the app is blocked on this device until push is allowed and saved.
     Every role, phone and laptop. Not shown where push can't work (no VAPID key, old browser). --}}
@if (config('webpush.vapid.public_key'))
<div id="push-gate" class="hidden fixed inset-0 z-50 bg-gray-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="push-gate-title">
    <div class="min-h-full flex items-center justify-center p-4 pt-safe pb-safe">
        <div class="card w-full max-w-md p-6 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
                <x-icon name="bell" class="w-7 h-7" />
            </div>
            <h2 id="push-gate-title" class="mt-4 font-bold text-xl text-gray-900">Turn on notifications</h2>
            <p id="push-gate-text" class="mt-2 text-sm text-gray-600">Chat, task and approval alerts are part of the job. Turn them on for this device to keep using the app.</p>
            <ol id="push-gate-steps" class="hidden mt-4 text-left text-sm text-gray-700 list-decimal ps-5 space-y-1"></ol>
            <button type="button" id="push-gate-btn" class="mt-5 w-full px-4 py-3 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold">Turn on notifications</button>
            <button type="button" id="push-gate-reload" class="hidden mt-5 w-full px-4 py-3 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold">I've done it, reload</button>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="w-full py-2 text-sm text-gray-500 hover:underline">Log out</button>
            </form>
        </div>
    </div>
</div>
<script>
    (() => {
        const gate = document.getElementById('push-gate');
        const text = document.getElementById('push-gate-text');
        const steps = document.getElementById('push-gate-steps');
        const btn = document.getElementById('push-gate-btn');
        const reload = document.getElementById('push-gate-reload');
        const key = @js(config('webpush.vapid.public_key'));
        const saveUrl = @js(route('push.store'));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;

        const show = () => { gate.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); };
        const hide = () => { gate.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); };
        const showSteps = (intro, list) => {
            text.textContent = intro;
            steps.replaceChildren(...list.map((s) => Object.assign(document.createElement('li'), { textContent: s })));
            steps.classList.remove('hidden');
            btn.classList.add('hidden');
            reload.classList.remove('hidden');
        };
        reload.addEventListener('click', () => location.reload());

        if (!('serviceWorker' in navigator)) return;
        // iPhone only allows push for the installed app (iOS 16.4+)
        if (isIos && !standalone) {
            showSteps('On iPhone, alerts only work in the installed app.', [
                'Tap the Share button in Safari.',
                'Tap "Add to Home Screen", then "Add".',
                'Open MegaTechzy from your Home Screen and log in.',
            ]);
            reload.classList.add('hidden');
            show();
            return;
        }
        // Browser can't do push at all: don't lock the user out
        if (!('PushManager' in window) || !('Notification' in window)) return;

        const toKey = (b64) => Uint8Array.from(atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));

        async function subscribe() {
            const reg = await navigator.serviceWorker.ready;
            const sub = (await reg.pushManager.getSubscription())
                || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(key) });
            const json = sub.toJSON();
            json.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];
            const res = await fetch(saveUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(json) });
            if (!res.ok) throw new Error('save failed');
        }

        function blocked() {
            showSteps('Notifications are blocked for this site. Allow them, then reload:', standalone ? [
                'Long-press the MegaTechzy app icon and open App info.',
                'Tap Notifications and turn them on.',
                'Come back here and tap "I\'ve done it, reload".',
            ] : [
                'Click the icon left of the web address (lock or settings).',
                'Set Notifications to Allow.',
                'Tap "I\'ve done it, reload".',
            ]);
            show();
        }

        // Already allowed: keep this device's subscription saved (it can change), no gate
        if (Notification.permission === 'granted') { subscribe().catch(() => {}); return; }
        if (Notification.permission === 'denied') { blocked(); return; }

        show();
        btn.addEventListener('click', async () => {
            const permission = await Notification.requestPermission();
            if (permission === 'denied') { blocked(); return; }
            if (permission !== 'granted') return; // prompt dismissed: gate stays, try again
            try { await subscribe(); hide(); }
            catch (e) { text.textContent = 'Could not turn on notifications on this browser. Check your internet and try again.'; }
        });
    })();
</script>
@endif
