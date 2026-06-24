-- Migration: credit_notes
-- Version: credit_notes
-- Description: Create credit_notes table

CREATE TABLE IF NOT EXISTS credit_notes (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, return_id INT, sale_id INT NOT NULL, amount DECIMAL(10,2) NOT NULL, type VARCHAR(50) NOT NULL, status ENUM('pending', 'completed') DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_tenant (tenant_id), INDEX idx_return (return_id), INDEX idx_sale (sale_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
