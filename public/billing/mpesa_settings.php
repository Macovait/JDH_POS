<?php
/**
 * M-Pesa Settings - Jakababa POS
 * Pure Tailwind CSS
 */

$page_title = 'M-Pesa Settings';
ob_start();

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

if (!check_permission('settings.manage') && !is_super_admin()) {
    enforce_permission('settings.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security validation failed.';
    } elseif ($_POST['action'] === 'save_mpesa') {
        $settings = [
            'mpesa_enabled' => isset($_POST['mpesa_enabled']) ? '1' : '0',
            'mpesa_consumer_key' => trim($_POST['mpesa_consumer_key'] ?? ''),
            'mpesa_consumer_secret' => trim($_POST['mpesa_consumer_secret'] ?? ''),
            'mpesa_shortcode' => trim($_POST['mpesa_shortcode'] ?? ''),
            'mpesa_passkey' => trim($_POST['mpesa_passkey'] ?? ''),
            'mpesa_callback_url' => trim($_POST['mpesa_callback_url'] ?? ''),
            'mpesa_environment' => $_POST['mpesa_environment'] ?? 'sandbox',
        ];

        $required_fields = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_shortcode', 'mpesa_passkey'];
        $missing = [];
        foreach ($required_fields as $field) {
            if (empty($settings[$field])) {
                $missing[] = str_replace('mpesa_', '', $field);
            }
        }

        if (empty($missing) || $settings['mpesa_enabled'] === '0') {
            try {
                foreach ($settings as $key => $value) {
                    update_setting($key, $value, $tenant_id);
                }
                $success_message = 'M-Pesa settings saved successfully';
            } catch (Exception $e) {
                $error_message = 'Failed to save settings: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Please fill in: ' . implode(', ', $missing);
        }
    }
}

$settings = [];
try {
    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM settings WHERE tenant_id = ?');
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Error fetching settings: " . $e->getMessage());
}

$csrf_token = generate_csrf_token();
$mpesa_enabled = ($settings['mpesa_enabled'] ?? '0') === '1';
$mpesa_env = $settings['mpesa_environment'] ?? 'sandbox';
$callback_base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-mobile-alt text-green-400"></i> M-Pesa Settings
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Configure Safaricom M-Pesa Daraja API integration</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="../dashboard/billing.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Billing
        </a>
    </div>
</div>

<?php if ($success_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i>
    <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $mpesa_enabled ? 'bg-emerald-500/10' : 'bg-slate-500/10'; ?> flex items-center justify-center shrink-0">
            <i class="fas fa-power-off <?php echo $mpesa_enabled ? 'text-emerald-400' : 'text-slate-400'; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $mpesa_enabled ? 'text-emerald-400' : 'text-slate-400'; ?> truncate"><?php echo $mpesa_enabled ? 'Enabled' : 'Disabled'; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Status</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $mpesa_env === 'production' ? 'bg-emerald-500/10' : 'bg-amber-500/10'; ?> flex items-center justify-center shrink-0">
            <i class="fas fa-server <?php echo $mpesa_env === 'production' ? 'text-emerald-400' : 'text-amber-400'; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $mpesa_env === 'production' ? 'text-emerald-400' : 'text-amber-400'; ?> truncate"><?php echo ucfirst($mpesa_env); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Environment</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-building text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400 truncate"><?php echo htmlspecialchars($settings['mpesa_shortcode'] ?? '—'); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Shortcode</div>
        </div>
    </div>
</div>

<form method="POST" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="save_mpesa">

    <!-- Enable toggle + API credentials -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-cog text-amber-400"></i> API Configuration
            </h3>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="mpesa_enabled" value="1" <?php echo $mpesa_enabled ? 'checked' : ''; ?> class="sr-only peer">
                <div class="w-9 h-5 bg-slate-700 peer-focus:ring-1 peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                <span class="ml-2 text-xs text-slate-400">M-Pesa</span>
            </label>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Environment</label>
                    <select name="mpesa_environment" class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="sandbox" <?php echo $mpesa_env === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                        <option value="production" <?php echo $mpesa_env === 'production' ? 'selected' : ''; ?>>Production (Live)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Business Shortcode</label>
                    <input type="text" name="mpesa_shortcode" value="<?php echo htmlspecialchars($settings['mpesa_shortcode'] ?? ''); ?>"
                           class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                           placeholder="e.g., 123456">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Consumer Key</label>
                    <input type="text" name="mpesa_consumer_key" value="<?php echo htmlspecialchars($settings['mpesa_consumer_key'] ?? ''); ?>"
                           class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                           placeholder="Your M-Pesa API Consumer Key">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Consumer Secret</label>
                    <input type="password" name="mpesa_consumer_secret" value="<?php echo htmlspecialchars($settings['mpesa_consumer_secret'] ?? ''); ?>"
                           class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                           placeholder="Your M-Pesa API Consumer Secret">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Passkey</label>
                    <input type="text" name="mpesa_passkey" value="<?php echo htmlspecialchars($settings['mpesa_passkey'] ?? ''); ?>"
                           class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                           placeholder="Your M-Pesa API Passkey">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5 font-medium">Callback URL</label>
                    <input type="text" name="mpesa_callback_url" value="<?php echo htmlspecialchars($settings['mpesa_callback_url'] ?? ''); ?>"
                           class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                           placeholder="https://yourdomain.com/billing/mpesa_callback.php">
                    <p class="text-xs text-slate-500 mt-1">Set this in Safaricom Developer Portal</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Setup instructions -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-info-circle text-amber-400"></i> Setup Instructions
            </h3>
        </div>
        <div class="p-4">
            <ol class="list-decimal list-inside text-slate-400 space-y-2 text-sm">
                <li>Go to <a href="https://developer.safaricom.co.ke" target="_blank" class="text-amber-400 hover:text-amber-300 transition-colors">Safaricom Developer Portal</a></li>
                <li>Create an app or use existing one to get Consumer Key &amp; Secret</li>
                <li>Get your Business Shortcode from M-Pesa dashboard</li>
                <li>Generate a Passkey from the developer portal</li>
                <li>Set the Callback URL to: <code class="bg-slate-900 px-2 py-0.5 rounded text-amber-400 text-xs"><?php echo htmlspecialchars($callback_base); ?>/JDH_POS/public/billing/mpesa_callback.php</code></li>
                <li>Save all credentials above and enable M-Pesa</li>
            </ol>
        </div>
    </div>

    <!-- Submit -->
    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors">
        <i class="fas fa-save text-xs"></i> Save M-Pesa Settings
    </button>
</form>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>