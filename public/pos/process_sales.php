<?php
/**
 * Process Sales Page for Jakababa POS
 * Handles form POST submission from pos.php
 * Processes the sale and redirects to view_sale or back to POS with error
 */

// Bootstrap paths and core dependencies (works from /pos and deeper)
$pathsFile = __DIR__ . '/../../src/paths.php';
if (!file_exists($pathsFile)) {
    $pathsFile = dirname(__DIR__, 3) . '/src/paths.php';
}
require_once $pathsFile;

safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_login();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$csrf_token = generate_csrf_token();

$user_id = (int) ($_SESSION['user']['id'] ?? 0);
$tenant_id = get_current_tenant_id();
$branch_id = (int) ($_SESSION['user']['branch_id'] ?? $_SESSION['branch_id'] ?? 1);

function table_has_col($pdo, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            $cache[$key] = $stmt->rowCount() > 0;
        } catch (Exception $e) { $cache[$key] = false; }
    }
    return $cache[$key];
}

function table_check($pdo, $table) {
    static $cache = [];
    if (!isset($cache[$table])) {
        try { $pdo->query("SELECT 1 FROM `$table` LIMIT 1"); $cache[$table] = true; }
        catch (Exception $e) { $cache[$table] = false; }
    }
    return $cache[$table];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token. Please refresh and try again.');
    }
    $csrf_token = generate_csrf_token();

    // Resolve tenant_id with safe fallbacks to prevent FK errors
    if (!$tenant_id && table_has_col($pdo, 'users', 'tenant_id')) {
        $stmt = $pdo->prepare("SELECT tenant_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u && (int)$u['tenant_id'] > 0) {
            $tenant_id = (int) $u['tenant_id'];
        }
    }

    if (!$tenant_id && table_check($pdo, 'branches') && table_has_col($pdo, 'branches', 'tenant_id') && $branch_id > 0) {
        $stmt = $pdo->prepare("SELECT tenant_id FROM branches WHERE id = ? LIMIT 1");
        $stmt->execute([$branch_id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($b && (int)$b['tenant_id'] > 0) {
            $tenant_id = (int) $b['tenant_id'];
        }
    }

    if (!$tenant_id && table_check($pdo, 'companies')) {
        $stmt = $pdo->query("SELECT id FROM companies ORDER BY id ASC LIMIT 1");
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($c && (int)$c['id'] > 0) {
            $tenant_id = (int) $c['id'];
        }
    }

    if (!$tenant_id) { $tenant_id = 1; }
    $_SESSION['tenant_id'] = $tenant_id;

    $branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : $branch_id;
    $customer_id = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');
    $order_type = $_POST['order_type'] ?? 'walkin';

    // Vertical feature inputs
    $kitchen_notes_in    = trim($_POST['kitchen_notes']    ?? '');
    $table_number_in     = trim($_POST['table_number']     ?? '');
    $prescription_on     = !empty($_POST['prescription_enabled']) && $_POST['prescription_enabled'] != '0';
    $prescription_ref    = trim($_POST['prescription_ref']    ?? '');
    $prescription_doctor = trim($_POST['prescription_doctor'] ?? '');
    $prescription_notes  = trim($_POST['prescription_notes']  ?? '');
    $appointment_id_in   = (int) ($_POST['appointment_id'] ?? 0);
    $room_number_in      = trim($_POST['room_number'] ?? '');
    $guest_name_in       = trim($_POST['guest_name'] ?? '');
    $serial_number_in    = trim($_POST['serial_number'] ?? '');
    $warranty_months_in  = trim($_POST['warranty_months'] ?? '');
    $age_verified_in     = !empty($_POST['age_verified']) && $_POST['age_verified'] != '0';
    $age_verify_id_in    = trim($_POST['age_verify_id'] ?? '');
    $staff_id_in         = (int) ($_POST['staff_id'] ?? 0);
    $batch_number_in     = trim($_POST['batch_number'] ?? '');

    // Get business type
    $business_type = get_current_business_type($tenant_id);
    
    // Business type validation for order type
    safe_require('functions.php', 'src', true);
    $bt_config = get_business_type_config($business_type) ?? [];
    $allowed_order_types = $bt_config['order_types'] ?? ['walkin'];
    
    // Validate order type is allowed for this business type
    if (!in_array($order_type, $allowed_order_types)) {
        throw new Exception("Invalid order type '$order_type' for business type '$business_type'. Allowed: " . implode(', ', $allowed_order_types));
    }
    
    // M-Pesa specific data
    $mpesa_checkout_request_id = $_POST['mpesa_checkout_request_id'] ?? '';
    if ($payment_method === 'mpesa' && empty($mpesa_checkout_request_id)) {
        throw new Exception('M-Pesa payment verification failed: Missing checkout request ID');
    }

    // Get items from JSON
    $items = [];
    if (!empty($_POST['items_json'])) {
        $items = json_decode($_POST['items_json'], true) ?? [];
    }

    if (empty($items)) throw new Exception('No items in cart');

    $subtotal = (float) ($_POST['subtotal'] ?? 0);
    $discount = (float) ($_POST['discount'] ?? 0);
    $tax = (float) ($_POST['tax'] ?? 0);
    $total = (float) ($_POST['total'] ?? 0);

    $pdo = get_db_connection();

    // Detect schema
    $has_company_id_sales = table_has_col($pdo, 'sales', 'tenant_id');
    $has_invoice = table_has_col($pdo, 'sales', 'invoice_number');
    $has_status = table_has_col($pdo, 'sales', 'status');
    $has_subtotal_sales = table_has_col($pdo, 'sales', 'subtotal');
    $has_tax_sales = table_has_col($pdo, 'sales', 'tax');
    $has_order_type_col = table_has_col($pdo, 'sales', 'order_type');
    $has_notes_col = table_has_col($pdo, 'sales', 'notes');
    $has_biz_type_col = table_has_col($pdo, 'sales', 'business_type');

    // Auto-migrate: add business_type column if missing
    if (!$has_biz_type_col) {
        try {
            $pdo->exec("ALTER TABLE sales ADD COLUMN business_type VARCHAR(50) NULL AFTER order_type");
            $pdo->exec("ALTER TABLE sales ADD INDEX idx_sales_business_type (business_type)");
            $has_biz_type_col = true;
        } catch (Exception $e) {
            error_log("Auto-migrate business_type on sales failed: " . $e->getMessage());
        }
    }

    // Auto-migrate: vertical-feature columns on sales table
    $vertical_cols_to_migrate = [
        'age_verified'  => "TINYINT(1) NOT NULL DEFAULT 0",
        'age_verify_id' => "VARCHAR(50) NULL",
        'staff_id'      => "INT NULL",
        'batch_number'  => "VARCHAR(100) NULL",
        'weight_kg'     => "DECIMAL(10,3) NULL",
        'appointment_id' => "INT NULL",
        'room_number'   => "VARCHAR(50) NULL",
        'guest_name'    => "VARCHAR(100) NULL",
        'serial_number' => "VARCHAR(100) NULL",
        'warranty_months' => "VARCHAR(20) NULL",
    ];
    foreach ($vertical_cols_to_migrate as $col => $def) {
        if (!table_has_col($pdo, 'sales', $col)) {
            try {
                $pdo->exec("ALTER TABLE sales ADD COLUMN {$col} {$def}");
            } catch (Exception $e) {
                error_log("Auto-migrate sales.{$col} failed: " . $e->getMessage());
            }
        }
    }

    // Auto-migrate: columns on sale_items table
    $sale_item_cols_to_migrate = [
        'weight_kg'     => "DECIMAL(10,3) NULL",
        'batch_number'  => "VARCHAR(100) NULL",
        'expiry_date'   => "DATE NULL",
        'serial_number' => "VARCHAR(100) NULL",
    ];
    foreach ($sale_item_cols_to_migrate as $col => $def) {
        if (!table_has_col($pdo, 'sale_items', $col)) {
            try {
                $pdo->exec("ALTER TABLE sale_items ADD COLUMN {$col} {$def}");
            } catch (Exception $e) {
                error_log("Auto-migrate sale_items.{$col} failed: " . $e->getMessage());
            }
        }
    }

    $has_subtotal_items = table_has_col($pdo, 'sale_items', 'subtotal');
    $has_company_id_inv = table_has_col($pdo, 'inventory', 'tenant_id');
    $has_selling_price = table_has_col($pdo, 'products', 'selling_price');
    $has_inventory_logs = table_check($pdo, 'inventory_logs');
    $has_stock_movements = table_check($pdo, 'stock_movements');

    $pdo->beginTransaction();

    // Validate stock
    $product_prices = [];
    foreach ($items as $item) {
        $pid = (int) $item['product_id'];
        $qty = (int) $item['quantity'];
        $price_expr = $has_selling_price ? 'COALESCE(p.selling_price, p.price)' : 'p.price';

        // Validate stock with proper tenant isolation (tenant_id + branch_id)
        $stmt = $pdo->prepare("SELECT p.id, p.name, {$price_expr} AS actual_price, COALESCE(i.stock, 0) AS stock 
            FROM products p 
            LEFT JOIN inventory i ON p.id = i.product_id AND i.branch_id = ? AND i.tenant_id = ?
            WHERE p.id = ? AND p.tenant_id = ?");
        $stmt->execute([$branch_id, $tenant_id, $pid, $tenant_id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) throw new Exception("Product #$pid not found");
        if ((int)$p['stock'] < $qty) throw new Exception("Insufficient stock for {$p['name']}: Available {$p['stock']}, Requested {$qty}");

        $product_prices[$pid] = (float) $p['actual_price'];
    }

    // Generate invoice number
    $invoice_number = 'INV-' . date('YmdHis') . '-' . rand(100, 999);
    if ($has_invoice) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_sequences (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NOT NULL, branch_id INT NOT NULL, year INT(4) NOT NULL, month INT(2) NOT NULL, last_number INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_sequence (tenant_id, branch_id, year, month))");
            $year = date('Y'); $month = date('m');
            $stmt = $pdo->prepare("INSERT INTO invoice_sequences (tenant_id, branch_id, year, month, last_number, updated_at) VALUES (?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE last_number = last_number + 1, updated_at = NOW()");
            $stmt->execute([$tenant_id, $branch_id, $year, $month]);
            $stmt = $pdo->prepare("SELECT last_number FROM invoice_sequences WHERE tenant_id = ? AND branch_id = ? AND year = ? AND month = ?");
            $stmt->execute([$tenant_id, $branch_id, $year, $month]);
            $seq = $stmt->fetch(PDO::FETCH_ASSOC);
            $invoice_number = "INV-{$year}{$month}-" . str_pad($seq['last_number'], 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) { error_log("Invoice generation fallback: " . $e->getMessage()); }
    }

    // Build INSERT for sales
    $fields = ['branch_id', 'user_id', 'customer_id', 'total', 'discount', 'payment_method', 'created_at'];
    $pholders = ['?', '?', '?', '?', '?', '?', 'NOW()'];
    $params = [$branch_id, $user_id, $customer_id, $total, $discount, $payment_method];

    if ($has_company_id_sales) { array_unshift($fields, 'tenant_id'); array_unshift($pholders, '?'); array_unshift($params, $tenant_id); }
    if ($has_invoice) { $fields[] = 'invoice_number'; $pholders[] = '?'; $params[] = $invoice_number; }
    if ($has_subtotal_sales) { $fields[] = 'subtotal'; $pholders[] = '?'; $params[] = $subtotal; }
    if ($has_tax_sales) { $fields[] = 'tax'; $pholders[] = '?'; $params[] = $tax; }
    if ($has_status) { $fields[] = 'status'; $pholders[] = '?'; $params[] = 'completed'; }
    if ($has_order_type_col) { $fields[] = 'order_type'; $pholders[] = '?'; $params[] = $order_type; }
    if ($has_notes_col) { $fields[] = 'notes'; $pholders[] = '?'; $params[] = $notes; }
    if ($has_biz_type_col) { $fields[] = 'business_type'; $pholders[] = '?'; $params[] = get_current_business_type($tenant_id); }

    // Optional vertical-feature columns
    $vertical_cols = [
        'kitchen_notes'   => $kitchen_notes_in !== '' ? $kitchen_notes_in : null,
        'table_number'      => $table_number_in !== '' ? $table_number_in : null,
        'appointment_id'    => $appointment_id_in > 0 ? $appointment_id_in : null,
        'room_number'       => $room_number_in !== '' ? $room_number_in : null,
        'guest_name'        => $guest_name_in !== '' ? $guest_name_in : null,
        'serial_number'     => $serial_number_in !== '' ? $serial_number_in : null,
        'warranty_months'   => $warranty_months_in !== '' ? $warranty_months_in : null,
        'age_verified'      => $age_verified_in ? 1 : null,
        'age_verify_id'     => $age_verify_id_in !== '' ? $age_verify_id_in : null,
        'staff_id'          => $staff_id_in > 0 ? $staff_id_in : null,
        'batch_number'      => $batch_number_in !== '' ? $batch_number_in : null,
    ];
    foreach ($vertical_cols as $col => $val) {
        if ($val !== null && table_has_col($pdo, 'sales', $col)) {
            $fields[] = $col;
            $pholders[] = '?';
            $params[] = $val;
        }
    }

    // M-Pesa payment verification
    $mpesa_receipt = null;
    if ($payment_method === 'mpesa') {
        // Verify M-Pesa payment was successful
        $stmt_mpesa = $pdo->prepare("
            SELECT id, mpesa_receipt, status, result_code 
            FROM mpesa_transactions 
            WHERE checkout_request_id = ? AND tenant_id = ?
            LIMIT 1
        ");
        $stmt_mpesa->execute([$mpesa_checkout_request_id, $tenant_id]);
        $mpesa_txn = $stmt_mpesa->fetch(PDO::FETCH_ASSOC);
        
        if (!$mpesa_txn) {
            throw new Exception('M-Pesa payment not found. Please try again.');
        }
        
        if ($mpesa_txn['status'] !== 'completed') {
            throw new Exception('M-Pesa payment was not completed. Status: ' . ($mpesa_txn['result_code'] ?? 'Unknown'));
        }
        
        $mpesa_receipt = $mpesa_txn['mpesa_receipt'];
        
        // Add M-Pesa receipt to sales fields
        if (table_has_col($pdo, 'sales', 'mpesa_receipt')) {
            $fields[] = 'mpesa_receipt';
            $pholders[] = '?';
            $params[] = $mpesa_receipt;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO sales (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $pholders) . ")");
    $stmt->execute($params);
    $sale_id = (int) $pdo->lastInsertId();

    // Insert sale items + update inventory
    foreach ($items as $item) {
        $pid = (int) $item['product_id'];
        $qty = (int) $item['quantity'];
        $price = $product_prices[$pid] ?? (float) ($item['price'] ?? 0);
        $item_sub = $price * $qty;

        if ($has_subtotal_items) {
            $stmt = $pdo->prepare("INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $sale_id, $pid, $qty, $price, $item_sub]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO sale_items (tenant_id, sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $sale_id, $pid, $qty, $price]);
        }

        // Update inventory with proper tenant isolation
        $stmt = $pdo->prepare("SELECT product_id FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
        $stmt->execute([$pid, $branch_id, $tenant_id]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE inventory SET stock = stock - ?, updated_at = NOW() WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
            $stmt->execute([$qty, $pid, $branch_id, $tenant_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO inventory (product_id, branch_id, tenant_id, stock, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$pid, $branch_id, $tenant_id, -$qty]);
        }

        // Log inventory
        if ($has_inventory_logs) {
            try {
                $stmt = $pdo->prepare("INSERT INTO inventory_logs (tenant_id, product_id, branch_id, change_amount, notes, user_id, created_at) VALUES (?, ?, ?, ?, 'Sale', ?, NOW())");
                $stmt->execute([$tenant_id, $pid, $branch_id, -$qty, $user_id]);
            } catch (Exception $e) {}
        }
        
        // Log stock movement
        if ($has_stock_movements) {
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(stock, 0) as current_stock FROM inventory WHERE product_id = ? AND branch_id = ? AND tenant_id = ?");
                $stmt->execute([$pid, $branch_id, $tenant_id]);
                $inv = $stmt->fetch(PDO::FETCH_ASSOC);
                $current_stock = (int) ($inv['current_stock'] ?? 0);
                
                $stmt = $pdo->prepare("INSERT INTO stock_movements (tenant_id, branch_id, product_id, movement_type, quantity_change, quantity_before, quantity_after, notes, user_id, created_at) VALUES (?, ?, ?, 'sale', ?, ?, ?, 'Sale', ?, NOW())");
                $stmt->execute([$tenant_id, $branch_id, $pid, -$qty, $current_stock, $current_stock - $qty, $user_id]);
            } catch (Exception $e) {
                error_log("Stock movement log failed: " . $e->getMessage());
            }
        }
    }

    // Log activity
    try {
        if (function_exists('log_activity')) {
            log_activity($user_id, 'sale_completed', ['sale_id' => $sale_id, 'total' => $total, 'items' => count($items, get_current_tenant_id())]);
        }
    } catch (Exception $e) {}

    // ===== AUTO PRINT LABELS (Enterprise Feature) =====
    if (function_exists('label_printer')) {
        require_once __DIR__ . '/../../src/SettingsManager.php';
        $labelSettings = new SettingsManager($pdo, $tenant_id);
        $autoPrintEnabled = $labelSettings->get('auto_print_labels_on_sale', '0') === '1';

        if ($autoPrintEnabled && !empty($items)) {
            $soldProducts = [];
            foreach ($items as $item) {
                if (!empty($item['product_id'])) {
                    $soldProducts[] = [
                        'id'  => (int)$item['product_id'],
                        'qty' => max(1, (int)($item['quantity'] ?? 1))
                    ];
                }
            }
            if (!empty($soldProducts)) {
                try {
                    $labelSize = $labelSettings->get('auto_print_label_size', 'k22');
                    $method    = $labelSettings->get('auto_print_method', 'pdf'); // pdf | escpos

                    label_printer()->printForProducts(
                        $pdo,
                        $tenant_id,
                        $branch_id,
                        $user_id,
                        $soldProducts,
                        $labelSize,
                        $method
                    );
                    $_SESSION['auto_labels_printed'] = count($soldProducts);
                    $_SESSION['auto_labels_method'] = $method;
                } catch (Exception $e) {
                    error_log("Auto label print failed: " . $e->getMessage());
                }
            }
        }
    }

    // ---- Vertical Feature Persistence ----
    $current_business_type = get_current_business_type($tenant_id);

    // Bakery/Kitchen: Kitchen Order Ticket
    $needs_kot = $kitchen_notes_in !== '' || $table_number_in !== '';
    if ($needs_kot && table_check($pdo, 'kitchen_orders') && table_check($pdo, 'kitchen_order_items')) {
        try {
            $ko_has_tenant = table_has_col($pdo, 'kitchen_orders', 'tenant_id');
            $ko_fields = ['branch_id', 'sale_id', 'table_number', 'order_type', 'status', 'priority', 'notes', 'created_at'];
            $ko_place  = ['?', '?', '?', '?', "'pending'", '0', '?', 'NOW()'];
            $ko_params = [$branch_id, $sale_id, $table_number_in !== '' ? $table_number_in : null, $order_type ?: 'walkin', $kitchen_notes_in !== '' ? $kitchen_notes_in : null];
            if ($ko_has_tenant) { $ko_fields[] = 'tenant_id'; $ko_place[] = '?'; $ko_params[] = $tenant_id; }
            $ko_sql = "INSERT INTO kitchen_orders (" . implode(',', $ko_fields) . ") VALUES (" . implode(',', $ko_place) . ")";
            $stmt = $pdo->prepare($ko_sql);
            $stmt->execute($ko_params);
            $kitchen_order_id = (int) $pdo->lastInsertId();
            if ($kitchen_order_id > 0) {
                $koi_has_tenant = table_has_col($pdo, 'kitchen_order_items', 'tenant_id');
                foreach ($items as $item) {
                    $pid = (int) $item['product_id'];
                    $pname = $product_names[$pid] ?? ("Product #" . $pid);
                    $qty = (int) $item['quantity'];
                    if ($koi_has_tenant) {
                        $stmt = $pdo->prepare("INSERT INTO kitchen_order_items (tenant_id, kitchen_order_id, product_name, quantity, notes, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
                        $stmt->execute([$tenant_id, $kitchen_order_id, $pname, $qty, $kitchen_notes_in ?: null]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO kitchen_order_items (kitchen_order_id, product_name, quantity, notes, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                        $stmt->execute([$kitchen_order_id, $pname, $qty, $kitchen_notes_in ?: null]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("KOT creation failed (non-fatal): " . $e->getMessage());
        }
    }

    // Pharmacy: Prescription record
    if ($current_business_type === 'pharmacy' && ($prescription_on || $prescription_ref !== '')) {
        if (table_check($pdo, 'prescriptions')) {
            try {
                $stmt = $pdo->prepare("INSERT INTO prescriptions (tenant_id, branch_id, sale_id, customer_id, prescription_ref, doctor_name, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'dispensed', NOW())");
                $stmt->execute([$tenant_id, $branch_id, $sale_id, $customer_id, $prescription_ref !== '' ? $prescription_ref : null, $prescription_doctor !== '' ? $prescription_doctor : null, $prescription_notes !== '' ? $prescription_notes : null]);
            } catch (Exception $e) {
                error_log("Prescription creation failed (non-fatal): " . $e->getMessage());
            }
        }
    }

    $pdo->commit();

    // Link appointment to sale (outside main transaction so failure is non-fatal)
    if ($appointment_id_in > 0 && table_check($pdo, 'appointments')) {
        try {
            $appt_has_sale = table_has_col($pdo, 'appointments', 'sale_id');
            $appt_has_status = table_has_col($pdo, 'appointments', 'status');
            $sets = []; $aparams = [];
            if ($appt_has_sale) { $sets[] = 'sale_id = ?'; $aparams[] = $sale_id; }
            if ($appt_has_status) { $sets[] = "status = 'completed'"; }
            if (!empty($sets)) {
                $aparams[] = $appointment_id_in;
                $aparams[] = $tenant_id;
                $stmt = $pdo->prepare("UPDATE appointments SET " . implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?");
                $stmt->execute($aparams);
            }
        } catch (Exception $e) {
            error_log("Appointment link failed (non-fatal): " . $e->getMessage());
        }
    }

    $_SESSION['flash_message'] = "Sale #$sale_id completed successfully" . ($has_invoice ? " ({$invoice_number})" : '');
    $_SESSION['flash_type'] = 'success';
    header("Location: view_sale.php?id={$sale_id}");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Process sale error: " . $e->getMessage());
    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header("Location: pos.php?branch_id=" . $branch_id . "&error=" . urlencode($e->getMessage()));
    exit;
}
