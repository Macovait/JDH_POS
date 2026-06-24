<?php
/**
 * View Sale - Display sale details
 * Pure Tailwind CSS
 */

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
require_once $pathsFile;

safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sale_id) { header('Location: ../all_sales.php'); exit; }

$pdo       = get_db_connection();
$tenant_id = get_current_tenant_id();

// Business type labels — safe fallback, no undefined function call
$bt_sale_label     = 'Sale';
$bt_customer_label = 'Customer';
if (function_exists('get_current_business_type') && function_exists('get_business_type_config')) {
    $bt_config         = get_business_type_config(get_current_business_type()) ?? [];
    $bt_sale_label     = $bt_config['sale_label']     ?? 'Sale';
    $bt_customer_label = $bt_config['customer_label'] ?? 'Customer';
}

// Column detection helper (scoped to avoid conflicts)
$_hc = function(string $table, string $col) use ($pdo): bool {
    try {
        $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $s->execute([$col]);
        return $s->rowCount() > 0;
    } catch (Exception $e) { return false; }
};

$has_invoice       = $_hc('sales',    'invoice_number');
$has_status        = $_hc('sales',    'status');
$has_subtotal      = $_hc('sales',    'subtotal');
$has_tax           = $_hc('sales',    'tax');
$has_order_type    = $_hc('sales',    'order_type');
$has_notes         = $_hc('sales',    'notes');
$has_selling_price = $_hc('products', 'selling_price');

// Fetch sale
$sale = null;
try {
    $q = "SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                 u.name AS cashier_name, b.name AS branch_name
          FROM sales s
          LEFT JOIN customers c ON s.customer_id = c.id
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN branches b ON s.branch_id = b.id
          WHERE s.id = ?";
    if ($tenant_id && $_hc('sales', 'tenant_id')) {
        $q .= " AND (s.tenant_id = ? OR s.tenant_id IS NULL)";
        $s = $pdo->prepare($q); $s->execute([$sale_id, $tenant_id]);
    } else {
        $s = $pdo->prepare($q); $s->execute([$sale_id]);
    }
    $sale = $s->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('view_sale fetch: ' . $e->getMessage()); }

if (!$sale) {
    $_SESSION['flash_message'] = 'Sale not found';
    $_SESSION['flash_type']    = 'error';
    header('Location: ../all_sales.php'); exit;
}

