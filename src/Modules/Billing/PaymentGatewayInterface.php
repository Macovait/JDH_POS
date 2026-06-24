<?php
/**
 * Payment Gateway Interface
 * 
 * Abstract interface for payment gateways
 * Supports: Stripe, PayPal, MPesa, Bank Transfer
 * 
 * @package JDH_POS
 * @version 2.0.0
 */

namespace JDH_POS\Billing;

interface PaymentGatewayInterface {
    
    /**
     * Initialize payment with amount and details
     * @param float $amount Amount to charge
     * @param string $currency Currency code (KES, USD, etc.)
     * @param array $metadata Additional metadata
     * @return array Payment response with status and references
     */
    public function initializePayment(float $amount, string $currency, array $metadata = []): array;
    
    /**
     * Process payment callback/webhook
     * @param array $data Webhook callback data
     * @return array Processed payment data
     */
    public function processCallback(array $data): array;
    
    /**
     * Check payment status
     * @param string $paymentReference Payment ID or reference
     * @return array Status details
     */
    public function checkStatus(string $paymentReference): array;
    
    /**
     * Refund payment
     * @param string $paymentReference Original payment ID
     * @param float $amount Amount to refund (null = full refund)
     * @param string $reason Refund reason
     * @return array Refund response
     */
    public function refund(string $paymentReference, ?float $amount = null, string $reason = ''): array;
    
    /**
     * Create customer in gateway
     * @param string $email Customer email
     * @param string $name Customer name
     * @param array $data Additional customer data
     * @return array Customer reference
     */
    public function createCustomer(string $email, string $name, array $data = []): array;
    
    /**
     * Get payment methods for customer
     * @param string $customerReference Customer ID
     * @return array Available payment methods
     */
    public function getPaymentMethods(string $customerReference): array;
    
    /**
     * Verify webhook signature
     * @param string $payload Raw webhook payload
     * @param string $signature Webhook signature header
     * @return bool True if valid
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;
}

/**
 * Payment Gateway Factory
 */
class PaymentGatewayFactory {
    
    const STRIPE = 'stripe';
    const PAYPAL = 'paypal';
    const MPESA = 'mpesa';
    const BANK_TRANSFER = 'bank_transfer';
    const CASH = 'cash';
    const RAZORPAY = 'razorpay';
    const PAYSTACK = 'paystack';
    const FLUTTERWAVE = 'flutterwave';
    const SQUARE = 'square';
    const BRAINTREE = 'braintree';
    const AUTHORIZE_NET = 'authorize_net';
    const PAYTM = 'paytm';
    const WECHAT_PAY = 'wechat_pay';
    const ALIPAY = 'alipay';
    const GOOGLE_PAY = 'google_pay';
    const APPLE_PAY = 'apple_pay';
    const KLARNA = 'klarna';
    const AFTERPAY = 'afterpay';
    const CLEARPAY = 'clearpay';
    const GO_CARDLESS = 'gocardless';
    const CRYPTO = 'crypto';
    const UPI = 'upi';
    const IDEAL = 'ideal';
    const BANCONTACT = 'bancontact';
    const GIROPAY = 'giropay';
    const SOFORT = 'sofort';
    const EPS = 'eps';
    const PRZELEWY24 = 'przelewy24';
    const TRUSTLY = 'trustly';
    const REVOLUT = 'revolut';
    const WISE = 'wise';
    const WORLDPAY = 'worldpay';
    const SAGEPAY = 'sagepay';
    const TWOCHECKOUT = '2checkout';
    const PAYFAST = 'payfast';
    const SEZZLE = 'sezzle';
    const AFFIRM = 'affirm';
    const VENMO = 'venmo';
    const CASH_APP = 'cash_app';
    
