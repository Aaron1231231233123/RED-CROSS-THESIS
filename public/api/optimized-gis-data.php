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

include_once '../../assets/conn/db_conn.php';
include_once '../Dashboards/module/optimized_functions.php';

// Function to get optimized GIS data using PostGIS
function getOptimizedGISData($bloodTypeFilter = 'all') {
    try {
        // Check if PostGIS is available by looking for geography columns
        $checkPostGIS = supabaseRequest("donor_form?select=permanent_geom&limit=1");
        
        if (empty($checkPostGIS['data'])) {
            // PostGIS not available, fallback to regular query
            return getFallbackGISData($bloodTypeFilter);
        }
        
        // Get approved/eligible donors from eligibility table
        // ROOT CAUSE FIX: Only show donors with status='approved' or 'eligible' AND blood_collection_id is set (complete process)
        $eligibilityQuery = "eligibility?select=donor_id,blood_type,status,blood_collection_id&status=in.(approved,eligible)&blood_collection_id=not.is.null";
        if ($bloodTypeFilter !== 'all' && !empty($bloodTypeFilter)) {
            $eligibilityQuery .= "&blood_type=eq." . urlencode($bloodTypeFilter);
        }
        $eligibilityResponse = supabaseRequest($eligibilityQuery);
        $eligibleDonors = [];
        $donorBloodTypes = [];
        
        if (!empty($eligibilityResponse['data'])) {
            foreach ($eligibilityResponse['data'] as $elig) {
                // FALLBACK: Defensive check - verify blood_collection_id is set
                $hasBloodCollectionId = !empty($elig['blood_collection_id'] ?? null);
                if ($hasBloodCollectionId) {
                    $eligibleDonors[$elig['donor_id']] = true;
                    if (!empty($elig['blood_type'])) {
                        $donorBloodTypes[$elig['donor_id']] = $elig['blood_type'];
                    }
                }
            }
        }
        
        if (empty($eligibleDonors)) {
            // No eligible donors found
            return processGISResults([], $bloodTypeFilter);
        }
        
        // Get donor IDs as array for filtering
        $donorIdArray = array_keys($eligibleDonors);
        
        // PostGIS available - use optimized spatial queries with coordinates
        // Get ONLY approved/eligible donors (with or without coordinates)
        // Supabase PostgREST supports in.() with comma-separated values
        $donorIds = implode(',', $donorIdArray);
        $query = "donor_form?select=donor_id,permanent_address,office_address,permanent_latitude,permanent_longitude,office_latitude,office_longitude,permanent_geom,office_geom&donor_id=in.(" . $donorIds . ")";
        $results = supabaseRequest($query);
        
        if (empty($results['data'])) {
            return getFallbackGISData($bloodTypeFilter);
        }
        
        // Process results with coordinates and add blood_type from eligibility
        $filteredResults = [];
        foreach ($results['data'] as $donor) {
            $donorId = $donor['donor_id'];
            
            // Skip if not in eligible donors list
            if (!isset($eligibleDonors[$donorId])) {
                continue;
            }
            
            // Determine location source based on available data
            $locationSource = 'none';
            if (!empty($donor['permanent_geom']) || (!empty($donor['permanent_latitude']) && !empty($donor['permanent_longitude']))) {
                $locationSource = 'permanent';
            } elseif (!empty($donor['office_geom']) || (!empty($donor['office_latitude']) && !empty($donor['office_longitude']))) {
                $locationSource = 'office';
            }
            
            $filteredResults[] = [
                'donor_id' => $donorId,
                'permanent_address' => $donor['permanent_address'] ?? null,
                'office_address' => $donor['office_address'] ?? null,
                'permanent_latitude' => $donor['permanent_latitude'] ?? null,
                'permanent_longitude' => $donor['permanent_longitude'] ?? null,
                'office_latitude' => $donor['office_latitude'] ?? null,
                'office_longitude' => $donor['office_longitude'] ?? null,
                'location_source' => $locationSource,
                'blood_type' => $donorBloodTypes[$donorId] ?? null
            ];
        }
        
        return processGISResults($filteredResults, $bloodTypeFilter);
        
    } catch (Exception $e) {
        error_log("PostGIS query error: " . $e->getMessage());
        return getFallbackGISData($bloodTypeFilter);
    }
}

