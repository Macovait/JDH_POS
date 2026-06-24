<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class StripeWebhookTablesMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->createProcessedStripeEvents();
        $this->addStripeColumns();
        echo "Migration 017 completed successfully.\n";
    }

    private function createProcessedStripeEvents(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS processed_stripe_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stripe_event_id VARCHAR(100) NOT NULL UNIQUE,
            type VARCHAR(100) NOT NULL,
            payload JSON NOT NULL,
            processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event (stripe_event_id),
            INDEX idx_type (type, processed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created processed_stripe_events table\n";
    }

    private function addStripeColumns(): void
    {
        $cols = $this->pdo->query("SHOW COLUMNS FROM pos_tenants")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('stripe_customer_id', $cols)) {
            $this->pdo->exec("ALTER TABLE pos_tenants ADD COLUMN stripe_customer_id VARCHAR(100) NULL AFTER updated_at");
            echo "Added stripe_customer_id to pos_tenants\n";
        }

        $cols2 = $this->pdo->query("SHOW COLUMNS FROM pos_subscriptions")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('stripe_subscription_id', $cols2)) {
            $this->pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN stripe_subscription_id VARCHAR(100) NULL AFTER stripe_invoice_id");
            echo "Added stripe_subscription_id to pos_subscriptions\n";
        }
        if (!in_array('stripe_payment_method_id', $cols2)) {
            $this->pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN stripe_payment_method_id VARCHAR(100) NULL AFTER stripe_subscription_id");
            echo "Added stripe_payment_method_id to pos_subscriptions\n";
        }
    }
}

$migration = new StripeWebhookTablesMigration();
$migration->up();
