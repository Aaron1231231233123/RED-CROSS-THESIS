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
            // Validate and fix coordinates first, then add small zone offset if valid
            $result = validateAndFixCoordinates($result, $address);
            // Only add zone offset if coordinates are valid (not snapped)
            if ($result['source'] !== 'snapped') {
                $result = addZoneOffset($result, $address);
            }
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
    // Constrain search to Iloilo/Panay-Guimaras-Negros Occidental bounding box to avoid ocean centroids
    // viewbox format: minLon,minLat,maxLon,maxLat
    $viewbox = urlencode('121.70,9.80,123.60,11.60');
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedAddress}&countrycodes=ph&limit=8&addressdetails=1&viewbox={$viewbox}&bounded=1&extratags=1";
    
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
        $class = $result['class'] ?? '';
        $type = $result['type'] ?? '';
        $placeRank = intval($result['place_rank'] ?? 0);
        
        // Hard filters: skip very coarse administrative/boundary results (these often fall in the sea)
        if ($class === 'boundary' || ($type === 'administrative' && $placeRank <= 12)) {
            continue; // ignore this candidate
        }

        // STRONGLY prefer actual buildings/residential areas - avoid roads/streets
        $highlyPreferredTypes = ['house','building','residential','commercial','industrial','place','amenity'];
        $preferredTypes = ['city','town','village','hamlet','suburb','neighbourhood','yes'];
        $avoidTypes = ['highway','road','street','path','track','footway','cycleway','bridleway','steps'];
        
        // Heavily penalize road/street results (these cause "middle of road" issues)
        if (in_array($type, $avoidTypes, true) || $class === 'highway') {
            $score -= 100; // Strong penalty for roads
            continue; // Skip road results entirely
        }
        
        // Highly prefer actual buildings/houses
        if (in_array($type, $highlyPreferredTypes, true)) {
            $score += 100;
        } elseif (in_array($type, $preferredTypes, true)) {
            $score += 50;
        }
        
        if ($placeRank >= 18) { // Very fine granularity (building level)
            $score += 30;
        } elseif ($placeRank >= 16) { // Fine granularity (address level)
            $score += 20;
        }

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
    
    if (!empty($scoredResults)) {
        return $scoredResults[0]['result'];
    }
    // As an absolute fallback, still try to avoid boundary/administrative-only results
    foreach ($results as $r) {
        $class = $r['class'] ?? '';
        $type = $r['type'] ?? '';
        $placeRank = intval($r['place_rank'] ?? 0);
        if (!($class === 'boundary' || ($type === 'administrative' && $placeRank <= 12))) {
            return $r;
        }
    }
    return $results[0];
}

