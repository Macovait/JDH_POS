<?php
/**
 * Security Dashboard API
 * Provides security data for the admin dashboard
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
    
    // Get security dashboard data
    $dashboard_data = $audit_manager->getSecurityDashboard();
    
    // Add summary statistics
    $dashboard_data['active_users'] = getActiveUsersCount();
    $dashboard_data['data_access_count'] = getDataAccessCount();
    $dashboard_data['security_events_count'] = getSecurityEventsCount();
    $dashboard_data['violations_count'] = getViolationsCount();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($dashboard_data);
    
} catch (Exception $e) {
    error_log("Security dashboard API error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load security dashboard data',
        'message' => $e->getMessage()
    ]);
}

/**
 * Get active users count (last 24 hours)
 */
function getActiveUsersCount(): int {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as count 
            FROM audit_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting active users: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get data access count (last 24 hours)
 */
function getDataAccessCount(): int {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM data_access_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting data access count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get security events count (last 24 hours)
 */
function getSecurityEventsCount(): int {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM security_events 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting security events count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get violations count (last 24 hours)
 */
function getViolationsCount(): int {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM security_events 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND severity IN ('high', 'critical')
        ");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error getting violations count: " . $e->getMessage());
        return 0;
    }
}
?>
