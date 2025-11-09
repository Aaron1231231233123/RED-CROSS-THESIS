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
function normalizeMunicipalityToken($text) {
    if ($text === null) {
        return '';
    }
    $token = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $token = strtolower($token);
    $token = preg_replace('/[^a-z0-9\s]/', ' ', $token);
    $token = preg_replace('/\s+/', ' ', $token);
    return trim($token);
}

function getAllowedIloiloMunicipalities() {
    return [
        'Ajuy','Alimodian','Anilao','Banate','Barotac Nuevo','Barotac Viejo','Batad','Bingawan','Cabatuan','Calinog',
        'Carles','Concepcion','Dingle','Dueñas','Dumangas','Estancia','Guimbal','Igbaras','Janiuay','Lambunao',
        'Leganes','Leon','Maasin','Mina','Miagao','New Lucena','Oton','Passi','Pavia','Pototan','San Dionisio',
        'San Enrique','San Joaquin','San Miguel','Santa Barbara','Sara','Tigbauan','Tubungan','Zarraga'
    ];
}

function getMunicipalityAliasMap() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [];
    foreach (getAllowedIloiloMunicipalities() as $name) {
        $map[normalizeMunicipalityToken($name)] = $name;
    }
    $aliases = [
        'passi city' => 'Passi',
        'city of passi' => 'Passi',
        'municipality of passi' => 'Passi',
        'san miguel iloilo' => 'San Miguel',
        'municipality of san miguel' => 'San Miguel',
        'municipality of oton' => 'Oton',
        'municipality of sara' => 'Sara',
        'municipality of banate' => 'Banate',
        'municipality of anilao' => 'Anilao'
    ];
    foreach ($aliases as $alias => $canonical) {
        $map[normalizeMunicipalityToken($alias)] = $canonical;
    }
    return $map;
}

function resolveMunicipalityFromText($text) {
    if (!$text) {
        return null;
    }
    $token = normalizeMunicipalityToken($text);
    $aliasMap = getMunicipalityAliasMap();
    if (isset($aliasMap[$token])) {
        return $aliasMap[$token];
    }
    foreach ($aliasMap as $aliasToken => $city) {
        if ($aliasToken !== '' && strpos($token, $aliasToken) !== false) {
            return $city;
        }
    }
    return null;
}

function extractAddressComponents($address) {
    $components = [
        'street' => null,
        'barangay' => null,
        'city' => null,
        'province' => null,
        'postalcode' => null,
        'country' => 'Philippines'
    ];
    if (!$address) {
        return $components;
    }
    $parts = array_map('trim', explode(',', $address));
    foreach ($parts as $index => $part) {
        if ($components['postalcode'] === null && preg_match('/\b\d{4}\b/', $part, $match)) {
            $components['postalcode'] = $match[0];
            $part = trim(str_replace($match[0], '', $part));
        }
        if ($components['barangay'] === null && preg_match('/\b(barangay|brgy\.?|zone)\b/i', $part)) {
            $cleaned = preg_replace('/\b(barangay|brgy\.?|zone)\b[:.]?/i', '', $part);
            $components['barangay'] = trim($cleaned);
            continue;
        }
        $cityCandidate = resolveMunicipalityFromText($part);
        if ($components['city'] === null && $cityCandidate) {
            $components['city'] = $cityCandidate;
            continue;
        }
        if ($components['province'] === null && stripos($part, 'iloilo') !== false) {
            $components['province'] = 'Iloilo';
            continue;
        }
        if ($components['street'] === null && $part !== '') {
            $components['street'] = $part;
        }
    }
    if ($components['city'] === null) {
        $resolved = resolveMunicipalityFromText($address);
        if ($resolved) {
            $components['city'] = $resolved;
        }
    }
    if ($components['province'] === null) {
        $components['province'] = 'Iloilo';
    }
    return $components;
}

