<?php
/**
 * Export Security Report API
 * Exports security data in various formats
 * 
 * @package Jakababa\API
 * @version 1.0.0
 */

require_once __DIR__ . '/../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);
safe_require('functions.php', 'src', true);
require_once __DIR__ . '/../src/Security/SecurityBootstrap.php';

// Initialize security system
SecurityBootstrap::initialize();

// Secure API endpoint with super admin requirement
secure_api_endpoint('admin.security');

try {
    $format = $_GET['format'] ?? 'json';
    $days = intval($_GET['days'] ?? 30);
    
    $audit_manager = new AuditTrailManager();
    
    // Generate report data
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime("-{$days} days"));
    
    $report = $audit_manager->generateComplianceReport($start_date, $end_date);
    $dashboard_data = $audit_manager->getSecurityDashboard();
    
    // Merge data
    $export_data = [
        'report_metadata' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'period_start' => $start_date,
            'period_end' => $end_date,
            'format' => $format
        ],
        'compliance_report' => $report,
        'dashboard_data' => $dashboard_data
    ];
    
    switch (strtolower($format)) {
        case 'csv':
            exportAsCSV($export_data);
            break;
        case 'xml':
            exportAsXML($export_data);
            break;
        case 'json':
        default:
            exportAsJSON($export_data);
            break;
    }
    
} catch (Exception $e) {
    error_log("Export security report API error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to export security report',
        'message' => $e->getMessage()
    ]);
}

/**
 * Export as JSON
 */
function exportAsJSON(array $data): void {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="security_report_' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
}

/**
 * Export as CSV
 */
function exportAsCSV(array $data): void {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="security_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, ['Security Report Export']);
    fputcsv($output, ['Generated:', $data['report_metadata']['generated_at']]);
    fputcsv($output, ['Period:', $data['report_metadata']['period_start'] . ' to ' . $data['report_metadata']['period_end']]);
    fputcsv($output, []);
    
    // Write summary statistics
    fputcsv($output, ['Summary Statistics']);
    fputcsv($output, ['Metric', 'Value']);
    
    if (isset($data['compliance_report']['summary'])) {
        foreach ($data['compliance_report']['summary'] as $key => $value) {
            fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), $value]);
        }
    }
    
    fputcsv($output, []);
    
    // Write security events
    if (isset($data['dashboard_data']['security_events'])) {
        fputcsv($output, ['Security Events']);
        fputcsv($output, ['Date', 'Event Type', 'Severity', 'Description']);
        
        foreach ($data['dashboard_data']['security_events'] as $event) {
            fputcsv($output, [
                $event['created_at'] ?? '',
                $event['event_type'] ?? '',
                $event['severity'] ?? '',
                $event['description'] ?? ''
            ]);
        }
    }
    
    fclose($output);
}

/**
 * Export as XML
 */
function exportAsXML(array $data): void {
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="security_report_' . date('Y-m-d') . '.xml"');
    
    $xml = new SimpleXMLElement('<security_report/>');
    
    // Add metadata
    $metadata = $xml->addChild('metadata');
    foreach ($data['report_metadata'] as $key => $value) {
        $metadata->addChild($key, htmlspecialchars((string)$value));
    }
    
    // Add summary
    if (isset($data['compliance_report']['summary'])) {
        $summary = $xml->addChild('summary');
        foreach ($data['compliance_report']['summary'] as $key => $value) {
            $summary->addChild($key, htmlspecialchars((string)$value));
        }
    }
    
    // Add security events
    if (isset($data['dashboard_data']['security_events'])) {
        $events = $xml->addChild('security_events');
        foreach ($data['dashboard_data']['security_events'] as $event) {
            $event_node = $events->addChild('event');
            foreach ($event as $key => $value) {
                $event_node->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
    
    echo $xml->asXML();
}
?>
