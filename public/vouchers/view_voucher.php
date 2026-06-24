<?php
/**
 * View Voucher - Detail page
 * Pure Tailwind CSS matching all_sales.php / view_sale.php design system
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
$user_role = $_SESSION['role'] ?? '';

// Fetch voucher
$voucher = null;
try {
    $stmt = $pdo->prepare('SELECT * FROM vouchers WHERE id = ? AND tenant_id = ?');
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('view_voucher fetch: ' . $e->getMessage());
}

if (!$voucher) {
    header('Location: vouchers.php?error=' . urlencode('Voucher not found'));
    exit;
}

// Fetch usage history via sales table — check if voucher_code column exists
$has_voucher_code = false;
try {
    $s = $pdo->prepare("SHOW COLUMNS FROM `sales` LIKE 'voucher_code'");
    $s->execute();
    $has_voucher_code = $s->rowCount() > 0;
} catch (Exception $e) {}

$usage_rows  = [];
$usage_total = 0;
$usage_count = 0;

if ($has_voucher_code) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.invoice_number, s.total, s.discount, s.payment_method,
                   s.created_at, s.status,
                   c.name AS customer_name,
                   u.name AS cashier_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u     ON u.id = s.user_id
            WHERE s.tenant_id = ? AND s.voucher_code = ?
            ORDER BY s.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$tenant_id, $voucher['code']]);
        $usage_rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $usage_count = count($usage_rows);
        $usage_total = array_sum(array_column($usage_rows, 'discount'));
    } catch (PDOException $e) {
        error_log('view_voucher usage: ' . $e->getMessage());
    }
} else {
    // Fallback: check usage_count column on vouchers table
    $usage_count = (int) ($voucher['usage_count'] ?? 0);
}

$currency = function_exists('get_settings') ? get_settings('currency', 'KES', $tenant_id) : 'KES';

// Computed status
$is_active  = $voucher['active'] && (is_null($voucher['expires_at']) || $voucher['expires_at'] >= date('Y-m-d'));
$is_expired = !is_null($voucher['expires_at']) && $voucher['expires_at'] < date('Y-m-d');
$st_key     = $is_active ? 'active' : ($is_expired ? 'expired' : 'inactive');
$status_tw  = [
    'active'   => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'expired'  => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
    'inactive' => 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30',
];
$payment_tw = [
    'cash'          => 'bg-emerald-500/10 text-emerald-400',
    'card'          => 'bg-blue-500/10 text-blue-400',
    'mpesa'         => 'bg-amber-500/10 text-amber-400',
    'bank_transfer' => 'bg-purple-500/10 text-purple-400',
    'credit'        => 'bg-red-500/10 text-red-400',
];

$page_title = 'Voucher: ' . $voucher['code'];
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5">
    <div>
        <a href="vouchers.php" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Vouchers
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-ticket-alt text-amber-400"></i>
            <span class="font-mono tracking-widest"><?php echo htmlspecialchars($voucher['code']); ?></span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_tw[$st_key]; ?>">
                <?php echo ucfirst($st_key); ?>
            </span>
        </h1>
        <?php if (!empty($voucher['description'])): ?>
        <p class="text-sm text-slate-500 mt-0.5"><?php echo htmlspecialchars($voucher['description']); ?></p>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="voucher_form.php?id=<?php echo $voucher_id; ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-pen text-xs"></i> Edit
        </a>
        <a href="voucher_usage.php?id=<?php echo $voucher_id; ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-history text-xs"></i> Full History
        </a>
    </div>
</div>

<!-- Body grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Left: usage history table -->
    <div class="lg:col-span-2 space-y-5">

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 flex items-center gap-2">
                <i class="fas fa-history text-amber-400 text-xs"></i>
                <h3 class="text-sm font-semibold text-white">
                    Usage History
                    <span class="ml-1.5 text-xs font-normal text-slate-500">(<?php echo $usage_count; ?> uses)</span>
                </h3>
            </div>

            <?php if (!$has_voucher_code): ?>
            <div class="px-4 py-10 text-center">
                <i class="fas fa-info-circle text-3xl text-slate-700 block mb-2"></i>
                <p class="text-sm text-slate-500">Usage tracking requires a <code class="text-amber-400">voucher_code</code> column on the sales table.</p>
            </div>
            <?php elseif (empty($usage_rows)): ?>
            <div class="px-4 py-10 text-center">
                <i class="fas fa-ticket-alt text-3xl text-slate-700 block mb-2"></i>
                <p class="text-sm text-slate-500">This voucher has not been used yet.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[500px]">
                    <thead class="bg-slate-800/60 border-b border-slate-700/60">
                        <tr>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Invoice</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Payment</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Discount</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($usage_rows as $row):
                            $pm    = $row['payment_method'] ?? 'cash';
                            $pm_tw = $payment_tw[$pm] ?? 'bg-slate-500/10 text-slate-400';
                            $inv   = !empty($row['invoice_number']) ? $row['invoice_number'] : 'SALE-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
                        ?>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <a href="../pos/receipts/view_sale.php?id=<?php echo (int)$row['id']; ?>"
                                   class="font-mono text-sm font-semibold text-amber-400 hover:text-amber-300 transition-colors">
                                    <?php echo htmlspecialchars($inv); ?>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-slate-300"><?php echo date('d M Y', strtotime($row['created_at'])); ?></div>
                                <div class="text-xs text-slate-500"><?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-300"><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in'); ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $pm_tw; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $pm)); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-400">
                                -<?php echo $currency; ?> <?php echo number_format((float)($row['discount'] ?? 0), 2); ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-amber-400">
                                <?php echo $currency; ?> <?php echo number_format((float)$row['total'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Footer -->
            <div class="flex items-center justify-between px-4 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
                <p class="text-xs text-slate-500">
                    Showing <span class="text-slate-300 font-medium"><?php echo $usage_count; ?></span> use<?php echo $usage_count !== 1 ? 's' : ''; ?>
                </p>
                <p class="text-xs text-slate-500">
                    Total saved: <span class="text-emerald-400 font-semibold"><?php echo $currency; ?> <?php echo number_format($usage_total, 2); ?></span>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: voucher details -->
    <div class="space-y-4">

        <!-- Value card -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-tag text-amber-400 text-xs"></i> Voucher Value
            </h3>
            <div class="flex items-center justify-center py-4">
                <div class="text-center">
                    <div class="text-4xl font-black <?php echo $voucher['type'] === 'fixed' ? 'text-emerald-400' : 'text-amber-400'; ?>">
                        <?php if ($voucher['type'] === 'fixed'): ?>
                        <?php echo $currency; ?> <?php echo number_format((float)$voucher['value'], 2); ?>
                        <?php else: ?>
                        <?php echo $voucher['value']; ?>%
                        <?php endif; ?>
                    </div>
                    <div class="mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $voucher['type'] === 'fixed' ? 'bg-blue-500/10 text-blue-400' : 'bg-amber-500/10 text-amber-400'; ?>">
                            <?php echo $voucher['type'] === 'fixed' ? 'Fixed Amount' : 'Percentage'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-info-circle text-amber-400 text-xs"></i> Details
            </h3>
            <dl class="space-y-2.5">
                <?php
                $details = [
                    'Status'       => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $status_tw[$st_key] . '">' . ucfirst($st_key) . '</span>',
                    'Min Purchase' => $voucher['min_purchase'] > 0 ? $currency . ' ' . number_format((float)$voucher['min_purchase'], 2) : '<span class="text-slate-600">None</span>',
                    'Max Discount' => !is_null($voucher['max_discount']) ? $currency . ' ' . number_format((float)$voucher['max_discount'], 2) : '<span class="text-slate-600">No limit</span>',
                    'Usage Limit'  => !empty($voucher['usage_limit']) ? number_format((int)$voucher['usage_limit']) . ' uses' : '<span class="text-slate-600">Unlimited</span>',
                    'Times Used'   => number_format($usage_count),
                    'Expires'      => $voucher['expires_at'] ? date('d M Y', strtotime($voucher['expires_at'])) : '<span class="text-slate-600">Never</span>',
                    'Created'      => date('d M Y', strtotime($voucher['created_at'])),
                ];
                foreach ($details as $label => $val):
                ?>
                <div class="flex justify-between gap-3">
                    <dt class="text-sm text-slate-500 shrink-0"><?php echo $label; ?></dt>
                    <dd class="text-sm text-white text-right"><?php echo $val; ?></dd>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($voucher['usage_limit']) && $usage_count > 0): ?>
                <div class="pt-1">
                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                        <span>Usage</span>
                        <span><?php echo $usage_count; ?> / <?php echo (int)$voucher['usage_limit']; ?></span>
                    </div>
                    <div class="h-1.5 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-500 rounded-full transition-all"
                             style="width: <?php echo min(100, round($usage_count / max(1, (int)$voucher['usage_limit']) * 100)); ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Quick actions -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-bolt text-amber-400 text-xs"></i> Actions
            </h3>
            <div class="flex flex-col gap-2">
                <a href="voucher_form.php?id=<?php echo $voucher_id; ?>"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-700/60 text-slate-300 text-sm hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-pen text-amber-400 text-xs w-4 text-center"></i> Edit Voucher
                </a>
                <a href="voucher_usage.php?id=<?php echo $voucher_id; ?>"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-700/60 text-slate-300 text-sm hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-history text-blue-400 text-xs w-4 text-center"></i> Full Usage History
                </a>
                <a href="vouchers.php"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-700/60 text-slate-300 text-sm hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-list text-slate-400 text-xs w-4 text-center"></i> All Vouchers
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
