-- Migration: discounts
-- Version: discounts
-- Description: Create discounts table

CREATE TABLE IF NOT EXISTS discounts (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT DEFAULT NULL, code VARCHAR(50) NOT NULL, type ENUM('percentage', 'fixed') NOT NULL, value DECIMAL(10,2) NOT NULL, min_order DECIMAL(10,2) DEFAULT 0, max_discount DECIMAL(10,2) DEFAULT NULL, starts_at DATETIME, expires_at DATETIME, usage_limit INT DEFAULT NULL, used_count INT DEFAULT 0, active BOOLEAN DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_code (tenant_id, code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
