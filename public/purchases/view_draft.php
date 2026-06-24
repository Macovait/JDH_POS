<?php
/**
 * View Draft Sale - Display draft sale details
 * View and manage individual draft sales
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Check permission
if (!check_permission('sales.view')) {
    enforce_permission('sales.view');
}

$tenant_id = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Unauthorized'); }

$pdo = get_db_connection();

// Get current user info
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$branch_id = (int) ($_SESSION['user']['branch_id'] ?? get_current_branch_id());

// Get draft ID from URL
$draft_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$draft_id) {
    header('Location: list_draft.php');
    exit;
}

// Initialize variables
$error = '';
$draft = null;
$items = [];
$debug_info = [];

try {
    // Fetch draft details
    $stmt = $pdo->prepare("
        SELECT s.*,
               c.name as customer_name,
               c.phone as customer_phone,
               c.email as customer_email,
               u.name as created_by_name,
               b.name as branch_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.id = ? AND s.tenant_id = ? AND s.status = 'draft'
    ");
    $stmt->execute([$draft_id, $tenant_id]);
    $draft = $stmt->fetch();

    if (!$draft) {
        $error = 'Draft sale not found';
    } else {
        // Fetch draft items
        $stmt = $pdo->prepare("
            SELECT si.*, p.name as product_name, p.sku, p.image
            FROM sale_items si
            LEFT JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
        ");
        $stmt->execute([$draft_id]);
        $items = $stmt->fetchAll();

        $debug_info['draft_found'] = true;
        $debug_info['item_count'] = count($items);
    }

} catch (PDOException $e) {
    error_log("Error fetching draft: " . $e->getMessage());
    $error = "Database error: " . $e->getMessage();
    $debug_info['error'] = $e->getMessage();
}

// Handle actions (delete, convert to sale, edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete') {
            // Delete draft
            $pdo->beginTransaction();

            // Delete items first
            $stmt = $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?");
            $stmt->execute([$draft_id]);

            // Delete sale
            $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
            $stmt->execute([$draft_id]);

            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, created_at)
                VALUES (?, 'draft_deleted', ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                "Deleted draft sale #$draft_id",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            $pdo->commit();

            header('Location: list_draft.php?msg=deleted');
            exit;

        } elseif ($action === 'convert') {
            // Convert draft to sale
            $pdo->beginTransaction();

            // Update status to completed
            $stmt = $pdo->prepare("
                UPDATE sales 
                SET status = 'completed', 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$draft_id]);

            // Log activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, created_at)
                VALUES (?, 'draft_converted', ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                "Converted draft #$draft_id to sale",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            $pdo->commit();

            header('Location: view_sale.php?id=' . $draft_id . '&msg=converted');
            exit;
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error processing request: " . $e->getMessage();
        $debug_info['action_error'] = $e->getMessage();
    }
    } // end CSRF else
}

$page_title = 'View Draft Sale';
$csrf_token = generate_csrf_token();
$currency_symbol = get_tenant_currency();
ob_start();
?>

<style>
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .status-badge.draft {
        background: rgba(251, 191, 36, 0.2);
        color: #FBBF24;
        border: 1px solid rgba(251, 191, 36, 0.3);
    }

    .debug-panel {
        position: fixed;
        top: 1rem;
        right: 1rem;
        max-width: 400px;
        max-height: 80vh;
        overflow-y: auto;
        background: rgba(31, 41, 55, 0.95);
        backdrop-filter: blur(8px);
        border: 1px solid #FBBF24;
        border-radius: 1rem;
        padding: 1rem;
        font-size: 0.75rem;
        z-index: 9999;
        display: none;
        color: white;
    }

    .debug-panel.visible {
        display: block;
    }

    .debug-toggle {
        position: fixed;
        top: 1rem;
        right: 1rem;
        background: #FBBF24;
        color: #000;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: bold;
        cursor: pointer;
        z-index: 10000;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Print styles */
    @media print {
        body {
            background: white;
            color: black;
        }

        .no-print {
            display: none !important;
        }

        .bg-slate-800/40 border border-slate-700/60 rounded-xl {
            background: white;
            border: 1px solid #ddd;
            box-shadow: none;
        }

        .text-amber-400 {
            color: #000 !important;
        }
    }
</style>

