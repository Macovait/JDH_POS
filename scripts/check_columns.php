<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();

$tables = ['products', 'categories', 'inventory', 'product_stock', 'product_tags', 'product_tag_relations'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE {$table}");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "{$table} columns: " . implode(', ', $cols) . "\n";
    } catch (Exception $e) {
        echo "{$table}: NOT FOUND (" . $e->getMessage() . ")\n";
    }
}
