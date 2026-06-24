<?php
/**
 * Convert Quotation to Sale
 */

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

safe_require('db.php', 'src', true);

$quotation_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($quotation_id <= 0) {
    header('Location: list_quotation.php?error=Invalid quotation');
    exit;
}

try {
    $pdo = get_db_connection();
    $tenant_id = get_current_tenant_id();

    // Fetch quotation
    $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$quotation_id, $tenant_id]);
    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) {
        header('Location: list_quotation.php?error=Quotation not found');
        exit;
    }

    // Redirect to POS with quotation data
    header('Location: ../pos/pos.php?convert_quotation=' . $quotation_id);
    exit;

} catch (Exception $e) {
    header('Location: list_quotation.php?error=' . urlencode('Conversion failed'));
    exit;
}
