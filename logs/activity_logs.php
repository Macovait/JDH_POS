<?php
/**
 * Activity Logs page for Jakababa POS
 * 
 * Complete CRUD operations for system activity logs
 * Create: Automatically created by system actions
 * Read: View logs with filters and search
 * Update: Add notes, mark as reviewed, soft delete
 * Delete: Permanent deletion with confirmation
 */

require_once __DIR__ . '/../src/auth.php';
require_login();

// Check for activity view permission
if (!check_permission('activity.view') && !is_super_admin()) {
    enforce_permission('activity.view');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';

$pdo = get_db_connection();

// Get current user info for navbar
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$branch_id = (int) ($_SESSION['user']['branch_id'] ?? 1);

// Handle CRUD Operations
// ======================

// Handle log deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    try {
        if ($_POST['action'] === 'delete_log') {
            $log_id = (int) ($_POST['log_id'] ?? 0);

            // Check permission for deletion
            if (!check_permission('activity.delete') && !is_super_admin()) {
                throw new Exception('You do not have permission to delete logs');
            }

            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id = ?");
            $stmt->execute([$log_id]);

            // Log this deletion using the correct function signature
            log_activity($user_id, 'activity.deleted', ['log_id' => $log_id]);

            $response = ['success' => true, 'message' => 'Log deleted successfully'];

        } elseif ($_POST['action'] === 'bulk_delete') {
            $log_ids = $_POST['log_ids'] ?? [];

            if (!check_permission('activity.delete') && !is_super_admin()) {
                throw new Exception('You do not have permission to delete logs');
            }

            if (empty($log_ids)) {
                throw new Exception('No logs selected');
            }

            $placeholders = implode(',', array_fill(0, count($log_ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
            $stmt->execute($log_ids);

            // Log bulk deletion
            log_activity($user_id, 'activity.bulk_deleted', ['count' => count($log_ids)]);

            $response = ['success' => true, 'message' => count($log_ids) . ' logs deleted successfully'];

        } elseif ($_POST['action'] === 'update_notes') {
            $log_id = (int) ($_POST['log_id'] ?? 0);
            $notes = $_POST['notes'] ?? '';

            if (!check_permission('activity.update') && !is_super_admin()) {
                throw new Exception('You do not have permission to update logs');
            }

            $stmt = $pdo->prepare("UPDATE activity_logs SET notes = ? WHERE id = ?");
            $stmt->execute([$notes, $log_id]);

            $response = ['success' => true, 'message' => 'Notes updated successfully'];

        } elseif ($_POST['action'] === 'mark_reviewed') {
            $log_id = (int) ($_POST['log_id'] ?? 0);

            if (!check_permission('activity.update') && !is_super_admin()) {
                throw new Exception('You do not have permission to update logs');
            }

            $stmt = $pdo->prepare("UPDATE activity_logs SET reviewed = 1, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $log_id]);

            $response = ['success' => true, 'message' => 'Log marked as reviewed'];

        } elseif ($_POST['action'] === 'archive_old') {
            $days = (int) ($_POST['days'] ?? 30);

            if (!check_permission('activity.delete') && !is_super_admin()) {
                throw new Exception('You do not have permission to archive logs');
            }

            // Check if archive table exists
            try {
                $pdo->query("SELECT 1 FROM activity_logs_archive LIMIT 1");
            } catch (PDOException $e) {
                // Create archive table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS activity_logs_archive LIKE activity_logs
                ");
            }

            $pdo->beginTransaction();

            // Copy to archive
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs_archive 
                SELECT * FROM activity_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $archived = $stmt->rowCount();

            // Delete from main table
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);

            $pdo->commit();

            log_activity($user_id, 'activity.archived', ['days' => $days, 'archived_count' => $archived]);

            $response = ['success' => true, 'message' => $archived . ' logs archived successfully'];
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response = ['success' => false, 'message' => $e->getMessage()];
        error_log("Activity logs error: " . $e->getMessage());
    }

    // Return JSON for AJAX requests
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Regular form submission
    $_SESSION['flash_message'] = $response['message'];
    $_SESSION['flash_type'] = $response['success'] ? 'success' : 'error';
    header('Location: activity_logs.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Get filter parameters
$action_filter = $_GET['action'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$limit = intval($_GET['limit'] ?? 100);
$show_archived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
$reviewed_filter = $_GET['reviewed'] ?? 'all';

// Validate dates
$date_from = date('Y-m-d', strtotime($date_from));
$date_to = date('Y-m-d', strtotime($date_to));

// Get all unique actions for filter dropdown
$actions = [];
try {
    $actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching actions: " . $e->getMessage());
}

// Get all users for filter dropdown
$users = [];
try {
    $users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

// Build query
$table = $show_archived ? 'activity_logs_archive' : 'activity_logs';

$sql = "
    SELECT 
        al.id,
        al.user_id,
        al.action,
        al.description,
        al.meta,
        al.notes,
        al.reviewed,
        al.reviewed_by,
        al.reviewed_at,
        al.ip_address,
        al.created_at,
        u.name as user_name,
        ru.name as reviewer_name
    FROM $table al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN users ru ON al.reviewed_by = ru.id
    WHERE DATE(al.created_at) BETWEEN :date_from AND :date_to
";

$params = [
    ':date_from' => $date_from,
    ':date_to' => $date_to
];

if ($action_filter !== 'all') {
    $sql .= " AND al.action = :action";
    $params[':action'] = $action_filter;
}

if ($user_filter !== 'all') {
    $sql .= " AND al.user_id = :user_id";
    $params[':user_id'] = $user_filter;
}

if ($reviewed_filter !== 'all') {
    $sql .= " AND al.reviewed = :reviewed";
    $params[':reviewed'] = ($reviewed_filter === 'reviewed') ? 1 : 0;
}

if (!empty($search)) {
    $sql .= " AND (al.meta LIKE :search OR al.action LIKE :search OR al.notes LIKE :search OR al.description LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY al.id DESC LIMIT :limit";
$params[':limit'] = $limit;

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    if ($key === ':limit') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$logs = $stmt->fetchAll();

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT DATE(created_at)) as active_days,
        COUNT(DISTINCT action) as unique_actions,
        COUNT(DISTINCT user_id) as active_users,
        SUM(CASE WHEN reviewed = 0 THEN 1 ELSE 0 END) as unreviewed_count
    FROM $table
    WHERE DATE(created_at) BETWEEN :date_from AND :date_to
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([
    ':date_from' => $date_from,
    ':date_to' => $date_to
]);
$stats = $stmt->fetch();

// Default stats if null
if (!$stats) {
    $stats = [
        'total_logs' => 0,
        'active_days' => 0,
        'unique_actions' => 0,
        'active_users' => 0,
        'unreviewed_count' => 0
    ];
}

// Get activity timeline for chart
$timeline_sql = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM $table
    WHERE DATE(created_at) BETWEEN :date_from AND :date_to
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$stmt = $pdo->prepare($timeline_sql);
$stmt->execute([
    ':date_from' => $date_from,
    ':date_to' => $date_to
]);
$timeline = $stmt->fetchAll();

// Parse meta data for display
foreach ($logs as &$log) {
    if (!empty($log['meta'])) {
        $decoded = json_decode($log['meta'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $log['meta_parsed'] = $decoded;
        } else {
            $log['meta_parsed'] = $log['meta'];
        }
    } else {
        $log['meta_parsed'] = null;
    }
}

// Get action colors for icons
$action_colors = [
    'auth.login' => 'success',
    'auth.login_failed' => 'warning',
    'auth.logout' => 'info',
    'user.created' => 'success',
    'user.updated' => 'accent',
    'user.deleted' => 'warning',
    'customer.created' => 'success',
    'customer.updated' => 'accent',
    'customer.deleted' => 'warning',
    'product.created' => 'success',
    'product.updated' => 'accent',
    'product.deleted' => 'warning',
    'sale.completed' => 'success',
    'sale.draft_created' => 'info',
    'sale.refunded' => 'warning',
    'inventory.updated' => 'info',
    'inventory.adjusted' => 'accent',
    'branch.created' => 'success',
    'branch.updated' => 'accent',
    'branch.deleted' => 'warning',
    'supplier.created' => 'success',
    'supplier.updated' => 'accent',
    'supplier.deleted' => 'warning',
    'voucher.created' => 'success',
    'voucher.updated' => 'accent',
    'voucher.deleted' => 'warning',
    'discount.created' => 'success',
    'discount.updated' => 'accent',
    'discount.deleted' => 'warning',
    'permission.denied' => 'warning',
    'database.backup' => 'info',
    'database.restore' => 'warning',
    'activity.deleted' => 'warning',
    'activity.bulk_deleted' => 'warning',
    'activity.archived' => 'info'
];

$page_title = 'Activity Logs | Jakababa POS';
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Jakababa POS - Activity Logs">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">

    <!-- Chart.js for visualizations -->
    <script src="<?php echo base_url('assets/js/chart.js.min.js'); ?>"></script>

    <!-- Tailwind -->
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1E3A8A',
                        'primary-dark': '#0F2B5E',
                        accent: '#FBBF24',
                        'accent-dark': '#F59E0B',
                        warning: '#EF4444',
                        success: '#10B981',
                        info: '#3B82F6',
                        'card-bg': '#1F2937',
                        'card-border': '#374151',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>

    <style>
        /* Page enter animation */
        .page-enter {
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Focus styles */
        *:focus-visible {
            outline: 3px solid #FBBF24;
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* Table row hover */
        .table-row-hover:hover {
            background: rgba(251, 191, 36, 0.05);
        }

        /* Role badge */
        .role-badge {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        /* Action badge */
        .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .action-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .action-warning {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .action-info {
            background: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .action-accent {
            background: rgba(251, 191, 36, 0.1);
            color: #FBBF24;
            border: 1px solid rgba(251, 191, 36, 0.2);
        }

        /* Meta data styling */
        .meta-json {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            color: #9CA3AF;
            max-width: 300px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Fixed chart container */
        .chart-container {
            position: relative;
            height: 200px !important;
            width: 100%;
            max-height: 200px;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1F2937;
        }

        ::-webkit-scrollbar-thumb {
            background: #4B5563;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6B7280;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #1F2937;
            border: 1px solid #374151;
            border-radius: 1.5rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease-out;
        }

        /* Selection checkbox */
        .selection-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #FBBF24;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-primary to-primary-dark min-h-screen page-enter font-sans antialiased">

    <!-- Flash Message -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div id="flashMessage"
            class="fixed top-4 right-4 z-50 px-4 py-3 rounded-xl shadow-lg animate-slide-up <?php echo $_SESSION['flash_type'] === 'success' ? 'bg-success text-white' : 'bg-warning text-white'; ?>"
            style="z-index: 10000;">
            <div class="flex items-center gap-2">
                <i
                    class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($_SESSION['flash_message']); ?></span>
            </div>
        </div>
        <?php
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        ?>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content p-6">
            <div class="flex items-center gap-3 text-warning mb-4">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
                <h3 class="text-xl font-bold text-white">Delete Log</h3>
            </div>
            <p class="text-gray-300 mb-6">Are you sure you want to delete this log? This action cannot be undone.</p>
            <div class="flex justify-end gap-3">
                <button onclick="closeDeleteModal()"
                    class="px-4 py-2 bg-card-bg border border-card-border rounded-xl hover:border-accent transition text-white">
                    Cancel
                </button>
                <button id="confirmDeleteBtn"
                    class="px-4 py-2 bg-warning text-white rounded-xl hover:bg-warning-dark transition">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div id="bulkDeleteModal" class="modal-overlay">
        <div class="modal-content p-6">
            <div class="flex items-center gap-3 text-warning mb-4">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
                <h3 class="text-xl font-bold text-white">Delete Selected Logs</h3>
            </div>
            <p class="text-gray-300 mb-2">Are you sure you want to delete <span id="selectedCount">0</span> selected
                logs?</p>
            <p class="text-sm text-gray-400 mb-6">This action cannot be undone.</p>
            <div class="flex justify-end gap-3">
                <button onclick="closeBulkDeleteModal()"
                    class="px-4 py-2 bg-card-bg border border-card-border rounded-xl hover:border-accent transition text-white">
                    Cancel
                </button>
                <button id="confirmBulkDeleteBtn"
                    class="px-4 py-2 bg-warning text-white rounded-xl hover:bg-warning-dark transition">
                    Delete All
                </button>
            </div>
        </div>
    </div>

    <!-- Archive Modal -->
    <div id="archiveModal" class="modal-overlay">
        <div class="modal-content p-6">
            <div class="flex items-center gap-3 text-info mb-4">
                <i class="fas fa-archive text-2xl"></i>
                <h3 class="text-xl font-bold text-white">Archive Old Logs</h3>
            </div>
            <p class="text-gray-300 mb-4">Archive logs older than:</p>
            <select id="archiveDays"
                class="w-full px-3 py-2 rounded-xl bg-primary-dark border border-card-border text-white mb-4">
                <option value="30">30 days</option>
                <option value="60">60 days</option>
                <option value="90">90 days</option>
                <option value="180">180 days</option>
                <option value="365">1 year</option>
            </select>
            <p class="text-sm text-gray-400 mb-6">Archived logs will be moved to the archive table and removed from main
                view.</p>
            <div class="flex justify-end gap-3">
                <button onclick="closeArchiveModal()"
                    class="px-4 py-2 bg-card-bg border border-card-border rounded-xl hover:border-accent transition text-white">
                    Cancel
                </button>
                <button onclick="archiveOldLogs()"
                    class="px-4 py-2 bg-info text-white rounded-xl hover:bg-info-dark transition">
                    Archive
                </button>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div id="notesModal" class="modal-overlay">
        <div class="modal-content p-6">
            <div class="flex items-center gap-3 text-accent mb-4">
                <i class="fas fa-pen-to-square text-2xl"></i>
                <h3 class="text-xl font-bold text-white">Add Notes</h3>
            </div>
            <textarea id="logNotes" rows="4"
                class="w-full px-3 py-2 rounded-xl bg-primary-dark border border-card-border text-white mb-4"
                placeholder="Enter notes about this log..."></textarea>
            <input type="hidden" id="currentLogId">
            <div class="flex justify-end gap-3">
                <button onclick="closeNotesModal()"
                    class="px-4 py-2 bg-card-bg border border-card-border rounded-xl hover:border-accent transition text-white">
                    Cancel
                </button>
                <button onclick="saveNotes()"
                    class="px-4 py-2 bg-accent text-black rounded-xl hover:bg-accent-dark transition">
                    Save Notes
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Bar -->
    <nav class="bg-card-bg/80 backdrop-blur-sm border-b border-card-border sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-4">
                    <a href="index.php" class="flex items-center gap-2 text-white hover:text-accent transition-colors">
                        <i class="fas fa-arrow-left"></i>
                        <span class="hidden sm:inline">Dashboard</span>
                    </a>
                    <div class="h-4 w-px bg-card-border"></div>
                    <div class="flex items-center gap-2">
                        <i class="fas fa-clock text-accent"></i>
                        <h1 class="text-xl font-semibold text-white">Activity Logs</h1>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Branch info -->
                    <span class="text-sm text-gray-400 flex items-center gap-1">
                        <i class="fas fa-store"></i>
                        Branch #<?php echo $branch_id; ?>
                    </span>

                    <!-- User role badge -->
                    <span class="role-badge text-sm px-3 py-1 rounded-full flex items-center gap-1 text-accent">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($user_role); ?>
                    </span>

                    <!-- Logout -->
                    <a href="logout.php" class="text-gray-400 hover:text-warning transition-colors">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-white">Activity Logs</h1>
                <p class="text-gray-400 mt-1">Monitor system activity and user actions</p>
            </div>

            <div class="flex gap-2">
                <!-- Archive Toggle -->
                <a href="?<?php echo http_build_query(array_merge($_GET, ['show_archived' => $show_archived ? '0' : '1'])); ?>"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-card-bg/50 rounded-xl border border-card-border text-gray-300 hover:text-white hover:border-accent transition-all">
                    <i class="fas fa-<?php echo $show_archived ? 'clock' : 'archive'; ?>"></i>
                    <span class="text-sm"><?php echo $show_archived ? 'Show Current' : 'Show Archived'; ?></span>
                </a>

                <!-- Archive Old Logs (Admin only) -->
                <?php if (check_permission('activity.delete') || is_super_admin()): ?>
                    <button onclick="openArchiveModal()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-card-bg/50 rounded-xl border border-card-border text-gray-300 hover:text-white hover:border-accent transition-all">
                        <i class="fas fa-box-archive"></i>
                        <span class="text-sm">Archive Old</span>
                    </button>
                <?php endif; ?>

                <!-- Bulk Actions (visible when logs are selected) -->
                <div id="bulkActions" class="hidden items-center gap-2">
                    <button onclick="selectAllLogs()"
                        class="px-3 py-2 bg-card-bg/50 rounded-xl border border-card-border text-gray-300 hover:text-white transition-all">
                        <i class="fas fa-check-double"></i>
                    </button>
                    <button onclick="openBulkDeleteModal()"
                        class="px-3 py-2 bg-warning/20 text-warning rounded-xl border border-warning/30 hover:bg-warning/30 transition-all">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                <!-- Export Button -->
                <button onclick="exportLogs()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-card-bg/50 rounded-xl border border-card-border text-gray-300 hover:text-white hover:border-accent transition-all">
                    <i class="fas fa-download"></i>
                    <span class="text-sm">Export</span>
                </button>

                <!-- Refresh Button -->
                <button onclick="window.location.reload()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-card-bg/50 rounded-xl border border-card-border text-gray-300 hover:text-white hover:border-accent transition-all">
                    <i class="fas fa-sync-alt"></i>
                    <span class="text-sm">Refresh</span>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8 animate-slide-up">
            <div class="bg-card-bg/30 backdrop-blur-sm rounded-xl border border-card-border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Logs</p>
                        <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_logs']); ?></p>
                    </div>
                    <div class="p-3 bg-accent/10 rounded-xl">
                        <i class="fas fa-file-lines text-accent text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-card-bg/30 backdrop-blur-sm rounded-xl border border-card-border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Active Days</p>
                        <p class="text-2xl font-bold text-success"><?php echo $stats['active_days']; ?></p>
                    </div>
                    <div class="p-3 bg-success/10 rounded-xl">
                        <i class="fas fa-calendar text-success text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-card-bg/30 backdrop-blur-sm rounded-xl border border-card-border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Unique Actions</p>
                        <p class="text-2xl font-bold text-accent"><?php echo $stats['unique_actions']; ?></p>
                    </div>
                    <div class="p-3 bg-accent/10 rounded-xl">
                        <i class="fas fa-bolt text-accent text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-card-bg/30 backdrop-blur-sm rounded-xl border border-card-border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Active Users</p>
                        <p class="text-2xl font-bold text-info"><?php echo $stats['active_users']; ?></p>
                    </div>
                    <div class="p-3 bg-info/10 rounded-xl">
                        <i class="fas fa-users text-info text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-card-bg/30 backdrop-blur-sm rounded-xl border border-card-border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Unreviewed</p>
                        <p class="text-2xl font-bold text-warning"><?php echo $stats['unreviewed_count']; ?></p>
                    </div>
                    <div class="p-3 bg-warning/10 rounded-xl">
                        <i class="fas fa-eye-slash text-warning text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-card-bg/30 backdrop-blur-sm rounded-2xl border border-card-border p-4 mb-6 animate-slide-up">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-7 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                            class="flex-1 px-3 py-2 rounded-xl bg-card-bg border border-card-border text-white focus:border-accent outline-none">
                        <span class="text-gray-500 self-center">to</span>
                        <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                            class="flex-1 px-3 py-2 rounded-xl bg-card-bg border border-card-border text-white focus:border-accent outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Action</label>
                    <select name="action"
                        class="w-full px-3 py-2 rounded-xl bg-card-bg border border-card-border text-white focus:border-accent outline-none">
                        <option value="all">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">User</label>
                    <select name="user"
                        class="w-full px-3 py-2 rounded-xl bg-card-bg border border-card-border text-white focus:border-accent outline-none">
                        <option value="all">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Status</label>
                    <select name="reviewed"
                        class="w-full px-3 py-2 rounded-xl bg-card-bg border border-card-border text-white focus:border-accent outline-none">
                        <option value="all" <?php echo $reviewed_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="unreviewed" <?php echo $reviewed_filter === 'unreviewed' ? 'selected' : ''; ?>>
                            Unreviewed</option>
                        <option value="reviewed" <?php echo $reviewed_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed
                        </option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Search</label>
                    <div class="flex gap-2">
                        <div class="flex-1 relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search in meta or notes..."
                                class="w-full pl-9 pr-3 py-2 rounded-xl bg-card-bg border border-card-border text-white placeholder-gray-500 focus:border-accent outline-none">
                        </div>
                        <select name="limit"
                            class="w-24 px-3 py-2 rounded-xl bg-card-bg border border-card-border text-white focus:border-accent outline-none">
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="250" <?php echo $limit == 250 ? 'selected' : ''; ?>>250</option>
                            <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                        </select>
                    </div>
                </div>

                <!-- Preserve show_archived parameter -->
                <input type="hidden" name="show_archived" value="<?php echo $show_archived ? '1' : '0'; ?>">

                <div class="flex items-end gap-2 md:col-span-7">
                    <button type="submit"
                        class="px-6 py-2 bg-accent text-black rounded-xl font-semibold hover:bg-accent-dark transition-colors flex items-center gap-2">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>

                    <?php if ($action_filter !== 'all' || $user_filter !== 'all' || !empty($search) || $date_from !== date('Y-m-d', strtotime('-7 days')) || $date_to !== date('Y-m-d') || $limit !== 100 || $reviewed_filter !== 'all'): ?>
                        <a href="activity_logs.php<?php echo $show_archived ? '?show_archived=1' : ''; ?>"
                            class="px-4 py-2 bg-card-bg border border-card-border rounded-xl text-gray-400 hover:text-white hover:border-accent transition-colors flex items-center gap-2">
                            <i class="fas fa-times"></i>
                            Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Activity Chart -->
        <?php if (!empty($timeline)): ?>
            <div class="bg-card-bg/30 backdrop-blur-sm rounded-2xl border border-card-border p-4 mb-6 animate-slide-up">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-chart-bar text-accent"></i>
                        Activity Timeline
                    </h2>
                    <span class="text-xs text-gray-500">Last <?php echo count($timeline); ?> days</span>
                </div>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <!-- Logs Table -->
        <div
            class="bg-card-bg/30 backdrop-blur-xl rounded-3xl border border-card-border overflow-hidden animate-slide-up">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-card-bg/50 border-b border-card-border">
                        <tr>
                            <th class="px-4 py-4">
                                <input type="checkbox" id="selectAllCheckbox" class="selection-checkbox"
                                    onchange="toggleSelectAll(this)">
                            </th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                ID</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Timestamp</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                User</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                IP Address</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Action</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Details</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Status</th>
                            <th class="px-4 py-4 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-card-border">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-clock text-4xl text-gray-600"></i>
                                        <p class="text-lg">No activity logs found</p>
                                        <p class="text-sm">Try adjusting your filter criteria</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $action_parts = explode('.', $log['action']);
                                $action_category = $action_parts[0] ?? '';
                                $action_type = $action_parts[1] ?? '';

                                $action_color = $action_colors[$log['action']] ?? 'info';

                                // Determine icon based on action type
                                $icon = 'clock';
                                if (strpos($log['action'], 'created') !== false || strpos($log['action'], 'added') !== false)
                                    $icon = 'plus-circle';
                                elseif (strpos($log['action'], 'updated') !== false)
                                    $icon = 'pencil';
                                elseif (strpos($log['action'], 'deleted') !== false)
                                    $icon = 'trash';
                                elseif (strpos($log['action'], 'login') !== false) {
                                    if (strpos($log['action'], 'failed') !== false)
                                        $icon = 'exclamation-circle';
                                    else
                                        $icon = 'sign-in-alt';
                                } elseif (strpos($log['action'], 'logout') !== false)
                                    $icon = 'sign-out-alt';
                                elseif (strpos($log['action'], 'permission') !== false)
                                    $icon = 'lock';
                                elseif (strpos($log['action'], 'database') !== false)
                                    $icon = 'database';
                                ?>
                                <tr class="table-row-hover transition-colors" id="log-row-<?php echo $log['id']; ?>">
                                    <td class="px-4 py-4">
                                        <input type="checkbox" class="log-checkbox selection-checkbox"
                                            value="<?php echo $log['id']; ?>" onchange="updateBulkActions()">
                                    </td>
                                    <td class="px-4 py-4 font-mono text-xs text-gray-500">#<?php echo $log['id']; ?></td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col">
                                            <span
                                                class="text-white"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></span>
                                            <span
                                                class="text-xs text-gray-500"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if ($log['user_name']): ?>
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-accent/10 flex items-center justify-center">
                                                    <span class="text-accent text-xs font-semibold">
                                                        <?php echo strtoupper(substr($log['user_name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <span class="text-white"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500 italic">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span
                                            class="text-xs text-gray-400"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="action-badge action-<?php echo $action_color; ?>">
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 max-w-xs">
                                        <?php if (!empty($log['description'])): ?>
                                            <div class="text-xs text-gray-300 mb-1">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($log['meta_parsed']): ?>
                                            <?php if (is_array($log['meta_parsed'])): ?>
                                                <div class="space-y-1">
                                                    <?php
                                                    $display_count = 0;
                                                    foreach ($log['meta_parsed'] as $key => $value):
                                                        if (!is_array($value) && !is_object($value) && $display_count < 2):
                                                            $display_count++;
                                                            ?>
                                                            <div class="text-xs">
                                                                <span class="text-gray-500"><?php echo htmlspecialchars($key); ?>:</span>
                                                                <span class="text-gray-300 ml-1"><?php echo htmlspecialchars($value); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php if (count($log['meta_parsed']) > 2): ?>
                                                    <button onclick="toggleMetaDetails(<?php echo $log['id']; ?>)"
                                                        class="text-xs text-accent hover:text-accent-dark mt-1">
                                                        Show all <?php echo count($log['meta_parsed']); ?> fields
                                                    </button>
                                                <?php endif; ?>
                                                <div id="meta-<?php echo $log['id']; ?>" class="hidden mt-2">
                                                    <pre
                                                        class="meta-json"><?php echo htmlspecialchars(json_encode($log['meta_parsed'], JSON_PRETTY_PRINT)); ?></pre>
                                                </div>
                                            <?php else: ?>
                                                <span class="meta-json"><?php echo htmlspecialchars($log['meta_parsed']); ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (!empty($log['notes'])): ?>
                                            <div class="mt-2 text-xs text-gray-400 border-t border-card-border pt-1">
                                                <i class="fas fa-sticky-note text-accent mr-1"></i>
                                                <?php echo htmlspecialchars($log['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <?php if ($log['reviewed']): ?>
                                            <span class="text-xs text-success flex items-center gap-1"
                                                title="Reviewed by <?php echo htmlspecialchars($log['reviewer_name'] ?? 'Unknown'); ?> on <?php echo date('M j, H:i', strtotime($log['reviewed_at'])); ?>">
                                                <i class="fas fa-check-circle"></i>
                                                Reviewed
                                            </span>
                                        <?php else: ?>
                                            <button onclick="markAsReviewed(<?php echo $log['id']; ?>)"
                                                class="text-xs text-gray-400 hover:text-accent transition flex items-center gap-1">
                                                <i class="fas fa-eye"></i>
                                                Mark Reviewed
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <button
                                                onclick="openNotesModal(<?php echo $log['id']; ?>, '<?php echo htmlspecialchars(addslashes($log['notes'] ?? '')); ?>')"
                                                class="text-gray-400 hover:text-accent transition" title="Add Notes">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <?php if (check_permission('activity.delete') || is_super_admin()): ?>
                                                <button onclick="openDeleteModal(<?php echo $log['id']; ?>)"
                                                    class="text-gray-400 hover:text-warning transition" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <?php if (!empty($logs)): ?>
                <div
                    class="px-6 py-4 bg-card-bg/50 border-t border-card-border flex justify-between items-center text-sm text-gray-400">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-file-lines"></i>
                        <span>Showing <span class="text-white font-semibold"
                                id="visibleCount"><?php echo count($logs); ?></span> of <span
                                class="text-white font-semibold"><?php echo number_format($stats['total_logs']); ?></span>
                            logs</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-success rounded-full"></span>
                            Success
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-warning rounded-full"></span>
                            Warning
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-info rounded-full"></span>
                            Info
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed bottom-4 right-4 space-y-2 z-50"></div>

    <!-- Connection Status Indicator -->
    <div id="connection-status"
        class="fixed bottom-4 left-4 text-xs text-success flex items-center gap-1 bg-card-bg/80 backdrop-blur-sm px-3 py-2 rounded-full border border-card-border">
        <i class="fas fa-wifi"></i>
        <span>Online</span>
    </div>

    <script>
        // Initialize activity chart
        <?php if (!empty($timeline)): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('activityChart').getContext('2d');

                const labels = <?php echo json_encode(array_column($timeline, 'date')); ?>;
                const data = <?php echo json_encode(array_column($timeline, 'count')); ?>;

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Activity Count',
                            data: data,
                            borderColor: '#FBBF24',
                            backgroundColor: 'rgba(251, 191, 36, 0.1)',
                            borderWidth: 2,
                            pointBackgroundColor: '#FBBF24',
                            pointBorderColor: '#1F2937',
                            pointBorderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1F2937',
                                titleColor: '#FBBF24',
                                bodyColor: '#9CA3AF',
                                borderColor: '#374151',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(255, 255, 255, 0.1)', drawBorder: false },
                                ticks: { color: '#9CA3AF', stepSize: 1, font: { size: 10 } }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#9CA3AF', maxRotation: 45, minRotation: 45, font: { size: 10 } }
                            }
                        }
                    }
                });
            });
        <?php endif; ?>

        // CRUD Operations
        // ===============

        // Delete operations
        let deleteLogId = null;

        function openDeleteModal(logId) {
            deleteLogId = logId;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteLogId = null;
        }

        document.getElementById('confirmDeleteBtn')?.addEventListener('click', function () {
            if (deleteLogId) {
                deleteLog(deleteLogId);
            }
        });

        function deleteLog(logId) {
            const formData = new FormData();
            formData.append('action', 'delete_log');
            formData.append('log_id', logId);

            fetch('activity_logs.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`log-row-${logId}`)?.remove();
                        showToast(data.message, 'success');
                        closeDeleteModal();
                        updateVisibleCount();
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error deleting log', 'error');
                    console.error('Error:', error);
                });
        }

        // Bulk delete
        let selectedLogs = new Set();

        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.log-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                if (checkbox.checked) {
                    selectedLogs.add(cb.value);
                } else {
                    selectedLogs.delete(cb.value);
                }
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.log-checkbox');
            const bulkActions = document.getElementById('bulkActions');
            const selectAll = document.getElementById('selectAllCheckbox');

            selectedLogs.clear();
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedLogs.add(cb.value);
                }
            });

            if (selectedLogs.size > 0) {
                bulkActions.classList.remove('hidden');
                bulkActions.classList.add('flex');
            } else {
                bulkActions.classList.add('hidden');
                bulkActions.classList.remove('flex');
            }

            if (selectAll) {
                selectAll.checked = checkboxes.length > 0 && selectedLogs.size === checkboxes.length;
            }
        }

        function selectAllLogs() {
            const checkboxes = document.querySelectorAll('.log-checkbox');
            const selectAll = document.getElementById('selectAllCheckbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
                if (!allChecked) {
                    selectedLogs.add(cb.value);
                } else {
                    selectedLogs.delete(cb.value);
                }
            });

            if (selectAll) {
                selectAll.checked = !allChecked;
            }

            updateBulkActions();
        }

        function openBulkDeleteModal() {
            document.getElementById('selectedCount').textContent = selectedLogs.size;
            document.getElementById('bulkDeleteModal').classList.add('active');
        }

        function closeBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').classList.remove('active');
        }

        document.getElementById('confirmBulkDeleteBtn')?.addEventListener('click', function () {
            if (selectedLogs.size > 0) {
                bulkDeleteLogs(Array.from(selectedLogs));
            }
        });

        function bulkDeleteLogs(logIds) {
            const formData = new FormData();
            formData.append('action', 'bulk_delete');
            logIds.forEach(id => formData.append('log_ids[]', id));

            fetch('activity_logs.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        logIds.forEach(id => {
                            document.getElementById(`log-row-${id}`)?.remove();
                        });
                        showToast(data.message, 'success');
                        closeBulkDeleteModal();
                        selectedLogs.clear();
                        updateBulkActions();
                        updateVisibleCount();
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error deleting logs', 'error');
                    console.error('Error:', error);
                });
        }

        // Notes operations
        function openNotesModal(logId, currentNotes) {
            document.getElementById('currentLogId').value = logId;
            document.getElementById('logNotes').value = currentNotes || '';
            document.getElementById('notesModal').classList.add('active');
        }

        function closeNotesModal() {
            document.getElementById('notesModal').classList.remove('active');
            document.getElementById('currentLogId').value = '';
            document.getElementById('logNotes').value = '';
        }

        function saveNotes() {
            const logId = document.getElementById('currentLogId').value;
            const notes = document.getElementById('logNotes').value;

            const formData = new FormData();
            formData.append('action', 'update_notes');
            formData.append('log_id', logId);
            formData.append('notes', notes);

            fetch('activity_logs.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeNotesModal();
                        // Reload to show updated notes
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error saving notes', 'error');
                    console.error('Error:', error);
                });
        }

        // Mark as reviewed
        function markAsReviewed(logId) {
            const formData = new FormData();
            formData.append('action', 'mark_reviewed');
            formData.append('log_id', logId);

            fetch('activity_logs.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error marking as reviewed', 'error');
                    console.error('Error:', error);
                });
        }

        // Archive operations
        function openArchiveModal() {
            document.getElementById('archiveModal').classList.add('active');
        }

        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.remove('active');
        }

        function archiveOldLogs() {
            const days = document.getElementById('archiveDays').value;

            const formData = new FormData();
            formData.append('action', 'archive_old');
            formData.append('days', days);

            fetch('activity_logs.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeArchiveModal();
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('Error archiving logs', 'error');
                    console.error('Error:', error);
                });
        }

        // Toggle meta details
        function toggleMetaDetails(id) {
            const element = document.getElementById('meta-' + id);
            if (element) {
                element.classList.toggle('hidden');
            }
        }

        // Update visible count
        function updateVisibleCount() {
            const visibleRows = document.querySelectorAll('tbody tr').length;
            document.getElementById('visibleCount').textContent = visibleRows;
        }

        // Export logs
        function exportLogs() {
            const rows = [];
            const table = document.querySelector('table');
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            rows.push(headers.join(','));

            table.querySelectorAll('tbody tr').forEach(row => {
                const cols = Array.from(row.querySelectorAll('td')).map(td => {
                    let text = td.textContent.trim().replace(/,/g, ';').replace(/\n/g, ' ');
                    return `"${text}"`;
                });
                rows.push(cols.join(','));
            });

            const csv = rows.join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'activity_logs_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);

            showToast('Logs exported successfully', 'success');
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');

            const colors = {
                success: 'bg-success text-white',
                error: 'bg-warning text-white',
                info: 'bg-accent text-black',
                warning: 'bg-warning text-white'
            };

            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                info: 'info-circle',
                warning: 'exclamation-triangle'
            };

            toast.className = `flex items-center gap-2 ${colors[type]} px-4 py-3 rounded-xl shadow-lg animate-slide-up`;
            toast.innerHTML = `
                <i class="fas fa-${icons[type]}"></i>
                <span>${message}</span>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Flash message auto-hide
        document.addEventListener('DOMContentLoaded', function () {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                setTimeout(() => {
                    flashMessage.style.opacity = '0';
                    flashMessage.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => flashMessage.remove(), 300);
                }, 3000);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            if (e.target.matches('input, textarea, select')) return;

            // Alt + R - Refresh
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }

            // Alt + E - Export
            if (e.altKey && e.key === 'e') {
                e.preventDefault();
                exportLogs();
            }

            // Alt + A - Archive (if admin)
            <?php if (check_permission('activity.delete') || is_super_admin()): ?>
                if (e.altKey && e.key === 'a') {
                    e.preventDefault();
                    openArchiveModal();
                }
            <?php endif; ?>

            // Alt + D - Dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'index.php';
            }

            // Escape key - close modals
            if (e.key === 'Escape') {
                closeDeleteModal();
                closeBulkDeleteModal();
                closeArchiveModal();
                closeNotesModal();
            }
        });

        // Connection status
        function updateOnlineStatus() {
            const statusElement = document.getElementById('connection-status');
            if (statusElement) {
                if (navigator.onLine) {
                    statusElement.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                    statusElement.className = 'fixed bottom-4 left-4 text-xs text-success flex items-center gap-1 bg-card-bg/80 backdrop-blur-sm px-3 py-2 rounded-full border border-card-border';
                } else {
                    statusElement.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>';
                    statusElement.className = 'fixed bottom-4 left-4 text-xs text-warning flex items-center gap-1 bg-card-bg/80 backdrop-blur-sm px-3 py-2 rounded-full border border-card-border';
                }
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Handle window resize for chart
        window.addEventListener('resize', function () {
            if (window.activityChart) {
                window.activityChart.resize();
            }
        });
    </script>
</body>

</html>