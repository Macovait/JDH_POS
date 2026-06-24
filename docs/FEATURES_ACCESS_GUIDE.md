# New Features Integration Guide

## Accessing the Blue Ocean Features

This guide explains how to access all the newly implemented features in JDH POS.

---

## 1. Edge POS (Offline Mode)

**Location:** `src/POS/EdgePOSService.php`

**How to Access:**

```php
// In any POS file, check connection status
$edge = get_edge_pos();

// Check if online
if (is_pos_online()) {
    echo "Connected";
} else {
    echo "Working offline - changes will sync when online";
}

// Get connection details
$status = get_pos_connection_status();
// Returns: is_online, last_check, pending_sync, last_sync
```

**Features Available:**
- Local SQLite database for offline transactions
- Automatic sync queue when back online
- Conflict resolution for data collisions

---

## 2. AI Demand Forecasting

**Location:** `src/Intelligence/DemandForecastingService.php`

**How to Access:**

```php
// Forecast demand for a single product
$forecast = forecast_demand($productId, $branchId = null, $days = 30);

// Returns:
// - predicted daily sales for next 30 days
// - confidence level (0-1)
// - recommendation: 'reorder', 'review', or 'monitor'
// - suggested quantity

// Forecast ALL products needing reorder
$all = forecast_all_products($tenantId, $days = 30);
// Returns products that need reordering
```

**Access via Admin:**
- Navigate to: `admin/analytics.php` (when integrated)
- Or add to any page:

```php
require_once 'src/Intelligence/DemandForecastingService.php';
$forecast = forecast_demand(1);
```

---

## 3. CLV Prediction & Customer Segmentation

**Location:** `src/Intelligence/CLVPredictionService.php`

**How to Access:**

```php
// Predict customer lifetime value
$clv = predict_clv($customerId);

// Returns:
// - clv: predicted lifetime value
// - segment: 'vip', 'high', 'medium', 'low', 'churned'
// - churn_risk: 0-1 probability
// - confidence: prediction confidence
// - factors: purchase history analysis
```

**Customer Segments:**
- **VIP:** CLV > KSh 50,000
- **High:** CLV > KSh 10,000
- **Medium:** CLV > KSh 1,000
- **Low:** CLV < KSh 1,000
- **Churned:** Inactive > 2x average purchase interval

---

## 4. Unified Loyalty Platform

**Location:** `src/Intelligence/CLVPredictionService.php` (UnifiedLoyaltyService)

**How to Access:**

```php
// Get unified customer profile (phone, email, or loyalty card)
$customer = get_unified_customer_profile('+254700000000');

// Returns:
// - loyalty_points
// - tier: bronze, silver, gold, platinum
// - purchase_count
// - total_spent
// - preferences (clienteling notes)
```

**Tiers:**
| Tier | Points Required |
|------|----------------|
| Bronze | 0 - 4,999 |
| Silver | 5,000 - 19,999 |
| Gold | 20,000 - 49,999 |
| Platinum | 50,000+ |

---

## 5. Vendor PO Automation

**Location:** `src/Operations/VendorAutomationService.php`

**How to Access:**

```php
// Generate automatic purchase orders
$pos = generate_vendor_pos($companyId, $branchId = null);

// Returns array of POs grouped by vendor
// Each PO includes:
// - suggested items to reorder
// - quantities based on forecast
// - total cost
// - expected delivery date
```

**Features:**
- Auto-generates POs when stock hits reorder point
- Vendor scoring (fulfillment rate, lead time, quality)
- Optimal reorder point calculation

---

## 6. Payment Reconciliation

**Location:** `src/Operations/VendorAutomationService.php` (ReconciliationService)

**How to Access:**

```php
// Reconcile payments for a specific date
$result = reconcile_payments($companyId, '2026-04-19');

// Returns:
// - matched: transactions matched between gateway and bank
// - unmatched_gateway: payments not found in bank
// - unmatched_bank: bank entries not in gateway
// - variance: total difference
```

---

## 7. Vertical Features (Fine Dining, Pharmacy, Retail)

**Location:** `src/Vertical/VerticalFeatureRegistry.php`

**How to Access:**

```php
// Check if a feature is enabled
if (has_vertical_feature('table_management')) {
    // Fine dining mode is active
}

// Check pharmacy compliance
if (vertical_check_rxndrug($productId, $customerHistory)) {
    // Sale allowed
} else {
    // Prescription required
}
```

**Available Verticals:**

### Fine Dining
- `table_management` - Floor plan tables
- `course_timing` - Multi-course control
- `split_by_item` - Split bill by item
- `staff_sections` - Server sections
- `table_reservation` - Booking system

### Pharmacy
- `rxndrug_check` - Controlled substance validation
- `prescription_log` - Prescription tracking
- `batch_expiry` - Expiry alerts
- `narco_compliance` - Narcotics compliance

### High Retail
- `consignment` - Consignment tracking
- `commission_tiers` - Multi-level commission
- `layaway` - Payment plans
- `gift_registry` - Wedding/baby registries

---

## Quick Integration Examples

### Add to POS Interface (pos.php)

```php
<?php
// At top of file
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

// Check offline status
$offlineStatus = get_pos_connection_status();
$isOnline = $offlineStatus['is_online'] ?? true;
?>

<!-- In your HTML -->
<?php if (!$isOnline): ?>
<div class="offline-banner">
    ⚠️ You're offline. Changes will sync when connected.
    Pending: <?= $offlineStatus['pending_sync'] ?> transactions
</div>
<?php endif; ?>
```

### Add Demand Forecast to Inventory

```php
<?php
require_once 'src/Intelligence/DemandForecastingService.php';

// Get forecast for low-stock product
$productId = $_GET['product_id'] ?? 0;
$forecast = forecast_demand($productId);

if ($forecast['recommendation']['action'] === 'reorder'): ?>
    <div class="alert alert-warning">
        🔔 Reorder Recommended: 
        Order <?= $forecast['recommendation']['suggested_quantity'] ?> units
    </div>
<?php endif; ?>
```

### Add CLV to Customer View

```php
<?php
require_once 'src/Intelligence/CLVPredictionService.php';

$customerId = $_GET['customer_id'];
$clv = predict_clv($customerId);
?>

<div class="customer-segments">
    <span class="badge badge-<?= $clv['segment'] ?>">
        <?= strtoupper($clv['segment']) ?>
    </span>
    <span>CLV: KSh <?= number_format($clv['clv']) ?></span>
    <span>Churn Risk: <?= ($clv['churn_risk'] * 100) ?>%</span>
</div>
```

---

## Database Tables Required

Run the migration to create necessary tables:

```sql
-- Add to your database
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    points INT NOT NULL,
    type ENUM('earned', 'redeemed', 'expired') NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS company_verticals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    vertical_type VARCHAR(50) NOT NULL,
    features_json JSON,
    active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS product_forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    forecast_date DATE NOT NULL,
    predicted_daily_avg DECIMAL(10,2),
    confidence DECIMAL(3,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Support

For issues or questions about these features:
- Check the main documentation: `docs/POS_GAP_ANALYSIS.md`
- Review service source code in `src/` directory
- Contact development team