<?php
/**
 * Audit Trail API
 * Retrieve and export audit logs
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/db.php';

// Session context
$tenantId = $_SESSION['tenant_id'] ?? 0;
$branchId = $_SESSION['branch_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!$tenantId || !$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'list':
                $sql = "SELECT * FROM audit_logs WHERE tenant_id = ? AND branch_id = ?";
                $params = [$tenantId, $branchId];
                
                if (!empty($_GET['action_type'])) {
                    $sql .= " AND action = ?";
                    $params[] = $_GET['action_type'];
                }
                
                $sql .= " ORDER BY created_at DESC LIMIT 500";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['success' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
                
            case 'stats':
                $stmt = $pdo->prepare("
                    SELECT action, COUNT(*) as count 
                    FROM audit_logs 
                    WHERE tenant_id = ? AND branch_id = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY action ORDER BY count DESC
                ");
                $stmt->execute([$tenantId, $branchId]);
                $stats = [];
                while ($row = $stmt->fetch()) {
                    $stats[$row['action']] = (int)$row['count'];
                }
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            case 'export_csv':
                $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE tenant_id = ? AND branch_id = ? ORDER BY created_at DESC LIMIT 1000");
                $stmt->execute([$tenantId, $branchId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="audit_export_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['id', 'action', 'description', 'created_at']);
                foreach ($rows as $row) {
                    fputcsv($output, [$row['id'], $row['action'], $row['description'], $row['created_at']]);
                }
                fclose($output);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}