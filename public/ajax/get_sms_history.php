<?php
/**
 * AJAX endpoint to get SMS history
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src');

require_login();

header('Content-Type: application/json');

// ZERO-TRUST: Reject tenant_id override attempts
if (!empty($_GET['tenant_id'])) {
    error_log("ZERO-TRUST VIOLATION: tenant_id override in get_sms_history.php");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$tenant_id = get_current_tenant_id();

if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    $pdo = get_db_connection();
    
    $stmt = $pdo->prepare("
        SELECT id, phone, message, status, error_message, sent_at, created_at
        FROM sms_logs
        WHERE tenant_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$tenant_id, $limit, $offset]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM sms_logs WHERE tenant_id = ?");
    $count_stmt->execute([$tenant_id]);
    $total = $count_stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}