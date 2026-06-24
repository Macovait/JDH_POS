-- Migration: receipt_sms
-- Version: receipt_sms
-- Description: Create receipt_sms table

CREATE TABLE IF NOT EXISTS receipt_sms (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, sale_id INT NOT NULL, phone VARCHAR(20) NOT NULL, message TEXT, status ENUM('success', 'failed', 'pending') DEFAULT 'pending', price DECIMAL(10,4), error_message TEXT, sent_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_sale (sale_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
