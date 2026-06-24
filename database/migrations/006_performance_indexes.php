<?php
/**
 * Migration: Performance Indexes for POS
 * Adds indexes to improve query performance
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

echo "Applying performance indexes...\n";

try {
    $pdo = get_db_connection();
    
    // 1. Products table indexes
    echo "1. Adding products table indexes...\n";
    
    $indexes = [
        'idx_products_tenant_active' => 'ALTER TABLE products ADD INDEX idx_products_tenant_active (tenant_id, active, deleted_at)',
        'idx_products_category' => 'ALTER TABLE products ADD INDEX idx_products_category (category_id)',
        'idx_products_name' => 'ALTER TABLE products ADD INDEX idx_products_name (name)',
    ];
    
    foreach ($indexes as $name => $sql) {
        try {
            // Check if index already exists
            $table = 'products';
            $check = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$name}'");
            if ($check->rowCount() == 0) {
                $pdo->exec($sql);
                echo "   ✓ Created index: {$name}\n";
            } else {
                echo "   - Index already exists: {$name}\n";
            }
        } catch (PDOException $e) {
            echo "   ✗ Error creating {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    // 2. Sales table indexes
    echo "2. Adding sales table indexes...\n";
    
    $sales_indexes = [
        'idx_sales_date_tenant' => 'ALTER TABLE sales ADD INDEX idx_sales_date_tenant (created_at, tenant_id, branch_id)',
        'idx_sales_branch' => 'ALTER TABLE sales ADD INDEX idx_sales_branch (branch_id, created_at)',
        'idx_sales_invoice' => 'ALTER TABLE sales ADD INDEX idx_sales_invoice (invoice_number)',
    ];
    
    foreach ($sales_indexes as $name => $sql) {
        try {
            $check = $pdo->query("SHOW INDEX FROM sales WHERE Key_name = '{$name}'");
            if ($check->rowCount() == 0) {
                $pdo->exec($sql);
                echo "   ✓ Created index: {$name}\n";
            } else {
                echo "   - Index already exists: {$name}\n";
            }
        } catch (PDOException $e) {
            echo "   ✗ Error creating {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Inventory table indexes
    echo "3. Adding inventory table indexes...\n";
    
    $inventory_indexes = [
        'idx_inventory_product_branch' => 'ALTER TABLE inventory ADD INDEX idx_inventory_product_branch (product_id, branch_id)',
        'idx_inventory_tenant' => 'ALTER TABLE inventory ADD INDEX idx_inventory_tenant (tenant_id)',
    ];
    
    foreach ($inventory_indexes as $name => $sql) {
        try {
            $check = $pdo->query("SHOW INDEX FROM inventory WHERE Key_name = '{$name}'");
            if ($check->rowCount() == 0) {
                $pdo->exec($sql);
                echo "   ✓ Created index: {$name}\n";
            } else {
                echo "   - Index already exists: {$name}\n";
            }
        } catch (PDOException $e) {
            echo "   ✗ Error creating {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Categories table indexes
    echo "4. Adding categories table indexes...\n";
    
    try {
        $check = $pdo->query("SHOW INDEX FROM categories WHERE Key_name = 'idx_categories_tenant'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE categories ADD INDEX idx_categories_tenant (tenant_id, active)");
            echo "   ✓ Created index: idx_categories_tenant\n";
        } else {
            echo "   - Index already exists: idx_categories_tenant\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
    
    // 5. Customers table indexes
    echo "5. Adding customers table indexes...\n";
    
    $customer_indexes = [
        'idx_customers_tenant' => 'ALTER TABLE customers ADD INDEX idx_customers_tenant (tenant_id)',
        'idx_customers_phone' => 'ALTER TABLE customers ADD INDEX idx_customers_phone (phone)',
    ];
    
    foreach ($customer_indexes as $name => $sql) {
        try {
            $check = $pdo->query("SHOW INDEX FROM customers WHERE Key_name = '{$name}'");
            if ($check->rowCount() == 0) {
                $pdo->exec($sql);
                echo "   ✓ Created index: {$name}\n";
            } else {
                echo "   - Index already exists: {$name}\n";
            }
        } catch (PDOException $e) {
            echo "   ✗ Error creating {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    // 6. Settings table indexes
    echo "6. Adding settings table indexes...\n";
    
    try {
        $check = $pdo->query("SHOW INDEX FROM settings WHERE Key_name = 'idx_settings_key_tenant'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE settings ADD INDEX idx_settings_key_tenant (setting_key, tenant_id)");
            echo "   ✓ Created index: idx_settings_key_tenant\n";
        } else {
            echo "   - Index already exists: idx_settings_key_tenant\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
    }
    
    // 7. Sale items table indexes
    echo "7. Adding sale_items table indexes...\n";
    
    $sale_items_indexes = [
        'idx_saleitems_sale' => 'ALTER TABLE sale_items ADD INDEX idx_saleitems_sale (sale_id)',
        'idx_saleitems_product' => 'ALTER TABLE sale_items ADD INDEX idx_saleitems_product (product_id)',
    ];
    
    foreach ($sale_items_indexes as $name => $sql) {
        try {
            $check = $pdo->query("SHOW INDEX FROM sale_items WHERE Key_name = '{$name}'");
            if ($check->rowCount() == 0) {
                $pdo->exec($sql);
                echo "   ✓ Created index: {$name}\n";
            } else {
                echo "   - Index already exists: {$name}\n";
            }
        } catch (PDOException $e) {
            echo "   ✗ Error creating {$name}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ Performance indexes migration complete!\n";
    
} catch (PDOException $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
