<?php

namespace JDH\POS\Barcode;

/**
 * Barcode Label Controller
 * 
 * Centralized AJAX request handler with:
 * - Rate limiting
 * - Enhanced error handling
 * - Batch import support
 * - All barcode operations
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/BarcodeErrorHandler.php';
require_once __DIR__ . '/ProductImporter.php';
require_once __DIR__ . '/LabelTemplate.php';
require_once __DIR__ . '/BarcodeService.php';
require_once __DIR__ . '/LabelRenderer.php';
require_once __DIR__ . '/PdfLabelGenerator.php';
require_once __DIR__ . '/LabelPrintService.php';
require_once __DIR__ . '/../SettingsManager.php';

class BarcodeLabelsController
{
    private $pdo;
    private $tenant_id;
    private $branch_id;
    private $user_id;
    private $rateLimiter;
    private $errorHandler;
    private $settingsManager;
    private $barcode_available;
    private $csrf_token;

    public function __construct()
    {
        // Initialize
        header('Content-Type: application/json');
        require_login();

        $this->pdo = get_db_connection();
        $this->tenant_id = get_current_tenant_id();
        $this->branch_id = get_current_branch_id();
        $this->user_id = function_exists('get_current_user_id')
            ? get_current_user_id()
            : (int)($_SESSION['user']['id'] ?? 0);

        if (!$this->tenant_id) {
            $this->error('Company context missing', 403);
        }

        if (!check_permission('products.view') && !is_super_admin()) {
            $this->error('Permission denied', 403);
        }

        // Services
        $this->rateLimiter = new RateLimiter($this->pdo, $this->tenant_id, $this->user_id);
        $this->errorHandler = new BarcodeErrorHandler(false);
        $this->settingsManager = new SettingsManager($this->pdo, $this->tenant_id);

        // CSRF
        $this->csrf_token = generate_csrf_token();
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $this->error('Invalid security token', 403);
        }

        // Check barcode library
        $this->checkBarcodeLibrary();
    }

    /**
     * Route and dispatch request
     */
    public function dispatch()
    {
        $action = $_POST['action'] ?? null;

        try {
            switch ($action) {
                case 'get_products':
                    $this->getProducts();
                    break;
                case 'generate_barcodes':
                    $this->generateBarcodes();
                    break;
                case 'download_pdf':
                    $this->downloadPdf();
                    break;
                case 'batch_import':
                    $this->batchImport();
                    break;
                case 'download_csv_template':
                    $this->downloadCsvTemplate();
                    break;
                case 'reprint_last':
                    $this->reprintLast();
                    break;
                case 'batch_print':
                    $this->batchPrint();
                    break;
                case 'save_label_template':
                    $this->saveLabelTemplate();
                    break;
                case 'get_label_template':
                    $this->getLabelTemplate();
                    break;
                case 'save_setting':
                    $this->saveSetting();
                    break;
                default:
                    http_response_code(400);
                    $this->json(['success' => false, 'error' => 'Unknown action']);
            }
        } catch (\Exception $e) {
            error_log("Barcode controller error: " . $e->getMessage());
            http_response_code(400);
            $this->json($this->errorHandler->ajaxError(
                'ERROR',
                $e->getMessage()
            ));
        }
    }

    /**
     * ACTION: Get Products with Filters
     */
    private function getProducts()
    {
        $search = trim($_POST['search'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

        $query = "SELECT p.id, p.name, p.sku, p.price, p.category_id FROM products p WHERE p.deleted_at IS NULL";
        $params = [];

        // Always filter by tenant_id (core schema requirement)
        $query .= " AND p.tenant_id = ?";
        $params[] = $this->tenant_id;

        if ($search) {
            $query .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if ($category_id) {
            $query .= " AND p.category_id = ?";
            $params[] = $category_id;
        }

        $query .= " ORDER BY p.name LIMIT 500";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Apply SKU defaults and validation
        foreach ($products as &$p) {
            if (empty($p['sku'])) {
                $p['sku'] = 'SKU' . str_pad($p['id'], 8, '0', STR_PAD_LEFT);
            }
            $validation = $this->errorHandler->validateProduct($p);
            $p['validation'] = $validation;
        }

        $this->json([
            'success' => true,
            'products' => $products,
            'count' => count($products),
        ]);
    }

    /**
     * ACTION: Generate Barcode SVG Images
     */
    private function generateBarcodes()
    {
        if (!$this->barcode_available) {
            throw new \Exception('Barcode library not available');
        }

        // Rate limit
        $rl = $this->rateLimiter->checkBarcodeGeneration();
        if (!$rl['allowed']) {
            http_response_code(429);
            $this->json($this->errorHandler->ajaxError(
                'RATE_LIMITED',
                'Too many requests. Please wait.',
                $rl
            ));
            return;
        }

        $products = json_decode($_POST['products'] ?? '[]', true);
        if (empty($products)) {
            throw new \Exception('No products selected');
        }

        $barcodes = [];
        $generation_errors = [];

        foreach ($products as $item) {
            $product_id = (int)$item['id'];
            $sku = $item['sku'] ?? null;

            // Validate SKU
            $sku_validation = $this->errorHandler->validateSku($sku);
            if (!$sku_validation['valid']) {
                $generation_errors[] = [
                    'product_id' => $product_id,
                    'sku' => $sku,
                    'error' => $sku_validation['error'],
                ];
                continue;
            }

            try {
                $generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
                $barcode_svg = $generator->getBarcode(
                    $sku_validation['sanitized'],
                    \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128
                );

                $barcodes[$product_id] = [
                    'svg_base64' => base64_encode($barcode_svg),
                    'sku' => $sku_validation['sanitized'],
                ];
            } catch (\Exception $e) {
                error_log("Barcode gen error: " . $e->getMessage());
                $generation_errors[] = [
                    'product_id' => $product_id,
                    'sku' => $sku,
                    'error' => 'Generation failed',
                ];
            }
        }

        $this->json([
            'success' => empty($generation_errors) || !empty($barcodes),
            'barcodes' => $barcodes,
            'count' => count($barcodes),
            'errors' => $generation_errors,
        ]);
    }

    /**
     * ACTION: Download PDF
     */
    private function downloadPdf()
    {
        if (!$this->barcode_available) {
            throw new \Exception('Barcode library not available');
        }

        // Rate limit
        $rl = $this->rateLimiter->checkPdfExport();
        if (!$rl['allowed']) {
            http_response_code(429);
            $this->json($this->errorHandler->ajaxError(
                'RATE_LIMITED',
                'PDF export rate limit exceeded',
                $rl
            ));
            return;
        }

        $products = json_decode($_POST['products'] ?? '[]', true);
        $labelSize = $_POST['label_size'] ?? 'k22';

        if (empty($products)) {
            throw new \Exception('No products selected');
        }

        $template = LabelTemplate::get($labelSize) ?? LabelTemplate::get('k22');

        // Enrich products
        $enriched = [];
        foreach ($products as $p) {
            $stmt = $this->pdo->prepare("SELECT name, price, sku FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$p['id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $enriched[] = [
                    'id' => (int)$p['id'],
                    'name' => $row['name'],
                    'price' => $row['price'],
                    'sku' => $row['sku'],
                    'qty' => (int)($p['qty'] ?? 1),
                ];
            }
        }

        $store_name = LabelPrintService::resolveStoreName($this->pdo, $this->tenant_id);
        $renderer = new LabelRenderer(new BarcodeService());
        $pdfData = $renderer->prepareForPdf($enriched, $template, $store_name);

        $pdfGen = new PdfLabelGenerator();
        $pdfGen->generatePdf($pdfData, 'barcode-labels-' . date('Ymd-His') . '.pdf');
    }

    /**
     * ACTION: Batch Import
     */
    private function batchImport()
    {
        // Rate limit
        $rl = $this->rateLimiter->checkBatchImport();
        if (!$rl['allowed']) {
            http_response_code(429);
            $this->json($this->errorHandler->ajaxError(
                'RATE_LIMITED',
                'Batch import rate limit exceeded',
                $rl
            ));
            return;
        }

        if (!isset($_FILES['file'])) {
            throw new \Exception('No file uploaded');
        }

        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $importer = new ProductImporter($this->pdo, $this->tenant_id);

        $result = $importer->processBatch($_FILES['file'], $category_id);
        $this->json($result);
    }

    /**
     * ACTION: Download CSV Template
     */
    private function downloadCsvTemplate()
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="products-template.csv"');
        echo ProductImporter::generateTemplate();
        exit;
    }

    /**
     * ACTION: Reprint Last
     */
    private function reprintLast()
    {
        $labelPrintService = new LabelPrintService($this->pdo, $this->tenant_id, $this->branch_id, $this->user_id);
        $result = $labelPrintService->reprintLastLabels(1, 'pdf');
        $this->json($result);
    }

    /**
     * ACTION: Batch Print
     */
    private function batchPrint()
    {
        $filter = $_POST['filter'] ?? 'low_stock';
        $labelSize = $_POST['label_size'] ?? 'k22';
        $labelPrintService = new LabelPrintService($this->pdo, $this->tenant_id, $this->branch_id, $this->user_id);
        $result = $labelPrintService->batchPrint(['type' => $filter], $labelSize, 'pdf');
        $this->json($result);
    }

    /**
     * ACTION: Save Label Template
     */
    private function saveLabelTemplate()
    {
        $labelSize = $_POST['label_size'] ?? 'k22';
        $config = $_POST['config'] ?? '[]';
        $key = 'label_template_' . $labelSize;
        update_setting($key, $config, $this->tenant_id);
        $this->json(['success' => true]);
    }

    /**
     * ACTION: Get Label Template
     */
    private function getLabelTemplate()
    {
        $labelSize = $_POST['label_size'] ?? 'k22';
        $key = 'label_template_' . $labelSize;
        $config = function_exists('get_settings') ? get_settings($key, '[]', $this->tenant_id) : '[]';
        $this->json(['success' => true, 'config' => $config]);
    }

    /**
     * ACTION: Save Setting
     */
    private function saveSetting()
    {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        if ($key && function_exists('update_setting')) {
            update_setting($key, $value, $this->tenant_id);
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false]);
        }
    }

    /**
     * Check barcode library availability
     */
    private function checkBarcodeLibrary()
    {
        $this->barcode_available = false;

        try {
            if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
                require_once __DIR__ . '/../../vendor/autoload.php';
                if (class_exists('\Picqer\Barcode\BarcodeGenerator')) {
                    $this->barcode_available = true;
                }
            }
        } catch (\Exception $e) {
            error_log("Barcode library check failed: " . $e->getMessage());
        }
    }

    /**
     * Send JSON response
     */
    private function json($data)
    {
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    private function error($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

// Execute if called directly
if (php_sapi_name() !== 'cli') {
    $controller = new BarcodeLabelsController();
    $controller->dispatch();
}
