<?php
declare(strict_types=1);

namespace App\Pos;

/**
 * ThermalReceiptRenderer
 *
 * Produces an HTML receipt sized for 58mm or 80mm thermal printers.
 * The output is plain HTML/CSS — no external assets — so it prints
 * crisply via the browser print dialog or any thermal driver that
 * accepts HTML (most modern ones do).
 *
 * For raw ESC/POS bytes, use ::escpos() to get a printable stream.
 *
 * Usage:
 *   $r = new ThermalReceiptRenderer($sale, $items, $settings);
 *   echo $r->html(80); // or 58 for 58mm
 */
class ThermalReceiptRenderer
{
    private array  $sale;
    private array  $items;
    private array  $settings;

    public function __construct(array $sale, array $items, array $settings)
    {
        $this->sale     = $sale;
        $this->items    = $items;
        $this->settings = $settings + [
            'company_name'   => 'POS',
            'currency'       => 'KES',
            'tax_rate'       => 0,
            'company_address'=> '',
            'company_phone'  => '',
            'receipt_footer' => 'Thank you for your business!',
        ];
    }

    public function html(int $widthMm = 80): string
    {
        $w        = $widthMm === 58 ? '58mm' : '80mm';
        $cur      = htmlspecialchars($this->settings['currency'] ?? 'KES');
        $name     = htmlspecialchars($this->settings['company_name'] ?? '');
        $addr     = htmlspecialchars($this->settings['company_address'] ?? '');
        $phone    = htmlspecialchars($this->settings['company_phone'] ?? '');
        $footer   = htmlspecialchars($this->settings['receipt_footer'] ?? '');

        $invoice  = htmlspecialchars((string) ($this->sale['invoice_number'] ?? $this->sale['id'] ?? ''));
        $date     = htmlspecialchars((string) ($this->sale['created_at'] ?? date('Y-m-d H:i')));
        $cashier  = htmlspecialchars((string) ($this->sale['cashier_name'] ?? ''));
        $customer = htmlspecialchars((string) ($this->sale['customer_name'] ?? 'Walk-in'));
        $payment  = htmlspecialchars((string) ($this->sale['payment_method'] ?? 'Cash'));

        $subtotal = (float) ($this->sale['subtotal'] ?? 0);
        $discount = (float) ($this->sale['discount'] ?? 0);
        $tax      = (float) ($this->sale['tax']      ?? 0);
        $total    = (float) ($this->sale['total']    ?? 0);
        $tendered = (float) ($this->sale['amount_paid'] ?? $total);
        $change   = max(0.0, $tendered - $total);

        $rows = '';
        foreach ($this->items as $i) {
            $n  = htmlspecialchars((string) ($i['name'] ?? $i['product_name'] ?? ''));
            $qty = (float) ($i['qty'] ?? $i['quantity'] ?? 1);
            $p   = (float) ($i['price'] ?? $i['unit_price'] ?? 0);
            $line = $qty * $p - (float) ($i['discount'] ?? 0);
            $rows .= sprintf(
                '<div class="row item"><div class="n">%s</div><div class="q">%s × %s</div><div class="t">%s</div></div>',
                $n,
                rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.'),
                number_format($p, 2),
                number_format($line, 2)
            );
        }

        ob_start(); ?>
<!doctype html>
<html><head><meta charset="utf-8">
<title>Receipt <?php echo $invoice; ?></title>
<style>
@page { size: <?php echo $w; ?> auto; margin: 0; }
* { box-sizing: border-box; }
body {
    width: <?php echo $w; ?>;
    margin: 0; padding: 6px 8px;
    font-family: 'Courier New', 'Consolas', monospace;
    font-size: 11px; line-height: 1.35; color: #000;
}
.center { text-align: center; }
.right  { text-align: right; }
.bold   { font-weight: 700; }
.lg     { font-size: 14px; }
.xl     { font-size: 16px; font-weight: 700; }
hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
.row { display: flex; justify-content: space-between; gap: 4px; }
.item .n { flex: 1 1 auto; word-break: break-word; }
.item .q { flex: 0 0 auto; padding: 0 4px; color: #444; }
.item .t { flex: 0 0 auto; min-width: 50px; text-align: right; }
.kv { display: flex; justify-content: space-between; }
.barcode {
    text-align: center; font-family: 'Libre Barcode 128', monospace;
    font-size: 28px; line-height: 1; margin: 6px 0 2px;
}
@media print {
    html, body { width: <?php echo $w; ?>; }
}
</style></head>
<body>

<div class="center xl"><?php echo $name; ?></div>
<?php if ($addr):  ?><div class="center"><?php echo $addr; ?></div><?php endif; ?>
<?php if ($phone): ?><div class="center">Tel: <?php echo $phone; ?></div><?php endif; ?>
<hr>

<div class="kv"><span>Receipt:</span><span class="bold"><?php echo $invoice; ?></span></div>
<div class="kv"><span>Date:</span><span><?php echo $date; ?></span></div>
<?php if ($cashier): ?><div class="kv"><span>Cashier:</span><span><?php echo $cashier; ?></span></div><?php endif; ?>
<div class="kv"><span>Customer:</span><span><?php echo $customer; ?></span></div>
<hr>

<?php echo $rows; ?>
<hr>

<div class="kv"><span>Subtotal</span><span><?php echo $cur . ' ' . number_format($subtotal, 2); ?></span></div>
<?php if ($discount > 0): ?>
<div class="kv"><span>Discount</span><span>-<?php echo $cur . ' ' . number_format($discount, 2); ?></span></div>
<?php endif; ?>
<?php if ($tax > 0): ?>
<div class="kv"><span>Tax (<?php echo (float) $this->settings['tax_rate']; ?>%)</span><span><?php echo $cur . ' ' . number_format($tax, 2); ?></span></div>
<?php endif; ?>
<div class="kv lg bold"><span>TOTAL</span><span><?php echo $cur . ' ' . number_format($total, 2); ?></span></div>
<hr>

<div class="kv"><span>Payment</span><span><?php echo $payment; ?></span></div>
<div class="kv"><span>Tendered</span><span><?php echo $cur . ' ' . number_format($tendered, 2); ?></span></div>
<?php if ($change > 0): ?>
<div class="kv bold"><span>Change</span><span><?php echo $cur . ' ' . number_format($change, 2); ?></span></div>
<?php endif; ?>
<hr>

<div class="barcode">*<?php echo $invoice; ?>*</div>
<div class="center"><?php echo $invoice; ?></div>

<?php if ($footer): ?>
<hr>
<div class="center"><?php echo $footer; ?></div>
<?php endif; ?>

<div style="height: 14px"></div>

<script>
window.onload = function () { setTimeout(function () { window.print(); }, 100); };
window.onafterprint = function () { setTimeout(window.close, 300); };
</script>
</body></html>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Build raw ESC/POS byte stream for direct printer drivers.
     * Suitable for sending via WebUSB or a backend print spooler.
     */
    public function escpos(int $widthCols = 42): string
    {
        $ESC = "\x1b"; $GS = "\x1d";
        $INIT      = $ESC . "@";
        $BOLD_ON   = $ESC . "E\x01";
        $BOLD_OFF  = $ESC . "E\x00";
        $CENTER    = $ESC . "a\x01";
        $LEFT      = $ESC . "a\x00";
        $CUT       = $GS . "V\x42\x00";
        $DRAWER    = $ESC . "p\x00\x32\xfa";
        $LINE      = str_repeat('-', $widthCols) . "\n";

        $cur  = $this->settings['currency'] ?? 'KES';
        $name = $this->settings['company_name'] ?? '';

        $out  = $INIT . $CENTER . $BOLD_ON . $name . "\n" . $BOLD_OFF;
        if (!empty($this->settings['company_address'])) $out .= $this->settings['company_address'] . "\n";
        if (!empty($this->settings['company_phone']))   $out .= 'Tel: ' . $this->settings['company_phone'] . "\n";
        $out .= $LEFT . $LINE;

        $invoice = (string) ($this->sale['invoice_number'] ?? $this->sale['id'] ?? '');
        $out .= "Receipt: " . $invoice . "\n";
        $out .= "Date:    " . ($this->sale['created_at'] ?? date('Y-m-d H:i')) . "\n";
        $out .= $LINE;

        foreach ($this->items as $i) {
            $n  = (string) ($i['name'] ?? $i['product_name'] ?? '');
            $q  = (float) ($i['qty'] ?? $i['quantity'] ?? 1);
            $p  = (float) ($i['price'] ?? $i['unit_price'] ?? 0);
            $t  = number_format($q * $p, 2);
            $out .= str_pad(substr($n, 0, $widthCols - 8), $widthCols - 8) . str_pad($t, 8, ' ', STR_PAD_LEFT) . "\n";
            $out .= "  " . rtrim(rtrim(number_format($q, 2, '.', ''), '0'), '.') . " x " . number_format($p, 2) . "\n";
        }

        $out .= $LINE;
        $total = (float) ($this->sale['total'] ?? 0);
        $out .= $BOLD_ON . str_pad('TOTAL', $widthCols - 12) . str_pad($cur . ' ' . number_format($total, 2), 12, ' ', STR_PAD_LEFT) . "\n" . $BOLD_OFF;
        $out .= $LINE;
        $out .= $CENTER . ($this->settings['receipt_footer'] ?? 'Thank you!') . "\n\n\n";
        $out .= $DRAWER; // kick cash drawer
        $out .= $CUT;

        return $out;
    }
}