// Fallback function for when PostGIS is not available
function getFallbackGISData($bloodTypeFilter = 'all') {
    try {
        // Get approved/eligible donors from eligibility table
        // ROOT CAUSE FIX: Only show donors with status='approved' or 'eligible' AND blood_collection_id is set (complete process)
        $eligibilityQuery = "eligibility?select=donor_id,blood_type,status,blood_collection_id&status=in.(approved,eligible)&blood_collection_id=not.is.null";
        if ($bloodTypeFilter !== 'all' && !empty($bloodTypeFilter)) {
            $eligibilityQuery .= "&blood_type=eq." . urlencode($bloodTypeFilter);
        }
        $eligibilityResponse = supabaseRequest($eligibilityQuery);
        $eligibleDonors = [];
        $donorBloodTypes = [];
        
        if (!empty($eligibilityResponse['data'])) {
            foreach ($eligibilityResponse['data'] as $elig) {
                // FALLBACK: Defensive check - verify blood_collection_id is set
                $hasBloodCollectionId = !empty($elig['blood_collection_id'] ?? null);
                if ($hasBloodCollectionId) {
                    $eligibleDonors[$elig['donor_id']] = true;
                    if (!empty($elig['blood_type'])) {
                        $donorBloodTypes[$elig['donor_id']] = $elig['blood_type'];
                    }
                }
            }
        }
        
        if (empty($eligibleDonors)) {
            return processGISResults([], $bloodTypeFilter);
        }
        
        // Get donor IDs as comma-separated list for Supabase in.() filter
        $donorIds = implode(',', array_keys($eligibleDonors));
        
        // Get all eligible donors with addresses and coordinates (if available)
        $donorFormResponse = supabaseRequest("donor_form?select=donor_id,permanent_address,office_address,permanent_latitude,permanent_longitude,office_latitude,office_longitude&donor_id=in.(" . $donorIds . ")");
        $donorData = $donorFormResponse['data'] ?? [];
        
        $results = [];
        foreach ($donorData as $donor) {
            $donorId = $donor['donor_id'];
            
            // Skip if not in eligible donors list
            if (!isset($eligibleDonors[$donorId])) {
                continue;
            }
            
            $results[] = [
                'donor_id' => $donorId,
                'permanent_address' => $donor['permanent_address'] ?? null,
                'office_address' => $donor['office_address'] ?? null,
                'permanent_latitude' => $donor['permanent_latitude'] ?? null,
                'permanent_longitude' => $donor['permanent_longitude'] ?? null,
                'office_latitude' => $donor['office_latitude'] ?? null,
                'office_longitude' => $donor['office_longitude'] ?? null,
                'location_source' => (!empty($donor['permanent_latitude']) || !empty($donor['office_latitude'])) ? 'database' : 'none',
                'blood_type' => $donorBloodTypes[$donorId] ?? null
            ];
        }
        
        return processGISResults($results, $bloodTypeFilter);
        
    } catch (Exception $e) {
        error_log("Fallback GIS data error: " . $e->getMessage());
        return processGISResults([], $bloodTypeFilter);
    }
}

