<?php
/**
 * Verify Admin PIN for price overrides
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);

try {
    $pdo = get_db_connection();

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['pin'])) {
        throw new Exception('PIN required');
    }

    $pin = trim($data['pin']);

    if (strlen($pin) < 4) {
        throw new Exception('PIN must be at least 4 characters');
    }

    // Check if user has admin role or if PIN matches admin user
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $companyId = isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : 1;

    // First check if current user is admin
    $stmt = $pdo->prepare("
        SELECT u.id, u.name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.id = ? AND u.tenant_id = ? AND (r.name = 'admin' OR r.name = 'super_admin' OR u.is_admin = 1)
    ");

    $stmt->execute([$userId, $companyId]);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adminUser) {
        echo json_encode([
            'success' => true,
            'admin_id' => $adminUser['id'],
            'admin_name' => $adminUser['name']
        ]);
        exit;
    }

    // If not admin, check PIN against admin users
    $stmt = $pdo->prepare("
        SELECT u.id, u.name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.tenant_id = ? AND (r.name = 'admin' OR r.name = 'super_admin' OR u.is_admin = 1)
        AND u.pin = ? AND u.active = 1
    ");

    $stmt->execute([$companyId, $pin]);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adminUser) {
        echo json_encode([
            'success' => true,
            'admin_id' => $adminUser['id'],
            'admin_name' => $adminUser['name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid PIN'
        ]);
    }

} catch (Exception $e) {
    error_log("Admin PIN verification error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>