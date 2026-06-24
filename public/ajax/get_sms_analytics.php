<?php
/**
 * Get SMS Analytics
 * Returns detailed analytics data for date range
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

header('Content-Type: application/json');

require_login();

// ZERO-TRUST: Reject tenant_id override attempts
if (!empty($_GET['tenant_id'])) {
    error_log("ZERO-TRUST VIOLATION: tenant_id override in get_sms_analytics.php");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$tenant_id = get_current_tenant_id();

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    
    $pdo = get_db_connection();
    
    // Get summary stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sent,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM sms_logs
        WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $summary = $stmt->fetch();
    
    // Get daily breakdown
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM sms_logs
        WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$tenant_id, $date_from, $date_to]);
    $daily = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'total_sent' => (int)($summary['total_sent'] ?? 0),
        'delivered' => (int)($summary['delivered'] ?? 0),
        'sent' => (int)($summary['sent'] ?? 0),
        'failed' => (int)($summary['failed'] ?? 0),
        'pending' => (int)($summary['pending'] ?? 0),
        'daily_breakdown' => array_map(function($row) {
            return [
                'date' => $row['date'],
                'total' => (int)$row['total'],
                'delivered' => (int)$row['delivered'],
                'sent' => (int)$row['sent'],
                'failed' => (int)$row['failed']
            ];
        }, $daily)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
