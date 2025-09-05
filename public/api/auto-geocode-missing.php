<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include_once '../../assets/conn/db_conn.php';
include_once '../Dashboards/module/optimized_functions.php';

// Debug logging
error_log("AUTO-GEOCODE: Endpoint called at " . date('Y-m-d H:i:s'));
error_log("AUTO-GEOCODE: Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("AUTO-GEOCODE: Request body: " . file_get_contents('php://input'));

// Function to geocode missing coordinates automatically
function autoGeocodeMissing() {
    $results = [
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    try {
        // Get all donors with NULL coordinates
        $donorsToGeocode = supabaseRequest("donor_form?select=donor_id,permanent_address,office_address,permanent_latitude,permanent_longitude,office_latitude,office_longitude&or=(permanent_latitude.is.null,office_latitude.is.null)");
        
        error_log("AUTO-GEOCODE: Found " . count($donorsToGeocode['data'] ?? []) . " donors with missing coordinates");
        
        if (empty($donorsToGeocode['data'])) {
            return $results;
        }
        
        $donors = $donorsToGeocode['data'];
        $results['total'] = count($donors);
        
        // Process up to 5 donors at a time to avoid rate limits
        $batchSize = 5;
        $processed = 0;
        
        foreach (array_slice($donors, 0, $batchSize) as $donor) {
            $donorId = $donor['donor_id'];
            $results['processed']++;
            $processed++;
            
            // Try permanent address first
            if (is_null($donor['permanent_latitude']) && !empty($donor['permanent_address']) && $donor['permanent_address'] !== 'NULL') {
                $result = geocodeAndStore($donorId, $donor['permanent_address'], 'permanent');
                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to geocode permanent address for donor {$donorId}: {$result['error']}";
                }
            }
            
            // Try office address if permanent failed or doesn't exist
            if (is_null($donor['office_latitude']) && !empty($donor['office_address']) && $donor['office_address'] !== 'NULL') {
                $result = geocodeAndStore($donorId, $donor['office_address'], 'office');
                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to geocode office address for donor {$donorId}: {$result['error']}";
                }
            }
            
            // Add delay to respect rate limits
            if ($processed < $batchSize) {
                sleep(1);
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = "Exception: " . $e->getMessage();
    }
    
    return $results;
}

function geocodeAndStore($donorId, $address, $type) {
    try {
        error_log("AUTO-GEOCODE: Attempting to geocode donor {$donorId}, address: {$address}");
        
        // Use the improved geocoding endpoint
        $url = 'http://localhost/RED-CROSS-THESIS/public/api/improved-geocode-address.php';
        
        $postData = json_encode([
            'address' => $address,
            'donor_id' => $donorId
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $postData,
                'timeout' => 30
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            error_log("AUTO-GEOCODE: file_get_contents failed for donor {$donorId}: " . ($error['message'] ?? 'Unknown error'));
            return ['success' => false, 'error' => 'Failed to call geocoding API: ' . ($error['message'] ?? 'Unknown error')];
        }
        
        error_log("AUTO-GEOCODE: Response for donor {$donorId}: " . $response);
        
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }
        
        if (isset($result['lat']) && isset($result['lng'])) {
            // The improved geocoding endpoint already stores coordinates when donor_id is provided
            // So we just need to return success
            error_log("AUTO-GEOCODE: Successfully geocoded donor {$donorId} - {$result['lat']}, {$result['lng']}");
            return ['success' => true, 'lat' => $result['lat'], 'lng' => $result['lng']];
        }
        
        return ['success' => false, 'error' => 'No coordinates returned'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'auto_geocode';
    
    if ($action === 'auto_geocode') {
        try {
            error_log("AUTO-GEOCODE: Starting autoGeocodeMissing function");
            $results = autoGeocodeMissing();
            error_log("AUTO-GEOCODE: Function completed successfully");
            echo json_encode($results);
        } catch (Exception $e) {
            error_log("AUTO-GEOCODE: Exception occurred: " . $e->getMessage());
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
