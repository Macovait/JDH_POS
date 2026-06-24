<?php
/**
 * View Return - PURE TAILWIND ADVANCED EDITION
 * Fits within app.php layout - No standalone HTML
 * Consistent 12px font size throughout
 * URL: http://localhost/JDH_POS/public/pos/view_sell_return.php?id=123
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

$page_title = 'Return Details';
ob_start();

$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id();

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$return_id) {
    header('Location: list_sell_return.php');
    exit;
}

$error = '';
$return = null;
$return_items = [];

// Handle POST actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Invalid security token";
    } else {
        $action = $_POST['action'] ?? '';
        $approval_notes = trim($_POST['approval_notes'] ?? '');
        
        try {
            $pdo = get_db_connection();
            $pdo->beginTransaction();
            
            $new_status = $action === 'approve' ? 'completed' : ($action === 'reject' ? 'rejected' : '');
            
            if ($new_status) {
                // Update return status
                $stmt = $pdo->prepare("
                    UPDATE returns 
                    SET status = ?, approved_by = ?, approved_at = NOW(), 
                        notes = CONCAT(IFNULL(notes, ''), ?)
                    WHERE id = ? AND tenant_id = ?
                ");
                $update_notes = "\n[" . date('Y-m-d H:i:s') . "] {$new_status} by user {$user_id}: {$approval_notes}";
                $stmt->execute([$new_status, $user_id, $update_notes, $return_id, $tenant_id]);
                
                // If approving, update inventory
                if ($new_status === 'completed') {
                    $stmt = $pdo->prepare("SELECT ri.product_id, ri.quantity FROM return_items ri WHERE ri.return_id = ? AND ri.tenant_id = ?");
                    $stmt->execute([$return_id, $tenant_id]);
                    $items = $stmt->fetchAll();
                    
                    foreach ($items as $item) {
                        $stmt = $pdo->prepare("
                            UPDATE inventory 
                            SET stock = stock + ? 
                            WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                        ");
                        $stmt->execute([$item['quantity'], $item['product_id'], $branch_id, $tenant_id]);
                    }
                }
                
                // Log activity
                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (branch_id, user_id, action, description, meta, tenant_id, created_at)
                    VALUES (?, ?, 'return_updated', 'Return status updated', ?, ?, NOW())
                ");
                $meta = json_encode(['return_id' => $return_id, 'new_status' => $new_status]);
                $stmt->execute([$branch_id, $user_id, $meta, $tenant_id]);
                
                $pdo->commit();
                $_SESSION['success_message'] = "Return has been {$new_status}";
                header("Location: view_sell_return.php?id={$return_id}");
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update return: " . $e->getMessage();
        }
    }
}

$csrf_token = generate_csrf_token();

// Fetch return data
try {
    $pdo = get_db_connection();
    
    $stmt = $pdo->prepare("
        SELECT r.*, s.invoice_number, s.total as sale_total, s.created_at as sale_date,
               c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               u.name as processed_by_name, a.name as approved_by_name,
               cashier.name as cashier_name
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN admins a ON r.approved_by = a.id
        LEFT JOIN users cashier ON s.user_id = cashier.id
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    $stmt->execute([$return_id, $tenant_id]);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$return) {
        $error = "Return not found";
    } else {
        $stmt = $pdo->prepare("
            SELECT ri.*, p.name as product_name, p.sku
            FROM return_items ri
            JOIN products p ON ri.product_id = p.id AND p.tenant_id = ri.tenant_id
            WHERE ri.return_id = ? AND ri.tenant_id = ?
        ");
        $stmt->execute([$return_id, $tenant_id]);
        $return_items = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$currency_symbol = 'KSh';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([(int)$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency_symbol = $curr;
} catch (Exception $e) {}
?>

<!-- Additional Tailwind utilities (only animations) -->
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
    
    @media print {
        .no-print { display: none !important; }
        .print-card { border: 1px solid #ddd !important; box-shadow: none !important; background: white !important; }
        body, .print-card, .print-card * { color: black !important; background: white !important; }
    }
    
    /* Consistent 12px base via Tailwind classes */
    .text-2xs { font-size: 0.625rem; }
    .tracking-wider { letter-spacing: 0.05em; }
</style>

