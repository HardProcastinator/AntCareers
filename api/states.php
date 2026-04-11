<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$apiKey = trim((string)(getenv('CSC_API_KEY') ?: ''));
$countryCode = trim((string)($_GET['country_code'] ?? ''));

if ($apiKey === '') {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Location service is unavailable'
    ]);
    exit;
}

if ($countryCode === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'country_code is required']);
    exit;
}

$url = 'https://api.countrystatecity.in/v1/countries/' . rawurlencode($countryCode) . '/states';

$ch = curl_init($url);
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
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch states'
    ]);
    exit;
}

echo $response;