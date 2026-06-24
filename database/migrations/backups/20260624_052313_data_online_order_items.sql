-- ========================================================
-- Data Migration: online_order_items
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `online_order_items`;

INSERT INTO `online_order_items` (`id`, `order_id`, `product_id`, `product_name`, `qty`, `price`, `created_at`) VALUES
('1', '1', '1', 'Sample Product', '2', '1750.00', '2026-06-24 05:58:18');

SET FOREIGN_KEY_CHECKS=1;
