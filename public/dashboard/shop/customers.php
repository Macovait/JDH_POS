<?php
/**
 * Store Admin — Online Customers
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

// Load currency symbol from settings
try {
    $row = $pdo->prepare("SELECT setting_value FROM settings WHERE tenant_id = ? AND setting_key = 'currency' LIMIT 1");
    $row->execute([$tenant_id]);
    $currency = $row->fetchColumn() ?: 'KES';
} catch (Exception $e) {}

$blocks_count = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $st->execute([$tenant_id]);
    $blocks_count = (int)$st->fetchColumn();
} catch (Exception $e) {}

// ── Search & Pagination ──
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "WHERE tenant_id = ?";
$params = [$tenant_id];

if ($search) {
    $where .= " AND (customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ── Load Customers ──
$customers = [];
$total = 0;

try {
    $cnt = $pdo->prepare("SELECT COUNT(DISTINCT customer_email) FROM online_orders $where");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT 
            customer_name, 
            customer_email, 
            customer_phone, 
            COUNT(*) as orders_count, 
            SUM(total) as total_spent, 
            MAX(created_at) as last_order,
            MIN(created_at) as first_order
         FROM online_orders 
         $where 
         GROUP BY customer_email 
         ORDER BY last_order DESC 
         LIMIT $per_page OFFSET $offset"
    );
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silence
}

$total_pages = (int)ceil($total / $per_page);

// ── Stats ──
$stats = ['pending_orders' => 0, 'reviews_pending' => 0];
try {
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ? AND status = 'pending'");
    $r->execute([$tenant_id]);
    $stats['pending_orders'] = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE tenant_id = ? AND status = 'pending'");
    $r->execute([$tenant_id]);
    $stats['reviews_pending'] = (int)$r->fetchColumn();
} catch (Exception $e) {}

// ── Customer Orders (for detail view) ──
$viewCustomer = null;
$customerOrders = [];
$customerStats = ['total_orders' => 0, 'total_spent' => 0, 'avg_order' => 0];

if (isset($_GET['view'])) {
    $email = $_GET['view'];
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM online_orders 
             WHERE tenant_id = ? AND customer_email = ? 
             ORDER BY created_at DESC"
        );
        $stmt->execute([$tenant_id, $email]);
        $customerOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($customerOrders)) {
            $viewCustomer = $customerOrders[0];
            $customerStats['total_orders'] = count($customerOrders);
            $customerStats['total_spent'] = array_sum(array_column($customerOrders, 'total'));
            $customerStats['avg_order'] = $customerStats['total_orders'] > 0 
                ? $customerStats['total_spent'] / $customerStats['total_orders'] 
                : 0;
        }
    } catch (Exception $e) {}
}

$storeUrl = storefront_url($tenant_id);
$page_title = 'Customers';
ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-users text-amber-400 text-xl"></i>
                Customers
            </h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                <span><?= number_format($total) ?> online customers</span>
                <?php if ($stats['pending_orders'] > 0): ?>
                <span class="text-[10px] bg-amber-500/10 text-amber-400 px-2 py-0.5 rounded-full border border-amber-500/20">
                    <?= $stats['pending_orders'] ?> pending orders
                </span>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="orders.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800/80 border border-slate-700/60 text-slate-400 text-xs font-medium hover:bg-slate-700/80 hover:text-white hover:border-slate-600 transition-all duration-200">
                <i class="fas fa-shopping-cart text-[10px]"></i> Orders
            </a>
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all duration-200 hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-eye"></i> Preview Store
            </a>
        </div>
    </div>

    <?php if ($viewCustomer && !empty($customerOrders)): ?>
    <!-- ============================================================ -->
    <!-- CUSTOMER DETAIL VIEW -->
    <!-- ============================================================ -->
    <div>
        <a href="customers.php" class="inline-flex items-center gap-1.5 text-sm text-amber-400 font-medium hover:text-amber-300 transition-colors mb-4">
            <i class="fas fa-arrow-left text-xs"></i> Back to Customers
        </a>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <!-- Customer Info -->
            <div class="px-6 py-4 border-b border-slate-700/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-amber-500/20 to-orange-500/20 border-2 border-amber-500/30 flex items-center justify-center text-amber-400 text-xl font-bold">
                        <?= strtoupper(substr($viewCustomer['customer_name'] ?? 'G', 0, 1)) ?>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white"><?= htmlspecialchars($viewCustomer['customer_name'] ?? 'Guest') ?></h2>
                        <p class="text-xs text-slate-500 space-x-2">
                            <span><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($viewCustomer['customer_email']) ?></span>
                            <?php if ($viewCustomer['customer_phone']): ?>
                            <span class="text-slate-600">|</span>
                            <span><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($viewCustomer['customer_phone']) ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="text-[10px] text-slate-600 mt-1">
                            Customer since <?= date('F Y', strtotime($customerOrders[array_key_last($customerOrders)]['created_at'])) ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-6 text-sm">
                    <div class="text-center">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wider">Orders</div>
                        <div class="text-xl font-bold text-white"><?= $customerStats['total_orders'] ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wider">Total Spent</div>
                        <div class="text-xl font-bold text-amber-400"><?= $currency ?> <?= number_format($customerStats['total_spent'], 0) ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-[10px] text-slate-500 uppercase tracking-wider">Avg. Order</div>
                        <div class="text-xl font-bold text-emerald-400"><?= $currency ?> <?= number_format($customerStats['avg_order'], 0) ?></div>
                    </div>
                </div>
            </div>

            <!-- Order History -->
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-white flex items-center gap-2">
                        <i class="fas fa-history text-amber-400 text-sm"></i> Order History
                    </h3>
                    <span class="text-[10px] text-slate-500"><?= $customerStats['total_orders'] ?> orders total</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-slate-700/60 rounded-xl overflow-hidden">
                        <thead class="bg-slate-900/50 text-xs text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Order</th>
                                <th class="px-4 py-3 text-right font-semibold">Total</th>
                                <th class="px-4 py-3 text-center font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Date</th>
                                <th class="px-4 py-3 text-center font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            <?php foreach ($customerOrders as $order):
                                $statusClasses = [
                                    'pending' => 'bg-amber-500/15 text-amber-400',
                                    'processing' => 'bg-blue-500/15 text-blue-400',
                                    'shipped' => 'bg-purple-500/15 text-purple-400',
                                    'delivered' => 'bg-emerald-500/15 text-emerald-400',
                                    'cancelled' => 'bg-red-500/15 text-red-400',
                                    'refunded' => 'bg-slate-500/15 text-slate-400'
                                ];
                                $sc = $statusClasses[$order['status']] ?? 'bg-slate-500/15 text-slate-400';
                            ?>
                            <tr class="hover:bg-slate-700/20 transition-colors">
                                <td class="px-4 py-3">
                                    <a href="orders.php?view=<?= $order['id'] ?>" class="font-mono text-xs font-bold text-amber-400 hover:text-amber-300 transition-colors">
                                        <?= htmlspecialchars($order['order_number']) ?>
                                    </a>
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
                                <td class="px-4 py-3 text-slate-500 text-[11px]">
                                    <i class="far fa-calendar-alt text-[9px] mr-1"></i>
                                    <?= date('M d, Y H:i', strtotime($order['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="orders.php?view=<?= $order['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-amber-500/10 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-all border border-amber-500/20">
                                        <i class="fas fa-eye text-[9px]"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($customerOrders)): ?>
                        <tfoot class="bg-slate-900/50 border-t border-slate-700/60">
                            <tr>
                                <td colspan="2" class="px-4 py-2 text-xs font-semibold text-slate-500">Total</td>
                                <td class="px-4 py-2 text-center font-bold text-white"><?= $customerStats['total_orders'] ?></td>
                                <td colspan="2" class="px-4 py-2 text-right font-bold text-amber-400">
                                    <?= $currency ?> <?= number_format($customerStats['total_spent'], 0) ?>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ============================================================ -->
    <!-- CUSTOMER LIST VIEW -->
    <!-- ============================================================ -->

    <!-- Search -->
    <div class="flex flex-col sm:flex-row gap-3">
        <form method="GET" class="flex-1 flex gap-2">
            <div class="relative flex-1 max-w-md">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search customers by name, email or phone..."
                    class="w-full pl-9 pr-4 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
            </div>
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90 shadow-lg" style="background: <?= $brand_color ?>">
                <i class="fas fa-search text-xs"></i> Search
            </button>
            <?php if ($search): ?>
            <a href="customers.php" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-slate-700/50 text-slate-300 text-sm font-medium hover:bg-slate-700 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i> Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Stats Summary -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 card-hover">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                    <i class="fas fa-user text-blue-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Total Customers</p>
                    <p class="text-lg font-bold text-white"><?= number_format($total) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 card-hover">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                    <i class="fas fa-shopping-bag text-emerald-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Total Orders</p>
                    <p class="text-lg font-bold text-white"><?= number_format(array_sum(array_column($customers, 'orders_count'))) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 card-hover">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                    <i class="fas fa-clock text-amber-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Pending Orders</p>
                    <p class="text-lg font-bold text-white"><?= number_format($stats['pending_orders']) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 card-hover">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-purple-500/10 border border-purple-500/20 flex items-center justify-center">
                    <i class="fas fa-star text-purple-400 text-xs"></i>
                </div>
                <div>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Pending Reviews</p>
                    <p class="text-lg font-bold text-white"><?= number_format($stats['reviews_pending']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 text-slate-500 text-xs">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3.5 text-left font-semibold">Contact</th>
                        <th class="px-4 py-3.5 text-right font-semibold">Orders</th>
                        <th class="px-4 py-3.5 text-right font-semibold">Total Spent</th>
                        <th class="px-4 py-3.5 text-left font-semibold">First Order</th>
                        <th class="px-4 py-3.5 text-left font-semibold">Last Order</th>
                        <th class="px-4 py-3.5 text-center font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-slate-500">
                            <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-users text-2xl text-slate-600"></i>
                            </div>
                            <p class="font-medium text-slate-400">No customers found</p>
                            <p class="text-xs mt-1"><?= $search ? 'Try adjusting your search' : 'Customers will appear once they place orders' ?></p>
                            <?php if ($search): ?>
                            <a href="customers.php" class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 rounded-lg bg-amber-500/10 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-all border border-amber-500/20">
                                <i class="fas fa-times text-[10px]"></i> Clear search
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($customers as $c):
                        $name = $c['customer_name'] ?: 'Guest';
                        $initial = strtoupper(substr($name, 0, 1));
                        $colors = ['#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#ec4899', '#06b6d4', '#f472b6'];
                        $color = $colors[crc32($c['customer_email'] ?? $name) % count($colors)];
                        $hasRecentOrder = $c['last_order'] && strtotime($c['last_order']) > strtotime('-30 days');
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors group">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background: <?= $color ?>">
                                    <?= $initial ?>
                                </div>
                                <div>
                                    <div class="font-medium text-white text-sm flex items-center gap-2">
                                        <?= htmlspecialchars($name) ?>
                                        <?php if ($hasRecentOrder): ?>
                                        <span class="text-[8px] bg-emerald-500/15 text-emerald-400 px-1.5 py-0.5 rounded-full">Recent</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($c['orders_count'] > 5): ?>
                                    <span class="text-[9px] text-amber-400">⭐ Loyal customer</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($c['customer_email'] ?: '-') ?></div>
                            <?php if ($c['customer_phone']): ?>
                            <div class="text-[10px] text-slate-500"><?= htmlspecialchars($c['customer_phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="inline-flex items-center justify-center w-7 h-7 bg-blue-500/10 text-blue-400 rounded-full text-[10px] font-bold border border-blue-500/20">
                                <?= (int)$c['orders_count'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-amber-400 text-sm">
                            <?= $currency ?> <?= number_format((float)$c['total_spent'], 0) ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-[11px]">
                            <?= $c['first_order'] ? date('M d, Y', strtotime($c['first_order'])) : '-' ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-[11px]">
                            <?= $c['last_order'] ? date('M d, Y', strtotime($c['last_order'])) : '-' ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="?view=<?= urlencode($c['customer_email']) ?>"
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

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 border-t border-slate-700/60 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span class="text-xs text-slate-500">
                Showing <?= min($offset + 1, $total) ?>–<?= min($offset + $per_page, $total) ?> of <?= $total ?> customers
            </span>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                <a href="?p=<?= $page - 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fas fa-chevron-left text-[10px]"></i> Prev
                </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($total_pages, $page + 2);
                if ($startPage > 1) {
                    echo '<a href="?p=1' . ($search ? '&q=' . urlencode($search) : '') . '" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">1</a>';
                    if ($startPage > 2) echo '<span class="px-2 text-xs text-slate-500">...</span>';
                }
                for ($i = $startPage; $i <= $endPage; $i++):
                    $active = $i === $page;
                ?>
                <a href="?p=<?= $i ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
                    class="px-3 py-1.5 text-xs border rounded-lg transition <?= $active ? 'bg-amber-500 border-amber-500 text-white shadow-lg' : 'border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>">
                    <?= $i ?>
                </a>
                <?php endfor;
                if ($endPage < $total_pages) {
                    if ($endPage < $total_pages - 1) echo '<span class="px-2 text-xs text-slate-500">...</span>';
                    echo '<a href="?p=' . $total_pages . ($search ? '&q=' . urlencode($search) : '') . '" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                <a href="?p=<?= $page + 1 ?><?= $search ? '&q=' . urlencode($search) : '' ?>" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">
                    Next <i class="fas fa-chevron-right text-[10px]"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>