-- ========================================================
-- Data Migration: brands
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:30
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `brands`;

INSERT INTO `brands` (`id`, `name`, `description`, `image`, `active`, `created_at`, `updated_at`, `tenant_id`, `deleted_at`) VALUES
('1', 'MomEasy', 'Quality products', NULL, '1', '2026-06-08 23:12:58', '2026-06-08 23:13:27', '1', NULL);

SET FOREIGN_KEY_CHECKS=1;
