<?php
// Suppress ALL error output before ANYTHING else for JSON API
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@error_reporting(E_ALL);

// Start fresh output buffer
while (ob_get_level()) @ob_end_clean();
ob_start();

// Register fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) @ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message']]);
        exit;
    }
});

try {
    require_once __DIR__ . '/../../src/paths.php';
    safe_require('auth.php', 'src', true);
    safe_require('db.php', 'src', true);
    safe_require('functions.php', 'src', true);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Load error: ' . $e->getMessage()]);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$company_id = (int) ($input['company_id'] ?? ($_SESSION['tenant_id'] ?? 0));

if (!$company_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing company_id']);
    exit;
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT name, multiplier, start_date, end_date
        FROM loyalty_events
        WHERE tenant_id = ?
          AND start_date <= NOW()
          AND end_date >= NOW()
          AND active = 1
          AND deleted_at IS NULL
        ORDER BY multiplier DESC
    ");
    $stmt->execute([$company_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'events' => $events]);
} catch (Throwable $e) {
    while (ob_get_level()) @ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
exit;
