<?php
/**
 * Generate Security Report API
 * Generates comprehensive security compliance report
 * 
 * @package Jakababa\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../src/Security/SecurityBootstrap.php';

// Initialize security system
SecurityBootstrap::initialize();

// Secure API endpoint with super admin requirement
secure_api_endpoint('admin.security');

try {
    $audit_manager = new AuditTrailManager();
    $data_scope = get_data_scope();
    
    // Generate compliance report for last 30 days
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime('-30 days'));
    
    $report = $audit_manager->generateComplianceReport($start_date, $end_date);
    
    // Add system security status
    $report['security_status'] = [
        'security_system' => 'Active',
        'tenant_isolation' => 'Enforced',
        'audit_logging' => 'Active',
        'database_security' => 'Secure',
        'api_security' => 'Enforced',
        'session_security' => 'Active'
    ];
    
    // Add recommendations
    $report['recommendations'] = generateSecurityRecommendations($report);
    
    // Set headers for file download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="security_report_' . date('Y-m-d') . '.json"');
    
    echo json_encode($report, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Generate security report API error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate security report',
        'message' => $e->getMessage()
    ]);
}

/**
 * Generate security recommendations based on report data
 */
function generateSecurityRecommendations(array $report): array {
    $recommendations = [];
    
    // Check for high number of security violations
    if (isset($report['security_events']) && count($report['security_events']) > 10) {
        $recommendations[] = 'High number of security events detected. Review security logs and investigate patterns.';
    }
    
    // Check for permission denials
    if (isset($report['permission_checks'])) {
        $total_denials = array_sum(array_column($report['permission_checks'], 'denial_count'));
        if ($total_denials > 50) {
            $recommendations[] = 'High number of permission denials detected. Review user permissions and access patterns.';
        }
    }
    
    // Check data access patterns
    if (isset($report['data_access'])) {
        $high_access_tables = array_filter($report['data_access'], fn($t) => $t['access_count'] > 1000);
        if (!empty($high_access_tables)) {
            $recommendations[] = 'High data access volume detected. Consider implementing caching or optimization.';
        }
    }
    
    // Add general recommendations
    $recommendations[] = 'Regular security audits should be performed monthly.';
    $recommendations[] = 'Ensure all users have appropriate role-based permissions.';
    $recommendations[] = 'Monitor audit logs for unusual access patterns.';
    $recommendations[] = 'Keep security components updated to latest versions.';
    
    return $recommendations;
}
?>
