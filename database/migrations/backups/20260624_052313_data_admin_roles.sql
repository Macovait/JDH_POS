-- ========================================================
-- Data Migration: admin_roles
-- Database: jdh_pos
-- Row Count: 3
-- Generated: 2026-06-24 05:23:29
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `admin_roles`;

INSERT INTO `admin_roles` (`id`, `name`, `slug`, `description`, `permissions`, `is_system`, `is_owner_role`, `active`, `created_at`, `updated_at`) VALUES
('1', 'Super Admin', 'super_admin', 'Full system access', '{\"*\": true}', '1', '1', '1', '2026-04-30 22:14:20', '2026-04-30 22:14:20'),
('2', 'Admin', 'admin', 'Administrative access', '{\"tenants\": [\"view\", \"manage\"], \"billing\": [\"view\", \"manage\"]}', '1', '0', '1', '2026-04-30 22:14:20', '2026-04-30 22:14:20'),
('3', 'Support', 'support', 'Support staff access', '{\"tenants\": [\"view\"], \"support\": [\"access\"]}', '1', '0', '1', '2026-04-30 22:14:20', '2026-04-30 22:14:20');

SET FOREIGN_KEY_CHECKS=1;
