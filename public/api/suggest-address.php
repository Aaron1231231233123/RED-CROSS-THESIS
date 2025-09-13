<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Query q is required']);
    exit();
}

$encoded = urlencode($q);
$url = "https://nominatim.openstreetmap.org/search?format=json&q={$encoded}&countrycodes=ph&limit=8&addressdetails=1";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'User-Agent: RedCrossBloodBank/1.0 (+https://example.org)',
            'Accept: application/json'
        ],
        'timeout' => 15
    ]
]);

$resp = @file_get_contents($url, false, $context);
if ($resp === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream geocoder unavailable']);
    exit();
}

$data = json_decode($resp, true);
if (!is_array($data)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response']);
    exit();
}

$suggestions = [];
foreach ($data as $row) {
    $addr = $row['address'] ?? [];
    $suggestions[] = [
        'display_name' => $row['display_name'] ?? '',
        'lat' => isset($row['lat']) ? floatval($row['lat']) : null,
        'lng' => isset($row['lon']) ? floatval($row['lon']) : null,
        'address' => [
            'house_number' => $addr['house_number'] ?? '',
            'road' => $addr['road'] ?? '',
            'neighbourhood' => $addr['neighbourhood'] ?? '',
            'suburb' => $addr['suburb'] ?? '',
            'village' => $addr['village'] ?? '',
            'barangay' => $addr['barangay'] ?? ($addr['suburb'] ?? ($addr['village'] ?? '')),
            'city' => $addr['city'] ?? ($addr['town'] ?? ($addr['municipality'] ?? '')),
            'province' => $addr['province'] ?? ($addr['state'] ?? ''),
            'postcode' => $addr['postcode'] ?? ''
        ]
    ];
}

echo json_encode(['results' => $suggestions]);
?>


