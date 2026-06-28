<?php
declare(strict_types=1);

/**
 * KRA eTIMS Business Logic Service
 *
 * Bridges the JDH POS domain (sales, products, tenants) with the KRA eTIMS API.
 * Handles invoice mapping, submission, retry logic, and audit logging.
 *
 * Each tenant can independently enable/disable eTIMS and configure their KRA PIN,
 * branch ID, and device serial number via tenant settings.
 *
 * @package Jakababa\Services\Integration\KRA
 * @version 1.0.0
 */

namespace Jakababa\Services\Integration\KRA;

use PDO;

require_once __DIR__ . '/EtimsClient.php';

class EtimsService
{
    private PDO $pdo;
    private ?EtimsClient $client = null;
    private int $tenantId;

    /** KRA eTIMS receipt type codes */
    private const RCPT_TYPE_SALE = 'S';
    private const RCPT_TYPE_CREDIT_NOTE = 'R';

    /** KRA eTIMS tax type codes (Kenya VAT) */
    private const TAX_TYPE_VAT = 'B';
    private const TAX_TYPE_EXEMPT = 'C';
    private const TAX_TYPE_ZERO_RATED = 'E';

    /** KRA eTIMS payment type mapping */
    private const PAYMENT_TYPE_MAP = [
        'cash' => '01',
        'mpesa' => '04',
        'm-pesa' => '04',
        'card' => '02',
        'credit_card' => '02',
        'debit_card' => '02',
        'bank_transfer' => '03',
        'cheque' => '05',
        'credit' => '07',
        'voucher' => '06',
        'other' => '08',
    ];

    public function __construct(PDO $pdo, int $tenantId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
    }

    /**
     * Check if eTIMS is enabled for this tenant.
     */
    public function isEnabled(): bool
    {
        $setting = $this->getTenantSetting('etims_enabled');
        return $setting === '1' || $setting === 'true';
    }

    /**
     * Get or create the eTIMS API client for this tenant.
     */
    private function getClient(): EtimsClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = new EtimsClient([
            'environment' => $this->getTenantSetting('etims_environment') ?: 'sandbox',
            'tin' => $this->getTenantSetting('etims_tin') ?: '',
            'branch_id' => $this->getTenantSetting('etims_branch_id') ?: '00',
            'device_serial_no' => $this->getTenantSetting('etims_device_serial') ?: '',
            'cmc_key' => $this->getTenantSetting('etims_cmc_key') ?: '',
            'verify_ssl' => $this->getTenantSetting('etims_environment') === 'production',
        ]);

