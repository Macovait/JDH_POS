-- Migration: credit_payments
-- Version: credit_payments
-- Description: Create credit_payments table

CREATE TABLE IF NOT EXISTS credit_payments (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, credit_sale_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, payment_method VARCHAR(50), reference VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_credit (credit_sale_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
