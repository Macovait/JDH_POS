/* ============================================================
 * POS Offline Manager
 *
 * - Registers the POS service worker for app-shell caching
 * - Detects online/offline state and shows a banner
 * - Queues failed sales submissions to IndexedDB and replays them
 *   automatically when connectivity returns
 *
 * Public API:
 *   POSOffline.queueSale(payload) → Promise<id>
 *   POSOffline.getQueue()         → Promise<Sale[]>
 *   POSOffline.drainQueue()       → Promise<number> (count synced)
 *   POSOffline.isOnline()         → boolean
 *
 * Events fired on `document`:
 *   'pos:online', 'pos:offline', 'pos:queue-changed', 'pos:sale-synced'
 * ============================================================ */
(function () {
    'use strict';

    var DB_NAME = 'jdh-pos-offline';
    var DB_VER  = 1;
    var STORE   = 'sale_queue';
    var SYNC_ENDPOINT = 'process_sale.php';

    var dbPromise = null;
    function openDb() {
        if (dbPromise) return dbPromise;
        dbPromise = new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VER);
            req.onupgradeneeded = function () {
                var db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    var store = db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('queued_at', 'queued_at');
                }
            };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror   = function () { reject(req.error); };
        });
        return dbPromise;
    }

    function tx(mode) {
        return openDb().then(function (db) {
            return db.transaction(STORE, mode).objectStore(STORE);
        });
    }

    function queueSale(payload) {
        return tx('readwrite').then(function (s) {
            return new Promise(function (resolve, reject) {
                var record = {
                    payload:    payload,
                    queued_at:  Date.now(),
                    attempts:   0
                };
                var req = s.add(record);
                req.onsuccess = function () {
                    document.dispatchEvent(new CustomEvent('pos:queue-changed'));
                    resolve(req.result);
                };
                req.onerror = function () { reject(req.error); };
            });
        });
    }

    function getQueue() {
        return tx('readonly').then(function (s) {
            return new Promise(function (resolve, reject) {
                var req = s.getAll();
                req.onsuccess = function () { resolve(req.result || []); };
                req.onerror   = function () { reject(req.error); };
            });
        });
    }

    function deleteRecord(id) {
        return tx('readwrite').then(function (s) {
            return new Promise(function (resolve) {
                var req = s.delete(id);
                req.onsuccess = function () { resolve(); };
                req.onerror   = function () { resolve(); };
            });
        });
    }

    function bumpAttempts(id, count) {
        return tx('readwrite').then(function (s) {
            return new Promise(function (resolve) {
                var get = s.get(id);
                get.onsuccess = function () {
                    var rec = get.result;
                    if (!rec) return resolve();
                    rec.attempts = count;
                    rec.last_error_at = Date.now();
                    s.put(rec).onsuccess = function () { resolve(); };
                };
                get.onerror = function () { resolve(); };
            });
        });
    }

    function postSale(payload) {
        return fetch(SYNC_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': payload.csrf_token || ''
            },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json().catch(function () { return { success: true }; });
        });
    }

    var draining = false;
    function drainQueue() {
        if (draining) return Promise.resolve(0);
        if (!navigator.onLine) return Promise.resolve(0);
        draining = true;
        var synced = 0;
        return getQueue().then(function (rows) {
            rows.sort(function (a, b) { return a.queued_at - b.queued_at; });
            return rows.reduce(function (chain, row) {
                return chain.then(function () {
                    return postSale(row.payload).then(function (resp) {
                        return deleteRecord(row.id).then(function () {
                            synced++;
                            document.dispatchEvent(new CustomEvent('pos:sale-synced', { detail: { id: row.id, response: resp } }));
                        });
                    }).catch(function (err) {
                        // Stop on first failure to preserve order
                        return bumpAttempts(row.id, (row.attempts || 0) + 1)
                            .then(function () { throw err; });
                    });
                });
            }, Promise.resolve());
        }).catch(function () { /* stop the chain */ })
        .then(function () {
            draining = false;
            if (synced > 0) document.dispatchEvent(new CustomEvent('pos:queue-changed'));
            return synced;
        });
    }

    // ====== Queue Badge ======
    function updateBadge() {
        getQueue().then(function (rows) {
            var count = rows.length;
            var el = document.getElementById('offlineQueueBadge');
            if (!el) {
                el = document.createElement('span');
                el.id = 'offlineQueueBadge';
                el.style.cssText = 'background:#EF4444;color:#fff;font-size:0.65rem;font-weight:700;padding:2px 6px;border-radius:999px;margin-left:6px;display:none;';
                var target = document.querySelector('.header-meta') || document.querySelector('.header-left');
                if (target) target.appendChild(el);
            }
            if (el) {
                el.textContent = count;
                el.style.display = count > 0 ? 'inline-block' : 'none';
            }
        });
    }
    document.addEventListener('pos:queue-changed', updateBadge);

    // ====== Banner UI ======
    var banner = null;
    function ensureBanner() {
        if (banner) return banner;
        banner = document.createElement('div');
        banner.id = 'posOfflineBanner';
        banner.style.cssText = [
            'position:fixed', 'left:50%', 'top:14px',
            'transform:translateX(-50%) translateY(-150%)',
            'background:linear-gradient(135deg,#EF4444,#DC2626)',
            'color:#fff', 'padding:8px 18px',
            'border-radius:999px', 'font-weight:600',
            'font-size:0.82rem', 'z-index:9998',
            'box-shadow:0 14px 36px rgba(239,68,68,0.35)',
            'transition:transform 0.3s ease',
            'font-family:Inter, sans-serif',
            'display:flex', 'align-items:center', 'gap:8px'
        ].join(';');
        banner.innerHTML = '<i class="fas fa-wifi" style="opacity:0.5"></i><span></span>';
        document.body.appendChild(banner);
        return banner;
    }

    function setBanner(visible, msg, color) {
        ensureBanner();
        banner.querySelector('span').textContent = msg || '';
        if (color) banner.style.background = color;
        banner.style.transform = 'translateX(-50%) translateY(' + (visible ? '0' : '-150%') + ')';
    }

    function updateUI(online) {
        document.body.classList.toggle('pos-offline', !online);
        if (online) {
            setBanner(true, 'Back online — syncing queued sales...',
                'linear-gradient(135deg,#10B981,#059669)');
            drainQueue().then(function (n) {
                setBanner(true, n > 0 ? n + ' sale(s) synced' : 'All caught up',
                    'linear-gradient(135deg,#10B981,#059669)');
                setTimeout(function () { setBanner(false); }, 2200);
            });
        } else {
            setBanner(true, 'Offline — sales will be queued',
                'linear-gradient(135deg,#EF4444,#DC2626)');
        }
        document.dispatchEvent(new CustomEvent(online ? 'pos:online' : 'pos:offline'));
    }

    // ====== Service worker registration ======
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/JDH_POS/public/sw-pos.js', { scope: '/JDH_POS/public/' })
                .then(function (reg) {
                    console.info('[POS] Service worker registered, scope:', reg.scope);
                }).catch(function (err) {
                    console.warn('[POS] SW registration failed:', err);
                });
            navigator.serviceWorker.addEventListener('message', function (e) {
                if (e.data && e.data.type === 'pos:drain-queue') drainQueue();
            });
        });
    }

    // ====== Online/offline listeners ======
    window.addEventListener('online',  function () { updateUI(true);  });
    window.addEventListener('offline', function () { updateUI(false); });

    // Initial state on load
    document.addEventListener('DOMContentLoaded', function () {
        if (!navigator.onLine) updateUI(false);
        else drainQueue();  // sync any leftover from previous session
    });

    // ====== Public API ======
    window.POSOffline = {
        queueSale:  queueSale,
        getQueue:   getQueue,
        drainQueue: drainQueue,
        isOnline:   function () { return navigator.onLine; }
    };
})();
