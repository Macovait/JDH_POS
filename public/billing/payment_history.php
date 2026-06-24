<?php
/**
 * Payment History - Jakababa POS
 * Pure Tailwind CSS
 */

$page_title = 'Payment History';
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

if (!check_permission('billing.view') && !is_super_admin()) {
    enforce_permission('billing.view');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query - payments table columns: id, sale_id, method, amount, status, created_at, tenant_id
// JOIN sales for invoice_number, and customers for customer info
$where_conditions = ["p.tenant_id = ?"];
$params = [$tenant_id];

if (!empty($search)) {
    $where_conditions[] = "(s.invoice_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_method)) {
    $where_conditions[] = "p.method = ?";
    $params[] = $payment_method;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$base_from = "FROM payments p
    LEFT JOIN sales s ON p.sale_id = s.id
    LEFT JOIN customers c ON s.customer_id = c.id";

// Total count
$count_sql = "SELECT COUNT(*) as total $base_from $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get payments
$sql = "SELECT p.*, s.invoice_number, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
    $base_from $where_clause ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Statistics — status values in payments table: 'paid', 'pending', 'failed', etc.
$stats_sql = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_amount
    FROM payments WHERE tenant_id = ?";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$tenant_id]);
$stats = $stmt->fetch();

$csrf_token = generate_csrf_token();
$has_filters = !empty($search) || !empty($status_filter) || !empty($payment_method) || !empty($date_from) || !empty($date_to);

// Build pagination query string
$qs_parts = [];
if (!empty($search)) $qs_parts[] = 'search=' . urlencode($search);
if (!empty($status_filter)) $qs_parts[] = 'status=' . urlencode($status_filter);
if (!empty($payment_method)) $qs_parts[] = 'payment_method=' . urlencode($payment_method);
if (!empty($date_from)) $qs_parts[] = 'date_from=' . urlencode($date_from);
if (!empty($date_to)) $qs_parts[] = 'date_to=' . urlencode($date_to);
$qs = !empty($qs_parts) ? '&' . implode('&', $qs_parts) : '';
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-history text-amber-400"></i> Payment History
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">View all payment transactions</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="../dashboard/billing.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Billing
        </a>
    </div>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-receipt text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-white truncate"><?php echo number_format($stats['total']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400 truncate"><?php echo number_format($stats['completed']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Completed</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-clock text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400 truncate"><?php echo number_format($stats['pending']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Pending</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-times-circle text-red-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-red-400 truncate"><?php echo number_format($stats['failed']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Failed</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-money-bill-wave text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-white truncate">KSh <?php echo number_format($stats['total_amount'] ?? 0, 0); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Amount</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <form method="GET" class="flex flex-wrap gap-2">
        <div class="flex-1 min-w-[180px]">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search transaction, email, phone..."
                   class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <select name="status" class="px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 min-w-[110px]">
            <option value="">All Status</option>
            <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
        </select>
        <select name="payment_method" class="px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 min-w-[110px]">
            <option value="">All Methods</option>
            <option value="mpesa" <?php echo $payment_method === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
            <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
            <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
        </select>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
               class="px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 min-w-[130px]">
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
               class="px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 min-w-[130px]">
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter text-xs"></i> Filter
        </button>
        <?php if ($has_filters): ?>
        <a href="payment_history.php" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-times text-xs"></i> Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Payments table -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400">Transaction ID</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400">Customer</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400">Amount</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400">Method</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400">Status</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400">Date</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="7" class="px-3 py-10 text-center">
                        <i class="fas fa-receipt text-2xl text-slate-600 mb-2"></i>
                        <p class="text-slate-500 text-sm">No payment records found</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($payments as $payment):
                    $status = $payment['status'];
                    if ($status === 'paid' || $status === 'completed') {
                        $badge_class = 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30';
                    } elseif ($status === 'pending') {
                        $badge_class = 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30';
                    } else {
                        $badge_class = 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30';
                    }
                ?>
                <tr class="hover:bg-slate-700/20 transition-colors">
                    <td class="px-3 py-2.5">
                        <span class="font-mono text-xs text-white"><?php echo htmlspecialchars($payment['invoice_number'] ?? '#' . $payment['id']); ?></span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-white"><?php echo htmlspecialchars($payment['customer_name'] ?? 'Walk-in'); ?></div>
                        <div class="text-xs text-slate-500"><?php echo htmlspecialchars($payment['customer_email'] ?? $payment['customer_phone'] ?? ''); ?></div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-sm font-semibold text-white">KSh <?php echo number_format($payment['amount'], 0); ?></span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-sm text-slate-300"><?php echo ucfirst(htmlspecialchars($payment['method'] ?? '')); ?></span>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $badge_class; ?>">
                            <?php echo ucfirst(htmlspecialchars($status)); ?>
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-white"><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></div>
                        <div class="text-xs text-slate-500"><?php echo date('H:i:s', strtotime($payment['created_at'])); ?></div>
                    </td>
                    <td class="px-3 py-2.5">
                        <div class="flex items-center gap-1.5">
                            <button onclick="viewDetails(<?php echo $payment['id']; ?>)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-700/40 text-slate-400 hover:bg-amber-500/15 hover:text-amber-400 transition-colors" title="View Details">
                                <i class="fas fa-eye text-xs"></i>
                            </button>
                            <?php if ($status === 'pending'): ?>
                            <button onclick="checkStatus(<?php echo $payment['id']; ?>, this)" class="w-6 h-6 flex items-center justify-center rounded bg-slate-700/40 text-slate-400 hover:bg-emerald-500/15 hover:text-emerald-400 transition-colors" title="Check Status">
                                <i class="fas fa-sync text-xs"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/60 flex items-center justify-between">
        <div class="text-xs text-slate-500">
            Showing <?php echo ($offset + 1); ?>–<?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?>
        </div>
        <div class="flex items-center gap-1.5">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo $qs; ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-700/40 text-slate-400 hover:bg-slate-600 hover:text-white transition-colors">
                <i class="fas fa-chevron-left text-xs"></i>
            </a>
            <?php endif; ?>
            <span class="px-2.5 py-1 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-xs font-medium">
                <?php echo $page; ?> / <?php echo $total_pages; ?>
            </span>
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $qs; ?>"
               class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-700/40 text-slate-400 hover:bg-slate-600 hover:text-white transition-colors">
                <i class="fas fa-chevron-right text-xs"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     Payment Detail Modal
════════════════════════════════════════════════════════════════ -->
<div id="paymentDetailModal"
     class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modalTitle">

    <!-- Backdrop -->
    <div id="modalBackdrop"
         class="absolute inset-0 bg-black/70 "
         onclick="closeModal()"></div>

    <!-- Panel -->
    <div class="relative w-full max-w-2xl max-h-[90vh] flex flex-col bg-slate-900 border border-slate-700 rounded-xl shadow-2xl overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-700 bg-slate-800/60 shrink-0">
            <h2 id="modalTitle" class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-receipt text-amber-400"></i>
                <span id="modalInvoice">Payment Details</span>
            </h2>
            <button onclick="closeModal()"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <!-- Loading state -->
        <div id="modalLoading" class="flex flex-col items-center justify-center py-16 gap-3">
            <div class="w-8 h-8 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-slate-500 text-sm">Loading details…</p>
        </div>

        <!-- Error state -->
        <div id="modalError" class="hidden flex-col items-center justify-center py-16 gap-3 px-6 text-center">
            <i class="fas fa-exclamation-triangle text-2xl text-red-400"></i>
            <p id="modalErrorMsg" class="text-slate-400 text-sm"></p>
        </div>

        <!-- Content -->
        <div id="modalContent" class="hidden flex-col overflow-y-auto divide-y divide-slate-700/50">

            <!-- Payment summary row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 px-5 py-4 bg-slate-800/30">
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Amount</p>
                    <p id="detailAmount" class="text-sm font-bold text-white"></p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Method</p>
                    <p id="detailMethod" class="text-sm font-semibold text-slate-200"></p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Status</p>
                    <span id="detailStatus" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"></span>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-0.5">Date</p>
                    <p id="detailDate" class="text-xs text-slate-300"></p>
                </div>
            </div>

            <!-- Customer -->
            <div id="detailCustomerSection" class="hidden px-5 py-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Customer</p>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                        <i class="fas fa-user text-amber-400 text-xs"></i>
                    </div>
                    <div>
                        <p id="detailCustomerName"  class="text-sm font-semibold text-white"></p>
                        <p id="detailCustomerPhone" class="text-xs text-slate-500"></p>
                        <p id="detailCustomerEmail" class="text-xs text-slate-500"></p>
                    </div>
                </div>
            </div>

            <!-- M-Pesa receipt -->
            <div id="detailMpesaSection" class="hidden px-5 py-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">M-Pesa Receipt</p>
                <div class="bg-slate-800/60 rounded-lg p-3 text-xs text-slate-300 space-y-1">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Receipt No.</span>
                        <span id="mpesaReceipt" class="font-mono text-emerald-400"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Phone</span>
                        <span id="mpesaPhone"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Txn Date</span>
                        <span id="mpesaDate"></span>
                    </div>
                </div>
            </div>

            <!-- Sale totals -->
            <div id="detailSaleSection" class="hidden px-5 py-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Sale Summary</p>
                <div class="bg-slate-800/60 rounded-lg p-3 text-xs space-y-1.5">
                    <div class="flex justify-between text-slate-400">
                        <span>Subtotal</span><span id="saleSubtotal"></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Tax</span><span id="saleTax"></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Discount</span><span id="saleDiscount"></span>
                    </div>
                    <div class="flex justify-between font-semibold text-white border-t border-slate-700 pt-1.5 mt-1">
                        <span>Total</span><span id="saleTotal"></span>
                    </div>
                </div>
                <p id="saleNotes" class="text-xs text-slate-500 mt-2 hidden"></p>
            </div>

            <!-- Items table -->
            <div id="detailItemsSection" class="hidden px-5 py-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Items</p>
                <div class="rounded-lg border border-slate-700/60 overflow-hidden">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-slate-800/60 border-b border-slate-700/60">
                                <th class="px-3 py-2 text-left text-slate-400 font-semibold">Product</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-semibold">Qty</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-semibold">Unit Price</th>
                                <th class="px-3 py-2 text-right text-slate-400 font-semibold">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detailItemsBody" class="divide-y divide-slate-700/40"></tbody>
                    </table>
                </div>
            </div>

        </div><!-- /modalContent -->
    </div><!-- /panel -->
</div>

<script>
const CSRF = '<?php echo $csrf_token; ?>';

/* ── helpers ────────────────────────────────────────────────── */
function statusBadge(status) {
    const map = {
        paid:      'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
        completed: 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
        pending:   'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
        failed:    'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
        cancelled: 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    };
    return map[status] || 'bg-slate-700 text-slate-400';
}

function setText(id, val) { document.getElementById(id).textContent = val || '—'; }

function openModal()  {
    const m = document.getElementById('paymentDetailModal');
    m.classList.remove('hidden');
    m.classList.add('flex');
}
function closeModal() {
    const m = document.getElementById('paymentDetailModal');
    m.classList.add('hidden');
    m.classList.remove('flex');
}

/* ── viewDetails ────────────────────────────────────────────── */
function viewDetails(paymentId) {
    // Reset states
    document.getElementById('modalLoading').classList.remove('hidden');
    document.getElementById('modalError').classList.add('hidden');
    document.getElementById('modalContent').classList.add('hidden');
    document.getElementById('modalContent').classList.remove('flex');
    document.getElementById('modalInvoice').textContent = 'Payment Details';
    openModal();

    fetch('get_payment_details.php?payment_id=' + paymentId)
        .then(r => r.json())
        .then(data => {
            document.getElementById('modalLoading').classList.add('hidden');

            if (!data.success) {
                document.getElementById('modalErrorMsg').textContent = data.error || 'Failed to load details.';
                document.getElementById('modalError').classList.remove('hidden');
                document.getElementById('modalError').classList.add('flex');
                return;
            }

            const p = data.payment;
            const s = data.sale;
            const c = data.customer;
            const m = data.mpesa;

            // Invoice header
            document.getElementById('modalInvoice').textContent =
                (s && s.invoice_number) ? s.invoice_number : ('PMT-' + p.id);

            // Summary row
            setText('detailAmount', p.amount_fmt);
            setText('detailMethod', p.method.charAt(0).toUpperCase() + p.method.slice(1));
            setText('detailDate', p.created_at_fmt);
            const badge = document.getElementById('detailStatus');
            badge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' + statusBadge(p.status);
            badge.textContent = p.status.charAt(0).toUpperCase() + p.status.slice(1);

            // Customer
            const custSec = document.getElementById('detailCustomerSection');
            if (c && c.name) {
                custSec.classList.remove('hidden');
                setText('detailCustomerName',  c.name);
                setText('detailCustomerPhone', c.phone || '');
                setText('detailCustomerEmail', c.email || '');
            } else {
                custSec.classList.add('hidden');
            }

            // M-Pesa
            const mpSec = document.getElementById('detailMpesaSection');
            if (m && m.mpesa_receipt_number) {
                mpSec.classList.remove('hidden');
                setText('mpesaReceipt', m.mpesa_receipt_number);
                setText('mpesaPhone',   m.phone_number || '');
                setText('mpesaDate',    m.transaction_date || '');
            } else {
                mpSec.classList.add('hidden');
            }

            // Sale totals
            const saleSec = document.getElementById('detailSaleSection');
            if (s) {
                saleSec.classList.remove('hidden');
                setText('saleSubtotal', s.subtotal_fmt);
                setText('saleTax',      s.tax_fmt);
                setText('saleDiscount', s.discount_fmt);
                setText('saleTotal',    s.total_fmt);
                const notesEl = document.getElementById('saleNotes');
                if (s.notes) {
                    notesEl.textContent = 'Note: ' + s.notes;
                    notesEl.classList.remove('hidden');
                } else {
                    notesEl.classList.add('hidden');
                }
            } else {
                saleSec.classList.add('hidden');
            }

            // Items
            const itemsSec  = document.getElementById('detailItemsSection');
            const itemsBody = document.getElementById('detailItemsBody');
            itemsBody.innerHTML = '';
            if (data.items && data.items.length > 0) {
                itemsSec.classList.remove('hidden');
                data.items.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.className = 'hover:bg-slate-700/20';
                    tr.innerHTML = `
                        <td class="px-3 py-2 text-white">
                            ${escHtml(item.product_name)}
                            ${item.sku ? '<span class="text-slate-500 ml-1">' + escHtml(item.sku) + '</span>' : ''}
                        </td>
                        <td class="px-3 py-2 text-right text-slate-300">${item.quantity}</td>
                        <td class="px-3 py-2 text-right text-slate-300">KSh ${fmtNum(item.unit_price)}</td>
                        <td class="px-3 py-2 text-right font-semibold text-white">KSh ${fmtNum(item.subtotal)}</td>
                    `;
                    itemsBody.appendChild(tr);
                });
            } else {
                itemsSec.classList.add('hidden');
            }

            document.getElementById('modalContent').classList.remove('hidden');
            document.getElementById('modalContent').classList.add('flex');
        })
        .catch(() => {
            document.getElementById('modalLoading').classList.add('hidden');
            document.getElementById('modalErrorMsg').textContent = 'Network error – could not load payment details.';
            document.getElementById('modalError').classList.remove('hidden');
            document.getElementById('modalError').classList.add('flex');
        });
}

/* ── checkStatus ────────────────────────────────────────────── */
function checkStatus(paymentId, btn) {
    if (btn) {
        btn.disabled = true;
        btn.querySelector('i').classList.add('animate-spin');
    }

    fetch('update_payment_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'payment_id=' + paymentId + '&csrf_token=' + encodeURIComponent(CSRF)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            showToast(data.message || 'Error checking status', 'error');
            if (btn) { btn.disabled = false; btn.querySelector('i').classList.remove('animate-spin'); }
            return;
        }
        if (data.changed) {
            showToast('Payment ' + (data.invoice_number || '#' + paymentId) + ' is now ' + data.new_status, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message, 'info');
            if (btn) { btn.disabled = false; btn.querySelector('i').classList.remove('animate-spin'); }
        }
    })
    .catch(() => {
        showToast('Network error – could not check payment status', 'error');
        if (btn) { btn.disabled = false; btn.querySelector('i').classList.remove('animate-spin'); }
    });
}

/* ── tiny toast ─────────────────────────────────────────────── */
function showToast(msg, type = 'info') {
    const colors = {
        success: 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300',
        error:   'bg-red-500/20 border-red-500/40 text-red-300',
        info:    'bg-slate-700/80 border-slate-600 text-slate-200',
    };
    const t = document.createElement('div');
    t.className = 'fixed bottom-5 right-5 z-[9999] px-4 py-2.5 rounded-lg border text-sm shadow-lg transition-opacity ' + (colors[type] || colors.info);
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
}

/* ── utils ──────────────────────────────────────────────────── */
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtNum(n) {
    return Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Close on Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
