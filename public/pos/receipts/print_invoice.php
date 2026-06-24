<?php
/**
 * Print Invoice - Jakababa POS
 * Standalone print page — no layout wrapper
 */

$pathsFile = __DIR__ . '/../../../src/paths.php';
if (!file_exists($pathsFile)) $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
require_once $pathsFile;

safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
require_login();

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sale_id) { header('Location: ../all_sales.php'); exit; }

function inv_has_col(PDO $pdo, string $table, string $col): bool {
    try { $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $s->execute([$col]); return $s->rowCount() > 0; }
    catch (Exception $e) { return false; }
}

function convert_number_to_words($number): string {
    $dict = [0=>'zero',1=>'one',2=>'two',3=>'three',4=>'four',5=>'five',6=>'six',7=>'seven',
             8=>'eight',9=>'nine',10=>'ten',11=>'eleven',12=>'twelve',13=>'thirteen',14=>'fourteen',
             15=>'fifteen',16=>'sixteen',17=>'seventeen',18=>'eighteen',19=>'nineteen',20=>'twenty',
             30=>'thirty',40=>'forty',50=>'fifty',60=>'sixty',70=>'seventy',80=>'eighty',90=>'ninety',
             100=>'hundred',1000=>'thousand',1000000=>'million',1000000000=>'billion'];
    if (!is_numeric($number)) return '';
    $number = (int)abs($number);
    if ($number < 21)  return $dict[$number];
    if ($number < 100) { $t=((int)($number/10))*10; $u=$number%10; return $dict[$t].($u?'-'.$dict[$u]:''); }
    if ($number < 1000){ $h=(int)($number/100); $r=$number%100; return $dict[$h].' hundred'.($r?' and '.convert_number_to_words($r):''); }
    foreach ([1000000000,1000000,1000] as $base) {
        if ($number >= $base) { $q=(int)($number/$base); $r=$number%$base; return convert_number_to_words($q).' '.$dict[$base].($r?', '.convert_number_to_words($r):''); }
    }
    return '';
}

try {
    $pdo = get_db_connection();

    // Detect optional columns
    $cust_cols   = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    $branch_cols = $pdo->query("SHOW COLUMNS FROM branches")->fetchAll(PDO::FETCH_COLUMN);

    $cust_fields   = "c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email"
        . (in_array('address', $cust_cols)   ? ", c.address AS customer_address" : "")
        . (in_array('city',    $cust_cols)   ? ", c.city AS customer_city" : "");

    $branch_fields = "b.name AS branch_name"
        . (in_array('address',    $branch_cols) ? ", b.address AS branch_address" : "")
        . (in_array('phone',      $branch_cols) ? ", b.phone AS branch_phone" : "")
        . (in_array('email',      $branch_cols) ? ", b.email AS branch_email" : "")
        . (in_array('tax_number', $branch_cols) ? ", b.tax_number AS branch_tax_number" : "");

    $stmt = $pdo->prepare("SELECT s.*, $cust_fields, u.name AS cashier_name, $branch_fields
                            FROM sales s
                            LEFT JOIN customers c ON s.customer_id = c.id
                            LEFT JOIN users u ON s.user_id = u.id
                            LEFT JOIN branches b ON s.branch_id = b.id
                            WHERE s.id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) { header('Location: ../all_sales.php'); exit; }

    $stmt = $pdo->prepare("SELECT si.*, p.name AS product_name, p.sku
                            FROM sale_items si LEFT JOIN products p ON si.product_id = p.id
                            WHERE si.sale_id = ? ORDER BY si.id");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Company settings
    $company_name       = 'Jakababa POS';
    $company_address    = '';
    $company_phone      = '';
    $company_email      = '';
    $company_tax_number = '';
    $currency_symbol    = 'KES';
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('settings', $tables)) {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            $company_name       = $rows['company_name']    ?? $company_name;
            $company_address    = $rows['company_address'] ?? '';
            $company_phone      = $rows['company_phone']   ?? '';
            $company_email      = $rows['company_email']   ?? '';
            $company_tax_number = $rows['tax_number']      ?? '';
            $currency_symbol    = $rows['currency']        ?? 'KES';
        }
    } catch (Exception $e) {}

} catch (PDOException $e) {
    error_log('print_invoice: ' . $e->getMessage());
    die('Error loading invoice.');
}

