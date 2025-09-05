<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
include_once '../../assets/conn/db_conn.php';
include_once '../Dashboards/module/optimized_functions.php';

echo "🔧 Fixing NULL Coordinates with Improved Accuracy\n";
echo "================================================\n\n";

// Get all donors with NULL coordinates
$donorsToFix = supabaseRequest("donor_form?select=donor_id,permanent_address,office_address,permanent_latitude,permanent_longitude,office_latitude,office_longitude&or=(permanent_latitude.is.null,office_latitude.is.null)");

if (empty($donorsToFix['data'])) {
    echo "✅ No donors with NULL coordinates found!\n";
    exit();
}

$total = count($donorsToFix['data']);
$processed = 0;
$successful = 0;
$failed = 0;
$errors = [];

echo "📊 Found {$total} donors with NULL coordinates\n\n";

foreach ($donorsToFix['data'] as $donor) {
    $donorId = $donor['donor_id'];
    $processed++;
    
    echo "Processing donor {$donorId} ({$processed}/{$total})...\n";
    
    // Try permanent address first
    if (empty($donor['permanent_latitude']) && !empty($donor['permanent_address'])) {
        $result = geocodeDonorAddress($donorId, $donor['permanent_address'], 'permanent');
        if ($result['success']) {
            $successful++;
            echo "  ✅ Permanent address geocoded: {$donor['permanent_address']}\n";
        } else {
            $failed++;
            $errors[] = "Failed to geocode permanent address for donor {$donorId}: {$result['error']}";
            echo "  ❌ Permanent address failed: {$result['error']}\n";
        }
    }
    
    // Try office address if permanent failed or doesn't exist
    if (empty($donor['office_latitude']) && !empty($donor['office_address']) && $donor['office_address'] !== 'NULL') {
        $result = geocodeDonorAddress($donorId, $donor['office_address'], 'office');
        if ($result['success']) {
            $successful++;
            echo "  ✅ Office address geocoded: {$donor['office_address']}\n";
        } else {
            $failed++;
            $errors[] = "Failed to geocode office address for donor {$donorId}: {$result['error']}";
            echo "  ❌ Office address failed: {$result['error']}\n";
        }
    }
    
    // Add delay to respect rate limits
    sleep(1);
}

echo "\n🎯 Results Summary:\n";
echo "==================\n";
echo "Total donors: {$total}\n";
echo "Processed: {$processed}\n";
echo "Successful: {$successful}\n";
echo "Failed: {$failed}\n";

if (!empty($errors)) {
    echo "\n❌ Errors:\n";
    foreach (array_slice($errors, 0, 5) as $error) {
        echo "  - {$error}\n";
    }
    if (count($errors) > 5) {
        echo "  ... and " . (count($errors) - 5) . " more errors\n";
    }
}

echo "\n✅ NULL coordinates fix complete!\n";

function geocodeDonorAddress($donorId, $address, $type) {
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
        return ['success' => false, 'error' => 'Failed to call geocoding API'];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['error'])) {
        return ['success' => false, 'error' => $result['error']];
    }
    
    if (isset($result['lat']) && isset($result['lng'])) {
        return ['success' => true, 'lat' => $result['lat'], 'lng' => $result['lng']];
    }
    
    return ['success' => false, 'error' => 'No coordinates returned'];
}
?>
