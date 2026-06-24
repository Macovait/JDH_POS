# Route Protection Examples

This document provides examples of how to use the middleware functions for route protection in the Jakababa POS system.

## Table of Contents

1. [Admin Routes](#admin-routes)
2. [Tenant Routes](#tenant-routes)
3. [Feature-Based Protection](#feature-based-protection)
4. [AJAX Endpoints](#ajax-endpoints)
5. [Mixed Access Routes](#mixed-access-routes)

---

## Admin Routes

All admin routes should use `requireAdmin()` middleware to ensure only system administrators can access them.

### Example: Admin Dashboard

```php
<?php
// admin/index.php
require_once __DIR__ . '/../src/middleware.php';

// Require admin authentication
requireAdmin();

// Your admin dashboard code here
$pdo = get_db_connection();
// ... rest of the code
?>
```

### Example: Admin Companies Management

```php
<?php
// admin/companies.php
require_once __DIR__ . '/../src/middleware.php';

// Require admin authentication
requireAdmin();

// Your companies management code here
// ... rest of the code
?>
```

### Example: Admin Plans Management

```php
<?php
// admin/plans.php
require_once __DIR__ . '/../src/middleware.php';

// Require admin authentication
requireAdmin();

// Your plans management code here
// ... rest of the code
?>
```

---

## Tenant Routes

All tenant routes should use `requireTenant()` middleware to ensure only authenticated company users can access them.

### Example: Tenant Dashboard

```php
<?php
// public/dashboard/home.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Your tenant dashboard code here
$company_id = get_current_company_id();
$user_id = get_current_user_id();
// ... rest of the code
?>
```

### Example: POS System

```php
<?php
// public/pos/pos.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Your POS code here
// ... rest of the code
?>
```

### Example: Products Management

```php
<?php
// public/products/products.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Your products management code here
// ... rest of the code
?>
```

---

## Feature-Based Protection

Use `requireFeature()` middleware to protect routes based on subscription plan features.

### Example: Reports Page (Requires reports feature)

```php
<?php
// public/reports/reports.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Require reports feature
requireFeature('reports_view');

// Your reports code here
// ... rest of the code
?>
```

### Example: Multi-Branch Management (Requires multi_branch feature)

```php
<?php
// public/branches/branches.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Require multi-branch feature
requireFeature('multi_branch');

// Your branches management code here
// ... rest of the code
?>
```

### Example: M-Pesa Integration (Requires mpesa_integration feature)

```php
<?php
// public/payments/mpesa.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Require M-Pesa integration feature
requireFeature('mpesa_integration');

// Your M-Pesa integration code here
// ... rest of the code
?>
```

### Example: Multiple Features (Any of the features)

```php
<?php
// public/reports/advanced.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Require any of the advanced report features
requireAnyFeature(['reports_advanced', 'reports_export']);

// Your advanced reports code here
// ... rest of the code
?>
```

### Example: Multiple Features (All of the features)

```php
<?php
// public/analytics/dashboard.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Require all analytics features
requireAllFeatures(['reports_advanced', 'reports_export', 'inventory_alerts']);

// Your analytics dashboard code here
// ... rest of the code
?>
```

---

## AJAX Endpoints

AJAX endpoints should use the AJAX security middleware functions for proper authentication and validation.

### Example: Get Products AJAX

```php
<?php
// public/ajax/get_products.php
require_once __DIR__ . '/../../src/middleware.php';

// Require AJAX authentication
require_ajax_auth();

// Validate company_id
$company_id = $_GET['company_id'] ?? null;
if ($company_id) {
    validate_ajax_company($company_id);
}

// Rate limiting
ajax_rate_limit('get_products', 100, 60);

// Your AJAX code here
try {
    $products = db_fetch_all("
        SELECT * FROM products
        WHERE company_id = ? AND deleted_at IS NULL
    ", [$company_id]);

    json_success($products, 'Products retrieved successfully');
} catch (Exception $e) {
    json_error('Failed to retrieve products', 500, 'DATABASE_ERROR');
}
?>
```

### Example: Process Sale AJAX (POST)

```php
<?php
// public/ajax/process_sale.php
require_once __DIR__ . '/../../src/middleware.php';

// Require AJAX authentication
require_ajax_auth();

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
require_ajax_csrf($csrf_token);

// Validate company_id
$company_id = $_POST['company_id'] ?? null;
if ($company_id) {
    validate_ajax_company($company_id);
}

// Rate limiting
ajax_rate_limit('process_sale', 30, 60);

// Your AJAX code here
try {
    $input = get_json_input();

    // Process sale logic here
    // ...

    json_success($sale_data, 'Sale processed successfully');
} catch (Exception $e) {
    json_error('Failed to process sale', 500, 'PROCESSING_ERROR');
}
?>
```

### Example: Admin AJAX Endpoint

```php
<?php
// admin/ajax/get_companies.php
require_once __DIR__ . '/../../src/middleware.php';

// Require admin AJAX authentication
require_ajax_auth(true);

// Rate limiting
ajax_rate_limit('admin_get_companies', 60, 60);

// Your AJAX code here
try {
    $companies = db_fetch_all("
        SELECT * FROM companies
        WHERE deleted_at IS NULL
        ORDER BY created_at DESC
    ");

    json_success($companies, 'Companies retrieved successfully');
} catch (Exception $e) {
    json_error('Failed to retrieve companies', 500, 'DATABASE_ERROR');
}
?>
```

---

## Mixed Access Routes

Some routes may need to be accessible by both admins and tenants, but with different permissions.

### Example: User Profile (Tenant only)

```php
<?php
// public/dashboard/profile.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Your profile code here
$user_id = get_current_user_id();
$company_id = get_current_company_id();

// Users can only view/edit their own profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_user_id = $_POST['user_id'] ?? null;
    if ((int) $posted_user_id !== (int) $user_id) {
        // User trying to edit another user's profile
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

// ... rest of the code
?>
```

### Example: Company Settings (Tenant with feature check)

```php
<?php
// public/dashboard/settings.php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Check if user has permission to manage settings
if (!check_permission('manage_settings')) {
    http_response_code(403);
    echo 'Access denied: Insufficient permissions';
    exit;
}

// Your settings code here
// ... rest of the code
?>
```

---

## Best Practices

### 1. Always Use Middleware

```php
<?php
// GOOD: Always use middleware at the top of your file
require_once __DIR__ . '/../../src/middleware.php';
requireTenant();

// BAD: Don't check authentication manually
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}
?>
```

### 2. Use Feature-Based Protection

```php
<?php
// GOOD: Use feature-based protection
requireFeature('reports_view');

// BAD: Don't hardcode feature checks
if ($user_role !== 'admin') {
    echo 'Access denied';
    exit;
}
?>
```

### 3. Validate Company Context

```php
<?php
// GOOD: Always validate company context
$company_id = get_current_company_id();
if (!$company_id) {
    header('Location: /public/auth/login.php');
    exit;
}

// BAD: Don't trust user input for company_id
$company_id = $_GET['company_id']; // Dangerous!
?>
```

### 4. Use CSRF Protection for Forms

```php
<?php
// GOOD: Use CSRF protection
<form method="POST">
    <?= csrf_field() ?>
    <!-- form fields -->
</form>

// BAD: Don't forget CSRF protection
<form method="POST">
    <!-- form fields -->
</form>
?>
```

### 5. Use AJAX Security Functions

```php
<?php
// GOOD: Use AJAX security functions
require_ajax_auth();
require_ajax_csrf($_POST['csrf_token']);
validate_ajax_company($_POST['company_id']);

// BAD: Don't skip AJAX security
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}
?>
```

---

## Common Patterns

### Pattern 1: Admin + Feature Check

```php
<?php
require_once __DIR__ . '/../src/middleware.php';

// Require admin authentication
requireAdmin();

// Check if admin has specific permission
if (!check_permission('manage_plans')) {
    http_response_code(403);
    echo 'Access denied: Insufficient permissions';
    exit;
}

// Your code here
?>
```

### Pattern 2: Tenant + Feature Check

```php
<?php
require_once __DIR__ . '/../../src/middleware.php';

// Require tenant authentication
requireTenant();

// Require specific feature
requireFeature('inventory_management');

// Your code here
?>
```

### Pattern 3: AJAX + Authentication + CSRF

```php
<?php
require_once __DIR__ . '/../../src/middleware.php';

// Require AJAX authentication
require_ajax_auth();

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
require_ajax_csrf($csrf_token);

// Validate company_id
$company_id = $_POST['company_id'] ?? null;
validate_ajax_company($company_id);

// Rate limiting
ajax_rate_limit('action_name', 60, 60);

// Your AJAX code here
?>
```

---

## Troubleshooting

### Issue: "Access denied" error

**Solution:** Make sure you're using the correct middleware function:

- Use `requireAdmin()` for admin routes
- Use `requireTenant()` for tenant routes

### Issue: "Feature locked" error

**Solution:** Check if the company's subscription plan includes the required feature:

```php
<?php
if (hasFeature($company_id, 'feature_key')) {
    // Feature is available
} else {
    // Feature is locked
    header('Location: /public/upgrade.php?feature=feature_key');
    exit;
}
?>
```

### Issue: "CSRF token invalid" error

**Solution:** Make sure you're generating and including the CSRF token in your form:

```php
<?php
// Generate token
$csrf_token = csrf_token();

// Include in form
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <!-- other fields -->
</form>
?>
```

### Issue: "Company access denied" error

**Solution:** Make sure the company_id in the request matches the company_id in the session:

```php
<?php
$session_company_id = get_current_company_id();
$request_company_id = $_POST['company_id'] ?? null;

if ((int) $request_company_id !== (int) $session_company_id) {
    // Access denied
}
?>
```

---

## Summary

| Route Type      | Middleware                | Example                        |
| --------------- | ------------------------- | ------------------------------ |
| Admin routes    | `requireAdmin()`          | `admin/index.php`              |
| Tenant routes   | `requireTenant()`         | `public/dashboard/home.php`    |
| Feature-based   | `requireFeature()`        | `public/reports/reports.php`   |
| AJAX tenant     | `require_ajax_auth()`     | `public/ajax/get_products.php` |
| AJAX admin      | `require_ajax_auth(true)` | `admin/ajax/get_companies.php` |
| CSRF protection | `require_ajax_csrf()`     | AJAX POST endpoints            |

Always use the appropriate middleware function for your route type to ensure proper security and access control.
