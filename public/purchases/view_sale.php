<?php
/**
 * View Sale — redirect wrapper to the POS sale viewer
 */
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sale_id) {
    header('Location: list_draft.php');
    exit;
}
$qs = http_build_query(array_intersect_key($_GET, array_flip(['id','msg'])));
header('Location: ../pos/view_sale.php' . ($qs ? '?' . $qs : ''));
exit;
