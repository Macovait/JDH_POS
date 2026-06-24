# Jakababa POS: Company_ID to Tenant_ID Migration Roadmap

## Executive Summary

This document provides a comprehensive, zero-downtime migration strategy to transform Jakababa POS from a mixed `company_id` + `tenant_id` architecture to a clean single `tenant_id` ownership model.

**Current State:**
- Primary production schema uses `company_id` in 50+ tables
- Newer SaaS tables use `tenant_id` in parallel `pos_*` tables
- PHP code has mixed usage with bridging middleware
- Live production data must be preserved

**Target State:**
- All tenant-scoped tables use `tenant_id` exclusively
- `companies` becomes a metadata table linked to tenants
- Complete data isolation at tenant level
- Clean SaaS architecture ready for scaling

---

## Phase 0: Pre-Migration Audit & Preparation

### 0.1 Database Inventory (COMPLETED)

**Tables with `company_id` (50+ tables):**

| Table | Columns | FK Dependencies | Risk Level |
|-------|---------|-----------------|------------|
| `companies` | id, business_type_id, subscription_plan_id... | Primary | CRITICAL |
| `company_subscriptions` | company_id, plan_id... | companies | CRITICAL |
| `branches` | company_id... | companies | HIGH |
| `users` | company_id, branch_id... | companies, branches | HIGH |
| `products` | company_id, category_id, brand_id... | companies, categories, brands | HIGH |
| `categories` | company_id, branch_id... | companies, branches | HIGH |
| `customers` | company_id, branch_id... | companies, branches | HIGH |
| `suppliers` | company_id... | companies | HIGH |
| `inventory` | company_id, branch_id, product_id... | companies, branches, products | HIGH |
| `stock_movements` | company_id, branch_id, product_id... | companies, branches, products | HIGH |
| `sales` | company_id, branch_id, user_id, customer_id... | companies, branches, users, customers | HIGH |
| `sale_items` | company_id, branch_id, sale_id, product_id... | companies, branches, sales, products | HIGH |
| `payments` | company_id, branch_id, sale_id... | companies, branches, sales | HIGH |
| `purchase_orders` | company_id, branch_id, supplier_id... | companies, branches, suppliers | HIGH |
| `activity_logs` | company_id, branch_id, user_id... | companies, branches, users | MEDIUM |
| `notifications` | company_id, branch_id, user_id... | companies, branches, users | MEDIUM |
| `discounts` | company_id, branch_id... | companies, branches | MEDIUM |
| `vouchers` | company_id... | companies | MEDIUM |
| `settings` | company_id, branch_id... | companies, branches | MEDIUM |
| `feature_usage` | company_id... | companies | LOW |
| `invoices` | company_id, subscription_id... | companies, subscriptions | MEDIUM |
| `subscription_payments` | company_id... | companies | MEDIUM |
| `brands` | company_id... | companies | MEDIUM |
| `product_variants` | company_id, product_id... | companies, products | MEDIUM |
| `product_images` | company_id, product_id... | companies, products | LOW |
| `product_attributes` | company_id, product_id... | companies, products | LOW |
| `stock_batches` | company_id, branch_id, product_id... | companies, branches, products | HIGH |
| `stock_transfers` | company_id, from_branch_id, to_branch_id... | companies, branches | HIGH |
| `sale_returns` | company_id, branch_id, sale_id... | companies, branches, sales | HIGH |
| `report_snapshots` | company_id, branch_id... | companies, branches | LOW |
| `restaurant_tables` | company_id, branch_id... | companies, branches | LOW |
| `prescriptions` | company_id, sale_id, customer_id... | companies, sales, customers | MEDIUM |
| `appointments` | company_id, branch_id, customer_id... | companies, branches, customers | LOW |
| `promotions` | company_id, branch_id... | companies, branches | LOW |
| `override_requests` | company_id, branch_id... | companies, branches | LOW |
| `api_tokens` | company_id, user_id... | companies, users | MEDIUM |
| `webhooks` | company_id... | companies | LOW |
| `terminal_sessions` | company_id, branch_id... | companies, branches | LOW |
| `offline_sync_queue` | company_id, branch_id... | companies, branches | LOW |

