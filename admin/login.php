<?php
/**
 * JDH POS Admin - Super Admin Login
 * System management authentication
 */

require_once __DIR__ . '/bootstrap.php';

if (isset($_GET['logout'])) {
    admin_logout();
}

if (admin_is_authenticated()) {
    header('Location: ' . admin_url('dashboard.php'));
    exit;
}

$error = '';
$success = '';
$username = $_POST['username'] ?? '';

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enforce rate limiting on admin login (per IP)
    $rateLimitMiddleware = __DIR__ . '/../src/Middleware/ApiRateLimitMiddleware.php';
    if (file_exists($rateLimitMiddleware)) {
        require_once $rateLimitMiddleware;
        \Jakababa\Middleware\enforce_api_rate_limit('login_attempts');
    }

    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf, 'admin_login')) {
        $error = 'Invalid or expired security token. Please refresh the page and try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $pdo = admin_db();
            if (!$pdo) {
                throw new RuntimeException('Database connection failed. Start MySQL and try again.');
            }

            if (!admin_table_exists('admins')) {
                throw new RuntimeException('Admin setup incomplete. The admins table is missing.');
            }

            $columns = admin_table_columns('admins');
            if (!in_array('password_hash', $columns, true)) {
                throw new RuntimeException('Admin setup incomplete. The admins table is missing password_hash.');
            }

            $identifierParts = [];
            $params = [];

            if (in_array('username', $columns, true)) {
                $identifierParts[] = 'username = ?';
                $params[] = $username;
            }

            if (in_array('email', $columns, true)) {
                $identifierParts[] = 'email = ?';
                $params[] = $username;
            }

            if (!$identifierParts) {
                throw new RuntimeException('Admin setup incomplete. The admins table needs username or email.');
            }

            $select = ['id'];
            $select[] = in_array('username', $columns, true) ? 'username' : "COALESCE(email, '') AS username";
            $select[] = in_array('email', $columns, true) ? 'email' : "'' AS email";
            $select[] = 'password_hash';
            $select[] = in_array('name', $columns, true) ? 'name' : "'Admin' AS name";
            $select[] = in_array('role', $columns, true) ? 'role' : "'owner' AS role";
            $select[] = in_array('status', $columns, true) ? 'status' : "'active' AS status";

            $statusWhere = in_array('status', $columns, true) ? "AND status = 'active'" : (in_array('active', $columns, true) ? 'AND active = 1' : '');
            $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM admins WHERE (' . implode(' OR ', $identifierParts) . ") {$statusWhere} LIMIT 1");
            $stmt->execute($params);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password_hash'])) {
                $role = strtolower((string) ($admin['role'] ?? ''));
                if (!in_array($role, ['owner', 'admin', 'superadmin', 'super admin', 'super_admin'], true)) {
                    throw new RuntimeException('This admin account does not have platform owner access.');
                }

                if (function_exists('\Jakababa\Middleware\reset_rate_limit')) {
                    \Jakababa\Middleware\reset_rate_limit('login_attempts');
                }

                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['admin_login_time'] = time();
                $_SESSION['is_super_admin'] = true;
                
                $success = 'Login successful! Redirecting...';
                
                // Redirect to admin dashboard
                header('Refresh: 1; URL=' . admin_url('dashboard.php'));
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Throwable $e) {
            error_log('Admin login failed: ' . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JDH POS - Super Admin Login</title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        slate: {
                            850: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-out',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: radial-gradient(ellipse at top, rgba(251, 191, 36, 0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at bottom right, rgba(59, 130, 246, 0.08) 0%, transparent 40%),
                        #020617;
            min-height: 100vh;
        }
        .bg-slate-800/40 border border-slate-700/60 rounded-xl {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .input-group:focus-within i {
            color: #fbbf24;
        }
        .input-group:focus-within input {
            border-color: #fbbf24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
        }
        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(251, 191, 36, 0.1), transparent);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md animate-slide-up">
        <!-- Logo Section -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 mb-4">
                <i class="fas fa-shield-halved text-2xl text-amber-400"></i>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight mb-1">JDH POS</h1>
            <p class="text-slate-400 text-sm">Super Admin Portal</p>
            <div class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-500/10 border border-red-500/20">
                <i class="fas fa-lock text-[10px] text-red-400"></i>
                <span class="text-[10px] font-semibold text-red-400 uppercase tracking-wider">Restricted Access</span>
            </div>
        </div>

        <!-- Login Card -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 shadow-2xl shadow-black/50">
            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="mb-4 flex items-start gap-3 p-3 rounded-xl bg-red-500/10 border border-red-500/20 animate-fade-in">
                    <i class="fas fa-circle-exclamation text-red-400 mt-0.5"></i>
                    <p class="text-sm text-red-300 leading-relaxed"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="mb-4 flex items-start gap-3 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 animate-fade-in">
                    <i class="fas fa-circle-check text-emerald-400 mt-0.5"></i>
                    <p class="text-sm text-emerald-300 leading-relaxed"><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="on" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token('admin_login')); ?>">
                
                <!-- Username Field -->
                <div class="input-group">
                    <label for="username" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Username or Email
                    </label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm transition-colors"></i>
                        <input type="text" id="username" name="username"
                            value="<?php echo htmlspecialchars($username); ?>"
                            placeholder="admin@platform.com"
                            autocomplete="username"
                            required
                            class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 transition-all outline-none">
                    </div>
                </div>

                <!-- Password Field -->
                <div class="input-group">
                    <label for="password" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
                        Password
                    </label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-sm transition-colors"></i>
                        <input type="password" id="password" name="password"
                            placeholder=""
                            autocomplete="current-password"
                            required
                            class="w-full pl-10 pr-10 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 transition-all outline-none">
                        <button type="button" id="togglePassword" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors">
                            <i class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20">
                        <span class="text-xs text-slate-400 group-hover:text-slate-300 transition-colors">Remember this device</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                    class="w-full py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold text-sm rounded-xl transition-all duration-200 flex items-center justify-center gap-2 group">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right text-xs group-hover:translate-x-0.5 transition-transform"></i>
                </button>
            </form>

            <!-- Security Note -->
            <div class="mt-5 pt-4 border-t border-slate-700/50">
                <div class="flex items-start gap-2">
                    <i class="fas fa-shield text-slate-500 text-xs mt-0.5"></i>
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        This is a restricted area. All login attempts are logged and monitored. 
                        Unauthorized access will be prosecuted.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-slate-600 mt-6">
            &copy; <?php echo date('Y'); ?> JDH POS Platform. All rights reserved.
        </p>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Focus username on load if empty
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.getElementById('username');
            if (!usernameInput.value) {
                usernameInput.focus();
            }
        });
    </script>
</body>
</html>
