<?php
// Include database connection
include_once '../conn/db_conn.php';

// Check if donor_id is provided
$donorId = isset($_GET['donor_id']) ? $_GET['donor_id'] : null;

if (!$donorId) {
    // Return error if no donor_id provided
    header('Content-Type: application/json');
    echo json_encode([
        'is_eligible' => false,
        'status_message' => 'Donor ID is required',
        'remaining_days' => 0,
        'end_date' => null
    ]);
    exit;
}

// Function to execute an RPC query
function executeRPC($functionName, $params = []) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Prefer: return=representation'
    ];
    
    $url = SUPABASE_URL . '/rest/v1/rpc/' . $functionName;
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    return json_decode($response, true);
}

// Call the get_eligibility_status function from your SQL
$eligibilityStatus = executeRPC('get_eligibility_status', ['p_donor_id' => (int)$donorId]);

// Handle possible errors
if (isset($eligibilityStatus['error'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'is_eligible' => false,
        'status_message' => 'Error: ' . $eligibilityStatus['error'],
        'remaining_days' => 0,
        'end_date' => null
    ]);
    exit;
}

// Return the eligibility status
header('Content-Type: application/json');
echo json_encode($eligibilityStatus);
exit; 