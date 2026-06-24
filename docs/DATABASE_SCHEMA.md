# Multi-Tenant Database Schema Requirements

## Required Columns

Every tenant-scoped table MUST have these columns:

```sql
-- Standard columns for all scoped tables
ALTER TABLE your_table ADD COLUMN (
    tenant_id INT NOT NULL,
    branch_id INT NOT NULL,
    created_by INT NULL,          -- user_id who created
    business_type_id INT NULL,    -- optional, for business type filtering
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL       -- for soft deletes
);

-- Critical: Index for performance
CREATE INDEX idx_tenant_branch ON your_table(tenant_id, branch_id);
CREATE INDEX idx_tenant_branch_deleted ON your_table(tenant_id, branch_id, deleted_at);
```

## Migration Scripts

### Step 1: Add Columns to Existing Tables

```sql
-- Run this for each tenant-scoped table

-- Sales tables
ALTER TABLE sales ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE sales ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER tenant_id;
ALTER TABLE sales ADD COLUMN created_by INT NULL AFTER branch_id;

ALTER TABLE sale_items ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE sale_items ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER tenant_id;

-- Product tables
ALTER TABLE products ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE products ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER tenant_id;
ALTER TABLE products ADD COLUMN created_by INT NULL AFTER branch_id;

-- Customer tables
ALTER TABLE customers ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE customers ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER tenant_id;

-- Inventory tables
ALTER TABLE inventory ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE inventory ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER tenant_id;

-- Purchase tables
ALTER TABLE purchases ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE purchases ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER tenant_id;

-- Expense tables
ALTER TABLE expenses ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE expenses ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER tenant_id;
```

### Step 2: Create Indexes

```sql
-- Indexes for performance (critical!)
CREATE INDEX idx_sales_tenant_branch ON sales(tenant_id, branch_id);
CREATE INDEX idx_products_tenant_branch ON products(tenant_id, branch_id);
CREATE INDEX idx_customers_tenant_branch ON customers(tenant_id, branch_id);
CREATE INDEX idx_inventory_tenant_branch ON inventory(tenant_id, branch_id);
CREATE INDEX idx_purchases_tenant_branch ON purchases(tenant_id, branch_id);
CREATE INDEX idx_expenses_tenant_branch ON expenses(tenant_id, branch_id);

-- Composite indexes for common queries
CREATE INDEX idx_sales_tenant_branch_date ON sales(tenant_id, branch_id, created_at);
CREATE INDEX idx_sales_tenant_branch_status ON sales(tenant_id, branch_id, status);
```

### Step 3: Remove Default Values (After Migration)

```sql
-- After backfilling data, remove defaults to enforce strict scoping
ALTER TABLE sales ALTER COLUMN tenant_id DROP DEFAULT;
ALTER TABLE sales ALTER COLUMN branch_id DROP DEFAULT;

-- Make NOT NULL explicit
ALTER TABLE sales MODIFY tenant_id INT NOT NULL;
ALTER TABLE sales MODIFY branch_id INT NOT NULL;
```

## Table Scope Matrix

| Table | tenant_id | branch_id | user_id | business_type_id | Notes |
|-------|-----------|-----------|---------|------------------|-------|
| sales | ✅ | ✅ | - | - | Transaction data |
| sale_items | ✅ | ✅ | - | - | Child of sales |
| products | ✅ | ✅ | - | - | Per-branch inventory |
| customers | ✅ | ✅ | - | - | Branch-specific customers |
| inventory | ✅ | ✅ | - | - | Stock per branch |
| stock_adjustments | ✅ | ✅ | ✅ | - | Who made adjustment |
| purchases | ✅ | ✅ | - | - | Purchase orders |
| expenses | ✅ | ✅ | - | - | Branch expenses |
| categories | ✅ | - | - | - | Shared across branches |
| suppliers | ✅ | - | - | - | Shared across branches |
| taxes | ✅ | - | - | - | Company-wide tax settings |
| payment_methods | ✅ | - | - | - | Company-wide payment options |
| users | ✅ | ✅ | - | - | Branch assignment |
| user_settings | ✅ | - | ✅ | - | Personal user settings |
| saved_carts | ✅ | ✅ | ✅ | - | Personal saved carts |
| restaurant_tables | ✅ | ✅ | - | ✅ | Restaurant feature |
| reservations | ✅ | ✅ | - | ✅ | Restaurant feature |
| kitchen_orders | ✅ | ✅ | - | ✅ | Restaurant feature |
| sms_templates | ✅ | - | - | - | Company-wide templates |
| email_templates | ✅ | - | - | - | Company-wide templates |
| audit_logs | ✅ | ✅ | ✅ | - | Track who did what |

## Special Cases

### 1. Shared Tables (No Branch Scope)

Tables shared across all branches within a tenant:

```sql
-- These tables only have tenant_id
ALTER TABLE categories ADD COLUMN tenant_id INT NOT NULL AFTER id;
-- NO branch_id column

CREATE INDEX idx_categories_tenant ON categories(tenant_id);
```

**Registration in PHP:**
```php
MultiTenantFilter::registerTable('categories', ['tenant_id']);
```

### 2. User-Specific Tables

Tables scoped to individual users:

```sql
ALTER TABLE user_settings ADD COLUMN tenant_id INT NOT NULL AFTER id;
ALTER TABLE user_settings ADD COLUMN user_id INT NOT NULL AFTER tenant_id;
-- NO branch_id

CREATE INDEX idx_user_settings_tenant_user ON user_settings(tenant_id, user_id);
```

