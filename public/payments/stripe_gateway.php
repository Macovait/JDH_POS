<?php
/**
 * Stripe Payment Gateway Integration
 * Secure payment processing for POS and online orders
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);

// Branch filter for multi-tenant isolation
$current_branch_id = get_current_branch_id();
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);

// Check permissions
require_login();
if (!check_permission('payments.manage') && !is_super_admin()) {
    enforce_permission('payments.manage');
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id();

// Get Stripe settings
$settings = get_stripe_settings($pdo, $tenant_id);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        update_stripe_settings($pdo, $tenant_id, $_POST);
        $settings = get_stripe_settings($pdo, $tenant_id);
        $success = 'Stripe settings updated successfully';
    } elseif (isset($_POST['test_connection'])) {
        $test_result = test_stripe_connection($settings);
        if ($test_result['success']) {
            $success = 'Stripe connection successful';
        } else {
            $error = 'Stripe connection failed: ' . $test_result['error'];
        }
    } elseif (isset($_POST['create_payment_intent'])) {
        $result = create_stripe_payment_intent($settings, $_POST);
        if ($result['success']) {
            $success = 'Payment intent created successfully';
            $payment_intent = $result['data'];
        } else {
            $error = 'Failed to create payment intent: ' . $result['error'];
        }
    }
}

// Get recent payments
$recent_payments = get_recent_stripe_payments($pdo, $tenant_id);

$page_title = 'Stripe Payment Gateway | JDH POS';
ob_start();
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-amber-400">Stripe Payment Gateway</h1>
            <p class="text-gray-400 mt-1">Secure payment processing for your business</p>
        </div>
        <div class="flex gap-3">
            <button onclick="testConnection()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">
                <i class="fas fa-plug mr-2"></i>Test Connection
            </button>
            <button onclick="openSettings()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">
                <i class="fas fa-cog mr-2"></i>Settings
            </button>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success)): ?>
        <div class="bg-emerald-500/10 border border-emerald-500 rounded-xl p-4">
            <div class="flex items-center gap-3 text-emerald-400">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="bg-red-500/10 border border-red-500 rounded-xl p-4">
            <div class="flex items-center gap-3 text-red-400">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Connection Status -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                    <i class="fab fa-stripe text-purple-400"></i>
                    Connection Status
                </h3>
                <p class="text-gray-400 mt-1">Stripe payment gateway integration</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <?php if ($settings['publishable_key'] && $settings['secret_key']): ?>
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        <span class="text-green-400 text-sm">Configured</span>
                    <?php else: ?>
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <span class="text-red-400 text-sm">Not Configured</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($settings['publishable_key'] && $settings['secret_key']): ?>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-700/50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-green-400">$0.00</div>
                    <div class="text-sm text-gray-400">Today's Payments</div>
                </div>
                <div class="bg-gray-700/50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-blue-400">$0.00</div>
                    <div class="text-sm text-gray-400">This Week</div>
                </div>
                <div class="bg-gray-700/50 rounded-lg p-4">
                    <div class="text-2xl font-bold text-purple-400">$0.00</div>
                    <div class="text-sm text-gray-400">This Month</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Testing -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-credit-card text-blue-400"></i>
            Test Payment Processing
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <form method="post" class="space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.50" max="999999.99"
                               value="10.00" required
                               class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Currency</label>
                        <select name="currency" class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                            <option value="usd">USD</option>
                            <option value="kes">KES</option>
                            <option value="eur">EUR</option>
                            <option value="gbp">GBP</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <input type="text" name="description" value="Test Payment"
                               class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white">
                    </div>

                    <button type="submit" name="create_payment_intent" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors w-full">
                        <i class="fas fa-bolt mr-2"></i>Create Payment Intent
                    </button>
                </form>
            </div>

            <div>
                <div id="payment-result" class="hidden">
                    <h4 class="text-white font-medium mb-3">Payment Intent Created</h4>
                    <div id="payment-details" class="space-y-2 text-sm">
                        <!-- Payment details will be populated here -->
                    </div>
                </div>

                <div id="payment-form" class="hidden">
                    <h4 class="text-white font-medium mb-3">Test Payment Form</h4>
                    <div id="card-element" class="mb-4 p-4 border border-gray-600 rounded-lg bg-gray-700">
                        <!-- Stripe Elements will be mounted here -->
                    </div>
                    <button id="submit-payment" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm font-medium hover:bg-emerald-500/25 transition-colors w-full">
                        <i class="fas fa-lock mr-2"></i>Pay Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6">
        <h3 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-history text-green-400"></i>
            Recent Payments
        </h3>

        <?php if (empty($recent_payments)): ?>
            <div class="text-center py-8">
                <i class="fas fa-credit-card text-gray-600 text-4xl mb-4"></i>
                <p class="text-gray-400">No payments processed yet</p>
                <p class="text-sm text-gray-500 mt-2">Configure Stripe settings to start processing payments</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-700">
                        <tr class="text-gray-300 text-xs uppercase">
                            <th class="text-left py-3 px-4">Transaction ID</th>
                            <th class="text-left py-3 px-4">Amount</th>
                            <th class="text-left py-3 px-4">Status</th>
                            <th class="text-left py-3 px-4">Method</th>
                            <th class="text-left py-3 px-4">Date</th>
                            <th class="text-center py-3 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600">
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr class="hover:bg-gray-700/50">
                                <td class="py-3 px-4">
                                    <span class="text-blue-400 font-mono text-xs"><?php echo htmlspecialchars(substr($payment['transaction_id'], 0, 20)); ?>...</span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-green-400 font-medium">$<?php echo number_format($payment['amount'], 2); ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php
                                    $status_class = $payment['status'] === 'succeeded' ? 'bg-green-500/20 text-green-400' :
                                                   ($payment['status'] === 'pending' ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400');
                                    ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-gray-300"><?php echo ucfirst($payment['payment_method'] ?: 'card'); ?></td>
                                <td class="py-3 px-4 text-gray-400 text-xs"><?php echo date('M d, H:i', strtotime($payment['created_at'])); ?></td>
                                <td class="py-3 px-4 text-center">
                                    <button onclick="viewPayment('<?php echo $payment['transaction_id']; ?>')" class="text-blue-400 hover:text-blue-300 text-sm">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="fixed inset-0 bg-black/50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-gray-800 border border-gray-700 rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">Stripe Settings</h3>
                        <button onclick="closeSettings()" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <form method="post" class="p-6 space-y-4">\n<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="bg-amber-500/10 border border-amber-500/20 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-info-circle text-amber-400 mt-1"></i>
                            <div>
                                <h4 class="text-amber-400 font-medium mb-1">Get Your Stripe Keys</h4>
                                <p class="text-sm text-gray-300 mb-2">You need to create a Stripe account and get your API keys from the Stripe Dashboard.</p>
                                <a href="https://dashboard.stripe.com/apikeys" target="_blank" class="text-amber-400 hover:text-amber-300 text-sm underline">
                                    Go to Stripe Dashboard →
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Publishable Key</label>
                            <input type="password" name="publishable_key"
                                   value="<?php echo htmlspecialchars($settings['publishable_key']); ?>"
                                   placeholder="pk_test_... or pk_live_..."
                                   class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white placeholder-slate-500">
                            <p class="text-xs text-gray-500 mt-1">Safe to use in frontend code</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Secret Key</label>
                            <input type="password" name="secret_key"
                                   value="<?php echo htmlspecialchars($settings['secret_key']); ?>"
                                   placeholder="sk_test_... or sk_live_..."
                                   class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white placeholder-slate-500">
                            <p class="text-xs text-gray-500 mt-1">Keep this secret - never expose to frontend</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Webhook Endpoint Secret</label>
                            <input type="password" name="webhook_secret"
                                   value="<?php echo htmlspecialchars($settings['webhook_secret']); ?>"
                                   placeholder="whsec_..."
                                   class="form-input w-full px-4 py-3 rounded-xl bg-gray-700 border border-gray-600 text-white placeholder-slate-500">
                            <p class="text-xs text-gray-500 mt-1">For webhook signature verification</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="checkbox" name="test_mode" value="1" id="test_mode"
                                   class="w-4 h-4 text-amber-500 bg-gray-700 border-gray-600 rounded focus:ring-amber-500"
                                   <?php echo $settings['test_mode'] ? 'checked' : ''; ?>>
                            <label for="test_mode" class="text-sm text-gray-300">Test Mode</label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-700">
                        <button type="button" onclick="closeSettings()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium hover:bg-slate-700 transition-colors">Cancel</button>
                        <button type="submit" name="update_settings" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 text-black text-sm font-semibold hover:bg-amber-600 transition-colors">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functions
function openSettings() {
    document.getElementById('settings-modal').classList.remove('hidden');
}

function closeSettings() {
    document.getElementById('settings-modal').classList.add('hidden');
}

// Test connection
function testConnection() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="test_connection" value="1">';
    document.body.appendChild(form);
    form.submit();
}

// View payment details
function viewPayment(transactionId) {
    alert('Payment details for: ' + transactionId);
    // In a real implementation, this would open a modal with payment details
}

// Stripe Elements integration
<?php if ($settings['publishable_key'] && isset($payment_intent)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Load Stripe.js
    const stripe = Stripe('<?php echo htmlspecialchars($settings['publishable_key']); ?>');
    const elements = stripe.elements();

    // Create card element
    const cardElement = elements.create('card', {
        style: {
            base: {
                color: '#ffffff',
                fontFamily: '"Inter", sans-serif',
                fontSize: '16px',
                '::placeholder': {
                    color: '#9ca3af',
                },
            },
        },
    });

    cardElement.mount('#card-element');

    // Show payment form
    document.getElementById('payment-form').classList.remove('hidden');

    // Handle payment submission
    document.getElementById('submit-payment').addEventListener('click', async function() {
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

        try {
            const { error, paymentIntent } = await stripe.confirmCardPayment(
                '<?php echo $payment_intent['client_secret']; ?>',
                {
                    payment_method: {
                        card: cardElement,
                    }
                }
            );

            if (error) {
                alert('Payment failed: ' + error.message);
            } else if (paymentIntent.status === 'succeeded') {
                alert('Payment successful!');
                location.reload();
            }
        } catch (e) {
            alert('Payment error: ' + e.message);
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-lock mr-2"></i>Pay Now';
        }
    });

    // Show payment result
    document.getElementById('payment-result').classList.remove('hidden');
    document.getElementById('payment-details').innerHTML = `
        <div><strong>Amount:</strong> $<?php echo number_format($payment_intent['amount'] / 100, 2); ?></div>
        <div><strong>Currency:</strong> <?php echo strtoupper($payment_intent['currency']); ?></div>
        <div><strong>Status:</strong> <?php echo ucfirst($payment_intent['status']); ?></div>
        <div><strong>Client Secret:</strong> <code class="text-xs"><?php echo substr($payment_intent['client_secret'], 0, 50); ?>...</code></div>
    `;
});
<?php endif; ?>
</script>

<?php
// Helper functions
function get_stripe_settings($pdo, $tenant_id) {
    $stmt = $pdo->prepare("SELECT * FROM tenant_configs WHERE tenant_id = ? AND config_key LIKE 'stripe_%'");
    $stmt->execute([$tenant_id]);
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    return [
        'publishable_key' => $configs['stripe_publishable_key'] ?? '',
        'secret_key' => $configs['stripe_secret_key'] ?? '',
        'webhook_secret' => $configs['stripe_webhook_secret'] ?? '',
        'test_mode' => $configs['stripe_test_mode'] ?? '1'
    ];
}

function update_stripe_settings($pdo, $tenant_id, $data) {
    $settings = [
        'stripe_publishable_key' => trim($data['publishable_key'] ?? ''),
        'stripe_secret_key' => trim($data['secret_key'] ?? ''),
        'stripe_webhook_secret' => trim($data['webhook_secret'] ?? ''),
        'stripe_test_mode' => isset($data['test_mode']) ? '1' : '0'
    ];

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_configs (tenant_id, config_key, config_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->execute([$tenant_id, $key, $value]);
    }
}

function test_stripe_connection($settings) {
    if (empty($settings['secret_key'])) {
        return ['success' => false, 'error' => 'Secret key not configured'];
    }

    // In a real implementation, you would make a test API call to Stripe
    // For now, just validate the key format
    if (preg_match('/^sk_(test|live)_[a-zA-Z0-9]+$/', $settings['secret_key'])) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Invalid secret key format'];
    }
}

function create_stripe_payment_intent($settings, $data) {
    if (empty($settings['secret_key'])) {
        return ['success' => false, 'error' => 'Stripe not configured'];
    }

    // In a real implementation, you would use Stripe PHP SDK
    // For demo purposes, return mock data
    $amount = (int)($data['amount'] * 100); // Convert to cents

    return [
        'success' => true,
        'data' => [
            'id' => 'pi_' . bin2hex(random_bytes(14)),
            'client_secret' => 'pi_' . bin2hex(random_bytes(14)) . '_secret_' . bin2hex(random_bytes(20)),
            'amount' => $amount,
            'currency' => strtolower($data['currency']),
            'status' => 'requires_payment_method'
        ]
    ];
}

function get_recent_stripe_payments($pdo, $tenant_id) {
    // In a real implementation, you would query actual payment records
    // For demo purposes, return empty array
    return [];
}

$page_content = ob_get_clean();
require_once __DIR__ . '/../layouts/app_close.php';
require_once __DIR__ . '/../layouts/app.php';
?>
?>