        return $this->client;
    }

    // ── Invoice Submission ──

    /**
     * Submit a completed sale to KRA eTIMS.
     *
     * Call this after a sale is saved and committed to the database.
     * On success, stores the KRA receipt signature and CU invoice number.
     * On failure, queues for retry and logs the error.
     *
     * @param int $saleId The sale ID from the sales table
     * @return array{success: bool, cu_invoice_no?: string, receipt_sign?: string, error?: string}
     */
    public function submitSale(int $saleId): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'eTIMS not enabled for this tenant'];
        }

        try {
            // Fetch sale + items
            $sale = $this->fetchSale($saleId);
            if (!$sale) {
                return ['success' => false, 'error' => "Sale {$saleId} not found"];
            }

            // Skip if already submitted
            if (!empty($sale['etims_cu_invoice_no'])) {
                return [
                    'success' => true,
                    'cu_invoice_no' => $sale['etims_cu_invoice_no'],
                    'receipt_sign' => $sale['etims_receipt_sign'] ?? '',
                ];
            }

            $items = $this->fetchSaleItems($saleId);
            if (empty($items)) {
                return ['success' => false, 'error' => 'Sale has no items'];
            }

            // Map to eTIMS format
            $invoiceData = $this->mapSaleToInvoice($sale);
            $etimsItems = $this->mapSaleItems($items);

            // Submit to KRA
            $client = $this->getClient();
            $response = $client->submitInvoice($invoiceData, $etimsItems);

            // Extract KRA data
            $cuInvoiceNo = $response['data']['intrlData'] ?? '';
            $receiptSign = $response['data']['rcptSign'] ?? '';
            $sdcDateTime = $response['data']['sdcDateTime'] ?? '';
            $vsdcRcptPbctDate = $response['data']['vsdcRcptPbctDate'] ?? '';

            // Store KRA response on the sale
            $this->updateSaleEtimsData($saleId, [
                'etims_cu_invoice_no' => $cuInvoiceNo,
                'etims_receipt_sign' => $receiptSign,
                'etims_sdc_datetime' => $sdcDateTime,
                'etims_submitted_at' => date('Y-m-d H:i:s'),
                'etims_status' => 'submitted',
            ]);

            // Audit log
            $this->logEtimsEvent('invoice_submitted', $saleId, [
                'cu_invoice_no' => $cuInvoiceNo,
                'receipt_sign' => $receiptSign,
            ]);

            return [
                'success' => true,
                'cu_invoice_no' => $cuInvoiceNo,
                'receipt_sign' => $receiptSign,
            ];
        } catch (\RuntimeException $e) {
            // Queue for retry
            $this->queueForRetry($saleId, $e->getMessage());

            $this->logEtimsEvent('invoice_failed', $saleId, [
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Submit a credit note (return/void) to eTIMS.
     *
     * @param int $saleId Original sale ID being returned/voided
     * @param array $returnItems Items and quantities being returned
     * @return array{success: bool, error?: string}
     */
    public function submitCreditNote(int $saleId, array $returnItems): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'eTIMS not enabled'];
        }

        try {
            $sale = $this->fetchSale($saleId);
            if (!$sale || empty($sale['etims_cu_invoice_no'])) {
                return ['success' => false, 'error' => 'Original invoice not found or not submitted to eTIMS'];
            }

            $creditNoteData = [
                'orgInvcNo' => (string)$saleId,
                'cisInvcNo' => $sale['etims_cu_invoice_no'],
                'rcptTyCd' => self::RCPT_TYPE_CREDIT_NOTE,
                'pmtTyCd' => self::PAYMENT_TYPE_MAP[$sale['payment_method'] ?? 'cash'] ?? '01',
                'cfmDt' => date('YmdHis'),
                'salesDt' => date('Ymd'),
                'stockRlsDt' => date('Ymd'),
                'totItemCnt' => count($returnItems),
                'remark' => $sale['void_reason'] ?? 'Return/Refund',
            ];

            $etimsItems = [];
            $totalAmt = 0;
            $totalTax = 0;
            $seq = 1;

            foreach ($returnItems as $item) {
                $qty = abs((float)($item['quantity'] ?? 1));
                $unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
                $lineTotal = $qty * $unitPrice;
                $taxRate = (float)($item['tax_rate'] ?? 16);
                $taxAmt = $lineTotal * ($taxRate / (100 + $taxRate));
                $netAmt = $lineTotal - $taxAmt;

                $etimsItems[] = [
                    'itemSeq' => $seq++,
                    'itemCd' => $item['etims_item_code'] ?? $item['sku'] ?? '',
                    'itemClsCd' => $item['etims_class_code'] ?? '',
                    'itemNm' => $item['product_name'] ?? $item['name'] ?? '',
                    'pkgUnitCd' => 'EA',
                    'pkg' => $qty,
                    'qtyUnitCd' => 'EA',
                    'qty' => $qty,
                    'prc' => round($unitPrice, 2),
                    'splyAmt' => round($netAmt, 2),
                    'dcRt' => 0,
                    'dcAmt' => 0,
                    'taxTyCd' => $taxRate > 0 ? self::TAX_TYPE_VAT : self::TAX_TYPE_EXEMPT,
                    'taxblAmt' => round($netAmt, 2),
                    'taxAmt' => round($taxAmt, 2),
                    'totAmt' => round($lineTotal, 2),
                ];

                $totalAmt += $lineTotal;
                $totalTax += $taxAmt;
            }

            $creditNoteData['totTaxblAmt'] = round($totalAmt - $totalTax, 2);
            $creditNoteData['totTaxAmt'] = round($totalTax, 2);
            $creditNoteData['totAmt'] = round($totalAmt, 2);

            $client = $this->getClient();
            $response = $client->submitCreditNote($creditNoteData, $etimsItems);

            $this->logEtimsEvent('credit_note_submitted', $saleId, [
                'original_cu_invoice' => $sale['etims_cu_invoice_no'],
                'response' => $response['resultCd'] ?? '',
            ]);

            return ['success' => true];
        } catch (\RuntimeException $e) {
            $this->logEtimsEvent('credit_note_failed', $saleId, [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Item Registration ──

    /**
     * Register a product with KRA eTIMS.
     *
     * @param int $productId Product ID from products table
     * @return array{success: bool, error?: string}
     */
    public function registerItem(int $productId): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'eTIMS not enabled'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, sku, barcode, price, tax_rate, unit,
                       etims_item_code, etims_class_code
                FROM products 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$productId, $this->tenantId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                return ['success' => false, 'error' => 'Product not found'];
            }

            $taxRate = (float)($product['tax_rate'] ?? 16);

            $itemData = [
                'itemCd' => $product['etims_item_code'] ?: $product['sku'] ?: ('PROD' . str_pad((string)$productId, 8, '0', STR_PAD_LEFT)),
                'itemClsCd' => $product['etims_class_code'] ?: '',
                'itemTyCd' => '1',
                'itemNm' => $product['name'],
                'orgnNatCd' => 'KE',
                'pkgUnitCd' => 'EA',
                'qtyUnitCd' => strtoupper($product['unit'] ?? 'EA') ?: 'EA',
                'taxTyCd' => $taxRate > 0 ? self::TAX_TYPE_VAT : self::TAX_TYPE_EXEMPT,
                'dftPrc' => (float)$product['price'],
                'bcd' => $product['barcode'] ?: '',
                'useYn' => 'Y',
                'regrNm' => 'JDH_POS',
                'regrId' => 'JDH_POS',
                'modrNm' => 'JDH_POS',
                'modrId' => 'JDH_POS',
            ];

            $client = $this->getClient();
            $response = $client->saveItem($itemData);

            $this->logEtimsEvent('item_registered', $productId, [
                'item_code' => $itemData['itemCd'],
            ]);

            return ['success' => true];
        } catch (\RuntimeException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Retry Queue ──

    /**
     * Process pending eTIMS submissions that previously failed.
     *
     * @param int $limit Max items to process
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function processRetryQueue(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, sale_id, attempt_count 
            FROM etims_retry_queue 
            WHERE tenant_id = ? AND status = 'pending' AND next_retry_at <= NOW()
            ORDER BY next_retry_at ASC
            LIMIT ?
        ");
        $stmt->execute([$this->tenantId, $limit]);
        $queued = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

        foreach ($queued as $item) {
            $result['processed']++;
            $submitResult = $this->submitSale((int)$item['sale_id']);

            if ($submitResult['success']) {
                $this->pdo->prepare("
                    UPDATE etims_retry_queue SET status = 'completed', completed_at = NOW() WHERE id = ?
                ")->execute([$item['id']]);
                $result['succeeded']++;
            } else {
                $attempts = (int)$item['attempt_count'] + 1;
                $backoff = min(3600, 60 * pow(2, $attempts));
                $nextRetry = date('Y-m-d H:i:s', time() + (int)$backoff);

                if ($attempts >= 10) {
                    $this->pdo->prepare("
                        UPDATE etims_retry_queue 
                        SET status = 'failed', attempt_count = ?, last_error = ?, completed_at = NOW()
                        WHERE id = ?
                    ")->execute([$attempts, $submitResult['error'] ?? '', $item['id']]);
                } else {
                    $this->pdo->prepare("
                        UPDATE etims_retry_queue 
                        SET attempt_count = ?, next_retry_at = ?, last_error = ?
                        WHERE id = ?
                    ")->execute([$attempts, $nextRetry, $submitResult['error'] ?? '', $item['id']]);
                }
                $result['failed']++;
            }
        }

        return $result;
    }

    // ── Private Helpers ──

    private function mapSaleToInvoice(array $sale): array
    {
        $paymentMethod = strtolower($sale['payment_method'] ?? 'cash');

        return [
            'invcNo' => (string)$sale['id'],
            'orgInvcNo' => '0',
            'rcptTyCd' => self::RCPT_TYPE_SALE,
            'pmtTyCd' => self::PAYMENT_TYPE_MAP[$paymentMethod] ?? '01',
            'cfmDt' => date('YmdHis', strtotime($sale['created_at'])),
            'salesDt' => date('Ymd', strtotime($sale['created_at'])),
            'stockRlsDt' => date('Ymd', strtotime($sale['created_at'])),
            'totItemCnt' => (int)($sale['item_count'] ?? 0),
            'totTaxblAmt' => round((float)$sale['subtotal'], 2),
            'totTaxAmt' => round((float)$sale['tax_amount'], 2),
            'totAmt' => round((float)$sale['total'], 2),
            'remark' => $sale['notes'] ?? '',
            'custTin' => $sale['customer_tin'] ?? '',
            'custNm' => $sale['customer_name'] ?? '',
            'custBhfId' => '',
            'regrNm' => 'JDH_POS',
            'regrId' => 'JDH_POS',
            'modrNm' => 'JDH_POS',
            'modrId' => 'JDH_POS',
        ];
    }

    private function mapSaleItems(array $items): array
    {
        $etimsItems = [];
        $seq = 1;

        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 1);
            $unitPrice = (float)($item['unit_price'] ?? $item['price'] ?? 0);
            $lineTotal = $qty * $unitPrice;
            $discount = (float)($item['discount_amount'] ?? 0);
            $afterDiscount = $lineTotal - $discount;
            $taxRate = (float)($item['tax_rate'] ?? 16);

            // Kenya VAT: extract tax from inclusive price
            if ($taxRate > 0) {
                $taxAmt = $afterDiscount * ($taxRate / (100 + $taxRate));
            } else {
                $taxAmt = 0;
            }
            $netAmt = $afterDiscount - $taxAmt;

            $etimsItems[] = [
                'itemSeq' => $seq++,
                'itemCd' => $item['etims_item_code'] ?? $item['sku'] ?? '',
                'itemClsCd' => $item['etims_class_code'] ?? '',
                'itemNm' => $item['product_name'] ?? $item['name'] ?? '',
                'bcd' => $item['barcode'] ?? '',
                'pkgUnitCd' => 'EA',
                'pkg' => $qty,
                'qtyUnitCd' => 'EA',
                'qty' => $qty,
                'prc' => round($unitPrice, 2),
                'splyAmt' => round($netAmt, 2),
                'dcRt' => $lineTotal > 0 ? round(($discount / $lineTotal) * 100, 2) : 0,
                'dcAmt' => round($discount, 2),
                'taxTyCd' => $taxRate > 0 ? self::TAX_TYPE_VAT : self::TAX_TYPE_EXEMPT,
                'taxblAmt' => round($netAmt, 2),
                'taxAmt' => round($taxAmt, 2),
                'totAmt' => round($afterDiscount, 2),
            ];
        }

        return $etimsItems;
    }

    private function fetchSale(int $saleId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, 
                   c.name AS customer_name,
                   c.tax_id AS customer_tin,
                   (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.id = ? AND s.tenant_id = ?
        ");
        $stmt->execute([$saleId, $this->tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function fetchSaleItems(int $saleId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT si.*, 
                   p.name AS product_name, p.sku, p.barcode,
                   p.etims_item_code, p.etims_class_code,
                   p.tax_rate
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ? AND si.tenant_id = ?
        ");
        $stmt->execute([$saleId, $this->tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function updateSaleEtimsData(int $saleId, array $data): void
    {
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "`{$key}` = ?";
            $params[] = $value;
        }
        $params[] = $saleId;
        $params[] = $this->tenantId;

        $sql = "UPDATE sales SET " . implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?";
        $this->pdo->prepare($sql)->execute($params);
    }

    private function queueForRetry(int $saleId, string $error): void
    {
        // Check if already queued
        $stmt = $this->pdo->prepare("
            SELECT id FROM etims_retry_queue 
            WHERE sale_id = ? AND tenant_id = ? AND status = 'pending'
        ");
        $stmt->execute([$saleId, $this->tenantId]);

        if ($stmt->fetch()) {
            return; // Already queued
        }

        $this->pdo->prepare("
            INSERT INTO etims_retry_queue (sale_id, tenant_id, status, attempt_count, last_error, next_retry_at, created_at)
            VALUES (?, ?, 'pending', 1, ?, DATE_ADD(NOW(), INTERVAL 1 MINUTE), NOW())
        ")->execute([$saleId, $this->tenantId, $error]);
    }

    private function logEtimsEvent(string $event, int $entityId, array $data = []): void
    {
        try {
            $this->pdo->prepare("
                INSERT INTO etims_audit_log (tenant_id, event, entity_id, data, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ")->execute([
                $this->tenantId,
                $event,
                $entityId,
                json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            error_log("eTIMS audit log failed: " . $e->getMessage());
        }
    }

    private function getTenantSetting(string $key): string
    {
        $stmt = $this->pdo->prepare("
            SELECT setting_value FROM settings 
            WHERE setting_key = ? AND tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([$key, $this->tenantId]);
        $result = $stmt->fetchColumn();
        return $result !== false ? (string)$result : '';
    }
}
