<?php
// Redirect /pos/ to returns list
header('Location: returns/list_sell_return.php?' . $_SERVER['QUERY_STRING']);
exit;