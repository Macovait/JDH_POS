<?php
declare(strict_types=1);

/**
 * KRA eTIMS API Client
 *
 * Low-level HTTP client for the Kenya Revenue Authority Electronic Tax Invoice
 * Management System. Handles authentication, request signing, and API transport.
 *
 * Endpoints:
 *   - Device initialization & info
 *   - Standard tax invoice submission (TrnsSalesSaveWrReq)
 *   - Credit note submission (TrnsSalesSaveCreditNote)
 *   - Item classification lookup
 *   - Stock movement reporting
 *
 * @see https://www.kra.go.ke/etims
 * @package Jakababa\Services\Integration\KRA
 * @version 1.0.0
 */

namespace Jakababa\Services\Integration\KRA;

class EtimsClient
{
    private string $baseUrl;
    private string $tin;
    private string $branchId;
    private string $deviceSerialNo;
    private string $cmcKey;
    private int $timeout;
    private bool $verifySsl;

    public function __construct(array $config)
    {
        $env = $config['environment'] ?? 'sandbox';
        $this->baseUrl = $env === 'production'
            ? 'https://etims-api.kra.go.ke/etims-api'
            : 'https://etims-api-sbx.kra.go.ke/etims-api';

        $this->tin = $config['tin'] ?? '';
        $this->branchId = $config['branch_id'] ?? '00';
        $this->deviceSerialNo = $config['device_serial_no'] ?? '';
        $this->cmcKey = $config['cmc_key'] ?? '';
        $this->timeout = (int)($config['timeout'] ?? 30);
        $this->verifySsl = (bool)($config['verify_ssl'] ?? ($env === 'production'));
    }

    // ── Device Management ──

    /**
     * Initialize the device with KRA. Call once when setting up eTIMS.
     */
    public function initializeDevice(string $dvcSrlNo): array
    {
        return $this->post('/selectInitInfo', [
            'tin' => $this->tin,
            'bhfId' => $this->branchId,
            'dvcSrlNo' => $dvcSrlNo,
        ]);
    }

    /**
     * Get branch and device information from KRA.
     */
    public function getCodeList(string $lastReqDt = '20200101000000'): array
    {
        return $this->post('/selectCodeList', [
            'tin' => $this->tin,
            'bhfId' => $this->branchId,
            'lastReqDt' => $lastReqDt,
        ]);
    }

    // ── Tax Invoice Operations ──

    /**
     * Submit a standard tax invoice (sale) to eTIMS.
     *
     * @param array $invoiceData Invoice header fields
     * @param array $items Invoice line items
     * @return array KRA response with intrlData (Internal Data), rcptSign (Receipt Signature), etc.
     */
    public function submitInvoice(array $invoiceData, array $items): array
    {
        $payload = array_merge([
            'tin' => $this->tin,
            'bhfId' => $this->branchId,
        ], $invoiceData);

        $payload['itemList'] = $items;

        return $this->post('/trnsSalesSaveWrReq', $payload);
    }

    /**
     * Submit a credit note (return/void) to eTIMS.
     *
     * @param array $creditNoteData Credit note header (must reference original invoice)
     * @param array $items Items being returned
     * @return array KRA response
     */
    public function submitCreditNote(array $creditNoteData, array $items): array
    {
        $payload = array_merge([
            'tin' => $this->tin,
            'bhfId' => $this->branchId,
        ], $creditNoteData);

        $payload['itemList'] = $items;

        return $this->post('/trnsSalesSaveCreditNote', $payload);
    }

    // ── Item Classification ──

    /**
     * Fetch item classification codes from KRA.
     */
    public function getItemClassification(string $lastReqDt = '20200101000000'): array
    {
        return $this->post('/selectItemClsList', [
            'tin' => $this->tin,
            'bhfId' => $this->branchId,
            'lastReqDt' => $lastReqDt,
        ]);
    }

    /**
     * Register/save item to KRA.
     */
    public function saveItem(array $itemData): array
    {
        $payload = array_merge([
            'tin' => $this->tin,
            'bhfId' => $this->branchId,
        ], $itemData);

        return $this->post('/itemSaveReq', $payload);
    }

    // ── Stock I/O ──

    /**
     * Report stock movement (purchase/import) to KRA.
     */
    public function saveStockMovement(array $stockData): array
    {
        $payload = array_merge([
            'tin' => $this->tin,
            'bhfId' => $this->branchId,
        ], $stockData);

        return $this->post('/insertStockIO', $payload);
    }

    // ── HTTP Transport ──

    /**
     * Send a POST request to the eTIMS API.
     *
     * @throws \RuntimeException on cURL or API error
     */
    private function post(string $endpoint, array $data): array
    {
        $url = $this->baseUrl . $endpoint;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!empty($this->cmcKey)) {
            $headers[] = 'CMC-Key: ' . $this->cmcKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("eTIMS API request failed: {$error}");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new \RuntimeException("eTIMS API returned invalid JSON (HTTP {$httpCode})");
        }

        // eTIMS returns resultCd '000' for success
        if (isset($decoded['resultCd']) && $decoded['resultCd'] !== '000') {
            $msg = $decoded['resultMsg'] ?? 'Unknown eTIMS error';
            throw new \RuntimeException("eTIMS API error [{$decoded['resultCd']}]: {$msg}");
        }

        return $decoded;
    }

    /**
     * Test connectivity to the eTIMS API.
     */
    public function testConnection(): array
    {
        try {
            return $this->getCodeList();
        } catch (\RuntimeException $e) {
            return [
                'resultCd' => 'ERR',
                'resultMsg' => $e->getMessage(),
            ];
        }
    }
}
