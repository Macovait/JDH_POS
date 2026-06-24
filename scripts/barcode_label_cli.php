<?php
/**
 * Barcode Label System - CLI Tool
 * 
 * Command-line interface for batch operations, barcode generation, and maintenance
 * 
 * Usage:
 *   php scripts/barcode_label_cli.php --action=generate-missing --company=1
 *   php scripts/barcode_label_cli.php --action=bulk-update --file=updates.csv
 *   php scripts/barcode_label_cli.php --action=validate-barcodes --company=1
 * 
 * @package Jakababa
 * @subpackage CLI
 * @version 1.0
 */

// Prevent execution through web
if (php_sapi_name() !== 'cli') {
    exit("This script can only be run from command line.\n");
}

require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('barcode_label_functions.php', 'src', true);

// Color codes for CLI output
class CLI {
    const RESET = "\033[0m";
    const BLACK = "\033[30m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const BOLD = "\033[1m";

    public static function info($message) {
        echo self::CYAN . "ℹ " . $message . self::RESET . "\n";
    }

    public static function success($message) {
        echo self::GREEN . "✓ " . $message . self::RESET . "\n";
    }

    public static function error($message) {
        echo self::RED . "✗ " . $message . self::RESET . "\n";
    }

    public static function warning($message) {
        echo self::YELLOW . "⚠ " . $message . self::RESET . "\n";
    }

    public static function header($message) {
        echo "\n" . self::BOLD . self::BLUE . str_repeat("=", 60) . self::RESET . "\n";
        echo self::BOLD . self::BLUE . "  " . $message . self::RESET . "\n";
        echo self::BOLD . self::BLUE . str_repeat("=", 60) . self::RESET . "\n";
    }

    public static function line() {
        echo "\n";
    }
}

// Parse command line arguments
$args = getopt('', [
    'action:',
    'company:',
    'start:',
    'category:',
    'file:',
    'format:',
    'confirm',
    'help'
]);

// Display help
if (isset($args['help'])) {
    displayHelp();
    exit(0);
}

// Required parameters
$action = $args['action'] ?? null;
$tenant_id = (int)($args['tenant'] ?? 1);

if (!$action) {
    CLI::error('No action specified');
    displayHelp();
    exit(1);
}

try {
    $pdo = get_db_connection();

    // Verify company exists
    if ($tenant_id > 0) {
        $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ?");
        $stmt->execute([$tenant_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            CLI::error("Tenant ID $tenant_id not found");
            exit(1);
        }

        CLI::info("Working with company: " . $company['name']);
    }

    $barcodeLabel = new BarcodeLabel($pdo, $tenant_id);

    // Execute action
    switch ($action) {
        case 'generate-missing':
            generateMissingBarcodes($pdo, $barcodeLabel, $args);
            break;

        case 'validate-barcodes':
            validateBarcodes($pdo, $barcodeLabel, $args);
            break;

        case 'bulk-update':
            bulkUpdateBarcodes($pdo, $barcodeLabel, $args);
            break;

        case 'repair-duplicates':
            repairDuplicateBarcodes($pdo, $barcodeLabel, $args);
            break;

        case 'export-report':
            exportReport($pdo, $barcodeLabel, $args);
            break;

        case 'migrate-from-sku':
            migrateFromSKU($pdo, $barcodeLabel, $args);
            break;

        case 'generate-stats':
            generateStatistics($pdo, $barcodeLabel, $args);
            break;

        default:
            CLI::error("Unknown action: $action");
            exit(1);
    }

    CLI::success("Operation completed successfully");
    exit(0);

} catch (Exception $e) {
    CLI::error("Error: " . $e->getMessage());
    exit(1);
}

// ======================== ACTION FUNCTIONS ========================

function generateMissingBarcodes($pdo, $barcodeLabel, $args) {
    CLI::header('GENERATE MISSING BARCODES');

    $start = (int)($args['start'] ?? 10000);
    $category_id = isset($args['category']) ? (int)$args['category'] : null;

    CLI::info("Start number: $start");
    if ($category_id) {
        CLI::info("Category filter: $category_id");
    }

    // Find products without barcodes
    $query = "SELECT COUNT(*) as count FROM products WHERE barcode IS NULL AND active = 1 AND deleted_at IS NULL AND tenant_id = ?";
    $params = [$barcodeLabel->tenant_id];

    if ($category_id) {
        $query .= " AND category_id = ?";
        $params[] = $category_id;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'] ?? 0;

    if ($count === 0) {
        CLI::info("No products without barcodes found");
        return;
    }

    CLI::info("Found $count products without barcodes");

    // Confirm before proceeding
    if (!isset($args['confirm'])) {
        echo "\nContinue? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        if ($line !== 'yes') {
            CLI::warning('Operation cancelled');
            return;
        }
    }

    $updates = $barcodeLabel->generateMissingBarcodes($start, $category_id);

    CLI::success("Generated barcodes for $count products");
    CLI::info("Starting from: $start");
    CLI::info("First barcode: " . str_pad($start, 8, '0', STR_PAD_LEFT));
    CLI::info("Last barcode: " . str_pad($start + $count - 1, 8, '0', STR_PAD_LEFT));
}

function validateBarcodes($pdo, $barcodeLabel, $args) {
    CLI::header('VALIDATE BARCODES');

    $query = "SELECT id, name, sku, barcode, barcode_format FROM products WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL AND barcode IS NOT NULL";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$barcodeLabel->tenant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    CLI::info("Validating " . count($products) . " products");

    $invalid = 0;
    $warnings = 0;
    $valid = 0;

    foreach ($products as $product) {
        $format = $product['barcode_format'] ?? 'CODE128';
        $barcode = $product['barcode'];

        if (!$barcodeLabel->isValidBarcode($barcode, $format)) {
            CLI::error("Product {$product['id']}: {$product['name']} - Invalid barcode: $barcode ($format)");
            $invalid++;
        } else {
            $valid++;
        }
    }

    CLI::line();
    CLI::info("Validation Summary:");
    CLI::success("Valid: $valid");
    if ($invalid > 0) {
        CLI::error("Invalid: $invalid");
    }
}

function bulkUpdateBarcodes($pdo, $barcodeLabel, $args) {
    CLI::header('BULK UPDATE BARCODES');

    $file = $args['file'] ?? null;

    if (!$file || !file_exists($file)) {
        CLI::error("CSV file not found: $file");
        CLI::info("Expected format:");
        CLI::info("  product_id,barcode,format");
        CLI::info("  123,1234567890,CODE128");
        exit(1);
    }

    if (($handle = fopen($file, "r")) === false) {
        CLI::error("Cannot open file: $file");
        exit(1);
    }

    $header = fgetcsv($handle);
    $updates = [];
    $lineNum = 2;
    $errors = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 2) {
            CLI::warning("Line $lineNum: Missing columns");
            $errors++;
            continue;
        }

        $product_id = (int)$row[0];
        $barcode = trim($row[1]);
        $format = trim($row[2] ?? 'CODE128');

        if (!$product_id || !$barcode) {
            CLI::warning("Line $lineNum: Invalid data");
            $errors++;
            continue;
        }

        $updates[] = [
            'product_id' => $product_id,
            'barcode' => $barcode,
            'format' => $format
        ];

        $lineNum++;
    }

