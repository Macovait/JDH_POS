-- ========================================================
-- Data Migration: product_tags
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:39
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `product_tags`;

INSERT INTO `product_tags` (`id`, `tenant_id`, `name`, `slug`, `color`, `icon`, `description`, `business_type`, `business_type_id`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', '1', 'Best Seller', 'best-seller', '#3B82F6', 'fa-tag', '', 'supermarket', '2', '1', '0', '2', '2026-05-18 17:05:34', '2026-05-18 17:05:34', NULL);

SET FOREIGN_KEY_CHECKS=1;
