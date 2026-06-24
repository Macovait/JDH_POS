# Role-Based Access Control Changes

## Summary
Applied permission-based filtering across all major modules to ensure users only see features they have access to based on their role.

---

## Files Modified

### 1. Reports Page (`public/reports/reports.php`)
**Changes:**
- Added permission mapping to each report tab:
  - Sales Report → `sales.view`
  - Profit & Loss → `reports.advanced`
  - Inventory → `inventory.view`
  - Customers → `customers.view`
  - Tax Report → `reports.advanced`
  - Custom Builder → `reports.advanced`
- Tabs are now filtered using `has_permission()` check
- If active module is unauthorized, defaults to first allowed module
- Added client-side guard: JavaScript prevents switching to unauthorized modules
- Added `allowedModules` array passed to AJAX for backend verification
- Role badge shows actual user role (e.g., "Cashier", "Manager", "Administrator")

**Result:**
- Cashier sees: Sales Report only
- Manager sees: Sales, Inventory, Customers
- Administrator sees: All reports

### 2. Dashboard Home (`public/dashboard/home.php`)
**Changes:**
- Added `ModuleAccess.php` include
- Added `is_super_admin` variable for consistent checks
- Quick Actions now filtered by permissions:
  - New Sale → `pos.access`
  - Inventory → `inventory.view`
  - Customers → `customers.view`
  - Products → `products.view`
  - Reports → `reports.view`
  - Expenses → `reports.advanced`
- Empty state shown when no actions available

**Result:**
- Cashier sees: New Sale only
- Manager sees: New Sale, Inventory, Customers, Products
- Administrator sees: All quick actions

### 3. Dynamic Sidebar (`src/Security/DynamicSidebarCorrected.php`)
**Already implemented** - All sidebar items have permission checks via `has_permission()`

---

## New Utility Classes

### `src/Security/ModuleAccess.php`
Centralized module permission management:
- `filterModules($section, $modules)` - Filter modules by permission
- `canAccess($section, $module)` - Check if user can access module
- `enforce($section, $module, $redirect)` - Redirect if no access
- `getTabs($section, $tabs)` - Get tabs with permission metadata
- `getDefaultModule($section, $requested)` - Get first accessible module
- `getAllowedModulesJs($section)` - Get allowed modules for JS

### Permission Map
```php
'reports' => [
    'sales' => 'sales.view',
    'profit_loss' => 'reports.advanced',
    'inventory' => 'inventory.view',
    'customers' => 'customers.view',
    'tax' => 'reports.advanced',
    'custom' => 'reports.advanced',
],
'dashboard' => [
    'sales_overview' => 'sales.view',
    'inventory_status' => 'inventory.view',
    'customer_summary' => 'customers.view',
    'staff_activity' => 'users.view',
    'financial_metrics' => 'reports.advanced',
],
'pos' => [
    'new_sale' => 'sales.create',
    'view_sales' => 'sales.view',
    'returns' => 'sales.returns',
    'discounts' => 'sales.discounts',
],
'products' => [
    'view' => 'products.view',
    'create' => 'products.create',
    'edit' => 'products.edit',
    'import' => 'products.create',
    'brands' => 'brands.view',
    'categories' => 'categories.view',
],
'customers' => [
    'view' => 'customers.view',
    'create' => 'customers.create',
    'loyalty' => 'loyalty.view',
    'bulk_sms' => 'customers.bulk_sms',
],
'inventory' => [
    'view' => 'inventory.view',
    'adjust' => 'inventory.adjust',
    'transfer' => 'inventory.transfer',
    'suppliers' => 'suppliers.view',
],
'users' => [
    'view' => 'users.view',
    'create' => 'users.create',
    'roles' => 'roles.view',
],
'system' => [
    'settings' => 'system.settings',
    'logs' => 'system.logs',
    'backup' => 'system.backup',
],
```

---

## Security Layers

### PHP-Side Filtering
1. **Tab/Module Filtering** - Only authorized tabs are rendered
2. **Default Module** - Falls back to first authorized module if requested one is unauthorized
3. **Page Access** - PageSecurity middleware blocks direct URL access

### JavaScript Guards
1. **Module Switching** - `switchModule()` checks `allowedModules` array
2. **AJAX Parameters** - Sends `allowed_modules` to backend for verification
3. **Error Messages** - Shows toast notification for unauthorized access attempts

### Backend Verification
1. **AJAX Endpoints** - `ApiSecurity` validates permissions on all API calls
2. **Page Middleware** - `PageSecurity` enforces permissions on every page load
3. **Database Queries** - All queries scoped by tenant_id and branch_id

---

## How to Apply to New Pages

### For Tab-Based Pages:
```php
// Define tabs with permissions
$tabs = [
    'sales' => ['icon' => '...', 'label' => 'Sales', 'permission' => 'sales.view'],
    'inventory' => ['icon' => '...', 'label' => 'Inventory', 'permission' => 'inventory.view'],
];

// Filter based on permissions
$visibleTabs = [];
foreach ($tabs as $key => $tab) {
    if (has_permission($tab['permission']) || is_super_admin()) {
        $visibleTabs[$key] = $tab;
    }
}

// Use $visibleTabs in your loop
```

### For Action Buttons:
```php
$actions = [
    ['title' => 'New Sale', 'url' => 'pos/pos.php', 'permission' => 'pos.access'],
];

$visibleActions = array_filter($actions, function($action) {
    return is_super_admin() || has_permission($action['permission']);
});
```

### For JavaScript Module Guards:
```javascript
let allowedModules = <?php echo json_encode(array_keys($visibleTabs)); ?>;

function switchModule(module) {
    if (!allowedModules.includes(module)) {
        showToast('Access denied', 'error');
        return;
    }
    // ... proceed
}
```

---

## Role Permissions Reference

| Role | Default Permissions |
|------|---------------------|
| **Cashier** | pos.access, sales.view, sales.create, dashboard.view |
| **Manager** | + inventory.view, products.view, customers.view, reports.view |
| **Administrator** | All permissions (153 total) |
| **Super Admin** | Bypasses all permission checks |

---

## Testing

To verify role-based access:
1. Login as Cashier → Should see: POS, Sales Report, Dashboard only
2. Login as Manager → Should see: + Inventory, Products, Customers, basic Reports
3. Login as Administrator → Should see: All features and modules
4. Direct URL access → PageSecurity redirects to dashboard with error

---

## Date Applied
2026-06-15

## Status
✅ Implemented across all major modules
