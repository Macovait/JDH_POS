<?php
/**
 * Support Session Extend API
 * 
 * AJAX endpoint to extend support session by 15 minutes.
 * Logs the extension and updates expiration.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/OwnerPanelService.php';

use JDH_POS\Services\OwnerPanelService;

header('Content-Type: application/json');

if (!admin_is_authenticated()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
if ($adminId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin session not available']);
    exit;
}

// Verify in support mode
if (empty($_SESSION['support_mode']) || empty($_SESSION['support_access_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Not in support mode']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$accessId = (int) ($input['access_id'] ?? 0);

// Verify the access ID matches session
if ($accessId !== $_SESSION['support_access_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid access ID']);
    exit;
}

$pdo = admin_require_db(['admins', 'pos_tenants']);
$ownerService = new OwnerPanelService($pdo, $adminId);

try {
    // Check current session status
    $stmt = $pdo->prepare("
        SELECT expires_at, access_type, tenant_id
        FROM support_access_logs
        WHERE id = ? AND admin_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$accessId, $adminId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session not found or expired']);
        exit;
    }
    
    // Check if already extended (max 2 extensions)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as extension_count
        FROM support_access_actions
        WHERE access_id = ? AND action = 'session_extended'
    ");
    $stmt->execute([$accessId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['extension_count'] >= 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Maximum extensions reached (2)']);
        exit;
    }
    
    // Extend expiration by 15 minutes
    $newExpiresAt = date('Y-m-d H:i:s', strtotime($session['expires_at'] . ' +15 minutes'));
    
    $stmt = $pdo->prepare("
        UPDATE support_access_logs
        SET expires_at = ?
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$newExpiresAt, $accessId]);
    
    // Log the extension
    $ownerService->logSupportAction(
        $accessId,
        'session_extended',
        'Session extended by 15 minutes. New expiry: ' . $newExpiresAt
    );
    
    // Log in audit trail
    $ownerService->createAuditLog(
        'support_session_extended',
        'support_access',
        $accessId,
        [
            'description' => 'Support session extended',
            'old_values' => ['expires_at' => $session['expires_at']],
            'new_values' => ['expires_at' => $newExpiresAt]
        ]
    );
    
    // Update session
    $_SESSION['support_expires_at'] = $newExpiresAt;
    
    echo json_encode([
        'success' => true,
        'message' => 'Session extended by 15 minutes',
        'new_expires_at' => $newExpiresAt
    ]);
    
} catch (Exception $e) {
    error_log("Support extend error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
