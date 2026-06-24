<?php
declare(strict_types=1);
/**
 * JDH POS - Side by Side Login Page with Country Selection
 * Carousel left, login form right, modern design
 *
 * @package JDH_POS
 * @version 7.5 - Pure Tailwind CSS
 */

// ============================================================================
// Session Security
// ============================================================================

session_name('jakababa_saas_sid');
$cookieHost = $_SERVER['HTTP_HOST'] ?? '';
$cookieDomain = '';
if ($cookieHost !== '') {
    $parsedHost = parse_url((strpos($cookieHost, '://') === false ? 'http://' : '') . $cookieHost, PHP_URL_HOST);
    if ($parsedHost && $parsedHost !== 'localhost' && !filter_var($parsedHost, FILTER_VALIDATE_IP)) {
        $cookieDomain = $parsedHost;
    }
}

// ============================================================================
// Brute Force Protection
// ============================================================================
$maxFailedAttempts = 5;
$lockoutDuration = 300; // 5 minutes
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . '/jdh_login_' . md5($clientIp) . '.json';

function getLoginAttempts(): array {
    global $rateLimitFile;
    if (isset($_SESSION['_login_attempts'])) {
        return $_SESSION['_login_attempts'];
    }
    if (!file_exists($rateLimitFile)) return ['count' => 0, 'last_attempt' => 0, 'locked_until' => 0];
    $data = json_decode(file_get_contents($rateLimitFile), true);
    return $data ?: ['count' => 0, 'last_attempt' => 0, 'locked_until' => 0];
}

function recordFailedAttempt() {
    global $rateLimitFile, $maxFailedAttempts, $lockoutDuration;
    $data = getLoginAttempts();
    $data['count']++;
    $data['last_attempt'] = time();
    if ($data['count'] >= $maxFailedAttempts) {
        $data['locked_until'] = time() + $lockoutDuration;
    }
    $_SESSION['_login_attempts'] = $data;
    file_put_contents($rateLimitFile, json_encode($data), LOCK_EX);
}

function resetLoginAttempts() {
    global $rateLimitFile;
    unset($_SESSION['_login_attempts']);
    if (file_exists($rateLimitFile)) {
        unlink($rateLimitFile);
    }
}

function isLockedOut(): bool {
    $data = getLoginAttempts();
    return ($data['locked_until'] ?? 0) > time();
}

function getLockoutTimeRemaining(): int {
    $data = getLoginAttempts();
    return max(0, ($data['locked_until'] ?? 0) - time());
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $cookieDomain,
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SESSION['created_at'] ?? 0) < time() - 300) {
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}

$csrfToken = getLoginCsrfToken();
$_SESSION['csrf_token'] = $csrfToken;
setcookie('jakababa_login_csrf', $csrfToken, [
    'expires' => time() + 3600,
    'path' => '/',
    'domain' => $cookieDomain,
    'secure' => false,
    'httponly' => false,
    'samesite' => 'Lax'
]);

// ============================================================================
// Security Headers
// ============================================================================

