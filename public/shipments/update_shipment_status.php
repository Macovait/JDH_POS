<?php
/**
 * Update Shipment Status Page for Jakababa POS
 * Handle status updates for shipments (pending → shipped → delivered, or cancelled)
 */

require_once __DIR__ . '/../../src/auth.php';
require_login();

// Check for shipment permission
if (!check_permission('shipments.manage') && !is_super_admin()) {
    enforce_permission('shipments.manage');
}

require_once __DIR__ . '/../../src/db.php';

// Get parameters
$shipment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$new_status = isset($_GET['status']) ? $_GET['status'] : '';
$reason = isset($_GET['reason']) ? trim($_GET['reason']) : '';

// Validate
if (!$shipment_id || !in_array($new_status, ['shipped', 'delivered', 'cancelled'])) {
    header('Location: shipments.php?error=Invalid request');
    exit;
}

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$current_branch_id = get_current_branch_id();

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // Get current shipment details
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name, c.email as customer_email
        FROM shipments s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.id = ? AND s.branch_id = ?
    ");
    $stmt->execute([$shipment_id, $current_branch_id]);
    $shipment = $stmt->fetch();

    if (!$shipment) {
        throw new Exception("Shipment not found or you don't have permission to update it.");
    }

    // Validate status transition
    $current_status = $shipment['status'];
    $valid_transition = false;

    switch ($current_status) {
        case 'pending':
            if (in_array($new_status, ['shipped', 'cancelled'])) {
                $valid_transition = true;
            }
            break;
        case 'shipped':
            if (in_array($new_status, ['delivered', 'cancelled'])) {
                $valid_transition = true;
            }
            break;
        case 'delivered':
            // Can't change delivered status
            throw new Exception("Cannot change status of a delivered shipment.");
        case 'cancelled':
            // Can't change cancelled status
            throw new Exception("Cannot change status of a cancelled shipment.");
        default:
            throw new Exception("Invalid current status.");
    }

    if (!$valid_transition) {
        throw new Exception("Cannot change status from {$current_status} to {$new_status}.");
    }

    // Prepare update data
    $update_data = [
        'status' => $new_status,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // Add timestamp based on new status
    if ($new_status == 'shipped') {
        $update_data['shipped_at'] = date('Y-m-d H:i:s');
    } elseif ($new_status == 'delivered') {
        $update_data['delivered_at'] = date('Y-m-d H:i:s');
    }

    // Build update query
    $sql = "UPDATE shipments SET status = :status, updated_at = NOW()";
    $params = [':status' => $new_status, ':id' => $shipment_id, ':branch_id' => $current_branch_id];

    if ($new_status == 'shipped') {
        $sql .= ", shipped_at = NOW()";
    } elseif ($new_status == 'delivered') {
        $sql .= ", delivered_at = NOW()";
    }

    $sql .= " WHERE id = :id AND branch_id = :branch_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Add tracking history entry
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('tracking_history', $tables)) {
        $history_note = '';
        if ($new_status == 'cancelled' && !empty($reason)) {
            $history_note = "Reason: " . $reason;
        }

        $stmt = $pdo->prepare("
            INSERT INTO tracking_history (shipment_id, status, location, notes, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        $location = $shipment['city'] ?? 'Unknown location';
        $stmt->execute([$shipment_id, ucfirst($new_status), $location, $history_note]);
    }

    // Log activity
    $log_activity = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, created_at)
        VALUES (?, 'shipment_status_update', ?, ?, NOW())
    ");

    $description = "Updated shipment #{$shipment['tracking_number']} from {$current_status} to {$new_status}";
    if ($new_status == 'cancelled' && !empty($reason)) {
        $description .= " (Reason: {$reason})";
    }

    $log_activity->execute([
        $user_id,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // If cancelled, optionally update inventory (if you want to return items to stock)
    if ($new_status == 'cancelled') {
        // You could add logic here to return items to inventory if needed
        error_log("Shipment #{$shipment['tracking_number']} was cancelled");
    }

    $pdo->commit();

    // Redirect back with success message
    header("Location: view_shipment.php?id=" . $shipment_id . "&success=" . $new_status);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error updating shipment status: " . $e->getMessage());
    header("Location: view_shipment.php?id=" . $shipment_id . "&error=" . urlencode($e->getMessage()));
    exit;
}