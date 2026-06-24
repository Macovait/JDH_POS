<?php
/**
 * All Sales - Jakababa POS
 * Version: 3.0 - Pure Tailwind CSS with full security
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Advanced filtering and search
 * - Summary statistics
 * - Pagination
 * - Export functionality
 * - Pure Tailwind CSS
 */

declare(strict_types=1);

// ============================================
// BOOTSTRAP
// ============================================
$page_title = 'All Sales';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// ============================================
// GET CURRENT CONTEXT
// ============================================
$pdo = get_db_connection();
$tenantId = get_current_tenant_id();
$currentUserId = get_current_user_id();
$userRole = $_SESSION['role'] ?? '';
$isAdmin = in_array(strtolower($userRole), ['admin', 'owner', 'superadmin', 'super_admin', 'administrator'], true) 
           || (function_exists('is_super_admin') && is_super_admin());
$userBranch = get_current_branch_id();

// ============================================
// GET FILTERS
// ============================================
$search = trim($_GET['s'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$branchFilter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $userBranch;
$userFilter = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

// Force non-admins to their own branch and own sales
if (!$isAdmin) {
    $branchFilter = $userBranch;
    $userFilter = $currentUserId;
}

// ============================================
// PAGINATION
// ============================================
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// ============================================
// BUILD WHERE CLAUSE
// ============================================
$where = " WHERE s.tenant_id = ? ";
$params = [$tenantId];

if (!empty($search)) {
    $where .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR s.reference LIKE ?) ";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where .= " AND s.branch_id = ? ";
$params[] = $branchFilter;

if ($userFilter > 0) {
    $where .= " AND s.user_id = ? ";
    $params[] = $userFilter;
}

if (in_array($statusFilter, ['completed', 'pending', 'draft', 'cancelled', 'returned'], true)) {
    $where .= " AND s.status = ? ";
    $params[] = $statusFilter;
}

if (in_array($paymentFilter, ['cash', 'card', 'mpesa', 'bank_transfer', 'credit', 'split'], true)) {
    $where .= " AND s.payment_method = ? ";
    $params[] = $paymentFilter;
}

if (!empty($dateFrom)) {
    $where .= " AND DATE(s.created_at) >= ? ";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND DATE(s.created_at) <= ? ";
    $params[] = $dateTo;
}

// ============================================
// SORT
// ============================================
$order = " ORDER BY s.created_at DESC";
switch ($sortBy) {
    case 'oldest':  $order = " ORDER BY s.created_at ASC";  break;
    case 'highest': $order = " ORDER BY s.total DESC";      break;
    case 'lowest':  $order = " ORDER BY s.total ASC";       break;
}

// ============================================
// GET BRANCHES
// ============================================
$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name");
    $stmt->execute([$tenantId]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Branches fetch error: ' . $e->getMessage());
}

// ============================================
// GET TOTAL COUNT
// ============================================
$totalSales = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM sales s LEFT JOIN customers c ON c.id = s.customer_id $where");
    $stmt->execute($params);
    $totalSales = (int) $stmt->fetchColumn();
} catch (Exception $e) {
    error_log('Sales count error: ' . $e->getMessage());
}

// ============================================
// GET SALES LIST
// ============================================
$sales = [];
try {
    $sql = "
        SELECT s.id, 
               s.invoice_number, 
               s.reference, 
               s.total, 
               s.payment_method, 
               s.status,
               s.created_at, 
               s.customer_id, 
               s.branch_id, 
               s.user_id,
               c.name AS customer_name, 
               c.phone AS customer_phone,
               u.name AS cashier_name,
               b.name AS branch_name
        FROM sales s
        LEFT JOIN customers c ON c.id = s.customer_id
        LEFT JOIN users u ON u.id = s.user_id
        LEFT JOIN branches b ON b.id = s.branch_id
        $where
        $order
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Sales list error: ' . $e->getMessage());
}

$totalPages = max(1, (int) ceil($totalSales / $limit));

// ============================================
// GET SUMMARY
// ============================================
$summary = [
    'total_amount' => 0,
    'completed'    => 0,
    'pending'      => 0,
    'draft'        => 0,
    'cancelled'    => 0,
    'returned'     => 0,
    'cash'         => 0,
    'card'         => 0,
    'mpesa'        => 0,
    'bank'         => 0,
    'credit'       => 0,
];

try {
    $sw = " WHERE tenant_id = ? ";
    $sp = [$tenantId];
    if ($branchFilter > 0) { 
        $sw .= " AND branch_id = ? "; 
        $sp[] = $branchFilter; 
    }
    if ($userFilter > 0) { 
        $sw .= " AND user_id = ? ";   
        $sp[] = $userFilter; 
    }
    
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total END), 0) AS total_amount,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' AND status = 'completed' THEN total END), 0) AS cash,
            COALESCE(SUM(CASE WHEN payment_method = 'card' AND status = 'completed' THEN total END), 0) AS card,
            COALESCE(SUM(CASE WHEN payment_method = 'mpesa' AND status = 'completed' THEN total END), 0) AS mpesa,
            COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' AND status = 'completed' THEN total END), 0) AS bank,
            COALESCE(SUM(CASE WHEN payment_method = 'credit' AND status = 'completed' THEN total END), 0) AS credit
        FROM sales $sw
    ");
    $stmt->execute($sp);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $summary = array_map(function($v) { 
            return $v !== null ? (float) $v : 0; 
        }, $row);
    }
} catch (Exception $e) {
    error_log('Summary error: ' . $e->getMessage());
}

