<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

/**
 * Add missing columns to pos_subscriptions for admin subscription management.
 */
final class AddSubscriptionColumnsMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $columns = [
            "billing_cycle"      => "ENUM('monthly','yearly') DEFAULT 'monthly' AFTER `status`",
            "amount"             => "DECIMAL(10,2) DEFAULT 0.00 AFTER `billing_cycle`",
            "currency"           => "VARCHAR(3) DEFAULT 'KES' AFTER `amount`",
            "trial_ends_at"      => "TIMESTAMP NULL DEFAULT NULL AFTER `currency`",
            "payment_method"     => "VARCHAR(50) DEFAULT NULL AFTER `cancelled_at`",
            "payment_reference"  => "VARCHAR(100) DEFAULT NULL AFTER `payment_method`",
            "notes"              => "TEXT DEFAULT NULL AFTER `payment_reference`",
            "metadata"           => "JSON DEFAULT NULL AFTER `notes`",
        ];

        foreach ($columns as $name => $definition) {
            if (!$this->columnExists('pos_subscriptions', $name)) {
                $this->pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN {$name} {$definition}");
                echo "Added column {$name} to pos_subscriptions\n";
            } else {
                echo "Column {$name} already exists\n";
            }
        }

        echo "Migration completed successfully.\n";
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    }
}

// Run migration
$migration = new AddSubscriptionColumnsMigration();
$migration->up();
