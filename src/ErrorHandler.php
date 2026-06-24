<?php
declare(strict_types=1);

/**
 * Centralized Error Handler
 * 
 * Provides consistent error handling across the application
 * with production-safe error display and comprehensive logging.
 */

class ErrorHandler
{
    private static ?self $instance = null;
    private bool $isProduction;
    private string $logPath;
    
    private function __construct()
    {
        $this->isProduction = (getenv('APP_ENV') ?: 'production') === 'production';
        $this->logPath = __DIR__ . '/../storage/logs/';
        
        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register custom error handlers
     */
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Log all errors
        $this->logError($severity, $message, $file, $line);
        
        // In production, suppress display of non-fatal errors
        if ($this->isProduction && !in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            return true; // Suppress default error display
        }
        
        return false; // Allow default handler to run
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException(Throwable $e): void
    {
        $this->logException($e);
        
        if ($this->isProduction) {
            $this->displayProductionError();
        } else {
            $this->displayDevelopmentError($e);
        }
        
        exit(1);
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logError($error['type'], $error['message'], $error['file'], $error['line']);
            
            if ($this->isProduction) {
                $this->displayProductionError();
            } else {
                echo "Fatal error: {$error['message']} in {$error['file']}:{$error['line']}\n";
            }
        }
    }
    
    /**
     * Log error to file
     */
    private function logError(int $severity, string $message, string $file, int $line): void
    {
        $severityMap = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_NOTICE => 'NOTICE',
            E_DEPRECATED => 'DEPRECATED',
            E_PARSE => 'PARSE',
        ];
        
        $level = $severityMap[$severity] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');
        $logFile = $this->logPath . date('Y-m-d') . '.log';
        
        $entry = "[{$timestamp}] [{$level}] {$message} in {$file}:{$line}\n";
        
        error_log($entry, 3, $logFile);
    }
    
    /**
     * Log exception details
     */
    private function logException(Throwable $e): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logFile = $this->logPath . date('Y-m-d') . '.log';
        
        $entry = "[{$timestamp}] [EXCEPTION] {$e->getMessage()}\n";
        $entry .= "Stack trace:\n{$e->getTraceAsString()}\n";
        
        error_log($entry, 3, $logFile);
    }
    
    /**
     * Display user-friendly error in production
     */
    private function displayProductionError(): void
    {
        http_response_code(500);
        
        // Check if it's an API request
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred. Please try again later.'
            ]);
        } else {
            // HTML error page
            echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
               background: #f5f5f5; display: flex; align-items: center; justify-content: center; 
               height: 100vh; margin: 0; }
        .error-container { background: white; padding: 40px; border-radius: 8px; 
                          box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        .error-icon { font-size: 48px; color: #ef4444; margin-bottom: 20px; }
        h1 { color: #1f2937; margin: 0 0 10px; font-size: 24px; }
        p { color: #6b7280; line-height: 1.5; }
        .retry-btn { display: inline-block; margin-top: 20px; padding: 10px 20px; 
                     background: #3b82f6; color: white; text-decoration: none; 
                     border-radius: 4px; transition: background 0.2s; }
        .retry-btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>System Error</h1>
        <p>An unexpected error occurred. Our team has been notified and is working to resolve the issue.</p>
        <a href="javascript:location.reload()" class="retry-btn">Try Again</a>
    </div>
</body>
</html>';
        }
    }
    
    /**
     * Display detailed error in development
     */
    private function displayDevelopmentError(Throwable $e): void
    {
        http_response_code(500);
        
        echo "<h1>Exception: " . get_class($e) . "</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "</p>";
        echo "<h2>Stack Trace:</h2>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}

// Auto-register if this file is included
try {
    ErrorHandler::getInstance()->register();
} catch (Throwable $e) {
    // Fallback: use basic error logging
    error_log('Failed to initialize ErrorHandler: ' . $e->getMessage());
}
