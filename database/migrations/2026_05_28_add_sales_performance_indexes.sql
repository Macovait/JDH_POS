-- Sales Performance Indexes
-- Date: 2026-05-28
-- Purpose: Add indexes to optimize sales processing performance
-- Specifically targets the stock validation query in process_sale.php

-- Index for products table to optimize stock validation query:
-- WHERE p.id IN (...) AND p.tenant_id = ? AND (p.deleted_at IS NULL OR p.deleted_at = '0000-00-00 00:00:00')
ALTER TABLE `products` 
ADD INDEX `idx_products_tenant_id_deleted` (`tenant_id`, `id`, `deleted_at`);

-- Additional covering index for products to include frequently selected columns
-- This creates a covering index for the stock validation query to avoid hitting the table data
ALTER TABLE `products` 
ADD INDEX `idx_products_tenant_id_deleted_covering` (`tenant_id`, `id`, `deleted_at`, `name`, `price`, `selling_price`);

-- Index for sale_items to optimize lookups by sale_id (already exists but ensuring)
-- Note: sale_items table already has idx_sale_items_sale (sale_id) on line 1653

-- Index for inventory to optimize stock checks (already has good coverage)
-- The inventory table already has idx_inventory_tenant_branch_product (tenant_id, branch_id, product_id) on line 1439
-- which is optimal for the JOIN condition in the stock validation query

-- Optional: Index for faster tenant-based lookups in sales (already well covered)
-- sales table already has multiple tenant_id indexes including:
-- idx_sales_tenant (line 1624), idx_sales_tenant_id (line 1630), 
-- idx_sales_tenant_branch_date (line 1628), idx_sales_tenant_created (line 1629)

-- Note: The invoice_sequences table used for invoice number generation
-- already has a good index structure: UNIQUE KEY `unique_sequence` (`tenant_id`,`branch_id`,`year`,`month`)