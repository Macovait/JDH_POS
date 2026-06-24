# JDH POS - Owner Panel Documentation

## Overview

The **Owner Panel** (Super Admin Dashboard) is a professional SaaS control panel designed for managing a multi-tenant POS platform. It provides high-level aggregated insights, tenant management, subscription controls, revenue tracking, and comprehensive audit trails.

## Key Features

### 1. Dashboard (`dashboard.php`)
- **Aggregated SaaS Metrics Only** - No individual company sensitive data
- Real-time statistics: Total Companies, MRR, Monthly Revenue, Churn Rate
- Revenue trends chart (30-day view)
- Active support sessions monitoring
- Quick action shortcuts

### 2. Companies Management (`companies-v2.php`)
- List all tenant companies with filtering and pagination
- View company summary (non-sensitive data only)
- Update company status (Active, Trial, Suspended)
- Change subscription plans with billing cycle options
- Grant support access directly from company detail page
- Create new companies with guided setup

### 3. Subscription Plans (`plans.php`)
- Manage pricing plans with configurable features
- Set limits: Users, Branches, Products, Storage
- Feature matrix configuration per plan
- Trial period management

### 4. Revenue & Payments (`revenue.php`)
- Track all platform payments (M-Pesa, Card, Bank Transfer)
- Payment status monitoring (Completed, Pending, Failed, Refunded)
- Revenue breakdown by payment method
- 30-day revenue trend chart
- Export functionality for financial records

### 5. System Analytics (`analytics.php`)
- **Aggregated platform usage only**
- Total transactions count (platform-wide)
- Peak active users across all tenants
- API call volume tracking
- Average response time monitoring
- Daily breakdown tables
- System health metrics

### 6. Support Access (`support-access.php`)
- Create temporary support sessions for troubleshooting
- Read-only and Full access options
- Time-limited sessions (30min - 4hrs)
- Full audit trail of support actions
- Active session monitoring with revoke capability

### 7. Audit Logs (`audit-logs.php`)
- Complete audit trail of all owner panel actions
- Filter by action type, entity type, date range
- View detailed change logs (old vs new values)
- Export functionality for compliance

### 8. Admin Roles (`roles.php`)
- Role-based access control (RBAC)
- Predefined roles: Super Admin, Support Manager, Support Staff, Finance Manager
- Permission-based module access
- Granular action permissions per role

## Security & Data Privacy

### Multi-Tenant Isolation
- **Strict data isolation** between tenant companies
- Owner panel shows **aggregated data only**
- No access to individual company transactions, customers, or products

### Support Access Control
- Temporary access tokens with time limits
- All actions during support access are logged
- Access types: Read-Only (view only) or Full Access (can modify)
- Immediate revoke capability

### Support Mode Visual Indicator
- Banner appears at top of screen when in support session
- Shows remaining time with countdown timer
- Displays company name and access type
- Color-coded: Blue for Read-Only, Amber/Red for Full Access
- Extend session button (max 2 extensions)
- Quick exit button to return to owner panel
- Automatic redirect when session expires

### Audit Trail
- Every action logged with admin ID, IP address, timestamp
- Before/after values tracked for changes
- Immutable audit logs for compliance

### Permission System
- Role-based access to owner panel modules
- Granular permissions (view, create, edit, delete, manage)
- System roles cannot be deleted

## Database Schema

### New Tables

1. **`admin_roles`** - Owner panel roles with permissions
2. **`support_access_logs`** - Support session tracking
3. **`support_access_actions`** - Detailed action logs during support access
4. **`owner_audit_logs`** - Comprehensive audit trail
5. **`system_analytics`** - Aggregated platform metrics
6. **`platform_payments`** - All payments to the platform
7. **`platform_revenue_summary`** - Daily/Monthly revenue aggregation
8. **`features_catalog`** - Master list of SaaS features
9. **`plan_features`** - Feature matrix for subscription plans
10. **`system_health_metrics`** - Platform health monitoring

