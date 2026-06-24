<?php
declare(strict_types=1);

/**
 * Reset Password Page (SaaS Version) - Clean Centered
 * 
 * Allows a user to set a new password using a valid reset token.
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

$token = $_GET['token'] ?? '';
$error = null;
$success = null;
$tenant_subdomain = null;

if (empty($token)) {
    $error = 'No reset token provided.';
} else {
    try {
        // Validate token
        $reset = db_fetch_one("
            SELECT pr.user_id, u.email, u.name, u.tenant_id, 
                   t.subdomain, t.name as tenant_name
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            JOIN tenants t ON u.tenant_id = t.id
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ", [$token]);

        if (!$reset) {
            $error = 'Invalid or expired reset token.';
        } else {
            $user_id = $reset['user_id'];
            $tenant_id = $reset['tenant_id'];
            $tenant_subdomain = $reset['subdomain'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Verify CSRF token
                if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                    $error = 'Invalid security token. Please refresh the page.';
                    $csrf_token = generate_csrf_token();
                } else {
                    $password = $_POST['password'] ?? '';
                    $password_confirm = $_POST['password_confirm'] ?? '';

                    // Password validation
                    if (strlen($password) < 8) {
                        $error = 'Password must be at least 8 characters.';
                    } elseif (!preg_match('/[A-Z]/', $password)) {
                        $error = 'Password must contain at least one uppercase letter.';
                    } elseif (!preg_match('/[a-z]/', $password)) {
                        $error = 'Password must contain at least one lowercase letter.';
                    } elseif (!preg_match('/[0-9]/', $password)) {
                        $error = 'Password must contain at least one number.';
                    } elseif ($password !== $password_confirm) {
                        $error = 'Passwords do not match.';
                    } else {
                        // Update password
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        db_query("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", [$hash, $user_id]);
                        
                        // Delete used token
                        db_query("DELETE FROM password_resets WHERE token = ?", [$token]);
                        
                        // Clear remember tokens
                        db_query("DELETE FROM user_tokens WHERE user_id = ?", [$user_id]);

                        // Log activity
                        if (function_exists('log_activity')) {
                            log_activity($user_id, 'password.reset', [
                                'user_id' => $user_id,
                                'email' => $reset['email'],
                                'tenant_id' => $tenant_id
                            ], $tenant_id, get_current_tenant_id());
                        }

                        $success = 'Your password has been reset successfully. You can now log in.';
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JAKPOS | Reset Password</title>
    
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
        
        .password-requirement.valid {
            color: #10b981;
        }
        
        .password-requirement.invalid {
            color: #9ca3af;
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h1 class="text-xl font-semibold text-gray-800">Set New Password</h1>
                <p class="text-xs text-gray-500 mt-1">Create a strong password for your account</p>
            </div>
            
            <!-- Tenant Info -->
            <?php if ($tenant_subdomain): ?>
                <div class="mb-4 p-2.5 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-xs text-amber-700 text-center">
                        <svg class="w-3.5 h-3.5 inline mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Resetting password for: <strong><?= htmlspecialchars($tenant_subdomain) ?></strong>
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
                    <a href="login.php<?= $tenant_subdomain ? '?tenant=' . urlencode($tenant_subdomain) : '' ?>" 
                       class="text-sm text-primary-500 hover:text-primary-600 font-medium">
                        Go to login →
                    </a>
                </div>
            <?php elseif (!$error || ($error && strpos($error, 'token') === false)): ?>
            
            <!-- Reset Form Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <!-- New Password -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">New password</label>
                        <div class="relative">
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 transition pr-8"
                                   placeholder="••••••••"
                                   required autofocus>
                            <button type="button" 
                                    onclick="togglePassword('password')"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Confirm password</label>
                        <div class="relative">
                            <input type="password" 
                                   name="password_confirm" 
                                   id="password_confirm"
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 transition pr-8"
                                   placeholder="••••••••"
                                   required>
                            <button type="button" 
                                    onclick="togglePassword('password_confirm')"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Password Requirements -->
                    <div class="text-xs space-y-1">
                        <p class="text-gray-500">Password must contain:</p>
                        <ul class="space-y-0.5">
                            <li id="req-length" class="password-requirement invalid flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>At least 8 characters</span>
                            </li>
                            <li id="req-upper" class="password-requirement invalid flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>One uppercase letter</span>
                            </li>
                            <li id="req-lower" class="password-requirement invalid flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>One lowercase letter</span>
                            </li>
                            <li id="req-number" class="password-requirement invalid flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>One number</span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-primary-500 hover:bg-primary-600 text-white font-semibold py-2 rounded-lg text-sm transition transform hover:scale-[1.01] active:scale-[0.99] shadow-sm mt-2">
                        Reset password
                    </button>
                </form>
            </div>
            
            <!-- Back to Login Link -->
            <p class="text-center text-xs text-gray-500 mt-5">
                <a href="login.php<?= $tenant_subdomain ? '?tenant=' . urlencode($tenant_subdomain) : '' ?>" 
                   class="text-primary-500 hover:text-primary-600 font-medium">
                    ← Back to login
                </a>
            </p>
            
            <!-- Footer -->
            <p class="text-center text-[11px] text-gray-400 mt-4">
                © <?php echo date('Y'); ?> JAKPOS. All rights reserved.
            </p>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
        }
        
        // Real-time password validation
        const passwordInput = document.getElementById('password');
        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        
        function validatePassword(password) {
            let valid = true;
            
            // Length check
            if (password.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.classList.remove('invalid');
            } else {
                reqLength.classList.add('invalid');
                reqLength.classList.remove('valid');
                valid = false;
            }
            
            // Uppercase
            if (/[A-Z]/.test(password)) {
                reqUpper.classList.add('valid');
                reqUpper.classList.remove('invalid');
            } else {
                reqUpper.classList.add('invalid');
                reqUpper.classList.remove('valid');
                valid = false;
            }
            
            // Lowercase
            if (/[a-z]/.test(password)) {
                reqLower.classList.add('valid');
                reqLower.classList.remove('invalid');
            } else {
                reqLower.classList.add('invalid');
                reqLower.classList.remove('valid');
                valid = false;
            }
            
            // Numbers
            if (/[0-9]/.test(password)) {
                reqNumber.classList.add('valid');
                reqNumber.classList.remove('invalid');
            } else {
                reqNumber.classList.add('invalid');
                reqNumber.classList.remove('valid');
                valid = false;
            }
            
            return valid;
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                validatePassword(this.value);
            });
        }
        
        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password')?.value || '';
                const confirm = document.getElementById('password_confirm')?.value || '';
                
                if (!validatePassword(password)) {
                    e.preventDefault();
                    alert('Please meet all password requirements.');
                    return false;
                }
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }
            });
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>