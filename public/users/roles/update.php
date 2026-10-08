<?php
/**
 * Update Existing Role
 * Version: 4.0 - Complete security, zero-trust, and validation
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection with token rotation
 * - Comprehensive input validation
 * - Role name uniqueness check
 * - Permission assignment validation
 * - Transaction safety with rollback
 * - Activity logging
 * - Rate limiting
 * - Proper JSON responses
 */

require_once __DIR__ . '/../../../src/Security/CorsHandler.php';
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
// FUNCTION: Validate Permission IDs
// ============================================
function validatePermissions($pdo, $permissions, $tenantId) {
    if (empty($permissions)) {
        return ['valid' => true, 'errors' => []];
    }
    
    $placeholders = implode(',', array_fill(0, count($permissions), '?'));
    $stmt = $pdo->prepare("
        SELECT id FROM permissions 
        WHERE id IN ({$placeholders}) AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $params = array_merge($permissions, [$tenantId]);
    $stmt->execute($params);
    $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $invalidIds = array_diff($permissions, $validIds);
    
    return [
        'valid' => empty($invalidIds),
        'errors' => $invalidIds,
        'valid_ids' => $validIds
    ];
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in update_role.php");
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
    error_log("Permission denied for update_role.php. User: {$userId}, Role: {$userRole}");
    sendErrorResponse('Permission denied. You cannot manage roles.', 'permission_denied', 403);
}

// ============================================
// RATE LIMITING
// ============================================
$rateKey = 'rate_limit_update_role_' . $userId;
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
    error_log("CSRF validation failed in update_role.php for user: " . $userId);
    sendErrorResponse('Invalid security token. Please refresh the page.', 'csrf_invalid', 403);
}

// ============================================
// GET INPUT DATA
// ============================================
$roleId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$roleName = isset($_POST['name']) ? trim($_POST['name']) : '';
$roleDescription = isset($_POST['description']) ? trim($_POST['description']) : '';
$permissions = $_POST['perms'] ?? $_POST['permissions'] ?? [];
$action = $_POST['action'] ?? 'update';

// ============================================
// VALIDATE INPUT
// ============================================
if ($roleId <= 0) {
    sendErrorResponse('Invalid role ID', 'invalid_id', 400);
}

if (empty($roleName)) {
    sendErrorResponse('Role name is required', 'name_required', 400);
}

if (strlen($roleName) > 100) {
    sendErrorResponse('Role name cannot exceed 100 characters', 'name_too_long', 400);
}

// ============================================
// CHECK IF ROLE EXISTS AND BELONGS TO TENANT
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT id, name, is_system, tenant_id FROM roles 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleId, $tenantId]);
    $existingRole = $stmt->fetch();
    
    if (!$existingRole) {
        sendErrorResponse('Role not found or access denied', 'role_not_found', 404);
    }
    
    // Check if role is system-protected
    if ($existingRole['is_system'] && !is_super_admin()) {
        sendErrorResponse('System roles cannot be modified', 'system_role_protected', 403);
    }
    
    // ============================================
    // CHECK DUPLICATE ROLE NAME
    // ============================================
    $stmt = $pdo->prepare("
        SELECT id FROM roles 
        WHERE name = ? AND id != ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleName, $roleId, $tenantId]);
    if ($stmt->fetch()) {
        sendErrorResponse('A role with this name already exists', 'duplicate_name', 409);
    }
    
    // ============================================
    // VALIDATE PERMISSIONS
    // ============================================
    // Ensure permissions is an array
    if (!is_array($permissions)) {
        $permissions = [];
    }
    
    // Filter and cast to integers
    $permissions = array_map('intval', array_filter($permissions, function($val) {
        return is_numeric($val) && $val > 0;
    }));
    
    // Validate permissions exist
    $permValidation = validatePermissions($pdo, $permissions, $tenantId);
    if (!$permValidation['valid']) {
        sendErrorResponse(
            'Invalid permissions selected',
            'invalid_permissions',
            400,
            ['invalid_permission_ids' => $permValidation['errors']]
        );
    }
    
    // ============================================
    // START TRANSACTION
    // ============================================
    $pdo->beginTransaction();
    
    // ============================================
    // UPDATE ROLE
    // ============================================
    $stmt = $pdo->prepare("
        UPDATE roles 
        SET name = ?, description = ?, updated_at = NOW() 
        WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL
    ");
    $stmt->execute([$roleName, $roleDescription, $roleId, $tenantId]);
    
    // ============================================
    // UPDATE PERMISSIONS
    // ============================================
    // Delete existing permissions
    $stmt = $pdo->prepare("
        DELETE FROM role_permissions 
        WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)
    ");
    $stmt->execute([$roleId, $tenantId]);
    
    // Insert new permissions
    if (!empty($permissions)) {
        $insertStmt = $pdo->prepare("
            INSERT INTO role_permissions (role_id, permission_id, tenant_id) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($permissions as $permId) {
            $insertStmt->execute([$roleId, $permId, $tenantId]);
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
        'updated',
        $roleId,
        $roleName,
        [
            'old_name' => $existingRole['name'],
            'new_description' => $roleDescription,
            'permission_count' => count($permissions),
            'permissions' => $permissions
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
    // FETCH UPDATED ROLE DATA
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
    $stmt->execute([$tenantId, $tenantId, $roleId, $tenantId]);
    $updatedRole = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // SEND SUCCESS RESPONSE
    // ============================================
    sendJsonResponse([
        'success' => true,
        'message' => "Role '{$roleName}' updated successfully",
        'role' => [
            'id' => (int) $roleId,
            'name' => $roleName,
            'description' => $roleDescription,
            'is_system' => (bool) ($updatedRole['is_system'] ?? false),
            'tenant_id' => (int) ($updatedRole['tenant_id'] ?? 0),
            'level' => (int) ($updatedRole['level'] ?? 0),
            'permission_count' => (int) ($updatedRole['permission_count'] ?? 0),
            'permission_ids' => !empty($updatedRole['permission_ids']) ? 
                array_map('intval', explode(',', $updatedRole['permission_ids'])) : []
        ],
        'csrf_token' => $newCsrfToken,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in update_role.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in update_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in update_role.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}