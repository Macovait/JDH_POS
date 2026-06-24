<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Unauthorized'); }

$branch_name = get_current_branch_name();

define('TAG_ANALYTICS_LIMIT', 20);
define('TAG_ANALYTICS_TOP_PRODUCTS_LIMIT', 15);

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $days = max(7, min(365, (int)($_GET['days'] ?? 90)));
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tag-analytics-' . date('Ymd') . '.csv"');
    $exp = $pdo->prepare("
        SELECT t.name as tag, t.color,
            COUNT(DISTINCT r.product_id) as products,
            COALESCE(SUM(si.quantity),0) as units_sold,
            COUNT(DISTINCT si.sale_id) as orders,
            COALESCE(SUM(si.subtotal),0) as revenue
        FROM product_tags t
        JOIN product_tag_relations r ON r.tag_id=t.id AND r.tenant_id=t.tenant_id
        JOIN products p ON p.id=r.product_id AND p.tenant_id=r.tenant_id AND p.deleted_at IS NULL
        JOIN sale_items si ON si.product_id=p.id
        JOIN sales s ON s.id=si.sale_id AND s.tenant_id=?
        WHERE t.tenant_id=? AND t.is_active=1
          AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
          AND s.status = 'completed' AND s.voided = 0
        GROUP BY t.id ORDER BY revenue DESC
    ");
    $exp->execute([$tenant_id, $tenant_id, $days]);
    $rows = $exp->fetchAll(PDO::FETCH_ASSOC);
    echo implode(',', ['Tag','Products','Units Sold','Orders','Revenue']) . "\n";
    foreach ($rows as $row) {
        echo implode(',', [json_encode($row['tag']), $row['products'], $row['units_sold'], $row['orders'], number_format($row['revenue'],2)]) . "\n";
    }
    exit;
}

$days      = max(7, min(365, (int)($_GET['days'] ?? 90)));
$days_prev = $days * 2;
$pivot_months_count = max($days <= 30 ? 3 : ($days <= 90 ? 4 : 6), 1);

// Tag performance — current period
$tagStats = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.name, t.color, t.icon,
            (SELECT COUNT(DISTINCT r2.product_id) FROM product_tag_relations r2 WHERE r2.tag_id=t.id AND r2.tenant_id=t.tenant_id) as product_count,
            COALESCE(SUM(si.quantity), 0) as total_qty_sold,
            COALESCE(SUM(si.subtotal), 0) as total_revenue,
            COUNT(DISTINCT si.sale_id) as order_count
        FROM product_tags t
        JOIN product_tag_relations r ON r.tag_id=t.id AND r.tenant_id=t.tenant_id
        JOIN products p ON p.id=r.product_id AND p.tenant_id=r.tenant_id AND p.deleted_at IS NULL
        JOIN sale_items si ON si.product_id=p.id
        JOIN sales s ON s.id=si.sale_id AND s.tenant_id=?
        WHERE t.tenant_id=? AND t.is_active=1
          AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
          AND s.status = 'completed' AND s.voided = 0
        GROUP BY t.id ORDER BY total_revenue DESC LIMIT ". TAG_ANALYTICS_LIMIT
    );
    $stmt->execute([$tenant_id, $tenant_id, $days]);
    $tagStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $tagStats = []; }

// Tag performance — previous period (for trend)
$prevStats = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, COALESCE(SUM(si.subtotal),0) as prev_revenue
        FROM product_tags t
        JOIN product_tag_relations r ON r.tag_id=t.id AND r.tenant_id=t.tenant_id
        JOIN products p ON p.id=r.product_id AND p.tenant_id=r.tenant_id AND p.deleted_at IS NULL
        JOIN sale_items si ON si.product_id=p.id
        JOIN sales s ON s.id=si.sale_id AND s.tenant_id=?
        WHERE t.tenant_id=? AND t.is_active=1
          AND s.created_at BETWEEN DATE_SUB(CURDATE(), INTERVAL ? DAY) AND DATE_SUB(CURDATE(), INTERVAL ? DAY)
          AND s.status = 'completed' AND s.voided = 0
        GROUP BY t.id
    ");
    $stmt->execute([$tenant_id, $tenant_id, $days_prev, $days]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $prevStats[$r['id']] = (float)$r['prev_revenue'];
    }
} catch (Exception $e) { $prevStats = []; }

