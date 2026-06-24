<?php
/**
 * Print Quotation - JDH POS
 * Standalone print page — no layout wrapper
 */

$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) $pathsFile = dirname(__DIR__, 2) . '/src/paths.php';
require_once $pathsFile;

safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
require_login();

$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$quotation_id) { header('Location: list_quotation.php'); exit; }

$tenant_id = get_current_tenant_id();

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

    $stmt = $pdo->prepare("
        SELECT q.*,
               c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
               u.name AS created_by_name,
               b.name AS branch_name,
               bt.name AS business_type_name
        FROM quotations q
        LEFT JOIN customers c  ON q.customer_id      = c.id
        LEFT JOIN users u      ON q.created_by        = u.id
        LEFT JOIN branches b   ON q.branch_id         = b.id
        LEFT JOIN business_types bt ON q.business_type_id = bt.id
        WHERE q.id = ? AND q.tenant_id = ?
    ");
    $stmt->execute([$quotation_id, $tenant_id]);
    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quotation) { header('Location: list_quotation.php?error=not_found'); exit; }

    $stmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? AND branch_id = $current_branch_id ORDER BY id");
    $stmt->execute([$quotation_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Company settings
    $company_name    = 'JDH POS';
    $company_address = '';
    $company_phone   = '';
    $company_email   = '';
    $company_tax     = '';
    $currency_symbol = 'KSh';
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('settings', $tables)) {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            $company_name    = $rows['company_name']    ?? $company_name;
            $company_address = $rows['company_address'] ?? '';
            $company_phone   = $rows['company_phone']   ?? '';
            $company_email   = $rows['company_email']   ?? '';
            $company_tax     = $rows['tax_number']      ?? '';
            $currency_symbol = $rows['currency']        ?? 'KSh';
        }
    } catch (Exception $e) {}

} catch (PDOException $e) {
    error_log('print_quotation: ' . $e->getMessage());
    die('Error loading quotation.');
}

$total       = (float)($quotation['total'] ?? 0);
$status      = $quotation['status'] ?? 'draft';
$status_color = match($status) {
    'accepted' => '#059669',
    'rejected' => '#dc2626',
    'expired'  => '#6b7280',
    'sent'     => '#2563eb',
    default    => '#d97706',
};
$valid_until = !empty($quotation['valid_until']) ? date('d M Y', strtotime($quotation['valid_until'])) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotation <?php echo htmlspecialchars($quotation['quotation_number']); ?> — <?php echo htmlspecialchars($company_name); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{font-size:14px}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:#e5e7eb;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:24px 16px;color:#1f2937}

