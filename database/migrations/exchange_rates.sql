-- Migration: exchange_rates
-- Version: exchange_rates
-- Description: Create exchange_rates table

CREATE TABLE IF NOT EXISTS exchange_rates (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, from_currency VARCHAR(3) NOT NULL, to_currency VARCHAR(3) NOT NULL, rate DECIMAL(10,6) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_pair (tenant_id, from_currency, to_currency)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
