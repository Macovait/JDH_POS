<?php
/**
 * Migration 023: Add product_type, virtual, downloadable columns
 * Enables WooCommerce-style product type selection (simple, grouped, external, variable)
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Running migration 023: Add product_type, virtual, downloadable\n";

try {
    $columns = [
        'product_type'  => "ALTER TABLE products ADD COLUMN product_type VARCHAR(20) DEFAULT 'simple' AFTER enable_reviews",
        'virtual'       => "ALTER TABLE products ADD COLUMN virtual TINYINT(1) DEFAULT 0 AFTER product_type",
        'downloadable'  => "ALTER TABLE products ADD COLUMN downloadable TINYINT(1) DEFAULT 0 AFTER virtual",
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

    echo "Migration 023 completed successfully.\n";

} catch (PDOException $e) {
    error_log("Migration 023 failed: " . $e->getMessage());
    echo "   ✗ Migration failed: " . $e->getMessage() . "\n";
}
