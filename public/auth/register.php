<?php
declare(strict_types=1);

/**
 * User Registration Page (SaaS Version) - Side by Side Layout
 * Loads countries from /data/countries.json
 */

// Start output buffering
ob_start();

// Bootstrap the application
require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);
safe_require('logger.php', 'src', true);

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['tenant_id'])) {
    header('Location: ' . base_url('dashboard/home.php'));
    exit;
}

// Load countries from JSON file
$countriesFile = dirname(__DIR__, 2) . '/data/countries.json';
$countries = [];

if (file_exists($countriesFile)) {
    $data = json_decode(file_get_contents($countriesFile), true);
    $countries = $data['countries'] ?? [];
} else {
    // Fallback - minimal countries if file doesn't exist
    $countries = [
        ['code' => 'KE', 'name' => 'Kenya', 'dial_code' => '+254', 'flag' => 'ke'],
        ['code' => 'UG', 'name' => 'Uganda', 'dial_code' => '+256', 'flag' => 'ug'],
        ['code' => 'TZ', 'name' => 'Tanzania', 'dial_code' => '+255', 'flag' => 'tz'],
        ['code' => 'US', 'name' => 'United States', 'dial_code' => '+1', 'flag' => 'us'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'dial_code' => '+44', 'flag' => 'gb'],
    ];
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Initialize variables
$error = null;
$success = null;
$company_name = '';
$email = '';
$phone_number = '';
$country_code = '+254';
$password = '';

// AJAX endpoint to check company availability
if (isset($_GET['check_company']) && !empty($_GET['name'])) {
    header('Content-Type: application/json');
    try {
        $existing = db_fetch_one(
            "SELECT id, name, subdomain FROM tenants WHERE name = ? AND deleted_at IS NULL",
            [$_GET['name']]
        );
        
        if ($existing) {
            echo json_encode(['available' => false, 'message' => 'Company name already taken']);
        } else {
            echo json_encode(['available' => true, 'message' => 'Company name available']);
        }
    } catch (Exception $e) {
        echo json_encode(['available' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
        $csrf_token = generate_csrf_token();
    } else {
        $company_name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $country_code = trim($_POST['country_code'] ?? '+254');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $phone = $country_code . $phone_number;
        $password = $_POST['password'] ?? '';
        $agree_terms = isset($_POST['agree_terms']);

        // Validation
        if (empty($company_name) || empty($email) || empty($phone_number) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!$agree_terms) {
            $error = 'Please agree to the Terms of Use and Privacy Policy.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one number.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[0-9]{6,15}$/', $phone_number)) {
            $error = 'Please enter a valid phone number.';
        } else {
            try {
                $existing_company = db_fetch_one(
                    "SELECT id FROM tenants WHERE name = ? AND deleted_at IS NULL",
                    [$company_name]
                );

                if ($existing_company) {
                    $error = 'Company name already taken. Please choose another name.';
                } else {
                    $existing_email = db_fetch_one(
                        "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL",
                        [$email]
                    );

                    if ($existing_email) {
                        $error = 'Email already registered. Please login instead.';
                    } else {
                        $subdomain = strtolower(preg_replace('/[^a-z0-9-]/', '-', $company_name));
                        $subdomain = trim($subdomain, '-');
                        
                        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
                            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
                            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
                        );
                        $tenant_id = db_insert('tenants', [
                            'uuid' => $uuid,
                            'subdomain' => $subdomain,
                            'name' => $company_name,
                            'business_type' => 'retail',
                            'email' => $email,
                            'phone' => $phone,
                            'status' => 'active',
                            'is_active' => 1,
                            'is_suspended' => 0,
                            'currency' => 'KES',
                            'timezone' => 'Africa/Nairobi',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);

                        if ($tenant_id) {
                            $branch_id = db_insert('branches', [
                                'tenant_id' => $tenant_id,
                                'name' => 'Main Branch',
                                'code' => 'MB',
                                'is_active' => 1,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);

                            $admin_role = db_fetch_one(
                                "SELECT id FROM roles WHERE name = 'Administrator' LIMIT 1"
                            );
                            
                            if (!$admin_role) {
                                $admin_role = ['id' => 1];
                            }

                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            
                            $user_id = db_insert('users', [
                                'tenant_id' => $tenant_id,
                                'username' => $email,
                                'email' => $email,
                                'name' => $company_name . ' Admin',
                                'phone' => $phone,
                                'password_hash' => $password_hash,
                                'role_id' => $admin_role['id'],
                                'branch_id' => $branch_id,
                                'status' => 1,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);

                            if ($user_id) {
                                try {
                                    $pdo = get_db_connection();

                                    // 1. Assign plan & create subscription
                                    $planSlug = isset($_GET['plan']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['plan'])) : '';
                                    $selectedPlan = null;
                                    if ($planSlug) {
                                        $selectedPlan = db_fetch_one("SELECT id, price, currency, trial_days, billing_cycle FROM pos_plans WHERE slug = ? AND is_active = 1 LIMIT 1", [$planSlug]);
                                    }
                                    if (!$selectedPlan) {
                                        $selectedPlan = db_fetch_one("SELECT id, price, currency, trial_days, billing_cycle FROM pos_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
                                    }
                                    $planId = $selectedPlan ? (int)$selectedPlan['id'] : 1;
                                    $trialDays = (int)($selectedPlan['trial_days'] ?? 14);
                                    $planPrice = (float)($selectedPlan['price'] ?? 0);
                                    $planCurrency = $selectedPlan['currency'] ?? 'KES';
                                    $billingCycle = $selectedPlan['billing_cycle'] ?? 'monthly';

                                    // Update tenant with plan
                                    $stmt = $pdo->prepare("UPDATE tenants SET plan_id = ? WHERE id = ?");
                                    $stmt->execute([$planId, $tenant_id]);

                                    // Create subscription
                                    $trialEnds = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));
                                    $periodEnd = date('Y-m-d H:i:s', strtotime("+1 month"));
                                    $stmt = $pdo->prepare("INSERT INTO pos_subscriptions (tenant_id, plan_id, status, billing_cycle, amount, currency, trial_ends_at, current_period_start, current_period_end) VALUES (?, ?, 'trialing', ?, ?, ?, ?, NOW(), ?)");
                                    $stmt->execute([$tenant_id, $planId, $billingCycle, $planPrice, $planCurrency, $trialEnds, $periodEnd]);

                                    // 2. Create default settings
                                    $defaultSettings = [
                                        ['company_name', $company_name],
                                        ['company_email', $email],
                                        ['company_phone', $phone],
                                        ['currency', 'KES'],
                                        ['timezone', 'Africa/Nairobi'],
                                        ['date_format', 'd M Y'],
                                        ['time_format', 'H:i'],
                                        ['business_type', 'retail'],
                                        ['default_branch_id', (string)$branch_id],
                                        ['default_payment_method', 'cash'],
                                        ['tax_rate', '16'],
                                        ['online_store_enabled', '0'],
                                    ];
                                    $settingsStmt = $pdo->prepare("INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                                    foreach ($defaultSettings as $ds) {
                                        $settingsStmt->execute([$tenant_id, $ds[0], $ds[1]]);
                                    }

                                    // 3. Auto-login
                                    start_session_secure();
                                    session_regenerate_id(true);

                                    // Build role name
                                    $roleName = 'admin';
                                    try {
                                        $rStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ? AND tenant_id = ? LIMIT 1");
                                        $rStmt->execute([$admin_role['id'], $tenant_id]);
                                        $roleRow = $rStmt->fetch(PDO::FETCH_ASSOC);
                                        if ($roleRow) {
                                            $roleName = strtolower(str_replace([' ', '-', '/'], '_', $roleRow['name']));
                                            if (in_array($roleName, ['administrator', 'admin', 'owner', 'super_admin', 'superadmin', 'super-admin'])) {
                                                $roleName = 'admin';
                                            }
                                        }
                                    } catch (Exception $e) {}

                                    $_SESSION['user_id'] = (int)$user_id;
                                    $_SESSION['user_name'] = $company_name . ' Admin';
                                    $_SESSION['name'] = $company_name . ' Admin';
                                    $_SESSION['username'] = $email;
                                    $_SESSION['user_email'] = $email;
                                    $_SESSION['role'] = $roleName;
                                    $_SESSION['user_role'] = $roleName;
                                    $_SESSION['role_id'] = (int)$admin_role['id'];
                                    $_SESSION['tenant_id'] = (int)$tenant_id;
                                    $_SESSION['tenant_name'] = $company_name;
                                    $_SESSION['tenant_slug'] = $subdomain;
                                    $_SESSION['tenant_code'] = $subdomain;
                                    $_SESSION['tenant_status'] = 'active';
                                    $_SESSION['tenant_currency'] = 'KES';
                                    $_SESSION['tenant_timezone'] = 'Africa/Nairobi';
                                    $_SESSION['tenant_validated'] = true;
                                    $_SESSION['subscription_status'] = 'trialing';
                                    $_SESSION['tenant_plan_id'] = $planId;
                                    $_SESSION['business_type'] = 'retail';
                                    $_SESSION['branch_id'] = (int)$branch_id;
                                    $_SESSION['branch_name'] = 'Main Branch';
                                    $_SESSION['permissions'] = ['*'];
                                    $_SESSION['authenticated_at'] = time();
                                    $_SESSION['user'] = [
                                        'id' => (int)$user_id,
                                        'name' => $company_name . ' Admin',
                                        'username' => $email,
                                        'email' => $email,
                                        'role' => $roleName,
                                        'role_id' => (int)$admin_role['id'],
                                        'tenant_id' => (int)$tenant_id,
                                        'branch_id' => (int)$branch_id,
                                    ];

                                    // 4. Redirect to dashboard with welcome flag
                                    header('Location: ' . base_url('dashboard/home.php?welcome=1'));
                                    exit;
                                } catch (Exception $e) {
                                    error_log('Post-registration setup error: ' . $e->getMessage());
                                    // Fallback: redirect to login with note
                                    header('Location: ' . base_url('auth/login.php') . '?registered=1&tenant=' . urlencode($subdomain));
                                    exit;
                                }
                            } else {
                                $error = 'Failed to create user account. Please try again.';
                            }
                        } else {
                            $error = 'Failed to create company account. Please try again.';
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'Database error. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JAKPOS | Create Your Account</title>
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.4; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.5s ease-out;
        }
        
        .flag-img {
            width: 1.25rem;
            height: 0.875rem;
            object-fit: cover;
            border-radius: 0.125rem;
            flex-shrink: 0;
            display: inline-block;
            vertical-align: middle;
        }
        
        .carousel-container {
            position: relative;
            min-height: 280px;
        }
        
        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            opacity: 0;
            transition: opacity 0.6s ease-in-out;
            pointer-events: none;
        }
        
        .carousel-slide.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        .carousel-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.4);
            transition: all 0.3s ease;
        }
        
        .carousel-dot.active {
            background-color: rgba(255,255,255,0.9);
            transform: scale(1.2);
        }
        
        .animate-pulse-slow {
            animation: pulse 3s ease-in-out infinite;
        }
        
        .password-requirement.valid {
            color: #10b981;
        }
        
        .password-requirement.invalid {
            color: #6b7280;
        }
        
        .country-dropdown {
            position: relative;
        }
        
        .country-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 50;
            display: none;
        }
        
        .country-dropdown-menu.show {
            display: block;
        }
        
        .country-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .country-option:hover {
            background-color: #fef3e8;
        }
        
        .country-option.selected {
            background-color: #fef3e8;
            border-left: 2px solid #f59e0b;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #f59e0b;
            border-radius: 10px;
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fef3e8',
                            100: '#fde6d0',
                            200: '#fbcea1',
                            300: '#f9b572',
                            400: '#f79c43',
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                            800: '#92400e',
                            900: '#78350f',
                        }
                    }
                }
            }
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 font-sans" style="font-family: 'Inter', sans-serif;">

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-6xl animate-fade-in">
            <div class="grid lg:grid-cols-2 gap-8">
                
                <!-- Left Column - Info Panel -->
                <div class="block animate-slide-up">
                    <div class="bg-gradient-to-br from-primary-600 to-primary-700 rounded-xl p-8 text-white h-full relative overflow-hidden">
                        
                        <div class="absolute top-20 right-10 w-40 h-40 bg-white/10 rounded-full filter blur-2xl animate-pulse-slow"></div>
                        <div class="absolute bottom-20 left-10 w-40 h-40 bg-white/10 rounded-full filter blur-2xl animate-pulse-slow" style="animation-delay: -2s;"></div>
                        
                        <div class="flex items-center gap-2 mb-8">
                            <img src="<?php echo asset_url('img/logo.png'); ?>" alt="JAKPOS" class="h-10 w-auto drop-shadow-md">
                        </div>
                        
                        <div class="carousel-container" id="registerCarousel">
                            <!-- Slide 1 -->
                            <div class="carousel-slide active" data-index="0">
                                <div class="text-center">
                                    <div class="text-6xl mb-4">🚀</div>
                                    <h2 class="text-2xl font-bold mb-2">Your Journey Starts Here!</h2>
                                    <p class="text-white/80 text-sm">Join thousands of businesses using JAKPOS to streamline operations.</p>
                                </div>
                            </div>
                            <!-- Slide 2 -->
                            <div class="carousel-slide" data-index="1">
                                <div class="text-center">
                                    <div class="text-6xl mb-4">💳</div>
                                    <h2 class="text-2xl font-bold mb-2">Fast & Secure Payments</h2>
                                    <p class="text-white/80 text-sm">Accept M-Pesa, cash, cards, and more with real-time sync.</p>
                                </div>
                            </div>
                            <!-- Slide 3 -->
                            <div class="carousel-slide" data-index="2">
                                <div class="text-center">
                                    <div class="text-6xl mb-4">📊</div>
                                    <h2 class="text-2xl font-bold mb-2">Real-Time Analytics</h2>
                                    <p class="text-white/80 text-sm">Make data-driven decisions with live reports and insights.</p>
                                </div>
                            </div>
                            <!-- Slide 4 -->
                            <div class="carousel-slide" data-index="3">
                                <div class="text-center">
                                    <div class="text-6xl mb-4">🌍</div>
                                    <h2 class="text-2xl font-bold mb-2">Multi-Branch Management</h2>
                                    <p class="text-white/80 text-sm">Control multiple stores from one central dashboard.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-center gap-2 mt-4">
                            <button type="button" class="carousel-dot active" data-index="0" aria-label="Slide 1"></button>
                            <button type="button" class="carousel-dot" data-index="1" aria-label="Slide 2"></button>
                            <button type="button" class="carousel-dot" data-index="2" aria-label="Slide 3"></button>
                            <button type="button" class="carousel-dot" data-index="3" aria-label="Slide 4"></button>
                        </div>
                        
                        <div class="flex items-center gap-2 text-xs text-white/60 mt-6">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <span>Your data is protected with enterprise-grade security.</span>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Registration Form -->
                <div class="bg-white rounded-xl shadow-xl p-6 md:p-8 animate-slide-up">
                    
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center mb-4">
                            <img src="<?php echo asset_url('img/logo.png'); ?>" alt="JAKPOS" class="h-12 w-auto">
                        </div>
                        <h1 class="text-2xl font-bold text-gray-800">Create Your Account</h1>
                        <div class="inline-flex items-center gap-2 mt-2 px-3 py-1 bg-primary-50 rounded-full">
                            <svg class="w-3 h-3 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-xs text-primary-600 font-medium">Free 7-day trial</span>
                        </div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg ">
                            <p class="text-xs text-red-600 text-center"><?= htmlspecialchars($error) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg ">
                            <p class="text-xs text-green-600 text-center"><?= htmlspecialchars($success) ?></p>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-sm text-primary-500 hover:text-primary-600 font-medium">Click here to login →</a>
                        </div>
                    <?php else: ?>
                    
                    <form method="POST" action="" id="registerForm" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <!-- Company Name -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">What's your company name <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="text" name="company_name" id="company_name" value="<?= htmlspecialchars($company_name) ?>" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100 transition-all" placeholder="e.g., Acme Corporation" maxlength="100" required>
                                <div id="companySpinner" class="absolute right-3 top-1/2 -translate-y-1/2 hidden"><svg class="w-4 h-4 text-primary-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></div>
                            </div>
                            <div id="companyStatus" class="mt-1 text-xs"></div>
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100 transition-all" placeholder="you@company.com" maxlength="70" required>
                        </div>
                        
                        <!-- Phone with Country Dropdown -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                <div class="w-36 relative country-dropdown">
                                    <button type="button" id="countryDropdownBtn" class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg text-gray-800 bg-white flex items-center justify-between gap-2 hover:bg-gray-50 transition">
                                        <span class="flex items-center gap-2"><img id="selectedFlag" src="https://flagcdn.com/w40/ke.png" alt="" class="flag-img"><span id="selectedDialCode">+254</span></span>
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div id="countryDropdownMenu" class="country-dropdown-menu hidden">
                                        <input type="text" id="countrySearch" placeholder="Search country..." class="w-full px-3 py-2 text-sm border-b border-gray-200 focus:outline-none focus:border-primary-400 sticky top-0 bg-white">
                                        <div id="countryList" class="max-h-64 overflow-y-auto">
                                            <?php foreach ($countries as $country): ?>
                                                <div class="country-option" data-code="<?= htmlspecialchars($country['code']) ?>" data-dial="<?= htmlspecialchars($country['dial_code']) ?>" data-flag="<?= htmlspecialchars($country['flag']) ?>" data-name="<?= htmlspecialchars($country['name']) ?>">
                                                    <span class="flex items-center gap-2"><img src="https://flagcdn.com/w40/<?= htmlspecialchars($country['flag']) ?>.png" alt="" class="flag-img"><span class="text-sm"><?= htmlspecialchars($country['name']) ?></span></span>
                                                    <span class="text-sm text-gray-500"><?= htmlspecialchars($country['dial_code']) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="country_code" id="countryCode" value="+254">
                                <input type="tel" name="phone_number" id="phoneNumber" value="<?= htmlspecialchars($phone_number) ?>" class="flex-1 px-4 py-2.5 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100 transition-all" placeholder="712345678" required>
                            </div>
                        </div>
                        
                        <!-- Password -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="password" id="password" class="w-full px-4 py-2.5 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100 transition-all pr-10" placeholder="Create a strong password" required>
                                <button type="button" onclick="togglePassword('password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                            </div>
                            
                            <div class="mt-2 text-xs space-y-1">
                                <p class="text-gray-500">Password must contain:</p>
                                <ul class="grid grid-cols-2 gap-x-4 gap-y-0.5">
                                    <li id="req-length" class="password-requirement invalid flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>8+ characters</span></li>
                                    <li id="req-upper" class="password-requirement invalid flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>Uppercase letter</span></li>
                                    <li id="req-lower" class="password-requirement invalid flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>Lowercase letter</span></li>
                                    <li id="req-number" class="password-requirement invalid flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><span>Number</span></li>
                                </ul>
                            </div>
                        </div>
                        
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="agree_terms" value="1" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary-500 focus:ring-primary-400">
                            <span class="text-xs text-gray-600 leading-relaxed">By proceeding, you agree to our <a href="#" class="text-primary-500 hover:text-primary-600 hover:underline">Terms of Use</a> and <a href="#" class="text-primary-500 hover:text-primary-600 hover:underline">Privacy Policy</a></span>
                        </label>
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white font-semibold py-2.5 rounded-lg text-sm transition-all transform hover:scale-[1.01] active:scale-[0.99] shadow-md mt-4">Create Account</button>
                    </form>
                    
                    <div class="relative my-6"><div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div><div class="relative flex justify-center text-xs"><span class="px-3 bg-white text-gray-400">or</span></div></div>
                    
                    <button type="button" onclick="initiateGoogleSignup()" class="w-full py-2.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition-all flex items-center justify-center gap-2 text-gray-700 text-sm font-medium">
                        <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        <span>Continue with Google</span>
                    </button>
                    
                    <p class="text-center text-xs text-gray-500 mt-6">Already have an account? <a href="login.php" class="text-primary-500 hover:text-primary-600 font-medium">Sign in</a></p>
                    <p class="text-center text-[11px] text-gray-400 mt-4">© <?php echo date('Y'); ?> JAKPOS. All rights reserved.</p>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Country dropdown functionality
        const dropdownBtn = document.getElementById('countryDropdownBtn');
        const dropdownMenu = document.getElementById('countryDropdownMenu');
        const countrySearch = document.getElementById('countrySearch');
        const countryList = document.getElementById('countryList');
        const selectedFlag = document.getElementById('selectedFlag');
        const selectedDialCode = document.getElementById('selectedDialCode');
        const countryCodeInput = document.getElementById('countryCode');
        
        if (dropdownBtn) {
            dropdownBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
                if (dropdownMenu.classList.contains('show')) countrySearch?.focus();
            });
        }
        
        document.addEventListener('click', function() { dropdownMenu?.classList.remove('show'); });
        
        if (countrySearch) {
            countrySearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const options = countryList.querySelectorAll('.country-option');
                options.forEach(option => {
                    const countryName = option.getAttribute('data-name').toLowerCase();
                    option.style.display = countryName.includes(searchTerm) ? 'flex' : 'none';
                });
            });
        }
        
        const countryOptions = document.querySelectorAll('.country-option');
        countryOptions.forEach(option => {
            option.addEventListener('click', function() {
                const dialCode = this.getAttribute('data-dial');
                const flagCode = this.getAttribute('data-flag');
                selectedFlag.src = `https://flagcdn.com/w40/${flagCode}.png`;
                selectedDialCode.textContent = dialCode;
                countryCodeInput.value = dialCode;
                dropdownMenu.classList.remove('show');
                countryOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
        
        function initiateGoogleSignup() {
            const googleAuthUrl = '<?= base_url("auth/google-signup.php") ?>';
            fetch(googleAuthUrl, { method: 'HEAD' })
                .then(r => { if (r.ok) window.location.href = googleAuthUrl; else alert('Google signup is not configured yet.'); })
                .catch(() => alert('Google signup is not configured yet.'));
        }
        
        const passwordInput = document.getElementById('password');
        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        
        function validatePassword(password) {
            let valid = true;
            if (password.length >= 8) { reqLength.classList.add('valid'); reqLength.classList.remove('invalid'); } else { reqLength.classList.add('invalid'); reqLength.classList.remove('valid'); valid = false; }
            if (/[A-Z]/.test(password)) { reqUpper.classList.add('valid'); reqUpper.classList.remove('invalid'); } else { reqUpper.classList.add('invalid'); reqUpper.classList.remove('valid'); valid = false; }
            if (/[a-z]/.test(password)) { reqLower.classList.add('valid'); reqLower.classList.remove('invalid'); } else { reqLower.classList.add('invalid'); reqLower.classList.remove('valid'); valid = false; }
            if (/[0-9]/.test(password)) { reqNumber.classList.add('valid'); reqNumber.classList.remove('invalid'); } else { reqNumber.classList.add('invalid'); reqNumber.classList.remove('valid'); valid = false; }
            return valid;
        }
        
        if (passwordInput) passwordInput.addEventListener('input', function() { validatePassword(this.value); });
        
        const companyInput = document.getElementById('company_name');
        const companyStatus = document.getElementById('companyStatus');
        const companySpinner = document.getElementById('companySpinner');
        let debounceTimer;
        
        if (companyInput) {
            companyInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const name = this.value.trim();
                if (name.length < 3) { if (companyStatus) companyStatus.innerHTML = ''; return; }
                if (companySpinner) companySpinner.classList.remove('hidden');
                debounceTimer = setTimeout(() => {
                    fetch(`?check_company=1&name=${encodeURIComponent(name)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (companySpinner) companySpinner.classList.add('hidden');
                            if (data.available) {
                                companyStatus.innerHTML = '<span class="text-green-600">✓ Company name available</span>';
                                companyInput.classList.remove('border-red-400');
                                companyInput.classList.add('border-green-400');
                            } else {
                                companyStatus.innerHTML = '<span class="text-red-500">✗ ' + data.message + '</span>';
                                companyInput.classList.remove('border-green-400');
                                companyInput.classList.add('border-red-400');
                            }
                        })
                        .catch(error => { if (companySpinner) companySpinner.classList.add('hidden'); console.error('Error:', error); });
                }, 500);
            });
        }
        
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password')?.value || '';
                if (!validatePassword(password)) { e.preventDefault(); alert('Please meet all password requirements.'); return false; }
            });
        }
        
        // Registration Carousel
        (function() {
            const slides = document.querySelectorAll('.carousel-slide');
            const dots = document.querySelectorAll('.carousel-dot');
            if (!slides.length) return;
            
            let current = 0;
            let timer;
            
            function show(index) {
                slides.forEach((s, i) => {
                    s.classList.toggle('active', i === index);
                });
                dots.forEach((d, i) => {
                    d.classList.toggle('active', i === index);
                });
                current = index;
            }
            
            function next() {
                show((current + 1) % slides.length);
            }
            
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    show(index);
                    clearInterval(timer);
                    timer = setInterval(next, 5000);
                });
            });
            
            timer = setInterval(next, 5000);
        })();
    </script>
</body>
</html>
<?php ob_end_flush(); ?>