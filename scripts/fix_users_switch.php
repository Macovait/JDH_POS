<?php
/**
 * Fix broken switch statements in users.php
 */

$file = __DIR__ . '/../admin/users.php';
$content = file_get_contents($file);

// Fix role switch
$oldRole = "<?php switch (\$user['role']) { case 'admin': echo 'bg-purple-500/15 text-purple-300'; break; 'manager': 'bg-amber-500/15 text-amber-300'; 'staff': 'bg-sky-500/15 text-sky-300'; default: 'bg-white/10 text-slate-300'; } ?>";
$newRole = "<?php switch (\$user['role']) { case 'admin': echo 'bg-purple-500/15 text-purple-300'; break; case 'manager': echo 'bg-amber-500/15 text-amber-300'; break; case 'staff': echo 'bg-sky-500/15 text-sky-300'; break; default: echo 'bg-slate-500/15 text-slate-300'; } ?>";
$content = str_replace($oldRole, $newRole, $content);

// Fix status switch (outer)
$oldStatus = "<?php switch (\$user['status']) { case 'active': echo 'bg-green-500/15 text-green-400'; break; 'inactive': 'bg-slate-500/15 text-slate-400'; 'suspended': 'bg-red-500/15 text-red-400'; default: 'bg-white/10 text-slate-300'; } ?>";
$newStatus = "<?php switch (\$user['status']) { case 'active': echo 'bg-green-500/15 text-green-400'; break; case 'inactive': echo 'bg-slate-500/15 text-slate-400'; break; case 'suspended': echo 'bg-red-500/15 text-red-400'; break; default: echo 'bg-slate-500/15 text-slate-300'; } ?>";
$content = str_replace($oldStatus, $newStatus, $content);

// Fix dot status switch
$oldDot = "<?php switch (\$user['status']) { case 'active': echo 'bg-green-400'; break; 'inactive': 'bg-gray-400'; 'suspended': 'bg-red-400'; default: } ?>";
$newDot = "<?php switch (\$user['status']) { case 'active': echo 'bg-green-400'; break; case 'inactive': echo 'bg-slate-400'; break; case 'suspended': echo 'bg-red-400'; break; default: echo 'bg-slate-400'; } ?>";
$content = str_replace($oldDot, $newDot, $content);

// Fix hover:bg-white/[0.02] and hover:bg-white/10
$content = str_replace('hover:bg-white/[0.02]', 'hover:bg-slate-700/30', $content);
$content = str_replace('hover:bg-white/10', 'hover:bg-slate-700/50', $content);

file_put_contents($file, $content);
echo "Fixed users.php\n";
