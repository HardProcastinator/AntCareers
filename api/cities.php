<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$apiKey = trim((string)(getenv('CSC_API_KEY') ?: ''));
$countryCode = trim((string)($_GET['country_code'] ?? ''));
$stateCode = trim((string)($_GET['state_code'] ?? ''));

if ($apiKey === '') {
    http_response_code(503);
    echo json_encode([]);
    exit;
}

if ($countryCode === '' || $stateCode === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'country_code and state_code are required']);
    exit;
}

$url = 'https://api.countrystatecity.in/v1/countries/' . rawurlencode($countryCode) . '/states/' . rawurlencode($stateCode) . '/cities';

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
    echo json_encode([]);
    exit;
}

$parsed = json_decode($response, true);
if (!is_array($parsed)) {
    echo json_encode([]);
    exit;
}

echo $response;