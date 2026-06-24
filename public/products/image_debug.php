<?php
/**
 * Image Diagnostic Tool
 * Visit: http://localhost/JDH_POS/public/products/image_debug.php?id=<product_id>
 */
require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

header('Content-Type: text/plain');
echo "=== IMAGE DEBUG ===\n\n";
echo "Product ID: {$product_id}\n";
echo "Tenant ID:  {$tenant_id}\n\n";

echo "--- Constants ---\n";
echo "PUBLIC_PATH: " . PUBLIC_PATH . "\n";
echo "BASE_URL:    " . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "\n";
echo "DIRECTORY_SEPARATOR: " . DIRECTORY_SEPARATOR . "\n\n";

if (!$product_id) {
    echo "ERROR: No ?id= provided.\n";
    exit;
}

// Fetch product
$stmt = $pdo->prepare("SELECT id, name, image FROM products WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL");
$stmt->execute([':id' => $product_id, ':tenant_id' => $tenant_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "ERROR: Product not found.\n";
    exit;
}

echo "--- DB Record ---\n";
echo "Name:  {$product['name']}\n";
echo "Image: " . var_export($product['image'], true) . "\n\n";

// Simulate the normalization logic
$imgPath = ltrim($product['image'] ?? '', '/');
if (strpos($imgPath, 'public/') === 0) {
    $imgPath = substr($imgPath, 7);
}

echo "--- Normalized Path ---\n";
echo "Relative path (after stripping public/): {$imgPath}\n\n";

// Check file existence using the EXACT same logic as display functions
$fullPath = PUBLIC_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imgPath);
echo "--- File System Check ---\n";
echo "Full path (with DIRECTORY_SEPARATOR): {$fullPath}\n";
echo "file_exists(): " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";

// Also test the old broken logic for comparison
$oldPath = PUBLIC_PATH . str_replace('/', DIRECTORY_SEPARATOR, $imgPath);
echo "Full path (OLD broken logic): {$oldPath}\n";
echo "file_exists() with old logic: " . (file_exists($oldPath) ? 'YES' : 'NO') . "\n\n";

// Check directory contents
$uploadDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'product_images';
echo "--- Upload Directory ---\n";
echo "Dir: {$uploadDir}\n";
echo "is_dir(): " . (is_dir($uploadDir) ? 'YES' : 'NO') . "\n";
if (is_dir($uploadDir)) {
    $files = array_slice(scandir($uploadDir), 0, 10);
    echo "Files (first 10): " . implode(', ', $files) . "\n";
} else {
    // Try alternative
    $altDir = PUBLIC_PATH . '/uploads/product_images';
    echo "Alt dir (forward slash): {$altDir}\n";
    echo "is_dir(): " . (is_dir($altDir) ? 'YES' : 'NO') . "\n";
}
echo "\n";

// Generated URL
if (file_exists($fullPath)) {
    $mtime = @filemtime($fullPath) ?: time();
    $url = base_url($imgPath) . '?v=' . $mtime;
    echo "--- Generated URL ---\n";
    echo "URL: {$url}\n";
    echo "\nYou should be able to open this URL in your browser.\n";
} else {
    echo "--- URL Generation ---\n";
    echo "SKIPPED because file_exists() returned false.\n";
}
