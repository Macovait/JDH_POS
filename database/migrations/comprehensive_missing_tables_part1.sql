-- ============================================================
-- Comprehensive Migration: Create All Missing Tables - PART 1
-- Date: 2026-06-15
-- Description: Tables 1-30
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 1. CASH MANAGEMENT
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
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. INVENTORY MANAGEMENT
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
    INDEX `idx_created` (`created_at`)
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
    INDEX `idx_created` (`created_at`)
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
    INDEX `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. PURCHASING MODULE
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
    INDEX `idx_tenant` (`tenant_id`)
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
    INDEX `idx_status` (`status`)
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
    INDEX `idx_supplier` (`supplier_id`)
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
    INDEX `idx_tenant_supplier` (`tenant_id`, `supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CUSTOMER MANAGEMENT
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
    INDEX `idx_points` (`points_balance`)
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
    INDEX `idx_label` (`label_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loyalty_transactions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `customer_id`  INT UNSIGNED NOT NULL,
    `sale_id`      BIGINT UNSIGNED NULL,
    `type`         ENUM('earn','redeem','expire','adjust','bonus') NOT NULL,
    `points`       INT NOT NULL,
    `balance_after` INT NOT NULL,
    `description`  VARCHAR(255) NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_customer` (`tenant_id`, `customer_id`),
    INDEX `idx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
