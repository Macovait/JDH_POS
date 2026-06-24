-- ========================================================
-- Data Migration: email_templates
-- Database: jdh_pos
-- Row Count: 4
-- Generated: 2026-06-24 05:23:35
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `email_templates`;

INSERT INTO `email_templates` (`id`, `slug`, `name`, `subject`, `body_html`, `body_text`, `variables`, `category`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'trial_welcome', 'Trial Welcome', 'Welcome to JAKABABA SMART SYSTEM', '<h1>Welcome!</h1><p>Your trial has started. Get started here: {{setup_url}}</p>', 'Welcome! Your trial has started. Get started here: {{setup_url}}', '[\"setup_url\",\"tenant_name\",\"trial_days\"]', 'trial', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29'),
('2', 'payment_failed', 'Payment Failed', 'Payment failed for {{tenant_name}}', '<h1>Payment Failed</h1><p>We could not process your payment. Update your method: {{update_url}}</p>', 'Payment failed. Update your payment method: {{update_url}}', '[\"tenant_name\",\"amount\",\"update_url\",\"retry_date\"]', 'billing', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29'),
('3', 'invoice_receipt', 'Invoice Receipt', 'Receipt for Invoice {{invoice_number}}', '<h1>Thank you!</h1><p>Your payment of {{amount}} was received.</p>', 'Thank you! Your payment of {{amount}} was received.', '[\"invoice_number\",\"amount\",\"date\",\"download_url\"]', 'billing', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29'),
('4', 'usage_80_percent', 'Usage 80% Alert', 'You have used 80% of your {{feature_name}} limit', '<p>You have used 80% of your {{feature_name}} limit. Upgrade: {{upgrade_url}}</p>', 'You have used 80% of your {{feature_name}} limit. Upgrade: {{upgrade_url}}', '[\"feature_name\",\"usage\",\"limit\",\"upgrade_url\"]', 'usage', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29');

SET FOREIGN_KEY_CHECKS=1;
