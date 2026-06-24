<?php
/**
 * Migration 006: Add customer_id to online_orders + FK to customers
 * Run: php database/migrations/006_online_orders_customer_fk.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class OnlineOrdersCustomerFkMigration
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? get_db_connection();
    }

    public function run(): void
    {
        $this->log('=== Starting 006_online_orders_customer_fk ===');

        // 1. Ensure customer_id exists with exact type matching customers.id (int(11))
        if (!$this->columnExists('online_orders', 'customer_id')) {
            $this->pdo->exec(
                "ALTER TABLE online_orders ADD COLUMN customer_id int(11) DEFAULT NULL AFTER customer_email"
            );
            $this->log('OK: Added customer_id to online_orders');
        } else {
            // Fix type mismatch if previously created as UNSIGNED
            $this->pdo->exec(
                "ALTER TABLE online_orders MODIFY COLUMN customer_id int(11) DEFAULT NULL"
            );
            $this->log('OK: Ensured customer_id type matches customers.id');
        }

        // 2. Add FK (allow NULL so existing orders without a linked customer stay valid)
        try {
            $this->pdo->exec(
                "ALTER TABLE online_orders
                 ADD CONSTRAINT fk_online_orders_customer
                 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL"
            );
            $this->log('OK: Added FK fk_online_orders_customer');
        } catch (Throwable $e) {
            $this->log('WARN: Could not add FK — ' . $e->getMessage());
        }

        $this->log('=== Migration completed ===');
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function log(string $message): void
    {
        echo $message . PHP_EOL;
    }
}

if (php_sapi_name() === 'cli' || (basename($_SERVER['SCRIPT_NAME'] ?? '') === basename(__FILE__))) {
    try {
        $migration = new OnlineOrdersCustomerFkMigration();
        $migration->run();
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
        exit(1);
    }
}
