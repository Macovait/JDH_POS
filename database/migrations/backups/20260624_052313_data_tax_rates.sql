-- ========================================================
-- Data Migration: tax_rates
-- Database: jdh_pos
-- Row Count: 2
-- Generated: 2026-06-24 05:23:43
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `tax_rates`;

INSERT INTO `tax_rates` (`id`, `name`, `rate`, `description`, `active`, `created_at`, `updated_at`, `is_default`, `type`, `tenant_id`) VALUES
('1', 'VAT', '16.00', 'Value Added Tax', '1', '2026-04-30 22:05:32', '2026-06-15 20:13:53', '0', 'inclusive', '1'),
('2', 'Zero Rated', '16.00', 'Zero-rated items', '1', '2026-04-30 22:05:32', '2026-06-05 02:55:23', '0', 'inclusive', '1');

SET FOREIGN_KEY_CHECKS=1;
