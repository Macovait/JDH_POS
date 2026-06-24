<?php
/**
 * Add/Edit Customer Page for Jakababa POS
 * Create and manage customer information
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

// Check for customer management permission
if (!check_permission('customers.manage') && !is_super_admin()) {
    enforce_permission('customers.manage');
}

$page_title = 'Add Customer | Jakababa POS';
$user_id = get_current_user_id();
$user_name = get_current_user_name();
$user_role = $_SESSION['role'] ?? 'User';
$tenant_id = get_current_tenant_id();
$branch_id = get_current_branch_id();
$current_branch_name = get_current_branch_name();
$company_name = $_SESSION['tenant_name'] ?? 'Jakababa POS';
// Get customer ID for editing (optional)
$customer_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Get return URL for redirect after save
$return_url = isset($_GET['return']) ? $_GET['return'] : 'customers.php';

// Initialize variables
$customer = null;
$error = '';
$success = '';

// Form fields - match your database columns
$name = '';
$email = '';
$phone = '';
$address = '';
$city = '';
$postal_code = '';
$company = '';
$tax_id = '';
$credit_limit = 0;
$loyalty_points = 0;
$notes = '';

// If editing existing customer
if ($customer_id > 0) {
    try {
        $pdo = get_db_connection();

        // Fetch customer details - using only columns that exist
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone, address, city, postal_code, 
                   company, tax_id, credit_limit, loyalty_points, notes,
                   created_at, updated_at, status
            FROM customers 
            WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            $error = "Customer not found.";
        } else {
            // Populate form fields
            $name = $customer['name'] ?? '';
            $email = $customer['email'] ?? '';
            $phone = $customer['phone'] ?? '';
            $address = $customer['address'] ?? '';
            $city = $customer['city'] ?? '';
            $postal_code = $customer['postal_code'] ?? '';
            $company = $customer['tenant'] ?? '';
            $tax_id = $customer['tax_id'] ?? '';
            $credit_limit = (float) ($customer['credit_limit'] ?? 0);
            $loyalty_points = (int) ($customer['loyalty_points'] ?? 0);
            $notes = $customer['notes'] ?? '';
        }
    } catch (PDOException $e) {
        error_log("Error fetching customer: " . $e->getMessage());
        $error = "Failed to load customer data: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = get_db_connection();

        // Get form data
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $company = trim($_POST['tenant'] ?? '');
        $tax_id = trim($_POST['tax_id'] ?? '');
        $credit_limit = (float) ($_POST['credit_limit'] ?? 0);
        $loyalty_points = (int) ($_POST['loyalty_points'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        // Validate
        if (empty($name)) {
            throw new Exception("Customer name is required.");
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Check if phone already exists (for new customers)
        if (empty($customer_id) && !empty($phone)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE phone = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
            $stmt->execute([$phone]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("A customer with this phone number already exists.");
            }
        }

        // Check if email already exists (for new customers)
        if (empty($customer_id) && !empty($email)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("A customer with this email already exists.");
            }
        }

        $pdo->beginTransaction();

        if ($customer_id > 0) {
            // Update existing customer - using only columns that exist
            $sql = "UPDATE customers SET 
                    name = ?, email = ?, phone = ?, address = ?, 
                    city = ?, postal_code = ?, company = ?, tax_id = ?,
                    credit_limit = ?, loyalty_points = ?, notes = ?";

            // Check if updated_at column exists
            $columns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('updated_at', $columns)) {
                $sql .= ", updated_at = NOW()";
            }

            $sql .= " WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name,
                $email,
                $phone,
                $address,
                $city,
                $postal_code,
                $company,
                $tax_id,
                $credit_limit,
                $loyalty_points,
                $notes,
                $customer_id
            ]);

            $message = "Customer updated successfully.";

        } else {
            // Insert new customer - using only columns that exist
            $columns = $pdo->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);

            $field_names = [
                'name',
                'email',
                'phone',
                'address',
                'city',
                'postal_code',
                'tenant',
                'tax_id',
                'credit_limit',
                'loyalty_points',
                'notes',
                'created_by',
                'tenant_id'
            ];
            $placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
            $values = [
                $name,
                $email,
                $phone,
                $address,
                $city,
                $postal_code,
                $company,
                $tax_id,
                $credit_limit,
                $loyalty_points,
                $notes,
                $user_id,
                $tenant_id
            ];

            // Add created_at if it exists
            if (in_array('created_at', $columns)) {
                $field_names[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            // Add status if it exists
            if (in_array('status', $columns)) {
                $field_names[] = 'status';
                $placeholders[] = '1';
            }

            $sql = "INSERT INTO customers (" . implode(', ', $field_names) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            $customer_id = $pdo->lastInsertId();
            $message = "Customer created successfully.";
        }

        // Log activity
        $log_activity = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $log_activity->execute([
            $user_id,
            $customer_id > 0 ? 'customer_updated' : 'customer_created',
            ($customer_id > 0 ? "Updated" : "Created") . " customer: {$name}" . ($phone ? " ({$phone})" : ""),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $pdo->commit();

        // Redirect based on return URL
        if ($return_url === 'process_shipment') {
            header("Location: process_shipment.php?manual=1&customer_id=" . $customer_id . "&success=customer_added");
        } else {
            header("Location: " . $return_url . "?success=" . ($customer_id > 0 ? 'updated' : 'created'));
        }
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        error_log("Error saving customer: " . $e->getMessage());
    }
}

$csrf_token = generate_csrf_token();

ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-user text-amber-400"></i> <?php echo $customer_id ? 'Edit Customer' : 'Add New Customer'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-0.5"><?php echo $customer_id ? 'Update customer information' : 'Create a new customer record'; ?></p>
    </div>
</div>

<?php if ($error): ?>
<div class="mb-4 flex items-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
    <i class="fas fa-exclamation-circle"></i>
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Customer Form -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
                <form method="POST" action="add_customer.php<?php echo $return_url === 'process_shipment' ? '?return=process_shipment' : ''; ?>" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <?php if ($customer_id): ?>
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <?php endif; ?>

                    <!-- Basic Information -->
                    <div>
                        <h2 class="text-sm font-semibold mb-3 flex items-center gap-2 text-amber-400">
                            <i class="fas fa-info-circle text-xs"></i>Basic Information
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="sm:col-span-2">
                                <label for="name" class="block text-xs text-slate-500 mb-1">Full Name <span class="text-red-400">*</span></label>
                                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="Enter customer name" autocomplete="name">
                            </div>

                            <div>
                                <label for="phone" class="block text-xs text-slate-500 mb-1">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="0712345678" autocomplete="tel">
                            </div>

                            <div>
                                <label for="email" class="block text-xs text-slate-500 mb-1">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="customer@example.com" autocomplete="email">
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="pt-3 border-t border-slate-700/60">
                        <h2 class="text-sm font-semibold mb-3 flex items-center gap-2 text-amber-400">
                            <i class="fas fa-map-marker-alt text-xs"></i>Address Information
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="sm:col-span-2">
                                <label for="address" class="block text-xs text-slate-500 mb-1">Street Address</label>
                                <textarea id="address" name="address" rows="2"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="Full street address" autocomplete="street-address"><?php echo htmlspecialchars($address); ?></textarea>
                            </div>

                            <div>
                                <label for="city" class="block text-xs text-slate-500 mb-1">City</label>
                                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="Nairobi" autocomplete="address-level2">
                            </div>

                            <div>
                                <label for="postal_code" class="block text-xs text-slate-500 mb-1">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($postal_code); ?>"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="00100" autocomplete="postal-code">
                            </div>
                        </div>
                    </div>

                    <!-- Company Information -->
                    <div class="pt-3 border-t border-slate-700/60">
                        <h2 class="text-sm font-semibold mb-3 flex items-center gap-2 text-amber-400">
                            <i class="fas fa-building text-xs"></i>Company Information
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label for="tenant" class="block text-xs text-slate-500 mb-1">Company</label>
                                <input type="text" id="tenant" name="tenant" value="<?php echo htmlspecialchars($company); ?>"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="Company name" autocomplete="organization">
                            </div>

                            <div>
                                <label for="tax_id" class="block text-xs text-slate-500 mb-1">Tax ID / VAT Number</label>
                                <input type="text" id="tax_id" name="tax_id" value="<?php echo htmlspecialchars($tax_id); ?>"
                                    class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    placeholder="PIN-123456789" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <!-- Account Information (Admin only) -->
                    <?php if (is_super_admin() || $user_role === 'Admin'): ?>
                        <div class="pt-3 border-t border-slate-700/60">
                            <h2 class="text-sm font-semibold mb-3 flex items-center gap-2 text-amber-400">
                                <i class="fas fa-cog text-xs"></i>Account Settings
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label for="credit_limit" class="block text-xs text-slate-500 mb-1">Credit Limit (KSh)</label>
                                    <input type="number" id="credit_limit" name="credit_limit" step="0.01" min="0"
                                        value="<?php echo htmlspecialchars($credit_limit); ?>"
                                        class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        placeholder="0.00" autocomplete="off">
                                </div>

                                <div>
                                    <label for="loyalty_points" class="block text-xs text-slate-500 mb-1">Loyalty Points</label>
                                    <input type="number" id="loyalty_points" name="loyalty_points" min="0"
                                        value="<?php echo htmlspecialchars($loyalty_points); ?>"
                                        class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        placeholder="0" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Notes -->
                    <div class="pt-3 border-t border-slate-700/60">
                        <label for="notes" class="block text-xs text-slate-500 mb-1">Notes</label>
                        <textarea id="notes" name="notes" rows="2"
                            class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                            placeholder="Additional notes..." autocomplete="off"><?php echo htmlspecialchars($notes); ?></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row gap-2 pt-3">
                        <button type="submit"
                            class="flex-1 px-3 py-2 bg-emerald-500/15 border border-emerald-500/30 rounded-lg text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors flex items-center justify-center gap-1.5">
                            <i class="fas fa-save text-xs"></i>
                            <?php echo $customer_id ? 'Update Customer' : 'Save Customer'; ?>
                        </button>

                        <a href="<?php echo $return_url === 'process_shipment' ? 'process_shipment.php?manual=1' : 'customers.php'; ?>"
                            class="flex-1 px-3 py-2 bg-slate-700 border border-slate-600 rounded-lg text-slate-400 text-sm font-medium hover:bg-slate-600 transition-colors flex items-center justify-center gap-1.5">
                            <i class="fas fa-times text-xs"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Tips -->
            <div class="mt-4 bg-slate-800/40 border border-slate-700/60 rounded-xl p-3">
                <div class="flex items-start gap-2">
                    <i class="fas fa-lightbulb text-amber-400 mt-0.5 text-xs"></i>
                    <div>
                        <h3 class="text-xs font-semibold text-white mb-1">Quick Tips</h3>
                        <ul class="text-xs text-slate-500 space-y-0.5">
                            <li>• Phone and email are optional but recommended</li>
                            <li>• Address helps with shipping and deliveries</li>
                            <li>• Customers earn loyalty points with each purchase</li>
                            <li>• Credit limit is only for credit sales</li>
                        </ul>
                    </div>
                </div>
            </div>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
require_once __DIR__ . '/../layouts/app_close.php';

