<?php
/**
 * Process Bulk Return - Return Management System
 * Bulk return interface for multiple sales
 */

header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

if (!check_permission('sales.returns') && !is_super_admin()) {
    enforce_permission('sales.returns');
}

safe_require('db.php', 'src', true);

$page_title = 'Bulk Return';
ob_start();

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

$currency_symbol = 'KSh';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([(int)$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency_symbol = $curr;
} catch (Exception $e) {}

$sale_ids_param = $_GET['sale_ids'] ?? '';
$sale_ids = array_filter(array_map('intval', explode(',', $sale_ids_param)));
$error = $_GET['error'] ?? '';

$return_reasons = ['Defective', 'Wrong Item', 'Customer Changed Mind', 'Size Issue', 'Quality Issue', 'Other'];

if (empty($sale_ids)) {
    $error = 'No sales selected for bulk return';
}

$sales = [];
$all_items = [];
$sale_data = [];

if (!empty($sale_ids)) {
    try {
        $pdo = get_db_connection();
        $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT id, invoice_number, created_at, customer_id, total, payment_method
            FROM sales
            WHERE id IN ($placeholders) AND tenant_id = ? AND branch_id = ?
        ");
        
        $params = $sale_ids;
        $params[] = $tenant_id;
        $params[] = $branch_id;
        $stmt->execute($params);
        $sales = $stmt->fetchAll();
        
        foreach ($sales as $sale) {
            $stmt = $pdo->prepare("
                SELECT si.id, si.product_id, si.product_name, si.quantity, si.unit_price,
                       COALESCE(SUM(ri.quantity), 0) as returned_qty
                FROM sale_items si
                LEFT JOIN return_items ri ON si.id = ri.sale_item_id
                WHERE si.sale_id = ?
                GROUP BY si.id
            ");
            $stmt->execute([$sale['id']]);
            $items = $stmt->fetchAll();
            $all_items[$sale['id']] = $items;
            
            $stmt = $pdo->prepare("SELECT name, phone FROM customers WHERE id = ? LIMIT 1");
            $stmt->execute([$sale['customer_id']]);
            $sale['customer'] = $stmt->fetch() ?: null;
            $sale_data[$sale['id']] = $sale;
        }
    } catch (Exception $e) {
        error_log('Error loading sales for bulk return: ' . $e->getMessage());
        $error = 'Error loading sales data';
    }
}
?>

<style>
    .expand-row { display: none; }
    .expand-row.open { display: block; }
    .expand-toggle { cursor: pointer; transition: transform .2s; display: inline-block; }
    .expand-toggle.rotated { transform: rotate(180deg); }
    .loading-spinner { width: 24px; height: 24px; border: 2px solid rgba(251,191,36,0.2); border-top-color: #fbbf24; border-radius: 50%; animation: spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .item-returned { opacity: 0.5; }
    .item-returned input { cursor: not-allowed; background: rgba(0,0,0,0.2); }
</style>

<div class="fade-in">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 mb-6">
        <div>
            <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Returns Management</div>
            <h1 class="text-lg font-bold text-white">Bulk Return</h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="select_sale_for_return.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors text-sm"><i class="fas fa-arrow-left mr-1"></i> Back</a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if (empty($sales)): ?>
    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm">
        <i class="fas fa-exclamation-triangle"></i> Please select at least one sale from the sales list
    </div>
    <?php else: ?>

    <!-- Sales Summary Cards -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-4">
        <h3 class="text-sm font-semibold text-amber-400 uppercase mb-3"><i class="fas fa-receipt mr-2"></i> Selected Sales (<?php echo count($sales); ?>)</h3>
        <div class="space-y-3">
            <?php foreach ($sales as $sale): ?>
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden p-3">
                <div class="flex flex-wrap gap-4">
                    <div>
                        <span class="text-gray-400 text-xs">Invoice:</span>
                        <span class="font-mono text-white ml-2"><?php echo htmlspecialchars($sale['invoice_number']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Date:</span>
                        <span class="text-white ml-2"><?php echo date('M d, Y', strtotime($sale['created_at'])); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Customer:</span>
                        <span class="text-white ml-2"><?php echo htmlspecialchars($sale['customer']['name'] ?? 'Walk-in'); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs">Total:</span>
                        <span class="font-mono text-amber-400 ml-2"><?php echo $currency_symbol . ' ' . number_format($sale['total'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Reason Card -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-4">
        <h3 class="text-sm font-semibold text-amber-400 uppercase mb-3"><i class="fas fa-question-circle mr-2"></i> Return Reason</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label for="bulk-return-reason" class="text-xs text-slate-400 font-medium mb-1 block">Reason (Applies to all)</label>
                <select id="bulk-return-reason" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 w-full">
                    <option value="">Select a reason...</option>
                    <?php foreach ($return_reasons as $reason): ?>
                    <option value="<?php echo $reason; ?>"><?php echo $reason; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="bulk-return-notes" class="text-xs text-slate-400 font-medium mb-1 block">Notes (Optional)</label>
                <textarea id="bulk-return-notes" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 w-full" placeholder="Add notes for all returns..." rows="2"></textarea>
            </div>
        </div>
    </div>

    <!-- Items for Each Sale -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-4">
        <h3 class="text-sm font-semibold text-amber-400 uppercase mb-3"><i class="fas fa-boxes mr-2"></i> Select Items to Return</h3>
        
        <?php foreach ($sales as $sale): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-3">
            <div class="flex justify-between items-center mb-3">
                <h4 class="text-white font-medium">
                    <i class="fas fa-receipt text-amber-400 mr-2"></i>
                    <?php echo htmlspecialchars($sale['invoice_number']); ?> - Items
                </h4>
            </div>
            <div class="overflow-x-auto">
                <table class="wp-list-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Sold</th>
                            <th class="text-center">Available</th>
                            <th class="text-right">Price</th>
                            <th class="text-center">Return Qty</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_items[$sale['id']] as $item): 
                            $available = $item['quantity'] - $item['returned_qty'];
                            $isFullyReturned = $available <= 0;
                        ?>
                        <tr class="<?php echo $isFullyReturned ? 'item-returned' : ''; ?>">
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-center <?php echo $isFullyReturned ? 'text-red-400' : 'text-green-400'; ?>">
                                <?php echo $isFullyReturned ? '0 (fully returned)' : $available; ?>
                            </td>
                            <td class="text-right font-mono"><?php echo $currency_symbol . ' ' . number_format($item['unit_price'], 2); ?></td>
                            <td class="text-center">
                                <?php if ($isFullyReturned): ?>
                                <span class="text-gray-500 text-xs">(fully returned)</span>
                                <?php else: ?>
                                <input type="number" class="bulk-return-qty w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 w-20 text-center" 
                                       data-sale-id="<?php echo $sale['id']; ?>" 
                                       data-item-id="<?php echo $item['id']; ?>"
                                       data-unit-price="<?php echo $item['unit_price']; ?>"
                                       min="0" max="<?php echo $available; ?>" value="0">
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-mono">0.00</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Summary -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-4">
        <h3 class="text-sm font-semibold text-amber-400 uppercase mb-3"><i class="fas fa-calculator mr-2"></i> Return Summary</h3>
        <div class="flex justify-between items-center">
            <span class="text-lg text-white">Total Return Amount:</span>
            <span class="text-2xl font-bold text-amber-400 font-mono" id="bulk-total-amount">0.00</span>
        </div>
    </div>

    <!-- Submit Form -->
    <form method="POST" action="submit_bulk_return.php" id="bulk-return-form" class="hidden">
        <input type="hidden" name="sale_ids" value="<?php echo htmlspecialchars($sale_ids_param); ?>">
        <input type="hidden" name="return_items" id="form-return-items" value="">
        <input type="hidden" name="total_amount" id="form-total-amount" value="0">
        <input type="hidden" name="reason" id="form-reason" value="">
        <input type="hidden" name="notes" id="form-notes" value="">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? csrf_token(); ?>">
    </form>

    <div class="flex items-center gap-2 shrink-0 justify-end">
        <button type="button" id="bulk-submit-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors text-sm" onclick="submitBulkReturn()" disabled>
            <i class="fas fa-check mr-1"></i> Submit Bulk Return
        </button>
    </div>

    <?php endif; ?>
</div>

<script>
const CURRENCY = '<?php echo $currency_symbol; ?>';
const SALE_IDS = <?php echo json_encode($sale_ids); ?>;
const BRANCH_ID = <?php echo (int)$branch_id; ?>;
const TENANT_ID = <?php echo (int)$tenant_id; ?>;

function updateTotals() {
    let total = 0;
    document.querySelectorAll('.bulk-return-qty').forEach(function(input) {
        const qty = parseFloat(input.value) || 0;
        const price = parseFloat(input.dataset.unitPrice) || 0;
        const subtotal = qty * price;
        total += subtotal;
        
        const row = input.closest('tr');
        const subtotalCell = row.querySelector('td:last-child');
        if (subtotalCell && qty > 0) {
            subtotalCell.innerHTML = '<span class="font-mono text-amber-400">' + CURRENCY + ' ' + formatNumber(subtotal) + '</span>';
        }
    });
    
    document.getElementById('bulk-total-amount').textContent = CURRENCY + ' ' + formatNumber(total);
    document.getElementById('form-total-amount').value = total;
    
    validateForm();
}

function validateForm() {
    const hasItems = Array.from(document.querySelectorAll('.bulk-return-qty')).some(function(i) { return parseFloat(i.value) > 0; });
    const reason = document.getElementById('bulk-return-reason').value;
    const submitBtn = document.getElementById('bulk-submit-btn');
    
    submitBtn.disabled = !(hasItems && reason);
}

document.querySelectorAll('.bulk-return-qty').forEach(function(input) {
    input.addEventListener('input', updateTotals);
});

document.getElementById('bulk-return-reason').addEventListener('change', validateForm);

function submitBulkReturn() {
    const returnItems = {};
    
    document.querySelectorAll('.bulk-return-qty').forEach(function(input) {
        const qty = parseFloat(input.value) || 0;
        if (qty > 0) {
            const saleId = input.dataset.saleId;
            const itemId = input.dataset.itemId;
            if (!returnItems[saleId]) returnItems[saleId] = {};
            returnItems[saleId][itemId] = qty;
        }
    });
    
    if (Object.keys(returnItems).length === 0) {
        showToast('Please select at least one item to return', 'error');
        return;
    }
    
    if (!document.getElementById('bulk-return-reason').value) {
        showToast('Please select a return reason', 'error');
        return;
    }
    
    document.getElementById('form-return-items').value = JSON.stringify(returnItems);
    document.getElementById('form-reason').value = document.getElementById('bulk-return-reason').value;
    document.getElementById('form-notes').value = document.getElementById('bulk-return-notes').value;
    
    document.getElementById('bulk-return-form').submit();
}

function formatNumber(num) {
    return parseFloat(num || 0).toFixed(2);
}

function showToast(message, type) {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
require_once __DIR__ . '/../../layouts/app_close.php';
?>