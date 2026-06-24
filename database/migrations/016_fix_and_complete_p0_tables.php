<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

/**
 * Fix and complete P0 tables. Drops any partially created tables
 * and recreates them with types matching the existing POS schema.
 * Uses indexes instead of strict FK constraints for safety.
 */
final class FixAndCompleteP0TablesMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->dropIfExists('tenant_entitlements');
        $this->dropIfExists('tenant_add_ons');
        $this->dropIfExists('usage_metering');
        $this->dropIfExists('usage_metering_events');
        $this->dropIfExists('usage_metering_aggregates');
        $this->dropIfExists('credits');
        $this->dropIfExists('prorations');
        $this->dropIfExists('deferred_revenue');
        $this->dropIfExists('invoice_line_items');
        $this->dropIfExists('taxes');
        $this->dropIfExists('refunds');
        $this->dropIfExists('credit_notes');
        $this->dropIfExists('subscription_history');
        $this->dropIfExists('cancellation_reasons');
        $this->dropIfExists('payment_attempts');
        $this->dropIfExists('dunning_campaigns');
        $this->dropIfExists('tenant_metadata');
        $this->dropIfExists('tenant_tags');
        $this->dropIfExists('tenant_notes');
        $this->dropIfExists('support_tickets');
        $this->dropIfExists('tenant_branding');
        $this->dropIfExists('email_templates');
        $this->dropIfExists('email_logs');
        $this->dropIfExists('webhook_failures');
        $this->dropIfExists('api_rate_limit_violations');
        $this->dropIfExists('suspicious_activity_logs');
        $this->dropIfExists('system_alerts');

        // 010: platform_features (renamed from features to avoid conflict with POS features table)
        $this->exec("CREATE TABLE platform_features (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created platform_features\n";

        // plan_features references existing plans.id (INT UNSIGNED) and new platform_features.id (INT UNSIGNED)
        $this->dropIfExists('plan_features');
        $this->exec("CREATE TABLE plan_features (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id INT UNSIGNED NOT NULL,
            feature_id INT UNSIGNED NOT NULL,
            limit_value BIGINT DEFAULT NULL,
            is_unlimited BOOLEAN DEFAULT FALSE,
            is_included BOOLEAN DEFAULT TRUE,
            overage_rate DECIMAL(12, 4) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX idx_plan_feature (plan_id, feature_id),
            INDEX idx_feature (feature_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created plan_features\n";

        $this->exec("CREATE TABLE tenant_entitlements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            feature_id INT UNSIGNED NOT NULL,
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
            UNIQUE INDEX idx_tenant_feature (tenant_id, feature_id, effective_from),
            INDEX idx_effective (effective_from, effective_until),
            INDEX idx_enabled (tenant_id, is_enabled),
            INDEX idx_feature (feature_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created tenant_entitlements\n";

        $this->exec("CREATE TABLE tenant_add_ons (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            feature_id INT UNSIGNED NOT NULL,
            quantity INT DEFAULT 1,
            unit_price DECIMAL(12, 2) NOT NULL,
            billing_cycle ENUM('monthly', 'yearly', 'one_time') DEFAULT 'monthly',
            is_active BOOLEAN DEFAULT TRUE,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ends_at TIMESTAMP NULL,
            cancelled_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_active (tenant_id, is_active),
            INDEX idx_feature (feature_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created tenant_add_ons\n";

        $this->seedPlatformFeatures();

        // 011: usage metering
        $this->exec("CREATE TABLE usage_metering_types (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            unit VARCHAR(30) NOT NULL,
            is_metered BOOLEAN DEFAULT TRUE,
            reset_period ENUM('minute', 'hour', 'day', 'month', 'billing_cycle') DEFAULT 'month',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created usage_metering_types\n";

        $this->exec("CREATE TABLE usage_metering (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            type_id INT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            usage_value DECIMAL(20, 6) NOT NULL DEFAULT 0.000000,
            limit_value DECIMAL(20, 6) DEFAULT NULL,
            is_over_limit BOOLEAN DEFAULT FALSE,
            over_limit_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_period (tenant_id, type_id, period_start),
            INDEX idx_over_limit (is_over_limit, over_limit_at),
            INDEX idx_period (period_start, period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created usage_metering\n";

        $this->exec("CREATE TABLE usage_metering_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            type_id INT UNSIGNED NOT NULL,
            event_value DECIMAL(20, 6) NOT NULL,
            metadata JSON NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_type_time (tenant_id, type_id, recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created usage_metering_events\n";

        $this->exec("CREATE TABLE usage_metering_aggregates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            type_id INT UNSIGNED NOT NULL,
            aggregate_date DATE NOT NULL,
            total_value DECIMAL(20, 6) NOT NULL DEFAULT 0.000000,
            min_value DECIMAL(20, 6) DEFAULT NULL,
            max_value DECIMAL(20, 6) DEFAULT NULL,
            avg_value DECIMAL(20, 6) DEFAULT NULL,
            sample_count BIGINT UNSIGNED DEFAULT 1,
            UNIQUE INDEX idx_tenant_type_date (tenant_id, type_id, aggregate_date),
            INDEX idx_aggregate_date (aggregate_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created usage_metering_aggregates\n";

        $this->seedUsageTypes();

        // 012: billing & revenue
        $this->exec("CREATE TABLE credits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12, 2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            balance DECIMAL(12, 2) NOT NULL,
            type ENUM('top_up', 'refund', 'promo', 'write_off', 'transfer') NOT NULL,
            reference_id VARCHAR(100) NULL,
            description TEXT,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_balance (tenant_id, created_at),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created credits\n";

        $this->exec("CREATE TABLE prorations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id INT UNSIGNED NOT NULL,
            previous_plan_id INT UNSIGNED NULL,
            new_plan_id INT UNSIGNED NULL,
            previous_amount DECIMAL(12, 2) NOT NULL,
            new_amount DECIMAL(12, 2) NOT NULL,
            prorated_amount DECIMAL(12, 2) NOT NULL,
            days_used INT NOT NULL,
            days_remaining INT NOT NULL,
            billing_cycle ENUM('monthly', 'yearly') NOT NULL,
            applied_to_invoice_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_subscription (subscription_id),
            INDEX idx_tenant_date (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created prorations\n";

        $this->exec("CREATE TABLE deferred_revenue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id INT UNSIGNED NOT NULL,
            invoice_id INT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            total_amount DECIMAL(12, 2) NOT NULL,
            recognized_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
            remaining_amount DECIMAL(12, 2) NOT NULL,
            recognition_status ENUM('pending', 'partial', 'recognized', 'refunded') DEFAULT 'pending',
            last_recognized_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_recognition (recognition_status, period_end),
            INDEX idx_subscription (subscription_id, period_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created deferred_revenue\n";

        $this->exec("CREATE TABLE invoice_line_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            type ENUM('subscription', 'usage', 'proration', 'add_on', 'tax', 'discount', 'credit') NOT NULL,
            description VARCHAR(255) NOT NULL,
            quantity DECIMAL(12, 4) DEFAULT 1.0000,
            unit_price DECIMAL(12, 4) NOT NULL,
            total_price DECIMAL(12, 2) NOT NULL,
            tax_rate DECIMAL(5, 2) DEFAULT 0.00,
            tax_amount DECIMAL(12, 2) DEFAULT 0.00,
            period_start DATE NULL,
            period_end DATE NULL,
            metadata JSON NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_invoice (invoice_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created invoice_line_items\n";

        $this->exec("CREATE TABLE taxes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            rate DECIMAL(5, 2) NOT NULL,
            type ENUM('vat', 'gst', 'sales_tax', 'custom') DEFAULT 'vat',
            country_code VARCHAR(5) DEFAULT NULL,
            region VARCHAR(50) DEFAULT NULL,
            is_compound BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_country (country_code, region, is_active),
            INDEX idx_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created taxes\n";

        $this->exec("CREATE TABLE refunds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            invoice_id INT UNSIGNED NOT NULL,
            amount DECIMAL(12, 2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            reason ENUM('requested', 'duplicate', 'fraud', 'error', 'goods_returned') NOT NULL,
            description TEXT,
            status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
            processed_by_admin_id BIGINT UNSIGNED NULL,
            processed_at TIMESTAMP NULL,
            stripe_refund_id VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status, created_at),
            INDEX idx_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created refunds\n";

        $this->exec("CREATE TABLE credit_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            invoice_id INT UNSIGNED NULL,
            number VARCHAR(50) NOT NULL UNIQUE,
            amount DECIMAL(12, 2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            reason TEXT,
            status ENUM('draft', 'issued', 'voided') DEFAULT 'draft',
            applied_to_invoice_id INT UNSIGNED NULL,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_status (tenant_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created credit_notes\n";

        // 013: subscription lifecycle
        $this->exec("CREATE TABLE subscription_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id INT UNSIGNED NOT NULL,
            event ENUM('created', 'activated', 'renewed', 'upgraded', 'downgraded', 'paused', 'resumed', 'cancelled', 'expired', 'payment_failed', 'payment_succeeded') NOT NULL,
            previous_plan_id INT UNSIGNED NULL,
            new_plan_id INT UNSIGNED NULL,
            previous_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NULL,
            amount_changed DECIMAL(12, 2) DEFAULT NULL,
            metadata JSON NULL,
            triggered_by ENUM('system', 'user', 'admin', 'stripe', 'cron') NOT NULL,
            admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_subscription_event (subscription_id, event, created_at),
            INDEX idx_tenant_date (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created subscription_history\n";

        $this->exec("CREATE TABLE cancellation_reasons (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id INT UNSIGNED NOT NULL,
            primary_reason ENUM('too_expensive', 'missing_features', 'too_complex', 'switching_provider', 'business_closed', 'not_using', 'support_issue', 'other') NOT NULL,
            detailed_feedback TEXT,
            attempted_save BOOLEAN DEFAULT FALSE,
            save_offer_accepted BOOLEAN DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reason (primary_reason, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created cancellation_reasons\n";

        $this->exec("CREATE TABLE payment_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id INT UNSIGNED NOT NULL,
            invoice_id INT UNSIGNED NULL,
            attempt_number INT NOT NULL DEFAULT 1,
            amount DECIMAL(12, 2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            status ENUM('pending', 'succeeded', 'failed', 'abandoned') DEFAULT 'pending',
            failure_reason VARCHAR(100) NULL,
            gateway_response JSON NULL,
            stripe_payment_intent_id VARCHAR(100) NULL,
            next_retry_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_subscription_retry (subscription_id, status, next_retry_at),
            INDEX idx_failed (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created payment_attempts\n";

        $this->exec("CREATE TABLE dunning_campaigns (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id INT UNSIGNED NOT NULL,
            stage INT NOT NULL DEFAULT 1,
            email_sent_at TIMESTAMP NULL,
            sms_sent_at TIMESTAMP NULL,
            email_template_used VARCHAR(50) NULL,
            action_taken ENUM('none', 'email_sent', 'sms_sent', 'feature_restricted', 'suspended') DEFAULT 'none',
            resolved_at TIMESTAMP NULL,
            resolution_type ENUM('payment_received', 'cancelled', 'admin_override') NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_stage (stage, created_at),
            INDEX idx_unresolved (resolved_at, stage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created dunning_campaigns\n";

        // 014: tenant management
        $this->exec("CREATE TABLE tenant_metadata (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,
            industry VARCHAR(50) NULL,
            company_size ENUM('solo', 'small', 'medium', 'enterprise') NULL,
            annual_revenue_range VARCHAR(50) NULL,
            primary_use_case ENUM('pos_only', 'ecommerce_only', 'hybrid', 'marketplace') NULL,
            onboarding_completed_at TIMESTAMP NULL,
            onboarding_progress INT DEFAULT 0,
            health_score DECIMAL(3, 2) DEFAULT 1.00,
            health_score_calculated_at TIMESTAMP NULL,
            last_login_at TIMESTAMP NULL,
            last_activity_at TIMESTAMP NULL,
            support_priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
            account_manager_id BIGINT UNSIGNED NULL,
            churn_risk_score DECIMAL(3, 2) DEFAULT NULL,
            expected_ltv DECIMAL(12, 2) DEFAULT NULL,
            cac DECIMAL(12, 2) DEFAULT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_health (health_score, health_score_calculated_at),
            INDEX idx_onboarding (onboarding_progress, onboarding_completed_at),
            INDEX idx_churn (churn_risk_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created tenant_metadata\n";

        $this->exec("CREATE TABLE tenant_tags (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            tag VARCHAR(50) NOT NULL,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE INDEX idx_tenant_tag (tenant_id, tag),
            INDEX idx_tag (tag)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created tenant_tags\n";

        $this->exec("CREATE TABLE tenant_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            type ENUM('general', 'support', 'billing', 'technical', 'sales') DEFAULT 'general',
            is_pinned BOOLEAN DEFAULT FALSE,
            is_internal BOOLEAN DEFAULT TRUE,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_type (tenant_id, type, created_at),
            INDEX idx_pinned (tenant_id, is_pinned)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created tenant_notes\n";

        $this->exec("CREATE TABLE support_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            ticket_number VARCHAR(50) NOT NULL UNIQUE,
            subject VARCHAR(255) NOT NULL,
            description TEXT,
            category ENUM('bug', 'feature_request', 'billing', 'technical', 'account', 'other') DEFAULT 'other',
            priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            status ENUM('open', 'in_progress', 'waiting_on_customer', 'resolved', 'closed') DEFAULT 'open',
            assigned_to_admin_id BIGINT UNSIGNED NULL,
            resolved_at TIMESTAMP NULL,
            resolved_by_admin_id BIGINT UNSIGNED NULL,
            satisfaction_rating INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status, priority, created_at),
            INDEX idx_assigned (assigned_to_admin_id, status),
            INDEX idx_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created support_tickets\n";

        $this->exec("CREATE TABLE tenant_branding (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,
            primary_color VARCHAR(7) DEFAULT '#F59E0B',
            secondary_color VARCHAR(7) DEFAULT '#10B981',
            logo_url VARCHAR(500) NULL,
            favicon_url VARCHAR(500) NULL,
            login_background_url VARCHAR(500) NULL,
            receipt_header TEXT NULL,
            receipt_footer TEXT NULL,
            email_sender_name VARCHAR(100) NULL,
            email_sender_email VARCHAR(255) NULL,
            custom_css TEXT NULL,
            theme_mode ENUM('light', 'dark', 'system') DEFAULT 'system',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created tenant_branding\n";

        // 015: email & monitoring
        $this->exec("CREATE TABLE email_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created email_templates\n";

        $this->exec("CREATE TABLE email_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NULL,
            recipient_email VARCHAR(255) NOT NULL,
            template_id INT UNSIGNED NULL,
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
            INDEX idx_status (status, created_at),
            INDEX idx_tenant (tenant_id, created_at),
            INDEX idx_recipient (recipient_email, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created email_logs\n";

        $this->exec("CREATE TABLE webhook_failures (
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
            INDEX idx_retry (next_retry_at, retry_count),
            INDEX idx_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created webhook_failures\n";

        $this->exec("CREATE TABLE api_rate_limit_violations (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created api_rate_limit_violations\n";

        $this->exec("CREATE TABLE suspicious_activity_logs (
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
            INDEX idx_risk (risk_score, created_at),
            INDEX idx_type (activity_type, action_taken)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created suspicious_activity_logs\n";

        $this->exec("CREATE TABLE system_alerts (
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
            INDEX idx_severity (severity, is_resolved, created_at),
            INDEX idx_component (component, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "Created system_alerts\n";

        $this->seedEmailTemplates();

        echo "\n=== All P0 tables created successfully ===\n";
    }

    private function dropIfExists(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
    }

    private function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    private function seedPlatformFeatures(): void
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

        $stmt = $this->pdo->prepare("INSERT IGNORE INTO platform_features (slug, name, category, default_limit, default_unit, is_beta, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($features as $f) {
            $stmt->execute([$f['slug'], $f['name'], $f['category'], $f['default_limit'], $f['default_unit'], $f['is_beta'] ? 1 : 0, $f['description']]);
        }
        echo "Seeded " . count($features) . " platform features\n";
    }

    private function seedUsageTypes(): void
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

$migration = new FixAndCompleteP0TablesMigration();
$migration->up();
