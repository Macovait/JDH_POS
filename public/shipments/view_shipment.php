<?php
/**
 * View Shipment Page for Jakababa POS
 * Display detailed information about a shipment
 */

require_once __DIR__ . '/../../src/auth.php';
require_login();

if (!check_permission('shipments.manage') && !is_super_admin()) {
    enforce_permission('shipments.manage');
}

require_once __DIR__ . '/../../src/db.php';

$page_title = 'View Shipment';
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();

$shipment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$shipment_id) {
    header('Location: shipments.php');
    exit;
}

$branches = [];
if (is_super_admin() || check_permission('branches.view')) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query("SELECT id, name FROM branches WHERE active = 1 ORDER BY name");
        $branches = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching branches: " . $e->getMessage());
    }
}

$shipment = null;
$shipment_items = [];
$tracking_history = [];
$error = '';

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT s.*, 
               c.name as customer_name, 
               c.phone as customer_phone, 
               c.email as customer_email,
               c.address as customer_address,
               sl.invoice_number,
               sl.total as sale_total,
               b.name as branch_name
        FROM shipments s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN sales sl ON s.sale_id = sl.id
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.id = ?
    ");
    $stmt->execute([$shipment_id]);
    $shipment = $stmt->fetch();

    if (!$shipment) {
        $error = "Shipment not found.";
    } else {
        if (!is_super_admin() && $shipment['branch_id'] != $current_branch_id) {
            $error = "You do not have permission to view this shipment.";
        } else {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('shipment_items', $tables)) {
                $stmt = $pdo->prepare("SELECT si.*, p.name as product_name, p.sku, p.price FROM shipment_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.shipment_id = ? ORDER BY si.id");
                $stmt->execute([$shipment_id]);
                $shipment_items = $stmt->fetchAll();
            }
            if (in_array('tracking_history', $tables)) {
                $stmt = $pdo->prepare("SELECT * FROM tracking_history WHERE shipment_id = ? ORDER BY created_at DESC");
                $stmt->execute([$shipment_id]);
                $tracking_history = $stmt->fetchAll();
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching shipment: " . $e->getMessage());
    $error = "Failed to load shipment data: " . $e->getMessage();
}

$currency_symbol = 'KSh';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency'");
    $currency = $stmt->fetchColumn();
    if ($currency) $currency_symbol = $currency;
} catch (Exception $e) {}

$status_config = [
    'pending'   => ['bg' => 'bg-amber-500/15',   'text' => 'text-amber-400',   'ring' => 'ring-amber-500/30',   'icon' => 'clock',        'label' => 'Pending'],
    'shipped'   => ['bg' => 'bg-blue-500/15',    'text' => 'text-blue-400',    'ring' => 'ring-blue-500/30',    'icon' => 'truck',        'label' => 'Shipped'],
    'delivered' => ['bg' => 'bg-emerald-500/15', 'text' => 'text-emerald-400', 'ring' => 'ring-emerald-500/30', 'icon' => 'check-circle', 'label' => 'Delivered'],
    'cancelled' => ['bg' => 'bg-red-500/15',     'text' => 'text-red-400',     'ring' => 'ring-red-500/30',     'icon' => 'times-circle', 'label' => 'Cancelled'],
];

$current_status = isset($shipment['status']) ? $shipment['status'] : 'pending';
$status = isset($status_config[$current_status]) ? $status_config[$current_status] :
    ['bg' => 'bg-slate-500/15', 'text' => 'text-slate-400', 'ring' => 'ring-slate-500/30', 'icon' => 'question', 'label' => 'Unknown'];

$is_late = false;
if (isset($shipment['status']) && $shipment['status'] == 'shipped' && isset($shipment['estimated_delivery'])) {
    $today = new DateTime();
    $estimated = new DateTime($shipment['estimated_delivery']);
    if ($today > $estimated) $is_late = true;
}
?>
<?php
ob_start();
?>
<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-truck text-amber-400"></i> View Shipment
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Shipment details and tracking</p>
    </div>
    <a href="shipments.php"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors shrink-0">
        <i class="fas fa-arrow-left text-xs"></i> Back
    </a>
