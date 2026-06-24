<?php
/**
 * Hardware Integration Layer for Jakababa POS
 * Supports: Receipt Printers (ESC/POS), Barcode Scanners, Weighing Scales
 *
 * @package Jakababa
 * @subpackage Hardware
 * @version 3.0
 */

if (defined('HARDWARE_LOADED')) {
    return;
}
define('HARDWARE_LOADED', true);

// =============================================================================
// ESC/POS Receipt Printer
// =============================================================================

/**
 * ESC/POS Receipt Printer Driver
 * Generates ESC/POS byte sequences for thermal receipt printers
 * Supports: Epson TM-T20, TM-T88, Star TSP, Xprinter, and ESC/POS compatible printers
 */
class ESCPOSPrinter
{
    // ESC/POS Commands
    const ESC = "\x1B";
    const GS = "\x1D";
    const LF = "\x0A";
    const CR = "\x0D";
    const HT = "\x09";
    const FF = "\x0C";

    // Alignment
    const ALIGN_LEFT = 0;
    const ALIGN_CENTER = 1;
    const ALIGN_RIGHT = 2;

    // Font styles
    const FONT_A = 0;
    const FONT_B = 1;
    const FONT_C = 2;

    // Text sizes
    const SIZE_NORMAL = 0x00;
    const SIZE_DOUBLE_HEIGHT = 0x10;
    const SIZE_DOUBLE_WIDTH = 0x20;
    const SIZE_DOUBLE = 0x30;

    private $output = '';
    private $paperWidth; // in characters
    private $companyName;
    private $companyAddress;
    private $companyPhone;
    private $companyTaxPin;
    private $receiptFooter;

    public function __construct(int $paperWidth = 48)
    {
        $this->paperWidth = $paperWidth;

        // Load company settings
        if (function_exists('get_settings')) {
            $settings = get_settings();
            $this->companyName = $settings['company_name'] ?? 'Jakababa POS';
            $this->companyAddress = $settings['company_address'] ?? '';
            $this->companyPhone = $settings['company_phone'] ?? '';
            $this->companyTaxPin = $settings['tax_pin'] ?? '';
            $this->receiptFooter = $settings['receipt_footer'] ?? 'Thank you for shopping with us!';
        }
    }

    // -------------------------------------------------------------------------
    // Raw Output
    // -------------------------------------------------------------------------

    public function getOutput(): string
    {
        return $this->output;
    }

    public function reset(): self
    {
        $this->output .= self::ESC . "@";
        return $this;
    }

    // -------------------------------------------------------------------------
    // Text Formatting
    // -------------------------------------------------------------------------

    public function setAlignment(int $alignment): self
    {
        $this->output .= self::ESC . "a" . chr($alignment);
        return $this;
    }

    public function setBold(bool $enabled = true): self
    {
        $this->output .= self::ESC . "E" . chr($enabled ? 1 : 0);
        return $this;
    }

    public function setUnderline(int $mode = 1): self
    {
        $this->output .= self::ESC . "-" . chr($mode);
        return $this;
    }

    public function setFontSize(int $width = 0, int $height = 0): self
    {
        $size = ($width << 4) | $height;
        $this->output .= self::GS . "!" . chr($size);
        return $this;
    }

    public function setFont(int $font): self
    {
        $this->output .= self::ESC . "M" . chr($font);
        return $this;
    }

    public function setCharacterSize(int $width, int $height): self
    {
        $size = (($width & 0x07) << 4) | ($height & 0x07);
        $this->output .= self::GS . "!" . chr($size);
        return $this;
    }

