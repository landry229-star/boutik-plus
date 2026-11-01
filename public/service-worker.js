const CACHE_NAME = 'boutik-plus-static-v1';
const PRECACHE_URLS = [
    '/manifest.json',
    '/offline.html',
    '/css/boutik-plus.css',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];
const STATIC_URLS = new Set(PRECACHE_URLS);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => Promise.all(
                cacheNames
                    .filter((cacheName) => cacheName.startsWith('boutik-plus-static-') && cacheName !== CACHE_NAME)
                    .map((cacheName) => caches.delete(cacheName)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const requestUrl = new URL(event.request.url);

    if (event.request.method !== 'GET' || requestUrl.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(async () => {
                const offlinePage = await caches.match('/offline.html');

                return offlinePage || new Response('Vous êtes hors connexion.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                });
            }),
        );

        return;
    }

    if (!STATIC_URLS.has(requestUrl.pathname)) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(event.request).then(async (response) => {
                if (response.ok) {
                    await caches.open(CACHE_NAME).then((cache) => cache.put(event.request, response.clone()));
                }

                return response;
            });
        }),
    );
});
