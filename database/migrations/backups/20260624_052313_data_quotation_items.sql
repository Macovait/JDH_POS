-- ========================================================
-- Data Migration: quotation_items
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:40
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `quotation_items`;

INSERT INTO `quotation_items` (`id`, `quotation_id`, `product_id`, `product_name`, `quantity`, `price`, `subtotal`) VALUES
('2', '2', '6', 'Flannel Sheets', '1', '1500.00', '1500.00');

SET FOREIGN_KEY_CHECKS=1;