    public function invertColors(bool $enabled = true): self
    {
        $this->output .= self::GS . "B" . chr($enabled ? 1 : 0);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Text Output
    // -------------------------------------------------------------------------

    public function text(string $text): self
    {
        $this->output .= $text;
        return $this;
    }

    public function textLn(string $text = ''): self
    {
        $this->output .= $text . self::LF;
        return $this;
    }

    public function textCenter(string $text): self
    {
        return $this->setAlignment(self::ALIGN_CENTER)->textLn($text)->setAlignment(self::ALIGN_LEFT);
    }

    public function textRight(string $text): self
    {
        return $this->setAlignment(self::ALIGN_RIGHT)->textLn($text)->setAlignment(self::ALIGN_LEFT);
    }

    public function textBold(string $text): self
    {
        return $this->setBold(true)->text($text)->setBold(false);
    }

    public function textBoldLn(string $text): self
    {
        return $this->setBold(true)->textLn($text)->setBold(false);
    }

    // -------------------------------------------------------------------------
    // Formatting Helpers
    // -------------------------------------------------------------------------

    public function line(string $char = '-'): self
    {
        return $this->textLn(str_repeat($char, $this->paperWidth));
    }

    public function doubleLine(): self
    {
        return $this->line('=');
    }

    public function feed(int $lines = 1): self
    {
        for ($i = 0; $i < $lines; $i++) {
            $this->output .= self::LF;
        }
        return $this;
    }

    public function tab(): self
    {
        $this->output .= self::HT;
        return $this;
    }

    /**
     * Print a left-right aligned row (e.g., item name and price)
     */
    public function textRow(string $left, string $right): self
    {
        $maxLeft = $this->paperWidth - strlen($right) - 1;
        if (strlen($left) > $maxLeft) {
            $left = substr($left, 0, $maxLeft - 3) . '...';
        }
        $padding = $this->paperWidth - strlen($left) - strlen($right);
        $this->textLn($left . str_repeat(' ', max(1, $padding)) . $right);
        return $this;
    }

    /**
     * Print a multi-line item with quantity x price = total
     */
    public function itemRow(string $name, int $qty, float $price, float $total): self
    {
        $this->textLn($name);
        $line = sprintf("  %d x %s", $qty, number_format($price, 2));
        $right = number_format($total, 2);
        $this->textRow($line, $right);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Barcode & QR Code
    // -------------------------------------------------------------------------

    public function barcode(string $data, int $type = 73): self
    {
        // Type 73 = CODE39, 69 = UPC-A, 67 = EAN13
        $len = strlen($data);
        $this->output .= self::GS . "k" . chr($type) . chr($len) . $data;
        return $this;
    }

    public function barcodeHRI(string $data, int $type = 73): self
    {
        // Print barcode with human-readable text below
        $this->setAlignment(self::ALIGN_CENTER);
        $this->output .= self::GS . "H" . chr(2); // HRI below barcode
        $this->output .= self::GS . "h" . chr(80); // Barcode height
        $this->barcode($data, $type);
        $this->textLn('');
        $this->textLn($data);
        $this->setAlignment(self::ALIGN_LEFT);
        return $this;
    }

    public function qrCode(string $data, int $size = 6): self
    {
        $this->setAlignment(self::ALIGN_CENTER);
        // Set QR code model
        $this->output .= self::GS . "(k" . chr(4) . chr(0) . chr(49) . chr(65) . chr(32) . chr(0);
        // Set QR code size
        $this->output .= self::GS . "(k" . chr(3) . chr(0) . chr(49) . chr(67) . chr($size);
        // Store data
        $len = strlen($data) + 3;
        $pL = $len % 256;
        $pH = floor($len / 256);
        $this->output .= self::GS . "(k" . chr($pL) . chr($pH) . chr(49) . chr(80) . chr(48) . $data;
        // Print QR code
        $this->output .= self::GS . "(k" . chr(3) . chr(0) . chr(49) . chr(81) . chr(48);
        $this->feed(2);
        $this->setAlignment(self::ALIGN_LEFT);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Cash Drawer
    // -------------------------------------------------------------------------

    public function openDrawer(int $pin = 0, int $onTime = 80, int $offTime = 80): self
    {
        // Pin 0 = pin 2, Pin 1 = pin 5
        $this->output .= self::ESC . "p" . chr($pin) . chr($onTime) . chr($offTime);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Cut Paper
    // -------------------------------------------------------------------------

    public function cut(bool $full = true): self
    {
        $this->feed(3);
        $this->output .= self::GS . "V" . chr($full ? 65 : 66) . chr(0);
        return $this;
    }

    public function partialCut(): self
    {
        return $this->cut(false);
    }

    // -------------------------------------------------------------------------
    // Receipt Templates
    // -------------------------------------------------------------------------

    /**
     * Generate a full sales receipt
     */
    public function generateSaleReceipt(array $sale, array $items, array $paymentInfo = []): string
    {
        $this->reset();

        // Header
        $this->setAlignment(self::ALIGN_CENTER);
        $this->setFontSize(1, 1);
        $this->textBoldLn($this->companyName);
        $this->setFontSize(0, 0);

        if ($this->companyAddress) {
            $this->textLn($this->companyAddress);
        }
        if ($this->companyPhone) {
            $this->textLn('Tel: ' . $this->companyPhone);
        }
        if ($this->companyTaxPin) {
            $this->textLn('PIN: ' . $this->companyTaxPin);
        }

        $this->doubleLine();
        $this->setAlignment(self::ALIGN_LEFT);

        // Sale info
        $this->textRow('Receipt #:', $sale['receipt_number'] ?? ('INV-' . ($sale['id'] ?? '0000')));
        $this->textRow('Date:', date('d/m/Y H:i', strtotime($sale['created_at'] ?? 'now')));
        if (!empty($sale['branch_name'])) {
            $this->textRow('Branch:', $sale['branch_name']);
        }
        if (!empty($sale['cashier_name'])) {
            $this->textRow('Served by:', $sale['cashier_name']);
        }
        if (!empty($sale['customer_name']) && $sale['customer_name'] !== 'Walk-in Customer') {
            $this->textRow('Customer:', $sale['customer_name']);
        }

        $this->line();

        // Items header
        $this->setBold(true);
        $this->textLn('ITEM                QTY    PRICE   TOTAL');
        $this->setBold(false);
        $this->line();

        // Items
        $subtotal = 0;
        foreach ($items as $item) {
            $itemName = $item['name'] ?? $item['product_name'] ?? 'Unknown';
            $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
            $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
            $total = $qty * $price;
            $subtotal += $total;

            $this->itemRow($itemName, $qty, $price, $total);
        }

        $this->line();

        // Totals
        $this->textRow('Subtotal:', number_format($subtotal, 2));

        if (!empty($sale['discount']) && $sale['discount'] > 0) {
            $this->textRow('Discount:', '-' . number_format((float) $sale['discount'], 2));
        }

        if (!empty($sale['tax']) && $sale['tax'] > 0) {
            $this->textRow('Tax:', number_format((float) $sale['tax'], 2));
        }

        $this->doubleLine();
        $this->setFontSize(1, 0);
        $this->textBoldRow('TOTAL:', number_format((float) ($sale['total'] ?? $subtotal), 2));
        $this->setFontSize(0, 0);
        $this->doubleLine();

        // Payment info
        if (!empty($paymentInfo)) {
            $this->textRow('Payment Method:', ucfirst($paymentInfo['method'] ?? 'Cash'));
            if (isset($paymentInfo['amount_tendered'])) {
                $this->textRow('Amount Tendered:', number_format((float) $paymentInfo['amount_tendered'], 2));
                $this->textRow('Change:', number_format((float) $paymentInfo['change'], 2));
            }
        }

        // Loyalty points
        if (!empty($sale['loyalty_points_earned'])) {
            $this->feed(1);
            $this->textCenter('Points Earned: ' . (int) $sale['loyalty_points_earned']);
        }

        // Footer
        $this->feed(1);
        $this->setAlignment(self::ALIGN_CENTER);
        $this->textLn($this->receiptFooter);
        $this->textLn('Powered by Jakababa POS');
        $this->feed(1);

        // QR code with receipt number
        if (!empty($sale['receipt_number'])) {
            $this->qrCode($sale['receipt_number']);
        }

        $this->cut();
        $this->openDrawer();

        return $this->getOutput();
    }

    public function textBoldRow(string $left, string $right): self
    {
        return $this->setBold(true)->textRow($left, $right)->setBold(false);
    }

    /**
     * Generate end-of-day (Z-report) receipt
     */
    public function generateZReport(array $report): string
    {
        $this->reset();

        $this->setAlignment(self::ALIGN_CENTER);
        $this->setFontSize(1, 1);
        $this->textBoldLn('Z-REPORT');
        $this->setFontSize(0, 0);
        $this->doubleLine();
        $this->setAlignment(self::ALIGN_LEFT);

        $this->textRow('Date:', date('d/m/Y'));
        $this->textRow('Branch:', $report['branch_name'] ?? 'Main');
        $this->textRow('Cashier:', $report['cashier_name'] ?? 'System');
        $this->line();

        $this->setBold(true);
        $this->textLn('SALES SUMMARY');
        $this->setBold(false);
        $this->line();

        $this->textRow('Total Sales:', $report['total_sales'] ?? '0');
        $this->textRow('Total Revenue:', number_format($report['total_revenue'] ?? 0, 2));
        $this->textRow('Total Tax:', number_format($report['total_tax'] ?? 0, 2));
        $this->textRow('Total Discount:', number_format($report['total_discount'] ?? 0, 2));
        $this->textRow('Net Revenue:', number_format($report['net_revenue'] ?? 0, 2));

        $this->line();
        $this->setBold(true);
        $this->textLn('PAYMENT BREAKDOWN');
        $this->setBold(false);
        $this->line();

        if (!empty($report['payment_breakdown'])) {
            foreach ($report['payment_breakdown'] as $method => $amount) {
                $this->textRow(ucfirst($method) . ':', number_format($amount, 2));
            }
        }

        $this->line();
        $this->setBold(true);
        $this->textLn('REFUNDS');
        $this->setBold(false);
        $this->line();

        $this->textRow('Refund Count:', $report['refund_count'] ?? '0');
        $this->textRow('Refund Total:', number_format($report['refund_total'] ?? 0, 2));

        $this->doubleLine();
        $this->textCenter('End of Day Report');
        $this->textCenter(date('d/m/Y H:i:s'));
        $this->feed(2);

        $this->cut();
        $this->openDrawer();

        return $this->getOutput();
    }

    /**
     * Generate a kitchen order ticket
     */
    public function generateKitchenTicket(array $order, array $items): string
    {
        $this->reset();

        $this->setAlignment(self::ALIGN_CENTER);
        $this->setFontSize(1, 1);
        $this->textBoldLn('KITCHEN ORDER');
        $this->setFontSize(0, 0);
        $this->line();

        $this->setAlignment(self::ALIGN_LEFT);
        $this->textRow('Order #:', $order['order_number'] ?? ($order['id'] ?? ''));
        $this->textRow('Table:', $order['table_number'] ?? 'N/A');
        $this->textRow('Type:', ucfirst($order['order_type'] ?? 'Dine-in'));
        $this->textRow('Time:', date('H:i', strtotime($order['created_at'] ?? 'now')));

        $this->doubleLine();

        foreach ($items as $item) {
            $this->setFontSize(1, 0);
            $this->textBoldLn(($item['quantity'] ?? 1) . 'x ' . ($item['name'] ?? 'Unknown'));
            $this->setFontSize(0, 0);

            if (!empty($item['notes'])) {
                $this->textLn('  Note: ' . $item['notes']);
            }
            $this->line('.');
        }

        $this->feed(2);
        $this->cut();

        return $this->getOutput();
    }
}

// =============================================================================
// Barcode Scanner Handler
// =============================================================================

/**
 * Barcode Scanner Handler
 * Processes barcode input from USB/Bluetooth scanners
 * Supports: EAN-13, EAN-8, UPC-A, Code128, Code39, QR codes
 */
class BarcodeScanner
{
    const TYPE_EAN13 = 'EAN13';
    const TYPE_EAN8 = 'EAN8';
    const TYPE_UPCA = 'UPCA';
    const TYPE_CODE128 = 'CODE128';
    const TYPE_CODE39 = 'CODE39';
    const TYPE_QR = 'QR';
    const TYPE_UNKNOWN = 'UNKNOWN';

    /**
     * Detect barcode type from the barcode value
     */
    public static function detectType(string $barcode): string
    {
        $barcode = trim($barcode);

        if (empty($barcode)) {
            return self::TYPE_UNKNOWN;
        }

        // Numeric barcodes
        if (ctype_digit($barcode)) {
            $len = strlen($barcode);
            if ($len === 13) return self::TYPE_EAN13;
            if ($len === 8) return self::TYPE_EAN8;
            if ($len === 12) return self::TYPE_UPCA;
        }

        // Alphanumeric
        if (preg_match('/^[A-Z0-9\-\.\ \$\/\+\%]+$/i', $barcode)) {
            if (strlen($barcode) <= 43) return self::TYPE_CODE39;
        }

        return self::TYPE_CODE128;
    }

    /**
     * Validate EAN-13 checksum
     */
    public static function validateEAN13(string $barcode): bool
    {
        if (strlen($barcode) !== 13 || !ctype_digit($barcode)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $barcode[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $barcode[12];
    }

    /**
     * Lookup product by barcode in the database
     */
    public static function lookupProduct(string $barcode, ?int $branchId = null, ?int $companyId = null): ?array
    {
        if (!function_exists('db_fetch_one')) {
            return null;
        }

        // Try cache first
        $cacheKey = "barcode:" . ($companyId ?? 0) . ":" . $barcode;
        $cached = cache_get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $sql = "SELECT p.*, i.stock, i.reorder_level, c.name as category_name
                    FROM products p
                    LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = ?
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE (p.barcode = ? OR p.sku = ?) AND p.active = 1 AND p.deleted_at IS NULL";
            $params = [$branchId ?? ($_SESSION['branch_id'] ?? 1), $barcode, $barcode];

            if ($companyId) {
                $sql .= " AND p.tenant_id = ?";
                $params[] = $companyId;
            }

            $sql .= " LIMIT 1";

            $product = db_fetch_one($sql, $params);

            // Cache for 5 minutes
            cache_set($cacheKey, $product ?: false, 300);

            return $product ?: null;
        } catch (Exception $e) {
            error_log("Barcode lookup error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a random barcode (for products without barcodes)
     */
    public static function generateEAN13(string $prefix = '200'): string
    {
        // Use prefix (e.g., internal code starts with 2)
        $code = str_pad($prefix, 12, '0', STR_PAD_RIGHT);
        $code = substr($code, 0, 12);

        // Fill with random digits
        for ($i = strlen($prefix); $i < 12; $i++) {
            $code[$i] = (string) random_int(0, 9);
        }

        // Calculate checksum
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $code[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;

        return $code . $check;
    }
}

// =============================================================================
// Weighing Scale Integration
// =============================================================================

/**
 * Weighing Scale Handler
 * Supports: Serial/USB scales with standard protocols
 * Common scales: CAS, Digi, Avery, Mettler Toledo
 */
class WeighingScale
{
    // Weight unit codes
    const UNIT_KG = 'kg';
    const UNIT_G = 'g';
    const UNIT_LB = 'lb';
    const UNIT_OZ = 'oz';

    private $connectionType;
    private $port;
    private $baudRate;
    private $connected = false;

    public function __construct(string $connectionType = 'usb', string $port = '', int $baudRate = 9600)
    {
        $this->connectionType = $connectionType;
        $this->port = $port ?: $this->detectPort();
        $this->baudRate = $baudRate;
    }

    /**
     * Auto-detect the scale port
     */
    private function detectPort(): string
    {
        // Windows COM ports
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            for ($i = 1; $i <= 16; $i++) {
                if (file_exists("COM{$i}")) {
                    return "COM{$i}";
                }
            }
            return 'COM3'; // Default
        }

        // Linux/Mac serial ports
        $ports = glob('/dev/ttyUSB*') ?: [];
        $ports = array_merge($ports, glob('/dev/ttyACM*') ?: []);
        $ports = array_merge($ports, glob('/dev/ttyS*') ?: []);

        return !empty($ports) ? $ports[0] : '/dev/ttyUSB0';
    }

    /**
     * Read weight from scale (simulated for web environment)
     * In production, this would communicate with the actual hardware
     */
    public function readWeight(): array
    {
        // For web-based POS, weight is typically sent via:
        // 1. Direct serial connection (requires PHP extension)
        // 2. Scale API endpoint
        // 3. Manual entry

        return [
            'weight' => 0,
            'unit' => self::UNIT_KG,
            'stable' => false,
            'error' => 'Scale not connected. Use manual weight entry.',
            'mode' => 'manual'
        ];
    }

    /**
     * Parse weight from a scale's string output
     * Common formats: "ST,GS,+001.50kg", "ST,NT,+000.250kg"
     */
    public function parseWeightString(string $raw): array
    {
        $result = [
            'weight' => 0,
            'unit' => self::UNIT_KG,
            'stable' => false,
            'raw' => $raw
        ];

        // Remove whitespace
        $raw = trim($raw);

        // Check stability indicator
        $result['stable'] = (strpos($raw, 'ST') !== false);

        // Extract weight value and unit
        if (preg_match('/([+-]?\d+\.?\d*)\s*(kg|g|lb|oz)/i', $raw, $matches)) {
            $result['weight'] = (float) $matches[1];
            $result['unit'] = strtolower($matches[2]);

            // Convert to kg if needed
            switch ($result['unit']) {
                case 'g':
                    $result['weight_kg'] = $result['weight'] / 1000;
                    break;
                case 'lb':
                    $result['weight_kg'] = $result['weight'] * 0.453592;
                    break;
                case 'oz':
                    $result['weight_kg'] = $result['weight'] * 0.0283495;
                    break;
                default:
                    $result['weight_kg'] = $result['weight'];
            }
        }

        return $result;
    }

    /**
     * Calculate price based on weight and unit price
     */
    public static function calculatePrice(float $weight, float $pricePerKg, string $unit = 'kg'): float
    {
        switch ($unit) {
            case 'g':
                $weightKg = $weight / 1000;
                break;
            case 'lb':
                $weightKg = $weight * 0.453592;
                break;
            case 'oz':
                $weightKg = $weight * 0.0283495;
                break;
            default:
                $weightKg = $weight;
        }

        return round($weightKg * $pricePerKg, 2);
    }

    /**
     * Generate a PLU barcode for weighed items
     * Format: 2 + 4-digit PLU + 5-digit weight + 1 check digit = 12 digits (EAN-8 compatible)
     */
    public static function generatePLUBarcode(int $plu, float $weight): string
    {
        // Prefix 2 indicates weighed item
        $code = '2';
        // 4-digit PLU code
        $code .= str_pad($plu, 4, '0', STR_PAD_LEFT);
        // 5-digit weight in grams
        $weightGrams = round($weight * 1000);
        $code .= str_pad(min($weightGrams, 99999), 5, '0', STR_PAD_LEFT);
        // 1-digit random (or checksum)
        $code .= random_int(0, 9);
        // Final check digit
        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum += (int) $code[$i] * ($i % 2 === 0 ? 3 : 1);
        }
        $check = (10 - ($sum % 10)) % 10;
        $code .= $check;

        return $code;
    }

    /**
     * Parse a PLU barcode to extract PLU code and weight
     */
    public static function parsePLUBarcode(string $barcode): ?array
    {
        if (strlen($barcode) !== 12 || $barcode[0] !== '2') {
            return null;
        }

        $plu = (int) substr($barcode, 1, 4);
        $weightGrams = (int) substr($barcode, 5, 5);
        $weight = $weightGrams / 1000;

        return [
            'plu' => $plu,
            'weight' => $weight,
            'weight_unit' => 'kg',
            'weight_grams' => $weightGrams
        ];
    }
}

// =============================================================================
// Label Printer
// =============================================================================

/**
 * Shelf Label / Barcode Label Printer
 * Generates label data for DYMO, Zebra, Brother label printers
 */
class LabelPrinter
{
    private $labelWidth;
    private $labelHeight;

    public function __construct(int $width = 300, int $height = 150)
    {
        $this->labelWidth = $width;
        $this->labelHeight = $height;
    }

    /**
     * Generate a product shelf label as HTML (for browser-based printing)
     */
    public function generateProductLabel(array $product, int $quantity = 1): string
    {
        $labels = [];
        $name = htmlspecialchars($product['name'] ?? 'Product');
        $price = number_format($product['price'] ?? 0, 2);
        $sku = htmlspecialchars($product['sku'] ?? '');
        $barcode = htmlspecialchars($product['barcode'] ?? $sku);
        $category = htmlspecialchars($product['category_name'] ?? '');
        $unit = htmlspecialchars($product['unit'] ?? 'pcs');
        $currency = function_exists('get_settings')
            ? (get_settings('currency') ?? 'KES')
            : 'KES';
        $categoryHtml = $category ? "<div style='font-size:9px; color:#666;'>{$category}</div>" : '';

        for ($i = 0; $i < $quantity; $i++) {
            $label = '<div class="shelf-label" style="width:' . $this->labelWidth . 'px; height:' . $this->labelHeight . 'px; border:1px dashed #ccc; padding:8px; font-family:monospace; display:inline-block; margin:4px; page-break-inside:avoid;">';
            $label .= '<div style="font-size:12px; font-weight:bold; margin-bottom:4px;">' . $name . '</div>';
            $label .= '<div style="text-align:center; margin:6px 0;">';
            $label .= '<svg class="barcode" data-value="' . $barcode . '" style="width:200px; height:40px;"></svg>';
            $label .= '</div>';
            $label .= '<div style="display:flex; justify-content:space-between; font-size:10px;">';
            $label .= '<span>SKU: ' . $sku . '</span>';
            $label .= '<span style="font-size:14px; font-weight:bold;">' . $currency . ' ' . $price . '/' . $unit . '</span>';
            $label .= '</div>';
            $label .= $categoryHtml;
            $label .= '</div>';
            $labels[] = $label;
        }

        return implode("\n", $labels);
    }

    /**
     * Generate ZPL (Zebra Programming Language) for direct printer output
     */
    public function generateZPL(array $product): string
    {
        $name = substr($product['name'] ?? 'Product', 0, 30);
        $price = number_format($product['price'] ?? 0, 2);
        $sku = $product['sku'] ?? '';
        $barcode = $product['barcode'] ?? $sku;

        return "^XA\n"
            . "^FO50,50^A0N,30,30^FD{$name}^FS\n"
            . "^FO50,100^BY3^BCN,100,Y,N,N^FD{$barcode}^FS\n"
            . "^FO50,220^A0N,25,25^FD{$price}^FS\n"
            . "^FO300,220^A0N,20,20^FD{$sku}^FS\n"
            . "^XZ\n";
    }

    /**
     * Generate EPL (Eltron Programming Language) for older Zebra/Epson printers
     */
    public function generateEPL(array $product): string
    {
        $name = substr($product['name'] ?? 'Product', 0, 30);
        $price = number_format($product['price'] ?? 0, 2);
        $barcode = $product['barcode'] ?? ($product['sku'] ?? '');

        return "N\n"
            . "A50,50,0,3,1,1,N,\"{$name}\"\n"
            . "B50,100,0,3,2,6,100,B,\"{$barcode}\"\n"
            . "A50,220,0,3,1,1,N,\"{$price}\"\n"
            . "P1\n";
    }
}

// =============================================================================
// Global Helper Functions
// =============================================================================

if (!function_exists('get_printer')) {
    function get_printer(int $paperWidth = 48): ESCPOSPrinter
    {
        return new ESCPOSPrinter($paperWidth);
    }
}

if (!function_exists('get_barcode_scanner')) {
    function get_barcode_scanner(): BarcodeScanner
    {
        return new BarcodeScanner();
    }
}

if (!function_exists('get_scale')) {
    function get_scale(string $connectionType = 'usb'): WeighingScale
    {
        return new WeighingScale($connectionType);
    }
}

if (!function_exists('get_label_printer')) {
    function get_label_printer(int $width = 300, int $height = 150): LabelPrinter
    {
        return new LabelPrinter($width, $height);
    }
}
