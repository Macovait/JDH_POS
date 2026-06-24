<?php
require_once __DIR__ . '/../../src/db.php';
$pdo = get_db_connection();
if (!$pdo) die("DB fail\n");

echo "Creating online store and webhook tables...\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS online_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) DEFAULT 'cod',
    status ENUM('pending','paid','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "ok online_orders\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS online_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    qty INT UNSIGNED NOT NULL DEFAULT 1,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "ok online_order_items\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS webhook_endpoints (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(255) NOT NULL,
    events JSON DEFAULT '[]',
    status ENUM('active','paused','disabled') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "ok webhook_endpoints\n";

$pdo->exec("
CREATE TABLE IF NOT EXISTS webhook_deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    endpoint_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending','delivered','failed') DEFAULT 'pending',
    http_status INT UNSIGNED,
    response_body TEXT,
    attempt_count INT UNSIGNED DEFAULT 0,
    scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME,
    last_attempt_at DATETIME,
    error TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_scheduled (status, scheduled_at),
    INDEX idx_endpoint (endpoint_id),
    INDEX idx_event (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "ok webhook_deliveries\n";

try {
    $pdo->exec("ALTER TABLE pos_tenants ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(100) AFTER slug");
    echo "ok stripe_customer_id\n";
} catch (Exception $e) { echo "skip stripe_customer_id\n"; }

try {
    $pdo->exec("ALTER TABLE pos_subscriptions ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(100) AFTER tenant_id");
    echo "ok stripe_subscription_id\n";
} catch (Exception $e) { echo "skip stripe_subscription_id\n"; }

echo "Done.\n";
