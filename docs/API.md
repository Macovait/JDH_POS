# JDH POS API Documentation

**Generated:** 2026-05-04 08:42:56

## Overview

All API endpoints are located in `/public/ajax/` and require authentication unless noted.

**Base URL:** `https://your-domain.com/ajax/`

**Authentication:** Session-based (login required)

---

## Sales

### cart actions

**File:** `cart_actions.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for cart actions (add, update, remove items)

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| action | string | Yes |
| product_id | string | Yes |
| quantity | string | Yes |
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get today sales

**File:** `get_today_sales.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for getting today's sales total

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### hold sale

**File:** `hold_sale.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for holding sales Returns JSON response with sale result

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| action | string | Yes |
| data | string | Yes |
| id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### process sale

**File:** `process_sale.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for processing sales Returns JSON response with sale result Production-ready with schema detection

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

## Products

### check product

**File:** `check_product.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for checking product availability Returns stock status and product details

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| product_id | string | Yes |
| barcode | string | Yes |
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get product by barcode

**File:** `get_product_by_barcode.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX handler to get product by barcode/SKU

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| barcode | string | Yes |
| user_id | string | Yes |
| tenant_id | string | Yes |
| branch_id | string | Yes |
| business_type | string | Yes |
| business_type_id | string | Yes |

**Responses:**

```json
{
    "success": false,
    "error": "string"
}
```

---

### get products

**File:** `get_products.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to get products for POS Returns JSON list of products for the current branch With SaaS multi-tenant support and proper error handling

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| branch_id | string | Yes |
| page | string | Yes |
| limit | string | Yes |
| search | string | Yes |
| category_id | string | Yes |
| debug | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get supplier products

**File:** `get_supplier_products.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to get products from a specific supplier

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| supplier_id | string | Yes |
| search | string | Yes |
| limit | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### product operations

**File:** `product_operations.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for product management operations Handles: toggle_status, bulk_delete, bulk_status, quick_search, get_details

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| action | string | Yes |
| product_id | string | Yes |
| ids | string | Yes |
| status | string | Yes |
| search | string | Yes |
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### search products

**File:** `search_products.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to search products

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| search | string | Yes |
| category_id | string | Yes |
| branch_id | string | Yes |
| limit | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

## Customers

### get customer

**File:** `get_customer.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to get customer by phone number Returns customer details if found

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| phone | string | Yes |
| branch_id | string | Yes |

---

### get customer consent

**File:** `get_customer_consent.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Get Customer Consent List Returns list of customers with their SMS consent status

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| tenant_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get customer history

**File:** `get_customer_history.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Get customer purchase history via AJAX

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| customer_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get customer loyalty

**File:** `get_customer_loyalty.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX handler to get customer loyalty information

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| customer_id | string | Yes |
| sale_total | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

## Users

### get users

**File:** `get_users.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX Endpoint: Get Users Returns list of users for the current company Used for filter dropdowns in activity logs

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

## Reports

### clear old logs

**File:** `clear_old_logs.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX Endpoint: Clear Old Logs Deletes activity logs older than specified days Multi-tenant aware (tenant_id isolation)

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### export activity logs

**File:** `export_activity_logs.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX Endpoint: Export Activity Logs Exports activity logs to CSV format Multi-tenant aware (tenant_id isolation)

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| date_from | string | Yes |
| date_to | string | Yes |
| user_id | string | Yes |
| action | string | Yes |

---

### get activity logs

**File:** `get_activity_logs.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX Endpoint: Get Activity Logs Returns activity logs with filters and pagination Multi-tenant aware (tenant_id isolation)

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| date_from | string | Yes |
| date_to | string | Yes |
| user_id | string | Yes |
| action | string | Yes |
| page | string | Yes |
| limit | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get log details

**File:** `get_log_details.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX Endpoint: Get Log Details Returns detailed information about a specific activity log Multi-tenant aware (tenant_id isolation)

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get report data

**File:** `get_report_data.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for fetching report data Returns JSON data for reports based on filters Enhanced with better error handling and data validation

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| from | string | Yes |
| to | string | Yes |
| branch_id | string | Yes |
| type | string | Yes |
| tenant_id | string | Yes |
| debug | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### log price override

