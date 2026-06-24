<?php
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

echo "=== Categories columns ===\n";
foreach ($pdo->query('SHOW COLUMNS FROM categories')->fetchAll() as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}

echo "\n=== pos_tenants columns ===\n";
foreach ($pdo->query('SHOW COLUMNS FROM pos_tenants')->fetchAll() as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}

echo "\n=== Table Check ===\n";
foreach (['tenants','storefront_settings','storefront_banners','shop_orders','customer_accounts'] as $t) {
    try {
        $pdo->query("SELECT 1 FROM $t LIMIT 1");
        echo "$t: EXISTS\n";
    } catch (Exception $e) {
        echo "$t: MISSING\n";
    }
}
