-- Enterprise Barcode Label Printing Migration (2026)
-- Date: 2026-05-23
--
-- IMPORTANT: This migration depends on the following file which must be run FIRST:
--   sql/migrations/2026_05_23_create_tenant_settings.sql
--
-- The tenant_settings table is required for:
--   - Dashboard Label Printing quick settings card
--   - Global "Labels" button in header
--   - SettingsManager used by LabelPrinter / auto-print on sale
--   - Per-tenant label preferences (size, method, auto-print toggle)
--
-- Run order for full feature:
--   1. 2026_05_23_create_tenant_settings.sql
--   2. This file (2026_05_23_label_printing_enterprise.sql)

-- 1. Tenant settings table (required for new enterprise label features)
--    See: 2026_05_23_create_tenant_settings.sql (run first)

-- 2. Add short_barcode for professional compact codes
ALTER TABLE products 
ADD COLUMN short_barcode VARCHAR(20) NULL AFTER sku;

-- Populate short codes (safe to run multiple times)
UPDATE products 
SET short_barcode = CONCAT('JB', LPAD(id, 5, '0')) 
WHERE short_barcode IS NULL OR short_barcode = '';

-- 3. Print history & audit table (required for Reprint + SaaS features)
CREATE TABLE IF NOT EXISTS label_print_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    product_data JSON NOT NULL,
    label_size VARCHAR(20) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    print_method ENUM('pdf','browser','escpos') NOT NULL,
    printed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_user (tenant_id, user_id),
    INDEX idx_printed_at (printed_at),
    INDEX idx_tenant_product (tenant_id, JSON_EXTRACT(product_data, '$[0].id'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Optional: Add index for faster batch queries
ALTER TABLE products 
ADD INDEX idx_products_tenant_stock (tenant_id, deleted_at, stock_quantity);
