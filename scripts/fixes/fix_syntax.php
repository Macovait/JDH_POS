<?php
$f = 'c:/xampp/htdocs/JDH_POS/public/products/product_form.php';
$c = file_get_contents($f);
// Remove the broken line
$c = str_replace("\n =  > 0;\n", "\n", $c);
file_put_contents($f, $c);
echo "Fixed syntax error";
?>