**Tables with `tenant_id` (Newer SaaS tables):**

| Table | Status |
|-------|--------|
| `tenants` | Active - needs `companies` linkage |
| `pos_tenants` | Legacy - to be deprecated |
| `pos_subscriptions` | Parallel - needs consolidation |
| `pos_invoices` | Parallel - needs consolidation |
| `tenant_configs` | Active - keep |
| `audit_logs` (tenant version) | Active - keep |
| `api_keys` (tenant version) | Active - keep |

**Views Status:**
- No database views detected in current schema
- Application-level data aggregation used instead

### 0.2 PHP Code Inventory

**Files requiring updates (priority order):**

| File | Lines | Impact | Description |
|------|-------|--------|-------------|
| `src/auth.php` | 15+ | CRITICAL | Session initialization, login |
| `src/TenantContext.php` | 10+ | CRITICAL | Core tenant context |
| `src/TenantContextService.php` | 20+ | CRITICAL | Tenant isolation service |
| `src/Middleware/TenantMiddleware.php` | 15+ | HIGH | Tenant resolution |
| `public/ajax/process_sale.php` | 63 | HIGH | Core sales processing |
| `public/customers/bulk_sms.php` | 95 | MEDIUM | Customer operations |
| `public/auth/login.php` | 12 | HIGH | Login handling |

---

## Phase 1: Backup & Safety Measures

### 1.1 Complete Database Backup

```bash
# Full backup before any changes
mysqldump -u root -p jakababa_pos > backups/pre_tenant_migration_$(date +%Y%m%d_%H%M%S).sql

# Individual table backups for critical tables
mysqldump -u root -p jakababa_pos companies branches users products customers sales > backups/critical_tables_backup.sql
```

### 1.2 Create Migration Safety Tables

```sql
-- Migration tracking table
CREATE TABLE IF NOT EXISTS _migration_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phase VARCHAR(50) NOT NULL,
    operation VARCHAR(200) NOT NULL,
    status ENUM('started', 'completed', 'failed', 'rolled_back') NOT NULL,
    records_affected BIGINT UNSIGNED DEFAULT 0,
    error_message TEXT,
    executed_by VARCHAR(100),
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_phase_status (phase, status),
    INDEX idx_started (started_at)
) ENGINE=InnoDB;

-- Company-to-tenant mapping (preserves 1:1 relationship during migration)
CREATE TABLE IF NOT EXISTS _company_tenant_mapping (
    company_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    migration_status ENUM('pending', 'mapped', 'verified', 'completed') DEFAULT 'pending',
    data_migrated BOOLEAN DEFAULT FALSE,
    mapped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    UNIQUE KEY uk_tenant (tenant_id),
    INDEX idx_status (migration_status)
) ENGINE=InnoDB;
```

### 1.3 Application-Level Safety Switch

Create `config/migration_mode.php`:

```php
<?php
/**
 * Migration Mode Configuration
 * This file controls the application's behavior during migration
 */

return [
    // Current migration phase
    'phase' => 'pre_migration', // Options: pre_migration, adding_columns, data_migration, cutting_over, post_migration
    
    // Dual-write mode: Write to both company_id and tenant_id during transition
    'dual_write_mode' => false,
    
    // Read preference: 'company_id' | 'tenant_id' | 'both_check'
    'read_source' => 'company_id',
    
    // Rollback point (timestamp of last known good state)
    'rollback_timestamp' => null,
    
    // Feature flags for gradual rollout
    'features' => [
        'use_tenant_context' => false,
        'enforce_tenant_isolation' => false,
        'tenant_id_writes' => false,
    ],
    
    // Emergency contact for migration issues
    'emergency_contact' => 'admin@jakababa.com',
];
```

---

## Phase 2: Schema Evolution (Zero-Downtime)

### 2.1 Add tenant_id Columns to All Company-Scoped Tables

**Strategy:** Add nullable `tenant_id` columns alongside existing `company_id`

