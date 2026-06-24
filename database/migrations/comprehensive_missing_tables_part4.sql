-- ============================================================
-- Comprehensive Migration: Create All Missing Tables - PART 4
-- Date: 2026-06-15
-- Description: Tables 91-120 (System, Support, Marketing, Payments)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 13. SYSTEM & UTILITY
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
    INDEX `idx_permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- 14. SUPPORT & ADMIN
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

-- 15. MARKETING
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

-- 16. PAYMENTS
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

SET FOREIGN_KEY_CHECKS = 1;
