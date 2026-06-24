<?php
/**
 * Migration: API Keys, Webhook Events, and Missing P0 Tables
 */

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();
if (!$pdo) {
    die("Failed to connect to database\n");
}

echo "Creating API keys and missing P0 tables...\n";

// tenant_api_keys
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tenant_api_keys (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(100) NOT NULL DEFAULT 'Default',
        api_key VARCHAR(100) NOT NULL UNIQUE,
        api_secret VARCHAR(255) NOT NULL,
        rate_limit INT UNSIGNED NOT NULL DEFAULT 1000,
        permissions JSON,
        request_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_used_at DATETIME,
        expires_at DATE,
        status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
        created_by_admin_id INT UNSIGNED,
        revoked_at DATETIME,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tenant_api_keys_tenant_id (tenant_id),
        INDEX idx_tenant_api_keys_status (status),
        INDEX idx_tenant_api_keys_api_key (api_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ tenant_api_keys\n";

// processed_stripe_events
$pdo->exec("
    CREATE TABLE IF NOT EXISTS processed_stripe_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stripe_event_id VARCHAR(100) NOT NULL UNIQUE,
        event_type VARCHAR(100) NOT NULL,
        payload JSON,
        processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_stripe_event_id (stripe_event_id),
        INDEX idx_stripe_event_type (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "✓ processed_stripe_events\n";

// Ensure credits table has consumed_amount if missing
try {
    $pdo->exec("ALTER TABLE credits ADD COLUMN IF NOT EXISTS consumed_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER remaining_amount");
    echo "✓ credits.consumed_amount\n";
} catch (Exception $e) {
    echo "Note: credits.consumed_amount may already exist\n";
}

// Ensure subscription_history table exists with correct columns
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscription_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED,
            event_type VARCHAR(50) NOT NULL,
            old_plan_id INT UNSIGNED,
            new_plan_id INT UNSIGNED,
            metadata JSON,
            admin_id INT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sub_hist_tenant (tenant_id),
            INDEX idx_sub_hist_event (event_type),
            INDEX idx_sub_hist_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ subscription_history\n";
} catch (Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

echo "\nMigration complete.\n";
