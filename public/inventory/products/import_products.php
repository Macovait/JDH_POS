<?php
/**
 * Import Products from CSV/Excel
 * 
 * Allows users to import product data from CSV files.
 */

// Start output buffering
ob_start();

// Bootstrap the application
require_once __DIR__ . '/../../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);

// Require login
require_login();

// Load security bootstrap for has_permission()
require_once __DIR__ . '/../../../src/Security/SecurityBootstrap.php';
SecurityBootstrap::initialize();

// Check if user has permission to import products
if (!has_permission('products.create')) {
    header('Location: products.php?error=permission_denied');
    exit;
}

$error = null;
$success = null;
$imported_count = 0;
$skipped_count = 0;

// Process import form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed. Please try again.';
    } else {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, ['csv', 'txt'])) {
            $error = 'Invalid file format. Please upload a CSV file.';
        } else {
            try {
                $pdo = get_db_connection();
                $tenant_id = $_SESSION['user']['tenant_id'];
                $branch_id = get_current_branch_id();
                $user_id = $_SESSION['user']['id'];

                // Read CSV file
                $handle = fopen($file['tmp_name'], 'r');

                if ($handle === false) {
                    $error = 'Unable to read the uploaded file.';
                } else {
                    // Skip header row
                    fgetcsv($handle);

                    $pdo->beginTransaction();

                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) < 3) {
                            $skipped_count++;
                            continue;
                        }

                        // Map CSV columns
                        $name = trim($data[0] ?? '');
                        $sku = trim($data[1] ?? '');
                        $price = (float) ($data[2] ?? 0);
                        $description = trim($data[3] ?? '');
                        $category_name = trim($data[4] ?? '');
                        $supplier_name = trim($data[5] ?? '');
                        $cost_price = (float) ($data[6] ?? 0);
                        $unit = trim($data[7] ?? 'piece');
                        $tax_rate = (float) ($data[8] ?? 0);
                        $min_stock = (int) ($data[9] ?? 0);
                        $max_stock = (int) ($data[10] ?? 0);
                        $stock = (int) ($data[11] ?? 0);

                        // Validate required fields
                        if (empty($name) || empty($sku) || $price <= 0) {
                            $skipped_count++;
                            continue;
                        }

                        // Check if SKU already exists
                        $existing = db_fetch_one(
                            "SELECT id FROM products WHERE sku = ? AND tenant_id = ?",
                            [$sku, $tenant_id]
                        );

                        if ($existing) {
                            $skipped_count++;
                            continue;
                        }

                        // Get or create category
                        $category_id = null;
                        if (!empty($category_name)) {
                            $category = db_fetch_one(
                                "SELECT id FROM categories WHERE name = ? AND tenant_id = ?",
                                [$category_name, $tenant_id]
                            );

                            if ($category) {
                                $category_id = $category['id'];
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO categories (tenant_id, name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                                $stmt->execute([$tenant_id, $category_name]);
                                $category_id = (int) $pdo->lastInsertId();
                            }
                        }

                        // Get or create supplier
                        $supplier_id = null;
                        if (!empty($supplier_name)) {
                            $supplier = db_fetch_one(
                                "SELECT id FROM suppliers WHERE name = ? AND tenant_id = ?",
                                [$supplier_name, $tenant_id]
                            );

                            if ($supplier) {
                                $supplier_id = $supplier['id'];
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO suppliers (tenant_id, name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                                $stmt->execute([$tenant_id, $supplier_name]);
                                $supplier_id = (int) $pdo->lastInsertId();
                            }
                        }

                        // Insert product
                        $stmt = $pdo->prepare("
                            INSERT INTO products (
                                tenant_id, category_id, name, sku, description,
                                price, cost_price, unit, tax_rate, active, created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $tenant_id,
                            $category_id,
                            $name,
                            $sku,
                            $description,
                            $price,
                            $cost_price,
                            $unit,
                            $tax_rate,
                            1
                        ]);
                        $product_id = (int) $pdo->lastInsertId();

                        if ($product_id) {
                            // Insert inventory
                            $stmt = $pdo->prepare("
                                INSERT INTO inventory (tenant_id, product_id, branch_id, stock, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([$tenant_id, $product_id, $branch_id, $stock]);

                            $imported_count++;
                        } else {
                            $skipped_count++;
                        }
                    }

                    fclose($handle);
                    $pdo->commit();

                    // Log activity
                    if (function_exists('log_activity')) {
                        log_activity($user_id, 'import_products', [
                            'imported' => $imported_count, 'skipped' => $skipped_count,
                            'file' => $file['name']
                        ], get_current_tenant_id());
                    }

                    $success = "Import completed! {$imported_count} products imported, {$skipped_count} skipped.";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Import products error: " . $e->getMessage());
                $error = 'An error occurred during import. Please try again.';
            }
        }
    }
}

$page_title = 'Import Products';
ob_start();
?>

<div class="fade-in">
    <div class="max-w-2xl mx-auto">
        <div class="bg-slate-800 rounded-lg shadow-lg overflow-hidden border border-slate-700">
            <div class="bg-primary text-white px-6 py-4">
                <h1 class="text-2xl font-bold">
                    <i class="fas fa-file-import mr-2"></i> Import Products
                </h1>
                <p class="text-blue-100 text-sm mt-1">Upload a CSV file to import products</p>
            </div>

            <div class="p-6">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="mb-6">
                        <label class="block text-gray-300 font-semibold mb-2">
                            <i class="fas fa-file-csv mr-1"></i> Select CSV File
                        </label>
                        <input type="file" name="import_file" accept=".csv,.txt" required
                            class="w-full px-4 py-2 bg-primary-dark border border-slate-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <p class="text-gray-500 text-sm mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Accepted format: CSV with columns: Name, SKU, Price, Description, Category, Supplier,
                            Cost Price, Unit, Tax Rate, Min Stock, Max Stock, Stock
                        </p>
                    </div>

                    <div class="bg-primary-dark rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-gray-300 mb-2">
                            <i class="fas fa-download mr-1"></i> Sample CSV Format
                        </h3>
                        <pre class="text-xs text-gray-400 overflow-x-auto">Name,SKU,Price,Description,Category,Supplier,Cost Price,Unit,Tax Rate,Min Stock,Max Stock,Stock
Product 1,SKU001,100.00,Description here,Electronics,Supplier A,80.00,piece,16,5,100,50
Product 2,SKU002,250.00,Another product,Clothing,Supplier B,200.00,piece,16,10,200,75</pre>
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="products.php" class="text-gray-400 hover:text-amber-400 transition">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Products
                        </a>
                        <button type="submit"
                            class="bg-amber-500 text-black px-6 py-2 rounded-lg font-semibold hover:bg-amber-600 transition">
                            <i class="fas fa-upload mr-2"></i> Import Products
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app_close.php';
require_once __DIR__ . '/../../layouts/app.php';
?>
<?php ob_end_flush(); ?>
