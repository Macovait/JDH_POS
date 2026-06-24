</main>
    </div>

    <style id="laravel-crud-overrides">
        body.laravel-shell {
            font-size: 14px;
        }

        body.laravel-shell .enhanced-header,
        body.laravel-shell .breadcrumb-nav {
            background: var(--laravel-panel);
            border-color: var(--laravel-border);
            box-shadow: 0 18px 42px rgba(2, 6, 23, 0.18);
        }

        body.laravel-shell .search-input-wrapper,
        body.laravel-shell .branch-btn,
        body.laravel-shell .branch-display,
        body.laravel-shell .header-action,
        body.laravel-shell .user-menu-btn,
        body.laravel-shell .dropdown-menu {
            border-radius: 14px;
            border-color: var(--laravel-border);
        }

        body.laravel-shell .branch-name,
        body.laravel-shell .user-name,
        body.laravel-shell .dropdown-item,
        body.laravel-shell .search-result-title {
            font-size: 0.82rem;
        }

        body.laravel-shell .user-role,
        body.laravel-shell .dropdown-header,
        body.laravel-shell .search-result-subtitle,
        body.laravel-shell .item-meta,
        body.laravel-shell .notification-text,
        body.laravel-shell .breadcrumb-text {
            font-size: 0.75rem;
        }

        body.laravel-shell .content-area,
        body.laravel-shell main {
            font-size: 0.875rem;
            color: var(--laravel-text);
        }

        body.laravel-shell main h1,
        body.laravel-shell main .text-3xl {
            font-size: 1.85rem;
            line-height: 1.15;
            letter-spacing: -0.025em;
            color: var(--laravel-heading);
        }

        body.laravel-shell main h2,
        body.laravel-shell main .text-2xl {
            font-size: 1.4rem;
            line-height: 1.2;
            letter-spacing: -0.02em;
            color: var(--laravel-heading);
        }

        body.laravel-shell main h3,
        body.laravel-shell main .text-xl {
            font-size: 1.08rem;
            line-height: 1.3;
            color: var(--laravel-heading);
        }

        body.laravel-shell main .text-lg {
            font-size: 0.96rem;
        }

        body.laravel-shell main .text-sm {
            font-size: 0.8rem;
        }

        body.laravel-shell main .text-xs {
            font-size: 0.72rem;
        }

        body.laravel-shell main label {
            color: #CBD5E1;
            font-size: 0.78rem;
            font-weight: 600;
        }

        body.laravel-shell main .text-gray-400,
        body.laravel-shell main .text-slate-400,
        body.laravel-shell main .text-slate-500,
        body.laravel-shell main .text-gray-500 {
            color: var(--laravel-muted);
        }

        body.laravel-shell main .glass-card,
        body.laravel-shell main .stat-card,
        body.laravel-shell main .card-laravel,
        body.laravel-shell main .card-modern,
        body.laravel-shell main .stat-laravel,
        body.laravel-shell main .bulk-actions,
        body.laravel-shell main .modal-content {
            background: var(--laravel-panel);
            border: 1px solid var(--laravel-border);
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(2, 6, 23, 0.18);
            backdrop-filter: blur(14px);
        }

        body.laravel-shell main .glass-card:hover,
        body.laravel-shell main .stat-card:hover,
        body.laravel-shell main .card-modern:hover,
        body.laravel-shell main .stat-laravel:hover {
            border-color: rgba(245, 158, 11, 0.22);
            box-shadow: 0 24px 48px rgba(2, 6, 23, 0.24);
            transform: translateY(-1px);
        }

        body.laravel-shell main .btn-laravel,
        body.laravel-shell main .btn-primary,
        body.laravel-shell main .btn-secondary,
        body.laravel-shell main .btn-danger,
        body.laravel-shell main .btn-success,
        body.laravel-shell main .btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 38px;
            padding: 0.65rem 1rem;
            border-radius: 12px;
            font-size: 0.8125rem;
            font-weight: 600;
            line-height: 1.2;
            transition: all 0.18s ease;
            text-decoration: none;
            border: 1px solid transparent;
            box-shadow: none;
        }

        body.laravel-shell main .btn-laravel.btn-primary,
        body.laravel-shell main .btn-primary {
            background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
            color: #111827;
            border-color: rgba(245, 158, 11, 0.18);
        }

        body.laravel-shell main .btn-laravel.btn-primary:hover,
        body.laravel-shell main .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(245, 158, 11, 0.24);
        }

        body.laravel-shell main .btn-laravel.btn-secondary,
        body.laravel-shell main .btn-secondary,
        body.laravel-shell main .btn-ghost {
            background: rgba(30, 41, 59, 0.86);
            color: #E2E8F0;
            border-color: var(--laravel-border);
        }

        body.laravel-shell main .btn-laravel.btn-secondary:hover,
        body.laravel-shell main .btn-secondary:hover,
        body.laravel-shell main .btn-ghost:hover {
            border-color: rgba(245, 158, 11, 0.22);
            color: #F8FAFC;
            background: rgba(30, 41, 59, 0.96);
        }

        body.laravel-shell main .btn-laravel.btn-danger,
        body.laravel-shell main .btn-danger {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: #FFFFFF;
            border-color: rgba(239, 68, 68, 0.18);
        }

        body.laravel-shell main .btn-laravel.btn-success,
        body.laravel-shell main .btn-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: #FFFFFF;
            border-color: rgba(16, 185, 129, 0.18);
        }

        body.laravel-shell main input[type="text"],
        body.laravel-shell main input[type="email"],
        body.laravel-shell main input[type="password"],
        body.laravel-shell main input[type="number"],
        body.laravel-shell main input[type="search"],
        body.laravel-shell main input[type="date"],
        body.laravel-shell main input[type="datetime-local"],
        body.laravel-shell main input[type="time"],
        body.laravel-shell main input[type="url"],
        body.laravel-shell main input[type="tel"],
        body.laravel-shell main select,
        body.laravel-shell main textarea,
        body.laravel-shell main .input-field,
        body.laravel-shell main .select-field,
        body.laravel-shell main .form-input-modern,
        body.laravel-shell main .form-select-modern,
        body.laravel-shell main .form-textarea-modern {
            background: rgba(11, 18, 32, 0.92) !important;
            border: 1px solid var(--laravel-border) !important;
            color: var(--laravel-text) !important;
            border-radius: 12px !important;
            min-height: 40px;
            padding: 0.68rem 0.9rem !important;
            font-size: 0.82rem !important;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02);
        }

        body.laravel-shell main textarea,
        body.laravel-shell main .form-textarea-modern {
            min-height: 110px;
        }

        body.laravel-shell main select,
        body.laravel-shell main .select-field,
        body.laravel-shell main .form-select-modern {
            padding-right: 2.5rem !important;
            background-position: right 0.85rem center !important;
        }

        body.laravel-shell main input[type="text"]:focus,
        body.laravel-shell main input[type="email"]:focus,
        body.laravel-shell main input[type="password"]:focus,
        body.laravel-shell main input[type="number"]:focus,
        body.laravel-shell main input[type="search"]:focus,
        body.laravel-shell main input[type="date"]:focus,
        body.laravel-shell main input[type="datetime-local"]:focus,
        body.laravel-shell main input[type="time"]:focus,
        body.laravel-shell main input[type="url"]:focus,
        body.laravel-shell main input[type="tel"]:focus,
        body.laravel-shell main select:focus,
        body.laravel-shell main textarea:focus,
        body.laravel-shell main .input-field:focus,
        body.laravel-shell main .select-field:focus,
        body.laravel-shell main .form-input-modern:focus,
        body.laravel-shell main .form-select-modern:focus,
        body.laravel-shell main .form-textarea-modern:focus {
            border-color: rgba(245, 158, 11, 0.56) !important;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1) !important;
            outline: none !important;
        }

        body.laravel-shell main input::placeholder,
        body.laravel-shell main textarea::placeholder {
            color: #64748B !important;
        }

        body.laravel-shell main table,
        body.laravel-shell main .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.82rem;
        }

        body.laravel-shell main table thead th,
        body.laravel-shell main .data-table th {
            background: rgba(15, 23, 42, 0.92);
            color: var(--laravel-muted);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.82rem 0.95rem;
            border-bottom: 1px solid var(--laravel-border);
        }

        body.laravel-shell main table tbody td,
        body.laravel-shell main .data-table td {
            padding: 0.82rem 0.95rem;
            color: var(--laravel-text);
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            vertical-align: middle;
        }

        body.laravel-shell main table tbody tr:hover td,
        body.laravel-shell main .data-table tbody tr:hover td {
            background: rgba(245, 158, 11, 0.04);
        }

        body.laravel-shell main table tbody tr:last-child td,
        body.laravel-shell main .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        body.laravel-shell main .overflow-hidden,
        body.laravel-shell main .overflow-x-auto {
            border-radius: 18px;
        }

        body.laravel-shell main .badge,
        body.laravel-shell main .status-badge {
            font-size: 0.72rem;
            padding: 0.32rem 0.75rem;
            border-radius: 999px;
        }

        body.laravel-shell main .bulk-actions {
            top: 4.5rem;
            padding: 0.8rem 1rem;
        }

        body.laravel-shell main .currency {
            font-size: 0.94em;
        }

        @media (max-width: 1024px) {
            body.laravel-shell .content-area {
                padding: 1rem 1rem 1.25rem;
            }

            body.laravel-shell .breadcrumb-nav {
                margin: 0.85rem 1rem 0;
            }
        }

        @media (max-width: 640px) {
            body.laravel-shell main h1,
            body.laravel-shell main .text-3xl {
                font-size: 1.55rem;
            }

            body.laravel-shell main h2,
            body.laravel-shell main .text-2xl {
                font-size: 1.22rem;
            }

            body.laravel-shell .content-area {
                font-size: 0.84rem;
            }
        }
    </style>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        function switchTab(name, btn) {
            document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.add('hidden'); });
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            var el = document.getElementById('tab-' + name);
            if (el) el.classList.remove('hidden');
            if (btn) { btn.classList.add('active'); } else if (window.event && window.event.target) { window.event.target.classList.add('active'); }
            if (typeof initCharts === 'function') initCharts();
        }

        function toggleSubmenu(btn) {

            btn.classList.toggle('open');

            var menu = btn.nextElementSibling;

            if (menu && menu.classList.contains('slide-menu')) {

                menu.classList.toggle('open');

            }

        }



        window.addEventListener('resize', function() {
            if (window.innerWidth > 1024) {
                document.getElementById('sidebar').classList.remove('open');
                document.getElementById('sidebarOverlay').classList.remove('show');
            }
        });
        
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer') || createToastContainer();
            const toast = document.createElement('div');
            const icons = { success: 'fa-check-circle', error: 'fa-circle-xmark', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span class="flex-1">${message}</span><button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100 transition"><i class="fas fa-xmark"></i></button>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'fixed bottom-4 right-4 space-y-2 z-50';
            document.body.appendChild(container);
            return container;
        }
        
        // Sticky header scroll shadow
        (function() {
            var header = document.getElementById('enhancedHeader');
            if (!header) return;
            function updateHeader() {
                if (window.scrollY > 8) {
                    header.classList.add('is-scrolled');
                } else {
                    header.classList.remove('is-scrolled');
                }
            }
            window.addEventListener('scroll', updateHeader, { passive: true });
            updateHeader();
        })();

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.matches('input, textarea, select')) return;
            if (e.altKey && e.key === 'n') { e.preventDefault(); window.location.href = '<?php echo base_url("pos/pos-enterprise.php"); ?>'; }
            if (e.altKey && e.key === 'p') { e.preventDefault(); window.location.href = '<?php echo base_url("products/products.php"); ?>'; }
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });

        // Service Worker Registration - ENABLED for cache cleanup
        if ('serviceWorker' in navigator) {
            try {
                navigator.serviceWorker.register('/JDH_POS/public/sw-enhanced.js', {
                    scope: '/'
                }).then((registration) => {
                    console.log('[App] Service Worker registered:', registration.scope);
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (!newWorker) return;

                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                console.log('[App] New service worker available');
                                showToast('Update available. Refresh to update.', 'info');
                            }
                        });
                    });
                }).catch((error) => {
                    console.warn('[App] Service Worker registration failed:', error.message);
                });

                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.type === 'sync-complete') {
                        console.log('[App] Background sync completed:', event.data.data);
                        showToast('Offline data synced!', 'success');
                    }
                });

                const cleanupLegacyServiceWorkers = () => {
                    const legacyScripts = ['service-worker.js', 'sw.js', 'sw-pos.js'];
                    const unregisterPromises = [];

                    return navigator.serviceWorker.getRegistrations()
                        .then((registrations) => {
                            registrations.forEach((registration) => {
                                try {
                                    const scriptURL = (registration.active && registration.active.scriptURL)
                                        || (registration.installing && registration.installing.scriptURL)
                                        || (registration.waiting && registration.waiting.scriptURL)
                                        || '';

                                    const isLegacy = scriptURL && legacyScripts.some((name) => scriptURL.indexOf(name) !== -1);

                                    if (isLegacy) {
                                        unregisterPromises.push(
                                            registration.unregister()
                                                .then(() => {
                                                    console.log('[SW Cleanup] Unregistered legacy service worker:', scriptURL);
                                                    return true;
                                                })
                                                .catch((err) => {
                                                    console.warn('[SW Cleanup] Unregister failed:', err);
                                                    return false;
                                                })
                                        );
                                    }
                                } catch (e) {
                                    console.warn('[SW Cleanup] Failed to inspect registration:', e);
                                }
                            });

                            return Promise.all(unregisterPromises).then(() => {
                                if (unregisterPromises.length) {
                                    console.log('[SW Cleanup] Legacy service workers unregistered');
                                }
                            });
                        })
                        .catch((err) => {
                            console.warn('[SW Cleanup] getRegistrations failed:', err);
                        });
                };

                cleanupLegacyServiceWorkers();

                if ('caches' in window) {
                    caches.keys()
                        .then((cacheNames) => Promise.all(
                            cacheNames
                                .filter((name) => name.indexOf('jdh-pos-') === 0 || name.indexOf('pos-') === 0)
                                .map((name) => {
                                    console.log('[Cache Cleanup] Deleting stale cache:', name);
                                    return caches.delete(name);
                                })
                        ))
                        .catch((err) => {
                            console.warn('[Cache Cleanup] Failed to remove stale caches:', err);
                        });
                }

                (function forceCleanup(){
                    try {
                        if (localStorage.getItem('sw_force_cleanup') !== 'true') return;

                        navigator.serviceWorker.getRegistrations()
                            .then((registrations) => Promise.all(
                                registrations.map((registration) => registration.unregister().catch(() => false))
                            ))
                            .then(() => {
                                localStorage.removeItem('sw_force_cleanup');
                                console.log('[SW Cleanup] Unregistered service workers.');
                            })
                            .catch((err) => {
                                console.warn('[SW Cleanup]', err);
                            });
                    } catch (e) {
                        console.warn('[SW Cleanup]', e);
                    }
                })();
            } catch (e) {
                console.warn('[App] Service Worker error:', e);
            }
        } else {
            console.log('[App] Service Worker skipped: in iframe or not supported');
        }

        // Load offline manager for queue handling
        const offlineManagerScript = document.createElement('script');
        offlineManagerScript.src = '<?php echo base_url("assets/js/offline-manager.js"); ?>';
        offlineManagerScript.async = true;
        offlineManagerScript.onload = () => {
            console.log('[App] Offline manager loaded');
        };
        document.head.appendChild(offlineManagerScript);
    </script>

        <script>
        // Auto-wrap wide tables in a .table-responsive container so they scroll on small viewports.
        (function(){
            function wrapTable(table){
                if (!table || table.closest('.table-responsive')) return;
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }

            function shouldWrap(table, container){
                try {
                    const parentWidth = container.clientWidth || document.documentElement.clientWidth || window.innerWidth;
                    return table.scrollWidth > parentWidth || table.classList.contains('force-responsive');
                } catch (e) { return false; }
            }

            document.addEventListener('DOMContentLoaded', function(){
                // target tables inside main content areas and common card containers
                const containers = document.querySelectorAll('.content-area, main, .card-laravel, .card-modern, .glass-card, .report, .modal');
                const seen = new Set();
                containers.forEach(container => {
                    container.querySelectorAll('table').forEach(table => {
                        if (seen.has(table)) return; seen.add(table);
                        if (table.classList.contains('no-responsive')) return;
                        if (shouldWrap(table, container)) wrapTable(table);
                    });
                });
                // fallback: wrap any table marked with .force-responsive anywhere
                document.querySelectorAll('table.force-responsive').forEach(t => wrapTable(t));
            });
        })();
        </script>
</body>
</html>
