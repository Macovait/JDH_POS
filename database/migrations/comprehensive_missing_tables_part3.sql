-- ============================================================
-- Comprehensive Migration: Create All Missing Tables - PART 3
-- Date: 2026-06-15
-- Description: Tables 61-90 (POS, Restaurant, HR, Reservations)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 9. POS & TERMINAL
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

CREATE TABLE IF NOT EXISTS `pos_tabs` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `table_label`  VARCHAR(100) NULL,
    `customer_id`  INT UNSIGNED NULL,
    `cashier_id`   INT UNSIGNED NULL,
    `status`       ENUM('open','hold','closed','voided') NOT NULL DEFAULT 'open',
    `note`         TEXT NULL,
    `opened_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`    DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_branch_status` (`tenant_id`, `branch_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_tab_items` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tab_id`       INT UNSIGNED NOT NULL,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `product_id`   INT UNSIGNED NOT NULL,
    `product_name` VARCHAR(200) NOT NULL,
    `qty`          DECIMAL(10,3) NOT NULL DEFAULT 1,
    `unit_price`   DECIMAL(15,4) NOT NULL,
    `discount`     DECIMAL(15,4) NOT NULL DEFAULT 0,
    `subtotal`     DECIMAL(15,4) NOT NULL,
    `note`         TEXT NULL,
    `sent_to_kitchen` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tab` (`tab_id`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pos_kot_jobs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `tab_id`       INT UNSIGNED NULL,
    `sale_id`      BIGINT UNSIGNED NULL,
    `station_id`   INT UNSIGNED NULL,
    `items`        JSON NOT NULL,
    `status`       ENUM('pending','printed','cancelled') NOT NULL DEFAULT 'pending',
    `printed_at`   DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. RESTAURANT
CREATE TABLE IF NOT EXISTS `restaurant_tables` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `label`        VARCHAR(50) NOT NULL,
    `capacity`     TINYINT UNSIGNED NOT NULL DEFAULT 4,
    `status`       ENUM('available','occupied','reserved','cleaning') NOT NULL DEFAULT 'available',
    `current_tab_id` INT UNSIGNED NULL,
    `position_x`   FLOAT NULL,
    `position_y`   FLOAT NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_branch` (`tenant_id`, `branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. HR & SCHEDULING
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
    INDEX `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shifts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `opening_cash` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `closing_cash` DECIMAL(15,4) NULL,
    `expected_cash` DECIMAL(15,4) NULL,
    `variance`     DECIMAL(15,4) NULL,
    `notes`        TEXT NULL,
    `opened_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at`    DATETIME NULL,
    `status`       ENUM('open','closed') NOT NULL DEFAULT 'open',
    INDEX `idx_tenant_branch_status` (`tenant_id`, `branch_id`, `status`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. RESERVATIONS
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
    INDEX `idx_status` (`status`)
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
    INDEX `idx_completed` (`is_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
