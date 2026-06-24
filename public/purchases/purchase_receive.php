<?php
/**
 * Purchase Receive page for Jakababa POS
 * Process receiving of purchase orders and update inventory.
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
$message = '';
$error = '';

// Handle receive submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receive'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid security token. Please try again.';
  } else {
    $po_id = intval($_POST['po_id'] ?? 0);

    if ($po_id > 0) {
        $pdo->beginTransaction();

        try {
            // Lock the PO to prevent concurrent processing
            $poStmt = $pdo->prepare('SELECT id, status, branch_id, supplier_id FROM purchase_orders WHERE id = ? AND tenant_id = ? FOR UPDATE');
            $poStmt->execute([$po_id, $tenant_id]);
            $po = $poStmt->fetch();

            if (!$po) {
                throw new Exception('Purchase order not found');
            }

            if ($po['status'] !== 'pending' && $po['status'] !== 'approved') {
                throw new Exception('Purchase order cannot be received (current status: ' . $po['status'] . ')');
            }

            // Get items to receive
            $itemsStmt = $pdo->prepare('
                SELECT id, product_id, quantity, cost_price
                FROM purchase_order_items
                WHERE purchase_order_id = ? AND tenant_id = ?
            ');
            $itemsStmt->execute([$po_id, $tenant_id]);
            $items = $itemsStmt->fetchAll();

            if (empty($items)) {
                throw new Exception('No items to receive');
            }

            // Process each item
            foreach ($items as $item) {
                // Check if inventory record exists (inventory uses composite PK: product_id + branch_id)
                $checkStmt = $pdo->prepare('SELECT 1 FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?');
                $checkStmt->execute([$item['product_id'], $po['branch_id'], $tenant_id]);

                if ($checkStmt->fetch()) {
                    // Update existing inventory
                    $updateStmt = $pdo->prepare('
                        UPDATE inventory 
                        SET stock = stock + ?,
                            updated_at = NOW()
                        WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                    ');
                    $updateStmt->execute([$item['quantity'], $item['product_id'], $po['branch_id'], $tenant_id]);
                } else {
                    // Create new inventory record
                    $insertStmt = $pdo->prepare('
                        INSERT INTO inventory (tenant_id, product_id, branch_id, stock, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ');
                    $insertStmt->execute([$tenant_id, $item['product_id'], $po['branch_id'], $item['quantity']]);
                }

                // Log inventory change
                $logStmt = $pdo->prepare('
                    INSERT INTO inventory_logs (tenant_id, product_id, branch_id, change_amount, notes, user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ');
                $logStmt->execute([
                    $tenant_id,
                    $item['product_id'],
                    $po['branch_id'],
                    $item['quantity'],
                    "Received from PO #$po_id",
                    $user_id
                ]);

                // Mark item as fully received
                $pdo->prepare('UPDATE purchase_order_items SET received_quantity = quantity WHERE id = ? AND tenant_id = ?')
                    ->execute([$item['id'], $tenant_id]);

                log_activity($user_id, 'inventory.receive', [
                    'po_id' => $po_id, 'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'branch_id' => $po['branch_id']
                ], $tenant_id);
            }

            // Update PO status and sync total
            $pdo->prepare('UPDATE purchase_orders SET status = "received", updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                ->execute([$po_id, $tenant_id]);

            log_activity($user_id, 'po.receive', [
                'po_id' => $po_id, 'supplier_id' => $po['supplier_id'],
                'branch_id' => $po['branch_id'],
                'item_count' => count($items)
            ], $tenant_id);

            $pdo->commit();
            $message = 'Purchase order received successfully. Inventory has been updated.';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Receive failed: ' . $e->getMessage();
            error_log("PO Receive Error: " . $e->getMessage());
        }
    }
  } // end csrf-valid else
}

// Get all pending/approved POs for selection — tenant-scoped
$posStmt = $pdo->prepare('
    SELECT
        po.id, po.status, po.expected_date, po.created_at, po.notes,
        s.name as supplier_name, b.name as branch_name, b.id as branch_id,
        (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id = po.id) as item_count,
        (SELECT COALESCE(SUM(quantity), 0) FROM purchase_order_items WHERE purchase_order_id = po.id) as total_quantity,
        (SELECT COALESCE(SUM(quantity * cost_price), 0) FROM purchase_order_items WHERE purchase_order_id = po.id) as total_value
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    JOIN branches b ON po.branch_id = b.id
    WHERE po.tenant_id = ? AND po.status IN ("pending", "approved")
    ORDER BY po.expected_date ASC, po.id DESC
');
$posStmt->execute([$tenant_id]);
$pending_pos = $posStmt->fetchAll();

// Get selected PO items
$current_items = [];
$selected_po = null;

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

    // Get items with received_quantity
    $itemsStmt = $pdo->prepare('
        SELECT i.id, i.product_id, p.name as product_name, p.sku,
            i.quantity, i.received_quantity, i.cost_price,
            (i.quantity * i.cost_price) as line_total
        FROM purchase_order_items i
        JOIN products p ON i.product_id = p.id
        WHERE i.purchase_order_id = ? AND i.tenant_id = ?
        ORDER BY i.id ASC
    ');
    $itemsStmt->execute([$po_id, $tenant_id]);
    $current_items = $itemsStmt->fetchAll();
}

$page_title = 'Receive Purchase Order';
$current_year = date('Y');
$csrf_token = generate_csrf_token();
ob_start();
?>

<style>
    .gradient-text {
        background: linear-gradient(135deg, #FBBF24 0%, #F59E0B 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Table row hover */
    .table-row-hover:hover {
        background: rgba(251, 191, 36, 0.05);
    }

    /* Role badge */
    .role-badge {
        background: rgba(251, 191, 36, 0.1);
        border: 1px solid rgba(251, 191, 36, 0.3);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        color: #FBBF24;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    /* Status badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .status-pending {
        background: rgba(251, 191, 36, 0.1);
        color: #FBBF24;
        border: 1px solid rgba(251, 191, 36, 0.2);
    }

    .status-approved {
        background: rgba(59, 130, 246, 0.1);
        color: #3B82F6;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .status-received {
        background: rgba(16, 185, 129, 0.1);
        color: #10B981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    /* Form input */
    .form-input {
        background: #111827;
        border: 1px solid #374151;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        color: white;
        width: 100%;
        transition: all 0.2s;
    }

    .form-input:focus {
        border-color: #FBBF24;
        box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
        outline: none;
    }

    .form-input::placeholder {
        color: #6B7280;
    }

    select.form-input option {
        background: #1F2937;
        color: white;
    }

    /* Toast */
    .toast-container {
        position: fixed;
        bottom: 1rem;
        right: 1rem;
        z-index: 9999;
    }

    .toast {
        background: rgba(31, 41, 55, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid #374151;
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        color: white;
        margin-bottom: 0.5rem;
        animation: slideIn 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .toast.success {
        border-left: 4px solid #10B981;
    }

    .toast.error {
        border-left: 4px solid #EF4444;
    }

    .toast.info {
        border-left: 4px solid #FBBF24;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Modal */
    .modal {
        transition: all 0.3s ease;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(10px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background: rgba(31, 41, 55, 0.95);
        border: 1px solid #374151;
        border-radius: 1rem;
        padding: 1.5rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    /* Connection indicator */
    .connection-indicator {
        position: fixed;
        bottom: 1rem;
        left: 1rem;
        background: rgba(31, 41, 55, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid #374151;
        border-radius: 9999px;
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 40;
        color: #f3f4f6;
    }
</style>

<div class="fade-in">

    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">
                <a href="purchase_orders.php" class="hover:text-amber-300 transition-colors">Purchases</a>
                <span class="text-slate-600 mx-1">/</span>Receive
            </p>
            <h1 class="text-lg font-bold text-white">Receive Purchase Order</h1>
            <p class="text-xs text-slate-500 mt-0.5">Process incoming shipments and update inventory</p>
        </div>
        <div class="flex gap-2 shrink-0">
            <?php if ($po_id > 0): ?>
            <a href="purchase_order_items.php?po_id=<?php echo $po_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-xs font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-arrow-left text-xs"></i><span>Back to Items</span>
            </a>
            <?php else: ?>
            <a href="purchase_orders.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-xs font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-arrow-left text-xs"></i><span>Back to POs</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="mb-6 bg-[#10B981]/10 border border-[#10B981] rounded-xl p-4 ">
            <div class="flex items-center gap-3 text-[#10B981]">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-6 bg-[#EF4444]/10 border border-[#EF4444] rounded-xl p-4 ">
            <div class="flex items-start gap-3 text-[#EF4444]">
                <i class="fas fa-exclamation-circle mt-0.5"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- PO Selection -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-8 ">
        <h2 class="text-lg font-semibold text-white flex items-center gap-2 mb-4">
            <i class="fas fa-file-invoice text-[#FBBF24]"></i>
            Select Purchase Order
        </h2>

        <form method="get" class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <select name="po_id" class="form-input" required>
                    <option value="">-- Select a Purchase Order --</option>
                    <?php foreach ($pending_pos as $po): ?>
                        <option value="<?php echo $po['id']; ?>" <?php echo $po_id == $po['id'] ? 'selected' : ''; ?>>
                            PO #<?php echo str_pad($po['id'], 5, '0', STR_PAD_LEFT); ?> -
                            <?php echo htmlspecialchars($po['supplier_name']); ?>
                            (<?php echo $po['item_count']; ?> items,
                            <?php echo ucfirst($po['status']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit"
                class="px-6 py-3 bg-[#FBBF24] text-[#1E3A8A] rounded-xl font-semibold hover:bg-[#F59E0B] transition-colors flex items-center justify-center gap-2">
                <i class="fas fa-search"></i>
                Load Purchase Order
            </button>

            <?php if ($po_id > 0): ?>
                <a href="purchase_receive.php"
                    class="px-4 py-3 bg-[#1F2937] border border-[#374151] rounded-xl text-[#9CA3AF] hover:text-white hover:border-[#FBBF24] transition-colors flex items-center gap-2">
                    <i class="fas fa-times"></i>
                    <span>Clear</span>
                </a>
            <?php endif; ?>
        </form>

        <?php if (empty($pending_pos)): ?>
            <div class="mt-4 p-4 bg-[#FBBF24]/5 rounded-xl border border-[#FBBF24]/20">
                <p class="text-sm text-[#FBBF24] flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    No pending or approved purchase orders available for receiving.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- PO Details and Items -->
    <?php if ($po_id > 0 && $selected_po): ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden ">
            <!-- PO Header -->
            <div class="p-6 border-b border-[#374151] bg-[#111827]/50">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                            <i class="fas fa-file-invoice text-[#FBBF24]"></i>
                            PO #<?php echo str_pad($selected_po['id'], 5, '0', STR_PAD_LEFT); ?>
                        </h2>
                        <p class="text-[#9CA3AF] mt-1"><i class="fas fa-calendar-alt mr-1"></i>Created:
                            <?php echo date('M j, Y H:i', strtotime($selected_po['created_at'])); ?>
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <?php
                        $status_class = 'status-' . $selected_po['status'];
                        $status_icon = $selected_po['status'] === 'pending' ? 'clock' :
                            ($selected_po['status'] === 'approved' ? 'check-circle' : 'check-double');
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <i class="fas fa-<?php echo $status_icon; ?>"></i>
                            <?php echo ucfirst($selected_po['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-truck text-[#FBBF24] w-4"></i>
                        <span class="text-sm text-[#9CA3AF]">Supplier:</span>
                        <span
                            class="text-sm text-white"><?php echo htmlspecialchars($selected_po['supplier_name']); ?></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <i class="fas fa-store-alt text-[#FBBF24] w-4"></i>
                        <span class="text-sm text-[#9CA3AF]">Branch:</span>
                        <span
                            class="text-sm text-white"><?php echo htmlspecialchars($selected_po['branch_name']); ?></span>
                    </div>

                    <?php if ($selected_po['expected_date']): ?>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-[#FBBF24] w-4"></i>
                            <span class="text-sm text-[#9CA3AF]">Expected:</span>
                            <span
                                class="text-sm text-white"><?php echo date('M j, Y', strtotime($selected_po['expected_date'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($selected_po['notes'])): ?>
                    <div class="mt-4 p-3 bg-[#1F2937] rounded-xl border border-[#374151]">
                        <p class="text-sm text-[#9CA3AF]"><i
                                class="fas fa-file-alt mr-1"></i><?php echo nl2br(htmlspecialchars($selected_po['notes'])); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Items Table -->
            <div class="p-6">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-list-ul text-[#FBBF24]"></i>
                    Items to Receive
                </h3>

                <?php if (empty($current_items)): ?>
                    <div class="text-center py-8 text-[#9CA3AF]">
                        <i class="fas fa-cube text-5xl mb-3 text-[#6B7280]"></i>
                        <p>No items found in this purchase order.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-[#111827] border-b border-[#374151]">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-[#9CA3AF] uppercase"><i
                                            class="fas fa-cube mr-1"></i>Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-[#9CA3AF] uppercase"><i
                                            class="fas fa-barcode mr-1"></i>SKU</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-[#9CA3AF] uppercase"><i
                                            class="fas fa-hashtag mr-1"></i>Quantity</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-[#9CA3AF] uppercase"><i
                                            class="fas fa-coins mr-1"></i>Unit Cost</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-[#9CA3AF] uppercase"><i
                                            class="fas fa-calculator mr-1"></i>Line Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#374151]">
                                <?php
                                $grand_total = 0;
                                foreach ($current_items as $item):
                                    $grand_total += $item['line_total'];
                                    ?>
                                    <tr class="table-row-hover">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-cube text-[#FBBF24] w-4"></i>
                                                <span
                                                    class="text-white"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-[#9CA3AF]">
                                            <?php echo htmlspecialchars($item['sku'] ?: '-'); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-white">
                                            <?php echo number_format($item['quantity']); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right text-[#FBBF24]"><?php echo $currency_symbol; ?>
                                            <?php echo number_format($item['cost_price'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 text-right text-[#10B981] font-semibold">
                                            <?php echo $currency_symbol; ?>
                                            <?php echo number_format($item['line_total'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-[#111827] border-t border-[#374151]">
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-right text-[#9CA3AF] font-semibold">Grand Total:
                                    </td>
                                    <td class="px-4 py-4 text-right text-[#10B981] font-bold text-lg">
                                        <?php echo $currency_symbol; ?>
                                        <?php echo number_format($grand_total, 2); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Receive Button with Confirmation -->
                    <div class="mt-6 flex justify-end">
                        <button onclick="openConfirmModal()"
                            class="px-6 py-3 bg-[#10B981] text-white rounded-xl font-semibold hover:bg-[#059669] transition-all hover:scale-105 flex items-center gap-2">
                            <i class="fas fa-arrow-down"></i>
                            Receive & Update Inventory
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Info -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-[#FBBF24]/10 rounded-lg">
                    <i class="fas fa-shield-alt text-[#FBBF24]"></i>
                </div>
                <div>
                    <h3 class="text-white font-semibold mb-1">Receiving Process</h3>
                    <p class="text-sm text-[#9CA3AF]">
                        When you receive items, inventory will be automatically updated for the selected branch.
                        This action cannot be undone.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-[#FBBF24]/10 rounded-lg">
                    <i class="fas fa-clock text-[#FBBF24]"></i>
                </div>
                <div>
                    <h3 class="text-white font-semibold mb-1">Transaction Safety</h3>
                    <p class="text-sm text-[#9CA3AF]">
                        All operations are wrapped in database transactions. If any error occurs,
                        the entire receive operation will be rolled back.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center gap-3 text-[#EF4444] mb-4">
            <i class="fas fa-exclamation-triangle text-2xl"></i>
            <h3 class="text-xl font-semibold text-white">Confirm Receiving</h3>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
            <input type="hidden" name="confirm_receive" value="1">

            <p class="text-[#9CA3AF] mb-6">
                Are you sure you want to receive this purchase order? This will:
            </p>

            <ul class="text-sm text-[#f3f4f6] mb-6 space-y-2 list-disc list-inside">
                <li><i class="fas fa-plus-circle text-[#10B981] mr-2"></i>Add <?php echo count($current_items); ?>
                    items to inventory</li>
                <li><i class="fas fa-boxes text-[#FBBF24] mr-2"></i>Update stock levels for the branch</li>
                <li><i class="fas fa-check-double text-[#3B82F6] mr-2"></i>Mark the PO as "received"</li>
                <li><i class="fas fa-ban text-[#EF4444] mr-2"></i>This action cannot be undone</li>
            </ul>

            <div class="flex gap-3">
                <button type="submit"
                    class="flex-1 px-4 py-3 bg-[#10B981] text-white rounded-xl font-semibold hover:bg-[#059669] transition-colors">
                    <i class="fas fa-check mr-2"></i>Yes, Receive Items
                </button>
                <button type="button" onclick="closeConfirmModal()"
                    class="flex-1 px-4 py-3 bg-[#1F2937] border border-[#374151] text-white rounded-xl font-semibold hover:border-[#FBBF24] transition-colors">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Connection Status -->
<div id="connection-status" class="connection-indicator">
    <div class="w-2 h-2 bg-[#10B981] rounded-full animate-pulse"></div>
    <span class="text-[#10B981]"><i class="fas fa-wifi mr-1"></i>Online</span>
</div>

<script>
    // Modal Functions
    function openConfirmModal() {
        document.getElementById('confirmModal').classList.add('show');
    }

    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('show');
    }

    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });

    // Toast notification function
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');

        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas ${icons[type]}"></i>
            <span>${message}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) {
            return;
        }

        // Alt + R - Receive (if items loaded)
        <?php if (!empty($current_items)): ?>
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                openConfirmModal();
            }
        <?php endif; ?>

        // Alt + D - Back to Dashboard
        if (e.altKey && e.key === 'd') {
            e.preventDefault();
            window.location.href = 'index.php';
        }

        // Esc - Close modal
        if (e.key === 'Escape') {
            closeConfirmModal();
        }
    });

    // Connection status
    function updateOnlineStatus() {
        const statusEl = document.getElementById('connection-status');
        if (statusEl) {
            if (navigator.onLine) {
                statusEl.innerHTML = '<div class="w-2 h-2 bg-[#10B981] rounded-full animate-pulse"></div><span class="text-[#10B981]"><i class="fas fa-wifi mr-1"></i>Online</span>';
            } else {
                statusEl.innerHTML = '<div class="w-2 h-2 bg-[#EF4444] rounded-full animate-pulse"></div><span class="text-[#EF4444]"><i class="fas fa-wifi-slash mr-1"></i>Offline</span>';
            }
        }
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();

    // Auto-hide success message after 5 seconds
    document.addEventListener('DOMContentLoaded', function () {
        const successMsg = document.querySelector('.bg-\\[\\#10B981\\]\\/10');
        if (successMsg) {
            setTimeout(() => {
                successMsg.style.transition = 'opacity 0.5s ease';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }, 5000);
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
