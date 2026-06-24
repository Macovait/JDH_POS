<?php
/**
 * Store Admin — Orders Management
 * @version 3.0
 */
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = (int)($_SESSION['tenant_id'] ?? get_current_tenant_id() ?? 0);

if (!$tenant_id) {
    echo '<div class="flex items-center justify-center h-screen text-slate-400">Access Denied — no tenant context</div>';
    exit;
}

// Load store settings
$settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$brand_color = $settings['primary_color'] ?? '#f68b1e';
$store_name = $settings['store_name'] ?? $_SESSION['company_name'] ?? 'My Store';
$currency = $settings['currency'] ?? 'KES';

// Load currency symbol
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $curr = $stmt->fetchColumn();
    if ($curr) $currency = $curr;
} catch (Exception $e) {}

$blocks_count = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $st->execute([$tenant_id]);
    $blocks_count = (int)$st->fetchColumn();
} catch (Exception $e) {}

// ── Handle Status Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    if ($order_id && in_array($new_status, $allowed)) {
        try {
            $stmt = $pdo->prepare(
                "UPDATE online_orders 
                 SET status = ?, updated_at = NOW() 
                 WHERE id = ? AND tenant_id = ?"
            );
            $stmt->execute([$new_status, $order_id, $tenant_id]);
            $msg = 'Order status updated successfully!';
            $msg_type = 'success';
        } catch (Exception $e) {
            $msg = 'Update failed: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }
    header('Location: orders.php?msg=' . urlencode($msg) . '&type=' . $msg_type);
    exit;
}

// ── Handle Bulk Status Update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $order_ids = $_POST['order_ids'] ?? [];
    $new_status = $_POST['bulk_status'] ?? '';
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    if (!empty($order_ids) && in_array($new_status, $allowed)) {
        try {
            $pdo->beginTransaction();
            $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
            $stmt = $pdo->prepare(
                "UPDATE online_orders 
                 SET status = ?, updated_at = NOW() 
                 WHERE id IN ($placeholders) AND tenant_id = ?"
            );
            $params = array_merge([$new_status], $order_ids, [$tenant_id]);
            $stmt->execute($params);
            $pdo->commit();
            $msg = count($order_ids) . ' order(s) updated to ' . ucfirst($new_status) . '!';
            $msg_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Bulk update failed: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }
    header('Location: orders.php?msg=' . urlencode($msg) . '&type=' . $msg_type);
    exit;
}

// ── Filters ──
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "WHERE o.tenant_id = ?";
$params = [$tenant_id];

if ($status_filter && $status_filter !== 'all') {
    $where .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$orders = [];
$total_count = 0;

try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM online_orders o $where");
    $cnt->execute($params);
    $total_count = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT o.id, o.order_number, o.customer_name, o.customer_email, o.customer_phone, 
                o.total, o.status, o.payment_status, o.payment_method, o.created_at 
         FROM online_orders o 
         $where 
         ORDER BY o.created_at DESC 
         LIMIT $per_page OFFSET $offset"
    );
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silence
}

