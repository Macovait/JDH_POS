-- Plugin System Schema
-- Creates the plugins table for managing plugin state

CREATE TABLE IF NOT EXISTS plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    version VARCHAR(32) NOT NULL,
    description TEXT,
    author VARCHAR(128),
    status ENUM('active','inactive','error') DEFAULT 'inactive',
    tenant_id INT NULL,
    settings JSON NULL,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_tenant (tenant_id),
    UNIQUE KEY unique_plugin_tenant (slug, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
