<?php
/**
 * Cancel Shipment Page for Jakababa POS
 * Simplified cancellation with reason
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('shipments.manage') && !is_super_admin()) {
    enforce_permission('shipments.manage');
}

$page_title = 'Cancel Shipment';
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();
$tenant_id = get_current_tenant_id();

$shipment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$shipment_id) {
    header('Location: shipments.php');
    exit;
}

$shipment = null;
$error = '';
$success = '';

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name
        FROM shipments s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.id = ? AND s.branch_id = ?
    ");
    $stmt->execute([$shipment_id, $current_branch_id]);
    $shipment = $stmt->fetch();

    if (!$shipment) {
        $error = "Shipment not found or you don't have permission.";
    }
} catch (PDOException $e) {
    error_log("Error fetching shipment: " . $e->getMessage());
    $error = "Failed to load shipment data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = get_db_connection();
        $pdo->beginTransaction();

        $reason = trim($_POST['reason'] ?? '');
        $confirm = isset($_POST['confirm']) ? true : false;

        if (!$confirm) {
            throw new Exception("Please confirm cancellation.");
        }

        $stmt = $pdo->prepare("UPDATE shipments SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND branch_id = ?");
        $stmt->execute([$shipment_id, $current_branch_id]);

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('tracking_history', $tables)) {
            $stmt = $pdo->prepare("INSERT INTO tracking_history (shipment_id, status, notes, created_at) VALUES (?, 'Cancelled', ?, NOW())");
            $stmt->execute([$shipment_id, $reason ?: 'No reason provided']);
        }

        $log_activity = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'shipment_cancelled', ?, ?, NOW())");
        $log_activity->execute([$user_id, "Cancelled shipment #{$shipment['tracking_number']}" . ($reason ? " - {$reason}" : ""), $_SERVER['REMOTE_ADDR'] ?? null]);

        $pdo->commit();
        header("Location: view_shipment.php?id=" . $shipment_id . "&success=cancelled");
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

$csrf_token = generate_csrf_token();
ob_start();
?>
<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-times-circle text-red-400"></i> Cancel Shipment
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Review and confirm shipment cancellation</p>
    </div>
    <a href="shipments.php"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors shrink-0">
        <i class="fas fa-arrow-left text-xs"></i> Back
    </a>
</div>

<?php if ($error): ?>
<div class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm mb-4">
    <i class="fas fa-exclamation-circle shrink-0"></i>
    <span><?php echo htmlspecialchars($error); ?></span>
</div>
<?php endif; ?>

<?php if ($shipment): ?>
<div class="max-w-lg">
    <!-- Warning header -->
    <div class="bg-red-500/5 border border-red-500/20 rounded-xl p-6 text-center mb-4">
        <div class="inline-flex items-center justify-center w-12 h-12 bg-red-500/10 rounded-full mb-3">
            <i class="fas fa-exclamation-triangle text-red-400 text-lg"></i>
        </div>
        <h2 class="text-base font-bold text-white mb-1">Cancel This Shipment?</h2>
        <p class="text-sm text-slate-400">This action cannot be undone. The shipment will be marked as cancelled.</p>
    </div>

    <!-- Shipment summary -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-4">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <p class="text-xs text-slate-500 mb-0.5">Tracking Number</p>
                <p class="text-sm font-mono text-amber-400 font-semibold"><?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-0.5">Current Status</p>
                <p class="text-sm text-white font-medium"><?php echo ucfirst($shipment['status']); ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-0.5">Customer</p>
                <p class="text-sm text-white"><?php echo htmlspecialchars($shipment['customer_name'] ?? 'N/A'); ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-0.5">Total</p>
                <p class="text-sm text-white font-semibold">KSh <?php echo number_format($shipment['total'] ?? 0, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Form -->
    <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1">Reason for Cancellation (Optional)</label>
            <textarea name="reason" rows="3"
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none"
                placeholder="Enter reason for cancellation..."></textarea>
        </div>

        <label class="flex items-start gap-2.5 p-3 bg-slate-900/60 border border-slate-700/60 rounded-lg cursor-pointer hover:border-amber-500/30 transition-colors">
            <input type="checkbox" name="confirm" id="confirm" value="1" class="accent-amber-500 mt-0.5 shrink-0">
            <span class="text-sm text-slate-300">I confirm that I want to cancel this shipment. This action cannot be undone.</span>
        </label>

        <div class="flex gap-2.5">
            <button type="submit"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold hover:bg-red-500/25 transition-colors">
                <i class="fas fa-times text-xs"></i> Yes, Cancel Shipment
            </button>
            <a href="view_shipment.php?id=<?php echo $shipment_id; ?>"
               class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                No, Go Back
            </a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