// Fetch items
$items = [];
try {
    $price_expr = $has_selling_price ? 'COALESCE(p.selling_price, p.price)' : 'p.price';
    $s = $pdo->prepare("SELECT si.*, p.name AS product_name, p.sku, {$price_expr} AS product_price
                         FROM sale_items si LEFT JOIN products p ON si.product_id = p.id
                         WHERE si.sale_id = ?");
    $s->execute([$sale_id]);
    $items = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('view_sale items: ' . $e->getMessage()); }

$currency = 'KES';
if (function_exists('get_settings')) {
    $settings = get_settings();
    if (!empty($settings['currency'])) $currency = $settings['currency'];
}

$page_title = $bt_sale_label . ' #' . str_pad($sale_id, 6, '0', STR_PAD_LEFT);
ob_start();

// Status badge helper
$status_tw = [
    'completed' => ['bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30', 'fa-check-circle'],
    'pending'   => ['bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',       'fa-clock'],
    'cancelled' => ['bg-red-500/15 text-red-400 ring-1 ring-red-500/30',             'fa-times-circle'],
    'draft'     => ['bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30',       'fa-pen'],
];
$st      = $sale['status'] ?? 'pending';
$st_tw   = $status_tw[$st] ?? ['bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30', 'fa-circle'];
?>

<!-- Flash -->
<?php if (isset($_SESSION['flash_message'])): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg text-sm
    <?php echo ($_SESSION['flash_type'] ?? '') === 'success'
        ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400'
        : 'bg-red-500/10 border border-red-500/30 text-red-400'; ?>">
    <i class="fas fa-<?php echo ($_SESSION['flash_type'] ?? '') === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
</div>
<?php endif; ?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
    <div>
        <a href="/JDH_POS/public/pos/all_sales.php" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Sales
        </a>
        <h1 class="text-2xl font-bold text-white flex items-center gap-2 flex-wrap">
            <i class="fas fa-receipt text-amber-400 text-xl"></i>
            <?php echo htmlspecialchars($bt_sale_label); ?> #<?php echo str_pad($sale_id, 6, '0', STR_PAD_LEFT); ?>
            <?php if ($has_invoice && !empty($sale['invoice_number'])): ?>
                <span class="text-amber-400 text-base font-normal"><?php echo htmlspecialchars($sale['invoice_number']); ?></span>
            <?php endif; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            <i class="fas fa-clock text-xs mr-1"></i><?php echo date('d M Y, H:i', strtotime($sale['created_at'])); ?>
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <?php if ($has_status): ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium <?php echo $st_tw[0]; ?>">
            <i class="fas <?php echo $st_tw[1]; ?> text-xs"></i> <?php echo ucfirst($st); ?>
        </span>
        <?php endif; ?>
        <a href="print_invoice.php?id=<?php echo $sale_id; ?>" target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-300 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-print text-xs"></i> Print
        </a>
        <a href="receipt.php?id=<?php echo $sale_id; ?>" target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-receipt text-xs"></i> Receipt
        </a>
    </div>
</div>

<!-- Body grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    <!-- Left: items + notes -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Items table -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 flex items-center gap-2">
                <i class="fas fa-shopping-cart text-amber-400 text-sm"></i>
                <h3 class="text-sm font-semibold text-white">Items
                    <span class="ml-1.5 text-xs font-normal text-slate-500">(<?php echo count($items); ?>)</span>
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-800/60 border-b border-slate-700/60">
                        <tr>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Unit Price</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Qty</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                <i class="fas fa-inbox text-3xl text-slate-700 block mb-2"></i>No items found
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php $n = 1; foreach ($items as $item): ?>
                        <tr class="hover:bg-slate-700/25 transition-colors">
                            <td class="px-4 py-3 text-sm text-slate-500"><?php echo $n++; ?></td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?></div>
                                <?php if (!empty($item['sku'])): ?>
                                <div class="text-xs text-slate-500 mt-0.5">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-slate-400">
                                <?php echo $currency; ?> <?php echo number_format((float)$item['price'], 2); ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-white">
                                <?php echo (int)$item['quantity']; ?>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-amber-400">
                                <?php echo $currency; ?> <?php echo number_format((float)($item['subtotal'] ?? $item['price'] * $item['quantity']), 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notes -->
        <?php if ($has_notes && !empty($sale['notes'])): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-2 flex items-center gap-2">
                <i class="fas fa-sticky-note text-amber-400 text-xs"></i> Notes
            </h3>
            <p class="text-sm text-slate-400 leading-relaxed"><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: summary + details -->
    <div class="space-y-4">

        <!-- Summary -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-calculator text-amber-400 text-xs"></i> Summary
            </h3>
            <div class="space-y-2">
                <?php if ($has_subtotal && isset($sale['subtotal'])): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Subtotal</span>
                    <span class="text-slate-300"><?php echo $currency; ?> <?php echo number_format((float)$sale['subtotal'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($sale['discount'])): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Discount</span>
                    <span class="text-emerald-400">-<?php echo $currency; ?> <?php echo number_format((float)$sale['discount'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($has_tax && !empty($sale['tax'])): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Tax</span>
                    <span class="text-slate-300"><?php echo $currency; ?> <?php echo number_format((float)$sale['tax'], 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between items-center pt-3 mt-1 border-t border-slate-700/60">
                    <span class="text-base font-semibold text-white">Total</span>
                    <span class="text-xl font-bold text-amber-400"><?php echo $currency; ?> <?php echo number_format((float)$sale['total'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Transaction details -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-info-circle text-amber-400 text-xs"></i> Details
            </h3>
            <dl class="space-y-2.5">
                <?php
                $details = [
                    $bt_customer_label => htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'),
                ];
                if (!empty($sale['customer_phone'])) {
                    $details['Phone'] = htmlspecialchars($sale['customer_phone']);
                }
                if (!empty($sale['customer_email'])) {
                    $details['Email'] = htmlspecialchars($sale['customer_email']);
                }
                $details['Cashier']        = htmlspecialchars($sale['cashier_name'] ?? '—');
                $details['Branch']         = htmlspecialchars($sale['branch_name'] ?? '—');
                $details['Payment Method'] = htmlspecialchars(ucwords(str_replace('_', ' ', $sale['payment_method'] ?? 'cash')));
                if ($has_order_type && !empty($sale['order_type'])) {
                    $details['Order Type'] = htmlspecialchars(ucwords(str_replace('-', ' ', $sale['order_type'])));
                }
                $details['Date'] = date('d M Y, H:i:s', strtotime($sale['created_at']));
                foreach ($details as $label => $val):
                ?>
                <div class="flex justify-between gap-3">
                    <dt class="text-sm text-slate-500 shrink-0"><?php echo $label; ?></dt>
                    <dd class="text-sm text-white text-right"><?php echo $val; ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        </div>

        <!-- Payment reference -->
        <?php if (!empty($sale['reference'])): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-2 flex items-center gap-2">
                <i class="fas fa-hashtag text-amber-400 text-xs"></i> Payment Reference
            </h3>
            <p class="text-sm font-mono text-slate-400 break-all"><?php echo htmlspecialchars($sale['reference']); ?></p>
        </div>
        <?php endif; ?>

        <!-- Quick actions -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-bolt text-amber-400 text-xs"></i> Actions
            </h3>
            <div class="flex flex-col gap-2">
                <a href="print_invoice.php?id=<?php echo $sale_id; ?>" target="_blank"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-700/60 text-slate-300 text-sm hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-file-invoice text-amber-400 text-xs w-4 text-center"></i> Print Invoice
                </a>
                <a href="receipt.php?id=<?php echo $sale_id; ?>" target="_blank"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-700/60 text-slate-300 text-sm hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-receipt text-amber-400 text-xs w-4 text-center"></i> Print Receipt
                </a>
                <?php if ($st === 'completed'): ?>
                <a href="/JDH_POS/public/pos/returns/return_sale.php?id=<?php echo $sale_id; ?>"
                   class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-700/60 text-orange-400 text-sm hover:bg-orange-500/20 transition-colors">
                    <i class="fas fa-undo-alt text-xs w-4 text-center"></i> Process Return
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>