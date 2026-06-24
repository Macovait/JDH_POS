<?php
/**
 * System Monitoring Dashboard
 * View system health, performance metrics, and logs
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

// Get system stats
$stats = [
    'php_version' => PHP_VERSION,
    'memory_usage' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true),
    'disk_free' => disk_free_space(__DIR__),
    'disk_total' => disk_total_space(__DIR__),
    'uptime' => function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0],
    'time' => date('Y-m-d H:i:s')
];

$pdo = admin_require_db(['admins']);

$dbStats = [
    'tables' => 0,
    'total_rows' => 0,
    'db_size' => 0
];

try {
    $stmt = $pdo->query("SHOW TABLE STATUS");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $dbStats['tables'] = count($tables);
    
    foreach ($tables as $table) {
        $dbStats['total_rows'] += $table['Rows'] ?? 0;
        $dbStats['db_size'] += ($table['Data_length'] ?? 0) + ($table['Index_length'] ?? 0);
    }
} catch (Exception $e) {
    // Silently fail
}

// Get today's stats
$todayStats = [
    'sales' => 0,
    'revenue' => 0,
    'users_online' => 0
];

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as sales, COALESCE(SUM(total), 0) as revenue
        FROM sales
        WHERE DATE(created_at) = CURDATE()
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $todayStats['sales'] = (int) ($result['sales'] ?? 0);
    $todayStats['revenue'] = (float) ($result['revenue'] ?? 0);
} catch (Exception $e) {
    // Silently fail
}

$page_title = 'System Monitoring | JDH POS';
$current_page = 'monitoring';
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">System Monitoring</h1>
    
    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-sm text-slate-400">PHP Version</div>
            <div class="text-xl font-bold"><?php echo htmlspecialchars($stats['php_version']); ?></div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-sm text-slate-400">Memory Usage</div>
            <div class="text-xl font-bold"><?php echo formatBytes($stats['memory_usage']); ?></div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-sm text-slate-400">Database Tables</div>
            <div class="text-xl font-bold"><?php echo number_format($dbStats['tables']); ?></div>
        </div>
        <div class="bg-slate-800 rounded-lg p-4">
            <div class="text-sm text-slate-400">Today's Sales</div>
            <div class="text-xl font-bold"><?php echo number_format($todayStats['sales']); ?></div>
        </div>
    </div>
    
    <!-- System Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-slate-800 rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">System Status</h2>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-slate-400">Server Load</span>
                    <span class="text-green-400"><?php echo number_format($stats['uptime'][0], 2); ?> / <?php echo number_format($stats['uptime'][1], 2); ?> / <?php echo number_format($stats['uptime'][2], 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Disk Usage</span>
                    <span class="<?php echo ($stats['disk_free'] / $stats['disk_total'] < 0.1) ? 'text-red-400' : 'text-green-400'; ?>">
                        <?php echo formatBytes($stats['disk_total'] - $stats['disk_free']); ?> / <?php echo formatBytes($stats['disk_total']); ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Peak Memory</span>
                    <span class="text-blue-400"><?php echo formatBytes($stats['peak_memory']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Current Time</span>
                    <span><?php echo $stats['time']; ?></span>
                </div>
            </div>
        </div>
        
        <div class="bg-slate-800 rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4">Database Stats</h2>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-slate-400">Total Tables</span>
                    <span><?php echo number_format($dbStats['tables']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Total Rows</span>
                    <span><?php echo number_format($dbStats['total_rows']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Database Size</span>
                    <span><?php echo formatBytes($dbStats['db_size']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Today's Revenue</span>
                    <span class="text-green-400">$<?php echo number_format($todayStats['revenue'], 2); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="bg-slate-800 rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Quick Actions</h2>
        <div class="flex gap-3">
            <a href="audit_logs.php" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                View Audit Logs
            </a>
            <a href="webhook_logs.php" class="bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded">
                Webhook Logs
            </a>
            <a href="alerts.php" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded">
                System Alerts
            </a>
            <button onclick="runBackup()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded">
                Run Backup Now
            </button>
            <button onclick="clearCache()" class="bg-yellow-600 hover:bg-yellow-700 px-4 py-2 rounded">
                Clear Cache
            </button>
            <a href="../../scripts/generate-api-docs.php" target="_blank" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded">
                Generate API Docs
            </a>
        </div>
        <div id="action-result" class="mt-3 text-sm hidden"></div>
    </div>
    
    <!-- Real-time Status -->
    <div class="bg-slate-800 rounded-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Real-time Connection</h2>
            <span id="connection-status" class="px-2 py-1 rounded text-xs bg-slate-600">Disconnected</span>
        </div>
        <div id="events-log" class="bg-slate-900 rounded p-3 h-48 overflow-y-auto text-sm font-mono text-slate-400">
            <div class="text-slate-500">Waiting for connection...</div>
        </div>
    </div>
</div>

<script src="<?php echo asset_url('js/realtime.js'); ?>"></script>
<script>
const statusEl = document.getElementById('connection-status');
const logEl = document.getElementById('events-log');

function addLog(message, type = 'info') {
    const div = document.createElement('div');
    div.className = `mb-1 ${type === 'error' ? 'text-red-400' : type === 'success' ? 'text-green-400' : 'text-slate-400'}`;
    div.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
    logEl.appendChild(div);
    logEl.scrollTop = logEl.scrollHeight;
}

// Connect to realtime
const client = new RealtimeClient();

client.on('connected', () => {
    statusEl.textContent = 'Connected';
    statusEl.className = 'px-2 py-1 rounded text-xs bg-green-600';
    addLog('Connected to realtime events', 'success');
});

client.on('sale', (sale) => {
    const invoice = sale.invoice_number || sale.receipt_number || sale.id || 'N/A';
    const amount = sale.total || sale.final_amount || 0;
    addLog(`New sale: #${invoice} - ${amount}`, 'success');
});

client.on('error', (error) => {
    statusEl.textContent = 'Error';
    statusEl.className = 'px-2 py-1 rounded text-xs bg-red-600';
    addLog('Connection error', 'error');
});

function runBackup() {
    const resultEl = document.getElementById('action-result');
    resultEl.classList.remove('hidden');
    resultEl.textContent = 'Running backup...';
    
    fetch('/scripts/backup.php')
        .then(r => r.text())
        .then(text => {
            resultEl.innerHTML = '<span class="text-green-400">Backup completed!</span>';
        })
        .catch(e => {
            resultEl.innerHTML = '<span class="text-red-400">Backup failed: ' + e.message + '</span>';
        });
}

function clearCache() {
    const resultEl = document.getElementById('action-result');
    resultEl.classList.remove('hidden');
    resultEl.textContent = 'Cache cleared (placeholder)';
}
</script>

<?php
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>

