<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class UsageMeteringMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->createUsageMeteringTypes();
        $this->createUsageMetering();
        $this->createUsageMeteringEvents();
        $this->createUsageMeteringAggregates();
        $this->seedTypes();
        echo "Migration 011 completed successfully.\n";
    }

    private function createUsageMeteringTypes(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS usage_metering_types (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            unit VARCHAR(30) NOT NULL,
            is_metered BOOLEAN DEFAULT TRUE,
            reset_period ENUM('minute', 'hour', 'day', 'month', 'billing_cycle') DEFAULT 'month',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created usage_metering_types table\n";
    }

    private function createUsageMetering(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS usage_metering (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            type_id BIGINT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            usage_value DECIMAL(20, 6) NOT NULL DEFAULT 0.000000,
            limit_value DECIMAL(20, 6) DEFAULT NULL,
            is_over_limit BOOLEAN DEFAULT FALSE,
            over_limit_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (type_id) REFERENCES usage_metering_types(id),
            INDEX idx_tenant_period (tenant_id, type_id, period_start),
            INDEX idx_over_limit (is_over_limit, over_limit_at),
            INDEX idx_period (period_start, period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created usage_metering table\n";
    }

    private function createUsageMeteringEvents(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS usage_metering_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            type_id BIGINT UNSIGNED NOT NULL,
            event_value DECIMAL(20, 6) NOT NULL,
            metadata JSON NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (type_id) REFERENCES usage_metering_types(id),
            INDEX idx_tenant_type_time (tenant_id, type_id, recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created usage_metering_events table\n";
    }

    private function createUsageMeteringAggregates(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS usage_metering_aggregates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            type_id BIGINT UNSIGNED NOT NULL,
            aggregate_date DATE NOT NULL,
            total_value DECIMAL(20, 6) NOT NULL DEFAULT 0.000000,
            min_value DECIMAL(20, 6) DEFAULT NULL,
            max_value DECIMAL(20, 6) DEFAULT NULL,
            avg_value DECIMAL(20, 6) DEFAULT NULL,
            sample_count BIGINT UNSIGNED DEFAULT 1,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (type_id) REFERENCES usage_metering_types(id),
            UNIQUE INDEX idx_tenant_type_date (tenant_id, type_id, aggregate_date),
            INDEX idx_aggregate_date (aggregate_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created usage_metering_aggregates table\n";
    }

    private function seedTypes(): void
    {
        $types = [
            ['slug' => 'api_calls', 'name' => 'API Calls', 'unit' => 'count', 'is_metered' => true, 'reset_period' => 'month'],
            ['slug' => 'pos_transactions', 'name' => 'POS Transactions', 'unit' => 'count', 'is_metered' => true, 'reset_period' => 'month'],
            ['slug' => 'online_orders', 'name' => 'Online Orders', 'unit' => 'count', 'is_metered' => true, 'reset_period' => 'month'],
            ['slug' => 'storage_mb', 'name' => 'Storage', 'unit' => 'mb', 'is_metered' => true, 'reset_period' => 'month'],
            ['slug' => 'active_users', 'name' => 'Active Users', 'unit' => 'count', 'is_metered' => true, 'reset_period' => 'month'],
            ['slug' => 'branches', 'name' => 'Branches', 'unit' => 'count', 'is_metered' => false, 'reset_period' => 'billing_cycle'],
            ['slug' => 'webhooks', 'name' => 'Webhook Deliveries', 'unit' => 'count', 'is_metered' => true, 'reset_period' => 'month'],
        ];

        $stmt = $this->pdo->prepare("INSERT IGNORE INTO usage_metering_types (slug, name, unit, is_metered, reset_period) VALUES (?, ?, ?, ?, ?)");
        foreach ($types as $t) {
            $stmt->execute([$t['slug'], $t['name'], $t['unit'], $t['is_metered'] ? 1 : 0, $t['reset_period']]);
        }
        echo "Seeded " . count($types) . " usage metering types\n";
    }
}

$migration = new UsageMeteringMigration();
$migration->up();
