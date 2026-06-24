const CACHE_NAME = 'jdh-pos-v2'; // bump version to force cache refresh
const BASE_PATH = '/JDH_POS/public';

// Only cache real static assets — NEVER PHP pages (causes session lock deadlocks)
const STATIC_ASSETS = [
    BASE_PATH + '/assets/css/pos-saas.css',
    BASE_PATH + '/assets/js/pos.js',
    BASE_PATH + '/assets/js/pos-barcode.js',
    BASE_PATH + '/assets/js/pos-offline.js',
    BASE_PATH + '/assets/js/dashboard.js'
];

function isStaticAsset(url) {
    const path = url.pathname;
    return /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|webp)$/i.test(path);
}

function isPhpPage(url) {
    return /\.php$/i.test(url.pathname);
}

// Install: cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return Promise.all(
                STATIC_ASSETS.map((asset) => {
                    return fetch(asset)
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('Failed to fetch ' + asset + ' : ' + response.statusText);
                            }
                            return cache.put(asset, response);
                        })
                        .catch((err) => {
                            console.warn('Service worker skipped missing static asset:', asset, err);
                        });
                })
            );
        }).then(() => self.skipWaiting())
    );
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: cache-first for static assets only, pass-through for PHP/API
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests and cross-origin requests
    if (request.method !== 'GET' || !url.pathname.startsWith(BASE_PATH)) {
        return;
    }

    // Never intercept PHP pages or API/ajax calls — let browser handle them normally
    if (isPhpPage(url) || url.pathname.startsWith(BASE_PATH + '/api/') || url.pathname.startsWith(BASE_PATH + '/ajax/')) {
        return;
    }

    // Only cache actual static assets (css, js, images, fonts)
    if (!isStaticAsset(url)) {
        return;
    }

    // Static assets: cache-first with background revalidation
    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                // Revalidate in background
                fetch(request).then((response) => {
                    if (response.ok) {
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, response));
                    }
                });
                return cached;
            }
            return fetch(request).then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                }
                return response;
            });
        })
    );
});

// Background sync for offline sales
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-sales') {
        event.waitUntil(syncPendingSales());
    }
});

async function syncPendingSales() {
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach((client) => {
        client.postMessage({ type: 'SYNC_SALES' });
    });
}

// Push notifications (for low stock, new orders)
self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};
    event.waitUntil(
        self.registration.showNotification(data.title || 'JDH POS', {
            body: data.body || 'New notification',
            icon: BASE_PATH + '/assets/img/logo.png',
            badge: BASE_PATH + '/assets/img/logo.png',
            data: data.url || BASE_PATH + '/',
            tag: data.tag || 'default'
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        self.clients.openWindow(event.notification.data)
    );
});
