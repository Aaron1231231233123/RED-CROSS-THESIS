<?php
// Test script to verify duplicate eligibility prevention
require_once 'assets/conn/db_conn.php';

function testDuplicatePrevention() {
    echo "Testing duplicate eligibility prevention...\n\n";
    
    // Test data for a fake donor
    $testDonorId = 999999; // Use a high number that likely doesn't exist
    
    // Test 1: Create initial eligibility via create_eligibility.php
    echo "Test 1: Creating initial eligibility record...\n";
    $response1 = callCreateEligibility($testDonorId);
    echo "Response 1: " . json_encode($response1) . "\n\n";
    
    // Test 2: Try to create duplicate via create_eligibility.php
    echo "Test 2: Attempting to create duplicate via create_eligibility.php...\n";
    $response2 = callCreateEligibility($testDonorId);
    echo "Response 2: " . json_encode($response2) . "\n\n";
    
    // Test 3: Try to create duplicate via create_eligibility_record.php
    echo "Test 3: Attempting to create duplicate via create_eligibility_record.php...\n";
    $response3 = callCreateEligibilityRecord($testDonorId);
    echo "Response 3: " . json_encode($response3) . "\n\n";
    
    // Cleanup: Remove the test eligibility record
    echo "Cleanup: Removing test eligibility record...\n";
    $cleanupResponse = deleteTestEligibility($testDonorId);
    echo "Cleanup response: " . json_encode($cleanupResponse) . "\n\n";
    
    // Results
    $success1 = isset($response1['success']) && $response1['success'] === true;
    $blocked2 = isset($response2['success']) && $response2['success'] === false && 
                strpos($response2['message'] ?? '', 'already exists') !== false;
    $blocked3 = isset($response3['success']) && $response3['success'] === false && 
                strpos($response3['message'] ?? '', 'already exists') !== false;
    
    echo "=== TEST RESULTS ===\n";
    echo "Initial creation successful: " . ($success1 ? "✅ PASS" : "❌ FAIL") . "\n";
    echo "Duplicate blocked (create_eligibility.php): " . ($blocked2 ? "✅ PASS" : "❌ FAIL") . "\n";
    echo "Duplicate blocked (create_eligibility_record.php): " . ($blocked3 ? "✅ PASS" : "❌ FAIL") . "\n";
    
    if ($success1 && $blocked2 && $blocked3) {
        echo "\n🎉 ALL TESTS PASSED! Duplicate prevention is working correctly.\n";
    } else {
        echo "\n⚠️ SOME TESTS FAILED. Check the responses above for details.\n";
    }
}

function callCreateEligibility($donorId) {
    $url = 'http://localhost/REDCROSS/assets/php_func/create_eligibility.php';
    $data = [
        'donor_id' => $donorId,
        'status' => 'approved'
    ];
    
    return makeApiCall($url, $data);
}

function callCreateEligibilityRecord($donorId) {
    $url = 'http://localhost/REDCROSS/assets/php_func/create_eligibility_record.php';
    $data = [
        'donor_id' => $donorId,
        'status' => 'approved'
    ];
    
    return makeApiCall($url, $data);
}

function makeApiCall($url, $data) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'cURL error: ' . $error];
    }
    
    $decodedResponse = json_decode($response, true);
    if ($decodedResponse === null) {
        return ['success' => false, 'error' => 'Invalid JSON response', 'raw_response' => $response];
    }
    
    return $decodedResponse;
}

function deleteTestEligibility($donorId) {
    // Delete the test eligibility record directly via Supabase API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/eligibility?donor_id=eq.' . $donorId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['success' => $httpCode === 204, 'http_code' => $httpCode];
}

// Run the test if this file is accessed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    testDuplicatePrevention();
}
?> 