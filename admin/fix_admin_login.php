<?php
/**
 * Admin Login Diagnostic & Fix
 * Run this via browser: http://localhost/JDH_POS/admin/fix_admin_login.php
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain');

$pdo = admin_db();
if (!$pdo) {
    echo "ERROR: Cannot connect to database.\n";
    echo "DB Error: " . ($GLOBALS['admin_db_error'] ?? 'unknown') . "\n";
    exit;
}

echo "=== Admin Login Diagnostic ===\n\n";

// 1. Check if admins table exists
$hasAdmins = admin_table_exists('admins');
echo "admins table exists: " . ($hasAdmins ? 'YES' : 'NO') . "\n";

if (!$hasAdmins) {
    echo "\nThe admins table is missing. You need to run the admin schema migration.\n";
    echo "Try running one of these SQL files in phpMyAdmin:\n";
    echo "  - database/migrations/super_admin_security_schema.sql\n";
    echo "  - database/migrations/clean.sql (includes admins table)\n";
    exit;
}

// 2. Show columns
$columns = admin_table_columns('admins');
echo "admins columns: " . implode(', ', $columns) . "\n\n";

// 3. Show existing records
$selectCols = ['id'];
if (in_array('username', $columns, true)) $selectCols[] = 'username';
if (in_array('email', $columns, true)) $selectCols[] = 'email';
if (in_array('name', $columns, true)) $selectCols[] = 'name';
if (in_array('role', $columns, true)) $selectCols[] = 'role';
if (in_array('status', $columns, true)) $selectCols[] = 'status';
if (in_array('active', $columns, true)) $selectCols[] = 'active';

$stmt = $pdo->query('SELECT ' . implode(', ', $selectCols) . ' FROM admins LIMIT 10');
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Existing admin records (" . count($admins) . "):\n";
if (empty($admins)) {
    echo "  (none found)\n";
} else {
    foreach ($admins as $a) {
        $line = [];
        foreach ($a as $k => $v) $line[] = "$k=$v";
        echo '  ' . implode(' | ', $line) . "\n";
    }
}

echo "\n";

// 4. Check for default seed
$hasDefault = false;
if (!empty($admins)) {
    foreach ($admins as $a) {
        if (($a['email'] ?? '') === 'admin@platform.com' || ($a['username'] ?? '') === 'admin@platform.com') {
            $hasDefault = true;
            break;
        }
    }
}

if ($hasDefault) {
    echo "Default admin (admin@platform.com) EXISTS.\n";
    echo "If login still fails, the password hash may be wrong.\n";
    echo "Use the form below to reset the password to 'password'.\n";
} else {
    echo "Default admin (admin@platform.com) is MISSING.\n";
    echo "Use the form below to create it.\n";
}

// 5. Handle reset/create action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = password_hash('password', PASSWORD_BCRYPT);

    if (in_array('username', $columns, true) && in_array('email', $columns, true)) {
        // clean.sql schema (has username + email)
        $role = in_array('role', $columns, true) ? 'super_admin' : 'admin';
        $statusCol = in_array('status', $columns, true) ? 'status' : (in_array('active', $columns, true) ? 'active' : null);

        $cols = ['username', 'email', 'password_hash', 'name'];
        $vals = ['admin@platform.com', 'admin@platform.com', $newPassword, 'Super Admin'];

        if (in_array('role', $columns, true)) {
            $cols[] = 'role';
            $vals[] = $role;
        }
        if ($statusCol) {
            $cols[] = $statusCol;
            $vals[] = 'active';
        }

        $placeholders = array_fill(0, count($vals), '?');

        // Delete existing default first
        $pdo->prepare("DELETE FROM admins WHERE email = ? OR username = ?")->execute(['admin@platform.com', 'admin@platform.com']);
        $stmt = $pdo->prepare("INSERT INTO admins (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")");
        $stmt->execute($vals);

    } elseif (in_array('email', $columns, true)) {
        // super_admin_security_schema.sql schema (email only, no username)
        $cols = ['name', 'email', 'password_hash', 'role', 'status'];
        $vals = ['Super Admin', 'admin@platform.com', $newPassword, 'owner', 'active'];

        $placeholders = array_fill(0, count($vals), '?');

        $pdo->prepare("DELETE FROM admins WHERE email = ?")->execute(['admin@platform.com']);
        $stmt = $pdo->prepare("INSERT INTO admins (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")");
        $stmt->execute($vals);
    } else {
        echo "ERROR: Cannot determine schema to seed.\n";
        exit;
    }

    echo "\nSUCCESS: Default admin created/reset.\n";
    echo "Login with: admin@platform.com / password\n";
    echo "Redirecting to login page in 3 seconds...\n";
    header('Refresh: 3; URL=' . admin_url('login.php'));
    exit;
}
?>

--------------------------------------------------

<form method="POST">
    <button type="submit">
        <?php echo $hasDefault ? 'Reset Default Admin Password' : 'Create Default Admin Account'; ?>
    </button>
</form>

<style>
body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
button { background: #fbbf24; color: #0f172a; border: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 1rem; }
button:hover { background: #f59e0b; }
</style>
