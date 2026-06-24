<?php
$f='c:/xampp/htdocs/JDH_POS/public/users/roles/get_role.php';
$c=file_get_contents($f);

// Fix the misplaced closing brace
$c=str_replace(
    "}\n\n// Tenant isolation",
    "}\n\n// Tenant isolation",
    $c
);

// Remove the extra closing brace at line 35
$c=str_replace(
    "}\n}\n\ntry {",
    "}\n\ntry {",
    $c
);

file_put_contents($f,$c);
echo 'Fixed braces';
?>
