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

// Function to geocode address with improved accuracy
function geocodeAddressImproved($address) {
    // Clean and improve the address for better geocoding
    $cleanAddress = cleanAddressForGeocoding($address);
    
    // Try multiple geocoding strategies for better accuracy
    $strategies = [
        // Strategy 1: Full address with street details
        $cleanAddress,
        // Strategy 2: Address with "Iloilo, Philippines" suffix
        $cleanAddress . ", Iloilo, Philippines",
        // Strategy 3: Simplified address (remove zone numbers if causing issues)
        simplifyAddress($cleanAddress),
        // Strategy 4: Just municipality + Iloilo
        getMunicipalityFromAddress($cleanAddress) . ", Iloilo, Philippines"
    ];
    
    foreach ($strategies as $strategy) {
        $result = tryGeocodeStrategy($strategy);
        if ($result) {
            // Add zone-specific offset to avoid identical coordinates
            $result = addZoneOffset($result, $address);
            return $result;
        }
    }
    
    return null;
}

function cleanAddressForGeocoding($address) {
    // Remove common problematic characters and clean up
    $address = trim($address);
    $address = str_replace(['NULL', 'null', 'Null'], '', $address);
    $address = preg_replace('/\s+/', ' ', $address); // Multiple spaces to single space
    
    return $address;
}

function simplifyAddress($address) {
    // Remove zone numbers and simplify for better geocoding
    $simplified = preg_replace('/Zone \d+/', '', $address);
    $simplified = preg_replace('/\s+/', ' ', $simplified);
    return trim($simplified);
}

function getMunicipalityFromAddress($address) {
    // Extract municipality from address
    $municipalities = [
        'Santa Barbara', 'Zarraga', 'Pototan', 'Lemery', 'Passi', 'Oton', 'Jaro', 'Iloilo'
    ];
    
    foreach ($municipalities as $municipality) {
        if (stripos($address, $municipality) !== false) {
            return $municipality;
        }
    }
    
    return 'Iloilo';
}

function tryGeocodeStrategy($address) {
    $encodedAddress = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedAddress}&countrycodes=ph&limit=5&addressdetails=1";
    
    // Set user agent to avoid 403 errors
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: RedCrossBloodBank/1.0',
                'Accept: application/json'
            ],
            'timeout' => 15
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
    
    // Filter and prioritize results
    $bestResult = findBestGeocodingResult($data, $address);
    
    if ($bestResult) {
        return [
            'lat' => floatval($bestResult['lat']),
            'lng' => floatval($bestResult['lon']),
            'display_name' => $bestResult['display_name'],
            'source' => 'geocoded'
        ];
    }
    
    return null;
}

function findBestGeocodingResult($results, $originalAddress) {
    // Prioritize results with better address matching
    $scoredResults = [];
    
    foreach ($results as $result) {
        $score = 0;
        $displayName = strtolower($result['display_name']);
        $originalLower = strtolower($originalAddress);
        
        // Score based on address components
        if (stripos($displayName, 'iloilo') !== false) $score += 10;
        if (stripos($displayName, 'philippines') !== false) $score += 5;
        
        // Check for street name matches
        $streetWords = explode(' ', $originalAddress);
        foreach ($streetWords as $word) {
            if (strlen($word) > 3 && stripos($displayName, $word) !== false) {
                $score += 3;
            }
        }
        
        // Prefer results with higher importance (more detailed)
        if (isset($result['importance'])) {
            $score += $result['importance'] * 100;
        }
        
        $scoredResults[] = ['result' => $result, 'score' => $score];
    }
    
    // Sort by score and return best result
    usort($scoredResults, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return !empty($scoredResults) ? $scoredResults[0]['result'] : $results[0];
}

function addZoneOffset($result, $originalAddress) {
    // Add small random offset based on zone to avoid identical coordinates
    if (preg_match('/Zone (\d+)/', $originalAddress, $matches)) {
        $zone = intval($matches[1]);
        
        // Add small offset based on zone number (in degrees)
        // ~100-200m per zone to make them visually distinct
        $offsetLat = ($zone - 1) * 0.002; // ~200m per zone
        $offsetLng = ($zone - 1) * 0.002;
        
        $result['lat'] += $offsetLat;
        $result['lng'] += $offsetLng;
        
        // Add some randomness to make it more realistic
        $result['lat'] += (rand(-50, 50) / 100000); // ±0.5m random offset
        $result['lng'] += (rand(-50, 50) / 100000);
    }
    
    return $result;
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
    
    // Geocode the address with improved method
    $coords = geocodeAddressImproved($address);
    
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
            error_log("Stored improved coordinates for donor {$donorId}: {$lat}, {$lng} ({$address})");
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
