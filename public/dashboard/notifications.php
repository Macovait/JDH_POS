<?php
/**
 * Notifications page for Jakababa POS
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

if (!check_permission('notifications.view') && !is_super_admin()) {
    enforce_permission('notifications.view');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'mark_read':
            try {
                $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE (user_id = ? OR user_id IS NULL) AND read_at IS NULL');
                $stmt->execute([$user_id]);
                $success_message = 'All notifications marked as read';
                log_activity('notifications.marked_read', null, ['count' => $stmt->rowCount()], $user_id);
            } catch (PDOException $e) { $error_message = 'Failed to mark notifications as read'; }
            break;
        case 'mark_one_read':
            $notification_id = intval($_POST['notification_id'] ?? 0);
            if ($notification_id > 0) {
                try {
                    $stmt = $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE id = ? AND (user_id = ? OR user_id IS NULL)');
                    $stmt->execute([$notification_id, $user_id]);
                    $success_message = 'Notification marked as read';
                } catch (PDOException $e) { $error_message = 'Failed to mark notification as read'; }
            }
            break;
        case 'delete_all':
            if (is_super_admin() || $user_role === 'Admin') {
                try {
                    $stmt = $pdo->prepare('DELETE FROM notifications WHERE read_at IS NOT NULL AND (user_id = ? OR user_id IS NULL)');
                    $stmt->execute([$user_id]);
                    $success_message = 'All read notifications deleted';
                    log_activity('notifications.deleted_all', null, ['count' => $stmt->rowCount()], $user_id);
                } catch (PDOException $e) { $error_message = 'Failed to delete notifications'; }
            }
            break;
        case 'delete_one':
            if (is_super_admin() || $user_role === 'Admin') {
                $notification_id = intval($_POST['notification_id'] ?? 0);
                if ($notification_id > 0) {
                    try {
                        $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ? AND (user_id = ? OR user_id IS NULL)');
                        $stmt->execute([$notification_id, $user_id]);
                        $success_message = 'Notification deleted';
                    } catch (PDOException $e) { $error_message = 'Failed to delete notification'; }
                }
            }
            break;
    }
}

$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all';

$sql = "SELECT n.*, CASE WHEN n.user_id IS NULL THEN 'System' ELSE 'Personal' END as notification_type FROM notifications n WHERE (n.user_id = ? OR n.user_id IS NULL)";
$params = [$user_id];

if ($filter === 'unread') $sql .= " AND n.read_at IS NULL";
elseif ($filter === 'read') $sql .= " AND n.read_at IS NOT NULL";

if ($type === 'system') $sql .= " AND n.user_id IS NULL";
elseif ($type === 'user') { $sql .= " AND n.user_id = ?"; $params[] = $user_id; }

$sql .= " ORDER BY n.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$unread_count = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND read_at IS NULL');
$unread_count->execute([$user_id]);
$unread_count = $unread_count->fetchColumn();
$total_count = count($notifications);

$grouped_notifications = [];
foreach ($notifications as $notification) {
    $date = date('Y-m-d', strtotime($notification['created_at']));
    if (!isset($grouped_notifications[$date])) $grouped_notifications[$date] = [];
    $grouped_notifications[$date][] = $notification;
}

$page_title = 'Notifications';
$can_delete = is_super_admin() || $user_role === 'Admin';
$csrf_token = $_SESSION['csrf_token'] ?? '';
ob_start();
?>

<!-- ============================================ -->
<!-- PAGE HEADER -->
<!-- ============================================ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-bell text-amber-400"></i> Notifications
            <?php if ($unread_count > 0): ?>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">
                <?php echo $unread_count; ?> new
            </span>
            <?php endif; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
            <?php echo number_format($total_count); ?> notification<?php echo $total_count !== 1 ? 's' : ''; ?> total
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <?php if ($unread_count > 0): ?>
        <form method="POST" class="inline">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="mark_read">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-colors">
                <i class="fas fa-check-double text-xs"></i> Mark All Read
            </button>
        </form>
        <?php endif; ?>
        <?php if ($can_delete && $total_count > 0): ?>
        <button onclick="openDeleteAllModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-red-400 transition-colors">
            <i class="fas fa-trash text-xs"></i> Clear Read
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- MESSAGES -->
<!-- ============================================ -->
<?php if ($success_message): ?>
<div id="successMsg" class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SUMMARY CARDS -->
<!-- ============================================ -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-bell text-amber-400 text-xs"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-amber-400"><?php echo number_format($total_count); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Total</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-envelope text-blue-400 text-xs"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-blue-400"><?php echo number_format($unread_count); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Unread</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-envelope-open text-emerald-400 text-xs"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-emerald-400"><?php echo number_format($total_count - $unread_count); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Read</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-slate-700/60 flex items-center justify-center shrink-0">
            <i class="fas fa-microchip text-slate-400 text-xs"></i>
        </div>
        <div>
            <div class="text-sm font-bold text-slate-300"><?php echo number_format(count(array_filter($notifications, fn($n) => $n['user_id'] === null))); ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">System</div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- FILTERS -->
<!-- ============================================ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">
    <!-- Filter Pills -->
    <div class="flex flex-wrap gap-1.5">
        <?php
        $filterPills = [
            ['val' => 'all',    'label' => 'All',         'class' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            ['val' => 'unread', 'label' => 'Unread',      'class' => 'bg-blue-500/20 text-blue-400 border-blue-500/30 hover:bg-blue-500/30'],
            ['val' => 'read',   'label' => 'Read',        'class' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/30'],
        ];
        foreach ($filterPills as $pill):
            $isActive = $filter === $pill['val'];
        ?>
        <a href="?filter=<?php echo $pill['val']; ?>&type=<?php echo urlencode($type); ?>"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border transition-colors <?php echo $isActive ? 'ring-2 ring-amber-500/40 ' : ''; ?><?php echo $pill['class']; ?>">
            <?php echo $pill['label']; ?>
        </a>
        <?php endforeach; ?>

        <span class="w-px bg-slate-700 mx-1"></span>

        <?php
        $typePills = [
            ['val' => 'all',    'label' => 'All Types',  'class' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
            ['val' => 'system', 'label' => 'System',     'class' => 'bg-slate-700/60 text-slate-300 border-slate-600 hover:bg-slate-700'],
            ['val' => 'user',   'label' => 'Personal',   'class' => 'bg-purple-500/20 text-purple-400 border-purple-500/30 hover:bg-purple-500/30'],
        ];
        foreach ($typePills as $pill):
            $isActive = $type === $pill['val'];
        ?>
        <a href="?filter=<?php echo urlencode($filter); ?>&type=<?php echo $pill['val']; ?>"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border transition-colors <?php echo $isActive ? 'ring-2 ring-amber-500/40 ' : ''; ?><?php echo $pill['class']; ?>">
            <?php echo $pill['label']; ?>
        </a>
        <?php endforeach; ?>

        <?php if ($filter !== 'all' || $type !== 'all'): ?>
        <a href="notifications.php" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 transition-colors">
            <i class="fas fa-times text-xs"></i> Clear
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- NOTIFICATIONS LIST -->
<!-- ============================================ -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">

    <?php if (empty($notifications)): ?>
    <div class="px-4 py-14 text-center">
        <i class="fas fa-bell-slash text-4xl text-slate-700 block mb-3"></i>
        <p class="text-slate-500 text-sm">No notifications found</p>
        <?php if ($filter !== 'all' || $type !== 'all'): ?>
        <a href="notifications.php" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
            <i class="fas fa-times text-xs"></i> Clear filters
        </a>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="divide-y divide-slate-700/40">
        <?php foreach ($grouped_notifications as $date => $day_notifications):
            if ($date === date('Y-m-d')) $dateLabel = 'Today';
            elseif ($date === date('Y-m-d', strtotime('-1 day'))) $dateLabel = 'Yesterday';
            else $dateLabel = date('F j, Y', strtotime($date));
        ?>
        <!-- Date Group Header -->
        <div class="px-3 py-2 bg-slate-900/40 border-b border-slate-700/40">
            <span class="text-xs font-semibold text-amber-400/70 uppercase tracking-wider"><?php echo $dateLabel; ?></span>
        </div>

        <?php foreach ($day_notifications as $notification):
            $isUnread = empty($notification['read_at']);
            $isSystem = $notification['user_id'] === null;
        ?>
        <div class="group flex items-start gap-3 px-3 py-3 hover:bg-slate-700/30 transition-colors <?php echo $isUnread ? 'border-l-2 border-amber-500' : ''; ?>"
             id="notification-<?php echo (int)$notification['id']; ?>">

            <!-- Icon -->
            <div class="w-8 h-8 rounded-lg <?php echo $isSystem ? 'bg-slate-700/60' : 'bg-amber-500/10'; ?> flex items-center justify-center shrink-0 mt-0.5">
                <i class="fas <?php echo $isSystem ? 'fa-microchip text-slate-400' : 'fa-user text-amber-400'; ?> text-xs"></i>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-semibold text-white"><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></span>
                        <?php if ($isUnread): ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">NEW</span>
                        <?php endif; ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium <?php echo $isSystem ? 'bg-slate-700/60 text-slate-400' : 'bg-purple-500/15 text-purple-400'; ?>">
                            <?php echo $notification['notification_type']; ?>
                        </span>
                    </div>
                    <span class="text-xs text-slate-500 shrink-0"><?php echo date('H:i', strtotime($notification['created_at'])); ?></span>
                </div>
                <p class="text-sm text-slate-400 mt-0.5 leading-relaxed"><?php echo nl2br(htmlspecialchars($notification['message'] ?? '')); ?></p>
                <?php if (!empty($notification['read_at'])): ?>
                <div class="text-xs text-slate-600 mt-1">Read <?php echo date('M j, g:i A', strtotime($notification['read_at'])); ?></div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                <?php if ($isUnread): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="mark_one_read">
                    <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                    <button type="submit"
                            class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-emerald-400 hover:bg-emerald-500/20 transition-colors"
                            title="Mark as read">
                        <i class="fas fa-check text-xs"></i>
                    </button>
                </form>
                <?php endif; ?>
                <?php if ($can_delete): ?>
                <button onclick="openDeleteModal(<?php echo (int)$notification['id']; ?>)"
                        class="w-6 h-6 flex items-center justify-center rounded-lg bg-slate-700/60 text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors"
                        title="Delete">
                    <i class="fas fa-trash text-xs"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <!-- Table Footer -->
    <div class="flex items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300 font-medium"><?php echo count($notifications); ?></span> notifications
        </p>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-amber-500 rounded-full"></span><?php echo $unread_count; ?> unread</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-slate-500 rounded-full"></span><?php echo $total_count - $unread_count; ?> read</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- DELETE SINGLE MODAL -->
<!-- ============================================ -->
<div id="deleteModal" class="fixed inset-0 bg-black/60  flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-3">
            <i class="fas fa-exclamation-triangle text-red-400"></i>
            <h3 class="text-sm font-semibold text-white">Delete Notification</h3>
        </div>
        <p class="text-sm text-slate-400 mb-4">Are you sure? This action cannot be undone.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="delete_one">
            <input type="hidden" name="notification_id" id="deleteNotificationId">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-3 py-2 bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-medium rounded-lg hover:bg-red-500/25 transition-colors">Delete</button>
                <button type="button" onclick="closeDeleteModal()" class="flex-1 px-3 py-2 bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- DELETE ALL MODAL -->
<!-- ============================================ -->
<div id="deleteAllModal" class="fixed inset-0 bg-black/60  flex items-center justify-center hidden z-50">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 max-w-sm w-full mx-4 shadow-xl">
        <div class="flex items-center gap-2 mb-3">
            <i class="fas fa-exclamation-triangle text-red-400"></i>
            <h3 class="text-sm font-semibold text-white">Clear Read Notifications</h3>
        </div>
        <p class="text-sm text-slate-400 mb-4">Delete all read notifications? This cannot be undone.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="delete_all">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-3 py-2 bg-red-500/15 border border-red-500/30 text-red-400 text-sm font-medium rounded-lg hover:bg-red-500/25 transition-colors">Clear All</button>
                <button type="button" onclick="closeDeleteAllModal()" class="flex-1 px-3 py-2 bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-600 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openDeleteModal(id) {
        document.getElementById('deleteNotificationId').value = id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }
    function openDeleteAllModal() { document.getElementById('deleteAllModal').classList.remove('hidden'); }
    function closeDeleteAllModal() { document.getElementById('deleteAllModal').classList.add('hidden'); }

    document.querySelectorAll('#deleteModal, #deleteAllModal').forEach(modal => {
        modal.addEventListener('click', function(e) { if (e.target === this) this.classList.add('hidden'); });
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') document.querySelectorAll('#deleteModal, #deleteAllModal').forEach(m => m.classList.add('hidden'));
    });

    // Auto-dismiss success message
    const successMsg = document.getElementById('successMsg');
    if (successMsg) {
        setTimeout(() => {
            successMsg.style.transition = 'opacity 0.5s ease';
            successMsg.style.opacity = '0';
            setTimeout(() => successMsg.remove(), 500);
        }, 4000);
    }

    <?php if ($filter === 'all' && $type === 'all'): ?>
    setTimeout(() => window.location.reload(), 60000);
    <?php endif; ?>
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