```sql
-- Phase 2.1.1: Add tenant_id to core business tables
SET FOREIGN_KEY_CHECKS = 0;

-- Users and Organization
ALTER TABLE users ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_users_tenant (tenant_id),
    ADD INDEX idx_users_tenant_status (tenant_id, status);

ALTER TABLE branches ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_branches_tenant (tenant_id),
    ADD INDEX idx_branches_tenant_active (tenant_id, is_active);

-- Product Catalog
ALTER TABLE categories ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_categories_tenant (tenant_id);

ALTER TABLE brands ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_brands_tenant (tenant_id);

ALTER TABLE products ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_products_tenant (tenant_id),
    ADD INDEX idx_products_tenant_status (tenant_id, status);

ALTER TABLE product_variants ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_product_variants_tenant (tenant_id);

ALTER TABLE product_images ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_product_images_tenant (tenant_id);

ALTER TABLE product_attributes ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_product_attributes_tenant (tenant_id);

-- Inventory
ALTER TABLE inventory ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_inventory_tenant (tenant_id),
    ADD INDEX idx_inventory_tenant_branch (tenant_id, branch_id);

ALTER TABLE stock_batches ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_stock_batches_tenant (tenant_id);

ALTER TABLE stock_movements ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_stock_movements_tenant (tenant_id),
    ADD INDEX idx_stock_movements_tenant_date (tenant_id, created_at);

ALTER TABLE stock_transfers ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_stock_transfers_tenant (tenant_id);

-- Sales & Customers
ALTER TABLE customers ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_customers_tenant (tenant_id),
    ADD INDEX idx_customers_tenant_status (tenant_id, status);

ALTER TABLE suppliers ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_suppliers_tenant (tenant_id);

ALTER TABLE sales ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_sales_tenant (tenant_id),
    ADD INDEX idx_sales_tenant_date (tenant_id, sold_at);

ALTER TABLE sale_items ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_sale_items_tenant (tenant_id);

ALTER TABLE payments ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_payments_tenant (tenant_id);

ALTER TABLE sale_returns ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_sale_returns_tenant (tenant_id);

-- Purchasing
ALTER TABLE purchase_orders ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_purchase_orders_tenant (tenant_id);

-- Promotions & Discounts
ALTER TABLE discounts ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_discounts_tenant (tenant_id);

ALTER TABLE vouchers ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_vouchers_tenant (tenant_id);

ALTER TABLE promotions ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_promotions_tenant (tenant_id);

-- System & Logging
ALTER TABLE activity_logs ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_activity_logs_tenant (tenant_id),
    ADD INDEX idx_activity_logs_tenant_date (tenant_id, created_at);

ALTER TABLE notifications ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_notifications_tenant (tenant_id);

ALTER TABLE settings ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_settings_tenant (tenant_id);

ALTER TABLE feature_usage ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_feature_usage_tenant (tenant_id);

-- Business-specific tables
ALTER TABLE restaurant_tables ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_restaurant_tables_tenant (tenant_id);

ALTER TABLE prescriptions ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_prescriptions_tenant (tenant_id);

ALTER TABLE appointments ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_appointments_tenant (tenant_id);

-- Enterprise tables
ALTER TABLE override_requests ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_override_requests_tenant (tenant_id);

ALTER TABLE api_tokens ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_api_tokens_tenant (tenant_id);

ALTER TABLE webhooks ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_webhooks_tenant (tenant_id);

ALTER TABLE terminal_sessions ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_terminal_sessions_tenant (tenant_id);

ALTER TABLE offline_sync_queue ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD INDEX idx_offline_sync_queue_tenant (tenant_id);

SET FOREIGN_KEY_CHECKS = 1;

-- Log this operation
INSERT INTO _migration_log (phase, operation, status, records_affected) 
VALUES ('schema_evolution', 'add_tenant_id_columns', 'completed', 0);
```

### 2.2 Update Companies Table Structure

```sql
-- Ensure companies has tenant_id linkage
ALTER TABLE companies ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NULL AFTER id,
    ADD UNIQUE INDEX idx_companies_tenant (tenant_id),
    ADD INDEX idx_companies_tenant_status (tenant_id, status);

-- Add metadata JSON column for extended company info
ALTER TABLE companies ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER feature_flags;
```

