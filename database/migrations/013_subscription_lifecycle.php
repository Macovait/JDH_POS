<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class SubscriptionLifecycleMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->createSubscriptionHistory();
        $this->createCancellationReasons();
        $this->createPaymentAttempts();
        $this->createDunningCampaigns();
        echo "Migration 013 completed successfully.\n";
    }

    private function createSubscriptionHistory(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS subscription_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            event ENUM('created', 'activated', 'renewed', 'upgraded', 'downgraded', 'paused', 'resumed', 'cancelled', 'expired', 'payment_failed', 'payment_succeeded') NOT NULL,
            previous_plan_id BIGINT UNSIGNED NULL,
            new_plan_id BIGINT UNSIGNED NULL,
            previous_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NULL,
            amount_changed DECIMAL(12, 2) DEFAULT NULL,
            metadata JSON NULL,
            triggered_by ENUM('system', 'user', 'admin', 'stripe', 'cron') NOT NULL,
            admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES admins(id),
            INDEX idx_subscription_event (subscription_id, event, created_at),
            INDEX idx_tenant_date (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created subscription_history table\n";
    }

    private function createCancellationReasons(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS cancellation_reasons (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            primary_reason ENUM('too_expensive', 'missing_features', 'too_complex', 'switching_provider', 'business_closed', 'not_using', 'support_issue', 'other') NOT NULL,
            detailed_feedback TEXT,
            attempted_save BOOLEAN DEFAULT FALSE,
            save_offer_accepted BOOLEAN DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
            INDEX idx_reason (primary_reason, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created cancellation_reasons table\n";
    }

    private function createPaymentAttempts(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS payment_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NULL,
            attempt_number INT NOT NULL DEFAULT 1,
            amount DECIMAL(12, 2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            status ENUM('pending', 'succeeded', 'failed', 'abandoned') DEFAULT 'pending',
            failure_reason VARCHAR(100) NULL,
            gateway_response JSON NULL,
            stripe_payment_intent_id VARCHAR(100) NULL,
            next_retry_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
            INDEX idx_subscription_retry (subscription_id, status, next_retry_at),
            INDEX idx_failed (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created payment_attempts table\n";
    }

    private function createDunningCampaigns(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS dunning_campaigns (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            stage INT NOT NULL DEFAULT 1,
            email_sent_at TIMESTAMP NULL,
            sms_sent_at TIMESTAMP NULL,
            email_template_used VARCHAR(50) NULL,
            action_taken ENUM('none', 'email_sent', 'sms_sent', 'feature_restricted', 'suspended') DEFAULT 'none',
            resolved_at TIMESTAMP NULL,
            resolution_type ENUM('payment_received', 'cancelled', 'admin_override') NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
            INDEX idx_stage (stage, created_at),
            INDEX idx_unresolved (resolved_at, stage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created dunning_campaigns table\n";
    }
}

$migration = new SubscriptionLifecycleMigration();
$migration->up();
