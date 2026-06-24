<?php
/**
 * AJAX Endpoint: Get Activity Logs
 * 
 * Returns activity logs with filters and pagination
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

// Fix session values that may be incorrectly set as arrays
$user_id = is_array($_SESSION['user_id']) ? ($_SESSION['user_id'][0] ?? 0) : (int)$_SESSION['user_id'];
$tenant_id = is_array($_SESSION['tenant_id']) ? ($_SESSION['tenant_id'][0] ?? 0) : (int)$_SESSION['tenant_id'];

if ($user_id <= 0 || $tenant_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = (int) $_SESSION['tenant_id'];

    // Get filter parameters
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    $action = $_GET['action'] ?? null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // Build query
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
        WHERE al.tenant_id = ?
    ";

    $params = [$tenant_id];

    // Apply filters
    if (!empty($date_from)) {
        $sql .= " AND DATE(al.created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $sql .= " AND DATE(al.created_at) <= ?";
        $params[] = $date_to;
    }

    if (!empty($user_id)) {
        $sql .= " AND al.user_id = ?";
        $params[] = (int) $user_id;
    }

    if (!empty($action)) {
        $sql .= " AND al.action LIKE ?";
        $params[] = '%' . $action . '%';
    }

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM activity_logs al WHERE al.tenant_id = ?";
    $count_params = [$tenant_id];

    if (!empty($date_from)) {
        $count_sql .= " AND DATE(al.created_at) >= ?";
        $count_params[] = $date_from;
    }

    if (!empty($date_to)) {
        $count_sql .= " AND DATE(al.created_at) <= ?";
        $count_params[] = $date_to;
    }

    if (!empty($user_id)) {
        $count_sql .= " AND al.user_id = ?";
        $count_params[] = (int) $user_id;
    }

    if (!empty($action)) {
        $count_sql .= " AND al.action LIKE ?";
        $count_params[] = '%' . $action . '%';
    }

    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($count_params);
    $total = (int) $stmt->fetchColumn();

    // Add pagination
    $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Parse meta JSON for each log and extract supplemental info
    foreach ($logs as &$log) {
        // If description column is empty, try to extract from meta
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
    }

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);

} catch (Exception $e) {
    error_log("Error in get_activity_logs.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load activity logs'
    ]);
}
