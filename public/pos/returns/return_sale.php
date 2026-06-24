<?php
/**
 * Return / Refund Sale - Jakababa POS
 * PURE TAILWIND CSS EDITION - Modern return processing interface
 */

$page_title = 'Return Sale';
ob_start();

// Bootstrap paths and core dependencies
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
require_login();

safe_require('db.php', 'src', true);
$pdo = get_db_connection();

// Permission check
if (function_exists('check_permission') && !check_permission('sales.returns') && !(function_exists('is_super_admin') && is_super_admin())) {
    if (function_exists('enforce_permission')) {
        enforce_permission('sales.returns');
    }
}

$user_id     = (int) ($_SESSION['user']['id'] ?? 0);
$user_branch = (int) ($_SESSION['user']['branch_id'] ?? 0);
$tenant_id   = (int) get_current_tenant_id();
$currency = 'KES';
if (function_exists('get_settings')) { $s = get_settings(); if (!empty($s['currency'])) $currency = $s['currency']; }

$sale_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($sale_id <= 0) {
    header('Location: ../all_sales.php');
    exit;
}

$message = '';
$message_type = '';
$success_return_number = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    $post_token = $_POST['csrf_token'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($post_token, 'return_sale')) {
        $message      = 'Invalid or expired security token. Please refresh the page and try again.';
        $message_type = 'error';
        goto render_page;
    }

    $return_type    = $_POST['return_type'] ?? 'full';
    $selected_items = $_POST['items'] ?? [];
    $reason         = trim($_POST['reason'] ?? '');
    $refund_method  = trim($_POST['refund_method'] ?? 'cash');
    $notes          = trim($_POST['notes'] ?? '');
    $restock        = !empty($_POST['restock']) ? 1 : 0;

    $allowed_refund_methods = ['cash','card','mpesa','bank_transfer','credit_note','exchange'];
    if (!in_array($refund_method, $allowed_refund_methods)) $refund_method = 'cash';

    if (empty($reason)) {
        $message      = 'Please select a reason for the return.';
        $message_type = 'error';
        goto render_page;
    }

    try {
        $pdo->beginTransaction();

        // Column existence checks
        $_post_s_has_tenant  = false;
        $_post_si_has_tenant = false;
        $_post_si_has_sub    = false;
        try { $r = $pdo->query("SHOW COLUMNS FROM sales      LIKE 'tenant_id'"); $_post_s_has_tenant  = $r->rowCount() > 0; } catch (Exception $_e) {}
        try { $r = $pdo->query("SHOW COLUMNS FROM sale_items LIKE 'tenant_id'"); $_post_si_has_tenant = $r->rowCount() > 0; } catch (Exception $_e) {}
        try { $r = $pdo->query("SHOW COLUMNS FROM sale_items LIKE 'subtotal'");  $_post_si_has_sub    = $r->rowCount() > 0; } catch (Exception $_e) {}

        // Load sale
        $s_lock_where  = $_post_s_has_tenant
            ? "WHERE s.id = ? AND s.tenant_id = ? AND s.status IN ('completed', 'returned')"
            : "WHERE s.id = ? AND s.status IN ('completed', 'returned')";
        $s_lock_params = $_post_s_has_tenant ? [$sale_id, $tenant_id] : [$sale_id];

        $stmt = $pdo->prepare("
            SELECT s.*, c.name AS customer_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            {$s_lock_where}
        ");
        $stmt->execute($s_lock_params);
        $sale_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale_row) {
            throw new Exception('Sale not found or not eligible for return');
        }

        // Load sale items
        $si_sub_expr  = $_post_si_has_sub
            ? 'COALESCE(NULLIF(si.subtotal,0) / NULLIF(si.quantity,1), si.price)'
            : 'si.price';
        $si_line_expr = $_post_si_has_sub
            ? 'COALESCE(si.subtotal, si.price * si.quantity)'
            : 'si.price * si.quantity';
        $stmt = $pdo->prepare("
            SELECT si.id, si.product_id, si.quantity,
                   si.price            AS orig_price,
                   {$si_sub_expr}      AS unit_price,
                   {$si_line_expr}     AS line_total,
                   COALESCE(NULLIF(si.product_name,''), p.name, CONCAT('Product #', si.product_id)) AS product_name,
                   COALESCE(si.product_sku, p.sku) AS sku
            FROM sale_items si
            LEFT JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
            WHERE si.sale_id = ? AND si.tenant_id = ?
        ");
        $stmt->execute([$sale_id, $tenant_id]);
        $sale_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Load already-returned quantities
        $already_returned = [];
        try {
            $ar_stmt = $pdo->prepare("
                SELECT ri.sale_item_id, COALESCE(SUM(ri.quantity), 0) AS qty_returned
                FROM return_items ri
                JOIN returns ret ON ret.id = ri.return_id
                WHERE ri.sale_item_id IN (
                    SELECT id FROM sale_items WHERE sale_id = ? AND tenant_id = ?
                ) AND ret.tenant_id = ? AND ret.status = 'completed'
                GROUP BY ri.sale_item_id
            ");
            $ar_stmt->execute([$sale_id, $tenant_id, $tenant_id]);
            foreach ($ar_stmt->fetchAll(PDO::FETCH_ASSOC) as $ar) {
                $already_returned[(int)$ar['sale_item_id']] = (int)$ar['qty_returned'];
            }
        } catch (Exception $_e) { error_log('already_returned query failed: ' . $_e->getMessage()); }

        // Build return items list
        $return_items    = [];
        $return_subtotal = 0.0;

        if ($return_type === 'full') {
            foreach ($sale_items as $it) {
                $item_id      = (int)$it['id'];
                $already_qty  = $already_returned[$item_id] ?? 0;
                $unit         = (float)$it['unit_price'];
                $orig_qty     = (int)$it['quantity'];
                $qty          = max(0, $orig_qty - $already_qty);
                if ($qty <= 0) continue;
                $line = (float)$it['line_total'];
                if ($line <= 0) $line = $unit * $orig_qty;
                $line = ($line / ($orig_qty ?: 1)) * $qty;
                $return_subtotal += $line;
                $return_items[] = [
                    'id'          => $item_id,
                    'product_id'  => (int)$it['product_id'],
                    'quantity'    => $qty,
                    'price'       => $unit > 0 ? $unit : (float)$it['orig_price'],
                    'product_name'=> $it['product_name'],
                ];
            }
        } else {
            // PARTIAL RETURN - use submitted quantities
            foreach ($sale_items as $it) {
                $item_id      = (int)$it['id'];
                $already_qty  = $already_returned[$item_id] ?? 0;
                $orig_qty     = (int)$it['quantity'];
                $max_returnable = max(0, $orig_qty - $already_qty);
                $key  = 'item_' . $item_id;
                // IMPORTANT: Get the quantity from submitted form data
                $qty  = isset($selected_items[$key]) ? (int)$selected_items[$key] : 0;
                $qty  = max(0, min($qty, $max_returnable));
                
                error_log("Item $item_id - key: $key, submitted qty: " . ($selected_items[$key] ?? 'not set') . ", final qty: $qty, max: $max_returnable");
                
                if ($qty > 0) {
                    $unit      = (float)$it['unit_price'];
                    if ($unit <= 0) $unit = (float)$it['orig_price'];
                    $full_line = (float)$it['line_total'];
                    $full_qty  = $orig_qty ?: 1;
                    $line      = ($full_line / $full_qty) * $qty;
                    $return_subtotal += $line;
                    $return_items[] = [
                        'id'          => $item_id,
                        'product_id'  => (int)$it['product_id'],
                        'quantity'    => $qty,
                        'price'       => $unit,
                        'product_name'=> $it['product_name'],
                    ];
                }
            }
        }

        if (empty($return_items)) {
            $reasons = [];
            if (empty($sale_items)) {
                $reasons[] = 'No sale items found for this sale (sale_id=' . $sale_id . ')';
            } else {
                foreach ($sale_items as $it) {
                    $item_id     = (int)$it['id'];
                    $already_qty = $already_returned[$item_id] ?? 0;
                    $orig_qty    = (int)$it['quantity'];
                    $remaining   = max(0, $orig_qty - $already_qty);
                    $name        = $it['product_name'] ?? ('Item #' . $item_id);
                    if ($remaining <= 0) {
                        $reasons[] = "{$name}: already fully returned ({$already_qty}/{$orig_qty})";
                    } elseif ($return_type === 'partial') {
                        $key = 'item_' . $item_id;
                        $submitted = isset($selected_items[$key]) ? (int)$selected_items[$key] : 0;
                        if ($submitted <= 0) {
                            $reasons[] = "{$name}: qty not entered ({$remaining} available)";
                        }
                    } else {
                        $reasons[] = "{$name}: qty={$orig_qty}, already_returned={$already_qty}, remaining={$remaining}";
                    }
                }
            }
            $detail = !empty($reasons) ? ' Details: ' . implode('; ', $reasons) : '';
            throw new Exception('No returnable items found.' . $detail);
        }

        // Calculate return total with tax
        $original_total    = (float)$sale_row['total'];
        $original_tax      = (float)($sale_row['tax_amount'] ?? $sale_row['tax'] ?? 0);
        $original_subtotal = (float)($sale_row['subtotal'] ?? ($original_total - $original_tax));

        if ($return_subtotal <= 0 && $return_type === 'full') {
            $return_total = $original_total;
        } elseif ($return_subtotal <= 0) {
            throw new Exception('Could not calculate return amount — item prices are missing.');
        } else {
            $pct          = $original_subtotal > 0 ? ($return_subtotal / $original_subtotal) : 1.0;
            $return_tax   = $original_tax * $pct;
            $return_total = $return_subtotal + $return_tax;
        }

        $return_number = 'R' . date('Ymd') . '-' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Insert return record
        $_ret_has_tenant    = false;
        $_ret_has_refund_method = false;
        try { $r = $pdo->query("SHOW COLUMNS FROM returns LIKE 'tenant_id'"); $_ret_has_tenant = $r->rowCount() > 0; } catch (Exception $_e) {}
        try { $r = $pdo->query("SHOW COLUMNS FROM returns LIKE 'refund_method'"); $_ret_has_refund_method = $r->rowCount() > 0; } catch (Exception $_e) {}

        if ($_ret_has_tenant) {
            $rfm_col = $_ret_has_refund_method ? ', refund_method' : '';
            $rfm_ph  = $_ret_has_refund_method ? ', ?' : '';
            $stmt = $pdo->prepare("
                INSERT INTO returns (
                    return_number, return_type, sale_id, customer_id, branch_id, processed_by,
                    reason{$rfm_col}, notes, amount, status, tenant_id, created_at
                ) VALUES (?, 'sales', ?, ?, ?, ?, ?{$rfm_ph}, ?, ?, 'completed', ?, NOW())
            ");
            $params = [$return_number, $sale_id, $sale_row['customer_id'],
                $user_branch ?: ($sale_row['branch_id'] ?? null),
                $user_id, $reason];
            if ($_ret_has_refund_method) $params[] = $refund_method;
            array_push($params, $notes, $return_total, $tenant_id);
            $stmt->execute($params);
        } else {
            $rfm_col = $_ret_has_refund_method ? ', refund_method' : '';
            $rfm_ph  = $_ret_has_refund_method ? ', ?' : '';
            $stmt = $pdo->prepare("
                INSERT INTO returns (
                    return_number, return_type, sale_id, customer_id, branch_id, processed_by,
                    reason{$rfm_col}, notes, amount, status, created_at
                ) VALUES (?, 'sales', ?, ?, ?, ?, ?{$rfm_ph}, ?, ?, 'completed', NOW())
            ");
            $params = [$return_number, $sale_id, $sale_row['customer_id'],
                $user_branch ?: ($sale_row['branch_id'] ?? null),
                $user_id, $reason];
            if ($_ret_has_refund_method) $params[] = $refund_method;
            array_push($params, $notes, $return_total);
            $stmt->execute($params);
        }
        $return_id = (int) $pdo->lastInsertId();

        // Insert return items and update inventory
        $_ri_has_tenant = false;
        $_inv_has_tenant = false;
        try { $r = $pdo->query("SHOW COLUMNS FROM return_items LIKE 'tenant_id'"); $_ri_has_tenant = $r->rowCount() > 0; } catch (Exception $_e) {}
        try { $r = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'tenant_id'"); $_inv_has_tenant = $r->rowCount() > 0; } catch (Exception $_e) {}

        if ($_ri_has_tenant) {
            $stmt_item = $pdo->prepare("
                INSERT INTO return_items (tenant_id, return_id, sale_item_id, product_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmt_item = $pdo->prepare("
                INSERT INTO return_items (return_id, sale_item_id, product_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
        }

        foreach ($return_items as $ri) {
            $ri_line = $ri['quantity'] * $ri['price'];
            if ($_ri_has_tenant) {
                $stmt_item->execute([$tenant_id, $return_id, $ri['id'], $ri['product_id'], $ri['quantity'], $ri['price'], $ri_line]);
            } else {
                $stmt_item->execute([$return_id, $ri['id'], $ri['product_id'], $ri['quantity'], $ri['price'], $ri_line]);
            }
            
            // Update inventory if restock is enabled
            if ($restock && $user_branch > 0) {
                if ($_inv_has_tenant) {
                    $stmt_check = $pdo->prepare("SELECT 1 FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
                    $stmt_check->execute([$ri['product_id'], $user_branch, $tenant_id]);
                    if ($stmt_check->fetchColumn()) {
                        $stmt_upd = $pdo->prepare("UPDATE inventory SET stock = stock + ? WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
                        $stmt_upd->execute([$ri['quantity'], $ri['product_id'], $user_branch, $tenant_id]);
                    } else {
                        $stmt_ins = $pdo->prepare("INSERT INTO inventory (product_id, branch_id, stock, tenant_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                        $stmt_ins->execute([$ri['product_id'], $user_branch, $ri['quantity'], $tenant_id]);
                    }
                } else {
                    $stmt_ins = $pdo->prepare("INSERT INTO inventory (product_id, branch_id, stock, tenant_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                    $stmt_ins->execute([$ri['product_id'], $user_branch, $ri['quantity'], $tenant_id]);
                }
            }
        }

        // Update sale status if full return
        if ($return_type === 'full') {
            if ($_post_s_has_tenant) {
                $stmt = $pdo->prepare("UPDATE sales SET status = 'returned' WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$sale_id, $tenant_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE sales SET status = 'returned' WHERE id = ?");
                $stmt->execute([$sale_id]);
            }
        }

        $pdo->commit();

        $success_return_number = $return_number;
        $message      = "Return processed successfully! Return #: {$return_number}";
        $message_type = 'success';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message      = $e->getMessage();
        $message_type = 'error';
        error_log('Return error: ' . $e->getMessage());
    }
}

render_page:

$returnable_count = 0;

// Load sale + items for display
$sale = null;
$items = [];
try {
    $_s_has_tenant = false;
    try { $r = $pdo->query("SHOW COLUMNS FROM sales LIKE 'tenant_id'"); $_s_has_tenant = $r->rowCount() > 0; } catch (Exception $_e) {}

    $s_where  = $_s_has_tenant ? 'WHERE s.id = ? AND s.tenant_id = ? AND s.status IN (\'completed\', \'returned\')' : 'WHERE s.id = ? AND s.status IN (\'completed\', \'returned\')';
    $s_params = $_s_has_tenant ? [$sale_id, $tenant_id] : [$sale_id];

    $stmt = $pdo->prepare("
        SELECT s.*, c.name AS customer_name, c.phone AS customer_phone,
               u.name AS cashier_name, b.name AS branch_name
        FROM sales s
        LEFT JOIN customers c ON c.id = s.customer_id
        LEFT JOIN users u     ON u.id = s.user_id
        LEFT JOIN branches b  ON b.id = s.branch_id
        {$s_where}
    ");
    $stmt->execute($s_params);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sale) {
        $_inv_has_tenant = false;
        try { $r = $pdo->query("SHOW COLUMNS FROM inventory LIKE 'tenant_id'"); $_inv_has_tenant = $r->rowCount() > 0; } catch (Exception $_e) {}
        $inv_and = $_inv_has_tenant ? 'AND i.tenant_id = ' . (int)$tenant_id : '';

        $_si_has_sub = false;
        try { $r = $pdo->query("SHOW COLUMNS FROM sale_items LIKE 'subtotal'"); $_si_has_sub = $r->rowCount() > 0; } catch (Exception $_e) {}
        $si_sub_col = $_si_has_sub ? 'COALESCE(si.subtotal, si.price * si.quantity)' : 'si.price * si.quantity';

        $inv_branch_val = $user_branch > 0 ? (int)$user_branch : 0;

        $stmt = $pdo->prepare("
            SELECT si.id, si.product_id, si.quantity, si.price,
                   {$si_sub_col} AS line_total,
                   COALESCE(NULLIF(si.product_name,''), p.name, CONCAT('Product #', si.product_id)) AS product_name,
                   COALESCE(si.product_sku, p.sku) AS sku,
                   COALESCE(i.stock, 0) AS current_stock
            FROM sale_items si
            LEFT JOIN products p ON p.id = si.product_id AND p.tenant_id = si.tenant_id
            LEFT JOIN inventory i ON i.product_id = si.product_id AND i.branch_id = {$inv_branch_val} {$inv_and}
            WHERE si.sale_id = ? AND si.tenant_id = ?
            ORDER BY si.id
        ");
        $stmt->execute([$sale_id, $tenant_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Return load error: ' . $e->getMessage());
}

if (!$sale) {
    ?>
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center mb-4">
            <i class="fas fa-exclamation-triangle text-2xl text-red-400"></i>
        </div>
        <h2 class="text-lg font-semibold text-white mb-1">Sale Not Found</h2>
        <p class="text-sm text-slate-400 mb-5">This sale does not exist or is not eligible for a return.</p>
        <a href="../all_sales.php" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-900 text-sm font-semibold rounded-lg transition-all">
            <i class="fas fa-arrow-left text-xs"></i> Back to Sales
        </a>
    </div>
    <?php
    $page_content = ob_get_clean();
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}

// Load already-returned quantities
$display_returned = [];
try {
    $dr_stmt = $pdo->prepare("
        SELECT ri.sale_item_id, COALESCE(SUM(ri.quantity),0) AS qty_returned
        FROM return_items ri
        JOIN returns ret ON ret.id = ri.return_id
        WHERE ri.sale_item_id IN (SELECT id FROM sale_items WHERE sale_id = ? AND tenant_id = ?)
          AND ret.tenant_id = ? AND ret.status = 'completed'
        GROUP BY ri.sale_item_id
    ");
    $dr_stmt->execute([$sale_id, $tenant_id, $tenant_id]);
    foreach ($dr_stmt->fetchAll(PDO::FETCH_ASSOC) as $dr) {
        $display_returned[(int)$dr['sale_item_id']] = (int)$dr['qty_returned'];
    }
} catch (Exception $_e) {}

$items_subtotal = 0.0;
$returnable_subtotal = 0.0;
$returnable_count = 0;
foreach ($items as $it) {
    $line = isset($it['line_total']) && (float)$it['line_total'] > 0
        ? (float)$it['line_total']
        : (float)$it['price'] * (int)$it['quantity'];
    $items_subtotal += $line;
    $item_id = (int)$it['id'];
    $already = $display_returned[$item_id] ?? 0;
    $max_ret = max(0, (int)$it['quantity'] - $already);
    if ($max_ret > 0) {
        $unit = $line / ((int)$it['quantity'] ?: 1);
        $returnable_subtotal += $unit * $max_ret;
        $returnable_count += $max_ret;
    }
}
$original_total  = (float)($sale['total'] ?? $items_subtotal);
$return_baseline = $returnable_subtotal > 0 ? $returnable_subtotal : $original_total;
$csrf_token      = generate_csrf_token('return_sale');

$st        = $sale['status'] ?? 'completed';
$pm_map    = ['cash'=>'Cash','card'=>'Card','mpesa'=>'M-Pesa','bank_transfer'=>'Bank Transfer','credit'=>'Credit','split'=>'Split'];
$pm_label  = $pm_map[$sale['payment_method'] ?? ''] ?? ucwords(str_replace('_',' ',$sale['payment_method'] ?? 'Cash'));
$inv_label = $sale['invoice_number'] ?: ('#' . str_pad((string)$sale['id'], 6, '0', STR_PAD_LEFT));
?>

<div class="space-y-4">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fas fa-undo-alt text-amber-400"></i> Return Sale
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Process refund &bull; Restock inventory &bull; Full or partial return</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="../all_sales.php"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
                <i class="fas fa-arrow-left text-xs"></i> Back to Sales
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="mb-5 flex items-start gap-3 px-4 py-3.5 rounded-xl text-sm border
        <?php echo $message_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-300'; ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> text-base mt-px shrink-0"></i>
        <div class="flex-1">
            <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
            <?php if ($success_return_number): ?>
            <div class="mt-2">
                <a href="../all_sales.php"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/30 text-emerald-300 text-xs font-medium rounded-lg transition-all">
                    <i class="fas fa-list text-xs"></i> View All Sales
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sale Summary Card -->
    <?php
    $badge_cls = $st === 'returned' ? 'bg-red-500/15 text-red-400 ring-1 ring-red-500/30' : 'bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/30';
    $badge_dot = $st === 'returned' ? 'bg-red-400' : 'bg-emerald-400';
    ?>
    <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-5 hover:border-slate-600/80 transition-colors">
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-700/40">
            <h2 class="text-sm font-semibold text-slate-300 flex items-center gap-2">
                <i class="fas fa-receipt text-amber-400 text-xs"></i> Original Sale
            </h2>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badge_cls; ?>">
                <span class="w-1.5 h-1.5 rounded-full <?php echo $badge_dot; ?>"></span>
                <?php echo ucfirst($st); ?>
            </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-x-4 gap-y-4">
            <div>
                <p class="text-xs text-slate-500 mb-1">Invoice</p>
                <p class="text-sm font-bold text-amber-400 font-mono"><?php echo htmlspecialchars($inv_label); ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Date &amp; Time</p>
                <p class="text-sm text-white"><?php echo date('d M Y', strtotime($sale['created_at'])); ?></p>
                <p class="text-xs text-slate-500"><?php echo date('H:i', strtotime($sale['created_at'])); ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Customer</p>
                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></p>
                <?php if (!empty($sale['customer_phone'])): ?>
                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($sale['customer_phone']); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Cashier</p>
                <p class="text-sm text-white"><?php echo htmlspecialchars($sale['cashier_name'] ?? '—'); ?></p>
                <?php if (!empty($sale['branch_name'])): ?>
                <p class="text-xs text-slate-500"><?php echo htmlspecialchars($sale['branch_name']); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Payment</p>
                <p class="text-sm text-white"><?php echo htmlspecialchars($pm_label); ?></p>
                <?php if (!empty($sale['reference'])): ?>
                <p class="text-xs text-slate-500 font-mono truncate" title="<?php echo htmlspecialchars($sale['reference']); ?>"><?php echo htmlspecialchars($sale['reference']); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Sale Total</p>
                <p class="text-xl font-bold text-amber-400"><?php echo $currency . ' ' . number_format($original_total, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Already Returned State -->
    <?php if ($st === 'returned'): ?>
    <div class="flex items-center gap-4 px-5 py-4 rounded-xl bg-amber-500/10 border border-amber-500/30">
        <div class="w-10 h-10 rounded-full bg-amber-500/15 flex items-center justify-center shrink-0">
            <i class="fas fa-ban text-amber-400 text-lg"></i>
        </div>
        <div>
            <p class="font-semibold text-sm text-amber-300">Already Returned</p>
            <p class="text-xs text-amber-400/70 mt-0.5">This sale has been fully returned and cannot be processed again.</p>
        </div>
    </div>

    <!-- Success State -->
    <?php elseif ($success_return_number): ?>
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-20 h-20 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mb-5">
            <i class="fas fa-check-circle text-4xl text-emerald-400"></i>
        </div>
        <h2 class="text-xl font-bold text-white mb-1">Return Processed Successfully</h2>
        <p class="text-sm text-slate-400 mb-2">Return reference:</p>
        <p class="text-xl font-bold text-amber-400 font-mono mb-6"><?php echo htmlspecialchars($success_return_number); ?></p>
        <div class="flex gap-3">
            <a href="../all_sales.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-900 text-sm font-bold rounded-xl transition-all">
                <i class="fas fa-list text-xs"></i> All Sales
            </a>
            <a href="../receipts/view_sale.php?id=<?php echo (int)$sale_id; ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-700 hover:bg-slate-600 active:scale-95 text-white text-sm font-medium rounded-xl transition-all">
                <i class="fas fa-eye text-xs"></i> View Sale
            </a>
        </div>
    </div>

    <!-- Return Form -->
    <?php else: ?>
    <form method="post" id="returnForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

            <!-- LEFT PANEL: Items Table -->
            <div class="xl:col-span-2">
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl overflow-hidden">

                    <!-- Return Type Toggle -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 border-b border-slate-700/50">
                        <div>
                            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                                <i class="fas fa-boxes text-amber-400 text-xs"></i> Items to Return
                            </h2>
                            <p id="qty-hint" class="text-xs text-amber-400/80 mt-0.5"><i class="fas fa-lock text-xs mr-1"></i>Qty locked in Full Return &mdash; switch to <strong>Partial Return</strong> to edit quantities</p>
                        </div>
                        <div class="flex bg-slate-900/60 border border-slate-700/50 rounded-xl p-1 gap-1 shrink-0">
                            <label for="rt_full" id="lbl_full"
                                   class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all bg-amber-500 text-slate-900">
                                <input type="radio" id="rt_full" name="return_type" value="full" checked class="sr-only">
                                <i class="fas fa-layer-group text-xs"></i> Full Return
                            </label>
                            <label for="rt_partial" id="lbl_partial"
                                   class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all text-slate-400 hover:text-white">
                                <input type="radio" id="rt_partial" name="return_type" value="partial" class="sr-only">
                                <i class="fas fa-scissors text-xs"></i> Partial Return
                            </label>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px] text-sm">
                            <thead>
                                <tr class="border-b border-slate-700/50 bg-slate-900/30">
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Product</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Sold</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Returned</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Qty</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-700/30">
                                <?php foreach ($items as $it):
                                    $item_id      = (int)$it['id'];
                                    $orig_qty     = (int)$it['quantity'];
                                    $already_qty  = $display_returned[$item_id] ?? 0;
                                    $max_ret      = max(0, $orig_qty - $already_qty);
                                    $line         = isset($it['line_total']) && (float)$it['line_total'] > 0
                                        ? (float)$it['line_total']
                                        : (float)$it['price'] * $orig_qty;
                                    $unit_line    = $line / ($orig_qty ?: 1);
                                    $returnable_line = $unit_line * $max_ret;
                                    $stock        = (int)$it['current_stock'];
                                    $fully_returned = $max_ret <= 0;
                                ?>
                                <tr class="hover:bg-slate-700/20 transition-colors <?php echo $fully_returned ? 'opacity-50' : ''; ?>">
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-white"><?php echo htmlspecialchars($it['product_name']); ?></p>
                                        <?php if (!empty($it['sku'])): ?>
                                        <p class="text-xs text-slate-500 font-mono mt-0.5">SKU: <?php echo htmlspecialchars($it['sku']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($fully_returned): ?>
                                        <p class="text-xs text-red-400 mt-0.5 font-medium">✓ Already returned</p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-700/60 text-white font-bold text-xs"><?php echo $orig_qty; ?></span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <?php if ($already_qty > 0): ?>
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-red-500/10 text-red-400 font-bold text-xs border border-red-500/20"><?php echo $already_qty; ?></span>
                                        <?php else: ?>
                                        <span class="text-slate-600 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-right text-slate-300 font-medium"><?php echo $currency . ' ' . number_format((float)$it['price'], 2); ?></td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                            <?php echo $stock > 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'; ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?php echo $stock > 0 ? 'bg-emerald-400' : 'bg-red-400'; ?>"></span>
                                            <?php echo $stock; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <input type="number"
                                               name="items[item_<?php echo $item_id; ?>]"
                                               value="<?php echo $max_ret; ?>"
                                               min="0" max="<?php echo $max_ret; ?>"
                                               <?php echo $fully_returned ? 'disabled' : ''; ?>
                                               data-price="<?php echo htmlspecialchars((string)(float)$it['price']); ?>"
                                               data-line="<?php echo htmlspecialchars((string)$returnable_line); ?>"
                                               class="return-qty w-20 px-2 py-1.5 text-center bg-slate-800 border border-slate-600 rounded-lg text-white text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500/60 focus:border-amber-500 disabled:opacity-30 disabled:cursor-not-allowed read-only:bg-slate-700/50 read-only:text-slate-400 read-only:cursor-not-allowed transition-all"
                                               oninput="recalc()">
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold text-amber-400 return-subtotal">
                                        <?php echo $currency . ' ' . number_format($returnable_line, 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-900/40 border-t-2 border-slate-700/60">
                                    <td colspan="6" class="px-5 py-3.5 text-right font-bold text-white">Return Total</td>
                                    <td class="px-5 py-3.5 text-right text-lg font-bold text-amber-400" id="returnTotal">
                                        <?php echo $currency . ' ' . number_format($return_baseline, 2); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Options -->
            <div class="xl:col-span-1 flex flex-col gap-4">

                <!-- Return Details Card -->
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-5 hover:border-slate-600/80 transition-colors">
                    <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                        <i class="fas fa-clipboard-list text-amber-400 text-xs"></i> Return Details
                    </h3>
                    
                    <div class="mb-4">
                        <label for="refund_method" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Refund Method <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <select id="refund_method" name="refund_method" required
                                    class="w-full appearance-none px-3 py-2.5 pr-9 bg-slate-800 border border-slate-600 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/60 focus:border-amber-500 transition-all cursor-pointer">
                                <option value="cash" style="background:#1e293b;color:#fff">Cash Refund</option>
                                <option value="mpesa" style="background:#1e293b;color:#fff">M-Pesa Refund</option>
                                <option value="card" style="background:#1e293b;color:#fff">Card Refund</option>
                                <option value="bank_transfer" style="background:#1e293b;color:#fff">Bank Transfer</option>
                                <option value="credit_note" style="background:#1e293b;color:#fff">Credit Note</option>
                                <option value="exchange" style="background:#1e293b;color:#fff">Exchange Item</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="return_reason" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Return Reason <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <select id="return_reason" name="reason" required
                                    class="w-full appearance-none px-3 py-2.5 pr-9 bg-slate-800 border border-slate-600 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/60 focus:border-amber-500 transition-all cursor-pointer">
                                <option value="" style="background:#1e293b;color:#94a3b8">— Select a reason —</option>
                                <option value="defective" style="background:#1e293b;color:#fff">Defective / Damaged</option>
                                <option value="wrong_item" style="background:#1e293b;color:#fff">Wrong Item Delivered</option>
                                <option value="customer_decision" style="background:#1e293b;color:#fff">Customer Changed Mind</option>
                                <option value="quality_issue" style="background:#1e293b;color:#fff">Quality Issue</option>
                                <option value="exchange" style="background:#1e293b;color:#fff">Exchange Request</option>
                                <option value="other" style="background:#1e293b;color:#fff">Other Reason</option>
                            </select>
                            <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="return_notes" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Additional Notes</label>
                        <textarea id="return_notes" name="notes" rows="3"
                                  class="w-full px-3 py-2.5 bg-slate-800 border border-slate-600 rounded-xl text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/60 focus:border-amber-500 resize-none transition-all"
                                  placeholder="Any additional details about this return..."></textarea>
                    </div>
                    
                    <label for="restock_cb" class="flex items-start gap-3 p-3.5 bg-slate-900/50 border border-slate-700/50 rounded-xl cursor-pointer hover:border-amber-500/30 hover:bg-amber-500/5 transition-all group">
                        <input type="checkbox" id="restock_cb" name="restock" value="1" checked class="w-4 h-4 mt-0.5 accent-amber-400 shrink-0 cursor-pointer">
                        <div>
                            <p class="text-sm font-semibold text-white group-hover:text-amber-400 transition-colors">Restock Items</p>
                            <p class="text-xs text-slate-500 mt-0.5">Add returned quantities back to inventory</p>
                        </div>
                    </label>
                </div>

                <!-- Summary Card -->
                <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-5 hover:border-slate-600/80 transition-colors">
                    <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                        <i class="fas fa-calculator text-amber-400 text-xs"></i> Return Summary
                    </h3>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex justify-between text-slate-400">
                            <span>Original Sale Amount</span>
                            <span class="text-white font-medium"><?php echo $currency . ' ' . number_format($original_total, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Items Being Returned</span>
                            <span class="text-white font-medium" id="itemsCount"><?php echo $returnable_count; ?></span>
                        </div>
                        <div class="border-t border-slate-700/60 pt-2.5 flex justify-between font-bold">
                            <span class="text-white">Refund Amount</span>
                            <span class="text-amber-400 text-xl" id="summaryTotal"><?php echo $currency . ' ' . number_format($return_baseline, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col gap-2">
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 active:scale-95 text-white text-sm font-bold rounded-xl transition-all shadow-lg shadow-red-900/30">
                        <i class="fas fa-undo-alt"></i> Process Return & Refund
                    </button>
                    <a href="receipts/view_sale.php?id=<?php echo (int)$sale_id; ?>"
                       class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-slate-700 hover:bg-slate-600 active:scale-95 text-slate-300 text-sm font-medium rounded-xl transition-all">
                        <i class="fas fa-times text-xs"></i> Cancel
                    </a>
                </div>

            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
const CURRENCY   = <?php echo json_encode($currency); ?>;
const SALE_TOTAL = <?php echo json_encode($original_total); ?>;

function fmtMoney(n) {
    return CURRENCY + ' ' + n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
}

function recalc() {
    let total = 0;
    let itemsCount = 0;
    
    document.querySelectorAll('.return-qty').forEach(function(input, i) {
        let qty = parseInt(input.value, 10) || 0;
        const max = parseInt(input.getAttribute('max'), 10) || 0;
        if (qty < 0) qty = 0;
        if (qty > max) { qty = max; input.value = qty; }
        
        if (qty > 0) itemsCount += qty;

        const price   = parseFloat(input.dataset.price) || 0;
        const rawLine = parseFloat(input.dataset.line)  || 0;
        const subtotal = rawLine > 0 ? (rawLine / (max || 1)) * qty : price * qty;

        const cell = document.querySelectorAll('.return-subtotal')[i];
        if (cell) cell.textContent = fmtMoney(subtotal);
        total += subtotal;
    });

    const isFullReturn = document.querySelector('input[name="return_type"]:checked')?.value === 'full';
    const display = (total === 0 && isFullReturn && SALE_TOTAL > 0) ? SALE_TOTAL : total;

    const el1 = document.getElementById('returnTotal');
    const el2 = document.getElementById('summaryTotal');
    const itemsCountEl = document.getElementById('itemsCount');
    
    if (el1) el1.textContent = fmtMoney(display);
    if (el2) el2.textContent = fmtMoney(display);
    if (itemsCountEl) itemsCountEl.textContent = itemsCount;
}

function setReturnType(isFull) {
    const lblFull    = document.getElementById('lbl_full');
    const lblPartial = document.getElementById('lbl_partial');
    const hint       = document.getElementById('qty-hint');

    if (lblFull && lblPartial) {
        if (isFull) {
            lblFull.classList.add('bg-amber-500', 'text-slate-900');
            lblFull.classList.remove('text-slate-400');
            lblPartial.classList.remove('bg-amber-500', 'text-slate-900');
            lblPartial.classList.add('text-slate-400');
        } else {
            lblFull.classList.remove('bg-amber-500', 'text-slate-900');
            lblFull.classList.add('text-slate-400');
            lblPartial.classList.add('bg-amber-500', 'text-slate-900');
            lblPartial.classList.remove('text-slate-400');
        }
    }

    if (hint) {
        if (isFull) {
            hint.innerHTML = '<i class="fas fa-lock text-xs mr-1"></i>Qty locked &mdash; switch to <strong>Partial Return</strong> to edit individual quantities';
            hint.className = 'text-xs text-amber-400/80 mt-0.5';
        } else {
            hint.innerHTML = '<i class="fas fa-pencil-alt text-xs mr-1"></i>Enter quantities to return for each item below';
            hint.className = 'text-xs text-emerald-400/80 mt-0.5';
        }
    }

    document.querySelectorAll('.return-qty').forEach(function(input) {
        if (input.disabled) return;
        if (isFull) {
            input.value = input.getAttribute('max');
            input.setAttribute('readonly', 'readonly');
            input.style.opacity = '0.5';
            input.style.cursor  = 'not-allowed';
        } else {
            input.removeAttribute('readonly');
            input.style.opacity = '1';
            input.style.cursor  = 'text';
        }
    });
    recalc();
}

// Event Listeners
document.querySelectorAll('input[name="return_type"]').forEach(function(radio) {
    radio.addEventListener('change', function() { setReturnType(this.value === 'full'); });
});

document.getElementById('returnForm')?.addEventListener('submit', function(e) {
    const reason = document.getElementById('return_reason')?.value;
    if (!reason) {
        e.preventDefault();
        document.getElementById('return_reason')?.focus();
        alert('Please select a reason for the return.');
        return;
    }

    const isPartial = document.querySelector('input[name="return_type"]:checked')?.value === 'partial';
    if (isPartial) {
        const totalQty = Array.from(document.querySelectorAll('.return-qty:not([disabled])'))
            .reduce(function(sum, inp) { return sum + (parseInt(inp.value, 10) || 0); }, 0);
        if (totalQty === 0) {
            e.preventDefault();
            alert('Please enter at least one item quantity to return.');
            return;
        }
    }

    if (!confirm('⚠️ Process this return? This action cannot be undone.')) {
        e.preventDefault();
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Return...';
    }
});

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    recalc();
    setReturnType(true);
});
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
?>