# Super-Admin SaaS Dashboard Refactoring Guide

## Executive Summary

This document outlines the comprehensive refactoring of the existing PHP-based admin panel (`/admin/`) into a production-grade **Multi-Tenant SaaS Super-Admin Dashboard**. The refactoring enforces strict **data isolation**, **Zero-Trust security**, and **auditability** across all administrative functions.

---

## 🔐 Core Security Principles Implemented

### 1. Strict Multi-Tenant Isolation
- **Super-Admin** manages tenant *existence* and *status* only
- **NEVER** accesses private operational data (sales, customers, inventory)
- All queries use **aggregation-only** patterns

### 2. Principle of Least Privilege (PoLP)
- Only users with `owner` or `admin` role can access `/admin/`
- Granular RBAC with permission-based access control
- Session-level security with fingerprint validation

### 3. Zero-Trust Data Access
- All queries validated through `TenantIsolationService`
- Prohibited tables explicitly blocked from Super-Admin context
- Payment references always masked

### 4. Auditability
- Every administrative action triggers immutable audit log entry
- `AuditLogger` service captures: old/new values, changes, IP, user-agent, reason

---

## 📦 Deliverables Summary

### 1. Security Middleware (`SuperAdminMiddleware.php`)

**Location:** `src/Admin/SuperAdminMiddleware.php`

**Features:**
- Intercepts all requests to `/admin/` directory
- Role validation (`owner`, `admin`, `superadmin`)
- Session hardening with IP/user-agent fingerprinting
- CSRF protection with token validation
- Automatic audit logging for all requests
- Configurable session lifetime from database

**Usage:**
```php
require_once __DIR__ . '/../src/Admin/SuperAdminMiddleware.php';

SuperAdminMiddleware::requireAccess();
```

---

### 2. Tenant Isolation Service (`TenantIsolationService.php`)

**Location:** `src/Admin/TenantIsolationService.php`

**Security Pattern - Aggregation Without Exposure:**

| Method | Returns (SAFE) | Never Returns |
|--------|---------------|---------------|
| `getCompanyMetrics()` | counts, sums, averages | individual company data |
| `getRevenueMetrics()` | total revenue, trends | transaction details |
| `getPaymentsSafe()` | masked references, aggregated stats | raw customer data |
| `getCompaniesSafe()` | name, email, status, plan | sales, inventory |

**Query Validation:**
```php
$service->validateSafeQuery($sql);
// Throws SecurityException if query accesses prohibited tables
```

---

### 3. Audit Logging (`AuditLogger.php`)

**Location:** `src/Admin/AuditLogger.php`

**Tracked Actions:**
- **Tenant:** create, update, activate, suspend, delete, export
- **Subscription:** create, update, upgrade, downgrade, cancel, renew
- **Plan:** create, update, delete, publish, archive
- **Payment:** view, refund, dispute, reconcile
- **Support:** grant_access, revoke_access, view_data, modify_data
- **System:** config_update, user_create, user_update, permission_change
- **Auth:** login, logout, password_change, mfa_enable, mfa_disable

**Usage:**
```php
AuditLogger::logTenantAction(
    $adminId, 
    $adminName, 
    'suspend', 
    $companyId,
    ['status' => 'active'],
    ['status' => 'suspended'],
    'Non-payment of subscription'
);
```

---

### 4. Secure Support Access (`SecureSupportAccess.php`)

**Location:** `src/Admin/SecureSupportAccess.php`

**"Break-Glass" Mechanism:**
- Time-limited sessions (default 30 min, max 120 min)
- Access types: `read_only` | `full_access`
- Full audit trail of all actions during support access
- UI indicator for active support mode

**Session Validation:**
```php
if (SecureSupportAccess::isSupportModeActive()) {
    $session = SecureSupportAccess::getSupportSessionInfo();
    // Display warning banner in UI
}
```

---

## 🔧 Refactored Core Files

### Dashboard (`dashboard.php`)

**Refactoring Focus:**
- ✅ Displays ONLY aggregated KPIs (no per-tenant data)
- ✅ Shows: total tenants, MRR, active subscriptions, churn rate
- ✅ Revenue trends via `getRevenueTrend()` (safe aggregation)
- ✅ Active support sessions indicator

