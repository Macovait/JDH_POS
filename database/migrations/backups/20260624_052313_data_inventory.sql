-- ========================================================
-- Data Migration: inventory
-- Database: jdh_pos
-- Row Count: 16
-- Generated: 2026-06-24 05:23:36
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `inventory`;

INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES
('1', '1', '8', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 14:48:29', '2026-05-01 13:38:35', '1'),
('1', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('2', '1', '7', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-17 03:44:35', '2026-05-12 23:14:16', '1'),
('2', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('3', '1', '9', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-23 04:55:03', '2026-05-12 23:16:38', '1'),
('3', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('4', '1', '8', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 14:46:26', '2026-05-12 23:17:47', '1'),
('4', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('5', '1', '7', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 06:03:36', '2026-05-12 23:18:49', '1'),
('5', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('6', '1', '8', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 06:03:36', '2026-05-12 23:21:23', '1'),
('6', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('7', '1', '9', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-16 19:28:58', '2026-05-12 23:21:55', '1'),
('7', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('8', '1', '9', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-06-22 06:03:36', '2026-05-13 11:22:24', '1'),
('8', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1');

SET FOREIGN_KEY_CHECKS=1;
