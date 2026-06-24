<?php
/**
 * Shipments Management Page for Jakababa POS
 * Track and manage all customer shipments with dashboard color scheme
 */

require_once __DIR__ . '/../../src/auth.php';
require_login();
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

$page_title = 'Shipments';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$current_branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();

// Get all branches for super admin
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

// Pagination parameters
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : $current_branch_id;

// Verify user has access to selected branch
if (!is_super_admin() && !check_permission('branches.view')) {
    $branch_filter = $current_branch_id;
}

// Fetch shipments with pagination and filters
$shipments = [];
$total_shipments = 0;
$status_counts = ['pending' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0];

try {
    $pdo = get_db_connection();

    // Build query with filters
    $count_query = "SELECT COUNT(*) as count FROM shipments WHERE branch_id = :branch_id";
    $data_query = "
        SELECT s.*, 
               c.name as customer_name, 
               c.phone as customer_phone,
               c.email as customer_email,
               c.address as customer_address,
               c.city as customer_city,
               (SELECT COUNT(*) FROM shipment_items WHERE shipment_id = s.id) as item_count
        FROM shipments s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE s.branch_id = :branch_id";

    $params = [':branch_id' => $branch_filter];
    $data_params = [':branch_id' => $branch_filter];

    // Status filter
    if (!empty($status_filter)) {
        $count_query .= " AND status = :status";
        $data_query .= " AND s.status = :status";
        $params[':status'] = $status_filter;
        $data_params[':status'] = $status_filter;
    }

    // Date range filter
    if (!empty($date_from)) {
        $count_query .= " AND DATE(s.created_at) >= :date_from";
        $data_query .= " AND DATE(s.created_at) >= :date_from";
        $params[':date_from'] = $date_from;
        $data_params[':date_from'] = $date_from;
    }

    if (!empty($date_to)) {
        $count_query .= " AND DATE(s.created_at) <= :date_to";
        $data_query .= " AND DATE(s.created_at) <= :date_to";
        $params[':date_to'] = $date_to;
        $data_params[':date_to'] = $date_to;
    }

    // Search filter
    if (!empty($search)) {
        $count_query .= " AND (s.tracking_number LIKE :search OR s.courier LIKE :search OR s.notes LIKE :search)";
        $data_query .= " AND (s.tracking_number LIKE :search OR s.courier LIKE :search OR s.notes LIKE :search OR c.name LIKE :search OR c.phone LIKE :search)";
        $params[':search'] = "%$search%";
        $data_params[':search'] = "%$search%";
    }

    // Get total count
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_shipments = $stmt->fetch()['count'];

    // Get shipments with pagination
    $data_query .= " ORDER BY 
        CASE s.status 
            WHEN 'pending' THEN 1 
            WHEN 'shipped' THEN 2 
            WHEN 'delivered' THEN 3 
            WHEN 'cancelled' THEN 4 
            ELSE 5 
        END,
        s.created_at DESC 
        LIMIT :limit OFFSET :offset";

    $data_params[':limit'] = $limit;
    $data_params[':offset'] = $offset;

    $stmt = $pdo->prepare($data_query);
    foreach ($data_params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $shipments = $stmt->fetchAll();

    // Get status counts for summary
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM shipments 
        WHERE branch_id = :branch_id
        GROUP BY status
    ");
    $stmt->execute([':branch_id' => $branch_filter]);
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = (int) $row['count'];
    }

} catch (PDOException $e) {
    error_log("Error fetching shipments: " . $e->getMessage());
    $error = "Failed to load shipment data: " . $e->getMessage();
}

$total_pages = ceil($total_shipments / $limit);
$currency_symbol = get_settings('currency') ?? 'KSh';

