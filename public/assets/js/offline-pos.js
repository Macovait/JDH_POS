/**
 * Offline POS Sync System
 * @version 1.0.0
 */

class OfflinePOS {
    constructor() {
        this.dbName = 'JDH_POS_Offline';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        this.init();
    }
    
    async init() {
        await this.initDatabase();
        this.bindEvents();
        this.updateOnlineStatus();
        if (this.isOnline) this.syncPendingSales();
    }
    
    async initDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => { this.db = request.result; resolve(); };
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains('sales_queue')) {
                    const store = db.createObjectStore('sales_queue', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('status', 'status', { unique: false });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                }
                if (!db.objectStoreNames.contains('products_cache')) {
                    db.createObjectStore('products_cache', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('customers_cache')) {
                    db.createObjectStore('customers_cache', { keyPath: 'id' });
                }
            };
        });
    }
    
    bindEvents() {
        window.addEventListener('online', () => { this.isOnline = true; this.updateOnlineStatus(); this.syncPendingSales(); });
        window.addEventListener('offline', () => { this.isOnline = false; this.updateOnlineStatus(); });
    }
    
    updateOnlineStatus() {
        const indicator = document.getElementById('connectionStatus');
        if (indicator) {
            indicator.className = this.isOnline ? 'status-online' : 'status-offline';
            indicator.textContent = this.isOnline ? 'Online' : 'Offline Mode';
        }
    }
    
    async queueSale(saleData) {
        const sale = {
            ...saleData,
            status: 'pending',
            timestamp: Date.now(),
            sync_attempts: 0,
            offline_id: 'OFFLINE_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9)
        };
        
        const transaction = this.db.transaction(['sales_queue'], 'readwrite');
        const store = transaction.objectStore('sales_queue');
        const id = await new Promise((resolve, reject) => {
            const request = store.add(sale);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        
        this.showNotification('Sale saved offline. Will sync when online.', 'info');
        this.updatePendingCount();
        return { success: true, offline_id: sale.offline_id, queue_id: id };
    }
    
    async syncPendingSales() {
        if (this.syncInProgress || !this.isOnline) return;
        this.syncInProgress = true;
        
        try {
            const pending = await this.getPendingSales();
            if (pending.length === 0) { this.syncInProgress = false; return; }
            
            this.showNotification(`Syncing ${pending.length} offline sales...`, 'info');
            
            let successCount = 0;
            let failCount = 0;
            
            for (const sale of pending) {
                try {
                    const result = await this.syncSale(sale);
                    if (result.success) {
                        await this.markAsSynced(sale.id, result.sale_id, result.receipt_number);
                        successCount++;
                    } else {
                        await this.markAsFailed(sale.id, result.error);
                        failCount++;
                    }
                } catch (error) {
                    await this.markAsFailed(sale.id, error.message);
                    failCount++;
                }
            }
            
            if (successCount > 0) {
                this.showNotification(`${successCount} sales synced successfully!`, 'success');
            }
            if (failCount > 0) {
                this.showNotification(`${failCount} sales failed to sync.`, 'error');
            }
            
            this.updatePendingCount();
        } finally {
            this.syncInProgress = false;
        }
    }
    
    async syncSale(sale) {
        const response = await fetch('ajax/process_offline_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                offline_sale: true,
                sale_data: sale,
                offline_id: sale.offline_id
            })
        });
        
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return await response.json();
    }
    
    async getPendingSales() {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sales_queue'], 'readonly');
            const store = transaction.objectStore('sales_queue');
            const index = store.index('status');
            const request = index.getAll('pending');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    async markAsSynced(queueId, saleId, receiptNumber) {
        const transaction = this.db.transaction(['sales_queue'], 'readwrite');
        const store = transaction.objectStore('sales_queue');
        const sale = await new Promise((resolve, reject) => {
            const request = store.get(queueId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        
        if (sale) {
            sale.status = 'synced';
            sale.sale_id = saleId;
            sale.receipt_number = receiptNumber;
            sale.synced_at = Date.now();
            store.put(sale);
        }
    }
    
    async markAsFailed(queueId, error) {
        const transaction = this.db.transaction(['sales_queue'], 'readwrite');
        const store = transaction.objectStore('sales_queue');
        const sale = await new Promise((resolve, reject) => {
            const request = store.get(queueId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        
        if (sale) {
            sale.status = 'failed';
            sale.error = error;
            sale.sync_attempts = (sale.sync_attempts || 0) + 1;
            if (sale.sync_attempts >= 3) sale.status = 'permanent_fail';
            store.put(sale);
        }
    }
    
    async getPendingCount() {
        const pending = await this.getPendingSales();
        return pending.length;
    }
    
    async updatePendingCount() {
        const count = await this.getPendingCount();
        const badge = document.getElementById('offlineSaleCount');
        if (badge) badge.textContent = count > 0 ? count : '';
    }
    
    async cacheProducts(products) {
        const transaction = this.db.transaction(['products_cache'], 'readwrite');
        const store = transaction.objectStore('products_cache');
        for (const product of products) {
            product.cached_at = Date.now();
            store.put(product);
        }
    }
    
    async getCachedProduct(productId) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['products_cache'], 'readonly');
            const store = transaction.objectStore('products_cache');
            const request = store.get(productId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    async searchCachedProducts(query) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['products_cache'], 'readonly');
            const store = transaction.objectStore('products_cache');
            const request = store.getAll();
            request.onsuccess = () => {
                const products = request.result.filter(p => 
                    p.name.toLowerCase().includes(query.toLowerCase()) ||
                    p.sku.toLowerCase().includes(query.toLowerCase()) ||
                    (p.barcode && p.barcode.includes(query))
                );
                resolve(products);
            };
            request.onerror = () => reject(request.error);
        });
    }
    
    showNotification(message, type = 'info') {
        if (typeof showToast === 'function') {
            showToast(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }
    
    async getSyncStatus() {
        const pending = await this.getPendingSales();
        const failed = await new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sales_queue'], 'readonly');
            const store = transaction.objectStore('sales_queue');
            const index = store.index('status');
            const request = index.getAll('failed');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        
        const total = await new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sales_queue'], 'readonly');
            const store = transaction.objectStore('sales_queue');
            const request = store.count();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        
        return {
            isOnline: this.isOnline,
            pending: pending.length,
            failed: failed.length,
            total: total,
            syncing: this.syncInProgress
        };
    }
}

// Initialize offline POS system
let offlinePOS;
document.addEventListener('DOMContentLoaded', () => {
    offlinePOS = new OfflinePOS();
    window.offlinePOS = offlinePOS;
});

// Service Worker Registration
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
        .then(reg => console.log('Service Worker registered'))
        .catch(err => console.log('Service Worker registration failed'));
}
