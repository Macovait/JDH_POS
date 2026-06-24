<?php
// Test the storefront API directly
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/JDH_POS/public/api/v1/store/tenant');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Tenant-ID: 1']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response:\n";
    echo $response;
}
