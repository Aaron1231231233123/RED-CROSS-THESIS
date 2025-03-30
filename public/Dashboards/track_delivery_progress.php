<?php
header('Content-Type: application/json');

class DeliveryTracker {
    private $apiKey = '5b3ce3597851110001cf62489be6ba0ef4ad4aa59c60e5d09efb5a4d';
    private $baseUrl = 'https://api.openrouteservice.org/v2/directions/driving-car';

    public function calculateETA($originLat, $originLon, $destLat, $destLon) {
        // Format coordinates for OpenRoute API (longitude,latitude format)
        $coordinates = "$originLon,$originLat|$destLon,$destLat";
        
        // Build the API URL with parameters
        $url = $this->baseUrl . "?api_key=" . $this->apiKey . 
               "&start=" . $originLon . "," . $originLat .
               "&end=" . $destLon . "," . $destLat;

        // Initialize cURL session
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, application/geo+json, application/gpx+xml, img/png; charset=utf-8'
            ]
        ]);

        // Execute the request
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Failed to calculate ETA: ' . $err];
        }

        // Parse the response
        $data = json_decode($response, true);
        
        // Check if we have valid route data
        if (isset($data['features'][0]['properties']['segments'][0])) {
            $segment = $data['features'][0]['properties']['segments'][0];
            $duration = $segment['duration'];
            $distance = $segment['distance'];
            $coordinates = $data['features'][0]['geometry']['coordinates'];
            
            // Add some buffer time for blood preparation and handling
            $preparationTime = 10; // 10 minutes for blood preparation
            $handlingTime = 5; // 5 minutes for handling at both ends
            
            return [
                'duration' => round($duration / 60) + $preparationTime + $handlingTime, // Convert seconds to minutes and add buffer
                'distance' => round($distance / 1000, 2), // Convert meters to kilometers
                'route' => $coordinates,
                'preparation_time' => $preparationTime,
                'handling_time' => $handlingTime,
                'traffic_conditions' => $this->getTrafficConditions($duration, $distance)
            ];
        }

        return ['error' => 'Unable to calculate route'];
    }

    private function getTrafficConditions($duration, $distance) {
        // Calculate average speed in km/h
        $speedKmh = ($distance / 1000) / ($duration / 3600);
        
        // Determine traffic conditions based on average speed
        if ($speedKmh > 40) {
            return 'Light Traffic';
        } elseif ($speedKmh > 20) {
            return 'Moderate Traffic';
        } else {
            return 'Heavy Traffic';
        }
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tracker = new DeliveryTracker();
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['action']) && $data['action'] === 'calculate_eta') {
        if (isset($data['origin_lat'], $data['origin_lon'], $data['dest_lat'], $data['dest_lon'])) {
            $result = $tracker->calculateETA(
                $data['origin_lat'],
                $data['origin_lon'],
                $data['dest_lat'],
                $data['dest_lon']
            );
            echo json_encode($result);
        } else {
            echo json_encode(['error' => 'Missing coordinates']);
        }
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}
?> 