---

## Phase 3: Data Migration

### 3.1 Create Tenant Records for Existing Companies

```sql
-- For each company without a tenant, create a tenant record
INSERT INTO tenants (uuid, name, slug, subdomain, status, plan_id, settings, branding, created_at, updated_at)
SELECT 
    UUID() as uuid,
    c.name,
    c.slug,
    c.subdomain,
    c.status,
    COALESCE(c.subscription_plan_id, 1) as plan_id,
    c.settings,
    JSON_OBJECT('logo', c.logo, 'primary_color', '#3b82f6') as branding,
    c.created_at,
    c.updated_at
FROM companies c
LEFT JOIN tenants t ON t.id = c.tenant_id
WHERE c.tenant_id IS NULL OR t.id IS NULL;

-- Map companies to their new/existing tenants
INSERT INTO _company_tenant_mapping (company_id, tenant_id, migration_status)
SELECT 
    c.id as company_id,
    COALESCE(c.tenant_id, t.id) as tenant_id,
    'mapped'
FROM companies c
LEFT JOIN tenants t ON t.slug = c.slug
WHERE c.deleted_at IS NULL
ON DUPLICATE KEY UPDATE 
    tenant_id = VALUES(tenant_id),
    migration_status = 'mapped';

-- Update companies table with tenant_id
UPDATE companies c
JOIN _company_tenant_mapping ctm ON c.id = ctm.company_id
SET c.tenant_id = ctm.tenant_id
WHERE c.tenant_id IS NULL;
```

### 3.2 Populate tenant_id in All Tables

