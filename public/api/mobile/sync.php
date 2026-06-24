<?php
/**
 * Mobile Sync API
 * Handles real-time data synchronization across mobile apps
 */

require_once __DIR__ . '/../mobile/index.php';

$company = mobile_authenticate($pdo);

switch ($action) {
    case 'changes':
        get_sync_changes($pdo, $company);
        break;

    case 'push':
        push_sync_changes($pdo, $company);
        break;

    case 'status':
        get_sync_status($pdo, $company);
        break;

    default:
        mobile_error('Invalid sync action', 400);
}

function get_sync_changes($pdo, $company) {
    $data = validate_mobile_request(['last_sync', 'device_type']);

    $last_sync = $data['last_sync'];
    $device_type = $data['device_type'];
    $device_id = $data['device_id'] ?? null;
    $limit = (int) ($data['limit'] ?? 100);

    if (!strtotime($last_sync)) {
        mobile_error('Invalid last_sync timestamp', 400);
    }

    $changes = [
        'products' => [],
        'categories' => [],
        'customers' => [],
        'orders' => [],
        'inventory' => [],
        'sales' => [],
        'timestamp' => date('c')
    ];

    // Get product changes
    if (in_array($device_type, ['pos', 'customer', 'manager'])) {
        $stmt = $pdo->prepare("
            SELECT
                'product' as type, 'update' as action, id, name, price,
                cost_price, sku, barcode, image, active, updated_at
            FROM products
            WHERE tenant_id = ?
            AND deleted_at IS NULL
            AND updated_at > ?
            ORDER BY updated_at ASC
            LIMIT ?
        ");
        $stmt->execute([$company['id'], $last_sync, $limit]);
        $changes['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get category changes
    if (in_array($device_type, ['pos', 'customer', 'manager'])) {
        $stmt = $pdo->prepare("
            SELECT
                'category' as type, 'update' as action, id, name, color, icon, updated_at
            FROM categories
            WHERE tenant_id = ?
            AND updated_at > ?
            ORDER BY updated_at ASC
            LIMIT ?
        ");
        $stmt->execute([$company['id'], $last_sync, $limit]);
        $changes['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get customer changes (for POS and manager apps)
    if (in_array($device_type, ['pos', 'manager'])) {
        $stmt = $pdo->prepare("
            SELECT
                'customer' as type, 'update' as action, id, name, phone, email,
                address, loyalty_points, active, updated_at
            FROM customers
            WHERE tenant_id = ?
            AND updated_at > ?
            ORDER BY updated_at ASC
            LIMIT ?
        ");
        $stmt->execute([$company['id'], $last_sync, $limit]);
        $changes['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get order changes (for customer and driver apps)
    if (in_array($device_type, ['customer', 'driver'])) {
        $stmt = $pdo->prepare("
            SELECT
                'order' as type, 'update' as action, o.id, o.order_number,
                o.status, o.total, o.created_at, o.updated_at
            FROM customer_orders o
            WHERE o.tenant_id = ?  -- Note: customer_orders might need tenant_id
            AND o.updated_at > ?
            ORDER BY o.updated_at ASC
            LIMIT ?
        ");
        $stmt->execute([$company['id'], $last_sync, $limit]);
        $changes['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get inventory changes (for POS and manager apps)
    if (in_array($device_type, ['pos', 'manager'])) {
        $stmt = $pdo->prepare("
            SELECT
                'inventory' as type, 'update' as action, i.product_id,
                i.branch_id, i.stock, i.reorder_level, i.updated_at
            FROM inventory i
            WHERE i.tenant_id = ?
            AND i.updated_at > ?
            ORDER BY i.updated_at ASC
            LIMIT ?
        ");
        $stmt->execute([$company['id'], $last_sync, $limit]);
        $changes['inventory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get sales changes (for manager app)
    if ($device_type === 'manager') {
        $stmt = $pdo->prepare("
            SELECT
                'sale' as type, 'create' as action, s.id, s.total,
                s.payment_method, s.created_at
            FROM sales s
            WHERE s.tenant_id = ?
            AND s.created_at > ?
            ORDER BY s.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$company['id'], $last_sync, $limit]);
        $changes['sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate total changes
    $total_changes = 0;
    foreach ($changes as $key => $data) {
        if ($key !== 'timestamp') {
            $total_changes += count($data);
        }
    }

    mobile_success([
        'changes' => $changes,
        'total_changes' => $total_changes,
        'has_more' => $total_changes >= $limit,
        'next_sync' => date('c')
    ]);
}

function push_sync_changes($pdo, $company) {
    $data = validate_mobile_request(['changes', 'device_type']);

    $changes = $data['changes'];
    $device_type = $data['device_type'];
    $device_id = $data['device_id'] ?? null;

    if (!is_array($changes)) {
        mobile_error('Invalid changes format', 400);
    }

    $processed = 0;
    $errors = [];

    // Process offline sales (from POS app)
    if ($device_type === 'pos' && isset($changes['offline_sales'])) {
        foreach ($changes['offline_sales'] as $sale) {
            try {
                process_offline_sale($pdo, $company, $sale);
                $processed++;
            } catch (Exception $e) {
                $errors[] = "Sale processing failed: " . $e->getMessage();
            }
        }
    }

    // Process location updates (from driver app)
    if ($device_type === 'driver' && isset($changes['locations'])) {
        foreach ($changes['locations'] as $location) {
            try {
                update_driver_location($pdo, $company, $location);
                $processed++;
            } catch (Exception $e) {
                $errors[] = "Location update failed: " . $e->getMessage();
            }
        }
    }

    // Process customer updates (from customer app)
    if ($device_type === 'customer' && isset($changes['customer_updates'])) {
        foreach ($changes['customer_updates'] as $update) {
            try {
                update_customer_data($pdo, $company, $update);
                $processed++;
            } catch (Exception $e) {
                $errors[] = "Customer update failed: " . $e->getMessage();
            }
        }
    }

    mobile_success([
        'processed' => $processed,
        'errors' => $errors,
        'timestamp' => date('c')
    ], 'Sync completed');
}

function get_sync_status($pdo, $company) {
    $device_id = $_GET['device_id'] ?? null;

    $status = [
        'server_time' => date('c'),
        'last_sync' => null,
        'pending_changes' => 0,
        'connection_status' => 'online'
    ];

    // Get last sync time for this device
    if ($device_id) {
        $stmt = $pdo->prepare("
            SELECT last_sync
            FROM mobile_devices
            WHERE tenant_id = ? AND device_id = ?
            ORDER BY last_sync DESC
            LIMIT 1
        ");
        $stmt->execute([$company['id'], $device_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($device && $device['last_sync']) {
            $status['last_sync'] = $device['last_sync'];

            // Count pending changes since last sync
            $last_sync = $device['last_sync'];

            $tables = ['products', 'categories', 'customers', 'sales'];
            foreach ($tables as $table) {
                if (table_exists($pdo, $table)) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as cnt
                        FROM $table
                        WHERE tenant_id = ?
                        AND updated_at > ?
                    ");
                    $stmt->execute([$company['id'], $last_sync]);
                    $status['pending_changes'] += (int) $stmt->fetch()['cnt'];
                }
            }
        }
    }

    mobile_success($status);
}

function process_offline_sale($pdo, $company, $sale_data) {
    // Validate required fields
    $required = ['items', 'total', 'branch_id', 'timestamp'];
    foreach ($required as $field) {
        if (!isset($sale_data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $branch_id = (int) $sale_data['branch_id'];
    $user_id = (int) ($sale_data['user_id'] ?? 1);
    $customer_id = $sale_data['customer_id'] ? (int) $sale_data['customer_id'] : null;
    $items = $sale_data['items'];
    $total = (float) $sale_data['total'];
    $discount = (float) ($sale_data['discount'] ?? 0);
    $payment_method = $sale_data['payment_method'] ?? 'cash';
    $offline_timestamp = $sale_data['timestamp'];

    // Validate branch belongs to company
    $stmt = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND tenant_id = ? AND active = 1");
    $stmt->execute([$branch_id, $company['id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid branch');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Create sale record with offline timestamp
        $stmt = $pdo->prepare("
            INSERT INTO sales (
                tenant_id, branch_id, user_id, customer_id, total, discount, payment_method,
                status, created_at, device_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)
        ");
        $stmt->execute([
            $company['id'], $branch_id, $user_id, $customer_id, $total, $discount,
            $payment_method, $offline_timestamp, 'offline_sync'
        ]);
        $sale_id = $pdo->lastInsertId();

        // Add sale items
        $stmt_item = $pdo->prepare("
            INSERT INTO sale_items (
                tenant_id, sale_id, product_id, product_name, quantity, price, subtotal, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $product_id = (int) $item['product_id'];
            $quantity = (float) $item['quantity'];
            $price = (float) $item['price'];
            $item_total = $quantity * $price;

            $stmt_item->execute([
                $company['id'], $sale_id, $product_id, $item['name'], $quantity,
                $price, $item_total, $offline_timestamp
            ]);

            // Update inventory
            update_inventory_stock($pdo, $product_id, $branch_id, $company['id'], -$quantity);
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function update_driver_location($pdo, $company, $location_data) {
    $driver_id = (int) $location_data['driver_id'];
    $latitude = (float) $location_data['latitude'];
    $longitude = (float) $location_data['longitude'];
    $timestamp = $location_data['timestamp'] ?? date('Y-m-d H:i:s');

    // Validate driver belongs to company
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$driver_id, $company['id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid driver');
    }

    // Insert location
    $stmt = $pdo->prepare("
        INSERT INTO driver_locations (driver_id, latitude, longitude, created_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$driver_id, $latitude, $longitude, $timestamp]);
}

function update_customer_data($pdo, $company, $update_data) {
    $customer_id = (int) $update_data['customer_id'];

    // Validate customer belongs to company
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$customer_id, $company['id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid customer');
    }

    // Update customer information
    $updates = [];
    $params = [];

    if (isset($update_data['name'])) {
        $updates[] = 'name = ?';
        $params[] = $update_data['name'];
    }

    if (isset($update_data['phone'])) {
        $updates[] = 'phone = ?';
        $params[] = $update_data['phone'];
    }

    if (isset($update_data['email'])) {
        $updates[] = 'email = ?';
        $params[] = $update_data['email'];
    }

    if (isset($update_data['address'])) {
        $updates[] = 'address = ?';
        $params[] = $update_data['address'];
    }

    if (!empty($updates)) {
        $updates[] = 'updated_at = NOW()';
        $params[] = $customer_id;
        $params[] = $company['id'];

        $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
}

function update_inventory_stock($pdo, $product_id, $branch_id, $tenant_id, $quantity_change) {
    // Check if inventory record exists
    $stmt = $pdo->prepare("SELECT id, stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
    $stmt->execute([$product_id, $branch_id, $tenant_id]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($inventory) {
        $new_stock = $inventory['stock'] + $quantity_change;
        $stmt = $pdo->prepare("UPDATE inventory SET stock = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_stock, $inventory['id']]);
    } else {
        // Create inventory record
        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, branch_id, tenant_id, stock, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$product_id, $branch_id, $tenant_id, $quantity_change]);
    }
}

function table_exists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}
