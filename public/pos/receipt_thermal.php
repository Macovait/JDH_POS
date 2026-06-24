<?php
/**
 * Thermal Receipt Print Endpoint
 *
 * GET /pos/receipt_thermal.php?id=<sale_id>&w=80
 *
 * Returns a self-printing HTML receipt sized for 58mm or 80mm thermal printers.
 * The page auto-triggers window.print() on load.
 *
 * Query params:
 *   id  - sale id (required)
 *   w   - paper width in mm: 58 or 80 (default 80)
 *   raw - if "1", returns raw ESC/POS bytes (text/plain) instead of HTML
 */

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 2) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php',   'src', true);
require_login();

require_once __DIR__ . '/../../src/Pos/ThermalReceiptRenderer.php';

$sale_id   = (int) ($_GET['id'] ?? 0);
$width_mm  = (int) ($_GET['w']  ?? 80);
$raw       = isset($_GET['raw']) && $_GET['raw'] === '1';
$tenant_id = get_current_tenant_id();

if ($sale_id <= 0 || !$tenant_id) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT s.*, u.name AS cashier_name, c.name AS customer_name
        FROM sales s
        LEFT JOIN users u     ON s.user_id = u.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.id = ? AND s.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$sale_id, $tenant_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) {
        http_response_code(404);
        exit('Sale not found');
    }

    $stmt = $pdo->prepare("
        SELECT si.*, p.name AS product_name
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
    ");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $settings = ['currency' => 'KES', 'tax_rate' => 16, 'company_name' => 'POS'];
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    $renderer = new \App\Pos\ThermalReceiptRenderer($sale, $items, $settings);

    if ($raw) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="receipt-' . $sale_id . '.bin"');
        echo $renderer->escpos($width_mm === 58 ? 32 : 42);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo $renderer->html($width_mm);
    }
} catch (PDOException $e) {
    error_log('Thermal receipt error: ' . $e->getMessage());
    http_response_code(500);
    exit('Database error');
}
