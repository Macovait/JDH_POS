<?php
/**
 * Migration Runner Script
 * Creates all missing tables in the database
 */

require_once __DIR__ . '/bootstrap.php';

set_time_limit(300);

// Get database connection
$pdo = get_db_connection();

$results = [
    'success' => [],
    'error' => [],
    'skipped' => []
];

// Read all comprehensive migration SQL parts
$migration_files = [
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part1.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part2.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part3.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part4.sql',
    __DIR__ . '/../database/migrations/comprehensive_missing_tables_part5.sql',
];

$sql_content = '';
foreach ($migration_files as $file) {
    if (file_exists($file)) {
        $sql_content .= file_get_contents($file) . "\n";
    } else {
        $results['error'][] = ['table' => 'File', 'error' => "Migration file not found: $file"];
    }
}

// Split by statements (handle DELIMITER changes)
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// Remove DELIMITER statements and convert $$ to ;
$sql_content = preg_replace('/DELIMITER\s+\$\$/i', '', $sql_content);
$sql_content = preg_replace('/DELIMITER\s+;/i', '', $sql_content);
$sql_content = str_replace('$$', ';', $sql_content);

// Split into individual statements
$statements = array_filter(array_map('trim', preg_split('/;\s*/', $sql_content)));

$total = count($statements);
$current = 0;

foreach ($statements as $statement) {
    $current++;
    
    // Skip empty statements and comments
    if (empty($statement) || preg_match('/^--|^#|^\/\*/', $statement)) {
        continue;
    }
    
    // Extract table name from CREATE TABLE statement
    $table_name = null;
    if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $statement, $matches)) {
        $table_name = $matches[1];
    }
    
    try {
        $pdo->exec($statement);
        
        if ($table_name) {
            // Verify table was created
            $check = $pdo->query("SHOW TABLES LIKE '$table_name'")->fetchColumn();
            if ($check) {
                $results['success'][] = $table_name;
            } else {
                $results['error'][] = ['table' => $table_name, 'error' => 'Table not found after creation'];
            }
        }
    } catch (PDOException $e) {
        // Check if error is because table already exists
        if (strpos($e->getMessage(), 'already exists') !== false || 
            strpos($e->getMessage(), '1050') !== false) {
            if ($table_name) {
                $results['skipped'][] = $table_name;
            }
        } else {
            if ($table_name) {
                $results['error'][] = ['table' => $table_name, 'error' => $e->getMessage()];
            } else {
                $results['error'][] = ['table' => 'SQL Statement', 'error' => $e->getMessage()];
            }
        }
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

$page_title = 'Migration Results';
$current_page = 'database_audit';
ob_start();
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fas fa-database text-amber-400"></i> Migration Results
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">Executed <?= $total ?> SQL statements</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="database_audit.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-400 text-sm font-medium hover:bg-slate-700 hover:text-white transition-colors">
            <i class="fas fa-arrow-left text-xs"></i> Back to Audit
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-3 gap-2 mb-5">
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-check text-emerald-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-emerald-400"><?= count($results['success']) ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Tables Created</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-forward text-amber-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-amber-400"><?= count($results['skipped']) ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Already Exist</div>
        </div>
    </div>
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-xl p-3 flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center shrink-0">
            <i class="fas fa-times text-red-400 text-xs"></i>
        </div>
        <div class="min-w-0">
            <div class="text-sm font-bold text-red-400"><?= count($results['error']) ?></div>
            <div class="text-xs text-slate-500 leading-none mt-0.5">Errors</div>
        </div>
    </div>
</div>

<!-- Successfully Created -->
<?php if (count($results['success']) > 0): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
    <div class="px-3 py-2.5 border-b border-slate-700/60 bg-slate-800/60">
        <h2 class="text-sm font-semibold text-white">Successfully Created Tables</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Table Name</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php foreach ($results['success'] as $i => $table): ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?= $i + 1 ?></td>
                    <td class="px-3 py-2.5 text-sm font-mono text-emerald-400"><?= htmlspecialchars($table) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Errors -->
<?php if (count($results['error']) > 0): ?>
<div class="bg-slate-800/40 border border-slate-700/60 rounded-xl overflow-hidden mb-5">
    <div class="px-3 py-2.5 border-b border-slate-700/60 bg-slate-800/60">
        <h2 class="text-sm font-semibold text-white">Errors</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-700/60 bg-slate-800/60">
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Table</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Error</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/40">
                <?php foreach ($results['error'] as $err): ?>
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-3 py-2.5 text-sm font-mono text-red-400"><?= htmlspecialchars($err['table']) ?></td>
                    <td class="px-3 py-2.5 text-sm text-slate-400"><?= htmlspecialchars($err['error']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/layouts/super_admin.php';
?>