</div>

<?php if ($error): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-10 text-center">
    <i class="fas fa-exclamation-circle text-red-400 text-4xl mb-3 block"></i>
    <p class="text-white font-semibold mb-1">Error</p>
    <p class="text-slate-400 text-sm mb-4"><?php echo htmlspecialchars($error); ?></p>
    <a href="shipments.php"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm hover:bg-amber-500/20 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Shipments
    </a>
</div>
<?php else: ?>

<!-- Action buttons -->
<div class="flex flex-wrap gap-2 mb-5">
    <a href="process_shipment.php?id=<?php echo $shipment_id; ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
        <i class="fas fa-edit text-xs"></i> Edit
    </a>
    <?php if (isset($shipment['status']) && $shipment['status'] === 'pending'): ?>
    <a href="update_shipment_status.php?id=<?php echo $shipment_id; ?>&status=shipped"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-500/10 border border-blue-500/30 text-blue-400 text-sm font-medium hover:bg-blue-500/20 transition-colors"
       onclick="return confirm('Mark this shipment as shipped?')">
        <i class="fas fa-truck text-xs"></i> Mark as Shipped
    </a>
    <?php endif; ?>
    <?php if (isset($shipment['status']) && $shipment['status'] === 'shipped'): ?>
    <a href="update_shipment_status.php?id=<?php echo $shipment_id; ?>&status=delivered"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-colors"
       onclick="return confirm('Mark this shipment as delivered?')">
        <i class="fas fa-check-circle text-xs"></i> Mark as Delivered
    </a>
    <?php endif; ?>
    <?php if (isset($shipment['status']) && $shipment['status'] !== 'cancelled' && $shipment['status'] !== 'delivered'): ?>
    <button onclick="cancelShipment(<?php echo $shipment_id; ?>)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-colors">
        <i class="fas fa-times text-xs"></i> Cancel
    </button>
    <?php endif; ?>
    <?php if (!empty($shipment['tracking_url'])): ?>
    <a href="<?php echo htmlspecialchars($shipment['tracking_url']); ?>" target="_blank"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
        <i class="fas fa-external-link-alt text-xs"></i> Track on Courier
    </a>
    <?php endif; ?>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-hashtag text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-xs text-slate-500">Tracking #</div>
            <div class="text-xs font-mono font-semibold text-white truncate"><?php echo htmlspecialchars($shipment['tracking_number'] ?? 'N/A'); ?></div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $status['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas fa-<?php echo $status['icon']; ?> <?php echo $status['text']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-xs text-slate-500">Status</div>
            <div class="text-sm font-bold <?php echo $status['text']; ?>"><?php echo $status['label']; ?></div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-calendar text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-xs text-slate-500">Created</div>
            <div class="text-sm font-bold text-white"><?php echo isset($shipment['created_at']) ? date('d M Y', strtotime($shipment['created_at'])) : 'N/A'; ?></div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-tag text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-xs text-slate-500">Total Value</div>
            <div class="text-sm font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($shipment['total'] ?? 0, 2); ?></div>
        </div>
    </div>
</div>

<!-- Main content grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Left: Customer + Items + Tracking History -->
    <div class="lg:col-span-2 space-y-4">
        <!-- Customer Information -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
                <i class="fas fa-user text-amber-400"></i> Customer Information
            </h2>
            <div class="divide-y divide-slate-700/60">
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Name</span><span class="w-3/5 text-sm text-white font-medium"><?php echo htmlspecialchars($shipment['customer_name'] ?? 'N/A'); ?></span></div>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Phone</span><span class="w-3/5 text-sm text-white"><?php echo htmlspecialchars($shipment['customer_phone'] ?? $shipment['phone'] ?? 'N/A'); ?></span></div>
                <?php if (!empty($shipment['customer_email'])): ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Email</span><span class="w-3/5 text-sm text-white"><?php echo htmlspecialchars($shipment['customer_email']); ?></span></div>
                <?php endif; ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Address</span><span class="w-3/5 text-sm text-white"><?php echo nl2br(htmlspecialchars($shipment['address'] ?? 'N/A')); ?></span></div>
                <?php if (!empty($shipment['city'])): ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">City</span><span class="w-3/5 text-sm text-white"><?php echo htmlspecialchars($shipment['city']); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($shipment['postal_code'])): ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Postal Code</span><span class="w-3/5 text-sm text-white"><?php echo htmlspecialchars($shipment['postal_code']); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Shipment Items -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
                <i class="fas fa-boxes text-amber-400"></i> Shipment Items
            </h2>
            <?php if (empty($shipment_items)): ?>
            <div class="py-8 text-center">
                <i class="fas fa-box-open text-3xl text-slate-700 block mb-2"></i>
                <p class="text-slate-500 text-sm">No items recorded for this shipment</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-700/60">
                            <th class="pb-2 text-left text-xs font-medium text-slate-500">Product</th>
                            <th class="pb-2 text-center text-xs font-medium text-slate-500">SKU</th>
                            <th class="pb-2 text-center text-xs font-medium text-slate-500">Qty</th>
                            <th class="pb-2 text-right text-xs font-medium text-slate-500">Price</th>
                            <th class="pb-2 text-right text-xs font-medium text-slate-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        <?php $items_total = 0; foreach ($shipment_items as $item):
                            $item_price = $item['price'] ?? 0;
                            $item_quantity = $item['quantity'] ?? 0;
                            $item_total = $item_price * $item_quantity;
                            $items_total += $item_total;
                        ?>
                        <tr>
                            <td class="py-2 text-sm text-white font-medium"><?php echo htmlspecialchars($item['product_name'] ?? 'Unknown Product'); ?></td>
                            <td class="py-2 text-center text-xs text-slate-400"><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                            <td class="py-2 text-center text-sm text-white"><?php echo $item_quantity; ?></td>
                            <td class="py-2 text-right text-xs text-slate-400"><?php echo $currency_symbol . ' ' . number_format($item_price, 2); ?></td>
                            <td class="py-2 text-right text-sm font-semibold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($item_total, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($items_total > 0): ?>
                    <tfoot>
                        <tr class="border-t border-slate-700/60">
                            <td colspan="4" class="pt-2 text-right text-xs font-semibold text-slate-400">Items Total:</td>
                            <td class="pt-2 text-right text-sm font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($items_total, 2); ?></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($tracking_history)): ?>
        <!-- Tracking History -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
                <i class="fas fa-history text-amber-400"></i> Tracking History
            </h2>
            <div class="space-y-3">
                <?php foreach ($tracking_history as $history): ?>
                <div class="flex items-start gap-3">
                    <div class="w-7 h-7 rounded-full bg-blue-500/10 flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fas fa-circle text-blue-400 text-[8px]"></i>
                    </div>
                    <div>
                        <p class="text-sm text-white font-medium"><?php echo htmlspecialchars($history['status'] ?? 'Update'); ?></p>
                        <?php if (!empty($history['location'])): ?><p class="text-xs text-slate-400"><?php echo htmlspecialchars($history['location']); ?></p><?php endif; ?>
                        <p class="text-xs text-slate-600"><?php echo isset($history['created_at']) ? date('d M Y H:i', strtotime($history['created_at'])) : ''; ?></p>
                        <?php if (!empty($history['notes'])): ?><p class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($history['notes']); ?></p><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right sidebar: Shipment Info + Timeline + Notes -->
    <div class="space-y-4">
        <!-- Shipment Information -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
                <i class="fas fa-info-circle text-amber-400"></i> Shipment Info
            </h2>
            <div class="divide-y divide-slate-700/60">
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Tracking #</span><span class="w-3/5 text-xs font-mono text-white"><?php echo htmlspecialchars($shipment['tracking_number'] ?? 'N/A'); ?></span></div>
                <?php if (!empty($shipment['sale_id'])): ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Invoice</span><span class="w-3/5 text-xs"><a href="view_sale.php?id=<?php echo $shipment['sale_id']; ?>" class="text-amber-400 hover:text-amber-300 hover:underline transition-colors"><?php echo htmlspecialchars($shipment['invoice_number'] ?? '#' . $shipment['sale_id']); ?></a></span></div>
                <?php endif; ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Courier</span><span class="w-3/5 text-xs text-white"><?php echo htmlspecialchars($shipment['courier'] ?? 'Not assigned'); ?></span></div>
                <?php if (!empty($shipment['courier_service'])): ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Service</span><span class="w-3/5 text-xs text-white"><?php echo htmlspecialchars($shipment['courier_service']); ?></span></div>
                <?php endif; ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Shipping</span><span class="w-3/5 text-xs text-white"><?php echo $currency_symbol . ' ' . number_format($shipment['shipping_cost'] ?? 0, 2); ?></span></div>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Total</span><span class="w-3/5 text-xs font-bold text-amber-400"><?php echo $currency_symbol . ' ' . number_format($shipment['total'] ?? 0, 2); ?></span></div>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Branch</span><span class="w-3/5 text-xs text-white"><?php echo htmlspecialchars($shipment['branch_name'] ?? $current_branch_name); ?></span></div>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Created</span><span class="w-3/5 text-xs text-white"><?php echo isset($shipment['created_at']) ? date('d M Y H:i', strtotime($shipment['created_at'])) : 'N/A'; ?></span></div>
                <?php if (!empty($shipment['shipped_at'])): ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Shipped</span><span class="w-3/5 text-xs text-white"><?php echo date('d M Y H:i', strtotime($shipment['shipped_at'])); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($shipment['delivered_at'])): ?>
                <div class="flex py-2"><span class="w-2/5 text-xs text-slate-500">Delivered</span><span class="w-3/5 text-xs text-white"><?php echo date('d M Y H:i', strtotime($shipment['delivered_at'])); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($shipment['estimated_delivery'])): ?>
                <div class="flex py-2 items-center">
                    <span class="w-2/5 text-xs text-slate-500">Est. Delivery</span>
                    <span class="w-3/5 text-xs <?php echo $is_late ? 'text-red-400' : 'text-white'; ?>">
                        <?php echo date('d M Y', strtotime($shipment['estimated_delivery'])); ?>
                        <?php if ($is_late && isset($shipment['status']) && $shipment['status'] == 'shipped'): ?>
                        <span class="ml-1 px-1.5 py-0.5 rounded-full bg-red-500/15 text-red-400 text-[10px]">Late</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timeline -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
                <i class="fas fa-clock text-amber-400"></i> Timeline
            </h2>
            <div class="relative pl-5">
                <?php
                $tl_items = [
                    ['label' => 'Created',   'date' => $shipment['created_at'] ?? null,   'done' => true],
                    ['label' => 'Shipped',   'date' => $shipment['shipped_at'] ?? null,    'done' => !empty($shipment['shipped_at']),   'current' => (isset($shipment['status']) && $shipment['status'] === 'shipped' && empty($shipment['shipped_at']))],
                    ['label' => 'Delivered', 'date' => $shipment['delivered_at'] ?? null,  'done' => !empty($shipment['delivered_at']), 'current' => (isset($shipment['status']) && $shipment['status'] === 'delivered' && empty($shipment['delivered_at']))],
                ];
                foreach ($tl_items as $idx => $tl):
                    $is_last = $idx === count($tl_items) - 1;
                    $dot = $tl['done'] ? 'bg-emerald-500' : ((!empty($tl['current'])) ? 'bg-amber-400 ring-2 ring-amber-400/30' : 'bg-slate-600');
                ?>
                <?php if (!$is_last): ?><div class="absolute left-[9px] top-[<?php echo $idx * 52 + 10; ?>px] w-0.5 h-10 bg-slate-700/60"></div><?php endif; ?>
                <div class="relative flex items-start gap-2.5 mb-4">
                    <div class="w-[10px] h-[10px] rounded-full <?php echo $dot; ?> shrink-0 mt-1"></div>
                    <div>
                        <p class="text-sm font-medium text-white"><?php echo $tl['label']; ?></p>
                        <p class="text-xs text-slate-500"><?php echo $tl['done'] && $tl['date'] ? date('d M Y H:i', strtotime($tl['date'])) : ((!empty($tl['current'])) ? '<span class="text-amber-400">In progress</span>' : 'Pending'); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($shipment['notes'])): ?>
        <!-- Notes -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-2">
                <i class="fas fa-sticky-note text-amber-400"></i> Notes
            </h2>
            <p class="text-sm text-slate-300 whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($shipment['notes'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<!-- Cancel Modal -->
