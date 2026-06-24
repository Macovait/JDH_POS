/**
 * JDH POS - Enhanced Service Worker for Offline Support
 * Enables hybrid offline/online POS functionality
 * 
 * Features:
 * - Static asset caching (CSS, JS, images)
 * - Offline fallback page
 * - Background sync for sales data
 * - Queue system for offline transactions
 */

const CACHE_NAME = 'jdh-pos-v100'; // Force invalidate all cached PHP
const STATIC_CACHE = 'jdh-pos-static-v100';
const DYNAMIC_CACHE = 'jdh-pos-dynamic-v100';

// Static assets to cache on install
const STATIC_ASSETS = [
    '/JDH_POS/public/offline.html',
    '/JDH_POS/public/assets/css/app.css',
    '/JDH_POS/public/assets/css/custom.css',
    '/JDH_POS/public/assets/js/offline-manager.js',
    '/JDH_POS/public/assets/js/chart.js.min.js'
];

// API routes that should use network-first strategy
const API_ROUTES = [
    '/ajax/',
    '/api/',
    '/pos_backend.php'
];

// Pages that must NEVER be cached (heavy inline JS, frequently changing, or caused SyntaxErrors from stale caches)
const NEVER_CACHE_PAGES = [
    '/products/barcode_labels.php',
    '/barcode_labels.php',
    '/pos/pos.php',
    '/pos/responsive_pos.php',
    '/pos/discount_form.php',
    '/pos/discounts.php',
    '/products/product_form.php',
    '/products/product_edit.php',
    '/dashboard/home.php',
    '/dashboard/',
    '/receipt.php',
    '/pos/receipt',
    '/pos/select_sale_for_return.php',
    '/reports/reports.php'
];

// Install: Cache static assets individually (non-atomic)
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Caching static assets');
                const promises = STATIC_ASSETS.map(url =>
                    cache.add(url).catch(err => console.warn('[SW] Failed to cache:', url, err.message))
                );
                return Promise.all(promises);
            })
            .then(() => {
                console.log('[SW] Static assets cached');
                return self.skipWaiting();
            })
            .catch(err => {
                console.error('[SW] Cache install failed:', err);
                return self.skipWaiting();
            })
    );
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(name => {
                            return name.startsWith('jdh-pos-') &&
                                   name !== STATIC_CACHE &&
                                   name !== DYNAMIC_CACHE;
                        })
                        .map(name => {
                            console.log('[SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Activated');
                return self.clients.claim();
            })
    );
});

