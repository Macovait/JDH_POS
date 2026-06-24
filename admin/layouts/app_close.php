        </main>
    </div>
    
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
            if (btn) { btn.classList.add('active'); } else if (event && event.target) { event.target.classList.add('active'); }
            if (typeof initCharts === 'function') initCharts();
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
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.matches('input, textarea, select')) return;
            if (e.altKey && e.key === 'n') { e.preventDefault(); window.location.href = '<?php echo base_url("pos/pos-enterprise.php"); ?>'; }
            if (e.altKey && e.key === 'p') { e.preventDefault(); window.location.href = '<?php echo base_url("products/products.php"); ?>'; }
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });

        // Service Worker Registration - ENABLED for hybrid offline/online support
        // Enhanced service worker provides: caching, offline fallback, background sync
        // Requires: HTTPS or localhost (Service Workers require secure context)
        if ('serviceWorker' in navigator) {
            // Register enhanced service worker
            navigator.serviceWorker.register('<?php echo base_url("sw-enhanced.js"); ?>', {
                scope: '<?php echo base_url("/"); ?>'
            })
            .then(registration => {
                console.log('[App] Service Worker registered:', registration.scope);
                
                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New version available
                            console.log('[App] New service worker available');
                            showToast('Update available. Refresh to update.', 'info');
                        }
                    });
                });
            })
            .catch(error => {
                console.warn('[App] Service Worker registration failed:', error.message);
            });
            
            // Listen for messages from service worker
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data && event.data.type === 'sync-complete') {
                    console.log('[App] Background sync completed:', event.data.data);
                    showToast('Offline data synced!', 'success');
                }
            });
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
</body>
</html>
