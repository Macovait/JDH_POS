<?php
// Redirect to the canonical process return page
$sale_id = isset($_GET['sale_id']) ? (int) $_GET['sale_id'] : 0;
if ($sale_id > 0) {
    header('Location: return_sale.php?id=' . $sale_id);
} else {
    header('Location: select_sale_for_return.php');
}
exit;
