<?php
/**
 * Super Admin Dashboard - JDH POS SaaS
 * Matches all_sales.php layout style
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_subscriptions', 'pos_invoices', 'users', 'branches', 'sales']);

// Initialize stats
$stats = [];
$error = '';
$recentCompanies = [];
$revenueChart = ['labels' => [], 'data' => []];

try {
    // Fetch dashboard data
    $stats['companies'] = [
        'total' => $pdo->query("SELECT COUNT(*) FROM pos_tenants")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'active'")->fetchColumn(),
        'trial' => $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'trialing'")->fetchColumn(),
        'new_this_month' => $pdo->query("SELECT COUNT(*) FROM pos_tenants WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn(),
    ];

    $stats['revenue'] = [
        'mrr' => $pdo->query("
            SELECT SUM(CASE
                WHEN billing_cycle = 'monthly' THEN amount
                WHEN billing_cycle = 'yearly' THEN amount / 12
                ELSE amount
            END) FROM pos_subscriptions WHERE status IN ('active', 'trialing')
        ")->fetchColumn() ?? 0,
        'this_month' => $pdo->query("
            SELECT SUM(amount) FROM pos_invoices
            WHERE status = 'paid' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ")->fetchColumn() ?? 0,
        'paying_companies' => $pdo->query("SELECT COUNT(DISTINCT tenant_id) FROM pos_invoices WHERE status = 'paid'")->fetchColumn(),
    ];

    $stats['subscriptions'] = [
        'active' => $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'active'")->fetchColumn(),
        'trial' => $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'trialing'")->fetchColumn(),
        'past_due' => $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'past_due'")->fetchColumn(),
        'expired' => $pdo->query("SELECT COUNT(*) FROM pos_subscriptions WHERE status = 'expired'")->fetchColumn(),
    ];

    $stats['tenants'] = [
        'suspended' => $pdo->query("SELECT COUNT(*) FROM pos_tenants WHERE status = 'suspended' OR is_suspended = 1")->fetchColumn(),
    ];
    
    $stats['usage'] = [
        'total_active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn(),
        'total_branches' => $pdo->query("SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL")->fetchColumn(),
        'monthly_transactions' => $pdo->query("SELECT COUNT(*) FROM sales WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn(),
    ];

    // Revenue growth: this month vs last month
    $thisMonthRevenue = (float) $pdo->query("
        SELECT COALESCE(SUM(amount), 0) FROM pos_invoices
        WHERE status = 'paid' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ")->fetchColumn();
    $lastMonthRevenue = (float) $pdo->query("
        SELECT COALESCE(SUM(amount), 0) FROM pos_invoices
        WHERE status = 'paid'
          AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01')
          AND created_at < DATE_FORMAT(NOW(), '%Y-%m-01')
    ")->fetchColumn();
    if ($lastMonthRevenue > 0) {
        $stats['revenue']['growth_pct'] = round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
    } else {
        $stats['revenue']['growth_pct'] = $thisMonthRevenue > 0 ? 100 : 0;
    }

    // Churn rate: cancelled subs in last 30 days / total active 30 days ago
    $cancelledLast30 = (int) $pdo->query("
        SELECT COUNT(*) FROM pos_subscriptions
        WHERE status = 'cancelled' AND cancelled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetchColumn();
    $active30DaysAgo = (int) $pdo->query("
        SELECT COUNT(*) FROM pos_subscriptions
        WHERE status IN ('active','trialing')
          AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ")->fetchColumn();
    $stats['churn_rate'] = $active30DaysAgo > 0 ? round(($cancelledLast30 / $active30DaysAgo) * 100, 1) : 0;

    // Security: recent failed login attempts from admins/users
    $stats['security'] = [
        'failed_logins' => (int) $pdo->query("
            SELECT COUNT(*) FROM users
            WHERE failed_login_attempts > 0
        ")->fetchColumn(),
    ];

    // Last backup file
    $backupDir = __DIR__ . '/../storage/backups';
    $lastBackup = 'Never';
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/*.sql');
        if (!empty($files)) {
            usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
            $lastBackup = date('M j', filemtime($files[0]));
        }
    }
    $stats['last_backup'] = $lastBackup;

    // ---- POS REAL-TIME METRICS ----
    $stats['pos_sales'] = [
        'today' => (float) ($pdo->query("SELECT COALESCE(SUM(total), 0) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?? 0),
        'yesterday' => (float) ($pdo->query("SELECT COALESCE(SUM(total), 0) FROM sales WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn() ?? 0),
        'today_count' => (int) $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'yesterday_count' => (int) $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn(),
    ];

    // POS Sales chart (last 14 days from real sales table)
    $posChartRows = $pdo->query("
        SELECT DATE(created_at) as day, COALESCE(SUM(total), 0) as total
        FROM sales
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day
    ")->fetchAll(PDO::FETCH_ASSOC);
    $posDays = [];
    for ($i = 13; $i >= 0; $i--) {
        $posDays[date('Y-m-d', strtotime("-{$i} days"))] = 0;
    }
    foreach ($posChartRows as $r) { $posDays[$r['day']] = (float) $r['total']; }
    $posChart = ['labels' => [], 'data' => []];
    foreach ($posDays as $d => $v) {
        $posChart['labels'][] = date('M j', strtotime($d));
        $posChart['data'][] = $v;
    }

    // Recent transactions (POS sales)
    $recentTransactions = $pdo->query("
        SELECT s.id, s.total, s.created_at, s.payment_method,
               u.name as user_name, b.name as branch_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN branches b ON s.branch_id = b.id
        ORDER BY s.created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top products this month
    $topProducts = $pdo->query("
        SELECT p.name, p.sku, SUM(si.quantity) as total_qty, SUM(si.quantity * si.price) as total_revenue
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN sales s ON si.sale_id = s.id
        WHERE s.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        GROUP BY p.id
        ORDER BY total_revenue DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Payment methods breakdown (today)
    $payMethods = $pdo->query("
        SELECT payment_method, COUNT(*) as cnt, SUM(total) as amt
        FROM sales
        WHERE DATE(created_at) = CURDATE()
        GROUP BY payment_method
        ORDER BY amt DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Branch performance today
    $branchPerf = $pdo->query("
        SELECT b.name, COUNT(s.id) as sale_count, COALESCE(SUM(s.total), 0) as revenue
        FROM branches b
        LEFT JOIN sales s ON b.id = s.branch_id AND DATE(s.created_at) = CURDATE()
        WHERE b.deleted_at IS NULL
        GROUP BY b.id
        ORDER BY revenue DESC
        LIMIT 4
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Low stock alerts
    $lowStock = $pdo->query("
        SELECT p.name, p.sku, i.stock, i.reorder_level
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.stock <= i.reorder_level AND i.stock > 0
        ORDER BY i.stock ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent companies
    $recentCompanies = $pdo->query("
        SELECT t.*, s.plan_id, s.status as sub_status,
               JSON_UNQUOTE(JSON_EXTRACT(t.settings, '$.business_type')) as business_type
        FROM pos_tenants t
        LEFT JOIN pos_subscriptions s ON t.id = s.tenant_id
        ORDER BY t.created_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Chart data
    $chartData = $pdo->query("
        SELECT DATE(created_at) as date, SUM(amount) as total
        FROM pos_invoices
        WHERE status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($chartData as $row) {
        $revenueChart['labels'][] = date('M d', strtotime($row['date']));
        $revenueChart['data'][] = (float)$row['total'];
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Helper functions
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function statusBadge($status) {
    $colors = [
        'active' => 'bg-emerald-500/20 text-emerald-400',
        'trial' => 'bg-blue-500/20 text-blue-400',
        'trialing' => 'bg-blue-500/20 text-blue-400',
        'past_due' => 'bg-amber-500/20 text-amber-400',
        'cancelled' => 'bg-rose-500/20 text-rose-400',
        'suspended' => 'bg-red-500/20 text-red-400',
        'expired' => 'bg-orange-500/20 text-orange-400',
    ];
    $label = $status === 'trialing' ? 'Trial' : ucfirst(str_replace('_', ' ', $status));
    $cls = $colors[$status] ?? 'bg-slate-500/20 text-slate-400';
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $cls . '">' . $label . '</span>';
}

$page_title = 'Super Admin Dashboard';
$current_page = 'dashboard';
ob_start();
?>

<?php
// Compute health score (0-100 based on various metrics)
$healthComponents = [];
$healthComponents[] = ($stats['subscriptions']['active'] > 0) ? min(30, 30) : 0;
$healthComponents[] = ($stats['churn_rate'] < 5) ? 20 : (($stats['churn_rate'] < 10) ? 10 : 0);
$healthComponents[] = ($stats['revenue']['growth_pct'] > 0) ? min(20, 20) : (($stats['revenue']['growth_pct'] == 0) ? 10 : 0);
$healthComponents[] = ($stats['pos_sales']['today'] > 0) ? 15 : 0;
$healthComponents[] = ($stats['security']['failed_logins'] == 0) ? 15 : 5;
$platformScore = array_sum($healthComponents);

$dayGrowth = $stats['pos_sales']['yesterday'] > 0
    ? round((($stats['pos_sales']['today'] - $stats['pos_sales']['yesterday']) / $stats['pos_sales']['yesterday']) * 100, 1)
    : ($stats['pos_sales']['today'] > 0 ? 100 : 0);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<?php if ($error): ?>
<div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="space-y-5">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1">
                <i class="fas fa-shield-alt text-xs"></i>
                <span>Super Admin</span>
                <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-medium">Platform</span>
            </div>
            <h1 class="text-2xl font-bold text-white">
                Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>,
                <span class="bg-gradient-to-r from-amber-400 to-yellow-500 bg-clip-text text-transparent"><?= htmlspecialchars(admin_current_name()) ?></span>
            </h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1"><i class="fas fa-circle text-[8px] text-emerald-400"></i>All systems operational</span>
                <span class="text-slate-700">|</span>
                <span class="inline-flex items-center gap-1 text-amber-400">
                    <i class="fas fa-building text-[10px]"></i>
                    <?= number_format($stats['companies']['total']) ?> tenants
                </span>
                <span class="text-slate-700">|</span>
                <span><i class="far fa-clock text-slate-600"></i> <span id="liveClock"><?= date('D, M j · H:i') ?></span></span>
            </p>
        </div>
        <div class="flex gap-2 items-center">
            <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800/80 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-colors">
                <i class="fas fa-sync-alt text-xs"></i> Refresh
            </button>
            <a href="saas-analytics.php" class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800/80 border border-slate-700 rounded-lg text-slate-400 text-sm hover:bg-slate-700 transition-colors">
                <i class="fas fa-chart-line text-xs"></i> Analytics
            </a>
            <a href="<?= base_url('auth/signup.php') ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm hover:bg-amber-500/25 transition-all">
                <i class="fas fa-plus"></i> New Tenant
            </a>
        </div>
    </div>

    <!-- Health Bar -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="relative w-[76px] h-[76px] flex-shrink-0">
                    <svg width="76" height="76" viewBox="0 0 92 92" style="transform: rotate(-90deg);">
                        <circle cx="46" cy="46" r="40" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="6"/>
                        <circle cx="46" cy="46" r="40" fill="none" stroke="url(#scoreGrad)" stroke-width="6" stroke-linecap="round" stroke-dasharray="<?= round($platformScore * 251.3 / 100, 2) ?> 251.3"/>
                        <defs>
                            <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#34d399"/>
                                <stop offset="100%" stop-color="#fbbf24"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xl font-bold text-white"><?= (int) $platformScore ?></span>
                        <span class="text-[9px] uppercase tracking-wider text-slate-500 font-semibold">Health</span>
                    </div>
                </div>
                <div class="hidden lg:block w-px h-12 bg-white/5"></div>
            </div>

            <div class="flex-1 grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">vs Yesterday</div>
                    <div class="mt-1 flex items-center gap-1.5">
                        <i class="fas fa-arrow-<?= $dayGrowth >= 0 ? 'up text-emerald-400' : 'down text-rose-400' ?> text-xs"></i>
                        <span class="text-base font-semibold <?= $dayGrowth >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                            <?= number_format(abs($dayGrowth), 1) ?>%
                        </span>
                    </div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">MoM Growth</div>
                    <div class="mt-1 text-base font-semibold <?= $stats['revenue']['growth_pct'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= ($stats['revenue']['growth_pct'] >= 0 ? '+' : '') . number_format($stats['revenue']['growth_pct'], 1) ?>%
                    </div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Churn Rate</div>
                    <div class="mt-1 text-base font-semibold <?= $stats['churn_rate'] < 5 ? 'text-emerald-400' : ($stats['churn_rate'] < 10 ? 'text-amber-400' : 'text-rose-400') ?>"><?= $stats['churn_rate'] ?>%</div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Active Subs</div>
                    <div class="mt-1 text-base font-semibold text-blue-400"><?= number_format($stats['subscriptions']['active']) ?></div>
                </div>
                <div class="bg-slate-900/50 rounded-lg p-2">
                    <div class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Failed Logins</div>
                    <div class="mt-1 text-base font-semibold <?= $stats['security']['failed_logins'] == 0 ? 'text-emerald-400' : 'text-amber-400' ?>"><?= number_format($stats['security']['failed_logins']) ?></div>
                </div>
            </div>

            <?php if (!empty($lowStock)): ?>
            <a href="<?= base_url('inventory/inventory.php') ?>?status=low" target="_blank" class="flex items-center gap-3 p-2 rounded-lg bg-red-500/10 border border-red-500/30 hover:bg-red-500/15 transition-colors animate-pulse">
                <div class="w-8 h-8 rounded-full bg-red-500/20 flex items-center justify-center">
                    <i class="fas fa-bell text-red-400 text-sm"></i>
                </div>
                <div>
                    <div class="text-sm font-semibold text-red-300"><?= count($lowStock) ?> alerts</div>
                    <div class="text-[11px] text-red-300/70">Low stock items</div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <?php
        $suspendedCount = $stats['tenants']['suspended'] ?? 0;
        $expiredCount   = $stats['subscriptions']['expired'] ?? 0;
        $subColor       = ($stats['subscriptions']['past_due'] > 0 || $expiredCount > 0) ? 'rose' : 'emerald';
        $subSub         = ($stats['subscriptions']['past_due'] > 0 ? $stats['subscriptions']['past_due'] . ' past due' : '')
                          . ($expiredCount > 0 ? ($stats['subscriptions']['past_due'] > 0 ? ' · ' : '') . $expiredCount . ' expired' : '');
        if (empty($subSub)) $subSub = 'All healthy';

        $kpis = [
            ['lbl' => 'Total Companies', 'val' => number_format($stats['companies']['total']), 'sub' => $stats['companies']['active'] . ' active · ' . $stats['companies']['trial'] . ' trial' . ($suspendedCount > 0 ? ' · ' . $suspendedCount . ' suspended' : ''), 'ico' => 'fa-building', 'color' => 'blue'],
            ['lbl' => 'Monthly Recurring', 'val' => formatCurrency($stats['revenue']['mrr']), 'sub' => $stats['revenue']['paying_companies'] . ' paying companies', 'ico' => 'fa-coins', 'color' => 'amber', 'trend' => $stats['revenue']['growth_pct']],
            ['lbl' => 'Sales Today', 'val' => formatCurrency($stats['pos_sales']['today']), 'sub' => $stats['pos_sales']['today_count'] . ' transactions', 'ico' => 'fa-cash-register', 'color' => 'emerald', 'trend' => $dayGrowth],
            ['lbl' => 'Active Users', 'val' => number_format($stats['usage']['total_active_users']), 'sub' => $stats['usage']['total_branches'] . ' branches', 'ico' => 'fa-users', 'color' => 'purple'],
            ['lbl' => 'This Month Revenue', 'val' => formatCurrency($stats['revenue']['this_month']), 'sub' => number_format($stats['usage']['monthly_transactions']) . ' transactions', 'ico' => 'fa-calendar-week', 'color' => 'orange'],
            ['lbl' => 'Subscriptions', 'val' => number_format($stats['subscriptions']['active'] + $stats['subscriptions']['trial']), 'sub' => $subSub, 'ico' => 'fa-credit-card', 'color' => $subColor],
        ];
        $colors = [
            'amber' => 'rgba(251,191,36,0.14)', 'blue' => 'rgba(59,130,246,0.14)',
            'purple' => 'rgba(139,92,246,0.14)', 'orange' => 'rgba(249,115,22,0.14)',
            'emerald' => 'rgba(16,185,129,0.14)', 'rose' => 'rgba(239,68,68,0.14)',
        ];
        foreach ($kpis as $kpi):
            $bgColor = $colors[$kpi['color']] ?? $colors['amber'];
            $textColor = match($kpi['color']) {
                'amber' => 'text-amber-400', 'emerald' => 'text-emerald-400',
                'rose' => 'text-red-400', 'blue' => 'text-blue-400',
                'purple' => 'text-purple-400', 'orange' => 'text-orange-400',
                default => 'text-slate-300',
            };
        ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 hover:border-slate-600 transition-colors">
            <div class="flex items-start justify-between">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: <?= $bgColor ?>;">
                    <i class="fas <?= $kpi['ico'] ?> <?= $textColor ?> text-sm"></i>
                </span>
                <?php if (isset($kpi['trend']) && $kpi['trend'] != 0): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs <?= $kpi['trend'] > 0 ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400' ?>">
                        <i class="fas fa-arrow-<?= $kpi['trend'] > 0 ? 'up' : 'down' ?> text-[9px]"></i>
                        <?= number_format(abs($kpi['trend']), 1) ?>%
                    </span>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <div class="text-[11px] text-slate-500 uppercase tracking-wide font-semibold"><?= htmlspecialchars($kpi['lbl']) ?></div>
                <div class="text-xl font-bold text-white mt-1"><?= $kpi['val'] ?></div>
                <div class="text-[11px] text-slate-600 mt-0.5"><?= htmlspecialchars($kpi['sub']) ?></div>
            </div>
        </div>
        <?php endforeach; ?> 
    </div>

    <!-- Revenue Trend + Payment Mix -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden xl:col-span-2">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">POS Sales Trend</h3>
                    <p class="text-xs text-slate-500">Last 14 days · live data</p>
                </div>
                <span class="px-2 py-1 text-xs rounded-full bg-amber-500/20 text-amber-400">Revenue</span>
            </div>
            <div class="p-4">
                <div style="height: 240px;"><canvas id="posChart"></canvas></div>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Payment Mix</h3>
                    <p class="text-xs text-slate-500">Today's breakdown</p>
                </div>
                <i class="fas fa-credit-card text-slate-500 text-xs"></i>
            </div>
            <div class="p-4">
                <div style="height: 170px;"><canvas id="paymentChart"></canvas></div>
                <div class="mt-4 space-y-1.5">
                <?php if (empty($payMethods)): ?>
                    <p class="text-center text-slate-500 py-4 text-sm">No sales today yet</p>
                <?php else:
                    $pmChartColors = ['#fbbf24', '#60a5fa', '#34d399', '#a78bfa', '#f87171', '#22d3ee'];
                    $totalToday = array_sum(array_column($payMethods, 'amt')) ?: 1;
                    foreach ($payMethods as $pmIdx => $pm):
                        $pct = round(($pm['amt'] / $totalToday) * 100, 1);
                ?>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background: <?= $pmChartColors[$pmIdx % count($pmChartColors)] ?>"></span>
                        <span class="text-slate-400 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $pm['payment_method'] ?? 'Unknown')) ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500"><?= $pct ?>%</span>
                        <span class="text-white font-semibold"><?= formatCurrency($pm['amt']) ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions + Top Products -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden lg:col-span-2">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Recent Transactions</h3>
                    <p class="text-xs text-slate-500">Latest POS sales</p>
                </div>
                <a href="<?= base_url('pos/all_sales.php') ?>" target="_blank" class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold transition">View all <i class="fas fa-arrow-right ml-1 text-[9px]"></i></a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-900/50 border-b border-slate-700/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Sale</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Cashier</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php if (empty($recentTransactions)): ?>
                            <tr><td colspan="5" class="text-center text-slate-500 py-12">No transactions yet</td></tr>
                        <?php else: foreach (array_slice($recentTransactions, 0, 6) as $tx):
                            $txPm = $tx['payment_method'] ?? '';
                            $txPmClass = match ($txPm) {
                                'cash' => 'bg-emerald-500/15 text-emerald-400',
                                'mpesa' => 'bg-amber-500/15 text-amber-400',
                                'card' => 'bg-blue-500/15 text-blue-400',
                                default => 'bg-slate-500/15 text-slate-400',
                            };
                        ?>
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-4 py-3"><span class="font-mono text-amber-400 font-semibold text-xs">#<?= str_pad((string) $tx['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                                <td class="px-4 py-3 text-slate-300 text-sm"><?= htmlspecialchars(mb_strimwidth($tx['user_name'] ?? '—', 0, 22, '…')) ?></td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs <?= $txPmClass ?>"><?= htmlspecialchars(ucfirst($txPm ?: 'N/A')) ?></span></td>
                                <td class="px-4 py-3 text-right font-semibold text-white"><?= formatCurrency($tx['total']) ?></td>
                                <td class="px-4 py-3 text-right text-slate-500 text-[11px]"><?= date('M j, g:i a', strtotime($tx['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Top Products</h3>
                    <p class="text-xs text-slate-500">By revenue this month</p>
                </div>
                <a href="<?= base_url('products/products.php') ?>" target="_blank" class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold transition">View all <i class="fas fa-arrow-right ml-1 text-[9px]"></i></a>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($topProducts)): ?>
                    <p class="text-center text-slate-500 py-10 text-sm">No data yet</p>
                <?php else:
                    $maxQty = (float) max(array_column($topProducts, 'total_qty') ?: [1]);
                    foreach (array_slice($topProducts, 0, 5) as $i => $prod):
                        $bar = $maxQty > 0 ? round(((float) ($prod['total_qty'] ?? 0) / $maxQty) * 100) : 0;
                ?>
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-md flex items-center justify-center text-xs font-bold flex-shrink-0 <?= $i === 0 ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-700/60 text-slate-400' ?>">
                        <?= $i + 1 ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-white font-medium truncate"><?= htmlspecialchars($prod['name'] ?? '—') ?></span>
                            <span class="text-xs text-amber-400 font-semibold ml-2"><?= formatCurrency($prod['total_revenue']) ?></span>
                        </div>
                        <div class="h-1.5 w-full bg-slate-700/60 rounded-full overflow-hidden mt-0.5"><div class="h-full bg-amber-500/60 rounded-full" style="width: <?= $bar ?>%"></div></div>
                        <div class="text-[11px] text-slate-500 mt-1"><?= number_format((float) ($prod['total_qty'] ?? 0)) ?> units sold</div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="border-t border-slate-800 pt-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-1 h-5 bg-amber-500 rounded-full"></span>
            <h3 class="text-sm font-semibold text-white uppercase tracking-wide">Quick Actions</h3>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            <?php
            $actions = [
                ['title' => 'New Sale', 'desc' => 'Open POS', 'url' => 'pos/pos.php', 'ico' => 'fa-cash-register', 'color' => 'amber'],
                ['title' => 'Inventory', 'desc' => 'Manage stock', 'url' => 'inventory/inventory.php', 'ico' => 'fa-boxes-stacked', 'color' => 'emerald'],
                ['title' => 'Tenants', 'desc' => 'Manage companies', 'url' => '', 'ico' => 'fa-building', 'color' => 'blue', 'admin_url' => 'companies.php'],
                ['title' => 'Users', 'desc' => 'Manage access', 'url' => '', 'ico' => 'fa-users', 'color' => 'purple', 'admin_url' => 'users.php'],
                ['title' => 'Reports', 'desc' => 'Analytics', 'url' => 'reports/reports.php', 'ico' => 'fa-chart-bar', 'color' => 'orange'],
                ['title' => 'Settings', 'desc' => 'Configure', 'url' => '', 'ico' => 'fa-cog', 'color' => 'rose', 'admin_url' => 'settings.php'],
            ];
            $actionColors = [
                'amber' => 'rgba(251,191,36,0.14)', 'emerald' => 'rgba(16,185,129,0.14)',
                'purple' => 'rgba(139,92,246,0.14)', 'blue' => 'rgba(59,130,246,0.14)',
                'orange' => 'rgba(249,115,22,0.14)', 'rose' => 'rgba(239,68,68,0.14)',
            ];
            foreach ($actions as $a):
                $aBg = $actionColors[$a['color']] ?? $actionColors['amber'];
                $aTxt = match($a['color']) {
                    'amber' => 'text-amber-400', 'emerald' => 'text-emerald-400',
                    'blue' => 'text-blue-400', 'purple' => 'text-purple-400',
                    'orange' => 'text-orange-400', 'rose' => 'text-rose-400',
                    default => 'text-slate-300',
                };
                $href = !empty($a['admin_url']) ? $a['admin_url'] : base_url($a['url']);
                $target = !empty($a['admin_url']) ? '' : ' target="_blank"';
            ?>
            <a href="<?= $href ?>"<?= $target ?> class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 text-center hover:border-slate-600 transition-colors">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center mx-auto mb-2" style="background: <?= $aBg ?>;">
                    <i class="fas <?= $a['ico'] ?> text-lg <?= $aTxt ?>"></i>
                </span>
                <div class="text-sm font-semibold text-white"><?= htmlspecialchars($a['title']) ?></div>
                <div class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($a['desc']) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Branch Performance + Recent Signups + System Status -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Branch Performance</h3>
                    <p class="text-xs text-slate-500">Today's sales</p>
                </div>
                <i class="fas fa-store text-slate-600"></i>
            </div>
            <?php if (empty($branchPerf)): ?>
                <div class="text-center text-slate-500 py-12 text-sm">No branch data today</div>
            <?php else:
                $maxBranch = (float) max(array_column($branchPerf, 'revenue') ?: [1]);
            ?>
            <div class="p-4 space-y-3">
                <?php foreach ($branchPerf as $bp):
                    $bw = $maxBranch > 0 ? round(((float) $bp['revenue'] / $maxBranch) * 100) : 0;
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-white font-medium"><?= htmlspecialchars($bp['name']) ?></span>
                        <span class="text-[11px] text-slate-500"><?= (int) $bp['sale_count'] ?> sales</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-slate-700/60 rounded-full overflow-hidden"><div class="h-full rounded-full" style="width: <?= $bw ?>%; background: linear-gradient(90deg, #60a5fa, #3b82f6);"></div></div>
                        <span class="text-xs text-white font-semibold flex-shrink-0"><?= formatCurrency($bp['revenue']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">Recent Signups</h3>
                    <p class="text-xs text-slate-500">New tenant companies</p>
                </div>
                <a href="companies.php" class="text-[11px] text-amber-400 hover:text-amber-300 font-semibold transition">View all</a>
            </div>
            <?php if (empty($recentCompanies)): ?>
                <div class="text-center text-slate-500 py-12 text-sm">No signups yet</div>
            <?php else: ?>
            <div class="divide-y divide-slate-700/40">
                <?php foreach (array_slice($recentCompanies, 0, 5) as $ci => $company):
                    $grad = ['from-amber-500 to-orange-500','from-blue-500 to-cyan-500','from-emerald-500 to-teal-500','from-purple-500 to-pink-500','from-rose-500 to-red-500'][$ci % 5];
                ?>
                <div class="flex items-center justify-between p-3 hover:bg-slate-700/20 transition-colors">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br <?= $grad ?> flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">
                            <?= strtoupper(substr($company['name'], 0, 1)) ?>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm text-white font-medium truncate"><?= htmlspecialchars($company['name']) ?></div>
                            <div class="text-[10px] text-slate-500"><?= date('M j, Y', strtotime($company['created_at'])) ?></div>
                        </div>
                    </div>
                    <?= statusBadge($company['sub_status'] ?? 'trial') ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-700/60 flex justify-between items-center">
                <div>
                    <h3 class="text-sm font-semibold text-white">System Status</h3>
                    <p class="text-xs text-slate-500">Infrastructure health</p>
                </div>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-500/15 text-emerald-400">Online</span>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center"><i class="fas fa-check-circle text-emerald-400 text-sm"></i></div>
                        <div><div class="text-sm text-white font-medium">Platform</div><div class="text-[10px] text-slate-500">All services running</div></div>
                    </div>
                    <span class="text-emerald-400 text-xs font-semibold">OK</span>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center"><i class="fas fa-server text-blue-400 text-sm"></i></div>
                        <div><div class="text-sm text-white font-medium">Database</div><div class="text-[10px] text-slate-500">MySQL connected</div></div>
                    </div>
                    <span class="text-blue-400 text-xs font-semibold">OK</span>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center"><i class="fas fa-shield-alt text-purple-400 text-sm"></i></div>
                        <div><div class="text-sm text-white font-medium">Security</div><div class="text-[10px] text-slate-500"><?= $stats['security']['failed_logins'] ?> failed login attempts</div></div>
                    </div>
                    <span class="<?= $stats['security']['failed_logins'] == 0 ? 'text-emerald-400' : 'text-amber-400' ?> text-xs font-semibold"><?= $stats['security']['failed_logins'] == 0 ? 'OK' : 'Warn' ?></span>
                </div>
                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center"><i class="fas fa-clock text-amber-400 text-sm"></i></div>
                        <div><div class="text-sm text-white font-medium">Backups</div><div class="text-[10px] text-slate-500">Last: <?= htmlspecialchars($stats['last_backup']) ?></div></div>
                    </div>
                    <span class="text-amber-400 text-xs font-semibold"><?= $stats['last_backup'] === 'Never' ? 'None' : 'OK' ?></span>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
$pmLabels = array_map(fn($pm) => ucfirst(str_replace('_', ' ', $pm['payment_method'] ?? 'Unknown')), $payMethods);
$pmVals = array_map(fn($pm) => (float) $pm['amt'], $payMethods);
$pmChartColorsJs = ['#fbbf24', '#60a5fa', '#34d399', '#a78bfa', '#f87171', '#22d3ee'];
?>
<script>
(function() {
    const fmtCur = (v) => '$' + Number(v || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const tooltip = {
        backgroundColor: '#0f172a', titleColor: '#f8fafc', bodyColor: '#cbd5e1',
        borderColor: 'rgba(255,255,255,0.08)', borderWidth: 1, cornerRadius: 8, padding: 12,
    };
    const gridColor = 'rgba(255,255,255,0.05)';
    const tickColor = '#64748b';

    function initCharts() {
        // POS Sales Trend
        const posCtx = document.getElementById('posChart')?.getContext('2d');
        if (posCtx) {
            new Chart(posCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($posChart['labels']) ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?= json_encode($posChart['data']) ?>,
                        borderColor: '#fbbf24',
                        backgroundColor: 'rgba(251,191,36,0.10)',
                        tension: 0.4, fill: true,
                        pointRadius: 3, pointBackgroundColor: '#fbbf24',
                        pointBorderColor: '#0f172a', pointBorderWidth: 2, borderWidth: 2.5,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: (c) => 'Revenue: ' + fmtCur(c.parsed.y) } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 7 } },
                        y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, ticks: { color: tickColor, font: { size: 10 }, callback: (v) => '$' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v) } }
                    }
                }
            });
        }

        // Payment donut
        const paymentCtx = document.getElementById('paymentChart')?.getContext('2d');
        if (paymentCtx && <?= (!empty($payMethods) && array_sum($pmVals) > 0) ? 'true' : 'false' ?>) {
            new Chart(paymentCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($pmLabels) ?>,
                    datasets: [{
                        data: <?= json_encode($pmVals) ?>,
                        backgroundColor: <?= json_encode(array_slice($pmChartColorsJs, 0, count($pmVals))) ?>,
                        borderColor: '#0f172a', borderWidth: 3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: true, cutout: '68%', plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: (c) => c.label + ': ' + fmtCur(c.parsed) } } } }
            });
        }
    }

    // Live clock
    function tick() {
        const now = new Date();
        const opts = { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false };
        const el = document.getElementById('liveClock');
        if (el) el.textContent = now.toLocaleString('en-US', opts).replace(',', ' ·');
    }

    document.addEventListener('DOMContentLoaded', function() {
        initCharts();
        tick();
        setInterval(tick, 30000);
    });
})();
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
