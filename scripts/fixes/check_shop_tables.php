<?php
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';
$pdo = get_db_connection();
$tables = ['online_orders','online_order_items','storefront_settings','storefront_banners','carts','cart_items','coupons','product_reviews','featured_products','customer_accounts'];
foreach ($tables as $t) {
    $exists = $pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn();
    echo ($exists ? 'OK' : 'MISSING') . "\t$t\n";
}
