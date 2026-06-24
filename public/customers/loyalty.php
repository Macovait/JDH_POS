<?php
/**
 * Loyalty Points Management page for Jakababa POS
 * 
 * Manages customer loyalty points, rewards, and redemptions.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

require_login();

// Check for loyalty management permission
if (!check_permission('loyalty.manage') && !is_super_admin()) {
    enforce_permission('loyalty.manage');
}

$pdo = get_db_connection();

// Get current user and company info
$user_id = (int) ($_SESSION['user']['id'] ?? get_current_user_id());
$tenant_id = (int) get_current_tenant_id();
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$user_branch = (int) ($_SESSION['user']['branch_id'] ?? 0);
$is_admin = in_array(strtolower($user_role), ['admin', 'owner', 'superadmin'], true) || is_super_admin();
$branch_id = (int) ($user_branch ?: get_current_branch_id());
// Handle CRUD operations
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_points':
            // Add/remove points from customer
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $points = intval($_POST['points'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            $expiry_days = intval($_POST['expiry_days'] ?? 365);

            if ($customer_id > 0 && $points != 0) {
                try {
                    $pdo->beginTransaction();

                    // Get customer current points
                    $stmt = $pdo->prepare('SELECT name, loyalty_points FROM customers WHERE id = ?');
                    $stmt->execute([$customer_id]);
                    $customer = $stmt->fetch();

                    if (!$customer) {
                        throw new Exception('Customer not found');
                    }

                    // Update customer points
                    $new_points = $customer['loyalty_points'] + $points;
                    $stmt = $pdo->prepare('UPDATE customers SET loyalty_points = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$new_points, $customer_id]);

                    // Calculate expiry date if applicable
                    $expiry_date = null;
                    if ($points > 0 && $expiry_days > 0) {
                        $expiry_date = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
                    }

                    // Ensure points don't go negative
                    if ($new_points < 0) {
                        $new_points = 0;
                    }

                    // Update customer points with tenant_id
                    $stmt = $pdo->prepare('UPDATE customers SET loyalty_points = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$new_points, $customer_id, $tenant_id]);

                    // Log points transaction
                    $stmt = $pdo->prepare('
                        INSERT INTO loyalty_points_log (
                            customer_id, tenant_id, points_change, previous_balance, new_balance, 
                            reason, expiry_date, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([
                        $customer_id,
                        $tenant_id,
                        $points,
                        $customer['loyalty_points'],
                        $new_points,
                        $reason,
                        $expiry_date,
                        $user_id
                    ]);

                    $pdo->commit();

                    $success_message = sprintf(
                        '%s %d points %s. New balance: %d',
                        $points > 0 ? 'Added' : 'Removed',
                        abs($points),
                        $points > 0 ? 'to' : 'from',
                        $new_points
                    );

                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error adjusting points: " . $e->getMessage());
                    $error_message = 'Failed to adjust points: ' . $e->getMessage();
                }
            }
            break;

        case 'create_reward':
            // Create new reward
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $points_required = intval($_POST['points_required'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'fixed';
            $discount_value = floatval($_POST['discount_value'] ?? 0);
            $stock_available = intval($_POST['stock_available'] ?? 0);
            $max_uses = intval($_POST['max_uses'] ?? 0);

            if (empty($name) || $points_required <= 0 || $discount_value <= 0) {
                $error_message = 'Name, points required, and discount value are required';
            } else {
                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO loyalty_rewards (
                            tenant_id, name, description, points_required, discount_type, discount_value,
                            stock_available, max_uses, status, is_active, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "active", 1, NOW())
                    ');
                    $stmt->execute([
                        $tenant_id,
                        $name,
                        $description,
                        $points_required,
                        $discount_type,
                        $discount_value,
                        $stock_available,
                        $max_uses
                    ]);

                    $success_message = 'Reward created successfully';

                } catch (PDOException $e) {
                    error_log("Error creating reward: " . $e->getMessage());
                    $error_message = 'Failed to create reward: ' . $e->getMessage();
                }
            }
            break;

        case 'update_reward':
            // Update existing reward
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $points_required = intval($_POST['points_required'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'fixed';
            $discount_value = floatval($_POST['discount_value'] ?? 0);
            $stock_available = intval($_POST['stock_available'] ?? 0);
            $max_uses = intval($_POST['max_uses'] ?? 0);
            $status = isset($_POST['status']) ? $_POST['status'] : 'active';

            if ($id > 0 && !empty($name)) {
                try {
                    $stmt = $pdo->prepare('
                        UPDATE loyalty_rewards 
                        SET name = ?, description = ?, points_required = ?, discount_type = ?,
                            discount_value = ?, stock_available = ?, max_uses = ?, status = ?, updated_at = NOW()
                        WHERE id = ? AND tenant_id = ?
                    ');
                    $stmt->execute([
                        $name,
                        $description,
                        $points_required,
                        $discount_type,
                        $discount_value,
                        $stock_available,
                        $max_uses,
                        $status,
                        $id,
                        $tenant_id
                    ]);

                    $success_message = 'Reward updated successfully';

                } catch (PDOException $e) {
                    error_log("Error updating reward: " . $e->getMessage());
                    $error_message = 'Failed to update reward: ' . $e->getMessage();
                }
            }
            break;

        case 'delete_reward':
            // Delete reward (Admin only)
            if (is_super_admin() || $user_role === 'Admin') {
                $id = intval($_POST['id'] ?? 0);

                if ($id > 0) {
                    try {
                        // Check if reward has been redeemed
                        $check = $pdo->prepare('SELECT COUNT(*) FROM loyalty_redemptions WHERE reward_id = ? AND tenant_id = ?');
                        $check->execute([$id, $tenant_id]);
                        $redemptions = $check->fetchColumn();

                        if ($redemptions > 0) {
                            // Soft delete - just deactivate
                            $stmt = $pdo->prepare('UPDATE loyalty_rewards SET status = "inactive", deleted_at = NOW() WHERE id = ? AND tenant_id = ?');
                            $stmt->execute([$id, $tenant_id]);
                            $success_message = 'Reward deactivated (has redemption history)';
                        } else {
                            // Hard delete
                            $stmt = $pdo->prepare('DELETE FROM loyalty_rewards WHERE id = ? AND tenant_id = ?');
                            $stmt->execute([$id, $tenant_id]);
                            $success_message = 'Reward deleted successfully';
                        }

                    } catch (PDOException $e) {
                        error_log("Error deleting reward: " . $e->getMessage());
                        $error_message = 'Failed to delete reward: ' . $e->getMessage();
                    }
                }
            }
            break;

        case 'redeem_points':
            // Customer redeems points for reward
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $reward_id = intval($_POST['reward_id'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            if ($customer_id > 0 && $reward_id > 0) {
                try {
                    $pdo->beginTransaction();

                    // Get customer points and phone
                    $stmt = $pdo->prepare('SELECT name, loyalty_points, phone FROM customers WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$customer_id, $tenant_id]);
                    $customer = $stmt->fetch();

                    if (!$customer) {
                        throw new Exception('Customer not found');
                    }

                    // Get reward details
                    $stmt = $pdo->prepare('SELECT * FROM loyalty_rewards WHERE id = ? AND status = "active" AND tenant_id = ?');
                    $stmt->execute([$reward_id, $tenant_id]);
                    $reward = $stmt->fetch();

                    if (!$reward) {
                        throw new Exception('Reward not found or inactive');
                    }

                    // Check stock
                    if ($reward['stock_available'] > 0 && $reward['stock_used'] >= $reward['stock_available']) {
                        throw new Exception('Reward out of stock');
                    }

                    // Check max uses
                    if ($reward['max_uses'] > 0 && $reward['stock_used'] >= $reward['max_uses']) {
                        throw new Exception('Reward usage limit reached');
                    }

                    // Check points
                    if ($customer['loyalty_points'] < $reward['points_required']) {
                        throw new Exception('Insufficient points');
                    }

                    // Deduct points
                    $new_points = $customer['loyalty_points'] - $reward['points_required'];
                    $stmt = $pdo->prepare('UPDATE customers SET loyalty_points = ? WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$new_points, $customer_id, $tenant_id]);

                    // Create redemption record
                    $stmt = $pdo->prepare('
                        INSERT INTO loyalty_redemptions (
                            customer_id, reward_id, tenant_id, points_redeemed, status, notes, created_by, created_at
                        ) VALUES (?, ?, ?, ?, "completed", ?, ?, NOW())
                    ');
                    $stmt->execute([
                        $customer_id,
                        $reward_id,
                        $tenant_id,
                        $reward['points_required'],
                        $notes,
                        $user_id
                    ]);

                    // Update stock used
                    $stmt = $pdo->prepare('UPDATE loyalty_rewards SET stock_used = stock_used + 1 WHERE id = ? AND tenant_id = ?');
                    $stmt->execute([$reward_id, $tenant_id]);

                    // Log points deduction
                    $stmt = $pdo->prepare('
                        INSERT INTO loyalty_points_log (
                            customer_id, tenant_id, points_change, previous_balance, new_balance, 
                            reason, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([
                        $customer_id,
                        $tenant_id,
                        -$reward['points_required'],
                        $customer['loyalty_points'],
                        $new_points,
                        "Redeemed: {$reward['name']}",
                        $user_id
                    ]);

                    $pdo->commit();

                    // Send SMS notification if customer has phone number and SMS is enabled
                    if (!empty($customer['phone']) && get_tenant_setting($tenant_id, 'sms_enabled', '0') === '1') {
                        $message = "Hi {$customer['name']}, you've successfully redeemed {$reward['points_required']} loyalty points for {$reward['name']}. Thank you for your continued business!";
                        
                        // Send SMS asynchronously (non-blocking)
                        try {
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'http://localhost/JDH_POS/public/ajax/send_sms.php');
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                                'phone' => $customer['phone'],
                                'message' => $message,
                                'async' => true
                            ]));
                            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_exec($ch);
                            curl_close($ch);
                        } catch (Exception $e) {
                            // Log but don't fail the redemption
                            error_log("Failed to send SMS for redemption: " . $e->getMessage());
                        }
                    }

                    $success_message = 'Points redeemed successfully';
                    if (!empty($customer['phone'])) {
                        $success_message .= ' (SMS notification sent)';
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Error redeeming points: " . $e->getMessage());
                    $error_message = $e->getMessage();
                }
            }
            break;
    }
}

// Transactions filters
$search = trim($_GET['s'] ?? '');
$date_from = $_GET['date_from'] ?: date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?: date('Y-m-d');
$type_filter = $_GET['type'] ?? '';
if (!in_array($type_filter, ['issued', 'redeemed'], true)) {
    $type_filter = '';
}
$customer_filter = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int) $_GET['customer_id'] : 0;
$branch_filter = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
if ($branch_filter === 0) {
    $branch_filter = $user_branch ?: get_current_branch_id();
}
if (!$is_admin && $user_branch > 0) {
    $branch_filter = $user_branch;
}
$sort_by = $_GET['sort'] ?? 'newest';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$transaction_where = " WHERE l.tenant_id = ? ";
$transaction_params = [$tenant_id];
$transaction_where .= " AND c.tenant_id = ? ";
$transaction_params[] = $tenant_id;
$transaction_where .= " AND DATE(l.created_at) BETWEEN ? AND ? ";
$transaction_params[] = $date_from;
$transaction_params[] = $date_to;

if ($branch_filter > 0) {
    $transaction_where .= " AND c.branch_id = ? ";
    $transaction_params[] = $branch_filter;
}
if ($customer_filter > 0) {
    $transaction_where .= " AND l.customer_id = ? ";
    $transaction_params[] = $customer_filter;
}
if (!empty($search)) {
    $transaction_where .= " AND (l.reason LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR u.name LIKE ?) ";
    $transaction_params[] = "%{$search}%";
    $transaction_params[] = "%{$search}%";
    $transaction_params[] = "%{$search}%";
    $transaction_params[] = "%{$search}%";
}
if ($type_filter === 'issued') {
    $transaction_where .= " AND l.points_change > 0 ";
}
if ($type_filter === 'redeemed') {
    $transaction_where .= " AND l.points_change < 0 ";
}

$order = "ORDER BY l.created_at DESC";
switch ($sort_by) {
    case 'oldest':  $order = "ORDER BY l.created_at ASC";  break;
    case 'highest': $order = "ORDER BY l.points_change DESC"; break;
    case 'lowest':  $order = "ORDER BY l.points_change ASC"; break;
}

// Branches list
$branches = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? ORDER BY name");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* ignore */ }