<div id="cancelModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 max-w-sm w-full mx-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-amber-400"></i> Cancel Shipment
            </h3>
            <button onclick="closeCancelModal()" class="text-slate-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-slate-400 text-sm mb-3">Are you sure? This action cannot be undone.</p>
        <label class="block text-xs font-medium text-slate-400 mb-1">Reason (Optional)</label>
        <textarea id="cancelReason" rows="2"
            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none mb-4"
            placeholder="Enter reason for cancellation..."></textarea>
        <div class="flex gap-2.5">
            <button onclick="confirmCancel()"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold hover:bg-red-500/25 transition-colors">
                Cancel Shipment
            </button>
            <button onclick="closeCancelModal()"
                class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<div id="toastContainer" class="fixed bottom-4 right-4 z-[9999] space-y-2"></div>

<script>
    let shipmentId = <?php echo $shipment_id; ?>;

    function cancelShipment(id) { shipmentId = id; document.getElementById('cancelModal').classList.remove('hidden'); document.getElementById('cancelModal').classList.add('flex'); }
    function closeCancelModal() { document.getElementById('cancelModal').classList.add('hidden'); document.getElementById('cancelModal').classList.remove('flex'); }
    function confirmCancel() { const reason = document.getElementById('cancelReason').value; window.location.href = 'update_shipment_status.php?id=' + shipmentId + '&status=cancelled&reason=' + encodeURIComponent(reason); }
    document.getElementById('cancelModal')?.addEventListener('click', function (e) { if (e.target === this) closeCancelModal(); });

    function showToast(message, type = 'info') {
        const colors = { success: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400', error: 'bg-red-500/10 border-red-500/30 text-red-400', info: 'bg-amber-500/10 border-amber-500/30 text-amber-400' };
        const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `flex items-center gap-2 px-3 py-2 rounded-lg border text-sm ${colors[type] || colors.info}`;
        toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i><span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3000);
    }

    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.ctrlKey && e.key === 'e') { e.preventDefault(); window.location.href = 'process_shipment.php?id=' + shipmentId; }
        if (e.key === 'Escape') closeCancelModal();
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'updated') showToast('Shipment updated successfully', 'success');
    else if (urlParams.get('success') === 'cancelled') showToast('Shipment cancelled successfully', 'success');
    else if (urlParams.get('success') === 'shipped') showToast('Shipment marked as shipped', 'success');
    else if (urlParams.get('success') === 'delivered') showToast('Shipment marked as delivered', 'success');
    else if (urlParams.get('error')) showToast('Error: ' + urlParams.get('error'), 'error');
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
