-- ========================================================
-- Data Migration: pos_tenants
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:39
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `pos_tenants`;

INSERT INTO `pos_tenants` (`id`, `uuid`, `subdomain`, `domain`, `name`, `slug`, `status`, `is_suspended`, `plan_id`, `parent_tenant_id`, `settings`, `branding`, `created_at`, `updated_at`, `stripe_customer_id`) VALUES
('1', 'cf7718fa-44be-11f1-80fc-14abc50b6cdc', 'demo', NULL, 'JAKPOS', 'demo', 'active', '0', '1', NULL, NULL, NULL, '2026-04-30 22:14:20', '2026-05-16 23:36:04', NULL);

SET FOREIGN_KEY_CHECKS=1;
