-- ============================================================
-- Migration: Add missing tables identified by code audit
-- Date: 2026-06-14
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── job_queue / sync_queue ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `job_queue` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `job_type`     VARCHAR(100) NOT NULL,
    `payload`      JSON NULL,
    `status`       ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at`   DATETIME NULL,
    `finished_at`  DATETIME NULL,
    `error`        TEXT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status_available` (`status`, `available_at`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sync_queue` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `branch_id`    INT UNSIGNED NULL,
    `entity_type`  VARCHAR(60) NOT NULL,
    `entity_id`    BIGINT UNSIGNED NOT NULL,
    `action`       ENUM('create','update','delete') NOT NULL,
    `payload`      JSON NULL,
    `synced`       TINYINT(1) NOT NULL DEFAULT 0,
    `synced_at`    DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_synced` (`tenant_id`, `synced`),
    INDEX `idx_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── tenant_configs ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenant_configs` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `config_key`   VARCHAR(120) NOT NULL,
    `config_value` TEXT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tenant_key` (`tenant_id`, `config_key`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── support_access_logs / support_access_sessions ──────────
CREATE TABLE IF NOT EXISTS `support_access_logs` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `admin_id`     INT UNSIGNED NULL,
    `action`       VARCHAR(100) NOT NULL,
    `description`  TEXT NULL,
    `ip_address`   VARCHAR(45) NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant` (`tenant_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_access_sessions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `admin_id`     INT UNSIGNED NOT NULL,
    `session_token` VARCHAR(128) NOT NULL,
    `reason`       TEXT NULL,
    `expires_at`   DATETIME NOT NULL,
    `revoked_at`   DATETIME NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_token` (`session_token`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── marketing_campaigns / campaign_messages ─────────────────
CREATE TABLE IF NOT EXISTS `marketing_campaigns` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `name`         VARCHAR(200) NOT NULL,
    `type`         ENUM('sms','email','push','whatsapp') NOT NULL DEFAULT 'sms',
    `status`       ENUM('draft','scheduled','running','completed','cancelled') NOT NULL DEFAULT 'draft',
    `target_segment` VARCHAR(100) NULL,
    `message`      TEXT NULL,
    `scheduled_at` DATETIME NULL,
    `sent_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `open_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `click_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`   INT UNSIGNED NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `campaign_messages` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `campaign_id`  INT UNSIGNED NOT NULL,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `recipient_id` INT UNSIGNED NULL,
    `recipient`    VARCHAR(200) NOT NULL,
    `status`       ENUM('pending','sent','delivered','failed','bounced') NOT NULL DEFAULT 'pending',
    `sent_at`      DATETIME NULL,
    `error`        TEXT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_campaign` (`campaign_id`),
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── pos_tabs / pos_tab_items / pos_kot_jobs ─────────────────
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

-- ── restaurant_tables ────────────────────────────────────────
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

-- ── payment_transactions / payment_gateways ─────────────────
CREATE TABLE IF NOT EXISTS `payment_gateways` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `name`         VARCHAR(100) NOT NULL,
    `code`         VARCHAR(50) NOT NULL,
    `provider`     VARCHAR(50) NOT NULL,
    `config`       JSON NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `is_test_mode` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_active` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `gateway_id`   INT UNSIGNED NULL,
    `reference`    VARCHAR(120) NOT NULL,
    `type`         ENUM('payment','refund','reversal','topup') NOT NULL DEFAULT 'payment',
    `amount`       DECIMAL(15,4) NOT NULL,
    `currency`     VARCHAR(3) NOT NULL DEFAULT 'KES',
    `status`       ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
    `metadata`     JSON NULL,
    `sale_id`      BIGINT UNSIGNED NULL,
    `customer_id`  INT UNSIGNED NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_reference` (`tenant_id`, `reference`),
    INDEX `idx_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_sale` (`sale_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── currencies / exchange_rates ─────────────────────────────
CREATE TABLE IF NOT EXISTS `currencies` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code`         VARCHAR(3) NOT NULL,
    `name`         VARCHAR(80) NOT NULL,
    `symbol`       VARCHAR(10) NOT NULL,
    `decimal_places` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exchange_rates` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `from_currency` VARCHAR(3) NOT NULL,
    `to_currency`  VARCHAR(3) NOT NULL,
    `rate`         DECIMAL(20,8) NOT NULL,
    `source`       VARCHAR(50) NULL DEFAULT 'manual',
    `effective_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_pair` (`tenant_id`, `from_currency`, `to_currency`),
    INDEX `idx_effective` (`effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── shifts ───────────────────────────────────────────────────
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

-- ── label_templates ──────────────────────────────────────────
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

-- ── rate_limit_blocks ────────────────────────────────────────
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

-- ── offline sync tables ──────────────────────────────────────
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

-- ── conflict_log / product_sync_log ─────────────────────────
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

-- ── custom_reports ───────────────────────────────────────────
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

-- ── loyalty_transactions ─────────────────────────────────────
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

-- ── driver_locations ────────────────────────────────────────
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

-- ── user_notes ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_notes` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`    INT UNSIGNED NOT NULL,
    `user_id`      INT UNSIGNED NOT NULL,
    `author_id`    INT UNSIGNED NOT NULL,
    `note`         TEXT NOT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tenant_user` (`tenant_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── company_verticals ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `company_verticals` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `code`         VARCHAR(50) NOT NULL,
    `name`         VARCHAR(100) NOT NULL,
    `description`  TEXT NULL,
    `icon`         VARCHAR(50) NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
