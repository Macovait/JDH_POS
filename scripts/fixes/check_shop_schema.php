<?php
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

echo "=== Table Check ===\n";
$tables = ['tenants', 'pos_tenants', 'products', 'storefront_settings', 'shop_orders', 'customer_accounts'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $t LIMIT 1")->fetchColumn();
        echo "$t: EXISTS (rows: $count)\n";
    } catch (Exception $e) {
        echo "$t: MISSING (" . $e->getMessage() . ")\n";
    }
}

echo "\n=== Products columns ===\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo $c['Field'] . " (" . $c['Type'] . ")\n";
} catch (Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

echo "\n=== Settings columns ===\n";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo $c['Field'] . " (" . $c['Type'] . ")\n";
} catch (Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
