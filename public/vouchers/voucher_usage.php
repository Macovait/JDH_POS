<?php
/**
 * Voucher Usage History - Full paginated usage log
 * Pure Tailwind CSS matching all_sales.php design system
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('vouchers.manage') && !is_super_admin()) {
    enforce_permission('vouchers.manage');
}

$voucher_id = (int) ($_GET['id'] ?? 0);
if (!$voucher_id) {
    header('Location: vouchers.php');
    exit;
}

$pdo       = get_db_connection();
$tenant_id = get_current_tenant_id();

// Fetch voucher
$voucher = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('voucher_usage fetch: ' . $e->getMessage());
}

if (!$voucher) {
    header('Location: vouchers.php?error=' . urlencode('Voucher not found'));
    exit;
}

// Check if voucher_code column exists on sales
$has_voucher_code = false;
try {
    $s = $pdo->prepare("SHOW COLUMNS FROM `sales` LIKE 'voucher_code'");
    $s->execute();
    $has_voucher_code = $s->rowCount() > 0;
} catch (Exception $e) {}

// Filters
$search      = trim($_GET['s'] ?? '');
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to']   ?? '';
$sort_by     = $_GET['sort']      ?? 'newest';

// Pagination
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

$total_rows  = 0;
$usage_rows  = [];
$summary     = ['count' => 0, 'total_discount' => 0, 'total_sales' => 0];
$total_pages = 1;

$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenant_id) : 'KES';

$payment_tw = [
    'cash'          => 'bg-emerald-500/10 text-emerald-400',
    'card'          => 'bg-blue-500/10 text-blue-400',
    'mpesa'         => 'bg-amber-500/10 text-amber-400',
    'bank_transfer' => 'bg-purple-500/10 text-purple-400',
    'credit'        => 'bg-red-500/10 text-red-400',
];
$status_tw = [
    'completed' => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'pending'   => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
    'draft'     => 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30',
    'cancelled' => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
];

$build_qs = function (array $extra = []) use ($search, $date_from, $date_to, $sort_by, $voucher_id, $page) {
    $base = array_filter([
        'id'        => $voucher_id,
        's'         => $search,
        'date_from' => $date_from,
        'date_to'   => $date_to,
        'sort'      => $sort_by,
        'page'      => $page,
    ], fn($v) => $v !== '' && $v !== null);
    return http_build_query(array_merge($base, $extra));
};

if ($has_voucher_code) {
    $where  = " WHERE s.tenant_id = ? AND s.voucher_code = ? ";
    $params = [$tenant_id, $voucher['code']];

    if (!empty($search)) {
        $where .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?) ";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    if (!empty($date_from)) {
        $where .= " AND DATE(s.created_at) >= ? ";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $where .= " AND DATE(s.created_at) <= ? ";
        $params[] = $date_to;
    }

    $order = match($sort_by) {
        'oldest'   => "ORDER BY s.created_at ASC",
        'highest'  => "ORDER BY s.total DESC",
        'lowest'   => "ORDER BY s.total ASC",
        default    => "ORDER BY s.created_at DESC",
    };

    // Count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON c.id = s.customer_id $where");
        $stmt->execute($params);
        $total_rows  = (int) $stmt->fetchColumn();
        $total_pages = max(1, (int) ceil($total_rows / $limit));
    } catch (PDOException $e) { error_log('voucher_usage count: ' . $e->getMessage()); }

    // Summary (unfiltered by search/date — totals for this voucher)
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt,
                   COALESCE(SUM(discount), 0) AS total_discount,
                   COALESCE(SUM(CASE WHEN status = 'completed' THEN total END), 0) AS total_sales
            FROM sales
            WHERE tenant_id = ? AND voucher_code = ?
        ");
        $stmt->execute([$tenant_id, $voucher['code']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $summary = $row;
    } catch (PDOException $e) {}

    // Rows
    try {
        $sql = "
            SELECT s.id, s.invoice_number, s.total, s.discount, s.payment_method, s.status, s.created_at,
                   c.name AS customer_name, c.phone AS customer_phone,
                   u.name AS cashier_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u     ON u.id = s.user_id
            $where $order
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $usage_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('voucher_usage rows: ' . $e->getMessage()); }
}

// Computed voucher status
$is_active  = $voucher['active'] && (is_null($voucher['expires_at']) || $voucher['expires_at'] >= date('Y-m-d'));
$is_expired = !is_null($voucher['expires_at']) && $voucher['expires_at'] < date('Y-m-d');
$st_key     = $is_active ? 'active' : ($is_expired ? 'expired' : 'inactive');
$vst_tw     = [
    'active'   => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'expired'  => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    'inactive' => 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30',
];

$page_title = 'Usage: ' . $voucher['code'];
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="view_voucher.php?id=<?php echo $voucher_id; ?>" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Voucher
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-history text-amber-400"></i>
            Usage History —
            <span class="font-mono tracking-widest text-amber-400"><?php echo htmlspecialchars($voucher['code']); ?></span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $vst_tw[$st_key]; ?>">
                <?php echo ucfirst($st_key); ?>
            </span>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo number_format($total_rows); ?> transaction<?php echo $total_rows !== 1 ? 's' : ''; ?> found
        </p>
    </div>
    <div class="shrink-0">
        <a href="voucher_form.php?id=<?php echo $voucher_id; ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-pen text-xs"></i> Edit Voucher
        </a>
    </div>
</div>

<?php if (!$has_voucher_code): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm">
    <i class="fas fa-exclamation-triangle"></i>
    Usage tracking requires a <code class="font-mono bg-slate-800 px-1 rounded">voucher_code</code> column on the <code class="font-mono bg-slate-800 px-1 rounded">sales</code> table.
</div>
<?php else: ?>

<!-- Summary cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-receipt text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400"><?php echo number_format((int)$summary['cnt']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total Uses</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-tag text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400"><?php echo $currency; ?> <?php echo number_format((float)$summary['total_discount'], 2); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total Saved</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-chart-bar text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400"><?php echo $currency; ?> <?php echo number_format((float)$summary['total_sales'], 0); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Revenue (completed)</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="id" value="<?php echo $voucher_id; ?>">

        <div class="relative flex-1 min-w-[160px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Invoice, customer, phone…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <select name="sort" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="newest"  <?php echo $sort_by === 'newest'  ? 'selected' : ''; ?>>Newest First</option>
            <option value="oldest"  <?php echo $sort_by === 'oldest'  ? 'selected' : ''; ?>>Oldest First</option>
            <option value="highest" <?php echo $sort_by === 'highest' ? 'selected' : ''; ?>>Highest Amount</option>
            <option value="lowest"  <?php echo $sort_by === 'lowest'  ? 'selected' : ''; ?>>Lowest Amount</option>
        </select>

        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">

        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <a href="voucher_usage.php?id=<?php echo $voucher_id; ?>" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
    </form>
</div>

<!-- Table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px]">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Invoice</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Cashier</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Payment</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Discount</th>
                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
            <?php if (empty($usage_rows)): ?>
                <tr>
                    <td colspan="8" class="px-4 py-14 text-center">
                        <i class="fas fa-history text-4xl text-slate-700 block mb-3"></i>
                        <p class="text-slate-500 text-sm">No usage records found</p>
                        <?php if ($search || $date_from || $date_to): ?>
                        <a href="voucher_usage.php?id=<?php echo $voucher_id; ?>" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            <i class="fas fa-times text-xs"></i> Clear filters
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($usage_rows as $row):
                    $pm    = $row['payment_method'] ?? 'cash';
                    $pm_tw = $payment_tw[$pm] ?? 'bg-slate-500/10 text-slate-400';
                    $st    = $row['status'] ?? 'pending';
                    $st_tw = $status_tw[$st] ?? 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30';
                    $inv   = !empty($row['invoice_number']) ? $row['invoice_number'] : 'SALE-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
                ?>
                <tr class="hover:bg-slate-700/30 transition-colors group">
                    <td class="px-3 py-2.5">
                        <a href="../pos/receipts/view_sale.php?id=<?php echo (int)$row['id']; ?>"
                           class="font-mono text-sm font-semibold text-amber-400 hover:text-amber-300 transition-colors">
                            <?php echo htmlspecialchars($inv); ?>
                        </a>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-slate-300"><?php echo date('d M Y', strtotime($row['created_at'])); ?></div>
                        <div class="text-xs text-slate-500"><?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-white"><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in'); ?></div>
                        <?php if (!empty($row['customer_phone'])): ?>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($row['customer_phone']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo htmlspecialchars($row['cashier_name'] ?? '—'); ?></td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $pm_tw; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $pm)); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $st_tw; ?>">
                            <?php echo ucfirst($st); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <span class="text-sm font-semibold text-emerald-400">-<?php echo $currency; ?> <?php echo number_format((float)($row['discount'] ?? 0), 2); ?></span>
                    </td>
                    <td class="px-3 py-2.5 text-right">
                        <span class="text-sm font-bold text-amber-400"><?php echo $currency; ?> <?php echo number_format((float)$row['total'], 0); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table footer + pagination -->
    <?php if ($total_rows > 0): ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300 font-medium"><?php echo $offset + 1; ?>–<?php echo min($offset + $limit, $total_rows); ?></span>
            of <span class="text-slate-300 font-medium"><?php echo number_format($total_rows); ?></span> uses
        </p>
        <?php if ($total_pages > 1): ?>
        <div class="flex items-center gap-1">
            <a href="?<?php echo htmlspecialchars($build_qs(['page' => max(1, $page - 1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page <= 1 ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?<?php echo htmlspecialchars($build_qs(['page' => $i])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors font-medium <?php echo $i === $page ? 'bg-amber-500/20 border-amber-500/40 text-amber-400' : 'bg-slate-800 border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <a href="?<?php echo htmlspecialchars($build_qs(['page' => min($total_pages, $page + 1)])); ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page >= $total_pages ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; // has_voucher_code ?>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
