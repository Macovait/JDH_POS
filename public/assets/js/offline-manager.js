/**
 * JDH POS - Offline Manager
 * Handles offline functionality, queue management, and sync operations
 */

class OfflineManager {
    constructor() {
        this.dbName = 'JDH_POS_Offline';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncQueue = [];
        
        this.init();
    }
    
    async init() {
        await this.initIndexedDB();
        this.setupEventListeners();
        this.createOfflineIndicator();
        this.updateConnectionStatus();
        
        console.log('[OfflineManager] Initialized');
    }
    
    // Initialize IndexedDB
    initIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Sync queue for offline requests
                if (!db.objectStoreNames.contains('syncQueue')) {
                    const syncStore = db.createObjectStore('syncQueue', { keyPath: 'id' });
                    syncStore.createIndex('timestamp', 'timestamp', { unique: false });
                    syncStore.createIndex('type', 'type', { unique: false });
                }
                
                // Offline sales cache
                if (!db.objectStoreNames.contains('offlineSales')) {
                    const salesStore = db.createObjectStore('offlineSales', { keyPath: 'id' });
                    salesStore.createIndex('synced', 'synced', { unique: false });
                    salesStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
                
                // Products cache for offline lookup
                if (!db.objectStoreNames.contains('productsCache')) {
                    db.createObjectStore('productsCache', { keyPath: 'id' });
                }
                
                // Customers cache
                if (!db.objectStoreNames.contains('customersCache')) {
                    db.createObjectStore('customersCache', { keyPath: 'id' });
                }
            };
        });
    }
    
    // Setup event listeners
    setupEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateConnectionStatus();
            this.showNotification('Connection restored! Syncing data...', 'success');
            this.syncOfflineData();
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateConnectionStatus();
            this.showNotification('You are offline. Sales will be saved locally.', 'warning');
        });
        
        // Listen for messages from service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.type === 'sync-complete') {
                    this.handleSyncComplete(event.data.data);
                }
            });
        }
    }
    
    // Create offline indicator UI
    createOfflineIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'offline-indicator';
        indicator.className = 'offline-indicator';
        indicator.innerHTML = `
            <div class="offline-indicator-content">
                <i class="fas fa-wifi-slash"></i>
                <span class="offline-text">Offline Mode</span>
                <span class="queue-count" id="queue-count">(0 pending)</span>
                <button onclick="offlineManager.forceSync()" class="sync-btn" id="sync-btn" style="display: none;">
                    <i class="fas fa-sync-alt"></i>
                    Sync Now
                </button>
            </div>
        `;
        
        // Add styles
        const styles = document.createElement('style');
        styles.textContent = `
            .offline-indicator {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 9999;
                background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
                color: white;
                padding: 0.5rem 1rem;
                text-align: center;
                font-weight: 500;
                font-size: 0.875rem;
                transform: translateY(-100%);
                transition: transform 0.3s ease;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            
            .offline-indicator.visible {
                transform: translateY(0);
            }
            
            .offline-indicator.online-mode {
                background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            }
            
            .offline-indicator-content {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.75rem;
            }
            
            .offline-indicator i {
                font-size: 1rem;
            }
            
            .queue-count {
                font-size: 0.75rem;
                opacity: 0.9;
            }
            
            .sync-btn {
                background: rgba(255, 255, 255, 0.2);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: white;
                padding: 0.25rem 0.75rem;
                border-radius: 4px;
                font-size: 0.75rem;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .sync-btn:hover {
                background: rgba(255, 255, 255, 0.3);
            }
            
            .sync-btn.syncing {
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            /* Toast notifications */
            .offline-toast {
                position: fixed;
                bottom: 1rem;
                right: 1rem;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s ease;
                max-width: 400px;
            }
            
            .offline-toast.success {
                background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            }
            
            .offline-toast.warning {
                background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            }
            
            .offline-toast.error {
                background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            }
            
            .offline-toast.info {
                background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        
        document.head.appendChild(styles);
        document.body.appendChild(indicator);
        
        // Add padding to body when indicator is visible
        const originalPadding = document.body.style.paddingTop;
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.target.id === 'offline-indicator') {
                    if (mutation.target.classList.contains('visible')) {
                        document.body.style.paddingTop = '40px';
                    } else {
                        document.body.style.paddingTop = originalPadding;
                    }
                }
            });
        });
        
        observer.observe(indicator, { attributes: true, attributeFilter: ['class'] });
    }
    
    // Update connection status UI
    async updateConnectionStatus() {
        const indicator = document.getElementById('offline-indicator');
        const queueCount = await this.getQueueCount();
        const queueCountEl = document.getElementById('queue-count');
        const syncBtn = document.getElementById('sync-btn');
        
        if (queueCountEl) {
            queueCountEl.textContent = `(${queueCount} pending)`;
        }
        
        if (!this.isOnline) {
            indicator.classList.add('visible');
            indicator.classList.remove('online-mode');
            if (syncBtn) syncBtn.style.display = 'none';
        } else if (queueCount > 0) {
            // Online but has pending items
            indicator.classList.add('visible', 'online-mode');
            indicator.querySelector('.offline-text').textContent = 'Sync Pending';
            if (syncBtn) syncBtn.style.display = 'inline-flex';
        } else {
            indicator.classList.remove('visible', 'online-mode');
            if (syncBtn) syncBtn.style.display = 'none';
        }
    }
    
    // Get queue count
    async getQueueCount() {
        if (!this.db) return 0;
        
        return new Promise((resolve) => {
            const transaction = this.db.transaction(['syncQueue'], 'readonly');
            const store = transaction.objectStore('syncQueue');
            const request = store.count();
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => resolve(0);
        });
    }
    
    // Queue data for sync
    async queueForSync(data) {
        if (!this.db) await this.initIndexedDB();
        
        const queueItem = {
            id: crypto.randomUUID(),
            type: data.type || 'sale',
            url: data.url,
            method: data.method || 'POST',
            headers: data.headers || { 'Content-Type': 'application/json' },
            body: data.body,
            timestamp: Date.now(),
            attempts: 0
        };
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            const request = store.add(queueItem);
            
            request.onsuccess = () => {
                console.log('[OfflineManager] Queued for sync:', queueItem.id);
                this.updateConnectionStatus();
                resolve(queueItem.id);
                
                // Try to register for background sync
                this.registerBackgroundSync();
            };
            
            request.onerror = () => reject(request.error);
        });
    }
    
    // Register for background sync
    async registerBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('sync-sales');
                console.log('[OfflineManager] Background sync registered');
            } catch (error) {
                console.error('[OfflineManager] Background sync registration failed:', error);
            }
        }
    }
    
    // Sync offline data
    async syncOfflineData() {
        if (!this.isOnline) {
            console.log('[OfflineManager] Cannot sync - offline');
            return;
        }
        
        const syncBtn = document.getElementById('sync-btn');
        if (syncBtn) syncBtn.classList.add('syncing');
        
        try {
            const queue = await this.getSyncQueue();
            
            for (const item of queue) {
                try {
                    const response = await fetch(item.url, {
                        method: item.method,
                        headers: item.headers,
                        body: JSON.stringify(item.body)
                    });
                    
                    if (response.ok) {
                        await this.removeFromQueue(item.id);
                        console.log('[OfflineManager] Synced:', item.id);
                    } else {
                        throw new Error(`HTTP ${response.status}`);
                    }
                } catch (error) {
                    console.error('[OfflineManager] Sync failed for:', item.id, error);
                    await this.incrementAttempts(item.id);
                }
            }
            
            const remaining = await this.getQueueCount();
            if (remaining === 0) {
                this.showNotification('All data synced successfully!', 'success');
            } else {
                this.showNotification(`${remaining} items remaining to sync`, 'warning');
            }
            
            this.updateConnectionStatus();
        } catch (error) {
            console.error('[OfflineManager] Sync error:', error);
            this.showNotification('Sync failed. Will retry later.', 'error');
        } finally {
            if (syncBtn) syncBtn.classList.remove('syncing');
        }
    }
    
    // Force sync (called by user)
    forceSync() {
        this.syncOfflineData();
    }
    
    // Get sync queue
    getSyncQueue() {
        return new Promise((resolve) => {
            const transaction = this.db.transaction(['syncQueue'], 'readonly');
            const store = transaction.objectStore('syncQueue');
            const request = store.getAll();
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => resolve([]);
        });
    }
    
    // Remove from queue
    removeFromQueue(id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            const request = store.delete(id);
            
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }
    
    // Increment sync attempts
    incrementAttempts(id) {
        return new Promise((resolve) => {
            const transaction = this.db.transaction(['syncQueue'], 'readwrite');
            const store = transaction.objectStore('syncQueue');
            const getRequest = store.get(id);
            
            getRequest.onsuccess = () => {
                const item = getRequest.result;
                if (item) {
                    item.attempts++;
                    // Remove if too many failed attempts
                    if (item.attempts > 5) {
                        store.delete(id);
                        console.log('[OfflineManager] Removed after max retries:', id);
                    } else {
                        store.put(item);
                    }
                }
                resolve();
            };
            
            getRequest.onerror = () => resolve();
        });
    }
    
    // Handle sync complete message from SW
    handleSyncComplete(data) {
        console.log('[OfflineManager] Sync complete:', data);
        this.updateConnectionStatus();
        this.showNotification('Background sync completed!', 'success');
    }
    
    // Show toast notification
    showNotification(message, type = 'info', duration = 3000) {
        const existingToast = document.querySelector('.offline-toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        const toast = document.createElement('div');
        toast.className = `offline-toast ${type}`;
        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-triangle' : type === 'error' ? 'fa-times-circle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    // Check if can make API request
    async canMakeRequest(url) {
        if (this.isOnline) return true;
        
        // Check if it's a critical request that can be queued
        return url.includes('/ajax/process_sale.php') || 
               url.includes('/ajax/hold_sale.php');
    }
    
    // Make API request with offline support
    async makeRequest(url, options = {}) {
        const canQueue = await this.canMakeRequest(url);
        
        if (this.isOnline) {
            try {
                const response = await fetch(url, options);
                return response;
            } catch (error) {
                if (canQueue) {
                    // Queue for later
                    await this.queueForSync({
                        url: url,
                        method: options.method || 'POST',
                        headers: options.headers,
                        body: JSON.parse(options.body)
                    });
                    
                    return new Response(
                        JSON.stringify({ 
                            success: true, 
                            queued: true, 
                            message: 'Request queued for sync' 
                        }),
                        { headers: { 'Content-Type': 'application/json' } }
                    );
                }
                throw error;
            }
        } else if (canQueue) {
            // Offline - queue it
            await this.queueForSync({
                url: url,
                method: options.method || 'POST',
                headers: options.headers,
                body: JSON.parse(options.body)
            });
            
            return new Response(
                JSON.stringify({ 
                    success: true, 
                    queued: true, 
                    message: 'Offline mode - request saved locally' 
                }),
                { headers: { 'Content-Type': 'application/json' } }
            );
        } else {
            throw new Error('Offline - cannot complete request');
        }
    }
}

// Initialize global instance
const offlineManager = new OfflineManager();

// Export for use in other modules
window.OfflineManager = OfflineManager;
window.offlineManager = offlineManager;
