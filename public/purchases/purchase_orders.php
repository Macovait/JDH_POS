<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('purchase_orders.manage') && !is_super_admin()) {
    enforce_permission('purchase_orders.manage');
}

$pdo        = get_db_connection();
$tenant_id  = get_current_tenant_id();
if (!$tenant_id) { http_response_code(403); exit('Unauthorized'); }

$user_id            = (int)($_SESSION['user']['id'] ?? 0);
$user_role          = $_SESSION['user']['role'] ?? '';
$current_branch_id  = get_current_branch_id();
$current_branch_name= get_current_branch_name();

// AJAX: status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json');
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid security token']); exit;
    }
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'error' => 'Invalid action'];
    if ($action === 'update_status') {
        $po_id  = (int)($_POST['po_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $valid_statuses = ['pending', 'approved', 'received', 'cancelled'];
        if ($po_id > 0 && in_array($status, $valid_statuses)) {
            try {
                $stmt = $pdo->prepare('UPDATE purchase_orders SET status=?, updated_at=NOW() WHERE id=? AND tenant_id=?');
                $stmt->execute([$status, $po_id, $tenant_id]);
                log_activity($user_id, 'purchase_order.updated', ['po_id' => $po_id, 'status' => $status], $tenant_id);
                $response = ['success' => true, 'message' => 'Status updated to ' . ucfirst($status), 'status' => $status, 'po_id' => $po_id];
            } catch (PDOException $e) {
                error_log("PO status AJAX error: " . $e->getMessage());
                $response = ['success' => false, 'error' => 'Database error'];
            }
        } else {
            $response = ['success' => false, 'error' => 'Invalid parameters'];
        }
    }
    echo json_encode($response); exit;
}

// POST: create / delete
$redirect_msg  = '';
$redirect_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $redirect_msg = 'Invalid security token'; $redirect_type = 'error';
    } else {
        $action = $_POST['action'] ?? 'create';
        switch ($action) {
            case 'create':
                $supplier_id   = (int)($_POST['supplier_id'] ?? 0);
                $branch_id     = (int)($_POST['branch_id'] ?? $current_branch_id);
                $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
                $notes         = trim($_POST['notes'] ?? '');
                if ($supplier_id > 0) {
                    try {
                        $stmt = $pdo->prepare('INSERT INTO purchase_orders (supplier_id, branch_id, expected_date, notes, status, created_by, tenant_id, created_at) VALUES (?,?,?,?,?,?,?,NOW())');
                        $stmt->execute([$supplier_id, $branch_id, $expected_date, $notes, 'pending', $user_id, $tenant_id]);
                        $po_id = $pdo->lastInsertId();
                        log_activity($user_id, 'purchase_order.created', ['po_id' => $po_id], $tenant_id);
                        header("Location: purchase_order_items.php?po_id=$po_id"); exit;
                    } catch (PDOException $e) {
                        error_log("PO create error: " . $e->getMessage());
                        $redirect_msg = 'Failed to create purchase order'; $redirect_type = 'error';
                    }
                } else {
                    $redirect_msg = 'Please select a supplier'; $redirect_type = 'error';
                }
                break;
            case 'delete':
                if (is_super_admin() || $user_role === 'Admin') {
                    $po_id = (int)($_POST['po_id'] ?? 0);
                    if ($po_id > 0) {
                        try {
                            $pdo->prepare('DELETE FROM purchase_order_items WHERE purchase_order_id=? AND (tenant_id=? OR tenant_id IS NULL)')->execute([$po_id, $tenant_id]);
                            $pdo->prepare('DELETE FROM purchase_orders WHERE id=? AND tenant_id=?')->execute([$po_id, $tenant_id]);
                            $redirect_msg = 'Purchase order deleted successfully';
                            log_activity($user_id, 'purchase_order.deleted', ['po_id' => $po_id], $tenant_id);
                        } catch (PDOException $e) {
                            error_log("PO delete error: " . $e->getMessage());
                            $redirect_msg = 'Failed to delete purchase order'; $redirect_type = 'error';
                        }
                    }
                }
                break;
        }
    }
    if ($redirect_msg) {
        $param = $redirect_type === 'error' ? 'error' : 'success';
        header('Location: purchase_orders.php?' . http_build_query(array_merge($_GET, [$param => $redirect_msg]))); exit;
    }
}

