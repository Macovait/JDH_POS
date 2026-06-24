-- Migration: inventory
-- Version: inventory
-- Description: Create inventory table

CREATE TABLE IF NOT EXISTS inventory (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, branch_id INT NOT NULL, tenant_id INT NOT NULL, stock INT NOT NULL DEFAULT 0, reserved INT DEFAULT 0, last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_inventory (product_id, branch_id, tenant_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
