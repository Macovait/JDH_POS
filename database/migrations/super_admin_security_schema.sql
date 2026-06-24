-- Super-Admin Platform Security & Audit Schema
-- Zero-Trust multi-tenant isolation and audit trail

-- ============================================
-- ADMIN AUDIT LOG
-- ============================================

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    admin_name VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_type VARCHAR(50),
    target_id INT UNSIGNED,
    description TEXT,
    old_values JSON,
    new_values JSON,
    changes JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(512),
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_category_action (category, action),
    INDEX idx_target (target_type, target_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUPPORT ACCESS SESSIONS
-- ============================================

CREATE TABLE IF NOT EXISTS support_access_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    admin_name VARCHAR(255) NOT NULL,
    admin_email VARCHAR(255),
    company_id INT UNSIGNED NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    access_type ENUM('read_only', 'full_access') NOT NULL DEFAULT 'read_only',
    reason TEXT NOT NULL,
    token VARCHAR(128) NOT NULL,
    started_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    ended_at DATETIME,
    revoked_by INT UNSIGNED,
    revoke_reason TEXT,
    status ENUM('active', 'expired', 'revoked', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_id (company_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_status_expires (status, expires_at),
    INDEX idx_token (token),
    UNIQUE KEY uk_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUPPORT ACCESS AUDIT LOG
-- ============================================

CREATE TABLE IF NOT EXISTS support_access_audit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    admin_name VARCHAR(255) NOT NULL,
    action_type ENUM('login', 'logout', 'view', 'create', 'update', 'delete', 'export') NOT NULL,
    resource_type VARCHAR(100),
    resource_id INT UNSIGNED,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(512),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN USERS (Platform-level)
-- ============================================

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('owner', 'admin', 'support', 'viewer') NOT NULL DEFAULT 'viewer',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    permissions JSON,
    last_login_at DATETIME,
    last_login_ip VARCHAR(45),
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME,
    mfa_enabled BOOLEAN DEFAULT FALSE,
    mfa_secret VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ADMIN PERMISSIONS
-- ============================================

CREATE TABLE IF NOT EXISTS admin_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    resource VARCHAR(100) NOT NULL,
    actions JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_admin_resource (admin_id, resource),
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TENANT INVITATIONS
-- ============================================

CREATE TABLE IF NOT EXISTS tenant_invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'staff',
    token VARCHAR(128) NOT NULL,
    invited_by INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('pending', 'accepted', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME,
    INDEX idx_company (company_id),
    INDEX idx_token (token),
    INDEX idx_email (email),
    UNIQUE KEY uk_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT ADMIN CREATION (Seed)
-- ============================================

INSERT INTO admins (name, email, password_hash, role, status) 
SELECT 'Super Admin', 'admin@platform.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner', 'active'
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE email = 'admin@platform.com');

-- ============================================
-- PLATFORM CONFIGURATION
-- ============================================

INSERT INTO app_config (config_key, config_value, config_type, description, is_public) VALUES
('admin.session_lifetime', '7200', 'number', 'Admin session lifetime in seconds', FALSE),
('admin.csrf_enabled', 'true', 'boolean', 'Enable CSRF protection for admin', FALSE),
('admin.ip_whitelist_enabled', 'false', 'boolean', 'Enable IP whitelist for admin', FALSE),
('admin.trusted_subnets', '', 'string', 'Comma-separated list of trusted IP subnets', FALSE),
('admin.max_login_attempts', '5', 'number', 'Maximum failed login attempts', FALSE),
('admin.lockout_duration', '900', 'number', 'Account lockout duration in seconds', FALSE),
('support.default_duration', '30', 'number', 'Default support access duration in minutes', FALSE),
('support.max_duration', '120', 'number', 'Maximum support access duration in minutes', FALSE),
('support.require_reason', 'true', 'boolean', 'Require reason for support access', FALSE)
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);