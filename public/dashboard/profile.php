<?php
/**
 * My Profile - Jakababa POS
 * Pure Tailwind CSS Edition
 */

$page_title = 'My Profile';

// Bootstrap paths and core dependencies
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

safe_require('db.php', 'src', true);
$pdo = get_db_connection();

$user_id = (int) (get_current_user_id() ?? ($_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? 0)));
$tenant_id = get_current_tenant_id();
$message = '';
$error = '';
$active_tab = $_GET['tab'] ?? 'profile';

// ── Avatar Upload Directory ──
$upload_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// ── POST Handlers (must run before any output) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_profile') {
            $name    = trim($_POST['name'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $phone   = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (empty($name) || empty($email)) {
                throw new Exception('Name and email are required.');
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                throw new Exception('That email is already in use.');
            }

            $avatar = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $avatar = handleAvatarUpload($user_id, $_FILES['avatar'], $upload_dir);
            }

            if ($avatar) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, avatar = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $address, $avatar, $user_id]);
                $_SESSION['user']['avatar'] = $avatar;
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $address, $user_id]);
            }
            $_SESSION['user']['name'] = $name;
            $_SESSION['name'] = $name;
            $_SESSION['user_name'] = $name;
            $_SESSION['email'] = $email;
            log_activity($user_id, 'profile_update', ['description' => 'Updated profile information', 'ip_address' => $_SERVER['REMOTE_ADDR']], $tenant_id);
            $message = 'Profile updated successfully.';

        } elseif ($_POST['action'] === 'update_avatar') {
            $file = $_FILES['avatar'] ?? null;
            if (!$file || !is_array($file)) {
                throw new Exception('No image was uploaded.');
            }
            $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                $msgs = [
                    UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit (' . ini_get('upload_max_filesize') . ').',
                    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
                    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please retry.',
                    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing. Contact admin.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Check permissions.',
                    UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
                ];
                throw new Exception($msgs[$err] ?? 'Upload failed with code ' . $err);
            }
            if (!is_writable($upload_dir)) {
                throw new Exception('Upload folder is not writable. Check permissions on ' . realpath(dirname($upload_dir)));
            }
            $avatar = handleAvatarUpload($user_id, $file, $upload_dir);
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$avatar, $user_id]);
            $_SESSION['user']['avatar'] = $avatar;
            log_activity($user_id, 'avatar_update', ['description' => 'Updated profile avatar', 'ip_address' => $_SERVER['REMOTE_ADDR']], $tenant_id);
            $message = 'Avatar updated successfully.';

        } elseif ($_POST['action'] === 'remove_avatar') {
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([$user_id]);
            $old = $stmt->fetchColumn();
            if ($old) {
                $old_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($old, '/\\');
                if (file_exists($old_path)) @unlink($old_path);
                $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                unset($_SESSION['user']['avatar']);
                log_activity($user_id, 'avatar_remove', ['description' => 'Removed profile avatar', 'ip_address' => $_SERVER['REMOTE_ADDR']], $tenant_id);
                $message = 'Avatar removed successfully.';
            } else {
                throw new Exception('No avatar to remove.');
            }

        } elseif ($_POST['action'] === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($new) || empty($confirm)) {
                throw new Exception('All password fields are required.');
            }
            if ($new !== $confirm) {
                throw new Exception('New passwords do not match.');
            }
            if (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
                throw new Exception('Password must be at least 8 characters with an uppercase letter and a number.');
            }

            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($current, $row['password_hash'])) {
                throw new Exception('Current password is incorrect.');
            }

            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            log_activity($user_id, 'password_change', ['description' => 'Changed password', 'ip_address' => $_SERVER['REMOTE_ADDR']], $tenant_id);
            $message = 'Password changed successfully.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ── Helper: Avatar Upload ──
function handleAvatarUpload(int $user_id, array $file, string $upload_dir): string {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed, true)) {
        throw new Exception('Only JPG, PNG, GIF and WEBP files are allowed.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new Exception('File size must be less than 2MB.');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file. Check folder permissions.');
    }

    // Delete old avatar
    global $pdo;
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $old = $stmt->fetchColumn();
    if ($old) {
        $old_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($old, '/\\');
        if (file_exists($old_path)) @unlink($old_path);
    }

    return '/uploads/avatars/' . $filename;
}

