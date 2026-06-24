<?php
/**
 * Migration: online_orders table for customer-facing storefront
 */
require_once __DIR__ . '/../../src/paths.php';
require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS online_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            customer_email VARCHAR(255),
            delivery_address TEXT,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
            total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            items_json LONGTEXT,
            paid_amount DECIMAL(12,2) DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_status (tenant_id, status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "online_orders table created or already exists.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
