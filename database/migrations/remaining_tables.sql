-- Remaining 14 tables
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

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

CREATE TABLE IF NOT EXISTS `migrations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `migration`     VARCHAR(255) NOT NULL,
    `batch`         INT NOT NULL,
    `executed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_migration` (`migration`),
    INDEX `idx_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permission_groups` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `icon` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_permission_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

SET FOREIGN_KEY_CHECKS = 1;
