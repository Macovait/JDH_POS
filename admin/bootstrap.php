<?php
declare(strict_types=1);

if (defined('ADMIN_BOOTSTRAP_LOADED')) {
    return;
}
define('ADMIN_BOOTSTRAP_LOADED', true);

require_once __DIR__ . '/../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../src/Security/SecurityBootstrap.php';

// Initialize comprehensive security system for admin panel
SecurityBootstrap::initialize();

// ── DB + table helpers (must be defined before admin_hydrate_session_from_user) ──

if (!function_exists('admin_db')) {
    function admin_db(bool $retry = false): ?PDO
    {
        static $pdo = null;
        static $attempted = false;

        if ($retry) {
            $pdo = null;
            $attempted = false;
            $GLOBALS['admin_db_error'] = null;
        }

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        if ($attempted) {
            return null;
        }

        $attempted = true;

        try {
            $pdo = get_db_connection();
            $GLOBALS['admin_db_error'] = null;
            return $pdo;
        } catch (Throwable $e) {
            $pdo = null;
            $GLOBALS['admin_db_error'] = $e->getMessage();
            error_log('Admin database connection failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('admin_valid_table_name')) {
    function admin_valid_table_name(string $table): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $table);
    }
}

if (!function_exists('admin_table_exists')) {
    function admin_table_exists(string $table): bool
    {
        if (!admin_valid_table_name($table)) {
            return false;
        }
        $pdo = admin_db();
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            error_log("Admin table check failed for {$table}: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('admin_table_columns')) {
    function admin_table_columns(string $table): array
    {
        if (!admin_valid_table_name($table)) {
            return [];
        }
        $pdo = admin_db();
        if (!$pdo) {
            return [];
        }
        try {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            error_log("Admin column lookup failed for {$table}: " . $e->getMessage());
            return [];
        }
    }
}

// ── Define helper function before it's used ──
if (!function_exists('admin_hydrate_session_from_user')) {
    function admin_hydrate_session_from_user(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !empty($_SESSION['admin_id'])) {
            return;
        }

        try {
            $pdo = admin_db();
            if (!$pdo || !admin_table_exists('admins')) {
                return;
            }

            $columns = admin_table_columns('admins');
            if (!in_array('user_id', $columns, true)) {
                return;
            }

            $usernameExpr = in_array('username', $columns, true) ? 'a.username AS username' : "COALESCE(a.email, '') AS username";
            $emailExpr = in_array('email', $columns, true) ? 'a.email AS email' : "'' AS email";
            $nameExpr = in_array('name', $columns, true) ? 'a.name AS name' : "'Admin' AS name";
            $roleExpr = in_array('role', $columns, true) ? 'a.role AS role' : "'owner' AS role";
            $statusWhere = in_array('status', $columns, true) ? "AND a.status = 'active'" : (in_array('active', $columns, true) ? 'AND a.active = 1' : '');

            $stmt = $pdo->prepare(
                "SELECT a.id, {$usernameExpr}, {$emailExpr}, {$nameExpr}, {$roleExpr}
                 FROM admins a
                 WHERE a.user_id = ? {$statusWhere}
                 LIMIT 1"
            );
            $stmt->execute([$userId]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin) {
                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_username'] = $admin['username'] ?? '';
                $_SESSION['admin_email'] = $admin['email'] ?? '';
                $_SESSION['admin_name'] = $admin['name'] ?? 'Admin';
                $_SESSION['admin_role'] = $admin['role'] ?? 'owner';
                $_SESSION['is_super_admin'] = true;
                $_SESSION['admin_login_time'] = time();
            }
        } catch (PDOException $e) {
            error_log('admin_hydrate_session_from_user error: ' . $e->getMessage());
        }
    }
}

// Start an isolated admin session separate from the app session
if (!function_exists('admin_start_session')) {
    function admin_start_session(): void
    {
        static $started = false;
        if ($started) {
            return;
        }
        $started = true;

        // Open the shared app session first to read/hydrate admin data
        start_session_secure();

        // Hydrate admin session from user session if needed
        if (!empty($_SESSION['user_id']) && empty($_SESSION['admin_id']) && function_exists('is_super_admin') && is_super_admin()) {
            admin_hydrate_session_from_user();
        }

        // Keep admin authentication in the established application session.
        // The admin_* keys are namespaced, so they do not collide with user auth.
    }
}

if (!function_exists('admin_db_error')) {
    function admin_db_error(): ?string
    {
        admin_db();
        return $GLOBALS['admin_db_error'] ?? null;
    }
}

admin_start_session();

if (!function_exists('admin_table_has_column')) {
    function admin_table_has_column(string $table, string $column): bool
    {
        return in_array($column, admin_table_columns($table), true);
    }
}

if (!function_exists('admin_required_tables')) {
    function admin_required_tables(): array
    {
        return [
            'admins',
            'pos_tenants',
            'pos_plans',
            'pos_subscriptions',
            'pos_invoices',
        ];
    }
}

if (!function_exists('admin_missing_tables')) {
    function admin_missing_tables(?array $tables = null): array
    {
        $tables = $tables ?: admin_required_tables();
        $missing = [];

        foreach ($tables as $table) {
            if (!admin_table_exists((string) $table)) {
                $missing[] = (string) $table;
            }
        }

        return $missing;
    }
}

if (!function_exists('admin_render_setup_error_page')) {
    function admin_render_setup_error_page(string $title, string $message, array $missingTables = []): void
    {
        http_response_code(503);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= $safeTitle ?> | JDH POS Admin</title>
            <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
        </head>
        <body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6">
            <div class="max-w-2xl w-full rounded-2xl border border-red-500/30 bg-slate-900/90 shadow-2xl p-8">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-red-500/15 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-triangle-exclamation text-red-400 text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-white mb-2"><?= $safeTitle ?></h1>
                        <p class="text-slate-300 leading-relaxed"><?= $safeMessage ?></p>
                        <?php if ($missingTables): ?>
                            <div class="mt-5 rounded-xl bg-slate-950/70 border border-slate-800 p-4">
                                <p class="text-sm font-semibold text-slate-300 mb-3">Missing required tables:</p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($missingTables as $table): ?>
                                        <span class="px-3 py-1 rounded-full bg-red-500/10 text-red-300 border border-red-500/20 text-sm"><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="mt-6 flex flex-col sm:flex-row gap-3">
                            <button onclick="window.location.reload()" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-amber-400 text-slate-950 font-semibold hover:bg-amber-300 transition">
                                <i class="fas fa-rotate-right"></i>
                                Retry
                            </button>
                            <a href="<?= htmlspecialchars(admin_url('login.php'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-white/10 text-white font-semibold hover:bg-white/15 transition">
                                <i class="fas fa-right-to-bracket"></i>
                                Admin Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

if (!function_exists('admin_require_db')) {
    function admin_require_db(?array $tables = null): PDO
    {
        $pdo = admin_db();
        if (!$pdo) {
            admin_render_setup_error_page(
                'Admin database unavailable',
                'The platform admin panel cannot connect to the database. Start MySQL in XAMPP and verify DB_NAME, DB_USER, and DB_PASS in your .env file.'
            );
        }

        $missing = admin_missing_tables($tables);
        if ($missing) {
            admin_render_setup_error_page(
                'Admin setup incomplete',
                'The platform admin database is reachable, but required SaaS/admin tables are missing. Run the admin/SaaS migrations before using the superadmin panel.',
                $missing
            );
        }

        return $pdo;
    }
}

if (!function_exists('admin_is_authenticated')) {
    function admin_is_authenticated(): bool
    {
        admin_start_session();

        if (!empty($_SESSION['admin_id'])) {
            $_SESSION['is_super_admin'] = true;
            return true;
        }

        return false;
    }
}

if (!function_exists('admin_require_super_admin')) {
    function admin_require_super_admin(): void
    {
        if (admin_is_authenticated()) {
            $role = strtolower(trim((string) ($_SESSION['admin_role'] ?? '')));
            // All roles stored by login.php are permitted for the admin panel
            $allowed = ['owner', 'admin', 'superadmin', 'super admin', 'support', 'viewer'];
            if (in_array($role, $allowed, true) || !empty($_SESSION['is_super_admin'])) {
                return;
            }
        }

        header('Location: ' . admin_url('login.php'));
        exit;
    }
}

if (!function_exists('admin_current_name')) {
    function admin_current_name(): string
    {
        return $_SESSION['admin_name']
            ?? $_SESSION['user_name']
            ?? $_SESSION['name']
            ?? ($_SESSION['user']['name'] ?? 'Admin');
    }
}

if (!function_exists('admin_current_role')) {
    function admin_current_role(): string
    {
        return $_SESSION['admin_role']
            ?? ($_SESSION['is_super_admin'] ?? false ? 'Super Admin' : ($_SESSION['role'] ?? 'Admin'));
    }
}

if (!function_exists('admin_logout')) {
    function admin_logout(): void
    {
        // Check app session for user_id before switching to admin session
        start_session_secure();
        $hadUserSession = !empty($_SESSION['user_id']);
        session_write_close();

        // Now switch to admin session and clear it
        admin_start_session();

        foreach ([
            'admin_id',
            'admin_username',
            'admin_name',
            'admin_email',
            'admin_role',
            'admin_login_time',
            'is_super_admin',
            'support_mode',
            'support_access_id',
            'support_company_id',
            'support_company_name',
            'support_company_slug',
            'support_access_type',
            'support_started_at',
            'support_expires_at',
        ] as $key) {
            unset($_SESSION[$key]);
        }

        // Destroy the admin session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 3600,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Also logout the app user if they were logged in
        if ($hadUserSession && function_exists('logout_user')) {
            logout_user();
        }

        header('Location: ' . admin_url('login.php'));
        exit;
    }
}

