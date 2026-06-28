<?php
/**
 * Compact Receipt page for Jakababa POS - PURE TAILWIND EDITION
 * Optimized for thermal receipt printers (80mm width)
 * URL: http://localhost/JDH_POS/public/pos/receipt.php?id=123&print=1
 */

// Bootstrap paths and core dependencies
$pathsFile = __DIR__ . '/../../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
require_login();

$pdo = get_db_connection();

// Get sale ID from URL
$sale_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$sale_id) {
    die('Invalid receipt request');
}

// Get current user info
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$current_branch_id = get_current_branch_id();

// Fetch sale details
try {
    // Get sale information
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            c.name as customer_name,
            u.name as cashier_name,
            b.name as branch_name,
            b.tax_rate as branch_tax_rate,
            b.phone as branch_phone,
            b.address as branch_address,
            b.email as branch_email
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN branches b ON s.branch_id = b.id
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        die('Sale not found');
    }

    // Check if user has access to this branch's sales
    if (!is_super_admin() && $sale['branch_id'] != $current_branch_id) {
        die('You do not have permission to view this receipt');
    }

    // Get sale items
    $stmt = $pdo->prepare("
        SELECT 
            si.*,
            p.name as product_name
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY si.id
    ");
    $stmt->execute([$sale_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $subtotal = (float) ($sale['subtotal'] ?? 0);
    $discount = (float) ($sale['discount'] ?? 0);
    $tax = (float) ($sale['tax'] ?? 0);
    $total = (float) ($sale['total'] ?? 0);

    // Get company settings
    $company_name = 'Jakababa POS';
    $company_phone = '';
    $company_address = '';
    $company_email = '';
    $company_logo = '';
    $vat_number = '';
    $pin_number = '';
    $fiscal_stand = '';
    $receipt_description = '';
    $has_company_phone = false;
    $has_company_address = false;
    $has_company_email = false;

    $tenant_id = null;
    if (function_exists('get_current_tenant_id')) {
        $tenant_id = get_current_tenant_id();
    } elseif (!empty($_SESSION['tenant_id'])) {
        $tenant_id = (int) $_SESSION['tenant_id'];
    } elseif (!empty($_SESSION['user']['tenant_id'])) {
        $tenant_id = (int) $_SESSION['user']['tenant_id'];
    }

    try {
        $setting_keys = ['company_name','company_phone','company_address','company_email','company_logo','vat_number','pin_number','fiscal_stand','receipt_description'];
        if ($tenant_id) {
            $placeholders = implode(',', array_fill(0, count($setting_keys), '?'));
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE tenant_id = ? AND setting_key IN ({$placeholders})");
            $stmt->execute(array_merge([$tenant_id], $setting_keys));
        } else {
            $placeholders = implode(',', array_map(fn($k) => $pdo->quote($k), $setting_keys));
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$placeholders})");
        }
        while ($row = $stmt->fetch()) {
            switch ($row['setting_key']) {
                case 'company_name':    $company_name    = $row['setting_value']; break;
                case 'company_phone':   $company_phone   = $row['setting_value']; $has_company_phone = true; break;
                case 'company_address': $company_address = $row['setting_value']; $has_company_address = true; break;
                case 'company_email':   $company_email   = $row['setting_value']; $has_company_email = true; break;
                case 'company_logo':    $company_logo    = $row['setting_value']; break;
                case 'vat_number':      $vat_number      = $row['setting_value']; break;
                case 'pin_number':      $pin_number      = $row['setting_value']; break;
                case 'fiscal_stand':    $fiscal_stand    = $row['setting_value']; break;
                case 'receipt_description': $receipt_description = $row['setting_value']; break;
            }
        }
    } catch (Exception $e) {
        // Use defaults
    }

    // Use branch info as fallback
    $addr = $has_company_address ? $company_address : ($sale['branch_address'] ?? '');
    $phn  = $has_company_phone   ? $company_phone   : ($sale['branch_phone'] ?? '');
    $eml  = $has_company_email   ? $company_email   : ($sale['branch_email'] ?? '');

    // Barcode for invoice number
    $barcodeSvg = '';
    try {
        $generator = new Picqer\Barcode\BarcodeGeneratorSVG();
        $barcodeSvg = $generator->getBarcode($sale['invoice_number'], $generator::TYPE_CODE_128, 2, 40);
    } catch (Exception $e) {
        // Barcode library unavailable
    }

    // Resolve logo URL
    $logo_url = '';
    if (!empty($company_logo)) {
        $logo_url = base_url($company_logo);
    }

    // Build QR code payload
    $receipt_no = htmlspecialchars($sale['invoice_number'] ?? 'N/A');
    $store_name = htmlspecialchars($sale['branch_name'] ?? 'Main Branch');
    $date_str   = date('d/m/Y H:i', strtotime($sale['created_at']));
    $qr_payload = "Receipt No: {$receipt_no}\nStore: {$store_name}\nDate: {$date_str}\nTotal: KSh " . number_format($total, 0);

    // Generate QR code
    $qrDataUri = '';
    try {
        $writer  = new Endroid\QrCode\Writer\PngWriter();
        $qrCode  = new Endroid\QrCode\QrCode(
            data: $qr_payload,
            size: 120,
            margin: 3,
            encoding: new Endroid\QrCode\Encoding\Encoding('UTF-8'),
            errorCorrectionLevel: Endroid\QrCode\ErrorCorrectionLevel::Low
        );
        $qrResult = $writer->write($qrCode);
        $qrDataUri = $qrResult->getDataUri();
    } catch (Exception $e) {
        $qr_error = '[Receipt QR] ' . $e->getMessage();
        error_log($qr_error);
        $qrDataUri = '';
    }

} catch (Exception $e) {
    error_log("Error fetching receipt: " . $e->getMessage());
    die('Error loading receipt');
}