// ── Fetch User Data ──
$user = [];
$recent_activities = [];
$stats = [];
try {
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $fields = ['u.id', 'u.name', 'u.email', 'u.username', 'u.role_id', 'u.branch_id', 'u.created_at', 'u.updated_at'];
    if (in_array('avatar', $columns)) $fields[] = 'u.avatar';
    if (in_array('phone', $columns))   $fields[] = 'u.phone';
    if (in_array('address', $columns)) $fields[] = 'u.address';
    if (in_array('last_login', $columns)) $fields[] = 'u.last_login';

    $sql = "SELECT " . implode(', ', $fields) . ", r.name AS role_name, b.name AS branch_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN branches b ON u.branch_id = b.id
            WHERE u.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new Exception('User not found');

    // Recent activity
    try {
        $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? AND branch_id = $current_branch_id ORDER BY created_at DESC LIMIT 15");
        $stmt->execute([$user_id]);
        $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $recent_activities = []; }

    // Stats
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $total_sales = (int) $stmt->fetchColumn();
    } catch (PDOException $e) { $total_sales = 0; }
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM sales WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $sales_amount = (float) $stmt->fetchColumn();
    } catch (PDOException $e) { $sales_amount = 0; }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE created_by = ?");
        $stmt->execute([$user_id]);
        $customers_added = (int) $stmt->fetchColumn();
    } catch (PDOException $e) { $customers_added = 0; }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE created_by = ? AND deleted_at IS NULL");
        $stmt->execute([$user_id]);
        $products_added = (int) $stmt->fetchColumn();
    } catch (PDOException $e) { $products_added = 0; }

    $stats = [
        'total_sales'     => $total_sales,
        'sales_amount'    => $sales_amount,
        'customers_added' => $customers_added,
        'products_added'  => $products_added,
    ];
} catch (Exception $e) {
    $error = "Failed to load profile: " . $e->getMessage();
}

// Resolve avatar URL
$avatar_path = $user['avatar'] ?? ($_SESSION['user']['avatar'] ?? '');
$avatar_url  = '';
if ($avatar_path) {
    $full = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($avatar_path, '/\\');
    if (file_exists($full)) {
        $avatar_url = base_url($avatar_path) . '?v=' . filemtime($full);
    }
}

$initials = strtoupper(substr($user['name'] ?? 'U', 0, 1));
$name_parts = explode(' ', $user['name'] ?? '');
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
}