$subtotal = array_sum(array_map(fn($i) => (float)$i['quantity'] * (float)$i['price'], $items));
$discount = (float)($sale['discount'] ?? 0);
$tax      = (float)($sale['tax_amount'] ?? $sale['tax'] ?? 0);
$total    = (float)($sale['total'] ?? 0);

$payment_map = [
    'cash'          => ['icon' => '💵', 'label' => 'Cash'],
    'card'          => ['icon' => '💳', 'label' => 'Card'],
    'mpesa'         => ['icon' => '📱', 'label' => 'M-Pesa'],
    'bank_transfer' => ['icon' => '🏦', 'label' => 'Bank Transfer'],
    'credit'        => ['icon' => '📋', 'label' => 'Credit'],
    'split'         => ['icon' => '⚡', 'label' => 'Split'],
];
$pm_key      = $sale['payment_method'] ?? 'cash';
$payment_info = $payment_map[$pm_key] ?? ['icon' => '💵', 'label' => ucwords(str_replace('_', ' ', $pm_key))];

$inv_number  = !empty($sale['invoice_number']) ? $sale['invoice_number'] : '#' . str_pad($sale_id, 6, '0', STR_PAD_LEFT);
$status      = $sale['status'] ?? 'pending';
$status_color = match($status) { 'completed' => '#059669', 'cancelled' => '#dc2626', 'draft' => '#6b7280', default => '#d97706' };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?php echo htmlspecialchars($inv_number); ?> — <?php echo htmlspecialchars($company_name); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:#e5e7eb;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:24px 16px;color:#1f2937}

