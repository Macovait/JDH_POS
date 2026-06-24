-- ========================================================
-- Data Migration: attribute_values
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:29
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `attribute_values`;

INSERT INTO `attribute_values` (`id`, `tenant_id`, `attribute_id`, `value`, `label`, `color_hex`, `sort_order`, `created_at`) VALUES
('1', '1', '1', 'Blue', 'blue', '#0134fe', '1', '2026-05-20 19:32:52');

SET FOREIGN_KEY_CHECKS=1;
