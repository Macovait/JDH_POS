<?php
/**
 * Revenue & Payments - Owner Panel
 * 
 * Track all incoming payments to the platform with M-Pesa and card integration.
 * Display trends, payment history, and financial summaries.
 * 
 * @package JDH_POS\Admin
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_subscriptions', 'pos_invoices']);

$current_page = 'revenue';
$page_title = 'Revenue & Payments';

// Helper functions
function formatCurrency($amount, $currency = 'USD') {
    return '$' . number_format((float)$amount, 2);
}

function timeAgo($datetime) {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '-';
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('M d, Y', $timestamp);
}

// Get filter params
$filterStatus = $_GET['filter_status'] ?? '';
$filterMethod = $_GET['filter_method'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$page = (int) ($_GET['page'] ?? 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get payments with filters
$where = ["i.created_at BETWEEN ? AND ?"];
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

if ($filterStatus) {
    $where[] = "i.status = ?";
    $params[] = $filterStatus;
}

$sql = "SELECT i.*, t.name as tenant_name, t.subdomain 
        FROM pos_invoices i 
        LEFT JOIN pos_tenants t ON i.tenant_id = t.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY i.created_at DESC 
        LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total
$countSql = "SELECT COUNT(*) FROM pos_invoices i WHERE " . implode(" AND ", $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalPayments = $countStmt->fetchColumn();
$totalPages = (int) ceil($totalPayments / $perPage);
if ($totalPages < 1) $totalPages = 1;

// Calculate summary stats
$summary = [
    'total_revenue' => 0,
    'mpesa_revenue' => 0,
    'card_revenue' => 0,
    'other_revenue' => 0,
    'completed_count' => 0,
    'pending_count' => 0,
    'failed_count' => 0,
    'refunded_count' => 0,
];

foreach ($payments as $payment) {
    if ($payment['status'] === 'paid') {
        $summary['total_revenue'] += $payment['amount'];
        $summary['completed_count']++;
        $summary['other_revenue'] += $payment['amount']; // Simplified - all as other for now
    } elseif ($payment['status'] === 'pending') {
        $summary['pending_count']++;
    } elseif ($payment['status'] === 'failed') {
        $summary['failed_count']++;
    } elseif ($payment['status'] === 'refunded') {
        $summary['refunded_count']++;
    }
}

// Get chart data (last 30 days)
$chartData = [];
$chartSql = "SELECT DATE(created_at) as date, SUM(amount) as total 
             FROM pos_invoices 
             WHERE status = 'paid' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at) 
             ORDER BY date";
$chartStmt = $pdo->query($chartSql);
$chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

// P0 Billing Tables Data
$creditsTotal = db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM credits WHERE status = 'active'");
$creditsCount = db_fetch_value("SELECT COUNT(*) FROM credits WHERE status = 'active'");

$deferredRevenue = db_fetch_value("SELECT COALESCE(SUM(amount), 0) FROM deferred_revenue WHERE recognition_status IN ('unrecognized', 'partially_recognized')");

$prorations = db_fetch_all(
    "SELECT p.*, t.name as tenant_name
     FROM prorations p
     LEFT JOIN pos_tenants t ON p.tenant_id = t.id
     ORDER BY p.created_at DESC LIMIT 20"
);

$refunds = db_fetch_all(
    "SELECT r.*, t.name as tenant_name
     FROM refunds r
     LEFT JOIN pos_tenants t ON r.tenant_id = t.id
     ORDER BY r.created_at DESC LIMIT 20"
);

$creditNotes = db_fetch_all(
    "SELECT c.*, t.name as tenant_name
     FROM credit_notes c
     LEFT JOIN pos_tenants t ON c.tenant_id = t.id
     ORDER BY c.created_at DESC LIMIT 20"
);

// Additional helper functions
function methodIcon($method) {
    $icons = [
        'mpesa' => 'fa-mobile-alt text-emerald-400',
        'card'  => 'fa-credit-card text-blue-400',
        'bank_transfer' => 'fa-university text-amber-400',
        'paypal' => 'fa-paypal text-blue-400',
        'stripe' => 'fa-cc-stripe text-purple-400',
        'other' => 'fa-money-bill text-slate-400',
    ];
    return $icons[$method] ?? 'fa-money-bill text-slate-400';
}

function statusBadge($status) {
    $colors = [
        'completed' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
        'pending'   => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
        'failed'    => 'bg-red-500/20 text-red-400 border-red-500/30',
        'refunded'  => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
        'disputed'  => 'bg-rose-500/20 text-rose-400 border-rose-500/30',
        'paid'      => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
    ];
    $cls = $colors[$status] ?? 'bg-slate-500/20 text-slate-400 border-slate-500/30';
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border ' . $cls . '">' . ucfirst($status) . '</span>';
}

// Start output buffering
ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-lg font-bold text-white tracking-tight">Revenue & Payments</h1>
            <p class="text-sm text-slate-400 mt-1">Track all platform payments and financial metrics</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="export.php?type=payments&status=<?= urlencode($_GET['filter_status'] ?? '') ?>&method=<?= urlencode($_GET['filter_method'] ?? '') ?>&date_from=<?= urlencode($_GET['date_from'] ?? '') ?>&date_to=<?= urlencode($_GET['date_to'] ?? '') ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors text-xs">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 border-amber-500/20">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-amber-400/80 font-medium uppercase tracking-wider">Total Revenue</p>
                    <p class="text-2xl font-bold text-amber-400 mt-1"><?= formatCurrency($summary['total_revenue']) ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-coins text-amber-400"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><?= $summary['completed_count'] ?> completed payments</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-emerald-400/80 font-medium uppercase tracking-wider">M-Pesa</p>
                    <p class="text-2xl font-bold text-emerald-400 mt-1"><?= formatCurrency($summary['mpesa_revenue']) ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <i class="fas fa-mobile-alt text-emerald-400"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><?= round(($summary['mpesa_revenue'] / max($summary['total_revenue'], 1)) * 100, 1) ?>% of total</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-blue-400/80 font-medium uppercase tracking-wider">Card Payments</p>
                    <p class="text-2xl font-bold text-blue-400 mt-1"><?= formatCurrency($summary['card_revenue']) ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <i class="fas fa-credit-card text-blue-400"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><?= round(($summary['card_revenue'] / max($summary['total_revenue'], 1)) * 100, 1) ?>% of total</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">Pending</p>
                    <p class="text-lg font-bold text-white mt-1"><?= $summary['pending_count'] ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fas fa-clock text-amber-400"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3">Awaiting confirmation</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-purple-400/80 font-medium uppercase tracking-wider">Credit Balance</p>
                    <p class="text-2xl font-bold text-purple-400 mt-1"><?= formatCurrency($creditsTotal) ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <i class="fas fa-wallet text-purple-400"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3"><?= $creditsCount ?> active credits</p>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs text-pink-400/80 font-medium uppercase tracking-wider">Deferred Revenue</p>
                    <p class="text-2xl font-bold text-pink-400 mt-1"><?= formatCurrency($deferredRevenue) ?></p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-pink-500/10 flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-pink-400"></i>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3">Unrecognized revenue</p>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-chart-area text-amber-400"></i>
                Revenue Trend (30 Days)
            </h3>
            <div class="flex items-center gap-4 text-xs">
                <span class="flex items-center gap-1 text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    Total
                </span>
                <span class="flex items-center gap-1 text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    M-Pesa
                </span>
                <span class="flex items-center gap-1 text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                    Card
                </span>
            </div>
        </div>
        <div class="h-64">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <form method="get" class="flex flex-wrap items-end gap-4">
            <div class="w-40">
                <label for="filter_status" class="block text-xs text-slate-400 mb-1">Status</label>
                <select id="filter_status" name="filter_status" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 w-full">
                    <option value="">All Status</option>
                    <option value="completed" <?= ($filterStatus ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="pending" <?= ($filterStatus ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= ($filterStatus ?? '') === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="refunded" <?= ($filterStatus ?? '') === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="w-40">
                <label for="filter_method" class="block text-xs text-slate-400 mb-1">Payment Method</label>
                <select id="filter_method" name="filter_method" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 w-full">
                    <option value="">All Methods</option>
                    <option value="mpesa" <?= ($filterMethod ?? '') === 'mpesa' ? 'selected' : '' ?>>M-Pesa</option>
                    <option value="card" <?= ($filterMethod ?? '') === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="bank_transfer" <?= ($filterMethod ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                </select>
            </div>
            <div class="w-40">
                <label for="date_from" class="block text-xs text-slate-400 mb-1">From</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($dateFrom ?? '') ?>" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="w-40">
                <label for="date_to" class="block text-xs text-slate-400 mb-1">To</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($dateTo ?? '') ?>" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors text-xs">
                    <i class="fas fa-filter"></i> Filter
                </button>
            </div>
            <div>
                <a href="revenue.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors text-xs">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <form method="POST" action="export_csv.php" class="inline" target="_blank">
        <input type="hidden" name="table" value="pos_invoices">
        <input type="hidden" name="columns" value='[{"field":"id","label":"Invoice ID"},{"field":"tenant_id","label":"Tenant ID"},{"field":"amount","label":"Amount","format":"currency"},{"field":"currency","label":"Currency"},{"field":"status","label":"Status","format":"status"},{"field":"description","label":"Description"},{"field":"paid_at","label":"Paid At","format":"date"},{"field":"created_at","label":"Created","format":"date"}]'>
        <input type="hidden" name="filename" value="payments">
        <button type="submit" class="px-4 py-2 bg-slate-700/50 rounded-lg text-white text-sm hover:bg-slate-700/70 mb-4"><i class="fas fa-download mr-1"></i> Export CSV</button>
    </form>

    <!-- Payments Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-list text-blue-400"></i>
                Payment History (<?= $totalPayments ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50">
                    <tr class="text-slate-400 text-xs uppercase">
                        <th class="text-left py-3 px-5">Company</th>
                        <th class="text-left py-3 px-5">Amount</th>
                        <th class="text-left py-3 px-5">Method</th>
                        <th class="text-left py-3 px-5">Status</th>
                        <th class="text-left py-3 px-5">Plan</th>
                        <th class="text-left py-3 px-5">Date</th>
                        <th class="text-left py-3 px-5">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-500">
                            <i class="fas fa-receipt text-slate-600 text-3xl mb-3"></i>
                            <p>No payments found for selected filters</p>
                        </td>
                    </tr>
                    <?php else: foreach ($payments as $payment): ?>
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="py-4 px-5">
                            <p class="text-white font-medium"><?= htmlspecialchars($payment['company_name'] ?? 'Unknown') ?></p>
                        </td>
                        <td class="py-4 px-5">
                            <p class="text-white font-semibold"><?= formatCurrency($payment['amount'], $payment['currency']) ?></p>
                            <?php if ($payment['transaction_fee'] > 0): ?>
                            <p class="text-xs text-slate-500">Fee: <?= formatCurrency($payment['transaction_fee'], $payment['currency']) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-5">
                            <div class="flex items-center gap-2">
                                <i class="fas <?= methodIcon($payment['payment_method']) ?>"></i>
                                <span class="text-slate-300 capitalize"><?= str_replace('_', ' ', $payment['payment_method']) ?></span>
                            </div>
                        </td>
                        <td class="py-4 px-5"><?= statusBadge($payment['status']) ?></td>
                        <td class="py-4 px-5 text-slate-400"><?= htmlspecialchars($payment['plan_name'] ?? '-') ?></td>
                        <td class="py-4 px-5 text-slate-400 text-xs"><?= timeAgo($payment['created_at']) ?></td>
                        <td class="py-4 px-5">
                            <code class="text-xs text-cyan-400 bg-cyan-500/10 px-2 py-1 rounded"><?= htmlspecialchars(substr($payment['payment_reference'] ?? '-', 0, 20)) ?></code>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between p-4 border-t border-slate-700/50">
            <p class="text-xs text-slate-400">
                Showing <?= (($page - 1) * 25) + 1 ?> - <?= min($page * 25, $totalPayments) ?> of <?= $totalPayments ?> payments
            </p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                <a href="revenue.php?page=<?= $page - 1 ?>&filter_status=<?= urlencode($filterStatus ?? '') ?>&filter_method=<?= urlencode($filterMethod ?? '') ?>&date_from=<?= urlencode($dateFrom ?? '') ?>&date_to=<?= urlencode($dateTo ?? '') ?>" 
                   class="text-xs px-3 py-1.5 rounded-lg bg-slate-800/50 text-slate-300 hover:bg-slate-700/50">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                <a href="revenue.php?page=<?= $page + 1 ?>&filter_status=<?= urlencode($filterStatus ?? '') ?>&filter_method=<?= urlencode($filterMethod ?? '') ?>&date_from=<?= urlencode($dateFrom ?? '') ?>&date_to=<?= urlencode($dateTo ?? '') ?>" 
                   class="text-xs px-3 py-1.5 rounded-lg bg-slate-800/50 text-slate-300 hover:bg-slate-700/50">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Billing Tables Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Prorations -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="p-4 border-b border-slate-700/50">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-calculator text-cyan-400"></i> Prorations
                </h3>
            </div>
            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/50 sticky top-0">
                        <tr class="text-slate-400 text-xs">
                            <th class="text-left py-2 px-4">Tenant</th>
                            <th class="text-right py-2 px-4">Amount</th>
                            <th class="text-left py-2 px-4">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/30">
                        <?php if (empty($prorations)): ?>
                        <tr><td colspan="3" class="py-6 text-center text-slate-500 text-xs">No prorations</td></tr>
                        <?php else: foreach ($prorations as $pr): ?>
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-2 px-4 text-white text-xs"><?= htmlspecialchars($pr['tenant_name'] ?? 'Unknown') ?></td>
                            <td class="py-2 px-4 text-right text-white text-xs font-medium"><?= formatCurrency($pr['amount']) ?></td>
                            <td class="py-2 px-4 text-slate-400 text-xs"><?= ucfirst(str_replace('_', ' ', $pr['reason'] ?? 'N/A')) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Refunds -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="p-4 border-b border-slate-700/50">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-undo text-red-400"></i> Refunds
                </h3>
            </div>
            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/50 sticky top-0">
                        <tr class="text-slate-400 text-xs">
                            <th class="text-left py-2 px-4">Tenant</th>
                            <th class="text-right py-2 px-4">Amount</th>
                            <th class="text-left py-2 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/30">
                        <?php if (empty($refunds)): ?>
                        <tr><td colspan="3" class="py-6 text-center text-slate-500 text-xs">No refunds</td></tr>
                        <?php else: foreach ($refunds as $rf): ?>
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-2 px-4 text-white text-xs"><?= htmlspecialchars($rf['tenant_name'] ?? 'Unknown') ?></td>
                            <td class="py-2 px-4 text-right text-white text-xs font-medium"><?= formatCurrency($rf['amount']) ?></td>
                            <td class="py-2 px-4">
                                <span class="px-1.5 py-0.5 rounded text-[10px] <?= $rf['status'] === 'completed' ? 'bg-emerald-500/20 text-emerald-400' : ($rf['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-red-500/20 text-red-400') ?>">
                                    <?= ucfirst($rf['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Credit Notes -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="p-4 border-b border-slate-700/50">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-indigo-400"></i> Credit Notes
                </h3>
            </div>
            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-800/50 sticky top-0">
                        <tr class="text-slate-400 text-xs">
                            <th class="text-left py-2 px-4">Tenant</th>
                            <th class="text-right py-2 px-4">Amount</th>
                            <th class="text-left py-2 px-4">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/30">
                        <?php if (empty($creditNotes)): ?>
                        <tr><td colspan="3" class="py-6 text-center text-slate-500 text-xs">No credit notes</td></tr>
                        <?php else: foreach ($creditNotes as $cn): ?>
                        <tr class="hover:bg-slate-700/30">
                            <td class="py-2 px-4 text-white text-xs"><?= htmlspecialchars($cn['tenant_name'] ?? 'Unknown') ?></td>
                            <td class="py-2 px-4 text-right text-white text-xs font-medium"><?= formatCurrency($cn['amount']) ?></td>
                            <td class="py-2 px-4 text-slate-400 text-xs"><?= htmlspecialchars($cn['reason'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../public/assets/js/chart.js.min.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById("revenueChart").getContext("2d");
new Chart(revenueCtx, {
    type: "line",
    data: {
        labels: <?= json_encode($chartData['labels'] ?? []) ?>,
        datasets: [
            {
                label: "Total Revenue",
                data: <?= json_encode($chartData['revenue'] ?? []) ?>,
                borderColor: "#fbbf24",
                backgroundColor: "rgba(251, 191, 36, 0.1)",
                borderWidth: 2,
                fill: true,
                tension: 0.4
            },
            {
                label: "M-Pesa",
                data: <?= json_encode($chartData['mpesa'] ?? []) ?>,
                borderColor: "#34d399",
                backgroundColor: "rgba(52, 211, 153, 0.1)",
                borderWidth: 2,
                fill: false,
                tension: 0.4
            },
            {
                label: "Card",
                data: <?= json_encode($chartData['card'] ?? []) ?>,
                borderColor: "#60a5fa",
                backgroundColor: "rgba(96, 165, 250, 0.1)",
                borderWidth: 2,
                fill: false,
                tension: 0.4
            }
        ]
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
                ticks: { color: "#9ca3af", font: { size: 10 }, callback: function(value) { return "KSh " + value.toLocaleString(); } }
            }
        }
    }
});

</script>

<?php
// Get buffered content
$page_content = ob_get_clean();

// Include layout
require_once __DIR__ . '/layouts/super_admin.php';
?>

