-- Create custom_reports table for Custom Report Builder
CREATE TABLE IF NOT EXISTS `custom_reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'custom',
    `data_source` VARCHAR(100) NOT NULL,
    `selected_fields` JSON DEFAULT NULL,
    `filters` JSON DEFAULT NULL,
    `config` JSON DEFAULT NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_custom_reports_tenant` (`tenant_id`),
    KEY `idx_custom_reports_user` (`user_id`),
    KEY `idx_custom_reports_public` (`is_public`),
    CONSTRAINT `fk_custom_reports_tenant` 
        FOREIGN KEY (`tenant_id`) REFERENCES `pos_tenants`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_custom_reports_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;