-- JDH POS Database Backup
-- Generated: 2026-05-15 20:11:05
-- Database: jdh_pos

SET FOREIGN_KEY_CHECKS=0;


-- Table structure for `activity_logs`
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `reviewed` tinyint(1) DEFAULT 0,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_created` (`created_at`),
  KEY `idx_activity_user_date` (`user_id`,`created_at`),
  KEY `fk_activity_logs_reviewed_by` (`reviewed_by`),
  KEY `idx_activity_logs_user` (`user_id`),
  KEY `idx_activity_logs_action` (`action`),
  KEY `idx_logs_tenant` (`tenant_id`),
  KEY `idx_logs_branch` (`branch_id`),
  KEY `idx_activity_logs_tenant_action_date` (`tenant_id`,`action`,`created_at`),
  KEY `idx_activity_logs_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_activity_logs_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_activity_logs_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `activity_logs`
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('1', '1', '1', '1', 'product_create', '::1', '{\"product_id\":1,\"product_name\":\"Heavy Rompers\",\"tenant_id\":1,\"description\":\"Created new product: Heavy Rompers\"}', '2026-05-01 13:38:35', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('2', '1', '1', '1', 'category_updated', '::1', '{\"category_id\":1,\"category_name\":\"Baby Outfits\"}', '2026-05-01 15:55:13', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('3', '1', '1', '1', 'category_updated', '::1', '{\"category_id\":1,\"category_name\":\"Baby Outfits\"}', '2026-05-01 16:13:08', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('4', '1', '1', '1', 'product_update', '::1', '{\"product_id\":1,\"product_name\":\"Heavy Rompers\"}', '2026-05-01 17:36:19', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('5', '1', '1', '1', 'product_update', '::1', '{\"product_id\":1,\"product_name\":\"Heavy Rompers\"}', '2026-05-01 17:36:41', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('6', '1', '1', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":1,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-01 17:38:08', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('7', '1', '1', '1', 'product_update', '::1', '{\"product_id\":1,\"product_name\":\"Heavy Rompers\"}', '2026-05-01 17:46:39', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('8', '1', '1', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":2,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-01 22:37:36', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('9', '1', '1', 'sale_completed', 'Sale completed: INV-202605-0001 for 31330.00', '::1', '{\"sale_id\":3,\"invoice_number\":\"INV-202605-0001\",\"total\":3330,\"items\":1,\"payment_method\":\"split\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-01 23:06:40', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('10', '1', '1', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":4,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-01 23:09:49', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('11', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":5,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"mpesa\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-02 17:31:23', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('12', '1', '1', '0', 'branch.updated', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\"}', '2026-05-02 17:55:37', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('13', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":6,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"bank_transfer\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-02 18:37:27', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('14', NULL, '2', 'auth.login_success', 'Successful login for user: admin', 'unknown', NULL, '2026-05-08 09:50:14', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('15', NULL, '2', 'auth.login_success', 'Successful login for user: admin', 'unknown', NULL, '2026-05-08 09:50:32', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('16', NULL, NULL, 'auth.login_failed', 'Failed login for user: admin - Invalid password', 'unknown', NULL, '2026-05-08 09:50:32', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('17', NULL, NULL, 'auth.login_failed', 'Failed login for user: admin - Invalid tenant code', 'unknown', NULL, '2026-05-08 09:50:32', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('18', NULL, '2', 'auth.login_success', 'Successful login for user: admin', 'unknown', NULL, '2026-05-08 09:52:24', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('19', NULL, '2', 'auth.login_success', 'Successful login for user: admin', 'unknown', NULL, '2026-05-08 09:53:14', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('20', NULL, '2', 'auth.login_success', 'Successful login for user: admin', 'unknown', NULL, '2026-05-08 09:53:39', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('21', NULL, '2', 'auth.login_success', 'Successful login for user: admin', 'unknown', NULL, '2026-05-08 09:54:56', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('22', NULL, '2', 'auth.login_success', 'Successful login for user: admin', 'unknown', NULL, '2026-05-08 10:06:14', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('23', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-08 10:23:18', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('24', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-08 10:53:47', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('25', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-09 08:35:10', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('27', '1', '2', 'auth.logout', 'User logged out', '::1', '{\"username\":\"admin\"}', '2026-05-09 18:08:49', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('28', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-09 15:09:24', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('29', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-09 15:56:28', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('30', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-10 11:05:16', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('31', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-10 11:16:34', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('32', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-10 11:25:27', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('33', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-10 11:45:03', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('34', '1', '1', '0', 'sale.returned', '::1', '{\"sale_id\":6,\"return_id\":1,\"return_number\":\"R20260510-1296\",\"amount\":1500}', '2026-05-10 15:29:03', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('35', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-10 20:34:14', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('36', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260511-668 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-11 03:36:51', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('37', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260511-542 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-11 03:38:22', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('38', '1', '1', '0', 'sale.returned', '::1', '{\"sale_id\":4,\"return_id\":5,\"return_number\":\"R20260511-1701\",\"amount\":1500}', '2026-05-11 07:24:36', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('39', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260511-600 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-11 05:06:08', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('40', NULL, '2', 'return_status_update', NULL, NULL, '{\"description\":\"Updated return #R20260511-600 to completed\",\"ip_address\":\"::1\"}', '2026-05-11 06:53:21', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('41', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":7,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 12:32:07', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('42', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":8,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"credit\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 12:41:18', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('43', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 61660.00', '::1', '{\"sale_id\":9,\"invoice_number\":\"INV-202605-0001\",\"total\":6660,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 16:35:50', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('45', '1', '2', 'auth.logout', 'User logged out', '::1', '{\"username\":\"admin\"}', '2026-05-11 16:38:31', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('46', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-11 13:39:03', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('47', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 31330.00', '::1', '{\"sale_id\":10,\"invoice_number\":\"INV-202605-0001\",\"total\":3330,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 17:34:32', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('48', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 11665.00', '::1', '{\"sale_id\":11,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 17:43:17', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('49', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 3,330.00', '::1', '{\"sale_id\":12,\"invoice_number\":\"INV-202605-0001\",\"total\":3330,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 17:52:16', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('50', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 4,995.00', '::1', '{\"sale_id\":13,\"invoice_number\":\"INV-202605-0001\",\"total\":4995,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 17:52:39', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('51', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":14,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 17:54:20', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('52', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":15,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 18:07:08', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('53', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":16,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-11 18:30:57', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('54', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":17,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-12 10:19:39', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('55', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-12 13:22:47', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('56', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":18,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-12 16:47:10', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('57', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-12 13:49:09', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('58', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-12 14:48:59', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('59', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":19,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-12 20:56:09', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('60', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":20,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-12 21:21:22', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('61', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":21,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-12 21:32:32', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('62', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":22,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-12 21:49:48', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('63', '1', '1', '2', 'product_create', '::1', '{\"product_id\":2,\"product_name\":\"Baby Blanket\"}', '2026-05-12 23:14:17', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('64', '1', '1', '2', 'product_create', '::1', '{\"product_id\":3,\"product_name\":\"Baby Shawl\"}', '2026-05-12 23:16:38', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('65', '1', '1', '2', 'product_create', '::1', '{\"product_id\":4,\"product_name\":\"Cussion packed\"}', '2026-05-12 23:17:47', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('66', '1', '1', '2', 'product_create', '::1', '{\"product_id\":5,\"product_name\":\"Mackintosh\"}', '2026-05-12 23:18:49', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('67', '1', '1', '2', 'product_create', '::1', '{\"product_id\":6,\"product_name\":\"Flannel Sheets\"}', '2026-05-12 23:21:23', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('68', '1', '1', '2', 'product_create', '::1', '{\"product_id\":7,\"product_name\":\"Mittens\"}', '2026-05-12 23:21:56', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('70', '1', '2', 'auth.logout', 'User logged out', '::1', '{\"username\":\"admin\"}', '2026-05-12 23:24:43', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('71', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-12 20:26:39', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('72', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-13 06:12:29', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('73', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":23,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-13 11:18:59', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('74', '1', '1', '2', 'product_create', '::1', '{\"product_id\":8,\"product_name\":\"Cement\"}', '2026-05-13 11:22:24', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('75', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":24,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-13 13:07:44', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('76', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":25,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-13 13:27:49', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('77', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":26,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-13 14:03:24', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('78', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":27,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"cash\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-13 14:05:39', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('79', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 3,330.00', '::1', '{\"sale_id\":28,\"invoice_number\":\"INV-202605-0001\",\"total\":3330,\"items\":1,\"payment_method\":\"credit\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-13 15:15:12', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('80', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":29,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"credit\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-13 15:17:18', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('81', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-13 20:01:45', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('82', NULL, '2', 'auth.login_success', 'Successful login for user: admin', '::1', NULL, '2026-05-14 01:59:06', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('83', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260514-024 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-14 08:28:23', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('84', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260514-598 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-14 08:31:26', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('85', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260514-574 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-14 11:29:11', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('86', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260514-637 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-14 11:32:00', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('87', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260514-706 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-14 17:28:55', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('88', NULL, '2', 'return_created', NULL, NULL, '{\"description\":\"Created return #R20260514-554 for KSh 1,500.00\",\"ip_address\":\"::1\"}', '2026-05-14 17:30:56', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('89', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-05-15 11:41:52', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('90', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-05-15 11:42:30', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('91', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":30,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"credit\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-15 12:08:34', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('92', '1', '2', 'sale_completed', 'Sale completed: INV-202605-0001 for 1,665.00', '::1', '{\"sale_id\":31,\"invoice_number\":\"INV-202605-0001\",\"total\":1665,\"items\":1,\"payment_method\":\"split\",\"branch_id\":1,\"tenant_id\":1}', '2026-05-15 13:54:00', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('93', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-05-15 13:54:38', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('94', '1', '2', 'branch_switch', 'Switched to branch: Kisumu', '::1', '{\"branch_id\":1,\"branch_name\":\"Kisumu\",\"tenant_id\":1}', '2026-05-15 14:00:43', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);
INSERT INTO `activity_logs` (`id`, `branch_id`, `user_id`, `action`, `description`, `ip_address`, `meta`, `created_at`, `notes`, `reviewed`, `reviewed_by`, `reviewed_at`, `country`, `city`, `tenant_id`, `metadata`) VALUES ('95', '1', '2', '2', 'backup.created', '::1', '{\"file\":\"backup_2026-05-15_20-10-14.sql\",\"size\":319667}', '2026-05-15 20:10:15', NULL, '0', NULL, NULL, NULL, NULL, '1', NULL);


-- Table structure for `admin_notifications`
DROP TABLE IF EXISTS `admin_notifications`;
CREATE TABLE `admin_notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('info','warning','success','error') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `priority` tinyint(1) NOT NULL DEFAULT 0,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_priority` (`priority`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `admin_roles`
DROP TABLE IF EXISTS `admin_roles`;
CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_system` tinyint(1) DEFAULT 0,
  `is_owner_role` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `admin_roles`
INSERT INTO `admin_roles` (`id`, `name`, `slug`, `description`, `permissions`, `is_system`, `is_owner_role`, `active`, `created_at`, `updated_at`) VALUES ('1', 'Super Admin', 'super_admin', 'Full system access', '{\"*\": true}', '1', '1', '1', '2026-04-30 19:14:20', '2026-04-30 19:14:20');
INSERT INTO `admin_roles` (`id`, `name`, `slug`, `description`, `permissions`, `is_system`, `is_owner_role`, `active`, `created_at`, `updated_at`) VALUES ('2', 'Admin', 'admin', 'Administrative access', '{\"tenants\": [\"view\", \"manage\"], \"billing\": [\"view\", \"manage\"]}', '1', '0', '1', '2026-04-30 19:14:20', '2026-04-30 19:14:20');
INSERT INTO `admin_roles` (`id`, `name`, `slug`, `description`, `permissions`, `is_system`, `is_owner_role`, `active`, `created_at`, `updated_at`) VALUES ('3', 'Support', 'support', 'Support staff access', '{\"tenants\": [\"view\"], \"support\": [\"access\"]}', '1', '0', '1', '2026-04-30 19:14:20', '2026-04-30 19:14:20');


-- Table structure for `admins`
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','support') NOT NULL DEFAULT 'admin',
  `owner_role_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive','locked') NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `failed_login_attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `recovery_codes` longtext DEFAULT NULL CHECK (json_valid(`recovery_codes`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_status` (`status`),
  KEY `idx_admins_role` (`role`),
  KEY `idx_admins_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `admins`
INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `name`, `role`, `owner_role_id`, `status`, `last_login_at`, `last_login_ip`, `failed_login_attempts`, `locked_until`, `must_change_password`, `created_at`, `updated_at`, `deleted_at`, `recovery_codes`) VALUES ('1', 'superadmin', 'admin@jakababa.com', '$2y$10$GsRY8668DM39JDOYxMAeX.ofVZwHMSv0Pc94Ofht1pn.e.cy5FOxC', 'System Administrator', 'super_admin', NULL, 'active', NULL, NULL, '0', NULL, '0', '2026-04-30 19:14:20', '2026-04-30 19:14:20', NULL, NULL);


-- Table structure for `api_keys`
DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `api_key_hash` varchar(255) NOT NULL,
  `api_key_prefix` varchar(8) NOT NULL,
  `scopes` longtext DEFAULT NULL CHECK (json_valid(`scopes`)),
  `rate_limit_override` int(10) unsigned DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_reason` varchar(255) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_api_keys_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_api_keys_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `api_tokens`
DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `scopes` longtext DEFAULT NULL CHECK (json_valid(`scopes`)),
  `active` tinyint(1) DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`),
  KEY `idx_api_tokens_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_api_tokens_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `api_usage_stats`
DROP TABLE IF EXISTS `api_usage_stats`;
CREATE TABLE `api_usage_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `request_count` int(10) unsigned DEFAULT 0,
  `unique_users` int(10) unsigned DEFAULT 0,
  `avg_response_time_ms` int(10) unsigned DEFAULT NULL,
  `error_count` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_api_usage_stats_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_api_usage_stats_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `appointments`
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_name` varchar(150) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_appt_branch` (`branch_id`),
  KEY `idx_appt_date` (`appointment_date`),
  KEY `idx_appt_staff` (`staff_id`),
  KEY `idx_appt_customer` (`customer_id`),
  KEY `idx_appointments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_appointments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `old_values` longtext DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_action` (`tenant_id`,`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_audit_logs_tenant_user_date` (`tenant_id`,`user_id`,`created_at`),
  KEY `idx_audit_logs_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `audit_logs`
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('1', NULL, NULL, 'login_failed', 'user', NULL, NULL, '{\"username\":\"Super Admin\",\"success\":false}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'Login failed: Invalid credentials', '2026-05-04 15:09:58');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('2', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 15:10:28');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('3', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:29:50');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('4', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:38:24');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('5', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:38:47');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('6', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:39:08');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('7', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:39:37');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('8', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:57:14');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('9', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:57:31');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('10', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:57:54');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('11', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 20:58:08');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('12', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 21:00:03');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('13', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-04 21:34:38');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('14', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 04:11:16');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('15', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 05:18:29');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('16', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 10:35:02');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('17', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 10:57:51');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('18', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 11:37:25');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('19', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 11:37:40');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('20', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 11:46:35');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('21', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 18:59:06');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('22', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 21:34:00');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('23', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 21:50:24');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('24', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-06 22:21:47');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('25', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-06 22:23:47');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('26', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:26:30');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('27', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:28:11');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('28', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:30:45');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('29', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:32:08');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('30', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:33:08');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('31', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:34:03');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('32', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:41:03');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('33', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:42:00');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('34', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:42:59');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('35', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:45:01');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('36', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'curl/8.13.0', NULL, 'User logged in', '2026-05-06 22:45:31');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('37', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 04:58:57');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('38', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 09:52:57');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('39', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 10:04:32');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('40', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 10:25:47');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('41', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 10:50:33');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('42', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 'User logged in', '2026-05-07 10:54:11');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('43', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 12:31:14');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('44', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 13:15:12');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('45', '1', '2', 'login', 'user', '2', NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 13:48:58');
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `session_id`, `description`, `created_at`) VALUES ('46', NULL, NULL, 'login', 'user', NULL, NULL, '{\"username\":\"admin\",\"success\":true}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 'User logged in', '2026-05-07 15:31:21');


-- Table structure for `backups`
DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `size` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `backups`
INSERT INTO `backups` (`id`, `filename`, `size`, `status`, `created_at`) VALUES ('1', 'jdh_pos_2026-05-04_08-23-25.sql.gz', '24155', 'success', '2026-05-04 06:23:26');


-- Table structure for `branches`
DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `code` varchar(50) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `location` varchar(255) DEFAULT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime DEFAULT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `business_type_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_branches_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uq_branches_tenant_name` (`tenant_id`,`name`),
  KEY `idx_company_branch` (`active`),
  KEY `idx_branches_deleted_at` (`deleted_at`),
  KEY `idx_branches_tenant` (`tenant_id`),
  KEY `idx_branches_tenant_active` (`tenant_id`,`is_active`,`id`),
  KEY `idx_branches_tenant_deleted` (`tenant_id`,`deleted_at`),
  KEY `idx_branches_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_branches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `branches`
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `manager`, `active`, `created_at`, `location`, `tax_rate`, `updated_at`, `opening_time`, `closing_time`, `business_type_id`, `deleted_at`, `tenant_id`, `is_active`) VALUES ('1', 'Kisumu', 'MAIN', '123 Main Street', '+254700000000', '', '', '1', '2026-04-30 22:14:20', NULL, '0.00', NULL, '08:00:00', '20:00:00', '0', NULL, '1', '1');


-- Table structure for `brands`
DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_brands_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_brands_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `business_types`
DROP TABLE IF EXISTS `business_types`;
CREATE TABLE `business_types` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `default_receipt_template` varchar(50) NOT NULL DEFAULT 'default',
  `order_types` longtext DEFAULT NULL CHECK (json_valid(`order_types`)),
  `product_fields` longtext DEFAULT NULL CHECK (json_valid(`product_fields`)),
  `features` longtext DEFAULT NULL CHECK (json_valid(`features`)),
  `active` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_business_types_code` (`code`),
  KEY `idx_business_types_active_sort` (`is_active`,`sort_order`,`id`),
  CONSTRAINT `chk_business_types_is_active` CHECK (`is_active` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `business_types`
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('1', 'retail', 'Retail / General Store', 'General retail and point of sale', 'fa-store', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('2', 'supermarket', 'Supermarket / Grocery', 'Supermarket and grocery stores', 'fa-shopping-cart', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('3', 'restaurant', 'Restaurant / Cafe', 'Restaurants, cafes and food service', 'fa-utensils', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('4', 'pharmacy', 'Pharmacy / Drugstore', 'Pharmacies and drugstores', 'fa-pills', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('5', 'fashion', 'Fashion / Clothing', 'Fashion and clothing stores', 'fa-tshirt', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('6', 'electronics', 'Electronics', 'Electronics and tech stores', 'fa-laptop', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('7', 'hardware', 'Hardware / Building', 'Hardware and building materials', 'fa-wrench', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('8', 'salon', 'Salon / Beauty', 'Beauty salons and spa', 'fa-cut', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('9', 'wholesale', 'Wholesale', 'Wholesale and distribution', 'fa-boxes', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('10', 'other', 'Other', 'Other business types', 'fa-ellipsis-h', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 19:05:32', '2026-04-30 19:05:32');


-- Table structure for `categories`
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_type_id` int(11) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#3B82F6',
  `icon` varchar(50) DEFAULT 'tag',
  `image` varchar(255) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `business_type` varchar(50) DEFAULT 'supermarket',
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `fk_categories_updated_by` (`updated_by`),
  KEY `fk_categories_deleted_by` (`deleted_by`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_branch` (`branch_id`),
  KEY `idx_categories_created_by` (`created_by`),
  KEY `idx_categories_bt` (`business_type`),
  KEY `idx_categories_status` (`status`),
  KEY `idx_categories_business_type` (`business_type`),
  KEY `idx_categories_branch_id` (`branch_id`),
  KEY `idx_categories_deleted_at` (`deleted_at`),
  KEY `idx_categories_sort_order` (`sort_order`),
  KEY `idx_categories_tenant` (`tenant_id`),
  KEY `idx_categories_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `categories`
INSERT INTO `categories` (`id`, `business_type_id`, `code`, `parent_id`, `name`, `description`, `color`, `icon`, `image`, `branch_id`, `created_by`, `updated_at`, `status`, `sort_order`, `meta_title`, `meta_description`, `created_at`, `updated_by`, `deleted_at`, `deleted_by`, `business_type`, `tenant_id`) VALUES ('1', NULL, NULL, NULL, 'Baby Outfits', '', '#fbbf24', 'tag', '/JDH_POS/public/uploads/categories/69f4a6e4c43a2_Heavy Romper.png', NULL, NULL, '2026-05-01 13:13:08', 'active', '5', '', '', '2026-05-01 11:17:45', '1', NULL, NULL, 'supermarket', '1');


-- Table structure for `category_templates`
DROP TABLE IF EXISTS `category_templates`;
CREATE TABLE `category_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `business_type_id` smallint(5) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_templates_business_slug` (`business_type_id`,`slug`),
  KEY `fk_category_templates_parent` (`parent_id`),
  KEY `idx_category_templates_business_active` (`business_type_id`,`is_active`,`sort_order`),
  KEY `idx_category_templates_business_type` (`business_type_id`),
  KEY `idx_category_templates_active` (`is_active`),
  KEY `idx_category_templates_sort_order` (`sort_order`),
  CONSTRAINT `fk_category_templates_business_type` FOREIGN KEY (`business_type_id`) REFERENCES `business_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_category_templates_parent` FOREIGN KEY (`parent_id`) REFERENCES `category_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `companies`
DROP TABLE IF EXISTS `companies`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `companies` AS select `t`.`id` AS `id`,`t`.`name` AS `name`,`t`.`email` AS `email`,`t`.`phone` AS `phone`,`t`.`address` AS `address`,`t`.`logo_url` AS `logo`,case when coalesce(`t`.`is_suspended`,0) = 1 then 'suspended' when coalesce(`t`.`is_active`,0) = 1 and coalesce(`t`.`status`,'active') <> 'inactive' then 'active' else 'inactive' end AS `status`,case when coalesce(`t`.`is_suspended`,0) = 1 then 'suspended' when coalesce(`t`.`is_active`,0) = 1 and coalesce(`t`.`status`,'active') <> 'inactive' then 'active' else 'inactive' end AS `subscription_status`,`t`.`id` AS `tenant_id`,`t`.`created_at` AS `created_at`,`t`.`updated_at` AS `updated_at`,`t`.`deleted_at` AS `deleted_at`,`t`.`currency` AS `currency`,`t`.`timezone` AS `timezone`,`t`.`tax_rate` AS `tax_rate`,`t`.`business_type` AS `business_type`,(select `bt`.`id` from `business_types` `bt` where `bt`.`code` = `t`.`business_type` and `bt`.`active` = 1 limit 1) AS `business_type_id` from `tenants` `t` where `t`.`deleted_at` is null;

-- Dumping data for `companies`
INSERT INTO `companies` (`id`, `name`, `email`, `phone`, `address`, `logo`, `status`, `subscription_status`, `tenant_id`, `created_at`, `updated_at`, `deleted_at`, `currency`, `timezone`, `tax_rate`, `business_type`, `business_type_id`) VALUES ('1', 'JAKPOS', '', '', '', 'uploads/logos/company_1/logo_1_1778615488.png', 'active', 'active', '1', '2026-04-30 19:14:20', '2026-05-13 18:09:29', NULL, 'KES', 'Africa/Nairobi', '11.00', '', NULL);


-- Table structure for `company_subscriptions`
DROP TABLE IF EXISTS `company_subscriptions`;
CREATE TABLE `company_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','trialing','past_due','cancelled','expired') DEFAULT 'active',
  `billing_cycle` enum('monthly','yearly') DEFAULT 'monthly',
  `amount` decimal(12,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'KES',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `current_period_start` timestamp NULL DEFAULT NULL,
  `current_period_end` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_subscriptions_status` (`status`),
  KEY `idx_company_subscriptions_tenant_id` (`tenant_id`),
  KEY `idx_csub_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_company_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `company_usage`
DROP TABLE IF EXISTS `company_usage`;
CREATE TABLE `company_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `feature_name` varchar(100) NOT NULL,
  `usage_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_date` (`tenant_id`,`date`),
  CONSTRAINT `company_usage_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `contacts`
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `contact` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_contacts_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_contacts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `context_rules`
DROP TABLE IF EXISTS `context_rules`;
CREATE TABLE `context_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL,
  `condition_type` enum('time','stock','behavior','inventory','sales_velocity') NOT NULL,
  `condition_params` longtext NOT NULL CHECK (json_valid(`condition_params`)),
  `action_type` enum('show_panel','suggest_product','apply_discount','change_layout','alert') NOT NULL,
  `action_params` longtext NOT NULL CHECK (json_valid(`action_params`)),
  `priority` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_active` (`is_active`),
  KEY `idx_priority` (`priority`),
  KEY `idx_context_rules_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_context_rules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `crm_deals`
DROP TABLE IF EXISTS `crm_deals`;
CREATE TABLE `crm_deals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `value` decimal(15,2) DEFAULT 0.00,
  `status` enum('open','negotiating','won','lost') DEFAULT 'open',
  `expected_close_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_crm_deals_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_crm_deals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `crm_leads`
DROP TABLE IF EXISTS `crm_leads`;
CREATE TABLE `crm_leads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `source` varchar(50) DEFAULT 'website',
  `status` enum('new','contacted','qualified','converted','lost') DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `tenant_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_crm_leads_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_crm_leads_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `customer_behavior`
DROP TABLE IF EXISTS `customer_behavior`;
CREATE TABLE `customer_behavior` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) NOT NULL,
  `behavior_type` enum('view','search','add_to_cart','remove_from_cart','abandon','purchase') NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `search_term` varchar(255) DEFAULT NULL,
  `time_spent_seconds` int(11) DEFAULT 0,
  `referring_page` varchar(255) DEFAULT NULL,
  `device_type` enum('desktop','tablet','mobile') DEFAULT 'desktop',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_customer` (`customer_id`),
  KEY `idx_behavior_type` (`behavior_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_customer_behavior_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_behavior_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `customer_credit_transactions`
DROP TABLE IF EXISTS `customer_credit_transactions`;
CREATE TABLE `customer_credit_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `transaction_type` enum('sale','payment','refund','adjustment','credit_limit_change') NOT NULL,
  `reference_type` enum('sale','payment','manual') NOT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_before` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_customer` (`customer_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  KEY `idx_customer_credit_transactions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_credit_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `customer_credits`
DROP TABLE IF EXISTS `customer_credits`;
CREATE TABLE `customer_credits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `type` enum('credit','payment') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cc_customer` (`customer_id`),
  KEY `idx_customer_credits_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_credits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `customer_groups`
DROP TABLE IF EXISTS `customer_groups`;
CREATE TABLE `customer_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `min_spent` decimal(12,2) DEFAULT 0.00,
  `discount_rate` decimal(5,2) DEFAULT 0.00,
  `color` varchar(20) DEFAULT '#6B7280',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_deleted` (`deleted_at`),
  KEY `idx_customer_groups_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_groups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `customer_payment_schedules`
DROP TABLE IF EXISTS `customer_payment_schedules`;
CREATE TABLE `customer_payment_schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned NOT NULL,
  `due_date` date NOT NULL,
  `amount_due` decimal(12,2) NOT NULL,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','partial','paid','overdue','cancelled') DEFAULT 'pending',
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_customer` (`customer_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`),
  KEY `idx_overdue` (`status`,`due_date`),
  KEY `idx_customer_payment_schedules_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_payment_schedules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `customer_segments`
DROP TABLE IF EXISTS `customer_segments`;
CREATE TABLE `customer_segments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `segment_name` varchar(100) NOT NULL,
  `segment_type` enum('vip','frequent','at_risk','new','high_value','low_value') NOT NULL,
  `criteria` longtext NOT NULL CHECK (json_valid(`criteria`)),
  `total_customers` int(11) DEFAULT 0,
  `avg_order_value` decimal(12,2) DEFAULT 0.00,
  `lifetime_value` decimal(12,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`segment_type`),
  KEY `idx_customer_segments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_segments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `customers`
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `loyalty_points` int(11) DEFAULT 0,
  `active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `loyalty_tier` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `group_id` int(11) DEFAULT NULL,
  `total_spent` decimal(15,2) DEFAULT 0.00,
  `last_purchase` datetime DEFAULT NULL,
  `points_expired` int(10) unsigned DEFAULT 0,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `current_balance` decimal(12,2) DEFAULT 0.00,
  `total_credit_used` decimal(12,2) DEFAULT 0.00,
  `total_payments` decimal(12,2) DEFAULT 0.00,
  `last_payment_date` date DEFAULT NULL,
  `payment_terms_days` int(11) DEFAULT 30,
  `credit_status` enum('active','hold','suspended','blacklisted') DEFAULT 'active',
  `credit_notes` text DEFAULT NULL,
  `requires_approval` tinyint(1) DEFAULT 0,
  `tenant_name` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customers_phone` (`phone`),
  KEY `idx_customers_email` (`email`),
  KEY `deleted_by` (`deleted_by`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_customers_city` (`city`),
  KEY `group_id` (`group_id`),
  KEY `idx_company_customer` (`active`),
  KEY `idx_customers_company_active` (`active`),
  KEY `idx_customers_loyalty_tier` (`loyalty_tier`),
  KEY `idx_customers_loyalty_points` (`loyalty_points`),
  KEY `idx_customers_total_spent` (`total_spent`),
  KEY `idx_customers_tenant` (`tenant_id`),
  KEY `idx_customers_tenant_name` (`tenant_id`,`name`),
  KEY `idx_customers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `discounts`
DROP TABLE IF EXISTS `discounts`;
CREATE TABLE `discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `type` enum('fixed','percent') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `active` tinyint(4) DEFAULT 1,
  `description` text DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `min_purchase` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `priority` int(11) DEFAULT 5,
  `applicable_products` varchar(20) DEFAULT 'all',
  `product_ids` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_discounts_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_discounts_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_discounts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `dynamic_pricing`
DROP TABLE IF EXISTS `dynamic_pricing`;
CREATE TABLE `dynamic_pricing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `condition` enum('low_stock','overstock','happy_hour','slow_hours','customer_loyalty') NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `min_stock_percentage` int(11) DEFAULT NULL,
  `max_stock_percentage` int(11) DEFAULT NULL,
  `priority` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_active` (`is_active`),
  KEY `idx_product` (`product_id`),
  KEY `idx_dynamic_pricing_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_dynamic_pricing_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `expense_categories`
DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_expense_categories_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_expense_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `expenses`
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_date` (`expense_date`),
  KEY `idx_expenses_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_expenses_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `feature_usage`
DROP TABLE IF EXISTS `feature_usage`;
CREATE TABLE `feature_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feature_name` varchar(100) NOT NULL,
  `usage_count` int(11) DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_feature_usage_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_feature_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `features`
DROP TABLE IF EXISTS `features`;
CREATE TABLE `features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `feature_key` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module_name` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_features_key` (`feature_key`),
  KEY `idx_features_module` (`module_name`),
  KEY `idx_features_active` (`is_active`),
  KEY `idx_features_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_features_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `features`
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '1', 'pos', 'Point of Sale', 'Process sales and transactions', 'pos', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('2', '1', 'inventory', 'Inventory Management', 'Track products and stock levels', 'inventory', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('3', '1', 'reports', 'Advanced Reports', 'Detailed analytics and insights', 'reports', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('4', '1', 'multi_branch', 'Multi-Branch Support', 'Manage multiple branches', 'branches', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('5', '1', 'api_access', 'API Access', 'Access to REST API', 'integrations', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('6', '1', 'priority_support', 'Priority Support', 'Priority customer support', 'support', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('7', '1', 'loyalty', 'Loyalty Program', 'Customer rewards and points', 'loyalty', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('8', '1', 'purchases', 'Purchase Orders', 'Manage supplier orders', 'purchases', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('9', '1', 'quotations', 'Quotations', 'Create and manage quotes', 'quotations', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES ('10', '1', 'shipments', 'Shipments', 'Track deliveries', 'shipments', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');


-- Table structure for `features_catalog`
DROP TABLE IF EXISTS `features_catalog`;
CREATE TABLE `features_catalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `feature_key` varchar(100) DEFAULT NULL,
  `name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_key` (`feature_key`),
  KEY `idx_features_catalog_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_features_catalog_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `held_sales`
DROP TABLE IF EXISTS `held_sales`;
CREATE TABLE `held_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `sale_data` text NOT NULL,
  `table_number` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `item_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_held_sales_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_held_sales_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `hr_employees`
DROP TABLE IF EXISTS `hr_employees`;
CREATE TABLE `hr_employees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(10) unsigned DEFAULT 1,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT 0.00,
  `hire_date` date DEFAULT NULL,
  `status` enum('active','on_leave','terminated') DEFAULT 'active',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_department` (`department`),
  KEY `idx_hr_employees_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_hr_employees_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `hr_leave_requests`
DROP TABLE IF EXISTS `hr_leave_requests`;
CREATE TABLE `hr_leave_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`),
  KEY `idx_hr_leave_requests_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_hr_leave_requests_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `inventory`
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `minimum_stock` int(11) DEFAULT 0,
  `maximum_stock` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`product_id`,`branch_id`),
  KEY `idx_inventory_stock` (`stock`),
  KEY `idx_inventory_stock_level` (`stock`,`reorder_level`),
  KEY `idx_inventory_branch_product` (`branch_id`,`product_id`),
  KEY `idx_inventory_company_product_branch` (`product_id`,`branch_id`),
  KEY `idx_inventory_low_stock` (`stock`,`reorder_level`),
  KEY `idx_inventory_branch` (`branch_id`),
  KEY `idx_inventory_product_id` (`product_id`),
  KEY `idx_inventory_branch_id` (`branch_id`),
  KEY `idx_inventory_product_branch` (`product_id`,`branch_id`),
  KEY `idx_inventory_branch_company` (`branch_id`),
  KEY `idx_inventory_tenant` (`tenant_id`),
  KEY `idx_inventory_expiry` (`expiry_date`),
  KEY `idx_inventory_company_branch` (`branch_id`),
  KEY `idx_inventory_product_company` (`product_id`),
  KEY `idx_inventory_tenant_branch_product` (`tenant_id`,`branch_id`,`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_inventory_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_inventory_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `inventory`
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('1', '1', '0', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-13 08:18:59', '2026-05-01 10:38:35', '1');
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('2', '1', '6', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-15 10:54:00', '2026-05-12 20:14:16', '1');
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('3', '1', '0', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-12 20:16:38', '2026-05-12 20:16:38', '1');
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('4', '1', '0', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-12 20:17:47', '2026-05-12 20:17:47', '1');
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('5', '1', '0', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-12 20:18:49', '2026-05-12 20:18:49', '1');
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('6', '1', '0', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-12 20:21:23', '2026-05-12 20:21:23', '1');
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('7', '1', '0', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-12 20:21:55', '2026-05-12 20:21:55', '1');
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES ('8', '1', '10', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-05-13 08:22:57', '2026-05-13 08:22:24', '1');


-- Table structure for `inventory_logs`
DROP TABLE IF EXISTS `inventory_logs`;
CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `old_stock` int(11) DEFAULT 0,
  `new_stock` int(11) DEFAULT 0,
  `change_amount` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `branch_id` (`branch_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_inventory_logs_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `inventory_logs`
INSERT INTO `inventory_logs` (`id`, `product_id`, `branch_id`, `old_stock`, `new_stock`, `change_amount`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('1', '1', '1', '0', '15', '15', 'Manual update', '0', '2026-05-01 14:37:22', NULL);
INSERT INTO `inventory_logs` (`id`, `product_id`, `branch_id`, `old_stock`, `new_stock`, `change_amount`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('2', '1', '1', '9', '10', '1', 'Return completed #R20260511-600', '2', '2026-05-11 06:53:21', NULL);
INSERT INTO `inventory_logs` (`id`, `product_id`, `branch_id`, `old_stock`, `new_stock`, `change_amount`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('3', '1', '1', '0', '15', '15', 'Manual update', '0', '2026-05-11 14:48:45', NULL);
INSERT INTO `inventory_logs` (`id`, `product_id`, `branch_id`, `old_stock`, `new_stock`, `change_amount`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('4', '8', '1', '0', '10', '10', 'Manual update', '0', '2026-05-13 08:22:57', NULL);
INSERT INTO `inventory_logs` (`id`, `product_id`, `branch_id`, `old_stock`, `new_stock`, `change_amount`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('5', '2', '1', '0', '15', '15', 'Manual update', '0', '2026-05-13 10:07:25', NULL);


-- Table structure for `invoice_sequences`
DROP TABLE IF EXISTS `invoice_sequences`;
CREATE TABLE `invoice_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_sequences_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_invoice_sequences_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `invoice_sequences`
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('1', '1', '2026', '5', '1', '2026-05-01 14:38:07', '2026-05-01 14:38:07', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('2', '1', '2026', '5', '1', '2026-05-01 19:37:36', '2026-05-01 19:37:36', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('3', '1', '2026', '5', '1', '2026-05-01 20:06:40', '2026-05-01 20:06:40', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('4', '1', '2026', '5', '1', '2026-05-01 20:09:49', '2026-05-01 20:09:49', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('5', '1', '2026', '5', '1', '2026-05-02 14:31:23', '2026-05-02 14:31:23', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('6', '1', '2026', '5', '1', '2026-05-02 15:37:27', '2026-05-02 15:37:27', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('7', '1', '2026', '5', '1', '2026-05-11 09:32:06', '2026-05-11 09:32:06', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('8', '1', '2026', '5', '1', '2026-05-11 09:41:18', '2026-05-11 09:41:18', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('9', '1', '2026', '5', '1', '2026-05-11 13:35:50', '2026-05-11 13:35:50', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('10', '1', '2026', '5', '1', '2026-05-11 14:34:31', '2026-05-11 14:34:31', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('11', '1', '2026', '5', '1', '2026-05-11 14:43:17', '2026-05-11 14:43:17', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('12', '1', '2026', '5', '1', '2026-05-11 14:52:16', '2026-05-11 14:52:16', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('13', '1', '2026', '5', '1', '2026-05-11 14:52:39', '2026-05-11 14:52:39', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('14', '1', '2026', '5', '1', '2026-05-11 14:54:20', '2026-05-11 14:54:20', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('15', '1', '2026', '5', '1', '2026-05-11 15:07:08', '2026-05-11 15:07:08', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('16', '1', '2026', '5', '1', '2026-05-11 15:30:56', '2026-05-11 15:30:56', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('17', '1', '2026', '5', '1', '2026-05-12 07:19:39', '2026-05-12 07:19:39', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('18', '1', '2026', '5', '1', '2026-05-12 13:47:10', '2026-05-12 13:47:10', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('19', '1', '2026', '5', '1', '2026-05-12 17:56:09', '2026-05-12 17:56:09', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('20', '1', '2026', '5', '1', '2026-05-12 18:21:22', '2026-05-12 18:21:22', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('21', '1', '2026', '5', '1', '2026-05-12 18:32:32', '2026-05-12 18:32:32', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('22', '1', '2026', '5', '1', '2026-05-12 18:49:48', '2026-05-12 18:49:48', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('23', '1', '2026', '5', '1', '2026-05-13 08:18:59', '2026-05-13 08:18:59', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('24', '1', '2026', '5', '1', '2026-05-13 10:07:44', '2026-05-13 10:07:44', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('25', '1', '2026', '5', '1', '2026-05-13 10:27:49', '2026-05-13 10:27:49', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('26', '1', '2026', '5', '1', '2026-05-13 11:03:24', '2026-05-13 11:03:24', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('27', '1', '2026', '5', '1', '2026-05-13 11:05:39', '2026-05-13 11:05:39', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('28', '1', '2026', '5', '1', '2026-05-13 12:15:12', '2026-05-13 12:15:12', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('29', '1', '2026', '5', '1', '2026-05-13 12:17:18', '2026-05-13 12:17:18', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('30', '1', '2026', '5', '1', '2026-05-15 09:08:34', '2026-05-15 09:08:34', '1');
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES ('31', '1', '2026', '5', '1', '2026-05-15 10:53:59', '2026-05-15 10:53:59', '1');


-- Table structure for `invoices`
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `subscription_id` (`subscription_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_invoices_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `company_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `kitchen_order_items`
DROP TABLE IF EXISTS `kitchen_order_items`;
CREATE TABLE `kitchen_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `kitchen_order_id` int(11) NOT NULL,
  `sale_item_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `modifiers` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_koi_order` (`kitchen_order_id`),
  KEY `idx_kitchen_order_items_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_kitchen_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `kitchen_orders`
DROP TABLE IF EXISTS `kitchen_orders`;
CREATE TABLE `kitchen_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `table_number` varchar(20) DEFAULT NULL,
  `order_type` varchar(30) NOT NULL DEFAULT 'walkin',
  `status` enum('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
  `priority` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ko_branch` (`branch_id`),
  KEY `idx_ko_sale` (`sale_id`),
  KEY `idx_ko_status` (`status`),
  KEY `idx_kitchen_orders_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_kitchen_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `login_history`
DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_login_history_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_login_history_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `login_rate_limits`
DROP TABLE IF EXISTS `login_rate_limits`;
CREATE TABLE `login_rate_limits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_count` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_attempt_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `loyalty_points_log`
DROP TABLE IF EXISTS `loyalty_points_log`;
CREATE TABLE `loyalty_points_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `points_change` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_company_date` (`created_at`),
  KEY `idx_loyalty_points_log_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_loyalty_points_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `loyalty_redemptions`
DROP TABLE IF EXISTS `loyalty_redemptions`;
CREATE TABLE `loyalty_redemptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `reward_id` int(10) unsigned NOT NULL,
  `points_redeemed` int(10) unsigned NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_reward_id` (`reward_id`),
  KEY `idx_status` (`status`),
  KEY `idx_loyalty_redemptions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_loyalty_redemptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `loyalty_rewards`
DROP TABLE IF EXISTS `loyalty_rewards`;
CREATE TABLE `loyalty_rewards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `points_required` int(10) unsigned NOT NULL,
  `discount_type` enum('fixed','percentage') DEFAULT 'fixed',
  `discount_value` decimal(10,2) NOT NULL,
  `max_uses` int(10) unsigned DEFAULT NULL,
  `stock_available` int(10) unsigned DEFAULT NULL,
  `stock_used` int(10) unsigned DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_points_required` (`points_required`),
  KEY `idx_loyalty_rewards_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_loyalty_rewards_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `mobile_devices`
DROP TABLE IF EXISTS `mobile_devices`;
CREATE TABLE `mobile_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `device_type` enum('pos','customer','driver','manager') NOT NULL,
  `app_version` varchar(20) DEFAULT '1.0.0',
  `push_token` text DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_device` (`user_id`,`device_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_device_type` (`device_type`),
  KEY `idx_mobile_devices_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_mobile_devices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `notifications`
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notifications_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_notifications_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `offline_sync_queue`
DROP TABLE IF EXISTS `offline_sync_queue`;
CREATE TABLE `offline_sync_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `terminal_id` varchar(100) DEFAULT NULL,
  `offline_id` varchar(100) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `payload` longtext NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','synced','failed','conflict') DEFAULT 'pending',
  `synced_at` timestamp NULL DEFAULT NULL,
  `server_sale_id` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`status`),
  KEY `idx_offline_id` (`offline_id`),
  KEY `idx_terminal` (`terminal_id`),
  KEY `idx_offline_sync_queue_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_offline_sync_queue_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `override_requests`
DROP TABLE IF EXISTS `override_requests`;
CREATE TABLE `override_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `data` longtext DEFAULT NULL CHECK (json_valid(`data`)),
  `reason` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `threshold` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','expired') DEFAULT 'pending',
  `approval_code` varchar(20) NOT NULL,
  `approver_id` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`status`),
  KEY `idx_approval_code` (`approval_code`),
  KEY `idx_requester` (`requester_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_override_requests_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_override_requests_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `owner_audit_logs`
DROP TABLE IF EXISTS `owner_audit_logs`;
CREATE TABLE `owner_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `password_reset_tokens`
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `purpose` enum('password_reset','email_verification','account_unlock') DEFAULT 'password_reset',
  `is_used` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `password_resets`
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `payments`
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `method` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_payments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `permissions`
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_perms_tenant` (`tenant_id`),
  KEY `idx_permissions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `plan_features`
DROP TABLE IF EXISTS `plan_features`;
CREATE TABLE `plan_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `feature_id` int(10) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_features` (`plan_id`,`feature_id`),
  KEY `idx_plan_features_plan` (`plan_id`,`is_enabled`),
  KEY `idx_plan_features_feature` (`feature_id`,`is_enabled`),
  CONSTRAINT `fk_plan_features_feature` FOREIGN KEY (`feature_id`) REFERENCES `features` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_plan_features_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `plans`
DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `currency` varchar(10) NOT NULL DEFAULT 'KES',
  `max_users` int(10) unsigned NOT NULL DEFAULT 5,
  `max_branches` int(10) unsigned NOT NULL DEFAULT 1,
  `max_products` int(10) unsigned NOT NULL DEFAULT 100,
  `max_storage_mb` int(10) unsigned NOT NULL DEFAULT 500,
  `trial_days` smallint(5) unsigned NOT NULL DEFAULT 14,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_popular` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plans_slug` (`slug`),
  KEY `idx_plans_active_sort` (`is_active`,`sort_order`),
  CONSTRAINT `chk_plans_active` CHECK (`is_active` in (0,1)),
  CONSTRAINT `chk_plans_popular` CHECK (`is_popular` in (0,1))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `plans`
INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price`, `billing_cycle`, `currency`, `max_users`, `max_branches`, `max_products`, `max_storage_mb`, `trial_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES ('1', 'Free', 'free', 'Perfect for getting started', '0.00', 'monthly', 'KES', '2', '1', '50', '100', '0', '1', '0', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price`, `billing_cycle`, `currency`, `max_users`, `max_branches`, `max_products`, `max_storage_mb`, `trial_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES ('2', 'Basic', 'basic', 'Essential features for growing businesses', '999.00', 'monthly', 'KES', '10', '2', '1000', '2000', '14', '1', '0', '2', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price`, `billing_cycle`, `currency`, `max_users`, `max_branches`, `max_products`, `max_storage_mb`, `trial_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES ('3', 'Professional', 'professional', 'For growing businesses', '2499.00', 'monthly', 'KES', '15', '5', '1000', '2000', '14', '1', '1', '3', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price`, `billing_cycle`, `currency`, `max_users`, `max_branches`, `max_products`, `max_storage_mb`, `trial_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES ('4', 'Enterprise', 'enterprise', 'For large organizations', '4999.00', 'monthly', 'KES', '999', '999', '99999', '10000', '30', '1', '0', '4', '2026-04-30 19:05:32', '2026-04-30 19:05:32');
INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price`, `billing_cycle`, `currency`, `max_users`, `max_branches`, `max_products`, `max_storage_mb`, `trial_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES ('5', 'Starter', 'starter', 'For small businesses', '999.00', 'monthly', 'KES', '5', '2', '200', '500', '14', '1', '0', '2', '2026-04-30 19:05:32', '2026-04-30 19:05:32');


-- Table structure for `platform_payments`
DROP TABLE IF EXISTS `platform_payments`;
CREATE TABLE `platform_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `payment_method` enum('mpesa','card','bank_transfer') NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_reference` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_platform_payments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_platform_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `pos_api_keys`
DROP TABLE IF EXISTS `pos_api_keys`;
CREATE TABLE `pos_api_keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `key_hash` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `prefix` varchar(10) NOT NULL,
  `scopes` longtext NOT NULL CHECK (json_valid(`scopes`)),
  `rate_limit` int(11) DEFAULT 1000,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`key_hash`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_key_hash` (`key_hash`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `pos_api_keys_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `pos_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_api_rate_limits`
DROP TABLE IF EXISTS `pos_api_rate_limits`;
CREATE TABLE `pos_api_rate_limits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `api_key_id` int(10) unsigned NOT NULL,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `request_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_key_window` (`api_key_id`,`window_start`),
  CONSTRAINT `pos_api_rate_limits_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `pos_api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_business_features`
DROP TABLE IF EXISTS `pos_business_features`;
CREATE TABLE `pos_business_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_type_id` int(10) unsigned NOT NULL,
  `feature_key` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_business_feature` (`business_type_id`,`feature_key`),
  CONSTRAINT `pos_business_features_ibfk_1` FOREIGN KEY (`business_type_id`) REFERENCES `pos_business_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `pos_business_features`
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('1', '1', 'barcode', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('2', '1', 'stock', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('3', '2', 'barcode', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('4', '2', 'stock', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('5', '2', 'weight', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('6', '3', 'kitchen_notes', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('7', '3', 'table', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('8', '4', 'prescription', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('9', '4', 'stock', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('10', '5', 'appointment', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('11', '6', 'serial', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('12', '6', 'warranty', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('13', '7', 'barcode', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('14', '7', 'stock', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('15', '7', 'weight', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('16', '8', 'weight', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('17', '8', 'stock', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('18', '9', 'expiry', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('19', '9', 'stock', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('20', '10', 'room_charge', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('21', '11', 'bulk', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('22', '11', 'discount', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('23', '12', 'appointment', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('24', '13', 'barcode', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('25', '13', 'stock', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES ('26', '13', 'weight', '1', NULL, '2026-05-02 15:14:36', '2026-05-02 15:14:36');


-- Table structure for `pos_business_types`
DROP TABLE IF EXISTS `pos_business_types`;
CREATE TABLE `pos_business_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-store',
  `color` varchar(7) DEFAULT '#3B82F6',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `pos_business_types`
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('1', 'retail', 'Retail Store', 'fa-store', '#3B82F6', 'General retail store', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('2', 'supermarket', 'Supermarket', 'fa-shopping-cart', '#10B981', 'Large retail store with multiple categories', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('3', 'restaurant', 'Restaurant / Cafe', 'fa-utensils', '#F59E0B', 'Food service establishment', '1', '2', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('4', 'pharmacy', 'Pharmacy', 'fa-pills', '#EF4444', 'Pharmaceutical and medical supplies', '1', '3', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('5', 'salon', 'Salon / Beauty', 'fa-cut', '#EC4899', 'Beauty and personal care services', '1', '4', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('6', 'electronics', 'Electronics', 'fa-laptop', '#8B5CF6', 'Electronic devices and accessories', '1', '5', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('7', 'hardware', 'Hardware Store', 'fa-hammer', '#F97316', 'Construction and home improvement supplies', '1', '6', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('8', 'butchery', 'Butchery', 'fa-drumstick-bite', '#DC2626', 'Fresh meat and poultry', '1', '7', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('9', 'bakery', 'Bakery', 'fa-bread-slice', '#D97706', 'Fresh baked goods', '1', '8', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('10', 'hotel', 'Hotel / Lodging', 'fa-hotel', '#6366F1', 'Hospitality and accommodation', '1', '9', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('11', 'wholesale', 'Wholesale', 'fa-boxes', '#06B6D4', 'Bulk sales and distribution', '1', '10', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('12', 'service', 'Service Business', 'fa-concierge-bell', '#14B8A6', 'Professional services', '1', '11', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('13', 'grocery', 'Grocery Store', 'fa-apple-alt', '#22C55E', 'Food and household items', '1', '12', '2026-05-02 15:14:36', '2026-05-02 15:14:36');


-- Table structure for `pos_features`
DROP TABLE IF EXISTS `pos_features`;
CREATE TABLE `pos_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `feature_key` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_key` (`feature_key`),
  KEY `idx_module` (`module_name`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_invoices`
DROP TABLE IF EXISTS `pos_invoices`;
CREATE TABLE `pos_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'KES',
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('draft','pending','paid','failed','void','refunded') DEFAULT 'draft',
  `due_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `stripe_invoice_id` varchar(100) DEFAULT NULL,
  `mpesa_receipt` varchar(100) DEFAULT NULL,
  `pdf_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `subscription_id` (`subscription_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `pos_invoices_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `pos_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pos_invoices_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `pos_subscriptions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_order_types`
DROP TABLE IF EXISTS `pos_order_types`;
CREATE TABLE `pos_order_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `business_type_id` int(10) unsigned NOT NULL,
  `slug` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-circle',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_business_order` (`business_type_id`,`slug`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `pos_order_types_ibfk_1` FOREIGN KEY (`business_type_id`) REFERENCES `pos_business_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `pos_order_types`
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('1', '1', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('2', '2', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('3', '2', 'online', 'Online', 'fa-globe', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('4', '3', 'dinein', 'Dine-in', 'fa-chair', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('5', '3', 'takeaway', 'Takeaway', 'fa-bag', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('6', '3', 'delivery', 'Delivery', 'fa-truck', '1', '2', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('7', '4', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('8', '4', 'prescription', 'Prescription', 'fa-file-medical', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('9', '5', 'appointment', 'Appointment', 'fa-calendar', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('10', '5', 'walkin', 'Walk-in', 'fa-user', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('11', '6', 'retail', 'Retail', 'fa-store', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('12', '6', 'installment', 'Installment', 'fa-calendar-check', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('13', '7', 'retail', 'Retail', 'fa-store', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('14', '7', 'wholesale', 'Wholesale', 'fa-boxes', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('15', '8', 'retail', 'Retail', 'fa-store', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('16', '8', 'wholesale', 'Wholesale', 'fa-boxes', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('17', '9', 'dinein', 'Dine-in', 'fa-chair', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('18', '9', 'takeaway', 'Takeaway', 'fa-bag', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('19', '9', 'delivery', 'Delivery', 'fa-truck', '1', '2', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('20', '10', 'room', 'Room Service', 'fa-bed', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('21', '10', 'dinein', 'Restaurant', 'fa-utensils', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('22', '11', 'wholesale', 'Wholesale', 'fa-boxes', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('23', '11', 'online', 'Online', 'fa-globe', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('24', '12', 'service', 'Service', 'fa-hand-sparkles', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('25', '12', 'appointment', 'Appointment', 'fa-calendar', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('26', '13', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('27', '13', 'online', 'Online', 'fa-globe', '1', '1', '2026-05-02 15:14:36', '2026-05-02 15:14:36');
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES ('28', '13', 'delivery', 'Delivery', 'fa-truck', '1', '2', '2026-05-02 15:14:36', '2026-05-02 15:14:36');


-- Table structure for `pos_payment_methods`
DROP TABLE IF EXISTS `pos_payment_methods`;
CREATE TABLE `pos_payment_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('stripe_card','stripe_mpesa','mpesa','bank_transfer','cash') NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `stripe_customer_id` varchar(100) DEFAULT NULL,
  `stripe_payment_method_id` varchar(100) DEFAULT NULL,
  `mpesa_phone` varchar(20) DEFAULT NULL,
  `mpesa_shortcode` varchar(10) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account` varchar(100) DEFAULT NULL,
  `bank_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_plan_features`
DROP TABLE IF EXISTS `pos_plan_features`;
CREATE TABLE `pos_plan_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `feature_id` int(10) unsigned NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `limit_value` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plan_feature` (`plan_id`,`feature_id`),
  KEY `feature_id` (`feature_id`),
  CONSTRAINT `pos_plan_features_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `pos_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pos_plan_features_ibfk_2` FOREIGN KEY (`feature_id`) REFERENCES `pos_features` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_plans`
DROP TABLE IF EXISTS `pos_plans`;
CREATE TABLE `pos_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'KES',
  `billing_cycle` enum('monthly','quarterly','annual') DEFAULT 'monthly',
  `trial_days` int(11) DEFAULT 0,
  `max_users` int(11) DEFAULT 1,
  `max_branches` int(11) DEFAULT 1,
  `max_products` int(11) DEFAULT 100,
  `max_storage_mb` int(11) DEFAULT 1024,
  `max_api_calls` int(11) DEFAULT 1000,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_subscriptions`
DROP TABLE IF EXISTS `pos_subscriptions`;
CREATE TABLE `pos_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `plan_id` int(10) unsigned NOT NULL,
  `status` enum('active','trialing','past_due','cancelled','expired') DEFAULT 'active',
  `current_period_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `current_period_end` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `pos_subscriptions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `pos_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pos_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `pos_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_tenants`
DROP TABLE IF EXISTS `pos_tenants`;
CREATE TABLE `pos_tenants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `subdomain` varchar(63) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `status` enum('active','suspended','cancelled','trial') DEFAULT 'trial',
  `plan_id` int(10) unsigned DEFAULT 1,
  `parent_tenant_id` int(10) unsigned DEFAULT NULL,
  `settings` longtext DEFAULT NULL CHECK (json_valid(`settings`)),
  `branding` longtext DEFAULT NULL CHECK (json_valid(`branding`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `subdomain` (`subdomain`),
  UNIQUE KEY `domain` (`domain`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_subdomain` (`subdomain`),
  KEY `idx_uuid` (`uuid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_webhook_deliveries`
DROP TABLE IF EXISTS `pos_webhook_deliveries`;
CREATE TABLE `pos_webhook_deliveries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` int(10) unsigned NOT NULL,
  `event` varchar(100) NOT NULL,
  `payload` longtext NOT NULL CHECK (json_valid(`payload`)),
  `response_status` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `attempt` int(11) DEFAULT 1,
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook` (`webhook_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `pos_webhook_deliveries_ibfk_1` FOREIGN KEY (`webhook_id`) REFERENCES `pos_webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `pos_webhooks`
DROP TABLE IF EXISTS `pos_webhooks`;
CREATE TABLE `pos_webhooks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `url` varchar(500) NOT NULL,
  `events` longtext NOT NULL CHECK (json_valid(`events`)),
  `secret` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `failure_count` int(11) DEFAULT 0,
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `pos_webhooks_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `pos_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `product_affinity`
DROP TABLE IF EXISTS `product_affinity`;
CREATE TABLE `product_affinity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `related_product_id` int(11) NOT NULL,
  `affinity_score` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `times_bought_together` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_affinity_score` (`affinity_score`),
  KEY `idx_product` (`product_id`),
  KEY `idx_product_affinity_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_affinity_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `product_attributes`
DROP TABLE IF EXISTS `product_attributes`;
CREATE TABLE `product_attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `attribute_name` varchar(100) NOT NULL,
  `attribute_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_attributes_product` (`product_id`),
  KEY `idx_product_attributes_name` (`attribute_name`),
  KEY `idx_product_attributes_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_attributes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_attributes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `product_batches`
DROP TABLE IF EXISTS `product_batches`;
CREATE TABLE `product_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `remaining_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `cost_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pb_product` (`product_id`),
  KEY `idx_pb_expiry` (`expiry_date`),
  KEY `idx_product_batches_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_batches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `product_expiry`
DROP TABLE IF EXISTS `product_expiry`;
CREATE TABLE `product_expiry` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(10) unsigned DEFAULT 1,
  `product_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `expiry_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_batch` (`batch_number`),
  KEY `idx_product_expiry_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_expiry_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `product_images`
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_product_images_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_images_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `product_modifiers`
DROP TABLE IF EXISTS `product_modifiers`;
CREATE TABLE `product_modifiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `modifier_group` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `max_quantity` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_modifier_product` (`product_id`),
  KEY `idx_product_modifiers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_modifiers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `product_serials`
DROP TABLE IF EXISTS `product_serials`;
CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `status` enum('available','sold','returned','defective') NOT NULL DEFAULT 'available',
  `sale_id` int(11) DEFAULT NULL,
  `sale_item_id` int(11) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ps_product` (`product_id`),
  KEY `idx_ps_serial` (`serial_number`),
  KEY `idx_product_serials_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_serials_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `product_variants`
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sku` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_product_variants_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_variants_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `products`
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_type` varchar(50) DEFAULT NULL,
  `business_type_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `barcode` varchar(80) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(4) DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `tax_rate_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `reorder_level` int(11) DEFAULT 0,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_products_sku` (`sku`),
  KEY `idx_products_category` (`category_id`),
  KEY `fk_product_tax_rate` (`tax_rate_id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_company_product` (`status`),
  KEY `idx_products_company_category` (`category_id`),
  KEY `idx_products_company_status` (`status`),
  KEY `idx_products_barcode` (`barcode`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_active` (`active`),
  KEY `idx_products_deleted_at` (`deleted_at`),
  KEY `idx_products_company_active` (`active`),
  KEY `idx_products_tenant` (`tenant_id`),
  KEY `idx_products_tenant_category_status` (`tenant_id`,`category_id`,`status`,`id`),
  KEY `idx_products_tenant_active` (`tenant_id`,`active`),
  KEY `idx_products_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `products`
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('1', NULL, '2', '1', NULL, 'Heavy Rompers', 'HEAVYROM-590', '', '1500.00', NULL, '700.00', '1', NULL, 'Very Soft', NULL, NULL, '2026-05-01 10:38:35', '2026-05-01 14:46:39', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('2', NULL, '2', '1', NULL, 'Baby Blanket', 'BABYBLAN-300', '', '1500.00', NULL, '699.98', '1', NULL, '', NULL, NULL, '2026-05-12 20:14:16', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('3', NULL, '2', '1', NULL, 'Baby Shawl', 'BABYSHAW-213', '', '1600.00', NULL, '749.98', '1', NULL, '', NULL, NULL, '2026-05-12 20:16:38', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('4', NULL, '2', '1', NULL, 'Cussion packed', 'CUSSIONP-746', '', '2300.00', NULL, '1200.00', '1', NULL, '', NULL, NULL, '2026-05-12 20:17:47', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('5', NULL, '2', '1', NULL, 'Mackintosh', 'MACKINTO-270', '', '700.00', NULL, '300.00', '1', NULL, '', NULL, NULL, '2026-05-12 20:18:49', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('6', NULL, '2', '1', NULL, 'Flannel Sheets', 'FLANNELS-286', '', '1500.00', NULL, '560.00', '1', NULL, '', NULL, NULL, '2026-05-12 20:21:23', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('7', NULL, '2', '1', NULL, 'Mittens', 'MITTENS-227', '', '100.00', NULL, '65.00', '1', NULL, '', NULL, NULL, '2026-05-12 20:21:55', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `barcode`, `price`, `selling_price`, `cost_price`, `active`, `image`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `tenant_id`) VALUES ('8', NULL, '7', NULL, NULL, 'Cement', 'CEMENT-667', '', '1200.00', NULL, '650.00', '1', NULL, '', NULL, NULL, '2026-05-13 08:22:24', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', '1');


-- Table structure for `promotions`
DROP TABLE IF EXISTS `promotions`;
CREATE TABLE `promotions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'percentage',
  `conditions` longtext DEFAULT NULL CHECK (json_valid(`conditions`)),
  `actions` longtext DEFAULT NULL CHECK (json_valid(`actions`)),
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `valid_days` longtext DEFAULT NULL CHECK (json_valid(`valid_days`)),
  `valid_time_from` time DEFAULT NULL,
  `valid_time_until` time DEFAULT NULL,
  `priority` int(11) DEFAULT 0,
  `exclusive` tinyint(1) DEFAULT 0,
  `max_uses` int(11) DEFAULT 0,
  `current_uses` int(11) DEFAULT 0,
  `applicable_products` longtext DEFAULT NULL CHECK (json_valid(`applicable_products`)),
  `applicable_categories` longtext DEFAULT NULL CHECK (json_valid(`applicable_categories`)),
  `applicable_customer_groups` longtext DEFAULT NULL CHECK (json_valid(`applicable_customer_groups`)),
  `min_purchase_amount` decimal(10,2) DEFAULT 0.00,
  `min_quantity` int(11) DEFAULT 0,
  `max_discount_quantity` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active_dates` (`active`,`valid_from`,`valid_until`),
  KEY `idx_priority` (`priority`),
  KEY `idx_promotions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `purchase_order_items`
DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `received_quantity` int(11) NOT NULL DEFAULT 0,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `purchase_order_id` (`purchase_order_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_purchase_order_items_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_purchase_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `purchase_orders`
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'pending',
  `expected_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `due_amount` decimal(15,2) DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `manager` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `branch_id` (`branch_id`),
  KEY `purchase_orders_ibfk_3` (`created_by`),
  KEY `idx_po_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_purchase_orders_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `quotation_items`
DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_quotation_items_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_quotation_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `quotations`
DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_quotations_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_quotations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `rate_limit_violations`
DROP TABLE IF EXISTS `rate_limit_violations`;
CREATE TABLE `rate_limit_violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL COMMENT 'User ID, IP, or API key',
  `endpoint` varchar(100) NOT NULL COMMENT 'API endpoint that triggered limit',
  `request_count` int(10) unsigned NOT NULL COMMENT 'Number of requests made',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP address',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_rate_limit_violations_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_rate_limit_violations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `rate_limits`
DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_key` varchar(100) NOT NULL,
  `request_count` int(11) DEFAULT 1,
  `reset_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_action` (`user_id`,`action_key`),
  KEY `idx_reset` (`reset_at`),
  CONSTRAINT `rate_limits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `register_sessions`
DROP TABLE IF EXISTS `register_sessions`;
CREATE TABLE `register_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `opening_cash` decimal(14,2) NOT NULL DEFAULT 0.00,
  `closing_cash` decimal(14,2) DEFAULT NULL,
  `expected_cash` decimal(14,2) DEFAULT NULL,
  `cash_difference` decimal(14,2) DEFAULT NULL,
  `total_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_cash_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_card_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_other_sales` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sale_count` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_register_branch` (`branch_id`),
  KEY `idx_register_user` (`user_id`),
  KEY `idx_register_status` (`status`),
  KEY `idx_register_sessions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_register_sessions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `register_sessions`
INSERT INTO `register_sessions` (`id`, `branch_id`, `user_id`, `opening_cash`, `closing_cash`, `expected_cash`, `cash_difference`, `total_sales`, `total_cash_sales`, `total_card_sales`, `total_other_sales`, `sale_count`, `notes`, `status`, `opened_at`, `closed_at`, `tenant_id`) VALUES ('1', '1', '1', '200.00', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0', NULL, 'open', '2026-05-01 14:37:55', NULL, '1');
INSERT INTO `register_sessions` (`id`, `branch_id`, `user_id`, `opening_cash`, `closing_cash`, `expected_cash`, `cash_difference`, `total_sales`, `total_cash_sales`, `total_card_sales`, `total_other_sales`, `sale_count`, `notes`, `status`, `opened_at`, `closed_at`, `tenant_id`) VALUES ('2', '1', '2', '200.00', '200.00', '200.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0', '', 'closed', '2026-05-02 14:31:09', '2026-05-02 16:28:47', '1');
INSERT INTO `register_sessions` (`id`, `branch_id`, `user_id`, `opening_cash`, `closing_cash`, `expected_cash`, `cash_difference`, `total_sales`, `total_cash_sales`, `total_card_sales`, `total_other_sales`, `sale_count`, `notes`, `status`, `opened_at`, `closed_at`, `tenant_id`) VALUES ('3', '1', '2', '200.00', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0', NULL, 'open', '2026-05-11 09:23:35', NULL, '1');


-- Table structure for `remember_tokens`
DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE `remember_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `token_series` varchar(255) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_remember_tokens_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_remember_tokens_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `return_items`
DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `sale_item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_return_items_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_return_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `return_items`
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('1', '1', '1', '1', '6', '1', '1500.00', '1500.00', NULL, '2026-05-10 12:29:03');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('2', NULL, '3', '1', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-11 03:36:51');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('3', NULL, '4', '1', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-11 03:38:22');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('4', '1', '5', '1', '4', '1', '1500.00', '1500.00', NULL, '2026-05-11 04:24:36');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('5', NULL, '6', '1', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-11 05:06:08');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('6', NULL, '7', '2', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 08:28:23');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('7', NULL, '8', '1', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 08:31:26');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('14', NULL, '9', '2', NULL, '2', '1500.00', '3000.00', NULL, '2026-05-14 11:30:53');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('15', NULL, '10', '2', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 11:32:00');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('17', NULL, '11', '1', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 17:29:43');
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`) VALUES ('18', NULL, '12', '2', NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 17:30:56');


-- Table structure for `returns`
DROP TABLE IF EXISTS `returns`;
CREATE TABLE `returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `return_number` varchar(50) NOT NULL,
  `return_type` enum('sales','purchase') DEFAULT 'sales',
  `sale_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `processed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `sale_id` (`sale_id`),
  KEY `customer_id` (`customer_id`),
  KEY `branch_id` (`branch_id`),
  KEY `processed_by` (`processed_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_updated` (`updated_at`),
  KEY `idx_returns_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_returns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `returns`
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('1', 'R20260510-1296', 'sales', '6', NULL, '1', '0', NULL, 'wrong_item', '1500.00', 'completed', '', '2026-05-10 12:29:03', '2026-05-10 12:29:03', '1');
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('3', 'R20260511-668', 'sales', '5', NULL, '1', '2', NULL, 'Wrong Item', '1500.00', 'pending', '', '2026-05-11 03:36:51', '2026-05-11 03:36:51', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('4', 'R20260511-542', 'sales', '4', NULL, '1', '2', NULL, 'Wrong Item', '1500.00', 'pending', '', '2026-05-11 03:38:22', '2026-05-11 03:38:22', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('5', 'R20260511-1701', 'sales', '4', NULL, '1', '0', NULL, 'wrong_item', '1500.00', 'completed', '', '2026-05-11 04:24:36', '2026-05-11 04:24:36', '1');
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('6', 'R20260511-600', 'sales', '3', NULL, '1', '2', '2', 'Wrong Item', '1500.00', 'completed', '', '2026-05-11 05:06:08', '2026-05-11 06:53:21', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('7', 'R20260514-024', 'sales', '29', NULL, '1', '2', NULL, 'Wrong Item', '1500.00', 'pending', '', '2026-05-14 08:28:23', '2026-05-14 08:28:23', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('8', 'R20260514-598', 'sales', '22', NULL, '1', '2', NULL, 'Customer Changed Mind', '1500.00', 'pending', '', '2026-05-14 08:31:26', '2026-05-14 08:31:26', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('9', 'R20260514-574', 'sales', '28', NULL, '1', '2', NULL, 'Wrong Item', '1500.00', 'pending', '', '2026-05-14 11:29:11', '2026-05-14 11:29:11', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('10', 'R20260514-637', 'sales', '27', NULL, '1', '2', NULL, 'Damaged/Defective', '1500.00', 'pending', '', '2026-05-14 11:32:00', '2026-05-14 11:32:00', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('11', 'R20260514-706', 'sales', '23', NULL, '1', '2', NULL, 'Wrong Item', '1500.00', 'pending', '', '2026-05-14 17:28:55', '2026-05-14 17:28:55', NULL);
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `reason`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`) VALUES ('12', 'R20260514-554', 'sales', '26', NULL, '1', '2', NULL, 'Damaged/Defective', '1500.00', 'pending', '', '2026-05-14 17:30:56', '2026-05-14 17:30:56', NULL);


-- Table structure for `role_permissions`
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  KEY `idx_rp_tenant` (`tenant_id`),
  KEY `idx_role_permissions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `roles`
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_roles_tenant` (`tenant_id`),
  KEY `idx_roles_deleted` (`deleted_at`),
  KEY `idx_roles_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `roles`
INSERT INTO `roles` (`id`, `name`, `description`, `tenant_id`, `is_system`, `deleted_at`) VALUES ('1', 'Administrator', 'Full tenant access', NULL, '1', NULL);
INSERT INTO `roles` (`id`, `name`, `description`, `tenant_id`, `is_system`, `deleted_at`) VALUES ('2', 'Manager', 'Manage operations', NULL, '1', NULL);
INSERT INTO `roles` (`id`, `name`, `description`, `tenant_id`, `is_system`, `deleted_at`) VALUES ('3', 'Cashier', 'Process sales', NULL, '1', NULL);
INSERT INTO `roles` (`id`, `name`, `description`, `tenant_id`, `is_system`, `deleted_at`) VALUES ('4', 'Inventory', 'Manage inventory', NULL, '1', NULL);


-- Table structure for `saas_invoices`
DROP TABLE IF EXISTS `saas_invoices`;
CREATE TABLE `saas_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_status` (`status`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_saas_invoices_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_saas_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `sale_item_modifiers`
DROP TABLE IF EXISTS `sale_item_modifiers`;
CREATE TABLE `sale_item_modifiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `sale_item_id` int(11) NOT NULL,
  `modifier_id` int(11) NOT NULL,
  `modifier_name` varchar(100) NOT NULL,
  `modifier_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_sim_sale_item` (`sale_item_id`),
  KEY `idx_sale_item_modifiers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sale_item_modifiers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `sale_items`
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `weight` decimal(10,3) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(14,2) DEFAULT NULL,
  `price_override_reason` varchar(255) DEFAULT NULL,
  `kitchen_notes` varchar(255) DEFAULT NULL,
  `overridden_by` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`,`sale_id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_sale_items_tenant_id` (`tenant_id`),
  KEY `idx_saleitems_sale` (`sale_id`),
  KEY `idx_saleitems_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `sale_items`
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('1', NULL, '1', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('2', NULL, '2', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('3', NULL, '3', '1', '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('4', NULL, '4', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('5', NULL, '5', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('6', NULL, '6', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('7', NULL, '7', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('8', NULL, '8', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('9', NULL, '9', '1', '4', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '6000.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('10', NULL, '10', '1', '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('11', NULL, '11', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('12', NULL, '12', '1', '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('13', NULL, '13', '1', '3', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '4500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('14', NULL, '14', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('15', NULL, '15', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('16', NULL, '16', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('17', NULL, '17', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('18', NULL, '18', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('19', NULL, '19', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('20', NULL, '20', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('21', NULL, '21', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('22', NULL, '22', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('23', NULL, '23', '1', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('24', NULL, '24', '2', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('25', NULL, '25', '2', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('26', NULL, '26', '2', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('27', NULL, '27', '2', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('28', NULL, '28', '2', '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('29', NULL, '29', '2', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('30', NULL, '30', '2', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`) VALUES ('31', NULL, '31', '2', '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00');


-- Table structure for `sales`
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `register_session_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `due_amount` decimal(15,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `return_amount` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tip_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `balance_due` decimal(14,2) NOT NULL DEFAULT 0.00,
  `discount_type` varchar(20) DEFAULT 'fixed',
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_exempt` tinyint(1) DEFAULT 0,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` varchar(20) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pos_transaction` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'draft',
  `voided` tinyint(1) NOT NULL DEFAULT 0,
  `voided_by` int(11) DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `age_verified` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL,
  `order_type` enum('dine-in','takeaway','delivery') DEFAULT 'dine-in',
  `business_type` varchar(50) DEFAULT NULL,
  `business_type_id` int(11) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_sales_created_at` (`created_at`),
  KEY `idx_sales_customer` (`customer_id`),
  KEY `idx_sales_status_date` (`status`,`created_at`),
  KEY `idx_sales_branch_date` (`branch_id`,`created_at`),
  KEY `idx_sales_date` (`created_at`),
  KEY `idx_company_sales` (`created_at`),
  KEY `idx_sales_company_date` (`created_at`),
  KEY `idx_sales_company_status` (`status`),
  KEY `idx_sales_company_branch` (`branch_id`),
  KEY `idx_sales_branch` (`branch_id`),
  KEY `idx_sales_company_status_date` (`status`,`created_at`),
  KEY `idx_sales_branch_status_date` (`branch_id`,`status`,`created_at`),
  KEY `idx_sales_business_type` (`business_type`),
  KEY `idx_sales_tenant` (`tenant_id`),
  KEY `idx_sales_payment_method` (`payment_method`),
  KEY `idx_sales_customer_date` (`customer_id`,`created_at`),
  KEY `idx_sales_business_type_id` (`business_type_id`),
  KEY `idx_sales_tenant_branch_date` (`tenant_id`,`branch_id`,`created_at`,`id`),
  KEY `idx_sales_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_sales_tenant_id` (`tenant_id`),
  KEY `idx_sales_date_tenant` (`created_at`,`tenant_id`,`branch_id`),
  KEY `idx_sales_invoice` (`invoice_number`),
  CONSTRAINT `fk_sales_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `sales`
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('1', 'INV-202605-0001', '1', NULL, '1', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-01 14:38:08', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('2', 'INV-202605-0001', '1', NULL, '1', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-01 19:37:36', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('3', 'INV-202605-0001', '1', NULL, '1', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'split', NULL, '2026-05-01 20:06:40', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('4', 'INV-202605-0001', '1', NULL, '1', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-01 20:09:49', '0', 'returned', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('5', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'mpesa', NULL, '2026-05-02 14:31:23', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('6', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'bank_transfer', NULL, '2026-05-02 15:37:27', '0', 'returned', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('7', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 09:32:06', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('8', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'credit', NULL, '2026-05-11 09:41:18', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('9', 'INV-202605-0001', '1', NULL, '2', NULL, '6000.00', '6660.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '660.00', 'cash', NULL, '2026-05-11 13:35:50', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('10', 'INV-202605-0001', '1', NULL, '2', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'cash', NULL, '2026-05-11 14:34:31', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('11', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 14:43:17', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('12', 'INV-202605-0001', '1', NULL, '2', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'cash', NULL, '2026-05-11 14:52:16', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('13', 'INV-202605-0001', '1', NULL, '2', NULL, '4500.00', '4995.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '495.00', 'cash', NULL, '2026-05-11 14:52:39', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('14', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 14:54:20', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('15', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 15:07:08', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('16', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 15:30:57', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('17', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 07:19:39', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('18', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 13:47:10', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('19', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 17:56:09', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('20', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 18:21:22', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('21', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 18:32:32', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('22', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 18:49:48', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('23', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 08:18:59', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('24', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 10:07:44', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('25', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 10:27:49', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('26', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 11:03:24', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('27', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 11:05:39', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('28', 'INV-202605-0001', '1', NULL, '2', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'credit', NULL, '2026-05-13 12:15:12', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('29', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'credit', NULL, '2026-05-13 12:17:18', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('30', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'credit', NULL, '2026-05-15 09:08:34', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`) VALUES ('31', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'split', NULL, '2026-05-15 10:53:59', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1');


-- Table structure for `sales_velocity`
DROP TABLE IF EXISTS `sales_velocity`;
CREATE TABLE `sales_velocity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `daily_rate` decimal(10,2) DEFAULT 0.00,
  `weekly_rate` decimal(10,2) DEFAULT 0.00,
  `last_hour_sales` int(11) DEFAULT 0,
  `today_sales` int(11) DEFAULT 0,
  `peak_hour` tinyint(4) DEFAULT NULL,
  `velocity_score` decimal(5,4) DEFAULT 0.0000,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_velocity_score` (`velocity_score`),
  KEY `idx_trending` (`daily_rate`,`velocity_score`),
  KEY `idx_sales_velocity_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sales_velocity_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `schema_migrations`
DROP TABLE IF EXISTS `schema_migrations`;
CREATE TABLE `schema_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `checksum` varchar(64) DEFAULT NULL,
  `execution_time_ms` int(10) unsigned DEFAULT NULL,
  `status` enum('success','failed','rolled_back') NOT NULL DEFAULT 'success',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`),
  KEY `idx_schema_migrations_version` (`version`),
  KEY `idx_schema_migrations_status` (`status`),
  KEY `idx_schema_migrations_applied_at` (`applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `session_security`
DROP TABLE IF EXISTS `session_security`;
CREATE TABLE `session_security` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `fingerprint` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_fingerprint` (`fingerprint`),
  CONSTRAINT `session_security_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `sessions`
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `business_type` enum('retail','wholesale','restaurant','service','manufacturing','mixed') DEFAULT 'retail',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sessions_tenant` (`tenant_id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_sessions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `settings`
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `data_type` enum('text','number','boolean','json','email','url') DEFAULT 'text',
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting` (`tenant_id`,`setting_key`),
  KEY `idx_settings_tenant_id` (`tenant_id`),
  KEY `idx_settings_key_tenant` (`setting_key`,`tenant_id`),
  CONSTRAINT `fk_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1005 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `settings`
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('1', 'company_name', 'JAKPOS', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Company display name', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('2', 'company_email', '', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Company contact email', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('3', 'company_phone', '', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Company phone number', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('4', 'company_address', '', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Company physical address', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('5', 'business_type', 'supermarket', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Business type for UI customization', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('6', 'currency', 'KES', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Default currency code', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('7', 'timezone', 'Africa/Nairobi', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'System timezone', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('8', 'date_format', 'd M Y', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Date display format', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('9', 'time_format', 'H:i', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Time display format', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('10', 'tax_rate', '11', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Default tax rate percentage', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('11', 'default_branch_id', '1', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Default branch ID', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('12', 'default_payment_method', 'cash', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'general', 'Default payment method', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('13', 'enable_barcode_scanner', '1', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'system', 'Business type default: supermarket', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('14', 'allow_negative_stock', '0', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'system', 'Business type default: supermarket', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('15', 'enable_expiry_tracking', '1', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'system', 'Business type default: supermarket', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('16', 'default_reorder_level', '20', '1', '2', '2026-05-01 10:16:53', '2026-05-13 18:09:29', 'system', 'Business type default: supermarket', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('81', 'enable_tables', '1', '1', '2', '2026-05-01 20:25:17', '2026-05-11 13:18:59', 'system', 'Business type default: restaurant', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('82', 'enable_kitchen', '1', '1', '2', '2026-05-01 20:25:17', '2026-05-11 13:18:59', 'system', 'Business type default: restaurant', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('149', 'require_prescription', '1', '2', '2', '2026-05-02 15:38:01', '2026-05-13 09:50:08', 'system', 'Business type default: pharmacy', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('150', 'batch_tracking', '1', '2', '2', '2026-05-02 15:38:01', '2026-05-13 09:50:08', 'system', 'Business type default: pharmacy', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('625', 'company_logo', 'uploads/logos/company_1/logo_1_1778615488.png', '2', '2', '2026-05-12 10:14:11', '2026-05-12 19:51:28', 'general', 'Company logo path', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('965', 'site_title', 'Jakababa', '2', '2', '2026-05-13 17:35:34', '2026-05-13 18:09:29', 'general', 'Browser tab title and login heading', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('966', 'site_tagline', 'Smart System', '2', '2', '2026-05-13 17:35:34', '2026-05-13 18:09:29', 'general', 'Short subtitle below site title', 'text', '1');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES ('967', 'site_icon', 'uploads/icons/company_1/icon_1_1778695769.png', '2', '2', '2026-05-13 17:35:34', '2026-05-13 18:09:29', 'general', 'Site favicon path', 'text', '1');


-- Table structure for `shipment_items`
DROP TABLE IF EXISTS `shipment_items`;
CREATE TABLE `shipment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `shipment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_shipment` (`shipment_id`),
  KEY `idx_shipment_items_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_shipment_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `shipments`
DROP TABLE IF EXISTS `shipments`;
CREATE TABLE `shipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tracking_number` varchar(100) DEFAULT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `courier` varchar(100) DEFAULT NULL,
  `courier_service` varchar(100) DEFAULT NULL,
  `status` enum('pending','shipped','delivered','cancelled') DEFAULT 'pending',
  `address` text NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `estimated_delivery` date DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `tracking_url` varchar(255) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tracking_number` (`tracking_number`),
  KEY `sale_id` (`sale_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tracking` (`tracking_number`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_branch_status` (`branch_id`,`status`),
  KEY `idx_shipments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_shipments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `smart_alerts`
DROP TABLE IF EXISTS `smart_alerts`;
CREATE TABLE `smart_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `alert_type` enum('low_stock','high_demand','price_change','staff_alert','customer_vip') NOT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `data` longtext DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_branch` (`branch_id`),
  KEY `idx_unread` (`is_read`,`severity`),
  KEY `idx_smart_alerts_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_smart_alerts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `smart_recommendations`
DROP TABLE IF EXISTS `smart_recommendations`;
CREATE TABLE `smart_recommendations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recommendation_type` enum('upsell','cross_sell','bundle','repeat','trending','personalized') NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `recommended_products` longtext NOT NULL CHECK (json_valid(`recommended_products`)),
  `context_data` longtext DEFAULT NULL CHECK (json_valid(`context_data`)),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`recommendation_type`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_smart_recommendations_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_smart_recommendations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `sms_campaigns`
DROP TABLE IF EXISTS `sms_campaigns`;
CREATE TABLE `sms_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `target_type` enum('all','group','tier','selected','segment') NOT NULL,
  `target_ids` text DEFAULT NULL,
  `total_recipients` int(11) DEFAULT 0,
  `sent_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  `status` enum('draft','scheduled','processing','completed','cancelled') DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled` (`scheduled_at`),
  KEY `idx_sms_campaigns_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_campaigns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `sms_credits`
DROP TABLE IF EXISTS `sms_credits`;
CREATE TABLE `sms_credits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `balance` int(11) NOT NULL DEFAULT 0,
  `total_purchased` int(11) NOT NULL DEFAULT 0,
  `total_used` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_balance` (`balance`),
  KEY `idx_sms_credits_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_credits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `sms_credits`
INSERT INTO `sms_credits` (`id`, `balance`, `total_purchased`, `total_used`, `last_updated`, `tenant_id`) VALUES ('1', '0', '0', '0', '2026-05-02 16:52:08', '1');


-- Table structure for `sms_customer_consent`
DROP TABLE IF EXISTS `sms_customer_consent`;
CREATE TABLE `sms_customer_consent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `opted_in` tinyint(1) DEFAULT 1,
  `opted_in_date` timestamp NULL DEFAULT NULL,
  `opted_out_date` timestamp NULL DEFAULT NULL,
  `opted_out_reason` varchar(200) DEFAULT NULL,
  `consent_method` enum('signup','admin','sms_confirmation','import') DEFAULT 'signup',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(10) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_opted_in` (`opted_in`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_sms_customer_consent_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_customer_consent_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `sms_dnd_list`
DROP TABLE IF EXISTS `sms_dnd_list`;
CREATE TABLE `sms_dnd_list` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `added_by` int(10) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_sms_dnd_list_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_dnd_list_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `sms_logs`
DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE `sms_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_sms_logs_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `sms_templates`
DROP TABLE IF EXISTS `sms_templates`;
CREATE TABLE `sms_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `variables` longtext DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sms_templates_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `staff_commissions`
DROP TABLE IF EXISTS `staff_commissions`;
CREATE TABLE `staff_commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `sale_item_id` int(11) DEFAULT NULL,
  `commission_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sale_amount` decimal(14,2) NOT NULL,
  `commission_amount` decimal(14,2) NOT NULL,
  `status` enum('pending','approved','paid') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sc_user` (`user_id`),
  KEY `idx_sc_sale` (`sale_id`),
  KEY `idx_staff_commissions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_staff_commissions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `stock_batches`
DROP TABLE IF EXISTS `stock_batches`;
CREATE TABLE `stock_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `variant_id` int(10) unsigned DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `manufactured_at` date DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `quantity_received` decimal(14,3) NOT NULL DEFAULT 0.000,
  `quantity_remaining` decimal(14,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','consumed','expired','adjusted') NOT NULL DEFAULT 'active',
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lookup` (`branch_id`,`product_id`,`expiry_date`,`status`),
  KEY `idx_stock_batches_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_stock_batches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `stock_events`
DROP TABLE IF EXISTS `stock_events`;
CREATE TABLE `stock_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `old_qty` int(11) NOT NULL DEFAULT 0,
  `new_qty` int(11) NOT NULL DEFAULT 0,
  `event_type` varchar(20) DEFAULT 'sale',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_events_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_stock_events_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `stock_movement_log`
DROP TABLE IF EXISTS `stock_movement_log`;
CREATE TABLE `stock_movement_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `movement_type` varchar(50) NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `old_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `cost_price` decimal(12,4) DEFAULT 0.0000,
  `selling_price` decimal(12,4) DEFAULT 0.0000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_stock_movement_log_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_stock_movement_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `stock_movements`
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `movement_type` enum('purchase','sale','adjustment','return','transfer','damage','expired') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `quantity_before` int(11) NOT NULL DEFAULT 0,
  `quantity_after` int(11) NOT NULL DEFAULT 0,
  `reference_table` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_type` (`movement_type`),
  KEY `idx_created` (`created_at`),
  KEY `idx_product_date` (`product_id`,`created_at`),
  KEY `idx_branch_date` (`branch_id`,`created_at`),
  KEY `idx_stock_movements_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_stock_movements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `stock_movements`
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('1', '1', '1', NULL, NULL, 'sale', '-1', '15', '14', NULL, NULL, 'Sale', '1', '2026-05-01 14:38:08', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('2', '1', '1', NULL, NULL, 'sale', '-1', '14', '13', NULL, NULL, 'Sale', '1', '2026-05-01 19:37:36', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('3', '1', '1', NULL, NULL, 'sale', '-2', '13', '11', NULL, NULL, 'Sale', '1', '2026-05-01 20:06:40', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('4', '1', '1', NULL, NULL, 'sale', '-1', '11', '10', NULL, NULL, 'Sale', '1', '2026-05-01 20:09:49', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('5', '1', '1', NULL, NULL, 'sale', '-1', '10', '9', NULL, NULL, 'Sale', '2', '2026-05-02 14:31:23', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('6', '1', '1', NULL, NULL, 'sale', '-1', '9', '8', NULL, NULL, 'Sale', '2', '2026-05-02 15:37:27', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('7', '1', '1', NULL, NULL, 'sale', '-1', '9', '8', NULL, NULL, 'Sale', '2', '2026-05-11 09:32:06', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('8', '1', '1', NULL, NULL, 'sale', '-1', '8', '7', NULL, NULL, 'Sale', '2', '2026-05-11 09:41:18', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('9', '1', '1', NULL, NULL, 'sale', '-4', '7', '3', NULL, NULL, 'Sale', '2', '2026-05-11 13:35:50', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('10', '1', '1', NULL, NULL, 'sale', '-2', '3', '1', NULL, NULL, 'Sale', '2', '2026-05-11 14:34:32', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('11', '1', '1', NULL, NULL, 'sale', '-1', '1', '0', NULL, NULL, 'Sale', '2', '2026-05-11 14:43:17', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('12', '1', '1', NULL, NULL, 'sale', '-2', '15', '13', NULL, NULL, 'Sale', '2', '2026-05-11 14:52:16', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('13', '1', '1', NULL, NULL, 'sale', '-3', '13', '10', NULL, NULL, 'Sale', '2', '2026-05-11 14:52:39', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('14', '1', '1', NULL, NULL, 'sale', '-1', '10', '9', NULL, NULL, 'Sale', '2', '2026-05-11 14:54:20', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('15', '1', '1', NULL, NULL, 'sale', '-1', '9', '8', NULL, NULL, 'Sale', '2', '2026-05-11 15:07:08', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('16', '1', '1', NULL, NULL, 'sale', '-1', '8', '7', NULL, NULL, 'Sale', '2', '2026-05-11 15:30:57', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('17', '1', '1', NULL, NULL, 'sale', '-1', '7', '6', NULL, NULL, 'Sale', '2', '2026-05-12 07:19:39', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('18', '1', '1', NULL, NULL, 'sale', '-1', '6', '5', NULL, NULL, 'Sale', '2', '2026-05-12 13:47:10', '1');
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES ('19', '1', '1', NULL, NULL, 'sale', '-1', '5', '4', NULL, NULL, 'Sale', '2', '2026-05-12 17:56:09', '1');


-- Table structure for `stock_transfers`
DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `from_branch_id` int(11) NOT NULL,
  `to_branch_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_from_branch` (`from_branch_id`),
  KEY `idx_to_branch` (`to_branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_stock_transfers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_stock_transfers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `subscription_payments`
DROP TABLE IF EXISTS `subscription_payments`;
CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `payment_method` varchar(50) NOT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `gateway_response` longtext DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_status` (`status`),
  KEY `idx_subscription_payments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_subscription_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscription_payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `company_subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_payments_ibfk_3` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `subscription_plans`
DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'KES',
  `max_users` int(11) DEFAULT 5,
  `max_branches` int(11) DEFAULT 1,
  `max_products` int(11) DEFAULT 100,
  `max_storage_mb` int(11) DEFAULT 500,
  `features` longtext DEFAULT NULL CHECK (json_valid(`features`)),
  `is_active` tinyint(1) DEFAULT 1,
  `is_popular` tinyint(1) DEFAULT 0,
  `show_on_landing` tinyint(1) NOT NULL DEFAULT 1,
  `trial_days` int(11) DEFAULT 14,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_active` (`is_active`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `subscriptions`
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','trialing','past_due','cancelled','expired') DEFAULT 'active',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `current_period_start` timestamp NULL DEFAULT NULL,
  `current_period_end` timestamp NULL DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  `billing_cycle` enum('monthly','yearly') DEFAULT 'monthly',
  `amount` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'KES',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sub_plan` (`plan_id`),
  KEY `idx_sub_status` (`status`),
  KEY `idx_subscriptions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `suppliers`
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `address` text DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `branch_id` (`branch_id`),
  KEY `fk_supplier_created_by` (`created_by`),
  KEY `fk_supplier_updated_by` (`updated_by`),
  KEY `idx_suppliers_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_suppliers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_suppliers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `support_access_actions`
DROP TABLE IF EXISTS `support_access_actions`;
CREATE TABLE `support_access_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `access_log_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `action_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `system_analytics`
DROP TABLE IF EXISTS `system_analytics`;
CREATE TABLE `system_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_date` date DEFAULT NULL,
  `total_companies` int(11) DEFAULT 0,
  `active_companies` int(11) DEFAULT 0,
  `trial_companies` int(11) DEFAULT 0,
  `suspended_companies` int(11) DEFAULT 0,
  `total_mrr` decimal(15,2) DEFAULT 0.00,
  `total_transactions` bigint(20) DEFAULT 0,
  `active_users_count` int(11) DEFAULT 0,
  `api_calls_count` bigint(20) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `system_health_metrics`
DROP TABLE IF EXISTS `system_health_metrics`;
CREATE TABLE `system_health_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cpu_usage_percent` decimal(5,2) DEFAULT NULL,
  `memory_usage_percent` decimal(5,2) DEFAULT NULL,
  `db_connection_count` int(11) DEFAULT NULL,
  `error_rate_per_minute` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `system_settings`
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_label` varchar(200) DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `input_type` enum('text','textarea','password','number','email','url','checkbox','select','color') DEFAULT 'text',
  `setting_options` longtext DEFAULT NULL CHECK (json_valid(`setting_options`)),
  `is_required` tinyint(1) DEFAULT 0,
  `placeholder` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`),
  KEY `idx_setting_group` (`setting_group`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_system_settings_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `tax_rates`
DROP TABLE IF EXISTS `tax_rates`;
CREATE TABLE `tax_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_default` tinyint(1) DEFAULT 0,
  `type` enum('inclusive','exclusive') DEFAULT 'exclusive',
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tax_rates_active` (`active`),
  KEY `idx_tax_rates_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_tax_rates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `tax_rates`
INSERT INTO `tax_rates` (`id`, `name`, `rate`, `description`, `active`, `created_at`, `updated_at`, `is_default`, `type`, `tenant_id`) VALUES ('1', 'VAT', '16.00', 'Value Added Tax', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32', '0', 'exclusive', NULL);
INSERT INTO `tax_rates` (`id`, `name`, `rate`, `description`, `active`, `created_at`, `updated_at`, `is_default`, `type`, `tenant_id`) VALUES ('2', 'Zero Rated', '0.00', 'Zero-rated items', '1', '2026-04-30 19:05:32', '2026-04-30 19:05:32', '0', 'exclusive', NULL);


-- Table structure for `tenant_sessions`
DROP TABLE IF EXISTS `tenant_sessions`;
CREATE TABLE `tenant_sessions` (
  `id` varchar(100) NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `tenant_subscriptions`
DROP TABLE IF EXISTS `tenant_subscriptions`;
CREATE TABLE `tenant_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `plan_id` int(10) unsigned NOT NULL DEFAULT 1,
  `status` enum('active','trialing','past_due','cancelled','expired') DEFAULT 'trialing',
  `billing_cycle` enum('monthly','yearly') DEFAULT 'monthly',
  `amount` decimal(12,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'KES',
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_start` datetime DEFAULT current_timestamp(),
  `current_period_end` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_subscriptions_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_subscriptions_period_end` (`current_period_end`),
  CONSTRAINT `fk_tenant_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `tenant_subscriptions`
INSERT INTO `tenant_subscriptions` (`id`, `tenant_id`, `plan_id`, `status`, `billing_cycle`, `amount`, `currency`, `trial_ends_at`, `current_period_start`, `current_period_end`, `cancelled_at`, `cancel_reason`, `payment_method`, `payment_reference`, `metadata`, `created_at`, `updated_at`) VALUES ('1', '1', '4', 'active', 'monthly', '4999.00', 'KES', NULL, '2026-04-30 22:14:20', '2026-05-30 22:14:20', NULL, NULL, NULL, NULL, NULL, '2026-04-30 19:14:20', '2026-04-30 19:14:20');


-- Table structure for `tenants`
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `subdomain` varchar(100) NOT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `business_type` enum('retail','restaurant','pharmacy','salon','grocery','electronics','hardware','wholesale','service','other') DEFAULT 'retail',
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'Africa/Nairobi',
  `currency` varchar(10) DEFAULT 'KES',
  `language` varchar(10) DEFAULT 'en',
  `logo_url` varchar(500) DEFAULT NULL,
  `favicon_url` varchar(500) DEFAULT NULL,
  `theme_config` longtext DEFAULT NULL CHECK (json_valid(`theme_config`)),
  `receipt_config` longtext DEFAULT NULL CHECK (json_valid(`receipt_config`)),
  `tax_number` varchar(100) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT 16.00,
  `is_active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_suspended` tinyint(1) DEFAULT 0,
  `suspension_reason` varchar(255) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `settings` longtext DEFAULT NULL CHECK (json_valid(`settings`)),
  `features` longtext DEFAULT NULL CHECK (json_valid(`features`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `subdomain` (`subdomain`),
  KEY `idx_subdomain` (`subdomain`),
  KEY `idx_domain` (`domain`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_is_verified` (`is_verified`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_business_type` (`business_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_lookup` (`subdomain`,`is_active`,`is_verified`,`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master table for multi-tenant SaaS';

-- Dumping data for `tenants`
INSERT INTO `tenants` (`id`, `uuid`, `subdomain`, `domain`, `name`, `business_type`, `email`, `phone`, `address`, `city`, `country`, `timezone`, `currency`, `language`, `logo_url`, `favicon_url`, `theme_config`, `receipt_config`, `tax_number`, `tax_rate`, `is_active`, `is_verified`, `is_suspended`, `suspension_reason`, `verified_at`, `activated_at`, `expires_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`, `status`, `settings`, `features`) VALUES ('1', 'cf7718fa-44be-11f1-80fc-14abc50b6cdc', 'demo', NULL, 'JAKPOS', '', '', '', '', NULL, NULL, 'Africa/Nairobi', 'KES', 'en', 'uploads/logos/company_1/logo_1_1778615488.png', NULL, NULL, NULL, NULL, '11.00', '1', '1', '0', NULL, NULL, NULL, NULL, NULL, '2026-04-30 19:14:20', '2026-05-13 18:09:29', NULL, 'active', NULL, NULL);


-- Table structure for `terminal_sessions`
DROP TABLE IF EXISTS `terminal_sessions`;
CREATE TABLE `terminal_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `terminal_name` varchar(100) NOT NULL,
  `terminal_id` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `status` enum('active','idle','offline') DEFAULT 'active',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_terminal_sessions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_terminal_sessions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `tracking_history`
DROP TABLE IF EXISTS `tracking_history`;
CREATE TABLE `tracking_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shipment` (`shipment_id`),
  KEY `idx_tracking_history_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `two_factor_tokens`
DROP TABLE IF EXISTS `two_factor_tokens`;
CREATE TABLE `two_factor_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `method` enum('email','sms','totp') NOT NULL,
  `token` varchar(10) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_two_factor_tokens_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `ui_heatmap`
DROP TABLE IF EXISTS `ui_heatmap`;
CREATE TABLE `ui_heatmap` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `element_id` varchar(100) NOT NULL,
  `element_type` varchar(50) NOT NULL,
  `clicks` int(11) DEFAULT 0,
  `hover_duration` int(11) DEFAULT 0,
  `session_date` date NOT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_element` (`element_id`,`session_date`),
  KEY `idx_ui_heatmap_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_ui_heatmap_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `units_of_measure`
DROP TABLE IF EXISTS `units_of_measure`;
CREATE TABLE `units_of_measure` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_code` (`code`),
  KEY `idx_units_of_measure_tenant_id` (`tenant_id`),
  CONSTRAINT `chk_units_is_active` CHECK (`is_active` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `user_branches`
DROP TABLE IF EXISTS `user_branches`;
CREATE TABLE `user_branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_branch` (`user_id`,`branch_id`),
  KEY `idx_user_branches_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_user_branches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `user_efficiency`
DROP TABLE IF EXISTS `user_efficiency`;
CREATE TABLE `user_efficiency` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `transactions_per_hour` decimal(5,2) DEFAULT 0.00,
  `average_transaction_time_seconds` int(11) DEFAULT 0,
  `items_per_minute` decimal(5,2) DEFAULT 0.00,
  `upsell_success_rate` decimal(5,4) DEFAULT 0.0000,
  `error_rate` decimal(5,4) DEFAULT 0.0000,
  `customer_satisfaction_score` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_efficiency` (`transactions_per_hour`),
  KEY `idx_user_efficiency_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_user_efficiency_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `user_roles`
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  KEY `idx_ur_tenant` (`tenant_id`),
  KEY `idx_user_roles_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `user_tokens`
DROP TABLE IF EXISTS `user_tokens`;
CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'remember_me',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_user_tokens_tenant_id` (`tenant_id`),
  CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `pin_hash` varchar(255) DEFAULT NULL,
  `branch_id` int(11) DEFAULT 1,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_password_change` datetime DEFAULT NULL,
  `require_password_change` tinyint(1) DEFAULT 0,
  `api_token` varchar(255) DEFAULT NULL,
  `api_token_expires` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `customer_group` varchar(50) DEFAULT 'regular',
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `business_type` enum('retail','wholesale','restaurant','service','manufacturing','mixed') DEFAULT 'retail',
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_tenant_username` (`tenant_id`,`username`),
  UNIQUE KEY `uq_users_tenant_email` (`tenant_id`,`email`),
  KEY `fk_user_role` (`role_id`),
  KEY `idx_company_user` (`status`),
  KEY `idx_users_company_status` (`status`),
  KEY `idx_users_username_company` (`username`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_users_username` (`username`),
  KEY `idx_users_tenant_branch_status` (`tenant_id`,`branch_id`,`status`),
  KEY `idx_users_tenant_deleted` (`tenant_id`,`deleted_at`),
  KEY `idx_users_tenant_active` (`tenant_id`,`status`),
  KEY `idx_users_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `users`
INSERT INTO `users` (`id`, `name`, `email`, `username`, `role_id`, `password_hash`, `pin_hash`, `branch_id`, `status`, `created_at`, `phone`, `address`, `avatar`, `updated_at`, `last_login`, `password_reset_token`, `password_reset_expires`, `login_attempts`, `locked_until`, `two_factor_secret`, `two_factor_enabled`, `last_login_ip`, `last_password_change`, `require_password_change`, `api_token`, `api_token_expires`, `deleted_at`, `failed_login_attempts`, `customer_group`, `tenant_id`, `business_type`, `is_active`) VALUES ('1', 'Wycliffe Bunde', 'wickymacochiz80@gmail.com', 'Wycliffe Bunde', '1', '$2y$10$3UNqa0aUsxJ76RITzGOWBuco6vNbSyly1mrLkqnjT2qgD..oFKWvi', NULL, '1', '1', '2026-04-30 19:14:20', '+254759714022', '40111', NULL, '2026-05-01 15:34:45', NULL, NULL, NULL, '0', NULL, NULL, '0', NULL, NULL, '0', NULL, NULL, NULL, '0', 'regular', '1', 'retail', '1');
INSERT INTO `users` (`id`, `name`, `email`, `username`, `role_id`, `password_hash`, `pin_hash`, `branch_id`, `status`, `created_at`, `phone`, `address`, `avatar`, `updated_at`, `last_login`, `password_reset_token`, `password_reset_expires`, `login_attempts`, `locked_until`, `two_factor_secret`, `two_factor_enabled`, `last_login_ip`, `last_password_change`, `require_password_change`, `api_token`, `api_token_expires`, `deleted_at`, `failed_login_attempts`, `customer_group`, `tenant_id`, `business_type`, `is_active`) VALUES ('2', 'Store Admin', 'admin@demostore.com', 'admin', '1', '$2y$10$.wfz0ItTQjIykuGs.wmOBeTieWN5F6UCnuo7.ZET4XhT5aGicUuUu', NULL, '1', '1', '2026-05-02 04:31:47', NULL, NULL, NULL, NULL, '2026-05-14 05:59:06', NULL, NULL, '0', NULL, NULL, '0', '::1', NULL, '0', NULL, NULL, NULL, '0', 'regular', '1', 'retail', '1');


-- Table structure for `voucher_redemptions`
DROP TABLE IF EXISTS `voucher_redemptions`;
CREATE TABLE `voucher_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_voucher_redemptions_voucher` (`voucher_id`),
  KEY `fk_voucher_redemptions_sale` (`sale_id`),
  KEY `idx_voucher_redemptions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `vouchers`
DROP TABLE IF EXISTS `vouchers`;
CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL,
  `type` enum('fixed','percent') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `expires_at` date DEFAULT NULL,
  `active` tinyint(4) DEFAULT 1,
  `min_purchase` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_vouchers_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_vouchers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_vouchers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `waiting_list`
DROP TABLE IF EXISTS `waiting_list`;
CREATE TABLE `waiting_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `party_size` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `status` enum('waiting','seated','cancelled','no_show') NOT NULL DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seated_at` timestamp NULL DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wl_branch` (`branch_id`),
  KEY `idx_wl_status` (`status`),
  KEY `idx_waiting_list_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_waiting_list_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `warranties`
DROP TABLE IF EXISTS `warranties`;
CREATE TABLE `warranties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `sale_item_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `warranty_months` int(11) NOT NULL DEFAULT 12,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','claimed','voided') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_warranty_product` (`product_id`),
  KEY `idx_warranty_customer` (`customer_id`),
  KEY `idx_warranty_end` (`end_date`),
  KEY `idx_warranties_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_warranties_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for `webhook_logs`
DROP TABLE IF EXISTS `webhook_logs`;
CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `webhook_id` int(11) NOT NULL,
  `event` varchar(50) NOT NULL,
  `payload` longtext DEFAULT NULL CHECK (json_valid(`payload`)),
  `response_code` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook` (`webhook_id`),
  KEY `idx_event` (`event`),
  KEY `idx_webhook_logs_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for `webhooks`
DROP TABLE IF EXISTS `webhooks`;
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(500) NOT NULL,
  `events` longtext NOT NULL CHECK (json_valid(`events`)),
  `secret` varchar(64) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `failure_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`),
  KEY `idx_webhooks_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_webhooks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
