-- ========================================================
-- Complete Database Backup Migration
-- Database: jdh_pos
-- Tables: 353
-- Generated: 2026-06-24 05:23:46
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

-- ========================================================
-- Full Schema Backup for jdh_pos
-- Generated: 2026-06-24 05:23:13
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `abandoned_carts`;
CREATE TABLE `abandoned_carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `cart_id` bigint(20) unsigned NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `items_count` int(11) DEFAULT 0,
  `cart_value` decimal(10,2) DEFAULT 0.00,
  `abandoned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recovered_at` datetime DEFAULT NULL,
  `recovery_email_sent_at` datetime DEFAULT NULL,
  `recovery_email_opens` int(11) DEFAULT 0,
  `recovery_email_clicks` int(11) DEFAULT 0,
  `recovery_discount_code` varchar(50) DEFAULT NULL,
  `status` enum('abandoned','recovered','expired') DEFAULT 'abandoned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_abandoned_at` (`abandoned_at`),
  KEY `idx_email` (`tenant_id`,`customer_email`),
  KEY `cart_id` (`cart_id`),
  CONSTRAINT `abandoned_carts_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cart recovery analytics';

DROP TABLE IF EXISTS `accounting_periods`;
CREATE TABLE `accounting_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `period_type` enum('month','quarter','year','custom') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','closed','locked','future') DEFAULT 'open',
  `closed_by` bigint(20) unsigned DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_period_dates` (`tenant_id`,`start_date`,`end_date`),
  KEY `idx_period_dates` (`start_date`,`end_date`),
  CONSTRAINT `fk_ap_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Accounting period management';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
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
  KEY `idx_logs_created` (`created_at`),
  KEY `idx_logs_action` (`action`),
  CONSTRAINT `fk_activity_logs_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_api_keys_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_api_keys_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `api_rate_limit_violations`;
CREATE TABLE `api_rate_limit_violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `api_key_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `limit_type` varchar(50) NOT NULL,
  `requests_count` int(11) NOT NULL,
  `limit_value` int(11) NOT NULL,
  `blocked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`,`created_at`),
  KEY `idx_tenant` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `api_request_logs`;
CREATE TABLE `api_request_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `api_key_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `method` enum('GET','POST','PUT','PATCH','DELETE','HEAD','OPTIONS') NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `response_status` int(11) NOT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_size_bytes` int(11) DEFAULT NULL,
  `response_size_bytes` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_date` (`tenant_id`,`created_at`),
  KEY `idx_endpoint_status` (`endpoint`(255),`response_status`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed API request logging';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`),
  KEY `idx_api_tokens_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_api_tokens_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_api_usage_stats_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_api_usage_stats_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_appt_branch` (`branch_id`),
  KEY `idx_appt_date` (`appointment_date`),
  KEY `idx_appt_staff` (`staff_id`),
  KEY `idx_appt_customer` (`customer_id`),
  KEY `idx_appointments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_appointments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `attribute_groups`;
CREATE TABLE `attribute_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `business_type_id` bigint(20) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attribute_groups_tenant_id` (`tenant_id`),
  KEY `idx_attribute_groups_business_type` (`business_type_id`),
  CONSTRAINT `fk_attribute_groups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attribute_values`;
CREATE TABLE `attribute_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `value` varchar(255) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `color_hex` varchar(7) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_av_attribute_id` (`attribute_id`),
  KEY `idx_av_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_av_attribute` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_av_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `attributes`;
CREATE TABLE `attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('text','textarea','dropdown','multiselect','number','color','file','date','boolean') NOT NULL DEFAULT 'text',
  `group_id` bigint(20) unsigned DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_variant_forming` tinyint(1) NOT NULL DEFAULT 0,
  `is_filterable` tinyint(1) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attributes_tenant_id` (`tenant_id`),
  KEY `idx_attributes_group_id` (`group_id`),
  KEY `idx_attributes_code` (`tenant_id`,`code`),
  KEY `idx_attributes_deleted_at` (`deleted_at`),
  KEY `idx_attributes_created_by` (`created_by`),
  CONSTRAINT `fk_attributes_group` FOREIGN KEY (`group_id`) REFERENCES `attribute_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attributes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `background_jobs`;
CREATE TABLE `background_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `job_class` varchar(255) NOT NULL,
  `job_method` varchar(100) NOT NULL,
  `parameters` longtext DEFAULT NULL COMMENT 'JSON serialized parameters',
  `priority` tinyint(4) DEFAULT 5 COMMENT '1=highest, 10=lowest',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `delay_seconds` int(11) DEFAULT 0,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `error_trace` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status_priority` (`tenant_id`,`status`,`priority`,`scheduled_at`),
  KEY `idx_scheduled_at` (`scheduled_at`),
  KEY `idx_status_attempts` (`status`,`attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Background job queue for async processing';

DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `size` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `bank_reconciliations`;
CREATE TABLE `bank_reconciliations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `reconciliation_date` date NOT NULL,
  `statement_ending_balance` decimal(15,2) NOT NULL,
  `book_balance` decimal(15,2) NOT NULL,
  `difference_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_account_date` (`account_id`,`reconciliation_date`),
  KEY `idx_status` (`status`),
  KEY `fk_br_tenant` (`tenant_id`),
  CONSTRAINT `fk_br_account` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_br_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank reconciliation records';

DROP TABLE IF EXISTS `bill_of_materials`;
CREATE TABLE `bill_of_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'Finished product',
  `bom_name` varchar(100) NOT NULL,
  `bom_version` varchar(20) NOT NULL DEFAULT '1.0',
  `quantity_produced` decimal(10,3) NOT NULL DEFAULT 1.000 COMMENT 'Output quantity',
  `waste_percentage` decimal(5,2) DEFAULT 0.00,
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `overhead_cost` decimal(10,2) DEFAULT 0.00,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_version` (`product_id`,`bom_version`),
  KEY `idx_product_active` (`product_id`,`is_active`),
  KEY `fk_bom_tenant` (`tenant_id`),
  CONSTRAINT `fk_bom_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bom_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bill of Materials for manufactured products';

DROP TABLE IF EXISTS `bom_components`;
CREATE TABLE `bom_components` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `bom_id` bigint(20) unsigned NOT NULL,
  `component_product_id` int(11) NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `unit_cost` decimal(10,4) DEFAULT NULL COMMENT 'Snapshot cost at BOM creation',
  `waste_factor` decimal(5,2) DEFAULT 0.00,
  `is_critical` tinyint(1) DEFAULT 0,
  `substitute_product_ids` longtext DEFAULT NULL COMMENT 'JSON array of substitute product IDs',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bom` (`bom_id`),
  KEY `idx_component` (`component_product_id`),
  KEY `fk_bomc_tenant` (`tenant_id`),
  CONSTRAINT `fk_bomc_bom` FOREIGN KEY (`bom_id`) REFERENCES `bill_of_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bomc_component` FOREIGN KEY (`component_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bomc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Components required for BOM';

