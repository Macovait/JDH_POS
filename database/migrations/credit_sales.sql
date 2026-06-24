-- Migration: credit_sales
-- Version: credit_sales
-- Description: Create credit_sales table

CREATE TABLE IF NOT EXISTS credit_sales (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, customer_id INT NOT NULL, user_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, due_date DATE NOT NULL, status ENUM('pending', 'approved', 'partial', 'paid', 'cancelled') DEFAULT 'pending', notes TEXT, approved_by INT DEFAULT NULL, approved_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_branch (branch_id), INDEX idx_customer (customer_id), INDEX idx_status (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
