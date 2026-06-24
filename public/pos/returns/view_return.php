<?php
/**
 * View Return Page for Jakababa POS
 * Display detailed information about a sales return
 * Modern Tailwind CSS styling matching admin panel style
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

// Check for return permission
if (!check_permission('sales.returns') && !is_super_admin()) {
    enforce_permission('sales.returns');
}

safe_require('db.php', 'src', true);

$page_title = 'View Return | Jakababa POS';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();
$tenant_id = get_current_tenant_id();
$can_switch_branch = is_super_admin() || check_permission('branches.view');
$selected_branch_id = $current_branch_id;

// Get return ID from URL
$return_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$return_id) {
    header('Location: list_sell_return.php?branch_id=' . $selected_branch_id);
    exit;
}

$error = '';
$return = null;
$return_items = [];
$sale_items = [];

try {
    $pdo = get_db_connection();

    // Debug: Check if return exists
    $check_stmt = $pdo->prepare("SELECT id, tenant_id FROM returns WHERE id = ?");
    $check_stmt->execute([$return_id]);
    $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Return ID $return_id exists: " . ($check_result ? 'yes, tenant_id=' . $check_result['tenant_id'] : 'no'));
    error_log("Current tenant_id: $tenant_id");

    // Get return details — validate by branch (primary) and tenant (secondary)
    $sql = "
        SELECT r.*,
               s.invoice_number, s.created_at as sale_date,
               c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
               u1.name as processed_by_name,
               u2.name as approved_by_name,
               b.name as branch_name
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u1 ON r.processed_by = u1.id
        LEFT JOIN users u2 ON r.approved_by = u2.id
        LEFT JOIN branches b ON r.branch_id = b.id
        WHERE r.id = ? AND r.tenant_id = ?";
    $params = [$return_id, $tenant_id];
    if (!$can_switch_branch) {
        $sql .= " AND r.branch_id = ?";
        $params[] = $current_branch_id;
    } elseif (!empty($_GET['branch_id'])) {
        $requested_branch_id = (int) $_GET['branch_id'];
        if ($requested_branch_id > 0) {
            $selected_branch_id = $requested_branch_id;
            $sql .= " AND r.branch_id = ?";
            $params[] = $selected_branch_id;
        }
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $return = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$return) {
        $error = 'Return not found';
    } else {
        $selected_branch_id = (int) ($return['branch_id'] ?? $current_branch_id);
        // Get return items
        $stmt = $pdo->prepare("
            SELECT ri.*, p.name as product_name, p.sku
            FROM return_items ri
            LEFT JOIN products p ON ri.product_id = p.id
            WHERE ri.return_id = ? AND ri.tenant_id = ?
        ");
        $stmt->execute([$return_id, $tenant_id]);
        $return_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get original sale items if sale exists
        if ($return['sale_id']) {
            $stmt = $pdo->prepare("
                SELECT si.*, p.name as product_name
                FROM sale_items si
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = ? AND si.tenant_id = ?
            ");
            $stmt->execute([$return['sale_id'], $tenant_id]);
            $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Get currency symbol
    $currency_symbol = 'KSh';
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND (tenant_id = ? OR tenant_id IS NULL)");
    $stmt->execute([$tenant_id]);
    $currency = $stmt->fetchColumn();
    if ($currency) {
        $currency_symbol = $currency;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = 'Database error: ' . $e->getMessage();
}

// Status styling
$status_map = [
    'completed' => ['bg' => 'bg-green-500/20', 'text' => 'text-green-400', 'icon' => 'check-circle', 'label' => 'Completed'],
    'pending' => ['bg' => 'bg-yellow-500/20', 'text' => 'text-yellow-400', 'icon' => 'clock', 'label' => 'Pending'],
    'rejected' => ['bg' => 'bg-red-500/20', 'text' => 'text-red-400', 'icon' => 'times-circle', 'label' => 'Rejected']
];
$status = $status_map[$return['status'] ?? 'pending'] ?? $status_map['pending'];
?>
<?php
$page_title = 'View Return | Jakababa POS';
ob_start();
?>
<style>
.dash-card {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 10px;
    transition: border-color .15s ease, background .15s ease;
}
.dash-card:hover { border-color: #4b5563; }

.dash-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.dash-table th {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
    font-weight: 600;
    padding: 8px 12px;
    text-align: left;
    background: #111827;
    border-bottom: 1px solid #374151;
}
.dash-table td {
    padding: 8px 12px;
    font-size: 12.5px;
    color: #cbd5e1;
    border-bottom: 1px solid #2a3445;
}
.dash-table tr:last-child td { border-bottom: 0; }
.dash-table tr:hover td { background: rgba(255,255,255,0.02); }

.btn-primary {
    background: #fbbf24; color: #111827; font-weight: 600;
    padding: 8px 14px; border-radius: 8px; font-size: 13px;
    transition: background .15s ease;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-primary:hover { background: #f59e0b; }

.btn-ghost {
    background: #374151; border: 1px solid #4b5563; color: #f8fafc;
    font-weight: 600; padding: 8px 14px; border-radius: 8px; font-size: 13px;
    transition: background .15s ease;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-ghost:hover { background: #4b5563; }

.btn-success {
    background: #10b981; border: 1px solid #10b981; color: #fff;
    font-weight: 600; padding: 8px 14px; border-radius: 8px; font-size: 13px;
    transition: background .15s ease;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-success:hover { background: #059669; }

.btn-danger {
    background: #ef4444; border: 1px solid #ef4444; color: #fff;
    font-weight: 600; padding: 8px 14px; border-radius: 8px; font-size: 13px;
    transition: background .15s ease;
    display: inline-flex; align-items: center; gap: 6px;
}
.btn-danger:hover { background: #dc2626; }

.info-label { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
.info-value { color: #f8fafc; font-size: 0.875rem; }
.info-row { border-bottom: 1px solid #374151; padding: 0.5rem 0; display: flex; justify-content: space-between; }
.info-row:last-child { border-bottom: 0; }

.modal-bg { background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
.modal-card { background: #1f2937; border: 1px solid #374151; border-radius: 1rem; }

@media print {
    .no-print { display: none !important; }
    body { background: white; color: black; }
}
</style>
<div class="dash w-full max-w-full p-4 sm:p-5 space-y-4">

    <?php if ($error): ?>
        <div class="dash-card p-6 text-center">
            <i class="fas fa-exclamation-circle text-5xl mb-4" style="color:#fbbf24"></i>
            <h2 class="text-xl font-bold mb-2" style="color:#fbbf24">Error</h2>
            <p style="color:#cbd5e1"><?php echo htmlspecialchars($error); ?></p>
            <a href="list_sell_return.php?branch_id=<?php echo (int) $selected_branch_id; ?>" class="inline-block mt-4 btn-primary px-6 py-2 rounded-lg font-semibold">Back to Returns</a>
        </div>
    <?php else: ?>
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 no-print">
            <div>
                <div class="text-xs text-amber-400/70 uppercase tracking-wider font-semibold mb-0.5">Return #<?php echo htmlspecialchars($return['return_number'] ?? 'N/A'); ?></div>
                <h1 class="text-lg font-bold text-white">View Return</h1>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="list_sell_return.php?branch_id=<?php echo (int) $selected_branch_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
                    <i class="fas fa-list"></i> All Returns
                </a>
                <?php if ($return['status'] === 'pending'): ?>
                    <a href="../process_return.php?id=<?php echo $return_id; ?>&branch_id=<?php echo (int) $selected_branch_id; ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <button onclick="processReturn(<?php echo $return_id; ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors">
                        <i class="fas fa-check-circle"></i> Complete
                    </button>
                    <button onclick="rejectReturn(<?php echo $return_id; ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors" style="background:#f59e0b">
                        <i class="fas fa-times-circle"></i> Reject
                    </button>
                <?php endif; ?>
                <?php if ($return['status'] === 'completed'): ?>
                    <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                        <i class="fas fa-print"></i> Print
                    </button>
                <?php endif; ?>
                <button onclick="deleteReturn(<?php echo $return_id; ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/25 transition-colors">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="dash-card p-5">
                <div class="flex items-center gap-3">
                    <div class="<?php echo $status['bg']; ?> p-3 rounded-xl">
                        <i class="fas fa-<?php echo $status['icon']; ?> <?php echo $status['text']; ?> text-xl"></i>
                    </div>
                    <div>
                        <p class="text-xs" style="color:#94a3b8">Status</p>
                        <p class="text-xl font-bold <?php echo $status['text']; ?>"><?php echo $status['label']; ?></p>
                    </div>
                </div>
            </div>
            <div class="dash-card p-5">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl" style="background:rgba(59,130,246,0.14)">
                        <i class="fas fa-coins text-xl" style="color:#60a5fa"></i>
                    </div>
                    <div>
                        <p class="text-xs" style="color:#94a3b8">Return Amount</p>
                        <p class="text-xl font-bold" style="color:#fbbf24"><?php echo $currency_symbol; ?> <?php echo number_format($return['total_amount'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="dash-card p-5">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-xl" style="background:rgba(139,92,246,0.14)">
                        <i class="fas fa-calendar text-xl" style="color:#a78bfa"></i>
                    </div>
                    <div>
                        <p class="text-xs" style="color:#94a3b8">Processed On</p>
                        <p class="text-lg font-bold" style="color:#f8fafc"><?php echo date('d M Y', strtotime($return['created_at'])); ?></p>
                        <p class="text-xs" style="color:#94a3b8"><?php echo date('H:i:s', strtotime($return['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Returned Items -->
        <div class="dash-card p-6 mb-4">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2" style="color:#f8fafc">
                <i class="fas fa-boxes" style="color:#fbbf24"></i>
                Returned Items
            </h2>
            <div class="overflow-x-auto">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-right">Price</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($return_items as $item): ?>
                        <tr>
                            <td>
                                <div class="font-medium" style="color:#f8fafc"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="text-xs" style="color:#94a3b8"><?php echo htmlspecialchars($item['sku'] ?? ''); ?></div>
                            </td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-right"><?php echo $currency_symbol; ?> <?php echo number_format($item['price'] ?? 0, 2); ?></td>
                            <td class="text-right font-semibold" style="color:#fbbf24"><?php echo $currency_symbol; ?> <?php echo number_format(($item['price'] ?? 0) * $item['quantity'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background:#111827">
                        <tr>
                            <td colspan="3" class="text-right font-bold" style="color:#f8fafc">Total Return:</td>
                            <td class="text-right font-bold text-lg" style="color:#fbbf24"><?php echo $currency_symbol; ?> <?php echo number_format($return['total_amount'] ?? 0, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Original Sale Items -->
        <?php if (!empty($sale_items)): ?>
        <div class="dash-card p-6 mb-4">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2" style="color:#f8fafc">
                <i class="fas fa-receipt" style="color:#fbbf24"></i>
                Original Sale Items
            </h2>
            <div class="overflow-x-auto">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-right">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_items as $item): ?>
                        <tr>
                            <td style="color:#f8fafc"><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-right"><?php echo $currency_symbol; ?> <?php echo number_format($item['price'] ?? 0, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Return Information -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-6">
            <div class="dash-card p-6">
                <h2 class="text-lg font-semibold mb-4 flex items-center gap-2" style="color:#f8fafc">
                    <i class="fas fa-info-circle" style="color:#fbbf24"></i>
                    Return Information
                </h2>
                <div class="space-y-3">
                    <div class="info-row">
                        <span class="info-label">Return Number:</span>
                        <span class="info-value font-mono"><?php echo htmlspecialchars($return['return_number']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Invoice Number:</span>
                        <span class="info-value font-mono">
                            <?php if ($return['sale_id']): ?>
                                <a href="../sales/view_sale.php?id=<?php echo $return['sale_id']; ?>" class="text-yellow-400 hover:underline">
                                    <?php echo htmlspecialchars($return['invoice_number']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color:#94a3b8">N/A</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Customer:</span>
                        <span class="info-value">
                            <?php if ($return['customer_id']): ?>
                                <a href="../customers/view_customer.php?id=<?php echo $return['customer_id']; ?>" class="text-yellow-400 hover:underline">
                                    <?php echo htmlspecialchars($return['customer_name'] ?? 'N/A'); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($return['customer_name'] ?? 'Walk-in Customer'); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!empty($return['customer_phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($return['customer_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($return['customer_email'])): ?>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($return['customer_email']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Processed By:</span>
                        <span class="info-value"><?php echo htmlspecialchars($return['processed_by_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Approved By:</span>
                        <span class="info-value"><?php echo htmlspecialchars($return['approved_by_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Branch:</span>
                        <span class="info-value"><?php echo htmlspecialchars($return['branch_name']); ?></span>
                    </div>
                </div>
            </div>

            <div class="dash-card p-6">
                <h2 class="text-lg font-semibold mb-4 flex items-center gap-2" style="color:#f8fafc">
                    <i class="fas fa-clipboard-list" style="color:#fbbf24"></i>
                    Reason & Notes
                </h2>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm mb-1" style="color:#94a3b8">Return Reason</p>
                        <div class="rounded-xl p-3" style="background:#111827">
                            <p style="color:#f8fafc"><?php echo htmlspecialchars($return['reason']); ?></p>
                        </div>
                    </div>
                    <?php if (!empty($return['notes'])): ?>
                    <div>
                        <p class="text-sm mb-1" style="color:#94a3b8">Additional Notes</p>
                        <div class="rounded-xl p-3" style="background:#111827">
                            <p class="whitespace-pre-wrap" style="color:#f8fafc"><?php echo htmlspecialchars($return['notes']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sale Summary -->
        <?php if ($return['sale_id']): ?>
        <div class="dash-card p-6 mb-4">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2" style="color:#f8fafc">
                <i class="fas fa-chart-line" style="color:#fbbf24"></i>
                Sale Summary
            </h2>
            <div class="space-y-3">
                <div class="info-row">
                    <span class="info-label">Sale Date:</span>
                    <span class="info-value"><?php echo date('d M Y', strtotime($return['sale_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Original Total:</span>
                    <span class="info-value"><?php echo $currency_symbol; ?> <?php echo number_format($return['original_total'] ?? 0, 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Return Amount:</span>
                    <span class="info-value" style="color:#fbbf24"><?php echo $currency_symbol; ?> <?php echo number_format($return['total_amount'] ?? 0, 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Remaining:</span>
                    <span class="info-value" style="color:#34d399"><?php echo $currency_symbol; ?> <?php echo number_format(($return['original_total'] ?? 0) - ($return['total_amount'] ?? 0), 2); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Audit Trail -->
        <div class="dash-card p-6 no-print">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2" style="color:#f8fafc">
                <i class="fas fa-history" style="color:#fbbf24"></i>
                Audit Trail
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <span style="color:#94a3b8">Created:</span>
                    <span class="ml-2" style="color:#f8fafc"><?php echo date('d M Y H:i:s', strtotime($return['created_at'])); ?></span>
                </div>
                <div>
                    <span style="color:#94a3b8">Last Updated:</span>
                    <span class="ml-2" style="color:#f8fafc"><?php echo date('d M Y H:i:s', strtotime($return['updated_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <!-- Complete Modal -->
        <div id="processModal" class="fixed inset-0 modal-bg hidden items-center justify-center z-50 no-print">
            <div class="modal-card w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold flex items-center gap-2" style="color:#f8fafc">
                        <i class="fas fa-check-circle" style="color:#34d399"></i>
                        Complete Return
                    </h3>
                    <button onclick="closeProcessModal()" style="color:#94a3b8">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="mb-6" style="color:#cbd5e1">Are you sure you want to mark this return as completed? This will update inventory.</p>
                <div class="flex gap-3">
                    <button onclick="confirmProcess()" class="btn-success flex-1">Complete</button>
                    <button onclick="closeProcessModal()" class="btn-ghost flex-1">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div id="rejectModal" class="fixed inset-0 modal-bg hidden items-center justify-center z-50 no-print">
            <div class="modal-card w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold flex items-center gap-2" style="color:#f8fafc">
                        <i class="fas fa-times-circle" style="color:#fbbf24"></i>
                        Reject Return
                    </h3>
                    <button onclick="closeRejectModal()" style="color:#94a3b8">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="mb-4" style="color:#cbd5e1">Are you sure you want to reject this return?</p>
                <textarea id="rejectReason" class="w-full rounded-xl px-4 py-3 mb-4" style="background:#111827;border:1px solid #374151;color:#f8fafc" rows="3" placeholder="Reason for rejection (optional)"></textarea>
                <div class="flex gap-3">
                    <button onclick="confirmReject()" class="btn-primary flex-1" style="background:#f59e0b">Reject</button>
                    <button onclick="closeRejectModal()" class="btn-ghost flex-1">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <div id="deleteModal" class="fixed inset-0 modal-bg hidden items-center justify-center z-50 no-print">
            <div class="modal-card w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold flex items-center gap-2" style="color:#f8fafc">
                        <i class="fas fa-trash" style="color:#ef4444"></i>
                        Delete Return
                    </h3>
                    <button onclick="closeDeleteModal()" style="color:#94a3b8">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="mb-6" style="color:#cbd5e1">Are you sure you want to delete this return? This action cannot be undone.</p>
                <div class="flex gap-3">
                    <button onclick="confirmDelete()" class="btn-danger flex-1">Delete</button>
                    <button onclick="closeDeleteModal()" class="btn-ghost flex-1">Cancel</button>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
    let returnId = <?php echo $return_id; ?>;
    const returnBranchId = <?php echo (int) $selected_branch_id; ?>;

    function openProcessModal() {
        document.getElementById('processModal').classList.remove('hidden');
        document.getElementById('processModal').classList.add('flex');
    }

    function closeProcessModal() {
        document.getElementById('processModal').classList.add('hidden');
        document.getElementById('processModal').classList.remove('flex');
    }

    function processReturn(id) {
        returnId = id;
        openProcessModal();
    }

    function confirmProcess() {
        window.location.href = 'update_return_status.php?id=' + returnId + '&status=completed&branch_id=' + returnBranchId;
    }

    function openRejectModal() {
        document.getElementById('rejectModal').classList.remove('hidden');
        document.getElementById('rejectModal').classList.add('flex');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
        document.getElementById('rejectModal').classList.remove('flex');
    }

    function rejectReturn(id) {
        returnId = id;
        openRejectModal();
    }

    function confirmReject() {
        const reason = document.getElementById('rejectReason').value;
        window.location.href = 'update_return_status.php?id=' + returnId + '&status=rejected&reason=' + encodeURIComponent(reason) + '&branch_id=' + returnBranchId;
    }

    function openDeleteModal() {
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }

    function deleteReturn(id) {
        returnId = id;
        openDeleteModal();
    }

    function confirmDelete() {
        window.location.href = 'delete_return.php?id=' + returnId + '&branch_id=' + returnBranchId;
    }

    // Close modals on outside click
    window.onclick = function(event) {
        const processModal = document.getElementById('processModal');
        const rejectModal = document.getElementById('rejectModal');
        const deleteModal = document.getElementById('deleteModal');

        if (event.target === processModal) closeProcessModal();
        if (event.target === rejectModal) closeRejectModal();
        if (event.target === deleteModal) closeDeleteModal();
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;

        if (e.key === 'Escape') {
            closeProcessModal();
            closeRejectModal();
            closeDeleteModal();
        }

        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });

    // Check for URL parameters (success messages)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'updated') {
        alert('Return status updated successfully');
    }
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
require_once __DIR__ . '/../../layouts/app_close.php';