function geocodeAddressImproved($address) {
    // Clean and improve the address for better geocoding
    $cleanAddress = cleanAddressForGeocoding($address);

    // Try multiple geocoding strategies for better accuracy
    $components = extractAddressComponents($cleanAddress);
    $strategies = [];
    $seenStrategies = [];

    $pushStrategy = function($strategy) use (&$strategies, &$seenStrategies) {
        $key = json_encode($strategy);
        if (!isset($seenStrategies[$key])) {
            $strategies[] = $strategy;
            $seenStrategies[$key] = true;
        }
    };

    if (!empty($components['city']) || !empty($components['street']) || !empty($components['barangay'])) {
        $pushStrategy([
            'type' => 'structured',
            'components' => $components
        ]);
        if (!empty($components['street'])) {
            $componentsWithoutStreet = $components;
            $componentsWithoutStreet['street'] = null;
            $pushStrategy([
                'type' => 'structured',
                'components' => $componentsWithoutStreet
            ]);
        }
    }

    $addressVariants = [
        $cleanAddress,
        $cleanAddress . ", Iloilo, Philippines",
        simplifyAddress($cleanAddress),
        getMunicipalityFromAddress($cleanAddress) . ", Iloilo, Philippines"
    ];

    foreach ($addressVariants as $candidate) {
        if (!empty($candidate)) {
            $pushStrategy([
                'type' => 'free',
                'query' => $candidate
            ]);
        }
    }

    $barangayCityTail = [];
    if (!empty($components['barangay'])) {
        $barangayCityTail[] = $components['barangay'];
    }
    if (!empty($components['city'])) {
        $barangayCityTail[] = $components['city'];
    }
    if (!empty($components['province'])) {
        $barangayCityTail[] = $components['province'];
    }
    if (!empty($components['country'])) {
        $barangayCityTail[] = $components['country'];
    }
    if (!empty($barangayCityTail)) {
        $pushStrategy([
            'type' => 'free',
            'query' => implode(', ', $barangayCityTail)
        ]);
    }

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
        'Santa Barbara', 'Zarraga', 'Pototan', 'Lemery', 'Passi', 'Oton', 'Jaro', 'Iloilo',
        'Kalibo', 'Numancia', 'Batan', 'Altavas', 'New Washington',
        'Roxas City', 'Maayon', 'Dumarao', 'Ivisan', 'Pilar',
        'San Jose de Buenavista', 'Hamtic', 'Patnongon', 'Culasi', 'Sibalom'
    ];
    
    foreach ($municipalities as $municipality) {
        if (stripos($address, $municipality) !== false) {
            return $municipality;
        }
    }
    
    return 'Iloilo';
}