// Function to validate and fix coordinates (check for water, snap to valid locations)
function validateAndFixCoordinates($coords, $address) {
    $lat = $coords['lat'];
    $lng = $coords['lng'];
    
    // Define water areas (straits) to avoid
    $waterAreas = [
        ['minLat' => 10.62, 'maxLat' => 10.70, 'minLng' => 122.58, 'maxLng' => 122.65], // Guimaras Strait
        ['minLat' => 10.52, 'maxLat' => 10.66, 'minLng' => 122.75, 'maxLng' => 122.89], // Iloilo Strait
        ['minLat' => 10.25, 'maxLat' => 10.60, 'minLng' => 121.85, 'maxLng' => 122.25], // Panay Gulf (deep water)
    ];
    
    // Check if in water
    $inWater = false;
    foreach ($waterAreas as $area) {
        if ($lat >= $area['minLat'] && $lat <= $area['maxLat'] &&
            $lng >= $area['minLng'] && $lng <= $area['maxLng']) {
            $inWater = true;
            break;
        }
    }
    
    // Municipality centers for snapping
    $municipalityCenters = [
        'Iloilo City' => [10.7202, 122.5621],
        'Oton' => [10.6933, 122.4733],
        'Pavia' => [10.7750, 122.5444],
        'Leganes' => [10.7833, 122.5833],
        'Santa Barbara' => [10.8167, 122.5333],
        'San Miguel' => [10.7833, 122.4667],
        'Cabatuan' => [10.8833, 122.4833],
        'Maasin' => [10.8833, 122.4333],
        'Janiuay' => [10.9500, 122.5000],
        'Pototan' => [10.9500, 122.6333],
        'Dumangas' => [10.8333, 122.7167],
        'Zarraga' => [10.8167, 122.6000],
        'New Lucena' => [10.8833, 122.6000],
        'Alimodian' => [10.8167, 122.4333],
        'Leon' => [10.7833, 122.3833],
        'Tubungan' => [10.7833, 122.3333],
        'Mina' => [10.9333, 122.5833],
        'Barotac Nuevo' => [10.9000, 122.7000],
        'Barotac Viejo' => [11.0500, 122.8500],
        'Bingawan' => [11.2333, 122.5667],
        'Calinog' => [11.1167, 122.5000],
        'Carles' => [11.5667, 123.1333],
        'Concepcion' => [11.2167, 123.1167],
        'Dingle' => [11.0000, 122.6667],
        'Dueñas' => [11.0667, 122.6167],
        'Estancia' => [11.4500, 123.1500],
        'Guimbal' => [10.6667, 122.3167],
        'Igbaras' => [10.7167, 122.2667],
        'Javier' => [11.0833, 122.5667],
        'Lambunao' => [11.0500, 122.4667],
        'Miagao' => [10.6333, 122.2333],
        'Passi' => [11.1167, 122.6333],
        'San Dionisio' => [11.2667, 123.0833],
        'San Enrique' => [11.1000, 122.6667],
        'San Joaquin' => [10.5833, 122.1333],
        'San Rafael' => [11.1833, 122.8333],
        'Sara' => [11.2500, 123.0167],
        'Tigbauan' => [10.6833, 122.3667],
        'Jordan' => [10.6581, 122.5969],
        'Buenavista' => [10.6736, 122.6137],
        'Nueva Valencia' => [10.5343, 122.5935],
        'San Lorenzo' => [10.6108, 122.7027],
        'Sibunag' => [10.5157, 122.6911],
        'Bacolod' => [10.6765, 122.9509],
        'Talisay' => [10.7363, 122.9672],
        'Silay' => [10.7938, 122.9780],
        'Bago' => [10.5336, 122.8410],
        'La Carlota' => [10.4244, 122.9199],
        'Valladolid' => [10.4598, 122.8374]
    ];
    
    // Calculate distance to nearest city
    $minDist = PHP_FLOAT_MAX;
    $nearestCity = null;
    $nearestCoords = null;
    
    foreach ($municipalityCenters as $city => $center) {
        $d = haversineDistance($lat, $lng, $center[0], $center[1]);
        if ($d < $minDist) {
            $minDist = $d;
            $nearestCity = $city;
            $nearestCoords = $center;
        }
    }
    
    // If in water OR too far from any city (>30km), snap to nearest city from address
    if ($inWater || $minDist > 30) {
        // Try to find city from address first
        $addressLower = strtolower($address);
        foreach ($municipalityCenters as $city => $center) {
            if (stripos($addressLower, strtolower($city)) !== false) {
                $coords['lat'] = $center[0];
                $coords['lng'] = $center[1];
                $coords['display_name'] = $city . ', ' . (stripos($city, 'Guimaras') !== false ? 'Guimaras' : (stripos($city, 'Bacolod') !== false || stripos($city, 'Talisay') !== false || stripos($city, 'Silay') !== false ? 'Negros Occidental' : 'Iloilo')) . ', Philippines';
                $coords['source'] = 'snapped';
                return $coords;
            }
        }
        
        // Otherwise snap to nearest city
        if ($nearestCoords) {
            $coords['lat'] = $nearestCoords[0];
            $coords['lng'] = $nearestCoords[1];
            $coords['display_name'] = $nearestCity . ', Philippines (snapped)';
            $coords['source'] = 'snapped';
            return $coords;
        }
    }
    
    return $coords;
}

// Helper function for distance calculation
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Earth radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

function addZoneOffset($result, $originalAddress) {
    // Add very small offset based on zone to avoid identical coordinates
    // Reduced offset to prevent pushing coordinates into water or roads
    if (preg_match('/Zone (\d+)/', $originalAddress, $matches)) {
        $zone = intval($matches[1]);
        
        // Much smaller offset: ~50m per zone (reduced from 200m)
        $offsetLat = ($zone - 1) * 0.0005; // ~50m per zone
        $offsetLng = ($zone - 1) * 0.0005;
        
        $result['lat'] += $offsetLat;
        $result['lng'] += $offsetLng;
        
        // Very small random offset: ±0.2m (reduced from ±0.5m)
        $result['lat'] += (rand(-20, 20) / 100000);
        $result['lng'] += (rand(-20, 20) / 100000);
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
