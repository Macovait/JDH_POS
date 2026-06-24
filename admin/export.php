<?php
/**
 * Data Export Utility - Owner Panel
 * 
 * Handles CSV exports for companies, payments, audit logs, and analytics.
 * 
 * @package JDH_POS\Admin
 * @version 1.0.0
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Services/OwnerPanelService.php';

use JDH_POS\Services\OwnerPanelService;

admin_require_super_admin();

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

$pdo = admin_require_db(['admins', 'pos_tenants', 'pos_subscriptions', 'pos_invoices']);
$adminId = $_SESSION['admin_id'] ?? ($_SESSION['user_id'] ?? 0);
$ownerService = new OwnerPanelService($pdo, $adminId);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

switch ($type) {
    case 'companies':
        // Export companies
        fputcsv($output, ['ID', 'Name', 'Slug', 'Email', 'Phone', 'Business Type', 'Industry', 'Status', 'Plan', 'Created At', 'Trial Ends']);
        
        $filters = [
            'status' => $_GET['status'] ?? '',
            'plan_id' => $_GET['plan_id'] ?? '',
        ];
        $data = $ownerService->getCompanies($filters, 1, 10000);
        
        foreach ($data['companies'] ?? [] as $company) {
            fputcsv($output, [
                $company['id'],
                $company['name'],
                $company['slug'],
                $company['email'],
                $company['phone'],
                $company['business_type'],
                $company['industry'],
                $company['status'],
                $company['plan_name'],
                $company['created_at'],
                $company['trial_ends_at']
            ]);
        }
        break;
        
    case 'payments':
        // Export payments
        fputcsv($output, ['ID', 'Company', 'Amount', 'Currency', 'Payment Method', 'Status', 'Reference', 'Transaction Fee', 'Created At']);
        
        $filters = [
            'status' => $_GET['status'] ?? '',
            'payment_method' => $_GET['method'] ?? '',
            'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
        ];
        $data = $ownerService->getPlatformPayments($filters, 1, 10000);
        
        foreach ($data['payments'] ?? [] as $payment) {
            fputcsv($output, [
                $payment['id'],
                $payment['company_name'],
                $payment['amount'],
                $payment['currency'],
                $payment['payment_method'],
                $payment['status'],
                $payment['payment_reference'],
                $payment['transaction_fee'],
                $payment['created_at']
            ]);
        }
        break;
        
    case 'audit_logs':
        // Export audit logs
        fputcsv($output, ['ID', 'Date', 'Admin', 'Action', 'Entity Type', 'Entity ID', 'Description', 'IP Address']);
        
        $filters = [
            'action' => $_GET['action'] ?? '',
            'entity_type' => $_GET['entity'] ?? '',
            'date_from' => $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
            'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
        ];
        $data = $ownerService->getAuditLogs($filters, 1, 10000);
        
        foreach ($data['logs'] ?? [] as $log) {
            fputcsv($output, [
                $log['id'],
                $log['created_at'],
                $log['admin_name'] . ' (' . $log['admin_email'] . ')',
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                $log['description'],
                $log['ip_address']
            ]);
        }
        break;
        
    case 'analytics':
        // Export analytics
        fputcsv($output, ['Date', 'Active Companies', 'Total Companies', 'Active Users', 'Total Transactions', 'API Calls', 'Storage Used (MB)', 'Response Time (ms)']);
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $data = $ownerService->getSystemAnalytics($dateFrom, $dateTo);
        
        foreach ($data as $day) {
            fputcsv($output, [
                $day['metric_date'],
                $day['active_companies'],
                $day['total_companies'],
                $day['active_users_count'],
                $day['total_transactions'],
                $day['api_calls_count'],
                $day['storage_used_mb'],
                $day['avg_response_time_ms']
            ]);
        }
        break;
        
    default:
        fputcsv($output, ['Error' => 'Invalid export type']);
}

fclose($output);
exit;
