<?php
/**
 * Debug script to verify branch filtering is working
 * Run: http://localhost/JDH_POS/debug_branch_filter.php
 */

require_once __DIR__ . '/src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
require_login();

require_once __DIR__ . '/src/Security/SecurityBootstrap.php';
SecurityBootstrap::initialize();

require_once __DIR__ . '/src/DashboardContext.php';
require_once __DIR__ . '/src/DashboardRepository.php';

$pdo = get_db_connection();
$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'user';

echo "<pre style='background:#0b1120;color:#fff;padding:20px;font-family:monospace;'>";
echo "=== Branch Filter Debug ===\n\n";
echo "Tenant ID: {$tenant_id}\n";
echo "User ID: {$user_id}\n";
echo "Role: {$user_role}\n\n";

// Get all branches
$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND active = 1");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "ERROR fetching branches: " . $e->getMessage() . "\n";
}

echo "Available branches:\n";
foreach ($branches as $b) {
    echo "  - ID {$b['id']}: {$b['name']}\n";
}
echo "\n";

// Test 1: No branch filter (All Branches)
$ctxAll = DashboardContext::create(
    companyId: $tenant_id,
    branchId: null,
    businessType: 'retail',
    role: $user_role,
    userId: $user_id,
    isSuperAdmin: is_super_admin()
);

[$whereAll, $paramsAll] = $ctxAll->fullFilter($pdo, 's');
echo "=== All Branches (branchId=null) ===\n";
echo "isBranchScoped: " . ($ctxAll->isBranchScoped() ? 'YES' : 'NO') . "\n";
echo "WHERE clause: {$whereAll}\n";
echo "Params: " . json_encode($paramsAll) . "\n\n";

// Test 2: With branch filter
echo "=== With Branch Filter ===\n";
foreach ($branches as $b) {
    $branchId = (int) $b['id'];
    $ctxBranch = DashboardContext::create(
        companyId: $tenant_id,
        branchId: $branchId,
        businessType: 'retail',
        role: $user_role,
        userId: $user_id,
        isSuperAdmin: is_super_admin()
    );
    
    [$whereBranch, $paramsBranch] = $ctxBranch->fullFilter($pdo, 's');
    echo "\nBranch {$branchId} ({$b['name']}):\n";
    echo "  isBranchScoped: " . ($ctxBranch->isBranchScoped() ? 'YES' : 'NO') . "\n";
    echo "  WHERE: {$whereBranch}\n";
    echo "  Params: " . json_encode($paramsBranch) . "\n";
    
    // Run actual query
    try {
        $sql = "SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as revenue FROM sales WHERE status='completed' AND DATE(created_at)=CURDATE() {$whereBranch}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsBranch);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  Result: {$row['cnt']} sales, Revenue: {$row['revenue']}\n";
    } catch (Exception $e) {
        echo "  QUERY ERROR: " . $e->getMessage() . "\n";
    }
}

// Test 3: Check what the dashboard context currently has
echo "\n\n=== Current Session Context ===\n";
$ctxSession = DashboardContext::fromSession();
echo "Session branchId: " . ($ctxSession->branchId() ?? 'null') . "\n";
echo "Session isBranchScoped: " . ($ctxSession->isBranchScoped() ? 'YES' : 'NO') . "\n";

// Test 4: Check if rebuild works
echo "\n\n=== Context Rebuild Test ===\n";
if (!empty($branches)) {
    $testBranchId = (int) $branches[0]['id'];
    $ctxRebuilt = DashboardContext::create(
        companyId: $tenant_id,
        branchId: $testBranchId,
        businessType: $ctxSession->businessType(),
        role: $user_role,
        userId: $user_id,
        isSuperAdmin: $ctxSession->isSuperAdmin()
    );
    echo "Rebuilt with branch {$testBranchId}:\n";
    echo "  branchId: " . ($ctxRebuilt->branchId() ?? 'null') . "\n";
    echo "  isBranchScoped: " . ($ctxRebuilt->isBranchScoped() ? 'YES' : 'NO') . "\n";
}

echo "\n</pre>";
