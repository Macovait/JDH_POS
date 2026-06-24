/**
 * Main Service Worker
 * Provides offline support and caching for the POS application
 */

const CACHE_NAME = 'jdh-pos-v1';
const OFFLINE_URL = '/offline.html';
const CACHE_ASSETS = [
    '/',
    '/pos/pos.php',
    '/pos/returns-ui.html',
    '/pos/audit-ui.html',
    '/pos/credit-ui.html',
    '/pos/discounts-ui.html',
    '/pos/transfers-ui.html',
    '/img/logo.png',
    '/manifest.json'
];

// Install event - cache assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(CACHE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // Skip non-HTTP/HTTPS and external/CDN requests
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
    if (url.hostname !== self.location.hostname) return;

    event.respondWith(
        caches.match(event.request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    // Stale-while-revalidate
                    fetch(event.request).then((response) => {
                        if (response.ok) {
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(event.request, response.clone());
                            });
                        }
                    }).catch(() => {});
                    
                    return cachedResponse;
                }

                return fetch(event.request).catch(() => {
                    if (event.request.mode === 'navigate') {
                        return caches.match(OFFLINE_URL);
                    }
                });
            })
    );
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-sales') {
        event.waitUntil(syncSales());
    }
});

async function syncSales() {
    const db = await openDB();
    const tx = db.transaction('pendingSales', 'readwrite');
    const store = tx.objectStore('pendingSales');
    
    const allSales = await store.getAll();
    
    for (const sale of allSales) {
        try {
            await fetch('/ajax/sales.php', {
                method: 'POST',
                body: JSON.stringify(sale),
                headers: { 'Content-Type': 'application/json' }
            });
            await store.delete(sale.id);
        } catch (error) {
            console.error('Failed to sync sale:', error);
        }
    }
}

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('JDH-POS', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}