<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/countries.php';

$countries = getCountries();

// Return in the same format as the old external API for backward compatibility
$result = array_map(function($c) {
    return [
        'id'    => $c['code'],
        'name'  => $c['name'],
        'iso2'  => $c['code'],
    ];
}, $countries);

echo json_encode($result, JSON_UNESCAPED_UNICODE);