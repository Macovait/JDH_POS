-- ========================================================
-- Data Migration: activity_logs
-- Database: jdh_pos
-- Row Count: 45
-- Generated: 2026-06-24 05:23:28
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE `activity_logs`;

INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES
('1', NULL, '1', 'security_patch', 'Complete security hardening applied - Default accounts locked, user created, data anonymized', '127.0.0.1', NULL, '2026-06-11 11:33:44', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('2', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 12:24:06', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('3', '2', '2', 'branch_switch', 'Switched to branch: Kisii', '::1', '{\"branch_id\":2,\"branch_name\":\"Kisii\",\"tenant_id\":1}', '2026-06-11 12:24:17', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('4', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 13:05:10', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('5', '2', '2', 'branch_switch', 'Switched to branch: Kisii', '::1', '{\"branch_id\":2,\"branch_name\":\"Kisii\",\"tenant_id\":1}', '2026-06-11 13:05:14', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('6', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 13:15:28', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('7', '2', '2', 'branch_switch', 'Switched to branch: Kisii', '::1', '{\"branch_id\":2,\"branch_name\":\"Kisii\",\"tenant_id\":1}', '2026-06-11 13:15:35', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('8', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 13:17:15', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('9', '2', '2', 'branch_switch', 'Switched to branch: Kisii', '::1', '{\"branch_id\":2,\"branch_name\":\"Kisii\",\"tenant_id\":1}', '2026-06-11 13:26:54', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('10', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 13:44:50', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('11', '2', '2', 'branch_switch', 'Switched to branch: Kisii', '::1', '{\"branch_id\":2,\"branch_name\":\"Kisii\",\"tenant_id\":1}', '2026-06-11 13:46:31', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('12', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 13:46:44', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('13', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 14:56:23', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('14', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 16:43:49', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('15', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 16:44:17', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('16', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 16:47:54', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('17', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 16:48:30', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('18', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 16:48:56', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('19', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 16:49:15', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('20', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-06-11 16:58:26', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('21', '2', '2', 'branch_switch', 'Switched to branch: Kisii', '::1', '{\"branch_id\":2,\"branch_name\":\"Kisii\",\"tenant_id\":1}', '2026-06-11 16:58:55', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('22', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 16:59:06', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('23', '2', '2', 'branch_switch', 'Switched to branch: Kisii', '::1', '{\"branch_id\":2,\"branch_name\":\"Kisii\",\"tenant_id\":1}', '2026-06-11 17:58:32', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('24', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-06-11 18:32:23', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('25', '1', '1', 'sale.completed', 'Sale #97 completed for 812.00', '::1', '{\"data\":{\"sale_id\":97,\"total\":812,\"items\":1},\"ip\":\"::1\"}', '2026-06-17 04:04:13', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('26', '1', '1', 'held_sale.saved', 'Held sale #8 - saved', '::1', '{\"data\":{\"customer\":\"Walk-in Customer\",\"items\":2},\"ip\":\"::1\"}', '2026-06-17 04:22:52', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('27', '1', '1', 'held_sale.restored', 'Held sale #8 - restored', '::1', '{\"data\":{\"customer\":\"Walk-in Customer\"},\"ip\":\"::1\"}', '2026-06-17 04:23:04', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('28', '1', '1', 'held_sale.saved', 'Held sale #9 - saved', '::1', '{\"data\":{\"customer\":\"Walk-in\",\"items\":2},\"ip\":\"::1\"}', '2026-06-17 04:57:20', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('29', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-17 05:13:20', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('30', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-17 05:13:24', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('31', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-17 05:44:03', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('32', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-17 07:03:03', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('33', '1', '1', 'held_sale.restored', 'Held sale #9 - restored', '::1', '{\"data\":{\"customer\":\"Walk-in\"},\"ip\":\"::1\"}', '2026-06-17 07:03:25', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('34', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,5],\"count\":5,\"ip\":\"::1\"}', '2026-06-19 17:03:10', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('35', '1', '2', 'sale.completed', 'Sale #98 completed for 1,740.00', '::1', '{\"data\":{\"sale_id\":98,\"total\":1740,\"items\":1},\"ip\":\"::1\"}', '2026-06-19 17:03:20', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('36', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-20 16:03:37', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('37', NULL, '2', 'cache.cleared', '{\"tenant_id\":1,\"branch_id\":1,\"cache_type\":\"all\",\"files_cleared\":{\"apcu\":0,\"redis\":0,\"file\":1,\"database\":0,\"total\":1},\"errors\":null}', '::1', '{\"data\":{\"tenant_id\":1,\"branch_id\":1,\"cache_type\":\"all\",\"files_cleared\":{\"apcu\":0,\"redis\":0,\"file\":1,\"database\":0,\"total\":1},\"errors\":null}}', '2026-06-20 16:30:59', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('38', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-22 06:02:59', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('39', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-22 06:03:07', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('40', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-22 06:03:10', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('41', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,1,4,7,6],\"count\":5,\"ip\":\"::1\"}', '2026-06-22 06:03:13', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('42', '1', '2', 'sale.completed', 'Sale #99 completed for 6,612.00', '::1', '{\"data\":{\"sale_id\":99,\"total\":6612,\"items\":4},\"ip\":\"::1\"}', '2026-06-22 06:03:36', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('43', NULL, '2', 'cache.cleared', '{\"tenant_id\":1,\"branch_id\":1,\"cache_type\":\"all\",\"files_cleared\":{\"apcu\":0,\"redis\":0,\"file\":0,\"database\":0,\"total\":0},\"errors\":null}', '::1', '{\"data\":{\"tenant_id\":1,\"branch_id\":1,\"cache_type\":\"all\",\"files_cleared\":{\"apcu\":0,\"redis\":0,\"file\":0,\"database\":0,\"total\":0},\"errors\":null}}', '2026-06-22 06:04:25', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('44', '1', NULL, 'ai.recommendation', 'AI Recommendations generated for customer #0 - Trending now in your store', '::1', '{\"customer_id\":0,\"reason\":\"Trending now in your store\",\"recommendations\":[2,4,1,6,7],\"count\":5,\"ip\":\"::1\"}', '2026-06-23 04:54:58', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL),
('45', '1', '2', 'sale.completed', 'Sale #100 completed for 1,856.00', '::1', '{\"data\":{\"sale_id\":100,\"total\":1856,\"items\":1},\"ip\":\"::1\"}', '2026-06-23 04:55:03', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);

SET FOREIGN_KEY_CHECKS=1;
