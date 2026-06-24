<?php
/**
 * SaaS Migration - Adds multi-tenant architecture and billing tables
 * Run: php scripts/migrate.php --migration=002_saas_tables
 */

require_once __DIR__ . '/../../src/db.php';

class SaasMigration
{
    private PDO $db;
    private string $prefix;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?? get_db_connection();
        $this->prefix = 'pos_';
    }

    public function up(): bool
    {
        try {
            $this->db->beginTransaction();

            $this->createTenantsTable();
            $this->createTenantConfigsTable();
            $this->createPlansTable();
            $this->createFeaturesTable();
            $this->createPlanFeaturesTable();
            $this->createSubscriptionsTable();
            $this->createInvoicesTable();
            $this->createPaymentMethodsTable();
            $this->createApiKeysTable();
            $this->createApiRateLimitsTable();
            $this->createWebhooksTable();
            $this->createWebhookDeliveriesTable();
            $this->createAuditLogsTable();
            $this->createSessionsTable();
            $this->createTwoFactorTokensTable();
            $this->addTenantIdToExistingTables();
            $this->createIndexes();

            $this->db->commit();
            
            $this->seedDefaultData();
            
            echo "SaaS migration completed successfully!\n";
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("SaaS Migration Error: " . $e->getMessage());
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function createTenantsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}tenants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) UNIQUE NOT NULL,
            subdomain VARCHAR(63) UNIQUE,
            domain VARCHAR(255) UNIQUE,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) UNIQUE,
            status ENUM('active', 'suspended', 'cancelled', 'trial') DEFAULT 'trial',
            plan_id INT UNSIGNED DEFAULT 1,
            parent_tenant_id INT UNSIGNED,
            settings JSON,
            branding JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subdomain (subdomain),
            INDEX idx_uuid (uuid),
            INDEX idx_status (status),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created tenants table\n";
    }

    private function createTenantConfigsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}tenant_configs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            config_key VARCHAR(100) NOT NULL,
            config_value TEXT,
            is_encrypted BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_tenant_config (tenant_id, config_key),
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created tenant_configs table\n";
    }

    private function createPlansTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) DEFAULT 'KES',
            billing_cycle ENUM('monthly', 'quarterly', 'annual') DEFAULT 'monthly',
            trial_days INT DEFAULT 0,
            max_users INT DEFAULT 1,
            max_branches INT DEFAULT 1,
            max_products INT DEFAULT 100,
            max_storage_mb INT DEFAULT 1024,
            max_api_calls INT DEFAULT 1000,
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            is_featured BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created plans table\n";
    }

    private function createFeaturesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}features (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feature_key VARCHAR(100) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            module_name VARCHAR(50) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_module (module_name),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created features table\n";
    }

    private function createPlanFeaturesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}plan_features (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id INT UNSIGNED NOT NULL,
            feature_id INT UNSIGNED NOT NULL,
            is_enabled BOOLEAN DEFAULT TRUE,
            limit_value INT,
            FOREIGN KEY (plan_id) REFERENCES {$this->prefix}plans(id) ON DELETE CASCADE,
            FOREIGN KEY (feature_id) REFERENCES {$this->prefix}features(id) ON DELETE CASCADE,
            UNIQUE KEY uk_plan_feature (plan_id, feature_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created plan_features table\n";
    }

    private function createSubscriptionsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}subscriptions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            status ENUM('trialing', 'active', 'past_due', 'cancelled', 'paused', 'expired') DEFAULT 'trialing',
            billing_cycle ENUM('monthly', 'quarterly', 'annual') DEFAULT 'monthly',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) DEFAULT 'KES',
            tax_amount DECIMAL(10,2) DEFAULT 0,
            trial_ends_at TIMESTAMP NULL,
            current_period_start TIMESTAMP NOT NULL,
            current_period_end TIMESTAMP NOT NULL,
            cancelled_at TIMESTAMP NULL,
            cancel_reason VARCHAR(500),
            payment_method_id INT UNSIGNED,
            stripe_subscription_id VARCHAR(100),
            mpesa_subscription_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES {$this->prefix}plans(id),
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status),
            INDEX idx_period (current_period_start, current_period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created subscriptions table\n";
    }

    private function createInvoicesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}invoices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            invoice_number VARCHAR(50) UNIQUE NOT NULL,
            subscription_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'KES',
            tax_amount DECIMAL(10,2) DEFAULT 0,
            status ENUM('draft', 'pending', 'paid', 'failed', 'void', 'refunded') DEFAULT 'draft',
            due_date TIMESTAMP NOT NULL,
            paid_at TIMESTAMP NULL,
            payment_method VARCHAR(50),
            payment_reference VARCHAR(100),
            stripe_invoice_id VARCHAR(100),
            mpesa_receipt VARCHAR(100),
            pdf_path VARCHAR(500),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (subscription_id) REFERENCES {$this->prefix}subscriptions(id),
            INDEX idx_tenant (tenant_id),
            INDEX idx_status (status),
            INDEX idx_due_date (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created invoices table\n";
    }

    private function createPaymentMethodsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}payment_methods (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            type ENUM('stripe_card', 'stripe_mpesa', 'mpesa', 'bank_transfer', 'cash') NOT NULL,
            is_default BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            stripe_customer_id VARCHAR(100),
            stripe_payment_method_id VARCHAR(100),
            mpesa_phone VARCHAR(20),
            mpesa_shortcode VARCHAR(10),
            bank_name VARCHAR(100),
            bank_account VARCHAR(100),
            bank_code VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created payment_methods table\n";
    }

    private function createApiKeysTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}api_keys (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            key_hash VARCHAR(100) UNIQUE NOT NULL,
            name VARCHAR(100) NOT NULL,
            prefix VARCHAR(10) NOT NULL,
            scopes JSON NOT NULL,
            rate_limit INT DEFAULT 1000,
            last_used_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_key_hash (key_hash),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created api_keys table\n";
    }

    private function createApiRateLimitsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}api_rate_limits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            api_key_id INT UNSIGNED NOT NULL,
            window_start TIMESTAMP NOT NULL,
            request_count INT DEFAULT 0,
            INDEX idx_key_window (api_key_id, window_start),
            FOREIGN KEY (api_key_id) REFERENCES {$this->prefix}api_keys(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created api_rate_limits table\n";
    }

    private function createWebhooksTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}webhooks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            url VARCHAR(500) NOT NULL,
            events JSON NOT NULL,
            secret VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            failure_count INT DEFAULT 0,
            last_triggered_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created webhooks table\n";
    }

    private function createWebhookDeliveriesTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}webhook_deliveries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            webhook_id INT UNSIGNED NOT NULL,
            event VARCHAR(100) NOT NULL,
            payload JSON NOT NULL,
            response_status INT,
            response_body TEXT,
            attempt INT DEFAULT 1,
            status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (webhook_id) REFERENCES {$this->prefix}webhooks(id) ON DELETE CASCADE,
            INDEX idx_webhook (webhook_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created webhook_deliveries table\n";
    }

    private function createAuditLogsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT UNSIGNED,
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            session_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_action (tenant_id, action),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at),
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created audit_logs table\n";
    }

    private function createSessionsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}sessions (
            id VARCHAR(100) PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            payload TEXT,
            last_activity_at TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES {$this->prefix}tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$this->prefix}users(id) ON DELETE CASCADE,
            INDEX idx_tenant_user (tenant_id, user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created sessions table\n";
    }

    private function createTwoFactorTokensTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->prefix}two_factor_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            method ENUM('email', 'sms', 'totp') NOT NULL,
            token VARCHAR(10) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_token (token),
            FOREIGN KEY (user_id) REFERENCES {$this->prefix}users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        echo "Created two_factor_tokens table\n";
    }

    private function addTenantIdToExistingTables(): void
    {
        $tables = ['users', 'branches', 'products', 'categories', 'sales', 'expenses'];
        
        foreach ($tables as $table) {
            $fullTable = $this->prefix . $table;
            
            try {
                $this->db->exec("ALTER TABLE {$fullTable} ADD COLUMN tenant_id INT UNSIGNED NULL");
                $this->db->exec("ALTER TABLE {$fullTable} ADD INDEX idx_tenant (tenant_id)");
                echo "Added tenant_id to {$fullTable}\n";
            } catch (PDOException $e) {
                echo "tenant_id may already exist in {$fullTable}: " . $e->getMessage() . "\n";
            }
        }
    }

    private function createIndexes(): void
    {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_company_active ON {$this->prefix}companies(is_active)",
            "CREATE INDEX IF NOT EXISTS idx_user_company ON {$this->prefix}users(tenant_id, status)",
            "CREATE INDEX IF NOT EXISTS idx_product_company ON {$this->prefix}products(tenant_id, status)",
            "CREATE INDEX IF NOT EXISTS idx_sale_company ON {$this->prefix}sales(tenant_id, created_at)",
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->db->exec($index);
            } catch (PDOException $e) {
                // Index may already exist
            }
        }
        echo "Created indexes\n";
    }

    private function seedDefaultData(): void
    {
        // Seed default plans
        $plans = [
            ['name' => 'Starter', 'slug' => 'starter', 'description' => 'Perfect for small businesses starting out', 'price' => 0, 'max_users' => 2, 'max_branches' => 1, 'max_products' => 100, 'max_api_calls' => 500, 'trial_days' => 14],
            ['name' => 'Growth', 'slug' => 'growth', 'description' => 'For growing businesses with multiple staff', 'price' => 2999, 'max_users' => 10, 'max_branches' => 3, 'max_products' => 1000, 'max_api_calls' => 5000, 'trial_days' => 14],
            ['name' => 'Business', 'slug' => 'business', 'description' => 'Full-featured for established businesses', 'price' => 7999, 'max_users' => 25, 'max_branches' => 10, 'max_products' => 5000, 'max_api_calls' => 20000, 'trial_days' => 14],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'description' => 'Unlimited for large organizations', 'price' => 19999, 'max_users' => 100, 'max_branches' => 50, 'max_products' => 999999, 'max_api_calls' => 999999, 'trial_days' => 30],
        ];
        
        $stmt = $this->db->prepare("INSERT IGNORE INTO {$this->prefix}plans (name, slug, description, price, max_users, max_branches, max_products, max_api_calls, trial_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($plans as $plan) {
            $stmt->execute([
                $plan['name'], $plan['slug'], $plan['description'], 
                $plan['price'], $plan['max_users'], $plan['max_branches'], 
                $plan['max_products'], $plan['max_api_calls'], $plan['trial_days']
            ]);
        }
        echo "Seeded default plans\n";
        
        // Seed features
        $features = [
            ['feature_key' => 'pos_access', 'name' => 'Point of Sale', 'module_name' => 'pos'],
            ['feature_key' => 'sales_history', 'name' => 'Sales History', 'module_name' => 'pos'],
            ['feature_key' => 'sales_returns', 'name' => 'Sales Returns', 'module_name' => 'pos'],
            ['feature_key' => 'product_management', 'name' => 'Product Management', 'module_name' => 'products'],
            ['feature_key' => 'category_management', 'name' => 'Category Management', 'module_name' => 'products'],
            ['feature_key' => 'product_import', 'name' => 'Product Import/Export', 'module_name' => 'products'],
            ['feature_key' => 'inventory_tracking', 'name' => 'Inventory Tracking', 'module_name' => 'inventory'],
            ['feature_key' => 'inventory_transfers', 'name' => 'Stock Transfers', 'module_name' => 'inventory'],
            ['feature_key' => 'inventory_alerts', 'name' => 'Low Stock Alerts', 'module_name' => 'inventory'],
            ['feature_key' => 'multi_warehouse', 'name' => 'Multi-Warehouse', 'module_name' => 'inventory'],
            ['feature_key' => 'customer_management', 'name' => 'Customer Management', 'module_name' => 'customers'],
            ['feature_key' => 'customer_loyalty', 'name' => 'Loyalty Program', 'module_name' => 'customers'],
            ['feature_key' => 'supplier_management', 'name' => 'Supplier Management', 'module_name' => 'inventory'],
            ['feature_key' => 'purchase_orders', 'name' => 'Purchase Orders', 'module_name' => 'purchases'],
            ['feature_key' => 'purchase_receiving', 'name' => 'Purchase Receiving', 'module_name' => 'purchases'],
            ['feature_key' => 'reports_basic', 'name' => 'Basic Reports', 'module_name' => 'reports'],
            ['feature_key' => 'reports_advanced', 'name' => 'Advanced Analytics', 'module_name' => 'reports'],
            ['feature_key' => 'expense_tracking', 'name' => 'Expense Tracking', 'module_name' => 'expenses'],
            ['feature_key' => 'expense_categories', 'name' => 'Expense Categories', 'module_name' => 'expenses'],
            ['feature_key' => 'multi_branch', 'name' => 'Multi-Branch', 'module_name' => 'branches'],
            ['feature_key' => 'user_management', 'name' => 'User Management', 'module_name' => 'users'],
            ['feature_key' => 'role_management', 'name' => 'Role Management', 'module_name' => 'users'],
            ['feature_key' => 'api_access', 'name' => 'API Access', 'module_name' => 'api'],
            ['feature_key' => 'webhooks', 'name' => 'Webhooks', 'module_name' => 'api'],
            ['feature_key' => '2fa', 'name' => 'Two-Factor Auth', 'module_name' => 'security'],
            ['feature_key' => 'custom_branding', 'name' => 'Custom Branding', 'module_name' => 'settings'],
            ['feature_key' => 'mpesa_integration', 'name' => 'MPesa Integration', 'module_name' => 'payments'],
            ['feature_key' => 'stripe_integration', 'name' => 'Stripe Integration', 'module_name' => 'payments'],
        ];
        
        $stmt = $this->db->prepare("INSERT IGNORE INTO {$this->prefix}features (feature_key, name, module_name) VALUES (?, ?, ?)");
        
        foreach ($features as $feature) {
            $stmt->execute([$feature['feature_key'], $feature['name'], $feature['module_name']]);
        }
        echo "Seeded default features\n";
        
        // Link features to plans
        $stmt = $this->db->query("SELECT id FROM {$this->prefix}plans");
        $planIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmtFeature = $this->db->query("SELECT id FROM {$this->prefix}features");
        $featureIds = $stmtFeature->fetchAll(PDO::FETCH_COLUMN);
        
        $linkStmt = $this->db->prepare("INSERT IGNORE INTO {$this->prefix}plan_features (plan_id, feature_id, is_enabled) VALUES (?, ?, ?)");
        
        foreach ($planIds as $planId) {
            foreach ($featureIds as $featureId) {
                // Enable most features for all plans except free tier
                $enabled = $planId > 1 || in_array($featureId, [1, 2, 3, 4, 5, 15, 19, 20, 21]);
                $linkStmt->execute([$planId, $featureId, $enabled ? 1 : 0]);
            }
        }
        echo "Linked features to plans\n";
    }
}

// Run migration (called from migrate.php script)
function run_saas_migration(\PDO $db): bool
{
    $migration = new SaasMigration($db);
    return $migration->up();
}

// Alternative entry point - run directly from CLI
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $db = get_db_connection();
    $migration = new SaasMigration($db);
    $result = $migration->up();
    exit($result ? 0 : 1);
}