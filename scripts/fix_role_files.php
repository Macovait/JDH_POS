<?php
/**
 * Fix role files for multi-tenancy and soft deletes
 */

$files = [
    'c:/xampp/htdocs/JDH_POS/public/users/roles/delete.php',
    'c:/xampp/htdocs/JDH_POS/public/users/roles/create.php',
    'c:/xampp/htdocs/JDH_POS/public/users/roles/update.php',
    'c:/xampp/htdocs/JDH_POS/public/users/roles/duplicate.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $original = $content;
    
    // Add tenant_id after get_db_connection()
    if (strpos($content, '$tenant_id') === false) {
        $content = str_replace(
            "\$pdo = get_db_connection();",
            "\$pdo = get_db_connection();\n\$tenant_id = function_exists('get_current_tenant_id') ? (int)get_current_tenant_id() : (int)(\$_SESSION['tenant_id'] ?? 0);",
            $content
        );
    }
    
    // Fix delete.php - soft delete instead of hard delete
    if (basename($file) === 'delete.php') {
        // Fix SELECT for role name
        $content = str_replace(
            "SELECT name FROM roles WHERE id = ?",
            "SELECT name FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",
            $content
        );
        
        // Fix user_roles count
        $content = str_replace(
            "SELECT COUNT(*) FROM user_roles WHERE role_id = ?",
            "SELECT COUNT(*) FROM user_roles WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)",
            $content
        );
        
        // Fix users count
        $content = str_replace(
            "SELECT COUNT(*) FROM users WHERE role_id = ?",
            "SELECT COUNT(*) FROM users WHERE role_id = ? AND tenant_id = ? AND deleted_at IS NULL",
            $content
        );
        
        // Fix hard delete to soft delete
        $content = str_replace(
            "DELETE FROM roles WHERE id = ?",
            "UPDATE roles SET deleted_at = NOW() WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",
            $content
        );
        
        // Fix execute for role name check
        $content = str_replace(
            "\$stmt->execute([\$id]);\n    \$role_name = \$stmt->fetchColumn();",
            "\$stmt->execute([\$id, \$tenant_id]);\n    \$role_name = \$stmt->fetchColumn();",
            $content
        );
        
        // Fix execute for user_roles check
        $content = str_replace(
            "\$check->execute([\$id]);\n        \$user_count = \$check->fetchColumn();\n    } elseif (\$has_users_role_id) {",
            "\$check->execute([\$id, \$tenant_id]);\n        \$user_count = \$check->fetchColumn();\n    } elseif (\$has_users_role_id) {",
            $content
        );
        
        // Fix execute for users check
        $content = str_replace(
            "\$check->execute([\$id]);\n        \$user_count = \$check->fetchColumn();\n    }\n\n    if (\$user_count > 0) {",
            "\$check->execute([\$id, \$tenant_id]);\n        \$user_count = \$check->fetchColumn();\n    }\n\n    if (\$user_count > 0) {",
            $content
        );
        
        // Fix execute for soft delete
        $content = str_replace(
            "// Delete role\n    \$stmt = \$pdo->prepare('UPDATE roles SET deleted_at = NOW() WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');\n    \$stmt->execute([\$id]);",
            "// Soft delete role\n    \$stmt = \$pdo->prepare('UPDATE roles SET deleted_at = NOW() WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL');\n    \$stmt->execute([\$id, \$tenant_id]);",
            $content
        );
    }
    
    // Fix create.php
    if (basename($file) === 'create.php') {
        // Fix role existence check
        $content = str_replace(
            "SELECT id FROM roles WHERE name = ?",
            "SELECT id FROM roles WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",
            $content
        );
        
        // Fix INSERT roles
        $content = str_replace(
            "INSERT INTO roles (name, description, created_at) VALUES (?, ?, NOW())",
            "INSERT INTO roles (name, description, tenant_id, is_system, deleted_at) VALUES (?, ?, ?, 0, NULL)",
            $content
        );
        
        // Fix role_permissions INSERT
        $content = str_replace(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            "INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)",
            $content
        );
        
        // Fix execute for role check
        $content = str_replace(
            "\$check->execute([\$name]);",
            "\$check->execute([\$name, \$tenant_id]);",
            $content
        );
        
        // Fix execute for role insert
        $content = str_replace(
            "\$stmt->execute([\$name, \$description]);",
            "\$stmt->execute([\$name, \$description, \$tenant_id]);",
            $content
        );
        
        // Fix execute for permissions insert
        $content = str_replace(
            "\$stmt->execute([\$role_id, \$perm_id]);",
            "\$stmt->execute([\$role_id, \$perm_id, \$tenant_id]);",
            $content
        );
    }
    
    // Fix update.php
    if (basename($file) === 'update.php') {
        // Fix role existence check
        $content = str_replace(
            "SELECT id FROM roles WHERE name = ? AND id != ?",
            "SELECT id FROM roles WHERE name = ? AND id != ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",
            $content
        );
        
        // Fix UPDATE roles
        $content = str_replace(
            "UPDATE roles SET name = ?, description = ? WHERE id = ?",
            "UPDATE roles SET name = ?, description = ? WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",
            $content
        );
        
        // Fix DELETE role_permissions
        $content = str_replace(
            "DELETE FROM role_permissions WHERE role_id = ?",
            "DELETE FROM role_permissions WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)",
            $content
        );
        
        // Fix INSERT role_permissions
        $content = str_replace(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            "INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)",
            $content
        );
        
        // Fix execute for role check
        $content = str_replace(
            "\$check->execute([\$name, \$id]);",
            "\$check->execute([\$name, \$id, \$tenant_id]);",
            $content
        );
        
        // Fix execute for role update
        $content = str_replace(
            "\$stmt->execute([\$name, \$description, \$id]);",
            "\$stmt->execute([\$name, \$description, \$id, \$tenant_id]);",
            $content
        );
        
        // Fix execute for permissions delete
        $content = str_replace(
            "\$stmt->execute([\$id]);\n\n    // Insert new permissions",
            "\$stmt->execute([\$id, \$tenant_id]);\n\n    // Insert new permissions",
            $content
        );
        
        // Fix execute for permissions insert
        $content = str_replace(
            "\$stmt->execute([\$id, \$perm_id]);",
            "\$stmt->execute([\$id, \$perm_id, \$tenant_id]);",
            $content
        );
    }
    
    // Fix duplicate.php
    if (basename($file) === 'duplicate.php') {
        // Fix role existence check
        $content = str_replace(
            "SELECT id FROM roles WHERE name = ?",
            "SELECT id FROM roles WHERE name = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",
            $content
        );
        
        // Fix SELECT original role
        $content = str_replace(
            "SELECT description FROM roles WHERE id = ?",
            "SELECT description FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND deleted_at IS NULL",
            $content
        );
        
        // Fix SELECT permissions
        $content = str_replace(
            "SELECT permission_id FROM role_permissions WHERE role_id = ?",
            "SELECT permission_id FROM role_permissions WHERE role_id = ? AND (tenant_id = ? OR tenant_id IS NULL)",
            $content
        );
        
        // Fix INSERT roles
        $content = str_replace(
            "INSERT INTO roles (name, description, created_at) VALUES (?, ?, NOW())",
            "INSERT INTO roles (name, description, tenant_id, is_system, deleted_at) VALUES (?, ?, ?, 0, NULL)",
            $content
        );
        
        // Fix INSERT role_permissions
        $content = str_replace(
            "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
            "INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)",
            $content
        );
        
        // Fix execute for role name check
        $content = str_replace(
            "\$check->execute([\$new_name]);",
            "\$check->execute([\$new_name, \$tenant_id]);",
            $content
        );
        
        // Fix execute for select original role
        $content = str_replace(
            "\$stmt->execute([\$id]);\n    \$original = \$stmt->fetch();\n\n    // Get original permissions",
            "\$stmt->execute([\$id, \$tenant_id]);\n    \$original = \$stmt->fetch();\n\n    // Get original permissions",
            $content
        );
        
        // Fix execute for select permissions
        $content = str_replace(
            "\$stmt->execute([\$id]);\n    \$permissions = \$stmt->fetchAll(PDO::FETCH_COLUMN);",
            "\$stmt->execute([\$id, \$tenant_id]);\n    \$permissions = \$stmt->fetchAll(PDO::FETCH_COLUMN);",
            $content
        );
        
        // Fix execute for insert role
        $content = str_replace(
            "\$stmt->execute([\$new_name, \$original['description'] ?? 'Copy of existing role']);",
            "\$stmt->execute([\$new_name, \$original['description'] ?? 'Copy of existing role', \$tenant_id]);",
            $content
        );
        
        // Fix execute for insert permissions
        $content = str_replace(
            "\$stmt->execute([\$new_id, \$perm_id]);",
            "\$stmt->execute([\$new_id, \$perm_id, \$tenant_id]);",
            $content
        );
    }
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Fixed: $file\n";
    } else {
        echo "No changes needed: $file\n";
    }
}

echo "\nDone!\n";
