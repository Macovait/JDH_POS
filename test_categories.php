<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/JDH_POS/public/api/v1/store/categories');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Tenant-ID: 1']);
$response = curl_exec($ch);
curl_close($ch);
echo $response;
