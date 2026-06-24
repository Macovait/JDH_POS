<?php
/**
 * Jakababa POS - Upgrade Plan AJAX Endpoint
 * 
 * This file handles AJAX requests for upgrading subscription plans.
 * 
 * @package JakababaPOS
 * @author Senior SaaS Architect
 * @version 1.0.0
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/FeatureAccess.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf_token = generate_csrf_token();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !is_array($input)) {
    $input = $_POST;
}

// CSRF verification
$token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if ($token !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if (!isset($input['plan_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Plan ID is required'
    ]);
    exit;
}

$planId = (int) $input['plan_id'];
$companyId = $_SESSION['tenant_id'];

// Get database connection
$db = getDB();

// Initialize FeatureAccess
$featureAccess = new FeatureAccess($db);

// Check if plan exists
$allPlans = $featureAccess->getAllPlans();
$planExists = false;
foreach ($allPlans as $plan) {
    if ($plan['id'] === $planId) {
        $planExists = true;
        break;
    }
}

if (!$planExists) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid plan ID'
    ]);
    exit;
}

// Upgrade the plan
$result = $featureAccess->upgradePlan($planId, $companyId);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Plan upgraded successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to upgrade plan. Please try again.'
    ]);
}
