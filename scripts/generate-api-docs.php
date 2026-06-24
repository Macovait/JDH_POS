<?php
/**
 * API Documentation Generator
 * Scans all AJAX endpoints and generates OpenAPI/Swagger compatible docs
 */

$root = dirname(__DIR__);
$ajaxDir = $root . '/public/ajax';
$outputFile = $root . '/docs/API.md';

echo "=== JDH POS API Documentation Generator ===\n\n";

// Ensure docs directory exists
if (!is_dir(dirname($outputFile))) {
    mkdir(dirname($outputFile), 0755, true);
}

// Scan all PHP files in ajax directory
$files = glob($ajaxDir . '/*.php');
sort($files);

$endpoints = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    
    // Parse file for endpoint info
    $endpoint = parseEndpoint($filename, $content);
    if ($endpoint) {
        $endpoints[] = $endpoint;
    }
}

// Generate markdown documentation
$markdown = generateMarkdown($endpoints);

// Write to file
file_put_contents($outputFile, $markdown);

echo "✓ Generated API documentation\n";
echo "  File: {$outputFile}\n";
echo "  Endpoints: " . count($endpoints) . "\n";
echo "\nDone!\n";

/**
 * Parse a PHP file for endpoint information
 */
function parseEndpoint(string $filename, string $content): ?array
{
    $endpoint = [
        'file' => $filename,
        'method' => 'POST',
        'path' => '/ajax/' . $filename,
        'name' => str_replace(['.php', '_'], ['', ' '], $filename),
        'description' => '',
        'auth_required' => true,
        'params' => [],
        'responses' => []
    ];
    
    // Extract description from docblock
    if (preg_match('/\/\*\*\s*(.+?)\s*\*\//s', $content, $matches)) {
        $docblock = $matches[1];
        $lines = explode("\n", $docblock);
        $description = [];
        
        foreach ($lines as $line) {
            $line = trim($line, " \t*");
            if ($line && !str_starts_with($line, '@')) {
                $description[] = $line;
            }
        }
        
        $endpoint['description'] = implode(' ', $description);
    }
    
    // Detect if GET is supported
    if (preg_match('/_GET|$_GET/', $content)) {
        $endpoint['method'] = 'GET/POST';
    }
    
    // Detect auth requirement
    if (strpos($content, 'require_login') !== false || 
        strpos($content, 'check_permission') !== false) {
        $endpoint['auth_required'] = true;
    }
    
    // Extract parameters
    preg_match_all('/\$_POST\[[\'"](\w+)[\'"]\]|\$_GET\[[\'"](\w+)[\'"]\]/', $content, $matches);
    $params = array_unique(array_filter(array_merge($matches[1], $matches[2])));
    
    foreach ($params as $param) {
        $endpoint['params'][] = [
            'name' => $param,
            'type' => 'string',
            'required' => true
        ];
    }
    
    // Detect response structure
    if (strpos($content, "'success' => true") !== false) {
        $endpoint['responses']['success'] = [
            'success' => true,
            'data' => 'mixed'
        ];
    }
    if (strpos($content, "'success' => false") !== false) {
        $endpoint['responses']['error'] = [
            'success' => false,
            'error' => 'string'
        ];
    }
    
    return $endpoint;
}

/**
 * Generate markdown documentation
 */
function generateMarkdown(array $endpoints): string
{
    $md = "# JDH POS API Documentation\n\n";
    $md .= "**Generated:** " . date('Y-m-d H:i:s') . "\n\n";
    $md .= "## Overview\n\n";
    $md .= "All API endpoints are located in `/public/ajax/` and require authentication unless noted.\n\n";
    $md .= "**Base URL:** `https://your-domain.com/ajax/`\n\n";
    $md .= "**Authentication:** Session-based (login required)\n\n";
    $md .= "---\n\n";
    
    // Group endpoints by category
    $categories = [
        'Sales' => [],
        'Products' => [],
        'Customers' => [],
        'Users' => [],
        'Reports' => [],
        'Settings' => [],
        'System' => []
    ];
    
    foreach ($endpoints as $endpoint) {
        $name = strtolower($endpoint['name']);
        
        if (str_contains($name, 'sale') || str_contains($name, 'cart') || str_contains($name, 'checkout')) {
            $categories['Sales'][] = $endpoint;
        } elseif (str_contains($name, 'product')) {
            $categories['Products'][] = $endpoint;
        } elseif (str_contains($name, 'customer')) {
            $categories['Customers'][] = $endpoint;
        } elseif (str_contains($name, 'user')) {
            $categories['Users'][] = $endpoint;
        } elseif (str_contains($name, 'report') || str_contains($name, 'log')) {
            $categories['Reports'][] = $endpoint;
        } elseif (str_contains($name, 'setting')) {
            $categories['Settings'][] = $endpoint;
        } else {
            $categories['System'][] = $endpoint;
        }
    }
    
    // Generate docs for each category
    foreach ($categories as $category => $catEndpoints) {
        if (empty($catEndpoints)) continue;
        
        $md .= "## {$category}\n\n";
        
        foreach ($catEndpoints as $ep) {
            $md .= "### {$ep['name']}\n\n";
            $md .= "**File:** `{$ep['file']}`\n\n";
            $md .= "**Method:** `{$ep['method']}`\n\n";
            $md .= "**Auth Required:** " . ($ep['auth_required'] ? 'Yes' : 'No') . "\n\n";
            
            if ($ep['description']) {
                $md .= "**Description:** {$ep['description']}\n\n";
            }
            
            if (!empty($ep['params'])) {
                $md .= "**Parameters:**\n\n";
                $md .= "| Name | Type | Required |\n";
                $md .= "|------|------|----------|\n";
                foreach ($ep['params'] as $param) {
                    $md .= "| {$param['name']} | {$param['type']} | " . ($param['required'] ? 'Yes' : 'No') . " |\n";
                }
                $md .= "\n";
            }
            
            if (!empty($ep['responses'])) {
                $md .= "**Responses:**\n\n";
                foreach ($ep['responses'] as $type => $structure) {
                    $md .= "```json\n" . json_encode($structure, JSON_PRETTY_PRINT) . "\n```\n\n";
                }
            }
            
            $md .= "---\n\n";
        }
    }
    
    $md .= "## Error Codes\n\n";
    $md .= "| Code | Description |\n";
    $md .= "|------|-------------|\n";
    $md .= "| 400 | Bad Request - Missing or invalid parameters |\n";
    $md .= "| 401 | Unauthorized - Not logged in |\n";
    $md .= "| 403 | Forbidden - No permission |\n";
    $md .= "| 404 | Not Found |\n";
    $md .= "| 422 | Validation Error |\n";
    $md .= "| 429 | Rate Limited |\n";
    $md .= "| 500 | Server Error |\n";
    $md .= "\n";
    
    $md .= "## Rate Limiting\n\n";
    $md .= "API endpoints are rate limited. See `src/RateLimiter.php` for details.\n\n";
    $md .= "Default limits:\n";
    $md .= "- Login: 5 attempts per 15 minutes\n";
    $md .= "- API: 60 requests per minute\n";
    $md .= "\n";
    
    return $md;
}
