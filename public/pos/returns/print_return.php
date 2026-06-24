<?php
// Redirect to the correct returns print page
header('Location: view_return.php?' . $_SERVER['QUERY_STRING']);
exit;
