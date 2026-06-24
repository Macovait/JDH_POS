<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/JDH_POS/public/pos/pos.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/pos/pos.php';
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_USER_AGENT'] = 'test';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/JDH_POS/public/pos/pos.php';
$_SERVER['PHP_SELF'] = '/JDH_POS/public/pos/pos.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['tenant_id'] = 1;
$_SESSION['branch_id'] = 1;
$_SESSION['user_id'] = 1;
$_SESSION['user'] = ['id' => 1, 'name' => 'Test', 'role' => 'admin'];
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = 'test123';
$_SESSION['csrf_token_time'] = time();

ob_start();
try { include __DIR__ . '/public/pos/pos.php'; } catch (Throwable $e) { echo "PHPERROR: " . $e->getMessage() . "\n"; }
$html = ob_get_clean();

// Find main script block
if (preg_match('/(<script>\s*\/\/ ===+\s*\/\/ CONFIGURATION.*?)(<\/script>)/s', $html, $m, PREG_OFFSET_CAPTURE)) {
    $js = substr($m[1][0], strlen('<script>'));
    file_put_contents(__DIR__ . '/_pos.js', $js);
    echo "Extracted " . strlen($js) . " bytes\n";
    if (strpos($js, '</script>') !== false) {
        $pos = strpos($js, '</script>');
        echo "FATAL: </script> found inside JS at offset $pos:\n";
        echo substr($js, max(0,$pos-50), 100) . "\n";
    }
} else {
    echo "Could not find main script\n";
}
