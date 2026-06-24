<?php
require_once __DIR__ . '/src/paths.php';
require_once __DIR__ . '/src/db.php';
$pdo = get_db_connection();

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
    
    if (in_array('online_order_items', $tables)) {
        $stmt = $pdo->query("DESCRIBE online_order_items");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        echo "online_order_items columns: " . implode(', ', $cols) . "\n";
    } else {
        echo "online_order_items table MISSING\n";
    }
    
    if (in_array('notification_jobs', $tables)) {
        $stmt = $pdo->query("DESCRIBE notification_jobs");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        echo "notification_jobs columns: " . implode(', ', $cols) . "\n";
    } else {
        echo "notification_jobs table MISSING\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
