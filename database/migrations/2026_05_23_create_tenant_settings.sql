-- Tenant Settings Table (for multi-tenant configuration)
-- Date: 2026-05-23
-- Used by: SettingsManager for label printing, auto-print, and future tenant-level settings

CREATE TABLE IF NOT EXISTS tenant_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant_setting (tenant_id, setting_key),
    INDEX idx_tenant (tenant_id),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed some default label printing settings for existing tenants (optional, safe to run multiple times)
-- These will only be inserted if they don't already exist
INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value)
SELECT 
    t.id,
    'auto_print_labels_on_sale',
    '0'
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_settings ts 
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'auto_print_labels_on_sale'
);

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value)
SELECT 
    t.id,
    'auto_print_label_size',
    'k22'
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_settings ts 
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'auto_print_label_size'
);

INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value)
SELECT 
    t.id,
    'auto_print_method',
    'pdf'
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_settings ts 
    WHERE ts.tenant_id = t.id AND ts.setting_key = 'auto_print_method'
);
