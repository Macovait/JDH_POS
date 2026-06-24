-- ========================================================
-- Data Migration: admins
-- Database: jdh_pos
-- Row Count: 3
-- Generated: 2026-06-24 05:23:29
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `admins`;

INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `name`, `role`, `owner_role_id`, `status`, `last_login_at`, `last_login_ip`, `failed_login_attempts`, `locked_until`, `must_change_password`, `created_at`, `updated_at`, `deleted_at`, `recovery_codes`) VALUES
('1', 'superadmin', 'admin@jakababa.com', '$2y$10$ZJ9pGoTn60bf23NvF2bsAOhIZ1.nVCGXwOplcILQb3sEjOtPY3Qqe', 'System Administrator', 'super_admin', NULL, 'active', NULL, NULL, '0', '2026-06-11 11:33:44', '1', '2026-04-30 22:14:20', '2026-06-20 16:25:32', NULL, NULL),
('2', 'admin@platform.com', 'admin@platform.com', '$2y$10$52TK5/vrhEVHQMypQYN3eOnzHYdgbRHD7Da5XFM4XrqT2TTYnHTGS', 'Super Admin', 'super_admin', NULL, 'locked', NULL, NULL, '0', '2026-06-11 11:33:44', '1', '2026-05-17 23:30:03', '2026-06-11 11:33:44', NULL, NULL),
('11', 'admin', 'admin2@jakababa.com', '$2y$10$ZJ9pGoTn60bf23NvF2bsAOhIZ1.nVCGXwOplcILQb3sEjOtPY3Qqe', 'Super Admin', 'super_admin', NULL, 'active', NULL, NULL, '0', NULL, '0', '2026-06-20 16:11:14', '2026-06-20 16:25:32', NULL, NULL);

SET FOREIGN_KEY_CHECKS=1;
