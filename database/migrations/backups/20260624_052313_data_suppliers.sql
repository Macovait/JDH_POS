-- ========================================================
-- Data Migration: suppliers
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:42
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `suppliers`;

INSERT INTO `suppliers` (`id`, `name`, `contact`, `phone`, `email`, `active`, `address`, `tax_id`, `payment_terms`, `notes`, `created_by`, `branch_id`, `created_at`, `status`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`, `tenant_id`) VALUES
('3', 'MomEasy', '', '', '', '1', '', '', '', NULL, '2', NULL, '2026-06-09 15:35:37', '1', NULL, NULL, NULL, NULL, '1');

SET FOREIGN_KEY_CHECKS=1;
