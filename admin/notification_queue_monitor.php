<?php
/**
 * Notification Queue Monitor
 * Admin interface for monitoring the notification queue system
 * 
 * Provides visibility into:
 * - Queue status counts (pending, processing, sent, failed)
 * - Recent failed notifications with error details
 * - Processing statistics
 * - Manual retry capabilities
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions for admin features
if (!check_permission('admin.access') && !is_super_admin()) {
    enforce_permission('admin.access');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

if (!$tenant_id) {
    http_response_code(403);
    exit('Company context missing. Please log in again.');
}

// Handle CSRF token for POST requests
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle manual retry requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retry_failed') {
    // CSRF validation
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid security token');
    }
    
    $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    if ($notificationId > 0) {
        try {
            // Get the notification
            $stmt = $pdo->prepare("
                SELECT id, tenant_id, type, payload, attempts, max_attempts 
                FROM notification_queue 
                WHERE id = ? AND tenant_id = ? AND status = 'failed'
            ");
            $stmt->execute([$notificationId, $tenant_id]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($notification) {
                // Reset to pending for retry (if under max attempts)
                $newAttempts = (int)$notification['attempts'];
                $maxAttempts = (int)$notification['max_attempts'];
                
                if ($newAttempts < $maxAttempts) {
                    $updateStmt = $pdo->prepare("
                        UPDATE notification_queue 
                        SET status = 'pending', last_attempt_at = NULL 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$notificationId]);
                    
                    $result = ['success' => true, 'message' => 'Notification queued for retry'];
                } else {
                    $result = ['success' => false, 'message' => 'Maximum retry attempts exceeded'];
                }
            } else {
                $result = ['success' => false, 'message' => 'Notification not found or not failed'];
            }
        } catch (Exception $e) {
            $result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}

// Get queue statistics
$statsQuery = "
    SELECT 
        status,
        COUNT(*) as count
    FROM notification_queue 
    WHERE tenant_id = ?
    GROUP BY status
";
$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute([$tenant_id]);
$statsRows = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'pending' => 0,
    'processing' => 0,
    'sent' => 0,
    'failed' => 0
];

foreach ($statsRows as $row) {
    $status = $row['status'];
    if (array_key_exists($status, $stats)) {
        $stats[$status] = (int)$row['count'];
    }
}

// Get recent failed notifications (last 20)
$failedQuery = "
    SELECT id, type, payload, attempts, max_attempts, last_attempt_at, 
           created_at, error_message
    FROM notification_queue 
    WHERE tenant_id = ? AND status = 'failed'
    ORDER BY updated_at DESC
    LIMIT 20
";
$failedStmt = $pdo->prepare($failedQuery);
$failedStmt->execute([$tenant_id]);
$failedNotifications = $failedStmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent processing stats (last hour)
$recentQuery = "
    SELECT 
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_last_hour,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_last_hour,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_now
    FROM notification_queue 
    WHERE tenant_id = ? 
    AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
";
$recentStmt = $pdo->prepare($recentQuery);
$recentStmt->execute([$tenant_id]);
$recentStats = $recentStmt->fetch(PDO::FETCH_ASSOC);
$page_title = 'Notification Queue Monitor';
$current_page = 'notification_queue';
ob_start();
?>
<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-bell text-amber-400"></i> Notification Queue Monitor
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Monitor and manage queued notifications (email, SMS, alerts)</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <span class="text-xs text-slate-500">Auto-refresh: 30s</span>
        <button id="refresh-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-sync-alt text-xs"></i> Refresh
        </button>
    </div>
</div>

<!-- Alert Banner -->
<div id="action-alert" class="mb-4 hidden"></div>

<!-- Queue Statistics -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-clock text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400" id="pending-count"><?= $stats['pending'] ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Pending</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-sync-alt text-blue-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-blue-400" id="processing-count"><?= $stats['processing'] ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Processing</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-check-circle text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400" id="sent-count"><?= $stats['sent'] ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Sent</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-exclamation-triangle text-red-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-red-400" id="failed-count"><?= $stats['failed'] ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Failed</div>
        </div>
    </div>
</div>

<!-- Recent Activity (Last Hour) -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4 mb-5">
    <h2 class="text-sm font-semibold text-white mb-3">Recent Activity (Last Hour)</h2>
    <div class="grid grid-cols-3 gap-3 text-center">
        <div class="bg-slate-900/50 rounded-lg p-3">
            <p class="text-xs text-slate-500 mb-1">Sent</p>
            <p class="text-lg font-bold text-emerald-400" id="sent-last-hour"><?= $recentStats['sent_last_hour'] ?? 0 ?></p>
        </div>
        <div class="bg-slate-900/50 rounded-lg p-3">
            <p class="text-xs text-slate-500 mb-1">Failed</p>
            <p class="text-lg font-bold text-red-400" id="failed-last-hour"><?= $recentStats['failed_last_hour'] ?? 0 ?></p>
        </div>
        <div class="bg-slate-900/50 rounded-lg p-3">
            <p class="text-xs text-slate-500 mb-1">Processing</p>
            <p class="text-lg font-bold text-blue-400" id="processing-now"><?= $recentStats['processing_now'] ?? 0 ?></p>
        </div>
    </div>
</div>

<!-- Failed Notifications Detail -->
<?php if (!empty($failedNotifications)): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-700/60 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-white flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-400 text-xs"></i>
            Recent Failed Notifications
        </h3>
        <span class="text-xs text-slate-500">Click retry to re-queue</span>
    </div>
    <div class="divide-y divide-slate-700/40">
        <?php foreach ($failedNotifications as $notification): 
            $payload = json_decode($notification['payload'], true);
            $to = $payload['to'] ?? 'N/A';
            $subject = $payload['subject'] ?? 'N/A';
            $error = htmlspecialchars($notification['error_message'] ?? 'No error message');
            $attempts = $notification['attempts'];
            $maxAttempts = $notification['max_attempts'];
        ?>
        <div class="px-4 py-3 hover:bg-slate-700/30 transition-colors failed-notification-item" 
             data-id="<?= $notification['id'] ?>" 
             data-attempts="<?= $attempts ?>" 
             data-max-attempts="<?= $maxAttempts ?>">
            <div class="flex justify-between items-start gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/15 text-red-400 ring-1 ring-red-500/30">
                            Attempt <?= $attempts ?>/<?= $maxAttempts ?>
                        </span>
                        <span class="text-sm font-medium text-white truncate"><?= htmlspecialchars($notification['type']) ?></span>
                    </div>
                    <p class="text-xs text-slate-400 truncate">To: <?= htmlspecialchars($to) ?></p>
                    <?php if (!empty($subject) && $subject !== 'N/A'): ?>
                    <p class="text-xs text-slate-500 truncate mt-0.5"><?= htmlspecialchars($subject) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-slate-600 mt-1"><?= date('M d, H:i', strtotime($notification['created_at'])) ?></p>
                </div>
                <button class="retry-btn inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-colors <?= ($attempts >= $maxAttempts) ? 'opacity-50 cursor-not-allowed' : '' ?>"
                        data-id="<?= $notification['id'] ?>">
                    <i class="fas fa-redo text-xs"></i> Retry
                </button>
            </div>
            <?php if (!empty($error) && $error !== 'No error message'): ?>
            <div class="mt-2 pt-2 border-t border-slate-700/30">
                <p class="text-xs text-slate-500 font-medium mb-0.5">Error:</p>
                <div class="error-full text-xs text-red-400/80 font-mono bg-slate-900/50 rounded p-2 overflow-x-auto"><?= $error ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-12 text-center">
    <i class="fas fa-check-circle text-emerald-400 text-4xl mb-3 block"></i>
    <p class="text-white font-semibold">No Failed Notifications</p>
    <p class="text-sm text-slate-500 mt-1">All notifications are processing successfully!</p>
</div>
<?php endif; ?>

    <script>
        // Auto-refresh every 30 seconds
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                refreshPage();
            }, 30000); // 30 seconds
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
        
        function refreshPage() {
            // Show refresh indicator
            const refreshBtn = document.getElementById('refresh-btn');
            const originalHtml = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Refreshing...';
            refreshBtn.disabled = true;
            
            // Reload the page
            window.location.reload();
        }
        
        // Handle manual refresh button
        document.getElementById('refresh-btn').addEventListener('click', function(e) {
            e.preventDefault();
            refreshPage();
        });
        
        // Handle retry buttons
        document.querySelectorAll('.retry-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (this.classList.contains('opacity-50')) {
                    return; // Disabled button
                }
                
                const notificationId = this.getAttribute('data-id');
                const attempts = this.getAttribute('data-attempts');
                const maxAttempts = this.getAttribute('data-max-attempts');
                
                // Show loading state
                const originalHtml = this.innerHTML;
                this.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Retrying...';
                this.disabled = true;
                
                // Send AJAX request
                fetch('<?= base_url('admin/notification_queue_monitor.php') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= htmlspecialchars($csrf_token) ?>'
                    },
                    body: JSON.stringify({
                        action: 'retry_failed',
                        notification_id: notificationId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // Show alert
                    showAlert(data.success ? 'success' : 'error', data.message);
                    
                    // If successful, refresh the page after a short delay
                    if (data.success) {
                        setTimeout(() => {
                            refreshPage();
                        }, 1500);
                    } else {
                        // Reset button state on failure
                        this.innerHTML = originalHtml;
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'Network error occurred');
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                });
            });
        });
        
        // Handle click on failed notification item (select text for copying)
        document.querySelectorAll('.failed-notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't trigger if clicking the retry button
                if (e.target.classList.contains('retry-btn')) {
                    return;
                }
                
                // Select the error text for easy copying
                const errorElement = this.querySelector('.error-full');
                if (errorElement) {
                    const range = document.createRange();
                    range.selectNodeContents(errorElement);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                    
                    // Visual feedback
                    this.style.backgroundColor = '#f0f9ff';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 1500);
                }
            });
        });
        
        // Show alert banner
        function showAlert(type, message) {
            const alertDiv = document.getElementById('action-alert');
            const typeClasses = {
                'success': 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400',
                'error': 'bg-red-500/10 border border-red-500/30 text-red-400',
                'warning': 'bg-amber-500/10 border border-amber-500/30 text-amber-400',
                'info': 'bg-blue-500/10 border border-blue-500/30 text-blue-400'
            };
            
            alertDiv.className = `mb-4 flex items-center gap-2 px-3 py-2 rounded-lg text-sm ${typeClasses[type] || typeClasses['info']}`;
            alertDiv.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle')} text-xs"></i>
                <span>${message}</span>
            `;
            alertDiv.classList.remove('hidden');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.classList.add('hidden');
            }, 5000);
        }
        
        // Start auto-refresh when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
            
            // Stop auto-refresh when user is actively typing or interacting
            document.addEventListener('keydown', stopAutoRefresh);
            document.addEventListener('mousedown', stopAutoRefresh);
            document.addEventListener('touchstart', stopAutoRefresh);
            
            // Resume auto-refresh after a delay of inactivity
            let inactivityTimer;
            function resetInactivityTimer() {
                clearTimeout(inactivityTimer);
                inactivityTimer = setTimeout(startAutoRefresh, 5000); // 5 seconds of inactivity
            }
            
            document.addEventListener('keydown', resetInactivityTimer);
            document.addEventListener('mousedown', resetInactivityTimer);
            document.addEventListener('touchstart', resetInactivityTimer);
            
            // Initialize inactivity timer
            resetInactivityTimer();
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>

<style>
    .failed-notification-item { transition: all 0.2s; }
    .failed-notification-item:hover { transform: translateX(2px); }
</style>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>