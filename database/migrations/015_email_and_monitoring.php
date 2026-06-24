<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class EmailAndMonitoringMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->createEmailTemplates();
        $this->createEmailLogs();
        $this->createWebhookFailures();
        $this->createApiRateLimitViolations();
        $this->createSuspiciousActivityLogs();
        $this->createSystemAlerts();
        $this->seedEmailTemplates();
        echo "Migration 015 completed successfully.\n";
    }

    private function createEmailTemplates(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS email_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body_html TEXT NOT NULL,
            body_text TEXT NOT NULL,
            variables JSON NULL,
            category ENUM('trial', 'billing', 'subscription', 'usage', 'security', 'marketing') NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created email_templates table\n";
    }

    private function createEmailLogs(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS email_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            recipient_email VARCHAR(255) NOT NULL,
            template_id BIGINT UNSIGNED NULL,
            subject VARCHAR(255) NOT NULL,
            status ENUM('queued', 'sent', 'delivered', 'bounced', 'failed', 'opened', 'clicked') DEFAULT 'queued',
            provider VARCHAR(50) DEFAULT 'sendgrid',
            provider_message_id VARCHAR(100) NULL,
            opened_at TIMESTAMP NULL,
            clicked_at TIMESTAMP NULL,
            error_message TEXT NULL,
            metadata JSON NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
            FOREIGN KEY (template_id) REFERENCES email_templates(id),
            INDEX idx_status (status, created_at),
            INDEX idx_tenant (tenant_id, created_at),
            INDEX idx_recipient (recipient_email, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created email_logs table\n";
    }

    private function createWebhookFailures(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS webhook_failures (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            webhook_subscription_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            payload JSON NOT NULL,
            response_status INT NULL,
            response_body TEXT NULL,
            failure_reason VARCHAR(255) NULL,
            retry_count INT DEFAULT 0,
            next_retry_at TIMESTAMP NULL,
            succeeded_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_retry (next_retry_at, retry_count),
            INDEX idx_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created webhook_failures table\n";
    }

    private function createApiRateLimitViolations(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS api_rate_limit_violations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            api_key_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            limit_type VARCHAR(50) NOT NULL,
            requests_count INT NOT NULL,
            limit_value INT NOT NULL,
            blocked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address, created_at),
            INDEX idx_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created api_rate_limit_violations table\n";
    }

    private function createSuspiciousActivityLogs(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS suspicious_activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            activity_type ENUM('brute_force', 'impossible_travel', 'unusual_hour', 'data_exfiltration', 'privilege_escalation', 'api_abuse') NOT NULL,
            details JSON NOT NULL,
            risk_score INT DEFAULT 50,
            action_taken ENUM('logged', 'alerted', 'blocked', 'session_terminated', 'notified_admin') DEFAULT 'logged',
            reviewed_by_admin_id BIGINT UNSIGNED NULL,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reviewed_by_admin_id) REFERENCES admins(id),
            INDEX idx_risk (risk_score, created_at),
            INDEX idx_type (activity_type, action_taken)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created suspicious_activity_logs table\n";
    }

    private function createSystemAlerts(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS system_alerts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            severity ENUM('info', 'warning', 'critical') NOT NULL,
            component VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            metric_value VARCHAR(50) NULL,
            threshold_value VARCHAR(50) NULL,
            is_resolved BOOLEAN DEFAULT FALSE,
            resolved_at TIMESTAMP NULL,
            acknowledged_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (acknowledged_by_admin_id) REFERENCES admins(id),
            INDEX idx_severity (severity, is_resolved, created_at),
            INDEX idx_component (component, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created system_alerts table\n";
    }

    private function seedEmailTemplates(): void
    {
        $templates = [
            [
                'slug' => 'trial_welcome',
                'name' => 'Trial Welcome',
                'subject' => 'Welcome to JAKABABA SMART SYSTEM',
                'body_html' => '<h1>Welcome!</h1><p>Your trial has started. Get started here: {{setup_url}}</p>',
                'body_text' => 'Welcome! Your trial has started. Get started here: {{setup_url}}',
                'variables' => json_encode(['setup_url', 'tenant_name', 'trial_days']),
                'category' => 'trial'
            ],
            [
                'slug' => 'payment_failed',
                'name' => 'Payment Failed',
                'subject' => 'Payment failed for {{tenant_name}}',
                'body_html' => '<h1>Payment Failed</h1><p>We could not process your payment. Update your method: {{update_url}}</p>',
                'body_text' => 'Payment failed. Update your payment method: {{update_url}}',
                'variables' => json_encode(['tenant_name', 'amount', 'update_url', 'retry_date']),
                'category' => 'billing'
            ],
            [
                'slug' => 'invoice_receipt',
                'name' => 'Invoice Receipt',
                'subject' => 'Receipt for Invoice {{invoice_number}}',
                'body_html' => '<h1>Thank you!</h1><p>Your payment of {{amount}} was received.</p>',
                'body_text' => 'Thank you! Your payment of {{amount}} was received.',
                'variables' => json_encode(['invoice_number', 'amount', 'date', 'download_url']),
                'category' => 'billing'
            ],
            [
                'slug' => 'usage_80_percent',
                'name' => 'Usage 80% Alert',
                'subject' => 'You have used 80% of your {{feature_name}} limit',
                'body_html' => '<p>You have used 80% of your {{feature_name}} limit. Upgrade: {{upgrade_url}}</p>',
                'body_text' => 'You have used 80% of your {{feature_name}} limit. Upgrade: {{upgrade_url}}',
                'variables' => json_encode(['feature_name', 'usage', 'limit', 'upgrade_url']),
                'category' => 'usage'
            ],
        ];

        $stmt = $this->pdo->prepare("INSERT IGNORE INTO email_templates (slug, name, subject, body_html, body_text, variables, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($templates as $t) {
            $stmt->execute([$t['slug'], $t['name'], $t['subject'], $t['body_html'], $t['body_text'], $t['variables'], $t['category']]);
        }
        echo "Seeded " . count($templates) . " email templates\n";
    }
}

$migration = new EmailAndMonitoringMigration();
$migration->up();
