<?php
/**
 * Import Products from CSV/Excel
 * 
 * Allows users to import product data from CSV files.
 */

// Start output buffering
ob_start();

// Bootstrap the application
require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);
safe_require('functions.php', 'src', true);

// Require login
require_login();

// Check if user has permission to import products
if (!check_permission('products.create')) {
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
                $tenant_id = $_SESSION['tenant_id'];
                $branch_id = get_current_branch_id();
                $user_id = $_SESSION['user_id'];
                $business_type_id = get_current_business_type_id($tenant_id);

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
                            "SELECT id FROM products WHERE sku = ? AND tenant_id = ? AND deleted_at IS NULL",
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
                                price, cost_price, unit, tax_rate, active, business_type_id, created_by, created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
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
                            1,
                            $business_type_id,
                            $user_id
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
                        log_activity('import_products', null, [
                            'imported' => $imported_count, 'skipped' => $skipped_count,
                            'file' => $file['name']
                        ], $user_id, get_current_tenant_id());
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

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="products.php" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Products
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2"><i class="fas fa-file-import text-amber-400"></i> Import Products</h1>
        <p class="text-sm text-slate-500 mt-0.5">Upload a CSV file to import products</p>
    </div>
</div>

<div class="max-w-2xl">
    <?php if ($error): ?>
    <div class="flex items-center gap-2 px-4 py-3 mb-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="flex items-center gap-2 px-4 py-3 mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 space-y-5">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1">
                    <i class="fas fa-file-csv text-amber-500 mr-1"></i> Select CSV File
                </label>
                <input type="file" name="import_file" accept=".csv,.txt" required
                       class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-400 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-500 file:text-slate-900 hover:file:bg-amber-400 transition-all focus:outline-none">
                <p class="text-xs text-slate-600 mt-1.5">
                    <i class="fas fa-info-circle mr-1"></i>
                    Columns: Name, SKU, Price, Description, Category, Supplier, Cost Price, Unit, Tax Rate, Min Stock, Max Stock, Stock
                </p>
            </div>
            <div class="bg-slate-900/50 border border-slate-700/40 rounded-xl p-4">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">
                    <i class="fas fa-download text-amber-500 mr-1"></i> Sample CSV Format
                </h3>
                <pre class="text-xs text-slate-500 overflow-x-auto">Name,SKU,Price,Description,Category,Supplier,Cost Price,Unit,Tax Rate,Min Stock,Max Stock,Stock
Product 1,SKU001,100.00,Description here,Electronics,Supplier A,80.00,piece,16,5,100,50
Product 2,SKU002,250.00,Another product,Clothing,Supplier B,200.00,piece,16,10,200,75</pre>
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-upload text-xs"></i> Import Products
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
