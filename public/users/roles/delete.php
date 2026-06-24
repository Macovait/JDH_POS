<?php
/**
 * Delete Role
 * Version: 4.1 - Fixed function arguments
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection with token rotation
 * - Protected role checking
 * - User assignment validation
 * - Transaction safety with rollback
 * - Activity logging
 * - Rate limiting
 * - Proper JSON responses
 */

// ============================================
// ERROR HANDLING
// ============================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

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

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

// ============================================
// FUNCTION: Send JSON Response
// ============================================
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// FUNCTION: Send Error Response (3 parameters only)
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
// FUNCTION: Send Detailed Error Response (for validation errors)
// ============================================
function sendDetailedError($message, $errorCode, $statusCode, $details) {
    sendJsonResponse([
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

// ============================================
// FUNCTION: Log Activity
// ============================================
function logRoleActivity($action, $roleId, $roleName, $data = [], $userId = null, $tenantId = null, $branchId = null) {
    try {
        $pdo = get_db_connection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Role {$action}: '{$roleName}' (ID: {$roleId})";
        $meta = json_encode(['role_id' => $roleId, 'role_name' => $roleName, 'data' => $data, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        return $stmt->execute([$userId, "role.{$action}", $description, $meta, $branchId, $tenantId, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log role activity: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FUNCTION: Check if Role is Protected
// ============================================
function isRoleProtected($roleName, $roleId, $pdo, $tenantId) {
    // System protected roles (case-insensitive)
    $systemProtected = [
        'super admin', 'administrator', 'admin', 'owner', 'manager'
    ];
    
    if (in_array(strtolower($roleName), $systemProtected)) {
        return true;
    }
    
    // Check if role is marked as system
    $stmt = $pdo->prepare("
        SELECT is_system FROM roles 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleId, $tenantId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $role && $role['is_system'] == 1;
}

// ============================================
// VALIDATE REQUEST METHOD
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 'method_not_allowed', 405);
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in delete_role.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

// ============================================
// AUTHENTICATION CHECK
// ============================================
if ($userId <= 0 || $tenantId <= 0 || $branchId <= 0) {
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
    error_log("Permission denied for delete_role.php. User: {$userId}, Role: {$userRole}");
    sendErrorResponse('Permission denied. You cannot delete roles.', 'permission_denied', 403);
}

// ============================================
// RATE LIMITING
// ============================================
$rateKey = 'rate_limit_delete_role_' . $userId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 10) {
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
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// ============================================
// CSRF PROTECTION
// ============================================
$submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!verify_csrf_token($submittedToken)) {
    error_log("CSRF validation failed in delete_role.php for user: " . $userId);
    sendErrorResponse('Invalid security token. Please refresh the page.', 'csrf_invalid', 403);
}

// ============================================
// GET INPUT DATA
// ============================================
$roleId = isset($_POST['role_id']) ? (int) $_POST['role_id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$action = $_POST['action'] ?? 'delete';

// ============================================
// VALIDATE INPUT
// ============================================
if ($roleId <= 0) {
    sendErrorResponse('Invalid role ID', 'invalid_id', 400);
}

// ============================================
// CHECK IF ROLE EXISTS AND BELONGS TO TENANT
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT id, name, is_system, tenant_id, level 
        FROM roles 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleId, $tenantId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        sendErrorResponse('Role not found or access denied', 'role_not_found', 404);
    }
    
    // ============================================
    // CHECK IF ROLE IS PROTECTED
    // ============================================
    if (isRoleProtected($role['name'], $roleId, $pdo, $tenantId)) {
        sendErrorResponse('Cannot delete protected system roles', 'protected_role', 403);
    }
    
    // Also check if it's one of the explicit protected roles
    $explicitProtected = ['super_admin', 'admin', 'administrator', 'owner', 'manager'];
    if (in_array(strtolower($role['name']), $explicitProtected)) {
        sendErrorResponse('Cannot delete protected system roles', 'protected_role', 403);
    }
    
    // ============================================
    // CHECK IF ROLE HAS ASSIGNED USERS
    // ============================================
    $userCount = 0;
    $userList = [];
    
    // Check tables
    $tables = [];
    $tblStmt = $pdo->query("SHOW TABLES");
    while ($row = $tblStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $hasUserRoles = in_array('user_roles', $tables);
    $hasUsersRoleId = false;
    
    if (in_array('users', $tables)) {
        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM users");
            $userCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
            $hasUsersRoleId = in_array('role_id', $userCols);
        } catch (PDOException $e) {}
    }
    
    // Count users with this role
    if ($hasUserRoles) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, GROUP_CONCAT(u.name) as user_names
            FROM user_roles ur
            JOIN users u ON ur.user_id = u.id
            WHERE ur.role_id = ? AND (ur.tenant_id = ? OR ur.tenant_id IS NULL) AND u.tenant_id = ? AND u.deleted_at IS NULL
        ");
        $stmt->execute([$roleId, $tenantId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userCount = (int) ($result['count'] ?? 0);
        if ($userCount > 0 && !empty($result['user_names'])) {
            $userList = explode(',', $result['user_names']);
        }
    } elseif ($hasUsersRoleId) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, GROUP_CONCAT(name) as user_names
            FROM users 
            WHERE role_id = ? AND tenant_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$roleId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userCount = (int) ($result['count'] ?? 0);
        if ($userCount > 0 && !empty($result['user_names'])) {
            $userList = explode(',', $result['user_names']);
        }
    }
    
    if ($userCount > 0) {
        $userNames = implode(', ', array_slice($userList, 0, 5));
        if (count($userList) > 5) {
            $userNames .= ' and ' . (count($userList) - 5) . ' more';
        }
        // Use sendDetailedError for 4 parameters
        sendDetailedError(
            "Cannot delete role assigned to {$userCount} user(s): {$userNames}",
            'role_has_users',
            409,
            ['user_count' => $userCount, 'users' => $userList]
        );
    }
    
    // ============================================
    // START TRANSACTION
    // ============================================
    $pdo->beginTransaction();
    
    // ============================================
    // SOFT DELETE ROLE
    // ============================================
    $stmt = $pdo->prepare("
        UPDATE roles 
        SET deleted_at = NOW(), updated_at = NOW() 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleId, $tenantId]);
    
    // ============================================
    // DELETE ROLE PERMISSIONS (cleanup)
    // ============================================
    $stmt = $pdo->prepare("
        DELETE FROM role_permissions 
        WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)
    ");
    $stmt->execute([$roleId, $tenantId]);
    
    // ============================================
    // DELETE USER_ROLES ENTRIES (cleanup)
    // ============================================
    if ($hasUserRoles) {
        $stmt = $pdo->prepare("
            DELETE FROM user_roles 
            WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)
        ");
        $stmt->execute([$roleId, $tenantId]);
    } elseif ($hasUsersRoleId) {
        // If users table has role_id, set to NULL
        $stmt = $pdo->prepare("
            UPDATE users 
            SET role_id = NULL, updated_at = NOW() 
            WHERE role_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$roleId, $tenantId]);
    }
    
    // ============================================
    // GENERATE NEW CSRF TOKEN
    // ============================================
    $newCsrfToken = generate_csrf_token();
    
    // ============================================
    // LOG ACTIVITY
    // ============================================
    logRoleActivity(
        'deleted',
        $roleId,
        $role['name'],
        ['level' => $role['level'] ?? 0, 'is_system' => $role['is_system'] ?? 0],
        $userId,
        $tenantId,
        $branchId
    );
    
    // ============================================
    // COMMIT TRANSACTION
    // ============================================
    $pdo->commit();
    
    // ============================================
    // SEND SUCCESS RESPONSE
    // ============================================
    sendJsonResponse([
        'success' => true,
        'message' => "Role '{$role['name']}' deleted successfully",
        'role' => [
            'id' => (int) $roleId,
            'name' => $role['name']
        ],
        'csrf_token' => $newCsrfToken,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in delete_role.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in delete_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in delete_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}