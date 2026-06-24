<?php
/**
 * Get User Notes - AJAX Endpoint
 * Returns notes for a specific user as JSON
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
require_login();

if (!check_permission('users.manage') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

if (empty($tenant_id)) {
    http_response_code(403);
    echo json_encode(['error' => 'Tenant context required']);
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// Verify user belongs to tenant
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) LIMIT 1");
$stmt->execute([$user_id, $tenant_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$notes = [];
try {
    $stmt = $pdo->prepare("SELECT id, note, created_at FROM user_notes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notes as &$note) {
        $note['created_at'] = date('M d, Y g:i A', strtotime($note['created_at']));
    }
} catch (Exception $e) {
    error_log("Error fetching notes: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($notes);
