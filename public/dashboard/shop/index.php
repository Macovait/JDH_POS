<?php
/**
 * Online Store Admin — Shopify-style Dashboard
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

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'revenue_30d' => 0,
    'revenue_today' => 0,
    'total_products' => 0,
    'total_customers' => 0,
    'reviews_pending' => 0,
    'avg_order_value' => 0
];

try {
    // Total orders
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ?");
    $r->execute([$tenant_id]);
    $stats['total_orders'] = (int)$r->fetchColumn();

    // Pending orders
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ? AND status = 'pending'");
    $r->execute([$tenant_id]);
    $stats['pending_orders'] = (int)$r->fetchColumn();

    // Revenue last 30 days
    $r = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM online_orders WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND payment_status = 'paid'");
    $r->execute([$tenant_id]);
    $stats['revenue_30d'] = (float)$r->fetchColumn();

    // Revenue today
    $r = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM online_orders WHERE tenant_id = ? AND DATE(created_at) = CURDATE() AND payment_status = 'paid'");
    $r->execute([$tenant_id]);
    $stats['revenue_today'] = (float)$r->fetchColumn();

    // Total products
    $r = $pdo->prepare("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND active = 1 AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
    $r->execute([$tenant_id]);
    $stats['total_products'] = (int)$r->fetchColumn();

    // Total customers
    $r = $pdo->prepare("SELECT COUNT(DISTINCT customer_email) FROM online_orders WHERE tenant_id = ?");
    $r->execute([$tenant_id]);
    $stats['total_customers'] = (int)$r->fetchColumn();

    // Pending reviews
    $r = $pdo->prepare("SELECT COUNT(*) FROM product_reviews WHERE tenant_id = ? AND status = 'pending'");
    $r->execute([$tenant_id]);
    $stats['reviews_pending'] = (int)$r->fetchColumn();

    // Average order value
    if ($stats['total_orders'] > 0) {
        $r = $pdo->prepare("SELECT COALESCE(AVG(total), 0) FROM online_orders WHERE tenant_id = ? AND payment_status = 'paid'");
        $r->execute([$tenant_id]);
        $stats['avg_order_value'] = (float)$r->fetchColumn();
    }
} catch (Exception $e) {
    // Silence
}

// ── Recent Orders ─────────────────────────────────────────────────────────────
$recentOrders = [];
try {
    $stmt = $pdo->prepare(
        "SELECT id, order_number, customer_name, customer_email, total, status, payment_status, payment_method, created_at 
         FROM online_orders 
         WHERE tenant_id = ? 
         ORDER BY created_at DESC 
         LIMIT 10"
    );
    $stmt->execute([$tenant_id]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Top Products ──────────────────────────────────────────────────────────────
$topProducts = [];
try {
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.image, p.price, p.selling_price, 
                COUNT(oi.id) as sold, 
                COALESCE(SUM(oi.subtotal), 0) as revenue 
         FROM online_order_items oi 
         JOIN products p ON p.id = oi.product_id 
         WHERE oi.tenant_id = ? 
         GROUP BY oi.product_id 
         ORDER BY sold DESC 
         LIMIT 5"
    );
    $stmt->execute([$tenant_id]);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Sales Chart Data ──────────────────────────────────────────────────────────
$chartData = [];
try {
    $stmt = $pdo->prepare(
        "SELECT DATE(created_at) as date, 
                COUNT(*) as orders, 
                COALESCE(SUM(total), 0) as revenue 
         FROM online_orders 
         WHERE tenant_id = ? 
           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
           AND payment_status = 'paid' 
         GROUP BY DATE(created_at) 
         ORDER BY date ASC"
    );
    $stmt->execute([$tenant_id]);
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Store URL ─────────────────────────────────────────────────────────────────
$storeUrl = storefront_url($tenant_id);
$page_title = 'Dashboard';
$page_icon = 'fa-chart-pie';
$user_name = $_SESSION['name'] ?? 'Admin';

ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-chart-pie text-amber-400 text-xl"></i>
                Dashboard
            </h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                <span>Welcome back, <?= htmlspecialchars($user_name) ?></span>
                <span class="text-slate-600">•</span>
                <span class="text-[10px] text-slate-500">
                    <?= date('l, F j, Y') ?>
                </span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($stats['pending_orders'] > 0): ?>
            <a href="orders.php?status=pending" 
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs font-medium hover:bg-red-500/20 transition-all animate-pulse">
                <i class="fas fa-bell text-[10px]"></i>
                <?= $stats['pending_orders'] ?> Pending Order<?= $stats['pending_orders'] > 1 ? 's' : '' ?>
            </a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" 
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all duration-200 hover:opacity-90 shadow-lg" 
               style="background: <?= $brand_color ?>">
                <i class="fas fa-store"></i> Visit Store
            </a>
        </div>
    </div>

    <!-- Setup Banner (shown when no orders yet) -->
    <?php if ($stats['total_orders'] === 0): ?>
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background: linear-gradient(135deg, <?= $brand_color ?>, <?= $brand_color ?>cc);">
        <div class="absolute right-0 top-0 opacity-10 text-8xl transform translate-x-8 -translate-y-4">🛍️</div>
        <div class="relative z-10">
            <h2 class="text-xl font-extrabold mb-1">🚀 Your store is ready!</h2>
            <p class="text-sm text-white/80 max-w-lg">Complete setup to start receiving orders from customers.</p>
            <div class="flex flex-wrap items-center gap-3 mt-4">
                <a href="<?= base_url('products/product_form.php') ?>" 
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white font-bold text-sm transition hover:opacity-90" 
                   style="color: <?= $brand_color ?>">
                    <i class="fas fa-plus"></i> Add Products
                </a>
                <a href="settings.php" 
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/20 text-white font-bold text-sm hover:bg-white/30 transition">
                    <i class="fas fa-sliders-h"></i> Store Settings
                </a>
                <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" 
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/20 text-white font-bold text-sm hover:bg-white/30 transition">
                    <i class="fas fa-eye"></i> Preview
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
        <?php
        $kpis = [
            ['label' => 'Revenue (30d)', 'val' => $currency . ' ' . number_format($stats['revenue_30d'], 0), 'icon' => 'fa-coins', 'color' => 'emerald', 'change' => '+12%'],
            ['label' => 'Today\'s Revenue', 'val' => $currency . ' ' . number_format($stats['revenue_today'], 0), 'icon' => 'fa-calendar-day', 'color' => 'blue', 'change' => '+5%'],
            ['label' => 'Total Orders', 'val' => number_format($stats['total_orders']), 'icon' => 'fa-shopping-bag', 'color' => 'blue', 'change' => null],
            ['label' => 'Avg. Order Value', 'val' => $currency . ' ' . number_format($stats['avg_order_value'], 0), 'icon' => 'fa-chart-line', 'color' => 'purple', 'change' => null],
            ['label' => 'Products', 'val' => number_format($stats['total_products']), 'icon' => 'fa-box', 'color' => 'purple', 'change' => null],
            ['label' => 'Pending Reviews', 'val' => number_format($stats['reviews_pending']), 'icon' => 'fa-star', 'color' => 'orange', 'change' => $stats['reviews_pending'] > 0 ? 'New' : null],
        ];
        $colorClasses = [
            'emerald' => ['bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-400', 'border' => 'border-emerald-500/20'],
            'blue' => ['bg' => 'bg-blue-500/10', 'text' => 'text-blue-400', 'border' => 'border-blue-500/20'],
            'purple' => ['bg' => 'bg-purple-500/10', 'text' => 'text-purple-400', 'border' => 'border-purple-500/20'],
            'orange' => ['bg' => 'bg-orange-500/10', 'text' => 'text-orange-400', 'border' => 'border-orange-500/20'],
        ];
        foreach ($kpis as $k): 
            $c = $colorClasses[$k['color']] ?? $colorClasses['blue'];
        ?>
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 card-hover">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] text-slate-500 uppercase tracking-wider font-medium"><?= $k['label'] ?></span>
                <div class="w-8 h-8 <?= $c['bg'] ?> <?= $c['border'] ?> rounded-lg flex items-center justify-center <?= $c['text'] ?> border">
                    <i class="fas <?= $k['icon'] ?> text-xs"></i>
                </div>
            </div>
            <div class="text-lg font-bold text-white"><?= $k['val'] ?></div>
            <?php if ($k['change']): ?>
            <div class="text-[10px] text-emerald-400 mt-0.5 flex items-center gap-1">
                <i class="fas fa-arrow-up text-[8px]"></i>
                <?= $k['change'] ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="<?= base_url('products/product_form.php') ?>" 
           class="bg-slate-800/40 border-2 border-dashed border-slate-700/60 hover:border-amber-500/40 rounded-xl p-4 flex flex-col items-center gap-2 text-center transition-all duration-200 group">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover:bg-amber-500/20 transition-all">
                <i class="fas fa-plus text-lg"></i>
            </div>
            <span class="text-sm font-semibold text-white group-hover:text-amber-400 transition-colors">Add Product</span>
        </a>
        <a href="orders.php" 
           class="bg-slate-800/40 border border-slate-700/60 hover:border-blue-500/30 rounded-xl p-4 flex flex-col items-center gap-2 text-center transition-all duration-200 group">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-400 group-hover:bg-blue-500/20 transition-all">
                <i class="fas fa-shopping-bag text-lg"></i>
            </div>
            <span class="text-sm font-semibold text-white group-hover:text-blue-400 transition-colors">View Orders</span>
        </a>
        <a href="banners.php" 
           class="bg-slate-800/40 border border-slate-700/60 hover:border-purple-500/30 rounded-xl p-4 flex flex-col items-center gap-2 text-center transition-all duration-200 group">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400 group-hover:bg-purple-500/20 transition-all">
                <i class="fas fa-image text-lg"></i>
            </div>
            <span class="text-sm font-semibold text-white group-hover:text-purple-400 transition-colors">Edit Banners</span>
        </a>
        <a href="customize.php" 
           class="bg-slate-800/40 border border-slate-700/60 hover:border-amber-500/30 rounded-xl p-4 flex flex-col items-center gap-2 text-center transition-all duration-200 group">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 group-hover:bg-amber-500/20 transition-all">
                <i class="fas fa-paint-brush text-lg"></i>
            </div>
            <span class="text-sm font-semibold text-white group-hover:text-amber-400 transition-colors">Customize Store</span>
        </a>
    </div>

    <!-- Recent Orders - FULL WIDTH -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/60 flex items-center justify-between">
            <h3 class="font-bold text-white flex items-center gap-2">
                <i class="fas fa-clock text-amber-400 text-sm"></i>
                Recent Orders
            </h3>
            <a href="orders.php" class="text-[11px] text-amber-400 font-medium hover:text-amber-300 transition-all inline-flex items-center gap-1">
                View All <i class="fas fa-arrow-right text-[9px]"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 text-slate-500 text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Order</th>
                        <th class="px-4 py-3 text-left font-semibold">Customer</th>
                        <th class="px-4 py-3 text-left font-semibold">Email</th>
                        <th class="px-4 py-3 text-left font-semibold">Payment</th>
                        <th class="px-4 py-3 text-right font-semibold">Total</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-left font-semibold">Date</th>
                        <th class="px-4 py-3 text-center font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-slate-500">
                            <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-shopping-bag text-2xl text-slate-600"></i>
                            </div>
                            <p class="font-medium text-slate-400">No orders yet</p>
                            <p class="text-xs mt-1">Share your store link to get started</p>
                            <div class="mt-3">
                                <button onclick="navigator.clipboard.writeText('<?= addslashes($storeUrl) ?>')" 
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/10 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-all border border-amber-500/20">
                                    <i class="fas fa-copy text-[10px]"></i> Copy Store Link
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recentOrders as $order):
                        $statusClasses = [
                            'pending' => 'bg-amber-500/15 text-amber-400',
                            'processing' => 'bg-blue-500/15 text-blue-400',
                            'shipped' => 'bg-purple-500/15 text-purple-400',
                            'delivered' => 'bg-emerald-500/15 text-emerald-400',
                            'cancelled' => 'bg-red-500/15 text-red-400',
                            'refunded' => 'bg-slate-500/15 text-slate-400'
                        ];
                        $bc = $statusClasses[$order['status']] ?? 'bg-slate-500/15 text-slate-400';
                        $pc = $order['payment_status'] === 'paid' ? 'text-emerald-400' : ($order['payment_status'] === 'pending' ? 'text-amber-400' : 'text-red-400');
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors group">
                        <td class="px-4 py-3">
                            <a href="orders.php?view=<?= $order['id'] ?>" class="font-mono text-xs font-bold text-amber-400 hover:text-amber-300 transition-colors">
                                <?= htmlspecialchars($order['order_number']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-white text-xs"><?= htmlspecialchars($order['customer_name']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars($order['customer_email']) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-[10px] font-medium <?= $pc ?>">
                                <?= ucfirst($order['payment_status']) ?>
                            </span>
                            <?php if ($order['payment_method']): ?>
                            <span class="text-[10px] text-slate-500 block"><?= ucfirst($order['payment_method']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="font-bold text-white text-sm"><?= $currency ?> <?= number_format((float)$order['total'], 0) ?></div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-medium <?= $bc ?>">
                                <span class="w-1 h-1 rounded-full <?= str_replace('text-', 'bg-', $bc) ?>"></span>
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-[10px]">
                            <i class="far fa-calendar-alt text-[9px] mr-1"></i>
                            <?= date('M d, Y', strtotime($order['created_at'])) ?>
                            <span class="text-slate-600 text-[9px] block"><?= date('H:i', strtotime($order['created_at'])) ?></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="orders.php?view=<?= $order['id'] ?>" 
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

    <!-- Bottom Row: Top Products + Quick Stats -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Top Products -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60">
                <h3 class="font-bold text-white flex items-center gap-2 text-sm">
                    <i class="fas fa-fire text-amber-400 text-sm"></i>
                    Top Selling Products
                </h3>
            </div>
            <div class="p-3 space-y-2 max-h-[280px] overflow-y-auto custom-scroll">
                <?php if (empty($topProducts)): ?>
                <div class="text-center py-8 text-slate-500 text-xs">
                    <i class="fas fa-box-open text-slate-600 block text-2xl mb-2"></i>
                    No sales data yet
                </div>
                <?php else: ?>
                <?php foreach ($topProducts as $i => $tp):
                    $tpImg = '';
                    if (!empty($tp['image'])) {
                        $raw = $tp['image'];
                        $tpImg = str_starts_with($raw, 'http') ? $raw : (str_starts_with($raw, '/uploads/') ? $raw : '/uploads/product_images/' . basename($raw));
                    }
                ?>
                <div class="flex items-center gap-3 hover:bg-slate-700/20 rounded-lg p-2 transition-colors">
                    <div class="w-6 h-6 rounded-full bg-slate-700/60 flex items-center justify-center text-[10px] font-bold text-slate-400 flex-shrink-0">
                        <?= $i + 1 ?>
                    </div>
                    <div class="w-10 h-10 bg-slate-700/30 rounded-lg overflow-hidden flex items-center justify-center flex-shrink-0 border border-slate-700/40">
                        <?php if ($tpImg): ?>
                        <img src="<?= htmlspecialchars($tpImg) ?>" class="w-full h-full object-cover" loading="lazy" alt="<?= htmlspecialchars($tp['name']) ?>" onerror="this.parentElement.innerHTML='<i class=\'fas fa-box text-slate-500 text-sm\'></i>'">
                        <?php else: ?>
                        <i class="fas fa-box text-slate-500 text-sm"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($tp['name']) ?></div>
                        <div class="text-[10px] text-slate-500 flex items-center gap-2">
                            <span><?= $tp['sold'] ?> sold</span>
                            <span class="text-slate-600">•</span>
                            <span class="text-amber-400 font-medium">
                                <?= $currency ?> <?= number_format((float)($tp['selling_price'] ?: $tp['price']), 0) ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-[10px] font-bold text-emerald-400">
                        +<?= number_format((float)$tp['revenue'], 0) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h3 class="text-[11px] text-slate-500 font-semibold mb-3 flex items-center gap-2">
                <i class="fas fa-chart-simple text-amber-400 text-[10px]"></i>
                Store Performance
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-900/50 rounded-xl p-3 text-center border border-slate-700/40 hover:border-amber-500/20 transition-colors">
                    <div class="text-[10px] text-slate-500">Conversion Rate</div>
                    <div class="text-lg font-bold text-white">0%</div>
                    <div class="text-[9px] text-slate-500">Last 30 days</div>
                </div>
                <div class="bg-slate-900/50 rounded-xl p-3 text-center border border-slate-700/40 hover:border-amber-500/20 transition-colors">
                    <div class="text-[10px] text-slate-500">Avg. Items/Order</div>
                    <div class="text-lg font-bold text-white">0</div>
                    <div class="text-[9px] text-slate-500">All time</div>
                </div>
                <div class="bg-slate-900/50 rounded-xl p-3 text-center border border-slate-700/40 hover:border-amber-500/20 transition-colors">
                    <div class="text-[10px] text-slate-500">Return Rate</div>
                    <div class="text-lg font-bold text-white">0%</div>
                    <div class="text-[9px] text-slate-500">All time</div>
                </div>
                <div class="bg-slate-900/50 rounded-xl p-3 text-center border border-slate-700/40 hover:border-amber-500/20 transition-colors">
                    <div class="text-[10px] text-slate-500">Customer LTV</div>
                    <div class="text-lg font-bold text-amber-400"><?= $currency ?> 0</div>
                    <div class="text-[9px] text-slate-500">Avg. lifetime</div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>