<?php
/**
 * QUICK USER SWITCH - AJAX endpoint for POS
 * Switches the active cashier session using a PIN without a full logout
 * 
 * Version: 5.1 - Fixed function parameter count
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Multi-level authentication (PIN, password)
 * - Session preservation (cart, branch, settings)
 * - Comprehensive audit logging
 * - User activity tracking
 * - Switch limits and cooldown
 * - Permission validation
 * - Full PHP 8.0+ type declarations
 */

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
// TYPE DECLARATIONS
// ============================================

/**
 * Send JSON response with proper headers
 * 
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 * @return void
 */
function sendJsonResponse(array $data, int $statusCode = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response (3 parameters only)
 * 
 * @param string $message Error message
 * @param string $errorCode Error code
 * @param int $statusCode HTTP status code
 * @return void
 */
function sendErrorResponse(string $message, string $errorCode = 'unknown_error', int $statusCode = 400): void {
    sendJsonResponse([
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

/**
 * Send validation error with details (4 parameters)
 * 
 * @param string $message Error message
 * @param string $errorCode Error code
 * @param int $statusCode HTTP status code
 * @param array $details Additional details
 * @return void
 */
function sendValidationError(string $message, string $errorCode, int $statusCode, array $details): void {
    sendJsonResponse([
        'success' => false,
        'error' => $errorCode,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ], $statusCode);
}

/**
 * Log user switch activity
 * 
 * @param PDO $pdo Database connection
 * @param int $fromUserId Original user ID
 * @param int $toUserId Target user ID
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param string $ipAddress IP address
 * @return bool
 */
function logUserSwitch(
    PDO $pdo,
    int $fromUserId,
    int $toUserId,
    int $tenantId,
    int $branchId,
    string $ipAddress
): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, 'user.switch', ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "User switch: {$fromUserId} → {$toUserId}";
        $meta = json_encode([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'branch_id' => $branchId,
            'tenant_id' => $tenantId,
            'ip' => $ipAddress
        ]);
        
        return $stmt->execute([
            $toUserId,
            $description,
            $meta,
            $branchId,
            $tenantId,
            $ipAddress
        ]);
    } catch (Exception $e) {
        error_log("Failed to log user switch: " . $e->getMessage());
        return false;
    }
}

/**
 * Log user switch attempt (failed)
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID attempting switch
 * @param int $targetUserId Target user ID
 * @param int $tenantId Tenant ID
 * @param string $reason Failure reason
 * @param string $ipAddress IP address
 * @return bool
 */
function logFailedSwitch(
    PDO $pdo,
    int $userId,
    int $targetUserId,
    int $tenantId,
    string $reason,
    string $ipAddress
): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, 'user.switch.failed', ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Failed user switch attempt: {$userId} → {$targetUserId}";
        $meta = json_encode([
            'from_user_id' => $userId,
            'to_user_id' => $targetUserId,
            'reason' => $reason,
            'ip' => $ipAddress
        ]);
        
        return $stmt->execute([
            $userId,
            $description,
            $meta,
            null,
            $tenantId,
            $ipAddress
        ]);
    } catch (Exception $e) {
        error_log("Failed to log switch attempt: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user details by ID with role info
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $tenantId Tenant ID
 * @return array|false User data or false
 */
function getUserDetails(PDO $pdo, int $userId, int $tenantId): array|false {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.username,
                u.role_id,
                u.branch_id,
                u.password_hash,
                u.pin,
                u.is_active,
                u.last_login,
                u.status,
                r.name as role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.id = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId, $tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("User details error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user switch is allowed
 * 
 * @param array $fromUser Current user
 * @param array $toUser Target user
 * @param array $settings System settings
 * @return array Validation result
 */
function validateUserSwitch(array $fromUser, array $toUser, array $settings): array {
    $errors = [];
    $warnings = [];
    
    // Check if target user is active
    if (isset($toUser['status']) && $toUser['status'] != 1) {
        $errors[] = "Target user is inactive";
    }
    
    // Check if target user is active (alternative field)
    if (isset($toUser['is_active']) && $toUser['is_active'] != 1) {
        $errors[] = "Target user is inactive";
    }
    
    // Prevent switching to self
    if ($fromUser['id'] == $toUser['id']) {
        $warnings[] = "Switching to yourself has no effect";
    }
    
    // Check if target user has required permissions
    $allowedRoles = ['admin', 'administrator', 'manager', 'owner', 'super_admin', 'cashier', 'inventory'];
    $targetRole = strtolower($toUser['role_name'] ?? 'cashier');
    if (!in_array($targetRole, $allowedRoles)) {
        $warnings[] = "Target user role '{$targetRole}' may not have all POS permissions";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings,
        'target_role' => $targetRole
    ];
}

/**
 * Get system settings for user switching
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @return array Settings
 */
function getSwitchSettings(PDO $pdo, int $tenantId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM settings 
            WHERE tenant_id = ? 
            AND setting_key IN (
                'max_user_switches_per_day',
                'require_switch_reason',
                'switch_requires_approval',
                'preserve_cart_on_switch'
            )
        ");
        $stmt->execute([$tenantId]);
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get user switch count for today
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $tenantId Tenant ID
 * @return int Switch count
 */
function getSwitchCountToday(PDO $pdo, int $userId, int $tenantId): int {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as switch_count
            FROM activity_logs
            WHERE user_id = ?
                AND tenant_id = ?
                AND action = 'user.switch'
                AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$userId, $tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['switch_count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

// === 1. Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use POST.', 'method_not_allowed', 405);
}

// === 2. Get Current Context ===
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$tenantId = (int) ($_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$userRole = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

// Session tamper detection
$sessionTenant = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$sessionUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (($sessionTenant !== null && $sessionTenant !== $tenantId) ||
    ($sessionUser !== null && $sessionUser !== $currentUserId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in quick_switch_user.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($currentUserId <= 0 || $tenantId <= 0 || $branchId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Rate Limiting ===
$rateKey = 'rate_limit_switch_' . $currentUserId;
$rateData = $_SESSION[$rateKey] ?? ['count' => 0, 'reset' => time() + 60];

if (time() > $rateData['reset']) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

$rateData['count']++;

if ($rateData['count'] > 5) {
    sendErrorResponse('Too many switch attempts. Please wait a moment.', 'rate_limited', 429);
}

$_SESSION[$rateKey] = $rateData;

// === 4. Database Connection ===
require_once __DIR__ . '/../../src/db.php';
/** @var PDO $pdo */
$pdo = get_db_connection();

if (!$pdo instanceof PDO) {
    sendErrorResponse('Database connection failed', 'db_connection_error', 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// === 5. CSRF Protection ===
$submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (empty($sessionToken) || empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
    error_log("CSRF validation failed in quick_switch_user.php for user: " . $currentUserId);
    sendErrorResponse('Invalid security token', 'csrf_invalid', 403);
}

// === 6. Get Input ===
$targetUserId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$pin = (string) trim($_POST['pin'] ?? '');
$reason = (string) trim($_POST['reason'] ?? '');
$preserveCart = isset($_POST['preserve_cart']) ? (bool) $_POST['preserve_cart'] : true;

// === 7. Validate Input ===
if ($targetUserId <= 0) {
    sendErrorResponse('User ID is required', 'user_id_required', 400);
}

if (empty($pin)) {
    sendErrorResponse('PIN is required', 'pin_required', 400);
}

// === 8. Get Current User Details ===
$currentUser = getUserDetails($pdo, $currentUserId, $tenantId);
if (!$currentUser) {
    sendErrorResponse('Current user not found', 'current_user_not_found', 404);
}

// === 9. Get Target User Details ===
$targetUser = getUserDetails($pdo, $targetUserId, $tenantId);
if (!$targetUser) {
    logFailedSwitch($pdo, $currentUserId, $targetUserId, $tenantId, 'User not found', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    sendErrorResponse('User not found', 'user_not_found', 404);
}

// === 10. Verify PIN ===
$pinVerified = false;
$authMethod = '';

// Check dedicated PIN column
if (!empty($targetUser['pin']) && $targetUser['pin'] === $pin) {
    $pinVerified = true;
    $authMethod = 'pin';
} 
// Check PIN against password_hash (fallback)
elseif (!empty($targetUser['password_hash']) && password_verify($pin, $targetUser['password_hash'])) {
    $pinVerified = true;
    $authMethod = 'password';
}

if (!$pinVerified) {
    logFailedSwitch($pdo, $currentUserId, $targetUserId, $tenantId, 'Invalid PIN', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    sendErrorResponse('Incorrect PIN or password', 'invalid_credentials', 403);
}

// === 11. Validate Switch ===
$settings = getSwitchSettings($pdo, $tenantId);
$validation = validateUserSwitch($currentUser, $targetUser, $settings);

if (!$validation['valid']) {
    logFailedSwitch($pdo, $currentUserId, $targetUserId, $tenantId, implode(', ', $validation['errors']), $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    // Use validation error with details
    sendJsonResponse([
        'success' => false,
        'error' => 'validation_failed',
        'message' => 'User switch validation failed: ' . implode(', ', $validation['errors']),
        'validation_errors' => $validation['errors'],
        'timestamp' => date('Y-m-d H:i:s')
    ], 400);
}

// === 12. Check Daily Switch Limit ===
$maxSwitchesPerDay = isset($settings['max_user_switches_per_day']) ? (int)$settings['max_user_switches_per_day'] : 0;
if ($maxSwitchesPerDay > 0) {
    $switchCountToday = getSwitchCountToday($pdo, $currentUserId, $tenantId);
    if ($switchCountToday >= $maxSwitchesPerDay) {
        logFailedSwitch($pdo, $currentUserId, $targetUserId, $tenantId, 'Daily switch limit reached', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        sendErrorResponse(
            'Daily user switch limit reached (' . $maxSwitchesPerDay . ' switches)',
            'switch_limit_reached',
            429
        );
    }
}

// === 13. Check if Reason is Required ===
$requireReason = isset($settings['require_switch_reason']) && $settings['require_switch_reason'] == '1';
if ($requireReason && empty($reason)) {
    sendErrorResponse('Reason for user switch is required', 'reason_required', 400);
}

// === 14. Preserve Cart Data ===
$savedCart = [];
if ($preserveCart && isset($_SESSION['cart'])) {
    $savedCart = $_SESSION['cart'];
}

// === 15. Perform User Switch ===
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

try {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Clear old session data (except preserved items)
    $oldSession = $_SESSION;
    
    // Set new user data
    $_SESSION['user_id'] = (int) $targetUser['id'];
    $_SESSION['user_name'] = $targetUser['name'];
    $_SESSION['username'] = $targetUser['username'] ?? $targetUser['email'];
    $_SESSION['user_email'] = $targetUser['email'] ?? '';
    $_SESSION['role'] = strtolower($validation['target_role']);
    $_SESSION['user_role'] = strtolower($validation['target_role']);
    $_SESSION['role_id'] = (int) ($targetUser['role_id'] ?? 0);
    $_SESSION['branch_id'] = $targetUser['branch_id'] ? (int) $targetUser['branch_id'] : $branchId;
    $_SESSION['user'] = [
        'id' => (int) $targetUser['id'],
        'name' => $targetUser['name'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['role'],
        'role_id' => $_SESSION['role_id'],
        'tenant_id' => $tenantId,
        'branch_id' => $_SESSION['branch_id'],
    ];
    
    // Generate new CSRF token
    generate_csrf_token();
    $_SESSION['csrf_token_time'] = time();
    
    // Preserve cart if enabled
    if ($preserveCart && !empty($savedCart)) {
        $_SESSION['cart'] = $savedCart;
    }
    
    // Preserve tenant context
    $_SESSION['tenant_id'] = $tenantId;
    $_SESSION['company_id'] = $tenantId;
    
    // Preserve any other important session data
    if (isset($oldSession['branch_name'])) {
        $_SESSION['branch_name'] = $oldSession['branch_name'];
    }
    if (isset($oldSession['current_branch'])) {
        $_SESSION['current_branch'] = $oldSession['current_branch'];
    }
    
    // Update last login timestamp
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
    $stmt->execute([$ipAddress, $targetUser['id']]);
    
    // Log successful switch
    logUserSwitch($pdo, $currentUserId, $targetUserId, $tenantId, $branchId, $ipAddress);
    
    // === 16. Build Response ===
    $response = [
        'success' => true,
        'message' => 'User switched successfully',
        'user' => [
            'id' => (int) $targetUser['id'],
            'name' => $targetUser['name'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['role'],
            'branch_id' => (int) $_SESSION['branch_id']
        ],
        'cart_preserved' => $preserveCart && !empty($savedCart),
        'auth_method' => $authMethod,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Add warnings if any
    if (!empty($validation['warnings'])) {
        $response['warnings'] = $validation['warnings'];
    }
    
    sendJsonResponse($response);
    
} catch (Exception $e) {
    error_log("Error during user switch: " . $e->getMessage());
    sendErrorResponse('Failed to switch user: ' . $e->getMessage(), 'switch_error', 500);
}

} catch (PDOException $e) {
    error_log("Database error in quick_switch_user.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    error_log("Error in quick_switch_user.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    error_log("Fatal error in quick_switch_user.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}
