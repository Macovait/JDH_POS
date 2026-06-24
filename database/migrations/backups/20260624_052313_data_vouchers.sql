-- ========================================================
-- Data Migration: vouchers
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:45
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `vouchers`;

INSERT INTO `vouchers` (`id`, `code`, `type`, `value`, `expires_at`, `active`, `min_purchase`, `max_discount`, `usage_limit`, `usage_count`, `description`, `branch_id`, `created_at`, `tenant_id`) VALUES
('1', 'BGUC-9ENR', 'fixed', '1000.00', '2026-06-10', '1', '5000.00', NULL, '2', '0', 'For the repeat customers', NULL, '2026-06-08 21:26:25', '1');

SET FOREIGN_KEY_CHECKS=1;
