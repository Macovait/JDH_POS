-- ========================================================
-- Data Migration: branches
-- Database: jdh_pos
-- Row Count: 2
-- Generated: 2026-06-24 05:23:30
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `branches`;

INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `manager`, `active`, `created_at`, `location`, `tax_rate`, `updated_at`, `opening_time`, `closing_time`, `business_type_id`, `deleted_at`, `tenant_id`, `is_active`) VALUES
('1', 'Kisumu', 'MAIN', '123 Main Street', '+254700000000', '', '', '1', '2026-04-30 22:14:20', NULL, '0.00', NULL, '08:00:00', '20:00:00', '2', NULL, '1', '1'),
('2', 'Kisii', 'KSI', '400200', '0726527245', '', NULL, '1', '2026-06-10 18:53:55', 'Kisii', '16.00', '2026-06-10 19:10:06', '08:00:00', '18:30:00', NULL, NULL, '1', '1');

SET FOREIGN_KEY_CHECKS=1;
