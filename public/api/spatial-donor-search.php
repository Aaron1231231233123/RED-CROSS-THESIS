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

// Function to find donors within radius using PostGIS
function findDonorsWithinRadius($lat, $lng, $radiusKm = 10, $bloodType = null) {
    $radiusMeters = $radiusKm * 1000;
    
    $query = "
        SELECT 
            df.donor_id,
            df.permanent_address,
            df.office_address,
            df.permanent_latitude,
            df.permanent_longitude,
            ST_Distance(df.permanent_geom, ST_MakePoint(?, ?)::geography) as distance_meters,
            bbu.blood_type,
            bbu.status,
            bbu.expires_at
        FROM donor_form df
        JOIN blood_bank_units bbu ON df.donor_id = bbu.donor_id
        WHERE df.permanent_geom IS NOT NULL
            AND bbu.status = 'Valid'
            AND bbu.expires_at > NOW()
            AND ST_DWithin(
                df.permanent_geom,
                ST_MakePoint(?, ?)::geography,
                ?
            )
    ";
    
    $params = [$lng, $lat, $lng, $lat, $radiusMeters];
    
    if ($bloodType) {
        $query .= " AND bbu.blood_type = ?";
        $params[] = $bloodType;
    }
    
    $query .= " ORDER BY ST_Distance(df.permanent_geom, ST_MakePoint(?, ?)::geography) LIMIT 50";
    $params[] = $lng;
    $params[] = $lat;
    
    return querySQL('', '', [], $query, $params);
}

// Function to get donor density for heatmap
function getDonorDensity($gridSize = 0.01) {
    $query = "
        SELECT 
            ST_X(ST_Centroid(ST_Collect(df.permanent_geom::geometry))) as center_lng,
            ST_Y(ST_Centroid(ST_Collect(df.permanent_geom::geometry))) as center_lat,
            COUNT(*) as donor_count
        FROM donor_form df
        JOIN blood_bank_units bbu ON df.donor_id = bbu.donor_id
        WHERE df.permanent_geom IS NOT NULL
            AND bbu.status = 'Valid'
            AND bbu.expires_at > NOW()
        GROUP BY ST_SnapToGrid(df.permanent_geom::geometry, ?, ?)
        HAVING COUNT(*) > 0
        ORDER BY donor_count DESC
    ";
    
    return querySQL('', '', [], $query, [$gridSize, $gridSize]);
}

// Function to find donors by city
function findDonorsByCity($cityName) {
    $query = "
        SELECT 
            df.donor_id,
            df.permanent_address,
            df.permanent_latitude,
            df.permanent_longitude,
            bbu.blood_type,
            bbu.status
        FROM donor_form df
        JOIN blood_bank_units bbu ON df.donor_id = bbu.donor_id
        WHERE df.permanent_geom IS NOT NULL
            AND bbu.status = 'Valid'
            AND bbu.expires_at > NOW()
            AND (
                LOWER(df.permanent_address) LIKE LOWER(?)
                OR LOWER(df.office_address) LIKE LOWER(?)
            )
        ORDER BY df.donor_id
        LIMIT 100
    ";
    
    $cityPattern = "%{$cityName}%";
    return querySQL('', '', [], $query, [$cityPattern, $cityPattern]);
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'find_nearby':
            $lat = floatval($input['lat'] ?? 0);
            $lng = floatval($input['lng'] ?? 0);
            $radius = floatval($input['radius'] ?? 10);
            $bloodType = $input['blood_type'] ?? null;
            
            if ($lat == 0 || $lng == 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid coordinates']);
                exit();
            }
            
            $donors = findDonorsWithinRadius($lat, $lng, $radius, $bloodType);
            echo json_encode(['donors' => $donors]);
            break;
            
        case 'get_density':
            $gridSize = floatval($input['grid_size'] ?? 0.01);
            $density = getDonorDensity($gridSize);
            echo json_encode(['density' => $density]);
            break;
            
        case 'find_by_city':
            $cityName = $input['city'] ?? '';
            if (empty($cityName)) {
                http_response_code(400);
                echo json_encode(['error' => 'City name is required']);
                exit();
            }
            
            $donors = findDonorsByCity($cityName);
            echo json_encode(['donors' => $donors]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
