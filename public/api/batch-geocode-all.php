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

// Function to geocode a single address
function geocodeAddress($address) {
    $encodedAddress = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedAddress}&countrycodes=ph&limit=1";
    
    $opts = [
        'http' => [
            'header' => "User-Agent: RED-CROSS-THESIS/1.0 (your-email@example.com)\r\n"
        ]
    ];
    
    $context = stream_context_create($opts);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (empty($data)) {
        return null;
    }
    
    $result = $data[0];
    return [
        'lat' => floatval($result['lat']),
        'lng' => floatval($result['lon']),
        'display_name' => $result['display_name'],
        'source' => 'geocoded'
    ];
}

// Function to store coordinates in database
function storeCoordinates($donorId, $lat, $lng, $address) {
    try {
        // Use Supabase API to update coordinates
        $updateData = [
            'permanent_latitude' => $lat,
            'permanent_longitude' => $lng
        ];
        
        // Update via Supabase API
        $response = supabaseRequest("donor_form?donor_id=eq.{$donorId}", 'PATCH', $updateData);
        
        if ($response && !isset($response['error'])) {
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Error storing coordinates for donor {$donorId}: " . $e->getMessage());
        return false;
    }
}

// Main batch geocoding function
function batchGeocodeAll() {
    $results = [
        'total' => 0,
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    
    try {
        // Get all donors with addresses but no coordinates
        $donorsResponse = supabaseRequest("donor_form?select=donor_id,permanent_address,permanent_latitude,permanent_longitude&permanent_address=not.is.null&or=(permanent_latitude.is.null,permanent_longitude.is.null)");
        
        if (empty($donorsResponse['data'])) {
            return $results;
        }
        
        $donors = $donorsResponse['data'];
        $results['total'] = count($donors);
        
        foreach ($donors as $donor) {
            $results['processed']++;
            
            // Skip if already has coordinates
            if (!empty($donor['permanent_latitude']) && !empty($donor['permanent_longitude'])) {
                $results['skipped']++;
                continue;
            }
            
            $address = $donor['permanent_address'];
            $donorId = $donor['donor_id'];
            
            // Geocode the address
            $coords = geocodeAddress($address);
            
            if ($coords) {
                // Store coordinates
                if (storeCoordinates($donorId, $coords['lat'], $coords['lng'], $address)) {
                    $results['successful']++;
                    error_log("Geocoded donor {$donorId}: {$coords['lat']}, {$coords['lng']}");
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to store coordinates for donor {$donorId}";
                }
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to geocode address for donor {$donorId}: {$address}";
            }
            
            // Add delay to respect Nominatim rate limits
            sleep(1);
            
            // Break if we've processed too many (to avoid timeout)
            if ($results['processed'] >= 50) {
                $results['errors'][] = "Stopped at 50 records to avoid timeout. Run again to continue.";
                break;
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Batch geocoding error: " . $e->getMessage();
    }
    
    return $results;
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'batch_geocode';
    
    if ($action === 'batch_geocode') {
        $results = batchGeocodeAll();
        echo json_encode($results);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
