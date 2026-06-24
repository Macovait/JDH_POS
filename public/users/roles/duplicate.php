<?php
/**
 * Duplicate Role
 * Version: 4.0 - Complete security, zero-trust, and validation
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection with token rotation
 * - Comprehensive input validation
 * - Role name uniqueness check
 * - Full permission copying
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in duplicate_role.php");
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
    error_log("Permission denied for duplicate_role.php. User: {$userId}, Role: {$userRole}");
    sendErrorResponse('Permission denied. You cannot duplicate roles.', 'permission_denied', 403);
}

// ============================================
// RATE LIMITING
// ============================================
$rateKey = 'rate_limit_duplicate_role_' . $userId;
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
    error_log("CSRF validation failed in duplicate_role.php for user: " . $userId);
    sendErrorResponse('Invalid security token. Please refresh the page.', 'csrf_invalid', 403);
}

// ============================================
// GET INPUT DATA
// ============================================
$roleId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$newName = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
$action = $_POST['action'] ?? 'duplicate';

// ============================================
// VALIDATE INPUT
// ============================================
if ($roleId <= 0) {
    sendErrorResponse('Invalid role ID to duplicate', 'invalid_id', 400);
}

if (empty($newName)) {
    sendErrorResponse('New role name is required', 'name_required', 400);
}

if (strlen($newName) > 100) {
    sendErrorResponse('Role name cannot exceed 100 characters', 'name_too_long', 400);
}

// ============================================
// CHECK IF SOURCE ROLE EXISTS AND BELONGS TO TENANT
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT id, name, description, is_system, tenant_id, level 
        FROM roles 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleId, $tenantId]);
    $sourceRole = $stmt->fetch();
    
    if (!$sourceRole) {
        sendErrorResponse('Source role not found or access denied', 'source_not_found', 404);
    }
    
    // ============================================
    // CHECK IF NEW ROLE NAME ALREADY EXISTS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT id FROM roles 
        WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$newName, $tenantId]);
    if ($stmt->fetch()) {
        sendErrorResponse('A role with this name already exists', 'duplicate_name', 409);
    }
    
    // ============================================
    // GET SOURCE PERMISSIONS
    // ============================================
    $stmt = $pdo->prepare("
        SELECT permission_id FROM role_permissions 
        WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)
    ");
    $stmt->execute([$roleId, $tenantId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $permissionCount = count($permissions);
    
    // ============================================
    // START TRANSACTION
    // ============================================
    $pdo->beginTransaction();
    
    // ============================================
    // CREATE NEW ROLE
    // ============================================
    $description = $sourceRole['description'] ?: "Copy of {$sourceRole['name']}";
    $level = (int) ($sourceRole['level'] ?? 0);
    
    $stmt = $pdo->prepare("
        INSERT INTO roles (name, description, tenant_id, is_system, level, deleted_at) 
        VALUES (?, ?, ?, 0, ?, NULL)
    ");
    $stmt->execute([$newName, $description, $tenantId, $level]);
    $newRoleId = (int) $pdo->lastInsertId();
    
    // ============================================
    // COPY PERMISSIONS
    // ============================================
    if (!empty($permissions)) {
        $insertStmt = $pdo->prepare("
            INSERT INTO role_permissions (role_id, permission_id, tenant_id) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($permissions as $permId) {
            $insertStmt->execute([$newRoleId, $permId, $tenantId]);
        }
    }
    
    // ============================================
    // GENERATE NEW CSRF TOKEN
    // ============================================
    $newCsrfToken = generate_csrf_token();
    
    // ============================================
    // LOG ACTIVITY
    // ============================================
    logRoleActivity(
        'duplicated',
        $newRoleId,
        $newName,
        [
            'source_role_id' => $roleId,
            'source_role_name' => $sourceRole['name'],
            'permission_count' => $permissionCount
        ],
        $userId,
        $tenantId,
        $branchId
    );
    
    // ============================================
    // COMMIT TRANSACTION
    // ============================================
    $pdo->commit();
    
    // ============================================
    // FETCH NEW ROLE DATA
    // ============================================
    $stmt = $pdo->prepare("
        SELECT 
            r.id, r.name, r.description, r.is_system, r.tenant_id, r.level,
            GROUP_CONCAT(DISTINCT p.id) as permission_ids,
            COUNT(DISTINCT p.id) as permission_count
        FROM roles r
        LEFT JOIN role_permissions rp ON r.id = rp.role_id AND (rp.tenant_id = ? OR rp.tenant_id IS NULL)
        LEFT JOIN permissions p ON rp.permission_id = p.id AND (p.tenant_id = ? OR p.tenant_id IS NULL)
        WHERE r.id = ? AND (r.tenant_id = ? OR r.tenant_id IS NULL)
        GROUP BY r.id
    ");
    $stmt->execute([$tenantId, $tenantId, $newRoleId, $tenantId]);
    $newRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // SEND SUCCESS RESPONSE
    // ============================================
    sendJsonResponse([
        'success' => true,
        'message' => "Role '{$newName}' duplicated successfully",
        'role' => [
            'id' => (int) $newRoleId,
            'name' => $newName,
            'description' => $description,
            'is_system' => (bool) ($newRole['is_system'] ?? false),
            'tenant_id' => (int) ($newRole['tenant_id'] ?? 0),
            'level' => (int) ($newRole['level'] ?? 0),
            'permission_count' => (int) ($newRole['permission_count'] ?? 0),
            'permission_ids' => !empty($newRole['permission_ids']) ? 
                array_map('intval', explode(',', $newRole['permission_ids'])) : []
        ],
        'source_role' => [
            'id' => (int) $sourceRole['id'],
            'name' => $sourceRole['name']
        ],
        'csrf_token' => $newCsrfToken,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in duplicate_role.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in duplicate_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in duplicate_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}