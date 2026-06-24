<?php

namespace JDH\POS\Src\Barcode;

class BarcodeErrorHandler
{
    public function logError(string $message): void
    {
        error_log('[BarcodeError] ' . $message);
    }

    public function logWarning(string $message): void
    {
        error_log('[BarcodeWarning] ' . $message);
    }

    public function logInfo(string $message): void
    {
        error_log('[BarcodeInfo] ' . $message);
    }
}