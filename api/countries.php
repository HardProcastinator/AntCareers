<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$apiKey = 'e9bfd1259386698f0bfba32b480a0947a6d9dc43184a294d765d2c7524d14bcf';

$ch = curl_init('https://api.countrystatecity.in/v1/countries');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-CSCAPI-KEY: ' . $apiKey,
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch countries',
        'error' => $error,
        'status' => $httpCode
    ]);
    exit;
}

echo $response;