<?php
/**
 * Export Security Report - JSON Export
 */

require_once __DIR__ . '/../../src/paths.php';
safe_require('auth.php', 'src', true);
safe_require('db.php', 'src', true);

require_login();
if (!is_super_admin() && !check_permission('system.admin')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = get_db_connection();
$tenant_id = get_current_tenant_id() ?? 0;

$report = [
    'generated_at' => date('c'),
    'tenant_id' => $tenant_id,
    'active_users' => 0,
    'data_access_count' => 0,
    'security_events_count' => 0,
    'violations_count' => 0,
    'security_events' => [],
    'top_accessed_tables' => [],
    'permission_denials' => []
];

try {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    if (in_array('users', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (tenant_id = ? OR tenant_id IS NULL) AND last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND deleted_at IS NULL");
        $stmt->execute([$tenant_id]);
        $report['active_users'] = (int) $stmt->fetchColumn();
    }

    if (in_array('data_access_logs', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM data_access_logs WHERE (tenant_id = ? OR tenant_id IS NULL) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$tenant_id]);
        $report['data_access_count'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT table_name, operation, COUNT(*) as access_count, COUNT(DISTINCT user_id) as unique_users FROM data_access_logs WHERE (tenant_id = ? OR tenant_id IS NULL) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY table_name, operation ORDER BY access_count DESC LIMIT 10");
        $stmt->execute([$tenant_id]);
        $report['top_accessed_tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('security_events', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_events WHERE (tenant_id = ? OR tenant_id IS NULL) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$tenant_id]);
        $report['security_events_count'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT event_type, severity, description, created_at FROM security_events WHERE (tenant_id = ? OR tenant_id IS NULL) ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$tenant_id]);
        $report['security_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array('permission_checks', $tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM permission_checks WHERE (tenant_id = ? OR tenant_id IS NULL) AND granted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$tenant_id]);
        $report['violations_count'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT permission, COUNT(*) as denial_count FROM permission_checks WHERE (tenant_id = ? OR tenant_id IS NULL) AND granted = 0 GROUP BY permission ORDER BY denial_count DESC LIMIT 10");
        $stmt->execute([$tenant_id]);
        $report['permission_denials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Export security report error: " . $e->getMessage());
}

$filename = 'security_report_' . date('Y-m-d') . '.json';
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');

echo json_encode($report, JSON_PRETTY_PRINT);
