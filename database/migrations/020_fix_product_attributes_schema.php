<?php
/**
 * Migration: Fix product_attributes schema for global attribute templates
 * - Makes product_id nullable so global attributes (templates) can have NULL product_id
 * - Recreates FK constraint to allow NULL references
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();

echo "Running migration 020: Fix product_attributes schema for global attribute templates\n";

try {
    // Check if product_id is already nullable
    $colInfo = $pdo->query("SHOW COLUMNS FROM product_attributes WHERE Field = 'product_id'")->fetch(PDO::FETCH_ASSOC);
    $isNullable = $colInfo && strtoupper($colInfo['Null']) === 'YES';

    if (!$isNullable) {
        // Step 1: Drop existing FK constraint (it blocks making column nullable if there are issues)
        $fkCheck = $pdo->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'product_attributes' 
            AND CONSTRAINT_NAME = 'fk_product_attributes_product'
        ")->fetch(PDO::FETCH_ASSOC);

        if ($fkCheck) {
            $pdo->exec("ALTER TABLE product_attributes DROP FOREIGN KEY fk_product_attributes_product");
            echo "   ✓ Dropped FK: fk_product_attributes_product\n";
        }

        // Step 2: Make product_id nullable
        $pdo->exec("ALTER TABLE product_attributes MODIFY product_id int(11) NULL");
        echo "   ✓ Made product_id nullable\n";

        // Step 3: Recreate FK allowing NULL
        try {
            $pdo->exec("
                ALTER TABLE product_attributes
                ADD CONSTRAINT fk_product_attributes_product
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ");
            echo "   ✓ Recreated FK: fk_product_attributes_product\n";
        } catch (PDOException $e) {
            // FK may fail if orphaned rows exist; log but continue
            error_log("FK recreate warning: " . $e->getMessage());
            echo "   ⚠ FK recreate skipped (may have orphaned rows): " . $e->getMessage() . "\n";
        }
    } else {
        echo "   - product_id already nullable, skipping\n";
    }

    // Step 4: Add unique constraint on tenant_id + attribute_name for global attributes (product_id IS NULL)
    // MySQL 8.0.13+ supports functional indexes; for broader compatibility we just add a regular unique index
    // and rely on PHP pre-validation to enforce the global-attribute uniqueness.
    $indexCheck = $pdo->query("
        SELECT INDEX_NAME 
        FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'product_attributes' 
        AND INDEX_NAME = 'idx_unique_tenant_attribute'
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$indexCheck) {
        // We can't easily enforce uniqueness only for NULL product_id in older MySQL,
        // so we add a regular index and let PHP enforce the business rule.
        $pdo->exec("ALTER TABLE product_attributes ADD INDEX idx_tenant_attribute (tenant_id, attribute_name)");
        echo "   ✓ Added index: idx_tenant_attribute\n";
    } else {
        echo "   - Index idx_tenant_attribute already exists\n";
    }

    echo "Migration 020 completed successfully.\n";

} catch (PDOException $e) {
    error_log("Migration 020 failed: " . $e->getMessage());
    echo "   ✗ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}