**File:** `log_price_override.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** Price Override Logging Endpoint Logs all manual price overrides for audit purposes

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

## Settings

### get settings

**File:** `get_settings.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to load company settings GET only - returns JSON Multi-tenant: enforces tenant_id isolation

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### save settings

**File:** `save_settings.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to save company settings POST only - returns JSON Multi-tenant: enforces tenant_id isolation

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| csrf_token | string | Yes |
| action | string | Yes |
| company_name | string | Yes |
| company_email | string | Yes |
| company_phone | string | Yes |
| company_address | string | Yes |
| business_type | string | Yes |
| currency | string | Yes |
| timezone | string | Yes |
| date_format | string | Yes |
| time_format | string | Yes |
| tax_rate | string | Yes |
| default_branch_id | string | Yes |
| default_payment_method | string | Yes |
| notify_low_stock | string | Yes |
| low_stock_threshold | string | Yes |
| notify_new_order | string | Yes |
| notify_daily_report | string | Yes |
| daily_report_time | string | Yes |
| email_notifications | string | Yes |
| sms_notifications | string | Yes |
| notify_low_balance | string | Yes |
| invoice_prefix | string | Yes |
| invoice_next_number | string | Yes |
| invoice_footer | string | Yes |
| receipt_width | string | Yes |
| show_logo_on_receipt | string | Yes |
| show_tax_on_receipt | string | Yes |
| show_discount_on_receipt | string | Yes |
| auto_print_receipt | string | Yes |
| tax_display_mode | string | Yes |
| enable_barcode_scanner | string | Yes |
| enable_discounts | string | Yes |
| enable_returns | string | Yes |
| enable_draft_sales | string | Yes |
| allow_negative_stock | string | Yes |
| enable_expiry_tracking | string | Yes |
| default_reorder_level | string | Yes |
| session_timeout | string | Yes |
| require_pin_for_refund | string | Yes |
| enable_loyalty | string | Yes |
| enable_vouchers | string | Yes |
| round_prices | string | Yes |
| auto_backup | string | Yes |
| backup_frequency | string | Yes |
| backup_time | string | Yes |
| backup_retention_days | string | Yes |
| online_store_enabled | string | Yes |
| online_store_url | string | Yes |
| whatsapp_number | string | Yes |
| whatsapp_message | string | Yes |
| meta_title | string | Yes |
| meta_description | string | Yes |
| og_image_url | string | Yes |
| social_facebook | string | Yes |
| social_twitter | string | Yes |
| social_instagram | string | Yes |
| social_tiktok | string | Yes |
| loyalty_points_rate | string | Yes |
| loyalty_redeem_points | string | Yes |
| loyalty_points_value | string | Yes |
| loyalty_expire_days | string | Yes |
| voucher_prefix | string | Yes |
| voucher_length | string | Yes |
| voucher_expire_days | string | Yes |
| voucher_min_spend | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

## System

### ai recommendations

**File:** `ai_recommendations.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AI Smart Recommendations - POS Returns product recommendations based on customer behavior and affinity

**Responses:**

```json
{
    "success": false,
    "error": "string"
}
```

---

### ai track behavior

**File:** `ai_track_behavior.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AI Customer Behavior Tracking - POS Tracks customer purchase behavior for AI recommendations

**Responses:**

```json
{
    "success": false,
    "error": "string"
}
```

---

### apply filters

**File:** `apply_filters.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to apply filters to various data tables Supports filtering for products, sales, customers, etc.

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| filter_type | string | Yes |
| filters | string | Yes |
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### check register

**File:** `check_register.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to check if register is open for current user/branch GET

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### check stock

**File:** `check_stock.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to check product stock availability TENANT ISOLATION: Enforces tenant_id + branch_id filtering GET /ajax/check_stock.php?product_id=123&quantity=1

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| product_id | string | Yes |
| quantity | string | Yes |
| branch_id | string | Yes |
| tenant_id | string | Yes |
| user_id | string | Yes |
| business_type | string | Yes |
| business_type_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### check supplier

**File:** `check_supplier.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to check supplier information

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| supplier_id | string | Yes |
| email | string | Yes |
| phone | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### close register

**File:** `close_register.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to close a register session POST: closing_cash (required), notes (optional)

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### dashboard filters

