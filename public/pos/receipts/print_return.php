<?php
/**
 * Print Return Receipt
 * Printable version of return details
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
require_login();

$return_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$return_id) {
    die('Invalid return ID');
}

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$current_branch_id = get_current_branch_id();

try {
    $pdo = get_db_connection();

    // Fetch return details
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            s.invoice_number,
            s.created_at as sale_date,
            c.name as customer_name,
            c.phone as customer_phone,
            u.name as processed_by_name,
            b.name as branch_name,
            b.address as branch_address,
            b.phone as branch_phone
        FROM returns r
        LEFT JOIN sales s ON r.sale_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.processed_by = u.id
        LEFT JOIN branches b ON r.branch_id = b.id
        WHERE r.id = ?
    ");
    $stmt->execute([$return_id]);
    $return = $stmt->fetch();

    if (!$return) {
        die('Return not found');
    }

    // Check permission
    if (!is_super_admin() && $return['branch_id'] != $current_branch_id) {
        die('You do not have permission to view this return');
    }

    // Fetch return items
    $stmt = $pdo->prepare("
        SELECT ri.*, p.name as product_name, p.sku
        FROM return_items ri
        LEFT JOIN products p ON ri.product_id = p.id
        WHERE ri.return_id = ?
    ");
    $stmt->execute([$return_id]);
    $items = $stmt->fetchAll();

    // Get company settings
    $company_name = 'Jakababa POS';
    $company_address = '';
    $company_phone = '';

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            if ($row['setting_key'] === 'company_name')
                $company_name = $row['setting_value'];
            if ($row['setting_key'] === 'company_address')
                $company_address = $row['setting_value'];
            if ($row['setting_key'] === 'company_phone')
                $company_phone = $row['setting_value'];
        }
    } catch (Exception $e) {
        // Use defaults
    }

    $currency_symbol = 'KSh';

} catch (Exception $e) {
    error_log("Error printing return: " . $e->getMessage());
    die('Error loading return data');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Receipt -
        <?php echo $return['return_number']; ?>
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 20px;
            color: black;
        }

        .receipt {
            max-width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .mb-1 {
            margin-bottom: 5px;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        .mt-2 {
            margin-top: 10px;
        }

        .border-top {
            border-top: 1px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
        }

        .border-bottom {
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .store-name {
            font-size: 18px;
            font-weight: bold;
        }

        .receipt-header {
            margin-bottom: 15px;
        }

        .receipt-footer {
            margin-top: 15px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 5px 0;
            text-align: left;
        }

        .item-name {
            font-size: 12px;
        }

        .item-price {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-completed {
            background: #10B981;
            color: white;
        }

        .status-pending {
            background: #FBBF24;
            color: black;
        }

        .status-rejected {
            background: #EF4444;
            color: white;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt">
        <!-- Store Header -->
        <div class="receipt-header text-center border-bottom">
            <div class="store-name">
                <?php echo htmlspecialchars($company_name); ?>
            </div>
            <?php if (!empty($company_address)): ?>
                <div style="font-size: 11px;">
                    <?php echo htmlspecialchars($company_address); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($company_phone)): ?>
                <div style="font-size: 11px;">Tel:
                    <?php echo htmlspecialchars($company_phone); ?>
                </div>
            <?php endif; ?>
            <div style="font-size: 11px;">
                <?php echo htmlspecialchars($return['branch_name']); ?>
            </div>
        </div>

        <!-- Title -->
        <div class="text-center mb-2">
            <h2 style="font-size: 16px;">RETURN RECEIPT</h2>
        </div>

        <!-- Return Info -->
        <div class="mb-2">
            <table>
                <tr>
                    <td style="width: 40%;">Return #:</td>
                    <td class="font-bold">
                        <?php echo htmlspecialchars($return['return_number']); ?>
                    </td>
                </tr>
                <tr>
                    <td>Date:</td>
                    <td>
                        <?php echo date('d/m/Y H:i', strtotime($return['created_at'])); ?>
                    </td>
                </tr>
                <?php if ($return['invoice_number']): ?>
                    <tr>
                        <td>Original Invoice:</td>
                        <td>
                            <?php echo htmlspecialchars($return['invoice_number']); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>Customer:</td>
                    <td>
                        <?php echo htmlspecialchars($return['customer_name'] ?? 'Walk-in Customer'); ?>
                    </td>
                </tr>
                <?php if (!empty($return['customer_phone'])): ?>
                    <tr>
                        <td>Phone:</td>
                        <td>
                            <?php echo htmlspecialchars($return['customer_phone']); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>Processed By:</td>
                    <td>
                        <?php echo htmlspecialchars($return['processed_by_name']); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Status -->
        <div class="text-center mb-2">
            <span class="status-badge status-<?php echo $return['status']; ?>">
                <?php echo strtoupper($return['status']); ?>
            </span>
        </div>

        <!-- Items -->
        <div class="border-top border-bottom">
            <table>
                <thead>
                    <tr style="border-bottom: 1px solid #000;">
                        <th>Item</th>
                        <th style="text-align: center;">Qty</th>
                        <th style="text-align: right;">Price</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="font-size: 11px;">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php echo $item['quantity']; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php echo $currency_symbol; ?>
                                <?php echo number_format($item['price'], 2); ?>
                            </td>
                            <td style="text-align: right;">
                                <?php echo $currency_symbol; ?>
                                <?php echo number_format($item['subtotal'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="mt-2">
            <table>
                <tr class="total-row">
                    <td style="width: 70%;">TOTAL RETURN:</td>
                    <td style="text-align: right;">
                        <?php echo $currency_symbol; ?>
                        <?php echo number_format($return['amount'], 2); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Reason -->
        <div class="mt-2 border-top">
            <p style="font-size: 11px;"><strong>Reason:</strong>
                <?php echo htmlspecialchars($return['reason']); ?>
            </p>
            <?php if (!empty($return['notes'])): ?>
                <p style="font-size: 11px;"><strong>Notes:</strong>
                    <?php echo nl2br(htmlspecialchars($return['notes'])); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="receipt-footer text-center border-top">
            <p>Thank you for your business!</p>
            <p style="font-size: 10px;">
                <?php echo date('d/m/Y H:i:s'); ?>
            </p>
            <p style="font-size: 10px;">*** E&OE ***</p>
        </div>
    </div>

    <!-- Print Button -->
    <div class="text-center no-print" style="margin-top: 20px;">
        <button onclick="window.print()"
            style="padding: 10px 20px; background: #1E3A8A; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Print Receipt
        </button>
        <button onclick="window.close()"
            style="padding: 10px 20px; background: #6B7280; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <script>
        // Auto-print if requested
        <?php if (isset($_GET['print']) && $_GET['print'] == '1'): ?>
                window.onload = function() { window.print(); }
        <?php endif; ?>
    </script>
</body>

</html>
