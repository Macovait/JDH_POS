-- ========================================================
-- Data Migration: shipping_zones
-- Database: jdh_pos
-- Row Count: 2
-- Generated: 2026-06-24 05:23:42
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `shipping_zones`;

INSERT INTO `shipping_zones` (`id`, `tenant_id`, `name`, `description`, `countries`, `regions`, `cities`, `postal_codes`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', '1', 'Nairobi & Environs', 'Nairobi County and surrounding areas', NULL, NULL, NULL, NULL, '1', '0', '2026-05-17 09:47:32', '2026-05-17 09:47:32'),
('2', '1', 'Rest of Kenya', 'All other counties', NULL, NULL, NULL, NULL, '1', '0', '2026-05-17 09:47:32', '2026-05-17 09:47:32');

SET FOREIGN_KEY_CHECKS=1;
