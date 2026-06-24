/**
 * Jakababa POS - Offline Mode Handler
 * Manages offline/online state, local product cache, and transaction sync
 */

// Determine the base URL for AJAX calls (handles subdirectory deployments)
function getAjaxBaseUrl() {
    const path = window.location.pathname;
    // If we're in a subdirectory like /JDH_POS/public/, find the base
    const publicIndex = path.indexOf('/public/');
    if (publicIndex !== -1) {
        return path.substring(0, publicIndex) + '/public/ajax';
    }
    // Fallback: go up one level from current directory
    const lastSlash = path.lastIndexOf('/');
    const dir = path.substring(0, lastSlash);
    const parentDir = dir.substring(0, dir.lastIndexOf('/'));
    return parentDir + '/ajax';
}

// Determine the base URL for service worker (handles subdirectory deployments)
function getServiceWorkerPath() {
    const path = window.location.pathname;
    // If we're in a subdirectory like /JDH_POS/public/, find the base
    const publicIndex = path.indexOf('/public/');
    if (publicIndex !== -1) {
        return path.substring(0, publicIndex) + '/public/sw.js';
    }
    // Fallback: relative path from current location
    return '/sw.js';
}

const AJAX_BASE = getAjaxBaseUrl();
const SW_PATH = getServiceWorkerPath();

