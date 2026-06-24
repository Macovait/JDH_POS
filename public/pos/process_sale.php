<?php
/**
 * POS Process Sale - AJAX Endpoint (Demo Mode)
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

$pdo = get_db_connection();
$tenant_id = (int) ($_SESSION['tenant_id'] ?? 1);
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);
$user_id   = (int) ($_SESSION['user_id'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Release session lock — we only read auth context above
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

// CSRF verification
if (!isset($input['csrf_token']) || $input['csrf_token'] !== $csrf_token) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}
    if (!is_array($input)) {
        $input = $_POST;
    }
    $csrf_token = $_SESSION['csrf_token'] ?? '';

    $items = $input['items'] ?? [];
    if (empty($items)) {
        echo json_encode(['success' => false, 'error' => 'Cart is empty']);
        exit;
    }

    $payment_method = $input['payment_method'] ?? 'cash';
    $total = (float) ($input['total'] ?? 0);
    $discount = (float) ($input['discount'] ?? 0);

    // Build item map for batch stock check
    $productIds = [];
    $itemMap = [];
    $subtotal = 0;
    foreach ($items as $item) {
        $pid = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 1);
        $price = (float) ($item['price'] ?? 0);
        $line_total = $qty * $price;
        $subtotal += $line_total;
        $productIds[] = $pid;
        $itemMap[$pid] = [
            'product_id' => $pid,
            'product_name' => $item['name'] ?? '',
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => $line_total
        ];
    }

    // Batch stock validation — single query instead of N+1
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stockStmt = $pdo->prepare("SELECT product_id, stock FROM inventory WHERE tenant_id = ? AND branch_id = ? AND product_id IN ($placeholders)");
    $stockStmt->execute(array_merge([$tenant_id, $branch_id], $productIds));
    $stockMap = [];
    while ($r = $stockStmt->fetch(PDO::FETCH_ASSOC)) {
        $stockMap[(int)$r['product_id']] = (int) $r['stock'];
    }
    foreach ($itemMap as $pid => $item) {
        $available = $stockMap[$pid] ?? 0;
        if ($available < $item['quantity']) {
            echo json_encode([
                'success' => false,
                'error' => "Insufficient stock for {$item['product_name']}. Available: {$available}, Requested: {$item['quantity']}"
            ]);
            exit;
        }
    }

    $tax_rate = 16;
    $tax_amount = $subtotal * ($tax_rate / 100);
    $grand_total = $subtotal + $tax_amount - $discount;

    // Invoice number via sequence table (avoids full table scan on sales)
    $year = date('Y');
    $month = date('m');
    $pdo->exec("CREATE TABLE IF NOT EXISTS `invoice_sequences` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `tenant_id` int(11) NOT NULL,
        `branch_id` int(11) NOT NULL,
        `year` int(4) NOT NULL,
        `month` int(2) NOT NULL,
        `last_number` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_sequence` (`tenant_id`,`branch_id`,`year`,`month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $seqStmt = $pdo->prepare("INSERT INTO invoice_sequences (tenant_id, branch_id, year, month, last_number, updated_at) VALUES (?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE last_number = last_number + 1, updated_at = NOW()");
    $seqStmt->execute([$tenant_id, $branch_id, $year, $month]);

    $seqFetch = $pdo->prepare("SELECT last_number FROM invoice_sequences WHERE tenant_id = ? AND branch_id = ? AND year = ? AND month = ?");
    $seqFetch->execute([$tenant_id, $branch_id, $year, $month]);
    $seqRow = $seqFetch->fetch(PDO::FETCH_ASSOC);
    $next_num = str_pad($seqRow['last_number'], 4, '0', STR_PAD_LEFT);
    $invoice_number = "INV-{$year}{$month}-{$next_num}";

    $amount_tendered = $total;
    $change_amount = max(0, $amount_tendered - $grand_total);
    $due_amount = max(0, $grand_total - $amount_tendered);
    $balance_due = $due_amount;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO sales (
        tenant_id, branch_id, user_id, customer_id, invoice_number,
        subtotal, discount, tax_rate, tax_amount, total,
        payment_method, due_amount, balance_due,
        status, notes, created_at
    ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', 'Demo sale', NOW())");
    $stmt->execute([$tenant_id, $branch_id, $user_id, $invoice_number, $subtotal, $discount, $tax_rate, $tax_amount, $grand_total, $payment_method, $due_amount, $balance_due]);
    $sale_id = $pdo->lastInsertId();

    // Batch insert sale items
    $saleItemSql = "INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity, price, subtotal) VALUES ";
    $saleItemVals = [];
    $saleItemParams = [];
    foreach ($itemMap as $item) {
        $saleItemVals[] = "(?, ?, ?, ?, ?, ?)";
        array_push($saleItemParams, $tenant_id, $sale_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['line_total']);
    }
    if (!empty($saleItemVals)) {
        $pdo->prepare($saleItemSql . implode(',', $saleItemVals))->execute($saleItemParams);
    }

    // Batch inventory deduction with CASE to avoid N+1 updates
    $caseParts = [];
    $invParams = [];
    foreach ($itemMap as $pid => $item) {
        $caseParts[] = "WHEN ? THEN GREATEST(stock - ?, 0)";
        array_push($invParams, $pid, $item['quantity']);
    }
    $invParams[] = $tenant_id;
    $invParams[] = $branch_id;
    $invSql = "UPDATE inventory SET stock = CASE product_id " . implode(' ', $caseParts) . " ELSE stock END, updated_at = NOW() WHERE tenant_id = ? AND branch_id = ? AND product_id IN ($placeholders)";
    $pdo->prepare($invSql)->execute(array_merge($invParams, $productIds));

    $pdo->commit();

    // Clear PosBootstrapService caches so next POS load sees fresh data
    try {
        require_once __DIR__ . '/../../src/Pos/PosBootstrapService.php';
        $boot = new \App\Pos\PosBootstrapService($pdo, (int)$tenant_id, (int)$branch_id);
        $boot->invalidate('today_stats');
        $boot->invalidate('held_count');
        $boot->invalidate('products');
    } catch (Exception $e) {
        error_log('Cache invalidation error: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'sale_id' => $sale_id,
        'invoice_number' => $invoice_number,
        'total' => $grand_total,
        'receipt_id' => $invoice_number,
        'receipt_url' => 'receipts/receipt.php?id=' . $sale_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}