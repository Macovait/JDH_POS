<?php
/**
 * AJAX Endpoint: Get Users
 * 
 * Returns list of users for the current company
 * Used for filter dropdowns in activity logs
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

    // Get users for the current company
    $sql = "
        SELECT 
            id,
            name,
            username,
            email
        FROM users
        WHERE tenant_id = ?
        AND status = 'active'
        ORDER BY name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    error_log("Error in get_users.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load users'
    ]);
}