$page_title = 'Receipt | Jakababa POS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    
    <!-- Tailwind CSS CDN -->
    <link rel="stylesheet" href="<?php echo asset_url('css/app.css'); ?>">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Thermal receipt printer optimization - only what Tailwind can't do */
        @media print {
            @page {
                margin: 0;
                size: auto;
            }
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 2mm 3mm;
            }
        }
        
        /* Override Tailwind for thermal printer width */
        .receipt-width {
            width: 80mm;
            max-width: 80mm;
        }
        
        /* QR code sizing */
        .qr-img {
            width: 28mm;
            height: 28mm;
        }
        
        /* Barcode styling */
        .barcode-svg svg {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body class="bg-gray-100 flex flex-col items-center py-4">

    <!-- Action Buttons (not printed) -->
    <div class="no-print flex gap-2 mb-3 receipt-width">
        <button onclick="window.print()" class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-all">
            <i class="fas fa-print"></i> Print
        </button>
        <button onclick="window.close()" class="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm font-medium transition-all">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <!-- Receipt Container - Fixed width for thermal printer -->
    <div class="receipt-container bg-white shadow-lg receipt-width mx-auto print:shadow-none">
        
        <!-- Store Header -->
        <div class="text-center px-2 py-3 border-b border-dashed border-gray-300">
            <?php if (!empty($logo_url)): ?>
                <div class="flex justify-center mb-2">
                    <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($company_name); ?>" class="max-h-16 object-contain">
                </div>
            <?php endif; ?>
            
            <div class="text-base font-bold uppercase tracking-wide"><?php echo htmlspecialchars($company_name); ?></div>
            
            <div class="text-xs text-gray-600 mt-1 leading-relaxed">
                <?php echo htmlspecialchars($sale['branch_name'] ?? 'Main Branch'); ?><br>
                <?php if ($addr): ?>
                    <?php echo htmlspecialchars($addr); ?><br>
                <?php endif; ?>
                <?php if ($phn): ?>
                    Tel: <?php echo htmlspecialchars($phn); ?><br>
                <?php endif; ?>
                <?php if ($eml): ?>
                    <?php echo htmlspecialchars($eml); ?>
                <?php endif; ?>
            </div>
            
            <?php if ($vat_number || $pin_number || $fiscal_stand): ?>
                <div class="mt-2 pt-2 border-t border-dashed border-gray-300 text-[10px] text-gray-600">
                    <?php if ($vat_number): ?>
                        <div>VAT No: <?php echo htmlspecialchars($vat_number); ?></div>
                    <?php endif; ?>
                    <?php if ($pin_number): ?>
                        <div>PIN: <?php echo htmlspecialchars($pin_number); ?></div>
                    <?php endif; ?>
                    <?php if ($fiscal_stand): ?>
                        <div class="font-bold uppercase text-[10px] mt-1">Stand of Fiscal Receipt</div>
                        <div><?php echo htmlspecialchars($fiscal_stand); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($receipt_description): ?>
                <div class="text-[10px] text-gray-500 italic mt-1"><?php echo htmlspecialchars($receipt_description); ?></div>
            <?php endif; ?>
        </div>

        <!-- Receipt Info -->
        <div class="px-2 py-2 space-y-1 text-xs">
            <div class="flex justify-between">
                <span class="text-gray-600">Receipt No.:</span>
                <span class="font-mono font-bold"><?php echo htmlspecialchars($sale['invoice_number'] ?? 'N/A'); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Date:</span>
                <span><?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Cashier:</span>
                <span><?php echo htmlspecialchars($sale['cashier_name'] ?? 'System'); ?></span>
            </div>
            <?php if (!empty($sale['customer_name'])): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Customer:</span>
                    <span><?php echo htmlspecialchars($sale['customer_name']); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Barcode -->
        <?php if ($barcodeSvg): ?>
            <div class="flex justify-center py-2 border-t border-dashed border-gray-300">
                <div class="barcode-svg"><?php echo $barcodeSvg; ?></div>
            </div>
        <?php endif; ?>

        <!-- Items Header -->
        <div class="flex justify-between px-2 py-1 border-t border-dashed border-gray-300 text-xs font-bold uppercase tracking-wide">
            <span>ITEM</span>
            <span>QTY</span>
            <span>PRICE</span>
            <span>TOTAL</span>
        </div>
        
        <!-- Items List -->
        <div class="px-2">
            <?php foreach ($items as $item):
                $qty = (float) ($item['quantity'] ?? 1);
                $unit = (float) ($item['price'] ?? ($item['subtotal'] / max(1, $qty)));
            ?>
                <div class="py-1.5 border-b border-dotted border-gray-200">
                    <div class="text-xs font-medium"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div class="flex justify-between text-xs text-gray-600 mt-0.5">
                        <div class="flex gap-4">
                            <span><?php echo number_format($qty, 0); ?> x</span>
                            <span><?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format($unit, 0); ?></span>
                        </div>
                        <div class="font-semibold"><?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format((float) $item['subtotal'], 0); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Summary -->
        <div class="px-2 py-2 space-y-1 text-xs border-t border-dashed border-gray-300">
            <div class="flex justify-between">
                <span class="text-gray-600">Subtotal:</span>
                <span><?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format($subtotal, 0); ?></span>
            </div>
            
            <?php if ($discount > 0): ?>
                <div class="flex justify-between text-green-600">
                    <span>Discount:</span>
                    <span>- <?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format($discount, 0); ?></span>
                </div>
            <?php endif; ?>

            <div class="flex justify-between">
                <span class="text-gray-600">Tax (<?php echo number_format((float)($sale['branch_tax_rate'] ?? $sale['tax_rate'] ?? 16), 2); ?>%):</span>
                <span><?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format($tax, 0); ?></span>
            </div>

            <div class="flex justify-between pt-1 border-t-2 border-double border-gray-400 text-sm font-bold">
                <span>TOTAL</span>
                <span class="text-base"><?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format($total, 0); ?></span>
            </div>

            <div class="flex justify-between pt-1 text-xs font-semibold uppercase">
                <span>PAID VIA</span>
                <span><?php echo strtoupper($sale['payment_method'] ?? 'CASH'); ?></span>
            </div>
            <?php
                $amount_received = isset($sale['amount_received']) ? (float)$sale['amount_received'] : null;
                $change_given    = isset($sale['change_given'])    ? (float)$sale['change_given']    : null;
                if ($amount_received !== null && $amount_received > 0 && strtolower($sale['payment_method'] ?? 'cash') !== 'split'):
            ?>
            <div class="flex justify-between text-xs mt-1">
                <span class="text-gray-600">Cash Received:</span>
                <span><?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format($amount_received, 2); ?></span>
            </div>
            <div class="flex justify-between text-xs font-bold <?php echo ($change_given > 0) ? 'text-green-700' : ''; ?>">
                <span>Change:</span>
                <span><?php echo $currency_symbol ?? 'KSh'; ?> <?php echo number_format($change_given ?? 0, 2); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- QR Code -->
        <?php if (!empty($qrDataUri)): ?>
            <div class="flex flex-col items-center justify-center py-3 border-t border-dashed border-gray-300">
                <img src="<?php echo htmlspecialchars($qrDataUri); ?>" alt="Receipt QR" class="qr-img">
                <div class="text-[8px] text-gray-500 mt-1">Scan to verify receipt</div>
            </div>
        <?php endif; ?>

        <!-- KRA eTIMS Fiscal Data -->
        <?php if (!empty($sale['etims_cu_invoice_no'])): ?>
        <div class="px-2 py-2 border-t border-dashed border-gray-300 text-[9px] text-center">
            <div class="font-bold uppercase text-[10px] mb-1">KRA eTIMS Fiscal Data</div>
            <div>CU Invoice No: <?php echo htmlspecialchars($sale['etims_cu_invoice_no']); ?></div>
            <?php if (!empty($sale['etims_receipt_sign'])): ?>
                <div class="break-all mt-0.5">Sign: <?php echo htmlspecialchars(substr($sale['etims_receipt_sign'], 0, 50)); ?>...</div>
            <?php endif; ?>
            <?php if (!empty($sale['etims_sdc_datetime'])): ?>
                <div>SDC Date: <?php echo htmlspecialchars($sale['etims_sdc_datetime']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center px-2 py-3 border-t border-dashed border-gray-300 text-xs">
            <div class="font-medium">Thank you for your business!</div>
            <div class="text-gray-500 text-[9px] mt-1"><?php echo date('d/m/Y H:i:s'); ?></div>
            <?php if (!empty($sale['notes'])): ?>
                <div class="text-gray-600 text-[9px] mt-1">Note: <?php echo htmlspecialchars($sale['notes']); ?></div>
            <?php endif; ?>
            <div class="text-gray-400 text-[8px] mt-1">*** E&OE ***</div>
        </div>
    </div>

    <!-- Auto-print if requested -->
    <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        </script>
    <?php endif; ?>

    <script>
        // Keyboard shortcut: Esc to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.close();
            }
        });
        
        // Also listen for Ctrl+P to print
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>
</body>
</html>