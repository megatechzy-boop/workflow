{{-- "Turn on phone notifications": asks permission, then saves this device's push subscription --}}
<div id="push-box" class="hidden card px-4 py-3 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3 text-sm text-gray-700">
        <x-icon name="bell" class="w-5 h-5 text-accent-500 shrink-0" />
        <span id="push-text">Get chat and task alerts on this phone, even when the app is closed.</span>
    </div>
    <button type="button" id="push-btn" class="px-4 py-2 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold">Turn on notifications</button>
</div>
<script>
    (() => {
        const box = document.getElementById('push-box');
        const key = @js(config('webpush.vapid.public_key'));
        const saveUrl = @js(route('push.store'));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
        const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;

        if (!key || !('serviceWorker' in navigator)) return;
        // iPhone only allows push for the installed app (iOS 16.4+)
        if (isIos && !standalone) {
            document.getElementById('push-text').textContent = 'For alerts on iPhone: tap Share → "Add to Home Screen", then open the app from there.';
            document.getElementById('push-btn').remove();
            box.classList.remove('hidden');
            return;
        }
        if (!('PushManager' in window) || Notification.permission === 'denied') return;

        const toKey = (b64) => Uint8Array.from(atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));

        async function subscribe() {
            const reg = await navigator.serviceWorker.ready;
            const sub = (await reg.pushManager.getSubscription())
                || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(key) });
            const json = sub.toJSON();
            json.contentEncoding = (PushManager.supportedContentEncodings || ['aes128gcm'])[0];
            await fetch(saveUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(json) });
        }

        // Already allowed: keep this device's subscription saved (it can change), no button
        if (Notification.permission === 'granted') { subscribe().catch(() => {}); return; }

        box.classList.remove('hidden');
        document.getElementById('push-btn').addEventListener('click', async () => {
            if (await Notification.requestPermission() !== 'granted') { box.classList.add('hidden'); return; }
            try { await subscribe(); box.classList.add('hidden'); }
            catch (e) { document.getElementById('push-text').textContent = 'Could not turn on notifications on this browser.'; }
        });
    })();
</script>
