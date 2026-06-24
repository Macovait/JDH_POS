<?php
// Root redirect - forwards to the actual returns page under /pos/
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: pos/process_return.php' . $query);
exit;
