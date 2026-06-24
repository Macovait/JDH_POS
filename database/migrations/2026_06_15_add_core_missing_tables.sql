-- ============================================================
-- Migration: Add Core Missing Tables
-- Date: 2026-06-15
-- Description: Creates 37 missing tables identified by schema audit
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- 1. CASH MANAGEMENT TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `cash_drawers` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `name`          VARCHAR(100) NOT NULL,
    `opening_amount` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `current_amount` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `status`        ENUM('open','closed','locked') NOT NULL DEFAULT 'closed',
    `opened_by`     INT UNSIGNED NULL,
    `closed_by`     INT UNSIGNED NULL,
    `opened_at`     DATETIME NULL,
    `closed_at`     DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_branch` (`tenant_id`, `branch_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cash_movements` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `cash_drawer_id` INT UNSIGNED NOT NULL,
    `movement_type` ENUM('cash_in','cash_out','float_add','float_remove','transfer') NOT NULL,
    `amount`        DECIMAL(15,4) NOT NULL,
    `reason`        VARCHAR(255) NOT NULL,
    `reference`     VARCHAR(100) NULL,
    `performed_by`  INT UNSIGNED NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_drawer` (`tenant_id`, `cash_drawer_id`),
    INDEX `idx_type` (`movement_type`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`cash_drawer_id`) REFERENCES `cash_drawers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. INVENTORY MANAGEMENT TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `inventory_adjustments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `product_id`    INT UNSIGNED NOT NULL,
    `adjustment_type` ENUM('addition','reduction','damage','expiry','correction') NOT NULL,
    `quantity`      DECIMAL(15,4) NOT NULL,
    `unit_cost`     DECIMAL(15,4) NULL,
    `reason`        TEXT NOT NULL,
    `reference`     VARCHAR(100) NULL,
    `performed_by`  INT UNSIGNED NOT NULL,
    `approved_by`   INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_product` (`tenant_id`, `product_id`),
    INDEX `idx_branch` (`branch_id`),
    INDEX `idx_type` (`adjustment_type`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_counts` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `count_name`    VARCHAR(100) NOT NULL,
    `status`        ENUM('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
    `counted_by`    INT UNSIGNED NULL,
    `approved_by`   INT UNSIGNED NULL,
    `started_at`    DATETIME NULL,
    `completed_at`  DATETIME NULL,
    `notes`         TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_branch` (`tenant_id`, `branch_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_alerts` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NULL,
    `product_id`    INT UNSIGNED NOT NULL,
    `alert_type`    ENUM('low_stock','out_of_stock','overstock','expiry') NOT NULL,
    `threshold`     DECIMAL(15,4) NOT NULL,
    `current_qty`   DECIMAL(15,4) NOT NULL,
    `status`        ENUM('active','resolved','ignored') NOT NULL DEFAULT 'active',
    `notified_at`   DATETIME NULL,
    `resolved_at`   DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_product` (`tenant_id`, `product_id`),
    INDEX `idx_type_status` (`alert_type`, `status`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `units` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `unit_name`     VARCHAR(50) NOT NULL,
    `unit_symbol`   VARCHAR(10) NOT NULL,
    `unit_type`     ENUM('weight','volume','piece','length','area','time','other') NOT NULL DEFAULT 'piece',
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_unit` (`tenant_id`, `unit_name`),
    INDEX `idx_type` (`unit_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `unit_conversions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `from_unit_id`  INT UNSIGNED NOT NULL,
    `to_unit_id`    INT UNSIGNED NOT NULL,
    `conversion_factor` DECIMAL(15,6) NOT NULL,
    `product_id`    INT UNSIGNED NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_conversion` (`tenant_id`, `from_unit_id`, `to_unit_id`, `product_id`),
    INDEX `idx_from_unit` (`from_unit_id`),
    INDEX `idx_to_unit` (`to_unit_id`),
    FOREIGN KEY (`from_unit_id`) REFERENCES `units`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_unit_id`) REFERENCES `units`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. PURCHASING MODULE TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `purchases` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `supplier_id`   INT UNSIGNED NOT NULL,
    `purchase_number` VARCHAR(50) NOT NULL,
    `reference`     VARCHAR(100) NULL,
    `status`        ENUM('draft','ordered','partial','received','cancelled') NOT NULL DEFAULT 'draft',
    `subtotal`      DECIMAL(15,4) NOT NULL DEFAULT 0,
    `tax_amount`    DECIMAL(15,4) NOT NULL DEFAULT 0,
    `discount`      DECIMAL(15,4) NOT NULL DEFAULT 0,
    `total`         DECIMAL(15,4) NOT NULL DEFAULT 0,
    `notes`         TEXT NULL,
    `expected_date` DATE NULL,
    `received_at`   DATETIME NULL,
    `created_by`    INT UNSIGNED NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_number` (`tenant_id`, `purchase_number`),
    INDEX `idx_supplier` (`supplier_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `purchase_id`   INT UNSIGNED NOT NULL,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `product_id`    INT UNSIGNED NOT NULL,
    `quantity`      DECIMAL(15,4) NOT NULL,
    `received_qty`  DECIMAL(15,4) NOT NULL DEFAULT 0,
    `unit_price`    DECIMAL(15,4) NOT NULL,
    `tax_rate`      DECIMAL(5,2) NOT NULL DEFAULT 0,
    `discount`      DECIMAL(15,4) NOT NULL DEFAULT 0,
    `subtotal`      DECIMAL(15,4) NOT NULL,
    `notes`         TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_purchase` (`purchase_id`),
    INDEX `idx_product` (`product_id`),
    INDEX `idx_tenant` (`tenant_id`),
    FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_returns` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `purchase_id`   INT UNSIGNED NOT NULL,
    `return_number` VARCHAR(50) NOT NULL,
    `status`        ENUM('draft','pending','approved','completed','rejected') NOT NULL DEFAULT 'draft',
    `reason`        TEXT NOT NULL,
    `subtotal`      DECIMAL(15,4) NOT NULL DEFAULT 0,
    `total`         DECIMAL(15,4) NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED NOT NULL,
    `approved_by`   INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_number` (`tenant_id`, `return_number`),
    INDEX `idx_purchase` (`purchase_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_suppliers` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `product_id`    INT UNSIGNED NOT NULL,
    `supplier_id`   INT UNSIGNED NOT NULL,
    `supplier_sku`  VARCHAR(100) NULL,
    `cost_price`    DECIMAL(15,4) NULL,
    `lead_time_days` INT UNSIGNED NULL,
    `is_preferred`  TINYINT(1) NOT NULL DEFAULT 0,
    `min_order_qty` DECIMAL(15,4) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_product_supplier` (`tenant_id`, `product_id`, `supplier_id`),
    INDEX `idx_supplier` (`supplier_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_products` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `supplier_id`   INT UNSIGNED NOT NULL,
    `product_name`  VARCHAR(200) NOT NULL,
    `supplier_sku`  VARCHAR(100) NULL,
    `description`   TEXT NULL,
    `cost_price`    DECIMAL(15,4) NULL,
    `retail_price`  DECIMAL(15,4) NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_supplier` (`tenant_id`, `supplier_id`),
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CUSTOMER MANAGEMENT TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `customer_loyalty` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `customer_id`   INT UNSIGNED NOT NULL,
    `points_balance` INT NOT NULL DEFAULT 0,
    `lifetime_points` INT NOT NULL DEFAULT 0,
    `tier_id`       INT UNSIGNED NULL,
    `tier_updated_at` DATETIME NULL,
    `last_activity` DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_customer` (`tenant_id`, `customer_id`),
    INDEX `idx_points` (`points_balance`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loyalty_rules` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `rule_name`     VARCHAR(100) NOT NULL,
    `rule_type`     ENUM('earn_points','redeem_points','tier_upgrade','bonus','referral') NOT NULL,
    `condition_type` ENUM('purchase_amount','purchase_qty','product','category','customer_group','visit_count') NOT NULL,
    `condition_value` VARCHAR(255) NULL,
    `points_award`  INT NOT NULL DEFAULT 0,
    `points_multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1,
    `start_date`    DATE NULL,
    `end_date`      DATE NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `priority`      INT NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_type` (`tenant_id`, `rule_type`),
    INDEX `idx_active_dates` (`is_active`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_labels` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `customer_id`   INT UNSIGNED NOT NULL,
    `label_name`    VARCHAR(50) NOT NULL,
    `label_color`   VARCHAR(7) NOT NULL DEFAULT '#3498db',
    `created_by`    INT UNSIGNED NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_customer_label` (`tenant_id`, `customer_id`, `label_name`),
    INDEX `idx_label` (`label_name`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. INVOICE & BILLING TABLES
-- ============================================================

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
    INDEX `idx_product` (`product_id`),
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
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
    INDEX `idx_feature` (`feature_id`),
    FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE CASCADE
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
    INDEX `idx_due_date` (`due_date`),
    FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE RESTRICT
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

-- ============================================================
-- 6. E-COMMERCE / SHOP TABLES
-- ============================================================

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
    INDEX `idx_visible` (`is_visible`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
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
    INDEX `idx_payment` (`payment_status`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. DELIVERY & LOGISTICS TABLES
-- ============================================================

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

-- ============================================================
-- 8. PRODUCT MANAGEMENT TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `product_labels` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `product_id`    INT UNSIGNED NOT NULL,
    `label_name`    VARCHAR(50) NOT NULL,
    `label_color`   VARCHAR(7) NOT NULL DEFAULT '#3498db',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_product_label` (`tenant_id`, `product_id`, `label_name`),
    INDEX `idx_label` (`label_name`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. POS & TERMINAL TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `terminal_devices` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `device_name`   VARCHAR(100) NOT NULL,
    `device_type`   ENUM('pos_terminal','tablet','mobile','kiosk','printer','scanner') NOT NULL,
    `serial_number` VARCHAR(100) NULL,
    `mac_address`   VARCHAR(17) NULL,
    `last_ip`       VARCHAR(45) NULL,
    `status`        ENUM('active','inactive','maintenance','retired') NOT NULL DEFAULT 'active',
    `registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen_at`  DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_branch` (`tenant_id`, `branch_id`),
    INDEX `idx_serial` (`serial_number`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_settings` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `terminal_id`   INT UNSIGNED NULL,
    `receipt_header` TEXT NULL,
    `receipt_footer` TEXT NULL,
    `receipt_logo`  VARCHAR(255) NULL,
    `auto_print`    TINYINT(1) NOT NULL DEFAULT 1,
    `show_prices_with_tax` TINYINT(1) NOT NULL DEFAULT 0,
    `currency_symbol` VARCHAR(10) NOT NULL DEFAULT '$',
    `decimal_places` TINYINT NOT NULL DEFAULT 2,
    `thousands_separator` VARCHAR(1) NOT NULL DEFAULT ',',
    `decimal_separator` VARCHAR(1) NOT NULL DEFAULT '.',
    `enable_quick_checkout` TINYINT(1) NOT NULL DEFAULT 0,
    `require_customer` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_terminal` (`tenant_id`, `branch_id`, `terminal_id`),
    INDEX `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. HR & SCHEDULING TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `time_clock` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `clock_in`      DATETIME NOT NULL,
    `clock_out`     DATETIME NULL,
    `break_duration` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_hours`   DECIMAL(5,2) NULL,
    `notes`         TEXT NULL,
    `ip_address_in` VARCHAR(45) NULL,
    `ip_address_out` VARCHAR(45) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_date` (`clock_in`),
    INDEX `idx_branch` (`branch_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. RESERVATIONS & REMINDERS
-- ============================================================

CREATE TABLE IF NOT EXISTS `reservations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `branch_id`     INT UNSIGNED NOT NULL,
    `customer_id`   INT UNSIGNED NOT NULL,
    `resource_type` ENUM('table','room','equipment','staff') NOT NULL DEFAULT 'table',
    `resource_id`   INT UNSIGNED NOT NULL,
    `party_size`    INT UNSIGNED NOT NULL DEFAULT 1,
    `status`        ENUM('confirmed','pending','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
    `reservation_date` DATE NOT NULL,
    `start_time`    TIME NOT NULL,
    `end_time`      TIME NULL,
    `notes`         TEXT NULL,
    `created_by`    INT UNSIGNED NOT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_branch` (`tenant_id`, `branch_id`),
    INDEX `idx_customer` (`customer_id`),
    INDEX `idx_date` (`reservation_date`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reminders` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT NULL,
    `reminder_type` ENUM('task','appointment','follow_up','payment','birthday','custom') NOT NULL DEFAULT 'custom',
    `related_type`  VARCHAR(50) NULL,
    `related_id`    INT UNSIGNED NULL,
    `remind_at`     DATETIME NOT NULL,
    `is_completed`  TINYINT(1) NOT NULL DEFAULT 0,
    `completed_at`  DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
    INDEX `idx_remind_at` (`remind_at`),
    INDEX `idx_completed` (`is_completed`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. SYSTEM & UTILITY TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS `migrations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `migration`     VARCHAR(255) NOT NULL,
    `batch`         INT NOT NULL,
    `executed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_migration` (`migration`),
    INDEX `idx_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `connection`    TEXT NOT NULL,
    `queue`         TEXT NOT NULL,
    `payload`       LONGTEXT NOT NULL,
    `exception`     LONGTEXT NOT NULL,
    `failed_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_failed_at` (`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_retries` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `webhook_id`    INT UNSIGNED NOT NULL,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `payload`       JSON NOT NULL,
    `attempt`       INT UNSIGNED NOT NULL DEFAULT 1,
    `response_code` INT UNSIGNED NULL,
    `response_body` TEXT NULL,
    `error`         TEXT NULL,
    `next_retry_at` DATETIME NULL,
    `completed_at`  DATETIME NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_webhook` (`webhook_id`),
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_retry` (`next_retry_at`),
    INDEX `idx_attempt` (`attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED NOT NULL,
    `permission`    VARCHAR(100) NOT NULL,
    `is_granted`    TINYINT(1) NOT NULL DEFAULT 1,
    `granted_by`    INT UNSIGNED NOT NULL,
    `granted_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_permission` (`tenant_id`, `user_id`, `permission`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_permission` (`permission`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
