<?php

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
/**
 * Mobile Authentication API
 * Handles login, token refresh, and device registration
 */

// Only process POST requests for auth
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mobile_error('Method not allowed', 405);
}

$action = $action ?: 'login';

switch ($action) {
    case 'login':
        handle_mobile_login($pdo);
        break;

    case 'web_auth':
        handle_web_auth($pdo);
        break;

    case 'refresh':
        handle_token_refresh($pdo);
        break;

    case 'register_device':
        handle_device_registration($pdo);
        break;

    case 'logout':
        handle_mobile_logout($pdo);
        break;

    default:
        mobile_error('Invalid auth action', 400);
}

function handle_mobile_login($pdo) {
    $data = validate_mobile_request(['username', 'password', 'device_type']);

    $username = trim($data['username']);
    $password = $data['password'];
    $device_type = $data['device_type']; // 'pos', 'customer', 'driver', 'manager'
    $device_id = $data['device_id'] ?? null;
    $app_version = $data['app_version'] ?? '1.0.0';

    // Validate device type
    $valid_device_types = ['pos', 'customer', 'driver', 'manager'];
    if (!in_array($device_type, $valid_device_types)) {
        mobile_error('Invalid device type', 400);
    }

    // Authenticate user
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.name, u.email, u.role_id, u.status, u.tenant_id, u.branch_id,
               u.password_hash, t.name as company_name, b.name as branch_name, t.business_type_id
        FROM users u
        LEFT JOIN tenants t ON u.tenant_id = t.id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.username = ? AND u.status = 1 AND u.deleted_at IS NULL
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        mobile_error('Invalid credentials', 401);
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'] ?? '')) {
        mobile_error('Invalid credentials', 401);
    }

    // Check permissions based on device type
    $has_permission = false;
    switch ($device_type) {
        case 'pos':
            // Allow users with role_id 3 (cashier) or higher to access POS
            $has_permission = in_array($user['role_id'], [1, 2, 3, 4, 5]);
            break;
        case 'customer':
            // Customers can always access their app
            $has_permission = true;
            break;
        case 'driver':
            // Allow delivery drivers (role_id 4) or higher
            $has_permission = in_array($user['role_id'], [1, 2, 4]);
            break;
        case 'manager':
            // Allow managers and admins
            $has_permission = in_array($user['role_id'], [1, 2]);
            break;
    }

    if (!$has_permission) {
        mobile_error('Insufficient permissions for this device type', 403);
    }

    // Generate JWT-like token (simplified for demo)
    $token_data = [
        'user_id' => $user['id'],
        'tenant_id' => $user['tenant_id'],
        'branch_id' => $user['branch_id'],
        'device_type' => $device_type,
        'device_id' => $device_id,
        'issued_at' => time(),
        'expires_at' => time() + (24 * 60 * 60) // 24 hours
    ];

    $token = base64_encode(json_encode($token_data));
    $refresh_token = base64_encode(json_encode([
        'user_id' => $user['id'],
        'issued_at' => time(),
        'expires_at' => time() + (30 * 24 * 60 * 60) // 30 days
    ]));

    // Store device info for push notifications
    if ($device_id) {
        register_mobile_device($pdo, $user['id'], $device_id, $device_type, $app_version);
    }

    // Log successful login
    error_log("Mobile login: User {$user['username']} from {$device_type} device");

    mobile_success([
        'token' => $token,
        'refresh_token' => $refresh_token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role_id' => $user['role_id'],
            'tenant' => [
                'id' => $user['tenant_id'],
                'name' => $user['company_name'],
                'business_type_id' => $user['business_type_id']
            ],
            'branch' => [
                'id' => $user['branch_id'],
                'name' => $user['branch_name']
            ]
        ],
        'permissions' => get_user_permissions($pdo, $user['id'], $device_type),
        'expires_in' => 86400
    ], 'Login successful');
}

function handle_token_refresh($pdo) {
    $data = validate_mobile_request(['refresh_token']);

    $refresh_data = json_decode(base64_decode($data['refresh_token']), true);
    if (!$refresh_data || !isset($refresh_data['user_id'])) {
        mobile_error('Invalid refresh token', 401);
    }

    if ($refresh_data['expires_at'] < time()) {
        mobile_error('Refresh token expired', 401);
    }

    $stmt = $pdo->prepare("SELECT id, tenant_id, branch_id FROM users WHERE id = ? AND status = 1 AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$refresh_data['user_id']]);
    $refresh_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$refresh_user) {
        mobile_error('Invalid refresh token', 401);
    }

    // Generate new tokens
    $token_data = [
        'user_id' => $refresh_user['id'],
        'tenant_id' => $refresh_user['tenant_id'],
        'branch_id' => $refresh_user['branch_id'],
        'issued_at' => time(),
        'expires_at' => time() + (24 * 60 * 60)
    ];

    $new_token = base64_encode(json_encode($token_data));
    $new_refresh_token = base64_encode(json_encode([
        'user_id' => $refresh_user['id'],
        'tenant_id' => $refresh_user['tenant_id'],
        'branch_id' => $refresh_user['branch_id'],
        'issued_at' => time(),
        'expires_at' => time() + (30 * 24 * 60 * 60)
    ]));

    mobile_success([
        'token' => $new_token,
        'refresh_token' => $new_refresh_token,
        'expires_in' => 86400
    ], 'Token refreshed');
}

