# Multi-Tenant Filtering System Guide

## Overview

This system provides automatic data isolation across 4 dimensions:
- **tenant_id** - Company/Tenant isolation (highest level)
- **branch_id** - Branch/Location isolation within tenant
- **user_id** - User-specific data isolation
- **business_type_id** - Business type filtering (retail, restaurant, etc.)

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    TenantContext (Singleton)                │
│  • Immutable, thread-safe context from session               │
│  • Provides: tenant_id, branch_id, user_id, business_type   │
│  • Security validation & super admin detection               │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                 MultiTenantFilter (Singleton)               │
│  • Table scope configurations                                │
│  • Automatic WHERE clause generation                         │
│  • Query builders (SELECT, INSERT, UPDATE, DELETE)         │
│  • Access control verification                             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      Helper Functions                        │
│  • tenant_where() - Quick WHERE clause                      │
│  • tenant_where_pdo() - Prepared statement version          │
│  • build_tenant_query() - Complete query builder          │
│  • build_tenant_insert/update/delete() - DML builders     │
└─────────────────────────────────────────────────────────────┘
```

## Quick Start

### 1. Basic Usage (Simple Queries)

```php
require_once __DIR__ . '/../src/MultiTenantFilter.php';

// Automatic WHERE clause with tenant + branch scoping
$sql = "SELECT * FROM sales WHERE " . tenant_where('sales');
// Result: "SELECT * FROM sales WHERE tenant_id = 1 AND branch_id = 2"

// With table alias
$sql = "SELECT * FROM sales s WHERE " . tenant_where('sales', 's');
// Result: "SELECT * FROM sales s WHERE s.tenant_id = 1 AND s.branch_id = 2"
```

### 2. Prepared Statements (Recommended)

```php
// Get WHERE clause with placeholders
$scope = tenant_where_pdo('sales', 's');
// Returns: [
//   'where' => 's.tenant_id = :tenant_id AND s.branch_id = :branch_id',
//   'params' => [':tenant_id' => 1, ':branch_id' => 2],
//   'conditions' => ['s.tenant_id = :tenant_id', 's.branch_id = :branch_id']
// ]

// Build complete query
$additionalWhere = ['s.status = :status', 's.created_at > :date'];
$params = array_merge($scope['params'], [
    ':status' => 'completed',
    ':date' => '2024-01-01'
]);

$sql = "SELECT * FROM sales s WHERE {$scope['where']} AND s.status = :status";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
```

### 3. Complete Query Builder

```php
// Build complete SELECT with automatic scoping
$query = build_tenant_query('sales', '*', [
    'status = :status' => 'completed',
    'created_at > :date' => '2024-01-01'
], 's');

// Result:
// $query['sql'] = "SELECT * FROM sales AS s 
//    WHERE s.tenant_id = :tenant_id AND s.branch_id = :branch_id 
//    AND status = :status AND created_at > :date"
// $query['params'] = [':tenant_id' => 1, ':branch_id' => 2, ...]

$stmt = $pdo->prepare($query['sql']);
$stmt->execute($query['params']);
```

### 4. DML Operations (INSERT/UPDATE/DELETE)

```php
// INSERT - automatically injects tenant_id, branch_id, user_id
$insert = build_tenant_insert('sales', [
    'invoice_number' => 'INV-001',
    'total' => 100.00,
    'customer_id' => 5
]);
// Result includes: tenant_id, branch_id, user_id automatically added

$stmt = $pdo->prepare($insert['sql']);
$stmt->execute($insert['params']);

// UPDATE - automatically adds tenant scoping
$update = build_tenant_update('sales', 
    ['status' => 'refunded'],  // SET
    ['id = :id' => 123]       // WHERE
);

$stmt = $pdo->prepare($update['sql']);
$stmt->execute($update['params']);

// DELETE - automatically adds tenant scoping + soft delete
$delete = build_tenant_delete('sales', ['id = :id' => 123]);
// Uses soft delete if deleted_at column exists, otherwise hard delete
```

## Table Scope Configuration

### Default Scope Rules

```php
// Defined in MultiTenantFilter::$tableScopes
[
    // Core tables - scoped by tenant + branch
    'sales' => ['tenant_id', 'branch_id'],
    'products' => ['tenant_id', 'branch_id'],
    'customers' => ['tenant_id', 'branch_id'],
    
    // User-specific tables
    'user_settings' => ['tenant_id', 'user_id'],
    
    // Tenant-only tables (shared across branches)
    'categories' => ['tenant_id'],
    'suppliers' => ['tenant_id'],
    
    // Business type specific
    'restaurant_tables' => ['tenant_id', 'branch_id'],
    
    // System tables (no scoping)
    'companies' => [],
    'plans' => [],
]
```

### Custom Scope Registration

```php
// Register new table with custom scopes
MultiTenantFilter::registerTable('my_custom_table', ['tenant_id', 'branch_id', 'user_id']);

