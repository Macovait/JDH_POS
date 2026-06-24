<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Mobile Driver API
 * Handles delivery driver mobile app operations
 */

require_once __DIR__ . '/../mobile/index.php';

$company = mobile_authenticate($pdo);

switch ($action) {
    case 'deliveries':
        get_driver_deliveries($pdo, $company);
        break;

    case 'update_status':
        update_delivery_status($pdo, $company);
        break;

    case 'location':
        update_driver_location($pdo, $company);
        break;

    case 'earnings':
        get_driver_earnings($pdo, $company);
        break;

    case 'performance':
        get_driver_performance($pdo, $company);
        break;

    default:
        mobile_error('Invalid driver action', 400);
}

function get_driver_deliveries($pdo, $company) {
    $driver_id = (int) ($_GET['driver_id'] ?? 0);
    $status = $_GET['status'] ?? 'assigned'; // assigned, picked_up, delivered, failed

    if (!$driver_id) {
        mobile_error('Driver ID required', 400);
    }

    // Validate driver belongs to company
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$driver_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid driver', 403);
    }

    $status_filter = '';
    $params = [$driver_id];

    if ($status !== 'all') {
        $status_filter = "AND d.status = ?";
        $params[] = $status;
    }

    $stmt = $pdo->prepare("
        SELECT
            d.id, d.tracking_number, d.status, d.delivery_address,
            d.phone, d.estimated_delivery, d.actual_delivery,
            d.delivery_notes, d.created_at,
            o.id as order_id, o.total, o.customer_id,
            c.name as customer_name,
            b.name as branch_name
        FROM shipments d
        JOIN sales o ON d.sale_id = o.id
        JOIN customers c ON o.customer_id = c.id
        JOIN branches b ON d.branch_id = b.id
        WHERE d.driver_id = ? $status_filter
        ORDER BY d.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get delivery items for each delivery
    foreach ($deliveries as &$delivery) {
        $stmt_items = $pdo->prepare("
            SELECT si.product_name, si.quantity, si.price, si.total
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.id = ?
        ");
        $stmt_items->execute([$delivery['order_id']]);
        $delivery['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }

    mobile_success(['deliveries' => $deliveries]);
}

function update_delivery_status($pdo, $company) {
    $data = validate_mobile_request([
        'delivery_id', 'status', 'driver_id'
    ]);

    $delivery_id = (int) $data['delivery_id'];
    $status = $data['status'];
    $driver_id = (int) $data['driver_id'];
    $notes = $data['notes'] ?? '';
    $location_lat = $data['location_lat'] ?? null;
    $location_lng = $data['location_lng'] ?? null;

    // Validate status
    $valid_statuses = ['assigned', 'picked_up', 'out_for_delivery', 'delivered', 'failed'];
    if (!in_array($status, $valid_statuses)) {
        mobile_error('Invalid status', 400);
    }

    // Validate driver belongs to company
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$driver_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid driver', 403);
    }

    // Update delivery
    $update_fields = ['status = ?'];
    $params = [$status];

    if ($status === 'delivered') {
        $update_fields[] = 'actual_delivery = NOW()';
    }

    if ($location_lat && $location_lng) {
        $update_fields[] = 'delivery_lat = ?';
        $update_fields[] = 'delivery_lng = ?';
        $params[] = $location_lat;
        $params[] = $location_lng;
    }

    if (!empty($notes)) {
        $update_fields[] = 'delivery_notes = CONCAT(COALESCE(delivery_notes, \'\'), \' | \', ?)';
        $params[] = $notes;
    }

    $update_fields[] = 'updated_at = NOW()';
    $params[] = $delivery_id;

    $sql = "UPDATE shipments SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Log status change
    error_log("Delivery $delivery_id status changed to $status by driver $driver_id");

    // Send notification if delivered
    if ($status === 'delivered') {
        send_delivery_complete_notification($pdo, $delivery_id, $company);
    }

    mobile_success(null, 'Delivery status updated successfully');
}

function update_driver_location($pdo, $company) {
    $data = validate_mobile_request([
        'driver_id', 'latitude', 'longitude'
    ]);

    $driver_id = (int) $data['driver_id'];
    $latitude = (float) $data['latitude'];
    $longitude = (float) $data['longitude'];

    // Validate driver belongs to company
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$driver_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid driver', 403);
    }

    // Create driver_locations table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS driver_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            driver_id INT NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            accuracy FLOAT DEFAULT NULL,
            speed FLOAT DEFAULT NULL,
            heading FLOAT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_driver (driver_id),
            INDEX idx_location (latitude, longitude)
        )
    ");

    // Insert location
    $stmt = $pdo->prepare("
        INSERT INTO driver_locations (driver_id, latitude, longitude, accuracy, speed, heading)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $driver_id,
        $latitude,
        $longitude,
        $data['accuracy'] ?? null,
        $data['speed'] ?? null,
        $data['heading'] ?? null
    ]);

    mobile_success(null, 'Location updated successfully');
}

