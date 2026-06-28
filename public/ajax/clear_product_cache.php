<?php
/**
 * Clear Product Cache Endpoint
 * Clears cached product and category data for the current tenant/branch
 * 
 * Version: 3.0 - Enhanced security, logging, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection
 * - Multiple cache driver support (APCu, Redis, File)
 * - Comprehensive logging
 * - Graceful error handling
 */

require_once __DIR__ . '/../../src/Security/CorsHandler.php';
// ============================================
// ERROR HANDLING
// ============================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

ob_start();

try {

// ============================================
// SESSION START
// ============================================
if (!defined('SESSION_COOKIE_PATH')) {
    define('SESSION_COOKIE_PATH', '/');
}
session_name('jakababa_saas_sid');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// FUNCTION: Send JSON Response
// ============================================
function sendJsonResponse($data, $statusCode = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    \Jakababa\Security\apply_cors_headers();
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// FUNCTION: Send Error Response
// ============================================
function sendErrorResponse($message, $errorCode = 'unknown_error', $statusCode = 400) {
    sendJsonResponse([
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

// ============================================
// FUNCTION: Log Activity
// ============================================
function logActivity($userId, $action, $data = [], $ipAddress = null, $tenantId = null) {
    try {
        require_once __DIR__ . '/../../src/db.php';
        $pdo = get_db_connection();
        
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, ip_address, meta, created_at, tenant_id) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $description = is_array($data) ? json_encode($data) : (string)$data;
        $ip = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $meta = json_encode(['data' => $data]);
        
        return $stmt->execute([$userId, $action, $description, $ip, $meta, $tenantId]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNCTION: Get Current Tenant ID (Zero-Trust)
// ============================================
function getSecureTenantContext() {
    try {
        require_once __DIR__ . '/../../src/SecureTenantContext.php';
        $context = SecureTenantContext::getInstance();
        
        if ($context && $context->isValid()) {
            return [
                'tenant_id' => $context->getCompanyId(),
                'user_id' => $context->getUserId(),
                'branch_id' => $context->getBranchId(),
                'is_super_admin' => $context->isSuperAdmin(),
                'role' => $context->getUserRole()
            ];
        }
    } catch (Exception $e) {
        error_log("SecureTenantContext failed in clear_product_cache: " . $e->getMessage());
    }
    
    // Fallback to session values (with validation)
    $tenant_id = $_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0;
    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    $branch_id = $_SESSION['branch_id'] ?? 0;
    
    // Validate session integrity
    $session_tenant = $_SESSION['tenant_id'] ?? null;
    $session_user = $_SESSION['user_id'] ?? null;
    
    if (($session_tenant && (int)$session_tenant !== $tenant_id) ||
        ($session_user && (int)$session_user !== $user_id)) {
        error_log("ZERO-TRUST VIOLATION: Session mismatch in clear_product_cache");
        return null;
    }
    
    if ($tenant_id <= 0 || $user_id <= 0) {
        return null;
    }
    
    return [
        'tenant_id' => (int)$tenant_id,
        'user_id' => (int)$user_id,
        'branch_id' => (int)$branch_id,
        'is_super_admin' => false,
        'role' => $_SESSION['role'] ?? 'cashier'
    ];
}

// ============================================
// FUNCTION: Clear APCu Cache
// ============================================
function clearApcuCache($tenantId, $branchId) {
    $cleared = 0;
    
    if (!function_exists('apcu_exists')) {
        return $cleared;
    }
    
    try {
        // Get all APCu keys
        $info = apcu_cache_info();
        if (!$info || empty($info['cache_list'])) {
            return $cleared;
        }
        
        foreach ($info['cache_list'] as $entry) {
            $key = $entry['key'] ?? '';
            
            // Clear product and category cache keys for this tenant
            if (strpos($key, "products_lazy_{$tenantId}") !== false ||
                strpos($key, "products_lazy_{$tenantId}_{$branchId}") !== false ||
                strpos($key, "categories_{$tenantId}") !== false ||
                strpos($key, "products_{$tenantId}") !== false ||
                strpos($key, "product_stock_{$tenantId}") !== false) {
                
                if (apcu_delete($key)) {
                    $cleared++;
                }
            }
        }
    } catch (Exception $e) {
        error_log("APCu cache clear error: " . $e->getMessage());
    }
    
    return $cleared;
}

// ============================================
// FUNCTION: Clear Redis Cache
// ============================================
function clearRedisCache($tenantId, $branchId) {
    $cleared = 0;
    
    if (!extension_loaded('redis')) {
        return $cleared;
    }
    
    try {
        $redis = new Redis();
        $connected = $redis->connect('127.0.0.1', 6379, 1);
        
        if (!$connected) {
            return $cleared;
        }
        
        // Get all keys matching the pattern
        $patterns = [
            "products_lazy_{$tenantId}_*",
            "products_lazy_{$tenantId}_{$branchId}_*",
            "categories_{$tenantId}_*",
            "products_{$tenantId}_*",
            "product_stock_{$tenantId}_*"
        ];
        
        foreach ($patterns as $pattern) {
            $keys = $redis->keys($pattern);
            foreach ($keys as $key) {
                if ($redis->del($key)) {
                    $cleared++;
                }
            }
        }
        
        $redis->close();
    } catch (Exception $e) {
        error_log("Redis cache clear error: " . $e->getMessage());
    }
    
    return $cleared;
}

// ============================================
// FUNCTION: Clear File Cache
// ============================================
function clearFileCache($tenantId, $branchId) {
    $cleared = 0;
    $errors = [];
    $cacheDir = __DIR__ . '/../../storage/cache/';
    
    // Also check alternative cache locations
    $cacheDirs = [
        $cacheDir,
        __DIR__ . '/../../cache/',
        __DIR__ . '/../cache/',
        sys_get_temp_dir() . '/pos_cache/'
    ];
    
    foreach ($cacheDirs as $dir) {
        if (!is_dir($dir) || !is_writable($dir)) {
            continue;
        }
        
        $files = glob($dir . '*.cache') ?: [];
        $files = array_merge($files, glob($dir . '*.json') ?: []);
        $files = array_merge($files, glob($dir . '*.txt') ?: []);
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Clear product and category cache for this tenant
            if (strpos($filename, md5("products_{$tenantId}_{$branchId}")) !== false ||
                strpos($filename, md5("products_lazy_{$tenantId}")) !== false ||
                strpos($filename, md5("products_lazy_{$tenantId}_{$branchId}")) !== false ||
                strpos($filename, md5("categories_{$tenantId}")) !== false ||
                strpos($filename, md5("product_stock_{$tenantId}")) !== false) {
                
                if (@unlink($file)) {
                    $cleared++;
                } else {
                    $errors[] = "Failed to delete: $filename";
                }
            }
        }
    }
    
    return ['cleared' => $cleared, 'errors' => $errors];
}

// ============================================
// FUNCTION: Clear Database Cache Records
// ============================================
function clearDatabaseCache($tenantId, $branchId, $pdo) {
    $cleared = 0;
    
    try {
        // Check if cache table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'cache'");
        if ($stmt->rowCount() === 0) {
            return $cleared;
        }
        
        // Delete cache records for this tenant
        $stmt = $pdo->prepare("
            DELETE FROM cache 
            WHERE tenant_id = ? 
            AND (
                cache_key LIKE 'products_%' 
                OR cache_key LIKE 'categories_%' 
                OR cache_key LIKE 'product_stock_%'
            )
        ");
        $stmt->execute([$tenantId]);
        $cleared = $stmt->rowCount();
        
        // Also clear by branch if we have branch-specific cache
        $stmt = $pdo->prepare("
            DELETE FROM cache 
            WHERE tenant_id = ? 
            AND branch_id = ?
            AND cache_key LIKE 'products_%'
        ");
        $stmt->execute([$tenantId, $branchId]);
        $cleared += $stmt->rowCount();
        
    } catch (Exception $e) {
        error_log("Database cache clear error: " . $e->getMessage());
    }
    
    return $cleared;
}

// ============================================
// MAIN EXECUTION
// ============================================

// === 1. Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 'method_not_allowed', 405);
}

// === 2. Get Secure Context ===
$context = getSecureTenantContext();

if (!$context) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

$tenant_id = $context['tenant_id'];
$user_id = $context['user_id'];
$branch_id = $context['branch_id'];
$is_super_admin = $context['is_super_admin'];
$user_role = $context['role'] ?? 'cashier';

// === 3. CSRF Protection ===
$csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!verify_csrf_token($csrf_token)) {
    error_log("CSRF validation failed in clear_product_cache for user: " . $user_id);
    sendErrorResponse('CSRF validation failed', 'csrf_invalid', 403);
}

// === 4. Check Permissions ===
// Any authenticated user can refresh product cache (routine POS operation)
if (!$is_super_admin && !$user_id) {
    error_log("Permission denied for cache clear. User: {$user_id}");
    sendErrorResponse('Permission denied.', 'permission_denied', 403);
}

// === 5. Get Cache Type Parameter ===
$cache_type = $_POST['cache_type'] ?? 'all';
$valid_types = ['all', 'apcu', 'redis', 'file', 'database'];

if (!in_array($cache_type, $valid_types, true)) {
    $cache_type = 'all';
}

// === 6. Clear Cache ===
$results = [
    'apcu' => 0,
    'redis' => 0,
    'file' => 0,
    'database' => 0,
    'total' => 0
];
$errors = [];

// APCu cache
if ($cache_type === 'all' || $cache_type === 'apcu') {
    $results['apcu'] = clearApcuCache($tenant_id, $branch_id);
}

// Redis cache
if ($cache_type === 'all' || $cache_type === 'redis') {
    $results['redis'] = clearRedisCache($tenant_id, $branch_id);
}

// File cache
if ($cache_type === 'all' || $cache_type === 'file') {
    $fileResult = clearFileCache($tenant_id, $branch_id);
    $results['file'] = $fileResult['cleared'];
    $errors = array_merge($errors, $fileResult['errors']);
}

// Database cache
if ($cache_type === 'all' || $cache_type === 'database') {
    try {
        require_once __DIR__ . '/../../src/db.php';
        $pdo = get_db_connection();
        if ($pdo) {
            $results['database'] = clearDatabaseCache($tenant_id, $branch_id, $pdo);
        }
    } catch (Exception $e) {
        error_log("Database cache clear error: " . $e->getMessage());
        $errors[] = "Database cache: " . $e->getMessage();
    }
}

$results['total'] = array_sum($results);

// === 7. Log Activity ===
logActivity(
    $user_id,
    'cache.cleared',
    [
        'tenant_id' => $tenant_id,
        'branch_id' => $branch_id,
        'cache_type' => $cache_type,
        'files_cleared' => $results,
        'errors' => $errors ?: null
    ],
    $_SERVER['REMOTE_ADDR'] ?? null,
    $tenant_id
);

// === 8. Update Cache Settings ===
try {
    require_once __DIR__ . '/../../src/db.php';
    $pdo = get_db_connection();
    
    if ($pdo) {
        // Update last cache clear timestamp
        $stmt = $pdo->prepare("
            UPDATE settings 
            SET setting_value = NOW() 
            WHERE tenant_id = ? 
            AND setting_key = 'last_cache_clear'
        ");
        $stmt->execute([$tenant_id]);
        
        // If setting doesn't exist, insert it
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO settings (tenant_id, setting_key, setting_value, created_at, updated_at) 
                VALUES (?, 'last_cache_clear', NOW(), NOW(), NOW())
            ");
            $stmt->execute([$tenant_id]);
        }
    }
} catch (Exception $e) {
    error_log("Failed to update last_cache_clear: " . $e->getMessage());
}

// === 9. Return Response ===
sendJsonResponse([
    'success' => true,
    'message' => 'Cache cleared successfully',
    'cleared' => $results['total'],
    'details' => $results,
    'cache_type' => $cache_type,
    'tenant_id' => $tenant_id,
    'branch_id' => $branch_id,
    'timestamp' => date('Y-m-d H:i:s'),
    'errors' => $errors ?: null
]);

} catch (Throwable $e) {
    error_log("Fatal error in clear_product_cache.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'An unexpected error occurred while clearing cache.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}