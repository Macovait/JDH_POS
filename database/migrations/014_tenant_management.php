<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class TenantManagementMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->createTenantMetadata();
        $this->createTenantTags();
        $this->createTenantNotes();
        $this->createSupportTickets();
        $this->createTenantBranding();
        echo "Migration 014 completed successfully.\n";
    }

    private function createTenantMetadata(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS tenant_metadata (
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
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (account_manager_id) REFERENCES admins(id),
            INDEX idx_health (health_score, health_score_calculated_at),
            INDEX idx_onboarding (onboarding_progress, onboarding_completed_at),
            INDEX idx_churn (churn_risk_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created tenant_metadata table\n";
    }

    private function createTenantTags(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS tenant_tags (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            tag VARCHAR(50) NOT NULL,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_admin_id) REFERENCES admins(id),
            UNIQUE INDEX idx_tenant_tag (tenant_id, tag),
            INDEX idx_tag (tag)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created tenant_tags table\n";
    }

    private function createTenantNotes(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS tenant_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            type ENUM('general', 'support', 'billing', 'technical', 'sales') DEFAULT 'general',
            is_pinned BOOLEAN DEFAULT FALSE,
            is_internal BOOLEAN DEFAULT TRUE,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_admin_id) REFERENCES admins(id),
            INDEX idx_tenant_type (tenant_id, type, created_at),
            INDEX idx_pinned (tenant_id, is_pinned)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created tenant_notes table\n";
    }

    private function createSupportTickets(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS support_tickets (
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
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to_admin_id) REFERENCES admins(id),
            FOREIGN KEY (resolved_by_admin_id) REFERENCES admins(id),
            INDEX idx_status (status, priority, created_at),
            INDEX idx_assigned (assigned_to_admin_id, status),
            INDEX idx_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created support_tickets table\n";
    }

    private function createTenantBranding(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS tenant_branding (
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created tenant_branding table\n";
    }
}

$migration = new TenantManagementMigration();
$migration->up();
