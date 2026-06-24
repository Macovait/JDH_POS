# JDH POS - SaaS Multi-Tenant Point of Sale

A complete **SaaS-ready POS system** built with PHP + MySQL. Features multi-tenant architecture with `tenant_id` isolation, modern responsive UI with Tailwind CSS, and comprehensive business management tools.

![Architecture](https://img.shields.io/badge/Architecture-Multi--Tenant-blue)
![Database](https://img.shields.io/badge/Database-MySQL-green)
![Frontend](https://img.shields.io/badge/Frontend-Tailwind%20CSS-purple)
![PHP](https://img.shields.io/badge/PHP-8.0+-orange)

---

## 🏗️ System Architecture

### Multi-Tenant Design

Each tenant (business) operates in complete isolation using **tenant_id** as the ownership model:

- **Master Tenant Table**: `tenants` - Contains all tenant configurations
- **Data Isolation**: Every table has `tenant_id` column with foreign key constraints
- **No Cross-Tenant Data Leak**: SQL queries automatically filtered by tenant context
- **Shared Database**: Single database, multiple isolated tenants (cost-effective SaaS)

### Database Schema

- **146 Tables** covering all POS operations
- **1 View** (`companies` - backward compatibility)
- **Foreign Key Constraints** for data integrity
- **Proper Indexing** for performance

### Key Tables

| Category | Tables |
|----------|--------|
| Core Tenant | tenants, tenant_configs, tenant_subscriptions, tenant_sessions |
| Users & Auth | users, admins, roles, permissions, sessions |
| Business Core | branches, customers, suppliers, customer_groups |
| Products | products, categories, brands, variants, inventory, stock_movements |
| Sales | sales, sale_items, payments, returns, held_sales, discounts, vouchers |
| Purchasing | purchase_orders, purchase_order_items, shipments |
| Loyalty | loyalty_points_log, loyalty_rewards, loyalty_redemptions |
| Expenses | expenses, expense_categories |
| HR | hr_employees, hr_leave_requests |
| Analytics | sales_velocity, smart_alerts, smart_recommendations |
| System | activity_logs, audit_logs, notifications, settings |

---

## 🚀 Quick Start (XAMPP)

### Prerequisites

- XAMPP with PHP 8.0+ and MySQL
- Chrome/Firefox browser
- 500MB disk space

### Installation Steps

#### 1. Database Setup

```bash
# Option A: Automated (recommended)
php scripts/reset_database.php --execute --force

# Option B: Manual MySQL
mysql -u root -e "CREATE DATABASE jdh_pos CHARACTER SET utf8mb4;"
mysql -u root jdh_pos < sql/migrations/clean.sql
```

#### 2. Environment Configuration

```bash
cp .env.example .env
```

Edit `.env`:
```
DB_NAME=jdh_pos
DB_USER=root
DB_PASS=
APP_URL=http://localhost/JDH_POS/public/
```

#### 3. Seed Default Users

```bash
php scripts/seed_default_users.php
```

This creates:
- **Super Admin** (`superadmin` / `admin123`)
- **Demo Tenant** (Demo Store, code: `demo`)
- **Tenant Admin** (`admin` / `admin123`)

#### 4. Access the Application

| Portal | URL | Credentials |
|--------|-----|-------------|
| **Tenant POS** | `http://localhost/JDH_POS/public/auth/login.php` | demo / admin / admin123 |
| **Super Admin** | `http://localhost/JDH_POS/admin/login.php` | superadmin / admin123 |

---

## 📂 Directory Structure

```
JDH_POS/
├── admin/                    # Super Admin Panel
│   ├── login.php            # Admin authentication
│   ├── dashboard.php        # System overview
│   ├── companies.php        # Tenant management
│   └── ...
├── public/                   # Tenant-facing pages
│   ├── auth/
│   │   └── login.php        # Tenant login
│   ├── dashboard/           # Dashboard & reports
│   ├── pos/                 # Point of Sale interface
│   ├── products/            # Product management
│   ├── sales/               # Sales history
│   ├── customers/           # Customer management
│   ├── inventory/           # Stock management
│   └── ajax/                # AJAX endpoints
├── src/                      # Core PHP files
│   ├── auth.php             # Authentication functions
│   ├── db.php               # Database connection
│   ├── functions.php        # Utility functions
│   ├── logger.php           # Activity logging
│   ├── saas.php             # SaaS utilities
│   └── ...
├── api/                      # REST API endpoints
│   ├── products.php
│   ├── categories.php
│   └── ...
├── sql/
│   └── migrations/
│       └── clean.sql        # Complete database schema
├── scripts/                 # Utility scripts
│   ├── reset_database.php   # DB reset & setup
│   ├── seed_default_users.php # Create default logins
│   └── ...
├── .env                     # Environment configuration
└── README.md               # This file
```

---

## 🔐 Default Login Credentials

### Super Admin (System Management)

- **URL**: `http://localhost/JDH_POS/admin/login.php`
- **Username**: `superadmin`
- **Password**: `admin123`
- **Access**: Manage all tenants, billing, plans, system settings

### Tenant Admin (POS System)

- **URL**: `http://localhost/JDH_POS/public/auth/login.php`
- **Tenant Code**: `demo`
- **Username**: `admin`
- **Password**: `admin123`
- **Access**: POS, inventory, sales, reports, customers

⚠️ **Security**: Change these passwords immediately in production!

---

## ✨ Features

### Core POS Features

- ✅ **Quick Sales** - Barcode scanning, product search, hotkeys
- ✅ **Multiple Payment Methods** - Cash, card, mobile money, split payments
- ✅ **Receipt Printing** - Thermal printer support, email receipts
- ✅ **Discounts & Vouchers** - Percentage/fixed discounts, promo codes
- ✅ **Held Sales** - Park and resume transactions
- ✅ **Returns & Refunds** - Process returns with original sale linking
- ✅ **Multi-Branch** - Manage multiple store locations
- ✅ **Offline Mode** - Continue selling when internet is down

### Inventory Management

- ✅ **Stock Tracking** - Real-time inventory levels
- ✅ **Stock Alerts** - Low stock notifications
- ✅ **Purchase Orders** - Manage supplier orders
- ✅ **Stock Transfers** - Move stock between branches
- ✅ **Serial Numbers** - Track individual items
- ✅ **Expiry Dates** - Manage perishable goods
- ✅ **Barcode Labels** - Generate and print labels

### Customer Management

- ✅ **Customer Profiles** - Contact info, purchase history
- ✅ **Loyalty Program** - Points system, rewards
- ✅ **Customer Groups** - VIP, wholesale pricing
- ✅ **Credit Accounts** - Track customer credit

### Reporting & Analytics

- ✅ **Sales Reports** - Daily, weekly, monthly summaries
- ✅ **Inventory Reports** - Stock levels, movement history
- ✅ **Financial Reports** - Profit/loss, cash flow
- ✅ **Staff Reports** - Performance metrics
- ✅ **Export** - PDF, Excel, CSV formats

### SaaS Admin Features

- ✅ **Tenant Management** - Create, suspend, delete tenants
- ✅ **Subscription Plans** - Free, Basic, Professional, Enterprise
- ✅ **Billing & Invoices** - Automated billing, payment tracking
- ✅ **System Analytics** - MRR, churn, growth metrics
- ✅ **Support Tools** - Impersonate tenants, audit logs

---

## 🔧 Configuration

### Environment Variables (.env)

```bash
# Database
DB_HOST=localhost
DB_NAME=jdh_pos
DB_USER=root
DB_PASS=

# Application
APP_NAME="JDH POS"
APP_URL=http://localhost/JDH_POS/public/
APP_ENV=development
APP_DEBUG=true

# Security
ENCRYPTION_KEY=your_random_key_here
SESSION_LIFETIME=7200

# Email (SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password

# Tenant Defaults
DEFAULT_CURRENCY=KES
DEFAULT_TAX_RATE=16
DEFAULT_PLAN_ID=1
```

### Subscription Plans

| Plan | Price | Users | Branches | Products |
|------|-------|-------|----------|----------|
| Free | KES 0 | 2 | 1 | 50 |
| Basic | KES 999 | 5 | 2 | 200 |
| Professional | KES 2,499 | 15 | 5 | 1,000 |
| Enterprise | KES 4,999 | Unlimited | Unlimited | Unlimited |

---

## 🛠️ Development

### Adding a New Tenant Programmatically

```php
require_once 'src/db.php';

$tenantData = [
    'subdomain' => 'mystore',
    'name' => 'My Store',
    'email' => 'owner@mystore.com',
    'business_type' => 'retail',
    'currency' => 'KES',
    'timezone' => 'Africa/Nairobi'
];

// Create tenant
$stmt = $pdo->prepare("INSERT INTO tenants 
    (uuid, subdomain, name, email, business_type, status, is_active, currency, timezone, tax_rate, created_at)
    VALUES (UUID(), ?, ?, ?, ?, 'active', 1, ?, ?, 16.00, NOW())");
$stmt->execute(array_values($tenantData));
$tenantId = $pdo->lastInsertId();

// Create subscription
$stmt = $pdo->prepare("INSERT INTO tenant_subscriptions 
    (tenant_id, plan_id, status, billing_cycle, amount, currency, current_period_start, current_period_end)
    VALUES (?, 2, 'active', 'monthly', 999.00, 'KES', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
$stmt->execute([$tenantId]);
```

### Tenant Context in Code

```php
// Get current tenant ID
$tenant_id = $_SESSION['tenant_id'] ?? 0;

// Query with tenant isolation
$stmt = $pdo->prepare("SELECT * FROM products WHERE tenant_id = ? AND active = 1");
$stmt->execute([$tenant_id]);
$products = $stmt->fetchAll();

// Log activity with tenant context
log_activity('sale_created', 'Sale #' . $sale_id, $meta, $user_id, $tenant_id);
```

---

## 🧪 Testing

### Manual Test Checklist

- [ ] Login as tenant admin
- [ ] Create a sale transaction
- [ ] Apply discount/voucher
- [ ] Process return
- [ ] Check inventory updates
- [ ] Add new product
- [ ] Create purchase order
- [ ] View sales reports
- [ ] Login as super admin
- [ ] Create new tenant
- [ ] Manage subscription

### Load Testing

```bash
# Using Apache Bench
ab -n 1000 -c 10 http://localhost/JDH_POS/public/api/products.php
```

---

## 🚀 Deployment

### Production Checklist

- [ ] Change all default passwords
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Generate strong `ENCRYPTION_KEY`
- [ ] Configure SMTP for email delivery
- [ ] Set up SSL certificate (HTTPS)
- [ ] Configure backup automation
- [ ] Set up monitoring/alerting
- [ ] Review and tighten file permissions
- [ ] Remove `/scripts` from web access
- [ ] Enable MySQL query caching

### Server Requirements

- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite
- 2GB RAM minimum
- SSL certificate (Let's Encrypt recommended)

---

## 📝 API Documentation

### Authentication

All API requests require tenant context:

```http
GET /api/products.php
Headers:
  X-Tenant-ID: 1
  Authorization: Bearer {token}
```

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/products.php` | GET/POST | List/Create products |
| `/api/categories.php` | GET/POST | List/Create categories |
| `/api/sales.php` | POST | Create sale transaction |
| `/api/inventory.php` | GET | Check stock levels |

---

## 🆘 Troubleshooting

### Common Issues

**1. Chrome "Unsafe attempt to load URL" Error**
- Close all Chrome tabs
- Open new tab and type URL directly
- Or try Incognito mode (Ctrl+Shift+N)

**2. Database Connection Failed**
- Check `.env` DB credentials
- Ensure MySQL is running in XAMPP
- Verify database `jdh_pos` exists

**3. 404 Not Found**
- Check `APP_URL` in `.env` matches actual path
- Verify Apache mod_rewrite is enabled
- Check `.htaccess` file exists in `/public`

**4. Session Not Persisting**
- Check PHP session save path is writable
- Verify `session.cookie_domain` matches
- Clear browser cookies

---

## 📄 License

Proprietary - Jakababa POS System

---

## 👥 Support

For technical support:
- Email: support@jakababa.com
- Documentation: `/docs/` folder
- Admin Panel: `http://localhost/JDH_POS/admin/login.php`

---

## 🙏 Acknowledgments

- Tailwind CSS for styling
- Font Awesome for icons
- Chart.js for analytics
- MySQL for database

---

**Built with ❤️ for African businesses**
