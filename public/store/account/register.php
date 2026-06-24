<?php
/**
 * Customer Registration  Jakababa Smart Storefront
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

$errors = [];
$data = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['full_name'] = trim($_POST['full_name'] ?? '');
    $data['email'] = trim($_POST['email'] ?? '');
    $data['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (strlen($data['full_name']) < 2) $errors[] = 'Full name is required';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $password_confirm) $errors[] = 'Passwords do not match';

    // Check email uniqueness
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM online_customer_accounts WHERE tenant_id = ? AND email = ? AND branch_id = $current_branch_id LIMIT 1");
        $stmt->execute([$tenant_id, $data['email']]);
        if ($stmt->fetch()) $errors[] = 'An account with this email already exists';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO online_customer_accounts (tenant_id, email, phone, password_hash, full_name)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenant_id, $data['email'], $data['phone'], $hash, $data['full_name']]);
        $accountId = (int) $pdo->lastInsertId();

        // Auto-login
        $token = bin2hex(random_bytes(32));
        $expires = time() + 86400 * 30;
        $pdo->prepare("UPDATE online_customer_accounts SET remember_token = ?, last_login_at = NOW() WHERE id = ?")
            ->execute([$token, $accountId]);
        setcookie('shop_customer_token', $token, $expires, '/');
        setcookie('shop_customer_id', (string) $accountId, $expires, '/');
        setcookie('shop_customer_name', $data['full_name'], $expires, '/');

        header("Location: orders.php?tenant=$tenant_id&welcome=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account  <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{brand:{DEFAULT:'<?php echo $primary_color; ?>',dark:'#e07d16'},dark:{900:'#0f0f1a',800:'#1a1a2e'}}}}}</script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center font-sans p-4">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <a href="../index.php?tenant=<?php echo $tenant_id; ?>" class="inline-flex items-center gap-2 text-2xl font-bold text-dark-800">
            <div class="w-10 h-10 bg-brand rounded-lg flex items-center justify-center text-white text-lg">J</div>
            <?php echo htmlspecialchars($store_name); ?>
        </a>
        <p class="text-sm text-gray-500 mt-2">Create your account</p>
    </div>

    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-8">
        <?php if (!empty($errors)): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <?php foreach ($errors as $e) echo '<div class="flex items-center gap-2"><i class="fas fa-exclamation-circle text-xs"></i> ' . htmlspecialchars($e) . '</div>'; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($data['full_name']); ?>" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition" placeholder="John Doe">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($data['email']); ?>" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition" placeholder="you@example.com">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                <div class="relative">
                    <i class="fas fa-phone absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($data['phone']); ?>" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition" placeholder="+254 7XX XXX XXX">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
                    <input type="password" name="password" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition" placeholder="Min 6 characters">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3.5 top-3.5 text-gray-400 text-sm"></i>
                    <input type="password" name="password_confirm" required class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition" placeholder="Re-enter password">
                </div>
            </div>

            <button type="submit" class="w-full bg-brand hover:bg-brand-dark text-white font-semibold py-3 rounded-xl transition shadow-lg shadow-brand/20">
                Create Account
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-500">
            Already have an account?
            <a href="login.php?tenant=<?php echo $tenant_id; ?>" class="text-brand font-semibold hover:underline">Sign in</a>
        </div>
    </div>
</div>

</body>
</html>
