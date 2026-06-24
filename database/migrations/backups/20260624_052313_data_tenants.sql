-- ========================================================
-- Data Migration: tenants
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:44
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `tenants`;

INSERT INTO `tenants` (`id`, `uuid`, `subdomain`, `domain`, `name`, `business_type`, `email`, `phone`, `address`, `city`, `country`, `timezone`, `currency`, `language`, `logo_url`, `favicon_url`, `theme_config`, `receipt_config`, `tax_number`, `tax_rate`, `is_active`, `is_verified`, `is_suspended`, `suspension_reason`, `verified_at`, `activated_at`, `expires_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`, `status`, `settings`, `features`) VALUES
('1', 'cf7718fa-44be-11f1-80fc-14abc50b6cdc', 'demo', NULL, 'JAKPOS', '', '', '', '', NULL, NULL, 'Africa/Nairobi', 'KES', 'en', 'uploads/logos/company_1/logo_1_1778615488.png', NULL, NULL, NULL, NULL, '16.00', '1', '1', '0', NULL, NULL, NULL, NULL, NULL, '2026-04-30 22:14:20', '2026-06-15 17:03:48', NULL, 'active', NULL, NULL);

SET FOREIGN_KEY_CHECKS=1;
