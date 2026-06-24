<?php
/**
 * SMS Settings - Jakababa POS
 * Pure Tailwind CSS
 */

$page_title = 'SMS Settings';
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

/**
 * Sync SMS settings to OTP config file
 */
function syncOtpConfig($settings) {
    $configFile = __DIR__ . '/../../src/otp_config.php';
    if (!file_exists($configFile)) return;
    
    $config = require $configFile;
    
    // Update Africa's Talking settings
    if ($settings['sms_provider'] === 'africastalking') {
        $config['africastalking']['username'] = $settings['sms_username'] ?: 'sandbox';
        $config['africastalking']['api_key'] = $settings['sms_api_key'] ?: '';
        $config['africastalking']['sender_id'] = $settings['sms_sender_id'] ?: 'JDH_POS';
    }
    
    // Update Twilio settings
    if ($settings['sms_provider'] === 'twilio') {
        $config['twilio']['account_sid'] = $settings['sms_username'] ?: '';
        $config['twilio']['auth_token'] = $settings['sms_api_key'] ?: '';
        $config['twilio']['phone_number'] = $settings['sms_sender_id'] ?: '';
    }
    
    // Update test mode based on environment
    $config['test_mode'] = ($settings['sms_environment'] === 'sandbox' && empty($settings['sms_api_key']));
    
    // Save config back to file
    $content = "<?php\n/**\n * OTP Configuration\n * Auto-synced from SMS Settings\n */\n\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($configFile, $content, LOCK_EX);
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

$success_message = '';
$error_message = '';
$test_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = 'Security validation failed.';
    } elseif ($_POST['action'] === 'save_sms') {
        $is_active = isset($_POST['sms_enabled']) ? 1 : 0;
        $username = trim($_POST['sms_username'] ?? '');
        $api_key = trim($_POST['sms_api_key'] ?? '');
        $sender_id = trim($_POST['sms_sender_id'] ?? 'JAKABABA');
        $environment = $_POST['sms_environment'] ?? 'sandbox';
        $provider = $_POST['sms_provider'] ?? 'africastalking';

        if ($is_active) {
            $required_fields = [];
            if (empty($username)) $required_fields[] = 'username';
            if (empty($api_key)) $required_fields[] = 'API key';

            if (!empty($required_fields)) {
                $error_message = 'Please fill in: ' . implode(', ', $required_fields);
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO sms_provider_settings (tenant_id, provider, api_key, sender_id, username, environment, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            provider = VALUES(provider),
                            api_key = VALUES(api_key),
                            sender_id = VALUES(sender_id),
                            username = VALUES(username),
                            environment = VALUES(environment),
                            is_active = VALUES(is_active)
                    ");
                    $stmt->execute([$tenant_id, $provider, $api_key, $sender_id, $username, $environment, $is_active]);
                    
                    // Sync to OTP config for login system
                    syncOtpConfig([
                        'sms_provider' => $provider,
                        'sms_username' => $username,
                        'sms_api_key' => $api_key,
                        'sms_sender_id' => $sender_id,
                        'sms_environment' => $environment,
                        'sms_enabled' => $is_active
                    ]);
                    
                    $success_message = 'SMS settings saved successfully and synced with OTP login system';
                } catch (Exception $e) {
                    $error_message = 'Failed to save settings: ' . $e->getMessage();
                }
            }
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sms_provider_settings (tenant_id, provider, api_key, sender_id, username, environment, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        provider = VALUES(provider),
                        api_key = VALUES(api_key),
                        sender_id = VALUES(sender_id),
                        username = VALUES(username),
                        environment = VALUES(environment),
                        is_active = VALUES(is_active)
                ");
                $stmt->execute([$tenant_id, $provider, $api_key, $sender_id, $username, $environment, $is_active]);
                
                // Sync to OTP config even when disabled
                syncOtpConfig([
                    'sms_provider' => $provider,
                    'sms_username' => $username,
                    'sms_api_key' => $api_key,
                    'sms_sender_id' => $sender_id,
                    'sms_environment' => $environment,
                    'sms_enabled' => $is_active
                ]);
                
                $success_message = 'SMS settings saved successfully';
            } catch (Exception $e) {
                $error_message = 'Failed to save settings: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'test_connection') {
        $settings = [
            'sms_username' => trim($_POST['sms_username'] ?? ''),
            'sms_api_key' => trim($_POST['sms_api_key'] ?? ''),
            'sms_environment' => $_POST['sms_environment'] ?? 'sandbox',
        ];

        if (empty($settings['sms_username']) || empty($settings['sms_api_key'])) {
            $test_result = ['success' => false, 'message' => 'Username and API key are required'];
        } else {
            try {
                $url = $settings['sms_environment'] === 'production'
                    ? 'https://api.africastalking.com/version1/user?username=' . urlencode($settings['sms_username'])
                    : 'https://api.sandbox.africastalking.com/version1/user?username=' . urlencode($settings['sms_username']);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['ApiKey: ' . $settings['sms_api_key']]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code === 200) {
                    $data = json_decode($response, true);
                    $balance = $data['UserData']['balance'] ?? 'Unknown';
                    $test_result = ['success' => true, 'message' => 'Connection successful! Balance: ' . $balance];
                } else {
                    $test_result = ['success' => false, 'message' => 'Connection failed. HTTP Code: ' . $http_code];
                }
            } catch (Exception $e) {
                $test_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
        }
    } elseif ($_POST['action'] === 'add_credits') {
        $credits = (int)($_POST['credits'] ?? 0);

        if ($credits < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid credit amount']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO sms_credits (tenant_id, balance, total_purchased)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    balance = balance + VALUES(balance),
                    total_purchased = total_purchased + VALUES(total_purchased)
            ");
            $stmt->execute([$tenant_id, $credits, $credits]);

            $stmt = $pdo->prepare("SELECT balance FROM sms_credits WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $new_balance = $stmt->fetchColumn();

            echo json_encode(['success' => true, 'new_balance' => $new_balance]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Load SMS settings from sms_provider_settings table (compatible with bulk_sms.php)
$settings = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM sms_provider_settings WHERE tenant_id = ? AND provider = ?');
    $stmt->execute([$tenant_id, 'africastalking']);
    $provider_settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($provider_settings) {
        $settings = [
            'sms_enabled' => $provider_settings['is_active'] ? '1' : '0',
            'sms_username' => $provider_settings['username'] ?? '',
            'sms_api_key' => $provider_settings['api_key'] ?? '',
            'sms_sender_id' => $provider_settings['sender_id'] ?? 'JAKABABA',
            'sms_environment' => $provider_settings['environment'] ?? 'sandbox',
            'sms_provider' => $provider_settings['provider'] ?? 'africastalking',
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching SMS settings: " . $e->getMessage());
}

// Get SMS balance
$sms_balance = 0;
try {
    $stmt = $pdo->prepare('SELECT balance FROM sms_credits WHERE tenant_id = ?');
    $stmt->execute([$tenant_id]);
    $sms_balance = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching SMS balance: " . $e->getMessage());
}

$csrf_token = generate_csrf_token();
$sms_enabled = ($settings['sms_enabled'] ?? '0') === '1';
$sms_env = $settings['sms_environment'] ?? 'sandbox';
$sms_provider = $settings['sms_provider'] ?? 'africastalking';
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_url('api/sms_webhook.php');
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-sms text-amber-400"></i> SMS Settings
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Configure SMS provider settings</p>
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

<?php if ($test_result): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg <?php echo $test_result['success'] ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400'; ?> text-sm">
    <i class="fas <?php echo $test_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($test_result['message']); ?>
</div>
<?php endif; ?>

<!-- Summary cards -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $sms_enabled ? 'bg-emerald-500/10' : 'bg-slate-500/10'; ?> flex items-center justify-center shrink-0">
            <i class="fas fa-power-off <?php echo $sms_enabled ? 'text-emerald-400' : 'text-slate-400'; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $sms_enabled ? 'text-emerald-400' : 'text-slate-400'; ?> truncate"><?php echo $sms_enabled ? 'Enabled' : 'Disabled'; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Status</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $sms_env === 'production' ? 'bg-emerald-500/10' : 'bg-amber-500/10'; ?> flex items-center justify-center shrink-0">
            <i class="fas fa-server <?php echo $sms_env === 'production' ? 'text-emerald-400' : 'text-amber-400'; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $sms_env === 'production' ? 'text-emerald-400' : 'text-amber-400'; ?> truncate"><?php echo ucfirst($sms_env); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Environment</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-plug text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400 truncate"><?php echo $sms_provider === 'twilio' ? 'Twilio' : "Africa's Talking"; ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Provider</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-wallet text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400 truncate"><?php echo number_format($sms_balance); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Credits</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Left: SMS Configuration -->
    <div class="lg:col-span-2">
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="save_sms">

            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-cog text-amber-400"></i> SMS Configuration
                    </h3>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sms_enabled" value="1" <?php echo $sms_enabled ? 'checked' : ''; ?> class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-700 peer-focus:ring-1 peer-focus:ring-amber-500 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span class="ml-2 text-xs text-slate-400">SMS</span>
                    </label>
                </div>
                <div class="p-4 space-y-3">
                    <div>
                        <label for="smsProvider" class="block text-xs text-slate-400 mb-1.5 font-medium">SMS Provider</label>
                        <select name="sms_provider" id="smsProvider" onchange="updateProviderLabels()"
                                class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="africastalking" <?php echo $sms_provider === 'africastalking' ? 'selected' : ''; ?>>Africa's Talking</option>
                            <option value="twilio" <?php echo $sms_provider === 'twilio' ? 'selected' : ''; ?>>Twilio</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="smsUsername" class="block text-xs text-slate-400 mb-1.5 font-medium" id="usernameLabel">Username</label>
                            <input type="text" name="sms_username" id="smsUsername"
                                   value="<?php echo htmlspecialchars($settings['sms_username'] ?? ''); ?>"
                                   placeholder="sandbox"
                                   class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <p class="text-xs text-slate-500 mt-1" id="usernameHelp">Use "sandbox" for testing</p>
                        </div>
                        <div>
                            <label for="smsApiKey" class="block text-xs text-slate-400 mb-1.5 font-medium" id="apiKeyLabel">API Key</label>
                            <input type="password" name="sms_api_key" id="smsApiKey"
                                   value="<?php echo htmlspecialchars($settings['sms_api_key'] ?? ''); ?>"
                                   placeholder="Enter your API key"
                                   class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <p class="text-xs text-slate-500 mt-1" id="apiKeyHelp">Get from provider dashboard</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label for="smsSenderId" class="block text-xs text-slate-400 mb-1.5 font-medium" id="senderIdLabel">Sender ID</label>
                            <input type="text" name="sms_sender_id" id="smsSenderId"
                                   value="<?php echo htmlspecialchars($settings['sms_sender_id'] ?? 'JAKABABA'); ?>"
                                   placeholder="JAKABABA"
                                   class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <p class="text-xs text-slate-500 mt-1" id="senderIdHelp">Registered sender (max 11 chars)</p>
                        </div>
                        <div id="environmentField">
                            <label for="smsEnvironment" class="block text-xs text-slate-400 mb-1.5 font-medium">Environment</label>
                            <select name="sms_environment" id="smsEnvironment"
                                    class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                                <option value="sandbox" <?php echo $sms_env === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                <option value="production" <?php echo $sms_env === 'production' ? 'selected' : ''; ?>>Production (Live)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-save text-xs"></i> Save Settings
                </button>
                <button type="button" onclick="testConnection()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-plug text-xs"></i> Test Connection
                </button>
            </div>
        </form>
    </div>

    <!-- Right sidebar -->
    <div class="space-y-4">

        <!-- SMS Credits -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-wallet text-amber-400"></i> SMS Credits
                </h3>
            </div>
            <div class="p-4 text-center">
                <div class="text-3xl font-bold text-amber-400"><?php echo number_format($sms_balance); ?></div>
                <div class="text-xs text-slate-500 mt-1">Available Credits</div>
                <button onclick="openCreditModal()" class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Add Credits
                </button>
            </div>
        </div>

        <!-- OTP Login Status -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-mobile-alt text-amber-400"></i> OTP Login
                </h3>
            </div>
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-slate-400">Status</span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium <?php echo $sms_enabled ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-400'; ?>">
                        <?php echo $sms_enabled ? 'Active' : 'Disabled'; ?>
                    </span>
                </div>
                <p class="text-xs text-slate-500 mb-3">
                    Users can login with phone number + OTP code sent via SMS.
                </p>
                <a href="../auth/login.php" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                    <i class="fas fa-external-link-alt"></i> Test Login Page
                </a>
            </div>
        </div>

        <!-- Quick Guide -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-info-circle text-amber-400"></i> Quick Guide
                </h3>
            </div>
            <div class="p-4">
                <div class="space-y-2.5 text-sm">
                    <?php
                    $steps = [
                        'Sign up at <a href="https://africastalking.com" target="_blank" class="text-amber-400 hover:text-amber-300 transition-colors">africastalking.com</a> or <a href="https://twilio.com" target="_blank" class="text-amber-400 hover:text-amber-300 transition-colors">twilio.com</a>',
                        'Get API credentials from your provider dashboard',
                        'Enter credentials above and save settings',
                        'Test connection to verify setup',
                        'Users can now login with phone + OTP',
                    ];
                    foreach ($steps as $i => $step):
                    ?>
                    <div class="flex items-start gap-2">
                        <span class="w-5 h-5 rounded-full bg-amber-500/15 text-amber-400 text-xs flex items-center justify-center shrink-0 font-bold"><?php echo $i + 1; ?></span>
                        <p class="text-slate-400"><?php echo $step; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Webhook URL -->
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-700/60 bg-slate-800/60">
                <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-link text-amber-400"></i> Webhook URL
                </h3>
            </div>
            <div class="p-4">
                <div class="bg-slate-900 border border-slate-700 rounded-lg p-2.5 break-all text-slate-400 text-xs font-mono">
                    <?php echo htmlspecialchars($webhook_url); ?>
                </div>
                <p class="text-xs text-slate-500 mt-2">Use this URL for delivery status callbacks</p>
            </div>
        </div>
    </div>
</div>

<!-- Credit Purchase Modal -->
<div id="creditModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-shopping-cart text-amber-400"></i> Add SMS Credits
            </h3>
            <button onclick="closeCreditModal()" class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-slate-600 hover:text-white transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>

        <label class="block text-xs text-slate-400 mb-2 font-medium">Select Package</label>
        <div class="grid grid-cols-2 gap-2 mb-4">
            <?php foreach ([100, 500, 1000, 5000] as $pkg): ?>
            <button type="button" onclick="selectPackage(<?php echo $pkg; ?>)" class="package-btn p-3 bg-slate-900 border border-slate-700 rounded-lg text-center hover:border-amber-500/40 transition-colors" data-selected="false">
                <div class="text-lg font-bold text-white"><?php echo number_format($pkg); ?></div>
                <div class="text-xs text-slate-500">Credits</div>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="mb-4">
            <label for="customCredits" class="block text-xs text-slate-400 mb-1.5 font-medium">Or enter custom amount</label>
            <input type="number" id="customCredits" min="1" placeholder="Enter number of credits"
                   class="w-full px-2.5 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <button onclick="purchaseCredits()" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
            <i class="fas fa-shopping-cart text-xs"></i> Purchase Credits
        </button>
        <p class="text-xs text-slate-500 mt-2 text-center">Credits will be added to your account.</p>
    </div>
</div>

<script>
function updateProviderLabels() {
    const provider = document.getElementById('smsProvider').value;
    const usernameLabel = document.getElementById('usernameLabel');
    const usernameHelp = document.getElementById('usernameHelp');
    const apiKeyLabel = document.getElementById('apiKeyLabel');
    const apiKeyHelp = document.getElementById('apiKeyHelp');
    const senderIdLabel = document.getElementById('senderIdLabel');
    const senderIdHelp = document.getElementById('senderIdHelp');
    const environmentField = document.getElementById('environmentField');
    const smsUsername = document.getElementById('smsUsername');
    const smsSenderId = document.getElementById('smsSenderId');

    if (provider === 'twilio') {
        usernameLabel.textContent = 'Account SID';
        usernameHelp.textContent = 'Twilio Account SID (starts with AC...)';
        apiKeyLabel.textContent = 'Auth Token';
        apiKeyHelp.textContent = 'Your Twilio Auth Token';
        senderIdLabel.textContent = 'From Number';
        senderIdHelp.textContent = 'Twilio phone number (e.g., +1234567890)';
        smsUsername.placeholder = 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        smsSenderId.placeholder = '+1234567890';
        environmentField.style.display = 'none';
    } else {
        usernameLabel.textContent = "Africa's Talking Username";
        usernameHelp.textContent = 'Use "sandbox" for testing';
        apiKeyLabel.textContent = 'API Key';
        apiKeyHelp.textContent = "Get from AT dashboard";
        senderIdLabel.textContent = 'Sender ID';
        senderIdHelp.textContent = 'Registered sender (max 11 chars)';
        smsUsername.placeholder = 'sandbox';
        smsSenderId.placeholder = 'JAKABABA';
        environmentField.style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', updateProviderLabels);

function testConnection() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    formData.set('action', 'test_connection');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(() => window.location.reload())
    .catch(err => alert('Error: ' + err.message));
}

function openCreditModal() {
    document.getElementById('creditModal').classList.remove('hidden');
}

function closeCreditModal() {
    document.getElementById('creditModal').classList.add('hidden');
    document.querySelectorAll('.package-btn').forEach(btn => {
        btn.classList.remove('border-amber-500/40', 'bg-amber-500/10');
        btn.dataset.selected = 'false';
    });
    document.getElementById('customCredits').value = '';
}

function selectPackage(credits) {
    document.querySelectorAll('.package-btn').forEach(btn => {
        btn.classList.remove('border-amber-500/40', 'bg-amber-500/10');
        btn.dataset.selected = 'false';
    });
    const btn = event.currentTarget;
    btn.classList.add('border-amber-500/40', 'bg-amber-500/10');
    btn.dataset.selected = 'true';
    document.getElementById('customCredits').value = credits;
}

function purchaseCredits() {
    const credits = document.getElementById('customCredits').value;
    if (!credits || credits < 1) {
        alert('Please select or enter a valid number of credits');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_credits');
    formData.append('credits', credits);
    formData.append('csrf_token', '<?php echo $csrf_token; ?>');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Credits added successfully! New balance: ' + data.new_balance);
            closeCreditModal();
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>