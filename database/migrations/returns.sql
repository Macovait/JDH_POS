-- Migration: returns
-- Version: returns
-- Description: Create returns table

CREATE TABLE IF NOT EXISTS returns (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, sale_id INT NOT NULL, user_id INT NOT NULL, reason ENUM('damaged', 'defective', 'wrong_item', 'refund', 'other') DEFAULT 'refund', status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending', notes TEXT, approved_by INT DEFAULT NULL, approved_at TIMESTAMP NULL DEFAULT NULL, rejected_by INT DEFAULT NULL, rejected_at TIMESTAMP NULL DEFAULT NULL, rejection_reason TEXT, completed_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_branch (branch_id), INDEX idx_sale (sale_id), INDEX idx_status (status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
