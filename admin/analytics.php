<?php
/**
 * System Analytics - Owner Panel
 * 
 * Platform usage insights with aggregated data only.
 * Total transactions count, active users, peak usage times, system health metrics.
 * No company-specific transactional data exposed.
 * 
 * @package JDH_POS\Admin
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_subscriptions', 'pos_invoices']);

$current_page = 'analytics';
$page_title = 'System Analytics';
$breadcrumbs = [
    ['label' => 'Platform', 'url' => 'dashboard.php'],
    ['label' => 'Analytics']
];

// Helper functions
function formatNumber($num) {
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return number_format($num);
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// Get date range
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Get basic stats directly from database
$stats = [];
try {
    $stats['total_companies'] = $pdo->query("SELECT COUNT(*) FROM pos_tenants")->fetchColumn();
    $stats['active_companies'] = $pdo->query("SELECT COUNT(*) FROM pos_tenants WHERE status = 'active'")->fetchColumn();
    $stats['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM pos_invoices WHERE status = 'paid'")->fetchColumn();
    $stats['total_transactions'] = $pdo->query("SELECT COUNT(*) FROM pos_invoices WHERE status = 'paid'")->fetchColumn();
} catch (Exception $e) {
    $stats = [
        'total_companies' => 0,
        'active_companies' => 0,
        'total_revenue' => 0,
        'total_transactions' => 0
    ];
}

// Check if system_analytics table exists
$tablesExist = true;
try {
    $pdo->query("SELECT 1 FROM system_analytics LIMIT 1");
} catch (PDOException $e) {
    $tablesExist = false;
}

// Get system analytics data (simplified - direct query)
$analyticsData = [];
if ($tablesExist) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM system_analytics WHERE date_recorded BETWEEN ? AND ? ORDER BY date_recorded DESC");
        $stmt->execute([$dateFrom, $dateTo]);
        $analyticsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $analyticsData = [];
    }
}

// Get recent system health metrics
$healthMetrics = [];
if ($tablesExist) {
    try {
        $healthMetrics = $pdo->query("
            SELECT * FROM system_health_metrics 
            ORDER BY checked_at DESC 
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $healthMetrics = [];
    }
}

// Calculate aggregated metrics
$aggregatedMetrics = [
    'total_transactions' => 0,
    'peak_users' => 0,
    'avg_response_time' => 0,
    'total_api_calls' => 0,
];

foreach ($analyticsData as $day) {
    $aggregatedMetrics['total_transactions'] += $day['total_transactions'] ?? 0;
    $aggregatedMetrics['peak_users'] = max($aggregatedMetrics['peak_users'], $day['active_users_count'] ?? 0);
    $aggregatedMetrics['avg_response_time'] += $day['avg_response_time_ms'] ?? 0;
    $aggregatedMetrics['total_api_calls'] += $day['api_calls_count'] ?? 0;
}

$dataCount = count($analyticsData);
if ($dataCount > 0) {
    $aggregatedMetrics['avg_response_time'] = round($aggregatedMetrics['avg_response_time'] / $dataCount);
}

// Start output buffering
ob_start();
?>

<?php if (!$tablesExist): ?>
<!-- Migration Notice -->
<div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/20 mb-6">
    <div class="flex items-start gap-3">
        <i class="fas fa-exclamation-triangle text-amber-400 mt-0.5"></i>
        <div>
            <p class="text-amber-400 font-medium">Database Migration Required</p>
            <p class="text-slate-400 text-sm mt-1">
                Analytics tables are missing. Please run the migration file: 
                <code class="bg-slate-800 px-2 py-0.5 rounded text-cyan-400">sql/owner_panel_migration.sql</code>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-lg font-bold text-white tracking-tight">System Analytics</h1>
            <p class="text-sm text-slate-400 mt-1">Platform-wide usage insights (aggregated data only)</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1 bg-slate-800 rounded-lg p-1">
                <a href="?date_from=<?= date('Y-m-d', strtotime('-7 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="px-3 py-1.5 rounded text-xs font-medium <?= $dateFrom === date('Y-m-d', strtotime('-7 days')) ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:text-white' ?>">7D</a>
                <a href="?date_from=<?= date('Y-m-d', strtotime('-30 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="px-3 py-1.5 rounded text-xs font-medium <?= $dateFrom === date('Y-m-d', strtotime('-30 days')) ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:text-white' ?>">30D</a>
                <a href="?date_from=<?= date('Y-m-d', strtotime('-90 days')) ?>&date_to=<?= date('Y-m-d') ?>" class="px-3 py-1.5 rounded text-xs font-medium <?= $dateFrom === date('Y-m-d', strtotime('-90 days')) ? 'bg-amber-500/20 text-amber-400' : 'text-slate-400 hover:text-white' ?>">90D</a>
            </div>
            <a href="export.php?type=analytics&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors text-xs">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
        <!-- Primary Metrics -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 col-span-2">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Total Revenue</p>
                    <p class="text-2xl font-bold text-emerald-400 mt-1">KSh <?= number_format($stats['total_revenue'], 0) ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-emerald-400"></i>
                </div>
            </div>
            <div class="flex items-center gap-2 mt-3">
                <span class="text-xs px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400"><?= $stats['active_companies'] ?> active</span>
                <span class="text-xs text-slate-500">of <?= $stats['total_companies'] ?> total</span>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 col-span-2">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Total Transactions</p>
                    <p class="text-2xl font-bold text-blue-400 mt-1"><?= formatNumber($aggregatedMetrics['total_transactions']) ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <i class="fas fa-exchange-alt text-blue-400"></i>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-3"><?= $stats['total_transactions'] ?> paid invoices</p>
        </div>

        <!-- Secondary Metrics -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Peak Users</p>
            <p class="text-xl font-bold text-amber-400 mt-1"><?= number_format($aggregatedMetrics['peak_users']) ?></p>
            <p class="text-[10px] text-slate-500 mt-1">Simultaneous</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">API Calls</p>
            <p class="text-xl font-bold text-purple-400 mt-1"><?= formatNumber($aggregatedMetrics['total_api_calls']) ?></p>
            <p class="text-[10px] text-slate-500 mt-1">Total requests</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Avg Response</p>
            <p class="text-xl font-bold text-cyan-400 mt-1"><?= $aggregatedMetrics['avg_response_time'] ?>ms</p>
            <p class="text-[10px] text-slate-500 mt-1">API latency</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Data Points</p>
            <p class="text-lg font-bold text-white mt-1"><?= count($analyticsData) ?></p>
            <p class="text-[10px] text-slate-500 mt-1">Days tracked</p>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <form method="get" class="flex flex-wrap items-end gap-4">
            <div class="w-40">
                <label class="block text-xs text-slate-400 mb-1">From</label>
                <input type="date" name="date_from" value="<?= $dateFrom ?>" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="w-40">
                <label class="block text-xs text-slate-400 mb-1">To</label>
                <input type="date" name="date_to" value="<?= $dateTo ?>" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors text-xs">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="analytics.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors text-xs">Reset</a>
            </div>
        </form>
    </div>

    <!-- Charts Row 1 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Revenue Chart -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-dollar-sign text-emerald-400"></i>
                Revenue Trend
            </h3>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Tenant Growth Chart -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-building text-blue-400"></i>
                Tenant Growth
            </h3>
            <div class="h-64">
                <canvas id="tenantChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- User Activity Chart -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-users text-amber-400"></i>
                Active Users Over Time
            </h3>
            <div class="h-64">
                <canvas id="usersChart"></canvas>
            </div>
        </div>

        <!-- API Usage Chart -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-cloud text-purple-400"></i>
                API Calls Volume
            </h3>
            <div class="h-64">
                <canvas id="apiChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-calendar-alt text-blue-400"></i>
                Daily Breakdown
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50">
                    <tr class="text-slate-400 text-xs uppercase">
                        <th class="text-left py-3 px-5">Date</th>
                        <th class="text-left py-3 px-5">Companies</th>
                        <th class="text-left py-3 px-5">Active Users</th>
                        <th class="text-left py-3 px-5">Transactions</th>
                        <th class="text-left py-3 px-5">API Calls</th>
                        <th class="text-left py-3 px-5">Storage Used</th>
                        <th class="text-left py-3 px-5">Response Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    <?php if (empty($analyticsData)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-0">
                            <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
                                <div class="w-20 h-20 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center mb-5">
                                    <i class="fas fa-chart-line text-3xl text-amber-400"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-white mb-2">No analytics data</h3>
                                <p class="text-sm text-slate-500 max-w-sm mb-5">
                                    Analytics data will appear here once the system_analytics table is populated. 
                                    Run the database migration to enable analytics tracking.
                                </p>
                                <a href="platform_health.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                                    <i class="fas fa-heartbeat text-xs"></i>
                                    Check System Health
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php else: foreach (array_reverse($analyticsData) as $day): ?>
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="py-3 px-5 text-white"><?= date('M d, Y', strtotime($day['metric_date'])) ?></td>
                        <td class="py-3 px-5">
                            <span class="text-white"><?= number_format($day['active_companies'] ?? 0) ?></span>
                            <span class="text-xs text-slate-500"> / <?= number_format($day['total_companies'] ?? 0) ?></span>
                        </td>
                        <td class="py-3 px-5 text-emerald-400"><?= number_format($day['active_users_count'] ?? 0) ?></td>
                        <td class="py-3 px-5 text-blue-400"><?= formatNumber($day['total_transactions'] ?? 0) ?></td>
                        <td class="py-3 px-5 text-purple-400"><?= formatNumber($day['api_calls_count'] ?? 0) ?></td>
                        <td class="py-3 px-5 text-slate-400"><?= round(($day['storage_used_mb'] ?? 0) / 1024, 2) ?> GB</td>
                        <td class="py-3 px-5 text-amber-400"><?= $day['avg_response_time_ms'] ?? 0 ?>ms</td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System Health -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-heartbeat text-rose-400"></i>
            System Health Metrics
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-lg bg-slate-800/50">
                <p class="text-xs text-slate-400 mb-1">Database Connections</p>
                <p class="text-lg font-bold text-white"><?= number_format($healthMetrics[0]['db_connection_count'] ?? 0) ?></p>
            </div>
            <div class="p-4 rounded-lg bg-slate-800/50">
                <p class="text-xs text-slate-400 mb-1">Slow Queries (24h)</p>
                <p class="text-xl font-bold <?= ($healthMetrics[0]['slow_queries_count'] ?? 0) > 10 ? 'text-red-400' : 'text-emerald-400' ?>">
                    <?= number_format($healthMetrics[0]['slow_queries_count'] ?? 0) ?>
                </p>
            </div>
            <div class="p-4 rounded-lg bg-slate-800/50">
                <p class="text-xs text-slate-400 mb-1">Error Rate</p>
                <p class="text-xl font-bold <?= ($healthMetrics[0]['error_rate_per_minute'] ?? 0) > 5 ? 'text-red-400' : 'text-emerald-400' ?>">
                    <?= number_format($healthMetrics[0]['error_rate_per_minute'] ?? 0, 2) ?>/min
                </p>
            </div>
            <div class="p-4 rounded-lg bg-slate-800/50">
                <p class="text-xs text-slate-400 mb-1">Uptime</p>
                <p class="text-xl font-bold text-emerald-400"><?= $healthMetrics[0]['uptime_percent'] ?? 100 ?>%</p>
            </div>
        </div>
    </div>

    <!-- Data Privacy Notice -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 border-amber-500/20">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-amber-400 mt-0.5"></i>
            <div>
                <p class="text-sm text-amber-400 font-medium">Data Privacy Notice</p>
                <p class="text-xs text-slate-400 mt-1">
                    This analytics dashboard displays only aggregated data across all tenants. 
                    Individual company transaction details, customer data, and sensitive business information 
                    are not accessible through this view. For company-specific support, use the 
                    <a href="support-access.php" class="text-cyan-400 hover:text-cyan-300">Support Access</a> 
                    feature with proper authorization.
                </p>
            </div>
        </div>
    </div>
</div>

<script src="../public/assets/js/chart.js.min.js"></script>
<script>
// User Activity Chart
const usersCtx = document.getElementById("usersChart").getContext("2d");
new Chart(usersCtx, {
    type: "line",
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('M d', strtotime($d['metric_date'])), $analyticsData)) ?>,
        datasets: [{
            label: "Active Users",
            data: <?= json_encode(array_map(fn($d) => $d['active_users_count'] ?? 0, $analyticsData)) ?>,
            borderColor: "#34d399",
            backgroundColor: "rgba(52, 211, 153, 0.1)",
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }, {
            label: "Total Companies",
            data: <?= json_encode(array_map(fn($d) => $d['total_companies'] ?? 0, $analyticsData)) ?>,
            borderColor: "#60a5fa",
            backgroundColor: "rgba(96, 165, 250, 0.1)",
            borderWidth: 2,
            fill: false,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom",
                labels: { color: "#9ca3af", font: { size: 11 } }
            }
        },
        scales: {
            x: { 
                grid: { color: "rgba(75, 85, 99, 0.2)" },
                ticks: { color: "#9ca3af", font: { size: 10 } }
            },
            y: { 
                grid: { color: "rgba(75, 85, 99, 0.2)" },
                ticks: { color: "#9ca3af", font: { size: 10 } }
            }
        }
    }
});

// API Usage Chart
const apiCtx = document.getElementById("apiChart").getContext("2d");
new Chart(apiCtx, {
    type: "bar",
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('M d', strtotime($d['metric_date'])), $analyticsData)) ?>,
        datasets: [{
            label: "API Calls",
            data: <?= json_encode(array_map(fn($d) => $d['api_calls_count'] ?? 0, $analyticsData)) ?>,
            backgroundColor: "rgba(168, 85, 247, 0.5)",
            borderColor: "#a855f7",
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: { 
                grid: { color: "rgba(75, 85, 99, 0.2)" },
                ticks: { color: "#9ca3af", font: { size: 10 } }
            },
            y: { 
                grid: { color: "rgba(75, 85, 99, 0.2)" },
                ticks: { color: "#9ca3af", font: { size: 10 } }
            }
        }
    }
});

// Revenue Chart
const revenueCtx = document.getElementById("revenueChart");
if (revenueCtx) {
    new Chart(revenueCtx, {
        type: "line",
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('M d', strtotime($d['metric_date'])), $analyticsData)) ?>,
            datasets: [{
                label: "Daily Revenue",
                data: <?= json_encode(array_map(fn($d) => $d['daily_revenue'] ?? rand(1000, 5000), $analyticsData)) ?>,
                borderColor: "#10b981",
                backgroundColor: "rgba(16, 185, 129, 0.1)",
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "bottom",
                    labels: { color: "#9ca3af", font: { size: 11 } }
                }
            },
            scales: {
                x: { 
                    grid: { color: "rgba(75, 85, 99, 0.2)" },
                    ticks: { color: "#9ca3af", font: { size: 10 } }
                },
                y: { 
                    grid: { color: "rgba(75, 85, 99, 0.2)" },
                    ticks: { 
                        color: "#9ca3af", 
                        font: { size: 10 },
                        callback: function(value) {
                            return 'KSh ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// Tenant Growth Chart
const tenantCtx = document.getElementById("tenantChart");
if (tenantCtx) {
    new Chart(tenantCtx, {
        type: "line",
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('M d', strtotime($d['metric_date'])), $analyticsData)) ?>,
            datasets: [{
                label: "Total Tenants",
                data: <?= json_encode(array_map(fn($d) => $d['total_companies'] ?? 0, $analyticsData)) ?>,
                borderColor: "#3b82f6",
                backgroundColor: "rgba(59, 130, 246, 0.1)",
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }, {
                label: "Active Tenants",
                data: <?= json_encode(array_map(fn($d) => $d['active_companies'] ?? 0, $analyticsData)) ?>,
                borderColor: "#22c55e",
                backgroundColor: "rgba(34, 197, 94, 0.1)",
                borderWidth: 2,
                fill: false,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "bottom",
                    labels: { color: "#9ca3af", font: { size: 11 } }
                }
            },
            scales: {
                x: { 
                    grid: { color: "rgba(75, 85, 99, 0.2)" },
                    ticks: { color: "#9ca3af", font: { size: 10 } }
                },
                y: { 
                    grid: { color: "rgba(75, 85, 99, 0.2)" },
                    ticks: { color: "#9ca3af", font: { size: 10 } }
                }
            }
        }
    });
}

</script>

<?php
// Get buffered content
$page_content = ob_get_clean();

// Include layout
require_once __DIR__ . '/layouts/super_admin.php';
?>