// Status badge configurations
$status_config = [
    'pending'   => ['bg' => 'bg-amber-500/15',   'text' => 'text-amber-400',   'ring' => 'ring-amber-500/30',   'icon' => 'clock',        'label' => 'Pending'],
    'shipped'   => ['bg' => 'bg-blue-500/15',    'text' => 'text-blue-400',    'ring' => 'ring-blue-500/30',    'icon' => 'truck',        'label' => 'Shipped'],
    'delivered' => ['bg' => 'bg-emerald-500/15', 'text' => 'text-emerald-400', 'ring' => 'ring-emerald-500/30', 'icon' => 'check-circle', 'label' => 'Delivered'],
    'cancelled' => ['bg' => 'bg-red-500/15',     'text' => 'text-red-400',     'ring' => 'ring-red-500/30',     'icon' => 'times-circle', 'label' => 'Cancelled'],
];
?>
<?php
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-truck text-amber-400"></i> Shipment Management
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Track and manage customer deliveries</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="exportShipments()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-colors">
            <i class="fas fa-download text-xs"></i> Export
        </button>
        <a href="create_shipment.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> New Shipment
        </a>
    </div>
</div>

<!-- Summary cards -->
<?php
$ship_branch_qs = isset($_GET['branch_id']) ? '&branch_id=' . (int)$_GET['branch_id'] : '';
$ship_cards = [
    ['status'=>'pending',   'label'=>'Pending',   'count'=>$status_counts['pending'],   'icon'=>'fa-clock',        'color'=>'text-amber-400',   'bg'=>'bg-amber-500/10',   'border_on'=>'border-amber-500/40',   'border_off'=>'border-slate-700/60'],
    ['status'=>'shipped',   'label'=>'Shipped',   'count'=>$status_counts['shipped'],   'icon'=>'fa-truck',        'color'=>'text-blue-400',    'bg'=>'bg-blue-500/10',    'border_on'=>'border-blue-500/40',    'border_off'=>'border-slate-700/60'],
    ['status'=>'delivered', 'label'=>'Delivered', 'count'=>$status_counts['delivered'], 'icon'=>'fa-check-circle', 'color'=>'text-emerald-400', 'bg'=>'bg-emerald-500/10', 'border_on'=>'border-emerald-500/40', 'border_off'=>'border-slate-700/60'],
    ['status'=>'cancelled', 'label'=>'Cancelled', 'count'=>$status_counts['cancelled'], 'icon'=>'fa-times-circle', 'color'=>'text-red-400',     'bg'=>'bg-red-500/10',     'border_on'=>'border-red-500/40',     'border_off'=>'border-slate-700/60'],
];
?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <?php foreach ($ship_cards as $card):
        $active = $status_filter === $card['status'];
    ?>
    <a href="?status=<?php echo $card['status']; ?><?php echo $ship_branch_qs; ?>"
       class="bg-slate-800/50 border <?php echo $active ? $card['border_on'] : $card['border_off']; ?> rounded-xl p-3 flex items-center gap-2.5 hover:border-slate-600 transition-colors">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?>"><?php echo number_format($card['count']); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4">
    <form method="GET" class="flex flex-wrap gap-2 items-end">
        <?php if (!empty($branches)): ?>
            <input type="hidden" name="branch_id" value="<?php echo $branch_filter; ?>">
        <?php endif; ?>
        <div class="relative flex-1 min-w-[180px]">
            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Tracking #, courier, customer…"
                   class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
        <select name="status" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            <option value="">All Status</option>
            <option value="pending"   <?php echo $status_filter === 'pending'   ? 'selected' : ''; ?>>Pending</option>
            <option value="shipped"   <?php echo $status_filter === 'shipped'   ? 'selected' : ''; ?>>Shipped</option>
            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        <input type="date" name="date_from" value="<?php echo $date_from; ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        <input type="date" name="date_to" value="<?php echo $date_to; ?>"
               class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-filter mr-1 text-xs"></i>Filter
        </button>
        <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
        <a href="shipments.php<?php echo !empty($branches) ? '?branch_id=' . $branch_filter : ''; ?>"
           class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
            <i class="fas fa-times mr-1 text-xs"></i>Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Shipments list -->