function handle_device_registration($pdo) {
    $data = validate_mobile_request(['device_id', 'device_type', 'push_token']);

    $device_id = $data['device_id'];
    $device_type = $data['device_type'];
    $push_token = $data['push_token'];
    $app_version = $data['app_version'] ?? '1.0.0';
    $os = $data['os'] ?? 'unknown';
    $model = $data['model'] ?? 'unknown';

    // Get user from token (simplified - in production use proper JWT validation)
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    if (empty($token)) {
        mobile_error('Authentication required', 401);
    }

    $token_data = json_decode(base64_decode(str_replace('Bearer ', '', $token)), true);
    $user_id = $token_data['user_id'] ?? null;
    $tenant_id = $token_data['tenant_id'] ?? null;

    if (!$user_id) {
        mobile_error('Invalid token', 401);
    }

    if (!$tenant_id) {
        $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $tenant_id = $stmt->fetchColumn() ?: null;
    }

    if (!$tenant_id) {
        mobile_error('Invalid token tenant', 401);
    }

    register_mobile_device($pdo, $tenant_id, $user_id, $device_id, $device_type, $app_version, $push_token, $os, $model);

    mobile_success(null, 'Device registered successfully');
}

function handle_web_auth($pdo) {
    // Check if user is already authenticated via main POS session
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
        mobile_error('User not authenticated in main POS', 401);
    }

    $user_id = (int) $_SESSION['user_id'];
    $tenant_id = (int) $_SESSION['tenant_id'];

    // Verify user exists and is active
    $stmt = $pdo->prepare("SELECT id, username, status FROM users WHERE id = ? AND status = 1 AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        mobile_error('User not found or inactive', 401);
    }

    // Verify company exists and is active
    $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE id = ? AND status = 'active'");
    $stmt->execute([$tenant_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        mobile_error('Company not found or inactive', 401);
    }

    // Generate JWT-like token for web interface
    $token_data = [
        'user_id' => $user['id'],
        'tenant_id' => $company['id'],
        'branch_id' => $_SESSION['branch_id'] ?? 1,
        'device_type' => 'web',
        'device_id' => 'web-pos-' . session_id(),
        'issued_at' => time(),
        'expires_at' => time() + (24 * 60 * 60) // 24 hours
    ];

    $token = base64_encode(json_encode($token_data));

    mobile_success([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $_SESSION['user_name'] ?? $user['username'],
            'username' => $user['username'],
            'role_id' => $_SESSION['role_id'] ?? 3
        ],
        'tenant' => [
            'id' => $company['id'],
            'name' => $company['name']
        ]
    ], 'Web authentication successful');
}

function handle_mobile_logout($pdo) {
    // Get user from token
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    if (empty($token)) {
        mobile_error('Authentication token required', 401);
    }

    $token_data = json_decode(base64_decode($token), true);
    $user_id = $token_data['user_id'] ?? null;
    $device_id = $token_data['device_id'] ?? null;

    if ($device_id) {
        // Remove device registration
        $stmt = $pdo->prepare("DELETE FROM mobile_devices WHERE user_id = ? AND device_id = ?");
        $stmt->execute([$user_id, $device_id]);
    }

    mobile_success(null, 'Logged out successfully');
}

function check_user_permission($pdo, $user_id, $permission) {
    try {
        return function_exists('check_permission') && check_permission($permission);
    } catch (Exception $e) {
        return false;
    }
}

function get_user_permissions($pdo, $user_id, $device_type) {
    // Return relevant permissions based on device type
    $permissions = [];

    switch ($device_type) {
        case 'pos':
            $permissions = [
                'pos.access' => true,
                'sales.create' => true,
                'products.view' => true,
                'customers.view' => true
            ];
            break;

        case 'customer':
            $permissions = [
                'orders.create' => true,
                'orders.view' => true,
                'profile.view' => true
            ];
            break;

        case 'driver':
            $permissions = [
                'deliveries.view' => true,
                'deliveries.update' => true,
                'orders.view' => true
            ];
            break;

        case 'manager':
            $permissions = [
                'reports.view' => true,
                'inventory.view' => true,
                'staff.view' => true,
                'settings.view' => true
            ];
            break;
    }

    return $permissions;
}

function register_mobile_device($pdo, $tenant_id, $user_id, $device_id, $device_type, $app_version, $push_token = null, $os = null, $model = null) {
    // Create mobile_devices table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mobile_devices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            tenant_id BIGINT NOT NULL,
            device_id VARCHAR(255) NOT NULL,
            device_type ENUM('pos', 'customer', 'driver', 'manager') NOT NULL,
            app_version VARCHAR(20) DEFAULT '1.0.0',
            push_token TEXT,
            os VARCHAR(50),
            model VARCHAR(100),
            last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_device (user_id, device_id),
            INDEX idx_tenant (tenant_id),
            INDEX idx_user (user_id),
            INDEX idx_device_type (device_type)
        )
    ");

    // Insert or update device
    $stmt = $pdo->prepare("
        INSERT INTO mobile_devices (tenant_id, user_id, device_id, device_type, app_version, push_token, os, model)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            app_version = VALUES(app_version),
            push_token = VALUES(push_token),
            os = VALUES(os),
            model = VALUES(model),
            last_active = CURRENT_TIMESTAMP
    ");
        $stmt->execute([$tenant_id, $user_id, $device_id, $device_type, $app_version, $push_token, $os, $model]);
}
