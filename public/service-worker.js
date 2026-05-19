const CACHE_NAME = 'invoflow-v10';
const OFFLINE_PAGE = '/offline.html';

const ASSETS_TO_CACHE = [
    '/',
    '/mobile',
    '/offline.html',
    '/manifest.json',
    '/app_icon_512.png',
    '/favicon.ico',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
];

// ── Install: Cache all assets including offline page ────────────────────────
self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return Promise.allSettled(
                ASSETS_TO_CACHE.map(url => cache.add(url))
            );
        })
    );
});

// ── Activate: Remove old caches ─────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// ── Fetch: Smart strategy with offline fallback ─────────────────────────────
self.addEventListener('fetch', (event) => {
    // Ignore non-GET and non-http requests (e.g. chrome-extension)
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith('http')) return;

    // Navigation requests (HTML pages) → Network First, fallback to offline.html
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    // Try cached version of the requested page first
                    return caches.match(event.request)
                        .then(cached => cached || caches.match(OFFLINE_PAGE));
                })
        );
        return;
    }

    // API / JSON requests → Network Only (no cache), show nothing on fail
    if (event.request.url.includes('/api/') || event.request.headers.get('Accept')?.includes('application/json')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Static assets (CSS, JS, Fonts, Images) → Cache First, fallback to network
    event.respondWith(
        caches.match(event.request).then((cached) => {
            return cached || fetch(event.request).then((response) => {
                // Cache new assets on the fly
                if (response && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
                }
                return response;
            });
        }).catch(() => {
            // For image fallback you can return a placeholder here if needed
            return new Response('', { status: 408, statusText: 'Offline' });
        })
    );
});