// Fetch: Implement caching strategies
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-HTTP/HTTPS requests (chrome-extension:, data:, blob:, etc.)
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return;
    }

    // Skip external/CDN resources entirely — never intercept or cache them.
    // This prevents 503 errors from CDNs (Tailwind, Font Awesome, Google Fonts, etc.)
    // from being cached and served as broken assets.
    if (url.hostname !== self.location.hostname) {
        return;
    }

    // Skip non-GET requests (POST, PUT, DELETE go to network)
    if (request.method !== 'GET') {
        // Handle background sync for POST requests
        if (request.method === 'POST' && isSyncableRequest(url.pathname)) {
            event.respondWith(handleSyncableRequest(request));
        }
        return;
    }

    // Force network-only for heavy/dynamic pages that must never be cached
    // (prevents stale inline JS causing SyntaxError: Unexpected token '}')
    // IMPORTANT: Do NOT use respondWith here — just return to let the browser
    // perform the request directly without any SW interception or error wrapping.
    if (NEVER_CACHE_PAGES.some(page => url.pathname.includes(page))) {
        return;
    }

    // ── NEVER CACHE AJAX/API GET REQUESTS ─────────────────────────────────
    // AJAX responses carry auth-bearing data; a failed response (403, 401, 500)
    // cached here becomes a "permanent" broken state served silently from the SW.
    if (url.pathname.startsWith('/ajax/') || url.pathname.startsWith('/api/')) {
        return;
    }
    // ─────────────────────────────────────────────────────────────────────

    // NEVER cache PHP content pages, but provide offline fallback for navigations
    // to prevent Chrome's error page (chrome-error://chromewebdata/) from triggering
    // cross-origin security warnings when it tries to reload the original URL.
    if (/\.php$/i.test(url.pathname)) {
        if (request.mode === 'navigate') {
            event.respondWith(
                fetch(request).then(response => {
                    // If server returns an error (500, 502, 503, 504), serve offline fallback
                    if (!response.ok && (response.status >= 500 && response.status < 600)) {
                        console.log('[SW] Server error ' + response.status + ' for PHP navigation; serving offline fallback');
                        return caches.match('/JDH_POS/public/offline.html').then(cached => {
                            if (cached) return cached;
                            return new Response(
                                '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Offline</title></head>' +
                                '<body style="font-family:sans-serif;text-align:center;padding:2rem;background:#0F172A;color:#F9FAFB;">' +
                                '<h1>Temporarily Unavailable</h1><p>The page could not be loaded. Please try again later.</p>' +
                                '</body></html>',
                                { headers: { 'Content-Type': 'text/html' }, status: 503, statusText: 'Service Unavailable' }
                            );
                        });
                    }
                    return response;
                }).catch(() => {
                    // For localhost, don't show offline fallback — the server may still be reachable
                    // even when navigator.onLine is false (common on dev machines without internet)
                    const isLocalhost = url.hostname === 'localhost' || url.hostname === '127.0.0.1' || url.hostname === '::1';
                    if (isLocalhost) {
                        console.log('[SW] Localhost fetch failed for PHP navigation; retrying without SW interception');
                        // Retry directly — if this also fails, let the browser show its native error
                        return fetch(request, { cache: 'no-store' }).catch(err => {
                            console.warn('[SW] Localhost retry also failed:', err);
                            throw err; // Let browser handle it naturally
                        });
                    }
                    console.log('[SW] Network failed for PHP navigation; serving offline fallback');
                    return caches.match('/JDH_POS/public/offline.html').then(cached => {
                        if (cached) return cached;
                        return new Response(
                            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Offline</title></head>' +
                            '<body style="font-family:sans-serif;text-align:center;padding:2rem;background:#0F172A;color:#F9FAFB;">' +
                            '<h1>You are offline</h1><p>Please check your internet connection and try again.</p>' +
                            '</body></html>',
                            { headers: { 'Content-Type': 'text/html' } }
                        );
                    });
                })
            );
        }
        return;
    }

    // Also skip receipt pages explicitly (iframes must not be served cached/error responses)
    if (url.pathname.includes('/pos/receipt') || url.pathname.includes('/receipt.php')) {
        return;
    }

    // Strategy 1: API routes - Network First, then Cache
    if (isApiRoute(url.pathname)) {
        event.respondWith(networkFirstStrategy(request));
        return;
    }

    // Strategy 2: Static assets - Cache First, then Network
    if (isStaticAsset(url.pathname)) {
        // Skip external CDN resources (they may return errors that get cached)
        if (url.hostname !== self.location.hostname) {
            event.respondWith(fetch(request));
            return;
        }
        event.respondWith(cacheFirstStrategy(request));
        return;
    }

    // Strategy 3: Everything else - Stale While Revalidate (non-PHP only)
    event.respondWith(staleWhileRevalidateStrategy(request));
});

// Background Sync for offline sales
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-sales') {
        console.log('[SW] Background sync: sync-sales');
        event.waitUntil(syncOfflineSales());
    }
    
    if (event.tag === 'sync-expenses') {
        console.log('[SW] Background sync: sync-expenses');
        event.waitUntil(syncOfflineExpenses());
    }
});

// Push notifications (for future use)
self.addEventListener('push', (event) => {
    const data = event.data.json();
    const options = {
        body: data.body || 'New notification',
        icon: '/JDH_POS/public/assets/img/logo.png',
        badge: '/JDH_POS/public/assets/img/badge.png',
        tag: data.tag || 'default',
        requireInteraction: true
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'JDH POS', options)
    );
});

// ============ Helper Functions ============

function isApiRoute(pathname) {
    return API_ROUTES.some(route => pathname.includes(route));
}

function isStaticAsset(pathname) {
    return /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)$/.test(pathname);
}

function isSyncableRequest(pathname) {
    return pathname.includes('/ajax/process_sale.php') ||
           pathname.includes('/ajax/hold_sale.php') ||
           pathname.includes('/expenses/add_expense.php');
}

// Network First: Try network, fallback to cache
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            await cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        // Don't cache error responses — delete any stale cached version
        const cache = await caches.open(DYNAMIC_CACHE);
        await cache.delete(request);
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        const cachedResponse = await caches.match(request);
        if (cachedResponse && cachedResponse.ok) {
            return cachedResponse;
        }
        // Return offline JSON for API requests
        return new Response(
            JSON.stringify({ offline: true, error: 'Offline mode - data will sync when online' }),
            { headers: { 'Content-Type': 'application/json' } }
        );
    }
}

