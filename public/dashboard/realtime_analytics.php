<?php
/**
 * Real-time Dashboard Analytics
 * Advanced analytics with live updates
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

require_once __DIR__ . '/../../src/Security/SecurityBootstrap.php';
SecurityBootstrap::initialize();

// Branch filter for multi-tenant isolation
$current_branch_id = function_exists('get_current_branch_id') ? get_current_branch_id() : ($_SESSION['branch_id'] ?? 0);

$page_title = 'Real-time Analytics';
ob_start();
?>

<div id="realtime-dashboard" class="space-y-4">
    <!-- Connection Status -->
    <div id="realtime-status" class="hidden bg-gray-800 border border-gray-700 rounded-xl p-4">
        <div class="flex items-center gap-3">
            <div id="connection-indicator" class="w-3 h-3 rounded-full bg-gray-500"></div>
            <span id="status-text" class="text-sm text-gray-400">Connecting...</span>
            <button id="reconnect-btn" class="ml-auto text-xs text-amber-400 hover:text-amber-300 hidden">
                Reconnect
            </button>
        </div>
    </div>

    <!-- Live Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Live Sales Counter -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Today's Sales</p>
                    <div class="flex items-baseline gap-2">
                        <span id="live-sales-count" class="text-2xl font-bold text-white">0</span>
                        <span class="text-xs text-green-400" id="sales-trend">+0%</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center">
                    <i class="fas fa-shopping-cart text-green-400"></i>
                </div>
            </div>
            <div class="mt-2">
                <span class="text-xs text-gray-400">Revenue: </span>
                <span id="live-sales-revenue" class="text-sm text-green-400 font-medium">$0.00</span>
            </div>
        </div>

        <!-- Active Registers -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Active Registers</p>
                    <span id="live-active-registers" class="text-2xl font-bold text-white">0</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <i class="fas fa-cash-register text-blue-400"></i>
                </div>
            </div>
            <div class="mt-2">
                <span class="text-xs text-gray-400">POS terminals online</span>
            </div>
        </div>

        <!-- Inventory Alerts -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Stock Alerts</p>
                    <div class="flex items-baseline gap-2">
                        <span id="live-low-stock" class="text-xl font-bold text-amber-400">0</span>
                        <span class="text-xs text-red-400">/ <span id="live-out-stock">0</span></span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-amber-400"></i>
                </div>
            </div>
            <div class="mt-2">
                <span class="text-xs text-gray-400">Low / Out of stock</span>
            </div>
        </div>

        <!-- Average Sale Value -->
        <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider">Avg Sale Value</p>
                    <span id="live-avg-sale" class="text-2xl font-bold text-white">$0.00</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <i class="fas fa-chart-line text-purple-400"></i>
                </div>
            </div>
            <div class="mt-2">
                <span class="text-xs text-gray-400">Today's average</span>
            </div>
        </div>
    </div>

    <!-- Real-time Activity Feed -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-bolt text-amber-400"></i>
                Live Activity Feed
            </h3>
            <div class="flex items-center gap-2">
                <div id="activity-indicator" class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                <span class="text-xs text-gray-400">Live</span>
            </div>
        </div>

        <div id="activity-feed" class="space-y-2 max-h-64 overflow-y-auto">
            <div class="text-center text-gray-500 py-4">
                <i class="fas fa-circle-notch fa-spin text-amber-400 mb-2"></i>
                <p class="text-sm">Waiting for live updates...</p>
            </div>
        </div>
    </div>

    <!-- Inventory Alert Panel -->
    <div id="inventory-alerts-panel" class="bg-gray-800 border border-gray-700 rounded-xl p-4 hidden">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-amber-400"></i>
                Inventory Alerts
            </h3>
            <button id="dismiss-alerts" class="text-xs text-gray-400 hover:text-white">
                Dismiss All
            </button>
        </div>

        <div id="inventory-alerts-list" class="space-y-2">
            <!-- Alerts will be populated here -->
        </div>
    </div>
</div>

<script>
// Initialize a stub for window.realtime if the real module is not loaded
if (!window.realtime) {
    window.realtime = (function() {
        const _handlers = {};
        return {
            on: function(event, cb) {
                if (!_handlers[event]) _handlers[event] = [];
                _handlers[event].push(cb);
            },
            emit: function(event, data) {
                (_handlers[event] || []).forEach(cb => cb(data));
            },
            disconnect: function() {},
            init: function() {}
        };
    })();
}

document.addEventListener('DOMContentLoaded', function() {
    const realtimeDashboard = new RealtimeDashboard();
});

class RealtimeDashboard {
    constructor() {
        this.lastMetrics = null;
        this.activityFeed = [];
        this.pollInterval = null;
        this.init();
    }

    init() {
        this.bindRealtimeEvents();
        this.showConnectionStatus(true);
        // Start polling as fallback since SSE/WebSocket may not be available
        this.startPolling();
    }

    startPolling() {
        // Poll metrics every 30 seconds as a fallback
        this.refreshMetrics();
        this.pollInterval = setInterval(() => this.refreshMetrics(), 30000);
        this.updateConnectionStatus(true, 'Connected (polling)');
    }

    bindRealtimeEvents() {
        // Connected event
        window.realtime.on('connected', () => {
            clearInterval(this.pollInterval);
            this.updateConnectionStatus(true, 'Connected');
        });

        // Disconnected event
        window.realtime.on('disconnected', () => {
            this.updateConnectionStatus(false, 'Disconnected');
            this.startPolling();
        });

        // Sale events
        window.realtime.on('sale', (sale) => {
            this.addActivityItem('sale', `New sale: $${sale.final_amount} by ${sale.cashier || 'Staff'}`, sale.created_at);
            this.refreshMetrics();
        });

        // Metrics updates
        window.realtime.on('metrics', (metrics) => {
            this.updateMetrics(metrics);
        });

        // Inventory alerts
        window.realtime.on('inventory_alert', (alerts) => {
            this.updateInventoryAlerts(alerts);
        });

        // Ping events (heartbeat)
        window.realtime.on('ping', (data) => {
            this.updateConnectionStatus(true, 'Connected');
        });
    }

    updateConnectionStatus(connected, status) {
        const indicator = document.getElementById('connection-indicator');
        const statusText = document.getElementById('status-text');
        const reconnectBtn = document.getElementById('reconnect-btn');

        if (connected) {
            indicator.className = 'w-3 h-3 rounded-full bg-green-500';
            statusText.textContent = status;
            statusText.className = 'text-sm text-green-400';
            reconnectBtn.classList.add('hidden');
        } else {
            indicator.className = 'w-3 h-3 rounded-full bg-red-500';
            statusText.textContent = status;
            statusText.className = 'text-sm text-red-400';
            reconnectBtn.classList.remove('hidden');
        }
    }

    updateMetrics(metrics) {
        // Update sales metrics
        if (metrics.today) {
            document.getElementById('live-sales-count').textContent = metrics.today.sales_count || 0;
            document.getElementById('live-sales-revenue').textContent = '$' + (metrics.today.total_revenue || 0).toFixed(2);
            document.getElementById('live-avg-sale').textContent = '$' + (metrics.today.avg_sale || 0).toFixed(2);
        }

        // Update active registers
        if (metrics.sessions) {
            document.getElementById('live-active-registers').textContent = metrics.sessions.active_sessions || 0;
        }

        // Calculate trends if we have previous data
        if (this.lastMetrics && this.lastMetrics.today) {
            const salesTrend = this.calculateTrend(metrics.today.sales_count, this.lastMetrics.today.sales_count);
            document.getElementById('sales-trend').textContent = salesTrend;
            document.getElementById('sales-trend').className = `text-xs ${salesTrend.startsWith('+') ? 'text-green-400' : 'text-red-400'}`;
        }

        this.lastMetrics = metrics;
    }

    updateInventoryAlerts(alerts) {
        const panel = document.getElementById('inventory-alerts-panel');
        const list = document.getElementById('inventory-alerts-list');

        // Update counters
        const lowStockCount = alerts.low_stock ? alerts.low_stock.length : 0;
        const outStockCount = alerts.out_of_stock ? alerts.out_of_stock.length : 0;

        document.getElementById('live-low-stock').textContent = lowStockCount;
        document.getElementById('live-out-stock').textContent = outStockCount;

        // Show/hide panel
        if (lowStockCount > 0 || outStockCount > 0) {
            panel.classList.remove('hidden');
            list.innerHTML = '';

            // Add low stock alerts
            if (alerts.low_stock) {
                alerts.low_stock.forEach(item => {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'flex items-center justify-between p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg';
                    alertDiv.innerHTML = `
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-amber-400"></i>
                            <div>
                                <p class="text-sm text-white font-medium">${item.name}</p>
                                <p class="text-xs text-gray-400">${item.stock} remaining at ${item.branch_name}</p>
                            </div>
                        </div>
                        <button class="text-xs text-amber-400 hover:text-amber-300" onclick="this.parentElement.remove()">
                            Dismiss
                        </button>
                    `;
                    list.appendChild(alertDiv);
                });
            }

            // Add out of stock alerts
            if (alerts.out_of_stock) {
                alerts.out_of_stock.forEach(item => {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'flex items-center justify-between p-3 bg-red-500/10 border border-red-500/20 rounded-lg';
                    alertDiv.innerHTML = `
                        <div class="flex items-center gap-3">
                            <i class="fas fa-times-circle text-red-400"></i>
                            <div>
                                <p class="text-sm text-white font-medium">${item.name}</p>
                                <p class="text-xs text-gray-400">Out of stock at ${item.branch_name}</p>
                            </div>
                        </div>
                        <button class="text-xs text-red-400 hover:text-red-300" onclick="this.parentElement.remove()">
                            Dismiss
                        </button>
                    `;
                    list.appendChild(alertDiv);
                });
            }
        } else {
            panel.classList.add('hidden');
        }
    }

    addActivityItem(type, message, timestamp) {
        const feed = document.getElementById('activity-feed');
        const item = document.createElement('div');

        const icons = {
            'sale': 'fas fa-shopping-cart text-green-400',
            'return': 'fas fa-undo text-red-400',
            'default': 'fas fa-info-circle text-blue-400'
        };

        const iconClass = icons[type] || icons.default;
        const timeString = timestamp ? new Date(timestamp).toLocaleTimeString() : new Date().toLocaleTimeString();

        item.className = 'flex items-center gap-3 p-2 rounded-lg bg-gray-700/50 ';
        item.innerHTML = `
            <i class="${iconClass} text-sm"></i>
            <div class="flex-1">
                <p class="text-sm text-white">${message}</p>
                <p class="text-xs text-gray-400">${timeString}</p>
            </div>
        `;

        // Add to top of feed
        feed.insertBefore(item, feed.firstChild);

        // Keep only last 20 items
        while (feed.children.length > 20) {
            feed.removeChild(feed.lastChild);
        }

        // Remove loading message if present
        const loadingMsg = feed.querySelector('.text-center');
        if (loadingMsg) {
            loadingMsg.remove();
        }
    }

    calculateTrend(current, previous) {
        if (!previous || previous === 0) return '+0%';
        const change = ((current - previous) / previous) * 100;
        const sign = change >= 0 ? '+' : '';
        return sign + change.toFixed(1) + '%';
    }

    refreshMetrics() {
        const url = <?php echo json_encode(base_url('ajax/get_realtime_metrics.php')); ?>;
        fetch(url)
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (data && typeof data === 'object') this.updateMetrics(data);
            })
            .catch(e => {
                // Endpoint not available — silently continue with polling stub
            });
    }

    showConnectionStatus(show) {
        const statusDiv = document.getElementById('realtime-status');
        if (show) {
            statusDiv.classList.remove('hidden');
        } else {
            statusDiv.classList.add('hidden');
        }
    }
}

// Bind reconnect button
document.getElementById('reconnect-btn')?.addEventListener('click', () => {
    window.realtime.disconnect();
    setTimeout(() => window.realtime.init(), 1000);
});

// Bind dismiss alerts button
document.getElementById('dismiss-alerts')?.addEventListener('click', () => {
    document.getElementById('inventory-alerts-panel').classList.add('hidden');
});
</script>

<style>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

. {
    animation: fade-in 0.3s ease-out;
}
</style>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>