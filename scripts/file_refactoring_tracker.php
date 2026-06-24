<?php
/**
 * File Refactoring Tracker
 * 
 * Tracks the refactoring progress of all PHP files from tenant_id to tenant_id.
 * Generates reports and identifies remaining work.
 * 
 * Usage:
 *   php scripts/file_refactoring_tracker.php              # Full report
 *   php scripts/file_refactoring_tracker.php --todo       # Show only TODO files
 *   php scripts/file_refactoring_tracker.php --done       # Show completed files
 *   php scripts/file_refactoring_tracker.php --stats     # Statistics only
 *   php scripts/file_refactoring_tracker.php --export=csv # Export to CSV
 */

declare(strict_types=1);

class FileRefactoringTracker {
    private array $files = [];
    private string $projectRoot;
    private array $stats = [
        'total' => 0,
        'completed' => 0,
        'in_progress' => 0,
        'todo' => 0,
        'company_refs' => 0,
        'tenant_refs' => 0
    ];
    
    // Priority categories
    private array $categories = [
        'critical' => [
            'src/auth.php',
            'src/TenantContext.php',
            'src/Middleware/TenantMiddleware.php',
            'public/auth/login.php',
            'public/auth/logout.php',
        ],
        'high' => [
            'src/db.php',
            'src/functions.php',
            'public/ajax/process_sale.php',
            'public/dashboard/home.php',
            'public/dashboard/pos.php',
        ],
        'medium' => [
            'public/dashboard/*.php',  // All dashboard files
            'public/ajax/*.php',       // All AJAX files
        ],
        'low' => [
            'admin/*.php',
            'templates/*.php',
        ]
    ];
    
    public function __construct() {
        $this->projectRoot = dirname(__DIR__);
    }
    
    public function scan(): void {
        echo "=== Jakababa POS Refactoring Tracker ===\n\n";
        echo "Scanning codebase for tenant_id references...\n\n";
        
        $directories = ['src', 'public', 'api', 'admin'];
        
        foreach ($directories as $dir) {
            $this->scanDirectory($dir);
        }
        
        $this->analyzeFiles();
        $this->generateReport();
    }
    
