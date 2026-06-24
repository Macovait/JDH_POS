-- ============================================================
-- Comprehensive Migration: Create All Missing Tables - PART 2
-- Date: 2026-06-15
-- Description: Tables 31-60 (Billing, Shop, Delivery, Products)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 5. INVOICE & BILLING
CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `invoice_id`    INT UNSIGNED NOT NULL,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `product_id`    INT UNSIGNED NULL,
    `description`   VARCHAR(255) NOT NULL,
    `quantity`      DECIMAL(15,4) NOT NULL,
    `unit_price`    DECIMAL(15,4) NOT NULL,
    `discount`      DECIMAL(15,4) NOT NULL DEFAULT 0,
    `tax_rate`      DECIMAL(5,2) NOT NULL DEFAULT 0,
    `tax_amount`    DECIMAL(15,4) NOT NULL DEFAULT 0,
    `line_total`    DECIMAL(15,4) NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_invoice` (`invoice_id`),
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscription_features` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `subscription_id` INT UNSIGNED NOT NULL,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `feature_id`    INT UNSIGNED NOT NULL,
    `feature_key`   VARCHAR(100) NOT NULL,
    `usage_limit`   INT UNSIGNED NULL,
    `usage_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `is_enabled`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_subscription_feature` (`subscription_id`, `feature_id`),
    INDEX `idx_feature` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscription_invoices` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `subscription_id` INT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(50) NOT NULL,
    `amount`        DECIMAL(15,4) NOT NULL,
    `tax_amount`    DECIMAL(15,4) NOT NULL DEFAULT 0,
    `total`         DECIMAL(15,4) NOT NULL,
    `status`        ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `period_start`  DATE NOT NULL,
    `period_end`    DATE NOT NULL,
    `due_date`      DATE NOT NULL,
    `paid_at`       DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_invoice_number` (`tenant_id`, `invoice_number`),
    INDEX `idx_subscription` (`subscription_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gift_cards` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `card_number`   VARCHAR(50) NOT NULL,
    `pin`           VARCHAR(20) NULL,
    `initial_value` DECIMAL(15,4) NOT NULL,
    `balance`       DECIMAL(15,4) NOT NULL,
    `currency`      VARCHAR(3) NOT NULL DEFAULT 'USD',
    `status`        ENUM('active','redeemed','expired','cancelled') NOT NULL DEFAULT 'active',
    `recipient_name` VARCHAR(100) NULL,
    `recipient_email` VARCHAR(255) NULL,
    `message`       TEXT NULL,
    `purchased_by`  INT UNSIGNED NULL,
    `expires_at`    DATE NULL,
    `redeemed_at`   DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_card_number` (`tenant_id`, `card_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. E-COMMERCE / SHOP
CREATE TABLE IF NOT EXISTS `shop_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL UNIQUE,
    `shop_name`     VARCHAR(100) NOT NULL,
    `shop_description` TEXT NULL,
    `shop_logo`     VARCHAR(255) NULL,
    `theme`         VARCHAR(50) NOT NULL DEFAULT 'default',
    `primary_color` VARCHAR(7) NOT NULL DEFAULT '#3498db',
    `currency`      VARCHAR(3) NOT NULL DEFAULT 'USD',
    `timezone`      VARCHAR(50) NOT NULL DEFAULT 'UTC',
    `enable_reviews` TINYINT(1) NOT NULL DEFAULT 1,
    `require_login` TINYINT(1) NOT NULL DEFAULT 0,
    `shipping_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `tax_included`  TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_theme` (`theme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_products` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `product_id`    INT UNSIGNED NOT NULL,
    `slug`          VARCHAR(200) NOT NULL,
    `short_description` TEXT NULL,
    `detailed_description` LONGTEXT NULL,
    `seo_title`     VARCHAR(70) NULL,
    `seo_description` VARCHAR(160) NULL,
    `is_featured`   TINYINT(1) NOT NULL DEFAULT 0,
    `is_visible`    TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` INT NOT NULL DEFAULT 0,
    `meta_data`     JSON NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_slug` (`tenant_id`, `slug`),
    UNIQUE KEY `uk_product` (`tenant_id`, `product_id`),
    INDEX `idx_featured` (`is_featured`),
    INDEX `idx_visible` (`is_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_orders` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `customer_id`   INT UNSIGNED NOT NULL,
    `order_number`  VARCHAR(50) NOT NULL,
    `status`        ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `payment_status` ENUM('pending','paid','partial','refunded','failed') NOT NULL DEFAULT 'pending',
    `subtotal`      DECIMAL(15,4) NOT NULL,
    `tax_amount`    DECIMAL(15,4) NOT NULL DEFAULT 0,
    `shipping_amount` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `discount`      DECIMAL(15,4) NOT NULL DEFAULT 0,
    `total`         DECIMAL(15,4) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `billing_address` TEXT NOT NULL,
    `notes`         TEXT NULL,
    `placed_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `shipped_at`    DATETIME NULL,
    `delivered_at`  DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_order_number` (`tenant_id`, `order_number`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_payment` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. DELIVERY & LOGISTICS
CREATE TABLE IF NOT EXISTS `delivery_areas` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `area_name`     VARCHAR(100) NOT NULL,
    `description`   TEXT NULL,
    `delivery_fee`  DECIMAL(10,2) NOT NULL DEFAULT 0,
    `minimum_order` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `estimated_time` VARCHAR(50) NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_routes` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `route_name`    VARCHAR(100) NOT NULL,
    `driver_id`     INT UNSIGNED NULL,
    `vehicle`       VARCHAR(100) NULL,
    `status`        ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
    `planned_stops` INT NOT NULL DEFAULT 0,
    `completed_stops` INT NOT NULL DEFAULT 0,
    `started_at`    DATETIME NULL,
    `completed_at`  DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_driver` (`driver_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `driver_locations` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `rider_id`     INT UNSIGNED NOT NULL,
    `latitude`     DECIMAL(10,8) NOT NULL,
    `longitude`    DECIMAL(11,8) NOT NULL,
    `accuracy`     FLOAT NULL,
    `recorded_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rider` (`rider_id`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. PRODUCT MANAGEMENT
CREATE TABLE IF NOT EXISTS `product_labels` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `product_id`    INT UNSIGNED NOT NULL,
    `label_name`    VARCHAR(50) NOT NULL,
    `label_color`   VARCHAR(7) NOT NULL DEFAULT '#3498db',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_product_label` (`tenant_id`, `product_id`, `label_name`),
    INDEX `idx_label` (`label_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_price_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT(11) NOT NULL,
    `old_price` DECIMAL(10,2) NOT NULL,
    `new_price` DECIMAL(10,2) NOT NULL,
    `old_cost` DECIMAL(10,2) DEFAULT NULL,
    `new_cost` DECIMAL(10,2) DEFAULT NULL,
    `changed_by` INT(11) NOT NULL,
    `change_reason` VARCHAR(255) DEFAULT NULL,
    `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_price_history_product` (`product_id`),
    INDEX `idx_price_history_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_sync_log` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `product_id`   INT UNSIGNED NOT NULL,
    `action`       VARCHAR(50) NOT NULL,
    `source`       VARCHAR(50) NOT NULL DEFAULT 'local',
    `status`       ENUM('success','failed') NOT NULL DEFAULT 'success',
    `details`      TEXT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_product` (`tenant_id`, `product_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
