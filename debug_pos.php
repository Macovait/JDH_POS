<?php
/**
 * Debug script: Access pos.php with a valid session and save output
 * Restricted to localhost to prevent session token exposure
 */

if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

$sessionId = $_ENV['DEBUG_SESSION_ID'] ?? ($_GET['sid'] ?? '');
if (empty($sessionId)) {
    die('Set DEBUG_SESSION_ID environment variable or provide ?sid= parameter');
}

$url = 'http://localhost/JDH_POS/public/pos/pos.php';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'jakababa_saas_sid=' . $sessionId);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "HTTP Code: {$httpCode}\n";
echo "Headers:\n{$headers}\n";

// Save the full HTML
$outputFile = __DIR__ . '/pos_rendered.html';
file_put_contents($outputFile, $body);
echo "\nSaved rendered HTML to: {$outputFile}\n";
echo "Body length: " . strlen($body) . " bytes\n";

// Check for specific elements
$hasProductsGrid = strpos($body, 'id="productsGrid"') !== false;
$hasCartPanel = strpos($body, 'class="cart-panel"') !== false;
$hasProducts = strpos($body, 'product-card') !== false || strpos($body, 'pos-product-card') !== false;
$redirected = strpos($body, 'login.php') !== false || strpos($body, 'Sign In') !== false;

echo "\n--- Checks ---\n";
echo "Products grid element: " . ($hasProductsGrid ? "YES" : "NO") . "\n";
echo "Cart panel element: " . ($hasCartPanel ? "YES" : "NO") . "\n";
echo "Product cards in HTML: " . ($hasProducts ? "YES" : "NO") . "\n";
echo "Redirected to login: " . ($redirected ? "YES" : "NO") . "\n";

// Also check if CSS has key classes
$cssFile = __DIR__ . '/public/pos/pos.css';
if (file_exists($cssFile)) {
    $css = file_get_contents($cssFile);
    echo "\n--- CSS Checks ---\n";
    echo "Has .flex: " . (strpos($css, '.flex{') !== false ? "YES" : "NO") . "\n";
    echo "Has .flex-col: " . (strpos($css, '.flex-col{') !== false ? "YES" : "NO") . "\n";
    echo "Has .w-64: " . (strpos($css, '.w-64{') !== false ? "YES" : "NO") . "\n";
    echo "Has .grid: " . (strpos($css, '.grid{') !== false ? "YES" : "NO") . "\n";
    echo "Has .h-screen: " . (strpos($css, '.h-screen{') !== false ? "YES" : "NO") . "\n";
}
