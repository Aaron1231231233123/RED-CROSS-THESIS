<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to geocode address using Nominatim
function geocodeAddress($address) {
    $encodedAddress = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedAddress}&countrycodes=ph&limit=1";
    
    // Set user agent to avoid 403 errors
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: RedCrossBloodBank/1.0',
                'Accept: application/json'
            ],
            'timeout' => 10
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        return null;
    }
    
    // Filter results to prioritize Iloilo locations
    $iloiloResults = array_filter($data, function($result) {
        return stripos($result['display_name'], 'iloilo') !== false;
    });
    
    $result = !empty($iloiloResults) ? reset($iloiloResults) : $data[0];
    
    return [
        'lat' => floatval($result['lat']),
        'lng' => floatval($result['lon']),
        'display_name' => $result['display_name'],
        'source' => 'geocoded'
    ];
}

// Function to get coordinates with caching and database storage
function getCoordinatesWithCache($address, $donorId = null) {
    $cacheFile = sys_get_temp_dir() . '/geocode_cache_' . md5($address) . '.json';
    
    // Check cache first
    if (file_exists($cacheFile)) {
        $cacheAge = time() - filemtime($cacheFile);
        if ($cacheAge < 86400) { // 24 hours cache
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                // Store in database if donor ID provided
                if ($donorId && $cached['lat'] && $cached['lng']) {
                    storeCoordinatesInDatabase($donorId, $cached['lat'], $cached['lng'], $address);
                }
                return $cached;
            }
        }
    }
    
    // Geocode the address
    $coords = geocodeAddress($address);
    
    if ($coords) {
        // Cache the result
        file_put_contents($cacheFile, json_encode($coords));
        
        // Store in database if donor ID provided
        if ($donorId && $coords['lat'] && $coords['lng']) {
            storeCoordinatesInDatabase($donorId, $coords['lat'], $coords['lng'], $address);
        }
    }
    
    return $coords;
}

// Function to store coordinates in database
function storeCoordinatesInDatabase($donorId, $lat, $lng, $address) {
    include_once '../../assets/conn/db_conn.php';
    include_once '../Dashboards/module/optimized_functions.php';
    
    // Determine if this is permanent or office address based on the address content
    $isPermanent = true; // Default to permanent
    
    // Simple heuristic: if address contains "office" or "work", it's office address
    if (stripos($address, 'office') !== false || stripos($address, 'work') !== false) {
        $isPermanent = false;
    }
    
    try {
        // Use Supabase API to update coordinates
        $updateData = [];
        if ($isPermanent) {
            $updateData['permanent_latitude'] = $lat;
            $updateData['permanent_longitude'] = $lng;
        } else {
            $updateData['office_latitude'] = $lat;
            $updateData['office_longitude'] = $lng;
        }
        
        // Update via Supabase API
        $response = supabaseRequest("donor_form?donor_id=eq.{$donorId}", 'PATCH', $updateData);
        
        if ($response && !isset($response['error'])) {
            error_log("Stored coordinates for donor {$donorId}: {$lat}, {$lng} ({$address})");
        } else {
            error_log("Failed to store coordinates for donor {$donorId}: " . json_encode($response));
        }
    } catch (Exception $e) {
        error_log("Error storing coordinates for donor {$donorId}: " . $e->getMessage());
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $address = $input['address'] ?? '';
    $donorId = $input['donor_id'] ?? null; // Optional donor ID for database storage
    
    if (empty($address)) {
        http_response_code(400);
        echo json_encode(['error' => 'Address is required']);
        exit();
    }
    
    $coords = getCoordinatesWithCache($address, $donorId);
    
    if ($coords) {
        echo json_encode($coords);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Address not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