DROP TABLE IF EXISTS `branch_hours`;
CREATE TABLE `branch_hours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
  `opening_time` time NOT NULL,
  `closing_time` time NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_branch_day` (`branch_id`,`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `brands`;
CREATE TABLE `brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_brands_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_brands_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `campaign_messages`;
CREATE TABLE `campaign_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `recipient_id` int(10) unsigned DEFAULT NULL,
  `recipient` varchar(200) NOT NULL,
  `status` enum('pending','sent','delivered','failed','bounced') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cancellation_reasons`;
CREATE TABLE `cancellation_reasons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `primary_reason` enum('too_expensive','missing_features','too_complex','switching_provider','business_closed','not_using','support_issue','other') NOT NULL,
  `detailed_feedback` text DEFAULT NULL,
  `attempted_save` tinyint(1) DEFAULT 0,
  `save_offer_accepted` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reason` (`primary_reason`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `cart_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL COMMENT 'Future: product variants',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `weight_kg` decimal(10,3) DEFAULT 0.000,
  `image_url` varchar(500) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL COMMENT 'Snapshot at add time',
  `product_sku` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cart` (`cart_id`),
  KEY `idx_product` (`tenant_id`,`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Items in shopping carts';

DROP TABLE IF EXISTS `carts`;
CREATE TABLE `carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `session_id` varchar(255) NOT NULL COMMENT 'PHP session or guest token',
  `customer_id` int(11) DEFAULT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `shipping_cost` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'KES',
  `shipping_address_id` bigint(20) unsigned DEFAULT NULL,
  `billing_address_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `abandoned_notified_at` datetime DEFAULT NULL,
  `converted_to_order_id` bigint(20) unsigned DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session` (`tenant_id`,`session_id`),
  KEY `idx_customer` (`tenant_id`,`customer_id`),
  KEY `idx_abandoned` (`tenant_id`,`abandoned_notified_at`,`last_activity`),
  KEY `idx_converted` (`converted_to_order_id`),
  KEY `idx_carts_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Active and abandoned shopping carts';

DROP TABLE IF EXISTS `cash_drawers`;
CREATE TABLE `cash_drawers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `opening_amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `current_amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `status` enum('open','closed','locked') NOT NULL DEFAULT 'closed',
  `opened_by` int(10) unsigned DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cash_movements`;
CREATE TABLE `cash_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `cash_drawer_id` int(10) unsigned NOT NULL,
  `movement_type` enum('cash_in','cash_out','float_add','float_remove','transfer') NOT NULL,
  `amount` decimal(15,4) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `performed_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_drawer` (`tenant_id`,`cash_drawer_id`),
  KEY `idx_type` (`movement_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `chart_of_accounts`;
CREATE TABLE `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('asset','liability','equity','income','expense','contra_asset','contra_liability') NOT NULL,
  `normal_balance` enum('debit','credit') NOT NULL,
  `parent_account_id` bigint(20) unsigned DEFAULT NULL,
  `is_bank_account` tinyint(1) DEFAULT 0,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_system` tinyint(1) DEFAULT 0,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `opening_balance_date` date DEFAULT NULL,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_account_code` (`tenant_id`,`account_code`),
  KEY `idx_account_type` (`account_type`),
  KEY `idx_parent` (`parent_account_id`),
  CONSTRAINT `fk_coa_parent` FOREIGN KEY (`parent_account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_coa_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Double-entry chart of accounts';

DROP TABLE IF EXISTS `company_permissions`;
CREATE TABLE `company_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `is_allowed` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_permission` (`company_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_subscriptions_status` (`status`),
  KEY `idx_company_subscriptions_tenant_id` (`tenant_id`),
  KEY `idx_csub_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_company_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `company_verticals`;
CREATE TABLE `company_verticals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `conflict_log`;
CREATE TABLE `conflict_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `local_data` longtext DEFAULT NULL,
  `server_data` longtext DEFAULT NULL,
  `resolution` enum('server_wins','client_wins','manual') DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`,`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `consignment_agreements`;
CREATE TABLE `consignment_agreements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `agreement_number` varchar(50) NOT NULL,
  `agreement_date` date NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `commission_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `settlement_terms_days` int(11) DEFAULT 30,
  `min_monthly_sales` decimal(12,2) DEFAULT NULL,
  `exclusive_rights` tinyint(1) DEFAULT 0,
  `status` enum('draft','active','expired','terminated') DEFAULT 'draft',
  `contract_file` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_agreement` (`tenant_id`,`agreement_number`),
  KEY `idx_supplier_status` (`supplier_id`,`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  CONSTRAINT `fk_ca_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ca_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Consignment/vendor agreements';

DROP TABLE IF EXISTS `consignment_inventory`;
CREATE TABLE `consignment_inventory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` int(11) NOT NULL,
  `agreement_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `quantity_sold` int(11) DEFAULT 0,
  `quantity_returned` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_agreement_product` (`agreement_id`,`product_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_product` (`product_id`),
  KEY `fk_ci_tenant` (`tenant_id`),
  CONSTRAINT `fk_ci_agreement` FOREIGN KEY (`agreement_id`) REFERENCES `consignment_agreements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ci_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory items on consignment';

DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact` varchar(120) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_contacts_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_contacts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_active` (`is_active`),
  KEY `idx_priority` (`priority`),
  KEY `idx_context_rules_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_context_rules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `coupon_usage`;
CREATE TABLE `coupon_usage` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `coupon_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_coupon_order` (`coupon_id`,`order_id`),
  KEY `idx_customer` (`tenant_id`,`customer_id`),
  CONSTRAINT `coupon_usage_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Track coupon redemptions';

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','fixed_amount','free_shipping') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order_value` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL COMMENT 'Total allowed uses',
  `usage_limit_per_customer` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `applicable_products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Product IDs or NULL = all' CHECK (json_valid(`applicable_products`)),
  `applicable_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Category IDs or NULL = all' CHECK (json_valid(`applicable_categories`)),
  `excluded_products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`excluded_products`)),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_public` tinyint(1) DEFAULT 1 COMMENT 'Shown on storefront?',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_code` (`tenant_id`,`code`),
  KEY `idx_active_dates` (`tenant_id`,`is_active`,`start_date`,`end_date`),
  KEY `idx_usage` (`usage_count`,`usage_limit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Discount coupon codes';

DROP TABLE IF EXISTS `credit_notes`;
CREATE TABLE `credit_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `number` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `reason` text DEFAULT NULL,
  `status` enum('draft','issued','voided') DEFAULT 'draft',
  `applied_to_invoice_id` int(10) unsigned DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `number` (`number`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `credit_payments`;
CREATE TABLE `credit_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `credit_sale_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_credit` (`credit_sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `credit_sales`;
CREATE TABLE `credit_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','approved','partial','paid','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `credits`;
CREATE TABLE `credits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `balance` decimal(12,2) NOT NULL,
  `type` enum('top_up','refund','promo','write_off','transfer') NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_balance` (`tenant_id`,`created_at`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_crm_deals_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_crm_deals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  `tenant_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_crm_leads_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_crm_leads_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `currencies`;
CREATE TABLE `currencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(3) NOT NULL,
  `name` varchar(80) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `decimal_places` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `custom_reports`;
CREATE TABLE `custom_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `query_config` longtext DEFAULT NULL,
  `columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`columns`)),
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `created_by` int(10) unsigned DEFAULT NULL,
  `is_shared` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_accounts`;
CREATE TABLE `customer_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email_tenant` (`tenant_id`,`email`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE `customer_addresses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) NOT NULL,
  `type` enum('shipping','billing') DEFAULT 'shipping',
  `label` varchar(50) DEFAULT 'Home' COMMENT 'Home, Office, Other',
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `street_address` varchar(255) NOT NULL,
  `apartment` varchar(50) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Kenya',
  `is_default` tinyint(1) DEFAULT 0,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_customer` (`tenant_id`,`customer_id`),
  KEY `idx_type` (`tenant_id`,`customer_id`,`type`),
  KEY `idx_default` (`tenant_id`,`customer_id`,`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer shipping and billing addresses';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_customer` (`customer_id`),
  KEY `idx_behavior_type` (`behavior_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_customer_behavior_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_behavior_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_customer` (`customer_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  KEY `idx_customer_credit_transactions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_credit_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `customer_credits`;
CREATE TABLE `customer_credits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `sale_id` int(11) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `type` enum('credit','payment') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cc_customer` (`customer_id`),
  KEY `idx_customer_credits_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_credits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_deleted` (`deleted_at`),
  KEY `idx_customer_groups_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_groups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_labels`;
CREATE TABLE `customer_labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `label_name` varchar(50) NOT NULL,
  `label_color` varchar(7) NOT NULL DEFAULT '#3498db',
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer_label` (`tenant_id`,`customer_id`,`label_name`),
  KEY `idx_label` (`label_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_loyalty`;
CREATE TABLE `customer_loyalty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `points_balance` int(11) NOT NULL DEFAULT 0,
  `lifetime_points` int(11) NOT NULL DEFAULT 0,
  `tier_id` int(10) unsigned DEFAULT NULL,
  `tier_updated_at` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer` (`tenant_id`,`customer_id`),
  KEY `idx_points` (`points_balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_payment_methods`;
CREATE TABLE `customer_payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) NOT NULL,
  `method_type` enum('card','bank_account','mpesa','paypal','stripe','flutterwave') NOT NULL,
  `provider_token` varchar(255) NOT NULL COMMENT 'Payment gateway token',
  `last4` varchar(4) DEFAULT NULL,
  `card_brand` varchar(50) DEFAULT NULL,
  `card_expiry_month` tinyint(4) DEFAULT NULL,
  `card_expiry_year` smallint(6) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `billing_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_default` (`customer_id`,`is_default`),
  KEY `idx_provider_token` (`provider_token`),
  KEY `fk_cpm_tenant` (`tenant_id`),
  CONSTRAINT `fk_cpm_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cpm_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stored payment methods for customer subscriptions';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_customer` (`customer_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status` (`status`),
  KEY `idx_overdue` (`status`,`due_date`),
  KEY `idx_customer_payment_schedules_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_payment_schedules_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `customer_saved_payment_methods`;
CREATE TABLE `customer_saved_payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) NOT NULL,
  `method_type` enum('card','bank_account','mpesa','paypal','stripe','flutterwave') NOT NULL,
  `provider_token` varchar(255) NOT NULL COMMENT 'Payment gateway token',
  `last4` varchar(4) DEFAULT NULL,
  `card_brand` varchar(50) DEFAULT NULL,
  `card_expiry_month` tinyint(4) DEFAULT NULL,
  `card_expiry_year` smallint(6) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `billing_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_default` (`customer_id`,`is_default`),
  KEY `idx_provider_token` (`provider_token`),
  KEY `fk_cspm_tenant` (`tenant_id`),
  CONSTRAINT `fk_cspm_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cspm_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stored payment methods for customer subscriptions';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`segment_type`),
  KEY `idx_customer_segments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_segments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_subscription_plans`;
CREATE TABLE `customer_subscription_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `plan_type` enum('product','service','membership','bundle') DEFAULT 'service',
  `billing_cycles` longtext DEFAULT NULL COMMENT 'JSON array of available cycles with prices',
  `features` longtext DEFAULT NULL COMMENT 'JSON array of included features',
  `trial_days` int(11) DEFAULT 0,
  `setup_fee` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_subscriptions`;
CREATE TABLE `customer_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) NOT NULL,
  `subscription_plan_id` bigint(20) unsigned NOT NULL,
  `payment_method_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','trialing','past_due','cancelled','expired','paused') DEFAULT 'active',
  `billing_cycle` enum('daily','weekly','monthly','quarterly','yearly') DEFAULT 'monthly',
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `current_period_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `current_period_end` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `pause_resumes_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `auto_renew` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_status` (`customer_id`,`status`),
  KEY `idx_period_end` (`current_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
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
  KEY `idx_customers_postal_code` (`postal_code`),
  KEY `idx_customers_tenant_total_spent` (`tenant_id`,`total_spent`),
  KEY `idx_customers_tenant_loyalty_points` (`tenant_id`,`loyalty_points`),
  KEY `idx_tenant_customer_name` (`tenant_id`,`name`),
  KEY `idx_tenant_customer_phone` (`tenant_id`,`phone`),
  KEY `idx_tenant_customer_email` (`tenant_id`,`email`),
  FULLTEXT KEY `ft_customers_name_email_phone` (`name`,`email`,`phone`),
  CONSTRAINT `fk_customers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `data_export_logs`;
CREATE TABLE `data_export_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `export_type` enum('customer_data','sales_data','accounting_data','all_data') NOT NULL,
  `data_range_start` date DEFAULT NULL,
  `data_range_end` date DEFAULT NULL,
  `export_format` enum('csv','json','pdf','xml') DEFAULT 'json',
  `file_path` varchar(500) NOT NULL,
  `file_size_bytes` bigint(20) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `request_ip` varchar(45) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','expired','deleted') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_customer` (`tenant_id`,`customer_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_del_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GDPR data export request tracking';

DROP TABLE IF EXISTS `deferred_revenue`;
CREATE TABLE `deferred_revenue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `recognized_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(12,2) NOT NULL,
  `recognition_status` enum('pending','partial','recognized','refunded') DEFAULT 'pending',
  `last_recognized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recognition` (`recognition_status`,`period_end`),
  KEY `idx_subscription` (`subscription_id`,`period_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `delivery_areas`;
CREATE TABLE `delivery_areas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimum_order` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estimated_time` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `delivery_riders`;
CREATE TABLE `delivery_riders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `rider_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `alternative_phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `vehicle_type` enum('bicycle','motorcycle','car','van','truck') DEFAULT 'motorcycle',
  `vehicle_registration` varchar(50) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `commission_type` enum('percentage','fixed','per_delivery') DEFAULT 'per_delivery',
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `base_pay` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive','on_leave','suspended','busy','available') DEFAULT 'available',
  `current_location_lat` decimal(10,8) DEFAULT NULL,
  `current_location_lng` decimal(11,8) DEFAULT NULL,
  `last_location_update` timestamp NULL DEFAULT NULL,
  `total_deliveries` int(11) DEFAULT 0,
  `total_earnings` decimal(12,2) DEFAULT 0.00,
  `rating` decimal(3,2) DEFAULT 5.00,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_phone` (`tenant_id`,`phone`),
  UNIQUE KEY `uk_tenant_email` (`tenant_id`,`email`),
  KEY `idx_status_location` (`status`,`current_location_lat`,`current_location_lng`),
  KEY `idx_rating` (`rating`),
  CONSTRAINT `fk_dr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delivery riders/agents';

DROP TABLE IF EXISTS `delivery_routes`;
CREATE TABLE `delivery_routes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `driver_id` int(10) unsigned DEFAULT NULL,
  `vehicle` varchar(100) DEFAULT NULL,
  `status` enum('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
  `planned_stops` int(11) NOT NULL DEFAULT 0,
  `completed_stops` int(11) NOT NULL DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_driver` (`driver_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `delivery_tracking`;
CREATE TABLE `delivery_tracking` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `sale_id` int(11) NOT NULL,
  `rider_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('pending','assigned','picked_up','in_transit','arrived','delivered','failed','returned','cancelled') NOT NULL DEFAULT 'pending',
  `assigned_at` timestamp NULL DEFAULT NULL,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `estimated_delivery_time` timestamp NULL DEFAULT NULL,
  `actual_delivery_time` timestamp NULL DEFAULT NULL,
  `current_location_lat` decimal(10,8) DEFAULT NULL,
  `current_location_lng` decimal(11,8) DEFAULT NULL,
  `distance_traveled_km` decimal(10,2) DEFAULT 0.00,
  `tracking_url` varchar(500) DEFAULT NULL,
  `customer_signature` varchar(500) DEFAULT NULL,
  `delivery_photo` varchar(500) DEFAULT NULL,
  `customer_otp` varchar(10) DEFAULT NULL,
  `otp_verified_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_rider_status` (`rider_id`,`status`),
  KEY `idx_estimated_time` (`estimated_delivery_time`),
  KEY `fk_dt_tenant` (`tenant_id`),
  CONSTRAINT `fk_dt_rider` FOREIGN KEY (`rider_id`) REFERENCES `delivery_riders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dt_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Real-time delivery tracking';

DROP TABLE IF EXISTS `delivery_zones`;
CREATE TABLE `delivery_zones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimum_order` decimal(10,2) DEFAULT 0.00,
  `free_delivery_threshold` decimal(10,2) DEFAULT NULL,
  `estimated_time_minutes` int(11) DEFAULT 30,
  `polygon_coordinates` longtext DEFAULT NULL COMMENT 'GeoJSON polygon for zone boundaries',
  `center_lat` decimal(10,8) DEFAULT NULL,
  `center_lng` decimal(11,8) DEFAULT NULL,
  `radius_km` decimal(8,2) DEFAULT NULL COMMENT 'If circle zone',
  `zone_type` enum('polygon','circle','postal_code') DEFAULT 'polygon',
  `postal_codes` longtext DEFAULT NULL COMMENT 'JSON array of postal codes',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_dz_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delivery zones with geofencing';

DROP TABLE IF EXISTS `discount_usage`;
CREATE TABLE `discount_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `discount_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `sale_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_du_discount` (`discount_id`),
  KEY `idx_du_customer` (`customer_id`),
  KEY `idx_du_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(50) DEFAULT NULL COMMENT 'Coupon/promo code',
  `max_per_customer` int(11) DEFAULT NULL COMMENT 'Max uses per customer',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_discounts_code` (`code`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_discounts_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_discounts_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_discounts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `driver_locations`;
CREATE TABLE `driver_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `rider_id` int(10) unsigned NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy` float DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rider` (`rider_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `dunning_campaigns`;
CREATE TABLE `dunning_campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `stage` int(11) NOT NULL DEFAULT 1,
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `sms_sent_at` timestamp NULL DEFAULT NULL,
  `email_template_used` varchar(50) DEFAULT NULL,
  `action_taken` enum('none','email_sent','sms_sent','feature_restricted','suspended') DEFAULT 'none',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_type` enum('payment_received','cancelled','admin_override') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stage` (`stage`,`created_at`),
  KEY `idx_unresolved` (`resolved_at`,`stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_active` (`is_active`),
  KEY `idx_product` (`product_id`),
  KEY `idx_dynamic_pricing_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_dynamic_pricing_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `template_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('queued','sent','delivered','bounced','failed','opened','clicked') DEFAULT 'queued',
  `provider` varchar(50) DEFAULT 'sendgrid',
  `provider_message_id` varchar(100) DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`created_at`),
  KEY `idx_tenant` (`tenant_id`,`created_at`),
  KEY `idx_recipient` (`recipient_email`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_text` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `category` enum('trial','billing','subscription','usage','security','marketing') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category` (`category`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `employee_performance_kpis`;
CREATE TABLE `employee_performance_kpis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `kpi_date` date NOT NULL,
  `sales_amount` decimal(12,2) DEFAULT 0.00,
  `transactions_count` int(11) DEFAULT 0,
  `average_transaction_value` decimal(10,2) DEFAULT 0.00,
  `items_sold_count` int(11) DEFAULT 0,
  `returns_processed` int(11) DEFAULT 0,
  `return_amount` decimal(12,2) DEFAULT 0.00,
  `discount_given` decimal(12,2) DEFAULT 0.00,
  `customer_satisfaction_score` decimal(3,2) DEFAULT NULL,
  `upsell_amount` decimal(12,2) DEFAULT 0.00,
  `upsell_count` int(11) DEFAULT 0,
  `target_sales_amount` decimal(12,2) DEFAULT NULL,
  `target_achievement_percent` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_user_date` (`tenant_id`,`user_id`,`kpi_date`),
  KEY `idx_kpi_date` (`kpi_date`),
  KEY `fk_epk_user` (`user_id`),
  CONSTRAINT `fk_epk_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_epk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee daily performance KPIs';

DROP TABLE IF EXISTS `exchange_rates`;
CREATE TABLE `exchange_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `from_currency` varchar(3) NOT NULL,
  `to_currency` varchar(3) NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `source` varchar(50) DEFAULT 'manual',
  `effective_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_pair` (`tenant_id`,`from_currency`,`to_currency`),
  KEY `idx_effective` (`effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_expense_categories_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_expense_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_failed_at` (`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `feature_usage`;
CREATE TABLE `feature_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feature_name` varchar(100) NOT NULL,
  `usage_count` int(11) DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_feature_usage_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_feature_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `featured_products`;
CREATE TABLE `featured_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `section` enum('hero','new_arrivals','best_sellers','trending','deals','staff_picks') DEFAULT 'deals',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_product_section` (`tenant_id`,`product_id`,`section`),
  KEY `idx_section` (`tenant_id`,`section`,`is_active`,`display_order`),
  KEY `idx_dates` (`tenant_id`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Curated product placements per section';

DROP TABLE IF EXISTS `features`;
CREATE TABLE `features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `features_catalog`;
CREATE TABLE `features_catalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `gift_cards`;
CREATE TABLE `gift_cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `card_number` varchar(50) NOT NULL,
  `pin` varchar(20) DEFAULT NULL,
  `initial_value` decimal(15,4) NOT NULL,
  `balance` decimal(15,4) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('active','redeemed','expired','cancelled') NOT NULL DEFAULT 'active',
  `recipient_name` varchar(100) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `purchased_by` int(10) unsigned DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `redeemed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_card_number` (`tenant_id`,`card_number`),
  KEY `idx_status` (`status`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `held_sales`;
CREATE TABLE `held_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `business_type_id` int(11) DEFAULT NULL,
  `sale_data` text NOT NULL,
  `table_number` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `item_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_held_sales_tenant_id` (`tenant_id`),
  KEY `idx_business_type_id` (`business_type_id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_held_sales_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_department` (`department`),
  KEY `idx_hr_employees_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_hr_employees_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_status` (`status`),
  KEY `idx_hr_leave_requests_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_hr_leave_requests_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
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
  KEY `idx_inventory_min_stock` (`minimum_stock`),
  KEY `idx_inventory_max_stock` (`maximum_stock`),
  KEY `idx_tenant_branch_product` (`tenant_id`,`branch_id`,`product_id`),
  KEY `idx_tenant_low_stock` (`tenant_id`,`stock`,`reorder_level`),
  KEY `idx_inventory_tenant_stock` (`tenant_id`,`stock`),
  KEY `idx_inventory_reorder` (`reorder_level`,`stock`),
  KEY `idx_branch_product` (`branch_id`,`product_id`),
  KEY `idx_product_branch` (`product_id`,`branch_id`),
  CONSTRAINT `fk_inventory_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `inventory_adjustments`;
CREATE TABLE `inventory_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `adjustment_type` enum('addition','reduction','damage','expiry','correction') NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `unit_cost` decimal(15,4) DEFAULT NULL,
  `reason` text NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `performed_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_type` (`adjustment_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `inventory_counts`;
CREATE TABLE `inventory_counts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `count_name` varchar(100) NOT NULL,
  `status` enum('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
  `counted_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `branch_id` (`branch_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_inventory_logs_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `inventory_reservations`;
CREATE TABLE `inventory_reservations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `cart_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_cart` (`cart_id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Temporary stock holds for online carts';

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `unit_price` decimal(15,4) NOT NULL,
  `discount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `line_total` decimal(15,4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_line_items`;
CREATE TABLE `invoice_line_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `type` enum('subscription','usage','proration','add_on','tax','discount','credit') NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,4) DEFAULT 1.0000,
  `unit_price` decimal(12,4) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `invoice_sequences`;
CREATE TABLE `invoice_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `last_number` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_sequences_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_invoice_sequences_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `subscription_id` (`subscription_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_invoices_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `company_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `job_queue`;
CREATE TABLE `job_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `job_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_available` (`status`,`available_at`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `journal_entries`;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `journal_number` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `reference_type` enum('sale','purchase','payment','receipt','return','adjustment','transfer','salary','expense','depreciation','closing') DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `total_debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_reversed` tinyint(1) DEFAULT 0,
  `reversed_entry_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','posted','approved','reversed','cancelled') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_journal_number` (`tenant_id`,`journal_number`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  KEY `idx_status` (`status`),
  KEY `fk_je_reversed` (`reversed_entry_id`),
  CONSTRAINT `fk_je_reversed` FOREIGN KEY (`reversed_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_je_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal entries for accounting';

DROP TABLE IF EXISTS `journal_entry_lines`;
CREATE TABLE `journal_entry_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `journal_entry_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `debit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `memo` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_journal_entry` (`journal_entry_id`),
  KEY `idx_account` (`account_id`),
  KEY `fk_jel_tenant` (`tenant_id`),
  CONSTRAINT `fk_jel_account` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jel_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jel_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal entry line items';

DROP TABLE IF EXISTS `kitchen_order_items`;
CREATE TABLE `kitchen_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `kitchen_order_id` int(11) NOT NULL,
  `sale_item_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `modifiers` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','preparing','ready','served','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_number` tinyint(4) DEFAULT 1 COMMENT '1=appetizer, 2=main, 3=dessert',
  `station_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Assigned kitchen station',
  `prep_time_seconds` int(11) DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `expiry_time` timestamp NULL DEFAULT NULL,
  `modifiers_json` longtext DEFAULT NULL COMMENT 'JSON of selected modifiers',
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `is_rush` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_koi_order` (`kitchen_order_id`),
  KEY `idx_kitchen_order_items_tenant_id` (`tenant_id`),
  KEY `fk_koi_station` (`station_id`),
  CONSTRAINT `fk_kitchen_order_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_koi_station` FOREIGN KEY (`station_id`) REFERENCES `kitchen_stations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ko_branch` (`branch_id`),
  KEY `idx_ko_sale` (`sale_id`),
  KEY `idx_ko_status` (`status`),
  KEY `idx_kitchen_orders_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_kitchen_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `kitchen_stations`;
CREATE TABLE `kitchen_stations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` int(11) NOT NULL,
  `station_name` varchar(100) NOT NULL COMMENT 'e.g., Grill, Pizza, Dessert',
  `station_code` varchar(50) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `printer_ip` varchar(45) DEFAULT NULL,
  `printer_port` int(11) DEFAULT 9100,
  `printer_type` enum('escpos','network','usb','bluetooth') DEFAULT 'network',
  `auto_print` tinyint(1) DEFAULT 1,
  `sound_alert` tinyint(1) DEFAULT 1,
  `order_expiry_minutes` int(11) DEFAULT 30,
  `color_code` varchar(7) DEFAULT '#3B82F6',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_branch_station` (`tenant_id`,`branch_id`,`station_code`),
  KEY `idx_branch_active` (`branch_id`,`is_active`),
  CONSTRAINT `fk_ks_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kitchen display stations and printers';

DROP TABLE IF EXISTS `known_devices`;
CREATE TABLE `known_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `is_trusted` tinyint(1) DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_device` (`user_id`,`device_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_last_used` (`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `known_locations`;
CREATE TABLE `known_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `city` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_trusted` tinyint(1) DEFAULT 1,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_location` (`user_id`,`city`,`country`),
  KEY `idx_user` (`user_id`),
  KEY `idx_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `label_print_logs`;
CREATE TABLE `label_print_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` int(10) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`product_data`)),
  `label_size` varchar(20) NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `print_method` enum('pdf','browser','escpos') NOT NULL,
  `printed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_printed_at` (`printed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=137 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `label_templates`;
CREATE TABLE `label_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `type` enum('product','shelf','barcode','receipt','custom') NOT NULL DEFAULT 'product',
  `width_mm` decimal(6,2) DEFAULT NULL,
  `height_mm` decimal(6,2) DEFAULT NULL,
  `template` longtext DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `failure_reason` varchar(255) DEFAULT NULL,
  `blocked_by_geo` tinyint(1) DEFAULT 0,
  `blocked_by_rate_limit` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`created_at`),
  KEY `idx_username_time` (`username`,`created_at`),
  KEY `idx_success` (`success`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed login attempt logging with geolocation';

DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_login_history_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_login_history_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

DROP TABLE IF EXISTS `loyalty_points_log`;
CREATE TABLE `loyalty_points_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `points_change` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_company_date` (`created_at`),
  KEY `idx_loyalty_points_log_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_loyalty_points_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_reward_id` (`reward_id`),
  KEY `idx_status` (`status`),
  KEY `idx_loyalty_redemptions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_loyalty_redemptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_points_required` (`points_required`),
  KEY `idx_loyalty_rewards_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_loyalty_rewards_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loyalty_rules`;
CREATE TABLE `loyalty_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('earn_points','redeem_points','tier_upgrade','bonus','referral') NOT NULL,
  `condition_type` enum('purchase_amount','purchase_qty','product','category','customer_group','visit_count') NOT NULL,
  `condition_value` varchar(255) DEFAULT NULL,
  `points_award` int(11) NOT NULL DEFAULT 0,
  `points_multiplier` decimal(5,2) NOT NULL DEFAULT 1.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type` (`tenant_id`,`rule_type`),
  KEY `idx_active_dates` (`is_active`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loyalty_tier_rules`;
CREATE TABLE `loyalty_tier_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `tier_name` varchar(50) NOT NULL,
  `tier_level` tinyint(4) NOT NULL DEFAULT 1,
  `min_spent_annually` decimal(12,2) DEFAULT 0.00,
  `min_points_earned` int(11) DEFAULT 0,
  `points_multiplier` decimal(5,2) DEFAULT 1.00,
  `discount_rate` decimal(5,2) DEFAULT 0.00,
  `free_shipping` tinyint(1) DEFAULT 0,
  `birthday_reward_points` int(11) DEFAULT NULL,
  `priority_support` tinyint(1) DEFAULT 0,
  `early_access` tinyint(1) DEFAULT 0,
  `color_hex` varchar(7) DEFAULT '#6B7280',
  `badge_icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_tier` (`tenant_id`,`tier_name`),
  KEY `idx_tier_level` (`tier_level`),
  CONSTRAINT `fk_ltr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Loyalty tier definitions and benefits';

DROP TABLE IF EXISTS `loyalty_tiers`;
CREATE TABLE `loyalty_tiers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `min_points` int(10) unsigned NOT NULL DEFAULT 0,
  `max_points` int(10) unsigned DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `color` varchar(7) DEFAULT '#3498db',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_points` (`min_points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loyalty_transactions`;
CREATE TABLE `loyalty_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `type` enum('earn','redeem','expire','adjust','bonus') NOT NULL,
  `points` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_customer` (`tenant_id`,`customer_id`),
  KEY `idx_sale` (`sale_id`),
  KEY `idx_loyalty_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `marketing_campaigns`;
CREATE TABLE `marketing_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `type` enum('sms','email','push','whatsapp') NOT NULL DEFAULT 'sms',
  `status` enum('draft','scheduled','running','completed','cancelled') NOT NULL DEFAULT 'draft',
  `target_segment` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0,
  `open_count` int(10) unsigned NOT NULL DEFAULT 0,
  `click_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mfa_methods`;
CREATE TABLE `mfa_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `method_type` varchar(50) NOT NULL COMMENT 'totp,sms,email,webauthn,backup_codes',
  `secret` varchar(255) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_method` (`method_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  `executed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_migration` (`migration`),
  KEY `idx_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mobile_devices`;
CREATE TABLE `mobile_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `mpesa_transactions`;
CREATE TABLE `mpesa_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `cart_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `merchant_request_id` varchar(100) DEFAULT NULL,
  `checkout_request_id` varchar(100) DEFAULT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `transaction_date` datetime DEFAULT NULL,
  `result_code` varchar(10) DEFAULT NULL,
  `result_description` varchar(255) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled','refunded') DEFAULT 'pending',
  `callback_received_at` datetime DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `raw_callback` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_callback`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_merchant` (`merchant_request_id`),
  KEY `idx_checkout` (`checkout_request_id`),
  KEY `idx_receipt` (`mpesa_receipt_number`),
  KEY `idx_order` (`order_id`),
  KEY `idx_phone` (`tenant_id`,`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='M-Pesa STK Push transaction records';

DROP TABLE IF EXISTS `notification_jobs`;
CREATE TABLE `notification_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `payload` longtext DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notification_queue`;
CREATE TABLE `notification_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'email, sms, etc.',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Notification data as JSON' CHECK (json_valid(`payload`)),
  `status` enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 3,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notification_queue_tenant` (`tenant_id`),
  KEY `idx_notification_queue_status` (`status`),
  KEY `idx_notification_queue_created` (`created_at`),
  KEY `idx_notification_queue_status_attempts` (`status`,`attempts`),
  KEY `idx_notification_queue_pending` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Queue for asynchronous notification processing';

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_notifications_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_notifications_tenant_id` (`tenant_id`),
  KEY `idx_notifications_created` (`created_at`),
  CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `offline_customers`;
CREATE TABLE `offline_customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `data` longtext NOT NULL,
  `synced_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `offline_products`;
CREATE TABLE `offline_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `data` longtext NOT NULL,
  `synced_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `offline_settings`;
CREATE TABLE `offline_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `settings` longtext NOT NULL,
  `synced_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_branch` (`tenant_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`status`),
  KEY `idx_offline_id` (`offline_id`),
  KEY `idx_terminal` (`terminal_id`),
  KEY `idx_offline_sync_queue_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_offline_sync_queue_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `offline_transactions`;
CREATE TABLE `offline_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `local_id` varchar(100) NOT NULL,
  `payload` longtext NOT NULL,
  `status` enum('pending','synced','conflict','failed') NOT NULL DEFAULT 'pending',
  `synced_at` datetime DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `online_customer_accounts`;
CREATE TABLE `online_customer_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_email` (`tenant_id`,`email`),
  KEY `idx_customer` (`tenant_id`,`customer_id`),
  KEY `idx_phone` (`tenant_id`,`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer login accounts for online shop';

DROP TABLE IF EXISTS `online_order_items`;
CREATE TABLE `online_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `qty` int(10) unsigned NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `online_orders`;
CREATE TABLE `online_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `uuid` varchar(32) DEFAULT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(15,4) DEFAULT 0.0000,
  `shipping_amount` decimal(15,4) DEFAULT 0.0000,
  `discount` decimal(12,2) DEFAULT 0.00,
  `customer_id` int(11) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `payment_status` varchar(30) DEFAULT 'pending',
  `coupon_code` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `items_json` longtext DEFAULT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `placed_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_created` (`created_at`),
  KEY `fk_online_orders_customer` (`customer_id`),
  KEY `idx_online_orders_status` (`status`),
  KEY `idx_online_orders_created` (`created_at`),
  KEY `idx_uuid` (`uuid`),
  CONSTRAINT `fk_online_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `order_status_history`;
CREATE TABLE `order_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_by_type` enum('system','staff','customer') DEFAULT 'system',
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_status` (`status`),
  KEY `idx_approval_code` (`approval_code`),
  KEY `idx_requester` (`requester_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_override_requests_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_override_requests_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `password_history`;
CREATE TABLE `password_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `changed_by` enum('user','admin','system') DEFAULT 'user',
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_password` (`user_id`,`changed_at`),
  KEY `fk_ph_tenant` (`tenant_id`),
  CONSTRAINT `fk_ph_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ph_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historical password hashes to prevent reuse';

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

DROP TABLE IF EXISTS `payment_attempts`;
CREATE TABLE `payment_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `status` enum('pending','succeeded','failed','abandoned') DEFAULT 'pending',
  `failure_reason` varchar(100) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `stripe_payment_intent_id` varchar(100) DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscription_retry` (`subscription_id`,`status`,`next_retry_at`),
  KEY `idx_failed` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_gateways`;
CREATE TABLE `payment_gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_test_mode` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-money-bill',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pm_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `payment_methods_stored`;
CREATE TABLE `payment_methods_stored` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) NOT NULL,
  `method_type` enum('card','bank_account','mpesa','paypal','stripe','flutterwave') NOT NULL,
  `provider_token` varchar(255) NOT NULL COMMENT 'Payment gateway token',
  `last4` varchar(4) DEFAULT NULL,
  `card_brand` varchar(50) DEFAULT NULL,
  `card_expiry_month` tinyint(4) DEFAULT NULL,
  `card_expiry_year` smallint(6) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `billing_address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_default` (`customer_id`,`is_default`),
  KEY `idx_provider_token` (`provider_token`),
  KEY `fk_pms_tenant` (`tenant_id`),
  CONSTRAINT `fk_pms_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pms_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stored payment methods for customer subscriptions';

DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `gateway_id` int(10) unsigned DEFAULT NULL,
  `reference` varchar(120) NOT NULL,
  `type` enum('payment','refund','reversal','topup') NOT NULL DEFAULT 'payment',
  `amount` decimal(15,4) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'KES',
  `status` enum('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reference` (`tenant_id`,`reference`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `method` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) DEFAULT 'paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_payments_tenant_id` (`tenant_id`),
  KEY `idx_tenant_sale` (`tenant_id`,`sale_id`),
  CONSTRAINT `fk_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `permission_groups`;
CREATE TABLE `permission_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permission_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_perms_tenant` (`tenant_id`),
  KEY `idx_permissions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=192 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `plan_features`;
CREATE TABLE `plan_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned NOT NULL,
  `feature_id` int(10) unsigned NOT NULL,
  `limit_value` bigint(20) DEFAULT NULL,
  `is_unlimited` tinyint(1) DEFAULT 0,
  `is_included` tinyint(1) DEFAULT 1,
  `overage_rate` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_plan_feature` (`plan_id`,`feature_id`),
  KEY `idx_feature` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `platform_features`;
CREATE TABLE `platform_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` enum('core','add_on','beta','enterprise') DEFAULT 'core',
  `default_limit` bigint(20) DEFAULT NULL,
  `default_unit` varchar(30) DEFAULT NULL,
  `is_beta` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_platform_payments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_platform_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `plugins`;
CREATE TABLE `plugins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL,
  `version` varchar(32) NOT NULL,
  `description` text DEFAULT NULL,
  `author` varchar(128) DEFAULT NULL,
  `status` enum('active','inactive','error') DEFAULT 'inactive',
  `tenant_id` int(11) DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `installed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `unique_plugin_tenant` (`slug`,`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `points_expiry_rules`;
CREATE TABLE `points_expiry_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `points_type` enum('earned','bonus','birthday','referral') DEFAULT 'earned',
  `expiry_months` int(11) NOT NULL DEFAULT 12,
  `expiry_action` enum('remove','convert_to_voucher','partial_remove') DEFAULT 'remove',
  `grace_period_days` int(11) DEFAULT 0,
  `notify_before_days` int(11) DEFAULT 30,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_per_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rules for points expiration';

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
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `pos_kot_jobs`;
CREATE TABLE `pos_kot_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `tab_id` int(10) unsigned DEFAULT NULL,
  `sale_id` bigint(20) unsigned DEFAULT NULL,
  `station_id` int(10) unsigned DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `status` enum('pending','printed','cancelled') NOT NULL DEFAULT 'pending',
  `printed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_kot_stations`;
CREATE TABLE `pos_kot_stations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `station` varchar(40) NOT NULL,
  `label` varchar(80) NOT NULL,
  `printer_target` varchar(120) DEFAULT NULL,
  `paper_width_mm` tinyint(3) unsigned NOT NULL DEFAULT 80,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_station` (`tenant_id`,`branch_id`,`station`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_landing_blocks`;
CREATE TABLE `pos_landing_blocks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `section` varchar(50) NOT NULL COMMENT 'products,ai_slides,how_it_works,trust_strip,faq,cta,features,about',
  `block_key` varchar(100) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(500) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `icon_class` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_block` (`section`,`block_key`),
  KEY `idx_section_sort` (`section`,`sort_order`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_migrations`;
CREATE TABLE `pos_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `migration_name` varchar(100) NOT NULL,
  `ran_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`,`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `pos_newsletter_subscribers`;
CREATE TABLE `pos_newsletter_subscribers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `source` varchar(100) DEFAULT 'landing_page',
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_payment_methods`;
CREATE TABLE `pos_payment_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `pos_settings`;
CREATE TABLE `pos_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `terminal_id` int(10) unsigned DEFAULT NULL,
  `receipt_header` text DEFAULT NULL,
  `receipt_footer` text DEFAULT NULL,
  `receipt_logo` varchar(255) DEFAULT NULL,
  `auto_print` tinyint(1) NOT NULL DEFAULT 1,
  `show_prices_with_tax` tinyint(1) NOT NULL DEFAULT 0,
  `currency_symbol` varchar(10) NOT NULL DEFAULT '$',
  `decimal_places` tinyint(4) NOT NULL DEFAULT 2,
  `thousands_separator` varchar(1) NOT NULL DEFAULT ',',
  `decimal_separator` varchar(1) NOT NULL DEFAULT '.',
  `enable_quick_checkout` tinyint(1) NOT NULL DEFAULT 0,
  `require_customer` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_terminal` (`tenant_id`,`branch_id`,`terminal_id`),
  KEY `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_subscriptions`;
CREATE TABLE `pos_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `plan_id` int(10) unsigned NOT NULL,
  `status` enum('active','trialing','past_due','cancelled','expired') DEFAULT 'active',
  `billing_cycle` enum('monthly','yearly') DEFAULT 'monthly',
  `amount` decimal(12,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'KES',
  `trial_ends_at` datetime DEFAULT NULL,
  `current_period_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `current_period_end` timestamp NULL DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `stripe_subscription_id` varchar(100) DEFAULT NULL,
  `stripe_payment_method_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `pos_subscriptions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `pos_tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pos_subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `pos_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_tab_items`;
CREATE TABLE `pos_tab_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tab_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `qty` decimal(10,3) NOT NULL DEFAULT 1.000,
  `unit_price` decimal(15,4) NOT NULL,
  `discount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `subtotal` decimal(15,4) NOT NULL,
  `note` text DEFAULT NULL,
  `sent_to_kitchen` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tab` (`tab_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_tabs`;
CREATE TABLE `pos_tabs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `table_label` varchar(100) DEFAULT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `cashier_id` int(10) unsigned DEFAULT NULL,
  `status` enum('open','hold','closed','voided') NOT NULL DEFAULT 'open',
  `note` text DEFAULT NULL,
  `opened_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch_status` (`tenant_id`,`branch_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pos_tenants`;
CREATE TABLE `pos_tenants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(36) NOT NULL,
  `subdomain` varchar(63) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `status` enum('active','suspended','cancelled','trial') DEFAULT 'trial',
  `is_suspended` tinyint(1) NOT NULL DEFAULT 0,
  `plan_id` int(10) unsigned DEFAULT 1,
  `parent_tenant_id` int(10) unsigned DEFAULT NULL,
  `settings` longtext DEFAULT NULL CHECK (json_valid(`settings`)),
  `branding` longtext DEFAULT NULL CHECK (json_valid(`branding`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `stripe_customer_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `subdomain` (`subdomain`),
  UNIQUE KEY `domain` (`domain`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_subdomain` (`subdomain`),
  KEY `idx_uuid` (`uuid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `prescription_ref` varchar(100) DEFAULT NULL,
  `doctor_name` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','dispensed','cancelled') NOT NULL DEFAULT 'dispensed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rx_tenant` (`tenant_id`),
  KEY `idx_rx_branch` (`branch_id`),
  KEY `idx_rx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `processed_stripe_events`;
CREATE TABLE `processed_stripe_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stripe_event_id` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `stripe_event_id` (`stripe_event_id`),
  KEY `idx_event` (`stripe_event_id`),
  KEY `idx_type` (`type`,`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_affinity`;
CREATE TABLE `product_affinity` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `related_product_id` int(11) NOT NULL,
  `affinity_score` decimal(5,4) NOT NULL DEFAULT 0.0000,
  `times_bought_together` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_affinity_score` (`affinity_score`),
  KEY `idx_product` (`product_id`),
  KEY `idx_product_affinity_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_affinity_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_attribute_values`;
CREATE TABLE `product_attribute_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pav_product_id` (`product_id`),
  KEY `idx_pav_attribute_id` (`attribute_id`),
  KEY `idx_pav_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_pav_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_attributes`;
CREATE TABLE `product_attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) DEFAULT NULL,
  `attribute_name` varchar(100) NOT NULL,
  `attribute_value` text DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1,
  `position` int(11) DEFAULT 0,
  `used_for_variations` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product_attributes_product` (`product_id`),
  KEY `idx_product_attributes_name` (`attribute_name`),
  KEY `idx_product_attributes_tenant_id` (`tenant_id`),
  KEY `idx_tenant_attribute` (`tenant_id`,`attribute_name`),
  CONSTRAINT `fk_product_attributes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_attributes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pb_product` (`product_id`),
  KEY `idx_pb_expiry` (`expiry_date`),
  KEY `idx_product_batches_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_batches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_categories`;
CREATE TABLE `product_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `category_id` int(10) unsigned NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_category` (`tenant_id`,`product_id`,`category_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_expiry` (`expiry_date`),
  KEY `idx_batch` (`batch_number`),
  KEY `idx_product_expiry_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_expiry_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_product_images_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_images_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_labels`;
CREATE TABLE `product_labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `label_name` varchar(50) NOT NULL,
  `label_color` varchar(7) NOT NULL DEFAULT '#3498db',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_label` (`tenant_id`,`product_id`,`label_name`),
  KEY `idx_label` (`label_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_linked_products`;
CREATE TABLE `product_linked_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `linked_product_id` int(11) NOT NULL,
  `link_type` enum('upsell','cross_sell') NOT NULL DEFAULT 'upsell',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_linked_product` (`product_id`,`linked_product_id`,`link_type`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_linked_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_modifier_group_assignments`;
CREATE TABLE `product_modifier_group_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `group_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_group` (`product_id`,`group_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_group` (`group_id`),
  KEY `fk_pmga_tenant` (`tenant_id`),
  CONSTRAINT `fk_pmga_group` FOREIGN KEY (`group_id`) REFERENCES `recipe_modifier_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmga_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pmga_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Which modifier groups apply to which products';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_modifier_product` (`product_id`),
  KEY `idx_product_modifiers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_modifiers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_price_history`;
CREATE TABLE `product_price_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `old_price` decimal(10,2) NOT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `old_cost` decimal(10,2) DEFAULT NULL,
  `new_cost` decimal(10,2) DEFAULT NULL,
  `changed_by` int(11) NOT NULL,
  `change_reason` varchar(255) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_price_history_product` (`product_id`),
  KEY `idx_price_history_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE `product_reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL COMMENT 'NULL = guest review',
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Verified purchase',
  `rating` tinyint(3) unsigned NOT NULL COMMENT '1-5 stars',
  `title` varchar(200) DEFAULT NULL,
  `comment` text NOT NULL,
  `pros` text DEFAULT NULL,
  `cons` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of image URLs' CHECK (json_valid(`images`)),
  `helpful_count` int(11) DEFAULT 0,
  `not_helpful_count` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected','featured') DEFAULT 'pending',
  `is_verified_purchase` tinyint(1) DEFAULT 0,
  `moderated_by` int(11) DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `moderation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  KEY `idx_status` (`tenant_id`,`status`),
  KEY `idx_rating` (`tenant_id`,`product_id`,`rating`),
  KEY `idx_verified` (`tenant_id`,`is_verified_purchase`),
  KEY `idx_customer` (`tenant_id`,`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product reviews with moderation';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ps_product` (`product_id`),
  KEY `idx_ps_serial` (`serial_number`),
  KEY `idx_product_serials_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_serials_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_suppliers`;
CREATE TABLE `product_suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `supplier_sku` varchar(100) DEFAULT NULL,
  `cost_price` decimal(15,4) DEFAULT NULL,
  `lead_time_days` int(10) unsigned DEFAULT NULL,
  `is_preferred` tinyint(1) NOT NULL DEFAULT 0,
  `min_order_qty` decimal(15,4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_supplier` (`tenant_id`,`product_id`,`supplier_id`),
  KEY `idx_supplier` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_sync_log`;
CREATE TABLE `product_sync_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `action` varchar(50) NOT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'local',
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_tag_relations`;
CREATE TABLE `product_tag_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_tag` (`tenant_id`,`product_id`,`tag_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_tag` (`tag_id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  CONSTRAINT `fk_pt_relations_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_relations_tag` FOREIGN KEY (`tag_id`) REFERENCES `product_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_tags`;
CREATE TABLE `product_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#3B82F6',
  `icon` varchar(50) DEFAULT 'fa-tag',
  `description` text DEFAULT NULL,
  `business_type` varchar(50) DEFAULT NULL,
  `business_type_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_business_type` (`business_type`),
  KEY `idx_business_type_id` (`business_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sku` varchar(80) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `barcode` varchar(50) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_product_variants_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_product_variants_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_view_stats`;
CREATE TABLE `product_view_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `view_date` date NOT NULL,
  `view_count` int(11) DEFAULT 1,
  `unique_visitors` int(11) DEFAULT 1,
  `add_to_cart_count` int(11) DEFAULT 0,
  `purchase_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_product_date` (`tenant_id`,`product_id`,`view_date`),
  KEY `idx_popular` (`tenant_id`,`view_date`,`view_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily product analytics for trending/recommendations';

DROP TABLE IF EXISTS `production_consumption`;
CREATE TABLE `production_consumption` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `production_order_id` bigint(20) unsigned NOT NULL,
  `component_product_id` int(11) NOT NULL,
  `quantity_consumed` decimal(10,3) NOT NULL,
  `quantity_expected` decimal(10,3) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `cost_at_consumption` decimal(10,4) DEFAULT NULL,
  `consumed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `consumed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_production_order` (`production_order_id`),
  KEY `idx_component` (`component_product_id`),
  KEY `fk_pc_tenant` (`tenant_id`),
  CONSTRAINT `fk_pc_order` FOREIGN KEY (`production_order_id`) REFERENCES `production_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materials consumed during production';

DROP TABLE IF EXISTS `production_orders`;
CREATE TABLE `production_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `branch_id` int(11) NOT NULL,
  `production_number` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `bom_id` bigint(20) unsigned NOT NULL,
  `quantity_planned` decimal(10,3) NOT NULL,
  `quantity_produced` decimal(10,3) DEFAULT 0.000,
  `quantity_scrapped` decimal(10,3) DEFAULT 0.000,
  `status` enum('planned','approved','in_progress','completed','cancelled','on_hold') DEFAULT 'planned',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `scheduled_start_date` date DEFAULT NULL,
  `scheduled_end_date` date DEFAULT NULL,
  `actual_start_date` datetime DEFAULT NULL,
  `actual_end_date` datetime DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `workstation` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quality_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_number` (`tenant_id`,`production_number`),
  KEY `idx_product_status` (`product_id`,`status`),
  KEY `idx_scheduled_dates` (`scheduled_start_date`,`scheduled_end_date`),
  KEY `fk_po_branch` (`branch_id`),
  KEY `fk_po_bom` (`bom_id`),
  CONSTRAINT `fk_po_bom` FOREIGN KEY (`bom_id`) REFERENCES `bill_of_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Production/manufacturing orders';

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_type` varchar(50) DEFAULT NULL,
  `business_type_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `global_unique_id` varchar(50) DEFAULT NULL,
  `barcode` varchar(80) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_price_dates_from` date DEFAULT NULL,
  `sale_price_dates_to` date DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(4) DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
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
  `stock_status` varchar(20) DEFAULT 'instock',
  `manage_stock` tinyint(1) DEFAULT 0,
  `backorders` varchar(20) DEFAULT 'no',
  `low_stock_amount` decimal(10,2) DEFAULT NULL,
  `sold_individually` tinyint(1) DEFAULT 0,
  `weight` decimal(10,3) DEFAULT NULL,
  `length` decimal(10,3) DEFAULT NULL,
  `width` decimal(10,3) DEFAULT NULL,
  `height` decimal(10,3) DEFAULT NULL,
  `shipping_class` varchar(50) DEFAULT NULL,
  `purchase_note` text DEFAULT NULL,
  `menu_order` int(11) DEFAULT 0,
  `enable_reviews` tinyint(1) DEFAULT 1,
  `product_type` varchar(20) DEFAULT 'simple',
  `virtual` tinyint(1) DEFAULT 0,
  `downloadable` tinyint(1) DEFAULT 0,
  `tenant_id` bigint(20) unsigned NOT NULL,
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
  KEY `idx_shop_catalog` (`tenant_id`,`active`,`category_id`,`created_at`),
  KEY `idx_products_tenant_id_deleted` (`tenant_id`,`id`,`deleted_at`),
  KEY `idx_products_tenant_id_deleted_covering` (`tenant_id`,`id`,`deleted_at`,`name`,`price`,`selling_price`),
  KEY `idx_products_tenant_price` (`tenant_id`,`price`,`active`),
  KEY `idx_products_tenant_sku` (`tenant_id`,`sku`),
  KEY `idx_tenant_product` (`tenant_id`,`id`,`deleted_at`),
  KEY `idx_tenant_active` (`tenant_id`,`active`),
  KEY `idx_tenant_name` (`tenant_id`,`name`),
  KEY `idx_category` (`category_id`),
  KEY `idx_business_type` (`business_type_id`),
  FULLTEXT KEY `ft_products_name_description` (`name`,`description`),
  CONSTRAINT `fk_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active_dates` (`active`,`valid_from`,`valid_until`),
  KEY `idx_priority` (`priority`),
  KEY `idx_promotions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `prorations`;
CREATE TABLE `prorations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `previous_plan_id` int(10) unsigned DEFAULT NULL,
  `new_plan_id` int(10) unsigned DEFAULT NULL,
  `previous_amount` decimal(12,2) NOT NULL,
  `new_amount` decimal(12,2) NOT NULL,
  `prorated_amount` decimal(12,2) NOT NULL,
  `days_used` int(11) NOT NULL,
  `days_remaining` int(11) NOT NULL,
  `billing_cycle` enum('monthly','yearly') NOT NULL,
  `applied_to_invoice_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscription` (`subscription_id`),
  KEY `idx_tenant_date` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `received_qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(15,4) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `subtotal` decimal(15,4) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_purchase` (`purchase_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `branch_id` (`branch_id`),
  KEY `purchase_orders_ibfk_3` (`created_by`),
  KEY `idx_po_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_purchase_orders_tenant_id` (`tenant_id`),
  KEY `idx_po_supplier` (`supplier_id`),
  KEY `idx_po_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `purchase_returns`;
CREATE TABLE `purchase_returns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `purchase_id` int(10) unsigned NOT NULL,
  `return_number` varchar(50) NOT NULL,
  `status` enum('draft','pending','approved','completed','rejected') NOT NULL DEFAULT 'draft',
  `reason` text NOT NULL,
  `subtotal` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `created_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_number` (`tenant_id`,`return_number`),
  KEY `idx_purchase` (`purchase_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_id` (`quotation_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `business_type_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `total` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_quotations_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_quotations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `rate_limit_attempts`;
CREATE TABLE `rate_limit_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL COMMENT 'IP address, email, user ID or API key',
  `action` varchar(100) NOT NULL COMMENT 'login, password_reset, API request',
  `attempts` int(10) unsigned DEFAULT 1,
  `blocked_until` datetime DEFAULT NULL,
  `last_attempt_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identifier_action` (`identifier`,`action`),
  KEY `idx_blocked` (`blocked_until`),
  KEY `idx_last_attempt` (`last_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rate_limit_blocks`;
CREATE TABLE `rate_limit_blocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(200) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'ip',
  `blocked_until` datetime NOT NULL,
  `reason` varchar(200) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_identifier_type` (`identifier`,`type`),
  KEY `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rate_limit_violations`;
CREATE TABLE `rate_limit_violations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL COMMENT 'User ID, IP, or API key',
  `endpoint` varchar(100) NOT NULL COMMENT 'API endpoint that triggered limit',
  `request_count` int(10) unsigned NOT NULL COMMENT 'Number of requests made',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP address',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_rate_limit_violations_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_rate_limit_violations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `receipt_emails`;
CREATE TABLE `receipt_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `receipt_sms`;
CREATE TABLE `receipt_sms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `price` decimal(10,4) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `recipe_modifier_groups`;
CREATE TABLE `recipe_modifier_groups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `group_name` varchar(100) NOT NULL,
  `group_type` enum('radio','checkbox','quantity','range') DEFAULT 'radio',
  `min_selections` int(11) DEFAULT 0,
  `max_selections` int(11) DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_rmg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Advanced recipe modifier groups';

DROP TABLE IF EXISTS `recipe_modifier_options`;
CREATE TABLE `recipe_modifier_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `group_id` bigint(20) unsigned NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `price_adjustment` decimal(10,2) DEFAULT 0.00,
  `price_adjustment_type` enum('fixed','percentage') DEFAULT 'fixed',
  `calories` int(11) DEFAULT NULL,
  `prep_time_seconds` int(11) DEFAULT 0,
  `inventory_product_id` int(11) DEFAULT NULL COMMENT 'Stock deducted if selected',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_group_active` (`group_id`,`is_active`),
  KEY `fk_rmo_tenant` (`tenant_id`),
  CONSTRAINT `fk_rmo_group` FOREIGN KEY (`group_id`) REFERENCES `recipe_modifier_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rmo_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Options for recipe modifier groups';

DROP TABLE IF EXISTS `recurring_invoices`;
CREATE TABLE `recurring_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_subscription_id` bigint(20) unsigned NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('draft','sent','paid','failed','cancelled') DEFAULT 'draft',
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `attempt_count` int(11) DEFAULT 0,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_invoice` (`tenant_id`,`invoice_number`),
  KEY `idx_subscription_due` (`customer_subscription_id`,`due_date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `referral_programs`;
CREATE TABLE `referral_programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `referral_code` varchar(50) NOT NULL,
  `referrer_customer_id` int(11) NOT NULL,
  `referred_customer_id` int(11) DEFAULT NULL,
  `referred_email` varchar(255) DEFAULT NULL,
  `referred_phone` varchar(20) DEFAULT NULL,
  `status` enum('pending','converted','expired','cancelled') DEFAULT 'pending',
  `referrer_points_awarded` int(11) DEFAULT 0,
  `referred_points_awarded` int(11) DEFAULT 0,
  `referrer_discount_awarded` decimal(10,2) DEFAULT NULL,
  `referred_discount_awarded` decimal(10,2) DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_id`,`referral_code`),
  KEY `idx_referrer` (`referrer_customer_id`),
  KEY `idx_referred` (`referred_customer_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_rp_referred` FOREIGN KEY (`referred_customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rp_referrer` FOREIGN KEY (`referrer_customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer referral program tracking';

DROP TABLE IF EXISTS `refunds`;
CREATE TABLE `refunds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'KES',
  `reason` enum('requested','duplicate','fraud','error','goods_returned') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','processed') DEFAULT 'pending',
  `processed_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `stripe_refund_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`created_at`),
  KEY `idx_tenant` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_register_branch` (`branch_id`),
  KEY `idx_register_user` (`user_id`),
  KEY `idx_register_status` (`status`),
  KEY `idx_register_sessions_tenant_id` (`tenant_id`),
  KEY `idx_user_open` (`user_id`,`tenant_id`,`branch_id`,`status`),
  CONSTRAINT `fk_register_sessions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_remember_tokens_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_remember_tokens_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `reminder_type` enum('task','appointment','follow_up','payment','birthday','custom') NOT NULL DEFAULT 'custom',
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `remind_at` datetime NOT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_remind_at` (`remind_at`),
  KEY `idx_completed` (`is_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `resource_type` enum('table','room','equipment','staff') NOT NULL DEFAULT 'table',
  `resource_id` int(10) unsigned NOT NULL,
  `party_size` int(10) unsigned NOT NULL DEFAULT 1,
  `status` enum('confirmed','pending','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_date` (`reservation_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `restaurant_tables`;
CREATE TABLE `restaurant_tables` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `label` varchar(50) NOT NULL,
  `capacity` tinyint(3) unsigned NOT NULL DEFAULT 4,
  `status` enum('available','occupied','reserved','cleaning') NOT NULL DEFAULT 'available',
  `current_tab_id` int(10) unsigned DEFAULT NULL,
  `position_x` float DEFAULT NULL,
  `position_y` float DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `return_alerts`;
CREATE TABLE `return_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `return_id` int(11) NOT NULL,
  `alert_type` enum('high_value','frequent_customer','suspicious','manager_review') NOT NULL,
  `message` text NOT NULL,
  `is_sent` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_unsent` (`is_sent`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `return_audit_trail`;
CREATE TABLE `return_audit_trail` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `rollback_script` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `return_items`;
CREATE TABLE `return_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) DEFAULT NULL,
  `sale_item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_return` (`return_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_return_items_tenant_id` (`tenant_id`),
  KEY `idx_return_items_deleted` (`deleted_at`),
  CONSTRAINT `fk_return_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `return_predictions`;
CREATE TABLE `return_predictions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `prediction_score` decimal(5,4) NOT NULL COMMENT '0-1 probability',
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `factors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`factors`)),
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sale` (`sale_id`),
  KEY `idx_risk` (`risk_level`),
  KEY `idx_calculated` (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `approved_at` timestamp NULL DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `refund_method` varchar(30) NOT NULL DEFAULT 'cash',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  `approval_level` enum('level1','level2','level3') DEFAULT 'level1',
  `approved_by_manager_id` int(11) DEFAULT NULL,
  `approved_at_manager` timestamp NULL DEFAULT NULL,
  `requires_approval` tinyint(1) DEFAULT 0,
  `high_value_flag` tinyint(1) DEFAULT 0,
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
  KEY `idx_approved_by` (`approved_by`),
  KEY `idx_approved_at` (`approved_at`),
  KEY `idx_requires_approval` (`requires_approval`),
  KEY `idx_high_value_flag` (`high_value_flag`),
  KEY `idx_tenant_return_status` (`tenant_id`,`status`),
  KEY `idx_tenant_return_created` (`tenant_id`,`created_at`),
  KEY `idx_returns_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_returns_created` (`created_at`),
  CONSTRAINT `fk_returns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `rider_location_history`;
CREATE TABLE `rider_location_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `rider_id` bigint(20) unsigned NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy_meters` decimal(8,2) DEFAULT NULL,
  `speed_kmh` decimal(8,2) DEFAULT NULL,
  `bearing` smallint(6) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rider_time` (`rider_id`,`recorded_at`),
  KEY `idx_recorded_at` (`recorded_at`),
  KEY `fk_rlh_tenant` (`tenant_id`),
  CONSTRAINT `fk_rlh_rider` FOREIGN KEY (`rider_id`) REFERENCES `delivery_riders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rlh_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historical rider location tracking';

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  KEY `idx_rp_tenant` (`tenant_id`),
  KEY `idx_role_permissions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_roles_tenant` (`tenant_id`),
  KEY `idx_roles_deleted` (`deleted_at`),
  KEY `idx_roles_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_status` (`status`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_saas_invoices_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_saas_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sale_item_modifiers`;
CREATE TABLE `sale_item_modifiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `product_sku` varchar(80) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `weight` decimal(10,3) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `original_price` decimal(14,2) DEFAULT NULL,
  `price_override_reason` varchar(255) DEFAULT NULL,
  `kitchen_notes` varchar(255) DEFAULT NULL,
  `overridden_by` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `weight_kg` decimal(10,3) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `idx_sale_items_product` (`product_id`,`sale_id`),
  KEY `idx_sale_items_sale` (`sale_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_sale_items_tenant_id` (`tenant_id`),
  KEY `idx_saleitems_sale` (`sale_id`),
  KEY `idx_saleitems_product` (`product_id`),
  KEY `idx_sale_items_batch` (`batch_number`),
  KEY `idx_sale_items_serial` (`serial_number`),
  KEY `idx_sale_items_deleted` (`deleted_at`),
  KEY `idx_tenant_sale_product` (`tenant_id`,`sale_id`,`product_id`),
  KEY `idx_sale_items_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sale_payments`;
CREATE TABLE `sale_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `amount` decimal(15,4) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sale` (`sale_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_method` (`payment_method`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  `age_verify_id` varchar(50) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `weight_kg` decimal(10,3) DEFAULT NULL,
  `amount_received` decimal(12,2) DEFAULT NULL,
  `change_given` decimal(12,2) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `table_number` varchar(20) DEFAULT NULL,
  `kitchen_notes` text DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `warranty_months` int(11) DEFAULT NULL,
  `prescription_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `prescription_ref` varchar(100) DEFAULT NULL,
  `prescription_doctor` varchar(150) DEFAULT NULL,
  `prescription_notes` text DEFAULT NULL,
  `loyalty_points_redeemed` int(11) NOT NULL DEFAULT 0,
  `loyalty_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bt_id` int(11) DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT NULL,
  `weight_unit` varchar(10) DEFAULT NULL,
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
  KEY `idx_sales_discount_amount` (`discount_amount`),
  KEY `idx_sales_tax_amount` (`tax_amount`),
  KEY `idx_sales_tenant_date_payment` (`tenant_id`,`created_at`,`payment_method`),
  KEY `idx_sales_tenant_branch_date_status` (`tenant_id`,`branch_id`,`created_at`,`status`),
  KEY `idx_sales_tenant_customer_date` (`tenant_id`,`customer_id`,`created_at`),
  KEY `idx_sales_date_branch_status` (`created_at`,`branch_id`,`status`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_tenant_status_created` (`tenant_id`,`status`,`created_at`),
  KEY `idx_tenant_branch_created` (`tenant_id`,`branch_id`,`created_at`),
  KEY `idx_sales_status` (`status`),
  KEY `idx_sales_user` (`user_id`),
  KEY `idx_tenant_branch_date` (`tenant_id`,`branch_id`,`created_at`),
  KEY `idx_status_voided` (`status`,`voided`),
  CONSTRAINT `fk_sales_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_velocity_score` (`velocity_score`),
  KEY `idx_trending` (`daily_rate`,`velocity_score`),
  KEY `idx_sales_velocity_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sales_velocity_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `saved_filters`;
CREATE TABLE `saved_filters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `filter_name` varchar(100) NOT NULL,
  `filter_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`filter_config`)),
  `is_default` tinyint(1) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_tenant` (`user_id`,`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `security_audit_log`;
CREATE TABLE `security_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `security_logs`;
CREATE TABLE `security_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `branch_id` int(10) unsigned DEFAULT NULL,
  `business_id` int(10) unsigned DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'low',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_business` (`business_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_event` (`event_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `seo_slugs`;
CREATE TABLE `seo_slugs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `slug` varchar(255) NOT NULL,
  `entity_type` enum('product','category','page','brand') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `canonical_url` varchar(500) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `og_image` varchar(500) DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_entity` (`tenant_id`,`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SEO-friendly URL slugs with meta tags';

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

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting` (`tenant_id`,`setting_key`),
  KEY `idx_settings_tenant_id` (`tenant_id`),
  KEY `idx_settings_key_tenant` (`setting_key`,`tenant_id`),
  KEY `idx_settings_tenant` (`tenant_id`),
  CONSTRAINT `fk_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1814 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `shifts`;
CREATE TABLE `shifts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `opening_cash` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `closing_cash` decimal(15,4) DEFAULT NULL,
  `expected_cash` decimal(15,4) DEFAULT NULL,
  `variance` decimal(15,4) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `opened_at` datetime NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch_status` (`tenant_id`,`branch_id`,`status`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shipment_items`;
CREATE TABLE `shipment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `shipment_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_shipment` (`shipment_id`),
  KEY `idx_shipment_items_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_shipment_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
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

DROP TABLE IF EXISTS `shipping_rates`;
CREATE TABLE `shipping_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `zone_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'e.g. Standard, Express',
  `min_weight` decimal(10,3) DEFAULT 0.000,
  `max_weight` decimal(10,3) DEFAULT 999.000,
  `min_order_value` decimal(10,2) DEFAULT 0.00,
  `rate` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Flat rate or base rate',
  `per_kg_rate` decimal(10,2) DEFAULT 0.00 COMMENT 'Additional per kg',
  `free_shipping` tinyint(1) DEFAULT 0,
  `estimated_days` int(11) DEFAULT 3 COMMENT 'Delivery ETA in days',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_zone` (`zone_id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `shipping_rates_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shipping pricing rules per zone';

DROP TABLE IF EXISTS `shipping_zones`;
CREATE TABLE `shipping_zones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'e.g. Nairobi, Rest of Kenya',
  `description` varchar(255) DEFAULT NULL,
  `countries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'List of country codes' CHECK (json_valid(`countries`)),
  `regions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'List of regions/states' CHECK (json_valid(`regions`)),
  `cities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'List of city names' CHECK (json_valid(`cities`)),
  `postal_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'List of postal code ranges' CHECK (json_valid(`postal_codes`)),
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_sort` (`tenant_id`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Geographic delivery zones';

DROP TABLE IF EXISTS `shop_orders`;
CREATE TABLE `shop_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `status` enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_status` enum('pending','paid','partial','refunded','failed') NOT NULL DEFAULT 'pending',
  `subtotal` decimal(15,4) NOT NULL,
  `tax_amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `shipping_amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `discount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total` decimal(15,4) NOT NULL,
  `shipping_address` text NOT NULL,
  `billing_address` text NOT NULL,
  `notes` text DEFAULT NULL,
  `placed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_number` (`tenant_id`,`order_number`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shop_products`;
CREATE TABLE `shop_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` text DEFAULT NULL,
  `detailed_description` longtext DEFAULT NULL,
  `seo_title` varchar(70) DEFAULT NULL,
  `seo_description` varchar(160) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `meta_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_slug` (`tenant_id`,`slug`),
  UNIQUE KEY `uk_product` (`tenant_id`,`product_id`),
  KEY `idx_featured` (`is_featured`),
  KEY `idx_visible` (`is_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shop_settings`;
CREATE TABLE `shop_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `shop_name` varchar(100) NOT NULL,
  `shop_description` text DEFAULT NULL,
  `shop_logo` varchar(255) DEFAULT NULL,
  `theme` varchar(50) NOT NULL DEFAULT 'default',
  `primary_color` varchar(7) NOT NULL DEFAULT '#3498db',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `timezone` varchar(50) NOT NULL DEFAULT 'UTC',
  `enable_reviews` tinyint(1) NOT NULL DEFAULT 1,
  `require_login` tinyint(1) NOT NULL DEFAULT 0,
  `shipping_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `tax_included` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id` (`tenant_id`),
  KEY `idx_theme` (`theme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_branch` (`branch_id`),
  KEY `idx_unread` (`is_read`,`severity`),
  KEY `idx_smart_alerts_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_smart_alerts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `smart_recommendations`;
CREATE TABLE `smart_recommendations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recommendation_type` enum('upsell','cross_sell','bundle','repeat','trending','personalized') NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `recommended_products` longtext NOT NULL CHECK (json_valid(`recommended_products`)),
  `context_data` longtext DEFAULT NULL CHECK (json_valid(`context_data`)),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_company_type` (`recommendation_type`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_smart_recommendations_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_smart_recommendations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled` (`scheduled_at`),
  KEY `idx_sms_campaigns_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_campaigns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sms_credits`;
CREATE TABLE `sms_credits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `balance` int(11) NOT NULL DEFAULT 0,
  `total_purchased` int(11) NOT NULL DEFAULT 0,
  `total_used` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_balance` (`balance`),
  KEY `idx_sms_credits_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_credits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_opted_in` (`opted_in`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_sms_customer_consent_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_customer_consent_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sms_dnd_list`;
CREATE TABLE `sms_dnd_list` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `added_by` int(10) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_sms_dnd_list_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_dnd_list_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  KEY `idx_sms_logs_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sms_provider_settings`;
CREATE TABLE `sms_provider_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `provider` enum('africastalking','twilio','generic') DEFAULT 'africastalking',
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `sender_id` varchar(50) DEFAULT 'JAKABABA',
  `api_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sms_templates_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_sms_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sc_user` (`user_id`),
  KEY `idx_sc_sale` (`sale_id`),
  KEY `idx_staff_commissions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_staff_commissions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `stock_alerts`;
CREATE TABLE `stock_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned DEFAULT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','overstock','expiry') NOT NULL,
  `threshold` decimal(15,4) NOT NULL,
  `current_qty` decimal(15,4) NOT NULL,
  `status` enum('active','resolved','ignored') NOT NULL DEFAULT 'active',
  `notified_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  KEY `idx_type_status` (`alert_type`,`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lookup` (`branch_id`,`product_id`,`expiry_date`,`status`),
  KEY `idx_stock_batches_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_stock_batches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `stock_movement_log`;
CREATE TABLE `stock_movement_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_type` (`movement_type`),
  KEY `idx_created` (`created_at`),
  KEY `idx_product_date` (`product_id`,`created_at`),
  KEY `idx_branch_date` (`branch_id`,`created_at`),
  KEY `idx_stock_movements_tenant_id` (`tenant_id`),
  KEY `idx_movements_branch` (`branch_id`),
  KEY `idx_movements_created` (`created_at`),
  CONSTRAINT `fk_stock_movements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `stock_sync_log`;
CREATE TABLE `stock_sync_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `event_type` varchar(30) NOT NULL COMMENT 'sale,return,adjustment,reservation,release',
  `old_value` int(11) DEFAULT NULL,
  `new_value` int(11) DEFAULT NULL,
  `delta` int(11) NOT NULL DEFAULT 0,
  `source` varchar(30) NOT NULL COMMENT 'pos,online,cron,manual',
  `source_id` varchar(100) DEFAULT NULL COMMENT 'sale_id,order_id,cart_id',
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_synced_at` (`synced_at`),
  KEY `idx_processed` (`tenant_id`,`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for POS ↔ Online stock sync';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_from_branch` (`from_branch_id`),
  KEY `idx_to_branch` (`to_branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_stock_transfers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_stock_transfers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `storefront_banners`;
CREATE TABLE `storefront_banners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `subtitle` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) NOT NULL,
  `mobile_image_url` varchar(500) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `link_text` varchar(50) DEFAULT 'Shop Now',
  `position` enum('hero','featured','promo','sidebar') DEFAULT 'hero',
  `display_order` int(11) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `click_count` int(11) DEFAULT 0,
  `impression_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_position` (`tenant_id`,`position`,`is_active`),
  KEY `idx_dates` (`tenant_id`,`start_date`,`end_date`),
  KEY `idx_order` (`tenant_id`,`position`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Homepage banners and promotional sliders';

DROP TABLE IF EXISTS `storefront_blocks`;
CREATE TABLE `storefront_blocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `type` varchar(50) DEFAULT 'text-section',
  `props` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`props`)),
  `content` text NOT NULL,
  `bg_color` varchar(20) DEFAULT '#ffffff',
  `text_color` varchar(20) DEFAULT '#1f2937',
  `padding` varchar(20) DEFAULT 'py-8',
  `section_class` varchar(100) DEFAULT 'py-8',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_order` (`tenant_id`,`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `storefront_settings`;
CREATE TABLE `storefront_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `data_type` enum('text','number','boolean','json','email','url','color') DEFAULT 'text',
  `category` varchar(50) DEFAULT 'general' COMMENT 'general,seo,design,payment,social',
  `is_public` tinyint(1) DEFAULT 1 COMMENT 'Expose to storefront API?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_key` (`tenant_id`,`setting_key`),
  KEY `idx_category` (`tenant_id`,`category`)
) ENGINE=InnoDB AUTO_INCREMENT=17321 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-tenant storefront configuration';

DROP TABLE IF EXISTS `storefront_themes`;
CREATE TABLE `storefront_themes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `theme_name` varchar(100) DEFAULT 'default',
  `primary_color` varchar(7) DEFAULT '#f68b1e',
  `secondary_color` varchar(7) DEFAULT '#1a1a2e',
  `background_color` varchar(7) DEFAULT '#ffffff',
  `text_color` varchar(7) DEFAULT '#282828',
  `font_family` varchar(100) DEFAULT 'Inter',
  `custom_css` longtext DEFAULT NULL,
  `custom_js` longtext DEFAULT NULL,
  `header_html` longtext DEFAULT NULL,
  `footer_html` longtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_theme` (`tenant_id`,`theme_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant-specific theme overrides';

DROP TABLE IF EXISTS `subscription_features`;
CREATE TABLE `subscription_features` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `feature_id` int(10) unsigned NOT NULL,
  `feature_key` varchar(100) NOT NULL,
  `usage_limit` int(10) unsigned DEFAULT NULL,
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_subscription_feature` (`subscription_id`,`feature_id`),
  KEY `idx_feature` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subscription_history`;
CREATE TABLE `subscription_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `event` enum('created','activated','renewed','upgraded','downgraded','paused','resumed','cancelled','expired','payment_failed','payment_succeeded') NOT NULL,
  `previous_plan_id` int(10) unsigned DEFAULT NULL,
  `new_plan_id` int(10) unsigned DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `amount_changed` decimal(12,2) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `triggered_by` enum('system','user','admin','stripe','cron') NOT NULL,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscription_event` (`subscription_id`,`event`,`created_at`),
  KEY `idx_tenant_date` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subscription_invoices`;
CREATE TABLE `subscription_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `subscription_id` int(10) unsigned NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(15,4) NOT NULL,
  `tax_amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `total` decimal(15,4) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `due_date` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_number` (`tenant_id`,`invoice_number`),
  KEY `idx_subscription` (`subscription_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_status` (`status`),
  KEY `idx_subscription_payments_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_subscription_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscription_payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `company_subscriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_payments_ibfk_3` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sub_plan` (`plan_id`),
  KEY `idx_sub_status` (`status`),
  KEY `idx_subscriptions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `supplier_products`;
CREATE TABLE `supplier_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `supplier_id` int(10) unsigned NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `supplier_sku` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cost_price` decimal(15,4) DEFAULT NULL,
  `retail_price` decimal(15,4) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_supplier` (`tenant_id`,`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `deleted_by` (`deleted_by`),
  KEY `branch_id` (`branch_id`),
  KEY `fk_supplier_created_by` (`created_by`),
  KEY `fk_supplier_updated_by` (`updated_by`),
  KEY `idx_suppliers_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_suppliers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_suppliers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `support_access_actions`;
CREATE TABLE `support_access_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `access_log_id` int(11) DEFAULT NULL,
  `action_type` varchar(100) DEFAULT NULL,
  `action_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `support_access_logs`;
CREATE TABLE `support_access_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_access_sessions`;
CREATE TABLE `support_access_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `admin_id` int(10) unsigned NOT NULL,
  `session_token` varchar(128) NOT NULL,
  `reason` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`session_token`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `ticket_number` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('bug','feature_request','billing','technical','account','other') DEFAULT 'other',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('open','in_progress','waiting_on_customer','resolved','closed') DEFAULT 'open',
  `assigned_to_admin_id` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `satisfaction_rating` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `idx_status` (`status`,`priority`,`created_at`),
  KEY `idx_assigned` (`assigned_to_admin_id`,`status`),
  KEY `idx_tenant` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `suspicious_activity_logs`;
CREATE TABLE `suspicious_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `activity_type` enum('brute_force','impossible_travel','unusual_hour','data_exfiltration','privilege_escalation','api_abuse') NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`details`)),
  `risk_score` int(11) DEFAULT 50,
  `action_taken` enum('logged','alerted','blocked','session_terminated','notified_admin') DEFAULT 'logged',
  `reviewed_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_risk` (`risk_score`,`created_at`),
  KEY `idx_type` (`activity_type`,`action_taken`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sync_queue`;
CREATE TABLE `sync_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned DEFAULT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `action` enum('create','update','delete') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `synced` tinyint(1) NOT NULL DEFAULT 0,
  `synced_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_synced` (`tenant_id`,`synced`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `system_alerts`;
CREATE TABLE `system_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `severity` enum('info','warning','critical') NOT NULL,
  `component` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `metric_value` varchar(50) DEFAULT NULL,
  `threshold_value` varchar(50) DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `acknowledged_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_severity` (`severity`,`is_resolved`,`created_at`),
  KEY `idx_component` (`component`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `system_health_checks`;
CREATE TABLE `system_health_checks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL COMMENT 'NULL = global system health',
  `check_type` enum('database','cache','queue','storage','api','payment_gateway','sms_gateway','email') NOT NULL,
  `check_name` varchar(100) NOT NULL,
  `status` enum('healthy','degraded','down','checking') DEFAULT 'checking',
  `response_time_ms` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `details` longtext DEFAULT NULL COMMENT 'JSON with additional metrics',
  `checked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_check` (`tenant_id`,`check_type`),
  KEY `idx_checked_at` (`checked_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System health monitoring results';

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

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_label` varchar(200) DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `input_type` enum('text','textarea','password','number','email','url','checkbox','select','color','image') NOT NULL DEFAULT 'text',
  `setting_options` longtext DEFAULT NULL CHECK (json_valid(`setting_options`)),
  `is_required` tinyint(1) DEFAULT 0,
  `placeholder` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`),
  KEY `idx_setting_group` (`setting_group`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_system_settings_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tax_rates_active` (`active`),
  KEY `idx_tax_rates_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_tax_rates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `taxes`;
CREATE TABLE `taxes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `rate` decimal(5,2) NOT NULL,
  `type` enum('vat','gst','sales_tax','custom') DEFAULT 'vat',
  `country_code` varchar(5) DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL,
  `is_compound` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_country` (`country_code`,`region`,`is_active`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_add_ons`;
CREATE TABLE `tenant_add_ons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `feature_id` int(10) unsigned NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `billing_cycle` enum('monthly','yearly','one_time') DEFAULT 'monthly',
  `is_active` tinyint(1) DEFAULT 1,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ends_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_feature` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_api_keys`;
CREATE TABLE `tenant_api_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT 'Default',
  `api_key` varchar(100) NOT NULL,
  `api_secret` varchar(255) NOT NULL,
  `rate_limit` int(10) unsigned NOT NULL DEFAULT 1000,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `request_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `status` enum('active','revoked','expired') NOT NULL DEFAULT 'active',
  `created_by_admin_id` int(10) unsigned DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `idx_tenant_api_keys_tenant_id` (`tenant_id`),
  KEY `idx_tenant_api_keys_status` (`status`),
  KEY `idx_tenant_api_keys_api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_branding`;
CREATE TABLE `tenant_branding` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `primary_color` varchar(7) DEFAULT '#F59E0B',
  `secondary_color` varchar(7) DEFAULT '#10B981',
  `logo_url` varchar(500) DEFAULT NULL,
  `favicon_url` varchar(500) DEFAULT NULL,
  `login_background_url` varchar(500) DEFAULT NULL,
  `receipt_header` text DEFAULT NULL,
  `receipt_footer` text DEFAULT NULL,
  `email_sender_name` varchar(100) DEFAULT NULL,
  `email_sender_email` varchar(255) DEFAULT NULL,
  `custom_css` text DEFAULT NULL,
  `theme_mode` enum('light','dark','system') DEFAULT 'system',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_configs`;
CREATE TABLE `tenant_configs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `config_key` varchar(120) NOT NULL,
  `config_value` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_key` (`tenant_id`,`config_key`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_entitlements`;
CREATE TABLE `tenant_entitlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `feature_id` int(10) unsigned NOT NULL,
  `source` enum('plan','override','add_on','trial','promotion') DEFAULT 'plan',
  `limit_value` bigint(20) DEFAULT NULL,
  `is_unlimited` tinyint(1) DEFAULT 0,
  `is_enabled` tinyint(1) DEFAULT 1,
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `overridden_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `override_reason` text DEFAULT NULL,
  `grace_period_days` int(11) DEFAULT 0,
  `grace_period_ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_feature` (`tenant_id`,`feature_id`,`effective_from`),
  KEY `idx_effective` (`effective_from`,`effective_until`),
  KEY `idx_enabled` (`tenant_id`,`is_enabled`),
  KEY `idx_feature` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_metadata`;
CREATE TABLE `tenant_metadata` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `industry` varchar(50) DEFAULT NULL,
  `company_size` enum('solo','small','medium','enterprise') DEFAULT NULL,
  `annual_revenue_range` varchar(50) DEFAULT NULL,
  `primary_use_case` enum('pos_only','ecommerce_only','hybrid','marketplace') DEFAULT NULL,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `onboarding_progress` int(11) DEFAULT 0,
  `health_score` decimal(3,2) DEFAULT 1.00,
  `health_score_calculated_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `support_priority` enum('low','normal','high','critical') DEFAULT 'normal',
  `account_manager_id` bigint(20) unsigned DEFAULT NULL,
  `churn_risk_score` decimal(3,2) DEFAULT NULL,
  `expected_ltv` decimal(12,2) DEFAULT NULL,
  `cac` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id` (`tenant_id`),
  KEY `idx_health` (`health_score`,`health_score_calculated_at`),
  KEY `idx_onboarding` (`onboarding_progress`,`onboarding_completed_at`),
  KEY `idx_churn` (`churn_risk_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tenant_notes`;
CREATE TABLE `tenant_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `note` text NOT NULL,
  `type` enum('general','support','billing','technical','sales') DEFAULT 'general',
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_internal` tinyint(1) DEFAULT 1,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`,`created_at`),
  KEY `idx_pinned` (`tenant_id`,`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `tenant_settings`;
CREATE TABLE `tenant_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_setting` (`tenant_id`,`setting_key`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

DROP TABLE IF EXISTS `tenant_tags`;
CREATE TABLE `tenant_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `tag` varchar(50) NOT NULL,
  `created_by_admin_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_tag` (`tenant_id`,`tag`),
  KEY `idx_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

DROP TABLE IF EXISTS `terminal_devices`;
CREATE TABLE `terminal_devices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `device_type` enum('pos_terminal','tablet','mobile','kiosk','printer','scanner') NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `status` enum('active','inactive','maintenance','retired') NOT NULL DEFAULT 'active',
  `registered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  KEY `idx_serial` (`serial_number`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_terminal_sessions_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_terminal_sessions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `time_clock`;
CREATE TABLE `time_clock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `branch_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `clock_in` datetime NOT NULL,
  `clock_out` datetime DEFAULT NULL,
  `break_duration` int(10) unsigned NOT NULL DEFAULT 0,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ip_address_in` varchar(45) DEFAULT NULL,
  `ip_address_out` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_date` (`clock_in`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_timeclock_date` (`clock_in`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `time_clock_entries`;
CREATE TABLE `time_clock_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `clock_in_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `clock_out_time` timestamp NULL DEFAULT NULL,
  `clock_in_ip` varchar(45) DEFAULT NULL,
  `clock_out_ip` varchar(45) DEFAULT NULL,
  `clock_in_location` varchar(255) DEFAULT NULL,
  `clock_out_location` varchar(255) DEFAULT NULL,
  `clock_in_photo` varchar(500) DEFAULT NULL,
  `clock_out_photo` varchar(500) DEFAULT NULL,
  `total_hours` decimal(6,2) DEFAULT NULL,
  `break_minutes` int(11) DEFAULT 0,
  `overtime_minutes` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `status` enum('clocked_in','clocked_out','missing_clockout','adjusted') DEFAULT 'clocked_out',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`clock_in_time`),
  KEY `idx_branch_date` (`branch_id`,`clock_in_time`),
  KEY `idx_status` (`status`),
  KEY `fk_tce_tenant` (`tenant_id`),
  CONSTRAINT `fk_tce_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tce_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee time clock tracking';

DROP TABLE IF EXISTS `tracking_history`;
CREATE TABLE `tracking_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shipment` (`shipment_id`),
  KEY `idx_tracking_history_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `two_factor_tokens`;
CREATE TABLE `two_factor_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `method` enum('email','sms','totp') NOT NULL,
  `token` varchar(10) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_two_factor_tokens_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ui_heatmap`;
CREATE TABLE `ui_heatmap` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `element_id` varchar(100) NOT NULL,
  `element_type` varchar(50) NOT NULL,
  `clicks` int(11) DEFAULT 0,
  `hover_duration` int(11) DEFAULT 0,
  `session_date` date NOT NULL,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_element` (`element_id`,`session_date`),
  KEY `idx_ui_heatmap_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_ui_heatmap_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `unit_conversions`;
CREATE TABLE `unit_conversions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `from_unit_id` int(10) unsigned NOT NULL,
  `to_unit_id` int(10) unsigned NOT NULL,
  `conversion_factor` decimal(15,6) NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conversion` (`tenant_id`,`from_unit_id`,`to_unit_id`,`product_id`),
  KEY `idx_from_unit` (`from_unit_id`),
  KEY `idx_to_unit` (`to_unit_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `unit_symbol` varchar(10) NOT NULL,
  `unit_type` enum('weight','volume','piece','length','area','time','other') NOT NULL DEFAULT 'piece',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_unit` (`tenant_id`,`unit_name`),
  KEY `idx_type` (`unit_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `units_of_measure`;
CREATE TABLE `units_of_measure` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_code` (`code`),
  KEY `idx_units_of_measure_tenant_id` (`tenant_id`),
  CONSTRAINT `chk_units_is_active` CHECK (`is_active` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usage_metering`;
CREATE TABLE `usage_metering` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `usage_value` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `limit_value` decimal(20,6) DEFAULT NULL,
  `is_over_limit` tinyint(1) DEFAULT 0,
  `over_limit_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_period` (`tenant_id`,`type_id`,`period_start`),
  KEY `idx_over_limit` (`is_over_limit`,`over_limit_at`),
  KEY `idx_period` (`period_start`,`period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usage_metering_aggregates`;
CREATE TABLE `usage_metering_aggregates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `aggregate_date` date NOT NULL,
  `total_value` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `min_value` decimal(20,6) DEFAULT NULL,
  `max_value` decimal(20,6) DEFAULT NULL,
  `avg_value` decimal(20,6) DEFAULT NULL,
  `sample_count` bigint(20) unsigned DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_type_date` (`tenant_id`,`type_id`,`aggregate_date`),
  KEY `idx_aggregate_date` (`aggregate_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usage_metering_events`;
CREATE TABLE `usage_metering_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `event_value` decimal(20,6) NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type_time` (`tenant_id`,`type_id`,`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usage_metering_types`;
CREATE TABLE `usage_metering_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `is_metered` tinyint(1) DEFAULT 1,
  `reset_period` enum('minute','hour','day','month','billing_cycle') DEFAULT 'month',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_branches`;
CREATE TABLE `user_branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_branch` (`user_id`,`branch_id`),
  KEY `idx_user_branches_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_user_branches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_efficiency` (`transactions_per_hour`),
  KEY `idx_user_efficiency_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_user_efficiency_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_notes`;
CREATE TABLE `user_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `author_id` int(10) unsigned NOT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_password_history`;
CREATE TABLE `user_password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_password_history` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `permission` varchar(100) NOT NULL,
  `is_granted` tinyint(1) NOT NULL DEFAULT 1,
  `granted_by` int(10) unsigned NOT NULL,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_permission` (`tenant_id`,`user_id`,`permission`),
  KEY `idx_user` (`user_id`),
  KEY `idx_permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  KEY `idx_ur_tenant` (`tenant_id`),
  KEY `idx_user_roles_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_activity_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_token` (`session_token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_tokens`;
CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'remember_me',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_user_tokens_tenant_id` (`tenant_id`),
  CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `pin` varchar(10) DEFAULT NULL,
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
  `tenant_id` bigint(20) unsigned NOT NULL,
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
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_active` (`is_active`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `vendor_payouts`;
CREATE TABLE `vendor_payouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `agreement_id` bigint(20) unsigned NOT NULL,
  `payout_number` varchar(50) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(12,2) NOT NULL,
  `adjustments` decimal(12,2) DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_reference` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_payout` (`tenant_id`,`payout_number`),
  KEY `idx_supplier_status` (`supplier_id`,`status`),
  KEY `idx_period` (`period_start`,`period_end`),
  KEY `fk_vp_agreement` (`agreement_id`),
  CONSTRAINT `fk_vp_agreement` FOREIGN KEY (`agreement_id`) REFERENCES `consignment_agreements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vp_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payouts to vendors/consignors';

DROP TABLE IF EXISTS `void_reasons`;
CREATE TABLE `void_reasons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `reason_code` varchar(50) NOT NULL,
  `reason_text` varchar(255) NOT NULL,
  `category` enum('product','order','payment','customer','kitchen','delivery') DEFAULT 'product',
  `requires_approval` tinyint(1) DEFAULT 0,
  `approval_role_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_id`,`reason_code`),
  KEY `idx_category_active` (`category`,`is_active`),
  CONSTRAINT `fk_vr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Standardized void/cancellation reasons';

DROP TABLE IF EXISTS `voucher_redemptions`;
CREATE TABLE `voucher_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_voucher_redemptions_voucher` (`voucher_id`),
  KEY `fk_voucher_redemptions_sale` (`sale_id`),
  KEY `idx_voucher_redemptions_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_vouchers_tenant` (`tenant_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_vouchers_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_vouchers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wl_branch` (`branch_id`),
  KEY `idx_wl_status` (`status`),
  KEY `idx_waiting_list_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_waiting_list_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_warranty_product` (`product_id`),
  KEY `idx_warranty_customer` (`customer_id`),
  KEY `idx_warranty_end` (`end_date`),
  KEY `idx_warranties_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_warranties_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `webhook_deliveries`;
CREATE TABLE `webhook_deliveries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_id` int(10) unsigned NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','delivered','failed') DEFAULT 'pending',
  `http_status` int(10) unsigned DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `attempt_count` int(10) unsigned DEFAULT 0,
  `scheduled_at` datetime DEFAULT current_timestamp(),
  `delivered_at` datetime DEFAULT NULL,
  `last_attempt_at` datetime DEFAULT NULL,
  `error` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_scheduled` (`status`,`scheduled_at`),
  KEY `idx_endpoint` (`endpoint_id`),
  KEY `idx_event` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `webhook_endpoints`;
CREATE TABLE `webhook_endpoints` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `url` varchar(500) NOT NULL,
  `secret` varchar(255) NOT NULL,
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '[]' CHECK (json_valid(`events`)),
  `status` enum('active','paused','disabled') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `webhook_failures`;
CREATE TABLE `webhook_failures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `webhook_subscription_id` bigint(20) unsigned NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `response_status` int(11) DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `succeeded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_retry` (`next_retry_at`,`retry_count`),
  KEY `idx_tenant` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_webhook` (`webhook_id`),
  KEY `idx_event` (`event`),
  KEY `idx_webhook_logs_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `webhook_retries`;
CREATE TABLE `webhook_retries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `attempt` int(10) unsigned NOT NULL DEFAULT 1,
  `response_code` int(10) unsigned DEFAULT NULL,
  `response_body` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook` (`webhook_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_retry` (`next_retry_at`),
  KEY `idx_attempt` (`attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `webhook_subscriptions`;
CREATE TABLE `webhook_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `url` varchar(500) NOT NULL,
  `events` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of event names' CHECK (json_valid(`events`)),
  `secret` varchar(255) DEFAULT NULL COMMENT 'HMAC secret',
  `is_active` tinyint(1) DEFAULT 1,
  `last_triggered` datetime DEFAULT NULL,
  `last_response` int(11) DEFAULT NULL COMMENT 'HTTP status',
  `failure_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='External webhook subscriptions';

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
  `tenant_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`),
  KEY `idx_webhooks_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_webhooks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wishlist_items`;
CREATE TABLE `wishlist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `wishlist_id` bigint(20) unsigned NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_wishlist_product` (`wishlist_id`,`product_id`),
  CONSTRAINT `wishlist_items_ibfk_1` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wishlists`;
CREATE TABLE `wishlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT 'My Wishlist',
  `is_public` tinyint(1) DEFAULT 0,
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_share` (`share_token`),
  KEY `idx_tenant_customer` (`tenant_id`,`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer wishlists';

SET FOREIGN_KEY_CHECKS=1;

-- ========================================================
-- Full Data Backup for jdh_pos
-- Generated: 2026-06-24 05:23:13
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Data for table `activity_logs`
-- ----------------------------
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

-- ----------------------------
-- Data for table `admin_roles`
-- ----------------------------
INSERT INTO `admin_roles` (`id`, `name`, `slug`, `description`, `permissions`, `is_system`, `is_owner_role`, `active`, `created_at`, `updated_at`) VALUES
('1', 'Super Admin', 'super_admin', 'Full system access', '{\"*\": true}', '1', '1', '1', '2026-04-30 22:14:20', '2026-04-30 22:14:20'),
('2', 'Admin', 'admin', 'Administrative access', '{\"tenants\": [\"view\", \"manage\"], \"billing\": [\"view\", \"manage\"]}', '1', '0', '1', '2026-04-30 22:14:20', '2026-04-30 22:14:20'),
('3', 'Support', 'support', 'Support staff access', '{\"tenants\": [\"view\"], \"support\": [\"access\"]}', '1', '0', '1', '2026-04-30 22:14:20', '2026-04-30 22:14:20');

-- ----------------------------
-- Data for table `admins`
-- ----------------------------
INSERT INTO `admins` (`id`, `username`, `email`, `password_hash`, `name`, `role`, `owner_role_id`, `status`, `last_login_at`, `last_login_ip`, `failed_login_attempts`, `locked_until`, `must_change_password`, `created_at`, `updated_at`, `deleted_at`, `recovery_codes`) VALUES
('1', 'superadmin', 'admin@jakababa.com', '$2y$10$ZJ9pGoTn60bf23NvF2bsAOhIZ1.nVCGXwOplcILQb3sEjOtPY3Qqe', 'System Administrator', 'super_admin', NULL, 'active', NULL, NULL, '0', '2026-06-11 11:33:44', '1', '2026-04-30 22:14:20', '2026-06-20 16:25:32', NULL, NULL),
('2', 'admin@platform.com', 'admin@platform.com', '$2y$10$52TK5/vrhEVHQMypQYN3eOnzHYdgbRHD7Da5XFM4XrqT2TTYnHTGS', 'Super Admin', 'super_admin', NULL, 'locked', NULL, NULL, '0', '2026-06-11 11:33:44', '1', '2026-05-17 23:30:03', '2026-06-11 11:33:44', NULL, NULL),
('11', 'admin', 'admin2@jakababa.com', '$2y$10$ZJ9pGoTn60bf23NvF2bsAOhIZ1.nVCGXwOplcILQb3sEjOtPY3Qqe', 'Super Admin', 'super_admin', NULL, 'active', NULL, NULL, '0', NULL, '0', '2026-06-20 16:11:14', '2026-06-20 16:25:32', NULL, NULL);

-- ----------------------------
-- Data for table `attribute_values`
-- ----------------------------
INSERT INTO `attribute_values` (`id`, `tenant_id`, `attribute_id`, `value`, `label`, `color_hex`, `sort_order`, `created_at`) VALUES
('1', '1', '1', 'Blue', 'blue', '#0134fe', '1', '2026-05-20 19:32:52');

-- ----------------------------
-- Data for table `attributes`
-- ----------------------------
INSERT INTO `attributes` (`id`, `tenant_id`, `name`, `code`, `type`, `group_id`, `unit`, `status`, `is_variant_forming`, `is_filterable`, `is_required`, `sort_order`, `deleted_at`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
('1', '1', 'Color', 'color', 'color', NULL, NULL, '1', '0', '0', '0', '0', NULL, '2', '2', '2026-05-20 00:58:30', '2026-05-20 13:03:35');

-- ----------------------------
-- Data for table `audit_logs`
-- ----------------------------
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

-- ----------------------------
-- Data for table `backups`
-- ----------------------------
INSERT INTO `backups` (`id`, `filename`, `size`, `status`, `created_at`) VALUES
('1', 'jdh_pos_2026-05-04_08-23-25.sql.gz', '24155', 'success', '2026-05-04 09:23:26');

-- ----------------------------
-- Data for table `branches`
-- ----------------------------
INSERT INTO `branches` (`id`, `name`, `code`, `address`, `phone`, `email`, `manager`, `active`, `created_at`, `location`, `tax_rate`, `updated_at`, `opening_time`, `closing_time`, `business_type_id`, `deleted_at`, `tenant_id`, `is_active`) VALUES
('1', 'Kisumu', 'MAIN', '123 Main Street', '+254700000000', '', '', '1', '2026-04-30 22:14:20', NULL, '0.00', NULL, '08:00:00', '20:00:00', '2', NULL, '1', '1'),
('2', 'Kisii', 'KSI', '400200', '0726527245', '', NULL, '1', '2026-06-10 18:53:55', 'Kisii', '16.00', '2026-06-10 19:10:06', '08:00:00', '18:30:00', NULL, NULL, '1', '1');

-- ----------------------------
-- Data for table `brands`
-- ----------------------------
INSERT INTO `brands` (`id`, `name`, `description`, `image`, `active`, `created_at`, `updated_at`, `tenant_id`, `deleted_at`) VALUES
('1', 'MomEasy', 'Quality products', NULL, '1', '2026-06-08 23:12:58', '2026-06-08 23:13:27', '1', NULL);

-- ----------------------------
-- Data for table `business_types`
-- ----------------------------
INSERT INTO `business_types` (`id`, `code`, `name`, `description`, `icon`, `default_receipt_template`, `order_types`, `product_fields`, `features`, `active`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', 'retail', 'Retail / General Store', 'General retail and point of sale', 'fa-store', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('2', 'supermarket', 'Supermarket / Grocery', 'Supermarket and grocery stores', 'fa-shopping-cart', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('3', 'restaurant', 'Restaurant / Cafe', 'Restaurants, cafes and food service', 'fa-utensils', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('4', 'pharmacy', 'Pharmacy / Drugstore', 'Pharmacies and drugstores', 'fa-pills', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('5', 'fashion', 'Fashion / Clothing', 'Fashion and clothing stores', 'fa-tshirt', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('6', 'electronics', 'Electronics', 'Electronics and tech stores', 'fa-laptop', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('7', 'hardware', 'Hardware / Building', 'Hardware and building materials', 'fa-wrench', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('8', 'salon', 'Salon / Beauty', 'Beauty salons and spa', 'fa-cut', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('9', 'wholesale', 'Wholesale', 'Wholesale and distribution', 'fa-boxes', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '0', '1', '0', '2026-04-30 22:05:32', '2026-05-16 16:18:36'),
('10', 'other', 'Other', 'Other business types', 'fa-ellipsis-h', 'default', '[\"walk-in\",\"online\",\"delivery\"]', NULL, '{\"inventory\":true,\"barcode\":true}', '1', '1', '0', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('11', 'liquor_store', 'Liquor Store', NULL, 'fa-wine-bottle', 'default', NULL, NULL, NULL, '1', '1', '0', '2026-05-16 16:18:36', '2026-05-16 16:18:36'),
('12', 'stationery', 'Stationery / Office', NULL, 'fa-pencil-alt', 'default', NULL, NULL, NULL, '1', '1', '0', '2026-05-16 16:18:36', '2026-05-16 16:18:36');

-- ----------------------------
-- Data for table `categories`
-- ----------------------------
INSERT INTO `categories` (`id`, `business_type_id`, `code`, `parent_id`, `name`, `description`, `color`, `icon`, `image`, `branch_id`, `created_by`, `updated_at`, `status`, `sort_order`, `meta_title`, `meta_description`, `created_at`, `updated_by`, `deleted_at`, `deleted_by`, `business_type`, `tenant_id`) VALUES
('1', NULL, NULL, NULL, 'Baby Outfits', '', '#fbbf24', 'tag', '/JDH_POS/public/uploads/categories/69f4a6e4c43a2_Heavy Romper.png', NULL, NULL, '2026-05-01 16:13:08', 'active', '5', '', '', '2026-05-01 11:17:45', '1', NULL, NULL, 'supermarket', '1');

-- ----------------------------
-- Data for table `customers`
-- ----------------------------
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`, `city`, `postal_code`, `tax_id`, `loyalty_points`, `active`, `created_at`, `updated_at`, `status`, `deleted_at`, `deleted_by`, `credit_limit`, `notes`, `created_by`, `branch_id`, `loyalty_tier`, `group_id`, `total_spent`, `last_purchase`, `points_expired`, `tenant_id`, `current_balance`, `total_credit_used`, `total_payments`, `last_payment_date`, `payment_terms_days`, `credit_status`, `credit_notes`, `requires_approval`, `tenant_name`) VALUES
('1', 'Customer_1', 'REDACTED_1', 'customer_1@secured.local', 'REDACTED', 'REDACTED', '40111', '', '200', '1', '2026-05-18 14:35:21', '2026-05-18 11:35:42', '1', NULL, NULL, '0.00', '', '2', '1', 'bronze', NULL, '0.00', NULL, '0', '1', '0.00', '0.00', '0.00', NULL, '30', 'active', NULL, '0', NULL);

-- ----------------------------
-- Data for table `discounts`
-- ----------------------------
INSERT INTO `discounts` (`id`, `name`, `type`, `value`, `active`, `description`, `valid_from`, `valid_until`, `min_purchase`, `max_discount`, `usage_limit`, `usage_count`, `priority`, `applicable_products`, `product_ids`, `category_id`, `branch_id`, `created_at`, `tenant_id`, `code`, `max_per_customer`) VALUES
('1', 'May Discount', 'fixed', '4999.99', '1', 'Mother\'s Day Offer', '2026-05-17', '2026-05-31', '2000.00', '5000.00', NULL, '0', '5', 'all', NULL, NULL, NULL, '2026-05-17 18:11:38', '1', NULL, NULL);

-- ----------------------------
-- Data for table `email_templates`
-- ----------------------------
INSERT INTO `email_templates` (`id`, `slug`, `name`, `subject`, `body_html`, `body_text`, `variables`, `category`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'trial_welcome', 'Trial Welcome', 'Welcome to JAKABABA SMART SYSTEM', '<h1>Welcome!</h1><p>Your trial has started. Get started here: {{setup_url}}</p>', 'Welcome! Your trial has started. Get started here: {{setup_url}}', '[\"setup_url\",\"tenant_name\",\"trial_days\"]', 'trial', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29'),
('2', 'payment_failed', 'Payment Failed', 'Payment failed for {{tenant_name}}', '<h1>Payment Failed</h1><p>We could not process your payment. Update your method: {{update_url}}</p>', 'Payment failed. Update your payment method: {{update_url}}', '[\"tenant_name\",\"amount\",\"update_url\",\"retry_date\"]', 'billing', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29'),
('3', 'invoice_receipt', 'Invoice Receipt', 'Receipt for Invoice {{invoice_number}}', '<h1>Thank you!</h1><p>Your payment of {{amount}} was received.</p>', 'Thank you! Your payment of {{amount}} was received.', '[\"invoice_number\",\"amount\",\"date\",\"download_url\"]', 'billing', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29'),
('4', 'usage_80_percent', 'Usage 80% Alert', 'You have used 80% of your {{feature_name}} limit', '<p>You have used 80% of your {{feature_name}} limit. Upgrade: {{upgrade_url}}</p>', 'You have used 80% of your {{feature_name}} limit. Upgrade: {{upgrade_url}}', '[\"feature_name\",\"usage\",\"limit\",\"upgrade_url\"]', 'usage', '1', '2026-05-18 08:41:29', '2026-05-18 08:41:29');

-- ----------------------------
-- Data for table `features`
-- ----------------------------
INSERT INTO `features` (`id`, `tenant_id`, `feature_key`, `name`, `description`, `module_name`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'pos', 'Point of Sale', 'Process sales and transactions', 'pos', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('2', '1', 'inventory', 'Inventory Management', 'Track products and stock levels', 'inventory', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('3', '1', 'reports', 'Advanced Reports', 'Detailed analytics and insights', 'reports', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('4', '1', 'multi_branch', 'Multi-Branch Support', 'Manage multiple branches', 'branches', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('5', '1', 'api_access', 'API Access', 'Access to REST API', 'integrations', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('6', '1', 'priority_support', 'Priority Support', 'Priority customer support', 'support', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('7', '1', 'loyalty', 'Loyalty Program', 'Customer rewards and points', 'loyalty', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('8', '1', 'purchases', 'Purchase Orders', 'Manage supplier orders', 'purchases', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('9', '1', 'quotations', 'Quotations', 'Create and manage quotes', 'quotations', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('10', '1', 'shipments', 'Shipments', 'Track deliveries', 'shipments', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32');

-- ----------------------------
-- Data for table `inventory`
-- ----------------------------
INSERT INTO `inventory` (`product_id`, `branch_id`, `stock`, `reorder_level`, `expiry_date`, `batch_number`, `manufacturing_date`, `location`, `minimum_stock`, `maximum_stock`, `updated_at`, `created_at`, `tenant_id`) VALUES
('1', '1', '8', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 14:48:29', '2026-05-01 13:38:35', '1'),
('1', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('2', '1', '7', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-17 03:44:35', '2026-05-12 23:14:16', '1'),
('2', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('3', '1', '9', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-23 04:55:03', '2026-05-12 23:16:38', '1'),
('3', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('4', '1', '8', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 14:46:26', '2026-05-12 23:17:47', '1'),
('4', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('5', '1', '7', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 06:03:36', '2026-05-12 23:18:49', '1'),
('5', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('6', '1', '8', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-22 06:03:36', '2026-05-12 23:21:23', '1'),
('6', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('7', '1', '9', '5', NULL, NULL, NULL, NULL, '0', '0', '2026-06-16 19:28:58', '2026-05-12 23:21:55', '1'),
('7', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1'),
('8', '1', '9', '5', NULL, NULL, NULL, NULL, '0', NULL, '2026-06-22 06:03:36', '2026-05-13 11:22:24', '1'),
('8', '2', '0', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, '2026-06-15 12:14:57', '1');

-- ----------------------------
-- Data for table `inventory_logs`
-- ----------------------------
INSERT INTO `inventory_logs` (`id`, `product_id`, `branch_id`, `old_stock`, `new_stock`, `change_amount`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES
('1', '1', '1', '0', '15', '15', 'Manual update', '0', '2026-05-01 17:37:22', '1'),
('2', '1', '1', '9', '10', '1', 'Return completed #R20260511-600', '2', '2026-05-11 09:53:21', '1'),
('3', '1', '1', '0', '15', '15', 'Manual update', '0', '2026-05-11 17:48:45', '1'),
('4', '8', '1', '0', '10', '10', 'Manual update', '0', '2026-05-13 11:22:57', '1'),
('5', '2', '1', '0', '15', '15', 'Manual update', '0', '2026-05-13 13:07:25', '1'),
('6', '2', '1', '5', '6', '1', 'Return completed #R20260514-554', '2', '2026-05-17 08:05:33', '1'),
('7', '3', '1', '0', '10', '10', 'Manual update', '0', '2026-05-17 22:00:40', '1'),
('8', '2', '1', '4', '10', '6', 'Manual update', '0', '2026-05-17 22:00:59', '1'),
('9', '7', '1', '0', '10', '10', 'Manual update', '0', '2026-05-17 22:01:10', '1'),
('10', '5', '1', '0', '10', '10', 'Manual update', '0', '2026-05-17 22:01:24', '1'),
('11', '1', '1', '0', '10', '10', 'Manual update', '0', '2026-05-17 22:01:35', '1'),
('12', '6', '1', '0', '10', '10', 'Manual update', '0', '2026-05-17 22:01:48', '1'),
('13', '4', '1', '0', '10', '10', 'Manual update', '0', '2026-05-17 22:01:59', '1'),
('14', '2', '1', '4', '5', '1', 'Return completed #R20260517-900', '2', '2026-06-01 16:43:48', '1'),
('15', '2', '1', '5', '6', '1', 'Return completed #R20260517-900', '2', '2026-06-01 16:43:49', '1'),
('16', '3', '1', '0', '0', '10', 'Received from PO #1', '0', '2026-06-09 17:05:16', '1'),
('17', '2', '1', '0', '0', '10', 'Received from PO #2', '0', '2026-06-09 17:26:44', '1'),
('18', '2', '1', '4', '3', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-13 01:03:28', '1'),
('19', '2', '1', '3', '2', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-13 01:04:01', '1'),
('20', '2', '1', '2', '0', '-2', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-13 09:45:11', '1'),
('21', '4', '1', '3', '2', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-14 01:59:53', '1'),
('22', '4', '1', '2', '1', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-14 03:30:03', '1'),
('23', '4', '1', '1', '0', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-15 13:06:26', '1'),
('24', '8', '1', '1', '0', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-15 13:31:37', '1'),
('25', '2', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:56', '1'),
('26', '3', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:56', '1'),
('27', '8', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:56', '1'),
('28', '4', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:57', '1'),
('29', '6', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:57', '1'),
('30', '1', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:57', '1'),
('31', '5', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:57', '1'),
('32', '7', '1', '0', '10', '10', 'Bulk update', '2', '2026-06-15 13:50:57', '1'),
('33', '2', '1', '10', '9', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-15 13:51:33', '1'),
('34', '2', '1', '9', '8', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-15 20:17:47', '1'),
('35', '5', '1', '10', '9', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-15 20:17:47', '1'),
('36', '1', '1', '10', '8', '-2', 'Sale - Invoice: INV-202606-0001', '1', '2026-06-15 20:29:45', '1'),
('37', '4', '1', '10', '9', '-1', 'Sale - Invoice: INV-202606-0001', '1', '2026-06-16 13:32:33', '1'),
('38', '7', '1', '10', '9', '-1', 'Sale - Invoice: INV-202606-0001', '1', '2026-06-16 19:28:58', '1'),
('39', '2', '1', '8', '7', '-1', 'Sale - Invoice: INV-202606-0001', '1', '2026-06-17 03:44:35', '1'),
('40', '5', '1', '9', '8', '-1', 'Sale - Invoice: INV-202606-0001', '1', '2026-06-17 04:04:13', '1'),
('41', '6', '1', '10', '9', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-19 17:03:20', '1'),
('42', '5', '1', '8', '7', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-22 06:03:36', '1'),
('43', '6', '1', '9', '8', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-22 06:03:36', '1'),
('44', '4', '1', '9', '8', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-22 06:03:36', '1'),
('45', '8', '1', '10', '9', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-22 06:03:36', '1'),
('46', '3', '1', '10', '9', '-1', 'Sale - Invoice: INV-202606-0001', '2', '2026-06-23 04:55:03', '1');

-- ----------------------------
-- Data for table `invoice_sequences`
-- ----------------------------
INSERT INTO `invoice_sequences` (`id`, `branch_id`, `year`, `month`, `last_number`, `created_at`, `updated_at`, `tenant_id`) VALUES
('1', '1', '2026', '5', '1', '2026-05-01 17:38:07', '2026-05-01 17:38:07', '1'),
('2', '1', '2026', '5', '1', '2026-05-01 22:37:36', '2026-05-01 22:37:36', '1'),
('3', '1', '2026', '5', '1', '2026-05-01 23:06:40', '2026-05-01 23:06:40', '1'),
('4', '1', '2026', '5', '1', '2026-05-01 23:09:49', '2026-05-01 23:09:49', '1'),
('5', '1', '2026', '5', '1', '2026-05-02 17:31:23', '2026-05-02 17:31:23', '1'),
('6', '1', '2026', '5', '1', '2026-05-02 18:37:27', '2026-05-02 18:37:27', '1'),
('7', '1', '2026', '5', '1', '2026-05-11 12:32:06', '2026-05-11 12:32:06', '1'),
('8', '1', '2026', '5', '1', '2026-05-11 12:41:18', '2026-05-11 12:41:18', '1'),
('9', '1', '2026', '5', '1', '2026-05-11 16:35:50', '2026-05-11 16:35:50', '1'),
('10', '1', '2026', '5', '1', '2026-05-11 17:34:31', '2026-05-11 17:34:31', '1'),
('11', '1', '2026', '5', '1', '2026-05-11 17:43:17', '2026-05-11 17:43:17', '1'),
('12', '1', '2026', '5', '1', '2026-05-11 17:52:16', '2026-05-11 17:52:16', '1'),
('13', '1', '2026', '5', '1', '2026-05-11 17:52:39', '2026-05-11 17:52:39', '1'),
('14', '1', '2026', '5', '1', '2026-05-11 17:54:20', '2026-05-11 17:54:20', '1'),
('15', '1', '2026', '5', '1', '2026-05-11 18:07:08', '2026-05-11 18:07:08', '1'),
('16', '1', '2026', '5', '1', '2026-05-11 18:30:56', '2026-05-11 18:30:56', '1'),
('17', '1', '2026', '5', '1', '2026-05-12 10:19:39', '2026-05-12 10:19:39', '1'),
('18', '1', '2026', '5', '1', '2026-05-12 16:47:10', '2026-05-12 16:47:10', '1'),
('19', '1', '2026', '5', '1', '2026-05-12 20:56:09', '2026-05-12 20:56:09', '1'),
('20', '1', '2026', '5', '1', '2026-05-12 21:21:22', '2026-05-12 21:21:22', '1'),
('21', '1', '2026', '5', '1', '2026-05-12 21:32:32', '2026-05-12 21:32:32', '1'),
('22', '1', '2026', '5', '1', '2026-05-12 21:49:48', '2026-05-12 21:49:48', '1'),
('23', '1', '2026', '5', '1', '2026-05-13 11:18:59', '2026-05-13 11:18:59', '1'),
('24', '1', '2026', '5', '1', '2026-05-13 13:07:44', '2026-05-13 13:07:44', '1'),
('25', '1', '2026', '5', '1', '2026-05-13 13:27:49', '2026-05-13 13:27:49', '1'),
('26', '1', '2026', '5', '1', '2026-05-13 14:03:24', '2026-05-13 14:03:24', '1'),
('27', '1', '2026', '5', '1', '2026-05-13 14:05:39', '2026-05-13 14:05:39', '1'),
('28', '1', '2026', '5', '1', '2026-05-13 15:15:12', '2026-05-13 15:15:12', '1'),
('29', '1', '2026', '5', '1', '2026-05-13 15:17:18', '2026-05-13 15:17:18', '1'),
('30', '1', '2026', '5', '1', '2026-05-15 12:08:34', '2026-05-15 12:08:34', '1'),
('31', '1', '2026', '5', '1', '2026-05-15 13:53:59', '2026-05-15 13:53:59', '1'),
('32', '1', '2026', '5', '1', '2026-05-16 14:37:42', '2026-05-16 14:37:42', '1'),
('33', '1', '2026', '5', '1', '2026-05-16 15:19:10', '2026-05-16 15:19:10', '1'),
('34', '1', '2026', '5', '1', '2026-05-16 23:36:30', '2026-05-16 23:36:30', '1'),
('35', '1', '2026', '5', '1', '2026-05-18 18:01:45', '2026-05-18 18:01:45', '1'),
('36', '1', '2026', '5', '1', '2026-05-23 06:58:41', '2026-05-23 06:58:41', '1'),
('37', '1', '2026', '5', '1', '2026-05-24 23:54:04', '2026-05-24 23:54:04', '1'),
('38', '1', '2026', '5', '1', '2026-05-25 00:12:46', '2026-05-25 00:12:46', '1'),
('39', '1', '2026', '5', '1', '2026-05-26 16:49:54', '2026-05-26 16:49:54', '1'),
('40', '1', '2026', '5', '1', '2026-05-27 01:08:37', '2026-05-27 01:08:37', '1'),
('41', '1', '2026', '5', '1', '2026-05-27 17:20:01', '2026-05-27 17:20:01', '1'),
('42', '1', '2026', '5', '1', '2026-05-28 09:05:28', '2026-05-28 09:05:28', '1'),
('43', '1', '2026', '5', '1', '2026-05-28 15:39:38', '2026-05-28 15:39:38', '1'),
('44', '1', '2026', '5', '1', '2026-05-28 17:50:40', '2026-05-28 17:50:40', '1'),
('45', '1', '2026', '5', '1', '2026-05-28 17:52:52', '2026-05-28 17:52:52', '1'),
('46', '1', '2026', '5', '1', '2026-05-28 12:39:10', '2026-05-28 12:39:10', '1'),
('47', '1', '2026', '5', '1', '2026-05-28 19:41:02', '2026-05-28 19:41:02', '1'),
('48', '1', '2026', '5', '1', '2026-05-28 20:08:07', '2026-05-28 20:08:07', '1'),
('49', '1', '2026', '5', '1', '2026-05-29 00:08:32', '2026-05-29 00:08:32', '1'),
('50', '1', '2026', '5', '1', '2026-05-29 18:29:49', '2026-05-29 18:29:49', '1'),
('51', '1', '2026', '5', '1', '2026-05-29 19:16:04', '2026-05-29 19:16:04', '1'),
('52', '1', '2026', '5', '1', '2026-05-31 18:42:55', '2026-05-31 18:42:55', '1'),
('53', '1', '2026', '6', '1', '2026-06-01 16:41:50', '2026-06-01 16:41:50', '1'),
('54', '1', '2026', '6', '1', '2026-06-04 12:40:49', '2026-06-04 12:40:49', '1'),
('55', '1', '2026', '6', '1', '2026-06-04 16:08:42', '2026-06-04 16:08:42', '1'),
('56', '1', '2026', '6', '1', '2026-06-04 16:13:18', '2026-06-04 16:13:18', '1'),
('57', '1', '2026', '6', '1', '2026-06-04 16:39:30', '2026-06-04 16:39:30', '1'),
('58', '1', '2026', '6', '1', '2026-06-04 16:43:38', '2026-06-04 16:43:38', '1'),
('59', '1', '2026', '6', '1', '2026-06-04 18:15:00', '2026-06-04 18:15:00', '1'),
('60', '1', '2026', '6', '1', '2026-06-04 18:19:05', '2026-06-04 18:19:05', '1'),
('61', '1', '2026', '6', '1', '2026-06-04 22:54:18', '2026-06-04 22:54:18', '1'),
('62', '1', '2026', '6', '1', '2026-06-06 01:23:10', '2026-06-06 01:23:10', '1'),
('63', '1', '2026', '6', '1', '2026-06-06 02:01:35', '2026-06-06 02:01:35', '1'),
('64', '1', '2026', '6', '1', '2026-06-06 10:48:15', '2026-06-06 10:48:15', '1'),
('65', '1', '2026', '6', '1', '2026-06-06 11:26:17', '2026-06-06 11:26:17', '1'),
('66', '1', '2026', '6', '1', '2026-06-06 14:05:05', '2026-06-06 14:05:05', '1'),
('67', '1', '2026', '6', '1', '2026-06-06 14:54:19', '2026-06-06 14:54:19', '1'),
('68', '1', '2026', '6', '1', '2026-06-06 14:56:10', '2026-06-06 14:56:10', '1'),
('69', '1', '2026', '6', '1', '2026-06-06 15:50:03', '2026-06-06 15:50:03', '1'),
('70', '1', '2026', '6', '1', '2026-06-06 16:18:16', '2026-06-06 16:18:16', '1'),
('71', '1', '2026', '6', '1', '2026-06-10 19:02:04', '2026-06-10 19:02:04', '1'),
('72', '1', '2026', '6', '1', '2026-06-12 23:07:24', '2026-06-12 23:07:24', '1'),
('73', '1', '2026', '6', '1', '2026-06-12 23:34:42', '2026-06-12 23:34:42', '1'),
('74', '1', '2026', '6', '1', '2026-06-12 23:35:34', '2026-06-12 23:35:34', '1'),
('75', '1', '2026', '6', '1', '2026-06-12 23:44:41', '2026-06-12 23:44:41', '1'),
('76', '1', '2026', '6', '1', '2026-06-13 00:14:46', '2026-06-13 00:14:46', '1'),
('77', '1', '2026', '6', '1', '2026-06-13 00:14:53', '2026-06-13 00:14:53', '1'),
('78', '1', '2026', '6', '1', '2026-06-13 00:15:01', '2026-06-13 00:15:01', '1'),
('79', '1', '2026', '6', '1', '2026-06-13 00:19:45', '2026-06-13 00:19:45', '1'),
('80', '1', '2026', '6', '1', '2026-06-13 00:40:43', '2026-06-13 00:40:43', '1'),
('81', '1', '2026', '6', '1', '2026-06-13 00:40:49', '2026-06-13 00:40:49', '1'),
('82', '1', '2026', '6', '1', '2026-06-13 00:41:05', '2026-06-13 00:41:05', '1'),
('83', '1', '2026', '6', '1', '2026-06-13 00:41:31', '2026-06-13 00:41:31', '1'),
('84', '1', '2026', '6', '1', '2026-06-13 01:03:28', '2026-06-13 01:03:28', '1'),
('85', '1', '2026', '6', '1', '2026-06-13 01:04:01', '2026-06-13 01:04:01', '1'),
('86', '1', '2026', '6', '1', '2026-06-13 09:45:11', '2026-06-13 09:45:11', '1'),
('87', '1', '2026', '6', '1', '2026-06-14 01:59:53', '2026-06-14 01:59:53', '1'),
('88', '1', '2026', '6', '1', '2026-06-14 03:30:03', '2026-06-14 03:30:03', '1'),
('89', '1', '2026', '6', '1', '2026-06-15 13:06:26', '2026-06-15 13:06:26', '1'),
('90', '1', '2026', '6', '1', '2026-06-15 13:31:37', '2026-06-15 13:31:37', '1'),
('91', '1', '2026', '6', '1', '2026-06-15 13:51:33', '2026-06-15 13:51:33', '1'),
('92', '1', '2026', '6', '1', '2026-06-15 20:17:47', '2026-06-15 20:17:47', '1'),
('93', '1', '2026', '6', '1', '2026-06-15 20:29:45', '2026-06-15 20:29:45', '1'),
('94', '1', '2026', '6', '1', '2026-06-16 13:32:30', '2026-06-16 13:32:30', '1'),
('95', '1', '2026', '6', '1', '2026-06-16 19:28:58', '2026-06-16 19:28:58', '1'),
('96', '1', '2026', '6', '1', '2026-06-17 03:44:35', '2026-06-17 03:44:35', '1'),
('97', '1', '2026', '6', '1', '2026-06-17 04:04:12', '2026-06-17 04:04:12', '1'),
('98', '1', '2026', '6', '1', '2026-06-19 17:03:19', '2026-06-19 17:03:19', '1'),
('99', '1', '2026', '6', '1', '2026-06-22 06:03:35', '2026-06-22 06:03:35', '1'),
('100', '1', '2026', '6', '1', '2026-06-23 04:55:03', '2026-06-23 04:55:03', '1');

-- ----------------------------
-- Data for table `label_print_logs`
-- ----------------------------
INSERT INTO `label_print_logs` (`id`, `tenant_id`, `branch_id`, `user_id`, `product_data`, `label_size`, `quantity`, `print_method`, `printed_at`) VALUES
('1', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1}]', 'k22', '1', 'browser', '2026-05-24 13:39:04'),
('2', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 13:53:03'),
('3', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 13:53:05'),
('4', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 15:34:21'),
('5', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k36', '8', 'browser', '2026-05-24 15:34:35'),
('6', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 15:34:51'),
('7', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 15:35:05'),
('8', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:04:00'),
('9', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:04:05'),
('10', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:05:41'),
('11', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 16:06:25'),
('12', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:13:01'),
('13', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:13:04'),
('14', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 16:13:50'),
('15', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:45:59'),
('16', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:54:54'),
('17', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 16:54:57'),
('18', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 17:09:01'),
('19', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 17:09:03'),
('20', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 17:09:54'),
('21', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 17:09:56'),
('22', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 18:32:37'),
('23', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 18:32:45'),
('24', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 18:35:34'),
('25', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 18:35:37'),
('26', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 18:56:55'),
('27', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 18:56:58'),
('28', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:04:14'),
('29', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:04:16'),
('30', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:04:22'),
('31', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:04:26'),
('32', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:14:54'),
('33', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:14:56'),
('34', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:18:31'),
('35', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:18:33'),
('36', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K15', '8', 'browser', '2026-05-24 19:21:43'),
('37', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K15', '8', 'browser', '2026-05-24 19:21:44'),
('38', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K15', '8', 'browser', '2026-05-24 19:57:53'),
('39', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K15', '8', 'browser', '2026-05-24 19:57:55'),
('40', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'KA2', '8', 'browser', '2026-05-24 19:58:23'),
('41', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 19:58:41'),
('42', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 19:59:15'),
('43', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 20:07:31'),
('44', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 20:07:33'),
('45', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 20:08:07'),
('46', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 20:18:18'),
('47', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 20:18:19'),
('48', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 20:18:27'),
('49', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 20:18:29'),
('50', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 20:23:39'),
('51', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k22', '8', 'browser', '2026-05-24 20:23:41'),
('52', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 20:24:03'),
('53', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K27', '8', 'browser', '2026-05-24 20:24:24'),
('54', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":60}]', 'K27', '60', 'browser', '2026-05-24 20:25:27'),
('55', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":60}]', 'K27', '60', 'browser', '2026-05-24 20:25:28'),
('56', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":60}]', 'K38', '60', 'browser', '2026-05-24 20:26:32'),
('57', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":60}]', 'k36', '60', 'browser', '2026-05-24 20:26:47'),
('58', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":60}]', 'k5', '60', 'browser', '2026-05-24 20:27:16'),
('59', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1}]', 'k5', '1', 'browser', '2026-05-24 20:54:24'),
('60', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":2}]', 'k5', '2', 'browser', '2026-05-24 20:54:31'),
('61', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":22}]', 'k5', '22', 'browser', '2026-05-24 20:54:31'),
('62', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":22}]', 'k5', '22', 'browser', '2026-05-24 20:54:34'),
('63', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":22}]', 'K11', '22', 'browser', '2026-05-24 20:56:09'),
('64', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 21:00:59'),
('65', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 21:01:09'),
('66', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":10}]', 'K11', '10', 'browser', '2026-05-24 20:47:43'),
('67', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":10}]', 'K11', '10', 'browser', '2026-05-24 20:47:45'),
('68', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":40}]', 'K11', '40', 'browser', '2026-05-24 21:05:38'),
('69', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":40}]', 'K11', '40', 'browser', '2026-05-24 21:05:38'),
('70', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 21:30:56'),
('71', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 21:30:58'),
('72', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 21:49:38'),
('73', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 22:07:07'),
('74', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 22:07:15'),
('75', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 22:08:53'),
('76', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 22:09:42'),
('77', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 22:09:49'),
('78', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 22:21:40'),
('79', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 22:21:50'),
('80', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K05', '8', 'browser', '2026-05-24 22:23:09'),
('81', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 22:23:37'),
('82', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 22:29:17'),
('83', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 23:05:37'),
('84', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 23:05:40'),
('85', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-05-24 23:07:36'),
('86', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 23:08:36'),
('87', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 23:09:21'),
('88', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-24 23:25:13'),
('89', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 18:27:27'),
('90', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 18:27:59'),
('91', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 18:31:42'),
('92', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 18:38:51'),
('93', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '7', 'browser', '2026-05-26 18:39:09'),
('94', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":20}]', 'K11', '27', 'browser', '2026-05-26 18:39:16'),
('95', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":20}]', 'K11', '27', 'browser', '2026-05-26 18:39:18'),
('96', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":20}]', 'K11', '27', 'browser', '2026-05-26 18:41:53'),
('97', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":20}]', 'K11', '27', 'browser', '2026-05-26 18:52:16'),
('98', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 18:53:26'),
('99', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 18:53:33'),
('100', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":5},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '12', 'browser', '2026-05-26 18:53:33'),
('101', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":5},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '12', 'browser', '2026-05-26 18:53:36'),
('102', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":5},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":5},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '16', 'browser', '2026-05-26 18:53:38'),
('103', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":5},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":5},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '16', 'browser', '2026-05-26 18:53:42'),
('104', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":5},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":5},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '16', 'browser', '2026-05-26 18:57:33'),
('105', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 19:04:46'),
('106', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 19:04:49'),
('107', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 19:06:00'),
('108', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 19:41:41'),
('109', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 19:41:43'),
('110', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 20:04:58'),
('111', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 20:05:01'),
('112', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 20:24:40'),
('113', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 21:15:22'),
('114', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-26 21:15:27'),
('115', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-31 18:47:50'),
('116', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-31 18:47:53'),
('117', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-31 18:48:21'),
('118', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-31 19:06:58'),
('119', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":2},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '9', 'browser', '2026-05-31 19:07:03'),
('120', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-31 19:07:05'),
('121', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-05-31 19:07:06'),
('122', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":10},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '17', 'browser', '2026-05-31 19:07:06'),
('123', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":10},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '17', 'browser', '2026-05-31 19:07:09'),
('124', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":10},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '17', 'browser', '2026-05-31 19:07:09'),
('125', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":10},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":10},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '26', 'browser', '2026-05-31 19:07:09'),
('126', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":10},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":10},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '26', 'browser', '2026-05-31 19:07:13'),
('127', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-06-06 00:42:00'),
('128', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-06-06 00:42:17'),
('129', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-06-06 00:42:37'),
('130', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-06-06 00:57:47'),
('131', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-06-06 00:57:58'),
('132', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-06-09 10:43:49'),
('133', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-06-09 10:44:01'),
('134', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-06-09 10:44:19'),
('135', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'k5', '8', 'browser', '2026-06-09 11:03:58'),
('136', '1', '1', '2', '[{\"id\":2,\"name\":\"Baby Blanket\",\"price\":\"1500.00\",\"sku\":\"BABYBLAN-300\",\"qty\":1},{\"id\":3,\"name\":\"Baby Shawl\",\"price\":\"1600.00\",\"sku\":\"BABYSHAW-213\",\"qty\":1},{\"id\":8,\"name\":\"Cement\",\"price\":\"1200.00\",\"sku\":\"CEMENT-667\",\"qty\":1},{\"id\":4,\"name\":\"Cussion packed\",\"price\":\"2300.00\",\"sku\":\"CUSSIONP-746\",\"qty\":1},{\"id\":6,\"name\":\"Flannel Sheets\",\"price\":\"1500.00\",\"sku\":\"FLANNELS-286\",\"qty\":1},{\"id\":1,\"name\":\"Heavy Rompers\",\"price\":\"1500.00\",\"sku\":\"HEAVYROM-590\",\"qty\":1},{\"id\":5,\"name\":\"Mackintosh\",\"price\":\"700.00\",\"sku\":\"MACKINTO-270\",\"qty\":1},{\"id\":7,\"name\":\"Mittens\",\"price\":\"100.00\",\"sku\":\"MITTENS-227\",\"qty\":1}]', 'K11', '8', 'browser', '2026-06-09 11:04:11');

-- ----------------------------
-- Data for table `notification_jobs`
-- ----------------------------
INSERT INTO `notification_jobs` (`id`, `tenant_id`, `type`, `payload`, `status`, `created_at`) VALUES
('1', '1', 'order_placed', '{\"order_id\":2,\"order_number\":\"ORD-0CF59FB9\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":700}', 'pending', '2026-06-22 14:30:17'),
('2', '1', 'order_placed', '{\"order_id\":3,\"order_number\":\"ORD-F1195588\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-22 15:12:23'),
('3', '1', 'order_placed', '{\"order_id\":4,\"order_number\":\"ORD-C395EBFA\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-23 02:13:14'),
('4', '1', 'order_placed', '{\"order_id\":5,\"order_number\":\"ORD-B95AD6E6\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-23 12:26:50'),
('5', '1', 'order_placed', '{\"order_id\":6,\"order_number\":\"ORD-CD794247\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":2300}', 'pending', '2026-06-24 03:52:05'),
('6', '1', 'order_placed', '{\"order_id\":7,\"order_number\":\"ORD-CCF99CA8\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":1500}', 'pending', '2026-06-24 04:01:00'),
('7', '1', 'order_placed', '{\"order_id\":8,\"order_number\":\"ORD-65A17F43\",\"customer\":\"Wycliffe Bunde\",\"phone\":\"0759714022\",\"total\":3900}', 'pending', '2026-06-24 09:30:23');

-- ----------------------------
-- Data for table `online_order_items`
-- ----------------------------
INSERT INTO `online_order_items` (`id`, `order_id`, `product_id`, `product_name`, `qty`, `price`, `created_at`) VALUES
('1', '1', '1', 'Sample Product', '2', '1750.00', '2026-06-24 05:58:18');

-- ----------------------------
-- Data for table `online_orders`
-- ----------------------------
INSERT INTO `online_orders` (`id`, `tenant_id`, `uuid`, `order_number`, `customer_name`, `customer_phone`, `customer_email`, `subtotal`, `tax_amount`, `shipping_amount`, `discount`, `customer_id`, `delivery_address`, `shipping_address`, `billing_address`, `notes`, `payment_method`, `payment_status`, `coupon_code`, `total`, `status`, `items_json`, `paid_amount`, `placed_at`, `shipped_at`, `delivered_at`, `created_at`, `updated_at`) VALUES
('1', '1', 'demo-track-001', 'ORD-20240624-001', 'John Doe', '0712345678', 'john@example.com', '0.00', '0.0000', '250.0000', '0.00', NULL, '123 Kimathi Street, Nairobi', NULL, NULL, 'Please call before delivery.', 'mpesa', 'paid', NULL, '3750.00', 'shipped', '[{\"product_id\":2,\"name\":\"Baby Blanket\",\"price\":1500,\"quantity\":1},{\"product_id\":8,\"name\":\"Cement\",\"price\":1200,\"quantity\":1}]', '0.00', '2026-06-22 05:58:18', '2026-06-23 05:58:18', NULL, '2026-05-17 05:30:32', '2026-06-24 05:58:18'),
('2', '1', 'f877bb96e103ba6df6d1918e', 'ORD-0CF59FB9', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '700.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'cash', 'pending', NULL, '700.00', 'delivered', NULL, '0.00', NULL, NULL, NULL, '2026-06-22 14:30:17', '2026-06-22 14:34:13'),
('3', '1', '94da2d455ac4656c12fa246a', 'ORD-F1195588', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '1500.00', 'delivered', NULL, '0.00', NULL, NULL, NULL, '2026-06-22 15:12:23', '2026-06-23 00:59:31'),
('4', '1', 'd19fadced8bb3553732723e0', 'ORD-C395EBFA', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'card', 'pending', NULL, '1500.00', 'pending', NULL, '0.00', NULL, NULL, NULL, '2026-06-23 02:13:14', '2026-06-23 02:13:14'),
('5', '1', '483a08d9cea83234f47946f8', 'ORD-B95AD6E6', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '1500.00', 'processing', NULL, '0.00', NULL, NULL, NULL, '2026-06-23 12:26:50', '2026-06-23 18:48:44'),
('6', '1', 'eb4d295bfd9374f493ee16b6', 'ORD-CD794247', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '2300.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '2300.00', 'processing', NULL, '0.00', NULL, NULL, NULL, '2026-06-24 03:52:05', '2026-06-24 03:57:02'),
('7', '1', 'aa7de98ec50d4228b6acfec0', 'ORD-CCF99CA8', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '1500.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'cash', 'pending', NULL, '1500.00', 'processing', NULL, '0.00', NULL, NULL, NULL, '2026-06-24 04:01:00', '2026-06-24 06:20:31'),
('8', '1', '4a8b89ae425223cd8ae0336a', 'ORD-65A17F43', 'Wycliffe Bunde', '0759714022', 'wickymacochiz80@gmail.com', '3900.00', '0.0000', '0.0000', '0.00', NULL, '40111', NULL, NULL, '', 'mpesa', 'pending', NULL, '3900.00', 'pending', NULL, '0.00', NULL, NULL, NULL, '2026-06-24 09:30:22', '2026-06-24 09:30:23');

-- ----------------------------
-- Data for table `order_status_history`
-- ----------------------------
INSERT INTO `order_status_history` (`id`, `tenant_id`, `order_id`, `from_status`, `to_status`, `changed_by`, `changed_by_type`, `notes`, `metadata`, `created_at`) VALUES
('1', '1', '1', 'pending', 'pending', NULL, 'system', 'Order placed by customer', NULL, '2026-06-22 05:58:18'),
('2', '1', '1', 'pending', 'confirmed', NULL, 'system', 'Payment confirmed via M-Pesa', NULL, '2026-06-22 06:58:18'),
('3', '1', '1', 'pending', 'processing', NULL, 'system', 'Order being prepared for dispatch', NULL, '2026-06-23 05:58:18'),
('4', '1', '1', 'pending', 'shipped', NULL, 'system', 'Order handed to courier', NULL, '2026-06-23 06:58:18');

-- ----------------------------
-- Data for table `payments`
-- ----------------------------
INSERT INTO `payments` (`id`, `sale_id`, `method`, `amount`, `status`, `created_at`, `tenant_id`) VALUES
('1', '84', 'cash', '1740.00', 'paid', '2026-06-13 01:03:28', '1'),
('2', '85', 'cash', '1740.00', 'paid', '2026-06-13 01:04:01', '1'),
('3', '86', 'cash', '3480.00', 'paid', '2026-06-13 09:45:11', '1'),
('4', '87', 'cash', '2668.00', 'paid', '2026-06-14 01:59:53', '1'),
('5', '88', 'cash', '2668.00', 'paid', '2026-06-14 03:30:03', '1'),
('6', '89', 'cash', '2668.00', 'paid', '2026-06-15 13:06:26', '1'),
('7', '90', 'cash', '1392.00', 'paid', '2026-06-15 13:31:37', '1'),
('8', '91', 'cash', '1740.00', 'paid', '2026-06-15 13:51:33', '1'),
('9', '92', 'mpesa', '2552.00', 'paid', '2026-06-15 20:17:47', '1'),
('10', '93', 'card', '3480.00', 'paid', '2026-06-15 20:29:45', '1'),
('11', '94', 'card', '2668.00', 'paid', '2026-06-16 13:32:30', '1'),
('12', '96', 'cash', '1740.00', 'paid', '2026-06-17 03:44:35', '1'),
('13', '97', 'cash', '812.00', 'paid', '2026-06-17 04:04:13', '1'),
('14', '98', 'cash', '1740.00', 'paid', '2026-06-19 17:03:20', '1'),
('15', '99', 'card', '6612.00', 'paid', '2026-06-22 06:03:36', '1'),
('16', '100', 'cash', '1856.00', 'paid', '2026-06-23 04:55:03', '1');

-- ----------------------------
-- Data for table `permissions`
-- ----------------------------
INSERT INTO `permissions` (`id`, `code`, `description`, `module`, `tenant_id`, `category`, `deleted_at`) VALUES
('1', 'pos.view', 'Access the POS screen', 'pos', '1', 'pos', NULL),
('2', 'pos.sell', 'Create sales transactions', 'pos', '1', 'pos', NULL),
('3', 'pos.refund', 'Issue refunds and returns', 'pos', '1', 'pos', NULL),
('4', 'pos.discount', 'Apply discounts at checkout', 'pos', '1', 'pos', NULL),
('5', 'pos.hold', 'Park and retrieve held orders', 'pos', '1', 'pos', NULL),
('6', 'pos.order', 'Create and manage customer orders', 'pos', '1', 'pos', NULL),
('7', 'pos.split', 'Split bill between customers', 'pos', '1', 'pos', NULL),
('8', 'pos.table', 'Manage restaurant tables', 'pos', '1', 'pos', NULL),
('9', 'pos.kitchen', 'Send and manage kitchen orders', 'pos', '1', 'pos', NULL),
('10', 'sales.view', 'Access sales history', 'sales', '1', 'sales', NULL),
('11', 'sales.manage', 'Edit and void sales', 'sales', '1', 'sales', NULL),
('12', 'sales.export', 'Export sales data', 'sales', '1', 'sales', NULL),
('13', 'sales.returns', 'Handle product returns', 'sales', '1', 'sales', NULL),
('14', 'products.view', 'Browse product catalog', 'products', '1', 'products', NULL),
('15', 'products.create', 'Add new products', 'products', '1', 'products', NULL),
('16', 'products.edit', 'Modify product details', 'products', '1', 'products', NULL),
('17', 'products.delete', 'Remove products from catalog', 'products', '1', 'products', NULL),
('18', 'products.import', 'Bulk import products', 'products', '1', 'products', NULL),
('19', 'products.categories', 'Organize product categories', 'products', '1', 'products', NULL),
('20', 'products.pricing', 'Set product prices and tiers', 'products', '1', 'products', NULL),
('21', 'inventory.view', 'Check stock levels', 'inventory', '1', 'inventory', NULL),
('22', 'inventory.adjust', 'Manual stock adjustments', 'inventory', '1', 'inventory', NULL),
('23', 'inventory.transfer', 'Transfer stock between locations', 'inventory', '1', 'inventory', NULL),
('24', 'inventory.count', 'Perform stock takes', 'inventory', '1', 'inventory', NULL),
('25', 'inventory.reorder', 'Set reorder thresholds', 'inventory', '1', 'inventory', NULL),
('26', 'inventory.alerts', 'View and manage stock alerts', 'inventory', '1', 'inventory', NULL),
('27', 'customers.view', 'Access customer list', 'customers', '1', 'customers', NULL),
('28', 'customers.create', 'Add new customers', 'customers', '1', 'customers', NULL),
('29', 'customers.edit', 'Update customer information', 'customers', '1', 'customers', NULL),
('30', 'customers.delete', 'Remove customer records', 'customers', '1', 'customers', NULL),
('31', 'customers.loyalty', 'Manage loyalty points and rewards', 'customers', '1', 'customers', NULL),
('32', 'customers.credit', 'Manage customer credit accounts', 'customers', '1', 'customers', NULL),
('33', 'users.view', 'Access staff/user list', 'users', '1', 'users', NULL),
('34', 'users.create', 'Add new staff members', 'users', '1', 'users', NULL),
('35', 'users.edit', 'Update user information', 'users', '1', 'users', NULL),
('36', 'users.delete', 'Remove user accounts', 'users', '1', 'users', NULL),
('37', 'users.roles', 'Create and assign roles', 'users', '1', 'users', NULL),
('38', 'users.attendance', 'Track staff attendance', 'users', '1', 'users', NULL),
('39', 'reports.view', 'Access all reports', 'reports', '1', 'reports', NULL),
('40', 'reports.sales', 'View sales analytics', 'reports', '1', 'reports', NULL),
('41', 'reports.inventory', 'View stock and inventory reports', 'reports', '1', 'reports', NULL),
('42', 'reports.financial', 'View profit and loss statements', 'reports', '1', 'reports', NULL),
('43', 'reports.profit', 'View profit margins by product', 'reports', '1', 'reports', NULL),
('44', 'reports.tax', 'View tax collection summaries', 'reports', '1', 'reports', NULL),
('45', 'reports.export', 'Export report data', 'reports', '1', 'reports', NULL),
('46', 'reports.expense', 'Track and report expenses', 'reports', '1', 'reports', NULL),
('47', 'reports.dashboard', 'View main dashboard analytics', 'reports', '1', 'reports', NULL),
('48', 'expenses.view', 'Access expense records', 'expenses', '1', 'expenses', NULL),
('49', 'expenses.create', 'Record new expenses', 'expenses', '1', 'expenses', NULL),
('50', 'expenses.edit', 'Modify expense entries', 'expenses', '1', 'expenses', NULL),
('51', 'expenses.categories', 'Manage expense categories', 'expenses', '1', 'expenses', NULL),
('52', 'expenses.approve', 'Approve pending expenses', 'expenses', '1', 'expenses', NULL),
('53', 'settings.view', 'Access system settings', 'settings', '1', 'settings', NULL),
('54', 'settings.general', 'Manage store information', 'settings', '1', 'settings', NULL),
('55', 'settings.payment', 'Configure payment options', 'settings', '1', 'settings', NULL),
('56', 'settings.tax', 'Manage tax rates and rules', 'settings', '1', 'settings', NULL),
('57', 'settings.receipt', 'Customize receipt templates', 'settings', '1', 'settings', NULL),
('58', 'settings.backup', 'Manage system backups', 'settings', '1', 'settings', NULL),
('59', 'settings.api', 'Manage API keys and webhooks', 'settings', '1', 'settings', NULL),
('60', 'branch.view', 'Access branch locations', 'branch', '1', 'branch', NULL),
('61', 'branch.create', 'Add new branch locations', 'branch', '1', 'branch', NULL),
('62', 'branch.manage', 'Edit branch settings', 'branch', '1', 'branch', NULL),
('63', 'branch.transfer', 'Transfer between branches', 'branch', '1', 'branch', NULL),
('64', 'branch.reports', 'View branch performance', 'branch', '1', 'branch', NULL),
('65', 'kitchen.view', 'Access kitchen display', 'kitchen', '1', 'kitchen', NULL),
('66', 'kitchen.send', 'Submit orders to kitchen', 'kitchen', '1', 'kitchen', NULL),
('67', 'kitchen.ready', 'Mark items as ready', 'kitchen', '1', 'kitchen', NULL),
('68', 'kitchen.manage', 'Manage kitchen workflow', 'kitchen', '1', 'kitchen', NULL),
('69', 'delivery.view', 'Access delivery orders', 'delivery', '1', 'delivery', NULL),
('70', 'delivery.assign', 'Assign riders to orders', 'delivery', '1', 'delivery', NULL),
('71', 'delivery.update', 'Update delivery status', 'delivery', '1', 'delivery', NULL),
('72', 'delivery.track', 'Track live delivery status', 'delivery', '1', 'delivery', NULL),
('73', 'orders.view', 'Access all orders', 'orders', '1', 'orders', NULL),
('74', 'orders.dispatch', 'Dispatch orders for delivery', 'orders', '1', 'orders', NULL),
('75', 'orders.cancel', 'Cancel pending orders', 'orders', '1', 'orders', NULL),
('76', 'orders.modify', 'Edit existing orders', 'orders', '1', 'orders', NULL),
('77', 'dispatch.view', 'Access dispatch board', 'dispatch', '1', 'dispatch', NULL),
('78', 'dispatch.assign', 'Assign dispatch tasks', 'dispatch', '1', 'dispatch', NULL),
('79', 'dispatch.route', 'Plan delivery routes', 'dispatch', '1', 'dispatch', NULL),
('80', 'attendance.view', 'Check staff attendance', 'attendance', '1', 'attendance', NULL),
('81', 'attendance.clock', 'Record attendance', 'attendance', '1', 'attendance', NULL),
('82', 'attendance.manage', 'Edit attendance records', 'attendance', '1', 'attendance', NULL),
('83', 'attendance.leave', 'Manage leave applications', 'attendance', '1', 'attendance', NULL),
('84', 'dashboard.view', 'View Dashboard', 'core', '1', 'general', NULL),
('85', 'pos.access', 'Access Point of Sale', 'core', '1', 'general', NULL),
('86', 'sales.create', 'Create Sales', 'core', '1', 'general', NULL),
('93', 'profile.view', 'View Profile', 'core', '1', 'general', NULL),
('94', 'profile.edit', 'Edit Profile', 'core', '1', 'general', NULL),
('99', 'sales.edit', 'Edit Sales', 'core', '1', 'general', NULL),
('100', 'sales.delete', 'Delete Sales', 'core', '1', 'general', NULL),
('102', 'sales.refunds', 'Process Refunds', 'core', '1', 'general', NULL),
('103', 'sales.discounts', 'Manage Discounts', 'core', '1', 'general', NULL),
('104', 'sales.vouchers', 'Manage Vouchers', 'core', '1', 'general', NULL),
('109', 'products.manage', 'Manage Product Attributes', 'core', '1', 'general', NULL),
('110', 'products.export', 'Export Products', 'core', '1', 'general', NULL),
('112', 'brands.view', 'View Brands', 'core', '1', 'general', NULL),
('113', 'brands.create', 'Create Brands', 'core', '1', 'general', NULL),
('114', 'brands.edit', 'Edit Brands', 'core', '1', 'general', NULL),
('115', 'brands.delete', 'Delete Brands', 'core', '1', 'general', NULL),
('116', 'categories.view', 'View Categories', 'core', '1', 'general', NULL),
('117', 'categories.create', 'Create Categories', 'core', '1', 'general', NULL),
('118', 'categories.edit', 'Edit Categories', 'core', '1', 'general', NULL),
('119', 'categories.delete', 'Delete Categories', 'core', '1', 'general', NULL),
('121', 'inventory.manage', 'Manage Inventory', 'core', '1', 'general', NULL),
('124', 'inventory.expiry', 'Manage Expiry Tracking', 'core', '1', 'general', NULL),
('125', 'suppliers.view', 'View Suppliers', 'core', '1', 'general', NULL),
('126', 'suppliers.create', 'Create Suppliers', 'core', '1', 'general', NULL),
('127', 'suppliers.edit', 'Edit Suppliers', 'core', '1', 'general', NULL),
('128', 'suppliers.delete', 'Delete Suppliers', 'core', '1', 'general', NULL),
('133', 'customers.bulk_sms', 'Send Bulk SMS', 'core', '1', 'general', NULL),
('134', 'purchases.view', 'View Purchases', 'core', '1', 'general', NULL),
('135', 'purchases.create', 'Create Purchases', 'core', '1', 'general', NULL),
('136', 'purchases.edit', 'Edit Purchases', 'core', '1', 'general', NULL),
('137', 'purchases.approve', 'Approve Purchases', 'core', '1', 'general', NULL),
('138', 'shipments.view', 'View Shipments', 'core', '1', 'general', NULL),
('139', 'shipments.create', 'Create Shipments', 'core', '1', 'general', NULL),
('140', 'shipments.edit', 'Edit Shipments', 'core', '1', 'general', NULL),
('141', 'shipments.receive', 'Receive Shipments', 'core', '1', 'general', NULL),
('151', 'users.manage', 'Manage Users', 'core', '1', 'general', NULL),
('152', 'roles.view', 'View Roles', 'core', '1', 'general', NULL),
('153', 'roles.create', 'Create Roles', 'core', '1', 'general', NULL),
('154', 'roles.edit', 'Edit Roles', 'core', '1', 'general', NULL),
('155', 'roles.delete', 'Delete Roles', 'core', '1', 'general', NULL),
('156', 'roles.assign', 'Assign Roles', 'core', '1', 'general', NULL),
('157', 'branches.view', 'View Branches', 'core', '1', 'general', NULL),
('158', 'branches.create', 'Create Branches', 'core', '1', 'general', NULL),
('159', 'branches.edit', 'Edit Branches', 'core', '1', 'general', NULL),
('160', 'branches.delete', 'Delete Branches', 'core', '1', 'general', NULL),
('161', 'branches.manage', 'Manage Branches', 'core', '1', 'general', NULL),
('162', 'system.settings', 'Access System Settings', 'core', '1', 'general', NULL),
('163', 'system.backup', 'Perform Backup', 'core', '1', 'general', NULL),
('164', 'system.restore', 'Perform Restore', 'core', '1', 'general', NULL),
('165', 'system.logs', 'View System Logs', 'core', '1', 'general', NULL),
('166', 'system.maintenance', 'System Maintenance', 'core', '1', 'general', NULL),
('167', 'billing.view', 'View Billing', 'core', '1', 'general', NULL),
('168', 'billing.manage', 'Manage Billing', 'core', '1', 'general', NULL),
('169', 'billing.settings', 'Billing Settings', 'core', '1', 'general', NULL),
('170', 'crm.view', 'Access CRM', 'core', '1', 'general', NULL),
('171', 'crm.leads', 'Manage CRM Leads', 'core', '1', 'general', NULL),
('172', 'crm.deals', 'Manage CRM Deals', 'core', '1', 'general', NULL),
('173', 'hr.view', 'Access HR', 'core', '1', 'general', NULL),
('174', 'hr.employees', 'Manage Employees', 'core', '1', 'general', NULL),
('175', 'hr.leaves', 'Manage Leave Requests', 'core', '1', 'general', NULL),
('176', 'hr.payroll', 'Manage Payroll', 'core', '1', 'general', NULL),
('177', 'loyalty.view', 'View Loyalty Program', 'core', '1', 'general', NULL),
('178', 'loyalty.manage', 'Manage Loyalty Program', 'core', '1', 'general', NULL),
('179', 'marketing.sms', 'Send Marketing SMS', 'core', '1', 'general', NULL),
('180', 'marketing.email', 'Send Marketing Emails', 'core', '1', 'general', NULL),
('183', 'quotations.view', 'View Quotations', 'core', '1', 'general', NULL),
('184', 'quotations.create', 'Create Quotations', 'core', '1', 'general', NULL),
('185', 'quotations.edit', 'Edit Quotations', 'core', '1', 'general', NULL),
('186', 'quotations.delete', 'Delete Quotations', 'core', '1', 'general', NULL),
('190', 'expenses.delete', 'Delete Expenses', 'core', '1', 'general', NULL);

-- ----------------------------
-- Data for table `plans`
-- ----------------------------
INSERT INTO `plans` (`id`, `name`, `slug`, `description`, `price`, `billing_cycle`, `currency`, `max_users`, `max_branches`, `max_products`, `max_storage_mb`, `trial_days`, `is_active`, `is_popular`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', 'Free', 'free', 'Perfect for getting started', '0.00', 'monthly', 'KES', '2', '1', '50', '100', '0', '1', '0', '1', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('2', 'Basic', 'basic', 'Essential features for growing businesses', '999.00', 'monthly', 'KES', '10', '2', '1000', '2000', '14', '1', '0', '2', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('3', 'Professional', 'professional', 'For growing businesses', '2499.00', 'monthly', 'KES', '15', '5', '1000', '2000', '14', '1', '1', '3', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('4', 'Enterprise', 'enterprise', 'For large organizations', '4999.00', 'monthly', 'KES', '999', '999', '99999', '10000', '30', '1', '0', '4', '2026-04-30 22:05:32', '2026-04-30 22:05:32'),
('5', 'Starter', 'starter', 'For small businesses', '999.00', 'monthly', 'KES', '5', '2', '200', '500', '14', '1', '0', '2', '2026-04-30 22:05:32', '2026-04-30 22:05:32');

-- ----------------------------
-- Data for table `platform_features`
-- ----------------------------
INSERT INTO `platform_features` (`id`, `slug`, `name`, `category`, `default_limit`, `default_unit`, `is_beta`, `description`, `created_at`, `updated_at`) VALUES
('1', 'pos', 'Point of Sale', 'core', NULL, NULL, '0', 'Core POS functionality', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('2', 'ecommerce', 'E-commerce', 'core', NULL, NULL, '0', 'Online store', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('3', 'inventory', 'Inventory Management', 'core', NULL, NULL, '0', 'Stock tracking and management', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('4', 'analytics', 'Advanced Analytics', 'add_on', NULL, NULL, '0', 'Dashboards and reports', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('5', 'api_access', 'API Access', 'add_on', '1000', 'calls/day', '0', 'REST API access', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('6', 'multi_branch', 'Multi-Branch', 'core', '1', 'branches', '0', 'Number of branches allowed', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('7', 'users', 'Users', 'core', '5', 'users', '0', 'Number of staff users', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('8', 'products', 'Products', 'core', '100', 'products', '0', 'Number of products allowed', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('9', 'webhooks', 'Webhooks', 'add_on', '5', 'endpoints', '0', 'Outgoing webhook subscriptions', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('10', 'white_label', 'White Label', 'enterprise', NULL, NULL, '0', 'Custom branding and domains', '2026-05-18 08:41:28', '2026-05-18 08:41:28');

-- ----------------------------
-- Data for table `pos_business_features`
-- ----------------------------
INSERT INTO `pos_business_features` (`id`, `business_type_id`, `feature_key`, `is_enabled`, `config`, `created_at`, `updated_at`) VALUES
('1', '1', 'barcode', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('2', '1', 'stock', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('3', '2', 'barcode', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('4', '2', 'stock', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('5', '2', 'weight', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('6', '3', 'kitchen_notes', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('7', '3', 'table', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('8', '4', 'prescription', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('9', '4', 'stock', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('10', '5', 'appointment', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('11', '6', 'serial', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('12', '6', 'warranty', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('13', '7', 'barcode', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('14', '7', 'stock', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('15', '7', 'weight', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('16', '8', 'weight', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('17', '8', 'stock', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('18', '9', 'expiry', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('19', '9', 'stock', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('20', '10', 'room_charge', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('21', '11', 'bulk', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('22', '11', 'discount', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('23', '12', 'appointment', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('24', '13', 'barcode', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('25', '13', 'stock', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('26', '13', 'weight', '1', NULL, '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('27', '14', 'barcode_scanning', '1', NULL, '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('28', '14', 'track_stock', '1', NULL, '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('29', '14', 'age_verification', '1', NULL, '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('30', '14', 'bulk_pricing', '1', NULL, '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('31', '15', 'barcode_scanning', '1', NULL, '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('32', '15', 'track_stock', '1', NULL, '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('33', '15', 'bulk_pricing', '1', NULL, '2026-05-16 14:07:58', '2026-05-16 14:07:58');

-- ----------------------------
-- Data for table `pos_business_types`
-- ----------------------------
INSERT INTO `pos_business_types` (`id`, `slug`, `name`, `icon`, `color`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', 'retail', 'Retail Store', 'fa-store', '#3B82F6', 'General retail store', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('2', 'supermarket', 'Supermarket', 'fa-shopping-cart', '#10B981', 'Large retail store with multiple categories', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('3', 'restaurant', 'Restaurant / Cafe', 'fa-utensils', '#F59E0B', 'Food service establishment', '1', '2', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('4', 'pharmacy', 'Pharmacy', 'fa-pills', '#EF4444', 'Pharmaceutical and medical supplies', '1', '3', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('5', 'salon', 'Salon / Beauty', 'fa-cut', '#EC4899', 'Beauty and personal care services', '1', '4', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('6', 'electronics', 'Electronics', 'fa-laptop', '#8B5CF6', 'Electronic devices and accessories', '1', '5', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('7', 'hardware', 'Hardware Store', 'fa-hammer', '#F97316', 'Construction and home improvement supplies', '1', '6', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('8', 'butchery', 'Butchery', 'fa-drumstick-bite', '#DC2626', 'Fresh meat and poultry', '1', '7', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('9', 'bakery', 'Bakery', 'fa-bread-slice', '#D97706', 'Fresh baked goods', '1', '8', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('10', 'hotel', 'Hotel / Lodging', 'fa-hotel', '#6366F1', 'Hospitality and accommodation', '1', '9', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('11', 'wholesale', 'Wholesale', 'fa-boxes', '#06B6D4', 'Bulk sales and distribution', '0', '10', '2026-05-02 18:14:36', '2026-05-16 14:07:58'),
('12', 'service', 'Service Business', 'fa-concierge-bell', '#14B8A6', 'Professional services', '0', '11', '2026-05-02 18:14:36', '2026-05-16 14:07:58'),
('13', 'grocery', 'Grocery Store', 'fa-apple-alt', '#22C55E', 'Food and household items', '0', '12', '2026-05-02 18:14:36', '2026-05-16 14:07:58'),
('14', 'liquor_store', 'Liquor Store', 'fa-wine-bottle', '#7C3AED', 'Alcohol and beverage retail', '1', '10', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('15', 'stationery', 'Stationery / Office', 'fa-pencil-alt', '#0EA5E9', 'Office and school supplies', '1', '11', '2026-05-16 14:07:58', '2026-05-16 14:07:58');

-- ----------------------------
-- Data for table `pos_landing_blocks`
-- ----------------------------
INSERT INTO `pos_landing_blocks` (`id`, `section`, `block_key`, `title`, `subtitle`, `content`, `image_url`, `icon_class`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
('1', 'products', 'pos', 'Point of Sale', 'POS', 'Fast checkout with offline capability, barcode scanning, split payments, and multi-currency support. Works seamlessly even without internet and syncs when back online.', 'assets/img/pos-preview.png', 'fas fa-cash-register', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('2', 'products', 'erp', 'Inventory & ERP', 'ERP', 'Complete inventory management with stock tracking, purchase orders, supplier management, and multi-branch support. Never run out of stock again.', 'assets/img/erp-preview.png', 'fas fa-boxes-stacked', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('3', 'products', 'ecommerce', 'Online Store', 'Store', 'Launch your online storefront with product catalogs, customer accounts, secure checkout, and order tracking. Syncs with your POS inventory in real-time.', 'assets/img/store-preview.png', 'fas fa-store', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('4', 'products', 'ai', 'AI Assistant', 'AI', 'Smart ordering suggestions, predictive analytics, customer insights, and automated reports. Let AI handle the heavy lifting while you focus on growth.', 'assets/img/ai-preview.png', 'fas fa-robot', '4', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('5', 'how_it_works', 'signup', 'Create Account', NULL, 'Sign up in under 2 minutes with your business name and email. No credit card required to start your free trial.', NULL, 'fas fa-user-plus', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('6', 'how_it_works', 'setup', 'Setup Your Store', NULL, 'Add your products, set up branches, and configure your settings. Import existing data via CSV with one click.', NULL, 'fas fa-cogs', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('7', 'how_it_works', 'sell', 'Start Selling', NULL, 'Process sales, manage inventory, and serve customers online or in-store. Track everything from one dashboard.', NULL, 'fas fa-chart-line', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('8', 'trust_strip', 'secure', 'Bank-Level Security', NULL, NULL, NULL, 'fas fa-shield-alt', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('9', 'trust_strip', 'offline', 'Offline Capable', NULL, NULL, NULL, 'fas fa-wifi', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('10', 'trust_strip', 'support', '24/7 Support', NULL, NULL, NULL, 'fas fa-headset', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('11', 'ai_slides', 'smart_ordering', 'Smart Ordering', NULL, 'AI analyzes your sales patterns and automatically suggests optimal reorder quantities. Never overstock or understock again.', NULL, 'fas fa-wand-magic-sparkles', '1', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('12', 'ai_slides', 'insights', 'Customer Insights', NULL, 'Understand your best customers, peak hours, and top products. Make data-driven decisions with AI-powered analytics.', NULL, 'fas fa-brain', '2', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56'),
('13', 'ai_slides', 'forecasting', 'Sales Forecasting', NULL, 'Predict future sales trends based on historical data, seasonality, and market patterns. Plan ahead with confidence.', NULL, 'fas fa-chart-line', '3', '1', '2026-06-22 16:25:56', '2026-06-22 16:25:56');

-- ----------------------------
-- Data for table `pos_newsletter_subscribers`
-- ----------------------------
INSERT INTO `pos_newsletter_subscribers` (`id`, `email`, `source`, `subscribed_at`, `is_active`) VALUES
('1', 'shacazbabyandmother@gmail.com', 'landing_page', '2026-06-22 15:03:37', '1');

-- ----------------------------
-- Data for table `pos_order_types`
-- ----------------------------
INSERT INTO `pos_order_types` (`id`, `business_type_id`, `slug`, `label`, `icon`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', '1', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('2', '2', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('3', '2', 'online', 'Online', 'fa-globe', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('4', '3', 'dinein', 'Dine-in', 'fa-chair', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('5', '3', 'takeaway', 'Takeaway', 'fa-bag', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('6', '3', 'delivery', 'Delivery', 'fa-truck', '1', '2', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('7', '4', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('8', '4', 'prescription', 'Prescription', 'fa-file-medical', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('9', '5', 'appointment', 'Appointment', 'fa-calendar', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('10', '5', 'walkin', 'Walk-in', 'fa-user', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('11', '6', 'retail', 'Retail', 'fa-store', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('12', '6', 'installment', 'Installment', 'fa-calendar-check', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('13', '7', 'retail', 'Retail', 'fa-store', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('14', '7', 'wholesale', 'Wholesale', 'fa-boxes', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('15', '8', 'retail', 'Retail', 'fa-store', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('16', '8', 'wholesale', 'Wholesale', 'fa-boxes', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('17', '9', 'dinein', 'Dine-in', 'fa-chair', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('18', '9', 'takeaway', 'Takeaway', 'fa-bag', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('19', '9', 'delivery', 'Delivery', 'fa-truck', '1', '2', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('20', '10', 'room', 'Room Service', 'fa-bed', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('21', '10', 'dinein', 'Restaurant', 'fa-utensils', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('22', '11', 'wholesale', 'Wholesale', 'fa-boxes', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('23', '11', 'online', 'Online', 'fa-globe', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('24', '12', 'service', 'Service', 'fa-hand-sparkles', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('25', '12', 'appointment', 'Appointment', 'fa-calendar', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('26', '13', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('27', '13', 'online', 'Online', 'fa-globe', '1', '1', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('28', '13', 'delivery', 'Delivery', 'fa-truck', '1', '2', '2026-05-02 18:14:36', '2026-05-02 18:14:36'),
('29', '14', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('30', '14', 'wholesale', 'Wholesale', 'fa-boxes', '1', '1', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('31', '14', 'delivery', 'Delivery', 'fa-truck', '1', '2', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('32', '15', 'walkin', 'Walk-in', 'fa-user', '1', '0', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('33', '15', 'online', 'Online', 'fa-globe', '1', '1', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('34', '15', 'wholesale', 'Wholesale', 'fa-boxes', '1', '2', '2026-05-16 14:07:58', '2026-05-16 14:07:58'),
('35', '15', 'delivery', 'Delivery', 'fa-truck', '1', '3', '2026-05-16 14:07:58', '2026-05-16 14:07:58');

-- ----------------------------
-- Data for table `pos_tenants`
-- ----------------------------
INSERT INTO `pos_tenants` (`id`, `uuid`, `subdomain`, `domain`, `name`, `slug`, `status`, `is_suspended`, `plan_id`, `parent_tenant_id`, `settings`, `branding`, `created_at`, `updated_at`, `stripe_customer_id`) VALUES
('1', 'cf7718fa-44be-11f1-80fc-14abc50b6cdc', 'demo', NULL, 'JAKPOS', 'demo', 'active', '0', '1', NULL, NULL, NULL, '2026-04-30 22:14:20', '2026-05-16 23:36:04', NULL);

-- ----------------------------
-- Data for table `product_attributes`
-- ----------------------------
INSERT INTO `product_attributes` (`id`, `product_id`, `attribute_name`, `attribute_value`, `visible`, `position`, `used_for_variations`, `created_at`, `updated_at`, `tenant_id`) VALUES
('3', NULL, 'color', 'Blue', '1', '0', '0', '2026-05-18 21:12:54', '2026-05-18 21:12:54', '1');

-- ----------------------------
-- Data for table `product_tag_relations`
-- ----------------------------
INSERT INTO `product_tag_relations` (`id`, `tenant_id`, `product_id`, `tag_id`, `created_at`) VALUES
('1', '1', '2', '1', '2026-05-18 17:06:14'),
('2', '1', '3', '1', '2026-05-18 17:06:14'),
('3', '1', '1', '1', '2026-05-18 17:06:14'),
('4', '1', '4', '1', '2026-06-09 12:02:23');

-- ----------------------------
-- Data for table `product_tags`
-- ----------------------------
INSERT INTO `product_tags` (`id`, `tenant_id`, `name`, `slug`, `color`, `icon`, `description`, `business_type`, `business_type_id`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
('1', '1', 'Best Seller', 'best-seller', '#3B82F6', 'fa-tag', '', 'supermarket', '2', '1', '0', '2', '2026-05-18 17:05:34', '2026-05-18 17:05:34', NULL);

-- ----------------------------
-- Data for table `products`
-- ----------------------------
INSERT INTO `products` (`id`, `business_type`, `business_type_id`, `category_id`, `brand_id`, `name`, `sku`, `global_unique_id`, `barcode`, `price`, `sale_price`, `sale_price_dates_from`, `sale_price_dates_to`, `selling_price`, `cost_price`, `active`, `image`, `description`, `external_url`, `button_text`, `created_by`, `updated_by`, `created_at`, `updated_at`, `status`, `tax_rate_id`, `branch_id`, `deleted_at`, `deleted_by`, `unit`, `tax_rate`, `reorder_level`, `stock_status`, `manage_stock`, `backorders`, `low_stock_amount`, `sold_individually`, `weight`, `length`, `width`, `height`, `shipping_class`, `purchase_note`, `menu_order`, `enable_reviews`, `product_type`, `virtual`, `downloadable`, `tenant_id`) VALUES
('1', NULL, '2', '1', NULL, 'Heavy Rompers', 'HEAVYROM-590', NULL, '', '1500.00', NULL, NULL, NULL, NULL, '700.00', '1', 'uploads/product_images/prod_1782128909_6a39210d57896.png', 'Very Soft', NULL, NULL, NULL, NULL, '2026-05-01 13:38:35', '2026-06-22 14:48:29', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('2', NULL, '2', '1', NULL, 'Baby Blanket', 'BABYBLAN-300', NULL, '', '1500.00', NULL, NULL, NULL, NULL, '699.98', '1', 'uploads/product_images/prod_1779471103_6a1092ff1677d.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:14:16', '2026-05-22 17:31:43', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('3', NULL, '2', '1', NULL, 'Baby Shawl', 'BABYSHAW-213', NULL, '', '1600.00', NULL, NULL, NULL, NULL, '749.98', '1', 'uploads/product_images/prod_1782128882_6a3920f23858f.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:16:38', '2026-06-22 14:48:02', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('4', NULL, '2', '1', NULL, 'Cussion packed', 'CUSSIONP-746', NULL, '', '2300.00', NULL, NULL, NULL, NULL, '1200.00', '1', 'uploads/product_images/prod_1782128786_6a3920921ee68.png', '', NULL, NULL, NULL, NULL, '2026-05-12 23:17:47', '2026-06-22 14:46:26', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('5', NULL, '2', '1', NULL, 'Mackintosh', 'MACKINTO-270', NULL, '', '700.00', NULL, NULL, NULL, NULL, '300.00', '1', 'uploads/product_images/prod_1780311487_6a1d65bf4f8a7.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:18:49', '2026-06-01 13:58:07', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('6', NULL, '2', '1', NULL, 'Flannel Sheets', 'FLANNELS-286', NULL, '', '1500.00', NULL, NULL, NULL, NULL, '560.00', '1', 'uploads/product_images/prod_1780311343_6a1d652f98549.png', '', NULL, NULL, NULL, NULL, '2026-05-12 23:21:23', '2026-06-01 13:55:43', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('7', NULL, '2', '1', NULL, 'Mittens', 'MITTENS-227', NULL, '', '100.00', NULL, NULL, NULL, NULL, '65.00', '1', 'uploads/product_images/prod_1779470176_6a108f606abf1.jpg', '', NULL, NULL, NULL, NULL, '2026-05-12 23:21:55', '2026-05-22 17:16:16', '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '5', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1'),
('8', NULL, '7', NULL, NULL, 'Cement', 'CEMENT-667', NULL, '', '1200.00', NULL, NULL, NULL, NULL, '650.00', '1', NULL, '', NULL, NULL, NULL, NULL, '2026-05-13 11:22:24', NULL, '1', NULL, NULL, NULL, NULL, 'pcs', '0.00', '0', 'instock', '0', 'no', NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, '0', '1', 'simple', '0', '0', '1');

-- ----------------------------
-- Data for table `purchase_order_items`
-- ----------------------------
INSERT INTO `purchase_order_items` (`id`, `tenant_id`, `purchase_order_id`, `product_id`, `quantity`, `received_quantity`, `cost_price`) VALUES
('1', '1', '1', '3', '10', '10', '1500.00'),
('2', '1', '2', '2', '10', '10', '1700.00');

-- ----------------------------
-- Data for table `purchase_orders`
-- ----------------------------
INSERT INTO `purchase_orders` (`id`, `supplier_id`, `branch_id`, `total`, `status`, `expected_date`, `notes`, `due_amount`, `paid_amount`, `manager`, `created_by`, `created_at`, `updated_at`, `tenant_id`) VALUES
('1', '3', '1', '15000.00', 'received', '2026-06-09', 'It is emergency', '0.00', '0.00', NULL, '0', '2026-06-09 15:40:39', '2026-06-09 17:05:16', '1'),
('2', '3', '1', '17000.00', 'received', '2026-06-10', 'Waiting', '0.00', '0.00', NULL, '0', '2026-06-09 17:25:10', '2026-06-09 17:26:44', '1');

-- ----------------------------
-- Data for table `quotation_items`
-- ----------------------------
INSERT INTO `quotation_items` (`id`, `quotation_id`, `product_id`, `product_name`, `quantity`, `price`, `subtotal`) VALUES
('2', '2', '6', 'Flannel Sheets', '1', '1500.00', '1500.00');

-- ----------------------------
-- Data for table `quotations`
-- ----------------------------
INSERT INTO `quotations` (`id`, `tenant_id`, `quotation_number`, `customer_id`, `branch_id`, `business_type_id`, `created_by`, `total`, `status`, `valid_until`, `notes`, `terms`, `created_at`, `updated_at`) VALUES
('2', '1', 'Q20260608-1216', '1', '1', '2', '2', '1500.00', 'sent', '2026-06-15', '', 'Payment due within 30 days. Prices valid for 7 days.', '2026-06-08 18:46:27', '2026-06-08 18:46:27');

-- ----------------------------
-- Data for table `register_sessions`
-- ----------------------------
INSERT INTO `register_sessions` (`id`, `branch_id`, `user_id`, `opening_cash`, `closing_cash`, `expected_cash`, `cash_difference`, `total_sales`, `total_cash_sales`, `total_card_sales`, `total_other_sales`, `sale_count`, `notes`, `status`, `opened_at`, `closed_at`, `tenant_id`) VALUES
('1', '1', '1', '200.00', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0', NULL, 'open', '2026-05-01 17:37:55', NULL, '1'),
('2', '1', '2', '200.00', '200.00', '200.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0', '', 'closed', '2026-05-02 17:31:09', '2026-05-02 19:28:47', '1'),
('3', '1', '2', '200.00', '500.00', '200.00', '300.00', '0.00', '0.00', '0.00', '0.00', '0', '', 'closed', '2026-05-11 12:23:35', '2026-06-06 22:07:10', '1'),
('4', '1', '2', '200.00', NULL, NULL, NULL, '0.00', '0.00', '0.00', '0.00', '0', NULL, 'open', '2026-06-06 22:07:55', NULL, '1');

-- ----------------------------
-- Data for table `return_items`
-- ----------------------------
INSERT INTO `return_items` (`id`, `tenant_id`, `return_id`, `product_id`, `product_name`, `sale_item_id`, `quantity`, `unit_price`, `subtotal`, `reason`, `created_at`, `deleted_at`) VALUES
('1', '1', '1', '1', NULL, '6', '1', '1500.00', '1500.00', NULL, '2026-05-10 15:29:03', NULL),
('2', '1', '3', '1', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-11 06:36:51', NULL),
('3', '1', '4', '1', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-11 06:38:22', NULL),
('4', '1', '5', '1', NULL, '4', '1', '1500.00', '1500.00', NULL, '2026-05-11 07:24:36', NULL),
('5', '1', '6', '1', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-11 08:06:08', NULL),
('6', '1', '7', '2', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 11:28:23', NULL),
('7', '1', '8', '1', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 11:31:26', NULL),
('14', '1', '9', '2', NULL, NULL, '2', '1500.00', '3000.00', NULL, '2026-05-14 14:30:53', NULL),
('15', '1', '10', '2', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 14:32:00', NULL),
('17', '1', '11', '1', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-14 20:29:43', NULL),
('29', '1', '12', '2', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-17 08:04:44', NULL),
('33', '1', '17', '2', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-05-17 08:57:27', NULL),
('44', '1', '16', '8', NULL, NULL, '1', '1200.00', '1200.00', NULL, '2026-05-17 19:27:00', NULL),
('46', '1', '28', '2', NULL, NULL, '1', '1500.00', '1500.00', NULL, '2026-06-01 16:43:16', NULL),
('47', '1', '31', '2', 'Baby Blanket', '64', '1', '1500.00', '1500.00', 'wrong_item', '2026-06-03 16:09:25', NULL),
('48', '1', '32', '6', 'Flannel Sheets', '66', '1', '1500.00', '1500.00', 'wrong_item', '2026-06-03 22:49:53', NULL),
('49', '1', '34', '8', '', '75', '1', '1200.00', '1200.00', 'customer_changed_mind', '2026-06-04 21:51:20', NULL),
('50', '1', '34', '6', '', '76', '1', '1500.00', '1500.00', 'customer_changed_mind', '2026-06-04 21:51:20', NULL),
('51', '1', '35', '5', '', '98', '1', '700.00', '700.00', 'wrong_item', '2026-06-07 15:56:53', NULL),
('52', '1', '36', '5', '', '98', '1', '700.00', '700.00', 'wrong_item', '2026-06-07 17:20:47', NULL),
('53', '1', '38', '5', NULL, '98', '2', '700.00', '1400.00', NULL, '2026-06-07 20:29:55', NULL),
('54', '1', '39', '5', NULL, '97', '1', '700.00', '700.00', NULL, '2026-06-07 21:07:53', NULL);

-- ----------------------------
-- Data for table `returns`
-- ----------------------------
INSERT INTO `returns` (`id`, `return_number`, `return_type`, `sale_id`, `customer_id`, `branch_id`, `processed_by`, `approved_by`, `approved_at`, `reason`, `refund_method`, `amount`, `status`, `notes`, `created_at`, `updated_at`, `tenant_id`, `approval_level`, `approved_by_manager_id`, `approved_at_manager`, `requires_approval`, `high_value_flag`) VALUES
('1', 'R20260510-1296', 'sales', '6', NULL, '1', '0', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'completed', '', '2026-05-10 15:29:03', '2026-05-10 15:29:03', '1', 'level1', NULL, NULL, '0', '0'),
('3', 'R20260511-668', 'sales', '5', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-11 06:36:51', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('4', 'R20260511-542', 'sales', '4', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-11 06:38:22', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('5', 'R20260511-1701', 'sales', '4', NULL, '1', '0', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'completed', '', '2026-05-11 07:24:36', '2026-05-11 07:24:36', '1', 'level1', NULL, NULL, '0', '0'),
('6', 'R20260511-600', 'sales', '3', NULL, '1', '2', '2', NULL, 'Wrong Item', 'cash', '1500.00', 'completed', '', '2026-05-11 08:06:08', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('7', 'R20260514-024', 'sales', '29', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-14 11:28:23', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('8', 'R20260514-598', 'sales', '22', NULL, '1', '2', NULL, NULL, 'Customer Changed Mind', 'cash', '1500.00', 'pending', '', '2026-05-14 11:31:26', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('9', 'R20260514-574', 'sales', '28', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-14 14:29:11', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('10', 'R20260514-637', 'sales', '27', NULL, '1', '2', NULL, NULL, 'Damaged/Defective', 'cash', '1500.00', 'pending', '', '2026-05-14 14:32:00', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('11', 'R20260514-706', 'sales', '23', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-14 20:28:55', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('12', 'R20260514-554', 'sales', '26', NULL, '1', '2', '2', NULL, 'Damaged/Defective', 'cash', '1500.00', 'completed', '', '2026-05-14 20:30:56', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('16', 'R20260517-532', 'sales', '33', NULL, '1', '2', NULL, NULL, 'Customer Changed Mind', 'cash', '1200.00', 'pending', '', '2026-05-17 08:15:35', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('17', 'R20260517-458', 'sales', '34', NULL, '1', '2', NULL, NULL, 'Wrong Item', 'cash', '1500.00', 'pending', '', '2026-05-17 08:57:27', '2026-06-12 15:20:03', '1', 'level1', NULL, NULL, '0', '0'),
('28', 'R20260517-900', 'sales', '32', NULL, '1', '2', '2', NULL, 'Wrong Item', 'cash', '1500.00', 'completed', '', '2026-05-17 19:25:53', '2026-06-01 16:43:49', '1', 'level1', NULL, NULL, '0', '0'),
('31', 'R20260603-B332', 'sales', '52', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'completed', '', '2026-06-03 16:09:25', '2026-06-03 16:09:41', '1', 'level1', NULL, NULL, '0', '0'),
('32', 'R20260603-A6EF', 'sales', '53', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '1500.00', 'pending', '', '2026-06-03 22:49:53', '2026-06-03 22:49:53', '1', 'level1', NULL, NULL, '0', '0'),
('33', 'R20260603-4772', 'sales', '53', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '0.00', 'rejected', '\n[2026-06-03 23:48:01] Status changed from pending to rejected by user 2: ', '2026-06-03 23:05:25', '2026-06-03 23:48:01', '1', 'level1', NULL, NULL, '0', '0'),
('34', 'R20260604-DACB', 'sales', '60', NULL, '1', '2', NULL, NULL, 'customer_changed_mind', 'cash', '2700.00', 'pending', '\n[2026-06-04 23:50:11] Status changed from pending to pending by user 2: ', '2026-06-04 21:51:20', '2026-06-04 23:50:11', '1', 'level1', NULL, NULL, '0', '0'),
('35', 'R20260607-CA78', 'sales', '70', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '700.00', 'pending', '', '2026-06-07 15:56:53', '2026-06-07 15:56:53', '1', 'level1', NULL, NULL, '0', '0'),
('36', 'R20260607-EF9B', 'sales', '70', NULL, '1', '2', NULL, NULL, 'wrong_item', 'cash', '700.00', 'pending', '', '2026-06-07 17:20:47', '2026-06-07 17:20:47', '1', 'level1', NULL, NULL, '0', '0'),
('37', 'R20260607-7DAE', 'sales', '70', NULL, '1', '2', NULL, NULL, 'quality_issue', 'cash', '0.00', 'pending', '', '2026-06-07 17:38:58', '2026-06-07 17:38:58', '1', 'level1', NULL, NULL, '0', '0'),
('38', 'R20260607-1169', 'sales', '70', NULL, '1', '0', NULL, NULL, 'wrong_item', 'cash', '1400.00', 'completed', '', '2026-06-07 20:29:55', '2026-06-07 20:29:55', '1', 'level1', NULL, NULL, '0', '0'),
('39', 'R20260607-3892', 'sales', '69', NULL, '1', '0', NULL, NULL, 'wrong_item', 'card', '700.00', 'completed', '', '2026-06-07 21:07:53', '2026-06-07 21:07:53', '1', 'level1', NULL, NULL, '0', '0');

-- ----------------------------
-- Data for table `role_permissions`
-- ----------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `tenant_id`) VALUES
('1', '1', '0'),
('1', '2', '0'),
('1', '3', '0'),
('1', '4', '0'),
('1', '5', '0'),
('1', '6', '0'),
('1', '7', '0'),
('1', '8', '0'),
('1', '9', '0'),
('1', '10', '0'),
('1', '11', '0'),
('1', '12', '0'),
('1', '13', '0'),
('1', '14', '0'),
('1', '15', '0'),
('1', '16', '0'),
('1', '17', '0'),
('1', '18', '0'),
('1', '19', '0'),
('1', '20', '0'),
('1', '21', '0'),
('1', '22', '0'),
('1', '23', '0'),
('1', '24', '0'),
('1', '25', '0'),
('1', '26', '0'),
('1', '27', '0'),
('1', '28', '0'),
('1', '29', '0'),
('1', '30', '0'),
('1', '31', '0'),
('1', '32', '0'),
('1', '33', '0'),
('1', '34', '0'),
('1', '35', '0'),
('1', '36', '0'),
('1', '37', '0'),
('1', '38', '0'),
('1', '39', '0'),
('1', '40', '0'),
('1', '41', '0'),
('1', '42', '0'),
('1', '43', '0'),
('1', '44', '0'),
('1', '45', '0'),
('1', '46', '0'),
('1', '47', '0'),
('1', '48', '0'),
('1', '49', '0'),
('1', '50', '0'),
('1', '51', '0'),
('1', '52', '0'),
('1', '53', '0'),
('1', '54', '0'),
('1', '55', '0'),
('1', '56', '0'),
('1', '57', '0'),
('1', '58', '0'),
('1', '59', '0'),
('1', '60', '0'),
('1', '61', '0'),
('1', '62', '0'),
('1', '63', '0'),
('1', '64', '0'),
('1', '65', '0'),
('1', '66', '0'),
('1', '67', '0'),
('1', '68', '0'),
('1', '69', '0'),
('1', '70', '0'),
('1', '71', '0'),
('1', '72', '0'),
('1', '73', '0'),
('1', '74', '0'),
('1', '75', '0'),
('1', '76', '0'),
('1', '77', '0'),
('1', '78', '0'),
('1', '79', '0'),
('1', '80', '0'),
('1', '81', '0'),
('1', '82', '0'),
('1', '83', '0'),
('3', '1', '0'),
('3', '27', '0'),
('1', '84', '1'),
('1', '85', '1'),
('1', '86', '1'),
('1', '93', '1'),
('1', '94', '1'),
('1', '99', '1'),
('1', '100', '1'),
('1', '102', '1'),
('1', '103', '1'),
('1', '104', '1'),
('1', '109', '1'),
('1', '110', '1'),
('1', '112', '1'),
('1', '113', '1'),
('1', '114', '1'),
('1', '115', '1'),
('1', '116', '1'),
('1', '117', '1'),
('1', '118', '1'),
('1', '119', '1'),
('1', '121', '1'),
('1', '124', '1'),
('1', '125', '1'),
('1', '126', '1'),
('1', '127', '1'),
('1', '128', '1'),
('1', '133', '1'),
('1', '134', '1'),
('1', '135', '1'),
('1', '136', '1'),
('1', '137', '1'),
('1', '138', '1'),
('1', '139', '1'),
('1', '140', '1'),
('1', '141', '1'),
('1', '151', '1'),
('1', '152', '1'),
('1', '153', '1'),
('1', '154', '1'),
('1', '155', '1'),
('1', '156', '1'),
('1', '157', '1'),
('1', '158', '1'),
('1', '159', '1'),
('1', '160', '1'),
('1', '161', '1'),
('1', '162', '1'),
('1', '163', '1'),
('1', '164', '1'),
('1', '165', '1'),
('1', '166', '1'),
('1', '167', '1'),
('1', '168', '1'),
('1', '169', '1'),
('1', '170', '1'),
('1', '171', '1'),
('1', '172', '1'),
('1', '173', '1'),
('1', '174', '1'),
('1', '175', '1'),
('1', '176', '1'),
('1', '177', '1'),
('1', '178', '1'),
('1', '179', '1'),
('1', '180', '1'),
('1', '183', '1'),
('1', '184', '1'),
('1', '185', '1'),
('1', '186', '1'),
('1', '190', '1'),
('2', '10', '1'),
('2', '13', '1'),
('2', '14', '1'),
('2', '15', '1'),
('2', '16', '1'),
('2', '21', '1'),
('2', '22', '1'),
('2', '23', '1'),
('2', '27', '1'),
('2', '28', '1'),
('2', '29', '1'),
('2', '33', '1'),
('2', '34', '1'),
('2', '35', '1'),
('2', '39', '1'),
('2', '40', '1'),
('2', '41', '1'),
('2', '42', '1'),
('2', '45', '1'),
('2', '48', '1'),
('2', '49', '1'),
('2', '50', '1'),
('2', '52', '1'),
('2', '84', '1'),
('2', '85', '1'),
('2', '86', '1'),
('2', '93', '1'),
('2', '94', '1'),
('2', '99', '1'),
('2', '102', '1'),
('2', '109', '1'),
('2', '112', '1'),
('2', '113', '1'),
('2', '114', '1'),
('2', '116', '1'),
('2', '117', '1'),
('2', '118', '1'),
('2', '121', '1'),
('2', '124', '1'),
('2', '125', '1'),
('2', '126', '1'),
('2', '127', '1'),
('2', '133', '1'),
('2', '134', '1'),
('2', '135', '1'),
('2', '136', '1'),
('2', '137', '1'),
('2', '138', '1'),
('2', '139', '1'),
('2', '140', '1'),
('2', '141', '1'),
('2', '157', '1'),
('2', '159', '1'),
('2', '177', '1'),
('2', '178', '1'),
('2', '183', '1'),
('2', '184', '1'),
('2', '185', '1'),
('3', '10', '1'),
('3', '14', '1'),
('3', '21', '1'),
('3', '28', '1'),
('3', '39', '1'),
('3', '40', '1'),
('3', '84', '1'),
('3', '85', '1'),
('3', '86', '1'),
('3', '93', '1'),
('3', '94', '1'),
('4', '14', '1'),
('4', '21', '1'),
('4', '27', '1'),
('4', '84', '1'),
('4', '93', '1'),
('5', '1', '1'),
('5', '2', '1'),
('5', '3', '1'),
('5', '4', '1'),
('5', '5', '1'),
('5', '6', '1'),
('5', '7', '1'),
('5', '8', '1'),
('5', '9', '1'),
('5', '10', '1'),
('5', '11', '1'),
('5', '12', '1'),
('5', '13', '1'),
('5', '14', '1'),
('5', '15', '1'),
('5', '16', '1'),
('5', '17', '1'),
('5', '18', '1'),
('5', '19', '1'),
('5', '20', '1'),
('5', '21', '1'),
('5', '22', '1'),
('5', '23', '1'),
('5', '24', '1'),
('5', '25', '1'),
('5', '26', '1'),
('5', '27', '1'),
('5', '28', '1'),
('5', '29', '1'),
('5', '30', '1'),
('5', '31', '1'),
('5', '32', '1'),
('5', '33', '1'),
('5', '34', '1'),
('5', '35', '1'),
('5', '36', '1'),
('5', '37', '1'),
('5', '38', '1'),
('5', '39', '1'),
('5', '40', '1'),
('5', '41', '1'),
('5', '42', '1'),
('5', '43', '1'),
('5', '44', '1'),
('5', '45', '1'),
('5', '46', '1'),
('5', '47', '1'),
('5', '48', '1'),
('5', '49', '1'),
('5', '50', '1'),
('5', '51', '1'),
('5', '52', '1'),
('5', '53', '1'),
('5', '54', '1'),
('5', '55', '1'),
('5', '56', '1'),
('5', '57', '1'),
('5', '58', '1'),
('5', '59', '1'),
('5', '60', '1'),
('5', '61', '1'),
('5', '62', '1'),
('5', '63', '1'),
('5', '64', '1'),
('5', '65', '1'),
('5', '66', '1'),
('5', '67', '1'),
('5', '68', '1'),
('5', '69', '1'),
('5', '70', '1'),
('5', '71', '1'),
('5', '72', '1'),
('5', '73', '1'),
('5', '74', '1'),
('5', '75', '1'),
('5', '76', '1'),
('5', '77', '1'),
('5', '78', '1'),
('5', '79', '1'),
('5', '80', '1'),
('5', '81', '1'),
('5', '82', '1'),
('5', '83', '1'),
('5', '84', '1'),
('5', '85', '1'),
('5', '86', '1'),
('5', '93', '1'),
('5', '94', '1'),
('5', '99', '1'),
('5', '100', '1'),
('5', '102', '1'),
('5', '103', '1'),
('5', '104', '1'),
('5', '109', '1'),
('5', '110', '1'),
('5', '112', '1'),
('5', '113', '1'),
('5', '114', '1'),
('5', '115', '1'),
('5', '116', '1'),
('5', '117', '1'),
('5', '118', '1'),
('5', '119', '1'),
('5', '121', '1'),
('5', '124', '1'),
('5', '125', '1'),
('5', '126', '1'),
('5', '127', '1'),
('5', '128', '1'),
('5', '133', '1'),
('5', '134', '1'),
('5', '135', '1'),
('5', '136', '1'),
('5', '137', '1'),
('5', '138', '1'),
('5', '139', '1'),
('5', '140', '1'),
('5', '141', '1'),
('5', '151', '1'),
('5', '152', '1'),
('5', '153', '1'),
('5', '154', '1'),
('5', '155', '1'),
('5', '156', '1'),
('5', '157', '1'),
('5', '158', '1'),
('5', '159', '1'),
('5', '160', '1'),
('5', '161', '1'),
('5', '162', '1'),
('5', '163', '1'),
('5', '164', '1'),
('5', '165', '1'),
('5', '166', '1'),
('5', '167', '1'),
('5', '168', '1'),
('5', '169', '1'),
('5', '170', '1'),
('5', '171', '1'),
('5', '172', '1'),
('5', '173', '1'),
('5', '174', '1'),
('5', '175', '1'),
('5', '176', '1'),
('5', '177', '1'),
('5', '178', '1'),
('5', '179', '1'),
('5', '180', '1'),
('5', '183', '1'),
('5', '184', '1'),
('5', '185', '1'),
('5', '186', '1'),
('5', '190', '1'),
('6', '10', '1'),
('6', '14', '1'),
('6', '21', '1'),
('6', '27', '1'),
('6', '39', '1'),
('6', '40', '1'),
('7', '10', '1'),
('7', '14', '1'),
('7', '21', '1'),
('7', '27', '1'),
('7', '39', '1'),
('7', '40', '1'),
('8', '1', '1'),
('8', '2', '1'),
('8', '3', '1'),
('8', '4', '1'),
('8', '5', '1'),
('8', '6', '1'),
('8', '7', '1'),
('8', '8', '1'),
('8', '9', '1'),
('8', '10', '1'),
('8', '11', '1'),
('8', '12', '1'),
('8', '13', '1'),
('8', '14', '1'),
('8', '15', '1'),
('8', '16', '1'),
('8', '17', '1'),
('8', '18', '1'),
('8', '19', '1'),
('8', '20', '1'),
('8', '21', '1'),
('8', '22', '1'),
('8', '23', '1'),
('8', '24', '1'),
('8', '25', '1'),
('8', '26', '1'),
('8', '27', '1'),
('8', '28', '1'),
('8', '29', '1'),
('8', '30', '1'),
('8', '31', '1'),
('8', '32', '1'),
('8', '33', '1'),
('8', '34', '1'),
('8', '35', '1'),
('8', '36', '1'),
('8', '37', '1'),
('8', '38', '1'),
('8', '39', '1'),
('8', '40', '1'),
('8', '41', '1'),
('8', '42', '1'),
('8', '43', '1'),
('8', '44', '1'),
('8', '45', '1'),
('8', '46', '1'),
('8', '47', '1'),
('8', '48', '1'),
('8', '49', '1'),
('8', '50', '1'),
('8', '51', '1'),
('8', '52', '1'),
('8', '53', '1'),
('8', '54', '1'),
('8', '55', '1'),
('8', '56', '1'),
('8', '57', '1'),
('8', '58', '1'),
('8', '59', '1'),
('8', '60', '1'),
('8', '61', '1'),
('8', '62', '1'),
('8', '63', '1'),
('8', '64', '1'),
('8', '65', '1'),
('8', '66', '1'),
('8', '67', '1'),
('8', '68', '1'),
('8', '69', '1'),
('8', '70', '1'),
('8', '71', '1'),
('8', '72', '1'),
('8', '73', '1'),
('8', '74', '1'),
('8', '75', '1'),
('8', '76', '1'),
('8', '77', '1'),
('8', '78', '1'),
('8', '79', '1'),
('8', '80', '1'),
('8', '81', '1'),
('8', '82', '1'),
('8', '83', '1'),
('8', '84', '1'),
('8', '85', '1'),
('8', '86', '1'),
('8', '93', '1'),
('8', '94', '1'),
('8', '99', '1'),
('8', '100', '1'),
('8', '102', '1'),
('8', '103', '1'),
('8', '104', '1'),
('8', '109', '1'),
('8', '110', '1'),
('8', '112', '1'),
('8', '113', '1'),
('8', '114', '1'),
('8', '115', '1'),
('8', '116', '1'),
('8', '117', '1'),
('8', '118', '1'),
('8', '119', '1'),
('8', '121', '1'),
('8', '124', '1'),
('8', '125', '1');

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `tenant_id`) VALUES
('8', '126', '1'),
('8', '127', '1'),
('8', '128', '1'),
('8', '133', '1'),
('8', '134', '1'),
('8', '135', '1'),
('8', '136', '1'),
('8', '137', '1'),
('8', '138', '1'),
('8', '139', '1'),
('8', '140', '1'),
('8', '141', '1'),
('8', '151', '1'),
('8', '152', '1'),
('8', '153', '1'),
('8', '154', '1'),
('8', '155', '1'),
('8', '156', '1'),
('8', '157', '1'),
('8', '158', '1'),
('8', '159', '1'),
('8', '160', '1'),
('8', '161', '1'),
('8', '162', '1'),
('8', '163', '1'),
('8', '164', '1'),
('8', '165', '1'),
('8', '166', '1'),
('8', '167', '1'),
('8', '168', '1'),
('8', '169', '1'),
('8', '170', '1'),
('8', '171', '1'),
('8', '172', '1'),
('8', '173', '1'),
('8', '174', '1'),
('8', '175', '1'),
('8', '176', '1'),
('8', '177', '1'),
('8', '178', '1'),
('8', '179', '1'),
('8', '180', '1'),
('8', '183', '1'),
('8', '184', '1'),
('8', '185', '1'),
('8', '186', '1'),
('8', '190', '1'),
('9', '10', '1'),
('9', '12', '1'),
('9', '27', '1'),
('9', '32', '1'),
('9', '39', '1'),
('9', '40', '1'),
('9', '42', '1'),
('9', '43', '1'),
('9', '45', '1'),
('9', '48', '1'),
('9', '49', '1'),
('9', '50', '1'),
('9', '51', '1'),
('10', '14', '1'),
('10', '15', '1'),
('10', '16', '1'),
('10', '17', '1'),
('10', '18', '1'),
('10', '19', '1'),
('10', '21', '1'),
('10', '22', '1'),
('10', '23', '1'),
('10', '24', '1'),
('10', '25', '1'),
('10', '26', '1'),
('10', '41', '1'),
('10', '45', '1'),
('10', '110', '1'),
('10', '125', '1'),
('10', '126', '1'),
('10', '127', '1'),
('10', '134', '1'),
('10', '135', '1'),
('10', '137', '1'),
('11', '33', '1'),
('11', '34', '1'),
('11', '35', '1'),
('11', '36', '1'),
('11', '37', '1'),
('11', '38', '1'),
('11', '39', '1'),
('11', '173', '1'),
('11', '174', '1'),
('11', '175', '1'),
('11', '176', '1'),
('12', '1', '1'),
('12', '2', '1'),
('12', '3', '1'),
('12', '4', '1'),
('12', '5', '1'),
('12', '6', '1'),
('12', '7', '1'),
('12', '10', '1'),
('12', '13', '1'),
('12', '21', '1'),
('12', '27', '1'),
('12', '28', '1'),
('12', '29', '1'),
('12', '31', '1'),
('12', '40', '1'),
('13', '14', '1'),
('13', '21', '1'),
('13', '24', '1'),
('13', '125', '1'),
('13', '134', '1'),
('14', '1', '1'),
('14', '10', '1'),
('14', '13', '1'),
('14', '27', '1'),
('14', '28', '1'),
('14', '29', '1'),
('14', '39', '1'),
('15', '1', '1'),
('15', '9', '1'),
('15', '65', '1'),
('15', '67', '1'),
('16', '69', '1'),
('16', '71', '1'),
('16', '72', '1'),
('16', '73', '1'),
('16', '74', '1'),
('17', '10', '1'),
('17', '14', '1'),
('17', '21', '1'),
('17', '27', '1'),
('17', '39', '1'),
('17', '40', '1');

-- ----------------------------
-- Data for table `roles`
-- ----------------------------
INSERT INTO `roles` (`id`, `name`, `description`, `tenant_id`, `is_system`, `deleted_at`) VALUES
('1', 'Administrator', 'Full tenant access', '1', '1', NULL),
('2', 'Manager', 'Manage operations', '1', '1', NULL),
('3', 'Cashier', 'Process sales', '1', '1', NULL),
('4', 'Inventory', 'Manage inventory', '1', '1', NULL),
('5', 'Super Admin', 'Full platform access - manage all tenants, system settings', '1', '1', NULL),
('6', 'Developer', 'System maintenance, debugging, code access', '1', '1', NULL),
('7', 'Support', 'Platform support, view all tenants, resolve tickets', '1', '1', NULL),
('8', 'Owner', 'Full company access, billing, subscription management', '1', '0', NULL),
('9', 'Accountant', 'Financial operations, reports, expenses, billing', '1', '0', NULL),
('10', 'Inventory Manager', 'Full inventory control, stock management, transfers', '1', '0', NULL),
('11', 'HR Manager', 'Employee management, leave requests, payroll', '1', '0', NULL),
('12', 'Senior Cashier', 'All POS operations, basic manager duties, refunds', '1', '0', NULL),
('13', 'Inventory Clerk', 'Basic inventory tasks, stock counts, receiving', '1', '0', NULL),
('14', 'Customer Service', 'Customer management, returns, support tickets', '1', '0', NULL),
('15', 'Kitchen Staff', 'Kitchen display, order tickets, food preparation', '1', '0', NULL),
('16', 'Delivery Rider', 'Delivery management, tracking, order pickup', '1', '0', NULL),
('17', 'Viewer', 'Read-only access - view reports and data', '1', '0', NULL);

-- ----------------------------
-- Data for table `sale_items`
-- ----------------------------
INSERT INTO `sale_items` (`id`, `tenant_id`, `sale_id`, `product_id`, `product_name`, `product_sku`, `quantity`, `weight`, `unit`, `price`, `original_price`, `price_override_reason`, `kitchen_notes`, `overridden_by`, `subtotal`, `weight_kg`, `batch_number`, `expiry_date`, `serial_number`, `deleted_at`) VALUES
('1', '1', '1', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('2', '1', '2', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('3', '1', '3', '1', 'Heavy Rompers', NULL, '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00', NULL, NULL, NULL, NULL, NULL),
('4', '1', '4', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('5', '1', '5', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('6', '1', '6', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('7', '1', '7', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('8', '1', '8', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('9', '1', '9', '1', 'Heavy Rompers', NULL, '4', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '6000.00', NULL, NULL, NULL, NULL, NULL),
('10', '1', '10', '1', 'Heavy Rompers', NULL, '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00', NULL, NULL, NULL, NULL, NULL),
('11', '1', '11', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('12', '1', '12', '1', 'Heavy Rompers', NULL, '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00', NULL, NULL, NULL, NULL, NULL),
('13', '1', '13', '1', 'Heavy Rompers', NULL, '3', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '4500.00', NULL, NULL, NULL, NULL, NULL),
('14', '1', '14', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('15', '1', '15', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('16', '1', '16', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('17', '1', '17', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('18', '1', '18', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('19', '1', '19', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('20', '1', '20', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('21', '1', '21', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('22', '1', '22', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('23', '1', '23', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('24', '1', '24', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('25', '1', '25', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('26', '1', '26', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('27', '1', '27', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('28', '1', '28', '2', 'Baby Blanket', NULL, '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00', NULL, NULL, NULL, NULL, NULL),
('29', '1', '29', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('30', '1', '30', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('31', '1', '31', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('32', '1', '32', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('33', '1', '33', '8', 'Cement', NULL, '1', NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, NULL),
('34', '1', '34', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('35', '1', '35', '3', 'Baby Shawl', NULL, '1', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, NULL),
('36', '1', '35', '6', 'Flannel Sheets', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('37', '1', '36', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('38', '1', '37', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('39', '1', '37', '3', 'Baby Shawl', NULL, '1', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, NULL),
('40', '1', '37', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('41', '1', '38', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('42', '1', '39', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('43', '1', '39', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('44', '1', '40', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('45', '1', '41', '7', 'Mittens', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('46', '1', '41', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('47', '1', '42', '3', 'Baby Shawl', NULL, '1', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, NULL),
('48', '1', '42', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('49', '1', '42', '6', 'Flannel Sheets', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('50', '1', '43', '3', 'Baby Shawl', NULL, '2', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '3200.00', NULL, NULL, NULL, NULL, NULL),
('51', '1', '43', '5', 'Mackintosh', NULL, '2', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '1400.00', NULL, NULL, NULL, NULL, NULL),
('52', '1', '43', '7', 'Mittens', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('53', '1', '44', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('54', '1', '44', '6', 'Flannel Sheets', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('55', '1', '45', '5', 'Mackintosh', NULL, '1', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '700.00', NULL, NULL, NULL, NULL, NULL),
('56', '1', '46', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('57', '1', '46', '5', 'Mackintosh', NULL, '1', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '700.00', NULL, NULL, NULL, NULL, NULL),
('58', '1', '47', '7', 'Mittens', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('59', '1', '47', '5', 'Mackintosh', NULL, '1', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '700.00', NULL, NULL, NULL, NULL, NULL),
('60', '1', '48', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('61', '1', '49', '7', 'Mittens', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('62', '1', '50', '7', 'Mittens', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('63', '1', '51', '1', 'Heavy Rompers', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('64', '1', '52', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('65', '1', '52', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('66', '1', '53', '6', 'Flannel Sheets', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('67', '1', '54', '4', '', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('68', '1', '54', '8', '', NULL, '3', NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, '3600.00', NULL, NULL, NULL, NULL, NULL),
('69', '1', '54', '7', '', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('70', '1', '55', '6', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('71', '1', '56', '1', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('72', '1', '57', '1', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('73', '1', '58', '3', '', NULL, '1', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, NULL),
('74', '1', '59', '7', '', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('75', '1', '60', '8', '', NULL, '2', NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, '2400.00', NULL, NULL, NULL, NULL, NULL),
('76', '1', '60', '6', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('77', '1', '60', '3', '', NULL, '1', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, NULL),
('78', '1', '61', '2', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('79', '1', '62', '6', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('80', '1', '63', '6', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('81', '1', '64', '2', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('82', '1', '65', '2', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('83', '1', '66', '2', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('84', '1', '66', '3', '', NULL, '1', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, NULL),
('85', '1', '66', '8', '', NULL, '1', NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, NULL),
('86', '1', '66', '6', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('87', '1', '66', '1', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('88', '1', '66', '5', '', NULL, '2', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '1400.00', NULL, NULL, NULL, NULL, NULL),
('89', '1', '66', '7', '', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('90', '1', '67', '7', '', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('91', '1', '68', '4', '', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('92', '1', '68', '6', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('93', '1', '68', '1', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('94', '1', '68', '8', '', NULL, '1', NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, NULL),
('95', '1', '68', '2', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('96', '1', '69', '7', '', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('97', '1', '69', '5', '', NULL, '1', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '700.00', NULL, NULL, NULL, NULL, NULL),
('98', '1', '70', '5', '', NULL, '2', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '1400.00', NULL, NULL, NULL, NULL, NULL),
('99', '1', '71', '4', '', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('100', '1', '72', '2', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('101', '1', '73', '2', '', NULL, '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00', NULL, NULL, NULL, NULL, NULL),
('102', '1', '74', '1', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('103', '1', '75', '1', '', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('118', '1', '87', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('119', '1', '88', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('120', '1', '89', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('121', '1', '90', '8', 'Cement', NULL, '1', NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, NULL),
('122', '1', '91', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('123', '1', '92', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('124', '1', '92', '5', 'Mackintosh', NULL, '1', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '700.00', NULL, NULL, NULL, NULL, NULL),
('125', '1', '93', '1', 'Heavy Rompers', NULL, '2', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '3000.00', NULL, NULL, NULL, NULL, NULL),
('126', '1', '94', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('127', '1', '95', '7', 'Mittens', NULL, '1', NULL, NULL, '100.00', NULL, NULL, NULL, NULL, '100.00', NULL, NULL, NULL, NULL, NULL),
('128', '1', '96', '2', 'Baby Blanket', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('129', '1', '97', '5', 'Mackintosh', NULL, '1', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '700.00', NULL, NULL, NULL, NULL, NULL),
('130', '1', '98', '6', 'Flannel Sheets', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('131', '1', '99', '5', 'Mackintosh', NULL, '1', NULL, NULL, '700.00', NULL, NULL, NULL, NULL, '700.00', NULL, NULL, NULL, NULL, NULL),
('132', '1', '99', '6', 'Flannel Sheets', NULL, '1', NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, '1500.00', NULL, NULL, NULL, NULL, NULL),
('133', '1', '99', '4', 'Cussion packed', NULL, '1', NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, '2300.00', NULL, NULL, NULL, NULL, NULL),
('134', '1', '99', '8', 'Cement', NULL, '1', NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, '1200.00', NULL, NULL, NULL, NULL, NULL),
('135', '1', '100', '3', 'Baby Shawl', NULL, '1', NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, '1600.00', NULL, NULL, NULL, NULL, NULL);

-- ----------------------------
-- Data for table `sales`
-- ----------------------------
INSERT INTO `sales` (`id`, `invoice_number`, `branch_id`, `register_session_id`, `user_id`, `customer_id`, `subtotal`, `total`, `due_amount`, `due_date`, `return_amount`, `notes`, `discount`, `tip_amount`, `deposit_amount`, `balance_due`, `discount_type`, `discount_amount`, `tax_rate`, `tax_exempt`, `tax_amount`, `tax`, `payment_method`, `reference`, `created_at`, `pos_transaction`, `status`, `voided`, `voided_by`, `voided_at`, `void_reason`, `age_verified`, `updated_at`, `order_type`, `business_type`, `business_type_id`, `tenant_id`, `age_verify_id`, `staff_id`, `batch_number`, `weight_kg`, `amount_received`, `change_given`, `appointment_id`, `room_number`, `guest_name`, `table_number`, `kitchen_notes`, `serial_number`, `warranty_months`, `prescription_enabled`, `prescription_ref`, `prescription_doctor`, `prescription_notes`, `loyalty_points_redeemed`, `loyalty_discount`, `bt_id`, `weight`, `weight_unit`) VALUES
('1', 'INV-202605-0001', '1', NULL, '1', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-01 17:38:08', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('2', 'INV-202605-0001', '1', NULL, '1', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-01 22:37:36', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('3', 'INV-202605-0001', '1', NULL, '1', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'split', NULL, '2026-05-01 23:06:40', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('4', 'INV-202605-0001', '1', NULL, '1', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-01 23:09:49', '0', 'returned', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('5', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'mpesa', NULL, '2026-05-02 17:31:23', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('6', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'bank_transfer', NULL, '2026-05-02 18:37:27', '0', 'returned', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('7', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 12:32:06', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('8', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'credit', NULL, '2026-05-11 12:41:18', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('9', 'INV-202605-0001', '1', NULL, '2', NULL, '6000.00', '6660.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '660.00', 'cash', NULL, '2026-05-11 16:35:50', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('10', 'INV-202605-0001', '1', NULL, '2', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'cash', NULL, '2026-05-11 17:34:31', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('11', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 17:43:17', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('12', 'INV-202605-0001', '1', NULL, '2', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'cash', NULL, '2026-05-11 17:52:16', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('13', 'INV-202605-0001', '1', NULL, '2', NULL, '4500.00', '4995.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '495.00', 'cash', NULL, '2026-05-11 17:52:39', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('14', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 17:54:20', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('15', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 18:07:08', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('16', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-11 18:30:57', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('17', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 10:19:39', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('18', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 16:47:10', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('19', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 20:56:09', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('20', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 21:21:22', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('21', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 21:32:32', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('22', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-12 21:49:48', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('23', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 11:18:59', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('24', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 13:07:44', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('25', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 13:27:49', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('26', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 14:03:24', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('27', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-13 14:05:39', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('28', 'INV-202605-0001', '1', NULL, '2', NULL, '3000.00', '3330.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '330.00', 'credit', NULL, '2026-05-13 15:15:12', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('29', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'credit', NULL, '2026-05-13 15:17:18', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('30', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'credit', NULL, '2026-05-15 12:08:34', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('31', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'split', NULL, '2026-05-15 13:53:59', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('32', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'cash', NULL, '2026-05-16 14:37:42', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'liquor_store', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('33', 'INV-202605-0001', '1', NULL, '2', NULL, '1200.00', '1332.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '132.00', 'mpesa', NULL, '2026-05-16 15:19:10', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'stationery', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('34', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1665.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '165.00', 'flutterwave', NULL, '2026-05-16 23:36:30', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('35', 'INV-202605-0001', '1', NULL, '2', NULL, '3100.00', '3441.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '341.00', 'cash', NULL, '2026-05-18 18:01:45', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('36', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-05-23 06:58:41', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('37', 'INV-202605-0001', '1', NULL, '2', NULL, '5400.00', '6264.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '864.00', 'card', NULL, '2026-05-24 23:54:04', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('38', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'mpesa', NULL, '2026-05-25 00:12:49', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('39', 'INV-202605-0001', '1', NULL, '2', NULL, '3000.00', '3480.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '480.00', 'cash', NULL, '2026-05-26 16:50:00', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('40', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-05-27 01:08:37', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('41', 'INV-202605-0001', '1', NULL, '2', NULL, '1600.00', '1856.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '256.00', 'cash', NULL, '2026-05-27 17:20:01', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('42', 'INV-202605-0001', '1', NULL, '2', NULL, '5400.00', '6264.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '864.00', 'cash', NULL, '2026-05-28 09:05:29', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('43', 'INV-202605-0001', '1', NULL, '2', NULL, '4700.00', '5452.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '752.00', 'cash', NULL, '2026-05-28 15:39:38', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('44', 'INV-202605-0001', '1', NULL, '2', NULL, '3800.00', '4408.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '608.00', 'cash', NULL, '2026-05-28 17:50:40', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('45', 'INV-202605-0001', '1', NULL, '2', NULL, '700.00', '812.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '112.00', 'cash', NULL, '2026-05-28 17:52:52', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('46', 'INV-202605-0001', '1', NULL, '2', NULL, '2200.00', '2552.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '352.00', 'cash', NULL, '2026-05-28 12:39:10', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('47', 'INV-202605-0001', '1', NULL, '2', NULL, '800.00', '928.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '128.00', 'cash', NULL, '2026-05-28 19:41:02', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('48', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'flutterwave', NULL, '2026-05-28 20:08:07', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('49', 'INV-202605-0001', '1', NULL, '2', NULL, '100.00', '116.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '16.00', 'cash', NULL, '2026-05-29 00:08:32', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('50', 'INV-202605-0001', '1', NULL, '2', NULL, '100.00', '116.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '16.00', 'cash', NULL, '2026-05-29 18:29:49', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('51', 'INV-202605-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'stripe', NULL, '2026-05-29 19:16:04', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('52', 'INV-202605-0001', '1', NULL, '2', NULL, '3800.00', '4408.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '608.00', 'cash', NULL, '2026-05-31 18:42:55', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('53', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-01 16:41:50', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('54', 'INV-202606-0001', '1', NULL, '2', NULL, '6000.00', '6960.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '960.00', 'cash', NULL, '2026-06-04 12:40:49', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('55', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-04 16:08:43', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('56', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-04 16:13:18', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('57', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-04 16:39:31', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('58', 'INV-202606-0001', '1', NULL, '2', NULL, '1600.00', '1856.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '256.00', 'cash', NULL, '2026-06-04 16:43:38', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('59', 'INV-202606-0001', '1', NULL, '2', NULL, '100.00', '116.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '16.00', 'cash', NULL, '2026-06-04 18:15:00', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('60', 'INV-202606-0001', '1', NULL, '2', NULL, '5500.00', '6380.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '880.00', 'cash', NULL, '2026-06-04 18:19:05', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'hotel', '10', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('61', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-04 22:54:18', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('62', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-06 01:23:10', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('63', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-06 02:01:35', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('64', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-06 10:48:15', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('65', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-06 11:26:17', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('66', 'INV-202606-0001', '1', NULL, '2', NULL, '8800.00', '10208.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '1408.00', 'cash', NULL, '2026-06-06 14:05:05', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('67', 'INV-202606-0001', '1', NULL, '2', NULL, '100.00', '116.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '16.00', 'cash', NULL, '2026-06-06 14:54:19', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('68', 'INV-202606-0001', '1', NULL, '2', NULL, '8000.00', '9280.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '1280.00', 'cash', NULL, '2026-06-06 14:56:10', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('69', 'INV-202606-0001', '1', NULL, '2', NULL, '800.00', '928.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '128.00', 'cash', NULL, '2026-06-06 15:50:03', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('70', 'INV-202606-0001', '1', NULL, '2', NULL, '1400.00', '1624.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '224.00', 'cash', NULL, '2026-06-06 16:18:17', '0', 'returned', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '2000.00', '376.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('71', 'INV-202606-0001', '1', NULL, '2', NULL, '2300.00', '2668.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '368.00', 'split', NULL, '2026-06-10 19:02:04', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '2668.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('72', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-12 23:07:24', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '2000.00', '260.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('73', 'INV-202606-0001', '1', NULL, '2', NULL, '3000.00', '3480.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '480.00', 'cash', NULL, '2026-06-12 23:34:42', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '3480.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('74', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-12 23:35:34', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '1740.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('75', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-12 23:44:41', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '1740.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('87', 'INV-202606-0001', '1', NULL, '2', NULL, '2300.00', '2668.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '368.00', 'cash', NULL, '2026-06-14 01:59:53', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '2668.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('88', 'INV-202606-0001', '1', NULL, '2', NULL, '2300.00', '2668.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '368.00', 'cash', NULL, '2026-06-14 03:30:03', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '3000.00', '332.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('89', 'INV-202606-0001', '1', NULL, '2', NULL, '2300.00', '2668.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '368.00', 'cash', NULL, '2026-06-15 13:06:26', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '2668.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('90', 'INV-202606-0001', '1', NULL, '2', NULL, '1200.00', '1392.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '192.00', 'cash', NULL, '2026-06-15 13:31:37', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '1392.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('91', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-15 13:51:33', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '1740.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('92', 'INV-202606-0001', '1', NULL, '2', NULL, '2200.00', '2552.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '352.00', 'mpesa', NULL, '2026-06-15 20:17:47', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '2552.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('93', 'INV-202606-0001', '1', NULL, '1', NULL, '3000.00', '3480.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '480.00', 'card', NULL, '2026-06-15 20:29:45', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '3480.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('94', 'INV-202606-0001', '1', NULL, '1', NULL, '2300.00', '2668.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '368.00', 'card', NULL, '2026-06-16 13:32:30', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '2668.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('95', 'INV-202606-0001', '1', NULL, '1', NULL, '100.00', '116.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '16.00', 'cash', NULL, '2026-06-16 19:28:58', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, 'dine-in', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('96', 'INV-202606-0001', '1', NULL, '1', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-17 03:44:35', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', NULL, NULL, NULL, NULL, '1740.00', '0.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('97', 'INV-202606-0001', '1', NULL, '1', NULL, '700.00', '812.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '112.00', 'cash', NULL, '2026-06-17 04:04:13', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', '', '0', '', '0.000', '812.00', '0.00', '0', '', '', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('98', 'INV-202606-0001', '1', NULL, '2', NULL, '1500.00', '1740.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '240.00', 'cash', NULL, '2026-06-19 17:03:20', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', '', '0', '', '0.000', '1740.00', '0.00', '0', '', '', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('99', 'INV-202606-0001', '1', NULL, '2', NULL, '5700.00', '6612.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '912.00', 'card', NULL, '2026-06-22 06:03:36', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', '', '0', '', '0.000', '6612.00', '0.00', '0', '', '', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL),
('100', 'INV-202606-0001', '1', NULL, '2', NULL, '1600.00', '1856.00', '0.00', NULL, '0.00', '', '0.00', '0.00', '0.00', '0.00', 'fixed', '0.00', '0.00', '0', '0.00', '256.00', 'cash', NULL, '2026-06-23 04:55:03', '0', 'completed', '0', NULL, NULL, NULL, '0', NULL, '', 'supermarket', '2', '1', '', '0', '', '0.000', '1856.00', '0.00', '0', '', '', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, '0', '0.00', NULL, NULL, NULL);

-- ----------------------------
-- Data for table `schema_migrations`
-- ----------------------------
INSERT INTO `schema_migrations` (`id`, `version`, `name`, `applied_at`, `checksum`, `execution_time_ms`, `status`, `error_message`) VALUES
('1', '005_pos_tabs_and_kot', '005_pos_tabs_and_kot', '2026-06-17 17:00:10', '15cc28e10f47f376843dcb9ea4971f8287b7171677342ddd3045fd57ec370193', '198', 'success', NULL);

-- ----------------------------
-- Data for table `security_audit_log`
-- ----------------------------
INSERT INTO `security_audit_log` (`id`, `event_type`, `description`, `performed_by`, `ip_address`, `created_at`) VALUES
('1', 'security_hardening', 'Complete security patch applied - Default accounts locked, weak passwords disabled, rate limiting enabled', 'system', '127.0.0.1', '2026-06-11 11:33:44');

-- ----------------------------
-- Data for table `settings`
-- ----------------------------
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `created_by`, `updated_by`, `created_at`, `updated_at`, `category`, `description`, `data_type`, `tenant_id`) VALUES
('1', 'company_name', 'JAKPOS', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Company display name', 'text', '1'),
('2', 'company_email', 'wickymacochiz80@gmail.com', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Company contact email', 'text', '1'),
('3', 'company_phone', '0759714022', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Company phone number', 'text', '1'),
('4', 'company_address', '40111', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Company physical address', 'text', '1'),
('5', 'business_type', 'supermarket', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Business type for UI customization', 'text', '1'),
('6', 'currency', 'KES', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Default currency code', 'text', '1'),
('7', 'timezone', 'Africa/Nairobi', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'System timezone', 'text', '1'),
('8', 'date_format', 'd M Y', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Date display format', 'text', '1'),
('9', 'time_format', 'H:i', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:45', 'general', 'Time display format', 'text', '1'),
('10', 'tax_rate', '16', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:46', 'general', 'Default tax rate percentage', 'text', '1'),
('11', 'default_branch_id', '1', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:46', 'general', 'Default branch ID', 'text', '1'),
('12', 'default_payment_method', 'cash', '1', '2', '2026-05-01 13:16:53', '2026-06-15 17:03:46', 'general', 'Default payment method', 'text', '1'),
('13', 'enable_barcode_scanner', '1', '1', '2', '2026-05-01 13:16:53', '2026-06-15 20:16:55', 'system', 'Business type default: supermarket', 'text', '1'),
('14', 'allow_negative_stock', '0', '1', '2', '2026-05-01 13:16:53', '2026-06-15 20:16:55', 'system', 'Business type default: supermarket', 'text', '1'),
('15', 'enable_expiry_tracking', '1', '1', '2', '2026-05-01 13:16:53', '2026-06-15 20:16:55', 'system', 'Business type default: supermarket', 'text', '1'),
('16', 'default_reorder_level', '20', '1', '2', '2026-05-01 13:16:53', '2026-06-15 20:16:55', 'system', 'Business type default: supermarket', 'text', '1'),
('81', 'enable_tables', '1', '1', '2', '2026-05-01 23:25:17', '2026-06-13 16:08:01', 'system', 'Business type default: restaurant', 'text', '1'),
('82', 'enable_kitchen', '1', '1', '2', '2026-05-01 23:25:17', '2026-06-13 16:08:01', 'system', 'Business type default: restaurant', 'text', '1'),
('149', 'require_prescription', '1', '2', '2', '2026-05-02 18:38:01', '2026-06-13 19:41:05', 'system', 'Business type default: pharmacy', 'text', '1'),
('150', 'batch_tracking', '1', '2', '2', '2026-05-02 18:38:01', '2026-06-13 19:41:05', 'system', 'Business type default: pharmacy', 'text', '1'),
('625', 'company_logo', 'uploads/logos/company_1/logo_1_1781532227.png', '2', '2', '2026-05-12 13:14:11', '2026-06-15 17:03:47', 'general', 'Company logo path', 'text', '1'),
('965', 'site_title', 'Jakababa', '2', '2', '2026-05-13 20:35:34', '2026-06-15 17:03:47', 'general', 'Browser tab title and login heading', 'text', '1'),
('966', 'site_tagline', 'Smart System', '2', '2', '2026-05-13 20:35:34', '2026-06-15 17:03:47', 'general', 'Short subtitle below site title', 'text', '1'),
('967', 'site_icon', 'uploads/icons/company_1/icon_1_1778695769.png', '2', '2', '2026-05-13 20:35:34', '2026-05-13 21:09:29', 'general', 'Site favicon path', 'text', '1'),
('1188', 'enable_discounts', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Enable discounts', 'text', '1'),
('1189', 'enable_returns', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Enable sales returns', 'text', '1'),
('1190', 'enable_draft_sales', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Enable draft/hold sales', 'text', '1'),
('1194', 'session_timeout', '7200', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Session timeout in seconds', 'text', '1'),
('1195', 'require_pin_for_refund', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Require PIN for refunds', 'text', '1'),
('1196', 'require_pin_for_discount', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Require PIN for discounts', 'text', '1'),
('1197', 'enable_loyalty', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Enable loyalty points', 'text', '1'),
('1198', 'enable_vouchers', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Enable vouchers', 'text', '1'),
('1199', 'round_prices', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Round prices to nearest whole', 'text', '1'),
('1200', 'enable_cash', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Accept cash payments', 'text', '1'),
('1201', 'enable_mpesa', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Accept M-Pesa payments', 'text', '1'),
('1202', 'enable_card', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Accept card payments', 'text', '1'),
('1203', 'enable_credit', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Allow customer credit', 'text', '1'),
('1204', 'enable_stripe', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Stripe card payments', 'text', '1'),
('1205', 'enable_paypal', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'PayPal payments', 'text', '1'),
('1206', 'enable_razorpay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Razorpay India payments', 'text', '1'),
('1207', 'enable_paystack', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Paystack Africa payments', 'text', '1'),
('1208', 'enable_flutterwave', '1', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Flutterwave payments', 'text', '1'),
('1209', 'enable_square', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Square card payments', 'text', '1'),
('1210', 'enable_braintree', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Braintree payments', 'text', '1'),
('1211', 'enable_authorize_net', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Authorize.Net payments', 'text', '1'),
('1212', 'enable_paytm', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Paytm India payments', 'text', '1'),
('1213', 'enable_wechat_pay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'WeChat Pay', 'text', '1'),
('1214', 'enable_alipay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Alipay', 'text', '1'),
('1215', 'enable_google_pay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Google Pay', 'text', '1'),
('1216', 'enable_apple_pay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Apple Pay', 'text', '1'),
('1217', 'enable_klarna', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Klarna BNPL', 'text', '1'),
('1218', 'enable_afterpay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Afterpay BNPL', 'text', '1'),
('1219', 'enable_gocardless', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'GoCardless direct debit', 'text', '1'),
('1220', 'enable_crypto', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Cryptocurrency payments', 'text', '1'),
('1221', 'enable_upi', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'UPI India payments', 'text', '1'),
('1222', 'enable_ideal', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'iDEAL Netherlands', 'text', '1'),
('1223', 'enable_bancontact', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Bancontact Belgium', 'text', '1'),
('1224', 'enable_giropay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Giropay Germany', 'text', '1'),
('1225', 'enable_sofort', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'SOFORT payments', 'text', '1'),
('1226', 'enable_eps', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'EPS Austria', 'text', '1'),
('1227', 'enable_przelewy24', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Przelewy24 Poland', 'text', '1'),
('1228', 'enable_trustly', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Trustly Europe', 'text', '1'),
('1229', 'enable_revolut', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Revolut payments', 'text', '1'),
('1230', 'enable_wise', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Wise transfers', 'text', '1'),
('1231', 'enable_worldpay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Worldpay payments', 'text', '1'),
('1232', 'enable_sagepay', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Sage Pay payments', 'text', '1'),
('1233', 'enable_2checkout', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', '2Checkout payments', 'text', '1'),
('1234', 'enable_payfast', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Payfast South Africa', 'text', '1'),
('1235', 'enable_sezzle', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Sezzle BNPL', 'text', '1'),
('1236', 'enable_affirm', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Affirm BNPL', 'text', '1'),
('1237', 'enable_venmo', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Venmo payments', 'text', '1'),
('1238', 'enable_cash_app', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Cash App Pay', 'text', '1'),
('1239', 'auto_delete_sales', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Auto-delete old sales records', 'text', '1'),
('1240', 'data_retention_days', '365', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Data retention period in days', 'text', '1'),
('1241', 'enable_audit_log', '0', '2', '2', '2026-05-16 22:57:19', '2026-06-15 20:16:55', 'system', 'Enable audit logging', 'text', '1'),
('1254', 'locale', 'en', '2', '2', '2026-05-16 23:36:04', '2026-06-15 17:03:47', 'general', 'UI language locale', 'text', '1'),
('1261', 'online_store_enabled', '1', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'Enable online store', 'text', '1'),
('1262', 'online_store_url', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'Public store URL', 'text', '1'),
('1263', 'whatsapp_number', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'WhatsApp ordering number', 'text', '1'),
('1264', 'whatsapp_message', 'Hi, I would like to order:', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'WhatsApp pre-filled message', 'text', '1'),
('1265', 'meta_title', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'SEO meta title', 'text', '1'),
('1266', 'meta_description', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'SEO meta description', 'text', '1'),
('1267', 'og_image_url', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'Open Graph image URL', 'text', '1'),
('1268', 'social_facebook', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'Facebook page URL', 'text', '1'),
('1269', 'social_twitter', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'Twitter/X page URL', 'text', '1'),
('1270', 'social_instagram', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'Instagram page URL', 'text', '1'),
('1271', 'social_tiktok', '', '2', '2', '2026-05-17 07:17:57', '2026-05-17 07:17:57', 'online', 'TikTok page URL', 'text', '1'),
('1276', 'vat_number', '', '2', '2', '2026-05-20 21:46:38', '2026-06-15 17:03:45', 'general', 'VAT registration number', 'text', '1'),
('1277', 'pin_number', '', '2', '2', '2026-05-20 21:46:38', '2026-06-15 17:03:45', 'general', 'Tax PIN number', 'text', '1'),
('1278', 'fiscal_stand', '', '2', '2', '2026-05-20 21:46:38', '2026-06-15 17:03:45', 'general', 'Fiscal receipt stand label', 'text', '1'),
('1279', 'receipt_description', '', '2', '2', '2026-05-20 21:46:38', '2026-06-15 17:03:45', 'general', 'Receipt tagline printed below company info', 'text', '1'),
('1366', 'label_template_K11', '{\"fields\":[\"name\",\"sku\",\"price\",\"barcode\",\"store\"]}', NULL, '2', '2026-05-31 18:49:07', '2026-05-31 18:49:07', 'general', NULL, 'text', '1'),
('1576', 'db_timezone', '+03:00', NULL, NULL, '2026-06-12 09:46:27', '2026-06-12 09:46:27', 'system', NULL, 'text', '1'),
('1577', 'db_charset', 'utf8mb4', NULL, NULL, '2026-06-12 09:46:27', '2026-06-12 09:46:27', 'system', NULL, 'text', '1'),
('1716', 'subscription_plan', 'starter', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1717', 'max_users', '10', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1718', 'max_branches', '5', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1719', 'enable_multi_currency', '0', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1720', 'enable_api_access', '0', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1721', 'enable_white_label', '1', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1722', 'enable_advanced_reports', '1', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1723', 'enable_custom_domain', '0', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1724', 'enable_priority_support', '1', NULL, NULL, '2026-06-15 16:17:30', '2026-06-15 19:37:09', 'general', NULL, 'text', '1'),
('1813', 'last_cache_clear', '2026-06-22 06:04:25', NULL, NULL, '2026-06-20 16:30:59', '2026-06-22 06:04:25', 'general', NULL, 'text', '1');

-- ----------------------------
-- Data for table `shipping_zones`
-- ----------------------------
INSERT INTO `shipping_zones` (`id`, `tenant_id`, `name`, `description`, `countries`, `regions`, `cities`, `postal_codes`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
('1', '1', 'Nairobi & Environs', 'Nairobi County and surrounding areas', NULL, NULL, NULL, NULL, '1', '0', '2026-05-17 09:47:32', '2026-05-17 09:47:32'),
('2', '1', 'Rest of Kenya', 'All other counties', NULL, NULL, NULL, NULL, '1', '0', '2026-05-17 09:47:32', '2026-05-17 09:47:32');

-- ----------------------------
-- Data for table `sms_credits`
-- ----------------------------
INSERT INTO `sms_credits` (`id`, `balance`, `total_purchased`, `total_used`, `last_updated`, `tenant_id`) VALUES
('1', '0', '0', '0', '2026-05-02 19:52:08', '1'),
('2', '0', '0', '0', '2026-06-10 09:39:05', '1'),
('3', '0', '0', '0', '2026-06-10 09:39:07', '1'),
('4', '0', '0', '0', '2026-06-10 09:39:07', '1'),
('5', '0', '0', '0', '2026-06-10 09:39:07', '1'),
('6', '0', '0', '0', '2026-06-10 09:39:16', '1'),
('7', '0', '0', '0', '2026-06-10 09:39:18', '1'),
('8', '0', '0', '0', '2026-06-10 09:39:19', '1'),
('9', '0', '0', '0', '2026-06-10 09:49:15', '1'),
('10', '0', '0', '0', '2026-06-10 09:49:16', '1'),
('11', '0', '0', '0', '2026-06-10 09:49:16', '1'),
('12', '0', '0', '0', '2026-06-10 09:49:16', '1'),
('13', '0', '0', '0', '2026-06-10 09:49:18', '1'),
('14', '0', '0', '0', '2026-06-10 09:49:18', '1'),
('15', '0', '0', '0', '2026-06-10 09:49:18', '1'),
('16', '0', '0', '0', '2026-06-10 09:49:18', '1'),
('17', '0', '0', '0', '2026-06-10 09:50:49', '1'),
('18', '0', '0', '0', '2026-06-10 09:50:50', '1'),
('19', '0', '0', '0', '2026-06-10 09:50:51', '1'),
('20', '0', '0', '0', '2026-06-10 09:50:51', '1'),
('21', '0', '0', '0', '2026-06-10 09:50:57', '1'),
('22', '0', '0', '0', '2026-06-10 09:50:58', '1'),
('23', '0', '0', '0', '2026-06-10 09:50:59', '1'),
('24', '0', '0', '0', '2026-06-10 09:51:55', '1'),
('25', '0', '0', '0', '2026-06-10 09:51:55', '1'),
('26', '0', '0', '0', '2026-06-10 09:51:56', '1'),
('27', '0', '0', '0', '2026-06-10 09:51:56', '1'),
('28', '0', '0', '0', '2026-06-10 09:52:22', '1'),
('29', '0', '0', '0', '2026-06-10 09:52:23', '1'),
('30', '0', '0', '0', '2026-06-10 09:52:23', '1'),
('31', '0', '0', '0', '2026-06-10 09:52:23', '1'),
('32', '0', '0', '0', '2026-06-10 09:53:50', '1'),
('33', '0', '0', '0', '2026-06-10 09:53:52', '1'),
('34', '0', '0', '0', '2026-06-10 09:53:52', '1'),
('35', '0', '0', '0', '2026-06-10 09:53:52', '1'),
('36', '0', '0', '0', '2026-06-10 10:14:19', '1'),
('37', '0', '0', '0', '2026-06-10 10:14:20', '1'),
('38', '0', '0', '0', '2026-06-10 10:14:20', '1'),
('39', '0', '0', '0', '2026-06-10 10:14:20', '1'),
('40', '0', '0', '0', '2026-06-10 10:14:21', '1'),
('41', '0', '0', '0', '2026-06-10 10:14:22', '1'),
('42', '0', '0', '0', '2026-06-10 10:14:22', '1'),
('43', '0', '0', '0', '2026-06-10 10:14:22', '1'),
('44', '0', '0', '0', '2026-06-10 10:14:30', '1'),
('45', '0', '0', '0', '2026-06-10 10:14:31', '1'),
('46', '0', '0', '0', '2026-06-10 10:14:31', '1'),
('47', '0', '0', '0', '2026-06-10 10:17:34', '1'),
('48', '0', '0', '0', '2026-06-10 10:17:35', '1'),
('49', '0', '0', '0', '2026-06-10 10:17:35', '1'),
('50', '0', '0', '0', '2026-06-10 10:17:35', '1'),
('51', '0', '0', '0', '2026-06-10 10:17:36', '1'),
('52', '0', '0', '0', '2026-06-10 10:17:37', '1'),
('53', '0', '0', '0', '2026-06-10 10:17:37', '1'),
('54', '0', '0', '0', '2026-06-10 10:17:37', '1'),
('55', '0', '0', '0', '2026-06-10 10:44:14', '1'),
('56', '0', '0', '0', '2026-06-10 10:44:15', '1'),
('57', '0', '0', '0', '2026-06-10 10:44:15', '1'),
('58', '0', '0', '0', '2026-06-10 10:44:15', '1'),
('59', '100', '100', '0', '2026-06-10 11:59:34', '1'),
('60', '0', '0', '0', '2026-06-10 17:44:17', '1'),
('61', '0', '0', '0', '2026-06-10 17:44:18', '1'),
('62', '0', '0', '0', '2026-06-10 17:44:18', '1'),
('63', '0', '0', '0', '2026-06-10 17:44:18', '1'),
('64', '500', '500', '0', '2026-06-10 17:44:54', '1'),
('65', '0', '0', '0', '2026-06-13 00:19:20', '1'),
('66', '0', '0', '0', '2026-06-13 00:19:21', '1'),
('67', '0', '0', '0', '2026-06-13 00:19:21', '1'),
('68', '0', '0', '0', '2026-06-13 00:19:21', '1'),
('69', '0', '0', '0', '2026-06-15 23:07:01', '1'),
('70', '0', '0', '0', '2026-06-15 23:07:02', '1'),
('71', '0', '0', '0', '2026-06-15 23:07:02', '1'),
('72', '0', '0', '0', '2026-06-15 23:07:02', '1'),
('73', '0', '0', '0', '2026-06-15 23:07:32', '1'),
('74', '0', '0', '0', '2026-06-15 23:07:34', '1'),
('75', '0', '0', '0', '2026-06-15 23:07:35', '1'),
('76', '0', '0', '0', '2026-06-15 23:12:18', '1'),
('77', '0', '0', '0', '2026-06-15 23:12:19', '1'),
('78', '0', '0', '0', '2026-06-15 23:12:19', '1'),
('79', '0', '0', '0', '2026-06-15 23:12:19', '1');

-- ----------------------------
-- Data for table `sms_provider_settings`
-- ----------------------------
INSERT INTO `sms_provider_settings` (`id`, `tenant_id`, `provider`, `api_key`, `api_secret`, `username`, `sender_id`, `api_url`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'africastalking', NULL, NULL, NULL, 'JAKABABA', NULL, '1', '2026-06-10 09:39:05', '2026-06-10 09:39:05');

-- ----------------------------
-- Data for table `stock_movements`
-- ----------------------------
INSERT INTO `stock_movements` (`id`, `branch_id`, `product_id`, `variant_id`, `batch_id`, `movement_type`, `quantity_change`, `quantity_before`, `quantity_after`, `reference_table`, `reference_id`, `notes`, `user_id`, `created_at`, `tenant_id`) VALUES
('1', '1', '1', NULL, NULL, 'sale', '-1', '15', '14', NULL, NULL, 'Sale', '1', '2026-05-01 17:38:08', '1'),
('2', '1', '1', NULL, NULL, 'sale', '-1', '14', '13', NULL, NULL, 'Sale', '1', '2026-05-01 22:37:36', '1'),
('3', '1', '1', NULL, NULL, 'sale', '-2', '13', '11', NULL, NULL, 'Sale', '1', '2026-05-01 23:06:40', '1'),
('4', '1', '1', NULL, NULL, 'sale', '-1', '11', '10', NULL, NULL, 'Sale', '1', '2026-05-01 23:09:49', '1'),
('5', '1', '1', NULL, NULL, 'sale', '-1', '10', '9', NULL, NULL, 'Sale', '2', '2026-05-02 17:31:23', '1'),
('6', '1', '1', NULL, NULL, 'sale', '-1', '9', '8', NULL, NULL, 'Sale', '2', '2026-05-02 18:37:27', '1'),
('7', '1', '1', NULL, NULL, 'sale', '-1', '9', '8', NULL, NULL, 'Sale', '2', '2026-05-11 12:32:06', '1'),
('8', '1', '1', NULL, NULL, 'sale', '-1', '8', '7', NULL, NULL, 'Sale', '2', '2026-05-11 12:41:18', '1'),
('9', '1', '1', NULL, NULL, 'sale', '-4', '7', '3', NULL, NULL, 'Sale', '2', '2026-05-11 16:35:50', '1'),
('10', '1', '1', NULL, NULL, 'sale', '-2', '3', '1', NULL, NULL, 'Sale', '2', '2026-05-11 17:34:32', '1'),
('11', '1', '1', NULL, NULL, 'sale', '-1', '1', '0', NULL, NULL, 'Sale', '2', '2026-05-11 17:43:17', '1'),
('12', '1', '1', NULL, NULL, 'sale', '-2', '15', '13', NULL, NULL, 'Sale', '2', '2026-05-11 17:52:16', '1'),
('13', '1', '1', NULL, NULL, 'sale', '-3', '13', '10', NULL, NULL, 'Sale', '2', '2026-05-11 17:52:39', '1'),
('14', '1', '1', NULL, NULL, 'sale', '-1', '10', '9', NULL, NULL, 'Sale', '2', '2026-05-11 17:54:20', '1'),
('15', '1', '1', NULL, NULL, 'sale', '-1', '9', '8', NULL, NULL, 'Sale', '2', '2026-05-11 18:07:08', '1'),
('16', '1', '1', NULL, NULL, 'sale', '-1', '8', '7', NULL, NULL, 'Sale', '2', '2026-05-11 18:30:57', '1'),
('17', '1', '1', NULL, NULL, 'sale', '-1', '7', '6', NULL, NULL, 'Sale', '2', '2026-05-12 10:19:39', '1'),
('18', '1', '1', NULL, NULL, 'sale', '-1', '6', '5', NULL, NULL, 'Sale', '2', '2026-05-12 16:47:10', '1'),
('19', '1', '1', NULL, NULL, 'sale', '-1', '5', '4', NULL, NULL, 'Sale', '2', '2026-05-12 20:56:09', '1'),
('20', '1', '2', NULL, NULL, 'sale', '-1', '4', '3', 'sales', '84', NULL, '2', '2026-06-13 01:03:28', '1'),
('21', '1', '2', NULL, NULL, 'sale', '-1', '3', '2', 'sales', '85', NULL, '2', '2026-06-13 01:04:01', '1'),
('22', '1', '2', NULL, NULL, 'sale', '-2', '2', '0', 'sales', '86', NULL, '2', '2026-06-13 09:45:11', '1'),
('23', '1', '4', NULL, NULL, 'sale', '-1', '3', '2', 'sales', '87', NULL, '2', '2026-06-14 01:59:54', '1'),
('24', '1', '4', NULL, NULL, 'sale', '-1', '2', '1', 'sales', '88', NULL, '2', '2026-06-14 03:30:03', '1'),
('25', '1', '4', NULL, NULL, 'sale', '-1', '1', '0', 'sales', '89', NULL, '2', '2026-06-15 13:06:26', '1'),
('26', '1', '8', NULL, NULL, 'sale', '-1', '1', '0', 'sales', '90', NULL, '2', '2026-06-15 13:31:37', '1'),
('27', '1', '2', NULL, NULL, 'sale', '-1', '10', '9', 'sales', '91', NULL, '2', '2026-06-15 13:51:33', '1'),
('28', '1', '2', NULL, NULL, 'sale', '-1', '9', '8', 'sales', '92', NULL, '2', '2026-06-15 20:17:47', '1'),
('29', '1', '5', NULL, NULL, 'sale', '-1', '10', '9', 'sales', '92', NULL, '2', '2026-06-15 20:17:47', '1'),
('30', '1', '1', NULL, NULL, 'sale', '-2', '10', '8', 'sales', '93', NULL, '1', '2026-06-15 20:29:45', '1'),
('31', '1', '4', NULL, NULL, 'sale', '-1', '10', '9', 'sales', '94', NULL, '1', '2026-06-16 13:32:33', '1'),
('32', '1', '7', NULL, NULL, 'sale', '-1', '10', '9', 'sales', '95', NULL, '1', '2026-06-16 19:28:58', '1'),
('33', '1', '2', NULL, NULL, 'sale', '-1', '8', '7', 'sales', '96', NULL, '1', '2026-06-17 03:44:35', '1'),
('34', '1', '5', NULL, NULL, 'sale', '-1', '9', '8', 'sales', '97', NULL, '1', '2026-06-17 04:04:13', '1'),
('35', '1', '6', NULL, NULL, 'sale', '-1', '10', '9', 'sales', '98', NULL, '2', '2026-06-19 17:03:20', '1'),
('36', '1', '5', NULL, NULL, 'sale', '-1', '8', '7', 'sales', '99', NULL, '2', '2026-06-22 06:03:36', '1'),
('37', '1', '6', NULL, NULL, 'sale', '-1', '9', '8', 'sales', '99', NULL, '2', '2026-06-22 06:03:36', '1'),
('38', '1', '4', NULL, NULL, 'sale', '-1', '9', '8', 'sales', '99', NULL, '2', '2026-06-22 06:03:36', '1'),
('39', '1', '8', NULL, NULL, 'sale', '-1', '10', '9', 'sales', '99', NULL, '2', '2026-06-22 06:03:36', '1'),
('40', '1', '3', NULL, NULL, 'sale', '-1', '10', '9', 'sales', '100', NULL, '2', '2026-06-23 04:55:03', '1');

-- ----------------------------
-- Data for table `storefront_settings`
-- ----------------------------
INSERT INTO `storefront_settings` (`id`, `tenant_id`, `setting_key`, `setting_value`, `data_type`, `category`, `is_public`, `created_at`, `updated_at`) VALUES
('1', '1', 'site_title', 'Jakababa Online Store', 'text', 'general', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('2', '1', 'site_description', 'Shop the best deals online', 'text', 'seo', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('3', '1', 'logo_url', '/uploads/store/logo_1.png', 'url', 'design', '1', '2026-05-17 09:47:30', '2026-06-22 13:57:29'),
('4', '1', 'favicon_url', '/uploads/store/favicon_1_1782152786.png', 'url', 'design', '1', '2026-05-17 09:47:30', '2026-06-22 21:26:26'),
('5', '1', 'primary_color', '#f68b1e', 'color', 'design', '1', '2026-05-17 09:47:30', '2026-06-23 18:05:40'),
('6', '1', 'secondary_color', '#1a1a2e', 'color', 'design', '1', '2026-05-17 09:47:30', '2026-06-23 18:05:40'),
('7', '1', 'currency', 'KES', 'text', 'general', '1', '2026-05-17 09:47:30', '2026-06-22 21:19:49'),
('8', '1', 'whatsapp_number', '', 'text', 'social', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('9', '1', 'facebook_url', '', 'url', 'social', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('10', '1', 'instagram_url', '', 'url', 'social', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('11', '1', 'show_reviews', '1', 'boolean', 'general', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('12', '1', 'show_stock_count', '0', 'boolean', 'general', '1', '2026-05-17 09:47:30', '2026-06-22 21:18:40'),
('13', '1', 'min_order_amount', '0', 'number', 'general', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('14', '1', 'free_shipping_threshold', '0', 'number', 'general', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('15', '1', 'mpesa_enabled', '1', 'boolean', 'payment', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('16', '1', 'cod_enabled', '1', 'boolean', 'payment', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('17', '1', 'stripe_enabled', '0', 'boolean', 'payment', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('18', '1', 'stripe_publishable_key', '', 'text', 'payment', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('19', '1', 'meta_title', 'Jakababa - Online Shopping', 'text', 'seo', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('20', '1', 'meta_description', 'Shop online at Jakababa. Best prices, fast delivery.', 'text', 'seo', '1', '2026-05-17 09:47:30', '2026-05-17 09:47:30'),
('21', '1', 'online_store_enabled', '1', 'text', 'general', '1', '2026-06-22 19:20:16', '2026-06-23 01:10:45'),
('22', '1', 'store_name', 'Shacaz Baby Shop', 'text', 'general', '1', '2026-06-22 19:20:16', '2026-06-22 21:24:45'),
('34', '1', 'tiktok_url', '', 'text', 'general', '1', '2026-06-22 13:57:29', '2026-06-22 13:57:29'),
('42', '1', 'announcement_bar_text', '', 'text', 'general', '1', '2026-06-22 13:57:29', '2026-06-22 13:57:29'),
('43', '1', 'announcement_bar_enabled', '0', 'text', 'general', '1', '2026-06-22 13:57:29', '2026-06-22 21:18:40'),
('44', '1', 'homepage_sections', 'top_selling,flash_sale,new_arrivals,categories', 'text', 'general', '1', '2026-06-22 13:57:29', '2026-06-23 18:33:26'),
('65', '1', 'show_deal_spotlight', '0', 'text', 'general', '1', '2026-06-22 14:05:11', '2026-06-23 18:33:00'),
('66', '1', 'show_newsletter', '0', 'text', 'general', '1', '2026-06-22 14:05:11', '2026-06-23 18:32:58'),
('67', '1', 'show_recently_viewed', '0', 'text', 'general', '1', '2026-06-22 14:05:11', '2026-06-23 18:32:55'),
('68', '1', 'deal_of_the_day_product_id', '0', 'text', 'general', '1', '2026-06-22 14:05:11', '2026-06-22 21:18:40'),
('174', '1', 'font_family', 'system', 'text', 'general', '1', '2026-06-22 21:18:39', '2026-06-22 21:18:39'),
('175', '1', 'heading_font_size', 'medium', 'text', 'general', '1', '2026-06-22 21:18:39', '2026-06-22 21:18:39'),
('176', '1', 'body_font_size', 'medium', 'text', 'general', '1', '2026-06-22 21:18:39', '2026-06-22 21:18:39'),
('177', '1', 'button_style', 'rounded', 'text', 'general', '1', '2026-06-22 21:18:40', '2026-06-22 21:18:40'),
('178', '1', 'button_padding', 'normal', 'text', 'general', '1', '2026-06-22 21:18:40', '2026-06-22 21:18:40'),
('179', '1', 'card_style', 'elevated', 'text', 'general', '1', '2026-06-22 21:18:40', '2026-06-22 21:18:40'),
('181', '1', 'announcement_bar_color', '#f68b1e', 'text', 'general', '1', '2026-06-22 21:18:40', '2026-06-22 21:18:40'),
('398', '1', 'layout_mode', 'full-width', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('399', '1', 'layout_container_width', '1200', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('400', '1', 'layout_sidebar', 'none', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('401', '1', 'layout_grid_gap', 'medium', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('402', '1', 'layout_content_padding', 'normal', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('403', '1', 'header_style', 'standard', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:21:14'),
('404', '1', 'header_sticky', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:15:14'),
('405', '1', 'header_transparent_home', '0', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:56:38'),
('406', '1', 'header_bg_color', '#1a1a2e', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:21:14'),
('407', '1', 'header_text_color', 'light', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:21:14'),
('408', '1', 'header_mobile_style', 'overlay', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:15:14'),
('409', '1', 'header_dropdown_style', 'simple', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:15:26'),
('410', '1', 'header_top_bar', '0', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:15:26'),
('411', '1', 'header_top_bar_text', '', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:15:26'),
('412', '1', 'header_top_bar_bg', '#1a1a2e', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 16:21:14'),
('413', '1', 'header_top_bar_text_color', 'light', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:54:26'),
('414', '1', 'header_show_cart', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 18:04:08'),
('415', '1', 'header_show_account', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 18:04:01'),
('416', '1', 'header_show_search', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 18:04:11'),
('417', '1', 'header_show_wishlist', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-23 18:04:13'),
('435', '1', 'store_notice_message', 'Welcome to our store!', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('436', '1', 'store_notice_type', 'info', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('437', '1', 'store_notice_dismissible', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('438', '1', 'store_notice_link_text', '', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('439', '1', 'store_notice_link_url', '', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('440', '1', 'featured_product_id', '0', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('441', '1', 'featured_product_badge', 'Featured', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('442', '1', 'featured_product_show_description', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('443', '1', 'featured_product_show_reviews', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('444', '1', 'featured_product_image_position', 'left', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('445', '1', 'blog_posts_title', 'Latest Posts', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('446', '1', 'blog_posts_limit', '3', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('447', '1', 'blog_posts_columns', '2', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('448', '1', 'blog_posts_show_date', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('449', '1', 'blog_posts_show_excerpt', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('450', '1', 'blog_posts_show_read_more', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('451', '1', 'payment_icons_title', 'We Accept', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('452', '1', 'payment_icons_layout', 'row', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('453', '1', 'payment_icons_visa', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('454', '1', 'payment_icons_mastercard', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('455', '1', 'payment_icons_amex', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('456', '1', 'payment_icons_paypal', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('457', '1', 'payment_icons_mpesa', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('458', '1', 'social_links_title', 'Follow Us', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('459', '1', 'social_links_style', 'icon-with-label', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('460', '1', 'social_links_align', 'left', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('461', '1', 'social_links_facebook', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('462', '1', 'social_links_instagram', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('463', '1', 'social_links_twitter', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('464', '1', 'social_links_tiktok', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('465', '1', 'social_links_youtube', '0', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('466', '1', 'social_links_whatsapp', '0', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('467', '1', 'footer_bottom_text', '', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('468', '1', 'footer_show_payment_icons', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53'),
('469', '1', 'footer_show_social_links', '1', 'text', 'general', '1', '2026-06-22 23:52:53', '2026-06-22 23:52:53');

-- ----------------------------
-- Data for table `storefront_themes`
-- ----------------------------
INSERT INTO `storefront_themes` (`id`, `tenant_id`, `theme_name`, `primary_color`, `secondary_color`, `background_color`, `text_color`, `font_family`, `custom_css`, `custom_js`, `header_html`, `footer_html`, `is_active`, `created_at`, `updated_at`) VALUES
('1', '1', 'default', '#f68b1e', '#1a1a2e', '#ffffff', '#282828', 'Inter', NULL, NULL, NULL, NULL, '1', '2026-05-17 09:47:32', '2026-05-17 09:47:32');

-- ----------------------------
-- Data for table `suppliers`
-- ----------------------------
INSERT INTO `suppliers` (`id`, `name`, `contact`, `phone`, `email`, `active`, `address`, `tax_id`, `payment_terms`, `notes`, `created_by`, `branch_id`, `created_at`, `status`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`, `tenant_id`) VALUES
('3', 'MomEasy', '', '', '', '1', '', '', '', NULL, '2', NULL, '2026-06-09 15:35:37', '1', NULL, NULL, NULL, NULL, '1');

-- ----------------------------
-- Data for table `system_settings`
-- ----------------------------
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_label`, `setting_description`, `setting_group`, `input_type`, `setting_options`, `is_required`, `placeholder`, `sort_order`, `created_at`, `updated_at`, `tenant_id`) VALUES
('1', 'site_name', 'Jakababa', 'Site Name', 'The name of your platform displayed on the landing page and browser title', 'general', 'text', NULL, '0', NULL, '1', '2026-06-22 03:41:29', '2026-06-22 06:06:00', '0'),
('2', 'site_tagline', 'All-in-One POS System for African Businesses', 'Tagline', 'Short tagline shown in the hero section and meta title', 'general', 'text', NULL, '0', NULL, '2', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('3', 'site_logo_url', '', 'Logo URL', 'URL to your logo image (leave empty for default icon). Upload to /uploads/branding/', 'general', 'url', NULL, '0', NULL, '3', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('4', 'site_favicon_url', '', 'Favicon URL', 'URL to your favicon image', 'general', 'url', NULL, '0', NULL, '4', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('5', 'site_url', '', 'Site URL', 'The public URL of your site (e.g. https://jakpos.com)', 'general', 'url', NULL, '0', NULL, '5', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('6', 'contact_email', '', 'Contact Email', 'Public contact email address', 'general', 'email', NULL, '0', NULL, '6', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('7', 'contact_phone', '', 'Contact Phone', 'Public contact phone number', 'general', 'text', NULL, '0', NULL, '7', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('8', 'hero_title', 'The All-in-One AI System for African <span class=\"text-primary\">Retail</span> and <span class=\"text-primary\">Wholesale</span> Success', 'Hero Title', 'Main heading on the landing page hero section (HTML allowed)', 'general', 'textarea', NULL, '0', NULL, '10', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('9', 'hero_subtitle', 'Manage your sales, inventory, and payments in one place. JAKPOS keeps your business running smoothly, efficiently, and intelligently—anywhere, online or offline.', 'Hero Subtitle', 'Paragraph text below the hero heading', 'general', 'textarea', NULL, '0', NULL, '11', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('10', 'hero_cta_text', 'Get a Free Trial', 'Hero CTA Button Text', 'Text for the main call-to-action button', 'general', 'text', NULL, '0', NULL, '12', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('11', 'hero_cta_url', 'auth/register.php', 'Hero CTA URL', 'Link for the main CTA button', 'general', 'url', NULL, '0', NULL, '13', '2026-06-22 03:41:29', '2026-06-22 04:25:19', '0'),
('12', 'stats_businesses', '2,000+', 'Stats: Businesses', 'Number shown in hero stats bar', 'general', 'text', NULL, '0', NULL, '14', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('13', 'stats_uptime', '99.9%', 'Stats: Uptime', 'Uptime percentage shown in hero stats bar', 'general', 'text', NULL, '0', NULL, '15', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('14', 'stats_support', '24/7', 'Stats: Support', 'Support availability shown in hero stats bar', 'general', 'text', NULL, '0', NULL, '16', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('15', 'show_pricing_from_db', '1', 'Dynamic Pricing', 'Pull pricing cards from database plans (1=yes, 0=use static)', 'general', 'checkbox', NULL, '0', NULL, '20', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('16', 'seo_meta_description', 'Modern point-of-sale system for African businesses. Manage inventory, sales, customers, and analytics all in one place.', 'Meta Description', 'SEO meta description for the landing page', 'seo', 'textarea', NULL, '0', NULL, '1', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('17', 'seo_og_image', '', 'OG Image URL', 'Social sharing image URL', 'seo', 'url', NULL, '0', NULL, '2', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('18', 'seo_keywords', 'POS, point of sale, inventory management, African business, M-Pesa, retail', 'Meta Keywords', 'Comma-separated keywords', 'seo', 'text', NULL, '0', NULL, '3', '2026-06-22 03:41:29', '2026-06-22 03:41:29', '0'),
('19', 'landing_hero_image', '', 'Hero Image', 'Main hero section background or illustration image (recommended: 1200x800px)', 'landing', 'image', NULL, '0', NULL, '1', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('20', 'landing_hero_image_alt', 'POS Dashboard Preview', 'Hero Image Alt Text', 'Alt text for accessibility', 'landing', 'text', NULL, '0', NULL, '2', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('21', 'landing_logo', '', 'Site Logo', 'Upload your site logo (recommended: 200x60px, PNG with transparency)', 'landing', 'image', NULL, '0', NULL, '3', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('22', 'landing_logo_dark', '', 'Logo (Dark Background)', 'Logo variant for dark backgrounds (footer, etc.)', 'landing', 'image', NULL, '0', NULL, '4', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('23', 'landing_favicon', '', 'Favicon', 'Browser tab icon (recommended: 32x32px or 64x64px, ICO/PNG)', 'landing', 'image', NULL, '0', NULL, '5', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('24', 'landing_product_image_1', '', 'Product Screenshot 1', 'Screenshot for the Products section (e.g. POS interface)', 'landing', 'image', NULL, '0', NULL, '10', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('25', 'landing_product_image_2', '', 'Product Screenshot 2', 'Screenshot for the Features section (e.g. inventory)', 'landing', 'image', NULL, '0', NULL, '11', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('26', 'landing_product_image_3', '', 'Product Screenshot 3', 'Screenshot for additional showcase area', 'landing', 'image', NULL, '0', NULL, '12', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('27', 'landing_trust_badge_1', '', 'Trust Badge 1', 'Certification/trust badge image (e.g. ODPC registration)', 'landing', 'image', NULL, '0', NULL, '20', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('28', 'landing_trust_badge_2', '', 'Trust Badge 2', 'Additional trust badge or partner logo', 'landing', 'image', NULL, '0', NULL, '21', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0'),
('29', 'landing_og_image', '', 'Social Share Image', 'Image displayed when your site is shared on social media (recommended: 1200x630px)', 'landing', 'image', NULL, '0', NULL, '30', '2026-06-22 05:43:14', '2026-06-22 05:43:14', '0');

-- ----------------------------
-- Data for table `tax_rates`
-- ----------------------------
INSERT INTO `tax_rates` (`id`, `name`, `rate`, `description`, `active`, `created_at`, `updated_at`, `is_default`, `type`, `tenant_id`) VALUES
('1', 'VAT', '16.00', 'Value Added Tax', '1', '2026-04-30 22:05:32', '2026-06-15 20:13:53', '0', 'inclusive', '1'),
('2', 'Zero Rated', '16.00', 'Zero-rated items', '1', '2026-04-30 22:05:32', '2026-06-05 02:55:23', '0', 'inclusive', '1');

-- ----------------------------
-- Data for table `tenant_settings`
-- ----------------------------
INSERT INTO `tenant_settings` (`id`, `tenant_id`, `setting_key`, `setting_value`, `updated_at`) VALUES
('1', '1', 'auto_print_label_size', 'k22', '2026-06-16 13:56:03'),
('12', '1', 'auto_print_labels_on_sale', '1', '2026-06-09 10:44:35'),
('19', '1', 'auto_print_method', 'escpos', '2026-05-28 19:13:19');

-- ----------------------------
-- Data for table `tenant_subscriptions`
-- ----------------------------
INSERT INTO `tenant_subscriptions` (`id`, `tenant_id`, `plan_id`, `status`, `billing_cycle`, `amount`, `currency`, `trial_ends_at`, `current_period_start`, `current_period_end`, `cancelled_at`, `cancel_reason`, `payment_method`, `payment_reference`, `metadata`, `created_at`, `updated_at`) VALUES
('1', '1', '4', 'active', 'monthly', '4999.00', 'KES', NULL, '2026-04-30 22:14:20', '2026-05-30 22:14:20', NULL, NULL, NULL, NULL, NULL, '2026-04-30 22:14:20', '2026-04-30 22:14:20');

-- ----------------------------
-- Data for table `tenants`
-- ----------------------------
INSERT INTO `tenants` (`id`, `uuid`, `subdomain`, `domain`, `name`, `business_type`, `email`, `phone`, `address`, `city`, `country`, `timezone`, `currency`, `language`, `logo_url`, `favicon_url`, `theme_config`, `receipt_config`, `tax_number`, `tax_rate`, `is_active`, `is_verified`, `is_suspended`, `suspension_reason`, `verified_at`, `activated_at`, `expires_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`, `status`, `settings`, `features`) VALUES
('1', 'cf7718fa-44be-11f1-80fc-14abc50b6cdc', 'demo', NULL, 'JAKPOS', '', '', '', '', NULL, NULL, 'Africa/Nairobi', 'KES', 'en', 'uploads/logos/company_1/logo_1_1778615488.png', NULL, NULL, NULL, NULL, '16.00', '1', '1', '0', NULL, NULL, NULL, NULL, NULL, '2026-04-30 22:14:20', '2026-06-15 17:03:48', NULL, 'active', NULL, NULL);

-- ----------------------------
-- Data for table `usage_metering_types`
-- ----------------------------
INSERT INTO `usage_metering_types` (`id`, `slug`, `name`, `unit`, `is_metered`, `reset_period`, `created_at`, `updated_at`) VALUES
('1', 'api_calls', 'API Calls', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('2', 'pos_transactions', 'POS Transactions', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('3', 'online_orders', 'Online Orders', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('4', 'storage_mb', 'Storage', 'mb', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('5', 'active_users', 'Active Users', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('6', 'branches', 'Branches', 'count', '0', 'billing_cycle', '2026-05-18 08:41:28', '2026-05-18 08:41:28'),
('7', 'webhooks', 'Webhook Deliveries', 'count', '1', 'month', '2026-05-18 08:41:28', '2026-05-18 08:41:28');

-- ----------------------------
-- Data for table `user_roles`
-- ----------------------------
INSERT INTO `user_roles` (`user_id`, `role_id`, `created_at`, `tenant_id`) VALUES
('1', '3', '2026-06-15 22:23:39', '1');

-- ----------------------------
-- Data for table `user_tokens`
-- ----------------------------
INSERT INTO `user_tokens` (`id`, `user_id`, `token_hash`, `type`, `expires_at`, `created_at`, `tenant_id`) VALUES
('1', '2', '40465ab53de633e5e42b4d79a7561158f83bdd7918870a228fad7076afb018b4', 'remember_me', '2026-07-12 22:42:13', '2026-06-12 22:42:13', '1'),
('2', '2', '08d7eff398bc8ff2fbabedab247c54efa149156089f3018ab009733fa7165b1a', 'remember_me', '2026-07-12 22:44:23', '2026-06-12 22:44:23', '1'),
('3', '2', 'b4ae44918e492cbacdf319073b2804bee17d952274be84cbf3b7d2aa8b64de3f', 'remember_me', '2026-07-12 22:44:50', '2026-06-12 22:44:50', '1'),
('4', '2', '33fe77131f373fc1c7c9c661098acd9892f17163b4f9b4a70829f0a17d939b4a', 'remember_me', '2026-07-12 22:45:31', '2026-06-12 22:45:31', '1'),
('5', '2', 'ea5ca05b93da9d8e1f6c4d67aab8e62d7081e0f69d0e29814e13a1756ed9ce98', 'remember_me', '2026-07-12 22:46:52', '2026-06-12 22:46:52', '1'),
('6', '2', 'df81380c2f86a3eb2b928c9244447e0415e728df6632cbc2d4df358698ff7c8c', 'remember_me', '2026-07-12 22:56:32', '2026-06-12 22:56:32', '1'),
('7', '2', '0c008fb697af9bd88b9135490dd9ced280d91dbb73055d6e6372f826ddc35bed', 'remember_me', '2026-07-12 22:59:32', '2026-06-12 22:59:32', '1'),
('8', '2', '9e78f16a1c14bf20d609b951944e63adcff4f86fe2c0b00e238e6304bed90754', 'remember_me', '2026-07-12 23:02:10', '2026-06-12 23:02:10', '1'),
('9', '2', 'ff973c528ad60a7801ba7e926551c3bffc817130e5503a3e71f437092c73ea2a', 'remember_me', '2026-07-12 23:24:17', '2026-06-12 23:24:17', '1'),
('10', '2', 'bb73e3ca8dea79bab1679e94c1c5e003f7756088d5b5a125acd326c41ccea536', 'remember_me', '2026-07-12 23:24:44', '2026-06-12 23:24:44', '1'),
('11', '2', '8382daff9324ccf387237be5b71952fb929024628c928982dc337c643c0e4cb7', 'remember_me', '2026-07-12 23:38:12', '2026-06-12 23:38:12', '1'),
('12', '2', '4d05cdcd2f663952e132edc812e86b8964b55a07b1a5a1fb6180eee199849b24', 'remember_me', '2026-07-12 23:39:49', '2026-06-12 23:39:49', '1'),
('13', '2', '53148ce75f781135c8aa68152723256df40cfe6422b0480a433e98692a016720', 'remember_me', '2026-07-12 23:40:39', '2026-06-12 23:40:39', '1'),
('14', '2', '982925eb6f8455d562fec12753074a0e74597f11f655342acf593f0134b46504', 'remember_me', '2026-07-12 23:41:41', '2026-06-12 23:41:41', '1'),
('15', '2', 'b3faa086c3492e78ae69e233825a74e0e879ba43b6926031bcf2564c443241df', 'remember_me', '2026-07-12 23:48:56', '2026-06-12 23:48:56', '1'),
('16', '2', '737b2dcb0d7d1a37de2d75bf70752a49cbeb244e1caf43386693d2af45020585', 'remember_me', '2026-07-13 00:01:05', '2026-06-13 00:01:05', '1'),
('17', '2', '223574d854deefc0b40fd0f1d86cfd29a080769fa4a7bf5bb700f642b34ce4e6', 'remember_me', '2026-07-13 00:10:41', '2026-06-13 00:10:41', '1'),
('18', '2', 'ed4df993ec1e9fd780816e59f082f776b2add87056b955139377292877761bca', 'remember_me', '2026-07-13 00:13:47', '2026-06-13 00:13:47', '1'),
('19', '2', '0c192e3bd81b359786992e34e81fb710137eefaf4adac5a29c23bcf3eefefff5', 'remember_me', '2026-07-13 00:17:40', '2026-06-13 00:17:40', '1'),
('20', '2', '1e3f4a38b5acc6e1f9ad4d61e96dadfd8a1c366f4c1e0c35a387994b737be1ab', 'remember_me', '2026-07-13 00:31:29', '2026-06-13 00:31:29', '1'),
('21', '2', '7cc937f991cbeeed56cc847f3bc6af69efdae746d2c4322cf30377d4c4200c1b', 'remember_me', '2026-07-13 00:31:56', '2026-06-13 00:31:56', '1'),
('22', '2', '9d694cf599ea1ddd2fbd13da28b99711e71444e2be81a472ddbcc97c91a79fbc', 'remember_me', '2026-07-13 01:11:29', '2026-06-13 01:11:29', '1'),
('23', '2', 'f7abf87cdb779ebdf1a0ed6fffdab234affe74bc5349277ead718567784458d0', 'remember_me', '2026-07-13 09:44:18', '2026-06-13 09:44:18', '1'),
('24', '2', '3c79d7cb4fadc388c87b08dbf0eccb3e85fe697c6d03f2eb7283489ed935e6e1', 'remember_me', '2026-07-13 10:02:26', '2026-06-13 10:02:26', '1'),
('26', '2', 'c32a1514f9393caa1fc53d234d6207688768108a23bc57f24df44b9afcab08c8', 'remember_me', '2026-07-13 11:05:56', '2026-06-13 11:05:56', '1'),
('29', '1', '2223586972cc78bc96b650b781a3439e6d5bb70ad657f43005b6f738e4226467', 'remember_me', '2026-07-17 20:30:46', '2026-06-17 20:30:46', '1');

-- ----------------------------
-- Data for table `users`
-- ----------------------------
INSERT INTO `users` (`id`, `name`, `email`, `username`, `role_id`, `password_hash`, `pin`, `pin_hash`, `branch_id`, `status`, `created_at`, `phone`, `address`, `avatar`, `updated_at`, `last_login`, `password_reset_token`, `password_reset_expires`, `login_attempts`, `locked_until`, `two_factor_secret`, `two_factor_enabled`, `last_login_ip`, `last_password_change`, `require_password_change`, `api_token`, `api_token_expires`, `deleted_at`, `failed_login_attempts`, `customer_group`, `tenant_id`, `business_type`, `is_active`) VALUES
('1', 'Wycliffe Bunde', 'user_1@secured.local', 'Wycliffe', '3', '$2y$10$LfOJ1V2izYu15fTaFIRb/u32sWCovIKOhZZHDCET4QEXOr3g7EvC.', NULL, NULL, '1', '1', '2026-04-30 22:14:20', '+254759714022', 'REDACTED', NULL, '2026-06-15 13:25:12', '2026-06-22 22:08:11', NULL, NULL, '0', NULL, NULL, '0', '::1', NULL, '1', NULL, NULL, NULL, '0', 'regular', '1', 'retail', '1'),
('2', 'Bunde', 'user_2@secured.local', 'admin', '5', '$2y$10$vJDToEoziFVTBr5Jt0.B1.NrV4PaxvQJcsxeixyDuy.xmTn0XqS7K', NULL, NULL, '1', '1', '2026-05-02 07:31:47', '+254726527245', 'REDACTED', '/uploads/avatars/avatar_2_1778874962.jpg', '2026-06-20 15:32:57', '2026-06-24 05:31:51', NULL, NULL, '0', NULL, NULL, '0', '::1', NULL, '1', NULL, NULL, NULL, '0', 'regular', '1', 'retail', '1');

-- ----------------------------
-- Data for table `vouchers`
-- ----------------------------
INSERT INTO `vouchers` (`id`, `code`, `type`, `value`, `expires_at`, `active`, `min_purchase`, `max_discount`, `usage_limit`, `usage_count`, `description`, `branch_id`, `created_at`, `tenant_id`) VALUES
('1', 'BGUC-9ENR', 'fixed', '1000.00', '2026-06-10', '1', '5000.00', NULL, '2', '0', 'For the repeat customers', NULL, '2026-06-08 21:26:25', '1');

SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=1;