// Or use default (tenant_id only)
MultiTenantFilter::registerTable('another_table');
```

## Security Features

### 1. Automatic Scope Injection
All queries automatically include tenant context - developers cannot accidentally forget to filter.

### 2. Access Control
```php
// Check if user can access a specific record
$record = $pdo->query("SELECT * FROM sales WHERE id = 123")->fetch();
if (!can_access('sales', $record)) {
    throw new SecurityException('Access denied');
}
```

### 3. Super Admin Override
Super admins can access all data (controlled by `TenantContext::allowCrossTenant()`).

### 4. Immutable Context
`TenantContext` is immutable - once created, it cannot be modified, preventing scope escalation attacks.

## Advanced Patterns

### 1. Complex Joins

```php
// Multi-table joins with proper scoping
$scope1 = tenant_where_pdo('sales', 's');
$scope2 = tenant_where_pdo('customers', 'c');

$sql = "SELECT s.*, c.name 
        FROM sales s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE {$scope1['where']} AND {$scope2['where']}";

$params = array_merge($scope1['params'], $scope2['params']);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
```

### 2. Dynamic Scope Application

```php
// Add scope to existing query builder
$whereConditions = ['status = :status'];
$params = [':status' => 'active'];

apply_tenant_scope('products', $whereConditions, $params, 'p');

$sql = "SELECT * FROM products p WHERE " . implode(' AND ', $whereConditions);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
```

### 3. Cross-Branch Reporting (Admin Only)

```php
// Temporarily bypass branch filtering for reports
if (TenantContext::getInstance()->isSuperAdmin()) {
    // Manual query without branch filter
    $sql = "SELECT * FROM sales WHERE tenant_id = :tenant_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tenant_id' => $tenant_id]);
}
```

## Migration Guide

### Step 1: Add Scope Columns

Ensure all tenant-scoped tables have the required columns:

```sql
-- Add to existing tables
ALTER TABLE sales ADD COLUMN tenant_id INT NOT NULL AFTER id;
ALTER TABLE sales ADD COLUMN branch_id INT NOT NULL AFTER tenant_id;
ALTER TABLE sales ADD INDEX idx_tenant_branch (tenant_id, branch_id);
```

### Step 2: Backfill Data

```sql
-- Set tenant_id for existing records
UPDATE sales SET tenant_id = 1 WHERE tenant_id IS NULL;
UPDATE sales SET branch_id = 1 WHERE branch_id IS NULL;
```

### Step 3: Update Queries

Replace manual filtering with tenant functions:

```php
// BEFORE (manual, error-prone)
$sql = "SELECT * FROM sales WHERE tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['tenant_id'], $_SESSION['branch_id']]);

// AFTER (automatic, secure)
$query = build_tenant_query('sales');
$stmt = $pdo->prepare($query['sql']);
$stmt->execute($query['params']);
```

## Best Practices

1. **Always use helper functions** - Never hardcode tenant_id/branch_id
2. **Use prepared statements** - Prevent SQL injection
3. **Register new tables** - Add to `MultiTenantFilter::$tableScopes`
4. **Test with multiple tenants** - Ensure data isolation works
5. **Audit queries** - Log cross-tenant access attempts

## Troubleshooting

### "Missing tenant context" error
- Ensure `TenantContext` is initialized before database operations
- Check that session has `tenant_id`, `branch_id`, `user_id`

### Data showing from wrong branch
- Verify `get_current_branch_id()` returns correct value
- Check that queries use `tenant_where()` or `build_tenant_query()`

### Super admin seeing no data
- Super admin mode returns `1=1` for WHERE clauses
- Ensure tables have data for all tenants

## API Reference

### Functions

| Function | Description |
|----------|-------------|
| `tenant_where($table, $alias)` | Get WHERE clause string |
| `tenant_where_pdo($table, $alias)` | Get WHERE with placeholders |
| `build_tenant_query($table, $cols, $where, $alias)` | Build SELECT query |
| `build_tenant_insert($table, $data)` | Build INSERT with scope injection |
| `build_tenant_update($table, $data, $where)` | Build UPDATE with scope |
| `build_tenant_delete($table, $where, $soft)` | Build DELETE with scope |
| `can_access($table, $record)` | Check record access |
| `get_tenant_context()` | Get current context array |

### Classes

| Class | Purpose |
|-------|---------|
| `TenantContext` | Immutable context from session |
| `MultiTenantFilter` | Query builder and scope management |
