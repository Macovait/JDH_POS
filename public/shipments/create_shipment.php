<?php
/**
 * Create Shipment Page for Jakababa POS
 * Create new shipments for customer orders with dashboard color scheme
 */

require_once __DIR__ . '/../../src/auth.php';
require_login();
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

$page_title = 'Create Shipment';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$user_role = $_SESSION['role'] ?? '';
$tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
$user_branch = get_current_branch_id();
$current_branch_name = get_current_branch_name();

// Fetch customers for dropdown
$customers = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("
        SELECT id, name, phone, email, address, city, postal_code, company 
        FROM customers 
        WHERE deleted_at IS NULL 
        ORDER BY name
    ");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

// Fetch sales orders that can be shipped
$sales_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.invoice_number, s.created_at, 
               COALESCE(c.name, 'Walk-in Customer') as customer_name,
               s.total, s.payment_method
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.branch_id = ? AND s.status = 'completed' 
        AND NOT EXISTS (SELECT 1 FROM shipments WHERE sale_id = s.id)
        ORDER BY s.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_branch]);
    $sales_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching sales orders: " . $e->getMessage());
}

// Fetch courier services
$couriers = [
    ['name' => 'JNE', 'services' => ['REG' => 'Regular', 'YES' => 'YES', 'OKE' => 'OKE']],
    ['name' => 'J&T', 'services' => ['REG' => 'Regular', 'EZ' => 'EZ']],
    ['name' => 'SiCepat', 'services' => ['REG' => 'Regular', 'BEST' => 'BEST']],
    ['name' => 'Pos Indonesia', 'services' => ['POS' => 'Pos Reguler', 'KILAT' => 'Pos Kilat']],
    ['name' => 'Tiki', 'services' => ['ONS' => 'ONS', 'REG' => 'REG']],
    ['name' => 'Wahana', 'services' => ['REG' => 'Regular']],
    ['name' => 'DHL', 'services' => ['EXPRESS' => 'Express', 'ECONOMY' => 'Economy']],
    ['name' => 'FedEx', 'services' => ['PRIORITY' => 'Priority', 'ECONOMY' => 'Economy']]
];

// Get currency from settings
$currency_symbol = 'KSh';
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency' AND tenant_id = " . (int)$tenant_id);
    $currency = $stmt->fetchColumn();
    if ($currency) {
        $currency_symbol = $currency;
    }
} catch (Exception $e) {
    // Use default
}

// Generate tracking number
function generateTrackingNumber()
{
    $prefix = 'SHIP';
    $date = date('Ymd');
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $date . '-' . $random;
}

$tracking_number = generateTrackingNumber();
ob_start();
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-truck text-amber-400"></i> New Shipment
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Create a new shipment for a customer order</p>
    </div>
    <div class="flex items-center gap-2 shrink-0 flex-wrap">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 font-mono text-amber-400 text-sm">
            <i class="fas fa-qrcode text-xs"></i><?php echo $tracking_number; ?>
        </span>
        <a href="shipments.php"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back
        </a>
    </div>
</div>

<!-- Main Form -->
<form id="shipmentForm" method="POST" action="process_shipment.php" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
        <input type="hidden" name="tracking_number" value="<?php echo $tracking_number; ?>">
        <input type="hidden" name="branch_id" value="<?php echo $user_branch; ?>">
        <input type="hidden" name="created_by" value="<?php echo $user_id; ?>">

