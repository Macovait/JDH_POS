-- ========================================================
-- Data Migration: plans
-- Database: jdh_pos
-- Row Count: 5
-- Generated: 2026-06-24 05:23:37
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `plans`;

INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price`, `billing_cycle`, `currency`, `max_users`, `max_branches`, `max_products`, `max_storage_mb`, `trial_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', 'Free', 'free', 'Perfect for getting started', '0.00', 'monthly', 'KES', '2', '1', '50', '100', '0', '1', '0', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('2', 'Basic', 'basic', 'Essential features for growing businesses', '999.00', 'monthly', 'KES', '10', '2', '1000', '2000', '14', '1', '0', '2', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('3', 'Professional', 'professional', 'For growing businesses', '2499.00', 'monthly', 'KES', '15', '5', '1000', '2000', '14', '1', '1', '3', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('4', 'Enterprise', 'enterprise', 'For large organizations', '4999.00', 'monthly', 'KES', '999', '999', '99999', '10000', '30', '1', '0', '4', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('5', 'Starter', 'starter', 'For small businesses', '999.00', 'monthly', 'KES', '5', '2', '200', '500', '14', '1', '0', '2', '2026-04-30 22:05:32', '2026-04-30 22:05:32');

SET FOREIGN_KEY_CHECKS=1;
