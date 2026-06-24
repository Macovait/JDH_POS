# Admin Panel - Architecture & Setup Guide

## Overview

The `admin/` panel is the platform superadmin/developer interface for managing tenants, subscriptions, and system health. All admin pages share a common bootstrap that handles authentication, database preflight checks, and session management.

## 🏗 Architecture

### Entry Point (`admin/bootstrap.php`)

Every admin page includes this file first. It provides:

- **Session management** via the app's secure session helper (`start_session_secure()`)
- **Database connection** via `admin_db()` with graceful fallback
- **Table preflight** via `admin_require_db([...])`
- **Authentication guards** via `admin_require_super_admin()`
- **Schema-aware helpers** for the `admins` table (supports `username` or `email` columns)

**Typical page boilerplate:**

```php
<?php
require_once __DIR__ . '/bootstrap.php';
admin_require_super_admin();
$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_subscriptions']);
```

### Layout System

- **Wrapper start:** `admin/layouts/app.php` — renders the sidebar, header, and opens the main content area.
- **Wrapper end:** `admin/layouts/app_close.php` — closes the layout and includes shared JavaScript.

```php
require_once __DIR__ . '/layouts/app.php';
// page content here
require_once __DIR__ . '/layouts/app_close.php';
```

- **Sidebar:** `admin/components/sidebar.php`
- **Header:** `admin/components/header.php`

## 🔐 Authentication

### Login Flow

1. Superadmin visits `admin/login.php`.
2. Login detects the `admins` table schema and accepts either `username` or `email`.
3. On success, the session stores:
   - `admin_id`
   - `admin_role` (must be `owner`, `admin`, `superadmin`, or `super admin`)
   - `is_super_admin = true`
4. `admin_require_super_admin()` verifies the role whitelist on every page.

### Default Seed

After running the superadmin migration:

- **Email:** `admin@platform.com`
- **Password:** `password`

Run the migration file:
```bash
mysql -u root -p jdh_pos < database/migrations/super_admin_security_schema.sql
```

## 🛡 Database Preflight

All admin pages call `admin_require_db([...])`, which:

1. Checks if MySQL is reachable.
2. Verifies required tables exist.
3. If either fails, renders a graceful error page (HTTP 503) instead of crashing.

**Required admin tables:**
- `admins`
- `pos_tenants`
- `pos_plans`
- `pos_subscriptions`
- `pos_invoices`

## 📁 File Structure

| File | Purpose |
|------|---------|
| `admin/index.php` | Redirects to login or dashboard |
| `admin/login.php` | Superadmin authentication |
| `admin/bootstrap.php` | Shared helpers, DB, auth guards |
| `admin/dashboard.php` | Main dashboard with KPIs |
| `admin/companies.php` | Tenant/company management |
| `admin/subscriptions.php` | Subscription lifecycle |
| `admin/plans.php` | Plan tier management |
| `admin/users.php` | System user management |
| `admin/roles.php` | RBAC configuration |
| `admin/revenue.php` | Revenue & payments |
| `admin/analytics.php` | System analytics |
| `admin/saas-analytics.php` | SaaS metrics |
| `admin/monitoring.php` | Health & DB stats |
| `admin/audit_logs.php` | Audit trail viewer |
| `admin/export.php` | Data export utility |
| `admin/support-access.php` | Support mode management |
| `admin/settings.php` | System configuration |
| `admin/notifications.php` | Admin notifications |
| `admin/reports.php` | Custom reports |

## 🛠 Troubleshooting

### "Admin database unavailable" error page

- Start MySQL in XAMPP.
- Verify `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in your `.env` file.

### "Admin setup incomplete" error page

- Run the superadmin/SaaS migrations to create missing tables (`admins`, `pos_tenants`, etc.).

### Cannot log in

- Verify the `admins` table has a row with `password_hash`.
- Check that the account `role` is `owner`, `admin`, or `superadmin`.

## 📝 Adding New Admin Pages

1. Create a new PHP file in `/admin/`.
2. Include `bootstrap.php` and call `admin_require_super_admin()`.
3. Call `admin_require_db([...])` with the tables you need.
4. Set `$page_title` and `$current_page`.
5. Include `layouts/app.php`, add your content, then include `layouts/app_close.php`.
6. Add the menu item to `components/sidebar.php`.

## 📦 Dependencies

- PHP 8.1+
- MySQL / MariaDB
- TailwindCSS (loaded via CDN)
- Font Awesome 6 (loaded via CDN)

---

**Last Updated:** May 17, 2026  
**Version:** 2.1  
**Status:** In Active Development
