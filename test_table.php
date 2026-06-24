<?php
require_once __DIR__ . '/src/paths.php';
require_once __DIR__ . '/src/db.php';
$pdo = get_db_connection();

try {
    $stmt = $pdo->query("DESCRIBE online_orders");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    echo "Columns: " . implode(', ', $cols) . "\n";
    
    $count = $pdo->query("SELECT COUNT(*) FROM online_orders WHERE tenant_id = 1")->fetchColumn();
    echo "Orders for tenant 1: $count\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT uuid, order_number, customer_name, created_at FROM online_orders WHERE tenant_id = 1 LIMIT 3");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "UUID: {$row['uuid']}, Order: {$row['order_number']}, Name: {$row['customer_name']}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