// Top selling tagged products
$topProducts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, t.name as tag_name, t.color,
            COALESCE(SUM(si.quantity),0) as total_sold,
            COALESCE(SUM(si.subtotal),0) as revenue
        FROM products p
        JOIN product_tag_relations r ON r.product_id=p.id AND r.tenant_id=p.tenant_id
        JOIN product_tags t ON t.id=r.tag_id AND t.is_active=1
        JOIN sale_items si ON si.product_id=p.id
        JOIN sales s ON s.id=si.sale_id AND s.tenant_id=p.tenant_id
        WHERE p.tenant_id=? AND p.active=1 AND p.deleted_at IS NULL
          AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
          AND s.status = 'completed' AND s.voided = 0
        GROUP BY p.id, t.id
        ORDER BY revenue DESC LIMIT ". TAG_ANALYTICS_TOP_PRODUCTS_LIMIT
    );
    $stmt->execute([$tenant_id, $days]);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $topProducts = []; }

// Monthly trend (dynamic based on date range)
$monthlyTrend = [];
try {
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(s.created_at,'%Y-%m') as month,
            t.id as tag_id, t.name as tag_name, t.color,
            COALESCE(SUM(si.subtotal),0) as revenue
        FROM product_tags t
        JOIN product_tag_relations r ON r.tag_id=t.id AND r.tenant_id=t.tenant_id
        JOIN products p ON p.id=r.product_id AND p.tenant_id=r.tenant_id AND p.deleted_at IS NULL
        JOIN sale_items si ON si.product_id=p.id
        JOIN sales s ON s.id=si.sale_id AND s.tenant_id=?
        WHERE t.tenant_id=? AND t.is_active=1
          AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
          AND s.status = 'completed' AND s.voided = 0
        GROUP BY month, t.id ORDER BY month ASC, revenue DESC
    ");
    $stmt->execute([$tenant_id, $tenant_id, $pivot_months_count]);
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $monthlyTrend = []; }

// Aggregate KPIs
$kpi_revenue   = array_sum(array_column($tagStats, 'total_revenue'));
$kpi_orders    = array_sum(array_column($tagStats, 'order_count'));
$kpi_units     = array_sum(array_column($tagStats, 'total_qty_sold'));
$kpi_tags      = count($tagStats);
$kpi_aov       = $kpi_orders > 0 ? $kpi_revenue / $kpi_orders : 0;
$max_revenue   = $tagStats ? max(array_column($tagStats, 'total_revenue')) : 1;
$max_tp_rev    = $topProducts ? max(array_column($topProducts, 'revenue')) : 1;

// Build pivot for monthly trend
$pivot_months  = [];
$pivot_tags    = [];
$pivot_data    = [];
foreach ($monthlyTrend as $mt) {
    if (!in_array($mt['month'], $pivot_months)) $pivot_months[] = $mt['month'];
    if (!isset($pivot_tags[$mt['tag_id']])) $pivot_tags[$mt['tag_id']] = ['name' => $mt['tag_name'], 'color' => $mt['color']];
    $pivot_data[$mt['tag_id']][$mt['month']] = (float)$mt['revenue'];
}
sort($pivot_months);

$page_title = 'Tag Analytics | ' . (defined('APP_NAME') ? APP_NAME : 'JDH POS');
ob_start();
?>