$member_since = !empty($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'N/A';
$last_login   = !empty($user['last_login'])  ? date('M d, Y H:i', strtotime($user['last_login'])) : 'First login';

// ── Capture Output ──
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-user-circle text-amber-400"></i> My Profile
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            Manage your account settings and preferences
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="/JDH_POS/public/pos/pos.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-cash-register text-xs"></i> Open POS
        </a>
    </div>
</div>

<!-- Messages -->
<?php if ($message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-chart-bar text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400 truncate"><?php echo number_format($stats['total_sales'] ?? 0); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total Sales</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-money-bill-wave text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400 truncate">KSh <?php echo number_format($stats['sales_amount'] ?? 0, 0); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Revenue</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-users text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400 truncate"><?php echo number_format($stats['customers_added'] ?? 0); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Customers</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-box text-purple-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-purple-400 truncate"><?php echo number_format($stats['products_added'] ?? 0); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Products</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-slate-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-calendar text-slate-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-slate-400 truncate"><?php echo $member_since; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Member Since</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-slate-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-clock text-slate-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-slate-400 truncate"><?php echo $last_login; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Last Login</div>
        </div>
    </div>
</div>

<!-- Profile Hero Card -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-4 flex flex-col md:flex-row items-center gap-4">
    <!-- Avatar Upload -->
    <form id="avatarForm" method="POST" enctype="multipart/form-data" class="relative flex-shrink-0">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="action" value="update_avatar">
        <div class="relative group cursor-pointer" onclick="document.getElementById('avatarInput').click()">
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-2xl font-bold text-slate-900 overflow-hidden ring-2 ring-amber-500/20 group-hover:ring-amber-500/40 transition-all duration-300">
                <?php if ($avatar_url): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($user['name'] ?? 'Avatar'); ?>" class="w-full h-full object-cover" onerror="this.style.display='none'">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="absolute bottom-0 right-0 w-7 h-7 bg-gradient-to-br from-amber-400 to-amber-600 rounded-full flex items-center justify-center text-slate-900 shadow border-2 border-slate-800 transition-transform duration-300 group-hover:scale-110">
                <i class="fas fa-camera text-[10px]"></i>
            </div>
        </div>
        <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" onchange="handleAvatarSelect(this)">
        <?php if ($avatar_url): ?>
        <div class="text-center mt-2">
            <button type="button" onclick="if(confirm('Remove your profile picture?')){var f=document.createElement('form');f.method='POST';f.action='';var i=document.createElement('input');i.type='hidden';i.name='action';i.value='remove_avatar';f.appendChild(i);document.body.appendChild(f);f.submit();}" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-medium text-rose-300 bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 transition">
                <i class="fas fa-trash-alt text-[10px]"></i>Remove
            </button>
        </div>
        <?php endif; ?>
    </form>

    <!-- User Info -->
    <div class="flex-1 text-center md:text-left">
        <h2 class="text-xl font-bold text-white tracking-tight"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h2>
        <p class="text-amber-400 font-medium text-sm mt-0.5"><?php echo htmlspecialchars($user['role_name'] ?? 'Staff'); ?></p>
        <div class="flex flex-wrap justify-center md:justify-start gap-1.5 mt-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] bg-slate-800/80 border border-slate-700/50 text-slate-300">
                <i class="fas fa-envelope text-amber-400/80 text-[10px]"></i><?php echo htmlspecialchars($user['email'] ?? ''); ?>
            </span>
            <?php if (!empty($user['phone'])): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] bg-slate-800/80 border border-slate-700/50 text-slate-300">
                <i class="fas fa-phone text-amber-400/80 text-[10px]"></i><?php echo htmlspecialchars($user['phone']); ?>
            </span>
            <?php endif; ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] bg-slate-800/80 border border-slate-700/50 text-slate-300">
                <i class="fas fa-store text-amber-400/80 text-[10px]"></i><?php echo htmlspecialchars($user['branch_name'] ?? 'Main Branch'); ?>
            </span>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="flex gap-1 border-b border-slate-700/50 mb-4 overflow-x-auto">
    <?php
    $tabs = [
        'profile'  => ['user', 'Profile'],
        'security' => ['lock', 'Security'],
        'activity' => ['clock', 'Activity'],
    ];
    foreach ($tabs as $tab => $info):
        $isActive = $active_tab === $tab;
    ?>
    <a href="?tab=<?php echo $tab; ?>" class="tab-btn px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-all duration-200 flex items-center gap-2 <?php echo $isActive ? 'text-amber-400 border-b-2 border-amber-400' : 'text-slate-400 hover:text-slate-200'; ?>">
        <i class="fas fa-<?php echo $info[0]; ?> text-xs"></i><?php echo $info[1]; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Tab Content -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-6">

    <!-- Profile Tab -->
    <div id="tab-profile" class="<?php echo $active_tab === 'profile' ? 'block' : 'hidden'; ?>">
        <form method="POST" enctype="multipart/form-data" class="p-4 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="update_profile">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label for="profile_name" class="block text-sm font-semibold text-slate-300 mb-2">
                        <i class="fas fa-user text-amber-400 mr-1.5 text-xs"></i>Full Name
                    </label>
                    <input type="text" id="profile_name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required
                           autocomplete="name" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Your full name">
                </div>
                <div>
                    <label for="profile_email" class="block text-sm font-semibold text-slate-300 mb-2">
                        <i class="fas fa-envelope text-amber-400 mr-1.5 text-xs"></i>Email Address
                    </label>
                    <input type="email" id="profile_email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required
                           autocomplete="email" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="you@company.com">
                </div>
                <div>
                    <label for="profile_phone" class="block text-sm font-semibold text-slate-300 mb-2">
                        <i class="fas fa-phone text-amber-400 mr-1.5 text-xs"></i>Phone Number
                    </label>
                    <input type="tel" id="profile_phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                           autocomplete="tel" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="+254 XXX XXXXXX">
                </div>
                <div>
                    <label for="profile_address" class="block text-sm font-semibold text-slate-300 mb-2">
                        <i class="fas fa-map-pin text-amber-400 mr-1.5 text-xs"></i>Address
                    </label>
                    <input type="text" id="profile_address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
                           autocomplete="street-address" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" placeholder="Your address">
                </div>
                <div>
                    <div class="block text-sm font-semibold text-slate-300 mb-2">Role</div>
                    <div class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 opacity-60 flex items-center gap-2 cursor-not-allowed">
                        <i class="fas fa-briefcase text-amber-400/60 text-xs"></i>
                        <span><?php echo htmlspecialchars($user['role_name'] ?? 'Staff'); ?></span>
                    </div>
                </div>
                <div>
                    <div class="block text-sm font-semibold text-slate-300 mb-2">Branch</div>
                    <div class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 opacity-60 flex items-center gap-2 cursor-not-allowed">
                        <i class="fas fa-store text-amber-400/60 text-xs"></i>
                        <span><?php echo htmlspecialchars($user['branch_name'] ?? 'Main Branch'); ?></span>
                    </div>
                </div>
                <div>
                    <div class="block text-sm font-semibold text-slate-300 mb-2">Member Since</div>
                    <div class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 opacity-60 flex items-center gap-2 cursor-not-allowed">
                        <i class="fas fa-calendar text-amber-400/60 text-xs"></i>
                        <span><?php echo $member_since; ?></span>
                    </div>
                </div>
                <div>
                    <div class="block text-sm font-semibold text-slate-300 mb-2">Last Login</div>
                    <div class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 opacity-60 flex items-center gap-2 cursor-not-allowed">
                        <i class="fas fa-clock text-amber-400/60 text-xs"></i>
                        <span><?php echo $last_login; ?></span>
                    </div>
                </div>
            </div>
            <div class="flex justify-end pt-4 border-t border-slate-700/30">
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-check"></i>Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Security Tab -->
    <div id="tab-security" class="<?php echo $active_tab === 'security' ? 'block' : 'hidden'; ?>">
        <div class="p-4 max-w-2xl mx-auto">
            <h3 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-shield-alt text-amber-400 text-sm"></i>Change Password
            </h3>
            <form method="POST" class="space-y-4" onsubmit="return validatePasswordForm()">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label for="current_password" class="block text-sm font-semibold text-slate-300 mb-2">Current Password</label>
                    <div class="relative">
                        <input type="password" name="current_password" id="current_password" required
                               autocomplete="current-password" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 pr-10" placeholder="Enter current password">
                        <button type="button" onclick="togglePwd('current_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-amber-400 transition">
                            <i class="fas fa-eye" id="eye-current_password"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-semibold text-slate-300 mb-2">New Password</label>
                    <div class="relative">
                        <input type="password" name="new_password" id="new_password" required
                               autocomplete="new-password" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 pr-10" placeholder="Min 8 chars, uppercase + number"
                               oninput="checkStrength()">
                        <button type="button" onclick="togglePwd('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-amber-400 transition">
                            <i class="fas fa-eye" id="eye-new_password"></i>
                        </button>
                    </div>
                    <!-- Strength Meter -->
                    <div class="mt-3">
                        <div class="flex gap-1 h-1.5 mb-2">
                            <div id="strength-bar-1" class="flex-1 rounded-full bg-slate-700 transition-colors duration-300"></div>
                            <div id="strength-bar-2" class="flex-1 rounded-full bg-slate-700 transition-colors duration-300"></div>
                            <div id="strength-bar-3" class="flex-1 rounded-full bg-slate-700 transition-colors duration-300"></div>
                            <div id="strength-bar-4" class="flex-1 rounded-full bg-slate-700 transition-colors duration-300"></div>
                        </div>
                        <div class="flex justify-between text-xs">
                            <span id="strength-label" class="text-slate-500">Enter a password</span>
                            <div class="flex gap-3 text-slate-500">
                                <span id="req-len"><i class="fas fa-circle text-[8px] mr-1"></i>8+ chars</span>
                                <span id="req-upper"><i class="fas fa-circle text-[8px] mr-1"></i>A-Z</span>
                                <span id="req-num"><i class="fas fa-circle text-[8px] mr-1"></i>0-9</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-semibold text-slate-300 mb-2">Confirm New Password</label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirm_password" required
                               autocomplete="new-password" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 pr-10" placeholder="Repeat new password"
                               oninput="checkMatch()">
                        <button type="button" onclick="togglePwd('confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-amber-400 transition">
                            <i class="fas fa-eye" id="eye-confirm_password"></i>
                        </button>
                    </div>
                    <p id="match-msg" class="text-xs mt-1.5 h-4"></p>
                </div>

                <div class="pt-4 border-t border-slate-700/30">
                    <button type="submit" id="pwd-submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors w-full md:w-auto" disabled>
                        <i class="fas fa-lock"></i>Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Tab -->
    <div id="tab-activity" class="<?php echo $active_tab === 'activity' ? 'block' : 'hidden'; ?>">
        <div class="p-4">
            <h3 class="text-base font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-history text-amber-400 text-sm"></i>Recent Activity
            </h3>

            <?php if (!empty($recent_activities)): ?>
            <div class="space-y-2 max-h-[420px] overflow-y-auto pr-1">
                <?php foreach ($recent_activities as $act):
                    $icon_map = [
                        'login'           => 'sign-in-alt',
                        'logout'          => 'sign-out-alt',
                        'profile_update'  => 'user-edit',
                        'avatar_update'   => 'camera',
                        'password_change' => 'lock',
                        'sale_create'     => 'cash-register',
                        'sale_return'     => 'undo',
                    ];
                    $icon = $icon_map[$act['action']] ?? 'file-alt';
                    $date = date('M d, Y', strtotime($act['created_at']));
                    $time = date('H:i', strtotime($act['created_at']));
                ?>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-slate-800/40 border border-slate-700/30 hover:border-amber-500/20 hover:bg-slate-800/60 transition-all duration-200">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-<?php echo $icon; ?> text-amber-400 text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-200 font-medium"><?php echo htmlspecialchars($act['description'] ?? ucfirst(str_replace('_', ' ', $act['action']))); ?></p>
                        <div class="flex items-center gap-3 mt-1 text-xs text-slate-500">
                            <span><i class="far fa-clock mr-1"></i><?php echo $time; ?></span>
                            <span><i class="far fa-calendar mr-1"></i><?php echo $date; ?></span>
                            <?php if (!empty($act['ip_address'])): ?>
                            <span><i class="fas fa-globe mr-1"></i><?php echo htmlspecialchars($act['ip_address']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <div class="w-12 h-12 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-history text-xl text-slate-600"></i>
                </div>
                <p class="text-slate-400 text-sm">No activity recorded yet</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Danger Zone -->
<div class="bg-slate-800/40 border border-rose-500/20 rounded-xl p-4 mt-4">
    <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center">
            <i class="fas fa-exclamation-triangle text-rose-400 text-xs"></i>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-rose-300">Danger Zone</h3>
            <p class="text-[11px] text-slate-500">Irreversible actions. Proceed with caution.</p>
        </div>
    </div>
    <div class="flex flex-col sm:flex-row gap-2">
        <button onclick="confirmAction('deactivate', 'Are you sure you want to deactivate your account?')" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-500/30 text-rose-300 bg-transparent hover:bg-rose-500/10 hover:border-rose-500/50 transition-colors text-xs font-medium">
            <i class="fas fa-pause text-[10px]"></i>Deactivate Account
        </button>
        <button onclick="confirmAction('delete', 'WARNING: This will permanently delete your account. Are you sure?')" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-500/30 text-rose-300 bg-transparent hover:bg-rose-500/10 hover:border-rose-500/50 transition-colors text-xs font-medium">
            <i class="fas fa-trash text-[10px]"></i>Delete Account
        </button>
    </div>
</div>

<script>
// ── Page Toast (client-side) ──
function showPageToast(msg, type) {
    const colors = {
        error:   'bg-rose-500/10 border-rose-500/30 text-rose-100',
        success: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-100',
        warning: 'bg-amber-500/10 border-amber-500/30 text-amber-100',
        info:    'bg-blue-500/10 border-blue-500/30 text-blue-100',
    };
    const icons = {
        error:   'fa-exclamation-circle text-rose-400',
        success: 'fa-check-circle text-emerald-400',
        warning: 'fa-exclamation-triangle text-amber-400',
        info:    'fa-info-circle text-blue-400',
    };
    const el = document.createElement('div');
    el.className = 'fixed top-5 right-5 z-[9999] max-w-sm rounded-xl p-4 shadow-2xl  flex items-start gap-3 animate-slide-in ' + (colors[type] || colors.info);
    el.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + ' mt-0.5"></i><div class="flex-1 text-sm">' + msg + '</div><button onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100 transition"><i class="fas fa-times"></i></button>';
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(20px)'; el.style.transition = 'all 0.4s ease'; setTimeout(() => el.remove(), 400); }, 5000);
}

// ── Avatar Upload ──
function handleAvatarSelect(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
        showPageToast('File size must be less than 2MB', 'error');
        input.value = '';
        return;
    }
    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowed.includes(file.type)) {
        showPageToast('Only JPG, PNG, GIF and WEBP files are allowed', 'error');
        input.value = '';
        return;
    }
    if (confirm('Upload this image as your avatar?')) {
        document.getElementById('avatarForm').submit();
    } else {
        input.value = '';
    }
}

