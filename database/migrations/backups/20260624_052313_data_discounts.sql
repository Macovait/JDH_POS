-- ========================================================
-- Data Migration: discounts
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:34
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `discounts`;

INSERT INTO `discounts` (`id`, `name`, `type`, `value`, `active`, `description`, `valid_from`, `valid_until`, `min_purchase`, `max_discount`, `usage_limit`, `usage_count`, `priority`, `applicable_products`, `product_ids`, `category_id`, `branch_id`, `created_at`, `tenant_id`, `code`, `max_per_customer`) VALUES
('1', 'May Discount', 'fixed', '4999.99', '1', 'Mother\'s Day Offer', '2026-05-17', '2026-05-31', '2000.00', '5000.00', NULL, '0', '5', 'all', NULL, NULL, NULL, '2026-05-17 18:11:38', '1', NULL, NULL);

SET FOREIGN_KEY_CHECKS=1;
