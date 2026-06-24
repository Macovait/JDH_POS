<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/JDH_POS/public/pos/pos.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/pos/pos.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/..';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_USER_AGENT'] = 'test';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/JDH_POS/public/pos/pos.php';
$_SERVER['PHP_SELF'] = '/JDH_POS/public/pos/pos.php';

// Minimal session
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['tenant_id'] = 1;
$_SESSION['branch_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['user'] = ['id' => 1, 'name' => 'Test', 'role' => 'admin'];
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = 'test123';
$_SESSION['csrf_token_time'] = time();

ob_start();
try {
    include __DIR__ . '/public/pos/pos.php';
} catch (Throwable $e) {
    echo "PHP ERROR: " . $e->getMessage() . "\n";
}
$html = ob_get_clean();

// Extract the main <script> block
if (preg_match('/<script>\s*\/\/ =+\s*\/\/ CONFIGURATION.*?<\/script>/s', $html, $matches)) {
    $js = $matches[0];
    // Remove <script> tags
    $js = preg_replace('/^<script>\s*/s', '', $js);
    $js = preg_replace('/\s*<\/script>$/s', '', $js);
    file_put_contents(__DIR__ . '/_pos_extracted.js', $js);
    echo "Extracted " . strlen($js) . " bytes to _pos_extracted.js\n";
    
    // Check for </script> inside JS strings
    if (preg_match('/["\'][^"\']*<\/script>/', $js)) {
        echo "WARNING: Found </script> inside a JS string!\n";
    }
    
    // Show first 500 chars
    echo substr($js, 0, 500) . "\n";
} else {
    echo "Could not extract main script block\n";
    // Try to find all script blocks
    preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $scripts);
    echo "Found " . count($scripts[0]) . " script blocks\n";
    foreach ($scripts[0] as $i => $s) {
        echo "Block $i: " . strlen($s) . " bytes\n";
    }
}
