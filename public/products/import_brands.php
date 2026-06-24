<?php
/**
 * Import Brands from CSV
 *
 * Allows users to import brand data from CSV files.
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('db.php', 'src', true);
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('functions.php', 'src', true);

require_login();

if (!check_permission('products.manage') && !is_super_admin()) {
    enforce_permission('products.manage');
}

$error = null;
$success = null;
$imported_count = 0;
$skipped_count = 0;

$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();

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

                // Read CSV file
                $handle = fopen($file['tmp_name'], 'r');

                if ($handle === false) {
                    $error = 'Unable to read the uploaded file.';
                } else {
                    // Skip header row
                    fgetcsv($handle);

                    $pdo->beginTransaction();

                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) < 1) {
                            $skipped_count++;
                            continue;
                        }

                        // Map CSV columns
                        $name = trim($data[0] ?? '');
                        $description = trim($data[1] ?? '');
                        $status = strtolower(trim($data[2] ?? 'active'));

                        // Validate required fields
                        if (empty($name)) {
                            $skipped_count++;
                            continue;
                        }

                        // Convert status to boolean
                        $active = ($status === 'active' || $status === '1' || $status === 'yes') ? 1 : 0;

                        // Check if brand name already exists
                        $existing = db_fetch_one(
                            "SELECT id FROM brands WHERE name = ? AND tenant_id = ? AND deleted_at IS NULL",
                            [$name, $tenant_id]
                        );

                        if ($existing) {
                            $skipped_count++;
                            continue;
                        }

                        // Insert brand
                        db_insert(
                            "INSERT INTO brands (tenant_id, name, description, active, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
                            [$tenant_id, $name, $description, $active]
                        );

                        $imported_count++;
                    }

                    $pdo->commit();
                    fclose($handle);

                    // Log activity
                    if (function_exists('log_activity')) {
                        log_activity($user_id, 'brand_import', [
                            'count' => $imported_count, 'skipped' => $skipped_count,
                            'tenant_id' => $tenant_id
                        ], $tenant_id, get_current_tenant_id());
                    }

                    $success = "Successfully imported {$imported_count} brands." . ($skipped_count > 0 ? " {$skipped_count} brands were skipped." : "");
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Import brands error: " . $e->getMessage());
                $error = 'Import failed: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Import Brands | JDH POS';
ob_start();
?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <a href="brands.php" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-amber-400 transition-colors mb-2">
            <i class="fas fa-arrow-left text-xs"></i> Back to Brands
        </a>
        <h1 class="text-lg font-bold text-white flex items-center gap-2"><i class="fas fa-file-import text-amber-400"></i> Import Brands</h1>
        <p class="text-sm text-slate-500 mt-0.5">Upload a CSV file to import brands</p>
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
                <p class="text-xs text-slate-600 mt-1.5"><i class="fas fa-info-circle mr-1"></i>Columns: Name, Description, Status</p>
            </div>
            <div class="bg-slate-900/50 border border-slate-700/40 rounded-xl p-4">
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">
                    <i class="fas fa-download text-amber-500 mr-1"></i> Sample CSV Format
                </h3>
                <pre class="text-xs text-slate-500 overflow-x-auto">Name,Description,Status
Brand A,Description for Brand A,active
Brand B,Description for Brand B,active
Brand C,Description for Brand C,inactive</pre>
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-amber-500/15 border border-amber-500/40 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                    <i class="fas fa-upload text-xs"></i> Import Brands
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';