// ============================================
// GET CURRENCY
// ============================================
$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenantId) : 'KES';

// ============================================
// BUILD QUERY STRING HELPER
// ============================================
$buildQs = function (array $extra = []) use ($search, $statusFilter, $paymentFilter, $dateFrom, $dateTo, $sortBy, $branchFilter, $userFilter, $page) {
    $base = [];
    if ($search !== '') $base['s'] = $search;
    if ($statusFilter !== '') $base['status'] = $statusFilter;
    if ($paymentFilter !== '') $base['payment'] = $paymentFilter;
    if ($dateFrom !== '') $base['date_from'] = $dateFrom;
    if ($dateTo !== '') $base['date_to'] = $dateTo;
    if ($sortBy !== 'newest') $base['sort'] = $sortBy;
    if ($branchFilter > 0) $base['branch_id'] = $branchFilter;
    if ($userFilter > 0) $base['user_id'] = $userFilter;
    if ($page > 1) $base['page'] = $page;
    
    return http_build_query(array_merge($base, $extra));
};

// ============================================
// TAILWIND CLASSES
// ============================================
$statusTw = [
    'completed' => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'pending'   => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
    'draft'     => 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30',
    'cancelled' => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    'returned'  => 'bg-orange-500/15 text-orange-400 ring-1 ring-orange-500/30',
];

$paymentTw = [
    'cash'          => 'bg-emerald-500/10 text-emerald-400',
    'card'          => 'bg-blue-500/10 text-blue-400',
    'mpesa'         => 'bg-amber-500/10 text-amber-400',
    'bank_transfer' => 'bg-purple-500/10 text-purple-400',
    'credit'        => 'bg-red-500/10 text-red-400',
    'split'         => 'bg-indigo-500/10 text-indigo-400',
];
?>

<!-- ============================================ -->
<!-- PAGE HEADER -->
<!-- ============================================ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-receipt text-amber-400"></i> All Sales
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo number_format($totalSales); ?> transaction<?php echo $totalSales !== 1 ? 's' : ''; ?> found
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <?php if ($isAdmin): ?>
        <a href="export_sales.php?<?php echo htmlspecialchars($buildQs(['format' => 'csv'])); ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-download text-xs"></i> Export CSV
        </a>
        <?php endif; ?>
        <a href="pos.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-cash-register text-xs"></i> Open POS
        </a>
    </div>
</div>

