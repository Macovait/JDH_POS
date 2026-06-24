<?php
/**
 * Import Sales Page for Jakababa POS
 * Bulk import sales data from CSV/Excel files with database integration
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('logger.php', 'src', true);
require_login();

// Start session for flash messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf_token = generate_csrf_token();

$page_title = 'Import Sales | Jakababa POS';
$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user']['name'] ?? 'User');
$user_role = $_SESSION['user']['role'] ?? '';
$user_branch = (int) ($_SESSION['user']['branch_id'] ?? 0);
$tenant_id = get_current_tenant_id() ?: 1;

$message = '';
$errors = [];
$import_stats = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'updated' => 0,
    'created' => 0,
    'skipped' => 0
];

// Get existing products for validation
$products = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, name, selling_price, price FROM products WHERE tenant_id = ? AND (status = 'active' OR status IS NULL)");
    $stmt->execute([$tenant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
}

// Get existing customers for lookup
$customers = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, email, phone FROM customers WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customers[strtolower(trim($row['name']))] = $row;
    }
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
    // Handle file upload and import
    $file = $_FILES['import_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_size = $file['size'];
        $max_size = 10 * 1024 * 1024; // 10MB

        // Validate file size
        if ($file_size > $max_size) {
            $errors[] = "File size exceeds maximum limit of 10MB.";
        } else {
            // Process based on file type
            if ($extension === 'csv') {
                // Process CSV
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle) {
                    $row_number = 0;
                    $skip_first = isset($_POST['skip_first_row']);
                    $update_existing = isset($_POST['update_existing']);
                    $create_customers = isset($_POST['create_customers']);

                    // Begin transaction
                    try {
                        $pdo->beginTransaction();

                        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                            $row_number++;

                            // Skip header row if selected
                            if ($skip_first && $row_number === 1) {
                                continue;
                            }

                            try {
                                // Clean and map data
                                $data = array_map('trim', $data);

                                // Validate row has minimum required columns
                                if (count($data) < 5) {
                                    throw new Exception("Insufficient columns. Expected at least 5.");
                                }

                                // Map CSV columns to database fields
                                $invoice_date = $data[0] ?? '';
                                $customer_name = $data[1] ?? '';
                                $product_name = $data[2] ?? '';
                                $quantity = (int) str_replace(['.', ','], '', $data[3] ?? '0');
                                $price = (float) str_replace(['.', ','], '', $data[4] ?? '0');
                                $payment_method = strtolower($data[5] ?? 'cash');
                                $notes = $data[6] ?? '';
                                $customer_email = $data[7] ?? '';
                                $customer_phone = $data[8] ?? '';

                                // Validate required fields
                                if (empty($invoice_date)) {
                                    throw new Exception("Invoice date is required");
                                }
                                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice_date)) {
                                    throw new Exception("Invalid date format. Use YYYY-MM-DD");
                                }

                                if (empty($customer_name)) {
                                    throw new Exception("Customer name is required");
                                }

                                if (empty($product_name)) {
                                    throw new Exception("Product name is required");
                                }

                                // Find product ID
                                $product_id = null;
                                foreach ($products as $id => $name) {
                                    if (strcasecmp(trim($name), trim($product_name)) === 0) {
                                        $product_id = $id;
                                        break;
                                    }
                                }

                                if (!$product_id) {
                                    throw new Exception("Product not found: $product_name");
                                }

                                if ($quantity <= 0) {
                                    throw new Exception("Quantity must be greater than 0");
                                }
                                if ($price <= 0) {
                                    throw new Exception("Price must be greater than 0");
                                }

                                // Find or create customer
                                $customer_id = null;
                                $customer_key = strtolower(trim($customer_name));

                                if (isset($customers[$customer_key])) {
                                    $customer_id = $customers[$customer_key]['id'];
                                } elseif ($create_customers) {
                                    // Create new customer
                                    $stmt = $pdo->prepare("
                                        INSERT INTO customers (name, email, phone, created_at) 
                                        VALUES (?, ?, ?, NOW())
                                    ");
                                    $stmt->execute([$customer_name, $customer_email, $customer_phone]);
                                    $customer_id = $pdo->lastInsertId();

                                    // Add to cache
                                    $customers[$customer_key] = [
                                        'id' => $customer_id,
                                        'name' => $customer_name,
                                        'email' => $customer_email,
                                        'phone' => $customer_phone
                                    ];

                                    $import_stats['created']++;
                                } else {
                                    // Use walk-in customer (null)
                                    $customer_id = null;
                                }

                                // Calculate totals
                                $subtotal = $quantity * $price;

                                // Get tax rate
                                $tax_rate = 16; // Default
                                $tax_amount = $subtotal * ($tax_rate / 100);
                                $total = $subtotal + $tax_amount;

                                // Generate invoice number
                                $invoice_number = 'IMP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

                                // Check if sale already exists (by invoice date and customer)
                                if ($update_existing) {
                                    $stmt = $pdo->prepare("
                                        SELECT id FROM sales 
                                        WHERE DATE(created_at) = ? AND customer_id = ? 
                                        LIMIT 1
                                    ");
                                    $stmt->execute([$invoice_date, $customer_id]);
                                    $existing_sale = $stmt->fetch();

                                    if ($existing_sale) {
                                        $import_stats['skipped']++;
                                        continue;
                                    }
                                }

                                // Insert sale
                                $stmt = $pdo->prepare("
                                    INSERT INTO sales (
                                        tenant_id, branch_id, user_id, customer_id, invoice_number,
                                        subtotal, total, payment_method, notes, status,
                                        created_at, updated_at
                                    ) VALUES (
                                        ?, ?, ?, ?, ?,
                                        ?, ?, ?, ?, 'completed',
                                        ?, NOW()
                                    )
                                ");

                                $stmt->execute([
                                    $tenant_id,
                                    $user_branch,
                                    $user_id,
                                    $customer_id,
                                    $invoice_number,
                                    $subtotal,
                                    $total,
                                    $payment_method,
                                    $notes,
                                    $invoice_date
                                ]);

                                $sale_id = $pdo->lastInsertId();

                                // Insert sale item
                                $stmt = $pdo->prepare("
                                    INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity, price, subtotal)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([$tenant_id, $sale_id, $product_id, $quantity, $price, $subtotal]);

                                // Update inventory
                                try {
                                    $check = $pdo->prepare("SELECT stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
                                    $check->execute([$product_id, $user_branch, $tenant_id]);
                                    $current_stock = $check->fetch();

                                    if ($current_stock) {
                                        $update = $pdo->prepare("
                                            UPDATE inventory SET stock = stock - ? 
                                            WHERE product_id = ? AND branch_id = ? AND tenant_id = ?
                                        ");
                                        $update->execute([$quantity, $product_id, $user_branch, $tenant_id]);
                                    }
                                } catch (Exception $e) {
                                    // Ignore inventory errors
                                }

                                $import_stats['success']++;

                            } catch (Exception $e) {
                                $import_stats['failed']++;
                                $errors[] = "Row " . $row_number . ": " . $e->getMessage();
                            }
                        }

                        // Commit transaction
                        $pdo->commit();

                        $import_stats['total'] = $row_number - ($skip_first ? 1 : 0);

                        if ($import_stats['success'] > 0) {
                            $message = "Import completed! Successfully imported {$import_stats['success']} sales records.";
                            if ($import_stats['failed'] > 0) {
                                $message .= " Failed: {$import_stats['failed']} records.";
                            }
                            if ($import_stats['created'] > 0) {
                                $message .= " Created {$import_stats['created']} new customers.";
                            }
                            if ($import_stats['skipped'] > 0) {
                                $message .= " Skipped {$import_stats['skipped']} duplicate records.";
                            }
                        }

                        // Log activity
                        log_activity($user_id, 'import_sales', [
                            'total' => $import_stats['total'], 'success' => $import_stats['success'],
                            'failed' => $import_stats['failed'],
                            'created_customers' => $import_stats['created']
                        ], get_current_tenant_id());

                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $errors[] = "Transaction failed: " . $e->getMessage();
                        error_log("Import transaction error: " . $e->getMessage());
                    }

                    fclose($handle);

                } else {
                    $errors[] = "Failed to open uploaded file.";
                }

            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $errors[] = "Excel import requires additional library. Please use CSV format for now.";
            } else {
                $errors[] = "Unsupported file format. Please upload CSV files only.";
            }
        }
    } else {
        // Handle upload errors
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive.",
            UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive.",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
            UPLOAD_ERR_NO_FILE => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION => "File upload stopped by extension."
        ];
        $errors[] = $upload_errors[$file['error']] ?? "Unknown upload error.";
    }
    }
}

// Get recent imports for history
$recent_imports = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? AND action = 'import_sales' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_imports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching import history: " . $e->getMessage());
}

// Sample CSV template content
$template_headers = ['Invoice Date', 'Customer Name', 'Product Name', 'Quantity', 'Price', 'Payment Method', 'Notes', 'Customer Email', 'Customer Phone'];
$template_sample = [
    ['2024-01-15', 'John Doe', 'Sample Product 1', '2', '1500', 'cash', 'First purchase', 'john@example.com', '0712345678'],
    ['2024-01-15', 'Jane Smith', 'Sample Product 2', '1', '2500', 'card', '', 'jane@example.com', '0723456789'],
    ['2024-01-16', 'ABC Corp', 'Sample Product 3', '5', '500', 'bank_transfer', 'Bulk order', 'info@abccorp.com', '0734567890']
];
?>
<?php
ob_start();
?>
<style>
    /* File upload area */
    .upload-area {
        transition: all 0.3s ease;
        border: 2px dashed #374151;
        background: #1F2937;
    }

    .upload-area:hover {
        border-color: #FBBF24;
        background: rgba(251, 191, 36, 0.05);
    }

    .upload-area.dragover {
        border-color: #10B981;
        background: rgba(16, 185, 129, 0.05);
    }

    /* Progress bar */
    .progress-bar {
        transition: width 0.3s ease;
    }

    /* Sample table */
    .sample-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 0.75rem;
    }

    .sample-table th {
        background: #111827;
        padding: 0.5rem;
        text-align: left;
        font-weight: 600;
        color: #9CA3AF;
    }

    .sample-table td {
        padding: 0.5rem;
        border-bottom: 1px solid #374151;
        color: #E5E7EB;
    }

    .sample-table tr:last-child td {
        border-bottom: none;
    }

    /* Status badges */
    .badge-success {
        background: #065F46;
        color: #D1FAE5;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
    }

    .badge-error {
        background: #7F1D1D;
        color: #FEE2E2;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in {
        animation: fadeIn 0.4s ease-out;
    }
