-- ============================================================
-- Comprehensive Migration: Create All Missing Tables - PART 5
-- Date: 2026-06-15
-- Description: Tables 121-150 (Offline, Reports, Users, Company)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 17. LABEL PRINTING
CREATE TABLE IF NOT EXISTS `label_templates` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `name`         VARCHAR(150) NOT NULL,
    `type`         ENUM('product','shelf','barcode','receipt','custom') NOT NULL DEFAULT 'product',
    `width_mm`     DECIMAL(6,2) NULL,
    `height_mm`    DECIMAL(6,2) NULL,
    `template`     LONGTEXT NULL,
    `is_default`   TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_type` (`tenant_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. SECURITY & RATE LIMITING
CREATE TABLE IF NOT EXISTS `rate_limit_blocks` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `identifier`   VARCHAR(200) NOT NULL,
    `type`         VARCHAR(50) NOT NULL DEFAULT 'ip',
    `blocked_until` DATETIME NOT NULL,
    `reason`       VARCHAR(200) NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_identifier_type` (`identifier`, `type`),
    INDEX `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. OFFLINE SYNC
CREATE TABLE IF NOT EXISTS `offline_transactions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `device_id`    VARCHAR(100) NULL,
    `local_id`     VARCHAR(100) NOT NULL,
    `payload`      LONGTEXT NOT NULL,
    `status`       ENUM('pending','synced','conflict','failed') NOT NULL DEFAULT 'pending',
    `synced_at`    DATETIME NULL,
    `error`        TEXT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offline_products` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `product_id`   INT UNSIGNED NOT NULL,
    `data`         LONGTEXT NOT NULL,
    `synced_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_branch` (`tenant_id`, `branch_id`),
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offline_customers` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `customer_id`  INT UNSIGNED NOT NULL,
    `data`         LONGTEXT NOT NULL,
    `synced_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offline_settings` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `settings`     LONGTEXT NOT NULL,
    `synced_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_branch` (`tenant_id`, `branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conflict_log` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `entity_type`  VARCHAR(60) NOT NULL,
    `entity_id`    BIGINT UNSIGNED NOT NULL,
    `local_data`   LONGTEXT NULL,
    `server_data`  LONGTEXT NULL,
    `resolution`   ENUM('server_wins','client_wins','manual') NULL,
    `resolved_at`  DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`, `entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. REPORTS
CREATE TABLE IF NOT EXISTS `custom_reports` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `name`         VARCHAR(200) NOT NULL,
    `description`  TEXT NULL,
    `query_config` LONGTEXT NULL,
    `columns`      JSON NULL,
    `filters`      JSON NULL,
    `created_by`   INT UNSIGNED NULL,
    `is_shared`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. USER MANAGEMENT
CREATE TABLE IF NOT EXISTS `user_notes` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `author_id`    INT UNSIGNED NOT NULL,
    `note`         TEXT NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_user` (`tenant_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_password_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_password_history` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` VARCHAR(128) NOT NULL PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `payload` TEXT NOT NULL,
    `last_activity` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_sessions_user_id` (`user_id`),
    INDEX `idx_user_sessions_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 22. COMPANY/BUSINESS
CREATE TABLE IF NOT EXISTS `company_verticals` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code`         VARCHAR(50) NOT NULL,
    `name`         VARCHAR(100) NOT NULL,
    `description`  TEXT NULL,
    `icon`         VARCHAR(50) NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_permissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT(11) NOT NULL,
    `permission_id` INT(11) NOT NULL,
    `is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_company_permission` (`company_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `business_types` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `icon` VARCHAR(50) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_business_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 23. PERMISSIONS
CREATE TABLE IF NOT EXISTS `permission_groups` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `icon` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_permission_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 24. BRANCH
CREATE TABLE IF NOT EXISTS `branch_hours` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `branch_id` INT(11) NOT NULL,
    `day_of_week` TINYINT(1) NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
    `opening_time` TIME NOT NULL,
    `closing_time` TIME NOT NULL,
    `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_branch_day` (`branch_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default currencies
INSERT IGNORE INTO `currencies` (`code`, `name`, `symbol`, `decimal_places`) VALUES
('USD', 'US Dollar', '$', 2),
('KES', 'Kenyan Shilling', 'KSh', 2),
('EUR', 'Euro', '€', 2),
('GBP', 'British Pound', '£', 2);

-- Insert default business types
INSERT IGNORE INTO `business_types` (`code`, `name`, `description`, `icon`, `is_active`, `sort_order`) VALUES
('retail', 'Retail Store', 'General retail store selling various products', 'store', 1, 1),
('restaurant', 'Restaurant', 'Food and beverage service establishment', 'utensils', 1, 2),
('supermarket', 'Supermarket', 'Large self-service grocery and retail store', 'shopping-cart', 1, 3),
('pharmacy', 'Pharmacy', 'Pharmaceutical and health products store', 'medkit', 1, 4),
('wholesale', 'Wholesale', 'Bulk products distribution business', 'warehouse', 1, 5),
('salon', 'Salon & Spa', 'Beauty and personal care services', 'scissors', 1, 6),
('electronics', 'Electronics', 'Electronic devices and accessories store', 'laptop', 1, 7),
('clothing', 'Clothing Store', 'Apparel and fashion retail', 'tshirt', 1, 8);

-- Insert default company verticals
INSERT IGNORE INTO `company_verticals` (`code`, `name`, `description`, `icon`, `is_active`) VALUES
('retail', 'Retail', 'Retail and consumer goods businesses', 'store', 1),
('food_beverage', 'Food & Beverage', 'Restaurants, cafes, and food service', 'utensils', 1),
('healthcare', 'Healthcare', 'Pharmacies, clinics, and health services', 'medkit', 1),
('services', 'Services', 'Professional and personal services', 'briefcase', 1),
('manufacturing', 'Manufacturing', 'Production and manufacturing businesses', 'industry', 1),
('technology', 'Technology', 'Tech companies and software services', 'laptop-code', 1);

SET FOREIGN_KEY_CHECKS = 1;
