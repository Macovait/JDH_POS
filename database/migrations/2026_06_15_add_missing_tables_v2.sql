-- ============================================================
-- Migration: Add Missing Tables (Version 2 - Fixed FKs)
-- Date: 2026-06-15
-- Description: Creates 37 missing tables with proper column types
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
    INDEX `idx_created` (`created_at`)
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
