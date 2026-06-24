<?php
$file = 'c:/xampp/htdocs/JDH_POS/public/users/advanced_permissions.php';
$content = file_get_contents($file);

// Fix role existence checks
$content = str_replace("SELECT id FROM roles WHERE name = ?", "SELECT id FROM roles WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL", $content);
$content = str_replace("SELECT id FROM roles WHERE name = ? AND id != ?", "SELECT id FROM roles WHERE name = ? AND id != ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL", $content);

// Fix hard deletes to soft deletes
$content = str_replace("DELETE FROM roles WHERE id = ?", "UPDATE roles SET deleted_at = NOW() WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL", $content);
$content = str_replace("DELETE FROM role_permissions WHERE role_id = ?", "DELETE FROM role_permissions WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)", $content);

// Fix role_permissions INSERT
$content = str_replace("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", "INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)", $content);

// Fix SELECT queries
$content = str_replace("SELECT name FROM roles WHERE id = ?", "SELECT name FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL", $content);
$content = str_replace("SELECT description FROM roles WHERE id = ?", "SELECT description FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL", $content);
$content = str_replace("SELECT permission_id FROM role_permissions WHERE role_id = ?", "SELECT permission_id FROM role_permissions WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)", $content);
$content = str_replace("SELECT COUNT(*) FROM user_roles WHERE role_id = ?", "SELECT COUNT(*) FROM user_roles WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)", $content);

// Fix FROM permissions queries
$content = str_replace("FROM permissions ORDER BY module, code", "FROM permissions WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL ORDER BY module, code", $content);
$content = str_replace("FROM role_permissions", "FROM role_permissions WHERE (tenant_id = ? OR tenant_id IS NULL)", $content);

// Add tenant_id to execute calls for single-param queries that now need 2
$content = preg_replace("/\\\$(\w+)->execute\(\[\\\$id\]\);\s*\n\s*\\\$role_name =/", "\\$$1->execute([\$id, \$tenant_id]);\n                \$role_name =", $content);

file_put_contents($file, $content);
echo "Fixed: $file\n";
