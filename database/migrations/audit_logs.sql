-- Migration: audit_logs
-- Version: audit_logs
-- Description: Create audit_logs table

CREATE TABLE IF NOT EXISTS audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT DEFAULT NULL, action VARCHAR(100) NOT NULL, description TEXT, meta JSON, branch_id INT DEFAULT NULL, tenant_id INT NOT NULL, ip_address VARCHAR(45), user_agent VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_branch (branch_id), INDEX idx_action (action), INDEX idx_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
