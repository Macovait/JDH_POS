<?php
require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Migration 027: Create enterprise attribute tables\n";

try {
    // attributes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attributes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        group_id INT NULL,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(100) NOT NULL,
        type ENUM('text','textarea','dropdown','multiselect','number','color','file','date','boolean') DEFAULT 'text',
        unit VARCHAR(50) NULL,
        is_required TINYINT(1) DEFAULT 0,
        is_filterable TINYINT(1) DEFAULT 0,
        is_variant_forming TINYINT(1) DEFAULT 0,
        business_type_id INT NULL,
        sort_order INT DEFAULT 0,
        status TINYINT(1) DEFAULT 1,
        created_by INT NULL,
        updated_by INT NULL,
        deleted_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_tenant_name (tenant_id, name),
        INDEX idx_tenant_code (tenant_id, code),
        INDEX idx_tenant_status (tenant_id, status),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ Created table: attributes\n";

    // attribute_groups table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attribute_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        business_type_id INT NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ Created table: attribute_groups\n";

    // attribute_values table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attribute_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attribute_id INT NOT NULL,
        tenant_id INT NOT NULL,
        value VARCHAR(255) NOT NULL,
        label VARCHAR(255) NULL,
        color_hex VARCHAR(7) NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_attribute (attribute_id),
        INDEX idx_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ Created table: attribute_values\n";

    // product_attribute_values (link table)
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_attribute_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        attribute_id INT NOT NULL,
        value_id INT NULL,
        custom_value VARCHAR(255) NULL,
        tenant_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product (product_id),
        INDEX idx_attribute (attribute_id),
        INDEX idx_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ Created table: product_attribute_values\n";

    echo "Migration 027 completed.\n";
} catch (PDOException $e) {
    error_log("Migration 027 failed: " . $e->getMessage());
    echo "   ✗ Failed: " . $e->getMessage() . "\n";
}
