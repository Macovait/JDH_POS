-- Database Index Optimization
-- Adds indexes for frequently queried columns to improve performance
-- Run this in phpMyAdmin or mysql command line

-- Products table indexes
ALTER TABLE products ADD INDEX idx_tenant_active (tenant_id, active, deleted_at);
ALTER TABLE products ADD INDEX idx_sku (sku);
ALTER TABLE products ADD INDEX idx_barcode (barcode);
ALTER TABLE products ADD INDEX idx_category (category_id);
ALTER TABLE products ADD INDEX idx_brand (brand_id);

-- Sales table indexes
ALTER TABLE sales ADD INDEX idx_tenant_date (tenant_id, created_at);
ALTER TABLE sales ADD INDEX idx_receipt (receipt_number);
ALTER TABLE sales ADD INDEX idx_customer (customer_id);
ALTER TABLE sales ADD INDEX idx_status (status);
ALTER TABLE sales ADD INDEX idx_branch (branch_id);

-- Customers table indexes
ALTER TABLE customers ADD INDEX idx_tenant_active (tenant_id, active);
ALTER TABLE customers ADD INDEX idx_email (email);
ALTER TABLE customers ADD INDEX idx_phone (phone);

-- Users table indexes
ALTER TABLE users ADD INDEX idx_tenant_role (tenant_id, role_id, status);
ALTER TABLE users ADD INDEX idx_username (username);
ALTER TABLE users ADD INDEX idx_email (email);

-- Inventory table indexes
ALTER TABLE inventory ADD INDEX idx_product_branch (product_id, branch_id);
ALTER TABLE inventory ADD INDEX idx_tenant (tenant_id);

-- Activity Logs table indexes
ALTER TABLE activity_logs ADD INDEX idx_user_time (user_id, created_at);
ALTER TABLE activity_logs ADD INDEX idx_tenant (tenant_id);
ALTER TABLE activity_logs ADD INDEX idx_action (action);

-- Permissions table indexes
ALTER TABLE role_permissions ADD INDEX idx_role (role_id, tenant_id);
ALTER TABLE permissions ADD INDEX idx_code (code, tenant_id);

-- Categories table indexes
ALTER TABLE categories ADD INDEX idx_tenant_parent (tenant_id, parent_id);

-- Suppliers table indexes
ALTER TABLE suppliers ADD INDEX idx_tenant_active (tenant_id, active);

-- Purchases table indexes
ALTER TABLE purchases ADD INDEX idx_tenant_date (tenant_id, created_at);
ALTER TABLE purchases ADD INDEX idx_status (status);

-- Quotations table indexes
ALTER TABLE quotations ADD INDEX idx_tenant_date (tenant_id, created_at);
ALTER TABLE quotations ADD INDEX idx_customer (customer_id);

-- Expenses table indexes
ALTER TABLE expenses ADD INDEX idx_tenant_date (tenant_id, expense_date);
ALTER TABLE expenses ADD INDEX idx_category (category_id);

-- Settings table indexes
ALTER TABLE settings ADD INDEX idx_tenant_key (tenant_id, setting_key);

-- Notifications table indexes
ALTER TABLE notifications ADD INDEX idx_user_read (user_id, is_read);
ALTER TABLE notifications ADD INDEX idx_created (created_at);

-- Audit Logs table indexes
ALTER TABLE audit_logs ADD INDEX idx_user_time (user_id, created_at);
ALTER TABLE audit_logs ADD INDEX idx_tenant (tenant_id);
ALTER TABLE audit_logs ADD INDEX idx_action (action_type);
