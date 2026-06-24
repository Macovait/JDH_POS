<?php
/**
 * AJAX endpoint to open a register session
 * POST: opening_cash (required), notes (optional)
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');
safe_require('functions.php', 'src');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf_token = generate_csrf_token();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (int) ($_SESSION['user']['id'] ?? 0);
    $tenant_id = (int) (get_current_tenant_id() ?? 0);
    $branch_id = (int) (get_current_branch_id() ?? 0);

    if ($tenant_id <= 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Company context missing']);
        exit;
    }

    if ($branch_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Branch is required']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }

    // CSRF verification
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if ($token !== $csrf_token) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $opening_cash = isset($input['opening_cash']) ? (float) $input['opening_cash'] : 0;
    $notes = trim($input['notes'] ?? '');

    if ($opening_cash < 0) {
        echo json_encode(['success' => false, 'error' => 'Opening cash cannot be negative']);
        exit;
    }

    // Check no open session exists for this user/branch
    $stmt = $pdo->prepare("
        SELECT id FROM register_sessions
        WHERE user_id = ? AND branch_id = ? AND status = 'open'
        LIMIT 1
    ");
    $stmt->execute([$user_id, $branch_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'A register session is already open for this user at this branch']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO register_sessions (tenant_id, branch_id, user_id, opening_cash, notes, status, opened_at)
        VALUES (?, ?, ?, ?, ?, 'open', NOW())
    ");
    $stmt->execute([$tenant_id, $branch_id, $user_id, $opening_cash, $notes]);
    $session_id = (int) $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'message' => 'Register opened successfully'
    ]);

} catch (PDOException $e) {
    error_log("Error opening register: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error opening register: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