// ── Password Visibility ──
function togglePwd(id) {
    const input = document.getElementById(id);
    const icon  = document.getElementById('eye-' + id);
    if (!input || !icon) return;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// ── Password Strength ──
function checkStrength() {
    const pwd = document.getElementById('new_password').value;
    const bars = [
        document.getElementById('strength-bar-1'),
        document.getElementById('strength-bar-2'),
        document.getElementById('strength-bar-3'),
        document.getElementById('strength-bar-4'),
    ];
    const label = document.getElementById('strength-label');
    const reqLen  = document.getElementById('req-len');
    const reqUp   = document.getElementById('req-upper');
    const reqNum  = document.getElementById('req-num');

    let score = 0;
    if (pwd.length >= 8) score++;
    if (pwd.length >= 12) score++;
    if (/[A-Z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;

    const colors = ['bg-slate-700', 'bg-rose-500', 'bg-amber-500', 'bg-emerald-500', 'bg-emerald-400'];
    const texts  = ['Enter a password', 'Weak', 'Fair', 'Good', 'Strong'];
    const level  = pwd.length === 0 ? 0 : Math.min(4, Math.floor(score / 1.2) + 1);

    bars.forEach((bar, i) => {
        bar.className = 'flex-1 rounded-full transition-colors duration-300 ' + (i < level ? colors[level] : 'bg-slate-700');
    });
    label.textContent = texts[level];
    label.className = level === 0 ? 'text-slate-500' : (level <= 2 ? 'text-rose-400' : 'text-emerald-400');

    // Requirement indicators
    function setReq(el, ok) {
        if (!el) return;
        const icon = el.querySelector('i');
        if (icon) icon.className = ok ? 'fas fa-check-circle text-emerald-400 text-[10px] mr-1' : 'fas fa-circle text-slate-600 text-[8px] mr-1';
        el.className = ok ? 'text-emerald-400 transition-colors' : 'text-slate-500 transition-colors';
    }
    setReq(reqLen, pwd.length >= 8);
    setReq(reqUp, /[A-Z]/.test(pwd));
    setReq(reqNum, /[0-9]/.test(pwd));

    checkMatch();
}

function checkMatch() {
    const pwd = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const msg = document.getElementById('match-msg');
    const btn = document.getElementById('pwd-submit');

    if (!confirm.length) {
        msg.textContent = '';
        btn.disabled = true;
        return;
    }

    if (pwd === confirm) {
        msg.innerHTML = '<span class="text-emerald-400"><i class="fas fa-check mr-1"></i>Passwords match</span>';
    } else {
        msg.innerHTML = '<span class="text-rose-400"><i class="fas fa-times mr-1"></i>Passwords do not match</span>';
    }

    const valid = pwd.length >= 8 && /[A-Z]/.test(pwd) && /[0-9]/.test(pwd) && pwd === confirm;
    btn.disabled = !valid;
    btn.classList.toggle('opacity-50', !valid);
    btn.classList.toggle('cursor-not-allowed', !valid);
}

function validatePasswordForm() {
    const pwd = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    if (pwd !== confirm) {
        alert('Passwords do not match');
        return false;
    }
    return true;
}

// ── Danger Zone ──
function confirmAction(type, msg) {
    if (!confirm(msg)) return;
    if (type === 'delete') {
        const confirmText = prompt('Type "DELETE" to permanently delete your account:');
        if (confirmText !== 'DELETE') return;
    }
    window.location.href = type + '_account.php';
}

</script>


<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
