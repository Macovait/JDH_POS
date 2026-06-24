<?php
/**
 * Purchase Order Items management page for Jakababa POS
 * Manage items within a purchase order with add, edit, delete capabilities.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Check for purchase orders management permission
if (!check_permission('purchase_orders.manage') && !is_super_admin()) {
    enforce_permission('purchase_orders.manage');
}

$pdo        = get_db_connection();
$tenant_id  = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Unauthorized'); }

$user_id            = (int)($_SESSION['user']['id'] ?? 0);
$user_role          = $_SESSION['user']['role'] ?? '';
$current_branch_id  = get_current_branch_id();
$current_branch_name = get_current_branch_name();
$currency_symbol    = get_tenant_currency();

$po_id = intval($_GET['po_id'] ?? 0);
$edit_item_id = intval($_GET['edit'] ?? 0);
$message = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid security token. Please try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            // Add new item to PO
            $po = intval($_POST['purchase_order_id'] ?? 0);
            $product = intval($_POST['product_id'] ?? 0);
            $qty = intval($_POST['quantity'] ?? 1);
            $cost = floatval($_POST['cost_price'] ?? 0);
            
            if ($po > 0 && $product > 0 && $qty > 0 && $cost > 0) {
                try {
                    // Check if PO is still pending — tenant-scoped
                    $checkStmt = $pdo->prepare('SELECT status FROM purchase_orders WHERE id = ? AND tenant_id = ?');
                    $checkStmt->execute([$po, $tenant_id]);
                    $status = $checkStmt->fetchColumn();

                    if ($status !== 'pending') {
                        $error = 'Cannot add items to a PO that is not pending';
                    } else {
                        $pdo->prepare('INSERT INTO purchase_order_items (purchase_order_id, product_id, quantity, cost_price, tenant_id) VALUES (?,?,?,?,?)')
                            ->execute([$po, $product, $qty, $cost, $tenant_id]);

                        // Sync po.total
                        $pdo->prepare('UPDATE purchase_orders SET total=(SELECT COALESCE(SUM(quantity*cost_price),0) FROM purchase_order_items WHERE purchase_order_id=?), updated_at=NOW() WHERE id=? AND tenant_id=?')
                            ->execute([$po, $po, $tenant_id]);

                        log_activity($user_id, 'po_item.added', [
                            'po_id' => $po, 'product_id' => $product, 'quantity' => $qty
                        ], $tenant_id);
                        
                        // Stay on same PO
                        header("Location: purchase_order_items.php?po_id=$po&success=added");
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Error adding PO item: " . $e->getMessage());
                    $error = 'Failed to add item';
                }
            } else {
                $error = 'Please fill in all fields';
            }
            break;
            
        case 'update':
            // Update existing item
            $item_id = intval($_POST['item_id'] ?? 0);
            $po = intval($_POST['purchase_order_id'] ?? 0);
            $product = intval($_POST['product_id'] ?? 0);
            $qty = intval($_POST['quantity'] ?? 1);
            $cost = floatval($_POST['cost_price'] ?? 0);
            
            if ($item_id > 0 && $po > 0 && $product > 0 && $qty > 0 && $cost > 0) {
                try {
                    // Check if PO is still pending — tenant-scoped
                    $checkStmt = $pdo->prepare('SELECT status FROM purchase_orders WHERE id = ? AND tenant_id = ?');
                    $checkStmt->execute([$po, $tenant_id]);
                    $status = $checkStmt->fetchColumn();

                    if ($status !== 'pending') {
                        $error = 'Cannot update items in a PO that is not pending';
                    } else {
                        $pdo->prepare('UPDATE purchase_order_items SET product_id=?, quantity=?, cost_price=?, tenant_id=? WHERE id=? AND tenant_id=?')
                            ->execute([$product, $qty, $cost, $tenant_id, $item_id, $tenant_id]);

                        $pdo->prepare('UPDATE purchase_orders SET total=(SELECT COALESCE(SUM(quantity*cost_price),0) FROM purchase_order_items WHERE purchase_order_id=?), updated_at=NOW() WHERE id=? AND tenant_id=?')
                            ->execute([$po, $po, $tenant_id]);

                        log_activity($user_id, 'po_item.updated', [
                            'po_id' => $po, 'item_id' => $item_id, 'product_id' => $product
                        ], $tenant_id);
                        
                        header("Location: purchase_order_items.php?po_id=$po&success=updated");
                        exit;
                    }
                } catch (PDOException $e) {
                    error_log("Error updating PO item: " . $e->getMessage());
                    $error = 'Failed to update item';
                }
            }
            break;
            
        case 'delete':
            // Delete item
            if (is_super_admin() || $user_role === 'Admin') {
                $item_id = intval($_POST['item_id'] ?? 0);
                $po = intval($_POST['purchase_order_id'] ?? 0);
                
                if ($item_id > 0 && $po > 0) {
                    try {
                        // Check if PO is still pending — tenant-scoped
                        $checkStmt = $pdo->prepare('SELECT status FROM purchase_orders WHERE id = ? AND tenant_id = ?');
                        $checkStmt->execute([$po, $tenant_id]);
                        $status = $checkStmt->fetchColumn();

                        if ($status !== 'pending') {
                            $error = 'Cannot delete items from a PO that is not pending';
                        } else {
                            $pdo->prepare('DELETE FROM purchase_order_items WHERE id = ? AND tenant_id = ?')
                                ->execute([$item_id, $tenant_id]);

                            $pdo->prepare('UPDATE purchase_orders SET total=(SELECT COALESCE(SUM(quantity*cost_price),0) FROM purchase_order_items WHERE purchase_order_id=?), updated_at=NOW() WHERE id=? AND tenant_id=?')
                                ->execute([$po, $po, $tenant_id]);

                            log_activity($user_id, 'po_item.deleted', [
                                'po_id' => $po, 'item_id' => $item_id
                            ], $tenant_id);
                            
                            header("Location: purchase_order_items.php?po_id=$po&success=deleted");
                            exit;
                        }
                    } catch (PDOException $e) {
                        error_log("Error deleting PO item: " . $e->getMessage());
                        $error = 'Failed to delete item';
                    }
                }
            }
            break;
    }
}

// Check for success messages from redirect
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $message = 'Item added successfully';
            break;
        case 'updated':
            $message = 'Item updated successfully';
            break;
        case 'deleted':
            $message = 'Item deleted successfully';
            break;
    }
}

// Get all POs for dropdown — tenant-scoped
$posStmt = $pdo->prepare('
    SELECT po.id, s.name as supplier_name, b.name as branch_name, po.status, po.created_at
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    JOIN branches b ON po.branch_id = b.id
    WHERE po.tenant_id = ?
    ORDER BY po.id DESC
');
$posStmt->execute([$tenant_id]);
$purchase_orders = $posStmt->fetchAll();

// Get all products for dropdown — tenant-scoped
$productsStmt = $pdo->prepare('SELECT id, name, sku, price FROM products WHERE active=1 AND (tenant_id=? OR tenant_id IS NULL) ORDER BY name');
$productsStmt->execute([$tenant_id]);
$products = $productsStmt->fetchAll();

// Get items for selected PO
$items = [];
$selected_po = null;
$total_value = 0;

if ($po_id > 0) {
    // Get PO details
    $poStmt = $pdo->prepare('
        SELECT po.*, s.name as supplier_name, b.name as branch_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        JOIN branches b ON po.branch_id = b.id
        WHERE po.id = ? AND po.tenant_id = ?
    ');
    $poStmt->execute([$po_id, $tenant_id]);
    $selected_po = $poStmt->fetch();
    
    // Get items
    $itemsStmt = $pdo->prepare('
        SELECT 
            i.id,
            i.product_id,
            i.quantity,
            i.cost_price,
            (i.quantity * i.cost_price) as line_total,
            p.name as product_name,
            p.sku
        FROM purchase_order_items i
        JOIN products p ON i.product_id = p.id
        WHERE i.purchase_order_id = ? AND i.tenant_id = ?
        ORDER BY i.id ASC
    ');
        $itemsStmt->execute([$po_id, $tenant_id]);
    $items = $itemsStmt->fetchAll();
    
    // Calculate total
    foreach ($items as $item) {
        $total_value += $item['line_total'];
    }
}

// Get item for editing
$edit_item = null;
if ($edit_item_id > 0 && $po_id > 0) {
    $editStmt = $pdo->prepare('
        SELECT * FROM purchase_order_items WHERE id = ? AND purchase_order_id = ? AND tenant_id = ?
    ');
        $editStmt->execute([$edit_item_id, $po_id, $tenant_id]);
    $edit_item = $editStmt->fetch();
}

$page_title = 'PO Items';
$can_delete = is_super_admin() || $user_role === 'Admin';
$csrf_token = generate_csrf_token();
ob_start();
?>
<?php
// Status badge Tailwind classes (matching all_sales.php style)
$status_tw = [
    'pending'   => 'bg-amber-500/15 text-amber-400 ring-1 ring-amber-500/30',
    'approved'  => 'bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/30',
    'received'  => 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30',
    'cancelled' => 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30',
];
?>

<div class="fade-in">

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">
            <a href="purchase_orders.php" class="hover:text-amber-300 transition-colors">Purchases</a>
            <span class="text-slate-600 mx-1">/</span>Order Items
        </p>
        <h1 class="text-lg font-bold text-white">Purchase Order Items</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manage items for each purchase order</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="purchase_orders.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-xs font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to POs
        </a>
        <?php if ($po_id > 0 && $selected_po && $selected_po['status'] === 'pending'): ?>
        <a href="purchase_receive.php?po_id=<?php echo $po_id; ?>" 
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500 text-black text-xs font-semibold hover:bg-emerald-400 transition-colors">
            <i class="fas fa-arrow-down text-xs"></i> Receive PO
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- PO Selection -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <form method="get" class="flex flex-wrap gap-2 items-end">
        <div class="relative flex-1 min-w-[240px]">
            <i class="fas fa-file-invoice absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <select name="po_id" required
                    class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="">-- Select Purchase Order --</option>
                <?php foreach ($purchase_orders as $po): ?>
                    <option value="<?php echo $po['id']; ?>" <?php echo $po_id == $po['id'] ? 'selected' : ''; ?>>
                        PO #<?php echo str_pad($po['id'], 5, '0', STR_PAD_LEFT); ?> - 
                        <?php echo htmlspecialchars($po['supplier_name']); ?> 
                        (<?php echo ucfirst($po['status']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="px-3 py-2 bg-amber-500 text-black rounded-lg text-sm font-semibold hover:bg-amber-400 transition-colors">
            <i class="fas fa-search mr-1 text-xs"></i>Load Items
        </button>
        
        <?php if ($po_id > 0): ?>
        <a href="purchase_order_items.php" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<?php if ($po_id > 0 && $selected_po): 
    $status_class = $status_tw[$selected_po['status']] ?? 'bg-slate-500/15 text-slate-400 ring-1 ring-slate-500/30';
    $status_icon = $selected_po['status'] === 'pending' ? 'clock' : 
                  ($selected_po['status'] === 'approved' ? 'check-circle' : 
                  ($selected_po['status'] === 'received' ? 'check-double' : 'ban'));
?>
<!-- PO Summary -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                <span class="text-amber-400 font-bold text-sm">#<?php echo str_pad($selected_po['id'], 2, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div>
                <h3 class="text-white font-semibold text-sm"><i class="fas fa-truck mr-1.5 text-amber-400"></i><?php echo htmlspecialchars($selected_po['supplier_name']); ?></h3>
                <p class="text-xs text-slate-500"><i class="fas fa-store-alt mr-1"></i><?php echo htmlspecialchars($selected_po['branch_name']); ?></p>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                <i class="fas fa-<?php echo $status_icon; ?> mr-1"></i>
                <?php echo ucfirst($selected_po['status']); ?>
            </span>
            
            <?php if ($selected_po['status'] === 'pending'): ?>
                <span class="text-xs text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full">
                    <i class="fas fa-pen mr-1"></i>Editable
                </span>
            <?php else: ?>
                <span class="text-xs text-slate-500 bg-slate-700 px-2 py-0.5 rounded-full">
                    <i class="fas fa-eye mr-1"></i>Read Only
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Item Form -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <h2 class="text-sm font-semibold text-white flex items-center gap-2 mb-3">
        <i class="fas fa-<?php echo $edit_item ? 'pen' : 'plus-circle'; ?> text-amber-400"></i>
        <?php echo $edit_item ? 'Edit Item' : 'Add New Item'; ?>
    </h2>
    
    <form method="POST" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="<?php echo $edit_item ? 'update' : 'create'; ?>">
        <input type="hidden" name="purchase_order_id" value="<?php echo $po_id; ?>">
        <?php if ($edit_item): ?>
            <input type="hidden" name="item_id" value="<?php echo $edit_item['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div class="sm:col-span-1">
                <label class="block text-xs text-slate-500 mb-1"><i class="fas fa-cube mr-1"></i>Product *</label>
                <select name="product_id" required 
                        class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                        <?php echo $selected_po['status'] !== 'pending' ? 'disabled' : ''; ?>>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" 
                            <?php echo ($edit_item && $edit_item['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['name']); ?> 
                            <?php echo !empty($product['sku']) ? '(' . htmlspecialchars($product['sku']) . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs text-slate-500 mb-1"><i class="fas fa-hashtag mr-1"></i>Qty *</label>
                <input type="number" name="quantity" required min="1"
                       value="<?php echo $edit_item ? $edit_item['quantity'] : 1; ?>"
                       class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                       <?php echo $selected_po['status'] !== 'pending' ? 'disabled' : ''; ?>>
            </div>
            
            <div>
                <label class="block text-xs text-slate-500 mb-1"><i class="fas fa-coins mr-1"></i>Cost (<?php echo $currency_symbol; ?>) *</label>
                <input type="number" name="cost_price" required step="0.01" min="0"
                       value="<?php echo $edit_item ? $edit_item['cost_price'] : ''; ?>"
                       placeholder="0.00"
                       class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                       <?php echo $selected_po['status'] !== 'pending' ? 'disabled' : ''; ?>>
            </div>
        </div>
        
        <?php if ($selected_po['status'] === 'pending'): ?>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 px-3 py-2 bg-amber-500 text-black rounded-lg text-sm font-semibold hover:bg-amber-400 transition-colors flex items-center justify-center gap-1.5">
                <i class="fas fa-<?php echo $edit_item ? 'check-circle' : 'plus'; ?> text-xs"></i>
                <?php echo $edit_item ? 'Update Item' : 'Add Item'; ?>
            </button>
            
            <?php if ($edit_item): ?>
            <a href="purchase_order_items.php?po_id=<?php echo $po_id; ?>" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors flex items-center gap-1.5">
                <i class="fas fa-times text-xs"></i> Cancel
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="p-3 bg-red-500/10 rounded-lg border border-red-500/30">
            <p class="text-red-400 text-sm flex items-center gap-2">
                <i class="fas fa-info-circle"></i>
                This purchase order is <?php echo $selected_po['status']; ?> and cannot be modified.
            </p>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Items List -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="px-3 py-2 border-b border-slate-700/60 flex justify-between items-center">
        <h2 class="text-sm font-semibold text-white flex items-center gap-2">
            <i class="fas fa-list-ul text-amber-400"></i> Items List
        </h2>
        <span class="text-xs text-slate-500"><i class="fas fa-cubes mr-1"></i><?php echo count($items); ?> items</span>
    </div>
    
    <?php if (empty($items)): ?>
        <div class="p-8 text-center text-slate-500">
            <i class="fas fa-cube text-3xl text-slate-700 block mb-2"></i>
            <p class="text-sm">No items found</p>
            <p class="text-xs mt-1">Add items using the form above</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead>
                    <tr class="border-b border-slate-700/60 bg-slate-800/60">
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">#</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">Product</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase">SKU</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Qty</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Cost</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase">Total</th>
                        <?php if ($selected_po['status'] === 'pending'): ?>
                        <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php foreach ($items as $index => $item): ?>
                        <tr class="hover:bg-slate-700/30 transition-colors">
                            <td class="px-3 py-2 font-mono text-xs text-slate-500"><?php echo $index + 1; ?></td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-cube text-amber-400 text-xs w-4"></i>
                                    <span class="text-sm text-white"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                </div>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-500"><?php echo htmlspecialchars($item['sku'] ?: '-'); ?></td>
                            <td class="px-3 py-2 text-right text-sm text-white font-medium"><?php echo number_format($item['quantity']); ?></td>
                            <td class="px-3 py-2 text-right text-sm text-amber-400"><?php echo $currency_symbol; ?> <?php echo number_format($item['cost_price'], 2); ?></td>
                            <td class="px-3 py-2 text-right text-sm text-emerald-400 font-medium"><?php echo $currency_symbol; ?> <?php echo number_format($item['line_total'], 2); ?></td>
                            
                            <?php if ($selected_po['status'] === 'pending'): ?>
                            <td class="px-3 py-2">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="?po_id=<?php echo $po_id; ?>&edit=<?php echo $item['id']; ?>" 
                                       class="w-6 h-6 flex items-center justify-center rounded bg-slate-700/60 text-amber-400 hover:bg-amber-500/20 transition-colors"
                                       title="Edit item">
                                        <i class="fas fa-pen text-xs"></i>
                                    </a>
                                    <?php if ($can_delete): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this item?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="purchase_order_id" value="<?php echo $po_id; ?>">
                                        <button type="submit" 
                                                class="w-6 h-6 flex items-center justify-center rounded bg-slate-700/60 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors"
                                                title="Delete item">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-700/60 bg-slate-800/60">
                        <td colspan="<?php echo $selected_po['status'] === 'pending' ? '5' : '4'; ?>" class="px-3 py-2 text-right text-xs text-slate-500 font-medium">Grand Total:</td>
                        <td class="px-3 py-2 text-right text-emerald-400 font-bold"><?php echo $currency_symbol; ?> <?php echo number_format($total_value, 2); ?></td>
                        <?php if ($selected_po['status'] === 'pending'): ?>
                        <td></td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Table Footer -->
        <div class="px-3 py-2 border-t border-slate-700/60 bg-slate-800/40 flex justify-between items-center text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <i class="fas fa-cube text-xs"></i>
                <span>Total: <span class="text-white font-medium"><?php echo count($items); ?></span></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 bg-amber-400 rounded-full"></span> Cost
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full"></span> Total
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.target.matches('input, textarea, select')) return;

        <?php if ($po_id > 0 && $selected_po && $selected_po['status'] === 'pending'): ?>
        // Alt + N - Focus product select
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            document.querySelector('select[name="product_id"]')?.focus();
        }
        <?php endif; ?>

        // Alt + D - Back to POs
        if (e.altKey && e.key === 'd') {
            e.preventDefault();
            window.location.href = 'purchase_orders.php';
        }
    });
</script>

</div><!-- /.fade-in -->

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
