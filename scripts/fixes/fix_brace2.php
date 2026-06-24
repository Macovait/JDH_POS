<?php
$f='c:/xampp/htdocs/JDH_POS/public/users/roles/get_role.php';
$lines=file($f);

// The file has the closing brace at the wrong position
// Line 23 should have } but it's empty
// Line 35 has } but should be removed (the tenant check is standalone, not inside an if)

$newLines=[];
foreach($lines as $i=>$line){
    $lineNum=$i+1; // 1-indexed
    
    // Line 23 (index 22) - add the closing brace for role_id check
    if($lineNum==23){
        $newLines[]="}\n"; // Add closing brace
        continue;
    }
    
    // Line 35 (index 34) - skip the extra closing brace
    if($lineNum==35 && trim($line)==='}'){
        continue; // Skip this line
    }
    
    $newLines[]=$line;
}

file_put_contents($f,implode('',$newLines));
echo 'Fixed brace position';
?>
