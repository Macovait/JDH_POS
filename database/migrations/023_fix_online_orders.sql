-- Fix online_orders table schema for storefront checkout
-- Run this in phpMyAdmin or mysql CLI

-- Add missing columns to online_orders
ALTER TABLE online_orders
    ADD COLUMN IF NOT EXISTS uuid VARCHAR(32) UNIQUE AFTER tenant_id,
    ADD COLUMN IF NOT EXISTS order_number VARCHAR(50) AFTER uuid,
    ADD COLUMN IF NOT EXISTS subtotal DECIMAL(12,2) DEFAULT 0.00 AFTER customer_email,
    ADD COLUMN IF NOT EXISTS discount DECIMAL(12,2) DEFAULT 0.00 AFTER subtotal,
    ADD COLUMN IF NOT EXISTS payment_status VARCHAR(30) DEFAULT 'pending' AFTER payment_method,
    ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(50) AFTER payment_status,
    ADD COLUMN IF NOT EXISTS notes TEXT AFTER delivery_address;

-- Create index on uuid for fast lookups
CREATE INDEX IF NOT EXISTS idx_uuid ON online_orders(uuid);

-- Create notification_jobs table
CREATE TABLE IF NOT EXISTS notification_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    payload LONGTEXT,
    status VARCHAR(30) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
