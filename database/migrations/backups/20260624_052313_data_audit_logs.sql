-- ========================================================
-- Data Migration: audit_logs
-- Database: jdh_pos
-- Row Count: 46
-- Generated: 2026-06-24 05:23:29
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `audit_logs`;

INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES
('1', '1', NULL, 'login_failed', 'user', NULL, NULL, '{\"username\":\"Super Admin\",\"success\":false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'Login failed: Invalid credentials', '2026-05-04 18:09:58'),
('2', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 18:10:28'),
('3', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:29:50'),
('4', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:38:24'),
('5', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:38:47'),
('6', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:39:08'),
('7', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:39:37'),
('8', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:57:14'),
('9', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:57:31'),
('10', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:57:54'),
('11', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 23:58:08'),
('12', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-05 00:00:03'),
('13', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-05 00:34:38'),
('14', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 07:11:16'),
('15', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 08:18:29'),
('16', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 13:35:02'),
('17', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 13:57:51'),
('18', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 14:37:25'),
('19', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 14:37:40'),
('20', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 14:46:35'),
('21', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 21:59:06'),
('22', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 00:34:00'),
('23', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 00:50:24'),
('24', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 01:21:47'),
('25', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 01:23:47'),
('26', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:26:30'),
('27', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:28:11'),
('28', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:30:45'),
('29', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:32:08'),
('30', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:33:08'),
('31', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:34:03'),
('32', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:41:03'),
('33', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:42:00'),
('34', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:42:59'),
('35', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:45:01'),
('36', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-07 01:45:31'),
('37', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 07:58:57'),
('38', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 12:52:57'),
('39', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 13:04:32'),
('40', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 13:25:47'),
('41', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 13:50:33'),
('42', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 13:54:11'),
('43', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 15:31:14'),
('44', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 16:15:12'),
('45', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 16:48:58'),
('46', '1', NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 18:31:21');

SET FOREIGN_KEY_CHECKS=1;
