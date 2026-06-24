<?php
/**
 * Reports & Analytics - SaaS Admin - Zero-Trust Implementation
 *
 * Revenue, sales, products and company analytics with CSV export.
 * Super Admin only with controlled cross-tenant access.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/ReportsModel.php';

admin_require_super_admin();

// Initialize Zero-Trust Context
$context = TenantContext::getInstance();

// Initialize Reports Model with controlled access
$reportsModel = new ReportsModel();

// ── Safe query wrapper ──
function rquery(string $sql, array $params = [], bool $single = false)
{
    try {
        if ($single) {
            return db_fetch_one($sql, $params);
        }
        return db_fetch_all($sql, $params);
    } catch (\Exception $e) {
        error_log("Reports query error: " . $e->getMessage());
        return $single ? null : [];
    }
}

// ── Date range ──
$default_from = date('Y-m-01');
$default_to   = date('Y-m-d');

$from = $_GET['from'] ?? $default_from;
$to   = $_GET['to']   ?? $default_to;

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $default_from;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $default_to;

$date_filter = "DATE(s.created_at) BETWEEN ? AND ?";
$date_params = [$from, $to];

// ── CSV Export with Zero-Trust Controls ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $type = $_GET['type'] ?? 'sales';
    while (ob_get_level() > 0) { ob_end_clean(); }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . $from . '_to_' . $to . '.csv"');

    $output = fopen('php://output', 'w');

    switch ($type) {
        case 'sales':
            fputcsv($output, ['Date', 'Total Sales', 'Total Revenue', 'Avg Sale']);
            $rows = $reportsModel->exportSalesCSV($from, $to);
            foreach ($rows as $r) {
                $avg = $r['total_sales'] > 0 ? $r['total_revenue'] / $r['total_sales'] : 0;
                fputcsv($output, [$r['sale_date'], $r['total_sales'], number_format((float)$r['total_revenue'], 2), number_format($avg, 2)]);
            }
            break;

        case 'revenue_by_tenant':
            fputcsv($output, ['Rank', 'Company', 'Total Revenue', 'Sales Count']);
            $rows = $reportsModel->getRevenueByCompany($from, $to, 10);
            $rank = 1;
            foreach ($rows as $r) {
                fputcsv($output, [$rank++, $r['company_name'], number_format((float)$r['total_revenue'], 2), $r['sale_count']]);
            }
            break;

        case 'payment_methods':
            fputcsv($output, ['Payment Method', 'Transactions', 'Total Amount']);
            $rows = $reportsModel->exportPaymentMethodsCSV($from, $to);
            $payment_labels = get_payment_methods();
            foreach ($rows as $r) {
                fputcsv($output, [$payment_labels[$r['method']] ?? ucfirst($r['method']), $r['cnt'], number_format((float)$r['total_amount'], 2)]);
            }
            break;

        case 'top_products':
            fputcsv($output, ['Rank', 'Product', 'Qty Sold', 'Revenue']);
            $rows = $reportsModel->exportTopProductsCSV($from, $to);
            $rank = 1;
            foreach ($rows as $r) {
                fputcsv($output, [$rank++, $r['product_name'], $r['qty_sold'], number_format((float)$r['revenue'], 2)]);
            }
            break;

        case 'new_companies':
            fputcsv($output, ['Company', 'Slug', 'Email', 'Status', 'Created']);
            $rows = $reportsModel->getNewCompanies($from, $to, 100); // Allow more for export
            foreach ($rows as $r) {
                fputcsv($output, [$r['name'], $r['slug'], $r['email'], $r['status'], $r['created']]);
            }
            break;
    }

    fclose($output);
    exit;
}

// ── 1. Sales Summary with Tenant Scoping ──
$sales_summary = $reportsModel->getSalesSummary($from, $to);
$total_sales    = (int)($sales_summary['total_sales'] ?? 0);
$total_revenue  = (float)($sales_summary['total_revenue'] ?? 0);
$avg_sale       = $total_sales > 0 ? $total_revenue / $total_sales : 0;

// ── 2. Revenue by Company (Cross-tenant for SaaS Admin) ──
$revenue_by_company = $reportsModel->getRevenueByCompany($from, $to, 10);

// ── 3. Revenue by Payment Method ──
$revenue_by_payment = $reportsModel->getRevenueByPayment($from, $to);

// ── 4. Monthly Trend (last 6 months) ──
$monthly_trend = $reportsModel->getMonthlyTrend();

// ── 5. Top Products ──
$top_products = $reportsModel->getTopProducts($from, $to, 10);

// ── 6. New Companies (Cross-tenant for SaaS Admin) ──
$new_companies_count = $reportsModel->getNewCompaniesCount($from, $to);
$new_companies = $reportsModel->getNewCompanies($from, $to, 10);

// ── 3. Revenue by Payment Method ──
$revenue_by_payment = rquery("
    SELECT COALESCE(s.payment_method, 'unknown') AS method, COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS total_amount
    FROM sales s
    WHERE $date_filter AND s.status = 'completed'
    GROUP BY s.payment_method
    ORDER BY total_amount DESC
", $date_params);

// ── 4. Monthly Trend (last 6 months) ──
$monthly_trend = rquery("
    SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month_label, COALESCE(SUM(s.total), 0) AS revenue, COUNT(*) AS sale_count
    FROM sales s
    WHERE s.status = 'completed' AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(s.created_at, '%Y-%m')
    ORDER BY month_label ASC
");

$max_monthly_revenue = 0;
foreach ($monthly_trend as $mt) {
    if ((float)$mt['revenue'] > $max_monthly_revenue) {
        $max_monthly_revenue = (float)$mt['revenue'];
    }
}

// ── 5. Top Products ──
$top_products = rquery("
    SELECT p.name AS product_name, p.sku, SUM(si.quantity) AS qty_sold, SUM(si.quantity * si.price) AS revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE $date_filter AND s.status = 'completed'
    GROUP BY p.id
    ORDER BY qty_sold DESC
    LIMIT 10
", $date_params);

// ── 6. New Companies ──
$new_companies_count_row = rquery("
    SELECT COUNT(*) AS cnt FROM pos_tenants WHERE DATE(created_at) BETWEEN ? AND ?
", $date_params, true);
$new_companies_count = (int)($new_companies_count_row['cnt'] ?? 0);

$new_companies = rquery("
    SELECT name, slug,
           JSON_UNQUOTE(JSON_EXTRACT(settings, '$.email')) as email,
           status, DATE(created_at) AS created
    FROM pos_tenants
    WHERE DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 10
", $date_params);

// ── Helper ──
$payment_labels = get_payment_methods();

function fmt_money($amount): string
{
    return 'KSh ' . number_format((float)$amount, 2);
}

function pct_width(float $value, float $max): string
{
    if ($max <= 0) return '0%';
    return round(($value / $max) * 100) . '%';
}

// ── Page vars for components ──
$current_page = 'reports';
$page_title   = 'Reports & Analytics';
ob_start();
?>
<div class="space-y-6">
        <!-- Date Range Filter -->
        <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl p-5">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">From</label>
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
                        class="bg-slate-800/50 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-green-500/50">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">To</label>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
                        class="bg-slate-800/50 border border-slate-700/60 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-green-500/50">
                </div>
                <button type="submit"
                    class="px-5 py-2 bg-green-500 hover:bg-green-600 text-slate-900 font-semibold text-sm rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="?from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                    class="px-5 py-2 bg-slate-700/50 hover:bg-slate-700/60 text-white text-sm rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-rotate-right"></i> Reset
                </a>
                <div class="ml-auto relative">
                    <button type="button" id="exportBtn"
                        class="px-5 py-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-300 border border-blue-500/30 text-sm rounded-lg transition flex items-center gap-2">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <div id="exportMenu" class="hidden absolute right-0 mt-2 w-56 bg-slate-900 border border-slate-700/60 rounded-xl shadow-2xl z-50 overflow-hidden">
                        <a href="?export=csv&type=sales&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-slate-300 hover:bg-slate-700/30 transition">
                            <i class="fas fa-chart-line text-green-400 w-4"></i> Sales Summary
                        </a>
                        <a href="?export=csv&type=revenue_by_company&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-slate-300 hover:bg-slate-700/30 transition">
                            <i class="fas fa-building text-purple-400 w-4"></i> Revenue by Company
                        </a>
                        <a href="?export=csv&type=payment_methods&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-slate-300 hover:bg-slate-700/30 transition">
                            <i class="fas fa-credit-card text-amber-400 w-4"></i> Payment Methods
                        </a>
                        <a href="?export=csv&type=top_products&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-slate-300 hover:bg-slate-700/30 transition">
                            <i class="fas fa-box text-cyan-400 w-4"></i> Top Products
                        </a>
                        <a href="?export=csv&type=new_companies&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                            class="flex items-center gap-3 px-4 py-3 text-sm text-slate-300 hover:bg-slate-700/30 transition">
                            <i class="fas fa-plus-circle text-rose-400 w-4"></i> New Companies
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Stat Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl p-5 stat-card">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
                        <i class="fas fa-receipt text-green-400"></i>
                    </div>
                    <p class="text-slate-400 text-xs">Total Sales</p>
                </div>
                <p class="text-lg font-bold text-white"><?= number_format($total_sales) ?></p>
            </div>
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl p-5 stat-card">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                        <i class="fas fa-coins text-emerald-400"></i>
                    </div>
                    <p class="text-slate-400 text-xs">Total Revenue</p>
                </div>
                <p class="text-lg font-bold text-white"><?= fmt_money($total_revenue) ?></p>
            </div>
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl p-5 stat-card">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/20 flex items-center justify-center">
                        <i class="fas fa-calculator text-blue-400"></i>
                    </div>
                    <p class="text-slate-400 text-xs">Avg Sale Value</p>
                </div>
                <p class="text-lg font-bold text-white"><?= fmt_money($avg_sale) ?></p>
            </div>
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl p-5 stat-card">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
                        <i class="fas fa-building text-purple-400"></i>
                    </div>
                    <p class="text-slate-400 text-xs">New Companies</p>
                </div>
                <p class="text-lg font-bold text-white"><?= number_format($new_companies_count) ?></p>
            </div>
        </div>

        <!-- Two-column layout: Revenue by Company + Payment Methods -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- Revenue by Company -->
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/60">
                    <div>
                        <p class="text-slate-400 text-xs">Top 10</p>
                        <h2 class="text-lg font-semibold text-white">Revenue by Company</h2>
                    </div>
                    <a href="?export=csv&type=revenue_by_company&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                        class="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-slate-800/50 text-left text-xs text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3">#</th>
                                <th class="px-6 py-3">Company</th>
                                <th class="px-6 py-3 text-right">Revenue</th>
                                <th class="px-6 py-3 text-right">Sales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm">
                            <?php if (empty($revenue_by_company)): ?>
                                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No data for this period.</td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($revenue_by_company as $rc): ?>
                                    <tr>
                                        <td class="px-6 py-3 text-slate-500 font-mono"><?= $rank++ ?></td>
                                        <td class="px-6 py-3">
                                            <p class="font-semibold text-white"><?= htmlspecialchars($rc['company_name']) ?></p>
                                            <p class="text-xs text-slate-500"><?= htmlspecialchars($rc['company_slug']) ?></p>
                                        </td>
                                        <td class="px-6 py-3 text-right font-semibold text-green-400"><?= fmt_money($rc['total_revenue']) ?></td>
                                        <td class="px-6 py-3 text-right text-slate-400"><?= number_format((int)$rc['sale_count']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/60">
                    <div>
                        <p class="text-slate-400 text-xs">Breakdown</p>
                        <h2 class="text-lg font-semibold text-white">Revenue by Payment Method</h2>
                    </div>
                    <a href="?export=csv&type=payment_methods&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                        class="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-slate-800/50 text-left text-xs text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3">Method</th>
                                <th class="px-6 py-3 text-right">Transactions</th>
                                <th class="px-6 py-3 text-right">Amount</th>
                                <th class="px-6 py-3">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm">
                            <?php if (empty($revenue_by_payment)): ?>
                                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No data for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($revenue_by_payment as $rp):
                                    $label = $payment_labels[$rp['method']] ?? ucfirst($rp['method']);
                                    $share = $total_revenue > 0 ? ($rp['total_amount'] / $total_revenue) * 100 : 0;
                                ?>
                                    <tr>
                                        <td class="px-6 py-3 font-semibold text-white"><?= htmlspecialchars($label) ?></td>
                                        <td class="px-6 py-3 text-right text-slate-400"><?= number_format((int)$rp['cnt']) ?></td>
                                        <td class="px-6 py-3 text-right font-semibold text-green-400"><?= fmt_money($rp['total_amount']) ?></td>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-2 bg-slate-800/50 rounded-full overflow-hidden">
                                                    <div class="h-full bg-green-500 rounded-full bar-fill" style="width: <?= round($share) ?>%"></div>
                                                </div>
                                                <span class="text-xs text-slate-400 w-10 text-right"><?= number_format($share, 1) ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Monthly Revenue Trend -->
        <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl p-6">
            <div class="mb-6">
                <p class="text-slate-400 text-xs">Last 6 months</p>
                <h2 class="text-lg font-semibold text-white">Monthly Revenue Trend</h2>
            </div>
            <?php if (empty($monthly_trend)): ?>
                <p class="text-slate-500 text-sm text-center py-8">No revenue data available.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($monthly_trend as $mt):
                        $rev = (float)$mt['revenue'];
                        $bar_w = pct_width($rev, $max_monthly_revenue);
                        $month_name = date('M Y', strtotime($mt['month_label'] . '-01'));
                    ?>
                        <div class="flex items-center gap-4">
                            <div class="w-20 text-xs text-slate-400 text-right flex-shrink-0"><?= $month_name ?></div>
                            <div class="flex-1 h-8 bg-slate-800/50 rounded-lg overflow-hidden relative">
                                <div class="h-full bg-gradient-to-r from-green-500 to-emerald-400 rounded-lg bar-fill flex items-center justify-end pr-3"
                                    style="width: <?= $bar_w ?>; min-width: <?= $rev > 0 ? '2rem' : '0' ?>">
                                    <?php if ($rev > 0): ?>
                                        <span class="text-xs font-semibold text-white drop-shadow"><?= fmt_money($rev) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="w-16 text-xs text-slate-500 text-right flex-shrink-0"><?= number_format((int)$mt['sale_count']) ?> sales</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Two-column: Top Products + New Companies -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- Top Products -->
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/60">
                    <div>
                        <p class="text-slate-400 text-xs">By quantity sold</p>
                        <h2 class="text-lg font-semibold text-white">Top 10 Products</h2>
                    </div>
                    <a href="?export=csv&type=top_products&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                        class="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-slate-800/50 text-left text-xs text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3">#</th>
                                <th class="px-6 py-3">Product</th>
                                <th class="px-6 py-3 text-right">Qty Sold</th>
                                <th class="px-6 py-3 text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm">
                            <?php if (empty($top_products)): ?>
                                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No data for this period.</td></tr>
                            <?php else: ?>
                                <?php $rank = 1; foreach ($top_products as $tp): ?>
                                    <tr>
                                        <td class="px-6 py-3 text-slate-500 font-mono"><?= $rank++ ?></td>
                                        <td class="px-6 py-3">
                                            <p class="font-semibold text-white"><?= htmlspecialchars($tp['product_name']) ?></p>
                                            <?php if (!empty($tp['sku'])): ?>
                                                <p class="text-xs text-slate-500">SKU: <?= htmlspecialchars($tp['sku']) ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-right text-cyan-400 font-semibold"><?= number_format((int)$tp['qty_sold']) ?></td>
                                        <td class="px-6 py-3 text-right text-green-400 font-semibold"><?= fmt_money($tp['revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- New Companies -->
            <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-700/60 rounded-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-700/60">
                    <div>
                        <p class="text-slate-400 text-xs"><?= number_format($new_companies_count) ?> registered</p>
                        <h2 class="text-lg font-semibold text-white">New Companies</h2>
                    </div>
                    <a href="?export=csv&type=new_companies&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>"
                        class="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10">
                        <thead class="bg-slate-800/50 text-left text-xs text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3">Company</th>
                                <th class="px-6 py-3">Email</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-sm">
                            <?php if (empty($new_companies)): ?>
                                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No new companies in this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($new_companies as $nc):
                                    $status_colors = [
                                        'active'    => 'bg-green-500/20 text-green-300',
                                        'trial'     => 'bg-amber-500/20 text-amber-300',
                                        'suspended' => 'bg-rose-500/20 text-rose-300',
                                        'cancelled' => 'bg-slate-500/20 text-slate-300',
                                    ];
                                    $badge_cls = $status_colors[$nc['status']] ?? 'bg-slate-700/50 text-white';
                                ?>
                                    <tr>
                                        <td class="px-6 py-3">
                                            <p class="font-semibold text-white"><?= htmlspecialchars($nc['name']) ?></p>
                                            <p class="text-xs text-slate-500"><?= htmlspecialchars($nc['slug']) ?></p>
                                        </td>
                                        <td class="px-6 py-3 text-slate-400"><?= htmlspecialchars($nc['email']) ?></td>
                                        <td class="px-6 py-3">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold capitalize <?= $badge_cls ?>">
                                                <?= htmlspecialchars($nc['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-slate-400"><?= date('M d, Y', strtotime($nc['created'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
</div>

<script>
    // Export dropdown toggle
    (function() {
        const btn = document.getElementById('exportBtn');
        const menu = document.getElementById('exportMenu');

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    })();
</script>

<style>
    .glass {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
    }
    .stat-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
    }
    .bar-fill {
        transition: width 0.6s ease;
    }
    table tbody tr {
        transition: background 0.15s;
    }
    table tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }
</style>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