<div class="fade-in">

    <?php if ($error): ?>
        <div class="mb-4 bg-[#EF4444]/10 border border-[#EF4444] rounded-xl p-4  no-print">
            <div class="flex items-center gap-3 text-[#EF4444]">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'converted'): ?>
        <div class="mb-4 bg-[#10B981]/10 border border-[#10B981] rounded-xl p-4  no-print">
            <div class="flex items-center gap-3 text-[#10B981]">
                <i class="fas fa-check-circle"></i>
                <span>Draft successfully converted to sale!</span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($draft): ?>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3 mb-6 no-print">
            <a href="list_draft.php"
                class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-semibold transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>
                Back to Drafts
            </a>

            <form method="POST" class="inline" onsubmit="return confirm('Convert this draft to a completed sale?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="convert">
                <button type="submit"
                    class="px-4 py-2 bg-[#10B981] hover:bg-[#059669] text-white rounded-lg font-semibold transition-all flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    Convert to Sale
                </button>
            </form>

            <button onclick="window.print()"
                class="px-4 py-2 bg-[#4B5563] hover:bg-[#6B7280] text-white rounded-lg font-semibold transition-all flex items-center gap-2">
                <i class="fas fa-print"></i>
                Print
            </button>

            <form method="POST" class="inline ml-auto"
                onsubmit="return confirm('Are you sure you want to delete this draft?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit"
                    class="px-4 py-2 bg-[#EF4444] hover:bg-[#DC2626] text-white rounded-lg font-semibold transition-all flex items-center gap-2">
                    <i class="fas fa-trash"></i>
                    Delete Draft
                </button>
            </form>
        </div>

        <!-- Draft Details -->
        <div class="bg-[#1F2937] rounded-xl p-6 mb-6 border border-[#374151]">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-xl font-semibold flex items-center gap-2 text-white">
                    <i class="fas fa-info-circle text-[#FBBF24]"></i>
                    Draft Information
                </h2>
                <span class="status-badge draft">
                    <i class="fas fa-clock"></i>
                    Draft
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-[#9CA3AF]">Draft ID</p>
                    <p class="font-semibold text-white">#<?php echo str_pad($draft['id'], 6, '0', STR_PAD_LEFT); ?></p>
                </div>
                <div>
                    <p class="text-sm text-[#9CA3AF]">Date Created</p>
                    <p class="font-semibold text-white">
                        <?php echo date('d M Y H:i', strtotime($draft['created_at'])); ?></p>
                </div>
                <div>
                    <p class="text-sm text-[#9CA3AF]">Branch</p>
                    <p class="font-semibold text-white">
                        <?php echo htmlspecialchars($draft['branch_name'] ?? 'Main Branch'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-[#9CA3AF]">Created By</p>
                    <p class="font-semibold text-white">
                        <?php echo htmlspecialchars($draft['created_by_name'] ?? 'Unknown'); ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-[#9CA3AF]">Customer</p>
                    <p class="font-semibold text-white">
                        <?php if ($draft['customer_name']): ?>
                            <?php echo htmlspecialchars($draft['customer_name']); ?>
                            <?php if ($draft['customer_phone']): ?>
                                <span class="text-sm text-[#9CA3AF] ml-2">
                                    <i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($draft['customer_phone']); ?>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-[#9CA3AF]">Walk-in Customer</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!empty($draft['notes'])): ?>
                    <div class="md:col-span-3">
                        <p class="text-sm text-[#9CA3AF]">Notes</p>
                        <p class="font-semibold text-white bg-[#111827] p-3 rounded-lg">
                            <?php echo nl2br(htmlspecialchars($draft['notes'])); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-[#1F2937] rounded-xl p-6 border border-[#374151]">
            <h2 class="text-xl font-semibold flex items-center gap-2 mb-4 text-white">
                <i class="fas fa-shopping-cart text-[#FBBF24]"></i>
                Items (<?php echo count($items); ?>)
            </h2>

            <?php if (empty($items)): ?>
                <div class="text-center py-8 bg-[#111827] rounded-lg">
                    <i class="fas fa-box-open text-4xl text-[#4B5563] mb-3"></i>
                    <p class="text-[#9CA3AF]">No items in this draft</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-[#9CA3AF] border-b border-[#374151]">
                                <th class="pb-3">Product</th>
                                <th class="pb-3">SKU</th>
                                <th class="pb-3 text-right">Price</th>
                                <th class="pb-3 text-center">Quantity</th>
                                <th class="pb-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#374151]">
                            <?php
                            $subtotal = 0;
                            foreach ($items as $item):
                                $item_subtotal = (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
                                $subtotal += $item_subtotal;
                                ?>
                                <tr class="hover:bg-[#2D3748] transition-colors">
                                    <td class="py-3">
                                        <div class="flex items-center gap-3">
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt=""
                                                    class="w-10 h-10 object-cover rounded-lg">
                                            <?php else: ?>
                                                <div class="w-10 h-10 bg-[#111827] rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-cube text-[#FBBF24]/50"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span class="font-medium text-white">
                                                <?php echo htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 text-[#9CA3AF]"><?php echo htmlspecialchars($item['sku'] ?? '-'); ?></td>
                                    <td class="py-3 text-right text-white"><?php echo $currency_symbol; ?> <?php echo number_format((float) ($item['price'] ?? 0), 0); ?></td>
                                    <td class="py-3 text-center text-white"><?php echo (int) ($item['quantity'] ?? 1); ?></td>
                                    <td class="py-3 text-right font-semibold text-[#FBBF24]"><?php echo $currency_symbol; ?> <?php echo number_format($item_subtotal, 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="border-t border-[#374151]">
                            <?php
                            $discount = (float) ($draft['discount_amount'] ?? 0);
                            $tax = (float) ($draft['tax_amount'] ?? 0);
                            $total = (float) ($draft['total'] ?? 0);
                            ?>
                            <tr>
                                <td colspan="4" class="pt-4 text-right text-[#9CA3AF]">Subtotal:</td>
                                <td class="pt-4 text-right font-semibold text-white"><?php echo $currency_symbol; ?> <?php echo number_format($subtotal, 0); ?></td>
                            </tr>
                            <?php if ($discount > 0): ?>
                                <tr>
                                    <td colspan="4" class="text-right text-[#9CA3AF]">
                                        Discount
                                        <?php if (!empty($draft['discount_type'])): ?>
                                            (<?php echo $draft['discount_type'] === 'percent' ? $draft['discount'] . '%' : 'Fixed'; ?>):
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right text-[#EF4444]">-<?php echo $currency_symbol; ?> <?php echo number_format($discount, 0); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($tax > 0): ?>
                                <tr>
                                    <td colspan="4" class="text-right text-[#9CA3AF]">Tax
                                        (<?php echo (float) ($draft['tax_rate'] ?? 16); ?>%):</td>
                                    <td class="text-right text-white"><?php echo $currency_symbol; ?> <?php echo number_format($tax, 0); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr class="text-lg font-bold">
                                <td colspan="4" class="pt-2 text-right text-white">Total:</td>
                                <td class="pt-2 text-right text-[#FBBF24]"><?php echo $currency_symbol; ?> <?php echo number_format($total, 0); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
            <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                <p class="text-sm text-[#9CA3AF] mb-1">Subtotal</p>
                <p class="text-2xl font-bold text-white"><?php echo $currency_symbol; ?> <?php echo number_format((float) ($draft['subtotal'] ?? $subtotal), 0); ?></p>
            </div>
            <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                <p class="text-sm text-[#9CA3AF] mb-1">Discount</p>
                <p class="text-2xl font-bold text-[#EF4444]">-<?php echo $currency_symbol; ?> <?php echo number_format((float) ($draft['discount_amount'] ?? 0), 0); ?></p>
            </div>
            <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                <p class="text-sm text-[#9CA3AF] mb-1">Tax</p>
                <p class="text-2xl font-bold text-white"><?php echo $currency_symbol; ?> <?php echo number_format((float) ($draft['tax_amount'] ?? 0), 0); ?></p>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Connection Status -->
<div id="connection-status"
    class="fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151] no-print">
    <i class="fas fa-wifi"></i>
    <span>Online</span>
</div>

<script>
    // Debug toggle
    function toggleDebug() {
        document.getElementById('debugPanel').classList.toggle('visible');
    }

    // Print functionality
    window.onbeforeprint = function () {
        // Any print preparation
    };

    // Connection status
    function updateOnlineStatus() {
        const statusEl = document.getElementById('connection-status');
        if (statusEl) {
            if (navigator.onLine) {
                statusEl.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151] no-print';
            } else {
                statusEl.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>';
                statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#EF4444] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151] no-print';
            }
        }
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
