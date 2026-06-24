<?php
/**
 * System Health Dashboard
 * Monitor system status, performance metrics, and health checks
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Enforce admin access (super admin or system.admin permission)
require_login();
if (!is_super_admin() && !check_permission('system.admin')) {
    redirect(base_url('dashboard/home.php?error=unauthorized'));
}

safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

// Collect system metrics
$healthChecks = [
    'database' => checkDatabaseHealth($pdo),
    'disk_space' => checkDiskSpace(),
    'php_version' => checkPhpVersion(),
    'memory' => checkMemoryUsage(),
    'sessions' => checkActiveSessions($pdo),
    'errors' => checkRecentErrors(),
    'backups' => checkBackupStatus(),
];

// Helper functions
function checkDatabaseHealth($pdo) {
    try {
        $start = microtime(true);
        $stmt = $pdo->query("SELECT 1");
        $stmt->fetch();
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        
        // Check table sizes
        $stmt = $pdo->query("
            SELECT 
                table_name,
                ROUND(data_length / 1024 / 1024, 2) as size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            ORDER BY data_length DESC
            LIMIT 10
        ");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'status' => 'healthy',
            'response_time_ms' => $responseTime,
            'tables' => $tables,
            'message' => "Response time: {$responseTime}ms"
        ];
    } catch (Exception $e) {
        return [
            'status' => 'critical',
            'message' => $e->getMessage()
        ];
    }
}

function checkDiskSpace() {
    $free = disk_free_space('.');
    $total = disk_total_space('.');
    $used = $total - $free;
    $percentUsed = round(($used / $total) * 100, 2);
    
    return [
        'status' => $percentUsed > 90 ? 'warning' : ($percentUsed > 95 ? 'critical' : 'healthy'),
        'free_gb' => round($free / 1024 / 1024 / 1024, 2),
        'total_gb' => round($total / 1024 / 1024 / 1024, 2),
        'used_percent' => $percentUsed,
        'message' => "{$percentUsed}% used"
    ];
}

function checkPhpVersion() {
    $version = PHP_VERSION;
    $supported = version_compare($version, '7.4.0', '>=');
    
    return [
        'status' => $supported ? 'healthy' : 'warning',
        'version' => $version,
        'message' => $supported ? 'Supported' : 'Update recommended'
    ];
}

function checkMemoryUsage() {
    $memoryLimit = ini_get('memory_limit');
    $memoryUsage = memory_get_usage(true);
    $peakUsage = memory_get_peak_usage(true);
    
    return [
        'status' => 'healthy',
        'limit' => $memoryLimit,
        'current_mb' => round($memoryUsage / 1024 / 1024, 2),
        'peak_mb' => round($peakUsage / 1024 / 1024, 2),
        'message' => 'Memory usage normal'
    ];
}

function checkActiveSessions($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT user_id) as active_users
            FROM activity_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $activeUsers = $stmt->fetchColumn();
        
        return [
            'status' => 'healthy',
            'active_users' => $activeUsers,
            'message' => "{$activeUsers} active users"
        ];
    } catch (Exception $e) {
        return [
            'status' => 'warning',
            'message' => 'Unable to check sessions'
        ];
    }
}

function checkRecentErrors() {
    $logFile = __DIR__ . '/../../logs/error.log';
    $errors = [];
    
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recentLines = array_slice($lines, -50);
        
        $errorCount = 0;
        foreach ($recentLines as $line) {
            if (strpos($line, '[error]') !== false || strpos($line, 'PHP Fatal') !== false) {
                $errorCount++;
            }
        }
        
        return [
            'status' => $errorCount > 10 ? 'warning' : 'healthy',
            'recent_errors' => $errorCount,
            'message' => "{$errorCount} errors in last 50 log entries"
        ];
    }
    
    return [
        'status' => 'healthy',
        'message' => 'No error log found'
    ];
}

function checkBackupStatus() {
    $backupDir = __DIR__ . '/../../storage/backups';

    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/*.sql');
        if (!empty($files)) {
            $latest = max(array_map('filemtime', $files));
            $hoursAgo = round((time() - $latest) / 3600, 1);
            
            return [
                'status' => $hoursAgo > 24 ? 'warning' : 'healthy',
                'last_backup_hours' => $hoursAgo,
                'total_backups' => count($files),
                'message' => "Last backup {$hoursAgo}h ago"
            ];
        }
    }
    
    return [
        'status' => 'warning',
        'message' => 'No backups found'
    ];
}

$page_title = 'System Health';
$display_title = 'System Health Monitor';

require_once __DIR__ . '/../layouts/app.php';
?>

<main class="main-content p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-white mb-6">📊 System Health</h1>
        
        <!-- Status Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <?php foreach ($healthChecks as $name => $check): ?>
                <?php 
                $statusColors = [
                    'healthy' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
                    'warning' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
                    'critical' => 'bg-red-500/20 text-red-400 border-red-500/30',
                ];
                $color = $statusColors[$check['status']] ?? $statusColors['warning'];
                ?>
                <div class="rounded-xl border p-4 <?php echo $color; ?>">
                    <div class="text-sm font-medium mb-1"><?php echo ucfirst($name); ?></div>
                    <div class="text-xs opacity-80"><?php echo $check['message']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Database Performance -->
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">💾 Database Performance</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <div class="text-sm text-slate-400 mb-2">Response Time</div>
                    <div class="text-3xl font-bold text-emerald-400">
                        <?php echo $healthChecks['database']['response_time_ms'] ?? 'N/A'; ?>ms
                    </div>
                </div>
                <div>
                    <div class="text-sm text-slate-400 mb-2">Table Sizes</div>
                    <div class="space-y-2">
                        <?php foreach (($healthChecks['database']['tables'] ?? []) as $table): ?>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-300"><?php echo $table['table_name']; ?></span>
                                <span class="text-amber-400"><?php echo $table['size_mb']; ?> MB</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Disk Usage -->
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4">💾 Disk Usage</h2>
            <div class="mb-4">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-slate-300">Storage Used</span>
                    <span class="text-amber-400"><?php echo $healthChecks['disk_space']['used_percent']; ?>%</span>
                </div>
                <div class="h-2 bg-slate-700 rounded-full overflow-hidden">
                    <div class="h-full bg-amber-400 rounded-full transition-all" 
                         style="width: <?php echo $healthChecks['disk_space']['used_percent']; ?>"></div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-sm text-slate-400">Total</div>
                    <div class="text-lg font-semibold text-white"><?php echo $healthChecks['disk_space']['total_gb']; ?> GB</div>
                </div>
                <div>
                    <div class="text-sm text-slate-400">Used</div>
                    <div class="text-lg font-semibold text-amber-400">
                        <?php echo round($healthChecks['disk_space']['total_gb'] - $healthChecks['disk_space']['free_gb'], 2); ?> GB
                    </div>
                </div>
                <div>
                    <div class="text-sm text-slate-400">Free</div>
                    <div class="text-lg font-semibold text-emerald-400"><?php echo $healthChecks['disk_space']['free_gb']; ?> GB</div>
                </div>
            </div>
        </div>
        
        <!-- System Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-4">🔧 Environment</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-slate-400">PHP Version</span>
                        <span class="text-white"><?php echo $healthChecks['php_version']['version']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Memory Limit</span>
                        <span class="text-white"><?php echo $healthChecks['memory']['limit']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Memory Usage</span>
                        <span class="text-white"><?php echo $healthChecks['memory']['current_mb']; ?> MB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Peak Memory</span>
                        <span class="text-white"><?php echo $healthChecks['memory']['peak_mb']; ?> MB</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
                <h2 class="text-lg font-semibold text-white mb-4">🔄 Backup Status</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Last Backup</span>
                        <span class="text-<?php echo $healthChecks['backups']['status'] === 'healthy' ? 'emerald' : 'amber'; ?>-400">
                            <?php echo $healthChecks['backups']['last_backup_hours'] ?? 'N/A'; ?>h ago
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Total Backups</span>
                        <span class="text-white"><?php echo $healthChecks['backups']['total_backups'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Active Users</span>
                        <span class="text-white"><?php echo $healthChecks['sessions']['active_users'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Recent Errors</span>
                        <span class="text-<?php echo $healthChecks['errors']['status'] === 'healthy' ? 'emerald' : 'amber'; ?>-400">
                            <?php echo $healthChecks['errors']['recent_errors'] ?? 0; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
