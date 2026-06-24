<?php
/**
 * SHIFT MANAGEMENT - AJAX endpoint for POS
 * Version: 4.2 - Fixed close shift validation
 * 
 * Features:
 * - Zero-trust tenant isolation
 * - Complete shift CRUD operations
 * - Live sales tracking during shift
 * - Cash reconciliation with proper validation
 * - Comprehensive error handling
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
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
    
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
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
 * Log shift activity
 * 
 * @param string $action Action performed
 * @param int $shiftId Shift ID
 * @param array $data Additional data
 * @param int $userId User ID
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @return bool
 */
function logShiftActivity(
    string $action,
    int $shiftId,
    array $data = [],
    int $userId = 0,
    int $tenantId = 0,
    int $branchId = 0
): bool {
    try {
        require_once __DIR__ . '/../../src/db.php';
        /** @var PDO $pdo */
        $pdo = get_db_connection();
        
        if (!$pdo instanceof PDO) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, description, meta, branch_id, tenant_id, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $description = "Shift #{$shiftId} - {$action}";
        $meta = json_encode(['shift_id' => $shiftId, 'data' => $data, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        return $stmt->execute([$userId, "shift.{$action}", $description, $meta, $branchId, $tenantId, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log shift activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current open shift with live totals
 * 
 * @param PDO $pdo Database connection
 * @param int $tenantId Tenant ID
 * @param int $branchId Branch ID
 * @param int $userId User ID
 * @return array|null Shift data or null
 */
function getOpenShift(PDO $pdo, int $tenantId, int $branchId, int $userId): ?array {
    try {
        // Get the open shift
        $stmt = $pdo->prepare("
            SELECT * FROM register_sessions 
            WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND status = 'open' 
            ORDER BY opened_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $branchId, $userId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shift) {
            return null;
        }
        
        // Get live sales totals for this shift
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(total), 0) as total_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) as card_sales,
                COALESCE(SUM(CASE WHEN payment_method = 'mpesa' THEN total ELSE 0 END), 0) as mpesa_sales,
                COALESCE(SUM(CASE WHEN payment_method NOT IN ('cash', 'card', 'mpesa') THEN total ELSE 0 END), 0) as other_sales,
                COUNT(*) as sale_count,
                COALESCE(SUM(discount), 0) as total_discounts,
                COALESCE(SUM(tax), 0) as total_tax
            FROM sales
            WHERE tenant_id = ? AND branch_id = ? AND user_id = ? 
              AND status = 'completed' AND voided = 0
              AND created_at >= ?
        ");
        $stmt->execute([$tenantId, $branchId, $userId, $shift['opened_at']]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate expected cash
        $expectedCash = (float) $shift['opening_cash'] + (float) ($totals['cash_sales'] ?? 0);
        
        return [
            'id' => (int) $shift['id'],
            'tenant_id' => (int) $shift['tenant_id'],
            'branch_id' => (int) $shift['branch_id'],
            'user_id' => (int) $shift['user_id'],
            'opening_cash' => (float) $shift['opening_cash'],
            'closing_cash' => isset($shift['closing_cash']) ? (float) $shift['closing_cash'] : null,
            'expected_cash' => isset($shift['expected_cash']) ? (float) $shift['expected_cash'] : null,
            'cash_difference' => isset($shift['cash_difference']) ? (float) $shift['cash_difference'] : null,
            'total_sales' => (float) ($totals['total_sales'] ?? 0),
            'total_cash_sales' => (float) ($totals['cash_sales'] ?? 0),
            'total_card_sales' => (float) ($totals['card_sales'] ?? 0),
            'total_mpesa_sales' => (float) ($totals['mpesa_sales'] ?? 0),
            'total_other_sales' => (float) ($totals['other_sales'] ?? 0),
            'sale_count' => (int) ($totals['sale_count'] ?? 0),
            'total_discounts' => (float) ($totals['total_discounts'] ?? 0),
            'total_tax' => (float) ($totals['total_tax'] ?? 0),
            'expected_cash_total' => $expectedCash,
            'notes' => $shift['notes'] ?? '',
            'status' => $shift['status'],
            'opened_at' => $shift['opened_at'],
            'closed_at' => $shift['closed_at'] ?? null,
            'live_totals' => $totals
        ];
    } catch (Exception $e) {
        error_log("Error getting open shift: " . $e->getMessage());
        return null;
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

// === 1. Validate Request Method ===
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed. Use GET or POST.', 'method_not_allowed', 405);
}

// === 2. Get Current Context ===
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$tenantId = (int) ($_SESSION['tenant_id'] ?? $_SESSION['company_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$userRole = (string) ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

// Session tamper detection
$sessionTenant = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
$sessionUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (($sessionTenant !== null && $sessionTenant !== $tenantId) ||
    ($sessionUser !== null && $sessionUser !== $userId)) {
    error_log("ZERO-TRUST VIOLATION: Session mismatch in shift.php");
    sendErrorResponse('Session validation failed', 'session_mismatch', 401);
}

if ($userId <= 0 || $tenantId <= 0 || $branchId <= 0) {
    sendErrorResponse('Unauthorized access', 'unauthorized', 401);
}

// === 3. Database Connection ===
require_once __DIR__ . '/../../src/db.php';
/** @var PDO $pdo */
$pdo = get_db_connection();

if (!$pdo instanceof PDO) {
    sendErrorResponse('Database connection failed', 'db_connection_error', 500);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// === 4. CSRF Protection (for POST requests) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if (empty($sessionToken) || empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
        error_log("CSRF validation failed in shift.php for user: " . $userId);
        sendErrorResponse('Invalid security token', 'csrf_invalid', 403);
    }
}

// === 5. Get Action ===
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    sendErrorResponse('Action is required', 'action_required', 400);
}

// === 6. Handle Actions ===

// 6a. Open Shift
if ($action === 'open') {
    $openingCash = isset($_POST['opening_cash']) ? (float) $_POST['opening_cash'] : 0.00;
    
    if ($openingCash < 0) {
        sendErrorResponse('Opening cash cannot be negative', 'invalid_opening_cash', 400);
    }
    
    // Check for existing open shift
    $stmt = $pdo->prepare("SELECT id FROM register_sessions WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND status = 'open'");
    $stmt->execute([$tenantId, $branchId, $userId]);
    if ($stmt->fetch()) {
        sendErrorResponse('A shift is already open for this user and branch', 'shift_already_open', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO register_sessions 
            (tenant_id, branch_id, user_id, opening_cash, status, opened_at, created_at) 
            VALUES (?, ?, ?, ?, 'open', NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $branchId, $userId, $openingCash]);
        $shiftId = (int) $pdo->lastInsertId();
        
        logShiftActivity('opened', $shiftId, ['opening_cash' => $openingCash], $userId, $tenantId, $branchId);
        
        $pdo->commit();
        
        sendJsonResponse([
            'success' => true,
            'shift_id' => $shiftId,
            'message' => 'Shift opened successfully',
            'opening_cash' => $openingCash,
            'opened_at' => date('Y-m-d H:i:s'),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error opening shift: " . $e->getMessage());
        sendErrorResponse('Failed to open shift', 'open_error', 500);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error opening shift: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 6b. Close Shift
elseif ($action === 'close') {
    $closingCash = isset($_POST['closing_cash']) ? (float) $_POST['closing_cash'] : 0.00;
    $notes = isset($_POST['notes']) ? (string) trim($_POST['notes']) : '';
    
    if ($closingCash < 0) {
        sendErrorResponse('Closing cash cannot be negative', 'invalid_closing_cash', 400);
    }
    
    // Get current open shift
    $stmt = $pdo->prepare("
        SELECT * FROM register_sessions 
        WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND status = 'open' 
        ORDER BY opened_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $branchId, $userId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shift) {
        sendErrorResponse('No open shift found', 'no_open_shift', 404);
    }
    
    // Get cash sales for this shift
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as cash_sales
        FROM sales
        WHERE tenant_id = ? AND branch_id = ? AND user_id = ? 
          AND status = 'completed' AND voided = 0
          AND payment_method = 'cash'
          AND created_at >= ?
    ");
    $stmt->execute([$tenantId, $branchId, $userId, $shift['opened_at']]);
    $cashSales = (float) $stmt->fetchColumn();
    
    // Get all sales for this shift
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as total_sales,
            COUNT(*) as sale_count
        FROM sales
        WHERE tenant_id = ? AND branch_id = ? AND user_id = ? 
          AND status = 'completed' AND voided = 0
          AND created_at >= ?
    ");
    $stmt->execute([$tenantId, $branchId, $userId, $shift['opened_at']]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $expectedCash = (float) $shift['opening_cash'] + $cashSales;
    $cashDiff = $closingCash - $expectedCash;
    $isBalanced = abs($cashDiff) <= 0.50;
    
    try {
        $pdo->beginTransaction();
        
        // Update the shift with closing data
        $stmt = $pdo->prepare("
            UPDATE register_sessions 
            SET 
                closing_cash = ?, 
                expected_cash = ?, 
                cash_difference = ?, 
                total_sales = ?,
                total_cash_sales = ?,
                sale_count = ?,
                notes = CONCAT(COALESCE(notes, ''), ' ', ?),
                status = 'closed', 
                closed_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ? AND status = 'open'
        ");
        $stmt->execute([
            $closingCash, 
            $expectedCash, 
            $cashDiff,
            $totals['total_sales'] ?? 0,
            $cashSales,
            $totals['sale_count'] ?? 0,
            $notes,
            $shift['id'], 
            $tenantId
        ]);
        
        // Log activity
        logShiftActivity(
            'closed', 
            $shift['id'], 
            [
                'opening_cash' => $shift['opening_cash'],
                'closing_cash' => $closingCash,
                'expected_cash' => $expectedCash,
                'cash_difference' => $cashDiff,
                'total_sales' => $totals['total_sales'] ?? 0,
                'sale_count' => $totals['sale_count'] ?? 0,
                'is_balanced' => $isBalanced
            ], 
            $userId, 
            $tenantId, 
            $branchId
        );
        
        $pdo->commit();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Shift closed successfully' . ($isBalanced ? '' : ' with cash discrepancy'),
            'shift_id' => (int) $shift['id'],
            'opening_cash' => (float) $shift['opening_cash'],
            'closing_cash' => $closingCash,
            'expected_cash' => $expectedCash,
            'cash_difference' => $cashDiff,
            'total_sales' => (float) ($totals['total_sales'] ?? 0),
            'cash_sales' => $cashSales,
            'sale_count' => (int) ($totals['sale_count'] ?? 0),
            'is_balanced' => $isBalanced,
            'duration_minutes' => (int) ((time() - strtotime($shift['opened_at'])) / 60),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error closing shift: " . $e->getMessage());
        sendErrorResponse('Failed to close shift: ' . $e->getMessage(), 'close_error', 500);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error closing shift: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 6c. Get Shift Status
elseif ($action === 'status') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM register_sessions 
            WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND status = 'open' 
            ORDER BY opened_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $branchId, $userId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shift) {
            // Get live totals
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(total), 0) as total_sales,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash_sales,
                    COUNT(*) as sale_count
                FROM sales
                WHERE tenant_id = ? AND branch_id = ? AND user_id = ? 
                  AND status = 'completed' AND voided = 0
                  AND created_at >= ?
            ");
            $stmt->execute([$tenantId, $branchId, $userId, $shift['opened_at']]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $expectedCash = (float) $shift['opening_cash'] + (float) ($totals['cash_sales'] ?? 0);
            
            sendJsonResponse([
                'success' => true,
                'has_open_shift' => true,
                'shift' => [
                    'id' => (int) $shift['id'],
                    'opening_cash' => (float) $shift['opening_cash'],
                    'opened_at' => $shift['opened_at'],
                    'status' => $shift['status'],
                    'total_sales' => (float) ($totals['total_sales'] ?? 0),
                    'cash_sales' => (float) ($totals['cash_sales'] ?? 0),
                    'sale_count' => (int) ($totals['sale_count'] ?? 0),
                    'expected_cash' => $expectedCash,
                    'duration_minutes' => (int) ((time() - strtotime($shift['opened_at'])) / 60)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            sendJsonResponse([
                'success' => true,
                'has_open_shift' => false,
                'shift' => null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Error getting shift status: " . $e->getMessage());
        sendErrorResponse('Failed to get shift status', 'status_error', 500);
    } catch (Exception $e) {
        error_log("Error getting shift status: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 6d. Update Shift Totals
elseif ($action === 'update') {
    $saleTotal = isset($_POST['sale_total']) ? (float) $_POST['sale_total'] : 0.00;
    $paymentMethod = isset($_POST['payment_method']) ? (string) $_POST['payment_method'] : 'cash';
    $invoiceNumber = isset($_POST['invoice_number']) ? (string) $_POST['invoice_number'] : '';
    
    if ($saleTotal <= 0) {
        sendErrorResponse('Sale total must be greater than 0', 'invalid_sale_total', 400);
    }
    
    // Get current open shift
    $stmt = $pdo->prepare("
        SELECT id FROM register_sessions 
        WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND status = 'open' 
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $branchId, $userId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shift) {
        sendErrorResponse('No open shift found', 'no_open_shift', 404);
    }
    
    // Determine which column to update
    $updateField = 'total_other_sales';
    if ($paymentMethod === 'cash') {
        $updateField = 'total_cash_sales';
    } elseif ($paymentMethod === 'card') {
        $updateField = 'total_card_sales';
    } elseif ($paymentMethod === 'mpesa') {
        $updateField = 'total_mpesa_sales';
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE register_sessions 
            SET 
                total_sales = total_sales + ?,
                sale_count = sale_count + 1,
                {$updateField} = {$updateField} + ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ? AND status = 'open'
        ");
        $stmt->execute([$saleTotal, $saleTotal, $shift['id'], $tenantId]);
        
        $pdo->commit();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Shift updated successfully',
            'shift_id' => (int) $shift['id'],
            'sale_total' => $saleTotal,
            'payment_method' => $paymentMethod,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error updating shift: " . $e->getMessage());
        sendErrorResponse('Failed to update shift', 'update_error', 500);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error updating shift: " . $e->getMessage());
        sendErrorResponse($e->getMessage(), 'process_error', 500);
    }
}

// 6e. Invalid Action
else {
    sendErrorResponse('Invalid action: ' . $action, 'invalid_action', 400);
}

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in shift.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("Error Line: " . $e->getLine());
    
    sendErrorResponse('Database error occurred. Please try again.', 'db_error', 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in shift.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    
    sendErrorResponse($e->getMessage(), 'process_error', 500);
    
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error in shift.php: " . $e->getMessage());
    error_log("Error Line: " . $e->getLine());
    error_log("Error File: " . $e->getFile());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    sendErrorResponse('An unexpected error occurred. Please try again.', 'fatal_error', 500);
}