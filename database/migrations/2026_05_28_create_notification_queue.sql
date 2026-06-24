-- Notification Queue Table
-- Date: 2026-05-28
-- Purpose: Create a queue for asynchronous notification processing
-- Allows sales to complete quickly while notifications are processed in background

CREATE TABLE IF NOT EXISTS `notification_queue` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'email, sms, etc.',
    `payload` JSON NOT NULL COMMENT 'Notification data as JSON',
    `status` ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    `last_attempt_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    `error_message` TEXT NULL,
    
    KEY `idx_notification_queue_tenant` (`tenant_id`),
    KEY `idx_notification_queue_status` (`status`),
    KEY `idx_notification_queue_created` (`created_at`),
    KEY `idx_notification_queue_status_attempts` (`status`, `attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Queue for asynchronous notification processing';

-- Index for efficient polling of pending notifications
ALTER TABLE `notification_queue` 
ADD INDEX `idx_notification_queue_pending` (`status`, `created_at`);