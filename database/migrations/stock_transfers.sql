-- Migration: stock_transfers
-- Version: stock_transfers
-- Description: Create stock_transfers table

CREATE TABLE IF NOT EXISTS stock_transfers (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, from_branch_id INT NOT NULL, to_branch_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL, notes TEXT, status ENUM('pending', 'approved', 'completed', 'rejected') DEFAULT 'pending', approved_at TIMESTAMP NULL DEFAULT NULL, completed_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_status (status), INDEX idx_product (product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
