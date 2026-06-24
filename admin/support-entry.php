<?php
/**
 * Support Access Entry
 * 
 * Validates support access token and grants temporary access to a company.
 * Shows visual indicator when in support mode.
 * 
 * @package JDH_POS\Admin
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/OwnerPanelService.php';

use JDH_POS\Services\OwnerPanelService;

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants']);
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$ownerService = new OwnerPanelService($pdo, $adminId);
$supportLogsAvailable = admin_table_exists('support_access_logs');

$current_page = 'support_entry';
$page_title = 'Enter Support Session';

$message = '';
$messageType = '';

if (!$supportLogsAvailable) {
    $message = 'Support access is not configured in this database yet. Apply the super admin security migration to enable support sessions.';
    $messageType = 'error';
}

// Handle token validation and entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supportLogsAvailable) {
    $token = trim($_POST['access_token'] ?? '');
    
    if (empty($token)) {
        $message = 'Please enter an access token.';
        $messageType = 'error';
    } else {
        try {
            // Validate token
            $stmt = $pdo->prepare("
                SELECT sal.*, t.name as company_name, t.slug as company_slug
                FROM support_access_logs sal
                JOIN pos_tenants t ON sal.tenant_id = t.id
                WHERE sal.token = ? 
                AND sal.admin_id = ?
                AND sal.status = 'active'
                AND sal.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$token, $_SESSION['admin_id']]);
            $access = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$access) {
                $message = 'Invalid or expired access token.';
                $messageType = 'error';
            } else {
                // Set support session
                $_SESSION['support_mode'] = true;
                $_SESSION['support_access_id'] = $access['id'];
                $_SESSION['support_company_id'] = $access['tenant_id'];
                $_SESSION['support_company_name'] = $access['company_name'];
                $_SESSION['support_company_slug'] = $access['company_slug'];
                $_SESSION['support_access_type'] = $access['access_type'];
                $_SESSION['support_started_at'] = date('Y-m-d H:i:s');
                $_SESSION['support_expires_at'] = $access['expires_at'];
                
                // Log the entry action
                $ownerService->logSupportAction($access['id'], 'session_entered', 'Support session started');
                
                // Redirect to company context
                header('Location: ' . base_url('pos/pos.php') . '?tenant_id=' . $access['tenant_id'] . '&support_mode=1');
                exit;
            }
        } catch (Exception $e) {
            $message = 'Error validating token: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get active support sessions for quick entry
$activeSessions = $supportLogsAvailable ? $ownerService->getActiveSupportAccess() : [];

ob_start();
?>

<div class="max-w-2xl mx-auto">
    <!-- Header -->
    <div class="flex items-center gap-4 mb-6">
        <a href="<?php echo admin_url('support-access.php'); ?>" class="text-slate-400 hover:text-white">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-white tracking-tight">Enter Support Session</h1>
            <p class="text-sm text-slate-400 mt-1">Access a company using your support token</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? : 'error' ?> text-sm mb-4">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Token Entry Form -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6 mb-6">
        <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-full bg-amber-500/10 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-key text-amber-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-white">Enter Access Token</h3>
            <p class="text-sm text-slate-400 mt-1">Paste the token you received when creating support access</p>
        </div>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm text-slate-300 mb-2">Access Token</label>
                <input type="text" name="access_token" required <?= $supportLogsAvailable ? '' : 'disabled' ?>
                       class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 text-center font-mono text-lg tracking-wider"
                       placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
            </div>
            
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors w-full justify-center <?= $supportLogsAvailable ? '' : 'opacity-60 cursor-not-allowed' ?>" <?= $supportLogsAvailable ? '' : 'disabled' ?>>
                <i class="fas fa-sign-in-alt"></i> Enter Support Session
            </button>
        </form>
    </div>

    <!-- Quick Entry - Active Sessions -->
    <?php if (!empty($activeSessions)): ?>
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-bolt text-amber-400"></i>
            Quick Entry - Your Active Sessions
        </h3>
        
        <div class="space-y-3">
            <?php foreach ($activeSessions as $session): ?>
            <div class="flex items-center justify-between p-4 rounded-lg bg-slate-800/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold">
                        <?= strtoupper(substr($session['company_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="text-white font-medium"><?= htmlspecialchars($session['company_name']) ?></p>
                        <p class="text-xs text-slate-400">
                            <?= ucfirst($session['access_type']) ?> access • 
                            Expires <?= date('H:i', strtotime($session['expires_at'])) ?>
                        </p>
                    </div>
                </div>
                <form method="post" class="inline">
                    <input type="hidden" name="access_token" value="<?= htmlspecialchars($session['token']) ?>">
                    <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-amber-500/20 text-amber-400 hover:bg-amber-500/30 transition">
                        <i class="fas fa-sign-in-alt"></i> Enter
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Instructions -->
    <div class="mt-6 p-4 rounded-lg bg-blue-500/10 border border-blue-500/20">
        <h4 class="text-sm font-medium text-blue-400 mb-2">
            <i class="fas fa-info-circle"></i> How it works
        </h4>
        <ul class="text-sm text-slate-400 space-y-1">
            <li>• Create support access from the <a href="support-access.php" class="text-cyan-400">Support Access</a> page</li>
            <li>• You'll receive a unique token for the session</li>
            <li>• Enter the token here to access the company</li>
            <li>• All actions are logged for audit purposes</li>
            <li>• Session expires automatically after the set time</li>
        </ul>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
