<?php
/**
 * Customer Order History — Lookup by phone number
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
$current_branch_id = 0;
if (function_exists('get_current_branch_id')) {
    $current_branch_id = get_current_branch_id();
}

$tenant_id = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';

if ($tenant_id <= 0) {
    $tenant_id = 1;
}

$settings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
} catch (Exception $e) {}

$store_name = $settings['site_title'] ?? $settings['company_name'] ?? 'Online Store';
$currency = $settings['currency'] ?? 'KES';

$orders = [];
$has_table = false;
try { $pdo->query("SELECT 1 FROM online_orders WHERE branch_id = $current_branch_id LIMIT 1"); $has_table = true; } catch (Exception $e) {}

if ($has_table && $phone && $tenant_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, customer_name, customer_phone, total, status, items_json, created_at
            FROM online_orders
            WHERE customer_phone = ? AND tenant_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$phone, $tenant_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="shop.css">
</head>
<body>
    <?php include 'shop-header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <nav class="breadcrumb"><a href="./?tenant=<?php echo $tenant_id; ?>">Shop</a> <span>/</span> <span>My Orders</span></nav>
        <h1 class="page-title"><i class="fas fa-box" style="color:var(--j-orange);"></i> My Orders</h1>

        <div class="track-form" style="max-width:480px; padding:1.5rem;">
            <form method="GET" action="">
                <input type="hidden" name="tenant" value="<?php echo $tenant_id; ?>">
                <div class="search-box" style="margin-bottom:0;">
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Enter your phone number (e.g. +254700000000)" required>
                    <button type="submit"><i class="fas fa-search"></i> Find Orders</button>
                </div>
            </form>
        </div>

        <?php if ($phone && empty($orders)): ?>
        <div class="no-results">
            <i class="fas fa-inbox" style="font-size:2.5rem; color:var(--j-border); margin-bottom:1rem;"></i>
            <p>No orders found for <strong><?php echo htmlspecialchars($phone); ?></strong>.</p>
        </div>
        <?php elseif (!empty($orders)): ?>
        <div style="margin-top:1.5rem;">
            <p style="color:var(--j-muted); margin-bottom:1rem; font-size:0.9rem;">Found <?php echo count($orders); ?> order(s) for <strong><?php echo htmlspecialchars($phone); ?></strong></p>
            <?php foreach ($orders as $o):
                $items = json_decode($o['items_json'] ?? '[]', true);
                $item_names = array_slice(array_map(function($i) { return ($i['name'] ?? ''); }, $items), 0, 3);
                $status_class = 'status-' . ($o['status'] ?? 'pending');
            ?>
            <div class="order-card">
                <div class="order-card-header">
                    <div>
                        <span class="order-id">Order #<?php echo (int) $o['id']; ?></span>
                        <span style="color:var(--j-muted); font-size:0.8rem; margin-left:0.75rem;"><?php echo date('d M Y', strtotime($o['created_at'])); ?></span>
                    </div>
                    <span class="order-status <?php echo $status_class; ?>"><?php echo ucfirst($o['status'] ?? 'pending'); ?></span>
                </div>
                <div class="order-items">
                    <?php echo htmlspecialchars(implode(', ', $item_names)); ?>
                    <?php if (count($items) > 3): ?> + <?php echo count($items) - 3; ?> more<?php endif; ?>
                </div>
                <div class="order-total"><?php echo $currency; ?> <?php echo number_format((float) $o['total'], 2); ?></div>
                <div style="margin-top:0.75rem;">
                    <a href="track.php?tenant=<?php echo $tenant_id; ?>&order_id=<?php echo (int) $o['id']; ?>&phone=<?php echo urlencode($phone); ?>" style="color:var(--j-orange); text-decoration:none; font-size:0.85rem; font-weight:600;">
                        <i class="fas fa-map-marker-alt"></i> Track this order
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-results">
            <i class="fas fa-mobile-alt" style="font-size:2.5rem; color:var(--j-border); margin-bottom:1rem;"></i>
            <p>Enter your phone number above to see all your past orders.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php include 'shop-footer.php'; ?>
    <script src="shop.js"></script>
    <script>renderCart();</script>
</body>
</html>
