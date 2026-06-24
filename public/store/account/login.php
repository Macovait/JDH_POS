<?php
/**
 * Customer Login  Jakababa Smart Storefront
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/src/paths.php';
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
$current_branch_id = 0;
if (function_exists('get_current_branch_id')) {
    $current_branch_id = get_current_branch_id();
}

$tenant_id = isset($_GET['tenant']) ? (int) $_GET['tenant'] : 0;
if (!$tenant_id) {
    try {
        $row = $pdo->query("SELECT id FROM pos_tenants WHERE status IN ('active','trial') ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $tenant_id = (int) $row['id'];
        } else {
            $row = $pdo->query("SELECT id FROM tenants WHERE active = 1 ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row) $tenant_id = (int) $row['id'];
        }
    } catch (Exception $e) {}
}

// Load settings
$settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $settings[$row['setting_key']] = $row['setting_value'];
$store_name = $settings['site_title'] ?? 'Jakababa';
$primary_color = $settings['primary_color'] ?? '#f68b1e';

// Already logged in?
if (!empty($_COOKIE['shop_customer_token'])) {
    header("Location: orders.php?tenant=$tenant_id");
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email)) $errors[] = 'Email is required';
    if (empty($password)) $errors[] = 'Password is required';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, password_hash, full_name, customer_id FROM online_customer_accounts WHERE tenant_id = ? AND email = ? AND is_active = 1 AND branch_id = $current_branch_id LIMIT 1");
        $stmt->execute([$tenant_id, $email]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account && password_verify($password, $account['password_hash'])) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + 86400 * 30;
            $pdo->prepare("UPDATE online_customer_accounts SET remember_token = ?, last_login_at = NOW() WHERE id = ?")
                ->execute([$token, $account['id']]);
            setcookie('shop_customer_token', $token, $expires, '/');
            setcookie('shop_customer_id', $account['id'], $expires, '/');
            setcookie('shop_customer_name', $account['full_name'] ?? 'Customer', $expires, '/');

            $redirect = $_GET['redirect'] ?? "orders.php?tenant=$tenant_id";
            header("Location: $redirect");
            exit;
        }
        $errors[] = 'Invalid email or password';
    }
}

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['shop_csrf'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login  <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{brand:{DEFAULT:'<?php echo $primary_color; ?>',dark:'#e07d16'},dark:{900:'#0f0f1a',800:'#1a1a2e'}}}}}</script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center font-sans p-4">

<div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
        <a href="../index.php?tenant=<?php echo $tenant_id; ?>" class="inline-flex items-center gap-2 text-2xl font-bold text-dark-800">
            <div class="w-10 h-10 bg-brand rounded-lg flex items-center justify-center text-white text-lg">J</div>
            <?php echo htmlspecialchars($store_name); ?>
        </a>
        <p class="text-sm text-gray-500 mt-2">Sign in to your account</p>
    </div>

    <!-- Login Card -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-8">
        <?php if (!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <?php foreach ($errors as $e) echo '<div class="flex items-center gap-2"><i class="fas fa-exclamation-circle text-xs"></i> ' . htmlspecialchars($e) . '</div>'; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-5">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition" placeholder="you@example.com">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
                    <input type="password" name="password" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition" placeholder="">
                </div>
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand focus:ring-brand">
                    <span>Remember me</span>
                </label>
                <a href="#" class="text-brand font-medium hover:underline">Forgot password?</a>
            </div>

            <button type="submit" class="w-full bg-brand hover:bg-brand-dark text-white font-semibold py-3 rounded-xl transition shadow-lg shadow-brand/20">
                Sign In
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-500">
            Don't have an account?
            <a href="register.php?tenant=<?php echo $tenant_id; ?>" class="text-brand font-semibold hover:underline">Create one</a>
        </div>

        <div class="mt-4 pt-4 border-t text-center">
            <a href="../index.php?tenant=<?php echo $tenant_id; ?>" class="text-sm text-gray-500 hover:text-brand transition">
                <i class="fas fa-arrow-left mr-1"></i> Back to store
            </a>
        </div>
    </div>
</div>

</body>
</html>
