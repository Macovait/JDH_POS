-- ========================================================
-- Data Migration: user_roles
-- Database: jdh_pos
-- Row Count: 1
-- Generated: 2026-06-24 05:23:44
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `user_roles`;

INSERT INTO `user_roles` (`user_id`, `role_id`, `created_at`, `tenant_id`) VALUES
('1', '3', '2026-06-15 22:23:39', '1');

SET FOREIGN_KEY_CHECKS=1;