/* Screen toolbar */
.toolbar{width:100%;max-width:210mm;display:flex;gap:10px;justify-content:flex-end;margin-bottom:14px}
.toolbar button,.toolbar a{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;font-size:0.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:background .15s}
.btn-print{background:#1e3a8a;color:#fff}.btn-print:hover{background:#1e40af}
.btn-back{background:#fff;color:#374151;border:1px solid #d1d5db}.btn-back:hover{background:#f9fafb}

/* Invoice card */
.invoice{width:100%;max-width:210mm;background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.12);border-radius:12px;overflow:hidden}

/* Header band */
.inv-header{background:#1e3a8a;color:#fff;padding:20px 24px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.inv-header .company h1{font-size:1.35rem;font-weight:700;letter-spacing:-.3px;margin-bottom:5px}
.inv-header .company p{font-size:0.8rem;opacity:.85;line-height:1.55}
.inv-header .meta{text-align:right;flex-shrink:0}
.inv-header .meta .word-invoice{font-size:1.6rem;font-weight:800;color:#fbbf24;letter-spacing:2px;text-transform:uppercase}
.inv-header .meta .inv-num{font-size:1rem;font-weight:600;margin-top:2px}
.inv-header .meta .inv-status{display:inline-block;margin-top:6px;padding:3px 12px;border-radius:20px;font-size:0.7rem;font-weight:700;text-transform:uppercase;background:<?php echo $status_color; ?>;color:#fff}

/* Body */
.inv-body{padding:22px 24px}

/* Info row */
.info-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;background:#f9fafb;border-radius:8px;padding:14px;margin-bottom:20px;font-size:0.82rem}
.info-col .ic-label{font-size:0.72rem;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.info-col p{color:#374151;margin-bottom:3px;line-height:1.45}
.payment-pill{display:inline-block;padding:4px 11px;background:#eff6ff;border-radius:20px;color:#1e3a8a;font-weight:600;font-size:0.78rem;margin-top:4px}

/* Items table */
table.items{width:100%;border-collapse:collapse;margin-bottom:18px;font-size:0.83rem}
table.items thead tr{background:#f3f4f6}
table.items th{padding:9px 8px;font-weight:600;color:#6b7280;text-transform:uppercase;font-size:0.72rem;letter-spacing:.3px;border-bottom:2px solid #e5e7eb}
table.items td{padding:8px;border-bottom:1px solid #f3f4f6;color:#1f2937;vertical-align:top}
table.items tbody tr:last-child td{border-bottom:none}
table.items tbody tr:nth-child(even) td{background:#fafafa}
.pname{font-weight:600;color:#1e3a8a}
.psku{font-size:0.7rem;color:#9ca3af;display:block;margin-top:1px}
.tr{text-align:right}.tc{text-align:center}

/* Totals */
.totals-wrap{display:flex;justify-content:flex-end;margin-bottom:16px}
.totals-box{width:260px;background:#f9fafb;border-radius:8px;padding:14px;font-size:0.83rem}
.tl{display:flex;justify-content:space-between;padding:5px 0;color:#4b5563;border-bottom:1px solid #f3f4f6}
.tl:last-child{border-bottom:none}
.tl.grand{font-weight:700;color:#1e3a8a;font-size:1rem;border-top:2px solid #e5e7eb;padding-top:10px;margin-top:4px;border-bottom:none}
.tl .disc{color:#059669}

/* Notes */
.inv-notes{background:#fffbeb;border-left:4px solid #fbbf24;border-radius:6px;padding:10px 14px;font-size:0.8rem;color:#92400e;margin-bottom:14px}

/* Amount in words */
.amount-words{font-size:0.78rem;color:#6b7280;border-top:1px dashed #e5e7eb;padding-top:10px;margin-top:4px}
.amount-words strong{color:#374151}

/* Footer */
.inv-footer{padding:11px 24px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;justify-content:space-between;align-items:center;font-size:0.75rem;color:#6b7280}
.inv-footer .valid{color:#059669;font-weight:600}

/* ── Print ── */
@media print{
    @page{size:A4;margin:1cm}
    body{background:#fff;padding:0;display:block}
    .toolbar{display:none!important}
    .invoice{max-width:100%;box-shadow:none;border-radius:0}
    .inv-header,.info-row,.totals-box,.inv-footer{-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .info-row,.items,.totals-box{page-break-inside:avoid}
    .inv-body{padding:14px 18px}
    table.items th,table.items td{padding:5px 7px}
}
@media(max-width:600px){
    .inv-header{flex-direction:column;gap:8px}
    .inv-header .meta{text-align:left}
    .info-row{grid-template-columns:1fr}
    .totals-wrap{justify-content:stretch}
    .totals-box{width:100%}
    .toolbar{flex-direction:column}
    .toolbar button,.toolbar a{justify-content:center}
}
</style>
</head>
<body>

<!-- Screen-only toolbar -->
<div class="toolbar no-print">
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Invoice
    </button>
    <a href="view_sale.php?id=<?php echo $sale_id; ?>" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Sale
    </a>
</div>

<div class="invoice">

    <!-- Header -->
    <div class="inv-header">
        <div class="company">
            <h1><?php echo htmlspecialchars($company_name); ?></h1>
            <p>
                <?php echo htmlspecialchars($company_address ?: 'Nairobi, Kenya'); ?><br>
                <?php echo htmlspecialchars($company_phone ?: ''); ?>
                <?php if (!empty($company_email)): ?><br><?php echo htmlspecialchars($company_email); ?><?php endif; ?>
                <?php if (!empty($company_tax_number)): ?><br>PIN: <?php echo htmlspecialchars($company_tax_number); ?><?php endif; ?>
            </p>
        </div>
        <div class="meta">
            <div class="word-invoice">INVOICE</div>
            <div class="inv-num"><?php echo htmlspecialchars($inv_number); ?></div>
            <div class="inv-status"><?php echo ucfirst($status); ?></div>
        </div>
    </div>

    <!-- Body -->
    <div class="inv-body">

        <!-- Info row -->
        <div class="info-row">
            <div class="info-col">
                <div class="ic-label"><i class="fas fa-user fa-xs"></i> Bill To</div>
                <p><strong><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></strong></p>
                <?php if (!empty($sale['customer_phone'])): ?>
                <p><i class="fas fa-phone fa-xs" style="opacity:.6;margin-right:4px"></i><?php echo htmlspecialchars($sale['customer_phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($sale['customer_email'])): ?>
                <p><i class="fas fa-envelope fa-xs" style="opacity:.6;margin-right:4px"></i><?php echo htmlspecialchars($sale['customer_email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($sale['customer_address'])): ?>
                <p><?php echo htmlspecialchars($sale['customer_address']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-col">
                <div class="ic-label"><i class="fas fa-calendar-alt fa-xs"></i> Invoice Details</div>
                <p><span style="color:#6b7280">Date:</span> <?php echo date('d M Y', strtotime($sale['created_at'])); ?></p>
                <p><span style="color:#6b7280">Time:</span> <?php echo date('H:i', strtotime($sale['created_at'])); ?></p>
                <p><span style="color:#6b7280">Cashier:</span> <?php echo htmlspecialchars($sale['cashier_name'] ?? 'System'); ?></p>
                <?php if (!empty($sale['branch_name'])): ?>
                <p><span style="color:#6b7280">Branch:</span> <?php echo htmlspecialchars($sale['branch_name']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-col">
                <div class="ic-label"><i class="fas fa-credit-card fa-xs"></i> Payment</div>
                <span class="payment-pill"><?php echo $payment_info['icon']; ?> <?php echo $payment_info['label']; ?></span>
                <?php if (!empty($sale['reference'])): ?>
                <p style="margin-top:7px"><span style="color:#6b7280">Ref:</span> <?php echo htmlspecialchars($sale['reference']); ?></p>
                <?php endif; ?>
                <?php if (!empty($sale['order_type'])): ?>
                <p><span style="color:#6b7280">Type:</span> <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $sale['order_type']))); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width:40px" class="tc">#</th>
                    <th>Description</th>
                    <th class="tc" style="width:60px">Qty</th>
                    <th class="tr" style="width:100px">Unit Price</th>
                    <th class="tr" style="width:110px">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af">No items</td></tr>
                <?php else: ?>
                <?php $n = 1; foreach ($items as $item): $line = (float)$item['quantity'] * (float)$item['price']; ?>
                <tr>
                    <td class="tc" style="color:#9ca3af"><?php echo $n++; ?></td>
                    <td>
                        <span class="pname"><?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?></span>
                        <?php if (!empty($item['sku'])): ?><span class="psku">SKU: <?php echo htmlspecialchars($item['sku']); ?></span><?php endif; ?>
                    </td>
                    <td class="tc"><?php echo (int)$item['quantity']; ?></td>
                    <td class="tr"><?php echo $currency_symbol; ?> <?php echo number_format((float)$item['price'], 2); ?></td>
                    <td class="tr" style="font-weight:600"><?php echo $currency_symbol; ?> <?php echo number_format($line, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-wrap">
            <div class="totals-box">
                <div class="tl"><span>Subtotal</span><span><?php echo $currency_symbol; ?> <?php echo number_format($subtotal, 2); ?></span></div>
                <?php if ($discount > 0): ?>
                <div class="tl"><span>Discount</span><span class="disc">-<?php echo $currency_symbol; ?> <?php echo number_format($discount, 2); ?></span></div>
                <?php endif; ?>
                <?php if ($tax > 0): ?>
                <div class="tl"><span>Tax (<?php echo (float)($sale['tax_rate'] ?? 16); ?>%)</span><span><?php echo $currency_symbol; ?> <?php echo number_format($tax, 2); ?></span></div>
                <?php endif; ?>
                <div class="tl grand"><span>Grand Total</span><span><?php echo $currency_symbol; ?> <?php echo number_format($total, 2); ?></span></div>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($sale['notes'])): ?>
        <div class="inv-notes"><i class="fas fa-sticky-note fa-sm" style="margin-right:6px"></i><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></div>
        <?php endif; ?>

        <!-- Amount in words -->
        <div class="amount-words">
            Amount in words: <strong><?php echo ucfirst(convert_number_to_words((int)$total)); ?> <?php echo $currency_symbol; ?> only</strong>
        </div>

    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <span><i class="far fa-clock" style="margin-right:4px"></i><?php echo date('d M Y, H:i'); ?></span>
        <span class="valid"><i class="fas fa-check-circle" style="margin-right:4px"></i>Valid Invoice</span>
        <span>Thank you for your business!</span>
    </div>

</div>

</body>
</html>
