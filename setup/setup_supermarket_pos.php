<?php
/**
 * Supermarket POS System Setup
 * Creates required database tables and indexes
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

echo "=============================================\n";
echo "SUPERMARKET POS SYSTEM SETUP\n";
echo "=============================================\n\n";

try {
    // 1. Create POS Registers (Cashier Shifts) Table
    echo "Creating pos_registers table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS pos_registers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,
            opened_by BIGINT UNSIGNED NOT NULL,
            closed_by BIGINT UNSIGNED NULL,
            opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
            expected_cash DECIMAL(14,2) DEFAULT 0,
            actual_cash DECIMAL(14,2) DEFAULT NULL,
            cash_sales DECIMAL(14,2) DEFAULT 0,
            card_sales DECIMAL(14,2) DEFAULT 0,
            mpesa_sales DECIMAL(14,2) DEFAULT 0,
            other_sales DECIMAL(14,2) DEFAULT 0,
            variance DECIMAL(14,2) DEFAULT NULL,
            notes TEXT,
            opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            closed_at DATETIME NULL,
            status ENUM('open', 'closed', 'audited') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            FOREIGN KEY (opened_by) REFERENCES users(id),
            FOREIGN KEY (closed_by) REFERENCES users(id),
            INDEX idx_company_status (tenant_id, status),
            INDEX idx_branch_date (branch_id, opened_at),
            INDEX idx_opened_by (opened_by, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ pos_registers table created\n\n";
    
    // 2. Add barcode column to products if not exists
    echo "Checking products.barcode column...\n";
    
    $check = $db->query("
        SELECT COUNT(*) as count FROM information_schema.COLUMNS 
        WHERE TABLE_NAME = 'products' 
        AND COLUMN_NAME = 'barcode' 
        AND TABLE_SCHEMA = DATABASE()
    ");
    $exists = $check->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$exists) {
        $db->exec("
            ALTER TABLE products 
            ADD COLUMN barcode VARCHAR(100) NULL AFTER sku,
            ADD INDEX idx_barcode (barcode),
            ADD INDEX idx_barcode_company (barcode, tenant_id)
        ");
        echo "✅ barcode column added to products\n\n";
    } else {
        echo "✅ barcode column already exists\n\n";
    }
    
    // 3. Add indexes for faster product search
    echo "Adding product search indexes...\n";
    
    try {
        $db->exec("ALTER TABLE products ADD INDEX idx_name_search (name(100))");
        echo "✅ Added name search index\n";
    } catch (PDOException $e) {
        echo "⚠️ Name index may already exist\n";
    }
    
    try {
        $db->exec("ALTER TABLE products ADD INDEX idx_company_active (tenant_id, status)");
        echo "✅ Added company status index\n";
    } catch (PDOException $e) {
        echo "⚠️ Company status index may already exist\n";
    }
    
    try {
        $db->exec("ALTER TABLE products ADD INDEX idx_company_branch (tenant_id, branch_id, status)");
        echo "✅ Added company branch index\n";
    } catch (PDOException $e) {
        echo "⚠️ Company branch index may already exist\n";
    }
    
    echo "\n";
    
    // 4. Create held_sales table (optional - localStorage used by default)
    echo "Creating held_sales table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS held_sales (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            branch_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            cart_data JSON NOT NULL,
            customer_id BIGINT UNSIGNED NULL,
            discount_data JSON NULL,
            total DECIMAL(14,2) NOT NULL,
            item_count INT NOT NULL,
            notes TEXT,
            held_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resumed_at DATETIME NULL,
            expires_at DATETIME NULL,
            status ENUM('held', 'resumed', 'expired', 'deleted') DEFAULT 'held',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_company_user (tenant_id, user_id, status),
            INDEX idx_expires (expires_at, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ held_sales table created\n\n";
    
    // 5. Create split_payments table
    echo "Creating split_payment_details table...\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS split_payment_details (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sale_id BIGINT UNSIGNED NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            transaction_reference VARCHAR(100) NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            INDEX idx_sale (sale_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ split_payment_details table created\n\n";
    
    // 6. Add split_payments column to sales if not exists
    echo "Checking sales.split_payments column...\n";
    
    $check = $db->query("
        SELECT COUNT(*) as count FROM information_schema.COLUMNS 
        WHERE TABLE_NAME = 'sales' 
        AND COLUMN_NAME = 'split_payments' 
        AND TABLE_SCHEMA = DATABASE()
    ");
    $exists = $check->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$exists) {
        $db->exec("
            ALTER TABLE sales 
            ADD COLUMN split_payments TINYINT(1) DEFAULT 0 AFTER payment_method,
            ADD COLUMN is_split_payment TINYINT(1) DEFAULT 0 AFTER split_payments
        ");
        echo "✅ split payment columns added to sales\n\n";
    } else {
        echo "✅ split payment columns already exist\n\n";
    }
    
    // 7. Add shift_id to sales
    echo "Checking sales.shift_id column...\n";
    
    $check = $db->query("
        SELECT COUNT(*) as count FROM information_schema.COLUMNS 
        WHERE TABLE_NAME = 'sales' 
        AND COLUMN_NAME = 'shift_id' 
        AND TABLE_SCHEMA = DATABASE()
    ");
    $exists = $check->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$exists) {
        $db->exec("
            ALTER TABLE sales 
            ADD COLUMN shift_id BIGINT UNSIGNED NULL AFTER branch_id,
            ADD INDEX idx_shift (shift_id),
            ADD FOREIGN KEY (shift_id) REFERENCES pos_registers(id)
        ");
        echo "✅ shift_id column added to sales\n\n";
    } else {
        echo "✅ shift_id column already exists\n\n";
    }
    
    echo "=============================================\n";
    echo "SETUP COMPLETE!\n";
    echo "=============================================\n\n";
    
    echo "Next Steps:\n";
    echo "1. Include supermarket-pos.js in your pos.php:\n";
    echo "   <script src=\"/JDH_POS/public/pos/supermarket-pos.js\"></script>\n\n";
    echo "2. Add barcode field to your product form\n";
    echo "3. Configure barcode scanner to send Enter after scan\n";
    echo "4. Test all keyboard shortcuts (F1-F12)\n\n";
    
    echo "Features Enabled:\n";
    echo "  ✅ Barcode scanning with audio feedback\n";
    echo "  ✅ Product cache for instant search\n";
    echo "  ✅ Offline mode with queue\n";
    echo "  ✅ Cashier shift management\n";
    echo "  ✅ Hold & resume sales\n";
    echo "  ✅ Split payments\n";
    echo "  ✅ Receipt reprint\n";
    echo "  ✅ Full keyboard control\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
