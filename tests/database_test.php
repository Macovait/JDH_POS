<?php
/**
 * Database Tests
 * Test database connectivity and critical queries
 */

require_once __DIR__ . '/TestRunner.php';

$runner = new TestRunner();

// Database config
$dbHost = 'localhost';
$dbName = 'jdh_pos';
$dbUser = 'root';
$dbPass = '';

// Test 1: Database connection
$runner->test('Database connection successful', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    try {
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $t->assert($pdo !== null, 'PDO connection should be established');
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
});

// Test 2: Tenants table exists
$runner->test('Tenants table exists', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $stmt = $pdo->query("SHOW TABLES LIKE 'tenants'");
    $t->assert($stmt->rowCount() > 0, 'Tenants table should exist');
});

// Test 3: Users table exists
$runner->test('Users table exists', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $t->assert($stmt->rowCount() > 0, 'Users table should exist');
});

// Test 4: Can fetch demo tenant
$runner->test('Can fetch demo tenant', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $stmt = $pdo->prepare("SELECT id, name, status FROM tenants WHERE subdomain = 'demo'");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    $t->assert($tenant !== false, 'Demo tenant should exist');
    $t->assertEquals('active', $tenant['status'], 'Demo tenant should be active');
});

// Test 5: Can fetch demo user
$runner->test('Can fetch demo admin user', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    
    // First get demo tenant ID
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE subdomain = 'demo'");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        throw new Exception("Demo tenant not found");
    }
    
    $stmt = $pdo->prepare("SELECT id, username, status FROM users WHERE username = 'admin' AND tenant_id = ?");
    $stmt->execute([$tenant['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $t->assert($user !== false, 'Admin user should exist for demo tenant');
    $t->assertEquals(1, $user['status'], 'Admin user should be active');
});

// Test 6: Password hash verification works
$runner->test('Password verification works', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("No admin user found for password test");
    }
    
    $valid = password_verify('admin123', $user['password_hash']);
    $t->assert($valid === true, 'Password should verify correctly');
});

// Test 7: Products table exists
$runner->test('Products table exists', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    $t->assert($stmt->rowCount() > 0, 'Products table should exist');
});

// Test 8: Sales table exists
$runner->test('Sales table exists', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $stmt = $pdo->query("SHOW TABLES LIKE 'sales'");
    $t->assert($stmt->rowCount() > 0, 'Sales table should exist');
});

// Test 9: Critical indexes exist
$runner->test('Critical indexes exist on tenant_id columns', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    
    $tables = ['users', 'products', 'sales', 'inventory'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW INDEX FROM {$table} WHERE Column_name = 'tenant_id'");
        $t->assert($stmt->rowCount() > 0, "{$table} should have tenant_id index");
    }
});

// Test 10: Can perform tenant-scoped query
$runner->test('Tenant isolation query works', function($t) use ($dbHost, $dbName, $dbUser, $dbPass) {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = 1");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    $t->assert($count >= 0, 'Should be able to count users by tenant');
});

// Run tests
$success = $runner->run();
exit($success ? 0 : 1);
