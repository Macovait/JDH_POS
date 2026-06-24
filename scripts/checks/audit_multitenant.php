<?php
/**
 * Multi-Tenant Filtering Audit Tool
 * Comprehensive check of all PHP files for proper tenant/branch filtering
 */

header('Content-Type: text/plain');

echo "========================================\n";
echo "MULTI-TENANT FILTERING AUDIT\n";
echo "========================================\n\n";

$baseDir = __DIR__ . '/public';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$results = [
    'compliant' => [],      // Has proper filtering
    'missing_branch' => [], // Has tenant but missing branch
    'missing_tenant' => [], // Missing tenant entirely
    'no_queries' => [],     // No DB queries (safe)
    'suspicious' => [],     // Has queries but no filtering at all
];

$tableScopes = [
    // Tables that should have: tenant_id + branch_id
    'branch_scoped' => ['sales', 'sale_items', 'products', 'inventory', 'stock', 
                        'customers', 'expenses', 'purchases', 'purchase_items',
                        'employees', 'attendance', 'cash_register', 'payments'],
    
    // Tables that should have: tenant_id only
    'tenant_scoped' => ['branches', 'companies', 'subscription_plans', 'tenants'],
    
    // Tables that should have: tenant_id + branch_id + user_id
    'user_scoped' => ['user_settings', 'drafts', 'notifications', 'audit_logs'],
    
    // Tables that should have: tenant_id + business_type_id
    'type_scoped' => ['features', 'workflows', 'report_templates', 'business_settings'],
];

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    
    $path = $file->getPathname();
    $relative = str_replace(__DIR__ . '/', '', $path);
    $content = file_get_contents($path);
    
    // Skip if no database queries
    if (!preg_match('/(SELECT|INSERT|UPDATE|DELETE)\s+/i', $content)) {
        $results['no_queries'][] = $relative;
        continue;
    }
    
    // Check for filtering patterns
    $hasTenant = stripos($content, 'tenant_id') !== false;
    $hasBranch = stripos($content, 'branch_id') !== false;
    $hasUser = stripos($content, 'user_id') !== false;
    $hasBusinessType = stripos($content, 'business_type_id') !== false;
    $hasGetCurrentBranch = stripos($content, 'get_current_branch_id') !== false;
    $hasGetCurrentTenant = stripos($content, 'get_current_tenant_id') !== false;
    
    // Analyze what tables are queried
    preg_match_all('/FROM\s+(\w+)|INTO\s+(\w+)|UPDATE\s+(\w+)/i', $content, $tableMatches);
    $tables = array_filter(array_merge($tableMatches[1], $tableMatches[2], $tableMatches[3]));
    
    // Determine expected filtering based on tables
    $needsBranch = false;
    $needsTenant = false;
    $needsUser = false;
    $needsType = false;
    
    foreach ($tables as $table) {
        $table = strtolower($table);
        if (in_array($table, $tableScopes['branch_scoped'])) {
            $needsBranch = true;
            $needsTenant = true;
        }
        if (in_array($table, $tableScopes['tenant_scoped'])) {
            $needsTenant = true;
        }
        if (in_array($table, $tableScopes['user_scoped'])) {
            $needsUser = true;
            $needsBranch = true;
            $needsTenant = true;
        }
        if (in_array($table, $tableScopes['type_scoped'])) {
            $needsType = true;
            $needsTenant = true;
        }
    }
    
    // Categorize
    if ($needsTenant && !$hasTenant) {
        $results['missing_tenant'][] = [
            'file' => $relative,
            'tables' => array_unique($tables),
            'reason' => 'Queries tables that need tenant_id but none found'
        ];
    } elseif ($needsBranch && !$hasBranch) {
        $results['missing_branch'][] = [
            'file' => $relative,
            'tables' => array_unique($tables),
            'reason' => 'Queries branch-scoped tables but no branch_id filtering'
        ];
    } elseif (!$needsTenant && !$needsBranch && !$needsUser && !$needsType) {
        // No known tables queried - suspicious
        if (count($tables) > 0) {
            $results['suspicious'][] = [
                'file' => $relative,
                'tables' => array_unique($tables),
                'reason' => 'Has queries but unknown tables - manual review needed'
            ];
        }
    } else {
        $results['compliant'][] = [
            'file' => $relative,
            'filters' => []
        ];
        if ($hasTenant) $results['compliant'][count($results['compliant'])-1]['filters'][] = 'tenant';
        if ($hasBranch) $results['compliant'][count($results['compliant'])-1]['filters'][] = 'branch';
        if ($hasUser) $results['compliant'][count($results['compliant'])-1]['filters'][] = 'user';
        if ($hasBusinessType) $results['compliant'][count($results['compliant'])-1]['filters'][] = 'business_type';
    }
}

// REPORT
echo "📊 AUDIT RESULTS\n";
echo "========================================\n\n";

$total = count($results['compliant']) + count($results['missing_branch']) + 
         count($results['missing_tenant']) + count($results['suspicious']);

echo "Total files with DB queries: $total\n";
echo "✅ Compliant: " . count($results['compliant']) . "\n";
echo "⚠️  Missing branch filter: " . count($results['missing_branch']) . "\n";
echo "🚨 Missing tenant filter: " . count($results['missing_tenant']) . "\n";
echo "🔍 Suspicious (unknown tables): " . count($results['suspicious']) . "\n";
echo "ℹ️  No queries (safe): " . count($results['no_queries']) . "\n\n";

if (!empty($results['missing_tenant'])) {
    echo "\n🚨 CRITICAL: MISSING TENANT FILTER\n";
    echo "========================================\n";
    foreach ($results['missing_tenant'] as $item) {
        echo "File: {$item['file']}\n";
        echo "Tables: " . implode(', ', $item['tables']) . "\n";
        echo "Issue: {$item['reason']}\n\n";
    }
}

if (!empty($results['missing_branch'])) {
    echo "\n⚠️  WARNING: MISSING BRANCH FILTER\n";
    echo "========================================\n";
    foreach ($results['missing_branch'] as $item) {
        echo "File: {$item['file']}\n";
        echo "Tables: " . implode(', ', $item['tables']) . "\n";
        echo "Issue: {$item['reason']}\n\n";
    }
}

if (!empty($results['suspicious'])) {
    echo "\n🔍 MANUAL REVIEW NEEDED\n";
    echo "========================================\n";
    foreach ($results['suspicious'] as $item) {
        echo "File: {$item['file']}\n";
        echo "Tables: " . implode(', ', $item['tables']) . "\n";
        echo "Issue: {$item['reason']}\n\n";
    }
}

echo "\n========================================\n";
echo "RECOMMENDATIONS\n";
echo "========================================\n";

if (count($results['missing_tenant']) > 0) {
    echo "1. 🚨 FIX IMMEDIATELY: Add tenant_id filtering to critical files\n";
    echo "   These files can leak data across COMPANIES!\n\n";
}

if (count($results['missing_branch']) > 0) {
    echo "2. ⚠️  FIX SOON: Add branch_id filtering to branch-scoped tables\n";
    echo "   These files can leak data across BRANCHES!\n\n";
}

if (count($results['suspicious']) > 0) {
    echo "3. 🔍 REVIEW: Check unknown table queries\n";
    echo "   May need custom filtering logic\n\n";
}

echo "4. ✅ BEST PRACTICES:\n";
echo "   - Always use prepared statements with parameterized queries\n";
echo "   - Use get_current_branch_id() for branch context\n";
echo "   - Use get_current_tenant_id() for tenant context\n";
echo "   - Log all access for audit trails\n";