if (!headers_sent()) {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https://flagcdn.com; connect-src 'self'");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// ============================================================================
// Include Database Helpers
// ============================================================================

$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/src/paths.php';
require_once $rootPath . '/src/db.php';
require_once $rootPath . '/src/functions.php';
require_once $rootPath . '/src/OtpService.php';

// ============================================================================
// Load Countries from JSON File (cached in memory for 1 hour)
// ============================================================================

$countriesFile = $rootPath . '/data/countries.json';
$countries = [];
$countriesCacheFile = sys_get_temp_dir() . '/jdh_countries_cache.json';

if (file_exists($countriesCacheFile) && (time() - filemtime($countriesCacheFile) < 3600)) {
    $countries = json_decode(file_get_contents($countriesCacheFile), true) ?: [];
}

if (empty($countries)) {
    if (file_exists($countriesFile)) {
        $data = json_decode(file_get_contents($countriesFile), true);
        $countries = $data['countries'] ?? [];
    } else {
        $countries = [
            ['code' => 'KE', 'name' => 'Kenya', 'dial_code' => '+254', 'flag' => 'ke'],
            ['code' => 'UG', 'name' => 'Uganda', 'dial_code' => '+256', 'flag' => 'ug'],
            ['code' => 'TZ', 'name' => 'Tanzania', 'dial_code' => '+255', 'flag' => 'tz'],
            ['code' => 'US', 'name' => 'United States', 'dial_code' => '+1', 'flag' => 'us'],
            ['code' => 'GB', 'name' => 'United Kingdom', 'dial_code' => '+44', 'flag' => 'gb'],
        ];
    }
    if (!empty($countries)) {
        file_put_contents($countriesCacheFile, json_encode($countries));
    }
}

// ============================================================================
// Auto-detect single tenant for pre-fill (cached for 60 seconds)
// ============================================================================
$autoTenantCode = '';
$tenantCacheKey = 'jdh_login_tenant_auto';
if (isset($_SESSION[$tenantCacheKey]) && (time() - ($_SESSION[$tenantCacheKey . '_time'] ?? 0) < 60)) {
    $autoTenantCode = $_SESSION[$tenantCacheKey];
} else {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query("SELECT subdomain, name FROM tenants WHERE status='active' AND is_active=1 AND deleted_at IS NULL LIMIT 2");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($tenants) === 1) {
            $autoTenantCode = $tenants[0]['subdomain'] ?: $tenants[0]['name'];
        }
        $_SESSION[$tenantCacheKey] = $autoTenantCode;
        $_SESSION[$tenantCacheKey . '_time'] = time();
    } catch (Exception $e) {}
}

// ============================================================================
// Helper Functions
// ============================================================================

function getLoginCsrfToken(): string
{
    if (!empty($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }
    
    return $_COOKIE['jakababa_login_csrf'] ?? bin2hex(random_bytes(32));
}

function verify_csrf_token($token) {
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $cookieToken = $_COOKIE['jakababa_login_csrf'] ?? null;
    
    return (is_string($token) && is_string($sessionToken) && hash_equals($sessionToken, $token))
        || (is_string($token) && is_string($cookieToken) && hash_equals($cookieToken, $token));
}

function authenticateUser($tenantCode, $username, $password) {
    global $rootPath;
    try {
        $pdo = get_db_connection();
        
        $stmt = $pdo->prepare("
            SELECT id, name, subdomain, status, is_active, is_suspended, currency, timezone, business_type
            FROM tenants 
            WHERE (subdomain = ? OR name = ? OR id = ?) AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$tenantCode, $tenantCode, (int) $tenantCode]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tenant) {
            return ['success' => false, 'error' => 'Invalid tenant code.'];
        }
        
        if ($tenant['status'] !== 'active' || $tenant['is_active'] != 1) {
            return ['success' => false, 'error' => 'Tenant account is not active.'];
        }
        
        if ($tenant['is_suspended'] == 1) {
            return ['success' => false, 'error' => 'Tenant account has been suspended.'];
        }

        // Real-time subscription/trial check
        if (file_exists($rootPath . '/src/SubscriptionManager.php')) {
            require_once $rootPath . '/src/SubscriptionManager.php';
            $subMgr = new SubscriptionManager($pdo);
            if ($subMgr->isTrialExpired((int)$tenant['id'])) {
                $subMgr->suspendExpiredTenant((int)$tenant['id']);
                return ['success' => false, 'error' => 'Your free trial has expired. Please contact support to continue.'];
            }
        }

        $stmt = $pdo->prepare("
            SELECT id, name, email, username, password_hash, role_id, 
                   status, branch_id, tenant_id, 
                   last_login, created_at,
                   failed_login_attempts, locked_until
            FROM users 
            WHERE (email = ? OR username = ?) AND tenant_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$username, $username, $tenant['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid credentials.'];
        }
        
        if ($user['status'] != 1) {
            return ['success' => false, 'error' => 'Account is disabled.'];
        }
        
        if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
            $lockedUntil = new DateTime($user['locked_until']);
            return ['success' => false, 'error' => 'Account locked until ' . $lockedUntil->format('g:i A') . '.'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            if ($user['failed_login_attempts'] + 1 >= 5) {
                $stmt = $pdo->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
                $stmt->execute([$user['id']]);
            }
            
            return ['success' => false, 'error' => 'Invalid credentials.'];
        }
        
        $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return [
            'success' => true,
            'user' => $user,
            'tenant' => $tenant
        ];
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Authentication failed. Please try again.'];
    }
}

