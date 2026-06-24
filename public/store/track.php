<?php
/**
 * Order Tracking Page — Track order by ID and phone
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
$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
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

$order = null;
$has_table = false;
try { $pdo->query("SELECT 1 FROM online_orders WHERE branch_id = $current_branch_id LIMIT 1"); $has_table = true; } catch (Exception $e) {}

if ($has_table && $order_id && $phone) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM online_orders WHERE id = ? AND customer_phone = ? AND tenant_id = ? AND branch_id = $current_branch_id LIMIT 1");
        $stmt->execute([$order_id, $phone, $tenant_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$status_steps = [
    'pending' => ['label' => 'Order Placed', 'icon' => 'fa-clipboard-check', 'done' => true],
    'processing' => ['label' => 'Processing', 'icon' => 'fa-box-open', 'done' => false],
    'shipped' => ['label' => 'Shipped', 'icon' => 'fa-truck', 'done' => false],
    'completed' => ['label' => 'Delivered', 'icon' => 'fa-check-circle', 'done' => false],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order — <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="shop.css">
    <style>
        .track-form { background: var(--j-white); border: 1px solid var(--j-border); border-radius: 4px; padding: 2rem; max-width: 500px; margin: 0 auto 2rem; box-shadow: var(--j-card-shadow); }
        .track-form h2 { margin-bottom: 1.25rem; text-align: center; color: var(--j-text); }
        .track-steps { display: flex; justify-content: space-between; margin: 2rem 0; position: relative; }
        .track-steps::before { content: ''; position: absolute; top: 20px; left: 10%; right: 10%; height: 3px; background: var(--j-border); z-index: 0; }
        .track-step { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; z-index: 1; flex: 1; }
        .track-step .dot { width: 44px; height: 44px; border-radius: 50%; background: var(--j-white); border: 2px solid var(--j-border); display: flex; align-items: center; justify-content: center; font-size: 1rem; color: var(--j-muted); }
        .track-step.done .dot { background: #e8f5e9; border-color: #1e8e3e; color: #1e8e3e; }
        .track-step.active .dot { background: #fff3e0; border-color: var(--j-orange); color: var(--j-orange); }
        .track-step span { font-size: 0.8rem; color: var(--j-muted); }
        .track-step.done span, .track-step.active span { color: var(--j-text); font-weight: 600; }
        .order-summary { background: var(--j-bg); border: 1px solid var(--j-border); border-radius: 4px; padding: 1.25rem; margin-top: 1.5rem; }
        .order-summary-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--j-border); font-size: 0.9rem; color: var(--j-text); }
        .order-summary-row:last-child { border-bottom: none; font-weight: 700; color: var(--j-text); font-size: 1.05rem; margin-top: 0.5rem; padding-top: 0.75rem; border-top: 2px solid var(--j-border); }
    </style>
</head>
<body>
    <?php include 'shop-header.php'; ?>
    <div class="container" style="padding-top:2rem;">
        <nav class="breadcrumb"><a href="./?tenant=<?php echo $tenant_id; ?>">Shop</a> <span>/</span> <span>Track Order</span></nav>

        <div class="track-form">
            <h2><i class="fas fa-search-location" style="color:var(--j-orange);"></i> Track Your Order</h2>
            <form method="GET" action="">
                <input type="hidden" name="tenant" value="<?php echo $tenant_id; ?>">
                <div class="form-group">
                    <label>Order Number</label>
                    <input type="number" name="order_id" value="<?php echo $order_id; ?>" placeholder="e.g. 123" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="+254700000000" required>
                </div>
                <button type="submit" class="checkout-btn" style="margin-top:0.5rem;"><i class="fas fa-search"></i> Track Order</button>
            </form>
        </div>

        <?php if ($order): ?>
        <div class="track-form" style="max-width:600px;">
            <h2>Order #<?php echo (int) $order['id']; ?></h2>
            <div class="track-steps">
                <?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
                $current_status = $order['status'] ?? 'pending';
                $found_active = false;
                foreach ($status_steps as $key => $step):
                    $is_done = false;
                    if ($current_status === 'completed' || $current_status === $key || ($key === 'pending')) { $is_done = true; }
                    if ($current_status === 'cancelled') { $is_done = false; }
                    if ($current_status === 'pending' && $key !== 'pending') { $is_done = false; }
                    if ($current_status === 'processing' && in_array($key, ['shipped', 'completed'])) { $is_done = false; }
                    if ($current_status === 'shipped' && $key === 'completed') { $is_done = false; }
                    $is_active = ($key === $current_status);
                    $class = $is_done ? 'done' : ($is_active ? 'active' : '');
                ?>
                <div class="track-step <?php echo $class; ?>">
                    <div class="dot"><i class="fas <?php echo $step['icon']; ?>"></i></div>
                    <span><?php echo $step['label']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align:center; margin-bottom:1.5rem;">
                <span class="order-status status-<?php echo $current_status; ?>"><?php echo ucfirst($current_status); ?></span>
                <?php if ($current_status === 'cancelled'): ?><p style="color:#f87171; margin-top:0.5rem;">This order has been cancelled.</p><?php endif; ?>
            </div>

            <div class="order-summary">
                <?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
                $items = json_decode($order['items_json'] ?? '[]', true);
                foreach ($items as $it): ?>
                <div class="order-summary-row">
                    <span><?php echo htmlspecialchars($it['name'] ?? ''); ?> x<?php echo (int) ($it['quantity'] ?? 0); ?></span>
                    <span><?php echo $currency; ?> <?php echo number_format((float) ($it['price'] ?? 0) * (int) ($it['quantity'] ?? 0), 2); ?></span>
                </div>
                <?php endforeach; ?>
                <div class="order-summary-row">
                    <span>Total</span>
                    <span><?php echo $currency; ?> <?php echo number_format((float) $order['total'], 2); ?></span>
                </div>
            </div>

            <div style="margin-top:1.5rem; text-align:center;">
                <p style="color:var(--j-muted); font-size:0.85rem;">Placed on <?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></p>
                <?php if ($current_status === 'pending'): ?>
                <p style="margin-top:0.75rem;"><a href="orders.php?tenant=<?php echo $tenant_id; ?>&phone=<?php echo urlencode($phone); ?>" style="color:var(--j-orange); text-decoration:underline;">View all my orders</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($order_id && $phone): ?>
        <div class="track-form" style="text-align:center; max-width:500px;">
            <i class="fas fa-exclamation-circle" style="font-size:2rem; color:#f87171; margin-bottom:1rem;"></i>
            <h3>Order Not Found</h3>
            <p style="color:var(--j-muted); margin-top:0.5rem;">We couldn't find an order matching that number and phone. Please check and try again.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php include 'shop-footer.php'; ?>
    <script src="shop.js"></script>
    <script>renderCart();</script>
</body>
</html>
