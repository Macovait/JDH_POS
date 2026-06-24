<?php
/**
 * Barcode Error Handler
 * Stub implementation for Jakababa POS
 */

namespace JDH\POS\Barcode;

class BarcodeErrorHandler
{
    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }
}