```sql
-- Critical: Update in order of dependency (children after parents)

-- 1. Branches (references companies)
UPDATE branches b
JOIN companies c ON b.company_id = c.id
SET b.tenant_id = c.tenant_id
WHERE b.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 2. Users (references companies, branches)
UPDATE users u
JOIN companies c ON u.company_id = c.id
SET u.tenant_id = c.tenant_id
WHERE u.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 3. Categories (references companies, branches)
UPDATE categories cat
JOIN companies c ON cat.company_id = c.id
SET cat.tenant_id = c.tenant_id
WHERE cat.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 4. Brands (references companies)
UPDATE brands b
JOIN companies c ON b.company_id = c.id
SET b.tenant_id = c.tenant_id
WHERE b.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 5. Products (references companies, categories, brands)
UPDATE products p
JOIN companies c ON p.company_id = c.id
SET p.tenant_id = c.tenant_id
WHERE p.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 6. Product Variants (references companies, products)
UPDATE product_variants pv
JOIN companies c ON pv.company_id = c.id
SET pv.tenant_id = c.tenant_id
WHERE pv.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 7. Product Images (references companies, products)
UPDATE product_images pi
JOIN companies c ON pi.company_id = c.id
SET pi.tenant_id = c.tenant_id
WHERE pi.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 8. Product Attributes (references companies, products)
UPDATE product_attributes pa
JOIN companies c ON pa.company_id = c.id
SET pa.tenant_id = c.tenant_id
WHERE pa.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 9. Customers (references companies, branches)
UPDATE customers cust
JOIN companies c ON cust.company_id = c.id
SET cust.tenant_id = c.tenant_id
WHERE cust.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 10. Suppliers (references companies)
UPDATE suppliers s
JOIN companies c ON s.company_id = c.id
SET s.tenant_id = c.tenant_id
WHERE s.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 11. Inventory (references companies, branches, products)
UPDATE inventory i
JOIN companies c ON i.company_id = c.id
SET i.tenant_id = c.tenant_id
WHERE i.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 12. Stock Batches (references companies, branches, products)
UPDATE stock_batches sb
JOIN companies c ON sb.company_id = c.id
SET sb.tenant_id = c.tenant_id
WHERE sb.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 13. Stock Movements (references companies, branches, products)
UPDATE stock_movements sm
JOIN companies c ON sm.company_id = c.id
SET sm.tenant_id = c.tenant_id
WHERE sm.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 14. Stock Transfers (references companies, branches)
UPDATE stock_transfers st
JOIN companies c ON st.company_id = c.id
SET st.tenant_id = c.tenant_id
WHERE st.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 15. Purchase Orders (references companies, branches, suppliers)
UPDATE purchase_orders po
JOIN companies c ON po.company_id = c.id
SET po.tenant_id = c.tenant_id
WHERE po.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 16. Sales (references companies, branches, users, customers)
UPDATE sales s
JOIN companies c ON s.company_id = c.id
SET s.tenant_id = c.tenant_id
WHERE s.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 17. Sale Items (references sale_id, company_id)
UPDATE sale_items si
JOIN sales s ON si.sale_id = s.id
SET si.tenant_id = s.tenant_id
WHERE si.tenant_id IS NULL AND s.tenant_id IS NOT NULL;

-- 18. Payments (references companies, branches, sales)
UPDATE payments p
JOIN companies c ON p.company_id = c.id
SET p.tenant_id = c.tenant_id
WHERE p.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 19. Sale Returns (references companies, branches, sales)
UPDATE sale_returns sr
JOIN companies c ON sr.company_id = c.id
SET sr.tenant_id = c.tenant_id
WHERE sr.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 20. Discounts (references companies, branches)
UPDATE discounts d
JOIN companies c ON d.company_id = c.id
SET d.tenant_id = c.tenant_id
WHERE d.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 21. Vouchers (references companies)
UPDATE vouchers v
JOIN companies c ON v.company_id = c.id
SET v.tenant_id = c.tenant_id
WHERE v.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 22. Promotions (references companies, branches)
UPDATE promotions p
JOIN companies c ON p.company_id = c.id
SET p.tenant_id = c.tenant_id
WHERE p.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 23. Activity Logs (references companies, branches, users)
UPDATE activity_logs al
JOIN companies c ON al.company_id = c.id
SET al.tenant_id = c.tenant_id
WHERE al.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 24. Notifications (references companies, branches, users)
UPDATE notifications n
JOIN companies c ON n.company_id = c.id
SET n.tenant_id = c.tenant_id
WHERE n.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 25. Settings (references companies, branches)
UPDATE settings s
JOIN companies c ON s.company_id = c.id
SET s.tenant_id = c.tenant_id
WHERE s.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 26. Feature Usage (references companies)
UPDATE feature_usage fu
JOIN companies c ON fu.company_id = c.id
SET fu.tenant_id = c.tenant_id
WHERE fu.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 27. Business-specific tables
UPDATE restaurant_tables rt
JOIN companies c ON rt.company_id = c.id
SET rt.tenant_id = c.tenant_id
WHERE rt.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

UPDATE prescriptions p
JOIN companies c ON p.company_id = c.id
SET p.tenant_id = c.tenant_id
WHERE p.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

UPDATE appointments a
JOIN companies c ON a.company_id = c.id
SET a.tenant_id = c.tenant_id
WHERE a.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

-- 28. Enterprise tables
UPDATE override_requests orq
JOIN companies c ON orq.company_id = c.id
SET orq.tenant_id = c.tenant_id
WHERE orq.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

UPDATE api_tokens at
JOIN companies c ON at.company_id = c.id
SET at.tenant_id = c.tenant_id
WHERE at.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

UPDATE webhooks w
JOIN companies c ON w.company_id = c.id
SET w.tenant_id = c.tenant_id
WHERE w.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

UPDATE terminal_sessions ts
JOIN companies c ON ts.company_id = c.id
SET ts.tenant_id = c.tenant_id
WHERE ts.tenant_id IS NULL AND c.tenant_id IS NOT NULL;

UPDATE offline_sync_queue osq
JOIN companies c ON osq.company_id = c.id
SET osq.tenant_id = c.tenant_id
WHERE osq.tenant_id IS NULL AND c.tenant_id IS NOT NULL;
```

### 3.3 Data Validation Queries

