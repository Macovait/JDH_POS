<?php
require_once __DIR__ . '/../src/paths.php';
safe_require('db.php', 'src', true);
$pdo = get_db_connection();

try {
    $cols = $pdo->query("SHOW COLUMNS FROM features")->fetchAll(PDO::FETCH_ASSOC);
    echo "features table EXISTS:\n";
    foreach ($cols as $c) {
        echo "  {$c['Field']} = {$c['Type']}\n";
    }
} catch (PDOException $e) {
    echo "features table does not exist: " . $e->getMessage() . "\n";
}