// ── Status Counts ──
$statusCounts = ['all' => 0, 'pending' => 0, 'processing' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0, 'refunded' => 0];
try {
    $r = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM online_orders WHERE tenant_id = ? GROUP BY status");
    $r->execute([$tenant_id]);
    while ($row = $r->fetch()) {
        $statusCounts[$row['status']] = (int)$row['cnt'];
        $statusCounts['all'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}

// ── Single Order Detail ──
$viewOrder = null;
$orderItems = [];
if (isset($_GET['view'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM online_orders WHERE id = ? AND tenant_id = ?");
        $stmt->execute([(int)$_GET['view'], $tenant_id]);
        $viewOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($viewOrder) {
            $stmt2 = $pdo->prepare("SELECT * FROM online_order_items WHERE order_id = ?");
            $stmt2->execute([$viewOrder['id']]);
            $orderItems = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

$stats = [
    'pending_orders' => $statusCounts['pending'] ?? 0,
    'reviews_pending' => 0
];

$storeUrl = storefront_url($tenant_id);
$page_title = 'Orders';

// Handle messages from redirects
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = $_GET['type'] ?? 'success';
}

ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-shopping-cart text-amber-400 text-xl"></i>
                Orders
            </h1>
            <p class="text-sm text-slate-500 mt-1"><?= number_format($statusCounts['all']) ?> total orders</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="blocks.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/80 hover:text-white hover:border-slate-600 transition-all duration-200">
                <i class="fas fa-cubes text-[10px]"></i> Blocks
            </a>
            <a href="customize.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/80 hover:text-white hover:border-slate-600 transition-all duration-200">
                <i class="fas fa-paint-brush text-[10px]"></i> Customize
            </a>
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-eye"></i> Preview Store
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($msg)): ?>
    <div class="px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 <?= $msg_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400' ?>">
        <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <?php if ($viewOrder): ?>
    <!-- ============================================================ -->
    <!-- ORDER DETAIL VIEW -->
    <!-- ============================================================ -->
    <div>
        <a href="orders.php" class="inline-flex items-center gap-1.5 text-sm text-amber-400 font-medium hover:text-amber-300 transition-colors mb-4">
            <i class="fas fa-arrow-left text-xs"></i> Back to Orders
        </a>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <!-- Order Header -->
            <div class="px-6 py-4 border-b border-slate-700/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-extrabold text-white flex items-center gap-3">
                        <?= htmlspecialchars($viewOrder['order_number']) ?>
                        <?php
                        $statusClasses = [
                            'pending' => 'bg-amber-500/15 text-amber-400',
                            'processing' => 'bg-blue-500/15 text-blue-400',
                            'shipped' => 'bg-purple-500/15 text-purple-400',
                            'delivered' => 'bg-emerald-500/15 text-emerald-400',
                            'cancelled' => 'bg-red-500/15 text-red-400',
                            'refunded' => 'bg-slate-500/15 text-slate-400'
                        ];
                        $sc = $statusClasses[$viewOrder['status']] ?? 'bg-slate-500/15 text-slate-400';
                        ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium <?= $sc ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= str_replace('text-', 'bg-', $sc) ?>"></span>
                            <?= ucfirst($viewOrder['status']) ?>
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <i class="far fa-calendar-alt mr-1"></i>
                        <?= date('F j, Y H:i', strtotime($viewOrder['created_at'])) ?>
                    </p>
                </div>

                <!-- Status Update Form -->
                <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="order_id" value="<?= $viewOrder['id'] ?>">
                    <select name="new_status"
                        class="text-xs bg-slate-900/50 border border-slate-700/60 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                        <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $s): ?>
                        <option value="<?= $s ?>" <?= $viewOrder['status'] === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button name="update_status"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-white text-xs font-bold transition-all hover:opacity-90 shadow-lg"
                        style="background: <?= $brand_color ?>">
                        <i class="fas fa-save text-[10px]"></i> Update
                    </button>
                </form>
            </div>

            <!-- Order Info -->
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-900/50 rounded-xl p-4 border border-slate-700/40">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-user text-slate-600"></i> Customer
                    </p>
                    <p class="font-semibold text-white"><?= htmlspecialchars($viewOrder['customer_name']) ?></p>
                    <p class="text-sm text-slate-400"><?= htmlspecialchars($viewOrder['customer_email']) ?></p>
                    <p class="text-sm text-slate-400"><?= htmlspecialchars($viewOrder['customer_phone']) ?></p>
                </div>

                <div class="bg-slate-900/50 rounded-xl p-4 border border-slate-700/40">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-2 flex items-center gap-2">
                        <i class="fas fa-credit-card text-slate-600"></i> Payment
                    </p>
                    <p class="font-semibold text-white text-lg"><?= $currency ?> <?= number_format((float)$viewOrder['total'], 2) ?></p>
                    <p class="text-sm text-slate-400">Method: <?= ucfirst($viewOrder['payment_method'] ?? '-') ?></p>
                    <p class="text-sm font-medium <?= $viewOrder['payment_status'] === 'paid' ? 'text-emerald-400' : 'text-amber-400' ?>">
                        <?= ucfirst($viewOrder['payment_status'] ?? '-') ?>
                    </p>
                </div>
            </div>

            <!-- Order Items -->
            <div class="px-6 pb-6">
                <h3 class="font-bold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-list text-amber-400 text-sm"></i> Order Items
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-slate-700/60 rounded-xl overflow-hidden">
                        <thead class="bg-slate-900/50 text-xs text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Product</th>
                                <th class="px-4 py-3 text-center font-semibold">Qty</th>
                                <th class="px-4 py-3 text-right font-semibold">Unit Price</th>
                                <th class="px-4 py-3 text-right font-semibold">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            <?php if (empty($orderItems)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">
                                    <i class="fas fa-box text-slate-600 block mb-2"></i>
                                    No items found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($orderItems as $item): ?>
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-4 py-3 font-medium text-white"><?= htmlspecialchars($item['product_name']) ?></td>
                                <td class="px-4 py-3 text-center text-slate-300"><?= (int)$item['quantity'] ?></td>
                                <td class="px-4 py-3 text-right text-slate-300"><?= $currency ?> <?= number_format((float)$item['unit_price'], 2) ?></td>
                                <td class="px-4 py-3 text-right font-bold text-white"><?= $currency ?> <?= number_format((float)$item['subtotal'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-slate-900/50 border-t border-slate-700/60">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-slate-300">Total</td>
                                <td class="px-4 py-3 text-right font-extrabold text-amber-400">
                                    <?= $currency ?> <?= number_format((float)$viewOrder['total'], 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ============================================================ -->
    <!-- ORDER LIST VIEW -->
    <!-- ============================================================ -->

    <!-- Status Filters -->
    <div class="flex flex-wrap gap-1 bg-slate-800/40 border border-slate-700/60 rounded-xl p-1">
        <?php
        $statusLinks = [
            'all' => 'All',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded'
        ];
        foreach ($statusLinks as $sv => $sl):
            $active = ($status_filter === $sv || ($sv === 'all' && !$status_filter));
        ?>
        <a href="?status=<?= $sv ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all <?= $active ? 'bg-amber-500 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' ?>">
            <?= $sl ?>
            <span class="ml-1 opacity-60 text-[10px]"><?= $statusCounts[$sv] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Bulk Actions -->
    <div class="flex flex-col sm:flex-row gap-3">
        <form method="GET" class="flex-1 flex gap-2">
            <?php if ($status_filter): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Search by order #, name, email…"
                class="flex-1 max-w-xs text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg px-3 py-2 text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-white text-sm font-bold transition-all hover:opacity-90 shadow-lg"
                style="background: <?= $brand_color ?>">
                <i class="fas fa-search text-xs"></i> Search
            </button>
        </form>

        <!-- Bulk Actions -->
        <form method="POST" class="flex items-center gap-2" id="bulkForm">
            <input type="hidden" name="bulk_update" value="1">
            <select name="bulk_status"
                class="text-xs bg-slate-900/50 border border-slate-700/60 rounded-lg px-3 py-2 text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                <option value="">Bulk Action</option>
                <?php foreach (['processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                <option value="<?= $s ?>">Mark as <?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700/60 text-slate-300 text-xs font-medium hover:bg-slate-600/60 hover:text-white transition-all border border-slate-600/40"
                onclick="return confirm('Update selected orders?')">
                <i class="fas fa-arrow-right text-[10px]"></i> Apply
            </button>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 text-xs text-slate-500">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" id="selectAll" class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20" onchange="toggleSelectAll()">
                        </th>
                        <th class="px-4 py-3 text-left font-semibold">Order</th>
                        <th class="px-4 py-3 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3 text-right font-semibold">Total</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-center font-semibold">Payment</th>
                        <th class="px-4 py-3 text-left font-semibold">Date</th>
                        <th class="px-4 py-3 text-center font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-slate-500">
                            <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-inbox text-2xl text-slate-600"></i>
                            </div>
                            <p class="font-medium text-slate-400">No orders found</p>
                            <p class="text-xs mt-1">Try adjusting your filters or search</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order):
                        $statusClasses = [
                            'pending' => 'bg-amber-500/15 text-amber-400',
                            'processing' => 'bg-blue-500/15 text-blue-400',
                            'shipped' => 'bg-purple-500/15 text-purple-400',
                            'delivered' => 'bg-emerald-500/15 text-emerald-400',
                            'cancelled' => 'bg-red-500/15 text-red-400',
                            'refunded' => 'bg-slate-500/15 text-slate-400'
                        ];
                        $sc = $statusClasses[$order['status']] ?? 'bg-slate-500/15 text-slate-400';
                        $paidColor = $order['payment_status'] === 'paid' ? 'text-emerald-400' : 'text-amber-400';
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors group">
                        <td class="px-4 py-3">
                            <input type="checkbox" name="order_ids[]" value="<?= $order['id'] ?>" class="order-checkbox rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20" onchange="updateSelectAllState()">
                        </td>
                        <td class="px-4 py-3">
                            <a href="?view=<?= $order['id'] ?>" class="font-mono text-xs font-bold text-amber-400 hover:text-amber-300 transition-colors">
                                <?= htmlspecialchars($order['order_number']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-white"><?= htmlspecialchars($order['customer_name']) ?></div>
                            <div class="text-[10px] text-slate-500"><?= htmlspecialchars($order['customer_phone']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-white">
                            <?= $currency ?> <?= number_format((float)$order['total'], 0) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-medium <?= $sc ?>">
                                <span class="w-1 h-1 rounded-full <?= str_replace('text-', 'bg-', $sc) ?>"></span>
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs font-medium <?= $paidColor ?>">
                            <?= ucfirst($order['payment_status']) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-[11px]">
                            <i class="far fa-clock text-[9px] mr-1"></i>
                            <?= date('M d, H:i', strtotime($order['created_at'])) ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="?view=<?= $order['id'] ?>"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-amber-500/10 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-all border border-amber-500/20">
                                <i class="fas fa-eye text-[9px]"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_count > $per_page):
        $pages = ceil($total_count / $per_page);
    ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-sm">
        <p class="text-xs text-slate-500">
            Showing <?= min($offset + 1, $total_count) ?>–<?= min($offset + $per_page, $total_count) ?> of <?= $total_count ?> orders
        </p>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $pages; $i++):
                $isActive = $i === $page;
                $url = '?p=' . $i;
                if ($status_filter) $url .= '&status=' . urlencode($status_filter);
                if ($search) $url .= '&q=' . urlencode($search);
            ?>
            <a href="<?= $url ?>"
                class="w-8 h-8 flex items-center justify-center rounded-lg border text-xs font-medium transition-all <?= $isActive ? 'bg-amber-500/20 border-amber-500/40 text-amber-400 shadow-lg' : 'bg-slate-800/60 border-slate-700/40 text-slate-400 hover:bg-slate-700/60 hover:text-white' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
// Select all functionality
function toggleSelectAll() {
    const master = document.getElementById('selectAll');
    const boxes = document.querySelectorAll('.order-checkbox');
    boxes.forEach(cb => cb.checked = master.checked);
}

function updateSelectAllState() {
    const boxes = document.querySelectorAll('.order-checkbox');
    const checked = document.querySelectorAll('.order-checkbox:checked');
    const master = document.getElementById('selectAll');
    if (master) {
        master.checked = boxes.length > 0 && checked.length === boxes.length;
    }
}

// Auto-submit bulk actions when selecting status
document.addEventListener('DOMContentLoaded', function() {
    const bulkStatus = document.querySelector('select[name="bulk_status"]');
    if (bulkStatus) {
        bulkStatus.addEventListener('change', function() {
            if (this.value) {
                const checked = document.querySelectorAll('.order-checkbox:checked');
                if (checked.length === 0) {
                    alert('Please select at least one order.');
                    this.value = '';
                    return;
                }
                // Optionally auto-submit
                // document.getElementById('bulkForm').submit();
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>