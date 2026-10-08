<?php
/**
 * Get Single Role Data (AJAX)
 * Version: 3.0 - Complete security, zero-trust, and error handling
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection (optional)
 * - Complete role data with permissions
 * - User count and statistics
 * - Proper error handling
 * - JSON response with proper headers
 */

require_once __DIR__ . '/../../../src/Security/CorsHandler.php';
// ============================================
// ERROR HANDLING
// ============================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ============================================
// BOOTSTRAP
// ============================================
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) { 
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php'; 
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();

// ============================================
// FUNCTION: Send JSON Response
// ============================================
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    \Jakababa\Security\apply_cors_headers();
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
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
// VALIDATE REQUEST METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('Method not allowed. Use GET.', 'method_not_allowed', 405);
}

// ============================================
// GET CURRENT CONTEXT
// ============================================
$userId = function_exists('get_current_user_id') ? (int) get_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
$tenantId = function_exists('get_current_tenant_id') ? (int) get_current_tenant_id() : (int) ($_SESSION['tenant_id'] ?? 0);
$branchId = function_exists('get_current_branch_id') ? (int) get_current_branch_id() : (int) ($_SESSION['branch_id'] ?? 0);
$userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';

// ============================================
// ZERO-TRUST: Session Tamper Detection
// ============================================
$sessionTenant = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$sessionUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (($sessionTenant !== null && $sessionTenant !== $tenantId) ||
    ($sessionUser !== null && $sessionUser !== $userId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in get_role.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

// ============================================
// AUTHENTICATION CHECK
// ============================================
if ($userId <= 0 || $tenantId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// ============================================
// PERMISSION CHECK
// ============================================
$canManage = false;
if (function_exists('is_super_admin') && is_super_admin()) {
    $canManage = true;
} elseif (function_exists('check_permission') && check_permission('roles.manage')) {
    $canManage = true;
} elseif (in_array(strtolower($userRole), ['admin', 'administrator', 'owner', 'super_admin'])) {
    $canManage = true;
}

if (!$canManage) {
    error_log("Permission denied for get_role.php. User: {$userId}, Role: {$userRole}");
    sendErrorResponse('Permission denied', 'permission_denied', 403);
}

// ============================================
// RATE LIMITING
// ============================================
$rateKey = 'rate_limit_get_role_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 30) {
    sendErrorResponse('Too many requests. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

// ============================================
// DATABASE CONNECTION
// ============================================
$pdo = get_db_connection();
if (!$pdo) {
    sendErrorResponse('Database connection failed', 'db_connection_error', 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// ============================================
// GET ROLE ID
// ============================================
$roleId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0);

if ($roleId <= 0) {
    sendErrorResponse('Invalid role ID', 'invalid_id', 400);
}

// ============================================
// CSRF PROTECTION (Optional - for AJAX security)
// ============================================
$csrfToken = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

// Only validate if token is provided (optional for GET requests)
if (!empty($csrfToken) && !verify_csrf_token($csrfToken)) {
    error_log("CSRF validation failed in get_role.php for user: " . $userId);
    sendErrorResponse('Invalid security token', 'csrf_invalid', 403);
}

// ============================================
// FETCH ROLE WITH TENANT ISOLATION
// ============================================
try {
    // First verify role exists and belongs to tenant
    $stmt = $pdo->prepare("
        SELECT id FROM roles 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleId, $tenantId]);
    if (!$stmt->fetch()) {
        sendErrorResponse('Role not found or access denied', 'role_not_found', 404);
    }

    // Check if tables exist
    $tables = [];
    $tblStmt = $pdo->query("SHOW TABLES");
    while ($row = $tblStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $hasRolePermissions = in_array('role_permissions', $tables);
    $hasPermissions = in_array('permissions', $tables);
    $hasUserRoles = in_array('user_roles', $tables);
    $hasUsers = in_array('users', $tables);
    
    // Build role query
    $permField = "";
    $permJoin = "";
    $permJoin2 = "";
    
    if ($hasRolePermissions && $hasPermissions) {
        $permField = ", 
            GROUP_CONCAT(DISTINCT p.code ORDER BY p.code) as permission_codes, 
            GROUP_CONCAT(DISTINCT p.id ORDER BY p.id) as permission_ids,
            GROUP_CONCAT(DISTINCT p.description ORDER BY p.code) as permission_descriptions,
            COUNT(DISTINCT p.id) as permission_count
        ";
        $permJoin = "LEFT JOIN role_permissions rp ON r.id = rp.role_id AND (rp.tenant_id = ? OR rp.tenant_id IS NULL)";
        $permJoin2 = "LEFT JOIN permissions p ON rp.permission_id = p.id AND (p.tenant_id = ? OR p.tenant_id IS NULL)";
    }
    
    // User count
    $userCountField = "";
    $userJoin = "";
    
    if ($hasUserRoles && $hasUsers) {
        $userJoin = "LEFT JOIN user_roles ur ON r.id = ur.role_id AND (ur.tenant_id = ? OR ur.tenant_id IS NULL) LEFT JOIN users u ON ur.user_id = u.id AND u.tenant_id = ?";
        $userCountField = ", COUNT(DISTINCT u.id) as user_count";
    } elseif ($hasUsers) {
        // Check if users table has role_id column
        $colStmt = $pdo->query("SHOW COLUMNS FROM users");
        $userCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('role_id', $userCols)) {
            $userJoin = "LEFT JOIN users u ON r.id = u.role_id AND u.tenant_id = ?";
            $userCountField = ", COUNT(DISTINCT u.id) as user_count";
        }
    }
    
    if (empty($userJoin)) {
        $userCountField = ", 0 as user_count";
    }
    
    // Build final SQL with proper parameter binding
    $sql = "
        SELECT 
            r.id, 
            r.name, 
            r.description, 
            r.tenant_id,
            r.is_system,
            r.level,
            r.created_at,
            r.updated_at
            $permField
            $userCountField
        FROM roles r
        $permJoin
        $permJoin2
        $userJoin
        WHERE r.id = ?
        GROUP BY r.id
    ";
    
    // Build parameters
    $params = [];
    if ($hasRolePermissions && $hasPermissions) {
        $params[] = $tenantId;
        $params[] = $tenantId;
    }
    
    if (!empty($userJoin)) {
        if ($hasUserRoles && $hasUsers) {
            $params[] = $tenantId;
            $params[] = $tenantId;
        } elseif (strpos($userJoin, 'u.tenant_id') !== false) {
            $params[] = $tenantId;
        }
    }
    
    $params[] = $roleId;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        sendErrorResponse('Role not found', 'role_not_found', 404);
    }
    
    // ============================================
    // PROCESS PERMISSIONS
    // ============================================
    $permissions = [];
    if (!empty($role['permission_codes'])) {
        $codes = explode(',', $role['permission_codes']);
        $ids = !empty($role['permission_ids']) ? explode(',', $role['permission_ids']) : [];
        $descs = !empty($role['permission_descriptions']) ? explode(',', $role['permission_descriptions']) : [];
        
        foreach ($codes as $index => $code) {
            $permissions[] = [
                'id' => isset($ids[$index]) ? (int) $ids[$index] : 0,
                'code' => trim($code),
                'description' => isset($descs[$index]) ? trim($descs[$index]) : ''
            ];
        }
    }
    
    // ============================================
    // GET USERS WITH THIS ROLE
    // ============================================
    $users = [];
    try {
        if ($hasUserRoles && $hasUsers) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.username, u.status, u.tenant_id
                FROM users u
                JOIN user_roles ur ON u.id = ur.user_id
                WHERE ur.role_id = ? AND (ur.tenant_id = ? OR ur.tenant_id IS NULL) AND u.tenant_id = ?
                LIMIT 20
            ");
            $stmt->execute([$roleId, $tenantId, $tenantId]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($hasUsers) {
            $colStmt = $pdo->query("SHOW COLUMNS FROM users");
            $userCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('role_id', $userCols)) {
                $stmt = $pdo->prepare("
                    SELECT id, name, email, username, status, tenant_id
                    FROM users
                    WHERE role_id = ? AND tenant_id = ?
                    LIMIT 20
                ");
                $stmt->execute([$roleId, $tenantId]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        // Ignore user fetch errors
    }
    
    // ============================================
    // BUILD RESPONSE
    // ============================================
    $response = [
        'success' => true,
        'role' => [
            'id' => (int) $role['id'],
            'name' => $role['name'],
            'description' => $role['description'] ?? '',
            'tenant_id' => (int) ($role['tenant_id'] ?? 0),
            'is_system' => (bool) ($role['is_system'] ?? false),
            'level' => (int) ($role['level'] ?? 0),
            'permission_count' => (int) ($role['permission_count'] ?? 0),
            'user_count' => (int) ($role['user_count'] ?? 0),
            'created_at' => $role['created_at'] ?? '',
            'updated_at' => $role['updated_at'] ?? '',
            'permissions' => $permissions,
            'users' => array_map(function($user) {
                return [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'] ?? '',
                    'username' => $user['username'] ?? '',
                    'status' => (int) ($user['status'] ?? 1)
                ];
            }, $users),
            'permission_ids' => array_column($permissions, 'id'),
            'permission_codes' => array_column($permissions, 'code')
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    sendJsonResponse($response);
    
} catch (PDOException $e) {
    error_log("Database error in get_role.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in get_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in get_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}