// Cache First: Try cache, fallback to network
async function cacheFirstStrategy(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse && cachedResponse.ok) {
        return cachedResponse;
    }
    // Delete stale error cache entries
    if (cachedResponse) {
        const cache = await caches.open(STATIC_CACHE);
        await cache.delete(request);
    }
    
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            await cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('[SW] Both cache and network failed:', request.url);
        // Only serve offline page for non-PHP navigation requests
        if (request.mode === 'navigate' && !/\.php$/i.test(new URL(request.url).pathname)) {
            return caches.match('/JDH_POS/public/offline.html');
        }
        throw error;
    }
}

// Stale While Revalidate: Return cache, update in background
async function staleWhileRevalidateStrategy(request) {
    const cachedResponse = await caches.match(request);

    // Only serve cached response if it was a successful one
    if (cachedResponse && cachedResponse.ok) {
        // Update cache in background without blocking
        fetch(request).then(async networkResponse => {
            if (networkResponse.ok) {
                const cache = await caches.open(DYNAMIC_CACHE);
                await cache.put(request, networkResponse.clone());
            } else {
                // Delete stale error cache entry
                const cache = await caches.open(DYNAMIC_CACHE);
                await cache.delete(request);
            }
        }).catch(error => {
            console.log('[SW] Background update failed:', error);
        });
        return cachedResponse;
    }

    // Delete stale error cache entry if present
    if (cachedResponse) {
        const cache = await caches.open(DYNAMIC_CACHE);
        await cache.delete(request);
    }

    // No valid cached response, try network
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            await cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network fetch failed:', error);
        return new Response('{"error": "Network failed"}', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Handle syncable POST requests (queue for later)
async function handleSyncableRequest(request) {
    try {
        // Try network first
        const response = await fetch(request);
        return response;
    } catch (error) {
        // Network failed - queue for later
        console.log('[SW] Queuing request for later sync');
        
        // Clone request for storage
        const requestClone = request.clone();
        const body = await requestClone.json();
        
        // Store in IndexedDB for later sync
        await queueForSync({
            url: request.url,
            method: request.method,
            headers: Array.from(request.headers.entries()),
            body: body,
            timestamp: Date.now(),
            id: crypto.randomUUID()
        });
        
        // Register for background sync
        if ('sync' in self.registration) {
            await self.registration.sync.register('sync-sales');
        }
        
        // Return success response (will be processed later)
        return new Response(JSON.stringify({
            success: true,
            message: 'Request queued for sync',
            queued: true
        }), {
            status: 202,
            statusText: 'Accepted',
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }
}

// Queue data for sync
async function queueForSync(data) {
    // Use IndexedDB for persistent storage
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('JDH_POS_Offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            store.add(data);
            resolve();
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('syncQueue')) {
                db.createObjectStore('syncQueue', { keyPath: 'id' });
            }
        };
    });
}

// Sync offline sales when back online
async function syncOfflineSales() {
    const db = await openIndexedDB();
    const transaction = db.transaction(['syncQueue'], 'readonly');
    const store = transaction.objectStore('syncQueue');
    const requests = await store.getAll();
    
    for (const item of requests) {
        try {
            const headers = new Headers(item.headers);
            const response = await fetch(item.url, {
                method: item.method,
                headers: headers,
                body: JSON.stringify(item.body)
            });
            
            if (response.ok) {
                // Remove from queue on success
                const deleteTx = db.transaction(['syncQueue'], 'readwrite');
                const deleteStore = deleteTx.objectStore('syncQueue');
                await deleteStore.delete(item.id);
                
                // Notify clients
                notifyClients('sync-complete', { id: item.id, type: 'sale' });
            }
        } catch (error) {
            console.error('[SW] Sync failed for item:', item.id, error);
        }
    }
}

async function syncOfflineExpenses() {
    // Similar to syncOfflineSales but for expenses
    console.log('[SW] Syncing offline expenses...');
}

// Helper: Open IndexedDB
function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('JDH_POS_Offline', 1);
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

// Notify all clients
async function notifyClients(type, data) {
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
        client.postMessage({
            type: type,
            data: data
        });
    });
}

// Message handling from clients
self.addEventListener('message', (event) => {
    if (event.data === 'skipWaiting') {
        self.skipWaiting();
    }
    
    if (event.data.type === 'clear-cache') {
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(name => caches.delete(name))
            );
        });
    }
});

console.log('[SW] Enhanced service worker loaded - Offline support enabled');