const OfflinePOS = {
    isOnline: navigator.onLine,
    productCache: [],
    offlineQueue: [],
    syncInProgress: false,
    db: null,
    DB_NAME: 'JakababaPOS',
    DB_VERSION: 2,

    // =========================================================================
    // Initialization
    // =========================================================================

    async init() {
        // Open IndexedDB
        await this.openDatabase();

        // Register service worker only if explicitly enabled
        if (localStorage.getItem('sw_enabled') === 'true') {
            await this.registerServiceWorker();
        } else {
            console.log('[OfflinePOS] Service Worker registration skipped (disabled by default)');
        }

        // Load cached products
        await this.loadCachedProducts();

        // Load offline queue
        await this.loadOfflineQueue();
        this.updateOfflineCount();

        // Prime caches on first load when online
        if (this.isOnline) {
            await this.syncProductCache();
        }

        // Set up network listeners
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Start periodic sync check
        setInterval(() => this.checkSync(), 30000);

        // Initial status check
        this.updateConnectionStatus();

        console.log('[OfflinePOS] Initialized. Online:', this.isOnline);
    },

    // =========================================================================
    // Service Worker Registration
    // =========================================================================

    async registerServiceWorker() {
        if (localStorage.getItem('sw_enabled') !== 'true') {
            console.log('[OfflinePOS] Service Worker registration skipped because it is disabled by default');
            return null;
        }

        if ('serviceWorker' in navigator) {
            try {
                const swScope = SW_PATH.substring(0, SW_PATH.lastIndexOf('/')) + '/';
                const registration = await navigator.serviceWorker.register(SW_PATH, {
                    scope: swScope
                });

                console.log('[OfflinePOS] Service Worker registered at:', SW_PATH);
                console.log('[OfflinePOS] Service Worker scope:', registration.scope);

                // Listen for messages from SW
                navigator.serviceWorker.addEventListener('message', (event) => {
                    this.handleServiceWorkerMessage(event.data);
                });

                // Request background sync permission
                if ('sync' in registration) {
                    console.log('[OfflinePOS] Background Sync supported');
                }

                return registration;
            } catch (error) {
                console.warn('[OfflinePOS] Service Worker registration failed:', error);
            }
        }
        return null;
    },

    // =========================================================================
    // IndexedDB Operations
    // =========================================================================

    async openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);

            request.onerror = () => reject(request.error);

            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Products store
                if (!db.objectStoreNames.contains('products')) {
                    const productStore = db.createObjectStore('products', { keyPath: 'id' });
                    productStore.createIndex('sku', 'sku', { unique: false });
                    productStore.createIndex('barcode', 'barcode', { unique: false });
                    productStore.createIndex('name', 'name', { unique: false });
                    productStore.createIndex('category_id', 'category_id', { unique: false });
                }

                // Offline sales queue
                if (!db.objectStoreNames.contains('offline_sales')) {
                    const salesStore = db.createObjectStore('offline_sales', { keyPath: 'id', autoIncrement: true });
                    salesStore.createIndex('timestamp', 'timestamp', { unique: false });
                    salesStore.createIndex('synced', 'synced', { unique: false });
                }

                // Cached categories
                if (!db.objectStoreNames.contains('categories')) {
                    db.createObjectStore('categories', { keyPath: 'id' });
                }

                // Settings cache
                if (!db.objectStoreNames.contains('settings')) {
                    db.createObjectStore('settings', { keyPath: 'key' });
                }
            };
        });
    },

    async dbPut(storeName, data) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.put(data);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    async dbGet(storeName, key) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    async dbGetAll(storeName) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    },

    async dbDelete(storeName, key) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.delete(key);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },

    async dbClear(storeName) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            const request = store.clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },

    // =========================================================================
    // Product Cache Management
    // =========================================================================

    async loadCachedProducts() {
        try {
            this.productCache = await this.dbGetAll('products');
            console.log('[OfflinePOS] Loaded', this.productCache.length, 'cached products');
        } catch (e) {
            console.warn('[OfflinePOS] Failed to load cached products:', e);
            this.productCache = [];
        }
    },

    async cacheProducts(products) {
        try {
            for (const product of products) {
                await this.dbPut('products', product);
            }
            this.productCache = products;
            console.log('[OfflinePOS] Cached', products.length, 'products');

            // Also tell service worker about the cached products
            if (navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    type: 'CACHE_PRODUCTS',
                    products: products
                });
            }
        } catch (e) {
            console.error('[OfflinePOS] Failed to cache products:', e);
        }
    },

    async syncProductCache() {
        if (!this.isOnline) return;

        try {
            const response = await fetch(AJAX_BASE + '/get_products.php?limit=10000&minimal=1');
            if (response.ok) {
                const data = await response.json();
                const products = data.products || data || [];
                await this.cacheProducts(products);
            }
        } catch (e) {
            console.warn('[OfflinePOS] Product sync failed:', e);
        }
    },

    findProductOffline(query) {
        query = query.toLowerCase().trim();
        return this.productCache.filter(p => {
            return (p.name && p.name.toLowerCase().includes(query)) ||
                   (p.sku && p.sku.toLowerCase().includes(query)) ||
                   (p.barcode && p.barcode.toLowerCase() === query);
        });
    },

    getProductById(id) {
        return this.productCache.find(p => p.id === id);
    },

    getProductByBarcode(barcode) {
        barcode = barcode.trim();
        return this.productCache.find(p =>
            p.barcode === barcode || p.sku === barcode
        );
    },

    // =========================================================================
    // Offline Transaction Queue
    // =========================================================================

    async loadOfflineQueue() {
        try {
            this.offlineQueue = await this.dbGetAll('offline_sales');
            this.offlineQueue = this.offlineQueue.filter(item => !item.synced);
            console.log('[OfflinePOS] Loaded', this.offlineQueue.length, 'pending offline transactions');
        } catch (e) {
            this.offlineQueue = [];
        }
    },

    async saveOfflineSale(saleData) {
        const offlineSale = {
            ...saleData,
            timestamp: Date.now(),
            synced: false,
            offline_id: 'OFF-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9)
        };

        try {
            await this.dbPut('offline_sales', offlineSale);
            this.offlineQueue.push(offlineSale);

            console.log('[OfflinePOS] Saved offline sale:', offlineSale.offline_id);
            this.updateOfflineCount();

            return offlineSale;
        } catch (e) {
            console.error('[OfflinePOS] Failed to save offline sale:', e);
            throw e;
        }
    },

    async syncOfflineSales() {
        if (!this.isOnline || this.syncInProgress) return;

        this.syncInProgress = true;
        let syncedCount = 0;
        let failedCount = 0;

        console.log('[OfflinePOS] Starting sync of', this.offlineQueue.length, 'offline sales');

        for (const sale of [...this.offlineQueue]) {
            try {
                const response = await fetch(AJAX_BASE + '/process_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ...sale,
                        offline_id: sale.offline_id,
                        is_offline_sync: true
                    })
                });

                if (response.ok) {
                    const result = await response.json();
                    sale.synced = true;
                    sale.server_id = result.sale_id;
                    await this.dbPut('offline_sales', sale);
                    syncedCount++;
                    console.log('[OfflinePOS] Synced:', sale.offline_id, '-> Server ID:', result.sale_id);
                } else {
                    failedCount++;
                    console.warn('[OfflinePOS] Sync failed for:', sale.offline_id);
                }
            } catch (e) {
                failedCount++;
                console.error('[OfflinePOS] Sync error:', e);
                break; // Stop syncing if we lose connection
            }
        }

        // Remove synced items from queue
        this.offlineQueue = this.offlineQueue.filter(s => !s.synced);
        this.updateOfflineCount();

        this.syncInProgress = false;

        // Dispatch sync complete event
        window.dispatchEvent(new CustomEvent('offline-sync-complete', {
            detail: { synced: syncedCount, failed: failedCount }
        }));

        console.log('[OfflinePOS] Sync complete. Synced:', syncedCount, 'Failed:', failedCount);

        return { synced: syncedCount, failed: failedCount };
    },

    // =========================================================================
    // Network State Handlers
    // =========================================================================

    handleOnline() {
        this.isOnline = true;
        this.updateConnectionStatus();
        console.log('[OfflinePOS] Connection restored');

        // Show notification
        this.showNotification('Connection restored. Syncing offline transactions...', 'success');

        // Auto-sync
        this.syncOfflineSales().then(result => {
            if (result && result.synced > 0) {
                this.showNotification(
                    `Synced ${result.synced} offline transaction(s) successfully.`,
                    'success'
                );
            }
        });

        // Sync product cache
        this.syncProductCache();
    },

    handleOffline() {
        this.isOnline = false;
        this.updateConnectionStatus();
        console.log('[OfflinePOS] Connection lost - entering offline mode');

        this.showNotification(
            'You are now offline. Sales will be saved locally and synced when connection is restored.',
            'warning'
        );
    },

    updateConnectionStatus() {
        // Update UI indicators
        const indicators = document.querySelectorAll('.connection-status');
        indicators.forEach(el => {
            el.classList.toggle('online', this.isOnline);
            el.classList.toggle('offline', !this.isOnline);
            el.textContent = this.isOnline ? 'Online' : 'Offline';
        });

        const statusBars = document.querySelectorAll('.status-bar');
        statusBars.forEach(el => {
            el.classList.toggle('bg-green-500', this.isOnline);
            el.classList.toggle('bg-red-500', !this.isOnline);
        });

        // Update body class
        document.body.classList.toggle('offline-mode', !this.isOnline);
        document.body.classList.toggle('online-mode', this.isOnline);
    },

    async checkSync() {
        if (this.isOnline && this.offlineQueue.length > 0) {
            await this.syncOfflineSales();
        }
    },

    updateOfflineCount() {
        const count = this.offlineQueue.length;
        const badges = document.querySelectorAll('.offline-count-badge');
        badges.forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? 'inline-flex' : 'none';
        });
    },

    // =========================================================================
    // Service Worker Message Handler
    // =========================================================================

    handleServiceWorkerMessage(data) {
        switch (data.type) {
            case 'SYNC_COMPLETE':
                console.log('[OfflinePOS] SW sync complete:', data);
                this.loadOfflineQueue().then(() => this.updateOfflineCount());
                break;
        }
    },

    // =========================================================================
    // Notification Helper
    // =========================================================================

    showNotification(message, type = 'info') {
        // Use existing toast system if available
        if (typeof showToast === 'function') {
            showToast(message, type);
            return;
        }

        // Fallback: create inline notification
        const container = document.getElementById('toastContainer') || document.body;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        toast.style.cssText = `
            position: fixed; bottom: 1rem; right: 1rem; z-index: 9999;
            background: rgba(31,41,55,0.95); backdrop-filter: blur(10px);
            border: 1px solid #374151; border-radius: 0.5rem; padding: 0.75rem 1.5rem;
            color: white; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;
            animation: slideIn 0.3s ease;
            border-left: 4px solid ${type === 'success' ? '#10B981' : type === 'warning' ? '#FBBF24' : '#3B82F6'};
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    },

    // =========================================================================
    // Public API
    // =========================================================================

    getPendingCount() {
        return this.offlineQueue.length;
    },

    getOnlineStatus() {
        return this.isOnline;
    },

    getCachedProductCount() {
        return this.productCache.length;
    },

    async forceSyncNow() {
        if (this.isOnline) {
            return await this.syncOfflineSales();
        }
        return { synced: 0, failed: 0, message: 'Offline' };
    },

    async clearOfflineData() {
        await this.dbClear('offline_sales');
        this.offlineQueue = [];
        this.updateOfflineCount();
        console.log('[OfflinePOS] Cleared all offline data');
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => OfflinePOS.init());
} else {
    OfflinePOS.init();
}

// Export for use in other modules
window.OfflinePOS = OfflinePOS;
