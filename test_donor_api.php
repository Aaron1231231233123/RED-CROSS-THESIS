<?php
// Simple test file to check if the comprehensive donor details API works
echo "<h2>Testing Comprehensive Donor Details API</h2>";

// Test with a sample donor ID
$test_donor_id = '162'; // Using the donor ID from the error
$test_eligibility_id = ''; // Empty eligibility ID

echo "<p>Testing with Donor ID: $test_donor_id</p>";
echo "<p>Testing with Eligibility ID: $test_eligibility_id</p>";

// Test the API URL
$api_url = "http://localhost/REDCROSS/assets/php_func/comprehensive_donor_details_api.php?donor_id=" . urlencode($test_donor_id) . "&eligibility_id=" . urlencode($test_eligibility_id);

echo "<h3>API URL:</h3>";
echo "<p><a href='$api_url' target='_blank'>$api_url</a></p>";

// Test the API call
echo "<h3>API Response:</h3>";
$response = file_get_contents($api_url);
if ($response === false) {
    echo "<p style='color: red;'>Failed to fetch API response</p>";
} else {
    echo "<h4>Raw Response:</h4>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Check if response starts with HTML tags
    if (strpos($response, '<') === 0) {
        echo "<p style='color: red;'>Response contains HTML instead of JSON!</p>";
        echo "<p>This indicates a PHP error or warning is being output.</p>";
    }
    
    // Try to decode JSON
    $data = json_decode($response, true);
    if ($data === null) {
        echo "<p style='color: red;'>Invalid JSON response</p>";
        echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
    } else {
        echo "<h3>Decoded Data:</h3>";
        echo "<pre>" . print_r($data, true) . "</pre>";
        
        // Specifically check eligibility data
        if (isset($data['eligibility'])) {
            echo "<h4>Eligibility Data Analysis:</h4>";
            $eligibility = $data['eligibility'];
            echo "<ul>";
            echo "<li>Eligibility Data Exists: " . (empty($eligibility) ? 'No' : 'Yes') . "</li>";
            if (!empty($eligibility)) {
                echo "<li>Status: " . ($eligibility['status'] ?? 'not set') . "</li>";
                echo "<li>Donation Type: " . ($eligibility['donation_type'] ?? 'not set') . "</li>";
                echo "<li>Blood Type: " . ($eligibility['blood_type'] ?? 'not set') . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>No eligibility data in response!</p>";
        }
    }
}

// Also test the original API for comparison
echo "<hr><h3>Testing Original API for Comparison:</h3>";
$original_api_url = "http://localhost/REDCROSS/assets/php_func/donor_details_api.php?donor_id=" . urlencode($test_donor_id) . "&eligibility_id=" . urlencode($test_eligibility_id);
echo "<p><a href='$original_api_url' target='_blank'>$original_api_url</a></p>";

$original_response = file_get_contents($original_api_url);
if ($original_response === false) {
    echo "<p style='color: red;'>Failed to fetch original API response</p>";
} else {
    echo "<h4>Original API Raw Response:</h4>";
    echo "<pre>" . htmlspecialchars($original_response) . "</pre>";
    
    $original_data = json_decode($original_response, true);
    if ($original_data === null) {
        echo "<p style='color: red;'>Original API also has invalid JSON</p>";
    } else {
        echo "<h4>Original API Decoded Data:</h4>";
        echo "<pre>" . print_r($original_data, true) . "</pre>";
    }
}
?>
