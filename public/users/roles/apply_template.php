<?php
/**
 * Apply Permission Template to Role
 * Version: 4.0 - Complete security, zero-trust, and validation
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - CSRF protection with token rotation
 * - Comprehensive permission templates
 * - Role validation with tenant isolation
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
// FUNCTION: Get Template Permissions
// ============================================
function getTemplatePermissions($pdo, $template, $tenantId) {
    $template_perms = [];
    $stmt = null;
    
    // Check if permissions table exists
    $tables = [];
    $tblStmt = $pdo->query("SHOW TABLES");
    while ($row = $tblStmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    if (!in_array('permissions', $tables)) {
        return [];
    }
    
    // Build query based on template with tenant isolation
    switch ($template) {
        case 'full_access':
            $sql = "SELECT id FROM permissions WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'super_admin':
            $sql = "SELECT id FROM permissions WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'owner':
            $sql = "SELECT id FROM permissions WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'administrator':
            $sql = "SELECT id FROM permissions WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'manager':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND code NOT LIKE 'settings.%' 
                AND code NOT LIKE 'system.%'
                AND code NOT LIKE 'users.delete'
                AND code NOT LIKE 'roles.%'
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'cashier':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'pos.view' OR 
                    code LIKE 'pos.sell' OR 
                    code LIKE 'pos.hold' OR 
                    code LIKE 'pos.order' OR 
                    code LIKE 'customers.view' OR 
                    code LIKE 'customers.create' OR 
                    code LIKE 'sales.view'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'senior_cashier':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'pos.%' OR 
                    code LIKE 'sales.view' OR 
                    code LIKE 'sales.returns' OR 
                    code LIKE 'customers.%' OR 
                    code LIKE 'inventory.view' OR 
                    code LIKE 'reports.sales'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'pos_only':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (code LIKE 'pos.%' OR code LIKE 'sales.%')
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'inventory_manager':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'products.%' OR 
                    code LIKE 'inventory.%' OR 
                    code LIKE 'purchases.%' OR 
                    code LIKE 'suppliers.%' OR 
                    code LIKE 'reports.inventory'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'inventory_clerk':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'products.view' OR 
                    code LIKE 'inventory.view' OR 
                    code LIKE 'inventory.count' OR 
                    code LIKE 'suppliers.view' OR 
                    code LIKE 'purchases.view'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'reports_only':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND code LIKE 'reports.%'
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'customer_service':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'customers.%' OR 
                    code LIKE 'sales.returns' OR 
                    code LIKE 'sales.view' OR 
                    code LIKE 'pos.view'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'accountant':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'expenses.%' OR 
                    code LIKE 'reports.financial%' OR 
                    code LIKE 'reports.profit%' OR 
                    code LIKE 'reports.tax%' OR 
                    code LIKE 'sales.export' OR 
                    code LIKE 'sales.view' OR 
                    code LIKE 'customers.credit'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'hr_manager':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'users.%' OR 
                    code LIKE 'hr.%' OR 
                    code LIKE 'reports.view'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'branch_manager':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'users.view' OR 
                    code LIKE 'reports.%' OR 
                    code LIKE 'inventory.%' OR 
                    code LIKE 'branch.%' OR 
                    code LIKE 'pos.%' OR 
                    code LIKE 'sales.%' OR 
                    code LIKE 'customers.%' OR 
                    code LIKE 'staff.%'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'waiter':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'pos.view' OR 
                    code LIKE 'pos.sell' OR 
                    code LIKE 'pos.order' OR 
                    code LIKE 'pos.table' OR 
                    code LIKE 'pos.kitchen' OR 
                    code LIKE 'customers.view' OR 
                    code LIKE 'sales.view'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'kitchen_staff':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'kitchen.%' OR 
                    code LIKE 'pos.kitchen' OR 
                    code LIKE 'orders.view'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'delivery_rider':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE 'delivery.%' OR 
                    code LIKE 'orders.view' OR 
                    code LIKE 'orders.dispatch'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        case 'auditor_read_only':
            $sql = "
                SELECT id FROM permissions 
                WHERE (tenant_id = ? OR tenant_id IS NULL) 
                AND deleted_at IS NULL
                AND (
                    code LIKE '%.view' OR 
                    code LIKE '%.read' OR 
                    code LIKE 'reports.%' OR 
                    code LIKE 'sales.export'
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            break;
            
        default:
            return [];
    }
    
    if ($stmt) {
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    return [];
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
    error_log("ZERO-TRUST VIOLATION: Session mismatch in apply_template.php");
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
    error_log("Permission denied for apply_template.php. User: {$userId}, Role: {$userRole}");
    sendErrorResponse('Permission denied. You cannot apply templates.', 'permission_denied', 403);
}

// ============================================
// RATE LIMITING
// ============================================
$rateKey = 'rate_limit_apply_template_' . $userId;
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
    error_log("CSRF validation failed in apply_template.php for user: " . $userId);
    sendErrorResponse('Invalid security token. Please refresh the page.', 'csrf_invalid', 403);
}

// ============================================
// GET INPUT DATA
// ============================================
$roleId = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0);
$template = isset($_POST['template']) ? trim($_POST['template']) : '';
$action = $_POST['action'] ?? 'apply_template';

// ============================================
// VALIDATE INPUT
// ============================================
if ($roleId <= 0) {
    sendErrorResponse('Invalid role ID', 'invalid_id', 400);
}

if (empty($template)) {
    sendErrorResponse('Template name is required', 'template_required', 400);
}

// ============================================
// CHECK IF ROLE EXISTS AND BELONGS TO TENANT
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT id, name, is_system, tenant_id 
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
    $protectedRoles = ['super admin', 'administrator', 'admin', 'owner', 'manager'];
    if (in_array(strtolower($role['name']), $protectedRoles) || $role['is_system'] == 1) {
        sendErrorResponse('Cannot modify protected system roles', 'protected_role', 403);
    }
    
    // ============================================
    // GET TEMPLATE PERMISSIONS
    // ============================================
    $templatePermissions = getTemplatePermissions($pdo, $template, $tenantId);
    
    if (empty($templatePermissions)) {
        sendErrorResponse('Invalid template or no permissions found', 'invalid_template', 400);
    }
    
    // ============================================
    // START TRANSACTION
    // ============================================
    $pdo->beginTransaction();
    
    // ============================================
    // DELETE EXISTING PERMISSIONS
    // ============================================
    $stmt = $pdo->prepare("
        DELETE FROM role_permissions 
        WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)
    ");
    $stmt->execute([$roleId, $tenantId]);
    
    // ============================================
    // INSERT NEW PERMISSIONS
    // ============================================
    if (!empty($templatePermissions)) {
        $insertStmt = $pdo->prepare("
            INSERT INTO role_permissions (role_id, permission_id, tenant_id) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($templatePermissions as $permId) {
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
        'template_applied',
        $roleId,
        $role['name'],
        [
            'template' => $template,
            'permission_count' => count($templatePermissions)
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
    // SEND SUCCESS RESPONSE
    // ============================================
    sendJsonResponse([
        'success' => true,
        'message' => "Template '{$template}' applied to role '{$role['name']}' successfully",
        'role' => [
            'id' => (int) $roleId,
            'name' => $role['name']
        ],
        'template' => $template,
        'permission_count' => count($templatePermissions),
        'csrf_token' => $newCsrfToken,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in apply_template.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in apply_template.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in apply_template.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}