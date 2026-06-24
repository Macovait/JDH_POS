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

$lines = explode("\n", $html);
$totalLines = count($lines);
echo "Total HTML lines: $totalLines\n";

// Check if HTML is complete
$last10 = array_slice($lines, -10);
echo "Last 10 lines:\n" . implode("\n", $last10) . "\n\n";

// Find script blocks
$scriptStarts = [];
$scriptEnds = [];
foreach ($lines as $i => $line) {
    if (strpos($line, '<script') !== false && strpos($line, '<script') === 0) $scriptStarts[] = $i+1;
    if (strpos($line, '</script>') !== false) $scriptEnds[] = $i+1;
}
echo "Script start lines: " . implode(', ', $scriptStarts) . "\n";
echo "Script end lines: " . implode(', ', $scriptEnds) . "\n";

// Check line 1907-1908
if (isset($lines[1906])) echo "Line 1907: " . substr($lines[1906], 0, 120) . "\n";
if (isset($lines[1907])) echo "Line 1908: " . substr($lines[1907], 0, 120) . "\n";
