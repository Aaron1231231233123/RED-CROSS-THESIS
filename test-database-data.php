<?php
// Test script to verify database data retrieval
include_once 'assets/conn/db_conn.php';

// Function to make Supabase requests with pagination support
function supabaseRequest($endpoint, $limit = 1000) {
    $allData = [];
    $offset = 0;
    
    do {
        $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
        if (strpos($endpoint, '?') !== false) {
            $url .= '&limit=' . $limit . '&offset=' . $offset;
        } else {
            $url .= '?limit=' . $limit . '&offset=' . $offset;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
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
            throw new Exception('Supabase request failed with code: ' . $httpCode . ' Response: ' . $response);
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }
        
        $allData = array_merge($allData, $data);
        $offset += $limit;
        
        // Break if we got fewer records than requested (end of data)
        if (count($data) < $limit) {
            break;
        }
        
    } while (count($data) == $limit);
    
    return $allData;
}

try {
    echo "<h1>Database Data Test</h1>\n";
    echo "<p><strong>Note:</strong> This test will fetch ALL records using pagination to bypass Supabase's 1000 record limit.</p>\n";
    
    // Test blood_bank_units data
    echo "<h2>Blood Bank Units Data</h2>\n";
    echo "<p>Fetching all blood bank units with pagination...</p>\n";
    $bloodUnits = supabaseRequest("blood_bank_units?select=blood_type,collected_at,created_at,status&order=collected_at.asc");
    
    echo "<p>Total blood units found: " . count($bloodUnits) . "</p>\n";
    
    if (!empty($bloodUnits)) {
        echo "<h3>Sample Records:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Blood Type</th><th>Collected At</th><th>Created At</th><th>Status</th></tr>\n";
        
        foreach (array_slice($bloodUnits, 0, 5) as $unit) {
            echo "<tr>";
            echo "<td>" . ($unit['blood_type'] ?? 'N/A') . "</td>";
            echo "<td>" . ($unit['collected_at'] ?? 'N/A') . "</td>";
            echo "<td>" . ($unit['created_at'] ?? 'N/A') . "</td>";
            echo "<td>" . ($unit['status'] ?? 'N/A') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Show date range
        $dates = [];
        $years = [];
        foreach ($bloodUnits as $unit) {
            $dateField = !empty($unit['collected_at']) ? $unit['collected_at'] : $unit['created_at'];
            if ($dateField) {
                $dates[] = substr($dateField, 0, 7); // YYYY-MM
                $year = substr($dateField, 0, 4);
                if (!in_array($year, $years)) {
                    $years[] = $year;
                }
            }
        }
        $uniqueDates = array_unique($dates);
        sort($uniqueDates);
        sort($years);
        
        echo "<h3>Date Range in Database:</h3>\n";
        echo "<p>Years with data: " . implode(', ', $years) . "</p>\n";
        echo "<p>Total months with data: " . count($uniqueDates) . "</p>\n";
        echo "<p>Date range: " . min($uniqueDates) . " to " . max($uniqueDates) . "</p>\n";
        
        // Show sample of months
        $sampleMonths = array_slice($uniqueDates, 0, 10);
        echo "<p>Sample months: " . implode(', ', $sampleMonths) . "</p>\n";
        
        // Check for 2024-2025 data
        $has2024 = false;
        $has2025 = false;
        $count2024 = 0;
        $count2025 = 0;
        foreach ($uniqueDates as $date) {
            if (strpos($date, '2024') === 0) {
                $has2024 = true;
                $count2024++;
            }
            if (strpos($date, '2025') === 0) {
                $has2025 = true;
                $count2025++;
            }
        }
        
        echo "<p>Has 2024 data: " . ($has2024 ? "YES ({$count2024} months)" : 'NO') . "</p>\n";
        echo "<p>Has 2025 data: " . ($has2025 ? "YES ({$count2025} months)" : 'NO') . "</p>\n";
        
        // Blood type analysis
        $bloodTypeCounts = [];
        foreach ($bloodUnits as $unit) {
            $bloodType = $unit['blood_type'] ?? 'Unknown';
            $bloodTypeCounts[$bloodType] = ($bloodTypeCounts[$bloodType] ?? 0) + 1;
        }
        
        echo "<h3>Blood Type Distribution:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Blood Type</th><th>Count</th><th>Percentage</th></tr>\n";
        $totalUnits = count($bloodUnits);
        foreach ($bloodTypeCounts as $type => $count) {
            $percentage = round(($count / $totalUnits) * 100, 1);
            echo "<tr><td>{$type}</td><td>{$count}</td><td>{$percentage}%</td></tr>\n";
        }
        echo "</table>\n";
    }
    
    // Test blood_requests data
    echo "<h2>Blood Requests Data</h2>\n";
    $bloodRequests = supabaseRequest("blood_requests?select=patient_blood_type,rh_factor,units_requested,requested_on,status&order=requested_on.asc");
    
    echo "<p>Total blood requests found: " . count($bloodRequests) . "</p>\n";
    
    if (!empty($bloodRequests)) {
        echo "<h3>Sample Records:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Patient Blood Type</th><th>RH Factor</th><th>Units Requested</th><th>Requested On</th><th>Status</th></tr>\n";
        
        foreach (array_slice($bloodRequests, 0, 5) as $request) {
            echo "<tr>";
            echo "<td>" . ($request['patient_blood_type'] ?? 'N/A') . "</td>";
            echo "<td>" . ($request['rh_factor'] ?? 'N/A') . "</td>";
            echo "<td>" . ($request['units_requested'] ?? 'N/A') . "</td>";
            echo "<td>" . ($request['requested_on'] ?? 'N/A') . "</td>";
            echo "<td>" . ($request['status'] ?? 'N/A') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    echo "<h2>Summary</h2>\n";
    echo "<p>This test shows the actual data available in your database.</p>\n";
    echo "<p>If you see 2024-2025 data above, then the API should be able to display it properly.</p>\n";
    echo "<p>If you don't see 2024-2025 data, then the database needs to be populated with more recent data.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>
