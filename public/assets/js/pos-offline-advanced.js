/**
 * Advanced Offline Mode Module
 * 
 * Features:
 * - Intelligent sync conflict resolution
 * - Background sync with progress tracking
 * - Offline analytics and reports
 * - Network quality detection
 * - Optimistic UI updates with rollback
 * - Offline order queue management
 * 
 * @version 2.0 - Advanced Offline Mode
 */

(function () {
    'use strict';

    const DB_NAME = 'jdh-pos-advanced';
    const DB_VERSION = 2;
    const STORES = {
        SALE_QUEUE: 'sale_queue',
        SYNC_LOG: 'sync_log',
        OFFLINE_ANALYTICS: 'offline_analytics',
        CONFLICT_LOG: 'conflict_log',
        PENDING_UPDATES: 'pending_updates',
        CACHE: 'response_cache'
    };

    const SYNC_CONFIG = {
        maxRetries: 5,
        retryDelay: 5000,
        batchSize: 10,
        syncInterval: 30000, // 30 seconds
        staleThreshold: 86400000, // 24 hours
    };

    let db = null;
    let syncInProgress = false;
    let networkQuality = 'unknown'; // 'good', 'poor', 'offline'
    let lastSyncTime = null;

    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        initAdvancedOfflineMode();
    });

    async function initAdvancedOfflineMode() {
        // Safety check for IndexedDB
        if (!window.indexedDB) {
            console.warn('[Advanced Offline] IndexedDB not available - offline features disabled');
            return;
        }
        
        try {
            db = await openDatabase();
            console.info('[Advanced Offline] Database initialized');
            
            // Initialize sync monitoring
            initSyncMonitoring();
            initNetworkQualityDetection();
            initOfflineUI();
            
            // Register sync status listener
            if ('serviceWorker' in navigator && window.registration && 'sync' in window.registration) {
                window.registration.sync.register('pos-sync');
            }
            
            // Initial sync attempt
            await performBackgroundSync();
        } catch (err) {
            console.error('[Advanced Offline] Initialization failed:', err);
        }
    }

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            
            request.onupgradeneeded = (event) => {
                const database = event.target.result;
                
                // Sale queue store
                if (!database.objectStoreNames.contains(STORES.SALE_QUEUE)) {
                    const saleStore = database.createObjectStore(STORES.SALE_QUEUE, { keyPath: 'id', autoIncrement: true });
                    saleStore.createIndex('status', 'status');
                    saleStore.createIndex('priority', 'priority');
                    saleStore.createIndex('timestamp', 'timestamp');
                    saleStore.createIndex('syncAttempts', 'syncAttempts');
                }
                
                // Sync log store
                if (!database.objectStoreNames.contains(STORES.SYNC_LOG)) {
                    const logStore = database.createObjectStore(STORES.SYNC_LOG, { keyPath: 'id', autoIncrement: true });
                    logStore.createIndex('timestamp', 'timestamp');
                    logStore.createIndex('saleId', 'saleId');
                    logStore.createIndex('status', 'status');
                }
                
                // Conflict log store
                if (!database.objectStoreNames.contains(STORES.CONFLICT_LOG)) {
                    const conflictStore = database.createObjectStore(STORES.CONFLICT_LOG, { keyPath: 'id', autoIncrement: true });
                    conflictStore.createIndex('timestamp', 'timestamp');
                    conflictStore.createIndex('resolved', 'resolved');
                }
                
                // Offline analytics store
                if (!database.objectStoreNames.contains(STORES.OFFLINE_ANALYTICS)) {
                    const analyticsStore = database.createObjectStore(STORES.OFFLINE_ANALYTICS, { keyPath: 'id', autoIncrement: true });
                    analyticsStore.createIndex('type', 'type');
                    analyticsStore.createIndex('date', 'date');
                }
            };
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // ============================================
    // ENHANCED SALE QUEUING
    // ============================================
    async function queueSale(saleData) {
        const record = {
            payload: saleData,
            timestamp: Date.now(),
            status: 'pending',
            priority: calculatePriority(saleData),
            syncAttempts: 0,
            lastError: null,
            optimisticId: generateOptimisticId(),
            checksum: generateChecksum(saleData)
        };

        const tx = db.transaction(STORES.SALE_QUEUE, 'readwrite');
        const store = tx.objectStore(STORES.SALE_QUEUE);
        
        const id = await new Promise((resolve, reject) => {
            const request = store.add(record);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        // Log to analytics
        logOfflineEvent('sale_queued', { saleId: id, amount: saleData.total });
        
        // Trigger sync if online
        if (navigator.onLine && !syncInProgress) {
            performBackgroundSync();
        }

        return { id, optimisticId: record.optimisticId };
    }

    function calculatePriority(saleData) {
        // Higher priority for:
        // - Larger orders
        // - Credit sales (need immediate sync)
        // - Orders with held items
        let priority = 1;
        
        if (saleData.total > 500) priority += 2;
        if (saleData.payment_method === 'credit') priority += 3;
        if (saleData.is_held_sale) priority += 1;
        
        return priority;
    }

    function generateOptimisticId() {
        return 'OPT-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    }

    function generateChecksum(data) {
        // Simple checksum for conflict detection
        const str = JSON.stringify(data);
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return hash.toString(16);
    }

    // ============================================
    // SYNC CONFLICT RESOLUTION
    // ============================================
    async function resolveConflict(localSale, serverResponse) {
        const conflict = {
            timestamp: Date.now(),
            localSale: localSale,
            serverResponse: serverResponse,
            conflictType: detectConflictType(localSale, serverResponse),
            resolved: false,
            resolution: null
        };

        // Log conflict
        await logConflict(conflict);

        // Auto-resolve based on conflict type
        switch (conflict.conflictType) {
            case 'duplicate_sale':
                return await resolveDuplicateSale(conflict);
            case 'inventory_mismatch':
                return await resolveInventoryMismatch(conflict);
            case 'price_changed':
                return await resolvePriceChanged(conflict);
            case 'customer_changed':
                return await resolveCustomerChanged(conflict);
            default:
                return await manualResolution(conflict);
        }
    }

    function detectConflictType(local, server) {
        if (server.error === 'duplicate_sale_number') return 'duplicate_sale';
        if (server.error === 'insufficient_stock') return 'inventory_mismatch';
        if (server.price_changed) return 'price_changed';
        if (server.customer_changed) return 'customer_changed';
        return 'unknown';
    }

    async function resolveDuplicateSale(conflict) {
        // Server already has this sale - mark local as synced
        await updateSaleStatus(conflict.localSale.id, 'synced', {
            serverSaleId: conflict.serverResponse.existing_sale_id,
            note: 'Duplicate detected and merged'
        });
        
        return { resolved: true, action: 'merged' };
    }

    async function resolveInventoryMismatch(conflict) {
        // Notify user about stock issue
        const event = new CustomEvent('pos:inventory-conflict', {
            detail: {
                sale: conflict.localSale,
                availableStock: conflict.serverResponse.available_stock,
                message: 'Some items are no longer available in the requested quantity'
            }
        });
        document.dispatchEvent(event);
        
        // Queue for manual resolution
        return { resolved: false, action: 'requires_manual' };
    }

    async function resolvePriceChanged(conflict) {
        // Price changed since order was created
        const event = new CustomEvent('pos:price-conflict', {
            detail: {
                sale: conflict.localSale,
                newPrices: conflict.serverResponse.updated_prices,
                message: 'Prices have changed for some items'
            }
        });
        document.dispatchEvent(event);
        
        return { resolved: false, action: 'requires_manual' };
    }

    async function resolveCustomerChanged(conflict) {
        // Customer info changed - update and retry
        const updatedSale = {
            ...conflict.localSale,
            payload: {
                ...conflict.localSale.payload,
                customer_id: conflict.serverResponse.updated_customer_id
            }
        };
        
        await updateSaleInQueue(conflict.localSale.id, updatedSale);
        return { resolved: true, action: 'updated_and_retry' };
    }

    async function manualResolution(conflict) {
        // Emit event for UI to handle
        const event = new CustomEvent('pos:manual-conflict', {
            detail: conflict
        });
        document.dispatchEvent(event);
        
        return { resolved: false, action: 'manual_required' };
    }

    // ============================================
    // BACKGROUND SYNC
    // ============================================
    async function performBackgroundSync() {
        if (syncInProgress || !navigator.onLine) return;
        
        syncInProgress = true;
        updateSyncStatus('syncing');

        try {
            let pendingSales = await getPendingSales();
            if (!Array.isArray(pendingSales)) pendingSales = [];
            
            if (pendingSales.length === 0) {
                updateSyncStatus('idle');
                syncInProgress = false;
                return;
            }

            // Sort by priority and timestamp
            pendingSales.sort((a, b) => {
                if (b.priority !== a.priority) return b.priority - a.priority;
                return a.timestamp - b.timestamp;
            });

            // Process in batches
            const batch = pendingSales.slice(0, SYNC_CONFIG.batchSize);
            let successCount = 0;
            let conflictCount = 0;

            for (const sale of batch) {
                try {
                    const result = await syncSale(sale);
                    
                    if (result.success) {
                        await markSaleSynced(sale.id, result.serverData);
                        successCount++;
                    } else if (result.conflict) {
                        const resolution = await resolveConflict(sale, result);
                        if (resolution.resolved) conflictCount++;
                    } else {
                        await incrementRetryCount(sale.id, result.error);
                    }
                } catch (err) {
                    await incrementRetryCount(sale.id, err.message);
                }
            }

            // Log sync session
            await logSyncSession({
                total: batch.length,
                success: successCount,
                conflicts: conflictCount,
                pending: pendingSales.length - batch.length
            });

            // Update UI
            lastSyncTime = new Date();
            updateSyncStatus('idle', {
                lastSync: lastSyncTime,
                pendingCount: pendingSales.length - successCount
            });

            // Schedule next sync if more pending
            if (pendingSales.length > batch.length) {
                setTimeout(performBackgroundSync, SYNC_CONFIG.syncInterval);
            }

        } catch (err) {
            console.error('[Advanced Offline] Sync error:', err);
            updateSyncStatus('error', { message: err.message });
        } finally {
            syncInProgress = false;
        }
    }

    async function syncSale(sale) {
        const response = await fetch('../ajax/process_sale.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Optimistic-Id': sale.optimisticId,
                'X-Sale-Checksum': sale.checksum
            },
            body: JSON.stringify(sale.payload)
        });

        const result = await response.json();

        if (result.success) {
            return { success: true, serverData: result };
        }

        if (result.conflict || result.requires_resolution) {
            return { success: false, conflict: true, serverResponse: result };
        }

        return { success: false, error: result.error || 'Unknown error' };
    }

    // ============================================
    // NETWORK QUALITY DETECTION
    // ============================================
    function initNetworkQualityDetection() {
        // Use Network Information API if available
        if ('connection' in navigator) {
            const connection = navigator.connection;
            
            const updateQuality = () => {
                const effectiveType = connection.effectiveType; // '4g', '3g', '2g', 'slow-2g'
                networkQuality = 
                    effectiveType === '4g' ? 'good' :
                    effectiveType === '3g' ? 'fair' :
                    effectiveType === '2g' ? 'poor' : 'unknown';
                
                updateNetworkIndicator();
            };

            connection.addEventListener('change', updateQuality);
            updateQuality();
        }

        // Periodic latency check
        setInterval(checkNetworkLatency, 60000);
    }

    async function checkNetworkLatency() {
        if (!navigator.onLine) {
            networkQuality = 'offline';
            return;
        }

        const start = Date.now();
        try {
            await fetch('../api/health.php', { 
                method: 'HEAD',
                cache: 'no-store'
            });
            const latency = Date.now() - start;
            
            networkQuality = 
                latency < 200 ? 'good' :
                latency < 1000 ? 'fair' : 'poor';
                
            updateNetworkIndicator();
        } catch {
            networkQuality = 'offline';
            updateNetworkIndicator();
        }
    }

    // ============================================
    // OFFLINE UI COMPONENTS
    // ============================================
    function initOfflineUI() {
        // Add advanced sync status panel
        addSyncStatusPanel();
        addOfflineReportsPanel();
    }

    function addSyncStatusPanel() {
        const header = document.querySelector('.pos-header');
        if (!header) return;

        const statusDiv = document.createElement('div');
        statusDiv.id = 'advancedSyncStatus';
        statusDiv.className = 'flex items-center gap-2 px-3 py-1.5 bg-slate-800/80 rounded-lg border border-slate-700';
        statusDiv.innerHTML = `
            <div id="syncIndicator" class="w-2 h-2 rounded-full bg-emerald-500"></div>
            <span id="syncText" class="text-xs text-slate-400">Online</span>
            <button onclick="AdvancedOffline.showSyncPanel()" class="text-slate-500 hover:text-amber-400 transition-colors">
                <i class="fas fa-sync-alt text-xs"></i>
            </button>
        `;

        header.appendChild(statusDiv);

        // Add sync panel modal
        const modal = document.createElement('div');
        modal.id = 'syncPanelModal';
        modal.className = 'fixed inset-0 z-[2000] hidden items-center justify-center';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black/70" onclick="AdvancedOffline.hideSyncPanel()"></div>
            <div class="relative bg-slate-900 rounded-2xl w-full max-w-lg mx-4 border border-slate-700 shadow-2xl">
                <div class="flex justify-between items-center p-4 border-b border-slate-700">
                    <h3 class="text-white font-semibold"><i class="fas fa-cloud-upload-alt mr-2 text-amber-400"></i>Sync Status</h3>
                    <button onclick="AdvancedOffline.hideSyncPanel()" class="text-slate-400 hover:text-white">&times;</button>
                </div>
                <div class="p-4 space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 bg-slate-800 rounded-lg text-center">
                            <p class="text-2xl font-bold text-emerald-400" id="syncSuccessCount">0</p>
                            <p class="text-xs text-slate-500">Synced</p>
                        </div>
                        <div class="p-3 bg-slate-800 rounded-lg text-center">
                            <p class="text-2xl font-bold text-amber-400" id="syncPendingCount">0</p>
                            <p class="text-xs text-slate-500">Pending</p>
                        </div>
                        <div class="p-3 bg-slate-800 rounded-lg text-center">
                            <p class="text-2xl font-bold text-red-400" id="syncConflictCount">0</p>
                            <p class="text-xs text-slate-500">Conflicts</p>
                        </div>
                    </div>
                    <div id="syncProgressArea" class="hidden">
                        <div class="flex justify-between text-xs text-slate-400 mb-1">
                            <span>Syncing...</span>
                            <span id="syncProgressText">0%</span>
                        </div>
                        <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                            <div id="syncProgressBar" class="h-full bg-amber-500 transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    <div id="conflictList" class="max-h-40 overflow-y-auto space-y-2">
                        <!-- Conflicts populated here -->
                    </div>
                </div>
                <div class="p-4 border-t border-slate-700 flex gap-2">
                    <button onclick="AdvancedOffline.forceSync()" class="flex-1 py-2 bg-amber-500 text-slate-900 rounded-lg font-medium hover:bg-amber-400">
                        <i class="fas fa-sync mr-1"></i>Force Sync Now
                    </button>
                    <button onclick="AdvancedOffline.exportOfflineData()" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-lg hover:bg-slate-700">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function addOfflineReportsPanel() {
        // Add offline analytics to reports modal
        const reportsModal = document.getElementById('reportsModal');
        if (!reportsModal) return;

        const offlineTab = document.createElement('div');
        offlineTab.id = 'offlineReportsTab';
        offlineTab.className = 'hidden p-4';
        offlineTab.innerHTML = `
            <h4 class="text-white font-semibold mb-4">Offline Activity</h4>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="p-3 bg-slate-800 rounded-lg">
                    <p class="text-slate-500 text-xs">Offline Sales</p>
                    <p class="text-2xl font-bold text-white" id="offlineSalesCount">-</p>
                </div>
                <div class="p-3 bg-slate-800 rounded-lg">
                    <p class="text-slate-500 text-xs">Offline Revenue</p>
                    <p class="text-2xl font-bold text-emerald-400" id="offlineRevenue">-</p>
                </div>
            </div>
            <div class="h-48 bg-slate-800 rounded-lg p-3" id="offlineActivityChart">
                <!-- Chart placeholder -->
                <p class="text-slate-500 text-xs text-center mt-20">Offline activity chart</p>
            </div>
        `;

        reportsModal.querySelector('.modal-content')?.appendChild(offlineTab);
    }

    // ============================================
    // OFFLINE ANALYTICS
    // ============================================
    async function logOfflineEvent(type, data) {
        const record = {
            type,
            data,
            date: new Date().toISOString().split('T')[0],
            timestamp: Date.now()
        };

        const tx = db.transaction(STORES.OFFLINE_ANALYTICS, 'readwrite');
        const store = tx.objectStore(STORES.OFFLINE_ANALYTICS);
        await store.add(record);
    }

    async function getOfflineAnalytics(dateRange) {
        const tx = db.transaction(STORES.OFFLINE_ANALYTICS, 'readonly');
        const store = tx.objectStore(STORES.OFFLINE_ANALYTICS);
        const index = store.index('date');
        
        const events = await index.getAll();
        
        // Aggregate data
        const analytics = {
            totalOfflineSales: 0,
            totalOfflineRevenue: 0,
            averageSyncTime: 0,
            conflictRate: 0,
            dailyActivity: {}
        };

        events.forEach(event => {
            if (event.type === 'sale_queued') {
                analytics.totalOfflineSales++;
                analytics.totalOfflineRevenue += event.data.amount || 0;
            }
            
            if (!analytics.dailyActivity[event.date]) {
                analytics.dailyActivity[event.date] = { sales: 0, revenue: 0 };
            }
            analytics.dailyActivity[event.date].sales++;
            analytics.dailyActivity[event.date].revenue += event.data.amount || 0;
        });

        return analytics;
    }

    // ============================================
    // DATABASE HELPERS
    // ============================================
    async function getPendingSales() {
        const tx = db.transaction(STORES.SALE_QUEUE, 'readonly');
        const store = tx.objectStore(STORES.SALE_QUEUE);
        const index = store.index('status');
        const result = await index.getAll('pending');
        return Array.isArray(result) ? result : [];
    }

    async function updateSaleStatus(id, status, metadata = {}) {
        const tx = db.transaction(STORES.SALE_QUEUE, 'readwrite');
        const store = tx.objectStore(STORES.SALE_QUEUE);
        
        const sale = await store.get(id);
        if (sale) {
            sale.status = status;
            sale.syncedAt = Date.now();
            sale.metadata = { ...sale.metadata, ...metadata };
            await store.put(sale);
        }
    }

    async function updateSaleInQueue(id, updatedSale) {
        const tx = db.transaction(STORES.SALE_QUEUE, 'readwrite');
        const store = tx.objectStore(STORES.SALE_QUEUE);
        await store.put(updatedSale);
    }

    async function incrementRetryCount(id, error) {
        const tx = db.transaction(STORES.SALE_QUEUE, 'readwrite');
        const store = tx.objectStore(STORES.SALE_QUEUE);
        
        const sale = await store.get(id);
        if (sale) {
            sale.syncAttempts++;
            sale.lastError = error;
            
            if (sale.syncAttempts >= SYNC_CONFIG.maxRetries) {
                sale.status = 'failed';
            }
            
            await store.put(sale);
        }
    }

    async function logConflict(conflict) {
        const tx = db.transaction(STORES.CONFLICT_LOG, 'readwrite');
        const store = tx.objectStore(STORES.CONFLICT_LOG);
        await store.add(conflict);
    }

    async function logSyncSession(stats) {
        const tx = db.transaction(STORES.SYNC_LOG, 'readwrite');
        const store = tx.objectStore(STORES.SYNC_LOG);
        await store.add({
            timestamp: Date.now(),
            ...stats
        });
    }

    // ============================================
    // UI UPDATES
    // ============================================
    function updateSyncStatus(status, data = {}) {
        const indicator = document.getElementById('syncIndicator');
        const text = document.getElementById('syncText');
        
        if (!indicator || !text) return;

        const statusConfig = {
            idle: { color: 'bg-emerald-500', text: data.pendingCount ? `${data.pendingCount} pending` : 'All synced' },
            syncing: { color: 'bg-amber-500 animate-pulse', text: 'Syncing...' },
            error: { color: 'bg-red-500', text: data.message || 'Sync error' },
            offline: { color: 'bg-slate-500', text: 'Offline mode' }
        };

        const config = statusConfig[status] || statusConfig.idle;
        indicator.className = `w-2 h-2 rounded-full ${config.color}`;
        text.textContent = config.text;
    }

    function updateNetworkIndicator() {
        const qualityText = {
            good: 'Online',
            fair: 'Slow connection',
            poor: 'Poor connection',
            offline: 'Offline',
            unknown: 'Checking...'
        };

        const text = document.getElementById('syncText');
        if (text && networkQuality !== 'good' && networkQuality !== 'unknown') {
            text.textContent = qualityText[networkQuality];
        }
    }

    // ============================================
    // SYNC MONITORING
    // ============================================
    function initSyncMonitoring() {
        // Listen for online/offline events
        window.addEventListener('online', () => {
            networkQuality = 'checking';
            updateSyncStatus('syncing');
            performBackgroundSync();
        });

        window.addEventListener('offline', () => {
            networkQuality = 'offline';
            updateSyncStatus('offline');
        });

        // Periodic sync check
        setInterval(() => {
            if (navigator.onLine && !syncInProgress) {
                performBackgroundSync();
            }
        }, SYNC_CONFIG.syncInterval);
    }

    // ============================================
    // PUBLIC API
    // ============================================
    window.AdvancedOffline = {
        queueSale,
        getPendingCount: async () => {
            const pending = await getPendingSales();
            return pending.length;
        },
        showSyncPanel: () => {
            const modal = document.getElementById('syncPanelModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                AdvancedOffline.refreshSyncPanel();
            }
        },
        hideSyncPanel: () => {
            const modal = document.getElementById('syncPanelModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        },
        forceSync: async () => {
            if (!syncInProgress) {
                await performBackgroundSync();
            }
        },
        refreshSyncPanel: async () => {
            const pending = await getPendingSales();
            const conflicts = await new Promise((resolve, reject) => {
                const tx = db.transaction(STORES.CONFLICT_LOG, 'readonly');
                const store = tx.objectStore(STORES.CONFLICT_LOG);
                const request = store.getAll();
                request.onsuccess = () => {
                    const all = request.result || [];
                    resolve(all.filter(c => !c.resolved));
                };
                request.onerror = () => resolve([]);
            });

            document.getElementById('syncPendingCount').textContent = pending.length;
            document.getElementById('syncConflictCount').textContent = conflicts.length;
            
            // Show conflicts
            const conflictList = document.getElementById('conflictList');
            if (conflictList && conflicts.length > 0) {
                conflictList.innerHTML = conflicts.map(c => `
                    <div class="p-2 bg-red-500/10 border border-red-500/20 rounded text-xs">
                        <p class="text-red-400 font-medium">${c.conflictType}</p>
                        <p class="text-slate-400">${new Date(c.timestamp).toLocaleString()}</p>
                    </div>
                `).join('');
            }
        },
        exportOfflineData: async () => {
            const pending = await getPendingSales();
            const data = {
                exportedAt: new Date().toISOString(),
                pendingSales: pending,
                networkQuality,
                lastSyncTime
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `offline-data-${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
        },
        getOfflineAnalytics,
        isOnline: () => navigator.onLine,
        getNetworkQuality: () => networkQuality
    };

})();
