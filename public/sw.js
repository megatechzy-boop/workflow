// Service worker: makes the app installable. Pages always come from the network
// (data is private and live); only static build assets/icons are cached, and an
// offline page is shown when there is no connection.
const CACHE = 'mt-static-v1';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))),
    );
    self.clients.claim();
});

// Phone push (sent by WebPushChannel): show it, and open its link on tap
self.addEventListener('push', (event) => {
    const p = event.data ? event.data.json() : {};
    event.waitUntil(self.registration.showNotification(p.title || 'MegaTechzy', p));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/dashboard';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
            const win = wins.find((w) => new URL(w.url).origin === self.location.origin);
            return win ? win.focus().then(() => win.navigate(url)) : self.clients.openWindow(url);
        }),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Page navigations: network only, offline page as fallback (never cache private pages)
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }

    // Hashed build assets and icons never change: cache first
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((c) => c.put(req, copy));
                return res;
            })),
        );
    }
});