**Registration in PHP:**
```php
MultiTenantFilter::registerTable('user_settings', ['tenant_id', 'user_id']);
```

### 3. System Tables (No Scope)

Administrative tables with no tenant isolation:

```sql
-- No tenant_id, branch_id, or user_id
-- Examples: plans, system_settings, countries, currencies
```

**Registration in PHP:**
```php
MultiTenantFilter::registerTable('plans', []); // Empty array = no scope
```

### 4. Business Type Specific

Tables only for specific business types:

```sql
ALTER TABLE restaurant_tables ADD COLUMN tenant_id INT NOT NULL AFTER id;
ALTER TABLE restaurant_tables ADD COLUMN branch_id INT NOT NULL AFTER tenant_id;
ALTER TABLE restaurant_tables ADD COLUMN business_type_id INT NOT NULL AFTER branch_id;
```

**Registration in PHP:**
```php
MultiTenantFilter::registerTable('restaurant_tables', 
    ['tenant_id', 'branch_id', 'business_type_id']);
```

## Soft Delete Configuration

### Enable Soft Deletes

```sql
-- Add deleted_at to all tables
ALTER TABLE sales ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE products ADD COLUMN deleted_at TIMESTAMP NULL;
ALTER TABLE customers ADD COLUMN deleted_at TIMESTAMP NULL;

-- Update indexes to include deleted_at
CREATE INDEX idx_sales_tenant_branch_deleted 
    ON sales(tenant_id, branch_id, deleted_at);
```

### Query with Soft Deletes

```php
// Automatic soft delete filtering
$scope = tenant_where_pdo('sales', 's');
$sql = "SELECT * FROM sales s 
        WHERE {$scope['where']} 
        AND s.deleted_at IS NULL";
```

## Performance Optimization

### 1. Partitioning (Large Tables)

```sql
-- Partition sales table by tenant for massive datasets
ALTER TABLE sales PARTITION BY RANGE (tenant_id) (
    PARTITION p0 VALUES LESS THAN (100),
    PARTITION p1 VALUES LESS THAN (200),
    PARTITION p2 VALUES LESS THAN (300),
    PARTITION pmax VALUES LESS THAN MAXVALUE
);
```

### 2. Archive Strategy

```sql
-- Create archive table for old data
CREATE TABLE sales_archive LIKE sales;

-- Move old data (run monthly)
INSERT INTO sales_archive 
SELECT * FROM sales 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)
AND deleted_at IS NOT NULL;

DELETE FROM sales 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR)
AND deleted_at IS NOT NULL;
```

## Data Integrity

### Foreign Key Constraints

```sql
-- Ensure referential integrity
ALTER TABLE sales 
ADD CONSTRAINT fk_sales_tenant 
    FOREIGN KEY (tenant_id) REFERENCES companies(id),
ADD CONSTRAINT fk_sales_branch 
    FOREIGN KEY (branch_id) REFERENCES branches(id),
ADD CONSTRAINT fk_sales_user 
    FOREIGN KEY (created_by) REFERENCES users(id);
```

### Check Constraints (MySQL 8.0+)

```sql
-- Prevent cross-tenant data corruption
ALTER TABLE sales 
ADD CONSTRAINT chk_sales_tenant 
    CHECK (tenant_id > 0),
ADD CONSTRAINT chk_sales_branch 
    CHECK (branch_id > 0);
```

## Backfill Script

```sql
-- Set correct tenant_id and branch_id for existing records
-- Run this after adding columns

UPDATE sales SET tenant_id = 1, branch_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
UPDATE products SET tenant_id = 1, branch_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
UPDATE customers SET tenant_id = 1, branch_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;
UPDATE inventory SET tenant_id = 1, branch_id = 1 WHERE tenant_id = 0 OR tenant_id IS NULL;

-- Set created_by from session user (if available)
UPDATE sales SET created_by = 1 WHERE created_by IS NULL;
UPDATE products SET created_by = 1 WHERE created_by IS NULL;
```

## Validation Queries

### Verify Data Isolation

```sql
-- Check if data is properly isolated
SELECT 
    tenant_id,
    branch_id,
    COUNT(*) as record_count
FROM sales
GROUP BY tenant_id, branch_id
ORDER BY tenant_id, branch_id;

-- Should show separate counts per branch
-- If you see NULLs or 0s, migration failed
```

### Check for Orphaned Records

```sql
-- Find records without valid tenant
SELECT COUNT(*) as orphaned 
FROM sales s
LEFT JOIN companies c ON s.tenant_id = c.id
WHERE c.id IS NULL;

-- Should return 0
```

## Rollback Plan

If something goes wrong:

```sql
-- Remove scope columns (destructive - only in emergency!)
ALTER TABLE sales DROP COLUMN tenant_id;
ALTER TABLE sales DROP COLUMN branch_id;

-- Remove indexes
DROP INDEX idx_sales_tenant_branch ON sales;
```

## Verification Checklist

After migration, verify:

- [ ] All tables have tenant_id column
- [ ] All tables have branch_id column (if branch-scoped)
- [ ] No NULL values in tenant_id/branch_id
- [ ] Indexes created for performance
- [ ] Foreign keys working (if enabled)
- [ ] Application queries updated
- [ ] Data isolation working (test with 2 branches)
- [ ] Soft deletes working (if enabled)
