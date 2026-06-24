<?php
/**
 * Customer add/edit form for Jakababa POS
 * Standalone form extracted from customers.php
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (!check_permission('customers.manage') && !is_super_admin()) {
    enforce_permission('customers.manage');
}

$pdo = get_db_connection();
$user_id = get_current_user_id();
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$user_role = $_SESSION['role'] ?? $_SESSION['user']['role'] ?? 'cashier';
$is_superadmin = is_super_admin();
$is_company_admin = $is_superadmin || in_array($user_role, ['admin', 'owner']);

if (!$tenant_id && !$is_superadmin) {
    die("Company context missing. Please log in again.");
}

$customer_columns = [];
try {
    $customer_columns = array_flip(array_column($pdo->query('SHOW COLUMNS FROM customers')->fetchAll(PDO::FETCH_ASSOC), 'Field'));
} catch (PDOException $e) {
    error_log("Error reading customer columns: " . $e->getMessage());
}
$customer_has_column = static fn(string $column): bool => isset($customer_columns[$column]);

$business_type = get_current_business_type($tenant_id);
$bt_config = get_business_type_config($business_type);
$bt_customer_label = $bt_config['customer_label'] ?? 'Customer';

// Get customer groups
$customer_groups = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, min_spent, discount_rate FROM customer_groups WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY min_spent ASC");
    $stmt->execute([$tenant_id]);
    $customer_groups = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching customer groups: " . $e->getMessage());
}

// Fetch existing customer for edit
$customer = null;
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($customer_id > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, COALESCE(cg.name, 'None') as group_name
        FROM customers c
        LEFT JOIN customer_groups cg ON c.group_id = cg.id AND cg.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$customer_id, $tenant_id]);
    $customer = $stmt->fetch();
    if (!$customer) {
        header('Location: customers.php?error=' . urlencode('Customer not found'));
        exit;
    }
}

$is_edit = $customer_id > 0;
$duplicate_from = isset($_GET['duplicate_from']) ? intval($_GET['duplicate_from']) : 0;
if ($duplicate_from > 0 && !$is_edit) {
    $stmt = $pdo->prepare("
        SELECT c.*, COALESCE(cg.name, 'None') as group_name
        FROM customers c
        LEFT JOIN customer_groups cg ON c.group_id = cg.id AND cg.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$duplicate_from, $tenant_id]);
    $customer = $stmt->fetch();
    if ($customer) {
        $customer['name'] = $customer['name'] . ' (Copy)';
        $customer['customer_code'] = '';
        $customer['phone'] = '';
        $customer['email'] = '';
        $customer['loyalty_points'] = 0;
    }
}

// Handle POST
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Security validation failed. Please refresh and try again.';
    } else {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $points = intval($_POST['loyalty_points'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $tax_id = trim($_POST['tax_id'] ?? '');
        $credit_limit = floatval($_POST['credit_limit'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $group_id = !empty($_POST['group_id']) ? intval($_POST['group_id']) : null;

        if (empty($name)) {
            $errors[] = 'Customer name is required';
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if (!empty($phone) && !preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            $errors[] = 'Invalid phone number format';
        }

        if (empty($errors)) {
            try {
                if ($id > 0) {
                    // Update
                    $updates = ['name = ?'];
                    $update_params = [$name];
                    $update_fields = [
                        'phone' => $phone,
                        'email' => $email,
                        'loyalty_points' => $points,
                        'address' => $address,
                        'city' => $city,
                        'postal_code' => $postal_code,
                        'company_name' => $company_name,
                        'tax_id' => $tax_id,
                        'credit_limit' => $credit_limit,
                        'notes' => $notes,
                        'status' => $status,
                        'active' => $status,
                        'group_id' => $group_id,
                    ];
                    foreach ($update_fields as $column => $value) {
                        if ($customer_has_column($column)) {
                            $updates[] = $column . ' = ?';
                            $update_params[] = $value;
                        }
                    }
                    if ($customer_has_column('updated_at')) {
                        $updates[] = 'updated_at = NOW()';
                    }
                    $where = 'id = ?';
                    $update_params[] = $id;
                    if ($customer_has_column('tenant_id')) {
                        $where .= ' AND tenant_id = ?';
                        $update_params[] = $tenant_id;
                    }
                    $stmt = $pdo->prepare('UPDATE customers SET ' . implode(', ', $updates) . ' WHERE ' . $where);
                    $stmt->execute($update_params);

                    log_activity($user_id, 'customer.updated', [
                        'customer_id' => $id, 'customer_name' => $name,
                        'tenant_id' => $tenant_id
                    ], get_current_tenant_id());

                    header('Location: customers.php?success=' . urlencode('Customer updated successfully'));
                    exit;
                } else {
                    // Check duplicates
                    if (!empty($phone) && $customer_has_column('phone')) {
                        $check_sql = 'SELECT id FROM customers WHERE phone = ?';
                        $check_params = [$phone];
                        if ($customer_has_column('tenant_id')) {
                            $check_sql .= ' AND tenant_id = ?';
                            $check_params[] = $tenant_id;
                        }
                        $check = $pdo->prepare($check_sql);
                        $check->execute($check_params);
                        if ($check->fetch()) {
                            $errors[] = 'Customer with this phone number already exists';
                        }
                    }
                    if (!empty($email) && $customer_has_column('email')) {
                        $check_sql = 'SELECT id FROM customers WHERE email = ?';
                        $check_params = [$email];
                        if ($customer_has_column('tenant_id')) {
                            $check_sql .= ' AND tenant_id = ?';
                            $check_params[] = $tenant_id;
                        }
                        $check = $pdo->prepare($check_sql);
                        $check->execute($check_params);
                        if ($check->fetch()) {
                            $errors[] = 'Customer with this email already exists';
                        }
                    }

                    if (empty($errors)) {
                        $insert_columns = [];
                        $insert_placeholders = [];
                        $insert_params = [];
                        $add_insert_value = static function (string $column, $value) use ($customer_has_column, &$insert_columns, &$insert_placeholders, &$insert_params): void {
                            if ($customer_has_column($column)) {
                                $insert_columns[] = $column;
                                $insert_placeholders[] = '?';
                                $insert_params[] = $value;
                            }
                        };
                        $add_insert_expression = static function (string $column, string $expression) use ($customer_has_column, &$insert_columns, &$insert_placeholders): void {
                            if ($customer_has_column($column)) {
                                $insert_columns[] = $column;
                                $insert_placeholders[] = $expression;
                            }
                        };

                        $add_insert_value('tenant_id', $tenant_id);
                        $add_insert_value('branch_id', $branch_id);
                        $add_insert_value('customer_code', 'CUST-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT));
                        $add_insert_value('name', $name);
                        $add_insert_value('phone', $phone);
                        $add_insert_value('email', $email);
                        $add_insert_value('loyalty_points', $points);
                        $add_insert_value('address', $address);
                        $add_insert_value('city', $city);
                        $add_insert_value('postal_code', $postal_code);
                        $add_insert_value('company_name', $company_name);
                        $add_insert_value('tax_id', $tax_id);
                        $add_insert_value('credit_limit', $credit_limit);
                        $add_insert_value('notes', $notes);
                        $add_insert_value('status', $status);
                        $add_insert_value('active', $status);
                        $add_insert_value('group_id', $group_id);
                        $add_insert_value('created_by', $user_id);
                        $add_insert_expression('created_at', 'NOW()');
                        $add_insert_expression('updated_at', 'NOW()');

                        $stmt = $pdo->prepare('INSERT INTO customers (' . implode(', ', $insert_columns) . ') VALUES (' . implode(', ', $insert_placeholders) . ')');
                        $stmt->execute($insert_params);

                        $new_id = $pdo->lastInsertId();
                        log_activity($user_id, 'customer.created', [
                            'customer_id' => $new_id, 'customer_name' => $name,
                            'tenant_id' => $tenant_id
                        ], get_current_tenant_id());

                        header('Location: customers.php?success=' . urlencode('Customer added successfully'));
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log("Error saving customer: " . $e->getMessage());
                $errors[] = 'Database error: Failed to save customer';
            }
        }
    }
}

$csrf_token = generate_csrf_token();
$is_duplicate = $duplicate_from > 0 && !$is_edit;
$page_title = ($is_edit ? 'Edit' : ($is_duplicate ? 'Duplicate' : 'Add')) . ' ' . $bt_customer_label . ' | JDH POS';
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-user text-amber-400"></i> <?php echo $is_edit ? 'Edit' : ($is_duplicate ? 'Duplicate' : 'Add'); ?> <?php echo htmlspecialchars($bt_customer_label); ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5"><?php echo $is_edit ? 'Update customer information' : ($is_duplicate ? 'Create a copy of an existing customer' : 'Create a new customer record'); ?></p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="customers.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30">
    <div class="flex items-start gap-2">
        <i class="fas fa-exclamation-triangle text-red-400 mt-0.5 text-sm"></i>
        <div class="flex-1">
            <h3 class="text-red-400 font-medium text-sm mb-1">Please fix the following errors:</h3>
            <ul class="list-disc list-inside text-red-400/80 text-xs space-y-0.5">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
    <form method="POST" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="id" value="<?php echo $customer_id; ?>">

        <div>
            <label for="name" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                <i class="fas fa-user text-amber-400 text-xs"></i>Full Name <span class="text-red-400">*</span>
            </label>
            <input type="text" id="name" name="name" required
                value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>"
                autocomplete="name"
                class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Enter customer name">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div>
                <label for="phone" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-phone text-amber-400 text-xs"></i>Phone
                </label>
                <input type="tel" id="phone" name="phone"
                    value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                    autocomplete="tel"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="+254 712 345 678">
            </div>
            <div>
                <label for="email" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-envelope text-amber-400 text-xs"></i>Email
                </label>
                <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"
                    autocomplete="email"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="customer@example.com">
            </div>
        </div>

        <div>
            <label for="address" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                <i class="fas fa-map-marker-alt text-amber-400 text-xs"></i>Address
            </label>
            <input type="text" id="address" name="address"
                value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>"
                autocomplete="street-address"
                class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Street address">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div>
                <label for="city" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-city text-amber-400 text-xs"></i>City
                </label>
                <input type="text" id="city" name="city"
                    value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>"
                    autocomplete="address-level2"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="City">
            </div>
            <div>
                <label for="postal_code" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-mail-bulk text-amber-400 text-xs"></i>Postal Code
                </label>
                <input type="text" id="postal_code" name="postal_code"
                    value="<?php echo htmlspecialchars($customer['postal_code'] ?? ''); ?>"
                    autocomplete="postal-code"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="Postal code">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div>
                <label for="company_name" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-building text-amber-400 text-xs"></i>Company Name
                </label>
                <input type="text" id="company_name" name="company_name"
                    value="<?php echo htmlspecialchars($customer['company_name'] ?? ''); ?>"
                    autocomplete="organization"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="Company / Organization">
            </div>
            <div>
                <label for="tax_id" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-id-card text-amber-400 text-xs"></i>Tax ID / PIN
                </label>
                <input type="text" id="tax_id" name="tax_id"
                    value="<?php echo htmlspecialchars($customer['tax_id'] ?? ''); ?>"
                    autocomplete="off"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="Tax identification number">
            </div>
        </div>

        <?php if (!empty($customer_groups)): ?>
        <div>
            <label for="group_id" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                <i class="fas fa-users text-amber-400 text-xs"></i>Customer Group
            </label>
            <select id="group_id" name="group_id"
                class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="">None</option>
                <?php foreach ($customer_groups as $group): ?>
                    <option value="<?php echo $group['id']; ?>" <?php echo (isset($customer['group_id']) && $customer['group_id'] == $group['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($group['name']); ?> (<?php echo $group['discount_rate']; ?>%)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div>
                <label for="loyalty_points" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-gift text-amber-400 text-xs"></i>Loyalty Points
                </label>
                <input type="number" id="loyalty_points" name="loyalty_points" min="0"
                    value="<?php echo isset($customer['loyalty_points']) ? (int)$customer['loyalty_points'] : 0; ?>"
                    autocomplete="off"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="0">
            </div>
            <div>
                <label for="credit_limit" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                    <i class="fas fa-credit-card text-amber-400 text-xs"></i>Credit Limit
                </label>
                <input type="number" id="credit_limit" name="credit_limit" min="0" step="0.01"
                    value="<?php echo isset($customer['credit_limit']) ? (float)$customer['credit_limit'] : 0; ?>"
                    autocomplete="off"
                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                    placeholder="0.00">
            </div>
        </div>

        <div>
            <label for="notes" class="block text-xs text-slate-500 mb-1 flex items-center gap-1.5">
                <i class="fas fa-sticky-note text-amber-400 text-xs"></i>Notes
            </label>
            <textarea id="notes" name="notes" rows="2"
                autocomplete="off"
                class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Additional notes"><?php echo htmlspecialchars($customer['notes'] ?? ''); ?></textarea>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" name="status" id="status" value="1" <?php echo (!isset($customer['status']) || $customer['status']) ? 'checked' : ''; ?> class="w-4 h-4 rounded accent-amber-400 bg-slate-900 border-slate-700">
            <label for="status" class="text-xs text-slate-500">Active Customer</label>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 pt-3 border-t border-slate-700/60">
            <button type="submit" class="flex-1 px-3 py-2 bg-amber-500/15 border border-amber-500/30 rounded-lg text-amber-400 text-sm font-medium hover:bg-amber-500/25 transition-colors flex items-center justify-center gap-1.5">
                <i class="fas fa-save text-xs"></i><?php echo $is_edit ? 'Update' : 'Save'; ?> <?php echo htmlspecialchars($bt_customer_label); ?>
            </button>
            <a href="customers.php" class="flex-1 px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors flex items-center justify-center gap-1.5">
                <i class="fas fa-times text-xs"></i>Cancel
            </a>
        </div>
    </form>
</div>

<script>
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'customers.php';
        }
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.querySelector('form').submit();
        }
    });
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';
