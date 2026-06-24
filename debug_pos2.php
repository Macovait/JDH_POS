<?php
/**
 * Debug script: Include pos.php with a valid session context
 */

// Set required session variables before session_start
$_SESSION['initialized'] = true;
$_SESSION['last_activity'] = time();
$_SESSION['user_id'] = 1;
$_SESSION['tenant_id'] = 1;
$_SESSION['branch_id'] = 1;
$_SESSION['branch_name'] = 'Kisumu';
$_SESSION['role'] = 'Super Admin';
$_SESSION['role_id'] = 1;
$_SESSION['is_super_admin'] = true;
$_SESSION['user_name'] = 'Wycliffe Bunde';
$_SESSION['user_email'] = 'admin@demostore.com';
$_SESSION['tenant_name'] = 'JAKPOS';
$_SESSION['tenant_status'] = 'active';
$_SESSION['tenant_validated'] = true;
$_SESSION['business_type_1'] = 'supermarket';
$_SESSION['business_type'] = 'supermarket';
$_SESSION['biz_type_1'] = 'supermarket';
$_SESSION['biz_type_1_expires'] = time() + 3600;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['csrf_token_time'] = time();

// Set SERVER vars that pos.php expects
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/JDH_POS/public/pos/pos.php';
$_SERVER['PHP_SELF'] = '/JDH_POS/public/pos/pos.php';

// Turn on all error display
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Capture output
ob_start();
try {
    require_once __DIR__ . '/public/pos/pos.php';
} catch (Throwable $e) {
    echo "\n<!-- FATAL ERROR: " . htmlspecialchars($e->getMessage()) . " -->\n";
    echo "<!-- " . htmlspecialchars($e->getTraceAsString()) . " -->\n";
}
$output = ob_get_clean();

// Save output
$outputFile = __DIR__ . '/pos_rendered2.html';
file_put_contents($outputFile, $output);

echo "Output length: " . strlen($output) . " bytes\n";
echo "Saved to: {$outputFile}\n";

// Quick checks
echo "Has productsGrid: " . (strpos($output, 'id="productsGrid"') !== false ? "YES" : "NO") . "\n";
echo "Has cart-panel: " . (strpos($output, 'cart-panel') !== false ? "YES" : "NO") . "\n";
echo "Has error message: " . (strpos($output, 'FATAL ERROR') !== false ? "YES" : "NO") . "\n";

// Show first 2000 chars if output is very small
if (strlen($output) < 3000) {
    echo "\n--- Output (first 2000 chars) ---\n";
    echo substr($output, 0, 2000) . "\n";
}
