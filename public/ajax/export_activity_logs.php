<?php
/**
 * AJAX Endpoint: Export Activity Logs
 * 
 * Exports activity logs to CSV format
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

// Check authentication
if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $pdo = get_db_connection();
    $tenant_id = (int) $_SESSION['tenant_id'];

    // Get filter parameters
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    $action = $_GET['action'] ?? null;

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

    $sql .= " ORDER BY al.created_at DESC";

    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_H-i-s') . '"');

    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, [
        'ID',
        'Timestamp',
        'User',
        'Email',
        'Action',
        'Description',
        'IP Address'
    ]);

    // Add data rows
    foreach ($logs as $log) {
        $description = $log['description'] ?? '';
        if (empty($description) && !empty($log['meta'])) {
            $decoded = json_decode($log['meta'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $description = $decoded['description'] ?? $decoded['message'] ?? '';
            }
        }

        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $log['user_name'] ?? 'N/A',
            $log['user_email'] ?? 'N/A',
            $log['action'],
            $description,
            $log['ip_address'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Error in export_activity_logs.php: " . $e->getMessage());
    http_response_code(500);
    exit('Export failed');
}
