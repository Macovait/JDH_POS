<?php
$f='c:/xampp/htdocs/JDH_POS/public/users/roles/get_role.php';
$c=file_get_contents($f);

// Add tenant_id to role_permissions join
$c=str_replace(
    '$perm_join = $has_role_permissions ? "LEFT JOIN role_permissions rp ON r.id = rp.role_id" : "";',
    '$perm_join = $has_role_permissions ? "LEFT JOIN role_permissions rp ON r.id = rp.role_id AND (rp.tenant_id = $tenant_id OR rp.tenant_id IS NULL)" : "";',
    $c
);

// Add tenant_id to SELECT
$c=str_replace(
    'SELECT r.id, r.name, r.description',
    'SELECT r.id, r.name, r.description, r.tenant_id',
    $c
);

file_put_contents($f,$c);
echo 'Done';
?>
