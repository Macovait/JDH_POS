<?php
/**
 * Automated Company-to-Tenant Code Refactoring Script
 * 
 * This script analyzes and refactors PHP files to use tenant_id instead of tenant_id.
 * It provides a preview mode (default) and an apply mode.
 * 
 * Usage:
 *   php scripts/refactor_company_to_tenant.php --preview           # Preview changes
 *   php scripts/refactor_company_to_tenant.php --apply            # Apply changes
 *   php scripts/refactor_company_to_tenant.php --file=specific.php # Single file
 *   php scripts/refactor_company_to_tenant.php --dry-run          # Show what would change
 * 
 * SAFETY: Always backup before running --apply
 */

declare(strict_types=1);

class TenantRefactorEngine {
    private array $stats = [
        'files_scanned' => 0,
        'files_modified' => 0,
        'replacements' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    
    private bool $dryRun = false;
    private bool $applyMode = false;
    private ?string $singleFile = null;
    private array $targetDirs = ['public', 'src', 'api'];
    
    // Refactoring patterns
    private array $patterns = [
        // Session variables
        [
            'name' => 'session_company_id',
            'find' => '/\$_SESSION\[[\'"]company_id[\'"\']\]/',
            'replace' => '$_SESSION[\'tenant_id\']',
            'priority' => 1
        ],
        [
            'name' => 'session_company_name',
            'find' => '/\$_SESSION\[[\'"]company_name[\'"\']\]/',
            'replace' => '$_SESSION[\'tenant_name\']',
            'priority' => 1
        ],
        [
            'name' => 'session_company_code',
            'find' => '/\$_SESSION\[[\'"]company_code[\'"\']\]/',
            'replace' => '$_SESSION[\'tenant_code\']',
            'priority' => 1
        ],
        [
            'name' => 'session_company_validated',
            'find' => '/\$_SESSION\[[\'"]company_validated[\'"\']\]/',
            'replace' => '$_SESSION[\'tenant_validated\']',
            'priority' => 1
        ],
        
        // Function calls
        [
            'name' => 'get_current_tenant_id_call',
            'find' => '/get_current_tenant_id\(\)/',
            'replace' => 'get_current_tenant_id()',
            'priority' => 2
        ],
        [
            'name' => 'validate_current_tenant_call',
            'find' => '/validate_current_tenant\(\)/',
            'replace' => 'validate_current_tenant()',
            'priority' => 2
        ],
        [
            'name' => 'ensure_company_id_call',
            'find' => '/ensure_company_id\(\)/',
            'replace' => 'ensure_tenant_id()',
            'priority' => 2
        ],
        [
            'name' => 'company_filter_condition_call',
            'find' => '/company_filter_condition\(/',
            'replace' => 'tenant_filter_condition(',
            'priority' => 2
        ],
        
        // SQL patterns
        [
            'name' => 'sql_company_id_param',
            'find' => '/company_id\s*=\s*\?/i',
            'replace' => 'tenant_id = ?',
            'priority' => 3
        ],
        [
            'name' => 'sql_company_id_named',
            'find' => '/company_id\s*=\s*:company_id/i',
            'replace' => 'tenant_id = :tenant_id',
            'priority' => 3
        ],
        [
            'name' => 'sql_and_company_id',
            'find' => '/AND\s+company_id\s*=\s*\?/i',
            'replace' => 'AND tenant_id = ?',
            'priority' => 3
        ],
        [
            'name' => 'sql_where_company_id',
            'find' => '/WHERE\s+company_id\s*=\s*\?/i',
            'replace' => 'WHERE tenant_id = ?',
            'priority' => 3
        ],
        
        // Array keys
        [
            'name' => 'array_company_id_key',
            'find' => '/[\'"]company_id[\'"]\s*=>/',
            'replace' => '\'tenant_id\' =>',
            'priority' => 4
        ],
        [
            'name' => 'arrow_company_id',
            'find' => '/->company_id/',
            'replace' => '->tenant_id',
            'priority' => 4
        ],
        [
            'name' => 'object_company_id',
            'find' => '/\$([a-zA-Z_]+)->company_id/',
            'replace' => '$\1->tenant_id',
            'priority' => 4
        ],
        
        // Log activity calls
        [
            'name' => 'log_activity_tenant',
            'find' => '/log_activity\s*\(\s*([^,]+),\s*([^,]+),\s*([^,]+),\s*([^)]+)\)/',
            'replace' => 'log_activity(\1, \2, \3, \4, get_current_tenant_id())',
            'callback' => 'refactorLogActivity',
            'priority' => 5
        ],
        
        // Variable assignments
        [
            'name' => 'var_company_id',
            'find' => '/\$([a-zA-Z_]+)\s*=\s*get_current_company_id\(\)/',
            'replace' => '$\1 = get_current_tenant_id()',
            'priority' => 1
        ],
        [
            'name' => 'var_company_id_aliased',
            'find' => '/\$company_id\s*=/',
            'replace' => '$tenant_id =',
            'priority' => 1
        ],
    ];
    
    public function __construct(array $options = []) {
        $this->dryRun = $options['dry_run'] ?? false;
        $this->applyMode = $options['apply'] ?? false;
        $this->singleFile = $options['file'] ?? null;
    }
    
    public function run(): void {
        echo "=== Jakababa POS Tenant Refactoring Engine ===\n\n";
        
        if ($this->dryRun) {
            echo "Mode: DRY RUN (no changes will be made)\n\n";
        } elseif (!$this->applyMode) {
            echo "Mode: PREVIEW (use --apply to make changes)\n\n";
        } else {
            echo "Mode: APPLY CHANGES\n\n";
        }
        
        if ($this->singleFile) {
            $this->processFile($this->singleFile);
        } else {
            $this->processDirectory('public');
            $this->processDirectory('src');
            $this->processDirectory('api');
        }
        
        $this->printSummary();
    }
    
    private function processDirectory(string $dir): void {
        $basePath = dirname(__DIR__);
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $dir;
        
        if (!is_dir($fullPath)) {
            $this->stats['errors'][] = "Directory not found: {$fullPath}";
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->processFile($file->getPathname());
            }
        }
    }
    
