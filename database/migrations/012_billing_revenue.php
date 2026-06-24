<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

final class BillingRevenueMigration
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = get_db_connection();
    }

    public function up(): void
    {
        $this->createCredits();
        $this->createProrations();
        $this->createDeferredRevenue();
        $this->createInvoiceLineItems();
        $this->createTaxes();
        $this->createRefunds();
        $this->createCreditNotes();
        echo "Migration 012 completed successfully.\n";
    }

    private function createCredits(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS credits (
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
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by_admin_id) REFERENCES admins(id),
            INDEX idx_tenant_balance (tenant_id, created_at),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created credits table\n";
    }

    private function createProrations(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS prorations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            previous_plan_id BIGINT UNSIGNED NULL,
            new_plan_id BIGINT UNSIGNED NULL,
            previous_amount DECIMAL(12, 2) NOT NULL,
            new_amount DECIMAL(12, 2) NOT NULL,
            prorated_amount DECIMAL(12, 2) NOT NULL,
            days_used INT NOT NULL,
            days_remaining INT NOT NULL,
            billing_cycle ENUM('monthly', 'yearly') NOT NULL,
            applied_to_invoice_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_subscription (subscription_id),
            INDEX idx_tenant_date (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created prorations table\n";
    }

    private function createDeferredRevenue(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS deferred_revenue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            subscription_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            total_amount DECIMAL(12, 2) NOT NULL,
            recognized_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
            remaining_amount DECIMAL(12, 2) NOT NULL,
            recognition_status ENUM('pending', 'partial', 'recognized', 'refunded') DEFAULT 'pending',
            last_recognized_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_recognition (recognition_status, period_end),
            INDEX idx_subscription (subscription_id, period_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created deferred_revenue table\n";
    }

    private function createInvoiceLineItems(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS invoice_line_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id BIGINT UNSIGNED NOT NULL,
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
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            INDEX idx_invoice (invoice_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created invoice_line_items table\n";
    }

    private function createTaxes(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS taxes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            rate DECIMAL(5, 2) NOT NULL,
            type ENUM('vat', 'gst', 'sales_tax', 'custom') DEFAULT 'vat',
            country_code VARCHAR(5) DEFAULT NULL,
            region VARCHAR(50) DEFAULT NULL,
            is_compound BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_country (country_code, region, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created taxes table\n";
    }

    private function createRefunds(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS refunds (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12, 2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            reason ENUM('requested', 'duplicate', 'fraud', 'error', 'goods_returned') NOT NULL,
            description TEXT,
            status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
            processed_by_admin_id BIGINT UNSIGNED NULL,
            processed_at TIMESTAMP NULL,
            stripe_refund_id VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id),
            FOREIGN KEY (processed_by_admin_id) REFERENCES admins(id),
            INDEX idx_status (status, created_at),
            INDEX idx_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created refunds table\n";
    }

    private function createCreditNotes(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS credit_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            invoice_id BIGINT UNSIGNED NULL,
            number VARCHAR(50) NOT NULL UNIQUE,
            amount DECIMAL(12, 2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'KES',
            reason TEXT,
            status ENUM('draft', 'issued', 'voided') DEFAULT 'draft',
            applied_to_invoice_id BIGINT UNSIGNED NULL,
            created_by_admin_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id),
            FOREIGN KEY (created_by_admin_id) REFERENCES admins(id),
            INDEX idx_tenant_status (tenant_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        echo "Created credit_notes table\n";
    }
}

$migration = new BillingRevenueMigration();
$migration->up();