<!-- ═══ Header ═══════════════════════════════════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="product-tags.php" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-amber-400 transition-colors mb-1">
            <i class="fas fa-arrow-left text-[10px]"></i> Back to Tags
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-chart-pie text-amber-400"></i> Tag Analytics
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Performance breakdown in <span class="text-amber-400"><?php echo htmlspecialchars($branch_name ?? ''); ?></span></p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <!-- Date range pills -->
        <?php foreach ([7=>'7d',30=>'30d',90=>'90d',180=>'6mo',365=>'12mo'] as $d=>$label): ?>
        <a href="?days=<?php echo $d; ?>"
           class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors <?php echo $days==$d ? 'bg-amber-500/20 border-amber-500/50 text-amber-400' : 'bg-slate-800 border-slate-700 text-slate-400 hover:border-amber-500/30 hover:text-white'; ?>">
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
        <a href="?export=csv&days=<?php echo $days; ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs font-semibold hover:bg-slate-600 transition-colors">
            <i class="fas fa-download text-[10px]"></i> Export CSV
        </a>
    </div>
</div>

<!-- ═══ KPI Bar ════════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
    <?php
    $kpis = [
        ['fas fa-tags',       'amber-400',   'bg-amber-500/10',   'Active Tags',     number_format($kpi_tags)],
        ['fas fa-coins',      'emerald-400', 'bg-emerald-500/10', 'Total Revenue',   format_currency($kpi_revenue)],
        ['fas fa-receipt',    'blue-400',    'bg-blue-500/10',    'Orders',          number_format($kpi_orders)],
        ['fas fa-boxes',      'purple-400',  'bg-purple-500/10',  'Units Sold',      number_format($kpi_units)],
        ['fas fa-calculator', 'rose-400',    'bg-rose-500/10',    'Avg Order Value', format_currency($kpi_aov)],
    ];
    foreach ($kpis as [$icon,$color,$bg,$label,$val]):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $bg; ?> flex items-center justify-center shrink-0">
            <i class="<?php echo $icon; ?> text-<?php echo $color; ?> text-xs"></i>
        </div>
        <div>
            <div class="text-xl font-bold text-white"><?php echo $val; ?></div>
            <div class="text-[10px] text-slate-500 uppercase tracking-wide"><?php echo $label; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($tagStats)): ?>
<!-- Empty state -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl py-16 text-center mb-5">
    <i class="fas fa-chart-pie text-4xl text-slate-700 mb-3 block"></i>
    <p class="text-slate-400 font-medium mb-1">No data for this period</p>
    <p class="text-slate-500 text-sm mb-4">Sales with tagged products will appear here once recorded.</p>
    <a href="product-tags.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
        <i class="fas fa-tags text-xs"></i> Manage Tags
    </a>
</div>
<?php else: ?>