**Prohibited Data:**
- ❌ Individual company sales figures
- ❌ Customer lists or transaction details
- ❌ Product inventory or stock levels

---

### Companies Management (`companies.php`)

**Refactoring Focus:**
- ✅ Tenant lifecycle: Create, Activate, Suspend, Delete
- ✅ Plan assignment and subscription tracking
- ✅ Usage limits display (users, branches, products)
- ✅ Audit logging for all status changes

**Security Pattern:**
```php
// SAFE - Only management metadata
$company = $isolationService->getCompanyDetailsSafe($companyId);
// Returns: name, email, status, plan, subscription dates
// Never: sales, customers, transactions
```

---

### Revenue & Payments (`revenue.php`)

**Refactoring Focus:**
- ✅ Aggregated revenue metrics from payments table
- ✅ M-Pesa vs Card breakdown (percentage-based)
- ✅ Masked payment references (first/last 4 chars only)
- ✅ Transaction count, not individual records

**Safe Data Display:**
```php
// SAFE - Aggregated only
$metrics = $isolationService->getRevenueMetrics(30);
// Returns: total_revenue, mpesa_revenue, avg_transaction_value
```

---

## 🗄️ Database Schema

**New Tables:**
1. `admin_audit_log` - Immutable audit trail
2. `support_access_sessions` - Support session tracking
3. `support_access_audit` - Actions during support access
4. `admins` - Platform-level admin users
5. `admin_permissions` - RBAC for admins
6. `tenant_invitations` - Secure tenant onboarding

**Location:** `database/migrations/super_admin_security_schema.sql`

---

## 🚀 Implementation Roadmap

### Phase 1: Security Foundation (Completed)
- [x] SuperAdminMiddleware for access control
- [x] TenantIsolationService for data isolation
- [x] AuditLogger for action tracking
- [x] Database schema for audit tables

### Phase 2: Core Module Refactoring (In Progress)
- [x] Dashboard aggregation-only metrics
- [x] Companies lifecycle management
- [x] Revenue display with masked data
- [x] Support access "break-glass" system

### Phase 3: UI/UX Modernization (Planned)
- [ ] High-density card-based layout (WordPress-inspired)
- [ ] Real-time support mode indicator
- [ ] Responsive design for mobile administration
- [ ] Interactive charts for revenue trends

### Phase 4: System Hardening (Planned)
- [ ] Move unsafe files to `/dev/` directory
- [ ] Implement IP whitelisting for Super-Admin
- [ ] Add MFA requirement for owner role
- [ ] Automated security audit reports

---

## 🔒 Secure Query Patterns

### ✅ SAFE - Aggregation Only
```sql
-- Total companies by status
SELECT status, COUNT(*) FROM companies GROUP BY status;

-- Platform revenue (sum only)
SELECT SUM(amount) FROM payments WHERE status = 'completed';

-- Plan distribution
SELECT sp.name, COUNT(cs.company_id) 
FROM subscription_plans sp 
LEFT JOIN company_subscriptions cs ON ...
```

### ❌ PROHIBITED - Exposes Private Data
```sql
-- BLOCKED: Individual tenant sales
SELECT * FROM sales WHERE company_id = ?;

-- BLOCKED: Customer details
SELECT * FROM customers WHERE company_id = ?;

-- BLOCKED: Inventory levels
SELECT * FROM products WHERE company_id = ?;
```

---

## 📋 Integration Checklist

To fully integrate the refactored system:

1. **Run Migration:**
   ```bash
   mysql -u root -p jakababa_pos < database/migrations/super_admin_security_schema.sql
   ```

2. **Update Admin Pages:**
   - Add `require_super_admin()` at top of each admin page
   - Replace direct DB queries with `TenantIsolationService` methods

3. **Enable Support Access:**
   - Implement visual indicator in header when `is_support_mode_active()`

4. **Test Audit Logging:**
   ```php
   log_admin_action('tenant', 'suspend', $companyId, 'company', 
       ['status' => 'active'], 
       ['status' => 'suspended'], 
       'Non-payment');
   ```

---

## 📞 Security Contacts

For security vulnerabilities or concerns:
- Email: security@platform.com
- Emergency: +254 XXX XXXX

---

*Document Version: 2.0.0*
*Last Updated: 2026-04-19*
*Classification: Internal - Confidential*