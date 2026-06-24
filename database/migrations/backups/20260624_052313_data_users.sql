-- ========================================================
-- Data Migration: users
-- Database: jdh_pos
-- Row Count: 2
-- Generated: 2026-06-24 05:23:45
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `users`;

INSERT INTO `users` (`id`, `name`, `email`, `username`, `role_id`, `password_hash`, `pin`, `pin_hash`, `branch_id`, `status`, `created_at`, `phone`, `address`, `avatar`, `updated_at`, `last_login`, `password_reset_token`, `password_reset_expires`, `login_attempts`, `locked_until`, `two_factor_secret`, `two_factor_enabled`, `last_login_ip`, `last_password_change`, `require_password_change`, `api_token`, `api_token_expires`, `deleted_at`, `failed_login_attempts`, `customer_group`, `tenant_id`, `business_type`, `is_active`) VALUES
('1', 'Wycliffe Bunde', 'user_1@secured.local', 'Wycliffe', '3', '$2y$10$LfOJ1V2izYu15fTaFIRb/u32sWCovIKOhZZHDCET4QEXOr3g7EvC.', NULL, NULL, '1', '1', '2026-04-30 22:14:20', '+254759714022', 'REDACTED', NULL, '2026-06-15 13:25:12', '2026-06-22 22:08:11', NULL, NULL, '0', NULL, NULL, '0', '::1', NULL, '1', NULL, NULL, NULL, '0', 'regular', '1', 'retail', '1'),
('2', 'Bunde', 'user_2@secured.local', 'admin', '5', '$2y$10$vJDToEoziFVTBr5Jt0.B1.NrV4PaxvQJcsxeixyDuy.xmTn0XqS7K', NULL, NULL, '1', '1', '2026-05-02 07:31:47', '+254726527245', 'REDACTED', '/uploads/avatars/avatar_2_1778874962.jpg', '2026-06-20 15:32:57', '2026-06-24 05:31:51', NULL, NULL, '0', NULL, NULL, '0', '::1', NULL, '1', NULL, NULL, NULL, '0', 'regular', '1', 'retail', '1');

SET FOREIGN_KEY_CHECKS=1;
