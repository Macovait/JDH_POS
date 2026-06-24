-- ========================================================
-- Data Migration: platform_features
-- Database: jdh_pos
-- Row Count: 10
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `platform_features`;

INSERT INTO `platform_features` (`id`, `slug`, `name`, `category`, `default_limit`, `default_unit`, `is_beta`, `description`, `created_at`, `updated_at`) VALUES
('1', 'pos', 'Point of Sale', 'core', NULL, NULL, '0', 'Core POS functionality', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('2', 'ecommerce', 'E-commerce', 'core', NULL, NULL, '0', 'Online store', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('3', 'inventory', 'Inventory Management', 'core', NULL, NULL, '0', 'Stock tracking and management', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('4', 'analytics', 'Advanced Analytics', 'add_on', NULL, NULL, '0', 'Dashboards and reports', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('5', 'api_access', 'API Access', 'add_on', '1000', 'calls/day', '0', 'REST API access', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('6', 'multi_branch', 'Multi-Branch', 'core', '1', 'branches', '0', 'Number of branches allowed', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('7', 'users', 'Users', 'core', '5', 'users', '0', 'Number of staff users', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('8', 'products', 'Products', 'core', '100', 'products', '0', 'Number of products allowed', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('9', 'webhooks', 'Webhooks', 'add_on', '5', 'endpoints', '0', 'Outgoing webhook subscriptions', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('10', 'white_label', 'White Label', 'enterprise', NULL, NULL, '0', 'Custom branding and domains', '2026-05-18 08:41:28', '2026-05-18 08:41:28');

SET FOREIGN_KEY_CHECKS=1;
