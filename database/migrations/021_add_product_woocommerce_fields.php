<?php
/**
 * Migration 021: Add WooCommerce-style product fields
 * Adds missing columns to products table and creates linked_products junction table.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Running migration 021: Add WooCommerce-style product fields\n";

try {
    $columns = [
        'stock_status'       => "ALTER TABLE products ADD COLUMN stock_status VARCHAR(20) DEFAULT 'instock' AFTER reorder_level",
        'sold_individually'    => "ALTER TABLE products ADD COLUMN sold_individually TINYINT(1) DEFAULT 0 AFTER stock_status",
        'weight'             => "ALTER TABLE products ADD COLUMN weight DECIMAL(10,3) DEFAULT NULL AFTER sold_individually",
        'length'             => "ALTER TABLE products ADD COLUMN length DECIMAL(10,3) DEFAULT NULL AFTER weight",
        'width'              => "ALTER TABLE products ADD COLUMN width DECIMAL(10,3) DEFAULT NULL AFTER length",
        'height'             => "ALTER TABLE products ADD COLUMN height DECIMAL(10,3) DEFAULT NULL AFTER width",
        'shipping_class'     => "ALTER TABLE products ADD COLUMN shipping_class VARCHAR(50) DEFAULT NULL AFTER height",
        'purchase_note'      => "ALTER TABLE products ADD COLUMN purchase_note TEXT DEFAULT NULL AFTER shipping_class",
        'menu_order'         => "ALTER TABLE products ADD COLUMN menu_order INT DEFAULT 0 AFTER purchase_note",
        'enable_reviews'     => "ALTER TABLE products ADD COLUMN enable_reviews TINYINT(1) DEFAULT 1 AFTER menu_order",
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

    // Create linked_products table for upsells / cross-sells
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'product_linked_products'")->fetch(PDO::FETCH_ASSOC);
    if (!$tableCheck) {
        $pdo->exec("CREATE TABLE product_linked_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED DEFAULT NULL,
            product_id INT NOT NULL,
            linked_product_id INT NOT NULL,
            link_type ENUM('upsell','cross_sell') NOT NULL DEFAULT 'upsell',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_linked_product (product_id, linked_product_id, link_type),
            KEY idx_tenant (tenant_id),
            CONSTRAINT fk_linked_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        echo "   ✓ Created table: product_linked_products\n";
    } else {
        echo "   - Table product_linked_products already exists, skipping\n";
    }

    echo "Migration 021 completed successfully.\n";

} catch (PDOException $e) {
    error_log("Migration 021 failed: " . $e->getMessage());
    echo "   ✗ Migration failed: " . $e->getMessage() . "\n";
}
