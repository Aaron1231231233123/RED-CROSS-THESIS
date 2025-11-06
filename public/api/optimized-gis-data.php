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
        // Only show donors with status='approved' or status='eligible'
        $eligibilityQuery = "eligibility?select=donor_id,blood_type,status&status=in.(approved,eligible)";
        if ($bloodTypeFilter !== 'all' && !empty($bloodTypeFilter)) {
            $eligibilityQuery .= "&blood_type=eq." . urlencode($bloodTypeFilter);
        }
        $eligibilityResponse = supabaseRequest($eligibilityQuery);
        $eligibleDonors = [];
        $donorBloodTypes = [];
        
        if (!empty($eligibilityResponse['data'])) {
            foreach ($eligibilityResponse['data'] as $elig) {
                $eligibleDonors[$elig['donor_id']] = true;
                if (!empty($elig['blood_type'])) {
                    $donorBloodTypes[$elig['donor_id']] = $elig['blood_type'];
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
        $eligibilityQuery = "eligibility?select=donor_id,blood_type,status&status=in.(approved,eligible)";
        if ($bloodTypeFilter !== 'all' && !empty($bloodTypeFilter)) {
            $eligibilityQuery .= "&blood_type=eq." . urlencode($bloodTypeFilter);
        }
        $eligibilityResponse = supabaseRequest($eligibilityQuery);
        $eligibleDonors = [];
        $donorBloodTypes = [];
        
        if (!empty($eligibilityResponse['data'])) {
            foreach ($eligibilityResponse['data'] as $elig) {
                $eligibleDonors[$elig['donor_id']] = true;
                if (!empty($elig['blood_type'])) {
                    $donorBloodTypes[$elig['donor_id']] = $elig['blood_type'];
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
            // Iloilo (complete core set)
            'Iloilo City','Oton','Pavia','Leganes','Santa Barbara','San Miguel','Cabatuan','Maasin','Janiuay','Pototan','Dumangas','Zarraga','New Lucena','Alimodian','Leon','Tubungan','Mina','Barotac Nuevo','Barotac Viejo','Bingawan','Calinog','Carles','Concepcion','Dingle','Dueñas','Estancia','Guimbal','Igbaras','Javier','Lambunao','Miagao','Pavia','Passi','San Dionisio','San Enrique','San Joaquin','San Rafael','Sara','Tigbauan','Ajuy','Balasan','Banate','Anilao','Lemery','Batad','San Miguel',
            // Guimaras
            'Jordan','Buenavista','Nueva Valencia','San Lorenzo','Sibunag',
            // Negros Occidental (nearby to east for map coverage)
            'Bacolod','Talisay','Silay','Bago','La Carlota','Valladolid'
        ];

        $address = trim($address);
        
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
        if (stripos($address, 'Iloilo') === false && stripos($address, 'Guimaras') === false && stripos($address, 'Negros') === false) {
            // Try to determine province from municipality
            $province = 'Iloilo'; // default
            foreach ($foundMunicipalities as $muni) {
                if (in_array($muni, ['Jordan', 'Buenavista', 'Nueva Valencia', 'San Lorenzo', 'Sibunag'])) {
                    $province = 'Guimaras';
                    break;
                } elseif (in_array($muni, ['Bacolod', 'Talisay', 'Silay', 'Bago', 'La Carlota', 'Valladolid'])) {
                    $province = 'Negros Occidental';
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
        // Guimaras
        'Jordan' => [10.6581, 122.5969],
        'Buenavista' => [10.6736, 122.6137],
        'Nueva Valencia' => [10.5343, 122.5935],
        'San Lorenzo' => [10.6108, 122.7027],
        'Sibunag' => [10.5157, 122.6911],
        // Negros Occidental east of Guimaras Strait
        'Bacolod' => [10.6765, 122.9509],
        'Talisay' => [10.7363, 122.9672],
        'Silay' => [10.7938, 122.9780],
        'Bago' => [10.5336, 122.8410],
        'La Carlota' => [10.4244, 122.9199],
        'Valladolid' => [10.4598, 122.8374]
    ];
    
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
        // Guimaras Strait (between Panay and Guimaras) - expanded coverage
        ['minLat' => 10.55, 'maxLat' => 10.75, 'minLng' => 122.50, 'maxLng' => 122.70],
        // Iloilo Strait (between Guimaras and Negros) - expanded coverage
        ['minLat' => 10.45, 'maxLat' => 10.70, 'minLng' => 122.70, 'maxLng' => 122.95],
        // Panay Gulf (west of Panay) - deep water areas
        ['minLat' => 10.20, 'maxLat' => 10.65, 'minLng' => 121.80, 'maxLng' => 122.30],
        // Visayan Sea (north of Panay)
        ['minLat' => 11.20, 'maxLat' => 11.50, 'minLng' => 122.50, 'maxLng' => 123.20],
        // Sulu Sea (south of Negros)
        ['minLat' => 9.80, 'maxLat' => 10.30, 'minLng' => 122.50, 'maxLng' => 123.20],
    ];
    
    $isInWater = function($lat,$lng) use ($waterAreas){
        if ($lat === null || $lng === null) return false;
        foreach ($waterAreas as $area) {
            if ($lat >= $area['minLat'] && $lat <= $area['maxLat'] &&
                $lng >= $area['minLng'] && $lng <= $area['maxLng']) {
                return true;
            }
        }
        return false;
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
    
    foreach ($results as $donor) {
        // Get the address (office first, then permanent)
        $address = !empty($donor['office_address']) ? $donor['office_address'] : $donor['permanent_address'];
        
        // For Top Donors: Resolve city name robustly
        $resolvedCity = null;
        foreach (array_keys($municipalityCenters) as $cityName) {
            if (stripos($address, $cityName) !== false) {
                $resolvedCity = $cityName;
                break;
            }
        }
        // If still not found and we have coordinates, snap to nearest municipality center
        // Try permanent coordinates first, then office coordinates
        $snapLat = !empty($donor['permanent_latitude']) ? $donor['permanent_latitude'] : (!empty($donor['office_latitude']) ? $donor['office_latitude'] : null);
        $snapLng = !empty($donor['permanent_longitude']) ? $donor['permanent_longitude'] : (!empty($donor['office_longitude']) ? $donor['office_longitude'] : null);
        
        if (!$resolvedCity && $snapLat !== null && $snapLng !== null) {
            $minDist = PHP_FLOAT_MAX;
            $nearest = null;
            foreach ($municipalityCenters as $name => $coords) {
                $d = $distanceKm($snapLat, $snapLng, $coords[0], $coords[1]);
                if ($d < $minDist) { $minDist = $d; $nearest = $name; }
            }
            // Only snap if within a reasonable radius (~35km)
            if ($nearest !== null && $minDist <= 35) { $resolvedCity = $nearest; }
        }
        // Fallback: last segment before province if present
        if (!$resolvedCity && preg_match('/,\s*([^,]+)\s*,\s*(iloilo|guimaras|negros)/i', $address, $m)) {
            $resolvedCity = trim($m[1]);
        }
        if (!$resolvedCity) { $resolvedCity = 'Iloilo City'; } // conservative default in mainland
        if (!isset($cityDonorCounts[$resolvedCity])) { $cityDonorCounts[$resolvedCity] = 0; }
        $cityDonorCounts[$resolvedCity]++;
        
        // For Heatmap: Use permanent address first, then office address if permanent is empty
        $heatmapAddress = !empty($donor['permanent_address']) ? $donor['permanent_address'] : (!empty($donor['office_address']) ? $donor['office_address'] : null);
        
        if (!empty($heatmapAddress)) {
            $standardizedAddress = standardizeAddress($heatmapAddress);
            // Use permanent coordinates if available, otherwise try office coordinates
            $heatmapLat = !empty($donor['permanent_latitude']) ? $donor['permanent_latitude'] : (!empty($donor['office_latitude']) ? $donor['office_latitude'] : null);
            $heatmapLng = !empty($donor['permanent_longitude']) ? $donor['permanent_longitude'] : (!empty($donor['office_longitude']) ? $donor['office_longitude'] : null);
            
            // Validate coordinates. ALWAYS ensure we have valid land-based coordinates
            // If invalid/offshore OR missing, use resolved city center coordinates
            $hasValidCoords = false;
            if (!empty($heatmapLat) && !empty($heatmapLng)) {
                if ($isValidCoordinate($heatmapLat, $heatmapLng)) {
                    $hasValidCoords = true;
                } else {
                    // Invalid coordinates - snap to resolved city center
                    if ($resolvedCity && isset($municipalityCenters[$resolvedCity])) {
                        $heatmapLat = $municipalityCenters[$resolvedCity][0];
                        $heatmapLng = $municipalityCenters[$resolvedCity][1];
                        $hasValidCoords = true;
                    }
                }
            }
            
            // If still no valid coordinates (missing or invalid), use resolved city center
            if (!$hasValidCoords && $resolvedCity && isset($municipalityCenters[$resolvedCity])) {
                $heatmapLat = $municipalityCenters[$resolvedCity][0];
                $heatmapLng = $municipalityCenters[$resolvedCity][1];
                $hasValidCoords = true;
            }
            
            // Always include in heatmapData - addresses without coordinates will be geocoded client-side
            $heatmapData[] = [
                'donor_id' => $donor['donor_id'],
                'original_address' => $heatmapAddress,
                'address' => $standardizedAddress,
                'latitude' => $heatmapLat,
                'longitude' => $heatmapLng,
                'location_source' => $donor['location_source'],
                'has_coordinates' => $hasValidCoords,
                'blood_type' => $donor['blood_type'] ?? null
            ];
        }
    }
    
    // Sort cities by donor count
    arsort($cityDonorCounts);
    
    // FINAL VALIDATION PASS: Ensure NO coordinates are in water or invalid
    // This is a safety net to catch any that slipped through
    foreach ($heatmapData as &$donor) {
        if ($donor['has_coordinates'] && !empty($donor['latitude']) && !empty($donor['longitude'])) {
            $lat = $donor['latitude'];
            $lng = $donor['longitude'];
            
            // Double-check: if in water or invalid, snap to nearest city from address
            if (!$isValidCoordinate($lat, $lng)) {
                // Extract city from address
                $addressLower = strtolower($donor['address']);
                $snapped = false;
                
                // Try to find city from address
                foreach ($municipalityCenters as $city => $coords) {
                    if (stripos($addressLower, strtolower($city)) !== false) {
                        $donor['latitude'] = $coords[0];
                        $donor['longitude'] = $coords[1];
                        $snapped = true;
                        break;
                    }
                }
                
                // If no city found in address, snap to nearest city center
                if (!$snapped) {
                    $minDist = PHP_FLOAT_MAX;
                    $nearestCoords = null;
                    foreach ($municipalityCenters as $coords) {
                        $d = $distanceKm($lat, $lng, $coords[0], $coords[1]);
                        if ($d < $minDist) {
                            $minDist = $d;
                            $nearestCoords = $coords;
                        }
                    }
                    if ($nearestCoords) {
                        $donor['latitude'] = $nearestCoords[0];
                        $donor['longitude'] = $nearestCoords[1];
                    }
                }
            }
        }
    }
    unset($donor); // Break reference
    
    // Count donors with coordinates
    $donorsWithCoords = 0;
    foreach ($heatmapData as $donor) {
        if ($donor['has_coordinates']) {
            $donorsWithCoords++;
        }
    }
    
    return [
        'totalDonorCount' => $totalDonorCount,
        'cityDonorCounts' => $cityDonorCounts,
        'heatmapData' => $heatmapData,
        'postgis_available' => !empty($results) && !empty($results[0]['permanent_latitude']),
        'donorsWithCoordinates' => $donorsWithCoords,
        'coordinateCoverage' => $totalDonorCount > 0 ? round(($donorsWithCoords / $totalDonorCount) * 100, 1) : 0
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
