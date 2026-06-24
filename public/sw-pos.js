/**
 * POS-Specific Service Worker
 * Optimized for point-of-sale offline functionality
 */

const POS_CACHE = 'jdh-pos-data-v1';
const POS_ASSETS = [
    '/pos/pos.php',
    '/ajax/get_products.php',
    '/ajax/customers.php',
    '/ajax/sales.php'
];

// Install - pre-cache POS assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(POS_CACHE)
            .then((cache) => cache.addAll(POS_ASSETS))
    );
});

// Handle push notifications
self.addEventListener('push', (event) => {
    const data = event.data?.json() || {};
    const title = data.title || 'POS Notification';
    const options = {
        body: data.body || '',
        icon: '/img/icon-192.png',
        badge: '/img/badge.png',
        data: data.url || '/'
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

// Notification click
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data));
});

// Periodic sync for inventory updates
self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'update-inventory') {
        event.waitUntil(updateInventory());
    }
});

async function updateInventory() {
    const cache = await caches.open(POS_CACHE);
    
    try {
        const response = await fetch('/ajax/get_products.php?limit=1000');
        const products = await response.json();
        
        await cache.put('/ajax/products-cache', new Response(JSON.stringify(products)));
    } catch (error) {
        console.error('Failed to update inventory cache:', error);
    }
}

// Handle messages from client
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// Cache API responses
self.addEventListener('fetch', (event) => {
    if (event.request.url.includes('/ajax/get_products.php')) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    return caches.open(POS_CACHE).then((cache) => {
                        cache.put(event.request, response.clone());
                        return response;
                    });
                })
                .catch(() => {
                    return caches.match(event.request);
                })
        );
    }
});