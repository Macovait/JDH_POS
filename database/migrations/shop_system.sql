-- =====================================================
-- JAKABABA SMART SYSTEM — E-Commerce Extension Migration
-- POS-integrated, Multi-tenant SaaS
-- DO NOT modify existing POS tables
-- =====================================================
-- Run: mysql -u root -p jdh_pos < shop_system.sql
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. INVENTORY RESERVATIONS (Prevent Overselling)
-- =====================================================
CREATE TABLE IF NOT EXISTS inventory_reservations (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    branch_id       INT NOT NULL DEFAULT 1,
    cart_id         BIGINT UNSIGNED NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    expires_at      DATETIME NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_product (tenant_id, product_id),
    INDEX idx_expires (expires_at),
    INDEX idx_cart (cart_id),
    INDEX idx_tenant_branch (tenant_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Temporary stock holds for online carts';

-- =====================================================
-- 2. STOCK SYNC LOG (Audit Trail)
-- =====================================================
CREATE TABLE IF NOT EXISTS stock_sync_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    branch_id       INT NOT NULL DEFAULT 1,
    event_type      VARCHAR(30) NOT NULL COMMENT 'sale,return,adjustment,reservation,release',
    old_value       INT DEFAULT NULL,
    new_value       INT DEFAULT NULL,
    delta           INT NOT NULL DEFAULT 0,
    source          VARCHAR(30) NOT NULL COMMENT 'pos,online,cron,manual',
    source_id       VARCHAR(100) DEFAULT NULL COMMENT 'sale_id,order_id,cart_id',
    synced_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed       TINYINT(1) DEFAULT 0,
    processed_at    DATETIME DEFAULT NULL,

    INDEX idx_tenant_product (tenant_id, product_id),
    INDEX idx_event_type (event_type),
    INDEX idx_synced_at (synced_at),
    INDEX idx_processed (tenant_id, processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for POS ↔ Online stock sync';

-- =====================================================
-- 3. STOREFRONT SETTINGS (Tenant-specific store config)
-- =====================================================
CREATE TABLE IF NOT EXISTS storefront_settings (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    setting_key     VARCHAR(100) NOT NULL,
    setting_value   LONGTEXT DEFAULT NULL,
    data_type       ENUM('text','number','boolean','json','email','url','color') DEFAULT 'text',
    category        VARCHAR(50) DEFAULT 'general' COMMENT 'general,seo,design,payment,social',
    is_public       TINYINT(1) DEFAULT 1 COMMENT 'Expose to storefront API?',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_tenant_key (tenant_id, setting_key),
    INDEX idx_category (tenant_id, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-tenant storefront configuration';

-- Seed default storefront settings
INSERT INTO storefront_settings (tenant_id, setting_key, setting_value, data_type, category) VALUES
(1, 'site_title', 'Jakababa Online Store', 'text', 'general'),
(1, 'site_description', 'Shop the best deals online', 'text', 'seo'),
(1, 'logo_url', '', 'url', 'design'),
(1, 'favicon_url', '', 'url', 'design'),
(1, 'primary_color', '#f68b1e', 'color', 'design'),
(1, 'secondary_color', '#1a1a2e', 'color', 'design'),
(1, 'currency', 'KES', 'text', 'general'),
(1, 'whatsapp_number', '', 'text', 'social'),
(1, 'facebook_url', '', 'url', 'social'),
(1, 'instagram_url', '', 'url', 'social'),
(1, 'show_reviews', '1', 'boolean', 'general'),
(1, 'show_stock_count', '0', 'boolean', 'general'),
(1, 'min_order_amount', '0', 'number', 'general'),
(1, 'free_shipping_threshold', '0', 'number', 'general'),
(1, 'mpesa_enabled', '1', 'boolean', 'payment'),
(1, 'cod_enabled', '1', 'boolean', 'payment'),
(1, 'stripe_enabled', '0', 'boolean', 'payment'),
(1, 'stripe_publishable_key', '', 'text', 'payment'),
(1, 'meta_title', 'Jakababa - Online Shopping', 'text', 'seo'),
(1, 'meta_description', 'Shop online at Jakababa. Best prices, fast delivery.', 'text', 'seo');

-- =====================================================
-- 4. CUSTOMER ADDRESSES (Shipping & Billing)
-- =====================================================
CREATE TABLE IF NOT EXISTS customer_addresses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    customer_id     INT NOT NULL,
    type            ENUM('shipping','billing') DEFAULT 'shipping',
    label           VARCHAR(50) DEFAULT 'Home' COMMENT 'Home, Office, Other',
    full_name       VARCHAR(255) NOT NULL,
    phone           VARCHAR(20) NOT NULL,
    email           VARCHAR(255) DEFAULT NULL,
    street_address  VARCHAR(255) NOT NULL,
    apartment       VARCHAR(50) DEFAULT NULL,
    city            VARCHAR(100) NOT NULL,
    state           VARCHAR(100) DEFAULT NULL,
    postal_code     VARCHAR(20) DEFAULT NULL,
    country         VARCHAR(100) DEFAULT 'Kenya',
    is_default      TINYINT(1) DEFAULT 0,
    latitude        DECIMAL(10,8) DEFAULT NULL,
    longitude       DECIMAL(11,8) DEFAULT NULL,
    delivery_notes  TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_customer (tenant_id, customer_id),
    INDEX idx_type (tenant_id, customer_id, type),
    INDEX idx_default (tenant_id, customer_id, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Customer shipping and billing addresses';

-- =====================================================
-- 5. SHIPPING ZONES (Delivery regions)
-- =====================================================
CREATE TABLE IF NOT EXISTS shipping_zones (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL COMMENT 'e.g. Nairobi, Rest of Kenya',
    description     VARCHAR(255) DEFAULT NULL,
    countries       JSON DEFAULT NULL COMMENT 'List of country codes',
    regions         JSON DEFAULT NULL COMMENT 'List of regions/states',
    cities          JSON DEFAULT NULL COMMENT 'List of city names',
    postal_codes    JSON DEFAULT NULL COMMENT 'List of postal code ranges',
    is_active       TINYINT(1) DEFAULT 1,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_active (tenant_id, is_active),
    INDEX idx_sort (tenant_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Geographic delivery zones';

-- =====================================================
-- 6. SHIPPING RATES (Pricing per zone)
-- =====================================================
CREATE TABLE IF NOT EXISTS shipping_rates (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    zone_id         BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL COMMENT 'e.g. Standard, Express',
    min_weight      DECIMAL(10,3) DEFAULT 0.000,
    max_weight      DECIMAL(10,3) DEFAULT 999.000,
    min_order_value DECIMAL(10,2) DEFAULT 0.00,
    rate            DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Flat rate or base rate',
    per_kg_rate     DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Additional per kg',
    free_shipping   TINYINT(1) DEFAULT 0,
    estimated_days  INT DEFAULT 3 COMMENT 'Delivery ETA in days',
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_zone (zone_id),
    INDEX idx_tenant_active (tenant_id, is_active),
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Shipping pricing rules per zone';

-- =====================================================
-- 7. CARTS (Shopping sessions)
-- =====================================================
CREATE TABLE IF NOT EXISTS carts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    session_id      VARCHAR(255) NOT NULL COMMENT 'PHP session or guest token',
    customer_id     INT DEFAULT NULL,
    coupon_code     VARCHAR(50) DEFAULT NULL,
    coupon_discount DECIMAL(10,2) DEFAULT 0.00,
    subtotal        DECIMAL(10,2) DEFAULT 0.00,
    shipping_cost   DECIMAL(10,2) DEFAULT 0.00,
    tax_amount      DECIMAL(10,2) DEFAULT 0.00,
    total           DECIMAL(10,2) DEFAULT 0.00,
    currency        VARCHAR(10) DEFAULT 'KES',
    shipping_address_id BIGINT UNSIGNED DEFAULT NULL,
    billing_address_id BIGINT UNSIGNED DEFAULT NULL,
    notes           TEXT DEFAULT NULL,
    abandoned_notified_at DATETIME DEFAULT NULL,
    converted_to_order_id BIGINT UNSIGNED DEFAULT NULL,
    last_activity   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_session (tenant_id, session_id),
    INDEX idx_customer (tenant_id, customer_id),
    INDEX idx_abandoned (tenant_id, abandoned_notified_at, last_activity),
    INDEX idx_converted (converted_to_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active and abandoned shopping carts';

-- =====================================================
-- 8. CART ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS cart_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    cart_id         BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    variant_id      INT DEFAULT NULL COMMENT 'Future: product variants',
    quantity        INT NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL,
    original_price  DECIMAL(10,2) DEFAULT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    weight_kg       DECIMAL(10,3) DEFAULT 0.000,
    image_url       VARCHAR(500) DEFAULT NULL,
    product_name    VARCHAR(255) NOT NULL COMMENT 'Snapshot at add time',
    product_sku     VARCHAR(100) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cart (cart_id),
    INDEX idx_product (tenant_id, product_id),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Items in shopping carts';

-- =====================================================
-- 9. ABANDONED CARTS (Recovery tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS abandoned_carts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    cart_id         BIGINT UNSIGNED NOT NULL,
    customer_email  VARCHAR(255) DEFAULT NULL,
    customer_phone  VARCHAR(20) DEFAULT NULL,
    items_count     INT DEFAULT 0,
    cart_value      DECIMAL(10,2) DEFAULT 0.00,
    abandoned_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recovered_at    DATETIME DEFAULT NULL,
    recovery_email_sent_at DATETIME DEFAULT NULL,
    recovery_email_opens INT DEFAULT 0,
    recovery_email_clicks INT DEFAULT 0,
    recovery_discount_code VARCHAR(50) DEFAULT NULL,
    status          ENUM('abandoned','recovered','expired') DEFAULT 'abandoned',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_abandoned_at (abandoned_at),
    INDEX idx_email (tenant_id, customer_email),
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cart recovery analytics';

-- =====================================================
-- 10. ONLINE ORDERS
-- =====================================================
CREATE TABLE IF NOT EXISTS online_orders (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    order_number    VARCHAR(50) NOT NULL COMMENT 'e.g. ORD-20260517-0001',
    pos_sale_id     INT DEFAULT NULL COMMENT 'Linked POS sale record',
    customer_id     INT DEFAULT NULL,
    customer_email  VARCHAR(255) NOT NULL,
    customer_phone  VARCHAR(20) NOT NULL,
    customer_name   VARCHAR(255) NOT NULL,

    -- Financials
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    coupon_code     VARCHAR(50) DEFAULT NULL,
    shipping_cost   DECIMAL(12,2) DEFAULT 0.00,
    tax_amount      DECIMAL(12,2) DEFAULT 0.00,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency        VARCHAR(10) DEFAULT 'KES',

    -- Addresses
    shipping_address_id BIGINT UNSIGNED DEFAULT NULL,
    billing_address_id BIGINT UNSIGNED DEFAULT NULL,
    shipping_address_snapshot JSON DEFAULT NULL,
    billing_address_snapshot JSON DEFAULT NULL,

    -- Status
    status          ENUM('pending','processing','shipped','delivered','cancelled','refunded','returned') DEFAULT 'pending',
    payment_status   ENUM('pending','paid','failed','refunded','partially_refunded') DEFAULT 'pending',
    payment_method  ENUM('mpesa','cod','stripe','paypal','bank_transfer') DEFAULT 'cod',
    payment_reference VARCHAR(255) DEFAULT NULL COMMENT 'M-Pesa transaction ID, etc.',

    -- Fulfillment
    shipping_method VARCHAR(100) DEFAULT NULL,
    tracking_number VARCHAR(100) DEFAULT NULL,
    shipped_at      DATETIME DEFAULT NULL,
    delivered_at    DATETIME DEFAULT NULL,

    -- Meta
    notes           TEXT DEFAULT NULL,
    internal_notes  TEXT DEFAULT NULL,
    ip_address      VARCHAR(45) DEFAULT NULL,
    user_agent      VARCHAR(500) DEFAULT NULL,
    source          ENUM('web','mobile','api','whatsapp') DEFAULT 'web',

    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_order_number (tenant_id, order_number),
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_tenant_payment (tenant_id, payment_status),
    INDEX idx_customer (tenant_id, customer_id),
    INDEX idx_pos_sale (pos_sale_id),
    INDEX idx_created (tenant_id, created_at),
    INDEX idx_tracking (tracking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Online shop orders';

-- =====================================================
-- 11. ONLINE ORDER ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS online_order_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    order_id        BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    variant_id      INT DEFAULT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL,
    original_price  DECIMAL(10,2) DEFAULT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    tax_amount      DECIMAL(10,2) DEFAULT 0.00,
    weight_kg       DECIMAL(10,3) DEFAULT 0.000,
    product_name    VARCHAR(255) NOT NULL COMMENT 'Snapshot',
    product_sku     VARCHAR(100) DEFAULT NULL,
    product_image   VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_product (tenant_id, product_id),
    FOREIGN KEY (order_id) REFERENCES online_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Line items for online orders';

-- =====================================================
-- 12. ORDER STATUS HISTORY (Audit trail)
-- =====================================================
CREATE TABLE IF NOT EXISTS order_status_history (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    order_id        BIGINT UNSIGNED NOT NULL,
    from_status     VARCHAR(30) DEFAULT NULL,
    to_status       VARCHAR(30) NOT NULL,
    changed_by      INT DEFAULT NULL COMMENT 'user_id or customer_id',
    changed_by_type ENUM('system','staff','customer') DEFAULT 'system',
    notes           TEXT DEFAULT NULL,
    metadata        JSON DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (order_id) REFERENCES online_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Order status change audit log';

-- =====================================================
-- 13. WISHLISTS
-- =====================================================
CREATE TABLE IF NOT EXISTS wishlists (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    customer_id     INT NOT NULL,
    name            VARCHAR(100) DEFAULT 'My Wishlist',
    is_public       TINYINT(1) DEFAULT 0,
    share_token     VARCHAR(64) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_customer (tenant_id, customer_id),
    UNIQUE INDEX idx_share (share_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Customer wishlists';

-- =====================================================
-- 14. WISHLIST ITEMS
-- =====================================================
CREATE TABLE IF NOT EXISTS wishlist_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    wishlist_id     BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    added_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes           VARCHAR(255) DEFAULT NULL,

    UNIQUE INDEX idx_wishlist_product (wishlist_id, product_id),
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. PRODUCT REVIEWS
-- =====================================================
CREATE TABLE IF NOT EXISTS product_reviews (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    customer_id     INT DEFAULT NULL COMMENT 'NULL = guest review',
    customer_name   VARCHAR(255) DEFAULT NULL,
    customer_email  VARCHAR(255) DEFAULT NULL,
    order_id        BIGINT UNSIGNED DEFAULT NULL COMMENT 'Verified purchase',
    rating          TINYINT UNSIGNED NOT NULL COMMENT '1-5 stars',
    title           VARCHAR(200) DEFAULT NULL,
    comment         TEXT NOT NULL,
    pros            TEXT DEFAULT NULL,
    cons            TEXT DEFAULT NULL,
    images          JSON DEFAULT NULL COMMENT 'Array of image URLs',
    helpful_count   INT DEFAULT 0,
    not_helpful_count INT DEFAULT 0,
    status          ENUM('pending','approved','rejected','featured') DEFAULT 'pending',
    is_verified_purchase TINYINT(1) DEFAULT 0,
    moderated_by    INT DEFAULT NULL,
    moderated_at    DATETIME DEFAULT NULL,
    moderation_notes TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_product (tenant_id, product_id),
    INDEX idx_status (tenant_id, status),
    INDEX idx_rating (tenant_id, product_id, rating),
    INDEX idx_verified (tenant_id, is_verified_purchase),
    INDEX idx_customer (tenant_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Product reviews with moderation';

-- =====================================================
-- 16. COUPONS
-- =====================================================
CREATE TABLE IF NOT EXISTS coupons (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    code            VARCHAR(50) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    discount_type   ENUM('percentage','fixed_amount','free_shipping') NOT NULL,
    discount_value  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_order_value DECIMAL(10,2) DEFAULT 0.00,
    max_discount    DECIMAL(10,2) DEFAULT NULL,
    usage_limit     INT DEFAULT NULL COMMENT 'Total allowed uses',
    usage_limit_per_customer INT DEFAULT NULL,
    usage_count     INT DEFAULT 0,
    applicable_products JSON DEFAULT NULL COMMENT 'Product IDs or NULL = all',
    applicable_categories JSON DEFAULT NULL COMMENT 'Category IDs or NULL = all',
    excluded_products JSON DEFAULT NULL,
    start_date      DATE DEFAULT NULL,
    end_date        DATE DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    is_public       TINYINT(1) DEFAULT 1 COMMENT 'Shown on storefront?',
    created_by      INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_tenant_code (tenant_id, code),
    INDEX idx_active_dates (tenant_id, is_active, start_date, end_date),
    INDEX idx_usage (usage_count, usage_limit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Discount coupon codes';

-- =====================================================
-- 17. COUPON USAGE
-- =====================================================
CREATE TABLE IF NOT EXISTS coupon_usage (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    coupon_id       BIGINT UNSIGNED NOT NULL,
    order_id        BIGINT UNSIGNED NOT NULL,
    customer_id     INT DEFAULT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_coupon_order (coupon_id, order_id),
    INDEX idx_customer (tenant_id, customer_id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track coupon redemptions';

-- =====================================================
-- 18. M-PESA TRANSACTIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id               BIGINT UNSIGNED NOT NULL,
    order_id                BIGINT UNSIGNED DEFAULT NULL,
    cart_id                 BIGINT UNSIGNED DEFAULT NULL,
    amount                  DECIMAL(12,2) NOT NULL,
    phone_number            VARCHAR(20) NOT NULL,
    merchant_request_id     VARCHAR(100) DEFAULT NULL,
    checkout_request_id     VARCHAR(100) DEFAULT NULL,
    mpesa_receipt_number    VARCHAR(50) DEFAULT NULL,
    transaction_date        DATETIME DEFAULT NULL,
    result_code             VARCHAR(10) DEFAULT NULL,
    result_description      VARCHAR(255) DEFAULT NULL,
    status                  ENUM('pending','processing','completed','failed','cancelled','refunded') DEFAULT 'pending',
    callback_received_at    DATETIME DEFAULT NULL,
    retry_count             INT DEFAULT 0,
    raw_callback            JSON DEFAULT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_merchant (merchant_request_id),
    INDEX idx_checkout (checkout_request_id),
    INDEX idx_receipt (mpesa_receipt_number),
    INDEX idx_order (order_id),
    INDEX idx_phone (tenant_id, phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='M-Pesa STK Push transaction records';

-- =====================================================
-- 19. NOTIFICATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    customer_id     INT DEFAULT NULL COMMENT 'NULL = broadcast',
    user_id         INT DEFAULT NULL COMMENT 'Staff notification',
    type            ENUM('order','payment','shipping','promotion','system','review') NOT NULL,
    channel         ENUM('email','sms','push','in_app') DEFAULT 'in_app',
    title           VARCHAR(200) NOT NULL,
    message         TEXT NOT NULL,
    action_url      VARCHAR(500) DEFAULT NULL,
    icon            VARCHAR(50) DEFAULT NULL,
    is_read         TINYINT(1) DEFAULT 0,
    read_at         DATETIME DEFAULT NULL,
    sent_at         DATETIME DEFAULT NULL,
    metadata        JSON DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant_customer (tenant_id, customer_id, is_read),
    INDEX idx_type (tenant_id, type),
    INDEX idx_user (tenant_id, user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Customer and staff notifications';

-- =====================================================
-- 20. STORE BANNERS / SLIDERS
-- =====================================================
CREATE TABLE IF NOT EXISTS storefront_banners (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(200) DEFAULT NULL,
    subtitle        VARCHAR(500) DEFAULT NULL,
    image_url       VARCHAR(500) NOT NULL,
    mobile_image_url VARCHAR(500) DEFAULT NULL,
    link_url        VARCHAR(500) DEFAULT NULL,
    link_text       VARCHAR(50) DEFAULT 'Shop Now',
    position        ENUM('hero','featured','promo','sidebar') DEFAULT 'hero',
    display_order   INT DEFAULT 0,
    start_date      DATE DEFAULT NULL,
    end_date        DATE DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    click_count     INT DEFAULT 0,
    impression_count INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_position (tenant_id, position, is_active),
    INDEX idx_dates (tenant_id, start_date, end_date),
    INDEX idx_order (tenant_id, position, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Homepage banners and promotional sliders';

-- =====================================================
-- 21. FEATURED PRODUCTS
-- =====================================================
CREATE TABLE IF NOT EXISTS featured_products (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    section         ENUM('hero','new_arrivals','best_sellers','trending','deals','staff_picks') DEFAULT 'deals',
    display_order   INT DEFAULT 0,
    is_active       TINYINT(1) DEFAULT 1,
    start_date      DATE DEFAULT NULL,
    end_date        DATE DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_tenant_product_section (tenant_id, product_id, section),
    INDEX idx_section (tenant_id, section, is_active, display_order),
    INDEX idx_dates (tenant_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Curated product placements per section';

-- =====================================================
-- 22. SEO SLUGS
-- =====================================================
CREATE TABLE IF NOT EXISTS seo_slugs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    entity_type     ENUM('product','category','page','brand') NOT NULL,
    entity_id       INT NOT NULL,
    canonical_url   VARCHAR(500) DEFAULT NULL,
    meta_title      VARCHAR(255) DEFAULT NULL,
    meta_description TEXT DEFAULT NULL,
    meta_keywords   VARCHAR(500) DEFAULT NULL,
    og_image        VARCHAR(500) DEFAULT NULL,
    og_title        VARCHAR(255) DEFAULT NULL,
    og_description  TEXT DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_tenant_slug (tenant_id, slug),
    INDEX idx_entity (tenant_id, entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='SEO-friendly URL slugs with meta tags';

-- =====================================================
-- 23. PRODUCT VIEW STATS (Analytics)
-- =====================================================
CREATE TABLE IF NOT EXISTS product_view_stats (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    product_id      INT NOT NULL,
    view_date       DATE NOT NULL,
    view_count      INT DEFAULT 1,
    unique_visitors INT DEFAULT 1,
    add_to_cart_count INT DEFAULT 0,
    purchase_count  INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_tenant_product_date (tenant_id, product_id, view_date),
    INDEX idx_popular (tenant_id, view_date, view_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily product analytics for trending/recommendations';

-- =====================================================
-- 24. WEBHOOK SUBSCRIPTIONS (For POS events)
-- =====================================================
CREATE TABLE IF NOT EXISTS webhook_subscriptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    url             VARCHAR(500) NOT NULL,
    events          JSON NOT NULL COMMENT 'Array of event names',
    secret          VARCHAR(255) DEFAULT NULL COMMENT 'HMAC secret',
    is_active       TINYINT(1) DEFAULT 1,
    last_triggered  DATETIME DEFAULT NULL,
    last_response   INT DEFAULT NULL COMMENT 'HTTP status',
    failure_count   INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant_active (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='External webhook subscriptions';

-- =====================================================
-- 25. STORE THEME CUSTOMIZATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS storefront_themes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    theme_name      VARCHAR(100) DEFAULT 'default',
    primary_color   VARCHAR(7) DEFAULT '#f68b1e',
    secondary_color VARCHAR(7) DEFAULT '#1a1a2e',
    background_color VARCHAR(7) DEFAULT '#ffffff',
    text_color      VARCHAR(7) DEFAULT '#282828',
    font_family     VARCHAR(100) DEFAULT 'Inter',
    custom_css      LONGTEXT DEFAULT NULL,
    custom_js       LONGTEXT DEFAULT NULL,
    header_html     LONGTEXT DEFAULT NULL,
    footer_html     LONGTEXT DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_tenant_theme (tenant_id, theme_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tenant-specific theme overrides';

-- =====================================================
-- SEED DATA
-- =====================================================
-- Insert default theme
INSERT INTO storefront_themes (tenant_id, theme_name, primary_color, secondary_color) VALUES
(1, 'default', '#f68b1e', '#1a1a2e')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Insert sample shipping zone for Kenya
INSERT INTO shipping_zones (tenant_id, name, description, is_active) VALUES
(1, 'Nairobi & Environs', 'Nairobi County and surrounding areas', 1),
(1, 'Rest of Kenya', 'All other counties', 1);

SET FOREIGN_KEY_CHECKS = 1;
