-- Migration: return_items
-- Version: return_items
-- Description: Create return_items table

CREATE TABLE IF NOT EXISTS return_items (id INT AUTO_INCREMENT PRIMARY KEY, return_id INT NOT NULL, product_id INT NOT NULL, sale_item_id INT, quantity INT NOT NULL, price DECIMAL(10,2) NOT NULL, reason ENUM('damaged', 'defective', 'wrong_item', 'other') DEFAULT 'damaged', INDEX idx_return (return_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
