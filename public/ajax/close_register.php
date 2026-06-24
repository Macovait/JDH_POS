<?php
/**
 * AJAX endpoint to close a register session
 * POST: closing_cash (required), notes (optional)
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

    $closing_cash = isset($input['closing_cash']) ? (float) $input['closing_cash'] : null;
    $notes = trim($input['notes'] ?? '');

    if ($closing_cash === null) {
        echo json_encode(['success' => false, 'error' => 'Closing cash amount is required']);
        exit;
    }

    if ($closing_cash < 0) {
        echo json_encode(['success' => false, 'error' => 'Closing cash cannot be negative']);
        exit;
    }

    // Find the open session for this user/branch
    $stmt = $pdo->prepare("
        SELECT id, opening_cash, opened_at FROM register_sessions
        WHERE user_id = ? AND branch_id = ? AND status = 'open'
        LIMIT 1
    ");
    $stmt->execute([$user_id, $branch_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['success' => false, 'error' => 'No open register session found']);
        exit;
    }

    $session_id = (int) $session['id'];
    $opening_cash = (float) $session['opening_cash'];

    // Calculate expected cash from sales during session
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(total), 0) as total_sales,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as total_cash_sales,
            COALESCE(SUM(CASE WHEN payment_method != 'cash' THEN total ELSE 0 END), 0) as total_other_sales,
            COUNT(*) as sale_count
        FROM sales
        WHERE branch_id = ? AND user_id = ? AND status = 'completed'
          AND created_at >= ?
    ");
    $stmt->execute([$branch_id, $user_id, $session['opened_at']]);
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_sales = (float) ($sales['total_sales'] ?? 0);
    $total_cash_sales = (float) ($sales['total_cash_sales'] ?? 0);
    $total_other_sales = (float) ($sales['total_other_sales'] ?? 0);
    $sale_count = (int) ($sales['sale_count'] ?? 0);

    $expected_cash = $opening_cash + $total_cash_sales;
    $cash_difference = $closing_cash - $expected_cash;

    $stmt = $pdo->prepare("
        UPDATE register_sessions SET
            closing_cash = ?,
            expected_cash = ?,
            cash_difference = ?,
            total_sales = ?,
            total_cash_sales = ?,
            total_other_sales = ?,
            sale_count = ?,
            notes = CONCAT(COALESCE(notes, ''), ?),
            status = 'closed',
            closed_at = NOW()
        WHERE id = ?
    ");
    $closing_notes = !empty($notes) ? "\nClose: " . $notes : '';
    $stmt->execute([
        $closing_cash, $expected_cash, $cash_difference,
        $total_sales, $total_cash_sales, $total_other_sales, $sale_count,
        $closing_notes, $session_id
    ]);

    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'opening_cash' => $opening_cash,
        'closing_cash' => $closing_cash,
        'expected_cash' => $expected_cash,
        'cash_difference' => $cash_difference,
        'total_sales' => $total_sales,
        'total_cash_sales' => $total_cash_sales,
        'total_other_sales' => $total_other_sales,
        'sale_count' => $sale_count,
        'message' => 'Register closed successfully'
    ]);

} catch (PDOException $e) {
    error_log("Error closing register: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error closing register: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
