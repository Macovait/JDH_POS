-- ========================================================
-- Data Migration: tenant_subscriptions
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:44
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `tenant_subscriptions`;

INSERT INTO `tenant_subscriptions` (`id`, `tenant_id`, `plan_id`, `status`, `billing_cycle`, `amount`, `currency`, `trial_ends_at`, `current_period_start`, `current_period_end`, `cancelled_at`, `cancel_reason`, `payment_method`, `payment_reference`, `metadata`, `created_at`, `updated_at`) VALUES
('1', '1', '4', 'active', 'monthly', '4999.00', 'KES', NULL, '2026-04-30 22:14:20', '2026-05-30 22:14:20', NULL, NULL, NULL, NULL, NULL, '2026-04-30 22:14:20', '2026-04-30 22:14:20');

SET FOREIGN_KEY_CHECKS=1;
