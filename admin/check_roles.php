<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_db_connection();

echo "=== ROLES TABLE STRUCTURE ===\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM roles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== ROLES DATA ===\n";
try {
    $stmt = $pdo->query("SELECT * FROM roles LIMIT 20");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($roles)) {
        echo "No roles found in the table.\n";
    } else {
        foreach ($roles as $role) {
            echo "ID: {$role['id']}, Name: {$role['name']}";
            if (isset($role['tenant_id'])) echo ", Tenant: {$role['tenant_id']}";
            if (isset($role['branch_id'])) echo ", Branch: {$role['branch_id']}";
            if (isset($role['is_system'])) echo ", System: {$role['is_system']}";
            echo "\n";
        }
    }
} catch (Exception $e) {
    echo "Error fetching roles: " . $e->getMessage() . "\n";
}

echo "\n=== TOTAL ROLES ===\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    echo "Total: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== CURRENT TENANT ===\n";
$tenant_id = get_current_tenant_id();
echo "Current Tenant ID: " . ($tenant_id ?: 'Not set') . "\n";
echo "Current Branch ID: " . (get_current_branch_id() ?: 'Not set') . "\n";