<?php if (empty($shipments)): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-10 text-center">
    <i class="fas fa-truck text-4xl text-slate-700 block mb-3"></i>
    <p class="text-slate-500 text-sm mb-3"><?php echo !empty($search) ? 'No matches for your search.' : 'No shipments found. Create your first shipment.'; ?></p>
    <?php if (!empty($search) || !empty($status_filter)): ?>
    <a href="shipments.php<?php echo !empty($branches) ? '?branch_id=' . $branch_filter : ''; ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm hover:bg-slate-600 transition-colors">
        <i class="fas fa-times text-xs"></i> Clear filters
    </a>
    <?php else: ?>
    <a href="create_shipment.php"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm hover:bg-amber-500/20 transition-colors">
        <i class="fas fa-plus text-xs"></i> Create Shipment
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="space-y-2.5">
    <?php foreach ($shipments as $shipment):
        $status = $status_config[$shipment['status']] ?? ['bg'=>'bg-slate-500/15','text'=>'text-slate-400','ring'=>'ring-slate-500/30','icon'=>'file','label'=>'Unknown'];
        $is_late = $shipment['estimated_delivery'] && strtotime($shipment['estimated_delivery']) < time() && $shipment['status'] === 'pending';
    ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 hover:border-slate-600 transition-colors">
        <!-- Card top row -->
        <div class="flex flex-wrap justify-between items-start gap-3 mb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
                    <i class="fas fa-truck text-amber-400 text-xs"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-sm font-semibold text-amber-400"><?php echo htmlspecialchars($shipment['tracking_number'] ?: 'N/A'); ?></span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium <?php echo $status['bg']; ?> <?php echo $status['text']; ?> ring-1 <?php echo $status['ring']; ?>">
                            <i class="fas fa-<?php echo $status['icon']; ?> text-[10px]"></i><?php echo $status['label']; ?>
                        </span>
                        <?php if ($is_late): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/15 text-red-400 ring-1 ring-red-500/30">
                            <i class="fas fa-exclamation-circle text-[10px]"></i>Late
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm font-medium text-white mt-0.5"><?php echo htmlspecialchars($shipment['customer_name'] ?? 'Unknown Customer'); ?></div>
                </div>
            </div>
            <div class="text-right text-xs text-slate-500">
                <div><?php echo date('d M Y', strtotime($shipment['created_at'])); ?></div>
                <div><?php echo date('H:i', strtotime($shipment['created_at'])); ?></div>
            </div>
        </div>

        <!-- Info grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-3">
            <div class="bg-slate-900/50 rounded-lg p-2.5">
                <p class="text-xs text-slate-500 mb-1">Delivery Address</p>
                <p class="text-sm text-slate-200"><?php echo htmlspecialchars($shipment['address'] ?: $shipment['customer_address'] ?: 'No address'); ?></p>
                <?php if (!empty($shipment['customer_phone'])): ?>
                <p class="text-xs text-slate-500 mt-1"><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($shipment['customer_phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-2.5">
                <p class="text-xs text-slate-500 mb-1">Courier</p>
                <p class="text-sm font-medium text-slate-200"><?php echo htmlspecialchars($shipment['courier'] ?? 'Not assigned'); ?></p>
                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($shipment['courier_service'] ?? 'Standard'); ?></p>
                <?php if (!empty($shipment['tracking_url'])): ?>
                <a href="<?php echo htmlspecialchars($shipment['tracking_url']); ?>" target="_blank" class="text-xs text-amber-400 hover:text-amber-300 transition-colors">
                    Track <i class="fas fa-external-link-alt ml-0.5 text-[10px]"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="bg-slate-900/50 rounded-lg p-2.5">
                <p class="text-xs text-slate-500 mb-1">Shipment Details</p>
                <div class="flex justify-between text-xs text-slate-400"><span>Items</span><span class="font-semibold text-slate-200"><?php echo $shipment['item_count']; ?></span></div>
                <div class="flex justify-between text-xs text-slate-400 mt-0.5"><span>Shipping</span><span><?php echo $currency_symbol . number_format($shipment['shipping_cost'] ?? 0, 2); ?></span></div>
                <div class="flex justify-between text-xs font-semibold text-amber-400 mt-0.5"><span>Total</span><span><?php echo $currency_symbol . number_format($shipment['total'], 2); ?></span></div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-1.5 pt-2.5 border-t border-slate-700/60">
            <a href="view_shipment.php?id=<?php echo $shipment['id']; ?>"
               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-amber-500/20 hover:text-amber-400 transition-colors" title="View">
                <i class="fas fa-eye text-xs"></i>
            </a>
            <?php if ($shipment['status'] === 'pending'): ?>
            <a href="update_shipment_status.php?id=<?php echo $shipment['id']; ?>&status=shipped"
               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-blue-500/20 hover:text-blue-400 transition-colors" title="Mark Shipped"
               onclick="return confirm('Mark as shipped?')">
                <i class="fas fa-truck text-xs"></i>
            </a>
            <button onclick="cancelShipment(<?php echo $shipment['id']; ?>)"
                    class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors" title="Cancel">
                <i class="fas fa-times text-xs"></i>
            </button>
            <?php endif; ?>
            <?php if ($shipment['status'] === 'shipped'): ?>
            <a href="update_shipment_status.php?id=<?php echo $shipment['id']; ?>&status=delivered"
               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-emerald-500/20 hover:text-emerald-400 transition-colors" title="Mark Delivered"
               onclick="return confirm('Mark as delivered?')">
                <i class="fas fa-check-circle text-xs"></i>
            </a>
            <a href="track_shipment.php?id=<?php echo $shipment['id']; ?>"
               class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-amber-500/20 hover:text-amber-400 transition-colors" title="Track">
                <i class="fas fa-map-marked-alt text-xs"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($total_pages > 1): ?>
<?php $pqs = 'status=' . urlencode($status_filter) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&search=' . urlencode($search) . (isset($_GET['branch_id']) ? '&branch_id=' . (int)$_GET['branch_id'] : ''); ?>
<div class="flex items-center justify-between mt-4">
    <p class="text-xs text-slate-500">Showing <span class="text-slate-300 font-medium"><?php echo $offset + 1; ?>&ndash;<?php echo min($offset + $limit, $total_shipments); ?></span> of <span class="text-slate-300 font-medium"><?php echo $total_shipments; ?></span></p>
    <div class="flex gap-1">
        <a href="?page=<?php echo max(1, $page-1); ?>&<?php echo $pqs; ?>"
           class="px-3 py-1.5 rounded-lg text-sm border <?php echo $page <= 1 ? 'border-slate-700/40 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'; ?> transition-colors">
            <i class="fas fa-chevron-left text-xs"></i>
        </a>
        <?php for ($i = 1; $i <= $total_pages; $i++): if ($i >= $page - 2 && $i <= $page + 2): ?>
        <a href="?page=<?php echo $i; ?>&<?php echo $pqs; ?>"
           class="px-3 py-1.5 rounded-lg text-sm border <?php echo $i === $page ? 'bg-amber-500/20 border-amber-500/40 text-amber-400 font-semibold' : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'; ?> transition-colors">
            <?php echo $i; ?>
        </a>
        <?php endif; endfor; ?>
        <a href="?page=<?php echo min($total_pages, $page+1); ?>&<?php echo $pqs; ?>"
           class="px-3 py-1.5 rounded-lg text-sm border <?php echo $page >= $total_pages ? 'border-slate-700/40 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'; ?> transition-colors">
            <i class="fas fa-chevron-right text-xs"></i>
        </a>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Cancel Modal -->
<div id="cancelModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 max-w-sm w-full mx-4">
        <div class="flex items-center gap-2.5 mb-3">
            <i class="fas fa-exclamation-triangle text-amber-400"></i>
            <h3 class="text-base font-semibold text-white">Cancel Shipment</h3>
        </div>
        <p class="text-slate-400 text-sm mb-3">Are you sure? This action cannot be undone.</p>
        <textarea id="cancelReason" rows="2"
            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none mb-4"
            placeholder="Reason (optional)"></textarea>
        <div class="flex gap-2.5">
            <button onclick="confirmCancel()"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-semibold hover:bg-red-500/25 transition-colors">
                Yes, Cancel
            </button>
            <button onclick="closeCancelModal()"
                class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="exportModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 max-w-sm w-full mx-4">
        <div class="flex items-center gap-2.5 mb-4">
            <i class="fas fa-download text-emerald-400"></i>
            <h3 class="text-base font-semibold text-white">Export Shipments</h3>
        </div>
        <form id="exportForm" action="export_shipments.php" method="POST">
            <input type="hidden" name="branch_id" value="<?php echo $branch_filter; ?>">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="hidden" name="date_from" value="<?php echo $date_from; ?>">
            <input type="hidden" name="date_to" value="<?php echo $date_to; ?>">
            <input type="hidden" name="search" value="<?php echo $search; ?>">
            <div class="mb-4">
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Format</label>
                <select name="format" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <option value="csv">CSV (Excel)</option>
                    <option value="pdf">PDF Report</option>
                </select>
            </div>
            <div class="flex gap-2.5">
                <button type="submit"
                    class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-semibold hover:bg-emerald-500/25 transition-colors">
                    <i class="fas fa-download text-xs"></i>Download
                </button>
                <button type="button" onclick="closeExportModal()"
                    class="flex-1 inline-flex items-center justify-center px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-[9999] space-y-2"></div>

<script>
    let shipmentToCancel = null;

    function confirmCancel() {
        if (shipmentToCancel) {
            const reason = document.getElementById('cancelReason').value;
            window.location.href = 'cancel_shipment.php?id=' + shipmentToCancel + '&reason=' + encodeURIComponent(reason);
        }
    }

    document.addEventListener('click', function (e) {
        const cancelModal = document.getElementById('cancelModal');
        const exportModal = document.getElementById('exportModal');
        if (e.target === cancelModal) closeCancelModal();
        if (e.target === exportModal) closeExportModal();
    });

    function cancelShipment(id) {
        shipmentToCancel = id;
        document.getElementById('cancelModal').classList.remove('hidden');
        document.getElementById('cancelModal').classList.add('flex');
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').classList.add('hidden');
        document.getElementById('cancelModal').classList.remove('flex');
        document.getElementById('cancelReason').value = '';
        shipmentToCancel = null;
    }

    function exportShipments() {
        document.getElementById('exportModal').classList.remove('hidden');
        document.getElementById('exportModal').classList.add('flex');
    }

    function closeExportModal() {
        document.getElementById('exportModal').classList.add('hidden');
        document.getElementById('exportModal').classList.remove('flex');
    }

    function showToast(message, type = 'success') {
        const colors = { success: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400', error: 'bg-red-500/10 border-red-500/30 text-red-400', info: 'bg-amber-500/10 border-amber-500/30 text-amber-400' };
        const icons = { success: 'check-circle', error: 'exclamation-circle', info: 'info-circle' };
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `flex items-center gap-2 px-3 py-2 rounded-lg border text-sm ${colors[type] || colors.info}`;
        toast.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i>${message}`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3000);
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'created') {
        showToast('Shipment created successfully', 'success');
    } else if (urlParams.get('success') === 'updated') {
        showToast('Shipment updated successfully', 'success');
    } else if (urlParams.get('success') === 'cancelled') {
        showToast('Shipment cancelled', 'info');
    } else if (urlParams.get('success') === 'status_updated') {
        showToast('Status updated', 'success');
    } else if (urlParams.get('error')) {
        showToast('Error: ' + urlParams.get('error'), 'error');
    }
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