<!-- ═══ Revenue Bar Chart ══════════════════════════════════════════ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 mb-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-chart-bar text-amber-400 mr-2"></i>Revenue by Tag</h3>
        <span class="text-xs text-slate-500">Last <?php echo $days; ?> days</span>
    </div>
    <div class="space-y-2.5">
        <?php foreach ($tagStats as $stat):
            $bar_pct = $max_revenue > 0 ? round((float)$stat['total_revenue'] / $max_revenue * 100) : 0;
            $share   = $kpi_revenue > 0 ? round((float)$stat['total_revenue'] / $kpi_revenue * 100, 1) : 0;
        ?>
        <div class="flex items-center gap-3">
            <div class="w-28 shrink-0 flex items-center gap-1.5">
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[9px] shrink-0"
                      style="background:<?php echo htmlspecialchars($stat['color']); ?>25;color:<?php echo htmlspecialchars($stat['color']); ?>;">
                    <i class="fas <?php echo htmlspecialchars($stat['icon'] ?: 'fa-tag'); ?>"></i>
                </span>
                <span class="text-xs text-slate-300 truncate"><?php echo htmlspecialchars($stat['name']); ?></span>
            </div>
            <div class="flex-1 bg-slate-700/40 rounded h-5 overflow-hidden relative">
                <div class="h-full rounded transition-all duration-500"
                     style="width:<?php echo $bar_pct; ?>%;background:<?php echo htmlspecialchars($stat['color']); ?>55;"></div>
                <span class="absolute inset-0 flex items-center px-2 text-[10px] font-semibold text-white">
                    <?php echo format_currency((float)$stat['total_revenue'], null, true); ?>
                </span>
            </div>
            <span class="w-10 text-right text-xs text-slate-500 shrink-0"><?php echo $share; ?>%</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ Tag Performance Table ══════════════════════════════════════ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
    <div class="px-4 py-3 border-b border-slate-700/40 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-table text-amber-400 mr-2"></i>Tag Performance</h3>
        <span class="text-xs text-slate-500">vs previous <?php echo $days; ?> days</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-900/50 border-b border-slate-700/60">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-6">#</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tag</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Products</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Units</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Orders</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">AOV</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Revenue</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Share</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Trend</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php foreach ($tagStats as $i => $stat):
                    $prev   = $prevStats[$stat['id']] ?? 0;
                    $curr   = (float)$stat['total_revenue'];
                    $aov    = (int)$stat['order_count'] > 0 ? $curr / (int)$stat['order_count'] : 0;
                    $share  = $kpi_revenue > 0 ? round($curr / $kpi_revenue * 100, 1) : 0;
                    $delta  = $prev > 0 ? round(($curr - $prev) / $prev * 100, 1) : null;
                ?>
                <tr class="hover:bg-slate-700/20 transition-colors">
                    <td class="px-3 py-2.5 text-slate-600 font-semibold"><?php echo $i+1; ?></td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium"
                              style="background:<?php echo htmlspecialchars($stat['color']); ?>20;color:<?php echo htmlspecialchars($stat['color']); ?>;">
                            <i class="fas <?php echo htmlspecialchars($stat['icon'] ?: 'fa-tag'); ?> text-[9px]"></i>
                            <?php echo htmlspecialchars($stat['name']); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right text-slate-300"><?php echo number_format((int)$stat['product_count']); ?></td>
                    <td class="px-3 py-2.5 text-right text-slate-300"><?php echo number_format((float)$stat['total_qty_sold']); ?></td>
                    <td class="px-3 py-2.5 text-right text-slate-300"><?php echo number_format((int)$stat['order_count']); ?></td>
                    <td class="px-3 py-2.5 text-right text-slate-400"><?php echo format_currency($aov); ?></td>
                    <td class="px-3 py-2.5 text-right font-semibold text-emerald-400"><?php echo format_currency($curr); ?></td>
                    <td class="px-3 py-2.5 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <span class="text-slate-400"><?php echo $share; ?>%</span>
                            <div class="w-12 bg-slate-700/40 rounded h-1.5 overflow-hidden">
                                <div class="h-full rounded" style="width:<?php echo $share; ?>%;background:<?php echo htmlspecialchars($stat['color']); ?>;"></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        <?php if ($delta === null): ?>
                            <span class="text-slate-600 text-[10px]">—</span>
                        <?php elseif ($delta > 0): ?>
                            <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-emerald-400">
                                <i class="fas fa-arrow-up text-[8px]"></i> <?php echo $delta; ?>%
                            </span>
                        <?php elseif ($delta < 0): ?>
                            <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-red-400">
                                <i class="fas fa-arrow-down text-[8px]"></i> <?php echo abs($delta); ?>%
                            </span>
                        <?php else: ?>
                            <span class="text-slate-500 text-[10px]">0%</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ═══ Top Products ═══════════════════════════════════════════════ -->