    private function scanDirectory(string $dir): void {
        $fullPath = $this->projectRoot . DIRECTORY_SEPARATOR . $dir;
        
        if (!is_dir($fullPath)) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->analyzeFile($file->getPathname());
            }
        }
    }
    
    private function analyzeFile(string $filePath): void {
        $relativePath = str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $filePath);
        $content = @file_get_contents($filePath);
        
        if ($content === false) {
            return;
        }
        
        $analysis = [
            'path' => $relativePath,
            'full_path' => $filePath,
            'size' => filesize($filePath),
            'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
            'company_refs' => 0,
            'tenant_refs' => 0,
            'status' => 'todo',
            'priority' => $this->getPriority($relativePath),
            'issues' => []
        ];
        
        // Count tenant_id references
        $companyPatterns = [
            '/\$_[SESSION]*[\'"]tenant_id[\'"]/i',
            '/get_current_tenant_id\s*\(/i',
            '/validate_current_tenant\s*\(/i',
            '/company_filter_condition\s*\(/i',
            '/tenant_id\s*=\s*\?/i',
            '/->tenant_id/i',
        ];
        
        foreach ($companyPatterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            $analysis['company_refs'] += count($matches[0]);
        }
        
        // Count tenant_id references
        $tenantPatterns = [
            '/\$_[SESSION]*[\'"]tenant_id[\'"]/i',
            '/get_current_tenant_id\s*\(/i',
            '/validate_current_tenant\s*\(/i',
            '/tenant_filter_condition\s*\(/i',
            '/tenant_id\s*=\s*\?/i',
            '/->tenant_id/i',
        ];
        
        foreach ($tenantPatterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            $analysis['tenant_refs'] += count($matches[0]);
        }
        
        // Determine status
        if ($analysis['company_refs'] === 0 && $analysis['tenant_refs'] > 0) {
            $analysis['status'] = 'completed';
        } elseif ($analysis['company_refs'] > 0 && $analysis['tenant_refs'] > 0) {
            $analysis['status'] = 'in_progress';
        } else {
            $analysis['status'] = 'todo';
        }
        
        // Check for specific issues
        if (strpos($content, 'tenant_id') !== false && strpos($content, 'tenant_id') !== false) {
            $analysis['issues'][] = 'Mixed tenant_id and tenant_id usage';
        }
        
        $this->files[$relativePath] = $analysis;
        $this->stats['total']++;
        $this->stats['company_refs'] += $analysis['company_refs'];
        $this->stats['tenant_refs'] += $analysis['tenant_refs'];
    }
    
    private function getPriority(string $path): string {
        foreach ($this->categories as $priority => $patterns) {
            foreach ($patterns as $pattern) {
                $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
                if (preg_match($regex, $path)) {
                    return $priority;
                }
            }
        }
        return 'low';
    }
    
    private function analyzeFiles(): void {
        foreach ($this->files as $file) {
            switch ($file['status']) {
                case 'completed':
                    $this->stats['completed']++;
                    break;
                case 'in_progress':
                    $this->stats['in_progress']++;
                    break;
                case 'todo':
                    $this->stats['todo']++;
                    break;
            }
        }
    }
    
    public function generateReport(): void {
        echo "=== Refactoring Progress Report ===\n\n";
        
        // Summary stats
        echo "Summary:\n";
        echo "  Total Files:     {$this->stats['total']}\n";
        echo "  Completed:       {$this->stats['completed']} (" . $this->percent($this->stats['completed']) . "%)\n";
        echo "  In Progress:     {$this->stats['in_progress']} (" . $this->percent($this->stats['in_progress']) . "%)\n";
        echo "  TODO:            {$this->stats['todo']} (" . $this->percent($this->stats['todo']) . "%)\n";
        echo "\n";
        echo "References:\n";
        echo "  tenant_id refs: {$this->stats['company_refs']}\n";
        echo "  tenant_id refs:  {$this->stats['tenant_refs']}\n";
        echo "\n";
        
        // Priority breakdown
        echo "Priority Breakdown:\n";
        $priorities = ['critical', 'high', 'medium', 'low'];
        foreach ($priorities as $priority) {
            $count = count(array_filter($this->files, fn($f) => $f['priority'] === $priority));
            echo "  {$priority}: {$count} files\n";
        }
        echo "\n";
        
        // Handle command-line options
        global $argv;
        
        if (in_array('--todo', $argv)) {
            $this->listFilesByStatus('todo');
        } elseif (in_array('--done', $argv)) {
            $this->listFilesByStatus('completed');
        } elseif (in_array('--in-progress', $argv)) {
            $this->listFilesByStatus('in_progress');
        } elseif (in_array('--stats', $argv)) {
            // Stats only - already printed
        } else {
            $this->listCriticalFiles();
        }
        
        // Export option
        foreach ($argv as $arg) {
            if (strpos($arg, '--export=') === 0) {
                $format = substr($arg, 9);
                $this->export($format);
            }
        }
    }
    
    private function listCriticalFiles(): void {
        echo "=== Critical Files Requiring Attention ===\n\n";
        
        $critical = array_filter($this->files, fn($f) => $f['priority'] === 'critical');
        
        if (empty($critical)) {
            echo "No critical files found!\n";
            return;
        }
        
        foreach ($critical as $path => $file) {
            $statusIcon = $this->getStatusIcon($file['status']);
            echo "{$statusIcon} {$path}\n";
            echo "   Status: {$file['status']} | Company refs: {$file['company_refs']} | Tenant refs: {$file['tenant_refs']}\n";
            if (!empty($file['issues'])) {
                echo "   Issues: " . implode(', ', $file['issues']) . "\n";
            }
            echo "\n";
        }
    }
    
    private function listFilesByStatus(string $status): void {
        $files = array_filter($this->files, fn($f) => $f['status'] === $status);
        
        echo "=== Files with status: {$status} ===\n\n";
        
        if (empty($files)) {
            echo "No files found with status: {$status}\n";
            return;
        }
        
        // Sort by priority
        uasort($files, fn($a, $b) => 
            array_search($a['priority'], ['critical', 'high', 'medium', 'low']) <=>
            array_search($b['priority'], ['critical', 'high', 'medium', 'low'])
        );
        
        foreach ($files as $path => $file) {
            echo "[{$file['priority']}] {$path}\n";
            echo "   Company refs: {$file['company_refs']} | Tenant refs: {$file['tenant_refs']}\n";
            if (!empty($file['issues'])) {
                echo "   ⚠ " . implode(', ', $file['issues']) . "\n";
            }
            echo "\n";
        }
    }
    
    private function export(string $format): void {
        $filename = "refactoring_tracker_" . date('Ymd_His') . ".{$format}";
        $filepath = $this->projectRoot . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $filename;
        
        if ($format === 'csv') {
            $fp = fopen($filepath, 'w');
            fputcsv($fp, ['File', 'Status', 'Priority', 'Company Refs', 'Tenant Refs', 'Issues', 'Last Modified']);
            
            foreach ($this->files as $file) {
                fputcsv($fp, [
                    $file['path'],
                    $file['status'],
                    $file['priority'],
                    $file['company_refs'],
                    $file['tenant_refs'],
                    implode('; ', $file['issues']),
                    $file['modified']
                ]);
            }
            
            fclose($fp);
            echo "\nExported to: {$filepath}\n";
        }
    }
    
    private function getStatusIcon(string $status): string {
        return match($status) {
            'completed' => '✅',
            'in_progress' => '🔄',
            'todo' => '⏳',
            default => '❓'
        };
    }
    
    private function percent(int $value): float {
        if ($this->stats['total'] === 0) {
            return 0;
        }
        return round(($value / $this->stats['total']) * 100, 1);
    }
}

// Run the tracker
$tracker = new FileRefactoringTracker();
$tracker->scan();
