<?php
/**
 * Edit Return - PURE TAILWIND ADVANCED EDITION
 * Update return status and details with approval workflow
 * Consistent 12px font size throughout
 * URL: http://localhost/JDH_POS/public/pos/edit_sell_return.php?id=123
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_login();
enforce_permission('sales.returns');

safe_require('db.php', 'src', true);

$page_title = 'Edit Return';
ob_start();

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$return = null;
$return_items = [];

if (!$return_id) {
    header('Location: list_sell_return.php');
    exit;
}

// Fetch return data
try {
    $pdo = get_db_connection();
    
    $stmt = $pdo->prepare("
        SELECT r.*, s.invoice_number, c.name as customer_name, c.phone as customer_phone,
               u.name as processed_by_name, a.name as approved_by_name
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN admins a ON r.approved_by = a.id
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$return_id, $tenant_id]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) {
        $error = "Return not found";
    } else {
        // Fetch return items
        $stmt = $pdo->prepare("
            SELECT ri.*, p.name as product_name, p.sku
            FROM return_items ri
            JOIN products p ON ri.product_id = p.id
            WHERE ri.return_id = ?
        ");
        $stmt->execute([$return_id]);
        $return_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Process update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Invalid security token";
    } else {
        $status = $_POST['status'] ?? '';
        $approval_notes = trim($_POST['approval_notes'] ?? '');
        
        if (!$status || !in_array($status, ['pending', 'completed', 'rejected', 'voided'])) {
            $error = "Invalid status selected";
        } else {
            try {
                $pdo->beginTransaction();
                
                $old_status = $return['status'];
                
                // Update return
                $stmt = $pdo->prepare("
                    UPDATE returns 
                    SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?), updated_at = NOW(),
                        approved_by = CASE WHEN ? IN ('completed', 'rejected') THEN ? ELSE approved_by END,
                        approved_at = CASE WHEN ? IN ('completed', 'rejected') THEN NOW() ELSE approved_at END
                    WHERE id = ? AND tenant_id = ?
                ");
                $update_notes = "\n[" . date('Y-m-d H:i:s') . "] Status changed from {$old_status} to {$status} by user {$user_id}: {$approval_notes}";
                $stmt->execute([$status, $update_notes, $status, $user_id, $status, $return_id, $tenant_id]);
                
                // If completing, update inventory
                if ($status === 'completed' && $old_status !== 'completed') {
                    foreach ($return_items as $item) {
                        // Check if inventory exists
                        $stmt = $pdo->prepare("
                            SELECT stock FROM inventory 
                            WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                        ");
                        $stmt->execute([$item['product_id'], $branch_id, $tenant_id]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            $stmt = $pdo->prepare("
                                UPDATE inventory 
                                SET stock = stock + ? 
                                WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                            ");
                            $stmt->execute([$item['quantity'], $item['product_id'], $branch_id, $tenant_id]);
                        } else {
                            $stmt = $pdo->prepare("
                            INSERT INTO inventory (product_id, branch_id, stock, tenant_id, created_at, updated_at)
                            VALUES (?, ?, ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([$item['product_id'], $branch_id, $item['quantity'], $tenant_id]);
                        }
                        
                        // Log inventory change
                        $stmt = $pdo->prepare("
                            INSERT INTO inventory_logs (product_id, branch_id, old_stock, new_stock, 
                            change_amount, notes, user_id, tenant_id, created_at)
                            SELECT ?, ?, stock - ?, stock, ?,
                            'Return completed', ?, ?, NOW()
                            FROM inventory 
                            WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                        ");
                        $stmt->execute([
                            $item['product_id'], $branch_id, $item['quantity'], $item['quantity'],
                            $user_id, $tenant_id, $item['product_id'], $branch_id, $tenant_id
                        ]);
                    }
                }
                
                // If rejecting and previously completed, reverse inventory
                if ($status === 'rejected' && $old_status === 'completed') {
                    foreach ($return_items as $item) {
                        $stmt = $pdo->prepare("
                            UPDATE inventory 
                            SET stock = GREATEST(stock - ?, 0)
                            WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                        ");
                        $stmt->execute([$item['quantity'], $item['product_id'], $branch_id, $tenant_id]);
                    }
                }
                
                // Log activity
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (branch_id, user_id, action, description, meta, tenant_id, created_at)
                    VALUES (?, ?, 'return_status_update', 'Updated return status', ?, ?, NOW())
                ");
                $meta = json_encode([
                    'return_id' => $return_id,
                    'return_number' => $return['return_number'],
                    'old_status' => $old_status,
                    'new_status' => $status
                ]);
                $stmt->execute([$branch_id, $user_id, $meta, $tenant_id]);
                
                $pdo->commit();
                
                $_SESSION['success_message'] = "Return #{$return['return_number']} updated to {$status}";
                header("Location: view_sell_return.php?id={$return_id}");
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to update return: " . $e->getMessage();
            }
        }
    }
}

$csrf_token = generate_csrf_token();

$currency_symbol = 'KSh';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([(int)$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency_symbol = $curr;
} catch (Exception $e) {}
?>

<!-- Pure Tailwind CSS - Only animations -->
<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    . { animation: fadeIn 0.3s ease-out; }
    .animate-slide-in { animation: slideIn 0.3s ease-out; }
    
    /* Remove spinner from number inputs */
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .status-badge-pending { background: rgba(245,158,11,0.12); color: #f59e0b; border-color: rgba(245,158,11,0.3); }
    .status-badge-completed { background: rgba(16,185,129,0.12); color: #10b981; border-color: rgba(16,185,129,0.3); }
    .status-badge-rejected { background: rgba(239,68,68,0.12); color: #ef4444; border-color: rgba(239,68,68,0.3); }
    .status-badge-voided { background: rgba(100,116,139,0.12); color: #94a3b8; border-color: rgba(100,116,139,0.3); }
    
    .edit-return-card:hover { transform: translateY(-2px); border-color: #fbbf24; }
</style>

<div class="space-y-4">
    
    <!-- Flash Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="fixed top-20 right-4 z-50 bg-emerald-500/90  text-white px-3 py-1.5 rounded-lg shadow-lg text-xs flex items-center gap-2 animate-slide-in no-print">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
    </div>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
            <div class="flex items-center gap-1.5 text-[10px] font-semibold text-amber-400 uppercase tracking-wider mb-0.5">
                <i class="fas fa-edit text-[10px]"></i>
                <span>Return Management</span>
            </div>
            <h1 class="text-xl font-bold text-white">Edit Return</h1>
            <p class="text-sm text-slate-500 mt-0.5">Update return status and details</p>
        </div>
        <a href="view_sell_return.php?id=<?php echo $return_id; ?>" 
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-[10px]"></i> Back to Details
        </a>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
    <div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-xs">
        <i class="fas fa-exclamation-circle mr-1.5"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($return): ?>
        
        <!-- Return Information Card -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-700/50 bg-slate-800/40">
                <div class="flex items-center gap-1.5">
                    <i class="fas fa-info-circle text-amber-400 text-[10px]"></i>
                    <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Return Information</h3>
                </div>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Return Number</p>
                        <p class="text-xs font-mono text-amber-400 mt-0.5"><?php echo htmlspecialchars($return['return_number']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Invoice</p>
                        <p class="text-xs text-slate-300 mt-0.5"><?php echo htmlspecialchars($return['invoice_number'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Customer</p>
                        <p class="text-xs text-slate-300 mt-0.5"><?php echo htmlspecialchars($return['customer_name'] ?? 'Walk-in'); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Amount</p>
                        <p class="text-xs font-bold text-amber-400 mt-0.5"><?php echo $currency_symbol . ' ' . number_format($return['amount'], 2); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Current Status</p>
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium border status-badge-<?php echo $return['status']; ?> mt-0.5">
                            <?php echo ucfirst($return['status']); ?>
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Processed By</p>
                        <p class="text-xs text-slate-300 mt-0.5"><?php echo htmlspecialchars($return['processed_by_name'] ?? 'System'); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Created At</p>
                        <p class="text-xs text-slate-300 mt-0.5"><?php echo date('d M Y H:i', strtotime($return['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 uppercase tracking-wider">Reason</p>
                        <p class="text-xs text-slate-300 mt-0.5"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $return['reason']))); ?></p>
                    </div>
                </div>
                <?php if ($return['approved_by_name']): ?>
                <div class="mt-3 pt-2 border-t border-slate-700/50 text-xs text-slate-500">
                    <i class="fas fa-check-circle mr-1 text-emerald-400"></i> Approved by <?php echo htmlspecialchars($return['approved_by_name']); ?> on <?php echo date('d M Y H:i', strtotime($return['approved_at'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Return Items Card -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-2.5 border-b border-slate-700/50 bg-slate-800/40 flex justify-between items-center">
                <div class="flex items-center gap-1.5">
                    <i class="fas fa-boxes text-amber-400 text-[10px]"></i>
                    <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Return Items</h3>
                </div>
                <span class="text-xs text-slate-500"><?php echo count($return_items); ?> item(s)</span>
            </div>
            <div class="p-0 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-900/50">
                        <tr>
                            <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Product</th>
                            <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">SKU</th>
                            <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Qty</th>
                            <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Unit Price</th>
                            <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 uppercase tracking-wider">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php foreach ($return_items as $item): ?>
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-4 py-2.5 text-xs text-slate-300"><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="px-4 py-2.5 text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2.5 text-right text-xs text-slate-300"><?php echo $item['quantity']; ?></td>
                            <td class="px-4 py-2.5 text-right text-xs text-slate-300"><?php echo $currency_symbol . ' ' . number_format($item['unit_price'], 2); ?></td>
                            <td class="px-4 py-2.5 text-right text-xs font-semibold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-900/40 border-t-2 border-slate-700/60">
                        <tr>
                            <td colspan="4" class="px-4 py-2.5 text-right font-semibold text-slate-300 text-xs">Total:</td>
                            <td class="px-4 py-2.5 text-right text-sm font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($return['amount'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Update Form Card -->
        <form method="POST" class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="px-4 py-2.5 border-b border-slate-700/50 bg-slate-800/40">
                <div class="flex items-center gap-1.5">
                    <i class="fas fa-tasks text-amber-400 text-[10px]"></i>
                    <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Update Status</h3>
                </div>
            </div>
            
            <div class="p-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Status *</label>
                    <select name="status" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors appearance-none cursor-pointer">
                        <option value="pending" <?php echo $return['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $return['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="rejected" <?php echo $return['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="voided" <?php echo $return['status'] === 'voided' ? 'selected' : ''; ?>>Voided</option>
                    </select>
                    <div class="mt-2 text-xs text-slate-500 space-y-1">
                        <p><i class="fas fa-circle text-[6px] mr-1.5 text-emerald-500"></i><span class="text-emerald-400 font-medium">Completed:</span> Finalizes the return and updates inventory.</p>
                        <p><i class="fas fa-circle text-[6px] mr-1.5 text-red-500"></i><span class="text-red-400 font-medium">Rejected:</span> Rejects the return request.</p>
                        <p><i class="fas fa-circle text-[6px] mr-1.5 text-slate-500"></i><span class="text-slate-400 font-medium">Voided:</span> Cancels the return (soft delete).</p>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-slate-400 mb-1">Approval Notes</label>
                    <textarea name="approval_notes" rows="3" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs focus:outline-none focus:ring-1 focus:ring-amber-500/60 transition-colors placeholder-slate-600 resize-none" 
                              placeholder="Add any notes about this status change..."></textarea>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="border-t border-slate-700/50 px-4 py-3 flex gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-save text-xs"></i> Update Return
                </button>
                <a href="view_sell_return.php?id=<?php echo $return_id; ?>" 
                   class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-300 text-sm font-medium hover:bg-slate-600 text-center transition-colors">
                    <i class="fas fa-times text-xs"></i> Cancel
                </a>
            </div>
        </form>
        
    <?php endif; ?>
</div>

<script>
// Auto-hide flash message after 4 seconds
setTimeout(function() {
    var flash = document.querySelector('.fixed.top-20.right-4');
    if (flash) flash.style.display = 'none';
}, 4000);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>