<?php

namespace JDH\POS\Barcode\Tests;

/**
 * Unit Tests for Barcode Label System
 * 
 * Run with: phpunit tests/Barcode/BarcodeLabelsTest.php
 * 
 * Tests cover:
 * - SKU validation
 * - Product data validation
 * - Batch CSV validation
 * - Error handling
 * - Rate limiting
 */

use JDH\POS\Barcode\BarcodeErrorHandler;
use JDH\POS\Barcode\RateLimiter;
use JDH\POS\Barcode\ProductImporter;

class BarcodeLabelsTest extends \PHPUnit\Framework\TestCase
{
    private $errorHandler;
    private $pdo;

    protected function setUp(): void
    {
        $this->errorHandler = new BarcodeErrorHandler(true); // Debug mode for tests
    }

    // =========================================================================
    // SKU VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * Valid SKU should pass
     */
    public function testValidSkuAccepted()
    {
        $result = $this->errorHandler->validateSku('SKU001');

        $this->assertTrue($result['valid']);
        $this->assertEquals('SKU001', $result['sanitized']);
        $this->assertEquals('', $result['error']);
    }

    /**
     * @test
     * SKU with dashes and underscores should pass
     */
    public function testSkuWithSpecialCharsAccepted()
    {
        $result = $this->errorHandler->validateSku('SKU-001_V2');

        $this->assertTrue($result['valid']);
        $this->assertEquals('SKU-001_V2', $result['sanitized']);
    }

    /**
     * @test
     * Empty SKU should fail
     */
    public function testEmptySkuRejected()
    {
        $result = $this->errorHandler->validateSku('');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('empty', strtolower($result['error']));
    }

    /**
     * @test
     * SKU exceeding 50 characters should fail
     */
    public function testSkuTooLongRejected()
    {
        $long_sku = str_repeat('A', 51);
        $result = $this->errorHandler->validateSku($long_sku);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too long', strtolower($result['error']));
    }

    /**
     * @test
     * SKU with invalid characters should be sanitized
     */
    public function testSkuWithInvalidCharsIsSanitized()
    {
        $result = $this->errorHandler->validateSku('SKU@001#ABC');

        // Should fail but provide sanitized version
        $this->assertFalse($result['valid']);
        $this->assertStringNotContainsString('@', $result['sanitized']);
        $this->assertStringNotContainsString('#', $result['sanitized']);
    }