```sql
-- Verify migration completeness for each table
-- Should return 0 rows for each table when complete

-- Critical tables - must have 100% coverage
SELECT 'users' as table_name, COUNT(*) as missing_tenant_id 
FROM users WHERE tenant_id IS NULL AND company_id IS NOT NULL
UNION ALL
SELECT 'products', COUNT(*) FROM products WHERE tenant_id IS NULL AND company_id IS NOT NULL
UNION ALL
SELECT 'sales', COUNT(*) FROM sales WHERE tenant_id IS NULL AND company_id IS NOT NULL
UNION ALL
SELECT 'customers', COUNT(*) FROM customers WHERE tenant_id IS NULL AND company_id IS NOT NULL
UNION ALL
SELECT 'inventory', COUNT(*) FROM inventory WHERE tenant_id IS NULL AND company_id IS NOT NULL;

-- Verify tenant_id consistency (should match company_id tenant mapping)
SELECT 
    'users' as table_name,
    COUNT(*) as inconsistent_records
FROM users u
JOIN companies c ON u.company_id = c.id
WHERE u.tenant_id != c.tenant_id

UNION ALL

SELECT 
    'products',
    COUNT(*)
FROM products p
JOIN companies c ON p.company_id = c.id
WHERE p.tenant_id != c.tenant_id;

-- Check for orphaned records (tenant_id set but company_id has different tenant)
SELECT 
    t.table_name,
    t.missing_count
FROM (
    SELECT 
        'users' as table_name,
        COUNT(*) as missing_count
    FROM users u
    JOIN companies c ON u.company_id = c.id
    WHERE u.tenant_id IS NOT NULL AND u.tenant_id != c.tenant_id
    
    UNION ALL
    
    SELECT 'sales', COUNT(*)
    FROM sales s
    JOIN companies c ON s.company_id = c.id
    WHERE s.tenant_id IS NOT NULL AND s.tenant_id != c.tenant_id
) t
WHERE t.missing_count > 0;
```

---

## Phase 4: Application Code Updates

### 4.1 Core Context Classes Update

**File: `src/TenantContext.php`**
- Add `getTenantId()` method (alias for `getCompanyId()` during transition)
- Update `getCurrentTenantWhere()` to use `tenant_id`

**File: `src/TenantContextService.php`**
- Update to use `tenant_id` consistently
- Add backward compatibility layer

**File: `src/Middleware/TenantMiddleware.php`**
- Ensure tenant resolution from company_id continues to work
- Add tenant_id to session alongside company_id

### 4.2 Auth System Updates

**File: `src/auth.php`**
- Update `init_user_session()` to set both `company_id` and `tenant_id`
- Add `get_current_tenant_id()` function
- Update `company_filter_condition()` to support both columns

### 4.3 Dual-Write Implementation

During the transition period, application should write to both columns:

```php
// Example pattern for INSERT operations
function insert_with_tenant($table, $data) {
    $company_id = get_current_company_id();
    $tenant_id = get_current_tenant_id(); // From session or companies lookup
    
    $data['company_id'] = $company_id;
    $data['tenant_id'] = $tenant_id;
    
    return db_insert($table, $data);
}

// Example pattern for SELECT operations (dual-check during transition)
function select_with_tenant($table, $conditions = []) {
    $company_id = get_current_company_id();
    $tenant_id = get_current_tenant_id();
    
    // Prefer tenant_id if available, fallback to company_id
    if ($tenant_id) {
        $conditions['tenant_id'] = $tenant_id;
    } else {
        $conditions['company_id'] = $company_id;
    }
    
    return db_select($table, $conditions);
}
```

---

## Phase 5: Testing & Validation

### 5.1 Pre-Cutover Testing

```sql
-- Create test tenant in isolation
INSERT INTO tenants (uuid, name, slug, subdomain, status, plan_id)
VALUES (UUID(), 'Migration Test Tenant', 'migration-test', 'migration-test', 'trial', 1);

-- Set @test_tenant_id = LAST_INSERT_ID();
-- Create test company linked to tenant
-- Insert test data across all tables with new tenant_id
-- Run application tests against test tenant
-- Verify data isolation
```

### 5.2 Production Validation Checklist

