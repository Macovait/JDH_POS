<?php
/**
 * View Quotation - Display quotation details
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$tenant_id = get_current_tenant_id();
$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$quotation_id) {
    header('Location: list_quotation.php');
    exit;
}

$pdo = get_db_connection();

// Fetch quotation
$stmt = $pdo->prepare("
    SELECT q.*, c.name as customer_name, c.phone, c.email, u.name as created_by_name,
           b.name as branch_name, bt.name as business_type_name
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.created_by = u.id
    LEFT JOIN branches b ON q.branch_id = b.id
    LEFT JOIN business_types bt ON q.business_type_id = bt.id
    WHERE q.id = ? AND q.tenant_id = ?
");
$stmt->execute([$quotation_id, $tenant_id]);
$quotation = $stmt->fetch();

if (!$quotation) {
    header('Location: list_quotation.php?error=not_found');
    exit;
}

// Fetch items
$stmt = $pdo->prepare("
    SELECT * FROM quotation_items WHERE quotation_id = ?
");
$stmt->execute([$quotation_id]);
$items = $stmt->fetchAll();

$currency_symbol = get_settings('currency', 'KSh', $tenant_id);
$page_title = 'Quotation #' . $quotation['quotation_number'];
ob_start();
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-400 uppercase tracking-wider mb-0.5">
                <i class="fas fa-file-invoice text-xs"></i>
                <span>Quotations</span>
            </div>
            <h1 class="text-xl font-bold text-white">Quotation Details</h1>
            <p class="text-sm text-slate-500 mt-0.5 font-mono">#<?php echo htmlspecialchars($quotation['quotation_number']); ?></p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <?php if ($quotation['status'] === 'draft'): ?>
                <a href="edit_quotation.php?id=<?php echo $quotation_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/15 border border-blue-500/30 rounded-lg text-blue-400 text-sm font-medium hover:bg-blue-500/25 transition-colors"><i class="fas fa-edit text-xs"></i> Edit</a>
            <?php endif; ?>
            <a href="print_quotation.php?id=<?php echo $quotation_id; ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors"><i class="fas fa-print text-xs"></i> Print</a>
            <a href="list_quotation.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-700 border border-slate-600 rounded-lg text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors"><i class="fas fa-arrow-left text-xs"></i> Back</a>
        </div>
    </div>

    <!-- Status Badge -->
    <div class="flex items-center gap-3 flex-wrap">
        <?php
        $status_colors = [
            'draft'    => 'bg-slate-500/15 text-slate-400 border-slate-500/30',
            'sent'     => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
            'accepted' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            'rejected' => 'bg-red-500/15 text-red-400 border-red-500/30',
            'expired'  => 'bg-orange-500/15 text-orange-400 border-orange-500/30'
        ];
        $status_color = $status_colors[$quotation['status']] ?? 'bg-slate-500/15 text-slate-400 border-slate-500/30';
        ?>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_color; ?>">
            <?php echo strtoupper($quotation['status']); ?>
        </span>
        <?php if ($quotation['valid_until']): ?>
            <span class="text-xs text-slate-500"><i class="fas fa-calendar mr-1"></i>Valid until: <?php echo date('d M Y', strtotime($quotation['valid_until'])); ?></span>
        <?php endif; ?>
    </div>

    <!-- Customer & Branch Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5"><i class="fas fa-user text-xs"></i> Customer Information</h3>
            <div class="space-y-2">
                <div class="flex justify-between items-center"><span class="text-slate-500 text-xs">Name:</span><span class="text-slate-200 text-xs"><?php echo htmlspecialchars($quotation['customer_name'] ?? 'Walk-in Customer'); ?></span></div>
                <?php if ($quotation['phone']): ?>
                <div class="flex justify-between items-center"><span class="text-slate-500 text-xs">Phone:</span><span class="text-slate-200 text-xs"><?php echo htmlspecialchars($quotation['phone']); ?></span></div>
                <?php endif; ?>
                <?php if ($quotation['email']): ?>
                <div class="flex justify-between items-center"><span class="text-slate-500 text-xs">Email:</span><span class="text-slate-200 text-xs"><?php echo htmlspecialchars($quotation['email']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5"><i class="fas fa-store text-xs"></i> Business Information</h3>
            <div class="space-y-2">
                <div class="flex justify-between items-center"><span class="text-slate-500 text-xs">Branch:</span><span class="text-slate-200 text-xs"><?php echo htmlspecialchars($quotation['branch_name'] ?? 'N/A'); ?></span></div>
                <div class="flex justify-between items-center"><span class="text-slate-500 text-xs">Created by:</span><span class="text-slate-200 text-xs"><?php echo htmlspecialchars($quotation['created_by_name'] ?? 'N/A'); ?></span></div>
                <div class="flex justify-between items-center"><span class="text-slate-500 text-xs">Date:</span><span class="text-slate-200 text-xs"><?php echo date('d M Y H:i', strtotime($quotation['created_at'])); ?></span></div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-700/50 flex items-center gap-2">
            <i class="fas fa-boxes text-amber-400 text-xs"></i>
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Items</h3>
            <span class="text-xs text-slate-500 ml-auto"><?php echo count($items); ?> item(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-900/50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Product</th>
                        <th class="px-4 py-2.5 text-center text-xs font-medium text-slate-500 uppercase tracking-wider w-20">Qty</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium text-slate-500 uppercase tracking-wider w-28">Price</th>
                        <th class="px-4 py-2.5 text-right text-xs font-medium text-slate-500 uppercase tracking-wider w-32">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php foreach ($items as $item): ?>
                    <tr class="hover:bg-slate-700/20 transition-colors">
                        <td class="px-4 py-2.5 text-sm text-slate-200"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="px-4 py-2.5 text-center text-sm text-slate-300"><?php echo $item['quantity']; ?></td>
                        <td class="px-4 py-2.5 text-right text-sm text-slate-300"><?php echo $currency_symbol . ' ' . number_format($item['price'], 2); ?></td>
                        <td class="px-4 py-2.5 text-right text-sm font-semibold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-900/40 border-t border-slate-700/50">
                    <tr>
                        <td colspan="3" class="px-4 py-2.5 text-right font-semibold text-slate-300 text-sm">Total:</td>
                        <td class="px-4 py-2.5 text-right text-base font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($quotation['total'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Notes & Terms -->
    <?php if ($quotation['notes'] || $quotation['terms']): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php if ($quotation['notes']): ?>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5"><i class="fas fa-sticky-note text-xs"></i> Notes</h3>
            <p class="text-sm text-slate-300 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></p>
        </div>
        <?php endif; ?>
        <?php if ($quotation['terms']): ?>
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5"><i class="fas fa-file-contract text-xs"></i> Terms & Conditions</h3>
            <p class="text-sm text-slate-300 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($quotation['terms'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>