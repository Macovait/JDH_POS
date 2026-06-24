<?php
/**
 * Add Expense page for Jakababa POS
 * Quick form for adding new expenses
 */

require_once __DIR__ . '/../../src/auth.php';

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
require_login();

// Check for expenses management permission
if (!check_permission('expenses.create') && !check_permission('expenses.view') && !is_super_admin()) {
    enforce_permission('expenses.create');
}

require_once __DIR__ . '/../../src/db.php';

$pdo = get_db_connection();

// Get current user info
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$branch_id = (int) ($_SESSION['branch_id'] ?? 1);

// Get expense categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM expense_categories WHERE branch_id = $current_branch_id ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get branches
$branches = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM branches WHERE active = 1 AND branch_id = $current_branch_id ORDER BY name");
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching branches: " . $e->getMessage());
}

// Handle form submission
$success_message = '';
$error_message = '';
$form_data = [
    'category_id' => '',
    'branch_id' => $branch_id,
    'amount' => '',
    'description' => '',
    'expense_date' => date('Y-m-d'),
    'payment_method' => 'cash',
    'reference_number' => '',
    'vendor' => '',
    'tax_amount' => '0',
    'notes' => '',
    'is_recurring' => false,
    'recurring_frequency' => 'monthly',
    'recurring_end_date' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data = [
        'category_id' => intval($_POST['category_id'] ?? 0),
        'branch_id' => intval($_POST['branch_id'] ?? $branch_id),
        'amount' => floatval($_POST['amount'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'expense_date' => $_POST['expense_date'] ?? date('Y-m-d'),
        'payment_method' => $_POST['payment_method'] ?? 'cash',
        'reference_number' => trim($_POST['reference_number'] ?? ''),
        'vendor' => trim($_POST['vendor'] ?? ''),
        'tax_amount' => floatval($_POST['tax_amount'] ?? 0),
        'notes' => trim($_POST['notes'] ?? ''),
        'is_recurring' => isset($_POST['is_recurring']),
        'recurring_frequency' => $_POST['recurring_frequency'] ?? 'monthly',
        'recurring_end_date' => $_POST['recurring_end_date'] ?? ''
    ];

    // Validation
    $errors = [];

    if ($form_data['category_id'] === 0) {
        $errors[] = 'Please select an expense category';
    }

    if ($form_data['amount'] <= 0) {
        $errors[] = 'Amount must be greater than zero';
    }

    if (empty($form_data['description'])) {
        $errors[] = 'Description is required';
    }

    if (empty($form_data['expense_date'])) {
        $errors[] = 'Expense date is required';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert expense
            $stmt = $pdo->prepare('
                INSERT INTO expenses (
                    category_id, branch_id, amount, description, expense_date,
                    payment_method, reference_number, vendor, tax_amount, notes,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            
            $stmt->execute([
                $form_data['category_id'],
                $form_data['branch_id'],
                $form_data['amount'],
                $form_data['description'],
                $form_data['expense_date'],
                $form_data['payment_method'],
                $form_data['reference_number'],
                $form_data['vendor'],
                $form_data['tax_amount'],
                $form_data['notes'],
                $user_id
            ]);

            $expense_id = $pdo->lastInsertId();

            // Handle receipt upload
            if (!empty($_FILES['receipt']['name']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
                $filename = 'receipt_' . $expense_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filepath)) {
                    $stmt = $pdo->prepare('UPDATE expenses SET receipt_image = ? WHERE id = ?');
                    $stmt->execute(['/uploads/receipts/' . $filename, $expense_id]);
                }
            }

            // Handle recurring expense
            if ($form_data['is_recurring']) {
                $stmt = $pdo->prepare('
                    INSERT INTO recurring_expenses (expense_id, frequency, end_date, created_at)
                    VALUES (?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $expense_id,
                    $form_data['recurring_frequency'],
                    $form_data['recurring_end_date'] ?: null
                ]);
            }

            // Log activity
            log_activity('expense.created', null, [
                'expense_id' => $expense_id, 'amount' => $form_data['amount'],
                'category_id' => $form_data['category_id']
            ], $user_id, get_current_tenant_id());

            $pdo->commit();

            $success_message = 'Expense added successfully!';

            // Reset form data for new entry
            $form_data = [
                'category_id' => '',
                'branch_id' => $branch_id,
                'amount' => '',
                'description' => '',
                'expense_date' => date('Y-m-d'),
                'payment_method' => 'cash',
                'reference_number' => '',
                'vendor' => '',
                'tax_amount' => '0',
                'notes' => '',
                'is_recurring' => false,
                'recurring_frequency' => 'monthly',
                'recurring_end_date' => ''
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error saving expense: " . $e->getMessage());
            $errors[] = 'Database error: Failed to save expense';
        }
    }

    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}

$page_title = 'Add Expense';
ob_start();
?>

<style>
        /* Clean card style */
        .card {
            background: #1F2937;
            border: 1px solid #4B5563;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        /* Form card */
        .form-card {
            background: #1F2937;
            border: 1px solid #4B5563;
            border-radius: 1.5rem;
            padding: 2rem;
        }

        /* Input fields */
        .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 {
            background: #111827;
            border: 1px solid #4B5563;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            color: #F9FAFB;
            width: 100%;
            transition: all 0.2s;
        }

        .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500:focus {
            outline: none;
            border-color: #FBBF24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.3);
        }

        .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500::placeholder {
            color: #6B7280;
        }

        .w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500:disabled {
            background: #374151;
            color: #9CA3AF;
            cursor: not-allowed;
        }

        /* Labels */
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #D1D5DB;
            margin-bottom: 0.5rem;
        }

        .form-label i {
            color: #FBBF24;
            width: 1.25rem;
        }

        /* Buttons */
        .btn-primary {
            background: #FBBF24;
            color: #1E3A8A;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            transition: all 0.2s;
            border: 1px solid #FBBF24;
            font-size: 1rem;
        }

        .btn-primary:hover {
            background: #F59E0B;
            border-color: #F59E0B;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
        }

        .btn-secondary {
            background: #374151;
            color: #F3F4F6;
            font-weight: 500;
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            transition: all 0.2s;
            border: 1px solid #4B5563;
            font-size: 1rem;
        }

        .btn-secondary:hover {
            background: #4B5563;
            border-color: #6B7280;
            transform: translateY(-2px);
        }

        /* Messages */
        .message-success {
            background: #065F46;
            color: #D1FAE5;
            border: 1px solid #10B981;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .message-error {
            background: #7F1D1D;
            color: #FEE2E2;
            border: 1px solid #EF4444;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Quick amount buttons */
        .quick-amount {
            background: #111827;
            border: 1px solid #4B5563;
            border-radius: 0.5rem;
            padding: 0.5rem;
            color: #D1D5DB;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .quick-amount:hover {
            background: #FBBF24;
            color: #1E3A8A;
            border-color: #FBBF24;
        }

        /* Receipt preview */
        .receipt-preview {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #111827;
            border: 1px dashed #4B5563;
            border-radius: 0.5rem;
            display: none;
        }

        .receipt-preview img {
            max-height: 100px;
            border-radius: 0.375rem;
        }

        /* Simple animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }


</style>

<div class="fade-in">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-[#F3F4F6]">Record New Expense</h1>
            <p class="text-[#9CA3AF] mt-2">Enter the details of your business expense below</p>
        </div>

        <!-- Success Message -->
        <?php if ($success_message): ?>
            <div class="message-success">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <div class="mt-3 flex gap-3">
                    <a href="add_expense.php" class="text-sm bg-[#065F46] hover:bg-[#047857] px-3 py-1 rounded-lg transition">
                        <i class="fas fa-plus mr-1"></i>Add Another
                    </a>
                    <a href="expenses.php" class="text-sm bg-[#065F46] hover:bg-[#047857] px-3 py-1 rounded-lg transition">
                        <i class="fas fa-list mr-1"></i>View All Expenses
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="message-error">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Expense Form -->
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" id="expenseForm">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <!-- Category and Branch -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
                    <div>
                        <label class="form-label">
                            <i class="fas fa-tag"></i>
                            Expense Category *
                        </label>
                        <select name="category_id" required class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $form_data['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($categories)): ?>
                            <p class="text-xs text-[#FBBF24] mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                No categories found. Please create one in Expense Management.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label">
                            <i class="fas fa-store"></i>
                            Branch
                        </label>
                        <select name="branch_id" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <?php foreach ($branches as $br): ?>
                                <option value="<?php echo $br['id']; ?>" <?php echo $form_data['branch_id'] == $br['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($br['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i>
                        Description *
                    </label>
                    <input type="text" name="description" required 
                           value="<?php echo htmlspecialchars($form_data['description']); ?>"
                           placeholder="e.g., Office supplies, Utilities, Rent, etc."
                           class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                </div>

                <!-- Amount and Tax -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
                    <div>
                        <label class="form-label">
                            <i class="fas fa-coins"></i>
                            Amount (KSh) *
                        </label>
                        <input type="number" name="amount" required step="0.01" min="0.01"
                               value="<?php echo htmlspecialchars($form_data['amount']); ?>"
                               placeholder="0.00"
                               class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" id="amount">
                        
                        <!-- Quick Amount Buttons -->
                        <div class="grid grid-cols-4 gap-2 mt-2">
                            <div class="quick-amount" onclick="setAmount(500)">500</div>
                            <div class="quick-amount" onclick="setAmount(1000)">1,000</div>
                            <div class="quick-amount" onclick="setAmount(5000)">5,000</div>
                            <div class="quick-amount" onclick="setAmount(10000)">10,000</div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">
                            <i class="fas fa-percent"></i>
                            Tax Amount (KSh)
                        </label>
                        <input type="number" name="tax_amount" step="0.01" min="0"
                               value="<?php echo htmlspecialchars($form_data['tax_amount']); ?>"
                               placeholder="0.00"
                               class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" id="taxAmount">
                        <p class="text-xs text-[#9CA3AF] mt-1">VAT/withholding tax if applicable</p>
                    </div>
                </div>

                <!-- Date and Payment Method -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
                    <div>
                        <label class="form-label">
                            <i class="fas fa-calendar-alt"></i>
                            Expense Date *
                        </label>
                        <input type="date" name="expense_date" required
                               value="<?php echo htmlspecialchars($form_data['expense_date']); ?>"
                               class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="form-label">
                            <i class="fas fa-credit-card"></i>
                            Payment Method
                        </label>
                        <select name="payment_method" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="cash" <?php echo $form_data['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="card" <?php echo $form_data['payment_method'] === 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="bank_transfer" <?php echo $form_data['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="mpesa" <?php echo $form_data['payment_method'] === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                            <option value="cheque" <?php echo $form_data['payment_method'] === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                        </select>
                    </div>
                </div>

                <!-- Vendor and Reference -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
                    <div>
                        <label class="form-label">
                            <i class="fas fa-building"></i>
                            Vendor/Supplier
                        </label>
                        <input type="text" name="vendor" 
                               value="<?php echo htmlspecialchars($form_data['vendor']); ?>"
                               placeholder="e.g., Supplier name"
                               class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>

                    <div>
                        <label class="form-label">
                            <i class="fas fa-hashtag"></i>
                            Reference Number
                        </label>
                        <input type="text" name="reference_number" 
                               value="<?php echo htmlspecialchars($form_data['reference_number']); ?>"
                               placeholder="Invoice/receipt number"
                               class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                    </div>
                </div>

                <!-- Notes -->
                <div class="mb-6">
                    <label class="form-label">
                        <i class="fas fa-sticky-note"></i>
                        Additional Notes
                    </label>
                    <textarea name="notes" rows="3" 
                              class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500" 
                              placeholder="Any additional information about this expense..."><?php echo htmlspecialchars($form_data['notes']); ?></textarea>
                </div>

                <!-- Receipt Upload -->
                <div class="mb-6">
                    <label class="form-label">
                        <i class="fas fa-file-invoice"></i>
                        Receipt/Attachment
                    </label>
                    <input type="file" name="receipt" id="receipt" accept="image/*,.pdf"
                           class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 p-2">
                    <p class="text-xs text-[#9CA3AF] mt-1">Upload receipt image or PDF (max 5MB)</p>
                    
                    <!-- Receipt Preview -->
                    <div id="receiptPreview" class="receipt-preview">
                        <p class="text-xs text-[#9CA3AF] mb-2">Preview:</p>
                        <img id="previewImage" src="" alt="Receipt preview">
                    </div>
                </div>

                <!-- Recurring Expense Option -->
                <div class="mb-6">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_recurring" id="isRecurring" value="1" 
                               <?php echo $form_data['is_recurring'] ? 'checked' : ''; ?>
                               class="w-5 h-5 accent-[#FBBF24]">
                        <span class="text-[#D1D5DB]">This is a recurring expense</span>
                    </label>
                </div>

                <!-- Recurring Options (hidden by default) -->
                <div id="recurringOptions" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6 p-4 bg-[#111827] rounded-lg <?php echo $form_data['is_recurring'] ? '' : 'hidden'; ?>">
                    <div>
                        <label class="form-label">
                            <i class="fas fa-sync-alt"></i>
                            Frequency
                        </label>
                        <select name="recurring_frequency" class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            <option value="daily" <?php echo $form_data['recurring_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $form_data['recurring_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $form_data['recurring_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarterly" <?php echo $form_data['recurring_frequency'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="yearly" <?php echo $form_data['recurring_frequency'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">
                            <i class="fas fa-calendar-times"></i>
                            End Date (optional)
                        </label>
                        <input type="date" name="recurring_end_date" 
                               value="<?php echo htmlspecialchars($form_data['recurring_end_date']); ?>"
                               class="w-full px-2 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                        <p class="text-xs text-[#9CA3AF] mt-1">Leave empty for indefinite</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-[#4B5563]">
                    <a href="expenses.php" class="btn-secondary">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-2"></i>
                        Save Expense
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Tips -->
        <div class="mt-6 p-4 bg-[#111827] rounded-lg border border-[#4B5563]">
            <h3 class="text-sm font-semibold text-[#FBBF24] mb-2 flex items-center gap-2">
                <i class="fas fa-lightbulb"></i>
                Quick Tips
            </h3>
            <ul class="text-xs text-[#9CA3AF] space-y-1">
                <li><i class="fas fa-check-circle text-[#10B981] mr-2"></i>Use the quick amount buttons for common expense values</li>
                <li><i class="fas fa-check-circle text-[#10B981] mr-2"></i>Attach receipts for better record keeping</li>
                <li><i class="fas fa-check-circle text-[#10B981] mr-2"></i>Set up recurring expenses for regular payments like rent, utilities</li>
                <li><i class="fas fa-check-circle text-[#10B981] mr-2"></i>Include tax amounts separately for accurate accounting</li>
                <li><i class="fas fa-check-circle text-[#10B981] mr-2"></i>Reference numbers help track invoices and receipts</li>
            </ul>
        </div>
    </div>

    <!-- Connection Status -->
    <div id="connection-status" class="fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#4B5563]">
        <i class="fas fa-wifi"></i>
        <span>Online</span>
    </div>

    <script>
        // Set amount from quick buttons
        function setAmount(value) {
            document.getElementById('amount').value = value;
            // Highlight the field briefly
            const field = document.getElementById('amount');
            field.style.backgroundColor = '#374151';
            setTimeout(() => {
                field.style.backgroundColor = '';
            }, 200);
        }

        // Toggle recurring options
        document.getElementById('isRecurring')?.addEventListener('change', function() {
            const options = document.getElementById('recurringOptions');
            if (this.checked) {
                options.classList.remove('hidden');
            } else {
                options.classList.add('hidden');
            }
        });

        // Receipt preview
        document.getElementById('receipt')?.addEventListener('change', function(e) {
            const preview = document.getElementById('receiptPreview');
            const previewImage = document.getElementById('previewImage');
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileType = file.type;
                
                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else if (fileType === 'application/pdf') {
                    previewImage.style.display = 'none';
                    preview.innerHTML = '<p class="text-sm text-[#FBBF24]"><i class="fas fa-file-pdf mr-2"></i>PDF file selected</p>';
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
            } else {
                preview.style.display = 'none';
            }
        });

        // Auto-calculate total including tax (optional)
        document.getElementById('amount')?.addEventListener('input', updateTotal);
        document.getElementById('taxAmount')?.addEventListener('input', updateTotal);

        function updateTotal() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const tax = parseFloat(document.getElementById('taxAmount').value) || 0;
            // You could display total somewhere if needed
        }

        // Form validation
        document.getElementById('expenseForm')?.addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            if (amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount greater than zero');
                document.getElementById('amount').focus();
            }
        });

        // Auto-dismiss success message after 8 seconds
        setTimeout(() => {
            const successMsg = document.querySelector('.message-success');
            if (successMsg) {
                successMsg.style.transition = 'opacity 0.5s';
                successMsg.style.opacity = '0';
                setTimeout(() => successMsg.remove(), 500);
            }
        }, 8000);

        // Connection status
        function updateOnlineStatus() {
            const statusEl = document.getElementById('connection-status');
            if (statusEl) {
                if (navigator.onLine) {
                    statusEl.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                    statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#10B981] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#4B5563]';
                } else {
                    statusEl.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline</span>';
                    statusEl.className = 'fixed bottom-4 left-4 text-xs text-[#EF4444] flex items-center gap-1 bg-[#1F2937] px-3 py-2 rounded-full border border-[#4B5563]';
                }
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('expenseForm').requestSubmit();
            }
            
            // Ctrl/Cmd + C to cancel
            if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                e.preventDefault();
                if (confirm('Cancel and go back to expenses?')) {
                    window.location.href = 'expenses.php';
                }
            }
        });

        // Remember form state (optional)
        window.addEventListener('load', function() {
            // Check if there's saved form data
            const savedData = sessionStorage.getItem('expenseFormData');
            if (savedData && !<?php echo json_encode(!empty($_POST)); ?>) {
                if (confirm('You have unsaved form data from a previous session. Restore it?')) {
                    const data = JSON.parse(savedData);
                    for (let key in data) {
                        const field = document.querySelector(`[name="${key}"]`);
                        if (field) {
                            if (field.type === 'checkbox') {
                                field.checked = data[key];
                            } else {
                                field.value = data[key];
                            }
                        }
                    }
                    if (data.is_recurring) {
                        document.getElementById('recurringOptions').classList.remove('hidden');
                    }
                }
                sessionStorage.removeItem('expenseFormData');
            }
        });

        // Save form data before page hide (modern replacement for deprecated beforeunload)
        window.addEventListener('pagehide', function() {
            if (!<?php echo json_encode(!empty($_POST) && empty($errors)); ?>) {
                const formData = {
                    category_id: document.querySelector('[name="category_id"]')?.value,
                    branch_id: document.querySelector('[name="branch_id"]')?.value,
                    amount: document.querySelector('[name="amount"]')?.value,
                    description: document.querySelector('[name="description"]')?.value,
                    expense_date: document.querySelector('[name="expense_date"]')?.value,
                    payment_method: document.querySelector('[name="payment_method"]')?.value,
                    reference_number: document.querySelector('[name="reference_number"]')?.value,
                    vendor: document.querySelector('[name="vendor"]')?.value,
                    tax_amount: document.querySelector('[name="tax_amount"]')?.value,
                    notes: document.querySelector('[name="notes"]')?.value,
                    is_recurring: document.querySelector('[name="is_recurring"]')?.checked,
                    recurring_frequency: document.querySelector('[name="recurring_frequency"]')?.value,
                    recurring_end_date: document.querySelector('[name="recurring_end_date"]')?.value
                };
                sessionStorage.setItem('expenseFormData', JSON.stringify(formData));
            }
        });
    </script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app_close.php';
require_once __DIR__ . '/../layouts/app.php';
?>