function get_driver_earnings($pdo, $company) {
    $driver_id = (int) ($_GET['driver_id'] ?? 0);
    $period = $_GET['period'] ?? 'today'; // today, week, month

    if (!$driver_id) {
        mobile_error('Driver ID required', 400);
    }

    // Validate driver belongs to company
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$driver_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid driver', 403);
    }

    // Build date filter
    $date_filter = '';
    switch ($period) {
        case 'today':
            $date_filter = 'DATE(s.created_at) = CURDATE()';
            break;
        case 'week':
            $date_filter = 'YEARWEEK(s.created_at) = YEARWEEK(CURDATE())';
            break;
        case 'month':
            $date_filter = 'MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())';
            break;
        default:
            $date_filter = 'DATE(s.created_at) = CURDATE()';
    }

    // Get delivery stats and earnings
    $stmt = $pdo->prepare("
        SELECT
            COUNT(d.id) as total_deliveries,
            COUNT(CASE WHEN d.status = 'delivered' THEN 1 END) as completed_deliveries,
            COUNT(CASE WHEN d.status = 'failed' THEN 1 END) as failed_deliveries,
            COALESCE(SUM(d.delivery_fee), 0) as total_earnings,
            AVG(d.delivery_time_minutes) as avg_delivery_time
        FROM shipments d
        JOIN sales s ON d.sale_id = s.id
        WHERE d.driver_id = ?
        AND $date_filter
    ");
    $stmt->execute([$driver_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get today's deliveries
    $stmt = $pdo->prepare("
        SELECT
            d.id, d.tracking_number, d.status, d.delivery_address,
            d.estimated_delivery, d.actual_delivery,
            COALESCE(d.delivery_fee, 0) as delivery_fee,
            s.total as order_total
        FROM shipments d
        JOIN sales s ON d.sale_id = s.id
        WHERE d.driver_id = ?
        AND DATE(d.created_at) = CURDATE()
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$driver_id]);
    $today_deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    mobile_success([
        'period' => $period,
        'stats' => [
            'total_deliveries' => (int) $stats['total_deliveries'],
            'completed_deliveries' => (int) $stats['completed_deliveries'],
            'failed_deliveries' => (int) $stats['failed_deliveries'],
            'total_earnings' => (float) $stats['total_earnings'],
            'avg_delivery_time' => (float) $stats['avg_delivery_time']
        ],
        'today_deliveries' => $today_deliveries
    ]);
}

function get_driver_performance($pdo, $company) {
    $driver_id = (int) ($_GET['driver_id'] ?? 0);

    if (!$driver_id) {
        mobile_error('Driver ID required', 400);
    }

    // Validate driver belongs to company
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$driver_id, $company['id']]);
    if (!$stmt->fetch()) {
        mobile_error('Invalid driver', 403);
    }

    // Get performance metrics for last 30 days
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_deliveries,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as on_time_deliveries,
            COUNT(CASE WHEN status = 'delivered' AND actual_delivery <= estimated_delivery THEN 1 END) as completed_deliveries,
            AVG(CASE WHEN status = 'delivered' THEN delivery_time_minutes END) as avg_delivery_time,
            AVG(rating) as avg_rating
        FROM shipments
        WHERE driver_id = ?
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$driver_id]);
    $performance = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate success rate
    $total = (int) $performance['total_deliveries'];
    $successful = (int) $performance['completed_deliveries'];
    $success_rate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;

    mobile_success([
        'performance' => [
            'total_deliveries' => $total,
            'successful_deliveries' => $successful,
            'success_rate' => $success_rate,
            'avg_delivery_time' => round((float) $performance['avg_delivery_time'], 1),
            'avg_rating' => round((float) $performance['avg_rating'], 1)
        ]
    ]);
}

function send_delivery_complete_notification($pdo, $delivery_id, $company) {
    // Get delivery details
    $stmt = $pdo->prepare("
        SELECT s.customer_id, c.phone, s.total
        FROM shipments d
        JOIN sales s ON d.sale_id = s.id
        JOIN customers c ON s.customer_id = c.id
        WHERE d.id = ?
    ");
    $stmt->execute([$delivery_id]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($delivery) {
        // In a full implementation, send SMS notification to customer
        // For now, just log it
        error_log("Delivery completed: ID $delivery_id, Customer {$delivery['customer_id']}, Amount {$delivery['total']}");
    }
}
