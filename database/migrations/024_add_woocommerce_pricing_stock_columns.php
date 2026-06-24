<?php
/**
 * Migration 024: Add WooCommerce pricing, external product, and stock management columns
 * Adds: sale_price, sale_price_dates_from, sale_price_dates_to, external_url, button_text,
 *       global_unique_id, manage_stock, backorders, low_stock_amount
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Running migration 024: Add pricing, external product, and stock columns\n";

try {
    $columns = [
        'sale_price'           => "ALTER TABLE products ADD COLUMN sale_price DECIMAL(10,2) DEFAULT NULL AFTER price",
        'sale_price_dates_from'  => "ALTER TABLE products ADD COLUMN sale_price_dates_from DATE DEFAULT NULL AFTER sale_price",
        'sale_price_dates_to'    => "ALTER TABLE products ADD COLUMN sale_price_dates_to DATE DEFAULT NULL AFTER sale_price_dates_from",
        'external_url'         => "ALTER TABLE products ADD COLUMN external_url VARCHAR(500) DEFAULT NULL AFTER description",
        'button_text'          => "ALTER TABLE products ADD COLUMN button_text VARCHAR(100) DEFAULT NULL AFTER external_url",
        'global_unique_id'     => "ALTER TABLE products ADD COLUMN global_unique_id VARCHAR(50) DEFAULT NULL AFTER sku",
        'manage_stock'         => "ALTER TABLE products ADD COLUMN manage_stock TINYINT(1) DEFAULT 0 AFTER stock_status",
        'backorders'           => "ALTER TABLE products ADD COLUMN backorders VARCHAR(20) DEFAULT 'no' AFTER manage_stock",
        'low_stock_amount'     => "ALTER TABLE products ADD COLUMN low_stock_amount DECIMAL(10,2) DEFAULT NULL AFTER backorders",
    ];

    foreach ($columns as $colName => $sql) {
        $check = $pdo->query("SHOW COLUMNS FROM products WHERE Field = '{$colName}'")->fetch(PDO::FETCH_ASSOC);
        if (!$check) {
            $pdo->exec($sql);
            echo "   ✓ Added column: {$colName}\n";
        } else {
            echo "   - Column {$colName} already exists, skipping\n";
        }
    }

    echo "Migration 024 completed successfully.\n";

} catch (PDOException $e) {
    error_log("Migration 024 failed: " . $e->getMessage());
    echo "   ✗ Migration failed: " . $e->getMessage() . "\n";
}
