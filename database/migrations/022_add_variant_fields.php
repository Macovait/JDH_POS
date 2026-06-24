<?php
/**
 * Migration 022: Add variant fields for WooCommerce-style variations tab
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Running migration 022: Add variant fields\n";

try {
    $columns = [
        'stock'       => "ALTER TABLE product_variants ADD COLUMN stock INT DEFAULT 0 AFTER sku",
        'barcode'     => "ALTER TABLE product_variants ADD COLUMN barcode VARCHAR(50) DEFAULT NULL AFTER stock",
        'active'      => "ALTER TABLE product_variants ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER barcode",
        'cost_price'  => "ALTER TABLE product_variants ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0.00 AFTER active",
    ];

    foreach ($columns as $colName => $sql) {
        $check = $pdo->query("SHOW COLUMNS FROM product_variants WHERE Field = '{$colName}'")->fetch(PDO::FETCH_ASSOC);
        if (!$check) {
            $pdo->exec($sql);
            echo "   ✓ Added column: {$colName}\n";
        } else {
            echo "   - Column {$colName} already exists, skipping\n";
        }
    }

    echo "Migration 022 completed successfully.\n";
} catch (PDOException $e) {
    error_log("Migration 022 failed: " . $e->getMessage());
    echo "   ✗ Migration failed: " . $e->getMessage() . "\n";
}