<?php if (!empty($topProducts)): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
    <div class="px-4 py-3 border-b border-slate-700/40">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-trophy text-amber-400 mr-2"></i>Top Tagged Products</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-900/50 border-b border-slate-700/60">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-6">#</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tag</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Units</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Revenue</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-28">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php foreach ($topProducts as $i => $tp):
                    $share_pct = $max_tp_rev > 0 ? round((float)$tp['revenue'] / $max_tp_rev * 100) : 0;
                ?>
                <tr class="hover:bg-slate-700/20 transition-colors">
                    <td class="px-3 py-2.5 text-slate-600 font-semibold"><?php echo $i+1; ?></td>
                    <td class="px-3 py-2.5 text-slate-200 font-medium max-w-[160px] truncate"><?php echo htmlspecialchars($tp['name']); ?></td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px]"
                              style="background:<?php echo htmlspecialchars($tp['color']); ?>20;color:<?php echo htmlspecialchars($tp['color']); ?>;">
                            <?php echo htmlspecialchars($tp['tag_name']); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right text-slate-400"><?php echo format_currency((float)$tp['price']); ?></td>
                    <td class="px-3 py-2.5 text-right text-slate-300"><?php echo number_format((float)$tp['total_sold']); ?></td>
                    <td class="px-3 py-2.5 text-right font-semibold text-emerald-400"><?php echo format_currency((float)$tp['revenue']); ?></td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-1.5">
                            <div class="flex-1 bg-slate-700/40 rounded h-1.5 overflow-hidden">
                                <div class="h-full bg-amber-500/60 rounded" style="width:<?php echo $share_pct; ?>%;"></div>
                            </div>
                            <span class="text-slate-500 w-7 text-right"><?php echo $share_pct; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ═══ Monthly Pivot Trend ════════════════════════════════════════ -->
<?php if (!empty($pivot_months) && !empty($pivot_tags)): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-700/40 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-white"><i class="fas fa-calendar-alt text-amber-400 mr-2"></i>Monthly Revenue Pivot</h3>
        <span class="text-xs text-slate-500">Last <?php echo $pivot_months_count; ?> month<?php echo $pivot_months_count > 1 ? 's' : ''; ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-900/50 border-b border-slate-700/60">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tag</th>
                    <?php foreach ($pivot_months as $m): ?>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">
                        <?php echo date('M y', strtotime($m . '-01')); ?>
                    </th>
                    <?php endforeach; ?>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php
                $col_totals = array_fill_keys($pivot_months, 0);
                $grand_total = 0;
                foreach ($pivot_tags as $tid => $tinfo):
                    $row_total = array_sum($pivot_data[$tid] ?? []);
                    $grand_total += $row_total;
                ?>
                <tr class="hover:bg-slate-700/20 transition-colors">
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px]"
                              style="background:<?php echo htmlspecialchars($tinfo['color']); ?>20;color:<?php echo htmlspecialchars($tinfo['color']); ?>;">
                            <?php echo htmlspecialchars($tinfo['name']); ?>
                        </span>
                    </td>
                    <?php foreach ($pivot_months as $m):
                        $v = $pivot_data[$tid][$m] ?? 0;
                        $col_totals[$m] += $v;
                    ?>
                    <td class="px-3 py-2.5 text-right <?php echo $v > 0 ? 'text-slate-300' : 'text-slate-700'; ?>">
                        <?php echo $v > 0 ? format_currency($v) : '—'; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="px-3 py-2.5 text-right font-semibold text-emerald-400"><?php echo format_currency($row_total); ?></td>
                </tr>
                <?php endforeach; ?>
                <!-- Totals row -->
                <tr class="bg-slate-800/60 font-semibold">
                    <td class="px-3 py-2.5 text-slate-400 text-[11px] uppercase tracking-wide">Total</td>
                    <?php foreach ($pivot_months as $m): ?>
                    <td class="px-3 py-2.5 text-right text-amber-400"><?php echo format_currency($col_totals[$m]); ?></td>
                    <?php endforeach; ?>
                    <td class="px-3 py-2.5 text-right text-amber-400"><?php echo format_currency($grand_total); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
