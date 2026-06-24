<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

/**
 * Create features, plan_features, tenant_entitlements, tenant_add_ons tables.
 */
final class EntitlementsAndFeaturesMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->createFeatures();
        $this->createPlanFeatures();
        $this->createTenantEntitlements();
        $this->createTenantAddOns();
        $this->seedFeatures();
        echo "Migration 010 completed successfully.\n";
    }

    private function createFeatures(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS features (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            category ENUM('core', 'add_on', 'beta', 'enterprise') DEFAULT 'core',
            default_limit BIGINT DEFAULT NULL,
            default_unit VARCHAR(30) DEFAULT NULL,
            is_beta BOOLEAN DEFAULT FALSE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created features table\n";
    }

    private function createPlanFeatures(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS plan_features (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id BIGINT UNSIGNED NOT NULL,
            feature_id BIGINT UNSIGNED NOT NULL,
            limit_value BIGINT DEFAULT NULL,
            is_unlimited BOOLEAN DEFAULT FALSE,
            is_included BOOLEAN DEFAULT TRUE,
            overage_rate DECIMAL(12, 4) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE,
            UNIQUE INDEX idx_plan_feature (plan_id, feature_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created plan_features table\n";
    }

    private function createTenantEntitlements(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS tenant_entitlements (
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
            UNIQUE INDEX idx_tenant_feature (tenant_id, feature_id, effective_from),
            INDEX idx_effective (effective_from, effective_until),
            INDEX idx_enabled (tenant_id, is_enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created tenant_entitlements table\n";
    }

    private function createTenantAddOns(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS tenant_add_ons (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created tenant_add_ons table\n";
    }

    private function seedFeatures(): void
    {
        $features = [
            ['slug' => 'pos', 'name' => 'Point of Sale', 'category' => 'core', 'default_limit' => null, 'default_unit' => null, 'is_beta' => false, 'description' => 'Core POS functionality'],
            ['slug' => 'ecommerce', 'name' => 'E-commerce', 'category' => 'core', 'default_limit' => null, 'default_unit' => null, 'is_beta' => false, 'description' => 'Online store'],
            ['slug' => 'inventory', 'name' => 'Inventory Management', 'category' => 'core', 'default_limit' => null, 'default_unit' => null, 'is_beta' => false, 'description' => 'Stock tracking and management'],
            ['slug' => 'analytics', 'name' => 'Advanced Analytics', 'category' => 'add_on', 'default_limit' => null, 'default_unit' => null, 'is_beta' => false, 'description' => 'Dashboards and reports'],
            ['slug' => 'api_access', 'name' => 'API Access', 'category' => 'add_on', 'default_limit' => 1000, 'default_unit' => 'calls/day', 'is_beta' => false, 'description' => 'REST API access'],
            ['slug' => 'multi_branch', 'name' => 'Multi-Branch', 'category' => 'core', 'default_limit' => 1, 'default_unit' => 'branches', 'is_beta' => false, 'description' => 'Number of branches allowed'],
            ['slug' => 'users', 'name' => 'Users', 'category' => 'core', 'default_limit' => 5, 'default_unit' => 'users', 'is_beta' => false, 'description' => 'Number of staff users'],
            ['slug' => 'products', 'name' => 'Products', 'category' => 'core', 'default_limit' => 100, 'default_unit' => 'products', 'is_beta' => false, 'description' => 'Number of products allowed'],
            ['slug' => 'webhooks', 'name' => 'Webhooks', 'category' => 'add_on', 'default_limit' => 5, 'default_unit' => 'endpoints', 'is_beta' => false, 'description' => 'Outgoing webhook subscriptions'],
            ['slug' => 'white_label', 'name' => 'White Label', 'category' => 'enterprise', 'default_limit' => null, 'default_unit' => null, 'is_beta' => false, 'description' => 'Custom branding and domains'],
        ];

        $stmt = $this->pdo->prepare("INSERT IGNORE INTO features (slug, name, category, default_limit, default_unit, is_beta, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($features as $f) {
            $stmt->execute([$f['slug'], $f['name'], $f['category'], $f['default_limit'], $f['default_unit'], $f['is_beta'] ? 1 : 0, $f['description']]);
        }
        echo "Seeded " . count($features) . " features\n";
    }
}

$migration = new EntitlementsAndFeaturesMigration();
$migration->up();
