-- ========================================================
-- Data Migration: categories
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:30
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `categories`;

INSERT INTO `categories` (`id`, `business_type_id`, `code`, `parent_id`, `name`, `description`, `color`, `icon`, `image`, `branch_id`, `created_by`, `updated_at`, `status`, `sort_order`, `meta_title`, `meta_description`, `created_at`, `updated_by`, `deleted_at`, `deleted_by`, `business_type`, `tenant_id`) VALUES
('1', NULL, NULL, NULL, 'Baby Outfits', '', '#fbbf24', 'tag', '/JDH_POS/public/uploads/categories/69f4a6e4c43a2_Heavy Romper.png', NULL, NULL, '2026-05-01 16:13:08', 'active', '5', '', '', '2026-05-01 11:17:45', '1', NULL, NULL, 'supermarket', '1');

SET FOREIGN_KEY_CHECKS=1;
