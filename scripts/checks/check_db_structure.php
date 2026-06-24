<?php
require_once 'src/paths.php';
safe_require('db.php', 'src', true);

try {
    $pdo = get_db_connection();
    
    echo "=== Checking Database Structure ===\n\n";
    
    // Check permissions table structure
    echo "Permissions table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE permissions");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\nRole permissions table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE role_permissions");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\nUser roles table structure:\n";
    $stmt = $pdo->prepare("DESCRIBE user_roles");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