.toolbar{width:100%;max-width:210mm;display:flex;gap:10px;justify-content:flex-end;margin-bottom:14px}
.toolbar button,.toolbar a{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;font-size:0.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:background .15s}
.btn-print{background:#1e3a8a;color:#fff}.btn-print:hover{background:#1e40af}
.btn-back{background:#fff;color:#374151;border:1px solid #d1d5db}.btn-back:hover{background:#f9fafb}

.invoice{width:100%;max-width:210mm;background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.12);border-radius:12px;overflow:hidden}

.inv-header{background:#1e3a8a;color:#fff;padding:20px 24px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.inv-header .company h1{font-size:1.35rem;font-weight:700;letter-spacing:-.3px;margin-bottom:5px}
.inv-header .company p{font-size:0.8rem;opacity:.85;line-height:1.55}
.inv-header .meta{text-align:right;flex-shrink:0}
.inv-header .meta .word-invoice{font-size:1.6rem;font-weight:800;color:#fbbf24;letter-spacing:2px;text-transform:uppercase}
.inv-header .meta .inv-num{font-size:1rem;font-weight:600;margin-top:2px}
.inv-header .meta .inv-status{display:inline-block;margin-top:6px;padding:3px 12px;border-radius:20px;font-size:0.7rem;font-weight:700;text-transform:uppercase;background:<?php echo $status_color; ?>;color:#fff}

.inv-body{padding:22px 24px}

.info-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;background:#f9fafb;border-radius:8px;padding:14px;margin-bottom:20px;font-size:0.82rem}
.info-col .ic-label{font-size:0.72rem;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.info-col p{color:#374151;margin-bottom:3px;line-height:1.45}
.valid-pill{display:inline-block;padding:4px 11px;background:#fef3c7;border-radius:20px;color:#92400e;font-weight:600;font-size:0.78rem;margin-top:4px}

table.items{width:100%;border-collapse:collapse;margin-bottom:18px;font-size:0.83rem}
table.items thead tr{background:#f3f4f6}
table.items th{padding:9px 8px;font-weight:600;color:#6b7280;text-transform:uppercase;font-size:0.72rem;letter-spacing:.3px;border-bottom:2px solid #e5e7eb}
table.items td{padding:8px;border-bottom:1px solid #f3f4f6;color:#1f2937;vertical-align:top}
table.items tbody tr:last-child td{border-bottom:none}
table.items tbody tr:nth-child(even) td{background:#fafafa}
.pname{font-weight:600;color:#1e3a8a}
.tr{text-align:right}.tc{text-align:center}

.totals-wrap{display:flex;justify-content:flex-end;margin-bottom:16px}
.totals-box{width:260px;background:#f9fafb;border-radius:8px;padding:14px;font-size:0.83rem}
.tl{display:flex;justify-content:space-between;padding:5px 0;color:#4b5563;border-bottom:1px solid #f3f4f6}
.tl:last-child{border-bottom:none}
.tl.grand{font-weight:700;color:#1e3a8a;font-size:1rem;border-top:2px solid #e5e7eb;padding-top:10px;margin-top:4px;border-bottom:none}

.inv-notes{background:#fffbeb;border-left:4px solid #fbbf24;border-radius:6px;padding:10px 14px;font-size:0.8rem;color:#92400e;margin-bottom:12px}
.inv-terms{background:#eff6ff;border-left:4px solid #3b82f6;border-radius:6px;padding:10px 14px;font-size:0.8rem;color:#1e40af;margin-bottom:14px}

.amount-words{font-size:0.78rem;color:#6b7280;border-top:1px dashed #e5e7eb;padding-top:10px;margin-top:4px}
.amount-words strong{color:#374151}

.inv-footer{padding:11px 24px;border-top:1px solid #e5e7eb;background:#f9fafb;display:flex;justify-content:space-between;align-items:center;font-size:0.75rem;color:#6b7280}
.inv-footer .valid{color:#d97706;font-weight:600}

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

<div class="toolbar no-print">
    <button class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Quotation
    </button>
    <a href="view_quotation.php?id=<?php echo $quotation_id; ?>" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Quotation
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
                <?php if (!empty($company_tax)): ?><br>PIN: <?php echo htmlspecialchars($company_tax); ?><?php endif; ?>
            </p>
        </div>
        <div class="meta">
            <div class="word-invoice">QUOTATION</div>
            <div class="inv-num"><?php echo htmlspecialchars($quotation['quotation_number']); ?></div>
            <div class="inv-status"><?php echo ucfirst($status); ?></div>
        </div>
    </div>

    <!-- Body -->
    <div class="inv-body">

        <!-- Info row -->
        <div class="info-row">
            <div class="info-col">
                <div class="ic-label"><i class="fas fa-user fa-xs"></i> Prepared For</div>
                <p><strong><?php echo htmlspecialchars($quotation['customer_name'] ?? 'Walk-in Customer'); ?></strong></p>
                <?php if (!empty($quotation['customer_phone'])): ?>
                <p><i class="fas fa-phone fa-xs" style="opacity:.6;margin-right:4px"></i><?php echo htmlspecialchars($quotation['customer_phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($quotation['customer_email'])): ?>
                <p><i class="fas fa-envelope fa-xs" style="opacity:.6;margin-right:4px"></i><?php echo htmlspecialchars($quotation['customer_email']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-col">
                <div class="ic-label"><i class="fas fa-calendar-alt fa-xs"></i> Quotation Details</div>
                <p><span style="color:#6b7280">Date:</span> <?php echo date('d M Y', strtotime($quotation['created_at'])); ?></p>
                <p><span style="color:#6b7280">Prepared by:</span> <?php echo htmlspecialchars($quotation['created_by_name'] ?? 'System'); ?></p>
                <?php if (!empty($quotation['branch_name'])): ?>
                <p><span style="color:#6b7280">Branch:</span> <?php echo htmlspecialchars($quotation['branch_name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($quotation['business_type_name'])): ?>
                <p><span style="color:#6b7280">Type:</span> <?php echo htmlspecialchars($quotation['business_type_name']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-col">
                <div class="ic-label"><i class="fas fa-clock fa-xs"></i> Valid Until</div>
                <span class="valid-pill"><i class="fas fa-calendar-check fa-xs" style="margin-right:4px"></i><?php echo $valid_until; ?></span>
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
                    <td><span class="pname"><?php echo htmlspecialchars($item['product_name'] ?? 'Item'); ?></span></td>
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
                <div class="tl grand"><span>Grand Total</span><span><?php echo $currency_symbol; ?> <?php echo number_format($total, 2); ?></span></div>
                <div class="amount-words">
                    Amount in words: <strong><?php echo ucfirst(convert_number_to_words((int)$total)); ?> <?php echo $currency_symbol; ?> only</strong>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($quotation['notes'])): ?>
        <div class="inv-notes"><i class="fas fa-sticky-note fa-sm" style="margin-right:6px"></i><?php echo nl2br(htmlspecialchars($quotation['notes'])); ?></div>
        <?php endif; ?>

        <!-- Terms -->
        <?php if (!empty($quotation['terms'])): ?>
        <div class="inv-terms"><i class="fas fa-file-contract fa-sm" style="margin-right:6px"></i><strong>Terms &amp; Conditions:</strong><br><?php echo nl2br(htmlspecialchars($quotation['terms'])); ?></div>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <span><i class="far fa-clock" style="margin-right:4px"></i><?php echo date('d M Y, H:i'); ?></span>
        <span class="valid"><i class="fas fa-hourglass-half" style="margin-right:4px"></i>Valid Until: <?php echo $valid_until; ?></span>
        <span>Thank you for your business!</span>
    </div>

</div>

</body>
</html>
