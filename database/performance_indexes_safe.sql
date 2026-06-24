-- Performance Indexes for POS Speed Optimization (Safe version - ignores existing indexes)

-- Critical indexes for inventory lookups (JOIN bottleneck)
ALTER TABLE inventory ADD INDEX IF NOT EXISTS idx_branch_product (branch_id, product_id);
ALTER TABLE inventory ADD INDEX IF NOT EXISTS idx_product_branch (product_id, branch_id);

-- Product filtering indexes
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_tenant_active (tenant_id, active, deleted_at);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_tenant_name (tenant_id, name);

-- Category filtering
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_category (category_id);

-- Business type filtering
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_business_type (business_type_id);

-- Sales query optimization
ALTER TABLE sales ADD INDEX IF NOT EXISTS idx_tenant_branch_date (tenant_id, branch_id, created_at);
ALTER TABLE sales ADD INDEX IF NOT EXISTS idx_status_voided (status, voided);

-- Register sessions lookup
ALTER TABLE register_sessions ADD INDEX IF NOT EXISTS idx_user_open (user_id, tenant_id, branch_id, status);

-- Held sales count
ALTER TABLE held_sales ADD INDEX IF NOT EXISTS idx_tenant_branch (tenant_id, branch_id);

-- Product tags optimization
ALTER TABLE product_tag_relations ADD INDEX IF NOT EXISTS idx_tenant_product (tenant_id, product_id);
ALTER TABLE product_tags ADD INDEX IF NOT EXISTS idx_tenant_active (tenant_id, is_active);
