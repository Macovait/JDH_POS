<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
/**
 * Barcode Label Generation Endpoint - AJAX Handler
 * Handles all AJAX actions for barcode label printing.
 * 
 * Actions:
 * - get_products: Fetch products based on search/category
 * - generate_barcodes: Generate barcode SVGs for selected products
 * - download_pdf: Generate and download professional PDF labels
 * - enqueue_pdf: Queue async PDF generation job for SaaS environments
 * - pdf_job_status: Check status of async PDF job
 * - get_pdf: Download completed PDF
 * - reprint_last: Reprint the last label job
 * - batch_print: Batch print by filter (low stock, out of stock, etc.)
 * - batch_import: Import products from CSV file
 * - download_csv_template: Download CSV template for batch import
 * - save_setting: Save a tenant setting (e.g., auto-print toggle)
 * - save_label_template: Save label template configuration per tenant
 * - get_label_template: Retrieve label template configuration per tenant
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Load Composer autoloader early (required for Dompdf and other vendor classes)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// === API-safe error handling (prevents raw 500 / HTML errors on fatal) ===
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'debug' => "$message in $file on line $line"
    ]);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Fatal server error',
            'debug' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

// Professional Barcode Label System (2026 Enterprise Upgrade)
require_once __DIR__ . '/../../src/Barcode/LabelTemplate.php';
require_once __DIR__ . '/../../src/Barcode/BarcodeService.php';
require_once __DIR__ . '/../../src/Barcode/LabelRenderer.php';
require_once __DIR__ . '/../../src/Barcode/PdfLabelGenerator.php';
require_once __DIR__ . '/../../src/Barcode/EscPosPrinter.php';
require_once __DIR__ . '/../../src/Barcode/LabelPrintService.php';
require_once __DIR__ . '/../../src/Barcode/ProductImporter.php';
require_once __DIR__ . '/../../src/Barcode/RateLimiter.php';
require_once __DIR__ . '/../../src/Barcode/BarcodeErrorHandler.php';
require_once __DIR__ . '/../../src/SettingsManager.php';

header('Content-Type: application/json');

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_id = get_current_user_id() ?? ($_SESSION['user']['id'] ?? null);

if (!$tenant_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Company context missing. Please log in again.']);
    exit;
}

if (!check_permission('products.view') && !is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied: products.view required']);
    exit;
}

// Lazy service getter - only instantiate LabelPrintService for actions that actually need it.
// This prevents fatal errors on lightweight actions like get_products when tenant context is partial.
$labelPrintService = null;
$getLabelPrintService = function () use (&$labelPrintService, $pdo, $tenant_id, $branch_id, $user_id) {
    if ($labelPrintService === null) {
        try {
            $labelPrintService = new \JDH\POS\Barcode\LabelPrintService($pdo, $tenant_id, $branch_id, $user_id);
        } catch (\Throwable $e) {
            error_log('[LabelPrintService] Failed to instantiate: ' . $e->getMessage());
            throw new \Exception('Label printing service unavailable: ' . $e->getMessage());
        }
    }
    return $labelPrintService;
};

// Resolve barcode library availability lazily so non-barcode actions can still work.
$barcode_available = class_exists('\Picqer\Barcode\BarcodeGenerator');
$barcode_library_error = $barcode_available
    ? null
    : 'Barcode library not available. Run: <code>composer require picqer/php-barcode-generator</code>';

$ensure_barcode_available = static function () use ($barcode_available, $barcode_library_error): void {
    if (!$barcode_available) {
        throw new Exception($barcode_library_error ?? 'Barcode library not available.');
    }
};

// Dompdf availability (required only for professional PDF export)
$dompdf_available = class_exists('Dompdf\Dompdf');
$dompdf_library_error = $dompdf_available
    ? null
    : 'Professional PDF export requires dompdf. Run in project root: composer require dompdf/dompdf';

$ensure_dompdf_available = static function () use ($dompdf_available, $dompdf_library_error): void {
    if (!$dompdf_available) {
        throw new Exception($dompdf_library_error);
    }
};

// ESC/POS thermal printer library
$escpos_available = class_exists('Mike42\Escpos\Printer');
$escpos_library_error = $escpos_available
    ? null
    : 'Direct thermal printing requires escpos-php. Run: composer require mike42/escpos-php';

$ensure_escpos_available = static function () use ($escpos_available, $escpos_library_error): void {
    if (!$escpos_available) {
        throw new Exception($escpos_library_error);
    }
};

$resolve_batch_filters = static function (string $filter): array {
    switch ($filter) {
        case 'low_stock':
            return ['low_stock' => true];
        case 'out_of_stock':
            return ['out_of_stock' => true];
        case 'new_this_month':
            return ['created_after' => date('Y-m-01')];
        default:
            throw new Exception('Invalid batch filter selected');
    }
};

// Helper to get store name
function getStoreName(\PDO $pdo, int $tenant_id): string {
    return \JDH\POS\Barcode\LabelPrintService::resolveStoreName($pdo, $tenant_id);
}
$store_name = getStoreName($pdo, $tenant_id);

function logLabelPrinterEvent(string $message, string $level = 'info'): void
{
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/label_printer.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), trim($message));
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function processPdfJob(string $jobId, string $storeName, int $tenant_id = 0, \PDO $pdo = null): array
{
    $storageDir = __DIR__ . '/../../storage/label_jobs';
    $jobFile = $storageDir . '/' . $jobId . '.json';

    if (!file_exists($jobFile)) {
        logLabelPrinterEvent("Job not found: {$jobId}", 'warning');
        throw new Exception('Job not found');
    }

    $fp = fopen($jobFile, 'c+');
    if (!$fp) {
        throw new Exception('Unable to open job file');
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new Exception('Unable to lock job file');
    }

    rewind($fp);
    $jobData = stream_get_contents($fp);
    $job = json_decode($jobData, true);
    if (!is_array($job)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        throw new Exception('Invalid job data');
    }

    // Tenant isolation: reject jobs that don't belong to the requesting tenant
    if ($tenant_id > 0 && isset($job['tenant_id']) && (int)$job['tenant_id'] !== $tenant_id) {
        flock($fp, LOCK_UN);
        fclose($fp);
        throw new Exception('Job not found');
    }

    if (in_array($job['status'], ['done', 'failed'], true)) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return $job;
    }

    if ($job['status'] === 'processing') {
        flock($fp, LOCK_UN);
        fclose($fp);
        return $job;
    }

    $job['status'] = 'processing';
    $job['updated_at'] = time();

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($job, JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    try {
        $products = $job['products'] ?? [];
        if (empty($products) || !is_array($products)) {
            throw new Exception('Invalid product payload');
        }

        $labelSize = $job['label_size'] ?? 'k22';
        $enriched = [];
        foreach ($products as $p) {
            $stmt = ($pdo ?? $GLOBALS['pdo'])->prepare('SELECT name, price, sku FROM products WHERE id = ? AND tenant_id = ' . (int)($job['tenant_id'] ?? 0) . ' LIMIT 1');
            $stmt->execute([(int)$p['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $enriched[] = [
                    'id'    => (int)$p['id'],
                    'name'  => $row['name'],
                    'price' => $row['price'],
                    'sku'   => $row['sku'],
                    'qty'   => max(1, (int)($p['qty'] ?? 1)),
                ];
            }
        }

        if (empty($enriched)) {
            throw new Exception('No valid products found for PDF');
        }

        $template = \JDH\POS\Barcode\LabelTemplate::get($labelSize) ?? \JDH\POS\Barcode\LabelTemplate::get('k22');
        $renderer = new \JDH\POS\Barcode\LabelRenderer(new \JDH\POS\Barcode\BarcodeService());
        $pdfData = $renderer->prepareForPdf($enriched, $template, $storeName);

        $outputFile = 'storage/label_jobs/output/' . $jobId . '.pdf';
        $outputPath = __DIR__ . '/../../' . $outputFile;
        (new \JDH\POS\Barcode\PdfLabelGenerator())->savePdf($pdfData, $outputPath);

        $job['status'] = 'done';
        $job['output_file'] = $outputFile;
        $job['updated_at'] = time();
    } catch (Exception $e) {
        $job['status'] = 'failed';
        $job['error'] = $e->getMessage();
        $job['updated_at'] = time();
    }

    $fp = fopen($jobFile, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($job, JSON_PRETTY_PRINT));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    return $job;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Lightweight diagnostic — no CSRF needed, returns server health
    if ($action === 'ping') {
        echo json_encode([
            'success' => true,
            'tenant_id' => $tenant_id,
            'branch_id' => $branch_id,
            'php_version' => PHP_VERSION,
            'barcode_available' => $barcode_available,
        ]);
        exit;
    }

    // ==================== PRODUCT FETCHING ====================
    if ($action === 'get_products') {
        try {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $search = trim($_POST['search'] ?? '');
            $category = $_POST['category'] ?? $_POST['category_id'] ?? '';
            
            // Fetch products directly
            $where = "WHERE p.deleted_at IS NULL";
            $params = [];

            if (!empty($search)) {
                $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            if (!empty($category)) {
                $where .= " AND p.category_id = ?";
                $params[] = $category;
            }

            // Always filter by tenant_id (core schema requirement)
            $where .= " AND p.tenant_id = ?";
            $params[] = $tenant_id;

            $query = "SELECT p.id, p.name, p.sku, p.price FROM products p {$where} ORDER BY p.name LIMIT 500";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($products as &$p) {
                if (empty($p['sku'])) {
                    $p['sku'] = 'SKU' . str_pad($p['id'], 8, '0', STR_PAD_LEFT);
                }
            }

            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ==================== BARCODE GENERATION (SVG for browser preview) ====================
    if ($action === 'generate_barcodes') {
        try {
            $ensure_barcode_available();

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $products = json_decode($_POST['products'] ?? '[]', true);
            if (empty($products)) {
                throw new Exception('No products selected');
            }

            $barcodes = [];

            foreach ($products as $item) {
                $product_id = (int)$item['id'];
                if (empty($item['sku'])) {
                    continue;
                }

                $sku = $item['sku'];

                try {
                    $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
                    $barcode_svg = $generator->getBarcode($sku, \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128);
                    $svg_base64 = base64_encode($barcode_svg);

                    $barcodes[$product_id] = [
                        'svg_base64' => $svg_base64,
                        'sku' => $sku
                    ];
                } catch (Exception $e) {
                    error_log("Barcode generation error for SKU $sku: " . $e->getMessage());
                    continue;
                }
            }

            echo json_encode([
                'success' => true,
                'barcodes' => $barcodes,
                'count' => count($barcodes)
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ==================== PREVIEW (HTML) ====================
    if ($action === 'get_preview') {
        try {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $products = json_decode($_POST['products'] ?? '[]', true);
            $labelSize = trim($_POST['label_size'] ?? 'k22');

            if (empty($products)) {
                throw new Exception('No products selected');
            }

            // Label size definitions: [w_mm, h_mm, cols, font_pt, barcode_slot_mm, circle]
            $sizes = [
                'k5'  => ['w'=>38,'h'=>16,'cols'=>5, 'font'=>6,  'bc'=>6,  'circle'=>false],
                'k22' => ['w'=>51,'h'=>25,'cols'=>4, 'font'=>7,  'bc'=>10, 'circle'=>false],
                'KA2' => ['w'=>51,'h'=>13,'cols'=>4, 'font'=>5,  'bc'=>5,  'circle'=>false],
                'K38' => ['w'=>76,'h'=>38,'cols'=>2, 'font'=>8,  'bc'=>16, 'circle'=>false],
                'K11' => ['w'=>19,'h'=>13,'cols'=>10,'font'=>5,  'bc'=>5,  'circle'=>false],
                'KA1' => ['w'=>25,'h'=>19,'cols'=>8, 'font'=>5,  'bc'=>7,  'circle'=>false],
                'K27' => ['w'=>51,'h'=>38,'cols'=>4, 'font'=>7,  'bc'=>14, 'circle'=>false],
                'k36' => ['w'=>76,'h'=>51,'cols'=>2, 'font'=>9,  'bc'=>20, 'circle'=>false],
                'K05' => ['w'=>13,'h'=>13,'cols'=>14,'font'=>4,  'bc'=>5,  'circle'=>true],
                'K09' => ['w'=>22,'h'=>22,'cols'=>9, 'font'=>5,  'bc'=>8,  'circle'=>true],
                'K15' => ['w'=>49,'h'=>49,'cols'=>3, 'font'=>7,  'bc'=>18, 'circle'=>true],
            ];
            $sz = $sizes[$labelSize] ?? $sizes['k22'];

            $w     = $sz['w'];
            $h     = $sz['h'];
            $cols  = $sz['cols'];
            $font  = $sz['font'];
            $bcH   = $sz['bc'];
            $isCircle = $sz['circle'];

            // A4 usable width ~190mm; perPage based on cols and 297mm height with 10mm margin
            $perPage = $cols * (int)floor((297 - 20) / ($h + 1));
            if ($perPage < 1) $perPage = 1;

            // Expand products by qty into flat list of labels
            $labels = [];
            foreach ($products as $item) {
                $qty = max(1, (int)($item['qty'] ?? 1));
                $sku  = htmlspecialchars($item['sku']   ?? '', ENT_QUOTES);
                $name = htmlspecialchars($item['name']  ?? '', ENT_QUOTES);
                $price = number_format((float)($item['price'] ?? 0), 2);

                // Try generating barcode SVG
                $barcodeSvg = '';
                if (!empty($sku) && class_exists('\Picqer\Barcode\BarcodeGeneratorSVG')) {
                    try {
                        $gen = new \Picqer\Barcode\BarcodeGeneratorSVG();
                        $svg = $gen->getBarcode($sku, \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128);
                        // Force black bars - SVGs from picqer are already black
                        $barcodeSvg = $svg;
                    } catch (\Exception $e) {
                        // no barcode
                    }
                }

                for ($i = 0; $i < $qty; $i++) {
                    $labels[] = compact('sku', 'name', 'price', 'barcodeSvg');
                }
            }

            // Chunk into pages
            $pages = array_chunk($labels, $perPage);
            $circleClass = $isCircle ? ' label-circle' : '';

            // Sheet CSS variable overrides for this size
            $sheetVars = '--label-w:' . $w . 'mm;--label-h:' . $h . 'mm;'
                . '--label-font-size-pt:' . $font . ';'
                . '--barcode-slot-height-mm:' . $bcH . ';';
            $gridStyle = 'grid-template-columns:repeat(' . $cols . ',' . $w . 'mm);'
                . 'grid-auto-rows:' . $h . 'mm;'
                . 'gap:0.5mm;padding:5mm;background:white;';

            $html = '';
            foreach ($pages as $page) {
                $html .= '<div class="label-sheet" style="' . $sheetVars . $gridStyle . '">';
                foreach ($page as $lbl) {
                    $labelStyle = 'width:' . $w . 'mm;height:' . $h . 'mm;';
                    $html .= '<div class="label' . $circleClass . '" style="' . $labelStyle . '">';

                    // Store name
                    $html .= '<div class="label-store">' . htmlspecialchars($store_name ?? '', ENT_QUOTES) . '</div>';

                    // Product name
                    $html .= '<div class="label-name">' . $lbl['name'] . '</div>';

                    // Barcode or SKU fallback
                    if ($lbl['barcodeSvg']) {
                        $html .= '<div class="label-barcode">' . $lbl['barcodeSvg'] . '</div>';
                    } else {
                        $html .= '<div class="label-barcode" style="letter-spacing:1px;font-size:' . max(6, $font - 1) . 'pt;font-weight:700;">'
                            . $lbl['sku'] . '</div>';
                    }

                    // SKU text
                    $html .= '<div class="label-sku">' . $lbl['sku'] . '</div>';

                    // Price
                    $html .= '<div class="label-price">KES ' . $lbl['price'] . '</div>';

                    $html .= '</div>'; // .label
                }
                $html .= '</div>'; // .label-sheet
            }

            echo json_encode(['success' => true, 'html' => $html]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ==================== PDF DOWNLOAD / ASYNC ENQUEUE ====================

    // Enqueue PDF generation job (async) - preferred for SaaS
    if ($action === 'enqueue_pdf') {
        try {
            $ensure_barcode_available();
            $ensure_dompdf_available();

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $products = json_decode($_POST['products'] ?? '[]', true);
            $labelSize = $_POST['label_size'] ?? 'k22';

            if (empty($products)) {
                throw new Exception('No products selected for PDF');
            }

            // Prepare job
            $jobId = bin2hex(random_bytes(12));
            $storageDir = __DIR__ . '/../../storage/label_jobs';
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0755, true);
            }

            $jobFile = $storageDir . '/' . $jobId . '.json';
            $job = [
                'id' => $jobId,
                'tenant_id' => $tenant_id,
                'user_id' => $user_id,
                'status' => 'pending',
                'label_size' => $labelSize,
                'products' => $products,
                'created_at' => time(),
                'updated_at' => time(),
            ];

            file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT));
            logLabelPrinterEvent("PDF job queued: {$jobId} for tenant {$tenant_id}", 'info');

            // Return job id to client for polling
            echo json_encode(['success' => true, 'job_id' => $jobId, 'status_url' => base_url('products/barcode_labels_generate.php')]);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // Check PDF job status and get download availability
    if ($action === 'pdf_job_status') {
        try {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $jobId = $_POST['job_id'] ?? '';
            if (!$jobId) throw new Exception('Missing job_id');

            $job = processPdfJob($jobId, $store_name, $tenant_id, $pdo);
            logLabelPrinterEvent("PDF job status checked: {$jobId} status={$job['status']}", 'info');
            $result = ['success' => true, 'job' => $job];

            if (!empty($job['status']) && $job['status'] === 'done' && !empty($job['output_file'])) {
                $result['download_url'] = base_url('products/barcode_labels_generate.php') . '?action=get_pdf&job_id=' . urlencode($jobId) . '&csrf_token=' . urlencode(generate_csrf_token());
            }

            echo json_encode($result);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // Stream generated PDF by job id
    if ($action === 'get_pdf' || isset($_GET['action']) && $_GET['action'] === 'get_pdf') {
        try {
            if (!verify_csrf_token($_REQUEST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $jobId = $_REQUEST['job_id'] ?? '';
            if (!$jobId) throw new Exception('Missing job_id');

            $storageDir = __DIR__ . '/../../storage/label_jobs';
            $jobFile = $storageDir . '/' . $jobId . '.json';
            if (!file_exists($jobFile)) {
                throw new Exception('Job not found');
            }

            $job = json_decode(file_get_contents($jobFile), true);

            // Tenant isolation: only the owning tenant can download
            if (!isset($job['tenant_id']) || (int)$job['tenant_id'] !== $tenant_id) {
                throw new Exception('Job not found');
            }

            if (empty($job['status']) || $job['status'] !== 'done' || empty($job['output_file'])) {
                throw new Exception('Job not complete');
            }

            $pdfPath = __DIR__ . '/../../' . ltrim($job['output_file'], '/\\');
            if (!file_exists($pdfPath)) {
                throw new Exception('Output file not found');
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
            readfile($pdfPath);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // Fallback: immediate synchronous PDF generation (backwards compatible)
    if ($action === 'download_pdf') {
        try {
            $ensure_barcode_available();
            $ensure_dompdf_available();

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $products = json_decode($_POST['products'] ?? '[]', true);
            $labelSize = $_POST['label_size'] ?? 'k22';
            $template = \JDH\POS\Barcode\LabelTemplate::get($labelSize) ?? \JDH\POS\Barcode\LabelTemplate::get('k22');

            if (empty($products)) {
                throw new Exception('No products selected for PDF');
            }

            // Enrich products with current data (tenant-scoped)
            $enriched = [];
            foreach ($products as $p) {
                $stmt = $pdo->prepare("SELECT name, price, sku FROM products WHERE id = ? AND tenant_id = ? LIMIT 1");
                $stmt->execute([(int)$p['id'], $tenant_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $enriched[] = [
                        'id'    => (int)$p['id'],
                        'name'  => $row['name'],
                        'price' => $row['price'],
                        'sku'   => $row['sku'],
                        'qty'   => (int)($p['qty'] ?? 1),
                    ];
                }
            }

            $renderer = new \JDH\POS\Barcode\LabelRenderer(new \JDH\POS\Barcode\BarcodeService());
            $pdfData = $renderer->prepareForPdf($enriched, $template, $store_name);

            $pdfGen = new \JDH\POS\Barcode\PdfLabelGenerator();
            $pdfGen->generatePdf($pdfData, 'barcode-labels-' . date('Ymd-His') . '.pdf');

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================== REPRINT LAST ====================
    if ($action === 'reprint_last') {
        try {
            $ensure_barcode_available();

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $method = $_POST['method'] ?? 'browser';
            $result = $getLabelPrintService()->reprintLastLabels(1, $method);

            if ($result['success'] && isset($result['html'])) {
                echo json_encode([
                    'success' => true,
                    'html' => $result['html']
                ]);
            } elseif ($result['success']) {
                echo json_encode($result);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $result['message'] ?? 'No previous labels found'
                ]);
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ==================== GET PREVIEW (delegated to LabelPrintService + LabelRenderer) ====================
    if ($action === 'get_preview') {
        try {
            $ensure_barcode_available();

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $products = json_decode($_POST['products'] ?? '[]', true);
            $labelSize = $_POST['label_size'] ?? 'k22';

            if (empty($products)) {
                throw new Exception('No products selected for preview');
            }

            $result = $getLabelPrintService()->printLabels($products, $labelSize, 'browser');

            if (!empty($result['html'])) {
                echo json_encode([
                    'success' => true,
                    'html' => $result['html'],
                    'count' => count($products)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Could not generate preview'
                ]);
            }
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ==================== BATCH PRINT ====================
    if ($action === 'batch_print') {
        try {
            $ensure_barcode_available();

            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $filter = $_POST['filter'] ?? 'low_stock';
            $labelSize = $_POST['label_size'] ?? 'k22';
            $method = $_POST['method'] ?? $_POST['print_method'] ?? 'browser';
            $result = $getLabelPrintService()->batchPrint($resolve_batch_filters($filter), $labelSize, $method);

            echo json_encode($result);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ==================== DOWNLOAD CSV TEMPLATE ====================
    if ($action === 'download_csv_template') {
        // Override JSON header for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="products-template.csv"');
        echo \JDH\POS\Barcode\ProductImporter::generateTemplate();
        exit;
    }

    // ==================== BATCH IMPORT ====================
    if ($action === 'batch_import') {
        try {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            if (!isset($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }

            $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $importer = new \JDH\POS\Barcode\ProductImporter($pdo, $tenant_id);

            $result = $importer->processBatch($_FILES['file'], $category_id);
            echo json_encode($result);
            exit;
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // ==================== SAVE SETTING (uses new SettingsManager for label printing keys) ====================
    if ($action === 'save_setting') {
        try {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF');
            }
            $key = $_POST['key'] ?? '';
            $value = $_POST['value'] ?? '';

            // Use the new tenant_settings table for label printing settings
            if (in_array($key, ['auto_print_labels_on_sale', 'auto_print_label_size', 'auto_print_method'], true)) {
                $settingsManager = new SettingsManager($pdo, $tenant_id);
                $success = $settingsManager->set($key, $value);
                echo json_encode(['success' => $success]);
            } else {
                // Fall back to old system for other settings
                if (function_exists('update_setting')) {
                    update_setting($key, $value, $tenant_id);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false]);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================== SAVE LABEL TEMPLATE ====================
    if ($action === 'save_label_template') {
        try {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $labelSize = $_POST['label_size'] ?? 'k22';
            $config = $_POST['config'] ?? '[]';
            $key = 'label_template_' . $labelSize;

            if (function_exists('update_setting')) {
                update_setting($key, $config, $tenant_id);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ==================== GET LABEL TEMPLATE ====================
    if ($action === 'get_label_template') {
        try {
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }

            $labelSize = $_POST['label_size'] ?? 'k22';
            $key = 'label_template_' . $labelSize;
            $config = get_settings($key, '[]', $tenant_id);

            echo json_encode([
                'success' => true,
                'config' => $config
            ]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // If no action matched
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// If not POST, return error (this endpoint only handles POST)
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
exit;
