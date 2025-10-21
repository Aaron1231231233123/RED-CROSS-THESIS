<?php
// Test the API with full dataset
include_once 'assets/conn/db_conn.php';

try {
    echo "<h1>API Full Data Test</h1>\n";
    echo "<p>Testing the forecast-reports-api.php with full dataset...</p>\n";
    
    // Make request to the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/RED-CROSS-THESIS/public/api/forecast-reports-api.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('CURL Error: ' . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('API request failed with code: ' . $httpCode . ' Response: ' . $response);
    }
    
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }
    
    if (!$data['success']) {
        throw new Exception('API returned success: false');
    }
    
    echo "<h2>API Response Summary</h2>\n";
    echo "<p><strong>Success:</strong> " . ($data['success'] ? 'Yes' : 'No') . "</p>\n";
    
    // Check data counts
    if (isset($data['debug_frontend_data']['database_summary'])) {
        $summary = $data['debug_frontend_data']['database_summary'];
        echo "<h3>Database Summary</h3>\n";
        echo "<p><strong>Total Blood Units:</strong> " . $summary['total_blood_units'] . "</p>\n";
        echo "<p><strong>Total Months:</strong> " . $summary['total_months'] . "</p>\n";
        echo "<p><strong>Years Covered:</strong> " . $summary['years_covered'] . "</p>\n";
        echo "<p><strong>2023 Months:</strong> " . $summary['months_2023'] . "</p>\n";
        echo "<p><strong>2024 Months:</strong> " . $summary['months_2024'] . "</p>\n";
        echo "<p><strong>2025 Months:</strong> " . $summary['months_2025'] . "</p>\n";
    }
    
    // Check month arrays
    echo "<h3>Month Arrays</h3>\n";
    echo "<p><strong>Historical Months:</strong> " . count($data['historical_months']) . " months</p>\n";
    echo "<p><strong>Forecast Months:</strong> " . count($data['forecast_months']) . " months</p>\n";
    echo "<p><strong>All Months:</strong> " . count($data['all_months']) . " months</p>\n";
    
    // Show sample months
    if (!empty($data['historical_months'])) {
        echo "<p><strong>Sample Historical Months:</strong> " . implode(', ', array_slice($data['historical_months'], 0, 5)) . "...</p>\n";
    }
    
    if (!empty($data['forecast_months'])) {
        echo "<p><strong>Sample Forecast Months:</strong> " . implode(', ', array_slice($data['forecast_months'], 0, 5)) . "...</p>\n";
    }
    
    // Check forecast data
    echo "<h3>Forecast Data</h3>\n";
    echo "<p><strong>Total Forecast Records:</strong> " . count($data['forecast_data']) . "</p>\n";
    
    if (!empty($data['forecast_data'])) {
        $sampleForecast = $data['forecast_data'][0];
        echo "<p><strong>Sample Forecast Record:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Blood Type: " . $sampleForecast['blood_type'] . "</li>\n";
        echo "<li>Month: " . $sampleForecast['month'] . "</li>\n";
        echo "<li>Forecasted Supply: " . $sampleForecast['forecasted_supply'] . "</li>\n";
        echo "<li>Forecasted Demand: " . $sampleForecast['forecasted_demand'] . "</li>\n";
        echo "<li>Projected Balance: " . $sampleForecast['projected_balance'] . "</li>\n";
        echo "</ul>\n";
    }
    
    // Check KPIs
    if (isset($data['kpis'])) {
        echo "<h3>KPIs</h3>\n";
        echo "<p><strong>Total Supply:</strong> " . $data['kpis']['total_supply'] . "</p>\n";
        echo "<p><strong>Total Demand:</strong> " . $data['kpis']['total_demand'] . "</p>\n";
        echo "<p><strong>Total Balance:</strong> " . $data['kpis']['total_balance'] . "</p>\n";
        echo "<p><strong>Critical Blood Types:</strong> " . (is_array($data['kpis']['critical_types']) ? implode(', ', $data['kpis']['critical_types']) : $data['kpis']['critical_types']) . "</p>\n";
    }
    
    echo "<h3>Data Source Explanations</h3>\n";
    if (isset($data['debug_frontend_data']['data_source_explanation'])) {
        echo "<p><strong>Data Source:</strong> " . $data['debug_frontend_data']['data_source_explanation'] . "</p>\n";
    }
    if (isset($data['debug_frontend_data']['forecast_explanation'])) {
        echo "<p><strong>Forecast:</strong> " . $data['debug_frontend_data']['forecast_explanation'] . "</p>\n";
    }
    
    echo "<h2>✅ API Test Results</h2>\n";
    echo "<p style='color: green;'><strong>SUCCESS:</strong> API is working correctly with full dataset!</p>\n";
    echo "<p>The API successfully processed " . $data['debug_frontend_data']['database_summary']['total_blood_units'] . " blood units across " . $data['debug_frontend_data']['database_summary']['total_months'] . " months.</p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ API Test Failed</h2>\n";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>

