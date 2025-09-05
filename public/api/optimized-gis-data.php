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
function getOptimizedGISData() {
    try {
        // Check if PostGIS is available by looking for geography columns
        $checkPostGIS = supabaseRequest("donor_form?select=permanent_geom&limit=1");
        
        if (empty($checkPostGIS['data'])) {
            // PostGIS not available, fallback to regular query
            return getFallbackGISData();
        }
        
        // PostGIS available - use optimized spatial queries
        $query = "donor_form?select=donor_id,permanent_address,office_address,permanent_latitude,permanent_longitude,office_latitude,office_longitude,permanent_geom,office_geom";
        $results = supabaseRequest($query);
        
        if (empty($results['data'])) {
            return getFallbackGISData();
        }
        
        // Filter results to only include donors with blood bank units
        $bloodBankUnits = supabaseRequest("blood_bank_units?select=donor_id");
        $validDonorIds = array_column($bloodBankUnits['data'] ?? [], 'donor_id');
        
        $filteredResults = [];
        foreach ($results['data'] as $donor) {
            if (in_array($donor['donor_id'], $validDonorIds)) {
                $filteredResults[] = [
                    'donor_id' => $donor['donor_id'],
                    'permanent_address' => $donor['permanent_address'],
                    'office_address' => $donor['office_address'],
                    'permanent_latitude' => $donor['permanent_latitude'],
                    'permanent_longitude' => $donor['permanent_longitude'],
                    'office_latitude' => $donor['office_latitude'],
                    'office_longitude' => $donor['office_longitude'],
                    'location_source' => $donor['permanent_geom'] ? 'permanent' : ($donor['office_geom'] ? 'office' : 'none')
                ];
            }
        }
        
        return processGISResults($filteredResults);
        
    } catch (Exception $e) {
        error_log("PostGIS query error: " . $e->getMessage());
        return getFallbackGISData();
    }
}

// Fallback function for when PostGIS is not available
function getFallbackGISData() {
    try {
        // Get all unique donor IDs from blood_bank_units
        $bloodBankUnits = supabaseRequest("blood_bank_units?select=donor_id");
        $donorIds = array_unique(array_column($bloodBankUnits['data'] ?? [], 'donor_id'));
        
        if (empty($donorIds)) {
            return processGISResults([]);
        }
        
        // Get donor addresses
        $donorIdsString = implode(',', $donorIds);
        $donorFormResponse = supabaseRequest("donor_form?select=donor_id,permanent_address,office_address&donor_id=in.(" . $donorIdsString . ")");
        $donorData = $donorFormResponse['data'] ?? [];
        
        $results = [];
        foreach ($donorData as $donor) {
            $results[] = [
                'donor_id' => $donor['donor_id'],
                'permanent_address' => $donor['permanent_address'],
                'office_address' => $donor['office_address'],
                'permanent_latitude' => null,
                'permanent_longitude' => null,
                'office_latitude' => null,
                'office_longitude' => null,
                'location_source' => 'none'
            ];
        }
        
        return processGISResults($results);
        
    } catch (Exception $e) {
        error_log("Fallback GIS data error: " . $e->getMessage());
        return processGISResults([]);
    }
}

// Process GIS results and return formatted data
function processGISResults($results) {
    $cityDonorCounts = [];
    $heatmapData = [];
    $totalDonorCount = count($results);
    
    // Function to clean and standardize address
    function standardizeAddress($address) {
        $municipalities = [
            'Pototan', 'Oton', 'Pavia', 'Leganes', 'Santa Barbara', 'San Miguel',
            'Cabatuan', 'Maasin', 'Janiuay', 'Dumangas', 'Zarraga', 'New Lucena',
            'Alimodian', 'Leon', 'Tubungan', 'Iloilo City'
        ];

        $address = trim($address);
        
        $foundMunicipalities = [];
        foreach ($municipalities as $muni) {
            if (stripos($address, $muni) !== false) {
                $foundMunicipalities[] = $muni;
            }
        }

        if (count($foundMunicipalities) > 1) {
            $primaryLocation = $foundMunicipalities[0];
            foreach (array_slice($foundMunicipalities, 1) as $muni) {
                $address = str_ireplace($muni, '', $address);
            }
            $address = str_ireplace($primaryLocation, '', $address);
            $address = trim($address, ' ,.') . ', ' . $primaryLocation;
        }

        if (stripos($address, 'Iloilo') === false) {
            $address .= ', Iloilo';
        }
        if (stripos($address, 'Philippines') === false) {
            $address .= ', Philippines';
        }

        $address = preg_replace('/\s+/', ' ', $address);
        $address = preg_replace('/,+/', ',', $address);
        $address = trim($address, ' ,');

        return $address;
    }
    
    foreach ($results as $donor) {
        // Get the address (office first, then permanent)
        $address = !empty($donor['office_address']) ? $donor['office_address'] : $donor['permanent_address'];
        
        // For Top Donors: Extract city name
        $iloiloCities = [
            'Oton', 'Pavia', 'Leganes', 'Santa Barbara', 'San Miguel', 
            'Cabatuan', 'Maasin', 'Janiuay', 'Pototan', 'Dumangas',
            'Zarraga', 'New Lucena', 'Alimodian', 'Leon', 'Tubungan',
            'Iloilo City'
        ];

        $cityFound = false;
        foreach ($iloiloCities as $cityName) {
            if (stripos($address, $cityName) !== false) {
                if (!isset($cityDonorCounts[$cityName])) {
                    $cityDonorCounts[$cityName] = 0;
                }
                $cityDonorCounts[$cityName]++;
                $cityFound = true;
                break;
            }
        }

        if (!$cityFound) {
            if (!isset($cityDonorCounts['Unidentified Location'])) {
                $cityDonorCounts['Unidentified Location'] = 0;
            }
            $cityDonorCounts['Unidentified Location']++;
        }
        
        // For Heatmap: Use permanent address
        if (!empty($donor['permanent_address'])) {
            $standardizedAddress = standardizeAddress($donor['permanent_address']);
            $heatmapData[] = [
                'donor_id' => $donor['donor_id'],
                'original_address' => $donor['permanent_address'],
                'address' => $standardizedAddress,
                'latitude' => $donor['permanent_latitude'],
                'longitude' => $donor['permanent_longitude'],
                'location_source' => $donor['location_source']
            ];
        }
    }
    
    // Sort cities by donor count
    arsort($cityDonorCounts);
    
    return [
        'totalDonorCount' => $totalDonorCount,
        'cityDonorCounts' => $cityDonorCounts,
        'heatmapData' => $heatmapData,
        'postgis_available' => !empty($results) && !empty($results[0]['permanent_latitude'])
    ];
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gisData = getOptimizedGISData();
    echo json_encode($gisData);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