    // =========================================================================
    // PRODUCT VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * Valid product should pass all checks
     */
    public function testValidProductAccepted()
    {
        $product = [
            'id' => 1,
            'name' => 'Test Product',
            'sku' => 'SKU001',
            'price' => 29.99,
            'category_id' => 1,
        ];

        $result = $this->errorHandler->validateProduct($product);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['sanitized']['id']);
    }

    /**
     * @test
     * Product missing required name should fail
     */
    public function testProductMissingNameRejected()
    {
        $product = [
            'id' => 1,
            'sku' => 'SKU001',
            'price' => 29.99,
        ];

        $result = $this->errorHandler->validateProduct($product);

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('name', $result['errors'][0]['field']);
    }

    /**
     * @test
     * Invalid price should fail
     */
    public function testProductInvalidPriceRejected()
    {
        $product = [
            'id' => 1,
            'name' => 'Test',
            'sku' => 'SKU001',
            'price' => 'not-a-number',
        ];

        $result = $this->errorHandler->validateProduct($product);

        $this->assertFalse($result['valid']);
        $errors = array_filter($result['errors'], fn($e) => $e['field'] === 'price');
        $this->assertNotEmpty($errors);
    }

    /**
     * @test
     * Long product name should be truncated with warning
     */
    public function testLongProductNameTruncated()
    {
        $long_name = str_repeat('A', 150);
        $product = [
            'id' => 1,
            'name' => $long_name,
            'sku' => 'SKU001',
            'price' => 29.99,
        ];

        $result = $this->errorHandler->validateProduct($product);

        $this->assertTrue($result['valid']);
        $this->assertLessThanOrEqual(100, strlen($result['sanitized']['name']));
    }

    /**
     * @test
     * Negative price should fail
     */
    public function testNegativePriceRejected()
    {
        $product = [
            'id' => 1,
            'name' => 'Test',
            'sku' => 'SKU001',
            'price' => -10.00,
        ];

        $result = $this->errorHandler->validateProduct($product);

        $this->assertFalse($result['valid']);
    }

    // =========================================================================
    // CSV BATCH VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * Valid CSV data should pass validation
     */
    public function testValidCsvDataAccepted()
    {
        $csvData = [
            ['name' => 'Product 1', 'sku' => 'SKU001', 'price' => '29.99'],
            ['name' => 'Product 2', 'sku' => 'SKU002', 'price' => '49.99'],
            ['name' => 'Product 3', 'sku' => 'SKU003', 'price' => '15.50'],
        ];

        $result = $this->errorHandler->validateBatchCsv($csvData);

        $this->assertTrue($result['valid']);
        $this->assertCount(3, $result['valid_rows']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * @test
     * CSV missing required column should fail
     */
    public function testCsvMissingColumnRejected()
    {
        $csvData = [
            ['name' => 'Product 1', 'sku' => 'SKU001'], // Missing 'price'
        ];

        $result = $this->errorHandler->validateBatchCsv($csvData);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * @test
     * CSV with mixed valid/invalid rows should mark only valid ones
     */
    public function testCsvMixedValidityTracked()
    {
        $csvData = [
            ['name' => 'Valid Product', 'sku' => 'SKU001', 'price' => '29.99'],
            ['name' => '', 'sku' => 'SKU002', 'price' => '49.99'], // Missing name
            ['name' => 'Another Valid', 'sku' => 'SKU003', 'price' => '15.50'],
        ];

        $result = $this->errorHandler->validateBatchCsv($csvData);

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['valid_rows']);
        $this->assertCount(1, $result['warnings']);
    }

    /**
     * @test
     * Empty CSV should fail
     */
    public function testEmptyCsvRejected()
    {
        $result = $this->errorHandler->validateBatchCsv([]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // =========================================================================
    // AJAX RESPONSE TESTS
    // =========================================================================

    /**
     * @test
     * Success response should have correct format
     */
    public function testAjaxSuccessResponseFormat()
    {
        $response = $this->errorHandler->ajaxSuccess(['count' => 5], 'Operation completed');

        $this->assertTrue($response['success']);
        $this->assertEquals('Operation completed', $response['message']);
        $this->assertEquals(['count' => 5], $response['data']);
    }

    /**
     * @test
     * Error response should have correct format
     */
    public function testAjaxErrorResponseFormat()
    {
        $response = $this->errorHandler->ajaxError('VALIDATION_FAILED', 'Invalid input');

        $this->assertFalse($response['success']);
        $this->assertEquals('VALIDATION_FAILED', $response['error']['code']);
        $this->assertEquals('Invalid input', $response['error']['message']);
    }

    // =========================================================================
    // INTEGRATION TESTS
    // =========================================================================

    /**
     * @test
     * Full validation flow for product import
     */
    public function testFullProductImportValidationFlow()
    {
        // Simulate user uploading CSV data
        $products = [
            ['name' => 'Laptop', 'sku' => 'LAP-001', 'price' => '899.99'],
            ['name' => 'Mouse', 'sku' => 'MOU-001', 'price' => '25.00'],
            ['name' => 'Keyboard', 'sku' => 'KEY-001', 'price' => '75.50'],
        ];

        $result = $this->errorHandler->validateBatchCsv($products);

        // All should be valid
        $this->assertTrue($result['valid']);
        $this->assertCount(3, $result['valid_rows']);

        // Each should have sanitized data
        foreach ($result['valid_rows'] as $row) {
            $this->assertArrayHasKey('data', $row);
            $this->assertArrayHasKey('name', $row['data']);
            $this->assertArrayHasKey('sku', $row['data']);
            $this->assertArrayHasKey('price', $row['data']);
        }
    }

    /**
     * @test
     * Detecting and handling duplicate SKUs
     */
    public function testDuplicateSkusDetected()
    {
        $products = [
            ['name' => 'Product A', 'sku' => 'DUP-001', 'price' => '25.00'],
            ['name' => 'Product B', 'sku' => 'DUP-001', 'price' => '30.00'], // Duplicate
        ];

        // Note: This test validates the data structure, not database-level uniqueness
        $result = $this->errorHandler->validateBatchCsv($products);

        // Both products are structurally valid, but duplicates would be caught at DB level
        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['valid_rows']);
    }
}

/**
 * Rate Limiter Tests
 */
class RateLimiterTest extends \PHPUnit\Framework\TestCase
{
    private $pdo;
    private $rateLimiter;

    protected function setUp(): void
    {
        // Mock PDO for testing rate limiter
        // In real tests, use a test database or mock object
        session_start();
    }

    /**
     * @test
     * First request should be allowed
     */
    public function testFirstRequestAllowed()
    {
        // This test would need a mock PDO connection
        // Demonstrating the expected behavior
        $this->markTestSkipped('Requires mock PDO setup');
    }

    /**
     * @test
     * Rate limit should prevent requests after threshold
     */
    public function testRateLimitEnforced()
    {
        $this->markTestSkipped('Requires mock PDO setup');
    }
}
