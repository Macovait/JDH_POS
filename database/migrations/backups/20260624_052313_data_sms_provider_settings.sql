-- ========================================================
-- Data Migration: sms_provider_settings
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:42
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `sms_provider_settings`;

INSERT INTO `sms_provider_settings` (`id`, `tenant_id`, `provider`, `api_key`, `api_secret`, `username`, `sender_id`, `api_url`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'africastalking', NULL, NULL, NULL, 'JAKABABA', NULL, '1', '2026-06-10 09:39:05', '2026-06-10 09:39:05');

SET FOREIGN_KEY_CHECKS=1;
