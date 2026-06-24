-- =====================================================
-- Database Migrations for JDH_POS
-- Adds missing foreign keys and indexes
-- =====================================================

-- Add missing foreign keys to companies table
ALTER TABLE companies 
ADD CONSTRAINT fk_companies_business_type 
  FOREIGN KEY (business_type_id) REFERENCES business_types(id) ON DELETE SET NULL;

ALTER TABLE companies 
ADD CONSTRAINT fk_companies_default_plan 
  FOREIGN KEY (default_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL;

-- Add missing indexes for better query performance
CREATE INDEX idx_companies_status_deleted ON companies(status, deleted_at);
CREATE INDEX idx_companies_business_type ON companies(business_type, status);

-- Add missing foreign keys to branches table
-- First add manager_id column if it doesn't exist
ALTER TABLE branches ADD COLUMN IF NOT EXISTS manager_id INT(11) NULL AFTER manager;

ALTER TABLE branches 
ADD CONSTRAINT fk_branches_manager 
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE branches 
ADD CONSTRAINT fk_branches_company 
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- Create branch_hours table for storing opening hours
CREATE TABLE IF NOT EXISTS `branch_hours` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_id` INT(11) NOT NULL,
  `day_of_week` TINYINT(1) NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
  `opening_time` TIME NOT NULL,
  `closing_time` TIME NOT NULL,
  `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_branch_day` (`branch_id`, `day_of_week`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- Branches Table Cleanup and Standardization
-- ==========================================

-- Step 1: Add manager_id column (if not exists)
ALTER TABLE branches ADD COLUMN IF NOT EXISTS manager_id INT(11) NULL AFTER manager;

-- Step 2: Sync is_active with active column before dropping
-- UPDATE branches SET is_active = active WHERE is_active IS NULL OR is_active != active;

-- Step 3: Drop redundant active column (uncomment after data sync)
-- ALTER TABLE branches DROP COLUMN active;

-- Step 4: Standardize timestamp columns
ALTER TABLE branches MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE branches MODIFY updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- Step 5: Add missing indexes for branches table
CREATE INDEX idx_branches_company_active ON branches(company_id, is_active, deleted_at);

-- ==========================================
-- Users Table Foreign Keys
-- ==========================================

-- Add foreign key for role_id
ALTER TABLE users ADD CONSTRAINT fk_users_role 
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

-- Add foreign key for branch_id
ALTER TABLE users ADD CONSTRAINT fk_users_branch 
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;

-- ==========================================
-- User Password History Table
-- ==========================================

CREATE TABLE IF NOT EXISTS `user_password_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_password_history` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- User Sessions Table
-- ==========================================

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` VARCHAR(128) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` TEXT,
  `payload` TEXT NOT NULL,
  `last_activity` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions_user_id` (`user_id`),
  KEY `idx_user_sessions_last_activity` (`last_activity`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- Users Table Consolidation
-- ==========================================

-- Step 1: Add new user_status enum column
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_status 
  ENUM('active','inactive','locked','suspended') NOT NULL DEFAULT 'active' AFTER status;

-- Step 2: Migrate data from old columns to new status field
-- UPDATE users SET user_status = 'active' WHERE status = 1 AND is_active = 1 AND deleted_at IS NULL;
-- UPDATE users SET user_status = 'inactive' WHERE status = 0 OR is_active = 0;
-- UPDATE users SET user_status = 'locked' WHERE locked_until > NOW();

-- Step 3: Drop old status columns after verification (uncomment after verification)
-- ALTER TABLE users DROP COLUMN status;
-- ALTER TABLE users DROP COLUMN is_active;

-- Step 4: Consolidate login attempts
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_login_attempts 
  INT(11) NOT NULL DEFAULT 0 AFTER failed_login_attempts;

-- Step 5: Migrate login attempt data (uncomment to run)
-- UPDATE users SET total_login_attempts = GREATEST(COALESCE(login_attempts, 0), COALESCE(failed_login_attempts, 0));

-- Step 6: Drop old login attempt columns after verification (uncomment after verification)
-- ALTER TABLE users DROP COLUMN login_attempts;
-- ALTER TABLE users DROP COLUMN failed_login_attempts;

-- ==========================================
-- Roles Table Enhancements
-- ==========================================

-- Add inheritance support for roles
ALTER TABLE roles ADD COLUMN IF NOT EXISTS inherits_from INT(11) NULL AFTER is_system;
ALTER TABLE roles ADD COLUMN IF NOT EXISTS sort_order INT(11) NOT NULL DEFAULT 0 AFTER inherits_from;

-- Add foreign key for role inheritance (self-referencing)
ALTER TABLE roles ADD CONSTRAINT fk_roles_inherits 
  FOREIGN KEY (inherits_from) REFERENCES roles(id) ON DELETE SET NULL;

-- ==========================================
-- Permission Groups Table
-- ==========================================

CREATE TABLE IF NOT EXISTS `permission_groups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `icon` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permission_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add group_id to permissions table
ALTER TABLE permissions ADD COLUMN IF NOT EXISTS `group_id` INT(11) NULL AFTER category;
ALTER TABLE permissions ADD CONSTRAINT fk_permissions_group 
  FOREIGN KEY (group_id) REFERENCES permission_groups(id) ON DELETE SET NULL;

-- ==========================================
-- Company Permissions Table
-- ==========================================

CREATE TABLE IF NOT EXISTS `company_permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  `is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_permission` (`company_id`, `permission_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- Performance Indexes for RBAC Tables
-- ==========================================

-- Indexes for roles table
CREATE INDEX idx_roles_deleted ON roles(deleted_at);
CREATE INDEX idx_roles_system ON roles(is_system);

-- Indexes for permissions table
CREATE INDEX idx_permissions_module ON permissions(module);
CREATE INDEX idx_permissions_category ON permissions(category);
CREATE INDEX idx_permissions_deleted ON permissions(deleted_at);

-- Indexes for role_permissions table
CREATE INDEX idx_role_permissions_role ON role_permissions(role_id);
CREATE INDEX idx_role_permissions_permission ON role_permissions(permission_id);

-- ==========================================
-- Role Inheritance Stored Function
-- ==========================================

-- Example: Setup role hierarchy (uncomment and customize for your roles)
-- UPDATE roles SET inherits_from = (SELECT id FROM roles WHERE name = 'Super Admin') WHERE name = 'Admin';
-- UPDATE roles SET inherits_from = (SELECT id FROM roles WHERE name = 'Admin') WHERE name = 'Manager';
-- UPDATE roles SET inherits_from = (SELECT id FROM roles WHERE name = 'Manager') WHERE name = 'Cashier';

-- Create function to get inherited permissions (uncomment to create)
-- DELIMITER $$
-- CREATE FUNCTION get_role_permissions(p_role_id INT) RETURNS TEXT DETERMINISTIC
-- BEGIN
--   DECLARE v_result TEXT DEFAULT '';
--   DECLARE v_current_id INT;
--   SET v_current_id = p_role_id;
--   
--   WHILE v_current_id IS NOT NULL DO
--     SET v_result = CONCAT(v_result, (SELECT GROUP_CONCAT(permission_id) FROM role_permissions WHERE role_id = v_current_id), ',');
--     SELECT inherits_from INTO v_current_id FROM roles WHERE id = v_current_id;
--   END WHILE;
--   
--   RETURN v_result;
-- END$$
-- DELIMITER ;

-- ==========================================
-- Business Types Performance Indexes
-- ==========================================

-- Indexes for business_types table
CREATE INDEX idx_business_types_active ON business_types(is_active, sort_order);
CREATE INDEX idx_business_types_code ON business_types(code);

-- ==========================================
-- Settings Performance Indexes
-- ==========================================

-- Indexes for settings table
CREATE INDEX idx_settings_company_category ON settings(company_id, category);
CREATE INDEX idx_settings_key_lookup ON settings(company_id, setting_key);

-- ==========================================
-- Products Table Foreign Keys
-- ==========================================

ALTER TABLE products ADD CONSTRAINT fk_products_company 
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE products ADD CONSTRAINT fk_products_category 
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

ALTER TABLE products ADD CONSTRAINT fk_products_tax_rate 
  FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE SET NULL;

ALTER TABLE products ADD CONSTRAINT fk_products_created_by 
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE products ADD CONSTRAINT fk_products_updated_by 
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE products ADD CONSTRAINT fk_products_deleted_by 
  FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE products ADD CONSTRAINT fk_products_branch 
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;

-- ==========================================
-- Product Price History Table
-- ==========================================

CREATE TABLE IF NOT EXISTS `product_price_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `old_price` DECIMAL(10,2) NOT NULL,
  `new_price` DECIMAL(10,2) NOT NULL,
  `old_cost` DECIMAL(10,2) DEFAULT NULL,
  `new_cost` DECIMAL(10,2) DEFAULT NULL,
  `changed_by` INT(11) NOT NULL,
  `change_reason` VARCHAR(255) DEFAULT NULL,
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_price_history_product` (`product_id`),
  KEY `idx_price_history_changed_at` (`changed_at`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: The following columns should be removed after data migration is complete
-- These are commented out as a safety measure - uncomment after confirming data is migrated
-- ALTER TABLE companies DROP COLUMN industry;
-- ALTER TABLE companies DROP COLUMN tax_rate;
-- ALTER TABLE companies DROP COLUMN subscription_status;
