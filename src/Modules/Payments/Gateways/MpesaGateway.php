<?php
/**
 * M-Pesa Payment Gateway Integration
 * Supports: STK Push, C2B, B2C, Transaction Status Query
 */

namespace JDH\Modules\Payments\Gateways;

use Exception;
use PDO;

class MpesaGateway 
{
    private $config;
    private $db;
    private $isSandbox;
    
    // M-Pesa API Endpoints
    private const SANDBOX_BASE_URL = 'https://sandbox.safaricom.co.ke';
    private const PRODUCTION_BASE_URL = 'https://api.safaricom.co.ke';
    
    public function __construct(array $config, PDO $db)
    {
        $this->config = $config;
        $this->db = $db;
        $this->isSandbox = $config['test_mode'] ?? true;
    }
    
    /**
     * Get base URL based on environment
     */
    private function getBaseUrl(): string
    {
        return $this->isSandbox ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }
    
    /**
     * Get OAuth access token
     */
    private function getAccessToken(): string
    {
        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        
        $credentials = base64_encode(
            $this->config['consumer_key'] . ':' . $this->config['consumer_secret']
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isSandbox);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new Exception('Failed to get M-Pesa access token');
        }
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? throw new Exception('Invalid token response');
    }
    
    /**
     * Initiate STK Push (Lipa na M-Pesa Online)
     */
    public function stkPush(array $params): array
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $url = $this->getBaseUrl() . '/mpesa/stkpush/v1/processrequest';
            
            $timestamp = date('YmdHis');
            $password = base64_encode(
                $this->config['shortcode'] . $this->config['passkey'] . $timestamp
            );
            
            $callbackUrl = $this->config['callback_url'] ?? 
                'https://' . $_SERVER['HTTP_HOST'] . '/JDH_POS/public/api/webhooks/mpesa';
            
            $payload = [
                'BusinessShortCode' => $this->config['shortcode'],
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $params['amount'],
                'PartyA' => $this->formatPhoneNumber($params['phone']),
                'PartyB' => $this->config['shortcode'],
                'PhoneNumber' => $this->formatPhoneNumber($params['phone']),
                'CallBackURL' => $callbackUrl,
                'AccountReference' => $params['account_reference'] ?? 'JDH-POS',
                'TransactionDesc' => $params['description'] ?? 'Payment'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isSandbox);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($httpCode === 200 && isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                return [
                    'success' => true,
                    'checkout_request_id' => $result['CheckoutRequestID'],
                    'merchant_request_id' => $result['MerchantRequestID'],
                    'customer_message' => $result['CustomerMessage'] ?? 'Enter M-Pesa PIN to complete'
                ];
            }
            
            return [
                'success' => false,
                'error' => $result['errorMessage'] ?? $result['ResponseDescription'] ?? 'STK Push failed',
                'raw_response' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Query STK Push transaction status
     */
    public function queryStkStatus(string $checkoutRequestId): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = $this->getBaseUrl() . '/mpesa/stkpushquery/v1/query';
            
            $timestamp = date('YmdHis');
            $password = base64_encode(
                $this->config['shortcode'] . $this->config['passkey'] . $timestamp
            );
            
            $payload = [
                'BusinessShortCode' => $this->config['shortcode'],
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isSandbox);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            // ResultCode 0 = Success, 1032 = Cancelled by user
            if (isset($result['ResultCode'])) {
                return [
                    'success' => $result['ResultCode'] === '0',
                    'result_code' => $result['ResultCode'],
                    'result_desc' => $result['ResultDesc'],
                    'mpesa_receipt' => $result['MpesaReceiptNumber'] ?? null,
                    'transaction_date' => $result['TransactionDate'] ?? null,
                    'phone' => $result['PhoneNumber'] ?? null,
                    'amount' => $result['Amount'] ?? null
                ];
            }
            
            return [
                'success' => false,
                'error' => $result['errorMessage'] ?? 'Status query failed',
                'raw_response' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Register C2B URLs (for paybill/till number payments)
     */
    public function registerC2BUrls(): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = $this->getBaseUrl() . '/mpesa/c2b/v1/registerurl';
            
            $baseCallback = $this->config['callback_url'] ?? 
                'https://' . $_SERVER['HTTP_HOST'] . '/JDH_POS/public/api/webhooks/mpesa';
            
            $payload = [
                'ShortCode' => $this->config['shortcode'],
                'ResponseType' => 'Completed',
                'ConfirmationURL' => $baseCallback . '/c2b/confirm',
                'ValidationURL' => $baseCallback . '/c2b/validate'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isSandbox);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            return [
                'success' => isset($result['ResponseDescription']),
                'message' => $result['ResponseDescription'] ?? 'Registration failed',
                'raw_response' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process C2B payment callback
     */
    public function processC2BCallback(array $data): array
    {
        // Validate required fields
        $required = ['TransactionType', 'TransID', 'TransAmount', 'BusinessShortCode', 
                     'BillRefNumber', 'MSISDN', 'FirstName'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }
        
        return [
            'success' => true,
            'transaction_id' => $data['TransID'],
            'amount' => (float) $data['TransAmount'],
            'phone' => $data['MSISDN'],
            'account_reference' => $data['BillRefNumber'],
            'timestamp' => $data['TransTime'] ?? date('YmdHis'),
            'name' => $data['FirstName'] . ' ' . ($data['MiddleName'] ?? '') . ' ' . ($data['LastName'] ?? '')
        ];
    }
    
    /**
     * Format phone number to 254XXXXXXXXX format
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading 0 and add 254
        if (strpos($phone, '0') === 0) {
            $phone = '254' . substr($phone, 1);
        }
        
        // If starts with 7, add 254
        if (strpos($phone, '7') === 0) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Reverse a transaction (refund)
     */
    public function reverseTransaction(string $transactionId, float $amount, string $receiverPhone): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = $this->getBaseUrl() . '/mpesa/reversal/v1/request';
            
            $payload = [
                'Initiator' => $this->config['initiator_name'] ?? 'testapi',
                'SecurityCredential' => $this->config['security_credential'] ?? '',
                'CommandID' => 'TransactionReversal',
                'TransactionID' => $transactionId,
                'Amount' => (int) $amount,
                'ReceiverParty' => $this->formatPhoneNumber($receiverPhone),
                'RecieverIdentifierType' => '1', // MSISDN
                'ResultURL' => $this->config['callback_url'] . '/reversal',
                'QueueTimeOutURL' => $this->config['callback_url'] . '/reversal/timeout',
                'Remarks' => 'Transaction reversal',
                'Occasion' => 'Refund'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isSandbox);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            return [
                'success' => isset($result['ResponseCode']) && $result['ResponseCode'] === '0',
                'conversation_id' => $result['ConversationID'] ?? null,
                'message' => $result['ResponseDescription'] ?? 'Reversal initiated'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
