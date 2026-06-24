<?php
// Simulate what get_payment_details.php does - full test with error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

echo "functions loaded OK\n";
echo "is_logged_in exists: " . (function_exists('is_logged_in') ? 'yes' : 'NO') . "\n";
echo "check_permission exists: " . (function_exists('check_permission') ? 'yes' : 'NO') . "\n";
echo "is_super_admin exists: " . (function_exists('is_super_admin') ? 'yes' : 'NO') . "\n";
echo "get_current_tenant_id exists: " . (function_exists('get_current_tenant_id') ? 'yes' : 'NO') . "\n";
