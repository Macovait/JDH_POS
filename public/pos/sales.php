<?php
/**
 * Sales management page for Jakababa POS
 * View, filter, and manage all sales transactions with branch-specific filtering
 */
// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

// Check for sales view permission
if (!check_permission('sales.view') && !is_super_admin()) {
    enforce_permission('sales.view');
}

safe_require('functions.php', 'src', true);
safe_require('db.php', 'src', true);

$pdo = get_db_connection();

// Get current user info
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$branch_id = get_current_branch_id();
$branch_name = get_current_branch_name();

// Business type filter
$business_type_filter = $_GET['business_type'] ?? '';

// Get all branches for super admin/admins (for branch switching)
$branches = [];
$tenant_id = get_current_tenant_id();
if (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view')) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL ORDER BY name");
        $stmt->execute([$tenant_id]);
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching branches: " . $e->getMessage());
    }
}

// Get available business types
$available_business_types = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT business_type FROM sales WHERE tenant_id = " . (int)$tenant_id . " AND business_type IS NOT NULL AND business_type != ''");
    $bt_values = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $bt_config_all = get_business_type_config();
    $business_types_list = $bt_config_all['business_types'] ?? [];
    foreach ($bt_values as $bt) {
        if (isset($business_types_list[$bt])) {
            $available_business_types[$bt] = $business_types_list[$bt]['name'] ?? ucfirst($bt);
        } else {
            $available_business_types[$bt] = ucfirst($bt);
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching business types: " . $e->getMessage());
}

// Handle branch switching
if (isset($_GET['branch_id']) && (is_super_admin() || $user_role === 'Admin' || check_permission('branches.view'))) {
    $new_branch_id = (int) $_GET['branch_id'];

    // Verify branch exists
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND active = 1");
    $stmt->execute([$new_branch_id]);
    $branch = $stmt->fetch();

    if ($branch) {
        $branch_id = $new_branch_id;
        $branch_name = $branch['name'];
        $_SESSION['user']['branch_id'] = $branch_id;
        $_SESSION['current_branch']['id'] = $branch_id;
        $_SESSION['current_branch']['name'] = $branch_name;
    }
}

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$customer_filter = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$status_filter = $_GET['status'] ?? 'all';
$payment_filter = $_GET['payment_method'] ?? 'all';
$search_term = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'date_desc';

// Build sorting
$sort_options = [
    'date_desc' => 's.created_at DESC',
    'date_asc' => 's.created_at ASC',
    'amount_desc' => 's.total DESC',
    'amount_asc' => 's.total ASC',
    'invoice_asc' => 's.invoice_number ASC',
    'invoice_desc' => 's.invoice_number DESC'
];
$order_by = $sort_options[$sort_by] ?? 's.created_at DESC';

// Get customers for filter
$customers = [];
try {
    $stmt = $pdo->query("SELECT id, name, phone FROM customers WHERE deleted_at IS NULL ORDER BY name LIMIT 100");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

// Build query with filters
$sql = "
    SELECT 
        s.id,
        s.invoice_number,
        s.created_at,
        s.total,
        s.subtotal,
        s.discount,
        s.discount_type,
        s.discount_amount,
        s.tax,
        s.tax_rate,
        s.tax_amount,
        s.payment_method,
        s.status,
        s.notes,
        s.reference,
        c.id as customer_id,
        c.name as customer_name,
        c.phone as customer_phone,
        u.name as cashier_name,
        b.name as branch_name,
        (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN branches b ON s.branch_id = b.id
    WHERE 1=1
";

$params = [];

// Branch filter
$branch_filter = branch_filter_condition('s', false);
if ($branch_filter != "1=1") {
    $sql .= " AND " . $branch_filter;
}

// Date range filter
if (!empty($date_from)) {
    $sql .= " AND DATE(s.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND DATE(s.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

// Customer filter
if ($customer_filter > 0) {
    $sql .= " AND s.customer_id = :customer_id";
    $params[':customer_id'] = $customer_filter;
}

// Status filter
if ($status_filter !== 'all') {
    $sql .= " AND s.status = :status";
    $params[':status'] = $status_filter;
}

// Payment method filter
if ($payment_filter !== 'all') {
    $sql .= " AND s.payment_method = :payment_method";
    $params[':payment_method'] = $payment_filter;
}

// Search term
if (!empty($search_term)) {
    $sql .= " AND (s.invoice_number LIKE :search OR c.name LIKE :search OR c.phone LIKE :search OR s.reference LIKE :search)";
    $params[':search'] = "%$search_term%";
}

// Business type filter
if (!empty($business_type_filter)) {
    $sql .= " AND s.business_type = :business_type";
    $params[':business_type'] = $business_type_filter;
}

$sql .= " ORDER BY $order_by";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Calculate statistics
$total_sales = count($sales);
$total_revenue = array_sum(array_column($sales, 'total'));
$total_tax = array_sum(array_column($sales, 'tax_amount'));
$average_sale = $total_sales > 0 ? $total_revenue / $total_sales : 0;

// Get payment method breakdown
$payment_methods = [];
foreach ($sales as $sale) {
    $method = $sale['payment_method'] ?: 'cash';
    if (!isset($payment_methods[$method])) {
        $payment_methods[$method] = 0;
    }
    $payment_methods[$method] += $sale['total'];
}

// Get status breakdown
$status_counts = [];
foreach ($sales as $sale) {
    $status = $sale['status'] ?: 'completed';
    if (!isset($status_counts[$status])) {
        $status_counts[$status] = 0;
    }
    $status_counts[$status]++;
}

$page_title = 'Sales Management';
$current_year = date('Y');
ob_start();

$statusTw = [
    'completed' => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'draft'     => 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30',
    'cancelled' => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    'void'      => 'bg-gray-500/15 text-gray-400 ring-1 ring-gray-500/30',
];

$paymentTw = [
    'cash'  => 'bg-emerald-500/10 text-emerald-400',
    'card'  => 'bg-blue-500/10 text-blue-400',
    'mpesa' => 'bg-amber-500/10 text-amber-400',
    'bank'  => 'bg-purple-500/10 text-purple-400',
    'split' => 'bg-indigo-500/10 text-indigo-400',
];
?>

<style>
    @media print {
        .no-print { display: none !important; }
        body { background: white; color: black; }
        .print-only { display: block !important; }
    }
</style>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-receipt text-amber-400"></i> Sales Management
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo htmlspecialchars($branch_name); ?> &middot; <?php echo $total_sales; ?> transaction<?php echo $total_sales !== 1 ? 's' : ''; ?>
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="exportSales()"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-download text-xs"></i> Export
        </button>
        <a href="../reports/sales_report.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-chart-bar text-xs"></i> Reports
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <?php
    $cards = [
        ['label' => 'Total Sales', 'value' => $total_sales,         'icon' => 'fa-shopping-cart', 'color' => 'text-amber-400',   'bg' => 'bg-amber-500/10',   'fmt' => 'num'],
        ['label' => 'Revenue',     'value' => $total_revenue,         'icon' => 'fa-coins',         'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10', 'fmt' => 'curr'],
        ['label' => 'Total Tax',   'value' => $total_tax,             'icon' => 'fa-percent',       'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10',    'fmt' => 'curr'],
        ['label' => 'Average',     'value' => $average_sale,          'icon' => 'fa-chart-line',    'color' => 'text-purple-400',  'bg' => 'bg-purple-500/10',  'fmt' => 'curr'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?> truncate">
                <?php echo $card['fmt'] === 'curr' ? format_currency($card['value']) : number_format((float)$card['value']); ?>
            </div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">

        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>"
                   placeholder="Invoice, customer, phone…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <select name="status" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            <option value="void" <?php echo $status_filter === 'void' ? 'selected' : ''; ?>>Void</option>
        </select>

        <select name="payment_method" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="all">All Payments</option>
            <option value="cash" <?php echo $payment_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="card" <?php echo $payment_filter === 'card' ? 'selected' : ''; ?>>Card</option>
            <option value="mpesa" <?php echo $payment_filter === 'mpesa' ? 'selected' : ''; ?>>M-PESA</option>
            <option value="bank" <?php echo $payment_filter === 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
            <option value="split" <?php echo $payment_filter === 'split' ? 'selected' : ''; ?>>Split</option>
        </select>

        <select name="customer_id" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="0">All Customers</option>
            <?php foreach ($customers as $customer): ?>
                <option value="<?php echo $customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($customer['name']); ?>
                    <?php if (!empty($customer['phone'])): ?> (<?php echo htmlspecialchars($customer['phone']); ?>)<?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (!empty($available_business_types)): ?>
        <select name="business_type" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Business Types</option>
            <?php foreach ($available_business_types as $bt_code => $bt_name): ?>
                <option value="<?php echo $bt_code; ?>" <?php echo $business_type_filter === $bt_code ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($bt_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <select name="sort" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
            <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
            <option value="amount_desc" <?php echo $sort_by === 'amount_desc' ? 'selected' : ''; ?>>Highest Amount</option>
            <option value="amount_asc" <?php echo $sort_by === 'amount_asc' ? 'selected' : ''; ?>>Lowest Amount</option>
        </select>

        <input type="date" name="date_from" value="<?php echo $date_from; ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        <input type="date" name="date_to" value="<?php echo $date_to; ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">

        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <a href="sales.php?branch_id=<?php echo $branch_id; ?>" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
    </form>
</div>

<!-- Sales Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Invoice</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date / Time</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Items</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Payment</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Cashier</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="9" class="px-4 py-14 text-center">
                            <i class="fas fa-receipt text-4xl text-slate-700 block mb-3"></i>
                            <p class="text-slate-500 text-sm">No sales found</p>
                            <?php if ($search_term || $status_filter !== 'all' || $payment_filter !== 'all' || $date_from || $date_to): ?>
                            <a href="sales.php?branch_id=<?php echo $branch_id; ?>" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                                <i class="fas fa-times text-xs"></i> Clear filters
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sales as $sale):
                        $pm = $sale['payment_method'] ?: 'cash';
                        $pmClass = $paymentTw[$pm] ?? 'bg-slate-500/10 text-slate-400';
                        $st = $sale['status'] ?: 'completed';
                        $stClass = $statusTw[$st] ?? 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30';
                    ?>
                    <tr class="hover:bg-slate-700/30 transition-colors group cursor-pointer" onclick="viewSale(<?php echo $sale['id']; ?>)">
                        <td class="px-3 py-2.5">
                            <span class="font-mono text-sm font-semibold text-amber-400"><?php echo htmlspecialchars($sale['invoice_number'] ?: 'N/A'); ?></span>
                            <div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($sale['branch_name'] ?? ''); ?></div>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="text-sm text-slate-300"><?php echo date('d M Y', strtotime($sale['created_at'])); ?></div>
                            <div class="text-xs text-slate-500"><?php echo date('H:i', strtotime($sale['created_at'])); ?></div>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="text-sm text-white"><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></div>
                            <?php if (!empty($sale['customer_phone'])): ?>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo $sale['item_count']; ?></td>
                        <td class="px-3 py-2.5 text-right">
                            <span class="text-sm font-bold text-amber-400"><?php echo format_currency($sale['total']); ?></span>
                            <?php if ($sale['discount'] > 0): ?>
                            <div class="text-xs text-red-400">-<?php echo format_currency($sale['discount']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $pmClass; ?>">
                                <?php echo ucfirst($pm); ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $stClass; ?>">
                                <?php echo ucfirst($st); ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo htmlspecialchars($sale['cashier_name'] ?: 'System'); ?></td>
                        <td class="px-3 py-2.5">
                            <div class="flex items-center justify-center gap-2" onclick="event.stopPropagation()">
                                <a href="view_sale.php?id=<?php echo $sale['id']; ?>"
                                   class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="View">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                                <a href="receipts/print_receipt.php?id=<?php echo $sale['id']; ?>" target="_blank"
                                   class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-slate-600 hover:text-white transition-colors" title="Print">
                                    <i class="fas fa-print text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Payment Method Breakdown -->
<?php if (!empty($payment_methods)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-chart-pie text-amber-400"></i>
            Payment Methods
        </h3>
        <div class="space-y-3">
            <?php foreach ($payment_methods as $method => $amount): ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-400"><?php echo ucfirst($method); ?></span>
                        <span class="text-white font-semibold"><?php echo format_currency($amount); ?></span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-1.5">
                        <div class="bg-amber-400 h-1.5 rounded-full" style="width: <?php echo ($amount / max($total_revenue, 1)) * 100; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
            <i class="fas fa-chart-bar text-emerald-400"></i>
            Status Breakdown
        </h3>
        <div class="space-y-3">
            <?php foreach ($status_counts as $status => $count): ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-400"><?php echo ucfirst($status); ?></span>
                        <span class="text-white font-semibold"><?php echo $count; ?></span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-1.5">
                        <div class="bg-emerald-400 h-1.5 rounded-full" style="width: <?php echo ($count / max($total_sales, 1)) * 100; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
</div>

<!-- View Sale Modal -->
<div id="viewSaleModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-50 no-print">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 sticky top-0 bg-slate-800 z-10">
            <h3 class="text-xl font-semibold text-white flex items-center gap-2">
                <i class="fas fa-receipt text-amber-400"></i>
                Sale Details
            </h3>
            <button onclick="closeViewModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="saleDetails" class="space-y-4">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-amber-400"></i>
                <p class="text-slate-400 mt-2">Loading...</p>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-50 no-print">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-white flex items-center gap-2">
                <i class="fas fa-download text-amber-400"></i>
                Export Sales Data
            </h3>
            <button onclick="closeExportModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="export_sales.php" method="POST" class="space-y-4">
            <input type="hidden" name="branch_id" value="<?php echo $branch_id; ?>">
            <input type="hidden" name="date_from" value="<?php echo $date_from; ?>">
            <input type="hidden" name="date_to" value="<?php echo $date_to; ?>">

            <div>
                <label class="block text-sm text-slate-400 mb-1">Export Format</label>
                <select name="format" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="csv">CSV (Excel)</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>

            <div>
                <label class="block text-sm text-slate-400 mb-1">Include</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="include_items" value="1" checked class="rounded bg-slate-700 border-slate-600 text-amber-400">
                        <span class="text-sm text-slate-300">Include item details</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="include_tax" value="1" checked class="rounded bg-slate-700 border-slate-600 text-amber-400">
                        <span class="text-sm text-slate-300">Include tax breakdown</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-amber-500 text-slate-900 py-2.5 rounded-lg font-semibold hover:bg-amber-400 transition-colors">
                    Export
                </button>
                <button type="button" onclick="closeExportModal()" class="flex-1 bg-slate-700 text-white py-2.5 rounded-lg font-semibold hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 space-y-2 z-50 no-print"></div>

<!-- Connection Status -->
<div id="connection-status" class="fixed bottom-4 left-4 text-xs text-emerald-400 flex items-center gap-1 bg-slate-800 px-3 py-2 rounded-full border border-slate-700 no-print">
    <i class="fas fa-wifi"></i>
    <span>Online</span>
</div>

<script>
    // View sale details
    function viewSale(saleId) {
        const modal = document.getElementById('viewSaleModal');
        const detailsDiv = document.getElementById('saleDetails');

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Load sale details via AJAX
        fetch(`get_sale_details.php?id=${saleId}`)
            .then(response => response.text())
            .then(html => {
                detailsDiv.innerHTML = html;
            })
            .catch(error => {
                detailsDiv.innerHTML = '<p class="text-center text-red-400">Error loading sale details</p>';
            });
    }

    function closeViewModal() {
        document.getElementById('viewSaleModal').classList.add('hidden');
        document.getElementById('viewSaleModal').classList.remove('flex');
    }

    // Export functions
    function exportSales() {
        document.getElementById('exportModal').classList.remove('hidden');
        document.getElementById('exportModal').classList.add('flex');
    }

    function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
        document.getElementById('exportModal').classList.remove('flex');
    }

    // Toast notification
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');

        const colors = {
            success: 'bg-emerald-500 text-white',
            error: 'bg-red-500 text-white',
            info: 'bg-amber-400 text-slate-900',
            warning: 'bg-orange-500 text-white'
        };

        toast.className = `px-4 py-3 rounded-lg shadow-lg ${colors[type]}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} mr-2"></i>${message}`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Close modals when clicking outside
    ['viewSaleModal', 'exportModal'].forEach(id => {
        const modal = document.getElementById(id);
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    this.classList.remove('flex');
                }
            });
        }
    });

    // Connection status
    function updateOnlineStatus() {
        const statusEl = document.getElementById('connection-status');
        if (statusEl) {
            if (navigator.onLine) {
                statusEl.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
            } else {
                statusEl.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>';
            }
        }
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;

        // Alt + N - New sale
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'add_sales.php';
        }

        // Alt + E - Export
        if (e.altKey && e.key === 'e') {
            e.preventDefault();
            exportSales();
        }

        // Escape - Close modal
        if (e.key === 'Escape') {
            closeViewModal();
            closeExportModal();
        }
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function () {
        // Check for URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === 'created') {
            showToast('Sale created successfully', 'success');
        } else if (urlParams.get('success') === 'updated') {
            showToast('Sale updated successfully', 'success');
        } else if (urlParams.get('success') === 'deleted') {
            showToast('Sale deleted successfully', 'success');
        } else if (urlParams.get('error')) {
            showToast(urlParams.get('error'), 'error');
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';