$success_message = htmlspecialchars($_GET['success'] ?? '');
$error_message   = htmlspecialchars($_GET['error'] ?? '');

// Filters
$status_filter   = $_GET['status'] ?? 'all';
$supplier_filter = (int)($_GET['supplier'] ?? 0);
$branch_filter   = (int)($_GET['branch'] ?? 0);
$date_from       = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to         = $_GET['date_to'] ?? date('Y-m-d');
$search          = trim($_GET['search'] ?? '');

// Main query — tenant-scoped
$sql = "
    SELECT po.id, po.status, po.expected_date, po.created_at, po.notes,
        po.total as total_value,
        s.name as supplier_name, s.id as supplier_id,
        b.name as branch_name, b.id as branch_id,
        (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=po.id) as item_count,
        (SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=po.id AND received_quantity>0) as received_items,
        DATEDIFF(po.expected_date, CURDATE()) as days_until_expected
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id=s.id
    JOIN branches b ON po.branch_id=b.id
    WHERE po.tenant_id=?
";
$params = [$tenant_id];

if ($status_filter !== 'all') { $sql .= " AND po.status=?"; $params[] = $status_filter; }
if ($supplier_filter > 0)     { $sql .= " AND po.supplier_id=?"; $params[] = $supplier_filter; }
if ($branch_filter > 0)       { $sql .= " AND po.branch_id=?"; $params[] = $branch_filter; }
if (!empty($date_from) && !empty($date_to)) { $sql .= " AND DATE(po.created_at) BETWEEN ? AND ?"; $params[] = $date_from; $params[] = $date_to; }
if (!empty($search)) { $sql .= " AND (s.name LIKE ? OR po.notes LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$sql .= " ORDER BY po.id DESC";

$stmt = $pdo->prepare($sql); $stmt->execute($params);
$purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Suppliers & branches scoped to tenant
$suppliers = $pdo->prepare('SELECT id, name FROM suppliers WHERE active=1 AND (tenant_id=? OR tenant_id IS NULL) ORDER BY name');
$suppliers->execute([$tenant_id]); $suppliers = $suppliers->fetchAll(PDO::FETCH_ASSOC);

$branches = $pdo->prepare('SELECT id, name FROM branches WHERE active=1 AND (tenant_id=? OR tenant_id IS NULL) ORDER BY name');
$branches->execute([$tenant_id]); $branches = $branches->fetchAll(PDO::FETCH_ASSOC);

// Stats — tenant-scoped
$stats_stmt = $pdo->prepare("
    SELECT COUNT(*) as total_pos,
        SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='approved'  THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status='received'  THEN 1 ELSE 0 END) as received_count,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        COALESCE(SUM(CASE WHEN status='received' THEN total ELSE 0 END), 0) as total_received_value
    FROM purchase_orders WHERE tenant_id=?
");
$stats_stmt->execute([$tenant_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Purchase Orders | ' . (defined('APP_NAME') ? APP_NAME : 'JDH POS');
$can_delete = is_super_admin() || $user_role === 'Admin';
// Fresh CSRF token for rendered forms (generated AFTER POST verification above)
$csrf_token = generate_csrf_token();
ob_start();
?>

<div class="fade-in">

<!-- ═══ Header ═══════════════════════════════════════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">Purchases</p>
        <h1 class="text-lg font-bold text-white">Purchase Orders</h1>
        <p class="text-xs text-slate-500 mt-0.5">Manage supplier orders in <span class="text-amber-400"><?php echo htmlspecialchars($current_branch_name ?? 'All Branches'); ?></span></p>
    </div>
    <div class="flex items-center gap-2">
        <button onclick="openCreateModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-xs font-semibold hover:bg-amber-400 transition-colors">
            <i class="fas fa-plus text-[10px]"></i> New Purchase Order
        </button>
    </div>
</div>

<?php if ($success_message): ?>
<div class="mb-4 flex items-center gap-2 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs" id="flash-success">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-xs">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<!-- ═══ KPI Cards ════════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
    <?php
    $kpi_cards = [
        ['fas fa-file-invoice', 'amber-400',   'Total POs',      number_format($stats['total_pos']),         ''],
        ['fas fa-clock',        'yellow-400',  'Pending',         number_format($stats['pending_count']),     ''],
        ['fas fa-check-circle', 'blue-400',    'Approved',        number_format($stats['approved_count']),    ''],
        ['fas fa-check-double', 'emerald-400', 'Received',        number_format($stats['received_count']),    ''],
        ['fas fa-ban',          'slate-400',   'Cancelled',       number_format($stats['cancelled_count']),   ''],
        ['fas fa-coins',        'emerald-400', 'Received Value',  format_currency((float)$stats['total_received_value']), ''],
    ];
    foreach ($kpi_cards as [$icon, $color, $label, $val, $sub]):
    ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-lg p-3">
        <div class="flex items-center gap-2 mb-1.5">
            <div class="w-6 h-6 rounded bg-slate-700/60 flex items-center justify-center flex-shrink-0">
                <i class="<?php echo $icon; ?> text-<?php echo $color; ?> text-[10px]"></i>
            </div>
            <p class="text-[10px] text-slate-500 uppercase tracking-wide"><?php echo $label; ?></p>
        </div>
        <p class="text-sm font-bold text-white"><?php echo $val; ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══ Filters ═══════════════════════════════════════════════════════ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-lg p-3 mb-5">
    <form method="GET" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-2">
        <div>
            <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Supplier / notes…"
                class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs placeholder-slate-600 focus:outline-none focus:border-amber-500/50">
        </div>
        <div>
            <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Status</label>
            <select name="status" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
                <option value="all" <?php echo $status_filter==='all'?'selected':''; ?>>All</option>
                <option value="pending"   <?php echo $status_filter==='pending'  ?'selected':''; ?>>Pending</option>
                <option value="approved"  <?php echo $status_filter==='approved' ?'selected':''; ?>>Approved</option>
                <option value="received"  <?php echo $status_filter==='received' ?'selected':''; ?>>Received</option>
                <option value="cancelled" <?php echo $status_filter==='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Supplier</label>
            <select name="supplier" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
                <option value="0">All Suppliers</option>
                <?php foreach ($suppliers as $sup): ?>
                <option value="<?php echo $sup['id']; ?>" <?php echo $supplier_filter==$sup['id']?'selected':''; ?>><?php echo htmlspecialchars($sup['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Branch</label>
            <select name="branch" class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
                <option value="0">All Branches</option>
                <?php foreach ($branches as $br): ?>
                <option value="<?php echo $br['id']; ?>" <?php echo $branch_filter==$br['id']?'selected':''; ?>><?php echo htmlspecialchars($br['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">From</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
        </div>
        <div>
            <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">To</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
        </div>
        <div class="flex items-end gap-1.5">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-400 text-xs font-semibold hover:bg-amber-500/30 transition-colors">
                <i class="fas fa-filter text-[9px]"></i> Filter
            </button>
            <?php if ($status_filter!=='all'||$supplier_filter>0||$branch_filter>0||!empty($search)||$date_from!==date('Y-m-d',strtotime('-30 days'))||$date_to!==date('Y-m-d')): ?>
            <a href="purchase_orders.php" class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-700 border border-slate-600 text-slate-400 hover:text-white hover:bg-slate-600 transition-colors" title="Clear filters">
                <i class="fas fa-times text-[9px]"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ═══ Table ═══════════════════════════════════════════════════════ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-lg overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2 border-b border-slate-700/60">
        <p class="text-xs font-semibold text-amber-400 uppercase tracking-wider">
            <i class="fas fa-file-invoice mr-1"></i> Orders
            <span class="ml-2 text-slate-500 font-normal normal-case"><?php echo count($purchase_orders); ?> result<?php echo count($purchase_orders)!==1?'s':''; ?></span>
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-slate-700/60">
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider whitespace-nowrap">PO #</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider">Supplier</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider">Branch</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider whitespace-nowrap">Created</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider whitespace-nowrap">Expected</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider">Items</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider">Total</th>
                    <th class="px-4 py-2 text-left text-[10px] font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
<?php if (empty($purchase_orders)): ?>
                <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500 text-xs">
                    <i class="fas fa-file-invoice text-3xl mb-2 block text-slate-700"></i>
                    No purchase orders found. <button onclick="openCreateModal()" class="text-amber-400 hover:underline">Create one</button>.
                </td></tr>
<?php else: foreach ($purchase_orders as $po):
    $s = $po['status'];
    $bmap = [
        'pending'  => ['bg-yellow-500/15 border-yellow-500/30 text-yellow-400',  'fa-clock'],
        'approved' => ['bg-blue-500/15 border-blue-500/30 text-blue-400',         'fa-check-circle'],
        'received' => ['bg-emerald-500/15 border-emerald-500/30 text-emerald-400','fa-check-double'],
        'cancelled'=> ['bg-slate-500/15 border-slate-500/30 text-slate-400',      'fa-ban'],
    ];
    [$badge_cls, $badge_icon] = $bmap[$s] ?? ['bg-slate-500/15 border-slate-500/30 text-slate-400','fa-circle'];
    $days = $po['days_until_expected'];
?>
                <tr class="hover:bg-slate-700/20 transition-colors" data-po-id="<?php echo $po['id']; ?>">
                    <td class="px-4 py-2"><span class="font-mono font-semibold text-amber-400">#<?php echo str_pad($po['id'],5,'0',STR_PAD_LEFT); ?></span></td>
                    <td class="px-4 py-2 text-white"><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                    <td class="px-4 py-2 text-slate-400"><?php echo htmlspecialchars($po['branch_name']); ?></td>
                    <td class="px-4 py-2 status-cell">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] border <?php echo $badge_cls; ?>">
                            <i class="fas <?php echo $badge_icon; ?>"></i> <?php echo ucfirst($s); ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-slate-400 whitespace-nowrap"><?php echo date('d M Y', strtotime($po['created_at'])); ?></td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <?php if ($po['expected_date']):
                            $dc = $days < 0 ? 'text-red-400' : ($days < 3 ? 'text-yellow-400' : 'text-slate-400'); ?>
                            <span class="<?php echo $dc; ?>"><?php echo date('d M Y', strtotime($po['expected_date'])); ?></span>
                            <?php if ($s==='pending'&&$days<0): ?>
                                <span class="block text-[10px] text-red-400"><i class="fas fa-exclamation-circle mr-0.5"></i>Overdue</span>
                            <?php elseif ($s==='pending'&&$days<3): ?>
                                <span class="block text-[10px] text-yellow-400"><i class="fas fa-clock mr-0.5"></i>Soon</span>
                            <?php endif; ?>
                        <?php else: ?><span class="text-slate-600">—</span><?php endif; ?>
                    </td>
                    <td class="px-4 py-2">
                        <span class="font-semibold text-white"><?php echo (int)$po['item_count']; ?></span>
                        <?php if ($po['received_items'] > 0): ?>
                            <span class="block text-[10px] text-emerald-400"><i class="fas fa-check-circle mr-0.5"></i><?php echo (int)$po['received_items']; ?> rcvd</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2 font-semibold text-emerald-400"><?php echo format_currency((float)$po['total_value']); ?></td>
                    <td class="px-4 py-2 actions-cell">
                        <div class="flex items-center gap-1">
                            <a href="purchase_order_items.php?po_id=<?php echo $po['id']; ?>"
                                class="p-1.5 rounded bg-slate-700/60 border border-slate-600 text-slate-300 hover:text-amber-400 hover:border-amber-500/40 transition-colors"
                                title="Manage items"><i class="fas fa-list-ul text-[9px]"></i></a>
                            <?php if ($s==='pending'||$s==='approved'): ?>
                            <a href="purchase_receive.php?po_id=<?php echo $po['id']; ?>"
                                class="p-1.5 rounded bg-slate-700/60 border border-slate-600 text-slate-300 hover:text-emerald-400 hover:border-emerald-500/40 transition-colors"
                                title="Receive items"><i class="fas fa-arrow-down text-[9px]"></i></a>
                            <?php endif; ?>
                            <?php if ($s!=='received'&&$s!=='cancelled'): ?>
                            <div class="relative">
                                <button onclick="toggleDropdown(<?php echo $po['id']; ?>)"
                                    class="p-1.5 rounded bg-slate-700/60 border border-slate-600 text-slate-300 hover:text-amber-400 hover:border-amber-500/40 transition-colors"
                                    title="Change status"><i class="fas fa-chevron-down text-[9px]"></i></button>
                                <div id="dropdown-<?php echo $po['id']; ?>"
                                    class="hidden absolute right-0 mt-1 w-36 bg-slate-800 border border-slate-700 rounded-lg shadow-xl z-50">
                                    <div class="p-1 space-y-0.5">
                                        <?php if ($s==='pending'): ?>
                                        <button onclick="updateStatus(event,<?php echo $po['id']; ?>,'approved')"
                                            class="w-full text-left px-3 py-1.5 text-xs text-slate-200 hover:bg-blue-500/10 hover:text-blue-400 rounded flex items-center gap-2">
                                            <i class="fas fa-check-circle text-blue-400 w-3"></i> Approve
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="updateStatus(event,<?php echo $po['id']; ?>,'cancelled')"
                                            class="w-full text-left px-3 py-1.5 text-xs text-slate-200 hover:bg-red-500/10 hover:text-red-400 rounded flex items-center gap-2">
                                            <i class="fas fa-ban text-red-400 w-3"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($can_delete && $s==='pending'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete PO #<?php echo $po['id']; ?>?')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                                <button type="submit"
                                    class="p-1.5 rounded bg-slate-700/60 border border-slate-600 text-slate-300 hover:text-red-400 hover:border-red-500/40 transition-colors"
                                    title="Delete PO"><i class="fas fa-trash-alt text-[9px]"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
<?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ Create PO Modal ══════════════════════════════════════════════ -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <div class="bg-slate-800 border border-slate-700 rounded-lg p-5 w-full max-w-md mx-4 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-file-invoice text-amber-400"></i> New Purchase Order
            </h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-white transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Supplier *</label>
                <select name="supplier_id" required class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
                    <option value="">Select Supplier</option>
                    <?php foreach ($suppliers as $sup): ?>
                    <option value="<?php echo $sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Branch *</label>
                <select name="branch_id" required class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
                    <option value="">Select Branch</option>
                    <?php foreach ($branches as $br): ?>
                    <option value="<?php echo $br['id']; ?>" <?php echo $br['id']==$current_branch_id?'selected':''; ?>><?php echo htmlspecialchars($br['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Expected Date</label>
                <input type="date" name="expected_date" min="<?php echo date('Y-m-d'); ?>"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50">
            </div>
            <div>
                <label class="block text-[10px] text-slate-500 uppercase tracking-wide mb-1">Notes (Optional)</label>
                <textarea name="notes" rows="2" placeholder="Special instructions…"
                    class="w-full px-2.5 py-1.5 rounded-lg bg-slate-900/60 border border-slate-700 text-white text-xs focus:outline-none focus:border-amber-500/50 resize-none"></textarea>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-400 text-xs font-semibold hover:bg-amber-500/30 transition-colors">
                    <i class="fas fa-save text-[9px]"></i> Create &amp; Add Items
                </button>
                <button type="button" onclick="closeCreateModal()" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-xs hover:bg-slate-600 transition-colors">
                    <i class="fas fa-times text-[9px]"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Toast ═════════════════════════════════════════════════════════ -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-[9999] space-y-2 pointer-events-none"></div>

<script>
const PO_CSRF = <?php echo json_encode($csrf_token); ?>;

function openCreateModal()  { document.getElementById('createModal').classList.remove('hidden'); }
function closeCreateModal() { document.getElementById('createModal').classList.add('hidden'); }
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
});

function toggleDropdown(poId) {
    document.querySelectorAll('[id^="dropdown-"]').forEach(function(d) {
        if (d.id !== 'dropdown-' + poId) d.classList.add('hidden');
    });
    var d = document.getElementById('dropdown-' + poId);
    if (d) d.classList.toggle('hidden');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.relative')) {
        document.querySelectorAll('[id^="dropdown-"]').forEach(function(d) { d.classList.add('hidden'); });
    }
});

function updateStatus(event, poId, status) {
    var btn = event.target.closest('button');
    if (!btn) return;
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-[9px]"></i>';
    btn.disabled = true;

    var fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'update_status');
    fd.append('po_id', poId);
    fd.append('status', status);
    fd.append('csrf_token', PO_CSRF);

    fetch('purchase_orders.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message, 'success');
                updateStatusBadge(poId, status);
                var dd = document.getElementById('dropdown-' + poId);
                if (dd) dd.classList.add('hidden');
                updateActionButtons(poId, status);
            } else {
                showToast(data.error || 'Failed to update status', 'error');
            }
        })
        .catch(function() { showToast('Network error', 'error'); })
        .finally(function() { btn.innerHTML = orig; btn.disabled = false; });
}

var statusBadgeMap = {
    pending:   { cls: 'bg-yellow-500/15 border-yellow-500/30 text-yellow-400',  icon: 'fa-clock',        label: 'Pending'   },
    approved:  { cls: 'bg-blue-500/15 border-blue-500/30 text-blue-400',         icon: 'fa-check-circle', label: 'Approved'  },
    received:  { cls: 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400',icon: 'fa-check-double', label: 'Received'  },
    cancelled: { cls: 'bg-slate-500/15 border-slate-500/30 text-slate-400',      icon: 'fa-ban',          label: 'Cancelled' },
};

function updateStatusBadge(poId, newStatus) {
    var row = document.querySelector('tr[data-po-id="' + poId + '"]');
    if (!row) return;
    var cell = row.querySelector('.status-cell');
    if (!cell) return;
    var cfg = statusBadgeMap[newStatus] || statusBadgeMap.pending;
    cell.innerHTML = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] border ' + cfg.cls + '"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
}

function updateActionButtons(poId, newStatus) {
    var row = document.querySelector('tr[data-po-id="' + poId + '"]');
    if (!row) return;
    var cell = row.querySelector('.actions-cell');
    if (!cell) return;
    if (newStatus === 'received' || newStatus === 'cancelled') {
        var rel = cell.querySelector('.relative');
        if (rel) rel.remove();
    }
    if (newStatus !== 'pending' && newStatus !== 'approved') {
        var rcv = cell.querySelector('a[href*="purchase_receive.php"]');
        if (rcv) rcv.remove();
    }
    if (newStatus !== 'pending') {
        var df = cell.querySelector('form');
        if (df) df.remove();
    }
}

function showToast(msg, type) {
    type = type || 'success';
    var c = document.getElementById('toastContainer');
    if (!c) return;
    var cls = type === 'success'
        ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
        : 'bg-red-500/10 border-red-500/30 text-red-400';
    var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    var t = document.createElement('div');
    t.className = 'pointer-events-auto flex items-center gap-2 p-3 rounded-lg border text-xs ' + cls;
    t.innerHTML = '<i class="fas ' + icon + '"></i><span>' + msg + '</span>';
    c.appendChild(t);
    setTimeout(function() {
        t.style.transition = 'opacity 0.3s';
        t.style.opacity = '0';
        setTimeout(function() { t.remove(); }, 300);
    }, 3000);
}

document.addEventListener('keydown', function(e) {
    if (e.target.matches('input,textarea,select')) return;
    if (e.altKey && e.key === 'n') { e.preventDefault(); openCreateModal(); }
    if (e.key === 'Escape') closeCreateModal();
});

document.addEventListener('DOMContentLoaded', function() {
    var f = document.getElementById('flash-success');
    if (f) setTimeout(function() { f.style.transition='opacity 0.5s'; f.style.opacity='0'; setTimeout(function(){f.remove();},500); }, 5000);
});
</script>

</div><!-- /.fade-in -->

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';

