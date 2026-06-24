<?php
/**
 * AJAX endpoint: Get enabled payment methods for current tenant
 * Returns JSON array of active payment gateways
 */

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/src/paths.php';

if (function_exists('safe_require')) {
    safe_require('auth.php', 'src');
    safe_require('db.php', 'src');
    safe_require('functions.php', 'src');
} else {
    require_once $root_path . '/src/auth.php';
    require_once $root_path . '/src/db.php';
    require_once $root_path . '/src/functions.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_login();

$tenant_id = get_current_tenant_id();
if (!$tenant_id) {
    echo json_encode(['success' => false, 'error' => 'Tenant required']);
    exit;
}

$settings = get_settings(null, null, $tenant_id);

$gateway_map = [
    'enable_cash' => ['id' => 'cash', 'label' => 'Cash', 'icon' => 'fa-money-bill-wave'],
    'enable_mpesa' => ['id' => 'mpesa', 'label' => 'M-Pesa', 'icon' => 'fa-mobile-alt'],
    'enable_card' => ['id' => 'card', 'label' => 'Card / Bank', 'icon' => 'fa-credit-card'],
    'enable_credit' => ['id' => 'credit', 'label' => 'Credit / Account', 'icon' => 'fa-hand-holding-dollar'],
    'enable_stripe' => ['id' => 'stripe', 'label' => 'Stripe', 'icon' => 'fa-cc-stripe'],
    'enable_paypal' => ['id' => 'paypal', 'label' => 'PayPal', 'icon' => 'fa-cc-paypal'],
    'enable_razorpay' => ['id' => 'razorpay', 'label' => 'Razorpay', 'icon' => 'fa-bolt'],
    'enable_paystack' => ['id' => 'paystack', 'label' => 'Paystack', 'icon' => 'fa-layer-group'],
    'enable_flutterwave' => ['id' => 'flutterwave', 'label' => 'Flutterwave', 'icon' => 'fa-wave-square'],
    'enable_square' => ['id' => 'square', 'label' => 'Square', 'icon' => 'fa-square'],
    'enable_braintree' => ['id' => 'braintree', 'label' => 'Braintree', 'icon' => 'fa-paypal'],
    'enable_authorize_net' => ['id' => 'authorize_net', 'label' => 'Authorize.Net', 'icon' => 'fa-shield-halved'],
    'enable_paytm' => ['id' => 'paytm', 'label' => 'Paytm', 'icon' => 'fa-wallet'],
    'enable_wechat_pay' => ['id' => 'wechat_pay', 'label' => 'WeChat Pay', 'icon' => 'fa-comment'],
    'enable_alipay' => ['id' => 'alipay', 'label' => 'Alipay', 'icon' => 'fa-a'],
    'enable_google_pay' => ['id' => 'google_pay', 'label' => 'Google Pay', 'icon' => 'fa-google'],
    'enable_apple_pay' => ['id' => 'apple_pay', 'label' => 'Apple Pay', 'icon' => 'fa-apple'],
    'enable_klarna' => ['id' => 'klarna', 'label' => 'Klarna', 'icon' => 'fa-clock'],
    'enable_afterpay' => ['id' => 'afterpay', 'label' => 'Afterpay', 'icon' => 'fa-calendar-days'],
    'enable_gocardless' => ['id' => 'gocardless', 'label' => 'GoCardless', 'icon' => 'fa-building-columns'],
    'enable_crypto' => ['id' => 'crypto', 'label' => 'Cryptocurrency', 'icon' => 'fa-bitcoin'],
    'enable_upi' => ['id' => 'upi', 'label' => 'UPI', 'icon' => 'fa-qrcode'],
    'enable_ideal' => ['id' => 'ideal', 'label' => 'iDEAL', 'icon' => 'fa-building'],
    'enable_bancontact' => ['id' => 'bancontact', 'label' => 'Bancontact', 'icon' => 'fa-credit-card'],
    'enable_giropay' => ['id' => 'giropay', 'label' => 'Giropay', 'icon' => 'fa-university'],
    'enable_sofort' => ['id' => 'sofort', 'label' => 'SOFORT', 'icon' => 'fa-money-bill-transfer'],
    'enable_eps' => ['id' => 'eps', 'label' => 'EPS', 'icon' => 'fa-euro-sign'],
    'enable_przelewy24' => ['id' => 'przelewy24', 'label' => 'Przelewy24', 'icon' => 'fa-24'],
    'enable_trustly' => ['id' => 'trustly', 'label' => 'Trustly', 'icon' => 'fa-handshake'],
    'enable_revolut' => ['id' => 'revolut', 'label' => 'Revolut', 'icon' => 'fa-revolut'],
    'enable_wise' => ['id' => 'wise', 'label' => 'Wise', 'icon' => 'fa-paper-plane'],
    'enable_worldpay' => ['id' => 'worldpay', 'label' => 'Worldpay', 'icon' => 'fa-globe'],
    'enable_sagepay' => ['id' => 'sagepay', 'label' => 'Sage Pay', 'icon' => 'fa-credit-card'],
    'enable_2checkout' => ['id' => '2checkout', 'label' => '2Checkout', 'icon' => 'fa-2'],
    'enable_payfast' => ['id' => 'payfast', 'label' => 'Payfast', 'icon' => 'fa-bolt'],
    'enable_sezzle' => ['id' => 'sezzle', 'label' => 'Sezzle', 'icon' => 'fa-calendar-check'],
    'enable_affirm' => ['id' => 'affirm', 'label' => 'Affirm', 'icon' => 'fa-check-double'],
    'enable_venmo' => ['id' => 'venmo', 'label' => 'Venmo', 'icon' => 'fa-v'],
    'enable_cash_app' => ['id' => 'cash_app', 'label' => 'Cash App', 'icon' => 'fa-money-bill'],
];

$methods = [];
foreach ($gateway_map as $setting_key => $meta) {
    if (($settings[$setting_key] ?? '0') === '1') {
        $methods[] = $meta;
    }
}

// Always ensure at least cash is available
if (empty($methods)) {
    $methods[] = $gateway_map['enable_cash'];
}

echo json_encode(['success' => true, 'methods' => $methods]);
exit;
