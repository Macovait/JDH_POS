-- ========================================================
-- Data Migration: tenant_settings
-- Database: jdh_pos
-- Row Count: 3
-- Generated: 2026-06-24 05:23:43
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `tenant_settings`;

INSERT INTO `tenant_settings` (`id`, `tenant_id`, `setting_key`, `setting_value`, `updated_at`) VALUES
('1', '1', 'auto_print_label_size', 'k22', '2026-06-16 13:56:03'),
('12', '1', 'auto_print_labels_on_sale', '1', '2026-06-09 10:44:35'),
('19', '1', 'auto_print_method', 'escpos', '2026-05-28 19:13:19');

SET FOREIGN_KEY_CHECKS=1;
