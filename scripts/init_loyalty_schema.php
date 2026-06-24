<?php
/**
 * Initialize Loyalty Program Database Schema
 * Creates all necessary tables and columns for the loyalty program
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

try {
    $pdo = get_db_connection();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    echo "Initializing Loyalty Program Database Schema...\n\n";

    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    // Create Loyalty Points Log table
    echo "Creating loyalty_points_log table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS loyalty_points_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED,
            points_change INT NOT NULL COMMENT 'Positive or negative',
            previous_balance INT UNSIGNED DEFAULT 0,
            new_balance INT UNSIGNED DEFAULT 0,
            reason VARCHAR(255),
            expiry_date TIMESTAMP NULL,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_id (customer_id),
            INDEX idx_company_id (tenant_id),
            INDEX idx_created_at (created_at),
            INDEX idx_expiry_date (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ loyalty_points_log\n";

    // Create Loyalty Rewards table
    echo "Creating loyalty_rewards table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS loyalty_rewards (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            points_required INT UNSIGNED NOT NULL,
            discount_type ENUM('fixed', 'percentage') DEFAULT 'fixed',
            discount_value DECIMAL(10, 2) NOT NULL,
            max_uses INT UNSIGNED,
            stock_available INT UNSIGNED,
            stock_used INT UNSIGNED DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            is_active TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL,
            INDEX idx_company_id (tenant_id),
            INDEX idx_status (status),
            INDEX idx_points_required (points_required)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ loyalty_rewards\n";

    // Create Loyalty Redemptions table
    echo "Creating loyalty_redemptions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS loyalty_redemptions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            reward_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED,
            points_redeemed INT UNSIGNED NOT NULL,
            status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
            notes TEXT,
            created_by INT UNSIGNED,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_id (customer_id),
            INDEX idx_reward_id (reward_id),
            INDEX idx_company_id (tenant_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "  ✓ loyalty_redemptions\n";

    // Add columns to customers table if missing
    echo "Verifying customers table columns...\n";
    $result = $pdo->query("SHOW COLUMNS FROM customers");
    $existing_columns = array_column($result->fetchAll(PDO::FETCH_ASSOC), 'Field');

    if (!in_array('loyalty_tier', $existing_columns)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN loyalty_tier VARCHAR(50) DEFAULT 'bronze'");
        echo "  ✓ Added loyalty_tier column\n";
    }

    if (!in_array('total_spent', $existing_columns)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN total_spent DECIMAL(15, 2) DEFAULT 0");
        echo "  ✓ Added total_spent column\n";
    }

    if (!in_array('loyalty_points', $existing_columns)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN loyalty_points INT UNSIGNED DEFAULT 0");
        echo "  ✓ Added loyalty_points column\n";
    }

    if (!in_array('points_expired', $existing_columns)) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN points_expired INT UNSIGNED DEFAULT 0");
        echo "  ✓ Added points_expired column\n";
    }

    // Create indexes
    echo "Creating indexes...\n";
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_customers_loyalty_tier ON customers(loyalty_tier)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_customers_loyalty_points ON customers(loyalty_points)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_customers_total_spent ON customers(total_spent)");
        echo "  ✓ Indexes created\n";
    } catch (Exception $e) {
        echo "  ℹ Indexes already exist\n";
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "\n✓ Loyalty program schema initialized successfully!\n";
    echo "\nDatabase is ready for loyalty program operations!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>