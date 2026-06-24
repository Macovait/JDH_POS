<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Quick Mobile API Test
 */

echo "<h1>Mobile API Quick Test</h1>";

// Test API key exists
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);

// Prevent iframe embedding
header('X-Frame-Options: DENY');
header('Content-Security-Policy: frame-ancestors \'none\'');

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT api_key FROM tenant_api_keys WHERE tenant_id = 1 AND status = 'active' LIMIT 1");
$stmt->execute();
$api_key = $stmt->fetch()['api_key'] ?? null;

echo "<h2>API Key Status</h2>";
if ($api_key) {
    echo "<p style='color: green;'>✅ API Key found: " . htmlspecialchars(substr($api_key, 0, 10) . '...') . "</p>";
} else {
    echo "<p style='color: red;'>❌ No API key found</p>";
}

// Test basic API response
echo "<h2>API Endpoint Test</h2>";
echo "<p>Try accessing: <a href='mobile_test.php' target='_blank'>Mobile API Tester</a></p>";
echo "<p>Try accessing: <a href='mobile_pos.php' target='_blank'>Mobile POS Interface</a></p>";

// Test database tables
echo "<h2>Database Tables Status</h2>";
$tables = ['mobile_devices', 'driver_locations'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        echo "<p>" . ($exists ? "✅" : "❌") . " $table table " . ($exists ? "exists" : "missing") . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ $table table error: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>Access URLs</h2>";
echo "<ul>";
echo "<li><a href='/JDH_POS/public/mobile_test.php'>Mobile API Testing Interface</a></li>";
echo "<li><a href='/JDH_POS/public/mobile_pos.php'>Mobile POS Web Interface</a></li>";
echo "<li><a href='/JDH_POS/public/api/mobile/v1/auth/login' target='_blank'>Direct API Test (POST)</a></li>";
echo "</ul>";

echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Login to your dashboard</li>";
echo "<li>Click 'Mobile POS' in the sidebar to test the web interface</li>";
echo "<li>Click 'API Tester' to test individual API endpoints</li>";
echo "<li>Use the API key above for mobile app development</li>";
echo "</ol>";
?>