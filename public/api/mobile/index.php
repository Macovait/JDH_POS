<?php
/**
 * Mobile API - Unified endpoints for mobile applications
 *
 * Supports:
 * - Staff Mobile POS
 * - Customer Mobile App
 * - Driver Mobile App
 * - Manager Mobile Dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Device-ID, X-App-Version');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
if (!$pdo) {
    mobile_error('Database connection failed', 500);
}

// Get API endpoint from URL
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Handle both direct access and routed access
$endpoint = '';
if (strpos($path, '/api/mobile/') !== false) {
    // Direct access: /JDH_POS/public/api/mobile/auth/login
    $api_base = '/JDH_POS/public/api/mobile/';
    if (strpos($path, $api_base) === 0) {
        $endpoint = substr($path, strlen($api_base));
    }
} elseif (isset($_SERVER['PATH_INFO'])) {
    // Routed access: /api/mobile/index.php/auth/login
    $endpoint = trim($_SERVER['PATH_INFO'], '/');
} elseif (strpos($path, 'index.php') !== false) {
    // Fallback: check query string
    $endpoint = $_GET['endpoint'] ?? '';
}

// Parse endpoint
$parts = explode('/', $endpoint);
$resource = $parts[0] ?? '';
$action = $parts[1] ?? '';
$id = $parts[2] ?? null;

// Route to appropriate handler
try {
    switch ($resource) {
        case 'auth':
            require_once __DIR__ . '/auth.php';
            break;

        case 'pos':
            require_once __DIR__ . '/pos.php';
            break;

        case 'customer':
            require_once __DIR__ . '/customer.php';
            break;

        case 'driver':
            require_once __DIR__ . '/driver.php';
            break;

        case 'manager':
            require_once __DIR__ . '/manager.php';
            break;

        case 'sync':
            require_once __DIR__ . '/sync.php';
            break;

        default:
            mobile_error('Invalid API endpoint', 404);
    }
} catch (Exception $e) {
    error_log("Mobile API Error: " . $e->getMessage());
    mobile_error('Internal server error', 500);
}

// Helper functions
function mobile_success($data = null, $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

function mobile_error($message, $code = 400, $data = null) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error_code' => $code,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

function mobile_authenticate($pdo) {
    $headers = getallheaders();

    // Check API key
    $api_key = $headers['X-API-Key'] ?? $_GET['api_key'] ?? null;
    if (!$api_key) {
        mobile_error('API key required', 401);
    }

    // Validate API key and get company
    $stmt = $pdo->prepare("SELECT t.id, t.name FROM tenant_api_keys k JOIN tenants t ON t.id = k.tenant_id WHERE k.api_key = ? AND k.status = 'active' AND (k.expires_at IS NULL OR k.expires_at >= CURDATE())");
    $stmt->execute([$api_key]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        mobile_error('Invalid API key', 401);
    }

    return $company;
}

function jwt_authenticate($pdo) {
    $headers = getallheaders();

    // Check JWT token
    $auth_header = $headers['Authorization'] ?? '';

    // Special handling for web interface - allow if request comes from localhost/trusted source
    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
    $http_host = $_SERVER['HTTP_HOST'] ?? '';

    // Allow requests from localhost or if specifically from mobile_pos.php
    if ($remote_addr === '127.0.0.1' || $remote_addr === '::1' ||
        strpos($http_host, 'localhost') !== false) {

        // Use the company from session if user is logged in
        $tenant_id = $_SESSION['tenant_id'] ?? 1;

        // Get tenant info first
        $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE id = ? AND status = 'active'");
        $stmt->execute([$tenant_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($company) {
            // For demo purposes, use the first user from this company
            $stmt = $pdo->prepare("SELECT id, tenant_id, branch_id, status FROM users WHERE tenant_id = ? AND status = 1 AND deleted_at IS NULL AND branch_id = $current_branch_id LIMIT 1");
            $stmt->execute([$tenant_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($company) {
                return [
                    'user' => $user,
                    'tenant' => $company,
                    'token_data' => ['device_type' => 'web']
                ];
            }
        }
    }

    // Regular JWT token authentication
    if (empty($auth_header) || !preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        mobile_error('Authentication token required', 401);
    }

    $token = $matches[1];

    try {
        // Decode JWT-like token (simplified - just base64 encoded JSON)
        $payload = json_decode(base64_decode($token), true);

        if (!$payload || !isset($payload['user_id']) || !isset($payload['tenant_id'])) {
            mobile_error('Invalid token', 401);
        }

        // Check token expiry
        if (isset($payload['expires_at']) && $payload['expires_at'] < time()) {
            mobile_error('Token expired', 401);
        }

        // Verify user still exists and is active
        $stmt = $pdo->prepare("SELECT id, tenant_id, branch_id, status FROM users WHERE id = ? AND status = 1 AND deleted_at IS NULL");
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            mobile_error('User not found or inactive', 401);
        }

        $tenant_id = $payload['tenant_id'] ?? $user['tenant_id'] ?? null;
        if (!$tenant_id) {
            mobile_error('Tenant not found', 401);
        }

        // Get tenant info
        $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE id = ? AND status = 'active'");
        $stmt->execute([$tenant_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            mobile_error('Tenant not found or inactive', 401);
        }

        return [
            'user' => $user,
            'tenant' => $company,
            'token_data' => $payload
        ];

    } catch (Exception $e) {
        error_log("JWT validation error: " . $e->getMessage());
        mobile_error('Token validation failed', 401);
    }
}

function validate_mobile_request($required_fields = []) {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            mobile_error("Missing required field: $field", 400);
        }
    }

    return $data;
}
