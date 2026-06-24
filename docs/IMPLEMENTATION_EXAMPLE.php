<?php
/**
 * Complete Implementation Example
 * Multi-Tenant Sales Management Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/MultiTenantFilter.php';

// Get database connection
$pdo = get_db_connection();

// ===========================================
// EXAMPLE 1: List Sales with Automatic Scoping
// ===========================================
function getSalesList($filters = [], $page = 1, $perPage = 50) {
    global $pdo;
    
    // Build query with automatic tenant + branch scoping
    $query = build_tenant_query('sales', 's.*, c.name as customer_name, c.phone as customer_phone', [], 's');
    
    // Add customer join (also scoped)
    $customerScope = tenant_where_pdo('customers', 'c');
    
    $sql = "SELECT {$query['columns']}
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id AND {$customerScope['where']}
            WHERE {$query['where']}";
    
    // Merge parameters
    $params = array_merge($query['params'], $customerScope['params']);
    
    // Add dynamic filters
    if (!empty($filters['status'])) {
        $sql .= " AND s.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND s.created_at >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND s.created_at <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (s.invoice_number LIKE :search OR c.name LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    // Order and limit
    $sql .= " ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $perPage;
    $params[':offset'] = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===========================================
// EXAMPLE 2: Create Sale with Auto Scope Injection
// ===========================================
function createSale($saleData, $items) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Build INSERT with automatic tenant_id, branch_id, created_by injection
        $insert = build_tenant_insert('sales', [
            'invoice_number' => $saleData['invoice_number'],
            'customer_id' => $saleData['customer_id'],
            'total' => $saleData['total'],
            'tax' => $saleData['tax'],
            'discount' => $saleData['discount'],
            'payment_method' => $saleData['payment_method'],
            'status' => 'completed',
            'notes' => $saleData['notes'] ?? null
        ]);
        
        $stmt = $pdo->prepare($insert['sql']);
        $stmt->execute($insert['params']);
        $saleId = $pdo->lastInsertId();
        
        // Insert sale items
        foreach ($items as $item) {
            $itemInsert = build_tenant_insert('sale_items', [
                'sale_id' => $saleId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item['quantity'] * $item['unit_price']
            ]);
            
            $stmt = $pdo->prepare($itemInsert['sql']);
            $stmt->execute($itemInsert['params']);
        }
        
        $pdo->commit();
        return $saleId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ===========================================
// EXAMPLE 3: Update Sale with Scope Protection
// ===========================================
function updateSale($saleId, $updateData) {
    global $pdo;
    
    // Build UPDATE with automatic tenant + branch scoping
    $update = build_tenant_update('sales', [
        'status' => $updateData['status'],
        'notes' => $updateData['notes'] ?? null
    ], [
        'id = :id' => $saleId
    ]);
    
    $stmt = $pdo->prepare($update['sql']);
    $stmt->execute($update['params']);
    
    return $stmt->rowCount();
}

// ===========================================
// EXAMPLE 4: Delete Sale (Soft Delete with Scope)
// ===========================================
function deleteSale($saleId) {
    global $pdo;
    
    // Build DELETE with automatic tenant scoping + soft delete
    $delete = build_tenant_delete('sales', [
        'id = :id' => $saleId
    ], true); // true = soft delete
    
    $stmt = $pdo->prepare($delete['sql']);
    $stmt->execute($delete['params']);
    
    return $stmt->rowCount();
}

// ===========================================
// EXAMPLE 5: Get Single Sale with Access Check
// ===========================================
function getSale($saleId) {
    global $pdo;
    
    // Build query with automatic scoping
    $query = build_tenant_query('sales', '*', ['id = :id' => $saleId], 's');
    
    $stmt = $pdo->prepare($query['sql']);
    $stmt->execute($query['params']);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        throw new Exception('Sale not found or access denied');
    }
    
    // Double-check access (optional, for sensitive operations)
    if (!can_access('sales', $sale)) {
        throw new SecurityException('Access denied to this sale');
    }
    
    return $sale;
}

// ===========================================
// EXAMPLE 6: Admin Report (Cross-Branch)
// ===========================================
function getAdminSalesReport($dateFrom, $dateTo) {
    global $pdo;
    
    $context = TenantContext::getInstance();
    
    if ($context->isSuperAdmin()) {
        // Admin: See all branches for this tenant
        $sql = "SELECT 
                    b.name as branch_name,
                    COUNT(*) as total_sales,
                    SUM(s.total) as total_revenue,
                    AVG(s.total) as avg_sale
                FROM sales s
                JOIN branches b ON s.branch_id = b.id
                WHERE s.tenant_id = :tenant_id
                AND s.created_at BETWEEN :date_from AND :date_to
                AND s.deleted_at IS NULL
                GROUP BY s.branch_id
                ORDER BY total_revenue DESC";
        
        $params = [
            ':tenant_id' => $context->getCompanyId(),
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ];
    } else {
        // Regular user: Current branch only (automatic scope)
        $scope = tenant_where_pdo('sales', 's');
        
        $sql = "SELECT 
                    COUNT(*) as total_sales,
                    SUM(s.total) as total_revenue,
                    AVG(s.total) as avg_sale
                FROM sales s
                WHERE {$scope['where']}
                AND s.created_at BETWEEN :date_from AND :date_to
                AND s.deleted_at IS NULL";
        
        $params = array_merge($scope['params'], [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ]);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===========================================
// EXAMPLE 7: Complex Dashboard Query
// ===========================================
function getDashboardData() {
    global $pdo;
    
    $scope = tenant_where_pdo('sales', 's');
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    
    // Today's sales
    $sql1 = "SELECT COALESCE(SUM(total), 0) as today_sales, COUNT(*) as today_count
             FROM sales s
             WHERE {$scope['where']}
             AND DATE(s.created_at) = :today
             AND s.status = 'completed'";
    
    $stmt = $pdo->prepare($sql1);
    $stmt->execute(array_merge($scope['params'], [':today' => $today]));
    $todayData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // This month's sales
    $sql2 = "SELECT COALESCE(SUM(total), 0) as month_sales
             FROM sales s
             WHERE {$scope['where']}
             AND s.created_at >= :month_start
             AND s.status = 'completed'";
    
    $stmt = $pdo->prepare($sql2);
    $stmt->execute(array_merge($scope['params'], [':month_start' => $monthStart]));
    $monthData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Low stock products (also scoped)
    $productScope = tenant_where_pdo('products', 'p');
    $sql3 = "SELECT COUNT(*) as low_stock_count
             FROM products p
             WHERE {$productScope['where']}
             AND p.stock_quantity <= p.reorder_level";
    
    $stmt = $pdo->prepare($sql3);
    $stmt->execute($productScope['params']);
    $stockData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'today_sales' => $todayData['today_sales'],
        'today_count' => $todayData['today_count'],
        'month_sales' => $monthData['month_sales'],
        'low_stock_count' => $stockData['low_stock_count']
    ];
}

// ===========================================
// EXAMPLE 8: API Response with Error Handling
// ===========================================
header('Content-Type: application/json');

try {
    $context = get_tenant_context();
    
    if (!$context['tenant_id'] || !$context['branch_id']) {
        throw new Exception('Missing tenant context');
    }
    
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            $sales = getSalesList($_GET);
            echo json_encode([
                'success' => true,
                'data' => $sales,
                'context' => $context
            ]);
            break;
            
        case 'get':
            $sale = getSale($_GET['id']);
            echo json_encode([
                'success' => true,
                'data' => $sale
            ]);
            break;
            
        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);
            $saleId = createSale($input['sale'], $input['items']);
            echo json_encode([
                'success' => true,
                'sale_id' => $saleId
            ]);
            break;
            
        case 'update':
            $input = json_decode(file_get_contents('php://input'), true);
            $affected = updateSale($input['id'], $input['data']);
            echo json_encode([
                'success' => true,
                'affected_rows' => $affected
            ]);
            break;
            
        case 'delete':
            $affected = deleteSale($_GET['id']);
            echo json_encode([
                'success' => true,
                'affected_rows' => $affected
            ]);
            break;
            
        case 'report':
            $report = getAdminSalesReport($_GET['date_from'], $_GET['date_to']);
            echo json_encode([
                'success' => true,
                'data' => $report
            ]);
            break;
            
        case 'dashboard':
            $data = getDashboardData();
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        default:
            throw new Exception('Unknown action');
    }
    
} catch (SecurityException $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