    fclose($handle);

    CLI::info("Ready to update " . count($updates) . " barcodes");
    if ($errors > 0) {
        CLI::warning("$errors errors found in CSV");
    }

    if (!isset($args['confirm'])) {
        echo "\nContinue? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        if ($line !== 'yes') {
            CLI::warning('Operation cancelled');
            return;
        }
    }

    $affected = $barcodeLabel->batchUpdateBarcodes($updates);
    CLI::success("Updated $affected barcodes");
}

function repairDuplicateBarcodes($pdo, $barcodeLabel, $args) {
    CLI::header('REPAIR DUPLICATE BARCODES');

    $query = "
        SELECT barcode, COUNT(*) as count, GROUP_CONCAT(id) as ids
        FROM products
        WHERE tenant_id = ? AND barcode IS NOT NULL AND deleted_at IS NULL
        GROUP BY barcode
        HAVING count > 1
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$barcodeLabel->tenant_id]);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($duplicates)) {
        CLI::info("No duplicate barcodes found");
        return;
    }

    CLI::warning("Found " . count($duplicates) . " duplicate barcodes");

    $start = (int)($args['start'] ?? 99000);
    $counter = $start;

    foreach ($duplicates as $dup) {
        $ids = array_map('intval', explode(',', $dup['ids']));
        CLI::info("Barcode: {$dup['barcode']} (used {$dup['count']} times)");

        // Keep first, update others
        $keep_id = array_shift($ids);
        CLI::info("  Keeping: $keep_id");

        foreach ($ids as $id) {
            $new_barcode = str_pad($counter, 8, '0', STR_PAD_LEFT);
            // SECURITY: Always filter by tenant_id to prevent cross-tenant data modification
            $update_stmt = $pdo->prepare("UPDATE products SET barcode = ? WHERE id = ? AND tenant_id = ?");
            $update_stmt->execute([$new_barcode, $id, $barcodeLabel->tenant_id]);
            if ($update_stmt->rowCount() > 0) {
                CLI::info("  Fixed $id -> $new_barcode");
            } else {
                CLI::warning("  Product $id not found or not in company {$barcodeLabel->tenant_id}");
            }
            $counter++;
        }
    }

    CLI::success("Repaired duplicate barcodes");
}

function exportReport($pdo, $barcodeLabel, $args) {
    CLI::header('EXPORT BARCODE REPORT');

    $filename = $args['file'] ?? 'barcode_report_' . date('Y-m-d_His') . '.csv';

    $query = "
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.barcode,
            p.barcode_format,
            p.price,
            COALESCE(c.name, 'Uncategorized') as category,
            p.active,
            p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.tenant_id = ? AND p.deleted_at IS NULL
        ORDER BY p.name
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$barcodeLabel->tenant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fp = fopen($filename, 'w');

    // Headers
    fputcsv($fp, ['ID', 'Name', 'SKU', 'Barcode', 'Format', 'Price (KSh)', 'Category', 'Active', 'Created']);

    // Data
    foreach ($products as $product) {
        fputcsv($fp, [
            $product['id'],
            $product['name'],
            $product['sku'] ?? '',
            $product['barcode'] ?? '',
            $product['barcode_format'],
            number_format($product['price'], 2, '.', ''),
            $product['category'],
            $product['active'] ? 'Yes' : 'No',
            $product['created_at']
        ]);
    }

    fclose($fp);

    CLI::success("Report exported to: $filename");
    CLI::info("Total products: " . count($products));
}

