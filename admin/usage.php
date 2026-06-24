<?php
/**
 * Usage Metering Dashboard
 * Platform-wide usage metrics, tenant breakdowns, and over-limit alerts.
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db();

// ---- FILTERS ----
$tenant_filter = (int) ($_GET['tenant_id'] ?? 0);
$type_filter = (int) ($_GET['type_id'] ?? 0);
$period = $_GET['period'] ?? 'current_month'; // current_month, last_month, last_7_days, today
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Period boundaries
$today = date('Y-m-d');
$periodStart = match($period) {
    'today' => $today,
    'last_7_days' => date('Y-m-d', strtotime('-6 days')),
    'last_month' => date('Y-m-01', strtotime('first day of last month')),
    default => date('Y-m-01'), // current_month
};
$periodEnd = match($period) {
    'today' => $today,
    'last_7_days' => $today,
    'last_month' => date('Y-m-t', strtotime('first day of last month')),
    default => date('Y-m-t'),
};

// ---- STATS ----
$totalMeteredToday = db_fetch_value(
    "SELECT COALESCE(SUM(usage_value), 0) FROM usage_metering WHERE period_start <= ? AND period_end >= ?",
    [$today, $today]
);

$overLimitCount = db_fetch_value(
    "SELECT COUNT(DISTINCT tenant_id) FROM usage_metering WHERE is_over_limit = 1 AND period_start <= ? AND period_end >= ?",
    [$today, $today]
);

$activeMeteringTypes = db_fetch_value("SELECT COUNT(*) FROM usage_metering_types WHERE is_metered = 1");

$totalTenantsWithUsage = db_fetch_value(
    "SELECT COUNT(DISTINCT tenant_id) FROM usage_metering WHERE period_start >= ? AND period_end <= ?",
    [$periodStart, $periodEnd]
);

// ---- OVER LIMIT ALERTS ----
$overLimitAlerts = db_fetch_all(
    "SELECT m.*, t.name as tenant_name, t.slug, mt.name as type_name, mt.unit, mt.slug as type_slug
     FROM usage_metering m
     JOIN pos_tenants t ON m.tenant_id = t.id
     JOIN usage_metering_types mt ON m.type_id = mt.id
     WHERE m.is_over_limit = 1 AND m.period_start <= ? AND m.period_end >= ?
     ORDER BY m.over_limit_at DESC
     LIMIT 20",
    [$today, $today]
);

// ---- TOP CONSUMERS ----
$topConsumers = db_fetch_all(
    "SELECT m.tenant_id, t.name as tenant_name, t.slug, mt.name as type_name, mt.unit,
            SUM(m.usage_value) as total_usage, MAX(m.limit_value) as limit_value
     FROM usage_metering m
     JOIN pos_tenants t ON m.tenant_id = t.id
     JOIN usage_metering_types mt ON m.type_id = mt.id
     WHERE m.period_start >= ? AND m.period_end <= ?
     GROUP BY m.tenant_id, m.type_id
     ORDER BY total_usage DESC
     LIMIT 15",
    [$periodStart, $periodEnd]
);

// ---- USAGE BY TYPE CHART DATA ----
$usageByType = db_fetch_all(
    "SELECT mt.name, mt.unit, COALESCE(SUM(m.usage_value), 0) as total
     FROM usage_metering_types mt
     LEFT JOIN usage_metering m ON mt.id = m.type_id AND m.period_start >= ? AND m.period_end <= ?
     WHERE mt.is_metered = 1
     GROUP BY mt.id
     ORDER BY total DESC",
    [$periodStart, $periodEnd]
);

// ---- DAILY TREND DATA ----
$dailyTrend = db_fetch_all(
    "SELECT DATE(m.period_start) as day, mt.name as type_name, COALESCE(SUM(m.usage_value), 0) as total
     FROM usage_metering m
     JOIN usage_metering_types mt ON m.type_id = mt.id
     WHERE m.period_start >= ? AND m.period_end <= ?
     GROUP BY DATE(m.period_start), m.type_id
     ORDER BY day ASC",
    [$periodStart, $periodEnd]
);

// ---- TENANT USAGE TABLE ----
$where = ['m.period_start >= ?', 'm.period_end <= ?'];
$params = [$periodStart, $periodEnd];
if ($tenant_filter) {
    $where[] = 'm.tenant_id = ?';
    $params[] = $tenant_filter;
}
if ($type_filter) {
    $where[] = 'm.type_id = ?';
    $params[] = $type_filter;
}
$whereSql = implode(' AND ', $where);

$totalRows = db_fetch_value(
    "SELECT COUNT(*) FROM usage_metering m WHERE {$whereSql}",
    $params
);

$tenantUsage = db_fetch_all(
    "SELECT m.*, t.name as tenant_name, t.slug, mt.name as type_name, mt.unit, mt.slug as type_slug,
            ROUND((m.usage_value / NULLIF(m.limit_value, 0)) * 100, 1) as pct_used
     FROM usage_metering m
     JOIN pos_tenants t ON m.tenant_id = t.id
     JOIN usage_metering_types mt ON m.type_id = mt.id
     WHERE {$whereSql}
     ORDER BY m.is_over_limit DESC, pct_used DESC, m.usage_value DESC
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);

// ---- DROPDOWNS ----
$meteringTypes = db_fetch_all("SELECT id, name, slug FROM usage_metering_types WHERE is_metered = 1 ORDER BY name");
$tenants = db_fetch_all("SELECT id, name, slug FROM pos_tenants ORDER BY name LIMIT 200");

$current_page = 'usage';
$page_title = 'Usage Metering';

ob_start();
?>
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-lg font-bold text-white">Usage Metering</h1>
            <p class="text-slate-400 text-sm mt-1">Platform-wide consumption metrics and tenant breakdowns</p>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Metered Today</p>
            <p class="text-white font-semibold text-lg"><?= number_format($totalMeteredToday, 0) ?></p>
            <p class="text-slate-500 text-xs">Total units consumed</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Over Limit</p>
            <p class="text-red-400 font-semibold text-lg"><?= $overLimitCount ?></p>
            <p class="text-slate-500 text-xs">Tenants exceeding limits</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Active Types</p>
            <p class="text-white font-semibold text-lg"><?= $activeMeteringTypes ?></p>
            <p class="text-slate-500 text-xs">Metering dimensions</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <p class="text-slate-400 text-xs mb-1">Tenants Active</p>
            <p class="text-emerald-400 font-semibold text-lg"><?= $totalTenantsWithUsage ?></p>
            <p class="text-slate-500 text-xs">With recorded usage</p>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (!empty($overLimitAlerts)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-6 border-red-500/30">
        <h2 class="text-red-400 font-semibold mb-3 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle"></i> Over-Limit Alerts
        </h2>
        <div class="space-y-3">
            <?php foreach (array_slice($overLimitAlerts, 0, 5) as $alert): ?>
            <div class="flex items-center justify-between bg-red-500/10 rounded-lg p-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-red-500/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-400 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium"><?= htmlspecialchars($alert['tenant_name']) ?> (<?= htmlspecialchars($alert['slug']) ?>)</p>
                        <p class="text-slate-400 text-xs"><?= htmlspecialchars($alert['type_name']) ?>: <?= number_format($alert['usage_value'], 2) ?> / <?= $alert['limit_value'] ? number_format($alert['limit_value'], 2) : 'Unlimited' ?> <?= htmlspecialchars($alert['unit']) ?></p>
                    </div>
                </div>
                <a href="tenant_detail.php?id=<?= $alert['tenant_id'] ?>&tab=usage" class="text-red-400 text-xs hover:text-red-300">View tenant →</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts & Top Consumers -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Usage by Type -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h2 class="text-white font-semibold mb-4">Usage by Type (<?= ucfirst(str_replace('_', ' ', $period)) ?>)</h2>
            <?php if (empty($usageByType) || array_sum(array_column($usageByType, 'total')) == 0): ?>
                <p class="text-slate-500 text-sm">No usage data for this period.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($usageByType as $ut): ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-slate-300 text-sm"><?= htmlspecialchars($ut['name']) ?></span>
                            <span class="text-white text-sm font-medium"><?= number_format($ut['total'], 0) ?> <?= htmlspecialchars($ut['unit']) ?></span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-2">
                            <?php $maxTotal = max(array_column($usageByType, 'total')); $barW = $maxTotal > 0 ? ($ut['total'] / $maxTotal) * 100 : 0; ?>
                            <div class="h-2 rounded-full bg-amber-400" style="width:<?= (int)$barW ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Consumers -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h2 class="text-white font-semibold mb-4">Top Consumers</h2>
            <?php if (empty($topConsumers)): ?>
                <p class="text-slate-500 text-sm">No usage data for this period.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                                <th class="pb-2 pr-4">Tenant</th>
                                <th class="pb-2 pr-4">Type</th>
                                <th class="pb-2 text-right">Usage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topConsumers as $tc): ?>
                            <tr class="border-b border-slate-700/30">
                                <td class="py-2 pr-4">
                                    <a href="tenant_detail.php?id=<?= $tc['tenant_id'] ?>&tab=usage" class="text-white hover:text-amber-400 transition">
                                        <?= htmlspecialchars($tc['tenant_name']) ?>
                                    </a>
                                    <p class="text-slate-500 text-xs"><?= htmlspecialchars($tc['slug']) ?></p>
                                </td>
                                <td class="py-2 pr-4 text-slate-400"><?= htmlspecialchars($tc['type_name']) ?></td>
                                <td class="py-2 text-right text-white font-medium"><?= number_format($tc['total_usage'], 2) ?> <?= htmlspecialchars($tc['unit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Daily Trend Chart -->
    <?php if (!empty($dailyTrend)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-6">
        <h2 class="text-white font-semibold mb-4">Daily Usage Trend</h2>
        <canvas id="usageTrendChart" height="80"></canvas>
    </div>
    <?php endif; ?>

    <!-- Tenant Usage Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-white font-semibold">Tenant Usage Details</h2>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="hidden" name="page" value="1">
                <select name="period" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="last_7_days" <?= $period === 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="current_month" <?= $period === 'current_month' ? 'selected' : '' ?>>Current Month</option>
                    <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                </select>
                <select name="tenant_id" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                    <option value="">All Tenants</option>
                    <?php foreach ($tenants as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $tenant_filter == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="type_id" class="px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white text-sm">
                    <option value="">All Types</option>
                    <?php foreach ($meteringTypes as $mt): ?>
                    <option value="<?= $mt['id'] ?>" <?= $type_filter == $mt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mt['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-left">
                        <th class="pb-2 pr-4">Tenant</th>
                        <th class="pb-2 pr-4">Type</th>
                        <th class="pb-2 pr-4 text-right">Usage</th>
                        <th class="pb-2 pr-4 text-right">Limit</th>
                        <th class="pb-2 pr-4 text-right">%</th>
                        <th class="pb-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tenantUsage)): ?>
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">No usage records match the filters.</td></tr>
                    <?php else: foreach ($tenantUsage as $tu): ?>
                    <tr class="border-b border-slate-700/30 <?= $tu['is_over_limit'] ? 'bg-red-500/5' : '' ?>">
                        <td class="py-3 pr-4">
                            <a href="tenant_detail.php?id=<?= $tu['tenant_id'] ?>&tab=usage" class="text-white hover:text-amber-400 transition">
                                <?= htmlspecialchars($tu['tenant_name']) ?>
                            </a>
                            <p class="text-slate-500 text-xs"><?= htmlspecialchars($tu['slug']) ?></p>
                        </td>
                        <td class="py-3 pr-4 text-slate-400"><?= htmlspecialchars($tu['type_name']) ?></td>
                        <td class="py-3 pr-4 text-right text-white"><?= number_format($tu['usage_value'], 2) ?></td>
                        <td class="py-3 pr-4 text-right text-slate-400"><?= $tu['limit_value'] ? number_format($tu['limit_value'], 2) : '—' ?></td>
                        <td class="py-3 pr-4 text-right">
                            <?php if ($tu['limit_value']): ?>
                                <span class="<?= $tu['pct_used'] >= 95 ? 'text-red-400' : ($tu['pct_used'] >= 80 ? 'text-yellow-400' : 'text-emerald-400') ?>"><?= $tu['pct_used'] ?>%</span>
                            <?php else: ?>
                                <span class="text-slate-500">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3">
                            <?php if ($tu['is_over_limit']): ?>
                                <span class="px-2 py-0.5 rounded text-xs bg-red-500/20 text-red-400">Over Limit</span>
                            <?php elseif ($tu['pct_used'] >= 80): ?>
                                <span class="px-2 py-0.5 rounded text-xs bg-yellow-500/20 text-yellow-400">Warning</span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-400">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php $totalPages = ceil($totalRows / $per_page); if ($totalPages > 1): ?>
        <div class="mt-4 flex items-center justify-between">
            <p class="text-slate-400 text-xs">Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $totalRows) ?> of <?= $totalRows ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&period=<?= $period ?>&tenant_id=<?= $tenant_filter ?>&type_id=<?= $type_filter ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>&period=<?= $period ?>&tenant_id=<?= $tenant_filter ?>&type_id=<?= $type_filter ?>" class="px-3 py-1 rounded text-xs <?= $i === $page ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 border border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&period=<?= $period ?>&tenant_id=<?= $tenant_filter ?>&type_id=<?= $type_filter ?>" class="px-3 py-1 bg-slate-700/50 rounded text-white text-xs hover:bg-slate-700/70"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Metering Types Reference -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
        <h2 class="text-white font-semibold mb-4">Metering Types</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <?php foreach ($meteringTypes as $mt): ?>
            <div class="bg-slate-800/50 rounded-lg p-3">
                <p class="text-white font-medium text-sm"><?= htmlspecialchars($mt['name']) ?></p>
                <p class="text-slate-500 text-xs">Slug: <span class="font-mono"><?= htmlspecialchars($mt['slug']) ?></span></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (!empty($dailyTrend)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
<?php
// Prepare chart data
$grouped = [];
$labels = [];
$datasets = [];
$typeNames = [];
foreach ($dailyTrend as $d) {
    $day = $d['day'];
    $type = $d['type_name'];
    if (!isset($grouped[$day])) $grouped[$day] = [];
    $grouped[$day][$type] = (float)$d['total'];
    if (!in_array($type, $typeNames)) $typeNames[] = $type;
}
$labels = array_keys($grouped);
$colors = ['#F59E0B', '#10B981', '#3B82F6', '#EF4444', '#8B5CF6', '#EC4899'];
foreach ($typeNames as $i => $type) {
    $data = [];
    foreach ($labels as $day) {
        $data[] = $grouped[$day][$type] ?? 0;
    }
    $datasets[] = [
        'label' => $type,
        'data' => $data,
        'borderColor' => $colors[$i % count($colors)],
        'backgroundColor' => $colors[$i % count($colors)] . '33',
        'fill' => true,
        'tension' => 0.3,
        'pointRadius' => 2,
    ];
}
?>
const ctx = document.getElementById('usageTrendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: <?= json_encode($datasets) ?>
    },
    options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { labels: { color: '#9CA3AF', font: { size: 11 } } }
        },
        scales: {
            x: { ticks: { color: '#9CA3AF', font: { size: 10 } }, grid: { color: 'rgba(75,85,99,0.2)' } },
            y: { ticks: { color: '#9CA3AF', font: { size: 10 } }, grid: { color: 'rgba(75,85,99,0.2)' }, beginAtZero: true }
        }
    }
});
</script>
<?php endif; ?>
<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';

