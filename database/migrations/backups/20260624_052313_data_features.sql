-- ========================================================
-- Data Migration: features
-- Database: jdh_pos
-- Row Count: 10
-- Generated: 2026-06-24 05:23:36
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `features`;

INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'pos', 'Point of Sale', 'Process sales and transactions', 'pos', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('2', '1', 'inventory', 'Inventory Management', 'Track products and stock levels', 'inventory', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('3', '1', 'reports', 'Advanced Reports', 'Detailed analytics and insights', 'reports', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('4', '1', 'multi_branch', 'Multi-Branch Support', 'Manage multiple branches', 'branches', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('5', '1', 'api_access', 'API Access', 'Access to REST API', 'integrations', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('6', '1', 'priority_support', 'Priority Support', 'Priority customer support', 'support', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('7', '1', 'loyalty', 'Loyalty Program', 'Customer rewards and points', 'loyalty', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('8', '1', 'purchases', 'Purchase Orders', 'Manage supplier orders', 'purchases', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('9', '1', 'quotations', 'Quotations', 'Create and manage quotes', 'quotations', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('10', '1', 'shipments', 'Shipments', 'Track deliveries', 'shipments', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32');

SET FOREIGN_KEY_CHECKS=1;
