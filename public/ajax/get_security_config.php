<?php
/**
 * Get Security Configuration for JavaScript
 * Returns security settings as JSON for client-side use
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

header('Content-Type: application/javascript');
header('Cache-Control: max-age=3600'); // Cache for 1 hour

try {
    require_once __DIR__ . '/../../src/Security/SecurityConfig.php';
    SecurityConfig::load();
    
    $config = [
        'session' => [
            'timeout' => SecurityConfig::get('session.timeout'),
            'warning_time' => SecurityConfig::get('session.warning_time'),
            'check_interval' => SecurityConfig::get('session.check_interval'),
        ],
        'csrf' => [
            'enabled' => SecurityConfig::get('csrf.enabled'),
        ],
    ];
    
    // Output as JavaScript global variable
    echo 'window.SECURITY_CONFIG = ' . json_encode($config) . ';';
    
} catch (Exception $e) {
    // Fallback defaults if config fails
    echo 'window.SECURITY_CONFIG = {"session":{"timeout":1800,"warning_time":300,"check_interval":10},"csrf":{"enabled":true}};';
}
?>
