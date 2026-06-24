<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

header('Content-Type: application/json');
ini_set('display_errors', '0');

/**
 * Send a JSON response and stop execution.
 *
 * @param array<string, mixed> $payload
 */
function branch_switch_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

// Allow both POST and GET requests
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$is_get = $_SERVER['REQUEST_METHOD'] === 'GET';

$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id() ?: get_current_tenant_id();
$tenant_id = $tenant_id;

if (!$user_id || !$tenant_id) {
    branch_switch_response([
        'success' => false,
        'message' => 'Not authenticated. Please log in again.',
    ], 401);
}

if (function_exists('validate_current_tenant') && !validate_current_tenant()) {
    branch_switch_response([
        'success' => false,
        'message' => 'Company access is no longer valid. Please log in again.',
    ], 403);
}

// Parse input based on request method
if ($is_get) {
    $input = $_GET;
} else {
    $raw_input = file_get_contents('php://input') ?: '';
    $input = json_decode($raw_input, true);
    if (!is_array($input)) {
        $input = $_POST;
    }
}

$branch_id = isset($input['branch_id']) ? (int) $input['branch_id'] : 0;
if ($branch_id <= 0) {
    branch_switch_response([
        'success' => false,
        'message' => 'Invalid branch ID.',
    ], 422);
}

// Get business_type_id for filtering
$business_type_id = null;
try {
    if (function_exists('get_current_business_type_id')) {
        $business_type_id = get_current_business_type_id($tenant_id);
    }
} catch (Exception $e) {
    $business_type_id = null;
}

try {
    $pdo = get_db_connection();
    if (!$pdo) {
        branch_switch_response([
            'success' => false,
            'message' => 'Database connection failed.',
        ], 500);
    }

    $has_branch_biz_type = db_has_column('branches', 'business_type_id');
    
    $branch_sql = "
        SELECT id, name, code, location, tax_rate, business_type_id, tenant_id
        FROM branches
        WHERE id = ? AND tenant_id = ? AND (active = 1 OR is_active = 1) AND deleted_at IS NULL
    ";
    $branch_params = [$branch_id, $tenant_id];
    
    if ($has_branch_biz_type && $business_type_id) {
        $branch_sql .= " AND (business_type_id = ? OR business_type_id IS NULL)";
        $branch_params[] = $business_type_id;
    }
    
    $branch_sql .= " LIMIT 1";
    
    $stmt = $pdo->prepare($branch_sql);
    $stmt->execute($branch_params);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        branch_switch_response([
            'success' => false,
            'message' => 'Branch not found or inactive.',
        ], 404);
    }

    $can_manage_branches = is_super_admin() || check_permission('branches.manage');

    if (!$can_manage_branches) {
        $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user_row = $stmt->fetch(PDO::FETCH_ASSOC);

        $assigned_branch_id = (int) ($user_row['branch_id'] ?? 0);
        $current_branch_id = (int) get_current_branch_id();
        $authorized_branch_id = $assigned_branch_id > 0 ? $assigned_branch_id : $current_branch_id;

        if ($authorized_branch_id <= 0 || $authorized_branch_id !== $branch_id) {
            branch_switch_response([
                'success' => false,
                'message' => 'You do not have access to this branch.',
            ], 403);
        }
    }

    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        $_SESSION['user'] = [];
    }
    if (!isset($_SESSION['current_branch']) || !is_array($_SESSION['current_branch'])) {
        $_SESSION['current_branch'] = [];
    }

    $_SESSION['branch_id'] = (int) $branch['id'];
    $_SESSION['branch_name'] = $branch['name'];
    $_SESSION['user']['branch_id'] = (int) $branch['id'];
    $_SESSION['user']['branch_name'] = $branch['name'];
    $_SESSION['current_branch']['id'] = (int) $branch['id'];
    $_SESSION['current_branch']['name'] = $branch['name'];
    $_SESSION['current_branch']['code'] = $branch['code'] ?? '';
    $_SESSION['current_branch']['location'] = $branch['location'] ?? '';
    $_SESSION['current_branch']['tax_rate'] = (float) ($branch['tax_rate'] ?? 0);
    $_SESSION['current_branch']['business_type_id'] = $branch['business_type_id'] ?? $business_type_id;

    if (function_exists('log_activity')) {
        log_activity('branch_switch', "Switched to branch: {$branch['name']}", [
                'branch_id' => (int) $branch['id'],
                'branch_name' => $branch['name'],
                'tenant_id' => (int) ($branch['tenant_id'] ?? $tenant_id),
            ],
            $user_id,
            $tenant_id
        );
    }

    $response = [
        'success' => true,
        'message' => 'Branch switched successfully.',
        'branch' => [
            'id' => (int) $branch['id'],
            'name' => $branch['name'],
            'code' => $branch['code'] ?? '',
            'location' => $branch['location'] ?? '',
            'tax_rate' => (float) ($branch['tax_rate'] ?? 0),
            'business_type_id' => (int) ($branch['business_type_id'] ?? $business_type_id),
        ],
    ];

    $debug_mode = !empty($_GET['debug']) || !empty($input['debug']) || !empty($_SESSION['debug_mode']);
    if ($debug_mode) {
        $response['debug'] = [
            'session' => [
                'branch_id' => $_SESSION['branch_id'] ?? null,
                'branch_name' => $_SESSION['branch_name'] ?? null,
                'current_branch' => $_SESSION['current_branch'] ?? null,
            'session_id' => session_id(),
        ],
        'user_id' => $user_id,
        'tenant_id' => $tenant_id,
        'tenant_id' => $tenant_id,
        'can_manage_branches' => $can_manage_branches,
    ];
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Handle redirect for GET requests
    if ($is_get && !empty($input['redirect'])) {
        header('Location: ' . $input['redirect']);
        exit;
    }

    branch_switch_response($response);
} catch (Throwable $e) {
    error_log('Branch switch failed: ' . $e->getMessage());

    branch_switch_response([
        'success' => false,
        'message' => 'Unable to switch branch right now.',
    ], 500);
}