<!-- ============================================ -->
<!-- MESSAGES -->
<!-- ============================================ -->
<?php if (!empty($_GET['success'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars(urldecode($_GET['message'] ?? 'Action completed successfully.')); ?>
</div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SUMMARY CARDS -->
<!-- ============================================ -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-5">
    <?php
    $cards = [
        ['label' => 'Revenue',    'value' => $summary['total_amount'], 'icon' => 'fa-chart-bar',      'color' => 'text-amber-400',   'bg' => 'bg-amber-500/10'],
        ['label' => 'Cash',       'value' => $summary['cash'],         'icon' => 'fa-money-bill-wave', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
        ['label' => 'Card',       'value' => $summary['card'],         'icon' => 'fa-credit-card',     'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10'],
        ['label' => 'M-Pesa',     'value' => $summary['mpesa'],        'icon' => 'fa-mobile-alt',      'color' => 'text-green-400',   'bg' => 'bg-green-500/10'],
        ['label' => 'Bank',       'value' => $summary['bank'],         'icon' => 'fa-university',      'color' => 'text-purple-400',  'bg' => 'bg-purple-500/10'],
        ['label' => 'Credit',     'value' => $summary['credit'],       'icon' => 'fa-file-invoice',    'color' => 'text-red-400',     'bg' => 'bg-red-500/10'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?> truncate">
                <?php echo $currency . ' ' . number_format((float)$card['value'], 0); ?>
            </div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ============================================ -->
<!-- FILTERS -->
<!-- ============================================ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">

    <!-- Status Pills -->
    <div class="flex flex-wrap gap-1.5">
        <?php
        $pills = [
            ''          => ['label' => 'All',       'count' => $totalSales,              'class' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            'completed' => ['label' => 'Completed', 'count' => $summary['completed'],     'class' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/30'],
            'pending'   => ['label' => 'Pending',   'count' => $summary['pending'],       'class' => 'bg-amber-500/20 text-amber-400 border-amber-500/30 hover:bg-amber-500/30'],
            'draft'     => ['label' => 'Draft',     'count' => $summary['draft'],         'class' => 'bg-slate-500/20 text-slate-300 border-slate-500/30 hover:bg-slate-500/30'],
            'cancelled' => ['label' => 'Cancelled', 'count' => $summary['cancelled'],     'class' => 'bg-red-500/20 text-red-400 border-red-500/30 hover:bg-red-500/30'],
            'returned'  => ['label' => 'Returned',  'count' => $summary['returned'],      'class' => 'bg-orange-500/20 text-orange-400 border-orange-500/30 hover:bg-orange-500/30'],
        ];
        foreach ($pills as $val => $pill):
            $active = $statusFilter === $val;
        ?>
        <a href="?<?php echo htmlspecialchars($buildQs(['status' => $val, 'page' => 1])); ?>"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border transition-colors <?php echo $pill['class']; ?>">
            <?php echo $pill['label']; ?>
            <span class="text-xs opacity-70">(<?php echo (int)$pill['count']; ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter Row -->
    <form method="get" class="flex flex-wrap gap-2 items-end">
        <?php if ($statusFilter): ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <?php endif; ?>

        <!-- Search -->
        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Invoice, customer, phone…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <!-- Payment -->
        <select name="payment" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Payments</option>
            <?php foreach (['cash' => 'Cash', 'card' => 'Card', 'mpesa' => 'M-Pesa', 'bank_transfer' => 'Bank Transfer', 'credit' => 'Credit', 'split' => 'Split'] as $k => $v): ?>
            <option value="<?php echo $k; ?>" <?php echo $paymentFilter === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Sort -->
        <select name="sort" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="newest"  <?php echo $sortBy === 'newest'  ? 'selected' : ''; ?>>Newest First</option>
            <option value="oldest"  <?php echo $sortBy === 'oldest'  ? 'selected' : ''; ?>>Oldest First</option>
            <option value="highest" <?php echo $sortBy === 'highest' ? 'selected' : ''; ?>>Highest Amount</option>
            <option value="lowest"  <?php echo $sortBy === 'lowest'  ? 'selected' : ''; ?>>Lowest Amount</option>
        </select>

        <!-- Date Range -->
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">

        <?php if ($isAdmin && count($branches) > 1): ?>
        <select name="branch_id" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <?php foreach ($branches as $b): ?>
            <option value="<?php echo $b['id']; ?>" <?php echo $branchFilter == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <a href="all_sales.php" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
    </form>
</div>

<!-- ============================================ -->
<!-- SALES TABLE -->
<!-- ============================================ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Invoice</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date / Time</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Cashier</th>
                    <?php if ($isAdmin && count($branches) > 1): ?>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Branch</th>
                    <?php endif; ?>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Payment</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($sales)): ?>
                <tr>
                    <td colspan="9" class="px-4 py-14 text-center">
                        <i class="fas fa-receipt text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No sales found</p>
                        <?php if ($search || $statusFilter || $paymentFilter || $dateFrom): ?>
                        <a href="all_sales.php" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <i class="fas fa-times text-xs"></i> Clear filters
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($sales as $sale):
                    $invRaw = !empty($sale['invoice_number']) ? $sale['invoice_number'] : 'SALE-' . str_pad((string)$sale['id'], 6, '0', STR_PAD_LEFT);
                    $pm = $sale['payment_method'] ?? 'cash';
                    $pmClass = $paymentTw[$pm] ?? 'bg-slate-500/10 text-slate-400';
                    $st = $sale['status'] ?? 'pending';
                    $stClass = $statusTw[$st] ?? 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30';
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <span class="font-mono text-sm font-semibold text-amber-400"><?php echo htmlspecialchars($invRaw); ?></span>
                        <?php if (!empty($sale['reference'])): ?>
                        <div class="text-xs text-slate-500 mt-0.5">Ref: <?php echo htmlspecialchars($sale['reference']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-slate-300"><?php echo date('d M Y', strtotime($sale['created_at'])); ?></div>
                        <div class="text-xs text-slate-500"><?php echo date('H:i', strtotime($sale['created_at'])); ?></div>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-white"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></div>
                        <?php if (!empty($sale['customer_phone'])): ?>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo htmlspecialchars($sale['cashier_name'] ?? '—'); ?></td>
                    <?php if ($isAdmin && count($branches) > 1): ?>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo htmlspecialchars($sale['branch_name'] ?? '—'); ?></td>
                    <?php endif; ?>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $pmClass; ?>">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $pm))); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <span class="text-sm font-bold text-amber-400"><?php echo $currency . ' ' . number_format((float)$sale['total'], 0); ?></span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $stClass; ?>">
                            <?php echo ucfirst($st); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center justify-center gap-2">
                            <a href="view_sale.php?id=<?php echo (int)$sale['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 hover:text-amber-300 transition-colors" title="View">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="receipts/print_invoice.php?id=<?php echo (int)$sale['id']; ?>" target="_blank"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-slate-600 hover:text-white transition-colors" title="Print">
                                <i class="fas fa-print text-xs"></i>
                            </a>
                            <?php if ($st === 'completed'): ?>
                            <a href="returns/return_sale.php?id=<?php echo (int)$sale['id']; ?>"
                               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-orange-400 hover:bg-orange-500/20 hover:text-orange-300 transition-colors" title="Return">
                                <i class="fas fa-undo-alt text-xs"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table Footer -->
    <?php if ($totalSales > 0): ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300 font-medium"><?php echo $offset + 1; ?>–<?php echo min($offset + $limit, $totalSales); ?></span>
            of <span class="text-slate-300 font-medium"><?php echo number_format($totalSales); ?></span> sales
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <a href="?<?php echo htmlspecialchars($buildQs(['page' => max(1, $page - 1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page <= 1 ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php 
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): 
            ?>
            <a href="?<?php echo htmlspecialchars($buildQs(['page' => $i])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors font-medium <?php echo $i === $page ? 'bg-amber-500/20 border-amber-500/40 text-amber-400' : 'bg-slate-800 border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <a href="?<?php echo htmlspecialchars($buildQs(['page' => min($totalPages, $page + 1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page >= $totalPages ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- FOOTER -->
<!-- ============================================ -->
<div class="mt-4 text-xs text-slate-500 flex flex-wrap items-center justify-between gap-2">
    <div class="flex items-center gap-3">
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-emerald-500 rounded-full"></span>Completed</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-amber-500 rounded-full"></span>Pending</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-slate-500 rounded-full"></span>Draft</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full"></span>Cancelled</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 bg-orange-500 rounded-full"></span>Returned</span>
    </div>
    <div>
        <?php if ($isAdmin): ?>
        <span class="text-slate-600">Admin view</span>
        <?php else: ?>
        <span class="text-slate-600">Your sales only</span>
        <?php endif; ?>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>