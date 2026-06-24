# Admin System Pattern

## Overview

The admin panel now follows the **same system patterns as the rest of the application** (public/ folder). This eliminates custom bootstrap code and ensures consistency across the codebase.

## Architecture

All admin files use this clean initialization pattern:

```php
<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

start_session_secure();

if (empty($_SESSION['admin_id']) || !is_super_admin()) {
    http_response_code(403);
    die('Access denied. Super admin only.');
}

$pdo = get_db_connection();
```

## Files Included

- ✅ **index.php** - Dashboard with system statistics
- ✅ **login.php** - Admin authentication (isolated, no bootstrap loading)
- ✅ **companies.php** - Company/tenant management
- ✅ **users.php** - User management
- ✅ **plans.php** - Subscription plans management
- ✅ **roles.php** - Role and permission management
- ✅ **settings.php** - Global system settings
- ✅ **notifications.php** - System notifications management
- ✅ **reports.php** - Analytics and reporting
- ✅ **subscriptions.php** - Company subscriptions management

## Key Functions Used

All admin pages use these core functions loaded via safe_require:

- `start_session_secure()` - Session initialization with security
- `get_db_connection()` - Database connection
- `is_super_admin()` - Admin role verification
- `db_fetch_one()` - Single row query
- `db_fetch_all()` - Multiple rows query
- Standard CSRF token generation and validation

## Removed Files

The following problematic files were completely removed:

- ❌ `_bootstrap.php` - Custom bootstrap (caused fatal error)
- ❌ `utils.php` - Custom utilities (incompatible with safe_require)
- ❌ `layout.php` - Template (unused, files use HTML directly)
- ❌ `components/` directory (unused)
- ❌ All test files (test*\*.php, debug*\*.php)

## Login Flow

1. User accesses `/admin/login.php`
2. Form submits with credentials
3. System creates admin session with `$_SESSION['admin_id']` and `$_SESSION['is_super_admin']`
4. Redirect to admin index.php
5. Index and other pages verify session via `start_session_secure()` and `is_super_admin()`

## Testing

```bash
# Login page should load
curl http://localhost/JDH_POS/admin/login.php

# Accessing other pages without login redirects to login
curl http://localhost/JDH_POS/admin/index.php
# → HTTP 302 redirect to login.php

# With valid admin session, dashboard loads
# Default credentials: admin / admin123 (created via admin_setup.php)
```

## Why This Works Better

1. **Consistency** - Matches patterns used throughout public/ folder
2. **No custom bootstrap** - Uses system's init.php infrastructure
3. **Simpler debugging** - Same debugging as other pages
4. **No utility complications** - Direct function calls from safe_require
5. **Standard security** - Session validation via start_session_secure()
6. **Maintainability** - Admin pages follow same conventions as company-level pages
