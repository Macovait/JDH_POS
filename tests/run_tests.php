<?php
/**
 * JDH POS - Run All Tests
 * Main entry point for test suite
 */

ob_start();

echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║       JDH POS Test Suite Runner          ║\n";
echo "╚══════════════════════════════════════════╝\n";
echo "\n";

$tests = [
    'Authentication Tests' => 'auth_test.php',
    'Database Tests' => 'database_test.php',
];

$totalPassed = 0;
$totalFailed = 0;

foreach ($tests as $name => $file) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Running: {$name}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        // Include and run the test
        try {
            include $filePath;
        } catch (Exception $e) {
            echo "Error running {$name}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Test file not found: {$file}\n";
    }
    
    echo "\n";
}

echo "═══════════════════════════════════════════\n";
echo "All tests completed!\n";
echo "═══════════════════════════════════════════\n";