// Transactions count
$total_transactions = 0;
$issued_count = 0;
$redeemed_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_points_log l JOIN customers c ON c.id = l.customer_id LEFT JOIN users u ON u.id = l.created_by $transaction_where");
    $stmt->execute($transaction_params);
    $total_transactions = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT SUM(l.points_change > 0), SUM(l.points_change < 0) FROM loyalty_points_log l JOIN customers c ON c.id = l.customer_id LEFT JOIN users u ON u.id = l.created_by $transaction_where");
    $stmt->execute($transaction_params);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $issued_count = (int) ($row[0] ?? 0);
    $redeemed_count = (int) ($row[1] ?? 0);
} catch (Exception $e) {
    error_log('Loyalty transaction count error: ' . $e->getMessage());
}

// Transactions list
$transactions = [];
try {
    $sql = "
        SELECT
            l.id,
            l.customer_id,
            l.points_change,
            l.reason,
            l.created_by,
            l.created_at,
            l.tenant_id,
            c.name AS customer_name,
            c.phone AS customer_phone,
            c.loyalty_points AS customer_loyalty_points,
            c.branch_id,
            b.name AS branch_name,
            u.name AS created_by_name
        FROM loyalty_points_log l
        JOIN customers c ON c.id = l.customer_id
        LEFT JOIN branches b ON b.id = c.branch_id
        LEFT JOIN users u ON u.id = l.created_by
        $transaction_where
        $order
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($transaction_params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Loyalty transaction list error: ' . $e->getMessage());
}

$total_pages = max(1, (int) ceil($total_transactions / $limit));

// Customers for dropdown
$customers = [];
try {
    $customer_sql = "
        SELECT id, name, phone, loyalty_points, branch_id
        FROM customers
        WHERE tenant_id = ? AND active = 1
    ";
    $customer_params = [$tenant_id];
    if ($branch_filter > 0) {
        $customer_sql .= " AND branch_id = ?";
        $customer_params[] = $branch_filter;
    }
    $customer_sql .= " ORDER BY name";

    $stmt = $pdo->prepare($customer_sql);
    $stmt->execute($customer_params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Loyalty customer list error: ' . $e->getMessage());
}

// Summary statistics
$stats = [
    'total_points_issued' => 0,
    'total_points_redeemed' => 0,
    'customers_with_points' => 0,
    'total_redemptions' => 0,
    'avg_points' => 0,
];
try {
    $stats_where = " WHERE tenant_id = ? ";
    $stats_params = [$tenant_id];
    $customer_stats_where = " WHERE tenant_id = ? ";
    $customer_stats_params = [$tenant_id];

    if ($branch_filter > 0) {
        $customer_stats_where .= " AND branch_id = ? ";
        $customer_stats_params[] = $branch_filter;
    }

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN points_change > 0 THEN points_change ELSE 0 END), 0) AS total_points_issued,
            ABS(COALESCE(SUM(CASE WHEN points_change < 0 THEN points_change ELSE 0 END), 0)) AS total_points_redeemed
        FROM loyalty_points_log
        $stats_where
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute(array_merge($stats_params, [$date_from, $date_to]));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['total_points_issued'] = $row['total_points_issued'];
        $stats['total_points_redeemed'] = $row['total_points_redeemed'];
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers $customer_stats_where AND loyalty_points > 0");
    $stmt->execute($customer_stats_params);
    $stats['customers_with_points'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loyalty_redemptions WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total_redemptions'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(AVG(loyalty_points), 0) FROM customers $customer_stats_where AND loyalty_points > 0");
    $stmt->execute($customer_stats_params);
    $stats['avg_points'] = (int) round($stmt->fetchColumn());
} catch (Exception $e) {
    error_log('Loyalty stats error: ' . $e->getMessage());
}

$build_qs = function (array $extra = []) use ($search, $type_filter, $date_from, $date_to, $customer_filter, $branch_filter, $sort_by, $page) {
    $base = array_filter([
        'tab'       => 'transactions',
        's'         => $search,
        'type'      => $type_filter,
        'date_from' => $date_from,
        'date_to'   => $date_to,
        'customer_id' => $customer_filter ?: null,
        'branch_id' => $branch_filter ?: null,
        'sort'      => $sort_by,
        'page'      => $page,
    ], fn($v) => $v !== '' && $v !== null);
    return http_build_query(array_merge($base, $extra));
};

$transaction_colspan = ($is_admin && count($branches) > 1) ? 7 : 6;

$page_title = 'Loyalty Management | Jakababa POS';
$current_year = date('Y');
$can_delete = is_super_admin() || $user_role === 'Admin';

ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-gift text-amber-400"></i> Loyalty Points
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Manage customer loyalty points and rewards</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button onclick="openAddPointsModal()"
            class="px-3 py-1.5 bg-emerald-500/15 border border-emerald-500/30 rounded-lg text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors flex items-center gap-1.5">
            <i class="fas fa-plus-circle text-xs"></i> Add Points
        </button>
        <button onclick="openCreateRewardModal()"
            class="px-3 py-1.5 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors flex items-center gap-1.5">
            <i class="fas fa-gift text-xs"></i> Create Reward
        </button>
    </div>
</div>

<!-- Success/Error Messages -->
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
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 mb-5">
    <?php
    $cards = [
        ['label' => 'Points Issued',   'value' => $stats['total_points_issued'] ?? 0,   'icon' => 'fa-arrow-up',      'color' => 'text-emerald-400',   'bg' => 'bg-emerald-500/10'],
        ['label' => 'Points Redeemed', 'value' => $stats['total_points_redeemed'] ?? 0, 'icon' => 'fa-arrow-down',    'color' => 'text-red-400',       'bg' => 'bg-red-500/10'],
        ['label' => 'Active Customers','value' => $stats['customers_with_points'] ?? 0, 'icon' => 'fa-users',         'color' => 'text-amber-400',   'bg' => 'bg-amber-500/10'],
        ['label' => 'Avg Points',      'value' => $stats['avg_points'] ?? 0,            'icon' => 'fa-chart-line',     'color' => 'text-blue-400',    'bg' => 'bg-blue-500/10'],
    ];
    foreach ($cards as $card):
    ?>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg <?php echo $card['bg']; ?> flex items-center justify-center shrink-0">
            <i class="fas <?php echo $card['icon']; ?> <?php echo $card['color']; ?> text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold <?php echo $card['color']; ?> truncate">
                <?php echo number_format((float)$card['value'], 0); ?>
            </div>
            <div class="text-xs text-slate-500 leading-none mt-0.5"><?php echo $card['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

        <!-- Tabs -->
        <div class="flex border-b border-slate-700/60 mb-6">
            <button data-tab="transactions" onclick="switchTab('transactions')" id="tab-transactions" class="border-b-2 border-amber-400 text-amber-400 px-4 py-3 text-sm font-medium">
                <i class="fas fa-history mr-2 text-sm"></i>Points Transactions
            </button>
            <button data-tab="rewards" onclick="switchTab('rewards')" id="tab-rewards" class="border-b-2 border-transparent text-slate-500 hover:text-slate-300 px-4 py-3 text-sm font-medium">
                <i class="fas fa-gift mr-2 text-sm"></i>Rewards
            </button>
            <button data-tab="redemptions" onclick="switchTab('redemptions')" id="tab-redemptions" class="border-b-2 border-transparent text-slate-500 hover:text-slate-300 px-4 py-3 text-sm font-medium">
                <i class="fas fa-check-circle mr-2 text-sm"></i>Redemptions
            </button>
        </div>

        <!-- Transactions Tab -->
        <div id="transactions-tab" class="tab-content">
            <!-- Type pills + filters -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3 mb-4 space-y-3">
                <div class="flex flex-wrap gap-1.5">
                    <?php
                    $pills = [
                        ''         => ['label' => 'All',       'count' => $total_transactions, 'on' => 'bg-amber-500/20 text-amber-400 border-amber-500/30', 'off' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
                        'issued'   => ['label' => 'Issued',    'count' => $issued_count,       'on' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30', 'off' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
                        'redeemed' => ['label' => 'Redeemed',  'count' => $redeemed_count,     'on' => 'bg-red-500/20 text-red-400 border-red-500/30', 'off' => 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'],
                    ];
                    foreach ($pills as $val => $pill):
                        $active = $type_filter === $val;
                    ?>
                    <a href="?<?php echo htmlspecialchars($build_qs(['type' => $val, 'page' => 1])); ?>"
                       class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium border transition-colors <?php echo $active ? $pill['on'] : $pill['off']; ?>">
                        <?php echo $pill['label']; ?>
                        <span class="text-xs opacity-70">(<?php echo (int)$pill['count']; ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <form method="get" class="flex flex-wrap gap-2 items-end">
                    <?php if ($type_filter): ?>
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type_filter); ?>">
                    <?php endif; ?>
                    <?php if ($is_admin && $branch_filter > 0): ?>
                    <input type="hidden" name="branch_id" value="<?php echo (int)$branch_filter; ?>">
                    <?php endif; ?>

                    <div class="relative flex-1 min-w-[160px]">
                        <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
                        <input type="search" name="s" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Customer, phone, reason…"
                               class="w-full pl-7 pr-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>

                    <select name="customer_id" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo (int)$customer['id']; ?>" <?php echo $customer_filter == $customer['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($customer['name']); ?> (<?php echo number_format((int)$customer['loyalty_points']); ?> pts)
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($is_admin && count($branches) > 1): ?>
                    <select name="branch_id" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="0">All Branches</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>" <?php echo $branch_filter == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>

                    <select name="sort" class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="highest" <?php echo $sort_by === 'highest' ? 'selected' : ''; ?>>Highest Points</option>
                        <option value="lowest" <?php echo $sort_by === 'lowest' ? 'selected' : ''; ?>>Lowest Points</option>
                    </select>

                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                           class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                           class="px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">

                    <button type="submit" class="px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors">
                        <i class="fas fa-filter mr-1 text-xs"></i>Filter
                    </button>
                    <a href="loyalty.php?tab=transactions" class="px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors">
                        <i class="fas fa-times mr-1 text-xs"></i>Clear
                    </a>
                </form>
            </div>

            <!-- Transactions table -->
            <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px]">
                        <thead>
                            <tr class="border-b border-slate-700/60 bg-slate-800/60">
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date / Time</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                                <?php if ($is_admin && count($branches) > 1): ?>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Branch</th>
                                <?php endif; ?>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Points</th>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Balance</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Reason</th>
                                <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="<?php echo $transaction_colspan; ?>" class="px-4 py-14 text-center">
                                    <i class="fas fa-exchange-alt text-4xl text-slate-700 block mb-3"></i>
                                    <p class="text-slate-500 text-sm">No transactions found</p>
                                    <?php if ($search || $type_filter || $customer_filter || $date_from || $date_to): ?>
                                    <a href="loyalty.php?tab=transactions" class="mt-2 inline-flex items-center gap-1 text-xs text-amber-400 hover:text-amber-300 transition-colors">
                                        <i class="fas fa-times text-xs"></i> Clear filters
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t):
                                $points = (int) ($t['points_change'] ?? 0);
                                $point_class = $points >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400';
                                $balance = (int) ($t['customer_loyalty_points'] ?? 0);
                                $created_at = !empty($t['created_at']) ? $t['created_at'] : null;
                            ?>
                            <tr class="hover:bg-slate-700/30 transition-colors group">
                                <td class="px-3 py-2.5">
                                    <div class="text-sm text-slate-300"><?php echo $created_at ? date('d M Y', strtotime($created_at)) : '—'; ?></div>
                                    <div class="text-xs text-slate-500"><?php echo $created_at ? date('H:i', strtotime($created_at)) : '—'; ?></div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="text-sm text-white"><?php echo htmlspecialchars($t['customer_name'] ?? 'Customer'); ?></div>
                                    <?php if (!empty($t['customer_phone'])): ?>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($t['customer_phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($is_admin && count($branches) > 1): ?>
                                <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo htmlspecialchars($t['branch_name'] ?? '—'); ?></td>
                                <?php endif; ?>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $point_class; ?>">
                                        <?php echo ($points > 0 ? '+' : '') . number_format($points, 0); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <span class="text-sm font-mono font-semibold text-slate-200"><?php echo number_format($balance, 0); ?></span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="text-sm text-slate-300"><?php echo htmlspecialchars($t['reason'] ?: '—'); ?></span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-slate-400"><?php echo htmlspecialchars($t['created_by_name'] ?: 'System'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table footer: count + pagination -->
                <?php if ($total_transactions > 0): ?>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-2.5 border-t border-slate-700/60 bg-slate-800/40">
                    <p class="text-xs text-slate-500">
                        Showing <span class="text-slate-300 font-medium"><?php echo $offset + 1; ?>–<?php echo min($offset + $limit, $total_transactions); ?></span>
                        of <span class="text-slate-300 font-medium"><?php echo number_format($total_transactions); ?></span> transactions
                    </p>
                    <?php if ($total_pages > 1): ?>
                    <div class="flex items-center gap-1">
                        <a href="?<?php echo htmlspecialchars($build_qs(['page' => max(1, $page - 1)])); ?>"
                           class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page <= 1 ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                            <i class="fas fa-chevron-left text-xs"></i>
                        </a>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?php echo htmlspecialchars($build_qs(['page' => $i])); ?>"
                           class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors font-medium <?php echo $i === $page ? 'bg-amber-500/20 border-amber-500/40 text-amber-400' : 'bg-slate-800 border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        <a href="?<?php echo htmlspecialchars($build_qs(['page' => min($total_pages, $page + 1)])); ?>"
                           class="w-7 h-7 flex items-center justify-center rounded-lg text-xs border transition-colors <?php echo $page >= $total_pages ? 'border-slate-700 text-slate-600 pointer-events-none' : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white'; ?>">
                            <i class="fas fa-chevron-right text-xs"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rewards Tab (hidden by default) -->
        <div id="rewards-tab" class="tab-content hidden">
            <!-- Rewards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if (empty($rewards)): ?>
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-gift text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No rewards created yet</p>
                        <button onclick="openCreateRewardModal()" class="mt-4 px-4 py-2 bg-[#FBBF24] text-black rounded-lg">
                            Create Your First Reward
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($rewards as $reward): ?>
                        <div class="card p-4 <?php echo $reward['active'] ? '' : 'opacity-60'; ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="font-semibold text-white">
                                        <?php echo htmlspecialchars($reward['name']); ?>
                                    </h3>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?php echo htmlspecialchars($reward['description'] ?: 'No description'); ?>
                                    </p>
                                </div>
                                <span class="badge-reward text-xs px-2 py-1 rounded-full">
                                    <?php echo $reward['points_required']; ?> pts
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                                <div>
                                    <span class="text-gray-500">Type:</span>
                                    <span class="text-white ml-1">
                                        <?php
                                        switch ($reward['reward_type']) {
                                            case 'discount':
                                                echo 'Discount';
                                                break;
                                            case 'product':
                                                echo 'Free Product';
                                                break;
                                            case 'shipping':
                                                echo 'Free Shipping';
                                                break;
                                            default:
                                                echo $reward['reward_type'];
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Value:</span>
                                    <span class="text-white ml-1">
                                        <?php
                                        if ($reward['reward_type'] === 'discount') {
                                            echo $reward['reward_value'] . '%';
                                        } else {
                                            echo 'KSh ' . number_format($reward['reward_value'], 0);
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Valid:</span>
                                    <span class="text-white ml-1">
                                        <?php echo $reward['valid_days']; ?> days
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Stock:</span>
                                    <span class="text-white ml-1">
                                        <?php
                                        if ($reward['stock'] > 0) {
                                            echo ($reward['stock'] - ($reward['stock_used'] ?? 0)) . '/' . $reward['stock'];
                                        } else {
                                            echo 'Unlimited';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs">
                                    <?php if ($reward['active']): ?>
                                        <span class="badge-active px-2 py-1 rounded-full">
                                            <i class="fas fa-circle text-xs mr-1"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-inactive px-2 py-1 rounded-full">
                                            <i class="fas fa-circle text-xs mr-1"></i>Inactive
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-gray-500 ml-2">
                                        <?php echo $reward['times_redeemed']; ?> redeemed
                                    </span>
                                </span>

                                <div class="flex gap-2">
                                    <button onclick="editReward(<?php echo htmlspecialchars(json_encode($reward)); ?>)"
                                        class="text-gray-400 hover:text-[#FBBF24] transition" title="Edit">
                                        <i class="fas fa-edit text-lg"></i>
                                    </button>
                                    <?php if ($can_delete): ?>
                                        <button
                                            onclick="deleteReward(<?php echo $reward['id']; ?>, '<?php echo htmlspecialchars(addslashes($reward['name'])); ?>')"
                                            class="text-gray-400 hover:text-[#EF4444] transition" title="Delete">
                                            <i class="fas fa-trash text-lg"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Redemptions Tab (hidden by default) -->
        <div id="redemptions-tab" class="tab-content hidden">
            <div class="card p-6 text-center">
                <i class="fas fa-check-circle text-4xl text-gray-600 mb-3"></i>
                <p class="text-gray-400">Redemption history will appear here</p>
                <p class="text-sm text-gray-500 mt-2">Use the "Redeem Points" option in customer view</p>
            </div>
        </div>
    </div>

    <!-- Add Points Modal -->
    <div id="addPointsModal" class="fixed inset-0 bg-black/70 flex items-center justify-center hidden z-50">
        <div class="bg-[#1F2937] rounded-xl border border-[#374151] p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-white">Add/Remove Points</h3>
                <button onclick="closeAddPointsModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="add_points">

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Customer *</label>
                    <select name="customer_id" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['name']); ?> (
                                <?php echo $customer['loyalty_points']; ?> pts)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Points *</label>
                    <input type="number" name="points" required placeholder="e.g., 50 or -10" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <p class="text-xs text-gray-500 mt-1">Positive to add, negative to remove</p>
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Reason</label>
                    <input type="text" name="reason" placeholder="e.g., Purchase bonus, adjustment" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Points Expiry (days)</label>
                    <input type="number" name="expiry_days" value="365" min="0" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    <p class="text-xs text-gray-500 mt-1">0 = never expires</p>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-[#10B981] text-white rounded-lg font-medium hover:bg-[#059669] transition">
                        Save Changes
                    </button>
                    <button type="button" onclick="closeAddPointsModal()"
                        class="flex-1 px-4 py-2 bg-[#374151] text-white rounded-lg font-medium hover:bg-[#4B5563] transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Reward Modal -->
    <div id="createRewardModal" class="fixed inset-0 bg-black/70 flex items-center justify-center hidden z-50">
        <div
            class="bg-[#1F2937] rounded-xl border border-[#374151] p-6 max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-white" id="rewardModalTitle">Create Reward</h3>
                <button onclick="closeCreateRewardModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4" id="rewardForm">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" id="rewardAction" value="create_reward">
                <input type="hidden" name="id" id="rewardId" value="0">

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Reward Name *</label>
                    <input type="text" name="name" id="rewardName" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Description</label>
                    <textarea name="description" id="rewardDescription" rows="2" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Points Required *</label>
                        <input type="number" name="points_required" id="rewardPoints" required min="1"
                            class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Reward Type</label>
                        <select name="reward_type" id="rewardType" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="discount">Discount (%)</option>
                            <option value="product">Free Product</option>
                            <option value="shipping">Free Shipping</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Reward Value</label>
                        <input type="number" name="reward_value" id="rewardValue" step="0.01" min="0"
                            class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <p class="text-xs text-gray-500">% or amount</p>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Valid Days</label>
                        <input type="number" name="valid_days" id="rewardValidDays" value="30" min="1"
                            class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Stock (0 = unlimited)</label>
                        <input type="number" name="stock" id="rewardStock" value="0" min="0" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Image URL</label>
                        <input type="text" name="image" id="rewardImage" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="active" id="rewardActive" value="1" checked
                        class="rounded border-gray-600">
                    <label for="rewardActive" class="text-sm text-gray-300">Active</label>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-[#FBBF24] text-black rounded-lg font-medium hover:bg-[#F59E0B] transition">
                        Save Reward
                    </button>
                    <button type="button" onclick="closeCreateRewardModal()"
                        class="flex-1 px-4 py-2 bg-[#374151] text-white rounded-lg font-medium hover:bg-[#4B5563] transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Reward Modal -->
    <div id="deleteRewardModal" class="fixed inset-0 bg-black/70 flex items-center justify-center hidden z-50">
        <div class="bg-[#1F2937] rounded-xl border border-[#374151] p-6 max-w-md w-full mx-4">
            <div class="flex items-center gap-3 text-[#EF4444] mb-4">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
                <h3 class="text-xl font-semibold text-white">Delete Reward</h3>
            </div>

            <form method="POST">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="delete_reward">
                <input type="hidden" name="id" id="deleteRewardId">

                <p class="text-gray-400 mb-6">
                    Are you sure you want to delete <span id="deleteRewardName"
                        class="text-white font-semibold"></span>?
                    This action cannot be undone.
                </p>

                <div class="flex gap-3">
                    <button type="submit"
                        class="flex-1 px-4 py-2 bg-[#EF4444] text-white rounded-lg font-medium hover:bg-[#DC2626] transition">
                        Delete Reward
                    </button>
                    <button type="button" onclick="closeDeleteRewardModal()"
                        class="flex-1 px-4 py-2 bg-[#374151] text-white rounded-lg font-medium hover:bg-[#4B5563] transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Connection Status -->
    <div id="connection-status"
        class="fixed bottom-4 left-4 text-xs text-green-500 flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151]">
        <i class="fas fa-wifi text-lg"></i>
        <span>Online</span>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('[data-tab]').forEach(tab => {
                const active = tab.dataset.tab === tabName;
                tab.classList.toggle('border-amber-400', active);
                tab.classList.toggle('border-transparent', !active);
                tab.classList.toggle('text-amber-400', active);
                tab.classList.toggle('text-slate-500', !active);
                tab.classList.toggle('hover:text-slate-300', !active);
            });

            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            const selected = document.getElementById(`${tabName}-tab`);
            if (selected) {
                selected.classList.remove('hidden');
            }

            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // Check URL for active tab
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'transactions';
        switchTab(activeTab);

        // Add Points Modal
        function openAddPointsModal() {
            document.getElementById('addPointsModal').classList.remove('hidden');
        }

        function closeAddPointsModal() {
            document.getElementById('addPointsModal').classList.add('hidden');
        }

        // Create Reward Modal
        function openCreateRewardModal() {
            document.getElementById('rewardModalTitle').textContent = 'Create Reward';
            document.getElementById('rewardAction').value = 'create_reward';
            document.getElementById('rewardId').value = '0';
            document.getElementById('rewardName').value = '';
            document.getElementById('rewardDescription').value = '';
            document.getElementById('rewardPoints').value = '';
            document.getElementById('rewardType').value = 'discount';
            document.getElementById('rewardValue').value = '';
            document.getElementById('rewardValidDays').value = '30';
            document.getElementById('rewardStock').value = '0';
            document.getElementById('rewardImage').value = '';
            document.getElementById('rewardActive').checked = true;
            document.getElementById('createRewardModal').classList.remove('hidden');
        }

        function editReward(reward) {
            document.getElementById('rewardModalTitle').textContent = 'Edit Reward';
            document.getElementById('rewardAction').value = 'update_reward';
            document.getElementById('rewardId').value = reward.id;
            document.getElementById('rewardName').value = reward.name || '';
            document.getElementById('rewardDescription').value = reward.description || '';
            document.getElementById('rewardPoints').value = reward.points_required || '';
            document.getElementById('rewardType').value = reward.reward_type || 'discount';
            document.getElementById('rewardValue').value = reward.reward_value || '';
            document.getElementById('rewardValidDays').value = reward.valid_days || '30';
            document.getElementById('rewardStock').value = reward.stock || '0';
            document.getElementById('rewardImage').value = reward.image || '';
            document.getElementById('rewardActive').checked = reward.active == 1;
            document.getElementById('createRewardModal').classList.remove('hidden');
        }

        function closeCreateRewardModal() {
            document.getElementById('createRewardModal').classList.add('hidden');
        }

        // Delete Reward Modal
        function deleteReward(id, name) {
            document.getElementById('deleteRewardId').value = id;
            document.getElementById('deleteRewardName').textContent = name;
            document.getElementById('deleteRewardModal').classList.remove('hidden');
        }

        function closeDeleteRewardModal() {
            document.getElementById('deleteRewardModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.fixed').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });

        // Connection status
        function updateOnlineStatus() {
            const statusEl = document.getElementById('connection-status');
            if (statusEl) {
                if (navigator.onLine) {
                    statusEl.innerHTML = '<i class="fas fa-wifi text-lg"></i><span>Online</span>';
                    statusEl.className = 'fixed bottom-4 left-4 text-xs text-green-500 flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151]';
                } else {
                    statusEl.innerHTML = '<i class="fas fa-wifi-slash text-lg"></i><span>Offline</span>';
                    statusEl.className = 'fixed bottom-4 left-4 text-xs text-red-500 flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#374151]';
                }
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Auto-hide success message
        setTimeout(() => {
            const successMsg = document.querySelector('.bg-\\[\\#10B981\\]\\/10');
            if (successMsg) {
                successMsg.style.transition = 'opacity 0.5s';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }
        }, 5000);
    </script>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