function tryGeocodeStrategy($strategy) {
    $baseParams = [
        'format' => 'json',
        'limit' => 8,
        'addressdetails' => 1,
        'countrycodes' => 'ph',
        'extratags' => 1
    ];
    $viewbox = '121.50,9.80,123.40,11.60';
    if (!is_array($strategy) || empty($strategy['type'])) {
        return null;
    }
    $params = $baseParams;
    $params['viewbox'] = $viewbox;
    $params['bounded'] = 1;

    if ($strategy['type'] === 'structured') {
        $components = $strategy['components'] ?? [];
        if (!empty($components['street'])) {
            $params['street'] = $components['street'];
        }
        if (!empty($components['barangay'])) {
            $params['city_district'] = $components['barangay'];
        }
        if (!empty($components['city'])) {
            $params['city'] = $components['city'];
        }
        $params['county'] = 'Iloilo';
        $params['state'] = 'Western Visayas';
        if (!empty($components['postalcode'])) {
            $params['postalcode'] = $components['postalcode'];
        }
        if (!empty($components['country'])) {
            $params['country'] = $components['country'];
        }
        $url = "https://nominatim.openstreetmap.org/search?" . http_build_query($params);
    } else {
        $query = $strategy['query'] ?? '';
        if (trim($query) === '') {
            return null;
        }
        $params['q'] = $query;
        $url = "https://nominatim.openstreetmap.org/search?" . http_build_query($params);
    }

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
        ['minLat' => 10.50, 'maxLat' => 10.78, 'minLng' => 122.45, 'maxLng' => 122.75], // Guimaras Strait
        ['minLat' => 10.42, 'maxLat' => 10.75, 'minLng' => 122.70, 'maxLng' => 123.05], // Iloilo Strait
        ['minLat' => 10.20, 'maxLat' => 10.70, 'minLng' => 121.50, 'maxLng' => 121.94], // Panay Gulf southern
        ['minLat' => 10.65, 'maxLat' => 11.35, 'minLng' => 121.50, 'maxLng' => 121.94], // Panay Gulf northern Antique
    ];
    
    $isInWater = function($latValue, $lngValue) use ($waterAreas) {
        if ($latValue === null || $lngValue === null) {
            return false;
        }
        foreach ($waterAreas as $area) {
            if ($latValue >= $area['minLat'] && $latValue <= $area['maxLat'] &&
                $lngValue >= $area['minLng'] && $lngValue <= $area['maxLng']) {
                return true;
            }
        }
        return false;
    };

    $inWater = $isInWater($lat, $lng);

    // Municipality centers for snapping
    $municipalityCenters = [
        'Ajuy' => [11.1710, 123.0150],
        'Alimodian' => [10.8167, 122.4333],
        'Anilao' => [11.0006, 122.7214],
        'Banate' => [11.0033, 122.8071],
        'Barotac Nuevo' => [10.9000, 122.7000],
        'Barotac Viejo' => [11.0500, 122.8500],
        'Batad' => [11.2873, 123.0455],
        'Bingawan' => [11.2333, 122.5667],
        'Cabatuan' => [10.8833, 122.4833],
        'Calinog' => [11.1167, 122.5000],
        'Carles' => [11.5667, 123.1333],
        'Concepcion' => [11.2167, 123.1167],
        'Dingle' => [11.0000, 122.6667],
        'Dueñas' => [11.0667, 122.6167],
        'Dumangas' => [10.8333, 122.7167],
        'Estancia' => [11.4500, 123.1500],
        'Guimbal' => [10.6667, 122.3167],
        'Igbaras' => [10.7167, 122.2667],
        'Janiuay' => [10.9500, 122.5000],
        'Lambunao' => [11.0500, 122.4667],
        'Leganes' => [10.7833, 122.5833],
        'Leon' => [10.7833, 122.3833],
        'Maasin' => [10.8833, 122.4333],
        'Mina' => [10.9333, 122.5833],
        'Miagao' => [10.6333, 122.2333],
        'New Lucena' => [10.8833, 122.6000],
        'Oton' => [10.6933, 122.4733],
        'Passi' => [11.1167, 122.6333],
        'Pavia' => [10.7750, 122.5444],
        'Pototan' => [10.9500, 122.6333],
        'San Dionisio' => [11.2667, 123.0833],
        'San Enrique' => [11.1000, 122.6667],
        'San Joaquin' => [10.5833, 122.1333],
        'San Miguel' => [10.7833, 122.4667],
        'Santa Barbara' => [10.8167, 122.5333],
        'Sara' => [11.2500, 123.0167],
        'Tigbauan' => [10.6833, 122.3667],
        'Tubungan' => [10.7833, 122.3333],
        'Zarraga' => [10.8167, 122.6000]
    ];
    $normalizeCityToken = function($text) {
        if ($text === null) {
            return '';
        }
        $token = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $token = strtolower($token);
        $token = preg_replace('/[^a-z0-9\s]/', ' ', $token);
        $token = preg_replace('/\s+/', ' ', $token);
        return trim($token);
    };

    $cityAliasMap = [];
    foreach ($municipalityCenters as $name => $coords) {
        $cityAliasMap[$normalizeCityToken($name)] = $name;
    }
    $aliasPairs = [
        ['passi city', 'Passi'],
        ['city of passi', 'Passi'],
        ['municipality of passi', 'Passi'],
        ['san miguel iloilo', 'San Miguel'],
        ['municipality of san miguel', 'San Miguel'],
        ['municipality of oton', 'Oton'],
        ['municipality of sara', 'Sara']
    ];
    foreach ($aliasPairs as [$alias, $canonical]) {
        $cityAliasMap[$normalizeCityToken($alias)] = $canonical;
    }

    $resolveCityFromText = function($text) use ($normalizeCityToken, $cityAliasMap) {
        if (!$text) {
            return null;
        }
        $tokenized = $normalizeCityToken($text);
        foreach ($cityAliasMap as $token => $canonical) {
            if (strpos($tokenized, $token) !== false) {
                return $canonical;
            }
        }
        return null;
    };

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

    $applyInlandBias = function($latValue, $lngValue, $cityHint = null) use ($municipalityCenters) {
        $targetCity = null;
        if (!empty($cityHint) && isset($municipalityCenters[$cityHint])) {
            $targetCity = $cityHint;
        }
        if ($targetCity === null) {
            $bestDist = PHP_FLOAT_MAX;
            $bestCity = null;
            foreach ($municipalityCenters as $name => $center) {
                $d = haversineDistance($latValue, $lngValue, $center[0], $center[1]);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $bestCity = $name;
                }
            }
            if ($bestCity !== null) {
                $targetCity = $bestCity;
            }
        }
        if ($targetCity === null || !isset($municipalityCenters[$targetCity])) {
            return [$latValue, $lngValue];
        }
        $cityCoords = $municipalityCenters[$targetCity];
        $adjustedLat = $latValue;
        $adjustedLng = $lngValue;

        if ($cityCoords[1] <= 122.05 && $adjustedLng <= $cityCoords[1]) {
            $adjustedLng = $cityCoords[1] + 0.012;
            $adjustedLat = $adjustedLat + 0.003;
        }
        if ($cityCoords[1] >= 122.75 && $adjustedLng >= $cityCoords[1]) {
            $adjustedLng = $cityCoords[1] - 0.012;
        }
        if ($cityCoords[0] >= 11.25 && $adjustedLat >= $cityCoords[0]) {
            $adjustedLat = $cityCoords[0] - 0.012;
        }
        if ($cityCoords[0] <= 10.55 && $adjustedLat <= $cityCoords[0]) {
            $adjustedLat = $cityCoords[0] + 0.012;
        }

        return [$adjustedLat, $adjustedLng];
    };

    $nudgeTowardsLand = function($latValue, $lngValue, $cityHint = null) use ($municipalityCenters, $isInWater, $nearestCoords, $nearestCity, $applyInlandBias) {
        $targets = [];
        if ($cityHint && isset($municipalityCenters[$cityHint])) {
            $targets[] = ['lat' => $municipalityCenters[$cityHint][0], 'lng' => $municipalityCenters[$cityHint][1]];
        }
        if ($nearestCoords) {
            $isDifferent = empty($targets) || $targets[0]['lat'] !== $nearestCoords[0] || $targets[0]['lng'] !== $nearestCoords[1];
            if ($isDifferent) {
                $targets[] = ['lat' => $nearestCoords[0], 'lng' => $nearestCoords[1]];
            }
        }

        $factors = [0.35, 0.55, 0.7, 0.85, 1.0];
        foreach ($targets as $target) {
            foreach ($factors as $factor) {
                $candidateLat = $latValue + ($target['lat'] - $latValue) * $factor;
                $candidateLng = $lngValue + ($target['lng'] - $lngValue) * $factor;
                if (!$isInWater($candidateLat, $candidateLng)) {
                    [$biasedLat, $biasedLng] = $applyInlandBias($candidateLat, $candidateLng, $cityHint);
                    if (!$isInWater($biasedLat, $biasedLng)) {
                        return [$biasedLat, $biasedLng];
                    }
                    return [$candidateLat, $candidateLng];
                }
            }
        }

        $offsets = [
            ['dLat' => 0.0, 'dLng' => 0.01],
            ['dLat' => 0.006, 'dLng' => 0.012],
            ['dLat' => -0.006, 'dLng' => 0.012],
            ['dLat' => 0.0, 'dLng' => 0.018],
        ];
        foreach ($offsets as $offset) {
            $candidateLat = $latValue + $offset['dLat'];
            $candidateLng = $lngValue + $offset['dLng'];
            if (!$isInWater($candidateLat, $candidateLng)) {
                [$biasedLat, $biasedLng] = $applyInlandBias($candidateLat, $candidateLng, $cityHint);
                if (!$isInWater($biasedLat, $biasedLng)) {
                    return [$biasedLat, $biasedLng];
                }
                return [$candidateLat, $candidateLng];
            }
        }
        return null;
    };
    
    // If in water OR too far from any city (>30km), snap to nearest city from address
    $snapCity = null;
    if ($inWater || $minDist > 30) {
        // Try to find city from address first
        $addressLower = strtolower($address);
        $possibleCity = $resolveCityFromText($address);
        if ($possibleCity && isset($municipalityCenters[$possibleCity])) {
            $coords['lat'] = $municipalityCenters[$possibleCity][0];
            $coords['lng'] = $municipalityCenters[$possibleCity][1];
            $coords['display_name'] = $possibleCity . ', Iloilo, Philippines';
            $coords['source'] = 'snapped';
            $snapCity = $possibleCity;
        } elseif ($nearestCoords) {
            $coords['lat'] = $nearestCoords[0];
            $coords['lng'] = $nearestCoords[1];
            $coords['display_name'] = $nearestCity . ', Iloilo, Philippines (snapped)';
            $coords['source'] = 'snapped';
            $snapCity = $nearestCity;
        }
    }
    
    [$coords['lat'], $coords['lng']] = $applyInlandBias($coords['lat'], $coords['lng'], $snapCity ?? $nearestCity);
    
    if ($isInWater($coords['lat'], $coords['lng'])) {
        $cityHint = $snapCity ?? $nearestCity;
        $nudged = $nudgeTowardsLand($coords['lat'], $coords['lng'], $cityHint);
        if ($nudged) {
            $coords['lat'] = $nudged[0];
            $coords['lng'] = $nudged[1];
            if ($coords['source'] !== 'database') {
                $coords['source'] = $coords['source'] ?? 'snapped';
            }
        } else {
            $coords['lat'] = $nearestCoords[0];
            $coords['lng'] = $nearestCoords[1];
            $coords['source'] = 'snapped';
        }
    }

    [$coords['lat'], $coords['lng']] = $applyInlandBias($coords['lat'], $coords['lng'], $snapCity ?? $nearestCity);

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
