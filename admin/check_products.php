<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();

echo "=== PRODUCTS TABLE STRUCTURE ===\n";
$stmt = $pdo->query("SHOW COLUMNS FROM products");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== PRODUCTS STATUS VALUES ===\n";
try {
    $stmt = $pdo->query("SELECT DISTINCT status FROM products LIMIT 10");
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Status values found: " . implode(', ', $statuses) . "\n";
} catch (Exception $e) {
    echo "No 'status' column or error: " . $e->getMessage() . "\n";
}

echo "\n=== ACTIVE COLUMN CHECK ===\n";
try {
    $stmt = $pdo->query("SELECT DISTINCT is_active FROM products LIMIT 10");
    $active = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "is_active values: " . implode(', ', $active) . "\n";
} catch (Exception $e) {
    echo "No 'is_active' column\n";
}

echo "\n=== TOTAL PRODUCTS ===\n";
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
echo "Total: " . $stmt->fetchColumn() . "\n";

echo "\n=== ACTIVE PRODUCTS ===\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1");
    echo "Active (is_active=1): " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
        echo "Active (status='active'): " . $stmt->fetchColumn() . "\n";
    } catch (Exception $e2) {
        echo "Cannot determine active products\n";
    }
}
