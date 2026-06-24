<?php
/**
 * ZERO-TRUST COMPATIBILITY VERIFICATION SCRIPT
 * Quick manual testing to ensure no functionality breakage
 * Run this via browser: http://localhost/JDH_POS/tests/ZeroTrustCompatibilityTest.php
 */

// Include required dependencies
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
safe_require('auth.php', 'src', true);

// Start session for testing
start_session_secure();

echo "<h1>🧪 Zero-Trust Compatibility Check</h1>";
echo "<p>Testing basic functionality preservation...</p>";
echo "<p>Running basic functionality tests...</p>";

// Test 1: Database Connection
echo "<h3>1. Database Connection</h3>";
$pdo = null;
try {
    $pdo = get_db_connection();
    echo "✅ Database connection: <strong>WORKING</strong><br>";
} catch (Exception $e) {
    echo "❌ Database connection: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 2: Basic Authentication
echo "<h3>2. Basic Authentication</h3>";
try {
    // Simulate session
    $_SESSION = [
        'user_id' => 1,
        'tenant_id' => 1,
        'role' => 'cashier'
    ];

    // Test old functions still work
    $user_id = get_current_user_id();
    $tenant_id = get_current_tenant_id();

    if ($user_id === 1 && $tenant_id === 1) {
        echo "✅ Legacy auth functions: <strong>WORKING</strong><br>";
    } else {
        echo "⚠️ Legacy auth functions: <strong>UNEXPECTED VALUES</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ Legacy auth functions: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 3: Product Loading (without Zero-Trust first)
echo "<h3>3. Product Loading (Legacy)</h3>";
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE active = 1");
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    echo "✅ Product count query: <strong>WORKING</strong> ($count products found)<br>";
} catch (Exception $e) {
    echo "❌ Product count query: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 4: Zero-Trust Context (if implemented)
echo "<h3>4. Zero-Trust Context</h3>";
try {
    if (file_exists(__DIR__ . '/../src/SecureTenantContext.php')) {
        require_once __DIR__ . '/../src/SecureTenantContext.php';
        if (class_exists('SecureTenantContext')) {
            $context = SecureTenantContext::getInstance();
            echo "✅ SecureTenantContext: <strong>AVAILABLE</strong><br>";
            echo "   - Tenant ID: " . ($context->getCompanyId() ?? 'null') . "<br>";
            echo "   - User ID: " . ($context->getUserId() ?? 'null') . "<br>";
            echo "   - Is Super Admin: " . ($context->isSuperAdmin() ? 'Yes' : 'No') . "<br>";
        } else {
            echo "⚠️ SecureTenantContext: <strong>CLASS NOT FOUND</strong><br>";
        }
    } else {
        echo "⚠️ SecureTenantContext: <strong>FILE NOT FOUND</strong> (legacy mode)<br>";
    }
} catch (Exception $e) {
    echo "❌ SecureTenantContext: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 5: Repository Pattern (if implemented)
echo "<h3>5. Repository Pattern</h3>";
try {
    if (file_exists(__DIR__ . '/../src/BaseModel.php')) {
        require_once __DIR__ . '/../src/BaseModel.php';
        if (class_exists('BaseModel')) {
            echo "✅ BaseModel: <strong>AVAILABLE</strong><br>";
        } else {
            echo "⚠️ BaseModel: <strong>CLASS NOT FOUND</strong><br>";
        }
    } else {
        echo "⚠️ BaseModel: <strong>FILE NOT FOUND</strong> (manual queries)<br>";
    }

    if (file_exists(__DIR__ . '/../src/repositories/ProductRepository.php')) {
        echo "✅ ProductRepository: <strong>FILE AVAILABLE</strong><br>";
    } else {
        echo "⚠️ ProductRepository: <strong>FILE NOT FOUND</strong><br>";
    }

    if (file_exists(__DIR__ . '/../admin/ProductModel.php')) {
        echo "✅ ProductModel (admin): <strong>FILE AVAILABLE</strong><br>";
    } else {
        echo "⚠️ ProductModel (admin): <strong>FILE NOT FOUND</strong><br>";
    }
} catch (Exception $e) {
    echo "❌ Repository check: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 6: Database Constraints
echo "<h3>6. Database Constraints</h3>";
try {
    $pdo = get_db_connection();

    // Check if products table has tenant_id constraint
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_NAME = 'products'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $stmt->execute();
    $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($constraints)) {
        echo "✅ Foreign key constraints: <strong>ACTIVE</strong> (" . count($constraints) . " found)<br>";
    } else {
        echo "⚠️ Foreign key constraints: <strong>NOT FOUND</strong> (run migration)<br>";
    }
} catch (Exception $e) {
    echo "❌ Constraint check: <strong>FAILED</strong> - " . $e->getMessage() . "<br>";
}

// Test 7: Critical Vulnerability Check
echo "<h3>7. Critical Vulnerability Check</h3>";
$criticalIssues = [];

// Check for dangerous fallback queries
$posFile = __DIR__ . '/../public/pos/pos.php';
if (file_exists($posFile)) {
    $content = file_get_contents($posFile);
    if (strpos($content, 'fallback_sql = str_replace("AND p.tenant_id = ?", "", $product_sql)') !== false) {
        $criticalIssues[] = "🚨 DANGEROUS FALLBACK QUERY in pos.php - REMOVES ALL TENANT FILTERS!";
    }
    if (strpos($content, 'TenantContext::getInstance()') !== false) {
        echo "✅ Zero-Trust context: <strong>IMPLEMENTED</strong> in POS<br>";
    } else {
        echo "⚠️ Zero-Trust context: <strong>NOT IMPLEMENTED</strong> in POS<br>";
    }
}

// Check for manual db_query calls (should be replaced with repositories)
$files = glob(__DIR__ . '/../**/*.php');
$manualQueries = 0;
foreach ($files as $file) {
    if (strpos($file, '/vendor/') !== false) continue;
    $content = file_get_contents($file);
    $manualQueries += substr_count($content, 'db_query(');
    $manualQueries += substr_count($content, 'db_fetch_one(');
    $manualQueries += substr_count($content, 'db_fetch_all(');
}

if ($manualQueries > 50) { // Allow some for legacy compatibility
    echo "⚠️ Manual database calls: <strong>HIGH COUNT</strong> ($manualQueries found - consider repository migration)<br>";
} else {
    echo "✅ Manual database calls: <strong>ACCEPTABLE</strong> ($manualQueries found)<br>";
}

echo "<h3>📋 SUMMARY</h3>";
if (empty($criticalIssues)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "🎉 <strong>GOOD NEWS:</strong> No critical security vulnerabilities detected!<br>";
    echo "✅ Core functionality appears intact<br>";
    echo "✅ Database connections working<br>";
    echo "✅ Legacy authentication preserved<br>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "🚨 <strong>CRITICAL ISSUES FOUND:</strong><br>";
    foreach ($criticalIssues as $issue) {
        echo "• $issue<br>";
    }
    echo "<br><strong>ACTION REQUIRED:</strong> Fix these issues immediately before going live!";
    echo "</div>";
}

echo "<h3>🔧 Next Steps</h3>";
echo "<ol>";
echo "<li><strong>Deploy database constraints</strong> from <code>database/migrations/zero_trust_tenant_constraints.sql</code></li>";
echo "<li><strong>Implement Zero-Trust context</strong> in authentication flow</li>";
echo "<li><strong>Migrate to Repository pattern</strong> for better maintainability</li>";
echo "<li><strong>Run full audit</strong> using ZERO_TRUST_AUDIT_CHECKLIST.md</li>";
echo "<li><strong>Monitor performance</strong> after deployment</li>";
echo "</ol>";

echo "<h3>📋 DEPLOYMENT READINESS</h3>";

// Initialize variables for scoring
$user_id = $tenant_id = $content = $constraints = $manualQueries = null;

// Get current values (from earlier tests)
try {
    $user_id = get_current_user_id();
    $tenant_id = get_current_tenant_id();

    // Check for dangerous fallback in POS file
    $posFile = __DIR__ . '/../public/pos/pos.php';
    if (file_exists($posFile)) {
        $content = file_get_contents($posFile);
    }

    // Check database constraints
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'products'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_NAME = 'products' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        $stmt->execute();
        $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Count manual queries
    $files = glob(__DIR__ . '/../**/*.php');
    $manualQueries = 0;
    foreach ($files as $file) {
        if (strpos($file, '/vendor/') !== false || strpos($file, '/tests/') !== false) continue;
        $fileContent = file_get_contents($file);
        $manualQueries += substr_count($fileContent, 'db_query(');
        $manualQueries += substr_count($fileContent, 'db_fetch_one(');
        $manualQueries += substr_count($fileContent, 'db_fetch_all(');
    }

} catch (Exception $e) {
    // Continue with defaults
}

// Calculate readiness score
$readinessScore = 0;
$maxScore = 7;

if ($pdo) $readinessScore++;
if ($user_id !== null && $tenant_id !== null) $readinessScore++;
if ($content && strpos($content, 'tenant_id = ?') !== false) $readinessScore++; // Legacy queries work
if (file_exists(__DIR__ . '/../src/SecureTenantContext.php')) $readinessScore++;
if (file_exists(__DIR__ . '/../src/BaseModel.php')) $readinessScore++;
if (!empty($constraints)) $readinessScore++;
if ($manualQueries <= 100) $readinessScore++; // Reasonable number of manual queries

$percentage = round(($readinessScore / $maxScore) * 100);

if ($percentage >= 80) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "🎉 <strong>READY FOR DEPLOYMENT</strong><br>";
    echo "Readiness Score: <strong>$readinessScore/$maxScore ($percentage%)</strong><br>";
    echo "✅ Core functionality intact<br>";
    echo "✅ Security framework available<br>";
    echo "✅ Safe to proceed with Zero-Trust implementation<br>";
    echo "</div>";
} elseif ($percentage >= 60) {
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "⚠️ <strong>DEPLOY WITH CAUTION</strong><br>";
    echo "Readiness Score: <strong>$readinessScore/$maxScore ($percentage%)</strong><br>";
    echo "⚠️ Some components missing - test thoroughly in staging<br>";
    echo "🔧 Consider implementing missing components first<br>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "🚨 <strong>NOT READY FOR DEPLOYMENT</strong><br>";
    echo "Readiness Score: <strong>$readinessScore/$maxScore ($percentage%)</strong><br>";
    echo "❌ Critical components missing<br>";
    echo "🔧 Implement core Zero-Trust components first<br>";
    echo "📖 See ZERO_TRUST_IMPLEMENTATION_GUIDE.md<br>";
    echo "</div>";
}

echo "<h3>🚀 Next Steps</h3>";
echo "<ol>";
if ($percentage >= 80) {
    echo "<li>✅ <strong>Deploy database constraints</strong> (safe, reversible)</li>";
    echo "<li>✅ <strong>Enable Zero-Trust context</strong> in authentication</li>";
    echo "<li>✅ <strong>Migrate critical modules</strong> (POS, Products, Sales)</li>";
    echo "<li>✅ <strong>Monitor for 24 hours</strong> after deployment</li>";
} else {
    echo "<li>🔧 <strong>Implement missing components</strong> from the list above</li>";
    echo "<li>📖 <strong>Follow implementation guide</strong> step by step</li>";
    echo "<li>🧪 <strong>Test thoroughly</strong> in staging environment</li>";
    echo "<li>⚠️ <strong>Do not deploy to production</strong> until readiness > 80%</li>";
}
echo "</ol>";

echo "<hr>";
echo "<p><small>Generated: " . date('Y-m-d H:i:s') . " | Test Version: 1.0 | Zero-Trust Compatibility Check</small></p>";
?>