<!-- Order Information -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
        <i class="fas fa-shopping-cart text-amber-400"></i> Order Information
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-box mr-1"></i>Select Order</label>
            <select name="sale_id" id="saleSelect" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                onchange="loadOrderDetails()">
                <option value="">Select an order to ship</option>
                <?php foreach ($sales_orders as $order): ?>
                <option value="<?php echo $order['id']; ?>"
                    data-customer="<?php echo htmlspecialchars($order['customer_name']); ?>"
                    data-total="<?php echo $order['total']; ?>">
                    <?php echo $order['invoice_number']; ?> - <?php echo htmlspecialchars($order['customer_name']); ?> - <?php echo $currency_symbol . number_format($order['total'], 2); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-user mr-1"></i>Or Select Customer</label>
            <select name="customer_id" id="customerSelect"
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                onchange="loadCustomerAddress()">
                <option value="">Select customer (optional)</option>
                <?php foreach ($customers as $customer): ?>
                <option value="<?php echo $customer['id']; ?>"
                    data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                    data-address="<?php echo htmlspecialchars($customer['address']); ?>"
                    data-city="<?php echo htmlspecialchars($customer['city']); ?>"
                    data-postal="<?php echo htmlspecialchars($customer['postal_code']); ?>"
                    data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                    data-email="<?php echo htmlspecialchars($customer['email']); ?>">
                    <?php echo htmlspecialchars($customer['name']); ?> - <?php echo htmlspecialchars($customer['phone']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div id="orderSummary" class="mt-3 hidden">
        <div class="bg-slate-900/60 rounded-lg p-3 border border-slate-700/60 grid grid-cols-2 gap-2 text-xs">
            <div><span class="text-slate-500">Invoice:</span> <span id="orderInvoice" class="font-mono text-white ml-1"></span></div>
            <div><span class="text-slate-500">Customer:</span> <span id="orderCustomer" class="text-white ml-1"></span></div>
            <div><span class="text-slate-500">Date:</span> <span id="orderDate" class="text-white ml-1"></span></div>
            <div><span class="text-slate-500">Total:</span> <span id="orderTotal" class="text-amber-400 font-semibold ml-1"></span></div>
        </div>
    </div>
</div>

<!-- Shipping Address -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
        <i class="fas fa-map-pin text-amber-400"></i> Shipping Address
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-user mr-1"></i>Recipient Name *</label>
            <input type="text" name="recipient_name" id="recipientName" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Full name of recipient">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-home mr-1"></i>Street Address *</label>
            <input type="text" name="address" id="address" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Street address, building, apartment">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-city mr-1"></i>City *</label>
            <input type="text" name="city" id="city" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="City">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-mail-bulk mr-1"></i>Postal Code</label>
            <input type="text" name="postal_code" id="postalCode"
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Postal code">
        </div>
    </div>
</div>

<!-- Contact Information -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
        <i class="fas fa-phone-alt text-amber-400"></i> Contact Information
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-phone mr-1"></i>Phone Number *</label>
            <input type="tel" name="phone" id="phone" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Contact phone number">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-envelope mr-1"></i>Email Address</label>
            <input type="email" name="email" id="email"
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                placeholder="Email for notifications">
        </div>
    </div>
</div>

<!-- Shipping Options -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
        <i class="fas fa-truck text-amber-400"></i> Shipping Options
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-truck-moving mr-1"></i>Courier *</label>
            <select name="courier" id="courierSelect" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                onchange="loadCourierServices()">
                <option value="">Select Courier</option>
                <?php foreach ($couriers as $courier): ?>
                <option value="<?php echo $courier['name']; ?>"><?php echo $courier['name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-cog mr-1"></i>Service Type *</label>
            <select name="courier_service" id="serviceSelect" required
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
                <option value="">Select Service</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-coins mr-1"></i>Shipping Cost *</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-2.5 flex items-center text-amber-400 text-xs pointer-events-none"><i class="fas fa-dollar-sign"></i></span>
                <input type="number" name="shipping_cost" id="shippingCost" required min="0" step="0.01" value="0.00"
                    class="w-full pl-7 pr-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-calendar-alt mr-1"></i>Estimated Delivery</label>
            <input type="date" name="estimated_delivery" id="estimatedDelivery"
                value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>"
                min="<?php echo date('Y-m-d'); ?>"
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
        </div>
    </div>
    <div class="mt-3 pt-3 border-t border-slate-700/60">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-cube mr-1"></i>Package Details</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Weight (kg)</label>
                <input type="number" name="weight" min="0.1" step="0.1" value="1.0"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Length (cm)</label>
                <input type="number" name="length" min="0" value="10"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Width (cm)</label>
                <input type="number" name="width" min="0" value="10"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Height (cm)</label>
                <input type="number" name="height" min="0" value="10"
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500">
            </div>
        </div>
    </div>
</div>

<!-- Shipment Items -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
        <i class="fas fa-boxes text-amber-400"></i> Items to Ship
    </h2>
    <div class="grid grid-cols-12 gap-2 mb-2 px-1 text-xs text-slate-500">
        <div class="col-span-5">Item Name</div>
        <div class="col-span-2">Qty</div>
        <div class="col-span-2">Price</div>
        <div class="col-span-2">Weight</div>
        <div class="col-span-1"></div>
    </div>
    <div id="items-container" class="space-y-2"></div>
    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-700/60">
        <button type="button" onclick="addItemRow()"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
            <i class="fas fa-plus text-xs"></i> Add Item
        </button>
        <div class="text-xs text-slate-500">
            Total Items: <span id="totalItems" class="text-amber-400 font-bold text-base ml-1">0</span>
        </div>
    </div>
</div>

<!-- Additional Information -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide flex items-center gap-1.5 mb-3">
        <i class="fas fa-file-alt text-amber-400"></i> Additional Information
    </h2>
    <div class="space-y-3">
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1"><i class="fas fa-pen mr-1"></i>Shipping Notes</label>
            <textarea name="notes" rows="3"
                class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 resize-none"
                placeholder="Special instructions for courier, delivery time preferences, etc."></textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                <input type="checkbox" name="signature_required" value="1" checked class="accent-amber-500">
                <i class="fas fa-pen-fancy text-amber-400 text-xs"></i> Signature required upon delivery
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                <input type="checkbox" name="sms_notification" value="1" class="accent-amber-500">
                <i class="fas fa-sms text-amber-400 text-xs"></i> SMS notification to recipient
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                <input type="checkbox" name="email_notification" value="1" checked class="accent-amber-500">
                <i class="fas fa-envelope text-amber-400 text-xs"></i> Email notification to recipient
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                <input type="checkbox" name="weekend_delivery" value="1" class="accent-amber-500">
                <i class="fas fa-calendar-week text-amber-400 text-xs"></i> Allow weekend delivery
            </label>
        </div>
    </div>
</div>

<!-- Summary & Actions -->
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl p-4">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
            <p class="text-xs text-slate-500 uppercase tracking-wide"><i class="fas fa-coins mr-1"></i>Total Shipment Value</p>
            <p class="text-2xl font-bold text-amber-400" id="totalValue"><?php echo $currency_symbol; ?> 0.00</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="submit" name="action" value="create"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-semibold hover:bg-emerald-500/25 transition-colors">
                <i class="fas fa-check-circle text-xs"></i> Create Shipment
            </button>
            <button type="submit" name="action" value="draft"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm font-medium hover:bg-amber-500/20 transition-colors">
                <i class="fas fa-save text-xs"></i> Save as Draft
            </button>
            <a href="shipments.php"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-times text-xs"></i> Cancel
            </a>
        </div>
    </div>
    <p class="text-xs text-slate-600 text-right mt-2"><i class="fas fa-info-circle mr-1"></i>By creating this shipment, you agree to the courier's terms and conditions.</p>
</div>
</form>

<!-- Address Preview Modal -->
<div id="addressPreviewModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center">
    <div class="bg-slate-800 rounded-xl border border-slate-700 p-5 max-w-md w-full mx-4 max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-semibold text-white flex items-center gap-2">
                <i class="fas fa-map-pin text-amber-400"></i> Address Preview
            </h3>
            <button onclick="closeAddressPreview()" class="text-slate-500 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="previewContent" class="bg-slate-900/60 border border-slate-700/60 rounded-lg p-4 mb-4 text-sm"></div>
        <div class="flex gap-2.5">
            <button onclick="useThisAddress()"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-semibold hover:bg-emerald-500/25 transition-colors">
                <i class="fas fa-check text-xs"></i> Use This Address
            </button>
            <button onclick="closeAddressPreview()"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg bg-slate-700 border border-slate-600 text-slate-300 text-sm font-medium hover:bg-slate-600 transition-colors">
                <i class="fas fa-pencil-alt text-xs"></i> Edit
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-[9999] space-y-2"></div>

<script>
    let itemCount = 0;
    const currencySymbol = '<?php echo $currency_symbol; ?>';

    document.addEventListener('DOMContentLoaded', function () {
        addItemRow();
    });

    function showToast(message, type = 'info') {
        const colors = { success: 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400', error: 'bg-red-500/10 border-red-500/30 text-red-400', info: 'bg-amber-500/10 border-amber-500/30 text-amber-400' };
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `flex items-center gap-2 px-3 py-2 rounded-lg border text-sm ${colors[type] || colors.info}`;
        toast.innerHTML = `<i class="fas ${icons[type] || 'fa-info-circle'}"></i>${message}`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(() => toast.remove(), 400); }, 3000);
    }

    function loadOrderDetails() {
        const select = document.getElementById('saleSelect');
        const summary = document.getElementById('orderSummary');
        const selected = select.options[select.selectedIndex];
        if (selected.value) {
            document.getElementById('orderInvoice').textContent = selected.text.split(' - ')[0];
            document.getElementById('orderCustomer').textContent = selected.dataset.customer;
            document.getElementById('orderDate').textContent = new Date().toLocaleDateString();
            document.getElementById('orderTotal').textContent = currencySymbol + ' ' + parseFloat(selected.dataset.total).toFixed(2);
            summary.classList.remove('hidden');
            showToast('Order loaded successfully', 'success');
        } else {
            summary.classList.add('hidden');
        }
    }

    function loadCustomerAddress() {
        const select = document.getElementById('customerSelect');
        const selected = select.options[select.selectedIndex];
        if (selected.value) {
            document.getElementById('recipientName').value = selected.dataset.name || '';
            document.getElementById('address').value = selected.dataset.address || '';
            document.getElementById('city').value = selected.dataset.city || '';
            document.getElementById('postalCode').value = selected.dataset.postal || '';
            document.getElementById('phone').value = selected.dataset.phone || '';
            document.getElementById('email').value = selected.dataset.email || '';
            showToast('Customer address loaded', 'success');
        }
    }

    function loadCourierServices() {
        const courier = document.getElementById('courierSelect').value;
        const serviceSelect = document.getElementById('serviceSelect');
        serviceSelect.innerHTML = '<option value="">Select Service</option>';
        const services = {
            'JNE': ['REG', 'YES', 'OKE'], 'J&T': ['REG', 'EZ'], 'SiCepat': ['REG', 'BEST'],
            'Pos Indonesia': ['POS', 'KILAT'], 'Tiki': ['ONS', 'REG'], 'Wahana': ['REG'],
            'DHL': ['EXPRESS', 'ECONOMY'], 'FedEx': ['PRIORITY', 'ECONOMY']
        };
        if (services[courier]) {
            services[courier].forEach(service => {
                const option = document.createElement('option');
                option.value = service;
                option.textContent = service + ' - ' + getServiceName(service);
                serviceSelect.appendChild(option);
            });
        }
    }

    function getServiceName(service) {
        const names = { 'REG': 'Regular', 'YES': 'YES', 'OKE': 'OKE', 'EZ': 'EZ', 'BEST': 'BEST', 'POS': 'Pos Reguler', 'KILAT': 'Pos Kilat', 'ONS': 'ONS', 'EXPRESS': 'Express', 'ECONOMY': 'Economy', 'PRIORITY': 'Priority' };
        return names[service] || service;
    }

    function addItemRow() {
        const container = document.getElementById('items-container');
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center';
        row.id = `item-row-${itemCount}`;
        row.innerHTML = `
            <div class="col-span-5"><input type="text" name="items[${itemCount}][name]" placeholder="Item name" required class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"></div>
            <div class="col-span-2"><input type="number" name="items[${itemCount}][quantity]" placeholder="Qty" min="1" value="1" required class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" onchange="updateTotals()"></div>
            <div class="col-span-2"><input type="number" name="items[${itemCount}][price]" placeholder="Price" min="0" step="0.01" required class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500" onchange="updateTotals()"></div>
            <div class="col-span-2"><input type="number" name="items[${itemCount}][weight]" placeholder="kg" min="0.1" step="0.1" value="1.0" class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"></div>
            <div class="col-span-1 flex justify-end"><button type="button" onclick="removeItemRow(${itemCount})" class="w-6 h-6 flex items-center justify-center rounded text-slate-500 hover:text-red-400 hover:bg-red-500/10 transition-colors" title="Remove"><i class="fas fa-times text-xs"></i></button></div>
        `;
        container.appendChild(row);
        itemCount++;
        updateTotals();
    }

    function removeItemRow(rowId) {
        const row = document.getElementById(`item-row-${rowId}`);
        if (row) { row.remove(); updateTotals(); showToast('Item removed', 'info'); }
    }

    function updateTotals() {
        let totalItems = 0, totalValue = 0;
        for (let i = 0; i < itemCount; i++) {
            const row = document.getElementById(`item-row-${i}`);
            if (row) {
                const qty = row.querySelector('input[name*="[quantity]"]')?.value || 0;
                const price = row.querySelector('input[name*="[price]"]')?.value || 0;
                totalItems += parseInt(qty) || 0;
                totalValue += (parseInt(qty) || 0) * (parseFloat(price) || 0);
            }
        }
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('totalValue').textContent = currencySymbol + ' ' + totalValue.toFixed(2);
    }

    function showAddressPreview() {
        const address = document.getElementById('address').value;
        const city = document.getElementById('city').value;
        const postal = document.getElementById('postalCode').value;
        const recipient = document.getElementById('recipientName').value;
        const phone = document.getElementById('phone').value;
        const preview = document.getElementById('previewContent');
        preview.innerHTML = `
            <p class="font-semibold mb-1.5 text-white"><i class="fas fa-user mr-2 text-amber-400"></i>${recipient || 'Recipient Name'}</p>
            <p class="text-slate-400 mb-1"><i class="fas fa-home mr-2"></i>${address || 'Street Address'}</p>
            <p class="text-slate-400 mb-1"><i class="fas fa-city mr-2"></i>${city || 'City'}${postal ? ', ' + postal : ''}</p>
            <p class="text-white mt-2"><i class="fas fa-phone-alt mr-2 text-amber-400"></i>${phone || 'Phone number'}</p>
        `;
        const modal = document.getElementById('addressPreviewModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAddressPreview() {
        const modal = document.getElementById('addressPreviewModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function useThisAddress() { closeAddressPreview(); showToast('Address confirmed', 'success'); }

    document.getElementById('shipmentForm').addEventListener('submit', function (e) {
        const items = document.querySelectorAll('input[name*="[name]"]');
        let hasItems = false;
        items.forEach(item => { if (item.value.trim() !== '') hasItems = true; });
        if (!hasItems) { e.preventDefault(); showToast('Please add at least one item to ship', 'error'); }
    });

    document.getElementById('courierSelect').addEventListener('change', function () {
        const delivery = document.getElementById('estimatedDelivery');
        const today = new Date();
        switch (this.value) {
            case 'DHL': case 'FedEx': today.setDate(today.getDate() + 2); break;
            case 'JNE': case 'J&T': case 'SiCepat': today.setDate(today.getDate() + 3); break;
            default: today.setDate(today.getDate() + 5);
        }
        delivery.value = today.toISOString().split('T')[0];
    });

    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input, textarea, select')) return;
        if (e.ctrlKey && e.key === 's') { e.preventDefault(); document.querySelector('button[type="submit"][value="create"]').click(); }
        if (e.ctrlKey && e.key === 'd') { e.preventDefault(); document.querySelector('button[type="submit"][value="draft"]').click(); }
        if (e.ctrlKey && e.key === 'p') { e.preventDefault(); showAddressPreview(); }
    });

    const addressField = document.getElementById('address');
    if (addressField) {
        const previewBtn = document.createElement('button');
        previewBtn.type = 'button';
        previewBtn.className = 'absolute right-2.5 top-1/2 -translate-y-1/2 text-amber-500 hover:text-amber-400 transition-colors';
        previewBtn.innerHTML = '<i class="fas fa-eye text-xs"></i>';
        previewBtn.onclick = showAddressPreview;
        const wrapper = addressField.parentElement;
        if (getComputedStyle(wrapper).position === 'static') wrapper.style.position = 'relative';
        wrapper.appendChild(previewBtn);
    }
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>
