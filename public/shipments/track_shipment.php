<?php
/**
 * Track Shipment Page for Jakababa POS
 * Public-facing shipment tracking with real-time updates
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
// No login required - this can be public or semi-public

require_once __DIR__ . '/../../src/db.php';

$page_title = 'Track Shipment';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Guest';
$user_role = $_SESSION['role'] ?? '';
$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);

// Get tracking number from URL
$tracking_number = isset($_GET['tracking']) ? trim($_GET['tracking']) : '';
$shipment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// If ID is provided, look up tracking number
if ($shipment_id > 0 && empty($tracking_number)) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT tracking_number FROM shipments WHERE id = ?");
        $stmt->execute([$shipment_id]);
        $tracking_number = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching tracking number: " . $e->getMessage());
    }
}

// Initialize variables
$shipment = null;
$shipment_items = [];
$tracking_history = [];
$error = '';
$tracking_found = false;

// If tracking number provided, fetch shipment details
if (!empty($tracking_number)) {
    try {
        $pdo = get_db_connection();

        $stmt = $pdo->prepare("
            SELECT s.*, 
                   c.name as customer_name,
                   b.name as branch_name
            FROM shipments s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN branches b ON s.branch_id = b.id
            WHERE s.tracking_number = ? OR s.id = ?
        ");
        $stmt->execute([$tracking_number, $tracking_number]);
        $shipment = $stmt->fetch();

        if ($shipment) {
            $tracking_found = true;

            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('shipment_items', $tables)) {
                $stmt = $pdo->prepare("
                    SELECT si.*, p.name as product_name, p.sku
                    FROM shipment_items si
                    LEFT JOIN products p ON si.product_id = p.id
                    WHERE si.shipment_id = ?
                    ORDER BY si.id
                ");
                $stmt->execute([$shipment['id']]);
                $shipment_items = $stmt->fetchAll();
            }

            if (in_array('tracking_history', $tables)) {
                $stmt = $pdo->prepare("
                    SELECT * FROM tracking_history 
                    WHERE shipment_id = ? 
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$shipment['id']]);
                $tracking_history = $stmt->fetchAll();
            }
        } else {
            $error = "Shipment not found with tracking number: " . htmlspecialchars($tracking_number);
        }
    } catch (PDOException $e) {
        error_log("Error fetching shipment: " . $e->getMessage());
        $error = "Failed to load shipment data. Please try again later.";
    }
}

// Get currency symbol
$currency_symbol = 'KSh';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = " . (int)$tenant_id);
    $currency = $stmt->fetchColumn();
    if ($currency) {
        $currency_symbol = $currency;
    }
} catch (Exception $e) {
    // Use default
}

$status_config = [
    'pending'   => ['bg' => 'bg-amber-500/15',   'text' => 'text-amber-400',   'icon' => 'clock',        'label' => 'Pending'],
    'shipped'   => ['bg' => 'bg-blue-500/15',    'text' => 'text-blue-400',    'icon' => 'truck',        'label' => 'Shipped'],
    'delivered' => ['bg' => 'bg-emerald-500/15', 'text' => 'text-emerald-400', 'icon' => 'check-circle', 'label' => 'Delivered'],
    'cancelled' => ['bg' => 'bg-red-500/15',     'text' => 'text-red-400',     'icon' => 'times-circle', 'label' => 'Cancelled'],
];

$progress_percentage = 0;
if ($shipment) {
    switch ($shipment['status']) {
        case 'pending': $progress_percentage = 25; break;
        case 'shipped': $progress_percentage = 60; break;
        case 'delivered': $progress_percentage = 100; break;
        case 'cancelled': $progress_percentage = 0; break;
        default: $progress_percentage = 0;
    }
}

$estimated_text = '';
$is_late = false;
if ($shipment && !empty($shipment['estimated_delivery'])) {
    $today = new DateTime();
    $estimated = new DateTime($shipment['estimated_delivery']);
    $interval = $today->diff($estimated);
    if ($shipment['status'] == 'shipped') {
        if ($today > $estimated) { $is_late = true; $estimated_text = 'Delayed'; }
        else {
            $days = $interval->days;
            if ($days == 0) $estimated_text = 'Today';
            elseif ($days == 1) $estimated_text = 'Tomorrow';
            else $estimated_text = $days . ' days';
        }
    } elseif ($shipment['status'] == 'delivered') {
        if (!empty($shipment['delivered_at'])) {
            $delivered = new DateTime($shipment['delivered_at']);
            $estimated_text = ($delivered > $estimated) ? 'Delivered late' : 'Delivered on time';
        }
    }
}
?>
<?php
ob_start();
?>
<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-map-marker-alt text-amber-400"></i> Track Shipment
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Enter a tracking number for real-time updates</p>
    </div>
    <a href="shipments.php"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors shrink-0">
        <i class="fas fa-arrow-left text-xs"></i> Back
    </a>
</div>

<!-- Tracking Search -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-5 text-center">
    <div class="inline-flex items-center justify-center w-12 h-12 bg-amber-500/10 rounded-full mb-3">
        <i class="fas fa-search text-amber-400"></i>
    </div>
    <h2 class="text-base font-bold text-white mb-1">Track Your Shipment</h2>
    <p class="text-sm text-slate-500 mb-4">Enter your tracking number to see real-time updates</p>
    <form method="GET" action="track_shipment.php" class="max-w-xl mx-auto">
        <div class="flex gap-2">
            <div class="flex-1 relative">
                <i class="fas fa-hashtag absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none"></i>
                <input type="text" name="tracking" value="<?php echo htmlspecialchars($tracking_number); ?>"
                    placeholder="Enter tracking number (e.g. SHIP-20260315-4661)"
                    class="w-full pl-8 pr-3 py-2.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" autofocus>
            </div>
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/20 transition-colors shrink-0">
                <i class="fas fa-search text-xs"></i> Track
            </button>
        </div>
    </form>
</div>

<?php if ($error): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-8 text-center">
    <div class="inline-flex items-center justify-center w-12 h-12 bg-red-500/10 rounded-full mb-3">
        <i class="fas fa-exclamation-triangle text-red-400"></i>
    </div>
    <p class="text-white font-semibold mb-1">Shipment Not Found</p>
    <p class="text-slate-400 text-sm mb-4"><?php echo htmlspecialchars($error); ?></p>
    <a href="track_shipment.php"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm hover:bg-amber-500/20 transition-colors">
        Try Another Tracking Number
    </a>
</div>
<?php elseif ($shipment): ?>
<?php $st = $status_config[$shipment['status']] ?? $status_config['pending']; ?>
<div class="space-y-4">

    <!-- Tracking header -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="text-xs text-slate-500 uppercase tracking-wide">Tracking Number</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $st['bg'] . ' ' . $st['text']; ?>">
                        <i class="fas fa-<?php echo $st['icon']; ?> text-[10px]"></i><?php echo $st['label']; ?>
                    </span>
                </div>
                <p class="text-xl font-bold text-white font-mono"><?php echo htmlspecialchars($shipment['tracking_number']); ?></p>
                <p class="text-xs text-slate-500 mt-0.5">via <?php echo htmlspecialchars($shipment['courier'] ?? 'Unknown Courier'); ?></p>
            </div>
            <div class="text-sm sm:text-right">
                <p class="text-xs text-slate-500 mb-0.5">Estimated Delivery</p>
                <?php if (!empty($shipment['estimated_delivery'])): ?>
                <p class="font-bold <?php echo $is_late ? 'text-red-400' : 'text-emerald-400'; ?>">
                    <?php echo date('M d, Y', strtotime($shipment['estimated_delivery'])); ?>
                </p>
                <?php if ($estimated_text): ?>
                <p class="text-xs <?php echo $is_late ? 'text-red-400' : 'text-slate-400'; ?>"><?php echo $estimated_text; ?></p>
                <?php endif; ?>
                <?php else: ?>
                <p class="text-sm text-slate-600">Not set</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <div class="flex justify-between text-xs text-slate-500 mb-2">
            <span>Pending</span><span>Shipped</span><span>Delivered</span>
        </div>
        <div class="w-full h-2 bg-slate-700 rounded-full overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-400 transition-all duration-500"
                 style="width: <?php echo $progress_percentage; ?>%"></div>
        </div>
        <div class="flex justify-between text-xs text-slate-600 mt-1.5">
            <span><?php echo isset($shipment['created_at']) ? date('M d', strtotime($shipment['created_at'])) : '-'; ?></span>
            <span><?php echo !empty($shipment['shipped_at']) ? date('M d', strtotime($shipment['shipped_at'])) : '-'; ?></span>
            <span><?php echo !empty($shipment['delivered_at']) ? date('M d', strtotime($shipment['delivered_at'])) : '-'; ?></span>
        </div>
    </div>

    <!-- Customer & Shipment Info -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
                <i class="fas fa-user text-amber-400"></i> Customer
            </h3>
            <div class="space-y-1.5 text-sm">
                <div><span class="text-slate-500">Name: </span><span class="text-white"><?php echo htmlspecialchars($shipment['customer_name'] ?? 'N/A'); ?></span></div>
                <div><span class="text-slate-500">Phone: </span><span class="text-white"><?php echo htmlspecialchars($shipment['phone'] ?? 'N/A'); ?></span></div>
                <div><span class="text-slate-500">Address: </span><span class="text-white"><?php echo nl2br(htmlspecialchars($shipment['address'] ?? 'N/A')); ?></span></div>
                <?php if (!empty($shipment['city'])): ?>
                <div><span class="text-slate-500">City: </span><span class="text-white"><?php echo htmlspecialchars($shipment['city']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
                <i class="fas fa-info-circle text-amber-400"></i> Shipment Info
            </h3>
            <div class="space-y-1.5 text-sm">
                <div><span class="text-slate-500">Courier: </span><span class="text-white"><?php echo htmlspecialchars($shipment['courier'] ?? 'N/A'); ?></span></div>
                <?php if (!empty($shipment['courier_service'])): ?>
                <div><span class="text-slate-500">Service: </span><span class="text-white"><?php echo htmlspecialchars($shipment['courier_service']); ?></span></div>
                <?php endif; ?>
                <div><span class="text-slate-500">Shipping Cost: </span><span class="text-white"><?php echo $currency_symbol . ' ' . number_format($shipment['shipping_cost'] ?? 0, 2); ?></span></div>
                <div><span class="text-slate-500">Total Value: </span><span class="text-amber-400 font-bold"><?php echo $currency_symbol . ' ' . number_format($shipment['total'] ?? 0, 2); ?></span></div>
            </div>
        </div>
    </div>

    <?php if (!empty($shipment_items)): ?>
    <!-- Items -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
            <i class="fas fa-boxes text-amber-400"></i> Items in Shipment
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="border-b border-slate-700/60">
                    <th class="pb-2 text-left text-xs font-medium text-slate-500">Product</th>
                    <th class="pb-2 text-center text-xs font-medium text-slate-500">Qty</th>
                    <th class="pb-2 text-right text-xs font-medium text-slate-500">Price</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php foreach ($shipment_items as $item): ?>
                    <tr>
                        <td class="py-2 text-sm text-white"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown'); ?></td>
                        <td class="py-2 text-center text-sm text-white"><?php echo $item['quantity']; ?></td>
                        <td class="py-2 text-right text-sm text-amber-400"><?php echo $currency_symbol . ' ' . number_format($item['price'] ?? 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($tracking_history)): ?>
    <!-- Tracking History -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
            <i class="fas fa-history text-amber-400"></i> Tracking History
        </h3>
        <div class="space-y-3">
            <?php foreach ($tracking_history as $history): ?>
            <div class="flex items-start gap-3">
                <div class="w-7 h-7 rounded-full bg-blue-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-circle text-blue-400 text-[8px]"></i>
                </div>
                <div>
                    <p class="text-sm text-white font-medium"><?php echo htmlspecialchars($history['status']); ?></p>
                    <p class="text-xs text-slate-500"><?php echo isset($history['created_at']) ? date('d M Y H:i', strtotime($history['created_at'])) : ''; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($shipment['notes'])): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-2">
            <i class="fas fa-sticky-note text-amber-400"></i> Notes
        </h3>
        <p class="text-sm text-slate-300 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($shipment['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Bottom actions -->
    <div class="flex gap-2 flex-wrap no-print">
        <a href="track_shipment.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
            <i class="fas fa-search text-xs"></i> Track Another
        </a>
        <?php if ($user_id > 0): ?>
        <a href="shipments.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-list text-xs"></i> Back to Shipments
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-12 text-center">
    <i class="fas fa-truck text-4xl text-slate-700 block mb-3"></i>
    <p class="text-white font-semibold mb-1">Enter a Tracking Number</p>
    <p class="text-slate-500 text-sm mb-2">Please enter a tracking number above to see shipment details</p>
    <p class="text-xs text-slate-600">Example: SHIP-20260315-4661</p>
</div>
<?php endif; ?>

<script>
    document.querySelector('input[name="tracking"]')?.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') { this.form.submit(); }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
