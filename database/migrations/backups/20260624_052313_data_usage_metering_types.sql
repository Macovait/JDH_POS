-- ========================================================
-- Data Migration: usage_metering_types
-- Database: jdh_pos
-- Row Count: 7
-- Generated: 2026-06-24 05:23:44
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `usage_metering_types`;

INSERT INTO `usage_metering_types` (`id`, `slug`, `name`, `unit`, `is_metered`, `reset_period`, `created_at`, `updated_at`) VALUES
('1', 'api_calls', 'API Calls', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('2', 'pos_transactions', 'POS Transactions', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('3', 'online_orders', 'Online Orders', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('4', 'storage_mb', 'Storage', 'mb', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('5', 'active_users', 'Active Users', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('6', 'branches', 'Branches', 'count', '0', 'billing_cycle', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('7', 'webhooks', 'Webhook Deliveries', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28');

SET FOREIGN_KEY_CHECKS=1;
