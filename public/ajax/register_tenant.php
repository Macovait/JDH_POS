<?php
/**
 * Register New Tenant
 * Handles tenant onboarding signup
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);

header('Content-Type: application/json');

try {
    $pdo = get_db_connection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['business_name', 'email', 'password', 'business_type', 'plan'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
            exit;
        }
    }
    
    // Validate email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered. Please login.']);
        exit;
    }
    
    // Generate unique subdomain
    $subdomain = generate_subdomain($input['business_name']);
    $originalSubdomain = $subdomain;
    $counter = 1;
    
    while (subdomain_exists($pdo, $subdomain)) {
        $subdomain = $originalSubdomain . $counter;
        $counter++;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Create tenant
    $stmt = $pdo->prepare("
        INSERT INTO tenants (
            name, subdomain, business_type, plan, status,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    $stmt->execute([
        $input['business_name'],
        $subdomain,
        $input['business_type'],
        $input['plan']
    ]);
    
    $tenant_id = $pdo->lastInsertId();
    
    // Create default branch
    $stmt = $pdo->prepare("
        INSERT INTO branches (
            tenant_id, name, is_main, status, created_at
        ) VALUES (?, 'Main Branch', 1, 'active', NOW())
    ");
    $stmt->execute([$tenant_id]);
    $branch_id = $pdo->lastInsertId();
    
    // Create default role (Administrator)
    $stmt = $pdo->prepare("
        INSERT INTO roles (
            tenant_id, name, description, created_at, updated_at
        ) VALUES (?, 'Administrator', 'Full system access', NOW(), NOW())
    ");
    $stmt->execute([$tenant_id]);
    $role_id = $pdo->lastInsertId();
    
    // Create user
    $password_hash = password_hash($input['password'], PASSWORD_BCRYPT);
    $username = generate_username($input['owner_name'] ?? $input['email']);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (
            tenant_id, branch_id, role_id, name, username,
            email, password, phone, is_super_admin, status,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW(), NOW())
    ");
    $stmt->execute([
        $tenant_id,
        $branch_id,
        $role_id,
        $input['owner_name'] ?? 'Admin User',
        $username,
        $input['email'],
        $password_hash,
        $input['phone'] ?? null
    ]);
    
    $user_id = $pdo->lastInsertId();
    
    // Create default categories based on business type
    create_default_categories($pdo, $tenant_id, $input['business_type']);
    
    // Create default settings
    create_default_settings($pdo, $tenant_id, $input['business_name']);
    
    // Log the registration
    if (function_exists('log_security_event')) {
        log_security_event('tenant_registered', [
            'tenant_id' => $tenant_id,
            'business_name' => $input['business_name'],
            'email' => $input['email'],
            'plan' => $input['plan']
        ]);
    }
    
    $pdo->commit();
    
    // Send welcome email (placeholder)
    // send_welcome_email($input['email'], $input['business_name'], $username);
    
    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully!',
        'data' => [
            'tenant_id' => $tenant_id,
            'subdomain' => $subdomain,
            'username' => $username
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Tenant registration failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Registration failed. Please try again later.'
    ]);
}

function generate_subdomain($business_name) {
    $subdomain = strtolower($business_name);
    $subdomain = preg_replace('/[^a-z0-9]/', '', $subdomain);
    return substr($subdomain, 0, 20);
}

function subdomain_exists($pdo, $subdomain) {
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE subdomain = ? LIMIT 1");
    $stmt->execute([$subdomain]);
    return (bool) $stmt->fetch();
}

function generate_username($name) {
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    return substr($username, 0, 15) . rand(100, 999);
}

function create_default_categories($pdo, $tenant_id, $business_type) {
    $categories = [
        'retail' => ['General', 'Electronics', 'Clothing', 'Food & Beverages', 'Household'],
        'restaurant' => ['Appetizers', 'Main Course', 'Desserts', 'Beverages', 'Specials'],
        'supermarket' => ['Fresh Produce', 'Dairy', 'Beverages', 'Snacks', 'Household'],
        'pharmacy' => ['Prescription', 'Over-the-Counter', 'Vitamins', 'Personal Care', 'Medical Supplies'],
        'electronics' => ['Phones', 'Computers', 'Accessories', 'Audio', 'Smart Home'],
    ];
    
    $defaultCategories = $categories[$business_type] ?? $categories['retail'];
    
    $stmt = $pdo->prepare("
        INSERT INTO categories (tenant_id, name, created_at, updated_at)
        VALUES (?, ?, NOW(), NOW())
    ");
    
    foreach ($defaultCategories as $category) {
        $stmt->execute([$tenant_id, $category]);
    }
}

function create_default_settings($pdo, $tenant_id, $business_name) {
    $settings = [
        'company_name' => $business_name,
        'currency' => 'USD',
        'currency_symbol' => '$',
        'timezone' => 'UTC',
        'date_format' => 'Y-m-d',
        'receipt_header' => $business_name,
        'receipt_footer' => 'Thank you for your business!',
        'tax_rate' => '0',
        'enable_loyalty' => '0',
        'receipt_printer' => 'default',
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO settings (tenant_id, setting_key, setting_value, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    
    foreach ($settings as $key => $value) {
        $stmt->execute([$tenant_id, $key, $value]);
    }
}
?>
