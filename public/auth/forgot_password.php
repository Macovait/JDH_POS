<?php
declare(strict_types=1);

/**
 * Forgot Password Page (SaaS Version) - Clean Centered
 * 
 * Allows a user to request a password reset link using tenant code/subdomain.
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
    redirect(dashboard_url());
    exit;
}

$csrf_token = generate_csrf_token();

// Detect subdomain-based tenant
$host = $_SERVER['HTTP_HOST'] ?? '';
$detected_tenant_code = null;
if (preg_match('/^([a-z0-9-]+)\./i', $host, $matches)) {
    $subdomain = strtolower($matches[1]);
    if ($subdomain !== 'www' && $subdomain !== 'admin') {
        $detected_tenant_code = $subdomain;
    }
}

// Ensure password_resets table exists
try {
    $pdo = get_db_connection();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `token` varchar(255) NOT NULL,
            `expires_at` datetime NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_token` (`token`),
            KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log("Failed to create password_resets table: " . $e->getMessage());
}

$error = null;
$success = null;
$tenant_code = $detected_tenant_code ?? '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page.';
        $csrf_token = generate_csrf_token();
    } else {
        $tenant_code = trim($_POST['tenant_code'] ?? '');
        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($tenant_code) || empty($identifier)) {
            $error = 'Please enter your tenant code and email/username.';
        } else {
            try {
                // Find tenant
                $tenant = db_fetch_one(
                    "SELECT id, name, subdomain, email as tenant_email, is_active, is_suspended 
                     FROM tenants 
                     WHERE (subdomain = ? OR name = ?) 
                       AND deleted_at IS NULL",
                    [$tenant_code, $tenant_code]
                );

                if (!$tenant) {
                    // Don't reveal if tenant exists for security
                    $success = 'If an account exists, you will receive a password reset email.';
                } elseif ($tenant['is_active'] != 1 || $tenant['is_suspended'] == 1) {
                    $error = 'Tenant account is not active. Please contact support.';
                } else {
                    // Find user by email or username within that tenant
                    $user = db_fetch_one("
                        SELECT u.id, u.name, u.email, u.username, u.status
                        FROM users u
                        WHERE u.tenant_id = ? 
                          AND u.status = 1
                          AND (u.email = ? OR u.username = ?)
                          AND u.deleted_at IS NULL
                    ", [$tenant['id'], $identifier, $identifier]);

                    if (!$user) {
                        // Don't reveal if user exists for security
                        $success = 'If an account exists, you will receive a password reset email.';
                    } else {
                        // Delete any existing expired tokens
                        db_query(
                            "DELETE FROM password_resets WHERE user_id = ? AND expires_at < NOW()",
                            [$user['id']]
                        );

                        // Generate a secure token
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                        // Store token
                        db_insert('password_resets', [
                            'user_id' => $user['id'],
                            'token' => $token,
                            'expires_at' => $expires,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);

                        // Build reset link
                        $base_domain = getenv('BASE_DOMAIN') ?: $_SERVER['HTTP_HOST'];
                        $tenant_subdomain = $tenant['subdomain'];
                        
                        if ($tenant_subdomain && $base_domain !== 'localhost') {
                            $resetLink = "https://{$tenant_subdomain}.{$base_domain}/auth/reset_password.php?token={$token}";
                        } else {
                            $resetLink = base_url("auth/reset_password.php?token={$token}&tenant={$tenant_subdomain}");
                        }

                        // Send email
                        $email_from = getenv('EMAIL_FROM') ?: 'noreply@jakababa.com';
                        $company_name = $tenant['name'];
                        
                        $subject = "Password Reset Request - {$company_name}";
                        $message = "Hello {$user['name']},\n\n";
                        $message .= "We received a request to reset your password for your account at {$company_name}.\n\n";
                        $message .= "Click the link below to reset your password (valid for 1 hour):\n";
                        $message .= $resetLink . "\n\n";
                        $message .= "If you did not request this, please ignore this email.\n\n";
                        $message .= "Thank you,\n{$company_name} Team";

                        $headers = "From: {$email_from}\r\n";
                        $headers .= "Reply-To: support@{$tenant_subdomain}.{$base_domain}\r\n";
                        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                        $mailSent = mail($user['email'], $subject, $message, $headers);

                        if ($mailSent) {
                            $success = 'A password reset link has been sent to your email address.';
                            
                            if (function_exists('log_activity')) {
                                log_activity($user['id'], 'password.reset_requested', [
                                    'user_id' => $user['id'],
                                    'email' => $user['email'],
                                    'tenant_id' => $tenant['id']
                                ], $tenant['id'], get_current_tenant_id());
                            }
                        } else {
                            error_log("Failed to send reset email to {$user['email']}");
                            $error = 'Failed to send email. Please try again later.';
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
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
    <title>JAKPOS | Forgot Password</title>
    
    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        . {
            animation: fadeIn 0.3s ease-out;
        }
        
        . {
            animation: slideUp 0.4s ease-out;
        }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    }
                }
            }
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans" style="font-family: 'Inter', sans-serif;">

    <div class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-sm ">
            
            <!-- Logo -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-primary-500 rounded-xl shadow-md mb-3">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
                <h1 class="text-xl font-semibold text-gray-800">Reset Password</h1>
                <p class="text-xs text-gray-500 mt-1">Enter your details to reset password</p>
            </div>
            
            <!-- Tenant Info -->
            <?php if ($detected_tenant_code): ?>
                <div class="mb-4 p-2.5 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-xs text-amber-700 text-center">
                        <svg class="w-3.5 h-3.5 inline mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Tenant: <strong><?= htmlspecialchars($detected_tenant_code) ?></strong>
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg ">
                    <p class="text-xs text-red-600 text-center"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg ">
                    <p class="text-xs text-green-600 text-center"><?= htmlspecialchars($success) ?></p>
                </div>
                <div class="text-center mt-4">
                    <a href="login.php<?= $detected_tenant_code ? '?tenant=' . urlencode($detected_tenant_code) : '' ?>" 
                       class="text-sm text-primary-500 hover:text-primary-600 font-medium">
                        Back to login →
                    </a>
                </div>
            <?php else: ?>
            
            <!-- Reset Form Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <!-- Tenant Code (if not detected) -->
                    <?php if (!$detected_tenant_code): ?>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Tenant code</label>
                        <input type="text" 
                               name="tenant_code" 
                               value="<?= htmlspecialchars($tenant_code) ?>"
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 transition"
                               placeholder="your-company"
                               required autofocus>
                        <p class="text-xs text-gray-400 mt-1">Your organization's unique identifier</p>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="tenant_code" value="<?= htmlspecialchars($detected_tenant_code) ?>">
                    <?php endif; ?>
                    
                    <!-- Email/Username -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Email or username</label>
                        <input type="text" 
                               name="identifier" 
                               value="<?= htmlspecialchars($identifier) ?>"
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 transition"
                               placeholder="admin@company.com or username"
                               required>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2 rounded-lg text-sm transition transform hover:scale-[1.01] active:scale-[0.99] shadow-sm">
                        Send reset link
                    </button>
                </form>
            </div>
            
            <!-- Back to Login Link -->
            <p class="text-center text-xs text-gray-500 mt-5">
                Remember your password?
                <a href="login.php<?= $detected_tenant_code ? '?tenant=' . urlencode($detected_tenant_code) : '' ?>" 
                   class="text-primary-500 hover:text-primary-600 font-medium">
                    Back to login
                </a>
            </p>
            
            <!-- Footer -->
            <p class="text-center text-[11px] text-gray-400 mt-4">
                © <?php echo date('Y'); ?> JAKPOS. All rights reserved.
            </p>
            
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>