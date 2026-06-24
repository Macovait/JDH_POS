<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_entitlements (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        feature_id BIGINT UNSIGNED NOT NULL,
        source ENUM('plan', 'override', 'add_on', 'trial', 'promotion') DEFAULT 'plan',
        limit_value BIGINT DEFAULT NULL,
        is_unlimited BOOLEAN DEFAULT FALSE,
        is_enabled BOOLEAN DEFAULT TRUE,
        effective_from DATE NOT NULL,
        effective_until DATE DEFAULT NULL,
        overridden_by_admin_id BIGINT UNSIGNED NULL,
        override_reason TEXT,
        grace_period_days INT DEFAULT 0,
        grace_period_ends_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE,
        FOREIGN KEY (overridden_by_admin_id) REFERENCES admins(id),
        UNIQUE INDEX idx_tenant_feature (tenant_id, feature_id, effective_from),
        INDEX idx_effective (effective_from, effective_until),
        INDEX idx_enabled (tenant_id, is_enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "tenant_entitlements OK\n";
} catch (PDOException $e) {
    echo "tenant_entitlements ERROR: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_add_ons (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id BIGINT UNSIGNED NOT NULL,
        feature_id BIGINT UNSIGNED NOT NULL,
        quantity INT DEFAULT 1,
        unit_price DECIMAL(12, 2) NOT NULL,
        billing_cycle ENUM('monthly', 'yearly', 'one_time') DEFAULT 'monthly',
        is_active BOOLEAN DEFAULT TRUE,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ends_at TIMESTAMP NULL,
        cancelled_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        FOREIGN KEY (feature_id) REFERENCES features(id),
        INDEX idx_tenant_active (tenant_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "tenant_add_ons OK\n";
} catch (PDOException $e) {
    echo "tenant_add_ons ERROR: " . $e->getMessage() . "\n";
}
