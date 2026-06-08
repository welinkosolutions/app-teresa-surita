const CACHE_VERSION = 'teresapp-v5';
const STATIC_CACHE = `${CACHE_VERSION}-static`;

const STATIC_ASSETS = [
    '/assets/img/icon-192.png',
    '/assets/img/icon-512.png'
];

self.addEventListener('install', event => {
    self.skipWaiting();

    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache =>
            Promise.all(
                STATIC_ASSETS.map(url =>
                    fetch(url)
                        .then(response => {
                            if (!response.ok) throw new Error(url);
                            return cache.put(url, response);
                        })
                        .catch(() => {})
                )
            )
        )
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(key => !key.startsWith(CACHE_VERSION))
                    .map(key => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

/* ===== PUSH ===== */
self.addEventListener('push', event => {
    if (!event.data) return;

    let data = {};

    try {
        data = event.data.json();
    } catch (e) {
        data = {
            title: 'Teresa Surita',
            body: 'Tem uma novidade esperando por você.',
            url: '/dashboard/',
            tag: 'elab-social-push'
        };
    }

    const title = data.title || 'Teresa Surita';
    const body = data.body || 'Tem uma novidade esperando por você.';
    const url = data.url || '/dashboard/';
    const tag = data.tag || 'elab-social-push';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: body,
            icon: '/assets/img/icon-192.png',
            badge: '/assets/img/icon-192.png',
            data: { url: url },
            vibrate: [180, 80, 180],
            tag: tag,
            renotify: true
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    const targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/dashboard/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            for (const client of windowClients) {
                if ('focus' in client) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});