<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class TenantOnlySchemaMigration
{
    private PDO $pdo;
    private int $defaultTenantId = 0;
    private bool $hasLegacyCompaniesTable = false;

    /**
     * Tables that should remain platform/global rather than tenant-scoped.
     *
     * @var array<string>
     */
    private array $globalTables = [
        'admins',
        'admin_notifications',
        'admin_roles',
        'backup_all_tables_20260426',
        'business_types',
        'category_templates',
        'login_rate_limits',
        'owner_audit_logs',
        'password_resets',
        'password_reset_tokens',
        'plans',
        'plan_features',
        'pos_api_rate_limits',
        'pos_features',
        'pos_plans',
        'pos_plan_features',
        'pos_tenants',
        'pos_webhook_deliveries',
        'rate_limits',
        'schema_migrations',
        'session_security',
        'subscription_plans',
        'support_access_actions',
        'system_analytics',
        'system_health_metrics',
    ];

    /**
     * Internal tenant tables do not need the legacy tenant_id mirror.
     *
     * @var array<string>
     */
    private array $companyCompatibilitySkipTables = [
        'tenant_configs',
        'tenant_sessions',
        'tenant_subscriptions',
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
    }

    public function run(): void
    {
        $this->log('Starting tenant-only schema migration...');

        $this->hasLegacyCompaniesTable = $this->tableType('companies') === 'BASE TABLE';
        $this->defaultTenantId = $this->ensureDefaultTenant();
        $this->normalizeTenantsTable();

        foreach ($this->getScopedTables() as $table) {
            try {
                $this->ensureTenantColumn($table);
                $this->backfillTenantColumn($table);
                $this->finalizeTenantColumn($table);

                if (!in_array($table, $this->companyCompatibilitySkipTables, true)) {
                    $this->ensureCompatibilityCompanyColumn($table);
                }
            } catch (Throwable $e) {
                $this->log("Warning: skipped {$table} ({$e->getMessage()}).");
            }
        }

        $this->replaceCompaniesTableWithCompatibilityView();

        $this->log('Tenant-only schema migration completed successfully.');
    }

    private function ensureDefaultTenant(): int
    {
        if (!$this->tableExists('tenants')) {
            throw new RuntimeException('The tenants table must exist before running this migration.');
        }

        $tenantId = (int) $this->queryValue(
            "SELECT id
             FROM tenants
             WHERE deleted_at IS NULL
             ORDER BY id
             LIMIT 1"
        );

        if ($tenantId > 0) {
            $this->log("Using existing tenant {$tenantId} as the default tenant.");
            return $tenantId;
        }

        $this->pdo->exec("
            INSERT INTO tenants (
                uuid, subdomain, name, business_type, email, phone, address,
                timezone, currency, is_active, is_verified, is_suspended,
                created_at, updated_at, status
            ) VALUES (
                UUID(), 'default', 'Default Tenant', 'retail', '',
                '', '', 'Africa/Nairobi', 'KES', 1, 1, 0, NOW(), NOW(), 'active'
            )
        ");

        $tenantId = (int) $this->pdo->lastInsertId();
        $this->log("Created default tenant {$tenantId}.");
        return $tenantId;
    }

    private function normalizeTenantsTable(): void
    {
        if ($this->columnExists('tenants', 'tenant_id')) {
            $this->pdo->exec("UPDATE tenants SET tenant_id = id WHERE tenant_id IS NULL OR tenant_id = 0");
        }
    }

    /**
     * @return array<string>
     */
    private function getScopedTables(): array
    {
        $sql = "
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ";

        $tables = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_filter($tables, function ($table): bool {
            if (!is_string($table)) {
                return false;
            }

            if ($table === 'tenants' || $table === 'companies') {
                return false;
            }

            if (in_array($table, $this->globalTables, true)) {
                return false;
            }

            return !preg_match('/(_backup|_migration)$/i', $table);
        }));
    }

    private function ensureTenantColumn(string $table): void
    {
        if ($this->columnExists($table, 'tenant_id')) {
            return;
        }

        $this->log("Adding tenant_id to {$table}...");
        $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN tenant_id BIGINT(20) UNSIGNED NULL");
    }

    private function backfillTenantColumn(string $table): void
    {
        if (!$this->columnExists($table, 'tenant_id')) {
            return;
        }

        $remaining = $this->countNullScope($table, 'tenant_id');
        if ($remaining === 0) {
            return;
        }

        if ($this->columnExists($table, 'tenant_id')) {
            if ($this->hasLegacyCompaniesTable) {
                $this->pdo->exec("
                    UPDATE `{$table}` t
                    JOIN companies c ON t.tenant_id = c.id
                    SET t.tenant_id = c.tenant_id
                    WHERE t.tenant_id IS NULL AND t.tenant_id IS NOT NULL
                ");
            } else {
                $this->pdo->exec("
                    UPDATE `{$table}`
                    SET tenant_id = tenant_id
                    WHERE tenant_id IS NULL AND tenant_id IS NOT NULL
                ");
            }
        }

        $this->backfillViaRelation($table, 'branch_id', 'branches');
        $this->backfillViaRelation($table, 'user_id', 'users');
        $this->backfillViaRelation($table, 'sale_id', 'sales');
        $this->backfillViaRelation($table, 'purchase_order_id', 'purchase_orders');
        $this->backfillViaRelation($table, 'product_id', 'products');
        $this->backfillViaRelation($table, 'customer_id', 'customers');
        $this->backfillViaRelation($table, 'supplier_id', 'suppliers');
        $this->backfillViaRelation($table, 'subscription_id', 'tenant_subscriptions');
        $this->backfillViaRelation($table, 'subscription_id', 'subscriptions');
        $this->backfillViaRelation($table, 'webhook_id', 'pos_webhooks');
        $this->backfillViaRelation($table, 'webhook_id', 'webhooks');

        if ($this->countNullScope($table, 'tenant_id') > 0) {
            $this->pdo->exec("
                UPDATE `{$table}`
                SET tenant_id = {$this->defaultTenantId}
                WHERE tenant_id IS NULL
            ");
        }
    }

    private function backfillViaRelation(string $table, string $foreignKey, string $parentTable): void
    {
        if (
            !$this->columnExists($table, $foreignKey)
            || !$this->tableExists($parentTable)
            || !$this->columnExists($parentTable, 'tenant_id')
        ) {
            return;
        }

        $this->pdo->exec("
            UPDATE `{$table}` child
            JOIN `{$parentTable}` parent ON child.`{$foreignKey}` = parent.id
            SET child.tenant_id = parent.tenant_id
            WHERE child.tenant_id IS NULL
              AND parent.tenant_id IS NOT NULL
        ");
    }

    private function finalizeTenantColumn(string $table): void
    {
        if (!$this->columnExists($table, 'tenant_id')) {
            return;
        }

        if ($this->countNullScope($table, 'tenant_id') === 0 && $this->columnIsNullable($table, 'tenant_id')) {
            try {
                $this->pdo->exec("ALTER TABLE `{$table}` MODIFY COLUMN tenant_id BIGINT(20) UNSIGNED NOT NULL");
            } catch (Throwable $e) {
                $this->log("Warning: could not make {$table}.tenant_id NOT NULL ({$e->getMessage()}).");
            }
        }

        $this->ensureIndex($table, $this->safeIdentifier('idx_' . $table . '_tenant_id'), 'tenant_id');
    }

    private function ensureCompatibilityCompanyColumn(string $table): void
    {
        if (!$this->columnExists($table, 'tenant_id')) {
            return;
        }

        if (!$this->columnExists($table, 'tenant_id')) {
            $this->log("Adding tenant_id compatibility mirror to {$table}...");
            $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN tenant_id BIGINT(20) UNSIGNED NULL AFTER tenant_id");
        }

        $this->pdo->exec("
            UPDATE `{$table}`
            SET tenant_id = tenant_id
            WHERE tenant_id IS NULL AND tenant_id IS NOT NULL
        ");

        $this->pdo->exec("
            UPDATE `{$table}`
            SET tenant_id = tenant_id
            WHERE tenant_id IS NULL AND tenant_id IS NOT NULL
        ");

        $this->ensureIndex($table, $this->safeIdentifier('idx_' . $table . '_company_id'), 'tenant_id');
        $this->createCompatibilityTriggers($table);
    }

    private function createCompatibilityTriggers(string $table): void
    {
        $insertTrigger = $this->safeIdentifier('compat_bi_' . $table);
        $updateTrigger = $this->safeIdentifier('compat_bu_' . $table);

        $this->pdo->exec("DROP TRIGGER IF EXISTS `{$insertTrigger}`");
        $this->pdo->exec("DROP TRIGGER IF EXISTS `{$updateTrigger}`");

        $this->pdo->exec("
            CREATE TRIGGER `{$insertTrigger}`
            BEFORE INSERT ON `{$table}`
            FOR EACH ROW
            BEGIN
                IF NEW.tenant_id IS NULL AND NEW.tenant_id IS NOT NULL THEN
                    SET NEW.tenant_id = NEW.tenant_id;
                END IF;
                IF NEW.tenant_id IS NULL AND NEW.tenant_id IS NOT NULL THEN
                    SET NEW.tenant_id = NEW.tenant_id;
                END IF;
            END
        ");

        $this->pdo->exec("
            CREATE TRIGGER `{$updateTrigger}`
            BEFORE UPDATE ON `{$table}`
            FOR EACH ROW
            BEGIN
                IF NEW.tenant_id IS NULL AND NEW.tenant_id IS NOT NULL THEN
                    SET NEW.tenant_id = NEW.tenant_id;
                END IF;
                IF NEW.tenant_id IS NULL AND NEW.tenant_id IS NOT NULL THEN
                    SET NEW.tenant_id = NEW.tenant_id;
                END IF;
            END
        ");
    }

    private function replaceCompaniesTableWithCompatibilityView(): void
    {
        $tableType = $this->tableType('companies');

        if ($tableType === 'BASE TABLE') {
            $backupName = 'companies_backup_' . date('Ymd_His');
            $this->log("Backing up legacy companies table to {$backupName}...");
            $this->pdo->exec("CREATE TABLE `{$backupName}` AS SELECT * FROM companies");
            $this->pdo->exec("DROP TABLE companies");
        } elseif ($tableType === 'VIEW') {
            $this->pdo->exec("DROP VIEW companies");
        }

        $this->log('Creating compatibility companies view backed by tenants...');
        $this->pdo->exec("
            CREATE VIEW companies AS
            SELECT
                t.id AS id,
                t.name AS name,
                t.email AS email,
                t.phone AS phone,
                t.address AS address,
                t.logo_url AS logo,
                CASE
                    WHEN COALESCE(t.is_suspended, 0) = 1 THEN 'suspended'
                    WHEN COALESCE(t.is_active, 0) = 1 AND COALESCE(t.status, 'active') <> 'inactive' THEN 'active'
                    ELSE 'inactive'
                END AS status,
                CASE
                    WHEN COALESCE(t.is_suspended, 0) = 1 THEN 'suspended'
                    WHEN COALESCE(t.is_active, 0) = 1 AND COALESCE(t.status, 'active') <> 'inactive' THEN 'active'
                    ELSE 'inactive'
                END AS subscription_status,
                t.id AS tenant_id,
                t.created_at AS created_at,
                t.updated_at AS updated_at,
                t.currency AS currency,
                t.timezone AS timezone,
                t.tax_rate AS tax_rate,
                t.business_type AS business_type,
                (
                    SELECT bt.id
                    FROM business_types bt
                    WHERE bt.code = t.business_type AND bt.active = 1
                    LIMIT 1
                ) AS business_type_id
            FROM tenants t
            WHERE t.deleted_at IS NULL
        ");
    }

    private function ensureIndex(string $table, string $indexName, string $column): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        try {
            $this->pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
        } catch (Throwable $e) {
            $this->log("Warning: could not add {$indexName} on {$table}.{$column} ({$e->getMessage()}).");
        }
    }

    private function countNullScope(string $table, string $column): int
    {
        return (int) $this->queryValue("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IS NULL");
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnIsNullable(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $column]);
        return strtoupper((string) $stmt->fetchColumn()) === 'YES';
    }

    private function tableExists(string $table): bool
    {
        return $this->tableType($table) !== null;
    }

    private function tableType(string $table): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT TABLE_TYPE
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table]);
        $type = $stmt->fetchColumn();
        return $type !== false ? (string) $type : null;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
        ");
        $stmt->execute([$table, $indexName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return mixed
     */
    private function queryValue(string $sql)
    {
        return $this->pdo->query($sql)->fetchColumn();
    }

    private function safeIdentifier(string $identifier): string
    {
        return substr(preg_replace('/[^A-Za-z0-9_]+/', '_', $identifier) ?? 'idx', 0, 60);
    }

    private function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}

if (PHP_SAPI === 'cli') {
    try {
        (new TenantOnlySchemaMigration())->run();
    } catch (Throwable $e) {
        fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