- [ ] All `tenant_id` columns populated
- [ ] No orphaned records (records with `company_id` but no `tenant_id`)
- [ ] Data consistency verified (`tenant_id` matches company's tenant)
- [ ] Foreign key integrity maintained
- [ ] Application writes to both columns
- [ ] Application reads from `tenant_id` preferentially
- [ ] Indexes created and optimized
- [ ] No performance degradation
- [ ] Backup verified restorable

---

## Phase 6: Cutover & Cleanup

### 6.1 Final Cutover Steps

```sql
-- 1. Make tenant_id NOT NULL (after 100% data migration verified)
-- Example for critical tables:
-- ALTER TABLE users MODIFY tenant_id INT UNSIGNED NOT NULL;
-- ALTER TABLE products MODIFY tenant_id INT UNSIGNED NOT NULL;
-- ALTER TABLE sales MODIFY tenant_id INT UNSIGNED NOT NULL;

-- 2. Add foreign key constraints to tenants table
-- ALTER TABLE users ADD CONSTRAINT fk_users_tenant 
--     FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

-- 3. Drop company_id foreign keys (after application fully migrated)
-- ALTER TABLE users DROP FOREIGN KEY fk_users_company;
-- ALTER TABLE users DROP INDEX idx_users_company;

-- 4. Final verification
SELECT 
    'Migration Complete' as status,
    (SELECT COUNT(*) FROM users WHERE tenant_id IS NULL) as users_missing,
    (SELECT COUNT(*) FROM products WHERE tenant_id IS NULL) as products_missing,
    (SELECT COUNT(*) FROM sales WHERE tenant_id IS NULL) as sales_missing;
```

### 6.2 Post-Migration Cleanup

```sql
-- Remove migration tracking tables (after 30-day validation period)
-- DROP TABLE IF EXISTS _migration_log;
-- DROP TABLE IF EXISTS _company_tenant_mapping;

-- Archive or drop company_id columns (after 90-day validation period)
-- ALTER TABLE users DROP COLUMN company_id;
-- ALTER TABLE products DROP COLUMN company_id;
-- etc.
```

---

## Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Data loss during migration | Low | Critical | Full backups, transaction wrapping, validation scripts |
| Application downtime | Low | High | Zero-downtime approach, dual-write period, blue-green ready |
| Orphaned records | Medium | High | Validation queries, mapping table enforcement |
| Performance degradation | Low | Medium | Index creation, query optimization, monitoring |
| Failed rollback | Low | Critical | Tested rollback scripts, point-in-time recovery |
| Tenant isolation breach | Low | Critical | Comprehensive testing, security validation |

---

## Rollback Plan

### Immediate Rollback (< 1 hour)

```sql
-- Disable tenant_id usage
UPDATE config SET value = 'company_id' WHERE key = 'tenant_read_source';

-- Application automatically falls back to company_id
-- No data loss - both columns exist
```

### Full Rollback (if data corruption detected)

```bash
# Restore from pre-migration backup
mysql -u root -p jakababa_pos < backups/pre_tenant_migration_YYYYMMDD.sql

# Or use point-in-time recovery if binlog enabled
```

---

## Timeline Estimate

| Phase | Duration | Dependencies |
|-------|----------|--------------|
| Phase 0: Audit | 1 day | - |
| Phase 1: Backup & Safety | 4 hours | Phase 0 |
| Phase 2: Schema Evolution | 4 hours | Phase 1 |
| Phase 3: Data Migration | 2-8 hours | Phase 2, depends on data size |
| Phase 4: Code Updates | 2-3 days | Phase 2 |
| Phase 5: Testing | 2-3 days | Phase 4 |
| Phase 6: Cutover | 4 hours | Phase 5 |
| Phase 7: Cleanup | 1 day (after 30 days) | Phase 6 |

**Total Critical Path:** 7-10 days

---

## Success Criteria

1. All 50+ tables use `tenant_id` as primary ownership column
2. Zero data loss verified by checksum comparison
3. Application functions identically from user perspective
4. Tenant isolation enforced at database level
5. Query performance maintained or improved
6. Successful rollback test completed
7. All existing integrations continue to work

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-27  
**Approved By:** Database Refactoring Architect  
**Next Review:** Before Phase 6 execution
