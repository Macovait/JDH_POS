-- ============================================================
-- KRA eTIMS Integration Migration
-- Adds columns and tables for Electronic Tax Invoice Management
-- ============================================================

-- Add eTIMS fields to sales table
ALTER TABLE `sales`
    ADD COLUMN `etims_cu_invoice_no` VARCHAR(100) DEFAULT NULL COMMENT 'KRA CU Invoice Number' AFTER `tenant_id`,
    ADD COLUMN `etims_receipt_sign` VARCHAR(500) DEFAULT NULL COMMENT 'KRA receipt signature' AFTER `etims_cu_invoice_no`,
    ADD COLUMN `etims_sdc_datetime` VARCHAR(20) DEFAULT NULL COMMENT 'KRA SDC datetime' AFTER `etims_receipt_sign`,
    ADD COLUMN `etims_submitted_at` DATETIME DEFAULT NULL COMMENT 'When invoice was submitted to KRA' AFTER `etims_sdc_datetime`,
    ADD COLUMN `etims_status` ENUM('pending','submitted','failed','exempt') DEFAULT NULL COMMENT 'eTIMS submission status' AFTER `etims_submitted_at`;

ALTER TABLE `sales`
    ADD INDEX `idx_sales_etims_status` (`etims_status`),
    ADD INDEX `idx_sales_etims_cu` (`etims_cu_invoice_no`);

-- Add eTIMS item classification to products table
ALTER TABLE `products`
    ADD COLUMN `etims_item_code` VARCHAR(100) DEFAULT NULL COMMENT 'KRA eTIMS item code' AFTER `tenant_id`,
    ADD COLUMN `etims_class_code` VARCHAR(100) DEFAULT NULL COMMENT 'KRA eTIMS classification code' AFTER `etims_item_code`,
    ADD COLUMN `etims_registered` TINYINT(1) DEFAULT 0 COMMENT 'Whether item is registered with KRA' AFTER `etims_class_code`;

-- Retry queue for failed eTIMS submissions
CREATE TABLE IF NOT EXISTS `etims_retry_queue` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT(11) NOT NULL,
    `tenant_id` BIGINT(20) UNSIGNED NOT NULL,
    `status` ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_error` TEXT DEFAULT NULL,
    `next_retry_at` DATETIME NOT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_retry_tenant_status` (`tenant_id`, `status`, `next_retry_at`),
    INDEX `idx_retry_sale` (`sale_id`),
    CONSTRAINT `fk_etims_retry_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_etims_retry_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log for all eTIMS API interactions
CREATE TABLE IF NOT EXISTS `etims_audit_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT(20) UNSIGNED NOT NULL,
    `event` VARCHAR(100) NOT NULL COMMENT 'e.g. invoice_submitted, credit_note_submitted, item_registered',
    `entity_id` INT(11) NOT NULL COMMENT 'sale_id or product_id',
    `data` JSON DEFAULT NULL COMMENT 'KRA response data, error messages',
    `created_at` DATETIME NOT NULL,
    INDEX `idx_etims_log_tenant` (`tenant_id`, `created_at`),
    INDEX `idx_etims_log_event` (`event`, `created_at`),
    CONSTRAINT `fk_etims_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KRA item classification cache (synced from KRA periodically)
CREATE TABLE IF NOT EXISTS `etims_item_classifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `item_cls_cd` VARCHAR(100) NOT NULL COMMENT 'KRA classification code',
    `item_cls_nm` VARCHAR(500) NOT NULL COMMENT 'Classification name',
    `item_cls_lvl` INT DEFAULT NULL COMMENT 'Classification level',
    `tax_ty_cd` VARCHAR(10) DEFAULT NULL COMMENT 'Tax type code',
    `mjr_tg_yn` VARCHAR(1) DEFAULT NULL COMMENT 'Major tag Y/N',
    `use_yn` VARCHAR(1) DEFAULT 'Y' COMMENT 'Active Y/N',
    `synced_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_item_cls_cd` (`item_cls_cd`),
    INDEX `idx_cls_name` (`item_cls_nm`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
