<?php
/**
 * Store Admin — Product Reviews
 * @version 3.0
 */
require_once __DIR__ . '/../../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = (int)($_SESSION['tenant_id'] ?? get_current_tenant_id() ?? 0);

if (!$tenant_id) {
    echo '<div class="flex items-center justify-center h-screen text-slate-400">Access Denied — no tenant context</div>';
    exit;
}

// Load store settings
$settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM storefront_settings WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$brand_color = $settings['primary_color'] ?? '#f68b1e';
$store_name = $settings['store_name'] ?? $_SESSION['company_name'] ?? 'My Store';
$blocks_count = 0;

try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM storefront_blocks WHERE tenant_id = ? AND is_active = 1");
    $st->execute([$tenant_id]);
    $blocks_count = (int)$st->fetchColumn();
} catch (Exception $e) {}

// ── Handle Review Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'], $_POST['action'])) {
    $rid = (int)$_POST['review_id'];
    $action = $_POST['action'];
    $msg = '';
    $msg_type = 'success';

    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE product_reviews SET status = 'approved', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$rid, $tenant_id]);
            $msg = 'Review approved successfully.';
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE product_reviews SET status = 'rejected', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$rid, $tenant_id]);
            $msg = 'Review rejected successfully.';
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$rid, $tenant_id]);
            $msg = 'Review deleted successfully.';
        } elseif ($action === 'bulk') {
            $ids = $_POST['review_ids'] ?? [];
            $bulk_action = $_POST['bulk_action'] ?? '';
            
            if (!empty($ids) && in_array($bulk_action, ['approve', 'reject', 'delete'])) {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                
                if ($bulk_action === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM product_reviews WHERE id IN ($placeholders) AND tenant_id = ?");
                    $params = array_merge($ids, [$tenant_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE product_reviews SET status = ?, updated_at = NOW() WHERE id IN ($placeholders) AND tenant_id = ?");
                    $params = array_merge([$bulk_action], $ids, [$tenant_id]);
                }
                $stmt->execute($params);
                $msg = count($ids) . ' review(s) ' . ($bulk_action === 'delete' ? 'deleted' : $bulk_action . 'd') . ' successfully.';
            } else {
                $msg = 'No reviews selected or invalid action.';
                $msg_type = 'error';
            }
        } else {
            $msg = 'Invalid action.';
            $msg_type = 'error';
        }
    } catch (Exception $e) {
        $msg = 'Action failed: ' . $e->getMessage();
        $msg_type = 'error';
    }

    header('Location: reviews.php?msg=' . urlencode($msg) . '&type=' . $msg_type);
    exit;
}

// ── Filters ──
$filter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "WHERE r.tenant_id = ?";
$params = [$tenant_id];

if ($filter !== 'all') {
    $where .= " AND r.status = ?";
    $params[] = $filter;
}

if ($search) {
    $where .= " AND (r.customer_name LIKE ? OR r.customer_email LIKE ? OR r.title LIKE ? OR r.comment LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// ── Load Reviews ──
$reviews = [];
$total = 0;

try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM product_reviews r $where");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT r.id, r.product_id, r.customer_name, r.customer_email, r.rating, 
                r.title, r.comment, r.status, r.created_at, 
                p.name as product_name, p.image as product_image 
         FROM product_reviews r 
         LEFT JOIN products p ON p.id = r.product_id 
         $where 
         ORDER BY r.created_at DESC 
         LIMIT $per_page OFFSET $offset"
    );
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$total_pages = (int)ceil($total / $per_page);

// ── Status Counts ──
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $r = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM product_reviews WHERE tenant_id = ? GROUP BY status");
    $r->execute([$tenant_id]);
    while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
    $counts['all'] = array_sum($counts);
} catch (Exception $e) {}

$stats = [
    'pending_orders' => 0,
    'reviews_pending' => $counts['pending'] ?? 0
];

try {
    $r = $pdo->prepare("SELECT COUNT(*) FROM online_orders WHERE tenant_id = ? AND status = 'pending'");
    $r->execute([$tenant_id]);
    $stats['pending_orders'] = (int)$r->fetchColumn();
} catch (Exception $e) {}