    private function processFile(string $filePath): void {
        $this->stats['files_scanned']++;
        
        // Skip certain files
        if ($this->shouldSkipFile($filePath)) {
            $this->stats['skipped']++;
            return;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->stats['errors'][] = "Failed to read: {$filePath}";
            return;
        }
        
        $originalContent = $content;
        $fileReplacements = 0;
        $changes = [];
        
        // Sort patterns by priority
        usort($this->patterns, fn($a, $b) => $a['priority'] <=> $b['priority']);
        
        foreach ($this->patterns as $pattern) {
            if (isset($pattern['callback']) && method_exists($this, $pattern['callback'])) {
                // Handle callback-based patterns
                $callback = $pattern['callback'];
                $newContent = $this->$callback($content, $pattern, $changes);
                if ($newContent !== $content) {
                    $fileReplacements++;
                    $content = $newContent;
                }
            } else {
                // Regex-based replacement
                $matches = [];
                preg_match_all($pattern['find'], $content, $matches, PREG_OFFSET_CAPTURE);
                
                if (!empty($matches[0])) {
                    $count = count($matches[0]);
                    $fileReplacements += $count;
                    $this->stats['replacements'] += $count;
                    
                    foreach ($matches[0] as $match) {
                        $changes[] = [
                            'pattern' => $pattern['name'],
                            'line' => $this->getLineNumber($content, $match[1]),
                            'match' => $match[0]
                        ];
                    }
                    
                    $content = preg_replace($pattern['find'], $pattern['replace'], $content);
                }
            }
        }
        
        if ($fileReplacements > 0) {
            $this->stats['files_modified']++;
            
            if (!$this->dryRun && $this->applyMode) {
                // Backup original
                $backupPath = $filePath . '.backup.' . date('YmdHis');
                copy($filePath, $backupPath);
                
                // Write changes
                file_put_contents($filePath, $content);
                echo "✓ Modified: {$filePath} ({$fileReplacements} changes)\n";
            } else {
                echo "○ Would modify: {$filePath} ({$fileReplacements} changes)\n";
                if ($this->dryRun && !empty($changes)) {
                    foreach (array_slice($changes, 0, 3) as $change) {
                        echo "  Line {$change['line']}: {$change['pattern']} => {$change['match']}\n";
                    }
                    if (count($changes) > 3) {
                        echo "  ... and " . (count($changes) - 3) . " more changes\n";
                    }
                }
            }
        }
    }
    
    private function shouldSkipFile(string $filePath): bool {
        $skipPatterns = [
            '/\.backup\./',
            '/vendor\//',
            '/cache\//',
            '/logs\//',
            '/scripts\/refactor_/', // Skip this script
            '/\.git\//',
            '/tests\//',
        ];
        
        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function getLineNumber(string $content, int $offset): int {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
    
    private function refactorLogActivity(string $content, array $pattern, array &$changes): string {
        // Complex refactoring for log_activity calls
        // This requires context-aware changes
        return preg_replace_callback(
            '/log_activity\s*\(\s*([^,]+),\s*([^,]+),\s*([^,\)]+)(?:,\s*([^\)]+))?\)/',
            function($matches) use (&$changes) {
                $userId = trim($matches[1]);
                $action = trim($matches[2]);
                $description = trim($matches[3]);
                $existingMeta = isset($matches[4]) ? trim($matches[4]) : 'null';
                
                $changes[] = [
                    'pattern' => 'log_activity_refactor',
                    'line' => 0,
                    'match' => 'log_activity call'
                ];
                
                return "log_activity({$userId}, {$action}, {$description}, {$existingMeta}, get_current_tenant_id())";
            },
            $content
        );
    }
    
    private function printSummary(): void {
        echo "\n=== Refactoring Summary ===\n";
        echo "Files scanned: {$this->stats['files_scanned']}\n";
        echo "Files to modify: {$this->stats['files_modified']}\n";
        echo "Total replacements: {$this->stats['replacements']}\n";
        echo "Files skipped: {$this->stats['skipped']}\n";
        
        if (!empty($this->stats['errors'])) {
            echo "\nErrors encountered:\n";
            foreach ($this->stats['errors'] as $error) {
                echo "  ✗ {$error}\n";
            }
        }
        
        if (!$this->applyMode && !$this->dryRun && $this->stats['files_modified'] > 0) {
            echo "\n⚠ To apply these changes, run with --apply flag\n";
            echo "⚠ Ensure you have backups before applying!\n";
        }
    }
}

// Parse command line arguments
$options = [
    'dry_run' => false,
    'apply' => false,
    'file' => null
];

foreach ($argv as $arg) {
    if ($arg === '--dry-run') {
        $options['dry_run'] = true;
    } elseif ($arg === '--apply') {
        $options['apply'] = true;
    } elseif ($arg === '--preview') {
        // Default mode
    } elseif (strpos($arg, '--file=') === 0) {
        $options['file'] = substr($arg, 7);
    }
}

// Run the refactor engine
$engine = new TenantRefactorEngine($options);
$engine->run();
