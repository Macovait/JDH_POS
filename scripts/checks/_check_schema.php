<?php
$pdo = new PDO('mysql:host=localhost;dbname=jdh_pos', 'root', '');
echo "=== sales table ===\n";
$r = $pdo->query('DESCRIBE sales');
while ($row = $r->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}
echo "\n--- Sample sale ---\n";
$r = $pdo->query('SELECT * FROM sales LIMIT 1');
$row = $r->fetch(PDO::FETCH_ASSOC);
if ($row) print_r($row);
else echo "No rows\n";
