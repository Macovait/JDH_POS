<?php
/**
 * Clean Audit Logs API
 * Cleans old audit logs based on retention policy
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $audit_manager = new AuditTrailManager();
    $audit_manager->cleanOldLogs();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Audit logs cleaned successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Clean audit logs API error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to clean audit logs',
        'message' => $e->getMessage()
    ]);
}
?>
