<?php
/**
 * Customer Orders — Order History & Tracking
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
$current_branch_id = 0;
if (function_exists('get_current_branch_id')) {
    $current_branch_id = get_current_branch_id();
}

$tenant_id = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
if (!$tenant_id) {
    try {
        $row = $pdo->query("SELECT id FROM pos_tenants WHERE status IN ('active','trial') ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $tenant_id = (int) $row['id'];
        } else {
            $row = $pdo->query("SELECT id FROM tenants WHERE active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row) $tenant_id = (int) $row['id'];
        }
    } catch (Exception $e) {}
}

// Auth check
$account = null;
if (!empty($_COOKIE['shop_customer_token']) && !empty($_COOKIE['shop_customer_id'])) {
    $stmt = $pdo->prepare("SELECT id, customer_id, full_name, email FROM online_customer_accounts WHERE tenant_id = ? AND id = ? AND remember_token = ? AND is_active = 1 AND branch_id = $current_branch_id LIMIT 1");
    $stmt->execute([$tenant_id, (int)$_COOKIE['shop_customer_id'], $_COOKIE['shop_customer_token']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$account) {
    header("Location: login.php?tenant=$tenant_id&redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Load settings
$settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
$store_name = $settings['site_title'] ?? 'Jakababa';
$primary_color = $settings['primary_color'] ?? '#f68b1e';

// Fetch orders
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM online_orders
        WHERE tenant_id = ? AND customer_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$tenant_id, $account['customer_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$welcome = isset($_GET['welcome']) ? 'Welcome! Your account has been created.' : '';

function statusBadge($status) {
    $map = [
        'pending' => 'bg-yellow-100 text-yellow-700',
        'processing' => 'bg-blue-100 text-blue-700',
        'shipped' => 'bg-purple-100 text-purple-700',
        'delivered' => 'bg-green-100 text-green-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'refunded' => 'bg-gray-100 text-gray-700',
        'returned' => 'bg-orange-100 text-orange-700'
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-700';
}

function paymentBadge($status) {
    $map = [
        'paid' => 'bg-green-100 text-green-700',
        'pending' => 'bg-yellow-100 text-yellow-700',
        'failed' => 'bg-red-100 text-red-700'
    ];
    return $map[$status] ?? 'bg-gray-100 text-gray-700';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{brand:{DEFAULT:'<?php echo $primary_color; ?>',dark:'#e07d16'},dark:{900:'#0f0f1a',800:'#1a1a2e'}}}}}</script>
</head>
<body class="bg-gray-50 min-h-screen font-sans">

<!-- Header -->
<header class="bg-white border-b sticky top-0 z-10">
    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center gap-3">
        <a href="../index.php?tenant=<?php echo $tenant_id; ?>" class="text-gray-500 hover:text-brand transition"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-lg font-bold text-dark-800">My Orders</h1>
        <div class="ml-auto flex items-center gap-3">
            <span class="text-sm text-gray-500 hidden sm:inline"><?php echo htmlspecialchars($account['full_name'] ?? 'Customer'); ?></span>
            <a href="../index.php?tenant=<?php echo $tenant_id; ?>" class="text-sm text-gray-500 hover:text-brand transition">Back to Shop</a>
        </div>
    </div>
</header>

<div class="max-w-4xl mx-auto px-4 py-6">
    <?php if ($welcome): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center gap-2">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($welcome); ?>
    </div>
    <?php endif; ?>

    <!-- Account Nav -->
    <div class="flex gap-2 overflow-x-auto mb-6 scrollbar-hide">
        <a href="orders.php?tenant=<?php echo $tenant_id; ?>" class="px-4 py-2 rounded-full bg-brand text-white text-sm font-medium whitespace-nowrap">My Orders</a>
        <span class="px-4 py-2 rounded-full bg-white text-gray-400 text-sm font-medium whitespace-nowrap border border-gray-200 opacity-50 cursor-not-allowed" title="Coming soon">Addresses</span>
        <span class="px-4 py-2 rounded-full bg-white text-gray-400 text-sm font-medium whitespace-nowrap border border-gray-200 opacity-50 cursor-not-allowed" title="Coming soon">Wishlist</span>
    </div>

    <?php if (empty($orders)): ?>
    <div class="text-center py-16">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fas fa-shopping-bag text-3xl text-gray-300"></i></div>
        <h3 class="font-bold text-dark-800 mb-1">No orders yet</h3>
        <p class="text-sm text-gray-500 mb-4">Start shopping and your orders will appear here.</p>
        <a href="../index.php?tenant=<?php echo $tenant_id; ?>" class="inline-block bg-brand hover:bg-brand-dark text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition">Start Shopping</a>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($orders as $order):
            // Fetch items
            $items = [];
            try {
                $stmt = $pdo->prepare("SELECT product_name, quantity, unit_price FROM online_order_items WHERE order_id = ? AND tenant_id = ?");
                $stmt->execute([$order['id'], $tenant_id]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        ?>
        <div class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-md transition">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <span class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                    <div class="font-mono text-sm font-semibold text-dark-800"><?php echo htmlspecialchars($order['order_number']); ?></div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?php echo statusBadge($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?php echo paymentBadge($order['payment_status']); ?>"><?php echo ucfirst($order['payment_status']); ?></span>
                </div>
            </div>

            <div class="space-y-2 mb-3">
                <?php foreach ($items as $item): ?>
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-2 h-2 rounded-full bg-gray-300"></div>
                    <span class="text-gray-600 flex-1"><?php echo htmlspecialchars($item['product_name']); ?></span>
                    <span class="text-gray-400 text-xs">x<?php echo $item['quantity']; ?></span>
                    <span class="font-medium">KES <?php echo number_format((float)$item['unit_price'], 2); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                <div class="text-sm text-gray-500">Total: <span class="font-bold text-dark-800 text-base">KES <?php echo number_format((float)$order['total'], 2); ?></span></div>
                <a href="../track.php?tenant=<?php echo $tenant_id; ?>&order=<?php echo urlencode($order['order_number']); ?>" class="text-sm text-brand font-medium hover:underline"><i class="fas fa-map-marker-alt mr-1"></i> Track</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