// ── Review Detail View ──
$viewReview = null;
if (isset($_GET['view'])) {
    try {
        $stmt = $pdo->prepare(
            "SELECT r.*, p.name as product_name, p.image as product_image 
             FROM product_reviews r 
             LEFT JOIN products p ON p.id = r.product_id 
             WHERE r.id = ? AND r.tenant_id = ?"
        );
        $stmt->execute([(int)$_GET['view'], $tenant_id]);
        $viewReview = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$storeUrl = storefront_url($tenant_id);
$page_title = 'Reviews';

// Handle messages from redirects
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = $_GET['type'] ?? 'success';
}

ob_start();
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fas fa-star text-amber-400 text-xl"></i>
                Reviews
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                <?= $counts['pending'] > 0 ? $counts['pending'] . ' pending approval' : 'Manage product reviews' ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" 
               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-white text-xs font-bold transition-all duration-200 hover:opacity-90 shadow-lg" 
               style="background: <?= $brand_color ?>">
                <i class="fas fa-eye"></i> Preview Store
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($msg)): ?>
    <div class="px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 <?= $msg_type === 'success' ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-400' : 'bg-red-500/10 border border-red-500/30 text-red-400' ?>">
        <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <?php if ($viewReview): ?>
    <!-- ============================================================ -->
    <!-- REVIEW DETAIL VIEW -->
    <!-- ============================================================ -->
    <div>
        <a href="reviews.php" class="inline-flex items-center gap-1.5 text-sm text-amber-400 font-medium hover:text-amber-300 transition-colors mb-4">
            <i class="fas fa-arrow-left text-xs"></i> Back to Reviews
        </a>

        <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($viewReview['title'] ?: 'Review #' . $viewReview['id']) ?></h2>
                    <p class="text-xs text-slate-500">
                        <?= date('F j, Y H:i', strtotime($viewReview['created_at'])) ?>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <?php
                    $statusClasses = [
                        'pending' => 'bg-amber-500/15 text-amber-400',
                        'approved' => 'bg-emerald-500/15 text-emerald-400',
                        'rejected' => 'bg-red-500/15 text-red-400'
                    ];
                    $sc = $statusClasses[$viewReview['status']] ?? 'bg-slate-500/15 text-slate-400';
                    ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium <?= $sc ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= str_replace('text-', 'bg-', $sc) ?>"></span>
                        <?= ucfirst($viewReview['status']) ?>
                    </span>
                </div>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Review Info -->
                <div class="space-y-4">
                    <div class="bg-slate-900/50 rounded-xl p-4 border border-slate-700/40">
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-2">
                            <i class="fas fa-user text-slate-600 mr-1"></i> Customer
                        </p>
                        <p class="font-semibold text-white"><?= htmlspecialchars($viewReview['customer_name'] ?: 'Guest') ?></p>
                        <p class="text-sm text-slate-400"><?= htmlspecialchars($viewReview['customer_email']) ?></p>
                    </div>

                    <div class="bg-slate-900/50 rounded-xl p-4 border border-slate-700/40">
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-2">
                            <i class="fas fa-box text-slate-600 mr-1"></i> Product
                        </p>
                        <p class="font-semibold text-white"><?= htmlspecialchars($viewReview['product_name'] ?: 'Unknown Product') ?></p>
                        <?php if ($viewReview['product_image']): ?>
                        <img src="<?= htmlspecialchars($viewReview['product_image']) ?>" class="mt-2 h-16 rounded-lg object-cover border border-slate-700/40" alt="Product image">
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Content -->
                <div class="bg-slate-900/50 rounded-xl p-4 border border-slate-700/40">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-2">
                        <i class="fas fa-star text-amber-400 mr-1"></i> Rating
                    </p>
                    <div class="flex items-center gap-0.5 text-amber-400 text-base mb-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?= $i <= (int)$viewReview['rating'] ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-2">
                        <i class="fas fa-comment text-slate-600 mr-1"></i> Comment
                    </p>
                    <p class="text-sm text-slate-300 leading-relaxed"><?= nl2br(htmlspecialchars($viewReview['comment'] ?: 'No comment provided.')) ?></p>
                </div>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 border-t border-slate-700/60 bg-slate-900/30 flex flex-wrap gap-2">
                <?php if ($viewReview['status'] === 'pending'): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="review_id" value="<?= $viewReview['id'] ?>">
                    <button type="submit" name="action" value="approve" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-500/10 text-emerald-400 text-sm font-medium hover:bg-emerald-500/20 transition-all border border-emerald-500/20">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button type="submit" name="action" value="reject" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-red-500/10 text-red-400 text-sm font-medium hover:bg-red-500/20 transition-all border border-red-500/20">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this review? This action cannot be undone.');">
                    <input type="hidden" name="review_id" value="<?= $viewReview['id'] ?>">
                    <button type="submit" name="action" value="delete" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700/50 text-slate-400 text-sm font-medium hover:bg-red-500/20 hover:text-red-400 transition-all border border-slate-700/40 hover:border-red-500/30">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ============================================================ -->
    <!-- REVIEW LIST VIEW -->
    <!-- ============================================================ -->

    <!-- Status Filters -->
    <div class="flex flex-wrap gap-1 bg-slate-800/40 border border-slate-700/60 rounded-xl p-1">
        <?php
        $statusLinks = [
            'all' => 'All',
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected'
        ];
        foreach ($statusLinks as $sv => $sl):
            $active = $filter === $sv;
        ?>
        <a href="?status=<?= $sv ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
           class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-all <?= $active ? 'bg-amber-500 text-white shadow-lg' : 'text-slate-400 hover:text-white hover:bg-slate-700/50' ?>">
            <?= $sl ?>
            <span class="ml-1 opacity-60 text-[10px]"><?= $counts[$sv] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search & Bulk Actions -->
    <div class="flex flex-col sm:flex-row gap-3">
        <form method="GET" class="flex-1 flex gap-2">
            <?php if ($filter !== 'all'): ?>
            <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
            <?php endif; ?>
            <div class="relative flex-1 max-w-md">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search by customer, product, title, or comment..."
                    class="w-full pl-9 pr-4 py-2.5 text-sm bg-slate-900/50 border border-slate-700/60 rounded-lg text-slate-200 placeholder-slate-500 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
            </div>
            <button type="submit" 
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90 shadow-lg" 
                    style="background: <?= $brand_color ?>">
                <i class="fas fa-search text-xs"></i> Search
            </button>
            <?php if ($search): ?>
            <a href="reviews.php<?= $filter !== 'all' ? '?status=' . urlencode($filter) : '' ?>" 
               class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-slate-700/50 text-slate-300 text-sm font-medium hover:bg-slate-700 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i> Clear
            </a>
            <?php endif; ?>
        </form>

        <!-- Bulk Actions -->
        <form method="POST" class="flex items-center gap-2" id="bulkForm">
            <input type="hidden" name="action" value="bulk">
            <input type="hidden" name="bulk_action" value="" id="bulkAction">
            <select name="bulk_action_select" id="bulkActionSelect" 
                    class="text-xs bg-slate-900/50 border border-slate-700/60 rounded-lg px-3 py-2.5 text-slate-200 focus:outline-none focus:border-amber-500/50 focus:ring-1 focus:ring-amber-500/20 transition-all">
                <option value="">Bulk Action</option>
                <option value="approve">Approve Selected</option>
                <option value="reject">Reject Selected</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button type="submit" 
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg bg-slate-700/60 text-slate-300 text-xs font-medium hover:bg-slate-600/60 hover:text-white transition-all border border-slate-600/40"
                    onclick="return confirmBulkAction()">
                <i class="fas fa-arrow-right text-[10px]"></i> Apply
            </button>
        </form>
    </div>

    <!-- Reviews Table -->
    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/50 text-slate-500 text-xs">
                    <tr>
                        <th class="px-4 py-3.5 w-10">
                            <input type="checkbox" id="selectAll" 
                                   class="rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20" 
                                   onchange="toggleSelectAll()">
                        </th>
                        <th class="px-4 py-3.5 text-left font-semibold">Product / Review</th>
                        <th class="px-4 py-3.5 text-center font-semibold">Rating</th>
                        <th class="px-4 py-3.5 text-center font-semibold">Status</th>
                        <th class="px-4 py-3.5 text-left font-semibold">Date</th>
                        <th class="px-4 py-3.5 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-slate-500">
                            <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-star text-2xl text-slate-600"></i>
                            </div>
                            <p class="font-medium text-slate-400">No reviews found</p>
                            <p class="text-xs mt-1"><?= $search ? 'Try adjusting your search' : 'Reviews will appear once customers leave feedback' ?></p>
                            <?php if ($search): ?>
                            <a href="reviews.php<?= $filter !== 'all' ? '?status=' . urlencode($filter) : '' ?>" 
                               class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 rounded-lg bg-amber-500/10 text-amber-400 text-xs font-medium hover:bg-amber-500/20 transition-all border border-amber-500/20">
                                <i class="fas fa-times text-[10px]"></i> Clear search
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($reviews as $rv):
                        $statusClasses = [
                            'pending' => 'bg-amber-500/15 text-amber-400',
                            'approved' => 'bg-emerald-500/15 text-emerald-400',
                            'rejected' => 'bg-red-500/15 text-red-400'
                        ];
                        $sc = $statusClasses[$rv['status']] ?? 'bg-slate-500/15 text-slate-400';
                        
                        $img = '';
                        if (!empty($rv['product_image'])) {
                            $raw = $rv['product_image'];
                            $img = str_starts_with($raw, 'http') ? $raw : (str_starts_with($raw, '/uploads/') ? $raw : '/uploads/product_images/' . basename($raw));
                        }
                    ?>
                    <tr class="hover:bg-slate-700/20 transition-colors group">
                        <td class="px-4 py-3">
                            <input type="checkbox" name="review_ids[]" value="<?= $rv['id'] ?>" 
                                   class="review-checkbox rounded border-slate-600 bg-slate-800 text-amber-500 focus:ring-amber-500/20" 
                                   onchange="updateSelectAllState()">
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-slate-700/30 rounded-lg overflow-hidden flex-shrink-0 border border-slate-700/40 flex items-center justify-center">
                                    <?php if ($img): ?>
                                    <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover" loading="lazy" alt="Product image">
                                    <?php else: ?>
                                    <i class="fas fa-box text-slate-500 text-sm"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <a href="?view=<?= $rv['id'] ?>" class="text-xs font-semibold text-white hover:text-amber-400 transition-colors">
                                        <?= htmlspecialchars($rv['product_name'] ?: 'Unknown Product') ?>
                                    </a>
                                    <p class="text-[11px] font-medium text-slate-400 truncate max-w-[200px]">
                                        <?= htmlspecialchars($rv['title'] ?: 'No title') ?>
                                    </p>
                                    <p class="text-[10px] text-slate-500 truncate max-w-[200px]">
                                        by <?= htmlspecialchars($rv['customer_name'] ?: 'Guest') ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-0.5 text-amber-400 text-xs">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i <= (int)$rv['rating'] ? '' : '-o' ?> text-[11px]"></i>
                                <?php endfor; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-medium <?= $sc ?>">
                                <span class="w-1 h-1 rounded-full <?= str_replace('text-', 'bg-', $sc) ?>"></span>
                                <?= ucfirst($rv['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-[11px]">
                            <i class="far fa-calendar-alt text-[9px] mr-1"></i>
                            <?= date('M d, Y', strtotime($rv['created_at'])) ?>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="?view=<?= $rv['id'] ?>" 
                                   class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:text-amber-400 hover:border-amber-500/30 transition-all" 
                                   title="View">
                                    <i class="fas fa-eye text-[10px]"></i>
                                </a>
                                
                                <?php if ($rv['status'] === 'pending'): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                                    <button type="submit" name="action" value="approve" 
                                            class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-emerald-400 hover:bg-emerald-500/20 hover:border-emerald-500/30 transition-all" 
                                            title="Approve">
                                        <i class="fas fa-check text-[10px]"></i>
                                    </button>
                                    <button type="submit" name="action" value="reject" 
                                            class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-red-400 hover:bg-red-500/20 hover:border-red-500/30 transition-all" 
                                            title="Reject">
                                        <i class="fas fa-times text-[10px]"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this review?')">
                                    <input type="hidden" name="review_id" value="<?= $rv['id'] ?>">
                                    <button type="submit" name="action" value="delete" 
                                            class="w-8 h-8 rounded-lg bg-slate-800/60 border border-slate-700/40 flex items-center justify-center text-slate-400 hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/30 transition-all" 
                                            title="Delete">
                                        <i class="fas fa-trash text-[10px]"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 border-t border-slate-700/60 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span class="text-xs text-slate-500">
                Showing <?= min($offset + 1, $total) ?>–<?= min($offset + $per_page, $total) ?> of <?= $total ?> reviews
            </span>
            <div class="flex gap-1">
                <?php if ($page > 1): ?>
                <a href="?status=<?= $filter ?><?= $search ? '&q=' . urlencode($search) : '' ?>&p=<?= $page - 1 ?>" 
                   class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">
                    <i class="fas fa-chevron-left text-[10px]"></i> Prev
                </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($total_pages, $page + 2);
                if ($startPage > 1) {
                    echo '<a href="?status=' . $filter . ($search ? '&q=' . urlencode($search) : '') . '&p=1" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">1</a>';
                    if ($startPage > 2) echo '<span class="px-2 text-xs text-slate-500">...</span>';
                }
                for ($i = $startPage; $i <= $endPage; $i++):
                    $active = $i === $page;
                ?>
                <a href="?status=<?= $filter ?><?= $search ? '&q=' . urlencode($search) : '' ?>&p=<?= $i ?>" 
                   class="px-3 py-1.5 text-xs border rounded-lg transition <?= $active ? 'bg-amber-500 border-amber-500 text-white shadow-lg' : 'border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>">
                    <?= $i ?>
                </a>
                <?php endfor;
                if ($endPage < $total_pages) {
                    if ($endPage < $total_pages - 1) echo '<span class="px-2 text-xs text-slate-500">...</span>';
                    echo '<a href="?status=' . $filter . ($search ? '&q=' . urlencode($search) : '') . '&p=' . $total_pages . '" class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                <a href="?status=<?= $filter ?><?= $search ? '&q=' . urlencode($search) : '' ?>&p=<?= $page + 1 ?>" 
                   class="px-3 py-1.5 text-xs border border-slate-700 rounded-lg text-slate-400 hover:bg-slate-700 hover:text-white transition">
                    Next <i class="fas fa-chevron-right text-[10px]"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<script>
// Select all functionality
function toggleSelectAll() {
    const master = document.getElementById('selectAll');
    const boxes = document.querySelectorAll('.review-checkbox');
    boxes.forEach(cb => cb.checked = master.checked);
}

function updateSelectAllState() {
    const boxes = document.querySelectorAll('.review-checkbox');
    const checked = document.querySelectorAll('.review-checkbox:checked');
    const master = document.getElementById('selectAll');
    if (master) {
        master.checked = boxes.length > 0 && checked.length === boxes.length;
    }
}

// Bulk action confirmation
function confirmBulkAction() {
    const select = document.getElementById('bulkActionSelect');
    const action = select.value;
    const checked = document.querySelectorAll('.review-checkbox:checked');
    
    if (!action) {
        alert('Please select an action.');
        return false;
    }
    
    if (checked.length === 0) {
        alert('Please select at least one review.');
        return false;
    }
    
    if (!confirm(`Are you sure you want to ${action} ${checked.length} review(s)?`)) {
        return false;
    }
    
    document.getElementById('bulkAction').value = action;
    return true;
}

// Auto-submit bulk action when selecting from dropdown (optional)
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('bulkActionSelect');
    if (select) {
        select.addEventListener('change', function() {
            const checked = document.querySelectorAll('.review-checkbox:checked');
            if (this.value && checked.length === 0) {
                alert('Please select at least one review first.');
                this.value = '';
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>