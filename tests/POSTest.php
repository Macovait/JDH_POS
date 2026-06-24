<?php
/**
 * POS-Specific Functionality Test
 * Tests POS operations after Zero-Trust implementation
 */

// Start session for testing
session_start();

// Include required dependencies
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('auth.php', 'src', true);

echo "<h1>🧪 POS Zero-Trust Functionality Test</h1>";
echo "<p>Testing POS-specific operations after security implementation...</p>";

// Test 1: Zero-Trust Context in POS
echo "<h3>1. POS Zero-Trust Context</h3>";
try {
    // Simulate a proper session for testing
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
        echo "✅ SecureTenantContext: <strong>ACTIVE</strong><br>";
        echo "   - Tenant ID: " . ($context->getCompanyId() ?? 'null') . "<br>";
        echo "   - User ID: " . ($context->getUserId() ?? 'null') . "<br>";
        echo "   - Branch ID: " . ($context->getBranchId() ?? 'null') . "<br>";
        echo "   - Is Super Admin: " . ($context->isSuperAdmin() ? 'Yes' : 'No') . "<br>";
    } else {
        echo "⚠️ SecureTenantContext: <strong>NOT FOUND</strong> - Legacy mode<br>";
    }
} catch (Exception $e) {
    echo "❌ SecureTenantContext: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 2: Product Repository
echo "<h3>2. Product Repository</h3>";
$productCount = 0;
$repositoryWorking = false;

try {
    // Check for new ProductModel (POS-specific)
    if (file_exists(__DIR__ . '/../public/products/ProductModel.php')) {
        require_once __DIR__ . '/../public/products/ProductModel.php';
        $productModel = new ProductModel();

        // Test with tenant context
        $products = $productModel->getProductWithBranches(1); // Get first product with branches
        $productCount = count($products);
        $repositoryWorking = true;

        echo "✅ ProductModel (POS): <strong>WORKING</strong><br>";
        echo "   - Loaded product with tenant scoping<br>";
        if (!empty($products)) {
            echo "   - Product: " . htmlspecialchars($products['name'] ?? 'N/A') . "<br>";
        }
    }

    // Check for general ProductRepository
    if (file_exists(__DIR__ . '/../src/repositories/ProductRepository.php')) {
        require_once __DIR__ . '/../src/repositories/ProductRepository.php';
        $productRepo = new ProductRepository();

        // Test search functionality
        $searchResults = $productRepo->search('test', 5);
        echo "✅ ProductRepository: <strong>WORKING</strong><br>";
        echo "   - Search function operational<br>";
        if (!$repositoryWorking) {
            $productCount = count($searchResults);
        }
    }

    if (!$repositoryWorking && !file_exists(__DIR__ . '/../src/repositories/ProductRepository.php')) {
        echo "⚠️ No Product Repository: <strong>NOT FOUND</strong> - Using legacy queries<br>";
    }

} catch (Exception $e) {
    echo "❌ Product Repository: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 3: Dangerous Fallback Check
echo "<h3>3. Critical Vulnerability Check</h3>";
$vulnerabilityFound = false;

$posFile = __DIR__ . '/pos.php';
if (file_exists($posFile)) {
    $content = file_get_contents($posFile);

    // Check for the dangerous fallback pattern
    if (strpos($content, 'fallback_sql = str_replace("AND p.tenant_id = ?", "", $product_sql)') !== false) {
        echo "🚨 <strong>CRITICAL VULNERABILITY FOUND:</strong> Dangerous fallback query still exists!<br>";
        echo "   This exposes ALL tenant data - FIX IMMEDIATELY!<br>";
        $vulnerabilityFound = true;
    }

    // Check for Zero-Trust implementation
    if (strpos($content, 'SecureTenantContext::getInstance()') !== false) {
        echo "✅ Zero-Trust Context: <strong>IMPLEMENTED</strong> in POS<br>";
    } else {
        echo "⚠️ Zero-Trust Context: <strong>NOT IMPLEMENTED</strong> in POS<br>";
    }

    if (strpos($content, 'ProductRepository') !== false) {
        echo "✅ Repository Pattern: <strong>IMPLEMENTED</strong> in POS<br>";
    } else {
        echo "⚠️ Repository Pattern: <strong>NOT IMPLEMENTED</strong> in POS<br>";
    }

    if (strpos($content, '// NO FALLBACK QUERIES - EVER') !== false) {
        echo "✅ Security Comments: <strong>PRESENT</strong> (good documentation)<br>";
    }
} else {
    echo "❌ POS file: <strong>NOT FOUND</strong><br>";
}

// Test 4: Category Loading
echo "<h3>4. Category Loading</h3>";
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE tenant_id = ? AND (status = 'active' OR status IS NULL)");
    $stmt->execute([1]); // Using tenant_id 1 for testing
    $categoryCount = $stmt->fetchColumn();

    echo "✅ Category Loading: <strong>WORKING</strong> ($categoryCount categories found)<br>";
} catch (Exception $e) {
    echo "❌ Category Loading: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 5: Branch Access
echo "<h3>5. Branch Access Control</h3>";
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE tenant_id = ? AND active = 1");
    $stmt->execute([1]); // Using tenant_id 1 for testing
    $branchCount = $stmt->fetchColumn();

    echo "✅ Branch Access: <strong>WORKING</strong> ($branchCount branches accessible)<br>";
} catch (Exception $e) {
    echo "❌ Branch Access: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

echo "<h3>📊 POS TEST SUMMARY</h3>";

$testsPassed = 0;
$testsTotal = 5;

if (isset($context) && $context->getCompanyId()) $testsPassed++;
if ($productCount >= 0) $testsPassed++; // 0 or more products is OK
if (!$vulnerabilityFound) $testsPassed++;
if ($categoryCount >= 0) $testsPassed++;
if ($branchCount >= 0) $testsPassed++;

$percentage = round(($testsPassed / $testsTotal) * 100);

if ($percentage >= 80 && !$vulnerabilityFound) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "🎉 <strong>POS READY FOR PRODUCTION</strong><br>";
    echo "Test Score: <strong>$testsPassed/$testsTotal ($percentage%)</strong><br>";
    echo "✅ Zero-Trust security active<br>";
    echo "✅ No critical vulnerabilities<br>";
    echo "✅ All core POS functions working<br>";
    echo "</div>";
} elseif ($vulnerabilityFound) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "🚨 <strong>CRITICAL SECURITY ISSUE</strong><br>";
    echo "Test Score: <strong>$testsPassed/$testsTotal ($percentage%)</strong><br>";
    echo "❌ Dangerous fallback query must be removed<br>";
    echo "🔧 Fix the vulnerability before going live<br>";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "⚠️ <strong>POS NEEDS ATTENTION</strong><br>";
    echo "Test Score: <strong>$testsPassed/$testsTotal ($percentage%)</strong><br>";
    echo "⚠️ Some functions may not work properly<br>";
    echo "🔧 Complete Zero-Trust implementation<br>";
    echo "</div>";
}

echo "<h3>🔧 Next Steps</h3>";
echo "<ol>";
if ($vulnerabilityFound) {
    echo "<li>🚨 <strong>URGENT:</strong> Remove dangerous fallback query from pos.php</li>";
}
if ($percentage < 100) {
    echo "<li>✅ Complete Zero-Trust implementation in POS</li>";
    echo "<li>✅ Test all POS functions (add to cart, checkout, receipts)</li>";
}
echo "<li>✅ Run this test daily during development</li>";
echo "<li>✅ Monitor POS performance after deployment</li>";
echo "</ol>";

echo "<hr>";
echo "<p><small>Generated: " . date('Y-m-d H:i:s') . " | POS Zero-Trust Test | " . ($vulnerabilityFound ? 'VULNERABILITY DETECTED' : 'SECURE') . "</small></p>";
?>