function normalizeLoginRoleName(string $roleName): string
{
    $role = strtolower(trim($roleName));
    $role = str_replace([' ', '-', '/'], '_', $role);
    
    return match ($role) {
        'administrator', 'admin', 'owner', 'super_admin', 'superadmin', 'super-admin' => 'admin',
        'branch_manager', 'manager' => 'manager',
        'cashier', 'sales', 'pos' => 'cashier',
        'inventory', 'stock' => 'inventory',
        default => $role ?: 'user',
    };
}

function getLoginRoleName(PDO $pdo, array $user, int $tenantId): string
{
    $roleId = (int) ($user['role_id'] ?? 0);
    
    if ($roleId > 0) {
        $role = $pdo->prepare("
            SELECT name FROM roles
            WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $role->execute([$roleId, $tenantId]);
        $roleName = $role->fetchColumn();

        if ($roleName) {
            return normalizeLoginRoleName((string) $roleName);
        }
    }
    
    return match ((int) $roleId) {
        1 => 'admin',
        2 => 'manager',
        3 => 'cashier',
        4 => 'inventory',
        default => 'user',
    };
}

function getLoginPermissions(PDO $pdo, int $userId, int $tenantId, string $roleName): array
{
    $permissions = $pdo->prepare("
        SELECT DISTINCT p.code
        FROM permissions p
        JOIN role_permissions rp ON rp.permission_id = p.id AND rp.tenant_id = p.tenant_id
        JOIN user_roles ur ON ur.role_id = rp.role_id AND ur.tenant_id = rp.tenant_id
        WHERE ur.user_id = ? AND p.tenant_id = ? AND p.deleted_at IS NULL
        ORDER BY p.code
    ");
    $permissions->execute([$userId, $tenantId]);
    $codes = array_values(array_column($permissions->fetchAll(PDO::FETCH_ASSOC), 'code'));
    
    if (!empty($codes)) {
        return $codes;
    }
    
    if (in_array($roleName, ['admin', 'owner', 'superadmin', 'super_admin'], true)) {
        $all = $pdo->prepare("SELECT code FROM permissions WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY code");
        $all->execute([$tenantId]);
        return array_values(array_column($all->fetchAll(PDO::FETCH_ASSOC), 'code'));
    }
    
    return [];
}

function getLoginBranchName(PDO $pdo, int $branchId, int $tenantId): string
{
    if ($branchId <= 0) {
        return '';
    }
    
    $stmt = $pdo->prepare("
        SELECT name FROM branches
        WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$branchId, $tenantId]);
    
    return (string) ($stmt->fetchColumn() ?? '');
}

function completeLogin($user, $tenant, $rememberMe) {
    session_regenerate_id(true);
    
    $pdo = get_db_connection();
    $tenantId = (int) $tenant['id'];
    $branchId = (int) ($user['branch_id'] ?? 1);
    $roleName = getLoginRoleName($pdo, $user, $tenantId);
    $branchName = getLoginBranchName($pdo, $branchId, $tenantId);

    $permissions = getLoginPermissions($pdo, (int) $user['id'], $tenantId, $roleName);
    
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['username'] ?? ($user['email'] ?? '');
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['role'] = $roleName;
    $_SESSION['user_role'] = $roleName;
    $_SESSION['role_id'] = (int) ($user['role_id'] ?? 0);
    $_SESSION['tenant_id'] = $tenantId;
    $_SESSION['tenant_name'] = $tenant['name'];
    $_SESSION['tenant_slug'] = $tenant['subdomain'] ?? '';
    $_SESSION['tenant_code'] = $tenant['subdomain'] ?? '';
    $_SESSION['tenant_status'] = $tenant['status'] ?? 'active';
    $_SESSION['tenant_currency'] = $tenant['currency'] ?? 'KES';
    $_SESSION['tenant_timezone'] = $tenant['timezone'] ?? 'Africa/Nairobi';
    $_SESSION['tenant_validated'] = true;
    $_SESSION['subscription_status'] = $tenant['status'] ?? 'active';
    $_SESSION['business_type'] = $tenant['business_type'] ?? 'retail';
    $_SESSION['branch_id'] = $branchId;
    $_SESSION['branch_name'] = $branchName;
    $_SESSION['permissions'] = $permissions;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['user_email'],
        'role' => $roleName,
        'role_id' => (int) ($user['role_id'] ?? 0),
        'tenant_id' => $tenantId,
        'branch_id' => $branchId,
    ];
    
    if ($rememberMe) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                INSERT INTO user_tokens (user_id, token_hash, type, expires_at, tenant_id)
                VALUES (?, ?, 'remember_me', DATE_ADD(NOW(), INTERVAL 30 DAY), ?)
            ");
            $stmt->execute([$user['id'], $hashedToken, $tenantId]);
        } catch (Exception $e) {}
        
        setcookie('remember_token', $token, time() + 2592000, '/', '', false, true);
    }
    
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_login_ip = ? WHERE id = ?");
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);
    } catch (Exception $e) {}
    
    // Reset failed login attempts
    resetLoginAttempts();
    
    // Log successful login
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, tenant_id, ip_address, user_agent, status, created_at)
            VALUES (?, ?, ?, ?, 'success', NOW())
        ");
        $stmt->execute([
            $user['id'],
            $tenantId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {}
}

// ============================================================================
// Process Login Request
// ============================================================================

$error = '';
$success_message = '';
$showCaptcha = false;
$lockoutTimeRemaining = 0;

// Check if user is locked out
if (isLockedOut()) {
    $lockoutTimeRemaining = getLockoutTimeRemaining();
    $error = 'Too many failed login attempts. Please try again in ' . ceil($lockoutTimeRemaining / 60) . ' minutes.';
}

// Show CAPTCHA after 3 failed attempts
$attempts = getLoginAttempts();
if ($attempts['count'] >= 3) {
    $showCaptcha = true;
}

if (!empty($_GET['registered'])) {
    $success_message = 'Account created successfully! Sign in below.';
}
if (!empty($_GET['tenant'])) {
    $autoTenantCode = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['tenant']));
}
if (!empty($_GET['error'])) {
    $errMap = [
        'tenant_inactive' => 'Tenant account is inactive or suspended.',
        'subscription_expired' => 'Your free trial has expired. Please contact support to continue.',
        'session_expired' => 'Your session has expired. Please sign in again.',
    ];
    $error = $errMap[$_GET['error']] ?? 'An error occurred. Please try again.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $loginType = $_POST['login_type'] ?? 'email';
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh the page.';
        $csrfToken = generate_csrf_token();
        setcookie('jakababa_login_csrf', $csrfToken, [
            'expires' => time() + 3600,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    } elseif ($loginType === 'phone') {
        // Phone login - send real OTP via SMS
        $phone = $_POST['phone'] ?? '';
        $countryCode = $_POST['country_code'] ?? '+254';
        $tenantCodePhone = $_POST['tenant_code_phone'] ?? '';
        
        // Remove leading 0 if present (local format to international)
        $phone = ltrim($phone, '0');
        $fullPhone = $countryCode . $phone;
        
        // Verify tenant exists
        if (empty($tenantCodePhone)) {
            $error = 'Please enter your tenant code.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE code = ? AND active = 1 LIMIT 1");
                $stmt->execute([$tenantCodePhone]);
                $tenant = $stmt->fetch();
                
                if (!$tenant) {
                    $error = 'Invalid tenant code.';
                } else {
                    // Check if user exists with this phone
                    $stmt = $pdo->prepare("
                        SELECT u.id, u.phone, u.name, u.tenant_id 
                        FROM users u 
                        WHERE u.tenant_id = ? AND u.phone = ? AND u.active = 1 
                        LIMIT 1
                    ");
                    $stmt->execute([$tenant['id'], $fullPhone]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        $error = 'No account found with this phone number.';
                    } else {
                        // Send OTP via SMS
                        $otpService = new OtpService($pdo);
                        $result = $otpService->generateAndSend($fullPhone, 'login');
                        
                        if ($result['success']) {
                            // Store pending login in session
                            $_SESSION['pending_otp_phone'] = $fullPhone;
                            $_SESSION['pending_otp_tenant'] = $tenant['id'];
                            $_SESSION['pending_otp_user'] = $user['id'];
                            $_SESSION['pending_otp_time'] = time();
                            
                            $success_message = 'Verification code sent to ' . $fullPhone;
                            // Redirect to OTP verification page
                            header('Location: verify_otp.php?phone=' . urlencode($fullPhone));
                            exit;
                        } else {
                            $error = $result['message'];
                            if (isset($result['otp'])) {
                                $error .= ' (Test mode - Code: ' . $result['otp'] . ')';
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Unable to send verification code. Please try again.';
                error_log("OTP send error: " . $e->getMessage());
            }
        }
    } else {
        $tenantCode = $_POST['tenant_code'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (empty($tenantCode)) {
            $error = 'Please enter your tenant code.';
        } elseif (empty($username)) {
            $error = 'Please enter your email or username.';
        } elseif (empty($password)) {
            $error = 'Please enter your password.';
        } else {
            $auth = authenticateUser($tenantCode, $username, $password);
            
            if ($auth['success']) {
                completeLogin($auth['user'], $auth['tenant'], $rememberMe);
                header('Location: ' . base_url('dashboard/home.php'));
                exit;
            } else {
                $error = $auth['error'];
                recordFailedAttempt();
                $showCaptcha = getLoginAttempts()['count'] >= 3;
            }
        }
    }
}

$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to JAKPOS POS system">
    <title>JAKPOS | Sign In</title>
    
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .country-dropdown-menu { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 0.375rem; box-shadow: 0 8px 12px -3px rgba(0, 0, 0, 0.08); max-height: 220px; overflow-y: auto; z-index: 50; display: none; margin-top: 0.25rem; }
        .country-dropdown-menu.show { display: block; }
        .country-option { display: flex; align-items: center; justify-content: space-between; padding: 0.35rem 0.625rem; cursor: pointer; transition: background-color 0.15s; }
        .country-option:hover { background-color: #FEF3C7; } .country-option.selected { background-color: #FEF3C7; border-left: 2px solid #F59E0B; }
        .flag-img { width: 1.25rem; height: 0.875rem; object-fit: cover; border-radius: 0.125rem; flex-shrink: 0; display: inline-block; vertical-align: middle; }
        .btn-primary { background-color: #F59E0B; color: white; transition: all 0.15s ease; }
        .btn-primary:hover { background-color: #D97706; } .btn-primary:active { transform: scale(0.98); } .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; }
        .tab-btn.active { background-color: #FEF3C7; color: #D97706; font-weight: 500; }
        .tab-btn:not(.active) { color: #6B7280; } .tab-btn:not(.active):hover { background-color: #F3F4F6; }
        .carousel-slide { transition: opacity 0.5s ease-in-out; } .carousel-slide.hidden { opacity: 0; display: none; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex items-center justify-center p-4">

    <main class="w-full max-w-7xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row fade-in">
        
        <!-- Left Column - Carousel -->
        <div class="flex md:w-1/2 bg-gradient-to-br from-amber-500 to-orange-600 p-8 flex-col justify-between relative min-h-[500px]">
            <!-- Logo -->
            <div class="flex items-center gap-2">
                <img src="<?php echo asset_url('img/logo.png'); ?>" alt="JAKPOS" class="h-10 w-auto drop-shadow-md">
            </div>
            
            <!-- Carousel Content -->
            <div class="flex-1 flex flex-col items-center justify-center text-center text-white space-y-6">
                <div id="carouselContainer" class="relative w-full max-w-sm">
                    <!-- Slide 1 -->
                    <div class="carousel-slide" data-index="0">
                        <div class="text-7xl mb-4">🚀</div>
                        <h2 class="text-2xl font-bold">Streamline Your Business</h2>
                        <p class="text-white/80 text-sm mt-2">Manage sales, inventory, and customers all in one powerful POS system.</p>
                    </div>
                    
                    <!-- Slide 2 -->
                    <div class="carousel-slide hidden" data-index="1">
                        <div class="text-7xl mb-4">💳</div>
                        <h2 class="text-2xl font-bold">Fast & Secure Payments</h2>
                        <p class="text-white/80 text-sm mt-2">Accept M-Pesa, cash, cards, and more with real-time sync.</p>
                    </div>
                    
                    <!-- Slide 3 -->
                    <div class="carousel-slide hidden" data-index="2">
                        <div class="text-7xl mb-4">📊</div>
                        <h2 class="text-2xl font-bold">Real-Time Analytics</h2>
                        <p class="text-white/80 text-sm mt-2">Make data-driven decisions with live reports and insights.</p>
                    </div>
                    
                    <!-- Slide 4 -->
                    <div class="carousel-slide hidden" data-index="3">
                        <div class="text-7xl mb-4">🌍</div>
                        <h2 class="text-2xl font-bold">Multi-Branch Management</h2>
                        <p class="text-white/80 text-sm mt-2">Control multiple stores from one central dashboard.</p>
                    </div>
                </div>
                
                <!-- Carousel Indicators -->
                <div class="flex gap-2">
                    <button class="carousel-dot w-2 h-2 rounded-full bg-white/50 transition-all" data-index="0"></button>
                    <button class="carousel-dot w-2 h-2 rounded-full bg-white/30 transition-all" data-index="1"></button>
                    <button class="carousel-dot w-2 h-2 rounded-full bg-white/30 transition-all" data-index="2"></button>
                    <button class="carousel-dot w-2 h-2 rounded-full bg-white/30 transition-all" data-index="3"></button>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="text-white/60 text-xs text-center">
                &copy; <?php echo date('Y'); ?> JAKPOS. All rights reserved.
            </div>
        </div>
        
        <!-- Right Column - Login Form -->
        <div class="w-full md:w-1/2 p-6 md:p-8 lg:p-10">
            <div class="max-w-sm mx-auto">
                <!-- Mobile Logo -->
                <div class="md:hidden flex items-center gap-2 mb-6">
                    <img src="<?php echo asset_url('img/logo.png'); ?>" alt="JAKPOS" class="h-10 w-auto">
                </div>
                
                <h1 class="text-xl font-bold text-gray-900">Sign In</h1>
                <p class="text-sm text-gray-500 mt-1">Access your dashboard and manage your business</p>
                
                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-start gap-2" role="alert">
                        <svg class="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2" role="alert">
                        <svg class="w-4 h-4 text-red-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Login Tabs -->
                <div class="mt-6 flex gap-1 bg-gray-100 rounded-lg p-1">
                    <button type="button" class="tab-btn flex-1 px-3 py-2 rounded-md text-sm font-medium transition-colors active" data-tab="email">
                        Email
                    </button>
                    <button type="button" class="tab-btn flex-1 px-3 py-2 rounded-md text-sm font-medium transition-colors" data-tab="phone">
                        Phone
                    </button>
                </div>
                
                <!-- Email Login Form -->
                <form id="emailLoginForm" method="POST" action="" class="mt-6 space-y-4" role="tabpanel">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="login_type" value="email">
                    
                    <div>
                        <label for="tenant_code" class="block text-sm font-medium text-gray-700 mb-1">Tenant Code</label>
                        <input type="text" 
                               id="tenant_code"
                               name="tenant_code" 
                               value="<?php echo htmlspecialchars($_POST['tenant_code'] ?? $autoTenantCode); ?>"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                               placeholder="your-company"
                               autocomplete="organization"
                               required>
                    </div>
                    
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Email or Username</label>
                        <input type="text" 
                               id="username"
                               name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                               placeholder="you@company.com"
                               autocomplete="username"
                               required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <input type="password" 
                                   id="password"
                                   name="password" 
                                   class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-amber-500 focus:border-transparent transition pr-10"
                                   placeholder="••••••••"
                                   autocomplete="current-password"
                                   required>
                            <button type="button" 
                                    onclick="togglePassword('password', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                    aria-label="Toggle password visibility">
                                <svg class="w-4 h-4 eye-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg class="w-4 h-4 eye-slash-icon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <label class="flex items-center cursor-pointer select-none">
                            <input type="checkbox" name="remember_me" value="1" class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                            <span class="ml-2 text-sm text-gray-600">Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="text-sm text-amber-500 hover:text-amber-600 font-medium">Forgot password?</a>
                    </div>
                    
                    <button type="submit" id="submitBtn" class="w-full btn-primary font-medium py-2.5 rounded-lg text-sm flex items-center justify-center gap-2">
                        <span id="btnText">Sign In</span>
                        <svg id="btnSpinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>
                
                <!-- Phone Login Form -->
                <form id="phoneLoginForm" method="POST" action="" class="mt-6 space-y-4 hidden" role="tabpanel">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="login_type" value="phone">
                    <input type="hidden" name="country_code" id="countryCode" value="+254">
                    
                    <div>
                        <label for="tenant_code_phone" class="block text-sm font-medium text-gray-700 mb-1">Tenant Code</label>
                        <input type="text" 
                               id="tenant_code_phone"
                               name="tenant_code_phone" 
                               class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                               placeholder="your-company"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <div class="flex gap-2">
                            <div class="w-28 relative">
                                <button type="button" 
                                        id="countryDropdownBtn"
                                        class="w-full px-2 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 bg-white flex items-center justify-between gap-1 hover:bg-gray-50 transition focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                        aria-haspopup="listbox"
                                        aria-expanded="false">
                                    <span class="flex items-center gap-1">
                                        <img id="selectedFlag" src="https://flagcdn.com/w40/ke.png" alt="" class="flag-img">
                                        <span id="selectedDialCode" class="text-sm">+254</span>
                                    </span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div id="countryDropdownMenu" class="country-dropdown-menu" role="listbox">
                                    <input type="text" 
                                           id="countrySearch" 
                                           placeholder="Search..." 
                                           class="w-full px-3 py-2 text-sm border-b border-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-500 sticky top-0 bg-white">
                                    <div id="countryList" class="max-h-52 overflow-y-auto">
                                        <?php foreach ($countries as $country): ?>
                                            <div class="country-option" 
                                                 role="option"
                                                 data-code="<?= htmlspecialchars($country['code']) ?>"
                                                 data-dial="<?= htmlspecialchars($country['dial_code']) ?>"
                                                 data-flag="<?= htmlspecialchars($country['flag']) ?>"
                                                 data-name="<?= htmlspecialchars($country['name']) ?>">
                                                <span class="flex items-center gap-2">
                                                    <img src="https://flagcdn.com/w40/<?= htmlspecialchars($country['flag']) ?>.png" alt="" class="flag-img">
                                                    <span class="text-sm"><?= htmlspecialchars($country['name']) ?></span>
                                                </span>
                                                <span class="text-sm text-gray-500"><?= htmlspecialchars($country['dial_code']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <input type="tel" 
                                   name="phone" 
                                   id="phoneNumber" 
                                   class="flex-1 px-3 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                                   placeholder="712345678"
                                   required>
                        </div>
                    </div>
                    
                    <button type="submit" id="phoneSubmitBtn" class="w-full btn-primary font-medium py-2.5 rounded-lg text-sm flex items-center justify-center gap-2">
                        <span id="phoneBtnText">Send OTP</span>
                        <svg id="phoneBtnSpinner" class="hidden w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>
                
                <!-- Sign Up Link -->
                <p class="mt-6 text-center text-sm text-gray-500">
                    Don't have an account? 
                    <a href="register.php" class="text-amber-500 hover:text-amber-600 font-medium">Create Account</a>
                </p>
                
                <!-- Google Sign In -->
                <div class="mt-4">
                    <button type="button" onclick="initiateGoogleLogin()" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        <svg class="w-5 h-5" viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Sign in with Google
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // ============================================================
        // Carousel
        // ============================================================
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const dots = document.querySelectorAll('.carousel-dot');
        let autoSlideInterval;
        
        function goToSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.add('hidden');
                if (dots[i]) dots[i].className = 'carousel-dot w-2 h-2 rounded-full bg-white/30 transition-all';
            });
            slides[index].classList.remove('hidden');
            if (dots[index]) dots[index].className = 'carousel-dot w-2 h-2 rounded-full bg-white/70 transition-all';
            currentSlide = index;
        }
        
        function nextSlide() {
            goToSlide((currentSlide + 1) % slides.length);
        }
        
        // Start auto-slide
        function startAutoSlide() {
            if (autoSlideInterval) clearInterval(autoSlideInterval);
            autoSlideInterval = setInterval(nextSlide, 5000);
        }
        
        // Dot click handlers
        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                goToSlide(index);
                startAutoSlide();
            });
        });
        
        // Initialize
        if (slides.length > 0) {
            goToSlide(0);
            startAutoSlide();
        }
        
        // ============================================================
        // Password visibility toggle
        // ============================================================
        function togglePassword(fieldId, btn) {
            const input = document.getElementById(fieldId);
            const eyeIcon = btn.querySelector('.eye-icon');
            const eyeSlashIcon = btn.querySelector('.eye-slash-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon?.classList.add('hidden');
                eyeSlashIcon?.classList.remove('hidden');
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                eyeIcon?.classList.remove('hidden');
                eyeSlashIcon?.classList.add('hidden');
                btn.setAttribute('aria-label', 'Show password');
            }
        }
        
        // ============================================================
        // Country dropdown
        // ============================================================
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
                const isOpen = dropdownMenu.classList.toggle('show');
                dropdownBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (isOpen) countrySearch?.focus();
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!dropdownBtn?.contains(e.target)) {
                dropdownMenu?.classList.remove('show');
                dropdownBtn?.setAttribute('aria-expanded', 'false');
            }
        });
        
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
                dropdownBtn.setAttribute('aria-expanded', 'false');
                
                countryOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
        // ============================================================
        // Tab switching
        // ============================================================
        const tabBtns = document.querySelectorAll('.tab-btn');
        const emailForm = document.getElementById('emailLoginForm');
        const phoneForm = document.getElementById('phoneLoginForm');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');
                
                tabBtns.forEach(b => {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                
                if (tab === 'email') {
                    emailForm.classList.remove('hidden');
                    phoneForm.classList.add('hidden');
                } else {
                    emailForm.classList.add('hidden');
                    phoneForm.classList.remove('hidden');
                }
            });
        });
        
        // ============================================================
        // Form validation and loading state
        // ============================================================
        document.getElementById('emailLoginForm')?.addEventListener('submit', function(e) {
            const tenantCode = this.querySelector('[name="tenant_code"]')?.value.trim();
            const username = this.querySelector('[name="username"]')?.value.trim();
            const password = this.querySelector('[name="password"]')?.value;
            
            if (!tenantCode) {
                e.preventDefault();
                alert('Please enter your tenant code');
                return false;
            }
            if (!username) {
                e.preventDefault();
                alert('Please enter your email or username');
                return false;
            }
            if (!password || password.length < 1) {
                e.preventDefault();
                alert('Please enter your password');
                return false;
            }
            
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
            }
            if (btnText) btnText.textContent = 'Signing in...';
            if (btnSpinner) btnSpinner.classList.remove('hidden');
        });
        
        document.getElementById('phoneLoginForm')?.addEventListener('submit', function(e) {
            const tenantCode = this.querySelector('[name="tenant_code_phone"]')?.value.trim();
            const phone = this.querySelector('[name="phone"]')?.value.trim();
            
            if (!tenantCode) {
                e.preventDefault();
                alert('Please enter your tenant code');
                return false;
            }
            if (!phone) {
                e.preventDefault();
                alert('Please enter your phone number');
                return false;
            }
            
            const btn = document.getElementById('phoneSubmitBtn');
            const btnText = document.getElementById('phoneBtnText');
            const btnSpinner = document.getElementById('phoneBtnSpinner');
            
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
            }
            if (btnText) btnText.textContent = 'Sending...';
            if (btnSpinner) btnSpinner.classList.remove('hidden');
        });
        
        // ============================================================
        // Google Login placeholder
        // ============================================================
        function initiateGoogleLogin() {
            alert('Google Sign-In will be available soon.');
            // window.location.href = 'google-login.php';
        }
        
        // ============================================================
        // Auto-focus first empty field
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const fields = ['tenant_code', 'username', 'password'];
            for (const field of fields) {
                const input = document.querySelector(`[name="${field}"]`);
                if (input && !input.value) {
                    input.focus();
                    break;
                }
            }
        });
    </script>
</body>
</html>