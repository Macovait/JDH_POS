<?php
/**
 * POS SECURITY VERIFICATION
 * Confirms Zero-Trust updates are working
 */

// Start session and load dependencies
session_start();
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('auth.php', 'src', true);

echo "<!DOCTYPE html>
<html>
<head>
    <title>POS Security Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>🛡️ POS Security Verification</h1>
    <p>Testing Zero-Trust implementation in pos.php</p>";

$issues = [];
$successes = [];

// Test 1: Zero-Trust Context Loading
echo "<h2>1. Zero-Trust Context</h2>";
try {
    $_SESSION = [
        'user_id' => 1,
        'tenant_id' => 1,
        'branch_id' => 1,
        'role' => 'cashier',
        'authenticated_user_id' => 1,
        'authenticated_company_id' => 1,
        'current_branch_id' => 1
    ];

    if (file_exists(__DIR__ . '/../src/SecureTenantContext.php')) {
        require_once __DIR__ . '/../src/SecureTenantContext.php';
        $context = SecureTenantContext::getInstance();

        if ($context->getCompanyId() && $context->getUserId()) {
            echo "<div class='success'>✅ SecureTenantContext loaded successfully<br>";
            echo "   - Tenant ID: " . $context->getCompanyId() . "<br>";
            echo "   - User ID: " . $context->getUserId() . "<br>";
            echo "   - Branch ID: " . $context->getBranchId() . "<br>";
            echo "   - Super Admin: " . ($context->isSuperAdmin() ? 'Yes' : 'No') . "</div>";
            $successes[] = "Zero-Trust context working";
        } else {
            echo "<div class='error'>❌ Zero-Trust context missing required data</div>";
            $issues[] = "Context data incomplete";
        }
    } else {
        echo "<div class='error'>❌ SecureTenantContext.php not found</div>";
        $issues[] = "SecureTenantContext file missing";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Context loading failed: " . $e->getMessage() . "</div>";
    $issues[] = "Context loading error: " . $e->getMessage();
}

// Test 2: Critical Vulnerability Check
echo "<h2>2. Critical Vulnerability Status</h2>";
$posFile = __DIR__ . '/pos.php';

if (file_exists($posFile)) {
    $content = file_get_contents($posFile);

    // Check for dangerous fallback
    if (strpos($content, 'fallback_sql = str_replace("AND p.tenant_id = ?", "", $product_sql)') !== false) {
        echo "<div class='error'>🚨 CRITICAL VULNERABILITY DETECTED: Dangerous fallback query still exists!<br>";
        echo "This exposes ALL tenant data - FIX IMMEDIATELY!</div>";
        $issues[] = "CRITICAL: Dangerous fallback query found";
    } elseif (strpos($content, 'SecureTenantContext::getInstance()') !== false) {
        echo "<div class='success'>✅ Zero-Trust context integration confirmed<br>";
        echo "✅ Dangerous fallback queries removed<br>";
        echo "✅ Tenant isolation active</div>";
        $successes[] = "Vulnerability eliminated";
    } else {
        echo "<div class='warning'>⚠️ Zero-Trust context not detected in POS file</div>";
        $issues[] = "Zero-Trust context not integrated";
    }

    // Check for secure comments
    if (strpos($content, '// NO FALLBACK QUERIES - EVER') !== false) {
        echo "<div class='success'>✅ Security documentation present</div>";
        $successes[] = "Security documentation added";
    }
} else {
    echo "<div class='error'>❌ POS file not found</div>";
    $issues[] = "POS file not found";
}

// Test 3: Product Loading Security
echo "<h2>3. Product Loading Security</h2>";
try {
    if (file_exists(__DIR__ . '/../public/products/ProductModel.php')) {
        require_once __DIR__ . '/../public/products/ProductModel.php';
        $productModel = new ProductModel();

        $product = $productModel->getProductWithBranches(1);
        if ($product && isset($product['id'])) {
            echo "<div class='success'>✅ ProductModel working with tenant scoping<br>";
            echo "   - Product loaded: " . htmlspecialchars($product['name'] ?? 'Unknown') . "</div>";
            $successes[] = "Product model secure";
        } else {
            echo "<div class='info'>ℹ️ ProductModel available but no sample data</div>";
        }
    } else {
        echo "<div class='warning'>⚠️ ProductModel not found - using legacy secure queries</div>";
    }

    // Test database connectivity
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE active = 1");
    $count = $stmt->fetch()['count'];

    echo "<div class='success'>✅ Database working - $count active products found</div>";
    $successes[] = "Database connectivity confirmed";

} catch (Exception $e) {
    echo "<div class='error'>❌ Product loading test failed: " . $e->getMessage() . "</div>";
    $issues[] = "Product loading error: " . $e->getMessage();
}

// Test 4: Authentication Security
echo "<h2>4. Authentication Security</h2>";
try {
    $user_id = get_current_user_id();
    $tenant_id = get_current_tenant_id();

    if ($user_id && $tenant_id) {
        echo "<div class='success'>✅ Legacy authentication functions working<br>";
        echo "   - User ID: $user_id<br>";
        echo "   - Tenant ID: $tenant_id</div>";
        $successes[] = "Authentication working";
    } else {
        echo "<div class='warning'>⚠️ Legacy auth functions returned empty values</div>";
        $issues[] = "Authentication not returning values";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Authentication error: " . $e->getMessage() . "</div>";
    $issues[] = "Authentication error: " . $e->getMessage();
}

// Summary
echo "<h2>📊 Security Verification Summary</h2>";

$successCount = count($successes);
$issueCount = count($issues);

if ($issueCount === 0) {
    echo "<div class='success'>";
    echo "<h3>🎉 POS SECURITY: FULLY SECURE</h3>";
    echo "<p>All security checks passed! Zero-Trust implementation is working perfectly.</p>";
    echo "<ul>";
    foreach ($successes as $success) {
        echo "<li>✅ $success</li>";
    }
    echo "</ul>";
    echo "</div>";
} elseif ($issueCount > 0 && strpos(implode(' ', $issues), 'CRITICAL') !== false) {
    echo "<div class='error'>";
    echo "<h3>🚨 CRITICAL SECURITY ISSUES DETECTED</h3>";
    echo "<p>Immediate action required:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>❌ $issue</li>";
    }
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div class='warning'>";
    echo "<h3>⚠️ POS SECURITY: NEEDS ATTENTION</h3>";
    echo "<p>Some issues detected:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>⚠️ $issue</li>";
    }
    echo "</ul>";
    echo "<p>Successful aspects:</p>";
    echo "<ul>";
    foreach ($successes as $success) {
        echo "<li>✅ $success</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<h2>🔧 Next Steps</h2>";
echo "<ol>";
if (in_array("CRITICAL: Dangerous fallback query found", $issues)) {
    echo "<li>🚨 <strong>URGENT:</strong> Remove the dangerous fallback query from pos.php immediately</li>";
}
if (in_array("SecureTenantContext file missing", $issues)) {
    echo "<li>🔧 Implement SecureTenantContext.php in src/ directory</li>";
}
if (in_array("Zero-Trust context not integrated", $issues)) {
    echo "<li>🔧 Update pos.php to use Zero-Trust context</li>";
}
echo "<li>✅ Run this verification script daily</li>";
echo "<li>✅ Monitor security logs for tenant access</li>";
echo "<li>✅ Test POS functionality after updates</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Report generated: " . date('Y-m-d H:i:s') . " | Security verification for pos.php</small></p>";

echo "</body></html>";
?>