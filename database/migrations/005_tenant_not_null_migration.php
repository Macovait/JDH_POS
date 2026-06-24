<?php
/**
 * Migration 005: Make tenant_id NOT NULL everywhere + add shop index + online_orders FK
 * Run: php database/migrations/005_tenant_not_null_migration.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class TenantNotNullMigration
{
    private PDO $pdo;
    private int $defaultTenantId = 1;

    /** Tables that are legitimately global and should be skipped */
    private array $globalTables = [
        'admins', 'admin_notifications', 'admin_roles',
        'backup_all_tables_20260426', 'business_types',
        'category_templates', 'login_rate_limits',
        'owner_audit_logs', 'password_resets', 'password_reset_tokens',
        'plans', 'plan_features',
        'pos_api_rate_limits', 'pos_features', 'pos_plans',
        'pos_plan_features', 'pos_tenants', 'pos_webhook_deliveries',
        'rate_limits', 'schema_migrations', 'session_security',
        'subscription_plans', 'support_access_actions',
        'system_analytics', 'system_health_metrics',
        'tenants',                       // self-referencing
        'tenant_configs',                // already NOT NULL
        'tenant_sessions',               // already NOT NULL
        'tenant_subscriptions',          // already NOT NULL
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
    }

    public function run(): void
    {
        $this->log('=== Starting 005_tenant_not_null_migration ===');

        $this->defaultTenantId = $this->ensureDefaultTenant();
        $this->log("Using default tenant ID: {$this->defaultTenantId}");

        // 1. Make tenant_id NOT NULL on all scoped tables
        $this->makeAllTenantIdsNotNull();

        // 2. Add shop catalog composite index
        $this->addShopCatalogIndex();

        // 3. Add missing FK for online_orders -> customers
        $this->addOnlineOrdersCustomerFk();

        $this->log('=== Migration completed successfully ===');
    }

    /**
     * Get the first active tenant to use as the default for back-filling.
     */
    private function ensureDefaultTenant(): int
    {
        $id = (int) $this->queryValue(
            "SELECT id FROM tenants WHERE deleted_at IS NULL ORDER BY id LIMIT 1"
        );

        if ($id > 0) {
            return $id;
        }

        // If no tenants table, try pos_tenants
        $id = (int) $this->queryValue(
            "SELECT id FROM pos_tenants WHERE status = 'active' ORDER BY id LIMIT 1"
        );

        if ($id > 0) {
            return $id;
        }

        throw new RuntimeException('No active tenant found. Please create a tenant first.');
    }

    /**
     * Find every table with a nullable tenant_id column, back-fill NULLs,
     * then ALTER to NOT NULL.
     */
    private function makeAllTenantIdsNotNull(): void
    {
        $tables = $this->getScopedTablesWithNullableTenant();

        foreach ($tables as $table) {
            try {
                $nullCount = (int) $this->queryValue(
                    "SELECT COUNT(*) FROM `{$table}` WHERE tenant_id IS NULL"
                );

                if ($nullCount > 0) {
                    $this->log("Back-filling {$nullCount} NULL tenant_id(s) in {$table}...");
                    $this->pdo->exec(
                        "UPDATE `{$table}` SET tenant_id = {$this->defaultTenantId} WHERE tenant_id IS NULL"
                    );
                }

                $this->pdo->exec(
                    "ALTER TABLE `{$table}` MODIFY COLUMN tenant_id BIGINT(20) UNSIGNED NOT NULL"
                );
                $this->log("OK: {$table}.tenant_id is now NOT NULL");
            } catch (Throwable $e) {
                $this->log("WARN: could not modify {$table} — {$e->getMessage()}");
            }
        }
    }

    /**
     * Add composite index for storefront product catalog queries.
     */
    private function addShopCatalogIndex(): void
    {
        try {
            $this->pdo->exec(
                "ALTER TABLE products ADD INDEX idx_shop_catalog (tenant_id, active, category_id, created_at)"
            );
            $this->log('OK: Added idx_shop_catalog on products(tenant_id, active, category_id, created_at)');
        } catch (Throwable $e) {
            // Index may already exist
            $this->log('NOTE: idx_shop_catalog may already exist — ' . $e->getMessage());
        }
    }

    /**
     * Add FK online_orders.customer_id -> customers.id (ON DELETE SET NULL).
     */
    private function addOnlineOrdersCustomerFk(): void
    {
        try {
            // Ensure customer_id allows NULL so SET NULL works
            $this->pdo->exec(
                "ALTER TABLE online_orders MODIFY COLUMN customer_id INT UNSIGNED DEFAULT NULL"
            );

            $this->pdo->exec(
                "ALTER TABLE online_orders
                 ADD CONSTRAINT fk_online_orders_customer
                 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL"
            );
            $this->log('OK: Added FK fk_online_orders_customer on online_orders(customer_id)');
        } catch (Throwable $e) {
            $this->log('WARN: Could not add online_orders FK — ' . $e->getMessage());
        }
    }

    /**
     * Return all BASE TABLE names that have a nullable tenant_id column.
     */
    private function getScopedTablesWithNullableTenant(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT TABLE_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME = 'tenant_id'
               AND IS_NULLABLE = 'YES'"
        );
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_filter($tables, function ($table) {
            return is_string($table)
                && !in_array($table, $this->globalTables, true)
                && !preg_match('/(_backup|_migration)$/i', $table);
        }));
    }

    /**
     * @return mixed
     */
    private function queryValue(string $sql)
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    private function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}

// ------------------------------------------------------------------
// Run migration if executed directly
// ------------------------------------------------------------------
if (php_sapi_name() === 'cli' || (basename($_SERVER['SCRIPT_NAME'] ?? '') === basename(__FILE__))) {
    try {
        $migration = new TenantNotNullMigration();
        $migration->run();
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
        exit(1);
    }
}