<div class="space-y-4 ">
    
    <!-- Flash Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 text-xs flex justify-between items-center animate-slide-in no-print">
        <span><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        <button onclick="this.parentElement.remove()" class="text-slate-500 hover:text-slate-300 ml-3">&times;</button>
    </div>
    <?php endif; ?>
    
    <!-- Header - Pure Tailwind with 12px base -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 no-print">
        <div>
            <div class="flex items-center gap-2 text-[10px] font-semibold text-amber-400 uppercase tracking-wider mb-0.5">
                <i class="fas fa-undo-alt text-[10px]"></i>
                <span>Return Details</span>
            </div>
            <h1 class="text-xl font-bold text-white">View Return</h1>
            <p class="text-sm text-slate-500 mt-0.5">Complete return information</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            <?php if ($return && $return['status'] === 'pending'): ?>
                <button onclick="openActionModal()" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-xs font-medium hover:bg-amber-500/25 transition-all">
                    <i class="fas fa-check-double text-[10px]"></i> Take Action
                </button>
            <?php endif; ?>
            <button onclick="copyReturnNumber()" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-copy text-xs"></i> Copy
            </button>
            <button onclick="window.print()" 
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-print text-xs"></i> Print
            </button>
            <a href="list_sell_return.php" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 border border-slate-700 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-arrow-left text-xs"></i> Back
            </a>
        </div>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
    <div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm flex justify-between items-center">
        <span><i class="fas fa-exclamation-circle mr-1.5"></i> <?php echo htmlspecialchars($error); ?></span>
        <button onclick="this.parentElement.remove()" class="text-slate-500 hover:text-slate-300 ml-3">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Return Not Found -->
    <?php if (!$return): ?>
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-10 text-center">
        <i class="fas fa-search text-3xl text-slate-700 mb-2 block"></i>
        <p class="text-slate-400 text-sm">Return not found</p>
        <a href="list_sell_return.php" class="inline-block mt-2 text-amber-400 hover:underline text-sm">Go back to list</a>
    </div>
    <?php else: ?>

    <!-- Status Header Card -->
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4 print-card">
        <div class="flex flex-wrap justify-between items-start gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <i class="fas fa-ticket-alt text-amber-400 text-sm"></i>
                    <span id="return-number" class="text-lg font-bold text-white font-mono"><?php echo htmlspecialchars($return['return_number']); ?></span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Created: <?php echo date('d M Y H:i', strtotime($return['created_at'])); ?></p>
            </div>
            <div class="text-right">
                <?php
                $status_colors = [
                    'completed' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                    'pending' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                    'rejected' => 'bg-red-500/15 text-red-400 border-red-500/30',
                    'voided' => 'bg-slate-500/15 text-slate-400 border-slate-500/30'
                ];
                $status_color = $status_colors[$return['status']] ?? $status_colors['pending'];
                ?>
                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_color; ?>">
                    <?php echo strtoupper($return['status']); ?>
                </span>
                <p class="text-xs text-slate-500 mt-1">Updated: <?php echo date('d M Y H:i', strtotime($return['updated_at'] ?? $return['created_at'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Two Column Info Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Sale Information -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4 print-card">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <i class="fas fa-receipt text-xs"></i> Sale Information
            </h3>
            <div class="space-y-2.5">
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Invoice Number:</span>
                    <span class="text-white text-xs font-mono"><?php echo htmlspecialchars($return['invoice_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Sale Date:</span>
                    <span class="text-slate-200 text-xs"><?php echo date('d M Y H:i', strtotime($return['sale_date'] ?? $return['created_at'])); ?></span>
                </div>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Sale Total:</span>
                    <span class="text-slate-200 text-xs"><?php echo $currency_symbol . ' ' . number_format($return['sale_total'] ?? 0, 2); ?></span>
                </div>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Cashier:</span>
                    <span class="text-slate-200 text-xs"><?php echo htmlspecialchars($return['cashier_name'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4 print-card">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <i class="fas fa-user text-xs"></i> Customer Information
            </h3>
            <div class="space-y-2.5">
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Name:</span>
                    <span class="text-slate-200 text-xs"><?php echo htmlspecialchars($return['customer_name'] ?? 'Walk-in Customer'); ?></span>
                </div>
                <?php if ($return['customer_phone']): ?>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Phone:</span>
                    <span class="text-slate-200 text-xs"><?php echo htmlspecialchars($return['customer_phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($return['customer_email']): ?>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Email:</span>
                    <span class="text-slate-200 text-xs"><?php echo htmlspecialchars($return['customer_email']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Return Items Table -->
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden print-card">
        <div class="px-4 py-2.5 border-b border-slate-700/50 flex justify-between items-center">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                <i class="fas fa-boxes text-xs"></i> Returned Items
            </h3>
            <span class="text-xs text-slate-500"><?php echo count($return_items); ?> item(s)</span>
        </div>
        
        <?php if (empty($return_items)): ?>
            <div class="p-6 text-center text-slate-500 text-sm">No items in this return</div>
        <?php else: ?>
            <div class="overflow-x-auto">
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
                            <td class="px-4 py-2.5 text-sm text-slate-300"><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="px-4 py-2.5 text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2.5 text-right text-sm text-slate-300"><?php echo $item['quantity']; ?></td>
                            <td class="px-4 py-2.5 text-right text-sm text-slate-300"><?php echo $currency_symbol . ' ' . number_format($item['unit_price'], 2); ?></td>
                            <td class="px-4 py-2.5 text-right text-sm font-semibold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-slate-900/40 border-t border-slate-700/50">
                        <tr>
                            <td colspan="4" class="px-4 py-2.5 text-right font-semibold text-slate-300 text-sm">Total Refund:</td>
                            <td class="px-4 py-2.5 text-right text-base font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($return['amount'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Two Column Footer -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Return Details -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4 print-card">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <i class="fas fa-info-circle text-xs"></i> Return Details
            </h3>
            <div class="space-y-2.5">
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Reason:</span>
                    <span class="text-slate-200 text-xs capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $return['reason'] ?? 'N/A')); ?></span>
                </div>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Type:</span>
                    <span class="text-slate-200 text-xs capitalize"><?php echo ucfirst($return['return_type']); ?> Return</span>
                </div>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Processed By:</span>
                    <span class="text-slate-200 text-xs"><?php echo htmlspecialchars($return['processed_by_name'] ?? 'System'); ?></span>
                </div>
                <?php if ($return['approved_by_name']): ?>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Approved By:</span>
                    <span class="text-slate-200 text-xs"><?php echo htmlspecialchars($return['approved_by_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($return['approved_at']): ?>
                <div class="flex justify-between items-center flex-wrap gap-1">
                    <span class="text-slate-500 text-xs">Approved At:</span>
                    <span class="text-slate-200 text-xs"><?php echo date('d M Y H:i', strtotime($return['approved_at'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4 print-card">
            <h3 class="text-xs font-semibold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                <i class="fas fa-sticky-note text-xs"></i> Notes
            </h3>
            <?php if ($return['notes']): ?>
                <div class="bg-slate-900/60 rounded-xl p-3 max-h-40 overflow-y-auto border border-slate-700/40">
                    <p class="text-slate-400 text-xs whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($return['notes'])); ?></p>
                </div>
            <?php else: ?>
                <p class="text-slate-500 text-sm italic">No additional notes</p>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- Action Modal -->
<div id="actionModal" class="fixed inset-0 bg-black/80  flex items-center justify-center z-50 hidden no-print">
    <div class="bg-slate-800 rounded-xl max-w-md w-full mx-4 border border-slate-700 shadow-2xl">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-700">
            <div class="flex items-center gap-2">
                <i class="fas fa-gavel text-amber-400"></i>
                <h3 class="text-base font-semibold text-white">Take Action</h3>
            </div>
            <button onclick="closeActionModal()" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        
        <form method="POST" class="p-4 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Select Action</label>
                <div class="grid grid-cols-2 gap-2">
                    <button type="submit" name="action" value="approve" 
                            class="flex items-center justify-center gap-1.5 px-3 py-2 bg-emerald-500/15 border border-emerald-500/30 rounded-lg text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors">
                        <i class="fas fa-check text-xs"></i> Approve
                    </button>
                    <button type="submit" name="action" value="reject" 
                            class="flex items-center justify-center gap-1.5 px-3 py-2 bg-red-500/15 border border-red-500/30 rounded-lg text-red-400 text-sm font-medium hover:bg-red-500/25 transition-colors">
                        <i class="fas fa-times text-xs"></i> Reject
                    </button>
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Approval Notes (Optional)</label>
                <textarea name="approval_notes" rows="3" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-amber-500/60 resize-none transition-colors" 
                          placeholder="Add any notes about this decision..."></textarea>
            </div>
            
            <button type="button" onclick="closeActionModal()" class="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-sm text-slate-300 hover:bg-slate-600 transition-colors">
                Cancel
            </button>
        </form>
    </div>
</div>

<script>
function copyReturnNumber() {
    const num = document.getElementById('return-number')?.textContent;
    if (num) {
        navigator.clipboard.writeText(num).then(() => {
            showToast('Return number copied to clipboard');
        });
    }
}

function openActionModal() {
    const modal = document.getElementById('actionModal');
    if (modal) modal.classList.remove('hidden');
}

function closeActionModal() {
    const modal = document.getElementById('actionModal');
    if (modal) modal.classList.add('hidden');
}

function showToast(message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg text-emerald-400 text-xs flex items-center gap-2 animate-slide-in';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Auto-hide flash message
setTimeout(() => {
    const flash = document.querySelector('.fixed.top-20.right-4');
    if (flash) flash.style.display = 'none';
}, 4000);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>