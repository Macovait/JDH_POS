<?php
/**
 * Support Access Management - Owner Panel
 * 
 * Manage temporary support access sessions for troubleshooting.
 * Full audit trail of all actions performed during support access.
 * 
 * @package JDH_POS\Admin
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';

admin_require_super_admin();

$pdo = admin_require_db(['admins', 'pos_tenants']);
$supportSessionsAvailable = admin_table_exists('support_access_sessions');

$current_page = 'support_access';
$page_title = 'Support Access Management';

// Handle actions
$message = '';
$messageType = '';

if (!$supportSessionsAvailable) {
    $message = 'Support access storage is not installed in this database yet. Apply the super admin security migration to enable this module.';
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supportSessionsAvailable) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'revoke':
                $accessId = (int) ($_POST['access_id'] ?? 0);
                $reason = $_POST['reason'] ?? '';
                
                // Simplified revoke - just update status
                $stmt = $pdo->prepare("UPDATE support_access_sessions SET status = 'revoked', revoked_at = NOW(), revoke_reason = ? WHERE id = ?");
                if ($stmt->execute([$reason, $accessId])) {
                    $message = 'Support access revoked successfully!';
                    $messageType = 'success';
                } else {
                    throw new Exception('Failed to revoke support access.');
                }
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get active sessions (simplified direct query)
$activeSessions = [];
if ($supportSessionsAvailable) {
try {
    $activeSessions = $pdo->query("
        SELECT s.*, t.name as tenant_name, t.subdomain 
        FROM support_access_sessions s 
        LEFT JOIN pos_tenants t ON s.tenant_id = t.id 
        WHERE s.status = 'active' 
        ORDER BY s.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activeSessions = [];
}
}

// Get history (simplified)
$page = (int) ($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$history = [];
$totalPages = 1;
if ($supportSessionsAvailable) {
try {
    $history = $pdo->query("
        SELECT s.*, t.name as tenant_name, t.subdomain 
        FROM support_access_sessions s 
        LEFT JOIN pos_tenants t ON s.tenant_id = t.id 
        ORDER BY s.created_at DESC 
        LIMIT $perPage OFFSET $offset
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $total = $pdo->query("SELECT COUNT(*) FROM support_access_sessions")->fetchColumn();
    $totalPages = (int) ceil($total / $perPage);
    if ($totalPages < 1) $totalPages = 1;
} catch (Exception $e) {
    $history = [];
}
}

// Helper functions
function statusBadge($status) {
    $colors = [
        'active'    => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
        'expired'   => 'bg-slate-500/20 text-slate-400 border-slate-500/30',
        'revoked'   => 'bg-red-500/20 text-red-400 border-red-500/30',
        'completed' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    ];
    $cls = $colors[$status] ?? 'bg-slate-500/20 text-slate-400 border-slate-500/30';
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border ' . $cls . '">' . ucfirst($status) . '</span>';
}

function accessTypeBadge($type) {
    $colors = [
        'read_only'  => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
        'full_access' => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
    ];
    $cls = $colors[$type] ?? 'bg-slate-500/20 text-slate-400 border-slate-500/30';
    $label = $type === 'read_only' ? 'Read Only' : 'Full Access';
    return '<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border ' . $cls . '">' . $label . '</span>';
}

function timeAgo($datetime) {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '-';
    $diff = time() - $timestamp;
    if ($diff < 60)   return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d, Y', $timestamp);
}

// Start output buffering
ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-lg font-bold text-white tracking-tight">Support Access</h1>
            <p class="text-sm text-slate-400 mt-1">Manage temporary support sessions and audit trails</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?php echo admin_url('audit_logs.php'); ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors text-xs">
                <i class="fas fa-shield-alt"></i> Audit Logs
            </a>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? : 'error' ?> text-sm">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <!-- Active Sessions -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-broadcast-tower text-emerald-400"></i>
                Active Sessions (<?= count($activeSessions) ?>)
            </h3>
        </div>
        
        <?php if (empty($activeSessions)): ?>
        <div class="p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-500/10 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-circle text-emerald-400 text-2xl"></i>
            </div>
            <h4 class="text-white font-medium mb-2">No Active Sessions</h4>
            <p class="text-sm text-slate-400">All support access sessions have been completed or expired.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50">
                    <tr class="text-slate-400 text-xs uppercase">
                        <th class="text-left py-3 px-5">Admin</th>
                        <th class="text-left py-3 px-5">Company</th>
                        <th class="text-left py-3 px-5">Access Type</th>
                        <th class="text-left py-3 px-5">Started</th>
                        <th class="text-left py-3 px-5">Expires</th>
                        <th class="text-left py-3 px-5">Reason</th>
                        <th class="text-right py-3 px-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    <?php foreach ($activeSessions as $session): ?>
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="py-4 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                                    <?= strtoupper(substr($session['admin_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-white font-medium"><?= htmlspecialchars($session['admin_name']) ?></p>
                                    <p class="text-xs text-slate-500"><?= htmlspecialchars($session['admin_email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-5">
                            <p class="text-white font-medium"><?= htmlspecialchars($session['company_name']) ?></p>
                        </td>
                        <td class="py-4 px-5"><?= accessTypeBadge($session['access_type']) ?></td>
                        <td class="py-4 px-5 text-slate-400 text-xs"><?= timeAgo($session['started_at']) ?></td>
                        <td class="py-4 px-5">
                            <?php 
                            $remaining = strtotime($session['expires_at']) - time();
                            $minutes = floor($remaining / 60);
                            $hours = floor($minutes / 60);
                            $displayMinutes = $minutes % 60;
                            ?>
                            <p class="text-amber-400 font-medium"><?= $hours ?>h <?= $displayMinutes ?>m</p>
                            <p class="text-xs text-slate-500"><?= date('H:i', strtotime($session['expires_at'])) ?></p>
                        </td>
                        <td class="py-4 px-5">
                            <p class="text-sm text-slate-300 truncate max-w-[200px]" title="<?= htmlspecialchars($session['reason']) ?>">
                                <?= htmlspecialchars($session['reason']) ?>
                            </p>
                        </td>
                        <td class="py-4 px-5 text-right">
                            <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to revoke this access?');">
                                <input type="hidden" name="action" value="revoke">
                                <input type="hidden" name="access_id" value="<?= $session['id'] ?>">
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">
                                    <i class="fas fa-times"></i> Revoke
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Access History -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white flex items-center gap-2">
                <i class="fas fa-history text-blue-400"></i>
                Access History
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-800/50">
                    <tr class="text-slate-400 text-xs uppercase">
                        <th class="text-left py-3 px-5">Admin</th>
                        <th class="text-left py-3 px-5">Company</th>
                        <th class="text-left py-3 px-5">Type</th>
                        <th class="text-left py-3 px-5">Status</th>
                        <th class="text-left py-3 px-5">Duration</th>
                        <th class="text-left py-3 px-5">Actions</th>
                        <th class="text-left py-3 px-5">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/50">
                    <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-500">
                            <p>No access history found</p>
                        </td>
                    </tr>
                    <?php else: foreach ($history as $log): ?>
                    <tr class="hover:bg-slate-700/30 transition">
                        <td class="py-3 px-5">
                            <p class="text-white"><?= htmlspecialchars($log['admin_name']) ?></p>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($log['admin_email']) ?></p>
                        </td>
                        <td class="py-3 px-5 text-white"><?= htmlspecialchars($log['company_name']) ?></td>
                        <td class="py-3 px-5"><?= accessTypeBadge($log['access_type']) ?></td>
                        <td class="py-3 px-5"><?= statusBadge($log['status']) ?></td>
                        <td class="py-3 px-5 text-slate-400 text-xs">
                            <?php if ($log['started_at'] && $log['ended_at']): ?>
                                <?php 
                                $duration = strtotime($log['ended_at']) - strtotime($log['started_at']);
                                $minutes = floor($duration / 60);
                                echo $minutes . ' min';
                                ?>
                            <?php elseif ($log['started_at'] && $log['expires_at']): ?>
                                <?= timeAgo($log['started_at']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-5">
                            <span class="text-sm text-slate-300"><?= $log['action_count'] ?? 0 ?> actions</span>
                        </td>
                        <td class="py-3 px-5 text-slate-400 text-xs"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between p-4 border-t border-slate-700/50">
            <p class="text-xs text-slate-400">Page <?= $page ?> of <?= $totalPages ?></p>
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                <a href="support-access.php?page=<?= $page - 1 ?>" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800/50 text-slate-300 hover:bg-slate-700/50">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                <a href="support-access.php?page=<?= $page + 1 ?>" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800/50 text-slate-300 hover:bg-slate-700/50">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Guidelines -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-400"></i>
                Support Access Guidelines
            </h3>
            <ul class="space-y-2 text-sm text-slate-400">
                <li class="flex items-start gap-2">
                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                    <span>Always document the reason for accessing a tenant's data</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                    <span>Use "Read Only" access when possible for troubleshooting</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                    <span>Access sessions automatically expire after the set duration</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check text-emerald-400 mt-1"></i>
                    <span>All actions during support access are logged for audit</span>
                </li>
            </ul>
        </div>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-amber-400"></i>
                Security Reminders
            </h3>
            <ul class="space-y-2 text-sm text-slate-400">
                <li class="flex items-start gap-2">
                    <i class="fas fa-shield-alt text-amber-400 mt-1"></i>
                    <span>Never share your support access token with anyone</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-shield-alt text-amber-400 mt-1"></i>
                    <span>Revoke access immediately after completing support tasks</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-shield-alt text-amber-400 mt-1"></i>
                    <span>Report any suspicious activity to the security team</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-shield-alt text-amber-400 mt-1"></i>
                    <span>Regular audits are performed on support access logs</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php
// Get buffered content
$page_content = ob_get_clean();

// Include layout
require_once __DIR__ . '/layouts/super_admin.php';
?>