### Migration

Run the migration file to create all necessary tables:

```bash
mysql -u root -p jakababa_pos < sql/owner_panel_migration.sql
```

## File Structure

```
admin/
├── dashboard.php          # New Owner Dashboard
├── companies-v2.php       # Enhanced Companies Management
├── support-access.php     # Support Access Management
├── revenue.php            # Revenue & Payments Tracking
├── analytics.php          # System Analytics (Aggregated)
├── audit-logs.php         # Audit Trail
├── roles.php              # Admin Roles (existing enhanced)
├── plans.php              # Subscription Plans (existing enhanced)
├── subscriptions.php      # Subscriptions (existing enhanced)
├── settings.php           # System Settings (existing enhanced)
├── notifications.php      # Notifications (existing)
├── components/
│   ├── sidebar.php        # Updated with new navigation
│   └── header.php         # Header component
└── layouts/
    └── app.php            # Layout with new CSS styles

src/
└── Services/
    └── OwnerPanelService.php  # Core business logic service

sql/
└── owner_panel_migration.sql   # Database migration
```

## Usage

### Accessing the Owner Panel

1. Log in as a Super Admin at `/admin/login.php`
2. Navigate to `/admin/dashboard.php` for the new Owner Dashboard
3. Use the sidebar navigation to access different modules

### Creating a Support Access Session

1. Go to **Companies** → Select a company → Click **Support Access**
2. Or go directly to **Security & Compliance** → **Support Access**
3. Fill in the reason for access
4. Select access type (Read Only or Full Access)
5. Set duration (30min - 4hrs)
6. Click **Grant Access**

### Entering a Support Session

1. After creating support access, you'll receive a unique token
2. Go to **Security & Compliance** → **Enter Support Session**
3. Enter your access token and click **Enter Support Session**
4. Or use **Quick Entry** to select from your active sessions
5. The support mode banner will appear at the top of the screen

### During Support Mode

- **Visual Banner**: Blue banner for Read-Only, Amber/Red for Full Access
- **Timer**: Shows remaining session time with countdown
- **Extend**: Click "+15 min" to extend session (max 2 times)
- **Exit**: Click "Exit" to return to owner panel and end session
- **Auto-redirect**: You'll be redirected back when session expires

### Managing Subscriptions

1. Go to **Tenant Management** → **Companies**
2. Click on a company to view details
3. In the **Subscription** section, use the **Change Plan** form
4. Select new plan and billing cycle
5. Click **Update**

### Viewing Audit Logs

1. Go to **Security & Compliance** → **Audit Logs**
2. Use filters to narrow down results
3. Click **View** on any log entry to see detailed changes
4. Export logs for compliance reporting

## API Integration

The `OwnerPanelService` class provides a clean API for all operations:

```php
use JDH_POS\Services\OwnerPanelService;

$ownerService = new OwnerPanelService($pdo, $adminId);

// Get dashboard stats
$stats = $ownerService->getDashboardStats();

// Get companies with filters
$companies = $ownerService->getCompanies(['status' => 'active'], 1, 20);

// Create support access
$access = $ownerService->createSupportAccess($companyId, $reason, 'read_only', 60);

// Update company plan
$ownerService->changeCompanyPlan($companyId, $newPlanId, 'monthly');

// Check permissions
if ($ownerService->hasPermission('companies', 'edit')) {
    // Allow edit action
}
```

## Configuration

### Default Roles

- **Super Admin**: Full system access
- **Support Manager**: Can view companies, create support access, view audit logs
- **Support Staff**: Limited access for basic troubleshooting
- **Finance Manager**: Revenue and payment management only

### Customization

Edit `src/Services/OwnerPanelService.php` to modify:
- Permission checks
- Data aggregation queries
- Feature catalog

## Support

For technical support or feature requests, contact the development team.

---

**Version**: 1.0.0  
**Last Updated**: 2026-04-16  
**Compatible With**: JDH POS Multi-Tenant SaaS Platform
