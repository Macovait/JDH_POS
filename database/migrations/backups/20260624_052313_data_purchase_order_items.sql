-- ========================================================
-- Data Migration: purchase_order_items
-- Database: jdh_pos
-- Row Count: 2
-- Generated: 2026-06-24 05:23:40
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `purchase_order_items`;

INSERT INTO `purchase_order_items` (`id`, `tenant_id`, `purchase_order_id`, `product_id`, `quantity`, `received_quantity`, `cost_price`) VALUES
('1', '1', '1', '3', '10', '10', '1500.00'),
('2', '1', '2', '2', '10', '10', '1700.00');

SET FOREIGN_KEY_CHECKS=1;
