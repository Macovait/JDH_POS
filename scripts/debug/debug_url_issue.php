<?php
/**
 * Debug URL Duplication Issue
 */

echo "=== Debug URL Duplication Issue ===\n\n";

echo "Current Request Info:\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'Not set') . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'Not set') . "\n\n";

echo "Base URL Configuration:\n";
echo "BASE_URL constant: " . (defined('BASE_URL') ? BASE_URL : 'Not defined') . "\n";

require_once 'src/paths.php';
echo "base_url() function output: " . base_url() . "\n";
echo "base_url('dashboard/home.php'): " . base_url('dashboard/home.php') . "\n\n";

echo "URL Analysis:\n";
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
echo "Request URI length: " . strlen($request_uri) . " characters\n";

// Count dashboard occurrences
$dashboard_count = substr_count($request_uri, 'dashboard');
echo "'dashboard' appears {$dashboard_count} times in the URL\n";

if ($dashboard_count > 2) {
    echo "⚠️  URL DUPLICATION DETECTED!\n";
    echo "This suggests a redirect loop or URL rewriting issue.\n\n";
    
    echo "Possible causes:\n";
    echo "1. .htaccess rewrite rules causing loops\n";
    echo "2. base_url() being called multiple times\n";
    echo "3. Redirect loop in authentication\n";
    echo "4. Incorrect URL construction in sidebar\n";
} else {
    echo "✅ URL appears normal\n";
}

echo "\nSession Info:\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "Session is active\n";
    echo "Session ID: " . session_id() . "\n";
    if (isset($_SESSION['user_id'])) {
        echo "Logged in user ID: " . $_SESSION['user_id'] . "\n";
        echo "User role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
    } else {
        echo "Not logged in\n";
    }
} else {
    echo "Session not active\n";
}
?>
