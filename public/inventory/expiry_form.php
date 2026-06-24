<?php
/**
 * Expiry Form - Add/Edit Expiry Entry
 * Standalone page matching Laravel layout style + Tailwind CSS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();

function ensureExpiryTables($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_expiry (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                branch_id INT UNSIGNED DEFAULT 1,
                product_id INT UNSIGNED NOT NULL,
                batch_number VARCHAR(100),
                quantity INT DEFAULT 1,
                expiry_date DATE NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                INDEX idx_company (tenant_id),
                INDEX idx_product (product_id),
                INDEX idx_expiry (expiry_date),
                INDEX idx_batch (batch_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("Expiry table error: " . $e->getMessage());
    }
}
ensureExpiryTables($pdo);

$entry_id = intval($_GET['id'] ?? 0);
$is_edit = $entry_id > 0;

$entry = null;
if ($is_edit && $pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM product_expiry
        WHERE id = ? AND tenant_id = ? AND (branch_id = ? OR branch_id IS NULL)
    ");
    $stmt->execute([$entry_id, $tenant_id, $branch_id]);
    $entry = $stmt->fetch();
    if (!$entry) {
        header('Location: item_expiry.php?error=' . urlencode('Entry not found'));
        exit;
    }
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    $batch_number = trim($_POST['batch_number'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $expiry_date = $_POST['expiry_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $errors = [];
    if ($product_id <= 0) {
        $errors[] = 'Product is required';
    }
    if (empty($expiry_date)) {
        $errors[] = 'Expiry date is required';
    }
    if ($quantity < 1) {
        $errors[] = 'Quantity must be at least 1';
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE product_expiry
                    SET product_id = ?, batch_number = ?, quantity = ?, expiry_date = ?, notes = ?, updated_at = NOW()
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([$product_id, $batch_number, $quantity, $expiry_date, $notes, $id, $tenant_id]);
                header('Location: item_expiry.php?success=' . urlencode('Expiry entry updated successfully'));
                exit;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO product_expiry (tenant_id, branch_id, product_id, batch_number, quantity, expiry_date, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$tenant_id, $branch_id, $product_id, $batch_number, $quantity, $expiry_date, $notes]);
                header('Location: item_expiry.php?success=' . urlencode('Expiry entry added successfully'));
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error saving expiry: " . $e->getMessage());
            $error_message = 'Database error: Failed to save expiry entry';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Load products for dropdown
$products = [];
try {
    $stmt = $pdo->prepare('SELECT id, name, sku, barcode FROM products WHERE tenant_id = ? AND status = 1 AND deleted_at IS NULL ORDER BY name LIMIT 200');
    $stmt->execute([$tenant_id]);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
}

$page_title = $is_edit ? 'Edit Expiry Entry' : 'Add Expiry Entry';
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-calendar-times text-amber-400"></i>
            <?php echo $is_edit ? 'Edit Expiry Entry' : 'Add Expiry Entry'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5"><?php echo $is_edit ? 'Update expiry details for this product batch' : 'Track product expiry dates and manage alerts'; ?></p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="item_expiry.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Expiry Tracking
        </a>
    </div>
</div>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo $error_message; ?></span>
</div>
<?php endif; ?>

<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-5 max-w-2xl">
    <form method="POST" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        <input type="hidden" name="id" value="<?php echo $entry['id'] ?? 0; ?>">

        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Product <span class="text-red-400">*</span></label>
            <select name="product_id" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="">Select Product</option>
                <?php foreach ($products as $prod): ?>
                    <option value="<?php echo $prod['id']; ?>"
                        <?php echo (isset($entry['product_id']) && $entry['product_id'] == $prod['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($prod['name']); ?> (<?php echo htmlspecialchars($prod['sku'] ?? $prod['barcode']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Batch Number</label>
                <input type="text" name="batch_number"
                       value="<?php echo htmlspecialchars($entry['batch_number'] ?? ''); ?>"
                       placeholder="Optional"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Quantity <span class="text-red-400">*</span></label>
                <input type="number" name="quantity" min="1" required
                       value="<?php echo htmlspecialchars($entry['quantity'] ?? '1'); ?>"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Expiry Date <span class="text-red-400">*</span></label>
            <input type="date" name="expiry_date" required
                   value="<?php echo htmlspecialchars($entry['expiry_date'] ?? ''); ?>"
                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Notes</label>
            <textarea name="notes" rows="3"
                      placeholder="Optional notes about this batch or expiry"
                      class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none"><?php echo htmlspecialchars($entry['notes'] ?? ''); ?></textarea>
        </div>

        <div class="flex gap-3 pt-4 border-t border-slate-700/60">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 text-sm font-semibold hover:bg-amber-500/25 transition-colors">
                <i class="fas fa-save text-xs"></i> <?php echo $is_edit ? 'Update Entry' : 'Save Entry'; ?>
            </button>
            <a href="item_expiry.php"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) {
            // Allow Ctrl+S to submit while in form fields
            if (!(e.ctrlKey && e.key === 's')) {
                return;
            }
        }

        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
        if (e.key === 'Escape') {
            window.location.href = 'item_expiry.php';
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
