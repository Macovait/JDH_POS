<?php
$f='c:/xampp/htdocs/JDH_POS/public/users/roles/get_role.php';
$c=file_get_contents($f);

$old="if (\$role_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(null);
    exit;
}

try {";

$new="if (\$role_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid role ID']);
    exit;
}

// Tenant isolation - get current tenant
\$tenant_id = function_exists('get_current_tenant_id') ? (int) get_current_tenant_id() : 0;

// Verify role belongs to this tenant (security check)
\$check_stmt = \$pdo->prepare(\"SELECT id FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL\");
\$check_stmt->execute([\$role_id, \$tenant_id]);
if (!\$check_stmt->fetch()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied - role not found']);
    exit;
}

try {";

$c=str_replace($old,$new,$c);
file_put_contents($f,$c);
echo 'Done';
?>