**File:** `dashboard_filters.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to apply filters to dashboard data

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| start_date | string | Yes |
| end_date | string | Yes |
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### delete purchase item

**File:** `delete_purchase_item.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to delete a purchase order item

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| item_id | string | Yes |
| purchase_order_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### download backup

**File:** `download_backup.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Download backup file Enforces authentication and path traversal protection

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| file | string | Yes |

---

### get csrf token

**File:** `get_csrf_token.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** Get a fresh CSRF token for AJAX requests Used by POS to refresh token before each sale submission

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get dashboard

**File:** `get_dashboard.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** Get Dashboard Stats - AJAX Endpoint GET /ajax/get_dashboard.php Returns POS dashboard statistics scoped by tenant_id + branch_id. Adapts output based on business_type.

---

### get dashboard data

**File:** `get_dashboard_data.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Dashboard Data API — single AJAX endpoint for the full dashboard GET /ajax/get_dashboard_data.php Optional query params: ?tenant_id=X&branch_id=Y Returns: { success: true, kpis: {...}, tables: {...}, charts: {...}, alerts: [...], meta: {...} }

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| tenant_id | string | Yes |
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get purchase draft

**File:** `get_purchase_draft.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to get purchase order draft

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| draft_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get register summary

**File:** `get_register_summary.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for register session summary GET

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| branch_id | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get sms analytics

**File:** `get_sms_analytics.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Get SMS Analytics Returns detailed analytics data for date range

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| tenant_id | string | Yes |
| date_from | string | Yes |
| date_to | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get sms history

**File:** `get_sms_history.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to get SMS history

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| tenant_id | string | Yes |
| limit | string | Yes |
| offset | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### get stock

**File:** `get_stock.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Get Stock - AJAX Endpoint GET /ajax/get_stock.php?product_id=123 Returns stock info for a product at the current branch. Scoped by tenant_id + branch_id.

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| product_id | string | Yes |

---

### open register

**File:** `open_register.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to open a register session POST: opening_cash (required), notes (optional)

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### refresh csrf

**File:** `refresh_csrf.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint for refreshing/validating session Returns current token without regenerating to prevent sync issues

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

---

### save purchase

**File:** `save_purchase.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to save purchase order

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### send sms

**File:** `send_sms.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to send bulk SMS Accepts POST: message, type (all/selected), customer_ids (array) Returns JSON: { status: true/false, sent: n, failed: n, message: "" }

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| csrf_token | string | Yes |
| message | string | Yes |
| type | string | Yes |
| customer_ids | string | Yes |

---

### shift

**File:** `shift.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Shift Management AJAX Handler

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| action | string | Yes |
| branch_id | string | Yes |
| opening_cash | string | Yes |
| closing_cash | string | Yes |
| notes | string | Yes |
| sale_total | string | Yes |
| payment_method | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### switch branch

**File:** `switch_branch.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** Send a JSON response and stop execution.

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| debug | string | Yes |

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### upgrade plan

**File:** `upgrade_plan.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** Jakababa POS - Upgrade Plan AJAX Endpoint This file handles AJAX requests for upgrading subscription plans.

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

### validate voucher

**File:** `validate_voucher.php`

**Method:** `GET/POST`

**Auth Required:** Yes

**Description:** AJAX endpoint to validate voucher codes Returns voucher details if valid

**Parameters:**

| Name | Type | Required |
|------|------|----------|
| code | string | Yes |
| branch_id | string | Yes |

---

### verify admin pin

**File:** `verify_admin_pin.php`

**Method:** `POST`

**Auth Required:** Yes

**Description:** Verify Admin PIN for price overrides

**Responses:**

```json
{
    "success": true,
    "data": "mixed"
}
```

```json
{
    "success": false,
    "error": "string"
}
```

---

## Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Missing or invalid parameters |
| 401 | Unauthorized - Not logged in |
| 403 | Forbidden - No permission |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Rate Limited |
| 500 | Server Error |

## Rate Limiting

API endpoints are rate limited. See `src/RateLimiter.php` for details.

Default limits:
- Login: 5 attempts per 15 minutes
- API: 60 requests per minute

