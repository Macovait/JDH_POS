<?php
/**
 * AJAX endpoint to check if register is open for current user/branch
 * GET
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'paths.php';

safe_require('auth.php', 'src');
safe_require('db.php', 'src');
safe_require('functions.php', 'src');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (int) ($_SESSION['user']['id'] ?? 0);
    $requested_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
    $tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);

    // Validate branch_id belongs to tenant (prevents cross-tenant data leakage)
    $branch_id = resolve_branch_id($requested_branch_id, $tenant_id);
    if ($branch_id <= 0 && $requested_branch_id > 0) {
        echo json_encode(['success' => false, 'error' => 'Access denied to this branch']);
        exit;
    }

    if ($branch_id <= 0) {
        $branch_id = get_current_branch_id() ?: 0;
    }

    $stmt = $pdo->prepare("
        SELECT id, tenant_id, branch_id, user_id, opening_cash, total_sales,
               total_cash_sales, total_card_sales, total_other_sales, sale_count,
               notes, status, opened_at, closed_at
        FROM register_sessions
        WHERE user_id = ? AND branch_id = ? AND status = 'open'
        LIMIT 1
    ");
    $stmt->execute([$user_id, $branch_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($session) {
        echo json_encode([
            'success' => true,
            'is_open' => true,
            'session' => [
                'id' => (int) $session['id'],
                'tenant_id' => (int) $session['tenant_id'],
                'branch_id' => (int) $session['branch_id'],
                'user_id' => (int) $session['user_id'],
                'opening_cash' => (float) $session['opening_cash'],
                'total_sales' => (float) $session['total_sales'],
                'total_cash_sales' => (float) $session['total_cash_sales'],
                'total_card_sales' => (float) $session['total_card_sales'],
                'total_other_sales' => (float) $session['total_other_sales'],
                'sale_count' => (int) $session['sale_count'],
                'notes' => $session['notes'],
                'status' => $session['status'],
                'opened_at' => $session['opened_at'],
                'closed_at' => $session['closed_at']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'is_open' => false,
            'session' => null
        ]);
    }

} catch (PDOException $e) {
    error_log("Error checking register: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error checking register: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
