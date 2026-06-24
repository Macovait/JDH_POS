-- ========================================================
-- Data Migration: product_attributes
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:39
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `product_attributes`;

INSERT INTO `product_attributes` (`id`, `product_id`, `attribute_name`, `attribute_value`, `visible`, `position`, `used_for_variations`, `created_at`, `updated_at`, `tenant_id`) VALUES
('3', NULL, 'color', 'Blue', '1', '0', '0', '2026-05-18 21:12:54', '2026-05-18 21:12:54', '1');

SET FOREIGN_KEY_CHECKS=1;