function migrateFromSKU($pdo, $barcodeLabel, $args) {
    CLI::header('MIGRATE BARCODES FROM SKU');

    $query = "SELECT COUNT(*) as count FROM products WHERE tenant_id = ? AND barcode IS NULL AND sku IS NOT NULL AND deleted_at IS NULL";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$barcodeLabel->tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'] ?? 0;

    if ($count === 0) {
        CLI::info("No products with SKU but no barcode found");
        return;
    }

    CLI::info("Found $count products to migrate");

    if (!isset($args['confirm'])) {
        echo "\nContinue? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        if ($line !== 'yes') {
            CLI::warning('Operation cancelled');
            return;
        }
    }

    $update_stmt = $pdo->prepare("UPDATE products SET barcode = sku WHERE tenant_id = ? AND barcode IS NULL AND sku IS NOT NULL");
    $update_stmt->execute([$barcodeLabel->tenant_id]);

    CLI::success("Migrated $count products");
}

function generateStatistics($pdo, $barcodeLabel, $args) {
    CLI::header('BARCODE STATISTICS');

    $stats = $barcodeLabel->getPrintingStatistics(30);

    CLI::info("Last 30 days:");
    CLI::info("  Total print jobs: " . ($stats['total_print_jobs'] ?? 0));
    CLI::info("  Total labels printed: " . ($stats['total_labels_printed'] ?? 0));
    CLI::info("  Avg labels per job: " . number_format($stats['avg_labels_per_job'] ?? 0, 1));
    CLI::info("  Max labels in one job: " . ($stats['max_labels_in_job'] ?? 0));
    CLI::info("  Unique users: " . ($stats['unique_users'] ?? 0));
    CLI::info("  Days with printing: " . ($stats['print_days'] ?? 0));

    CLI::line();

    // Product statistics
    $query = "
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN barcode IS NOT NULL THEN 1 ELSE 0 END) as with_barcode,
            SUM(CASE WHEN barcode IS NULL THEN 1 ELSE 0 END) as without_barcode
        FROM products
        WHERE tenant_id = ? AND active = 1 AND deleted_at IS NULL
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$barcodeLabel->tenant_id]);
    $prod_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    CLI::info("Product statistics:");
    CLI::info("  Total active products: " . $prod_stats['total_products']);
    CLI::success("  With barcode: " . $prod_stats['with_barcode']);
    if ($prod_stats['without_barcode'] > 0) {
        CLI::warning("  Without barcode: " . $prod_stats['without_barcode']);
    }

    $coverage = $prod_stats['total_products'] > 0 
        ? round(($prod_stats['with_barcode'] / $prod_stats['total_products']) * 100, 1) 
        : 0;
    CLI::info("  Barcode coverage: $coverage%");
}

function displayHelp() {
    CLI::header('BARCODE LABEL SYSTEM - CLI TOOL');

    echo <<<HELP
AVAILABLE ACTIONS:

  generate-missing
    Generate barcodes for products without them
    Options:
      --company=ID        Tenant ID (default: 1)
      --start=NUMBER      Starting barcode number (default: 10000)
      --category=ID       Optional category filter
      --confirm           Skip confirmation prompt

  validate-barcodes
    Check all barcodes for validity
    Options:
      --company=ID        Tenant ID (default: 1)

  bulk-update
    Update barcodes from CSV file
    Options:
      --company=ID        Tenant ID (default: 1)
      --file=PATH         CSV file path (required)
      --confirm           Skip confirmation prompt
    CSV Format:
      product_id,barcode,format
      123,ABC123456789,CODE128

  repair-duplicates
    Fix duplicate barcodes
    Options:
      --company=ID        Tenant ID (default: 1)
      --start=NUMBER      Starting number for repairs (default: 99000)
      --confirm           Skip confirmation prompt

  export-report
    Export barcode report to CSV
    Options:
      --company=ID        Tenant ID (default: 1)
      --file=PATH         Output file path

  migrate-from-sku
    Use SKU values as barcodes for products without them
    Options:
      --company=ID        Tenant ID (default: 1)
      --confirm           Skip confirmation prompt

  generate-stats
    Display barcode usage statistics
    Options:
      --company=ID        Tenant ID (default: 1)

EXAMPLES:

  php scripts/barcode_label_cli.php --action=generate-missing --company=1 --start=10000 --confirm

  php scripts/barcode_label_cli.php --action=bulk-update --company=1 --file=updates.csv

  php scripts/barcode_label_cli.php --action=validate-barcodes --company=1

HELP;

    CLI::line();
}

?>
