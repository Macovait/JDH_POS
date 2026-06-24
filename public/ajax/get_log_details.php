<?php
/**
 * AJAX Endpoint: Get Log Details
 * 
 * Returns detailed information about a specific activity log
 * Multi-tenant aware (tenant_id isolation)
 */

// Load paths first (needed for helper functions)
require_once __DIR__ . '/../../src/paths.php';

// Start session and load dependencies
if (session_status() === PHP_SESSION_NONE) {
    session_name('jakababa_saas_sid');
    session_start();
}

// Load database connection
require_once __DIR__ . '/../../src/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = (int) $_SESSION['tenant_id'];

    // Get log ID from request
    $log_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($log_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid log ID'
        ]);
        exit;
    }

    // Get log details
    $sql = "
        SELECT 
            al.id,
            al.action,
            al.description,
            al.ip_address,
            al.meta,
            al.created_at,
            u.name as user_name,
            u.email as user_email
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id AND u.tenant_id = al.tenant_id
        WHERE al.id = ?
        AND al.tenant_id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$log_id, $tenant_id]);
    $log = $stmt->fetch();

    if (!$log) {
        echo json_encode([
            'success' => false,
            'message' => 'Log not found'
        ]);
        exit;
    }

    // Parse meta JSON and extract supplemental info
    if (empty($log['description']) && !empty($log['meta'])) {
        $decoded = json_decode($log['meta'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $log['description'] = $decoded['description'] ?? $decoded['message'] ?? '';
        }
    }

    // Prepare metadata for display
    $log['metadata'] = null;
    if (!empty($log['meta'])) {
        $decoded = json_decode($log['meta'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $log['metadata'] = json_encode($decoded);
        } else {
            $log['metadata'] = $log['meta'];
        }
    }
    unset($log['meta']);

    echo json_encode([
        'success' => true,
        'log' => $log
    ]);

} catch (Exception $e) {
    error_log("Error in get_log_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load log details'
    ]);
}
