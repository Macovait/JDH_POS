-- Migration: receipt_emails
-- Version: receipt_emails
-- Description: Create receipt_emails table

CREATE TABLE IF NOT EXISTS receipt_emails (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, sale_id INT NOT NULL, email VARCHAR(255) NOT NULL, status ENUM('success', 'failed', 'pending') DEFAULT 'pending', error_message TEXT, sent_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_sale (sale_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