</style>

<div class="fade-in">

    <!-- Main Content -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 fade-in">

        <!-- Page Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Import Sales Data</h1>
                <p class="text-[#9CA3AF] text-sm mt-1">Bulk import sales records from CSV files</p>
            </div>
            <div class="bg-[#1F2937] rounded-lg px-4 py-2 flex items-center gap-2 border border-[#374151]">
                <i class="fas fa-file-csv text-[#FBBF24]"></i>
                <span class="text-sm">CSV Format</span>
            </div>
        </div>

        <!-- Import Statistics (if available) -->
        <?php if ($import_stats['total'] > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                    <p class="text-xs text-[#9CA3AF]">Total Rows</p>
                    <p class="text-2xl font-bold text-white"><?php echo $import_stats['total']; ?></p>
                </div>
                <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                    <p class="text-xs text-[#9CA3AF]">Success</p>
                    <p class="text-2xl font-bold text-[#10B981]"><?php echo $import_stats['success']; ?></p>
                </div>
                <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                    <p class="text-xs text-[#9CA3AF]">Failed</p>
                    <p class="text-2xl font-bold text-[#EF4444]"><?php echo $import_stats['failed']; ?></p>
                </div>
                <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                    <p class="text-xs text-[#9CA3AF]">New Customers</p>
                    <p class="text-2xl font-bold text-[#FBBF24]"><?php echo $import_stats['created']; ?></p>
                </div>
                <div class="bg-[#1F2937] rounded-xl p-4 border border-[#374151]">
                    <p class="text-xs text-[#9CA3AF]">Success Rate</p>
                    <p class="text-2xl font-bold text-white">
                        <?php echo $import_stats['total'] > 0 ? round(($import_stats['success'] / $import_stats['total']) * 100) : 0; ?>%
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div
                class="mb-6 p-4 rounded-lg <?php echo empty($errors) ? 'bg-[#10B981]/20 border border-[#10B981] text-[#10B981]' : 'bg-[#FBBF24]/20 border border-[#FBBF24] text-[#FBBF24]'; ?>">
                <i class="fas fa-<?php echo empty($errors) ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Error List -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-[#EF4444]/10 border border-[#EF4444] rounded-xl p-4">
                <h3 class="font-semibold mb-2 flex items-center gap-2 text-[#EF4444]">
                    <i class="fas fa-exclamation-circle"></i>
                    Import Errors (<?php echo count($errors); ?>)
                </h3>
                <div class="max-h-40 overflow-y-auto space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <p class="text-sm text-[#EF4444]/90">• <?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Import Form -->
        <div class="bg-[#1F2937] rounded-xl p-6 border border-[#374151] mb-6">
            <form id="importForm" method="POST" enctype="multipart/form-data" class="space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <!-- File Upload -->
                <div>
                    <label class="block text-sm font-medium text-[#9CA3AF] mb-2">Select File to Import</label>
                    <div class="upload-area rounded-xl p-8 text-center" id="uploadArea">
                        <input type="file" name="import_file" id="fileInput" accept=".csv" required class="hidden">
                        <label for="fileInput" class="cursor-pointer block" id="uploadLabel">
                            <i class="fas fa-cloud-upload-alt text-5xl text-[#9CA3AF] mb-4"></i>
                            <p class="text-xl font-semibold mb-1 text-white" id="uploadText">Click to upload or drag and
                                drop</p>
                            <p class="text-sm text-[#9CA3AF]">CSV files only (max 10MB)</p>
                        </label>
                        <div id="fileInfo" class="hidden mt-4 p-3 bg-[#111827] rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-file-csv text-[#10B981]"></i>
                                    <span id="fileName" class="font-medium text-white"></span>
                                </div>
                                <button type="button" onclick="clearFileSelection()"
                                    class="text-[#EF4444] hover:text-[#DC2626]">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="mt-2 flex gap-4 text-xs text-[#9CA3AF]">
                                <span>Size: <span id="fileSize"></span></span>
                                <span>Type: <span id="fileType">CSV</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import Progress (hidden by default) -->
                <div id="importProgress" class="hidden">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-white">Importing...</span>
                        <span id="progressPercent" class="text-[#FBBF24]">0%</span>
                    </div>
                    <div class="w-full bg-[#111827] rounded-full h-2">
                        <div id="progressBar" class="progress-bar bg-[#10B981] h-2 rounded-full" style="width: 0%">
                        </div>
                    </div>
                </div>

                <!-- Import Options -->
                <div class="bg-[#111827] rounded-xl p-4 border border-[#374151]">
                    <h3 class="font-semibold text-white mb-3 flex items-center gap-2">
                        <i class="fas fa-sliders-h text-[#FBBF24]"></i>
                        Import Options
                    </h3>
                    <div class="space-y-3">
                        <label
                            class="flex items-center justify-between p-2 hover:bg-[#1F2937] rounded-lg transition cursor-pointer">
                            <span class="flex items-center gap-2 text-[#D1D5DB]">
                                <i class="fas fa-arrow-up text-[#9CA3AF]"></i>
                                <span>Skip first row (header)</span>
                            </span>
                            <input type="checkbox" name="skip_first_row" checked class="accent-[#FBBF24]">
                        </label>

                        <label
                            class="flex items-center justify-between p-2 hover:bg-[#1F2937] rounded-lg transition cursor-pointer">
                            <span class="flex items-center gap-2 text-[#D1D5DB]">
                                <i class="fas fa-sync-alt text-[#9CA3AF]"></i>
                                <span>Update existing records</span>
                            </span>
                            <input type="checkbox" name="update_existing" class="accent-[#FBBF24]">
                        </label>

                        <label
                            class="flex items-center justify-between p-2 hover:bg-[#1F2937] rounded-lg transition cursor-pointer">
                            <span class="flex items-center gap-2 text-[#D1D5DB]">
                                <i class="fas fa-user-plus text-[#9CA3AF]"></i>
                                <span>Auto-create missing customers</span>
                            </span>
                            <input type="checkbox" name="create_customers" checked class="accent-[#FBBF24]">
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submitBtn"
                    class="w-full bg-[#10B981] text-white py-4 rounded-xl font-semibold hover:bg-[#059669] transition flex items-center justify-center gap-2">
                    <i class="fas fa-upload"></i>
                    Start Import
                </button>
            </form>
        </div>

        <!-- Template & Instructions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            <!-- Template Download -->
            <div class="bg-[#1F2937] rounded-xl p-6 border border-[#374151]">
                <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-download text-[#FBBF24]"></i>
                    Download Template
                </h3>

                <p class="text-sm text-[#9CA3AF] mb-4">Use our template to ensure correct format:</p>

                <a href="data:application/octet-stream;charset=utf-8,<?php echo urlencode(implode(',', $template_headers) . "\n" . implode("\n", array_map(function ($row) {
                    return implode(',', $row);
                }, $template_sample))); ?>" download="sales_import_template.csv"
                    class="inline-flex items-center gap-2 bg-[#FBBF24] text-[#1E3A8A] px-4 py-3 rounded-lg hover:bg-[#F59E0B] transition w-full justify-center font-semibold">
                    <i class="fas fa-file-csv"></i>
                    Download CSV Template
                </a>

                <!-- Sample Preview -->
                <div class="mt-4">
                    <p class="text-xs text-[#9CA3AF] mb-2">Sample data format:</p>
                    <div class="bg-[#111827] rounded-lg overflow-hidden">
                        <table class="sample-table">
                            <thead>
                                <tr>
                                    <?php foreach (array_slice($template_headers, 0, 5) as $header): ?>
                                        <th><?php echo htmlspecialchars($header); ?></th>
                                    <?php endforeach; ?>
                                    <th>...</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($template_sample, 0, 2) as $row): ?>
                                    <tr>
                                        <?php foreach (array_slice($row, 0, 5) as $cell): ?>
                                            <td><?php echo htmlspecialchars($cell); ?></td>
                                        <?php endforeach; ?>
                                        <td>...</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="bg-[#1F2937] rounded-xl p-6 border border-[#374151]">
                <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle text-[#FBBF24]"></i>
                    CSV Format Instructions
                </h3>

                <div class="space-y-4">
                    <div>
                        <h4 class="text-sm font-medium text-white mb-2">Required Columns:</h4>
                        <ul class="space-y-2 text-sm text-[#D1D5DB]">
                            <li class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-[#10B981] rounded-full"></span>
                                <span><span class="text-[#FBBF24]">Invoice Date</span> - Format: YYYY-MM-DD</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-[#10B981] rounded-full"></span>
                                <span><span class="text-[#FBBF24]">Customer Name</span> - Full name or company</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-[#10B981] rounded-full"></span>
                                <span><span class="text-[#FBBF24]">Product Name</span> - Must match existing
                                    products</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-[#10B981] rounded-full"></span>
                                <span><span class="text-[#FBBF24]">Quantity</span> - Positive integer</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-[#10B981] rounded-full"></span>
                                <span><span class="text-[#FBBF24]">Price</span> - Numeric, no currency symbols</span>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium text-white mb-2">Optional Columns:</h4>
                        <ul class="space-y-1 text-sm text-[#D1D5DB]">
                            <li class="ml-4">• Payment Method (cash, card, mpesa, bank_transfer)</li>
                            <li class="ml-4">• Notes</li>
                            <li class="ml-4">• Customer Email</li>
                            <li class="ml-4">• Customer Phone</li>
                        </ul>
                    </div>

                    <div class="bg-[#111827] rounded-lg p-3">
                        <h4 class="text-sm font-medium text-[#FBBF24] mb-1">⚠️ Important Notes:</h4>
                        <ul class="text-xs text-[#9CA3AF] space-y-1">
                            <li>• File must be in CSV format with comma separators</li>
                            <li>• Maximum file size: 10MB</li>
                            <li>• Date format: YYYY-MM-DD (e.g., 2024-01-15)</li>
                            <li>• Price format: 1500 (no commas or dots)</li>
                            <li>• Product names must match existing products</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Imports History -->
        <?php if (!empty($recent_imports)): ?>
            <div class="mt-6 bg-[#1F2937] rounded-xl p-6 border border-[#374151]">
                <h3 class="font-semibold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-history text-[#FBBF24]"></i>
                    Recent Import History
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#111827]">
                            <tr>
                                <th class="px-4 py-2 text-left text-[#9CA3AF]">Date</th>
                                <th class="px-4 py-2 text-left text-[#9CA3AF]">Records</th>
                                <th class="px-4 py-2 text-left text-[#9CA3AF]">Success</th>
                                <th class="px-4 py-2 text-left text-[#9CA3AF]">Failed</th>
                                <th class="px-4 py-2 text-left text-[#9CA3AF]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#374151]">
                            <?php foreach ($recent_imports as $import):
                                $meta = json_decode($import['meta'], true);
                                ?>
                                <tr>
                                    <td class="px-4 py-2 text-[#D1D5DB]">
                                        <?php echo date('d M Y H:i', strtotime($import['created_at'])); ?></td>
                                    <td class="px-4 py-2 text-[#D1D5DB]"><?php echo $meta['total'] ?? 0; ?></td>
                                    <td class="px-4 py-2 text-[#10B981]"><?php echo $meta['success'] ?? 0; ?></td>
                                    <td class="px-4 py-2 text-[#EF4444]"><?php echo $meta['failed'] ?? 0; ?></td>
                                    <td class="px-4 py-2">
                                        <?php if (($meta['failed'] ?? 0) == 0): ?>
                                            <span class="badge-success">Success</span>
                                        <?php else: ?>
                                            <span class="badge-error">Partial</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    // File upload handling
    const fileInput = document.getElementById('fileInput');
    const uploadArea = document.getElementById('uploadArea');
    const uploadText = document.getElementById('uploadText');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const submitBtn = document.getElementById('submitBtn');
    const importProgress = document.getElementById('importProgress');
    const progressBar = document.getElementById('progressBar');
    const progressPercent = document.getElementById('progressPercent');

    // Drag and drop handlers
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        uploadArea.classList.add('dragover');
    }

    function unhighlight() {
        uploadArea.classList.remove('dragover');
    }

    uploadArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        updateFileInfo();
    }

    fileInput.addEventListener('change', updateFileInfo);

    function updateFileInfo() {
        const file = fileInput.files[0];
        if (file) {
            uploadText.textContent = 'File selected:';
            uploadText.classList.add('text-[#10B981]');
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.classList.remove('hidden');

            // Validate file size
            if (file.size > 10 * 1024 * 1024) {
                alert('File size exceeds 10MB limit. Please choose a smaller file.');
                clearFileSelection();
            }
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function clearFileSelection() {
        fileInput.value = '';
        uploadText.textContent = 'Click to upload or drag and drop';
        uploadText.classList.remove('text-[#10B981]');
        fileInfo.classList.add('hidden');
    }

    // Form submission with progress
    document.getElementById('importForm').addEventListener('submit', function (e) {
        const file = fileInput.files[0];

        if (!file) {
            e.preventDefault();
            alert('Please select a file to import.');
            return;
        }

        // Validate file extension
        if (!file.name.toLowerCase().endsWith('.csv')) {
            e.preventDefault();
            alert('Please select a CSV file.');
            return;
        }

        // Show progress bar
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
        importProgress.classList.remove('hidden');

        // Animate progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) {
                progress = 90;
            }
            progressBar.style.width = progress + '%';
            progressPercent.textContent = Math.round(progress) + '%';
        }, 500);
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;

        // Ctrl + I - Focus file input
        if (e.ctrlKey && e.key === 'i') {
            e.preventDefault();
            fileInput.click();
        }

        // Ctrl + Enter - Submit form
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('importForm').requestSubmit();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../../layouts/app.php';
require_once __DIR__ . '/../../layouts/app_close.php';