    /**
     * Create gateway instance
     * @param string $gateway Gateway type
     * @param array $config Gateway configuration
     * @return PaymentGatewayInterface
     */
    public static function create(string $gateway, array $config = []): PaymentGatewayInterface {
        switch ($gateway) {
            case self::STRIPE:
                return new StripeGateway($config);
            case self::PAYPAL:
                return new PayPalGateway($config);
            case self::MPESA:
                return new \JDH\Modules\Payments\Gateways\MpesaGateway($config);
            case self::BANK_TRANSFER:
                return new BankTransferGateway($config);
            case self::CASH:
                return new CashGateway($config);
            case self::RAZORPAY:
                return new RazorpayGateway($config);
            case self::PAYSTACK:
                return new PaystackGateway($config);
            case self::FLUTTERWAVE:
                return new FlutterwaveGateway($config);
            case self::SQUARE:
                return new SquareGateway($config);
            case self::BRAINTREE:
                return new BraintreeGateway($config);
            case self::AUTHORIZE_NET:
                return new AuthorizeNetGateway($config);
            case self::PAYTM:
                return new PaytmGateway($config);
            case self::WECHAT_PAY:
                return new WeChatPayGateway($config);
            case self::ALIPAY:
                return new AlipayGateway($config);
            case self::GOOGLE_PAY:
                return new GooglePayGateway($config);
            case self::APPLE_PAY:
                return new ApplePayGateway($config);
            case self::KLARNA:
                return new KlarnaGateway($config);
            case self::AFTERPAY:
                return new AfterpayGateway($config);
            case self::CLEARPAY:
                return new ClearpayGateway($config);
            case self::GO_CARDLESS:
                return new GoCardlessGateway($config);
            case self::CRYPTO:
                return new CryptoGateway($config);
            case self::UPI:
                return new UpiGateway($config);
            case self::IDEAL:
                return new IdealGateway($config);
            case self::BANCONTACT:
                return new BancontactGateway($config);
            case self::GIROPAY:
                return new GiropayGateway($config);
            case self::SOFORT:
                return new SofortGateway($config);
            case self::EPS:
                return new EpsGateway($config);
            case self::PRZELEWY24:
                return new Przelewy24Gateway($config);
            case self::TRUSTLY:
                return new TrustlyGateway($config);
            case self::REVOLUT:
                return new RevolutGateway($config);
            case self::WISE:
                return new WiseGateway($config);
            case self::WORLDPAY:
                return new WorldpayGateway($config);
            case self::SAGEPAY:
                return new SagepayGateway($config);
            case self::TWOCHECKOUT:
                return new TwoCheckoutGateway($config);
            case self::PAYFAST:
                return new PayfastGateway($config);
            case self::SEZZLE:
                return new SezzleGateway($config);
            case self::AFFIRM:
                return new AffirmGateway($config);
            case self::VENMO:
                return new VenmoGateway($config);
            case self::CASH_APP:
                return new CashAppGateway($config);
            default:
                throw new \InvalidArgumentException("Unknown gateway: $gateway");
        }
    }

