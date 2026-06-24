<?php
/**
 * Supplier Form - Add/Edit Supplier
 * Standalone page matching Laravel layout style + Tailwind CSS
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('suppliers.manage') && !is_super_admin()) {
    enforce_permission('suppliers.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();
$user_id = get_current_user_id();

$error_message = '';

$supplier_id = intval($_GET['id'] ?? 0);
$is_edit = $supplier_id > 0;

$supplier = null;
if ($is_edit && $pdo) {
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL)');
    $stmt->execute([$supplier_id, $tenant_id]);
    $supplier = $stmt->fetch();
    if (!$supplier) {
        header('Location: suppliers.php?error=' . urlencode('Supplier not found'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error_message = 'Invalid security token. Please try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $payment_terms = trim($_POST['payment_terms'] ?? '');

    $errors = [];
    if (empty($name)) {
        $errors[] = 'Supplier name is required';
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare('
                    UPDATE suppliers
                    SET name=?, contact=?, phone=?, email=?, address=?, tax_id=?, payment_terms=?, updated_by=?, updated_at=NOW()
                    WHERE id=? AND tenant_id=?
                ');
                $stmt->execute([$name, $contact, $phone, $email, $address, $tax_id, $payment_terms, $user_id, $id, $tenant_id]);

                log_activity($user_id, 'supplier.updated', [
                    'supplier_id' => $id, 'supplier_name' => $name
                ], $tenant_id);

                header('Location: suppliers.php?success=' . urlencode('Supplier updated successfully'));
                exit;
            } else {
                $check = $pdo->prepare('SELECT id FROM suppliers WHERE name=? AND tenant_id=?');
                $check->execute([$name, $tenant_id]);
                if ($check->fetch()) {
                    $errors[] = 'Supplier with this name already exists';
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO suppliers (name, contact, phone, email, address, tax_id, payment_terms, tenant_id, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$name, $contact, $phone, $email, $address, $tax_id, $payment_terms, $tenant_id, $user_id]);

                    $new_id = $pdo->lastInsertId();
                    log_activity($user_id, 'supplier.created', [
                        'supplier_id' => $new_id, 'supplier_name' => $name
                    ], $tenant_id);

                    header('Location: suppliers.php?success=' . urlencode('Supplier added successfully'));
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log("Error saving supplier: " . $e->getMessage());
            $errors[] = 'Database error: Failed to save supplier';
        }
    }

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

$page_title = $is_edit ? 'Edit Supplier' : 'Add Supplier';
ob_start();
?>

<div class="fade-in">

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <p class="text-xs font-medium text-amber-400 uppercase tracking-wide mb-0.5">
            <a href="suppliers.php" class="hover:text-amber-300 transition-colors">Suppliers</a>
            <span class="text-slate-600 mx-1">/</span><?php echo $is_edit ? 'Edit' : 'Add New'; ?>
        </p>
        <h1 class="text-lg font-bold text-white"><?php echo $is_edit ? 'Edit Supplier' : 'Add New Supplier'; ?></h1>
        <p class="text-xs text-slate-500 mt-0.5"><?php echo $is_edit ? 'Update supplier details' : 'Register a new supplier to your inventory'; ?></p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="suppliers.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-xs font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Suppliers
        </a>
    </div>
</div>

<?php if ($error_message): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo $error_message; ?></span>
</div>
<?php endif; ?>

<form method="POST" class="w-full space-y-3">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
    <input type="hidden" name="id" value="<?php echo $supplier['id'] ?? 0; ?>">

    <!-- Section: Company Info -->
    <div class="accordion bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden" data-open="true">
        <button type="button" class="accordion-toggle w-full flex items-center justify-between px-4 py-3 text-left hover:bg-slate-700/20 transition-colors">
            <span class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0"><i class="fas fa-building text-amber-400 text-xs"></i></span>
                <span>
                    <span class="block text-sm font-semibold text-white">Company Information</span>
                    <span class="block text-xs text-slate-500">Business name and primary contact</span>
                </span>
            </span>
            <i class="accordion-chevron fas fa-chevron-down text-slate-500 text-xs transition-transform"></i>
        </button>
        <div class="accordion-body border-t border-slate-700/60 px-4 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Company Name <span class="text-red-400">*</span></label>
                <input type="text" name="name" required
                       value="<?php echo htmlspecialchars($supplier['name'] ?? ''); ?>"
                       placeholder="Supplier company name"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Contact Person</label>
                <input type="text" name="contact"
                       value="<?php echo htmlspecialchars($supplier['contact'] ?? ''); ?>"
                       placeholder="Full name of primary contact"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
        </div>
    </div>

    <!-- Section: Contact Details -->
    <div class="accordion bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden" data-open="true">
        <button type="button" class="accordion-toggle w-full flex items-center justify-between px-4 py-3 text-left hover:bg-slate-700/20 transition-colors">
            <span class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center shrink-0"><i class="fas fa-address-book text-blue-400 text-xs"></i></span>
                <span>
                    <span class="block text-sm font-semibold text-white">Contact Details</span>
                    <span class="block text-xs text-slate-500">Phone, email and physical address</span>
                </span>
            </span>
            <i class="accordion-chevron fas fa-chevron-down text-slate-500 text-xs transition-transform"></i>
        </button>
        <div class="accordion-body border-t border-slate-700/60 px-4 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Phone</label>
                <input type="tel" name="phone"
                       value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>"
                       placeholder="e.g., +254700000000"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Email</label>
                <input type="email" name="email"
                       value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>"
                       placeholder="supplier@company.com"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Address</label>
                <textarea name="address" rows="2"
                          placeholder="Physical address"
                          class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Section: Financial / Terms -->
    <div class="accordion bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden" data-open="false">
        <button type="button" class="accordion-toggle w-full flex items-center justify-between px-4 py-3 text-left hover:bg-slate-700/20 transition-colors">
            <span class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fas fa-file-invoice-dollar text-emerald-400 text-xs"></i></span>
                <span>
                    <span class="block text-sm font-semibold text-white">Financial &amp; Terms</span>
                    <span class="block text-xs text-slate-500">Tax identification and payment terms</span>
                </span>
            </span>
            <i class="accordion-chevron fas fa-chevron-down text-slate-500 text-xs transition-transform"></i>
        </button>
        <div class="accordion-body border-t border-slate-700/60 px-4 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Tax ID / VAT</label>
                <input type="text" name="tax_id"
                       value="<?php echo htmlspecialchars($supplier['tax_id'] ?? ''); ?>"
                       placeholder="Tax identification number"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 uppercase tracking-wide mb-1.5">Payment Terms</label>
                <input type="text" name="payment_terms"
                       value="<?php echo htmlspecialchars($supplier['payment_terms'] ?? ''); ?>"
                       placeholder="e.g., Net 30"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
        </div>
    </div>

    <!-- Sticky actions -->
    <div class="sticky bottom-0 -mx-4 sm:mx-0 px-4 sm:px-0 py-3 bg-slate-900/80 backdrop-blur border-t border-slate-700/60 flex gap-3">
        <button type="submit"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-400 transition-colors">
            <i class="fas fa-save text-xs"></i> <?php echo $is_edit ? 'Update Supplier' : 'Save Supplier'; ?>
        </button>
        <a href="suppliers.php"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
            Cancel
        </a>
    </div>
</form>

</div><!-- /.fade-in -->

<script>
    // Accordion: pop open/close on click
    document.querySelectorAll('.accordion').forEach(function (acc) {
        const body = acc.querySelector('.accordion-body');
        const chevron = acc.querySelector('.accordion-chevron');
        const setState = function (open) {
            acc.dataset.open = open ? 'true' : 'false';
            body.style.display = open ? '' : 'none';
            chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
        };
        setState(acc.dataset.open === 'true');
        acc.querySelector('.accordion-toggle').addEventListener('click', function () {
            setState(acc.dataset.open !== 'true');
        });
    });

    // Auto-open a section and focus the first invalid field on submit error
    document.querySelector('form').addEventListener('invalid', function (e) {
        const acc = e.target.closest('.accordion');
        if (acc && acc.dataset.open !== 'true') {
            acc.querySelector('.accordion-toggle').click();
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
        if (e.key === 'Escape' && !e.target.matches('input, textarea, select')) {
            window.location.href = 'suppliers.php';
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
