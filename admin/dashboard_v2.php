<?php
/**
 * Advanced SaaS Admin Dashboard
 * Real data, dark theme, modern SaaS admin UX
 */

require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants']);

$stats = [];
$recentCompanies = [];
$topUsers = [];
$recentSales = [];
$revenueChart = ['labels' => [], 'data' => []];
$system = [];

// ---- REAL DATA QUERIES ----

try {
    // SaaS / Tenant metrics
    $stats['companies'] = [
        'total' => (int) $pdo->query("SELECT COUNT(*) FROM pos_tenants")->fetchColumn(),
        'active' => (int) $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'active'")->fetchColumn(),
        'trial' => (int) $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'trialing'")->fetchColumn(),
    ];

    // POS metrics (real data)
    $stats['pos'] = [
        'users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn(),
        'branches' => (int) $pdo->query("SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL")->fetchColumn(),
        'sales_today' => (int) $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'sales_month' => (int) $pdo->query("SELECT COUNT(*) FROM sales WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn(),
        'revenue_today' => (float) ($pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?? 0),
        'revenue_month' => (float) ($pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn() ?? 0),
    ];

    // Chart: daily sales last 14 days
    $chartRows = $pdo->query("
        SELECT DATE(created_at) as day, COALESCE(SUM(total_amount), 0) as total
        FROM sales
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day
    ")->fetchAll(PDO::FETCH_ASSOC);

    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-{$i} days"))] = 0;
    }
    foreach ($chartRows as $r) {
        $days[$r['day']] = (float) $r['total'];
    }
    foreach ($days as $d => $v) {
        $revenueChart['labels'][] = date('M j', strtotime($d));
        $revenueChart['data'][] = $v;
    }

    // Top 5 users by sales volume this month
    $topUsers = $pdo->query("
        SELECT u.id, u.name, u.username, COUNT(s.id) as sale_count, COALESCE(SUM(s.total_amount), 0) as total_revenue
        FROM users u
        LEFT JOIN sales s ON u.id = s.user_id AND s.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        WHERE u.deleted_at IS NULL
        GROUP BY u.id
        ORDER BY total_revenue DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent sales
    $recentSales = $pdo->query("
        SELECT s.id, s.total_amount, s.created_at, u.name as user_name, b.name as branch_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN branches b ON s.branch_id = b.id
        ORDER BY s.created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // System health
    $system['php_version'] = PHP_VERSION;
    $system['mysql_version'] = $pdo->query("SELECT VERSION()")->fetchColumn();
    $system['db_size'] = (float) $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
    ")->fetchColumn();
    $system['total_tables'] = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $system['disk_free'] = round(disk_free_space(__DIR__) / 1024 / 1024 / 1024, 1);
    $system['disk_total'] = round(disk_total_space(__DIR__) / 1024 / 1024 / 1024, 1);

    // Recent companies
    $recentCompanies = $pdo->query("
        SELECT t.*, s.status as sub_status
        FROM pos_tenants t
        LEFT JOIN pos_subscriptions s ON t.id = s.tenant_id
        ORDER BY t.created_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

function fmtKES($amount) {
    return 'KSh ' . number_format($amount, 2);
}

function statusBadge($status) {
    $map = [
        'active' => ['bg-emerald-500/15 text-emerald-400 border-emerald-500/20', 'Active'],
        'trial' => ['bg-blue-500/15 text-blue-400 border-blue-500/20', 'Trial'],
        'trialing' => ['bg-blue-500/15 text-blue-400 border-blue-500/20', 'Trial'],
        'past_due' => ['bg-amber-500/15 text-amber-400 border-amber-500/20', 'Past Due'],
        'cancelled' => ['bg-rose-500/15 text-rose-400 border-rose-500/20', 'Cancelled'],
        'suspended' => ['bg-slate-500/15 text-slate-400 border-slate-500/20', 'Suspended'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-slate-500/15 text-slate-400 border-slate-500/20', ucfirst($status)];
    return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ' . $cls . '">' . $label . '</span>';
}

function trendArrow($current, $previous) {
    if ($previous <= 0) return '<span class="text-slate-500 text-xs">—</span>';
    $pct = round((($current - $previous) / $previous) * 100, 1);
    $icon = $pct >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
    $color = $pct >= 0 ? 'text-emerald-400' : 'text-rose-400';
    return "<span class=\"{$color} text-xs font-medium\"><i class=\"fas {$icon} mr-1\"></i>" . abs($pct) . "%</span>";
}

$page_title = 'Platform Dashboard';
$current_page = 'dashboard';
ob_start();
?>

<style>
.saas-dashboard { --dash-bg: #0B1120; --dash-panel: rgba(15,23,42,0.65); --dash-border: rgba(148,163,184,0.12); }
.saas-dashboard .kpi-card { background: var(--dash-panel); border: 1px solid var(--dash-border); border-radius: 16px; padding: 20px; backdrop-filter: blur(8px); transition: all 0.2s; }
.saas-dashboard .kpi-card:hover { border-color: rgba(148,163,184,0.25); transform: translateY(-2px); }
.saas-dashboard .kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.saas-dashboard .section-card { background: var(--dash-panel); border: 1px solid var(--dash-border); border-radius: 16px; overflow: hidden; }
.saas-dashboard .table-row { border-bottom: 1px solid var(--dash-border); transition: background 0.15s; }
.saas-dashboard .table-row:last-child { border-bottom: none; }
.saas-dashboard .table-row:hover { background: rgba(255,255,255,0.03); }
.saas-dashboard .mini-bar { height: 4px; border-radius: 2px; background: rgba(148,163,184,0.15); overflow: hidden; }
.saas-dashboard .mini-bar-fill { height: 100%; border-radius: 2px; transition: width 0.5s ease; }
.saas-dashboard .spark { display: flex; align-items: flex-end; gap: 3px; height: 40px; }
.saas-dashboard .spark-bar { flex: 1; border-radius: 2px 2px 0 0; background: rgba(251,191,36,0.25); min-height: 2px; }
.saas-dashboard .spark-bar:last-child { background: rgba(251,191,36,0.7); }
</style>

<div class="saas-dashboard">

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-lg font-bold text-white tracking-tight">Platform Dashboard</h1>
        <p class="text-sm text-slate-400 mt-1">Real-time overview of your SaaS POS ecosystem</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-xs text-slate-500 px-3 py-1.5 rounded-lg bg-slate-800/50 border border-slate-700/50">
            <i class="fas fa-circle text-emerald-400 text-[8px] mr-1.5 animate-pulse"></i>Live
        </span>
        <a href="<?= base_url('auth/signup.php') ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors text-xs">
            <i class="fas fa-plus mr-1"></i> New Tenant
        </a>
    </div>
</div>

<!-- Primary KPIs -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- Revenue Today -->
    <div class="kpi-card">
        <div class="flex items-start justify-between mb-3">
            <div class="kpi-icon bg-emerald-500/10 text-emerald-400"><i class="fas fa-coins"></i></div>
            <div class="spark" id="sparkRevenue"></div>
        </div>
        <p class="text-lg font-bold text-white"><?= fmtKES($stats['pos']['revenue_today']) ?></p>
        <div class="flex items-center justify-between mt-2">
            <p class="text-xs text-slate-400">Revenue Today</p>
            <span class="text-xs text-slate-500"><?= $stats['pos']['sales_today'] ?> sales</span>
        </div>
    </div>

    <!-- Revenue This Month -->
    <div class="kpi-card">
        <div class="flex items-start justify-between mb-3">
            <div class="kpi-icon bg-amber-500/10 text-amber-400"><i class="fas fa-chart-line"></i></div>
            <div class="spark" id="sparkMonth"></div>
        </div>
        <p class="text-lg font-bold text-white"><?= fmtKES($stats['pos']['revenue_month']) ?></p>
        <div class="flex items-center justify-between mt-2">
            <p class="text-xs text-slate-400">This Month</p>
            <span class="text-xs text-slate-500"><?= $stats['pos']['sales_month'] ?> orders</span>
        </div>
    </div>

    <!-- Active Users -->
    <div class="kpi-card">
        <div class="flex items-start justify-between mb-3">
            <div class="kpi-icon bg-blue-500/10 text-blue-400"><i class="fas fa-users"></i></div>
        </div>
        <p class="text-lg font-bold text-white"><?= number_format($stats['pos']['users']) ?></p>
        <div class="flex items-center justify-between mt-2">
            <p class="text-xs text-slate-400">Active Users</p>
            <span class="text-xs text-slate-500"><?= $stats['pos']['branches'] ?> branches</span>
        </div>
        <div class="mt-3">
            <div class="mini-bar"><div class="mini-bar-fill bg-blue-400" style="width:<?= min(100, $stats['pos']['users'] * 20) ?>%"></div></div>
        </div>
    </div>

    <!-- Tenants -->
    <div class="kpi-card">
        <div class="flex items-start justify-between mb-3">
            <div class="kpi-icon bg-purple-500/10 text-purple-400"><i class="fas fa-building"></i></div>
        </div>
        <p class="text-lg font-bold text-white"><?= number_format($stats['companies']['total']) ?></p>
        <div class="flex items-center justify-between mt-2">
            <p class="text-xs text-slate-400">Tenants</p>
            <div class="flex gap-2">
                <?php if ($stats['companies']['active']): ?><span class="text-xs text-emerald-400"><?= $stats['companies']['active'] ?> active</span><?php endif; ?>
                <?php if ($stats['companies']['trial']): ?><span class="text-xs text-blue-400"><?= $stats['companies']['trial'] ?> trial</span><?php endif; ?>
            </div>
        </div>
        <div class="mt-3">
            <div class="mini-bar"><div class="mini-bar-fill bg-purple-400" style="width:<?= $stats['companies']['total'] > 0 ? 100 : 0 ?>%"></div></div>
        </div>
    </div>
</div>

<!-- Main Content: Chart + Side Panels -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <!-- Revenue Chart -->
    <div class="lg:col-span-2 section-card p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-semibold text-white">Daily Revenue</h3>
                <p class="text-xs text-slate-500 mt-0.5">Last 14 days</p>
            </div>
            <span class="text-xs font-medium text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-full border border-emerald-500/20">POS Sales</span>
        </div>
        <div class="h-56 relative">
            <canvas id="revenueChart"></canvas>
            <?php if (empty(array_filter($revenueChart['data']))): ?>
            <div class="absolute inset-0 flex items-center justify-center">
                <p class="text-sm text-slate-500">No revenue data for this period</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="section-card p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Recent Sales</h3>
            <a href="<?= base_url('pos/sales/list_sales.php') ?>" class="text-xs text-slate-400 hover:text-white transition">View All</a>
        </div>
        <div class="space-y-3 max-h-56 overflow-y-auto pr-1">
            <?php if (empty($recentSales)): ?>
            <p class="text-xs text-slate-500 text-center py-8">No recent sales</p>
            <?php else: ?>
            <?php foreach ($recentSales as $sale): ?>
            <div class="table-row flex items-center gap-3 py-2 px-1 rounded-lg">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400 shrink-0">
                    <i class="fas fa-receipt text-xs"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-white truncate">#<?= $sale['id'] ?> — <?= htmlspecialchars($sale['user_name'] ?? 'Unknown') ?></p>
                    <p class="text-[10px] text-slate-500"><?= htmlspecialchars($sale['branch_name'] ?? 'Main') ?> · <?= date('M j, g:i a', strtotime($sale['created_at'])) ?></p>
                </div>
                <p class="text-xs font-semibold text-white shrink-0"><?= fmtKES($sale['total_amount']) ?></p>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <!-- Top Users -->
    <div class="section-card p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Top Users This Month</h3>
            <a href="users.php" class="text-xs text-slate-400 hover:text-white transition">Manage</a>
        </div>
        <?php if (empty($topUsers)): ?>
        <p class="text-xs text-slate-500 text-center py-8">No sales activity this month</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($topUsers as $i => $u): ?>
            <div class="table-row flex items-center gap-3 py-2.5 px-2 rounded-lg">
                <span class="text-xs font-bold text-slate-500 w-5 text-center"><?= $i + 1 ?></span>
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-white"><?= htmlspecialchars($u['name']) ?></p>
                    <p class="text-[10px] text-slate-500"><?= $u['sale_count'] ?> sales</p>
                </div>
                <p class="text-xs font-semibold text-emerald-400 shrink-0"><?= fmtKES($u['total_revenue']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tenants -->
    <div class="section-card p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Tenants</h3>
            <a href="companies.php" class="text-xs text-slate-400 hover:text-white transition">View All</a>
        </div>
        <?php if (empty($recentCompanies)): ?>
        <p class="text-xs text-slate-500 text-center py-8">No tenants registered</p>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($recentCompanies as $c): ?>
            <div class="table-row flex items-center gap-3 py-2.5 px-2 rounded-lg">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-pink-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                    <?= strtoupper(substr($c['name'], 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-white truncate"><?= htmlspecialchars($c['name']) ?></p>
                    <p class="text-[10px] text-slate-500"><?= htmlspecialchars($c['subdomain'] ?? '') ?> · <?= date('M j, Y', strtotime($c['created_at'])) ?></p>
                </div>
                <?= statusBadge($c['sub_status'] ?? 'trial') ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- System Health -->
<div class="section-card p-5 mb-6">
    <h3 class="text-sm font-semibold text-white mb-4">System Health</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="text-center">
            <p class="text-xs text-slate-500 mb-1">PHP</p>
            <p class="text-sm font-semibold text-white"><?= htmlspecialchars($system['php_version'] ?? '—') ?></p>
        </div>
        <div class="text-center">
            <p class="text-xs text-slate-500 mb-1">MySQL</p>
            <p class="text-sm font-semibold text-white"><?= htmlspecialchars($system['mysql_version'] ?? '—') ?></p>
        </div>
        <div class="text-center">
            <p class="text-xs text-slate-500 mb-1">DB Size</p>
            <p class="text-sm font-semibold text-white"><?= $system['db_size'] ?? 0 ?> MB</p>
        </div>
        <div class="text-center">
            <p class="text-xs text-slate-500 mb-1">Tables</p>
            <p class="text-sm font-semibold text-white"><?= number_format($system['total_tables'] ?? 0) ?></p>
        </div>
        <div class="text-center">
            <p class="text-xs text-slate-500 mb-1">Disk Free</p>
            <p class="text-sm font-semibold text-white"><?= $system['disk_free'] ?? 0 ?> GB</p>
        </div>
        <div class="text-center">
            <p class="text-xs text-slate-500 mb-1">Disk Total</p>
            <p class="text-sm font-semibold text-white"><?= $system['disk_total'] ?? 0 ?> GB</p>
        </div>
    </div>
    <div class="mt-4">
        <div class="mini-bar h-2">
            <?php $diskPct = ($system['disk_total'] ?? 0) > 0 ? (($system['disk_total'] - ($system['disk_free'] ?? 0)) / $system['disk_total']) * 100 : 0; ?>
            <div class="mini-bar-fill <?= $diskPct > 90 ? 'bg-rose-400' : ($diskPct >= 75 ? 'bg-amber-400' : 'bg-emerald-400') ?>" style="width:<?= $diskPct ?>%"></div>
        </div>
        <p class="text-[10px] text-slate-500 mt-1 text-right"><?= round($diskPct, 1) ?>% disk used</p>
    </div>
</div>

</div><!-- /.saas-dashboard -->

<script src="../public/assets/js/chart.js.min.js"></script>
<script>
const ctx = document.getElementById('revenueChart');
if (ctx) {
    const labels = <?= json_encode($revenueChart['labels']) ?>;
    const data = <?= json_encode($revenueChart['data']) ?>;
    const hasData = data.some(v => v > 0);
    if (hasData) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue',
                    data: data,
                    backgroundColor: 'rgba(251, 191, 36, 0.35)',
                    borderColor: 'rgba(251, 191, 36, 0.8)',
                    borderWidth: 1,
                    borderRadius: 4,
                    hoverBackgroundColor: 'rgba(251, 191, 36, 0.55)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: {
                    callbacks: { label: ctx => 'KSh ' + Number(ctx.raw).toLocaleString('en-KE', {minimumFractionDigits:2}) }
                }},
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748B', font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 7 }},
                    y: { grid: { color: 'rgba(100,116,139,0.1)' }, ticks: { color: '#64748B', font: { size: 10 }, callback: v => 'KSh ' + (v >= 1000 ? (v/1000).toFixed(1) + 'k' : v) }}
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }
}

// Generate sparklines
function spark(id, values) {
    const el = document.getElementById(id);
    if (!el || !values.length) return;
    const max = Math.max(...values, 1);
    el.innerHTML = values.map(v => `<div class="spark-bar" style="height:${Math.max(4, (v/max)*100)}%"></div>`).join('');
}
spark('sparkRevenue', <?= json_encode(array_slice($revenueChart['data'], -7)) ?>);
spark('sparkMonth', <?= json_encode($revenueChart['data']) ?>);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>