// Process GIS results and return formatted data
function processGISResults($results, $bloodTypeFilter = 'all') {
    $cityDonorCounts = [];
    $heatmapData = [];
    
    // Filter by blood type if specified
    if ($bloodTypeFilter !== 'all' && !empty($bloodTypeFilter)) {
        $results = array_filter($results, function($donor) use ($bloodTypeFilter) {
            return isset($donor['blood_type']) && $donor['blood_type'] === $bloodTypeFilter;
        });
    }
    
    $totalDonorCount = count($results);
    
    // Function to clean and standardize address
    function standardizeAddress($address) {
        $municipalities = [
            'Ajuy','Alimodian','Anilao','Banate','Barotac Nuevo','Barotac Viejo','Batad','Bingawan','Cabatuan','Calinog',
            'Carles','Concepcion','Dingle','Dueñas','Dumangas','Estancia','Guimbal','Igbaras','Janiuay','Lambunao',
            'Leganes','Leon','Maasin','Mina','Miagao','New Lucena','Oton','Passi','Pavia','Pototan','San Dionisio',
            'San Enrique','San Joaquin','San Miguel','Santa Barbara','Sara','Tigbauan','Tubungan','Zarraga'
        ];

        $address = trim($address);
        
        // Remove duplicate or empty segments
        $segments = preg_split('/,/', $address);
        $normalizedSegments = [];
        $seen = [];
        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $normalized = strtolower(preg_replace('/\s+/', ' ', $segment));
            if (in_array($normalized, $seen, true)) {
                continue;
            }
            $seen[] = $normalized;
            $normalizedSegments[] = $segment;
        }
        if (!empty($normalizedSegments)) {
            $address = implode(', ', $normalizedSegments);
        }
        
        // Normalize common abbreviations
        $address = preg_replace('/\bBrgy\.?\s*/i', 'Barangay ', $address);
        $address = preg_replace('/\bBrg\.?\s*/i', 'Barangay ', $address);
        $address = preg_replace('/\bBgy\.?\s*/i', 'Barangay ', $address);
        
        $foundMunicipalities = [];
        foreach ($municipalities as $muni) {
            if (stripos($address, $muni) !== false) {
                $foundMunicipalities[] = $muni;
            }
        }

        // If we found a municipality, ensure it's properly formatted
        if (count($foundMunicipalities) > 0) {
            $primaryLocation = $foundMunicipalities[0];
            // Ensure municipality is at the end with proper formatting
            if (stripos($address, $primaryLocation . ',') === false && 
                stripos($address, $primaryLocation . ' ') === false &&
                substr($address, -strlen($primaryLocation)) !== $primaryLocation) {
                // Municipality not at end, ensure it's there
                if (stripos($address, $primaryLocation) !== false) {
                    // Municipality found somewhere, keep address as is but ensure province
                }
            }
        }

        // Ensure province is present
        if (
            stripos($address, 'Iloilo') === false &&
            stripos($address, 'Guimaras') === false &&
            stripos($address, 'Negros') === false &&
            stripos($address, 'Aklan') === false &&
            stripos($address, 'Capiz') === false &&
            stripos($address, 'Antique') === false
        ) {
            // Try to determine province from municipality
            $province = 'Iloilo'; // default
            foreach ($foundMunicipalities as $muni) {
                if (in_array($muni, ['Jordan', 'Buenavista', 'Nueva Valencia', 'San Lorenzo', 'Sibunag'])) {
                    $province = 'Guimaras';
                    break;
                } elseif (in_array($muni, ['Bacolod', 'Talisay', 'Silay', 'Bago', 'La Carlota', 'Valladolid'])) {
                    $province = 'Negros Occidental';
                    break;
                } elseif (in_array($muni, ['New Washington', 'Kalibo', 'Numancia', 'Batan', 'Altavas'])) {
                    $province = 'Aklan';
                    break;
                } elseif (in_array($muni, ['Roxas City', 'Maayon', 'Dumarao', 'Ivisan', 'Pilar'])) {
                    $province = 'Capiz';
                    break;
                } elseif (in_array($muni, ['San Jose de Buenavista', 'Hamtic', 'Patnongon', 'Culasi', 'Sibalom'])) {
                    $province = 'Antique';
                    break;
                }
            }
            $address .= ', ' . $province;
        }
        
        if (stripos($address, 'Philippines') === false) {
            $address .= ', Philippines';
        }

        $address = preg_replace('/\s+/', ' ', $address);
        $address = preg_replace('/,+/', ',', $address);
        $address = trim($address, ' ,');

        return $address;
    }
    
    // Municipality reference coordinates (rough centroids) for validation/snapping
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
        'San Joaquin' => [10.5920, 122.0950],
        'San Miguel' => [10.7833, 122.4667],
        'Santa Barbara' => [10.8167, 122.5333],
        'Sara' => [11.2500, 123.0167],
        'Tigbauan' => [10.6833, 122.3667],
        'Tubungan' => [10.7833, 122.3333],
        'Zarraga' => [10.8167, 122.6000]
    ];

    $allowedIloiloCities = array_keys($municipalityCenters);
    $allowedCityLookup = array_fill_keys($allowedIloiloCities, true);

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
    foreach ($allowedIloiloCities as $cityName) {
        $cityAliasMap[$normalizeCityToken($cityName)] = $cityName;
    }
    $aliasPairs = [
        ['passi city', 'Passi'],
        ['city of passi', 'Passi'],
        ['municipality of passi', 'Passi'],
        ['municipality of san miguel', 'San Miguel'],
        ['san miguel iloilo', 'San Miguel'],
        ['municipality of oton', 'Oton'],
        ['municipality of sara', 'Sara'],
        ['municipality of banate', 'Banate'],
        ['municipality of anilao', 'Anilao']
    ];
    foreach ($aliasPairs as [$alias, $canonical]) {
        $cityAliasMap[$normalizeCityToken($alias)] = $canonical;
    }

    $normalizeCityName = function($rawName) use ($normalizeCityToken, $cityAliasMap) {
        if ($rawName === null) {
            return null;
        }
        $token = $normalizeCityToken($rawName);
        return $cityAliasMap[$token] ?? null;
    };
    
    $deg2rad = function($deg){ return $deg * M_PI / 180; };
    $distanceKm = function($lat1,$lon1,$lat2,$lon2) use ($deg2rad){
        $R = 6371; // km
        $dLat = $deg2rad($lat2 - $lat1);
        $dLon = $deg2rad($lon2 - $lon1);
        $a = sin($dLat/2)**2 + cos($deg2rad($lat1))*cos($deg2rad($lat2))*sin($dLon/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    };
    
    // COMPREHENSIVE water area detection - cover ALL water bodies (straits, gulfs, open sea)
    $waterAreas = [
        // Guimaras Strait (between Panay and Guimaras) - slightly relaxed near the coast
        ['minLat' => 10.52, 'maxLat' => 10.76, 'minLng' => 122.52, 'maxLng' => 122.74],
        // Iloilo Strait (between Guimaras and Negros Occidental)
        ['minLat' => 10.44, 'maxLat' => 10.73, 'minLng' => 122.72, 'maxLng' => 123.03],
        // Panay Gulf (south of Iloilo/Antique coastline)
        ['minLat' => 10.24, 'maxLat' => 10.68, 'minLng' => 121.54, 'maxLng' => 121.92],
        // Panay Gulf (north of Antique coastline)
        ['minLat' => 10.69, 'maxLat' => 11.31, 'minLng' => 121.54, 'maxLng' => 121.92],
        // Visayan Sea (north of Panay Island)
        ['minLat' => 11.02, 'maxLat' => 11.58, 'minLng' => 122.24, 'maxLng' => 123.31],
        // Sulu Sea (south of Negros Occidental)
        ['minLat' => 9.74, 'maxLat' => 10.36, 'minLng' => 122.44, 'maxLng' => 123.16],
    ];
    $coastalGraceKm = 0.60;
    $waterDistanceThresholdKm = 0.45;
    
    $isInWater = function($lat,$lng) use ($waterAreas, $municipalityCenters, $distanceKm, $coastalGraceKm, $waterDistanceThresholdKm){
        if ($lat === null || $lng === null) return false;
        $nearestDist = PHP_FLOAT_MAX;
        foreach ($municipalityCenters as $coords) {
            $d = $distanceKm($lat, $lng, $coords[0], $coords[1]);
            if ($d < $nearestDist) {
                $nearestDist = $d;
            }
        }
        foreach ($waterAreas as $area) {
            if ($lat >= $area['minLat'] && $lat <= $area['maxLat'] &&
                $lng >= $area['minLng'] && $lng <= $area['maxLng']) {
                if ($nearestDist <= $coastalGraceKm) {
                    return false;
                }
                return true;
            }
        }
        if ($nearestDist === PHP_FLOAT_MAX) {
            return false;
        }
        return $nearestDist > $waterDistanceThresholdKm;
    };
    
    // STRICT validation: reject if in water OR too far from any known municipality center (>20km)
    // This ensures ALL coordinates are on land and near actual cities
    $isValidCoordinate = function($lat,$lng) use ($isInWater,$municipalityCenters,$distanceKm){
        if ($lat === null || $lng === null) return false;
        if ($isInWater($lat, $lng)) return false;
        // Must be within 20km of some municipality center (stricter than 30km)
        $best = PHP_FLOAT_MAX;
        foreach ($municipalityCenters as $coords) {
            $d = $distanceKm($lat,$lng,$coords[0],$coords[1]);
            if ($d < $best) $best = $d;
        }
        return $best <= 20; // Stricter: 20km instead of 30km
    };

    $biasCoordinateInland = function($lat, $lng, $cityHint = null) use ($municipalityCenters, $distanceKm) {
        $targetCity = null;
        if (!empty($cityHint) && isset($municipalityCenters[$cityHint])) {
            $targetCity = $cityHint;
        }
        if ($targetCity === null) {
            $best = PHP_FLOAT_MAX;
            foreach ($municipalityCenters as $name => $coords) {
                $d = $distanceKm($lat, $lng, $coords[0], $coords[1]);
                if ($d < $best) {
                    $best = $d;
                    $targetCity = $name;
                }
            }
        }
        if ($targetCity === null || !isset($municipalityCenters[$targetCity])) {
            return [$lat, $lng];
        }
        $cityCoords = $municipalityCenters[$targetCity];
        $adjustedLat = $lat;
        $adjustedLng = $lng;

        $westCoastCities = [
            'San Joaquin', 'Miagao', 'Guimbal', 'Tigbauan'
        ];
        $northCoastCities = [
            'Leganes', 'Zarraga', 'Dumangas', 'Barotac Nuevo', 'Barotac Viejo'
        ];

        $isWestCoast = in_array($targetCity, $westCoastCities, true);
        $westPrimaryBoost = $targetCity === 'San Joaquin' ? 0.032 : 0.026;
        $westSecondaryBoost = $targetCity === 'San Joaquin' ? 0.028 : 0.018;
        $westMinLngBoost = $targetCity === 'San Joaquin' ? 0.035 : 0.020;
        $westLatBoost = $targetCity === 'San Joaquin' ? 0.012 : 0.006;
        $westLatFloorBoost = $targetCity === 'San Joaquin' ? 0.012 : 0.004;

        if ($cityCoords[1] <= 122.05 && $adjustedLng <= $cityCoords[1]) {
            $adjustedLng = max($adjustedLng, $cityCoords[1] + $westPrimaryBoost);
            $adjustedLat = $adjustedLat + 0.004;
        }

        if ($isWestCoast && $adjustedLng <= $cityCoords[1] - 0.004) {
            $adjustedLng = max($adjustedLng, $cityCoords[1] + $westPrimaryBoost);
            $adjustedLat = $adjustedLat + $westLatBoost;
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

        if (in_array($targetCity, $northCoastCities, true) && $adjustedLat >= $cityCoords[0]) {
            $adjustedLat = $cityCoords[0] - 0.008;
        }

        if ($isWestCoast && $adjustedLng <= $cityCoords[1]) {
            $adjustedLng = max($adjustedLng, $cityCoords[1] + $westSecondaryBoost);
            $adjustedLat = max($adjustedLat, $cityCoords[0] + 0.002);
        }

        if ($isWestCoast) {
            $minSafeLng = $cityCoords[1] + $westMinLngBoost;
            if ($adjustedLng < $minSafeLng) {
                $adjustedLng = $minSafeLng;
            }
            $minSafeLat = $cityCoords[0] + $westLatFloorBoost;
            if ($adjustedLat < $minSafeLat) {
                $adjustedLat = $minSafeLat;
            }
        }

        return [$adjustedLat, $adjustedLng];
    };

    $driveCoordinateTowardsLand = function($lat, $lng, $cityHint = null) use ($municipalityCenters, $isInWater, $distanceKm, $biasCoordinateInland) {
        $targets = [];
        if ($cityHint && isset($municipalityCenters[$cityHint])) {
            $targets[] = ['lat' => $municipalityCenters[$cityHint][0], 'lng' => $municipalityCenters[$cityHint][1]];
        }
        $best = PHP_FLOAT_MAX;
        $nearest = null;
        foreach ($municipalityCenters as $coords) {
            $d = $distanceKm($lat, $lng, $coords[0], $coords[1]);
            if ($d < $best) {
                $best = $d;
                $nearest = ['lat' => $coords[0], 'lng' => $coords[1]];
            }
        }
        if ($nearest) {
            $isDifferent = empty($targets) || $targets[0]['lat'] !== $nearest['lat'] || $targets[0]['lng'] !== $nearest['lng'];
            if ($isDifferent) {
                $targets[] = $nearest;
            }
        }

        $factors = [0.35, 0.55, 0.7, 0.85, 1.0];
        foreach ($targets as $target) {
            foreach ($factors as $factor) {
                $candidateLat = $lat + ($target['lat'] - $lat) * $factor;
                $candidateLng = $lng + ($target['lng'] - $lng) * $factor;
                if (!$isInWater($candidateLat, $candidateLng)) {
                    [$biasedLat, $biasedLng] = $biasCoordinateInland($candidateLat, $candidateLng, $cityHint);
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
            $candidateLat = $lat + $offset['dLat'];
            $candidateLng = $lng + $offset['dLng'];
            if (!$isInWater($candidateLat, $candidateLng)) {
                [$biasedLat, $biasedLng] = $biasCoordinateInland($candidateLat, $candidateLng, $cityHint);
                if (!$isInWater($biasedLat, $biasedLng)) {
                    return [$biasedLat, $biasedLng];
                }
                return [$candidateLat, $candidateLng];
            }
        }
        return null;
    };
    
    $diagnostics = [];
    foreach ($results as $donor) {
        $address = !empty($donor['office_address']) ? $donor['office_address'] : ($donor['permanent_address'] ?? null);
        $resolvedCity = null;

        if (!empty($address)) {
            $addressToken = $normalizeCityToken($address);
            foreach ($cityAliasMap as $token => $canonicalCity) {
                if (strpos($addressToken, $token) !== false) {
                    $resolvedCity = $canonicalCity;
                    break;
                }
            }
        }

        $snapLat = !empty($donor['permanent_latitude']) ? $donor['permanent_latitude'] : (!empty($donor['office_latitude']) ? $donor['office_latitude'] : null);
        $snapLng = !empty($donor['permanent_longitude']) ? $donor['permanent_longitude'] : (!empty($donor['office_longitude']) ? $donor['office_longitude'] : null);

        if (!$resolvedCity && $snapLat !== null && $snapLng !== null) {
            $minDist = PHP_FLOAT_MAX;
            $nearest = null;
            foreach ($municipalityCenters as $name => $coords) {
                $d = $distanceKm($snapLat, $snapLng, $coords[0], $coords[1]);
                if ($d < $minDist) {
                    $minDist = $d;
                    $nearest = $name;
                }
            }
            if ($nearest !== null && $minDist <= 25) {
                $resolvedCity = $nearest;
            }
        }

        if (!$resolvedCity && !empty($address) && stripos($address, 'iloilo') !== false) {
            $resolvedCity = null;
        }

        if (!$resolvedCity) {
            if ($snapLat !== null && $snapLng !== null) {
                $minDist = PHP_FLOAT_MAX;
                $nearest = null;
                foreach ($municipalityCenters as $name => $coords) {
                    $d = $distanceKm($snapLat, $snapLng, $coords[0], $coords[1]);
                    if ($d < $minDist) {
                        $minDist = $d;
                        $nearest = $name;
                    }
                }
                if ($nearest !== null && $minDist <= 25) {
                    $resolvedCity = $nearest;
                }
            }
        }

        if (!$resolvedCity || !isset($allowedCityLookup[$resolvedCity])) {
            continue;
        }

        $heatmapAddress = !empty($donor['permanent_address']) ? $donor['permanent_address'] : ($donor['office_address'] ?? null);
        if (empty($heatmapAddress)) {
            continue;
        }

        $standardizedAddress = standardizeAddress($heatmapAddress);
        $heatmapLat = !empty($donor['permanent_latitude']) ? $donor['permanent_latitude'] : (!empty($donor['office_latitude']) ? $donor['office_latitude'] : null);
        $heatmapLng = !empty($donor['permanent_longitude']) ? $donor['permanent_longitude'] : (!empty($donor['office_longitude']) ? $donor['office_longitude'] : null);

        $coordinateSource = 'original';
        $hasValidCoords = false;

        if ($heatmapLat !== null && $heatmapLng !== null) {
            if (!$isValidCoordinate($heatmapLat, $heatmapLng)) {
                $nudged = $driveCoordinateTowardsLand($heatmapLat, $heatmapLng, $resolvedCity);
                if ($nudged) {
                    [$heatmapLat, $heatmapLng] = $nudged;
                }
            }
            [$heatmapLat, $heatmapLng] = $biasCoordinateInland($heatmapLat, $heatmapLng, $resolvedCity);
            if ($isValidCoordinate($heatmapLat, $heatmapLng)) {
                $hasValidCoords = true;
            }
        }

        if (!$hasValidCoords) {
            $candidateLat = $municipalityCenters[$resolvedCity][0];
            $candidateLng = $municipalityCenters[$resolvedCity][1];
            if (!$isValidCoordinate($candidateLat, $candidateLng)) {
                $nudged = $driveCoordinateTowardsLand($candidateLat, $candidateLng, $resolvedCity);
                if ($nudged) {
                    [$candidateLat, $candidateLng] = $nudged;
                }
            }
            [$candidateLat, $candidateLng] = $biasCoordinateInland($candidateLat, $candidateLng, $resolvedCity);
            if ($isValidCoordinate($candidateLat, $candidateLng)) {
                $heatmapLat = $candidateLat;
                $heatmapLng = $candidateLng;
                $coordinateSource = 'snapped';
                $hasValidCoords = true;
            }
        }

        if (!$hasValidCoords) {
            continue;
        }

        if (!$isValidCoordinate($heatmapLat, $heatmapLng)) {
            $nudged = $driveCoordinateTowardsLand($heatmapLat, $heatmapLng, $resolvedCity);
            if (!$nudged) {
                continue;
            }
            [$heatmapLat, $heatmapLng] = $nudged;
            [$heatmapLat, $heatmapLng] = $biasCoordinateInland($heatmapLat, $heatmapLng, $resolvedCity);
            if (!$isValidCoordinate($heatmapLat, $heatmapLng)) {
                continue;
            }
        }

        $distanceToCentroid = isset($municipalityCenters[$resolvedCity])
            ? $distanceKm($heatmapLat, $heatmapLng, $municipalityCenters[$resolvedCity][0], $municipalityCenters[$resolvedCity][1])
            : null;
        $finalWaterCheck = $isInWater($heatmapLat, $heatmapLng);

        if (($distanceToCentroid !== null && $distanceToCentroid > 12) || $finalWaterCheck) {
            $fallbackLat = $municipalityCenters[$resolvedCity][0];
            $fallbackLng = $municipalityCenters[$resolvedCity][1];
            [$fallbackLat, $fallbackLng] = $biasCoordinateInland($fallbackLat, $fallbackLng, $resolvedCity);
            $nudgedFallback = $driveCoordinateTowardsLand($fallbackLat, $fallbackLng, $resolvedCity);
            if ($nudgedFallback) {
                [$fallbackLat, $fallbackLng] = $nudgedFallback;
            }
            if ($isInWater($fallbackLat, $fallbackLng)) {
                continue;
            }
            $heatmapLat = $fallbackLat;
            $heatmapLng = $fallbackLng;
            $coordinateSource = 'snapped';
            $distanceToCentroid = isset($municipalityCenters[$resolvedCity])
                ? $distanceKm($heatmapLat, $heatmapLng, $municipalityCenters[$resolvedCity][0], $municipalityCenters[$resolvedCity][1])
                : null;
            $finalWaterCheck = $isInWater($heatmapLat, $heatmapLng);
        }

        $latBeforeBias = $heatmapLat;
        $lngBeforeBias = $heatmapLng;
        $HeatmapIsInWater = $finalWaterCheck ? 'yes' : 'no';

        $distanceToCity = $distanceToCentroid;
        $isInWaterFinal = $finalWaterCheck;

        $heatmapData[] = [
            'donor_id' => $donor['donor_id'],
            'original_address' => $heatmapAddress,
            'address' => $standardizedAddress,
            'latitude' => $heatmapLat,
            'longitude' => $heatmapLng,
            'location_source' => $donor['location_source'],
            'has_coordinates' => true,
            'coordinate_source' => $coordinateSource,
            'fallback_latitude' => null,
            'fallback_longitude' => null,
            'blood_type' => $donor['blood_type'] ?? null,
            'city' => $resolvedCity
        ];

        $cityDonorCounts[$resolvedCity] = ($cityDonorCounts[$resolvedCity] ?? 0) + 1;

        $diagnostics[] = [
            'donor_id' => $donor['donor_id'],
            'resolved_city' => $resolvedCity,
            'coordinate_source' => $coordinateSource,
            'distance_to_city_km' => $distanceToCity,
            'in_water' => $isInWaterFinal,
            'address' => $heatmapAddress,
            'latitude' => $heatmapLat,
            'longitude' => $heatmapLng
        ];
    }

    arsort($cityDonorCounts);

    $donorsWithCoords = count($heatmapData);
    
    return [
        'totalDonorCount' => $totalDonorCount,
        'cityDonorCounts' => $cityDonorCounts,
        'heatmapData' => $heatmapData,
        'postgis_available' => !empty($results) && !empty($results[0]['permanent_latitude']),
        'donorsWithCoordinates' => $donorsWithCoords,
        'coordinateCoverage' => $totalDonorCount > 0 ? round(($donorsWithCoords / $totalDonorCount) * 100, 1) : 0,
        'diagnostics' => $diagnostics
    ];
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get blood type filter from query parameter
    $bloodTypeFilter = $_GET['blood_type'] ?? 'all';
    $gisData = getOptimizedGISData($bloodTypeFilter);
    echo json_encode($gisData);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