    /**
     * Get all supported gateways
     * @return array Gateway list
     */
    public static function getSupportedGateways(): array {
        return [
            self::STRIPE => 'Stripe Card/Checkout',
            self::PAYPAL => 'PayPal',
            self::MPESA => 'M-Pesa',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CASH => 'Cash',
            self::RAZORPAY => 'Razorpay (India)',
            self::PAYSTACK => 'Paystack (Africa)',
            self::FLUTTERWAVE => 'Flutterwave (Africa/Global)',
            self::SQUARE => 'Square',
            self::BRAINTREE => 'Braintree (PayPal)',
            self::AUTHORIZE_NET => 'Authorize.Net',
            self::PAYTM => 'Paytm (India)',
            self::WECHAT_PAY => 'WeChat Pay',
            self::ALIPAY => 'Alipay',
            self::GOOGLE_PAY => 'Google Pay',
            self::APPLE_PAY => 'Apple Pay',
            self::KLARNA => 'Klarna (BNPL)',
            self::AFTERPAY => 'Afterpay (BNPL)',
            self::CLEARPAY => 'Clearpay (UK BNPL)',
            self::GO_CARDLESS => 'GoCardless (Direct Debit)',
            self::CRYPTO => 'Cryptocurrency (BTC/ETH)',
            self::UPI => 'UPI (India)',
            self::IDEAL => 'iDEAL (Netherlands)',
            self::BANCONTACT => 'Bancontact (Belgium)',
            self::GIROPAY => 'Giropay (Germany)',
            self::SOFORT => 'SOFORT (Germany/Austria)',
            self::EPS => 'EPS (Austria)',
            self::PRZELEWY24 => 'Przelewy24 (Poland)',
            self::TRUSTLY => 'Trustly (Europe)',
            self::REVOLUT => 'Revolut',
            self::WISE => 'Wise (Transfer)',
            self::WORLDPAY => 'Worldpay',
            self::SAGEPAY => 'Sage Pay / Opayo',
            self::TWOCHECKOUT => '2Checkout / Verifone',
            self::PAYFAST => 'Payfast (South Africa)',
            self::SEZZLE => 'Sezzle (BNPL)',
            self::AFFIRM => 'Affirm (BNPL)',
            self::VENMO => 'Venmo',
            self::CASH_APP => 'Cash App Pay',
        ];
    }
}

/**
 * Base abstract gateway
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface {
    
    protected array $config;
    protected string $tenantId;
    
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->tenantId = $config['tenant_id'] ?? ($_SESSION['tenant_id'] ?? 0);
    }

    /**
     * Resolve a config value using fallback chain:
     * 1. Direct config array
     * 2. Tenant settings from database
     * 3. Environment variable
     * @param string $configKey   Key in config array / settings table
     * @param string $envVar      Environment variable name
     * @param mixed  $default     Default value if nothing found
     * @return mixed
     */
    protected function config(string $configKey, string $envVar = '', $default = null) {
        // 1. Direct config
        if (isset($this->config[$configKey]) && $this->config[$configKey] !== '') {
            return $this->config[$configKey];
        }
        // 2. Tenant settings from DB
        if ((int) $this->tenantId > 0 && function_exists('get_tenant_setting')) {
            $setting = get_tenant_setting((int) $this->tenantId, $configKey, '');
            if ($setting !== '') {
                return $setting;
            }
        }
        // 3. Environment variable
        if ($envVar !== '') {
            $env = getenv($envVar);
            if ($env !== false && $env !== '') {
                return $env;
            }
        }
        return $default;
    }

    /**
     * Log payment event
     */
    protected function log(string $action, array $data): void {
        $db = get_db_connection();
        
        try {
            $db->prepare("
                INSERT INTO payment_logs (tenant_id, gateway, action, reference, amount, status, response, created_at)
                VALUES (:tenant_id, :gateway, :action, :reference, :amount, :status, :response, NOW())
            ")->execute([
                ':tenant_id' => $this->tenantId,
                ':gateway' => static::class,
                ':action' => $action,
                ':reference' => $data['reference'] ?? null,
                ':amount' => $data['amount'] ?? 0,
                ':status' => $data['status'] ?? 'pending',
                ':response' => json_encode($data)
            ]);
        } catch (\Exception $e) {
            error_log("Payment log error: " . $e->getMessage());
        }
    }
    
    /**
     * Format amount for gateway (cents/paisa)
     */
    protected function formatAmount(float $amount, string $currency): int {
        // Most gateways use smallest currency unit
        $decimalPlaces = ['KES' => 0, 'USD' => 2, 'EUR' => 2, 'TZS' => 0];
        $decimals = $decimalPlaces[$currency] ?? 2;
        
        return (int) round($amount * pow(10, $decimals));
    }
    
    /**
     * Unformat amount from gateway
     */
    protected function unformatAmount(int $amount, string $currency): float {
        $decimalPlaces = ['KES' => 0, 'USD' => 2, 'EUR' => 2, 'TZS' => 0];
        $decimals = $decimalPlaces[$currency] ?? 2;
        
        return $amount / pow(10, $decimals);
    }
}