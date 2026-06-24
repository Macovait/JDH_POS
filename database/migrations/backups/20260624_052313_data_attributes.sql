-- ========================================================
-- Data Migration: attributes
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:29
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `attributes`;

INSERT INTO `attributes` (`id`, `tenant_id`, `name`, `code`, `type`, `group_id`, `unit`, `status`, `is_variant_forming`, `is_filterable`, `is_required`, `sort_order`, `deleted_at`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
('1', '1', 'Color', 'color', 'color', NULL, NULL, '1', '0', '0', '0', '0', NULL, '2', '2', '2026-05-20 00:58:30', '2026-05-20 13:03:35');

SET FOREIGN_KEY_CHECKS=1;
