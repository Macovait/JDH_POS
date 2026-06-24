<?php
$f='c:/xampp/htdocs/JDH_POS/public/users/users.php';
$c=file_get_contents($f);
$c=str_replace("DELETE FROM users WHERE id = ? AND tenant_id = ?","UPDATE users SET deleted_at = NOW() WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL",$c);
$c=str_replace("SELECT id, name FROM roles WHERE tenant_id = ? OR tenant_id IS NULL","SELECT id, name FROM roles WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",$c);
$c=str_replace("SELECT COUNT(*) FROM users u WHERE u.tenant_id = ?","SELECT COUNT(*) FROM users u WHERE (u.tenant_id = ? OR u.tenant_id IS NULL) AND u.deleted_at IS NULL",$c);
$c=str_replace("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND is_active = 1","SELECT COUNT(*) FROM users WHERE (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL AND is_active = 1",$c);
file_put_contents($f,$c);
echo "Fixed\n";
