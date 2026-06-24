<?php
declare(strict_types=1);

/**
 * GDPR Right to Deletion Endpoint
 * Anonymizes or deletes all personal data for a tenant.
 * Requires admin confirmation token.
 */

require_once __DIR__ . '/../../../src/paths.php';
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

session_start();

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
$tenantId = $_SESSION['tenant_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if (!$tenantId || !$userId || $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$confirmation = $input['confirmation'] ?? '';
$expected = 'DELETE ALL DATA FOR TENANT ' . $tenantId;

if ($confirmation !== $expected) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Confirmation required',
        'expected' => $expected
    ]);
    exit;
}

$pdo = get_db_connection();
$deleted = [];
$anonymized = [];

try {
    $pdo->beginTransaction();

    // Anonymize customers (keep sales data but remove PII)
    $stmt = $pdo->prepare("
        UPDATE customers
        SET name = CONCAT('Deleted_', id), email = CONCAT('deleted_', id, '@anonymized.local'),
            phone = NULL, address = NULL, deleted_at = NOW()
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    $anonymized['customers'] = $stmt->rowCount();

    // Anonymize users (except the current admin for safety)
    $stmt = $pdo->prepare("
        UPDATE users
        SET name = CONCAT('Deleted_', id), email = CONCAT('deleted_', id, '@anonymized.local'),
            phone = NULL, deleted_at = NOW()
        WHERE tenant_id = ? AND id != ?
    ");
    $stmt->execute([$tenantId, $userId]);
    $anonymized['users'] = $stmt->rowCount();

    // Delete audit logs with PII
    $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE tenant_id = ? AND action LIKE '%customer%'");
    $stmt->execute([$tenantId]);
    $deleted['audit_logs_customer'] = $stmt->rowCount();

    // Log the deletion
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (tenant_id, user_id, action, details, created_at)
        VALUES (?, ?, 'gdpr_data_deletion', ?, NOW())
    ");
    $stmt->execute([$tenantId, $userId, json_encode(['anonymized' => $anonymized, 'deleted' => $deleted])]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Data anonymized successfully',
        'anonymized' => $anonymized,
        'deleted' => $deleted,
        'note' => 'Sales and financial records retained for compliance. Personal identifiers removed.'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("GDPR deletion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Deletion failed']);
}
