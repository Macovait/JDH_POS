-- Zero-Trust Configuration Database Schema
-- All configuration, content, and assets must be dynamically loaded from database

-- ============================================
-- CENTRALIZED CONFIGURATION STORAGE
-- ============================================

-- Application-wide settings (tenant-agnostic)
CREATE TABLE IF NOT EXISTS app_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(128) NOT NULL UNIQUE,
    config_value LONGTEXT,
    config_type ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    description VARCHAR(255),
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    INDEX idx_config_key (config_key),
    INDEX idx_config_type (config_type),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant-specific configurations
CREATE TABLE IF NOT EXISTS tenant_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    config_key VARCHAR(128) NOT NULL,
    config_value LONGTEXT,
    config_type ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_config (tenant_id, config_key),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company-specific configurations
CREATE TABLE IF NOT EXISTS company_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    config_key VARCHAR(128) NOT NULL,
    config_value LONGTEXT,
    config_type ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_company_config (company_id, config_key),
    INDEX idx_company_id (company_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Branch-specific configurations
CREATE TABLE IF NOT EXISTS branch_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id INT UNSIGNED NOT NULL,
    config_key VARCHAR(128) NOT NULL,
    config_value LONGTEXT,
    config_type ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_branch_config (branch_id, config_key),
    INDEX idx_branch_id (branch_id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DYNAMIC CONTENT STORAGE
-- ============================================

-- Page content storage
CREATE TABLE IF NOT EXISTS page_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(128) NOT NULL UNIQUE,
    tenant_id INT UNSIGNED,
    company_id INT UNSIGNED,
    title VARCHAR(255),
    content LONGTEXT,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    hero_title VARCHAR(255),
    hero_subtitle TEXT,
    cta_text VARCHAR(255),
    cta_link VARCHAR(255),
    locale VARCHAR(10) DEFAULT 'en',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    updated_by INT UNSIGNED,
    INDEX idx_page_key (page_key),
    INDEX idx_tenant_company (tenant_id, company_id),
    INDEX idx_locale (locale),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature flags
CREATE TABLE IF NOT EXISTS feature_flags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flag_key VARCHAR(128) NOT NULL UNIQUE,
    flag_value BOOLEAN DEFAULT FALSE,
    description VARCHAR(255),
    tenant_id INT UNSIGNED,
    company_id INT UNSIGNED,
    rollout_percentage INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_flag_key (flag_key),
    INDEX idx_tenant_company (tenant_id, company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CENTRALIZED STRINGS & LOCALIZATION
-- ============================================

-- All UI strings stored in database
CREATE TABLE IF NOT EXISTS ui_strings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    string_key VARCHAR(128) NOT NULL,
    tenant_id INT UNSIGNED,
    company_id INT UNSIGNED,
    string_value TEXT NOT NULL,
    locale VARCHAR(10) DEFAULT 'en',
    context VARCHAR(255),
    is_fallback BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_string_lookup (string_key, tenant_id, company_id, locale),
    INDEX idx_string_key (string_key),
    INDEX idx_locale (locale),
    INDEX idx_tenant_company (tenant_id, company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- String categories for organization
CREATE TABLE IF NOT EXISTS string_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_key VARCHAR(64) NOT NULL UNIQUE,
    category_name VARCHAR(128) NOT NULL,
    description VARCHAR(255),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DYNAMIC ASSETS
-- ============================================

-- Asset storage metadata (actual files in S3/cloud storage)
CREATE TABLE IF NOT EXISTS assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_key VARCHAR(128) NOT NULL,
    tenant_id INT UNSIGNED,
    company_id INT UNSIGNED,
    asset_type ENUM('logo', 'favicon', 'image', 'icon', 'document', 'font', 'css', 'js') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    file_url VARCHAR(512),
    mime_type VARCHAR(128),
    file_size INT UNSIGNED,
    width INT UNSIGNED,
    height INT UNSIGNED,
    version INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    INDEX idx_asset_key (asset_key),
    INDEX idx_tenant_company (tenant_id, company_id),
    INDEX idx_asset_type (asset_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AUDIT & SECURITY
-- ============================================

-- Configuration change audit log
CREATE TABLE IF NOT EXISTS config_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,
    record_id INT UNSIGNED NOT NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    old_value LONGTEXT,
    new_value LONGTEXT,
    changed_by INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent VARCHAR(512),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_changed_by (changed_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DEFAULT CONFIGURATIONS
-- ============================================

-- Insert default app configurations (Zero-Trust requires all to come from DB)
INSERT INTO app_config (config_key, config_value, config_type, description, is_public) VALUES
('app.name', 'POS System', 'string', 'Application name', TRUE),
('app.version', '2.0.0', 'string', 'Application version', TRUE),
('app.env', 'development', 'string', 'Environment mode', FALSE),
('app.debug', 'false', 'boolean', 'Debug mode', FALSE),
('app.timezone', 'Africa/Nairobi', 'string', 'Default timezone', TRUE),
('app.locale', 'en', 'string', 'Default locale', TRUE),
('app.currency', 'KES', 'string', 'Default currency', TRUE),
('app.tax_rate', '16', 'number', 'Default tax rate', TRUE),
('app.encryption_key', '', 'encrypted', 'Master encryption key', FALSE),
('session.lifetime', '7200', 'number', 'Session lifetime in seconds', FALSE),
('session.secure', 'true', 'boolean', 'Force HTTPS for sessions', FALSE),
('session.httponly', 'true', 'boolean', 'HTTP-only cookies', FALSE),
('auth.max_login_attempts', '5', 'number', 'Max failed login attempts', FALSE),
('auth.lockout_duration', '900', 'number', 'Account lockout duration (seconds)', FALSE),
('auth.password_min_length', '8', 'number', 'Minimum password length', FALSE),
('auth.password_require_special', 'true', 'boolean', 'Require special characters', FALSE),
('auth.mfa_enabled', 'false', 'boolean', 'Multi-factor authentication', FALSE),
('api.rate_limit', '100', 'number', 'API rate limit per minute', FALSE),
('api.rate_limit_window', '60', 'number', 'Rate limit window in seconds', FALSE),
('mail.driver', 'smtp', 'string', 'Mail driver', FALSE),
('mail.from.address', '', 'string', 'Default from email', FALSE),
('mail.from.name', '', 'string', 'Default from name', FALSE),
('cache.enabled', 'true', 'boolean', 'Enable caching', FALSE),
('cache.lifetime', '3600', 'number', 'Cache lifetime in seconds', FALSE),
('logging.level', 'error', 'string', 'Log level', FALSE),
('logging.enabled', 'true', 'boolean', 'Enable logging', FALSE),
('security.csrf_enabled', 'true', 'boolean', 'Enable CSRF protection', FALSE),
('security.xss_enabled', 'true', 'boolean', 'Enable XSS protection', FALSE),
('security.cors_enabled', 'true', 'boolean', 'Enable CORS', FALSE),
('security.allowed_origins', '', 'string', 'Allowed CORS origins (comma-separated)', FALSE)
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);

-- Insert default string categories
INSERT INTO string_categories (category_key, category_name, description, display_order) VALUES
('navigation', 'Navigation', 'Menu and navigation labels', 1),
('actions', 'Actions', 'Button and action labels', 2),
('messages', 'Messages', 'User messages and notifications', 3),
('errors', 'Errors', 'Error messages', 4),
('validation', 'Validation', 'Form validation messages', 5),
('labels', 'Labels', 'Form labels and placeholders', 6),
('titles', 'Titles', 'Page titles and headings', 7),
('footer', 'Footer', 'Footer content', 8),
('landing', 'Landing Page', 'Landing page content', 9)
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- Insert default UI strings
INSERT INTO ui_strings (string_key, string_value, locale, context) VALUES
('nav.dashboard', 'Dashboard', 'en', 'navigation'),
('nav.pos', 'Point of Sale', 'en', 'navigation'),
('nav.sales', 'Sales', 'en', 'navigation'),
('nav.inventory', 'Inventory', 'en', 'navigation'),
('nav.customers', 'Customers', 'en', 'navigation'),
('nav.reports', 'Reports', 'en', 'navigation'),
('nav.settings', 'Settings', 'en', 'navigation'),
('nav.logout', 'Logout', 'en', 'navigation'),
('action.save', 'Save', 'en', 'actions'),
('action.cancel', 'Cancel', 'en', 'actions'),
('action.delete', 'Delete', 'en', 'actions'),
('action.edit', 'Edit', 'en', 'actions'),
('action.create', 'Create', 'en', 'actions'),
('action.update', 'Update', 'en', 'actions'),
('action.search', 'Search', 'en', 'actions'),
('action.filter', 'Filter', 'en', 'actions'),
('action.export', 'Export', 'en', 'actions'),
('action.import', 'Import', 'en', 'actions'),
('msg.success', 'Operation completed successfully', 'en', 'messages'),
('msg.error', 'An error occurred', 'en', 'messages'),
('msg.loading', 'Loading...', 'en', 'messages'),
('msg.no_data', 'No data available', 'en', 'messages'),
('error.403', 'Access denied', 'en', 'errors'),
('error.404', 'Page not found', 'en', 'errors'),
('error.500', 'Internal server error', 'en', 'errors'),
('error.unauthorized', 'Please log in to continue', 'en', 'errors'),
('validation.required', 'This field is required', 'en', 'validation'),
('validation.email', 'Please enter a valid email', 'en', 'validation'),
('validation.min_length', 'Minimum {min} characters required', 'en', 'validation'),
('title.users', 'User Management', 'en', 'titles'),
('title.products', 'Products', 'en', 'titles'),
('title.orders', 'Orders', 'en', 'titles')
ON DUPLICATE KEY UPDATE string_value = VALUES(string_value);