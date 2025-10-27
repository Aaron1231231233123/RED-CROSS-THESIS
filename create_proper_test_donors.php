<?php
/**
 * Create Proper Test Donors
 * This script creates test donors using the correct field structure
 */

require_once 'assets/conn/db_conn.php';

echo "<h1>👥 Create Proper Test Donors</h1>";

// Enhanced function to make Supabase requests
function properSupabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . "/rest/v1/" . $endpoint;
    
    $headers = [
        "Content-Type: application/json",
        "apikey: " . SUPABASE_API_KEY,
        "Authorization: Bearer " . SUPABASE_API_KEY,
        "Prefer: return=representation"
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error,
        'decoded' => json_decode($response, true)
    ];
}

// Test donors with correct field structure and valid enum values
$test_donors = [
    [
        'surname' => 'Doe',
        'first_name' => 'John',
        'middle_name' => 'Michael',
        'birthdate' => '1998-01-15',
        'age' => 26,
        'sex' => 'Male',
        'civil_status' => 'Single',
        'permanent_address' => 'Santa Barbara, Iloilo, Philippines',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'occupation' => 'Engineer',
        'mobile' => '09123456789',
        'email' => 'john.doe@example.com',
        'education' => 'College',
        'registration_channel' => 'PRC Portal',
        'prc_donor_number' => 'PRC' . date('Y') . '0001',
        'doh_nnbnets_barcode' => 'DOH' . date('Y') . '0001'
    ],
    [
        'surname' => 'Smith',
        'first_name' => 'Jane',
        'middle_name' => 'Elizabeth',
        'birthdate' => '1995-05-20',
        'age' => 29,
        'sex' => 'Female',
        'civil_status' => 'Single',
        'permanent_address' => 'Oton, Iloilo, Philippines',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'occupation' => 'Teacher',
        'mobile' => '09123456790',
        'email' => 'jane.smith@example.com',
        'education' => 'College',
        'registration_channel' => 'Mobile',
        'prc_donor_number' => 'PRC' . date('Y') . '0002',
        'doh_nnbnets_barcode' => 'DOH' . date('Y') . '0002'
    ],
    [
        'surname' => 'Johnson',
        'first_name' => 'Mike',
        'middle_name' => 'Robert',
        'birthdate' => '1992-12-10',
        'age' => 32,
        'sex' => 'Male',
        'civil_status' => 'Married',
        'permanent_address' => 'Pototan, Iloilo, Philippines',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'occupation' => 'Doctor',
        'mobile' => '09123456791',
        'email' => 'mike.johnson@example.com',
        'education' => 'Post Graduate',
        'registration_channel' => 'PRC Portal',
        'prc_donor_number' => 'PRC' . date('Y') . '0003',
        'doh_nnbnets_barcode' => 'DOH' . date('Y') . '0003'
    ]
];

echo "<h2>🧪 Creating Test Donors with Correct Structure</h2>";

$created_donors = [];
$failed_donors = [];

foreach ($test_donors as $index => $donor_data) {
    echo "<h3>Creating Donor " . ($index + 1) . ": {$donor_data['first_name']} {$donor_data['surname']}</h3>";
    
    $result = properSupabaseRequest("donor_form", "POST", $donor_data);
    
    if ($result['http_code'] == 201 || $result['http_code'] == 200) {
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
        echo "✅ Successfully created donor: {$donor_data['first_name']} {$donor_data['surname']}";
        if (isset($result['decoded']) && !empty($result['decoded'])) {
            $created_donor = $result['decoded'][0];
            $created_donors[] = $created_donor;
            echo "<br><strong>Donor ID:</strong> {$created_donor['donor_id']}";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24; margin: 10px 0;'>";
        echo "❌ Failed to create donor: {$donor_data['first_name']} {$donor_data['surname']}";
        echo "<br><strong>Error:</strong> " . $result['response'];
        echo "</div>";
        $failed_donors[] = $donor_data;
    }
}

// Summary
echo "<h2>📊 Creation Summary</h2>";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 8px;'>";
echo "<p><strong>Successfully created:</strong> " . count($created_donors) . " donors</p>";
echo "<p><strong>Failed:</strong> " . count($failed_donors) . " donors</p>";

if (!empty($created_donors)) {
    echo "<h3>✅ Created Donors:</h3>";
    echo "<ul>";
    foreach ($created_donors as $donor) {
        echo "<li>ID: {$donor['donor_id']} - {$donor['first_name']} {$donor['surname']} - {$donor['mobile']}</li>";
    }
    echo "</ul>";
}

if (!empty($failed_donors)) {
    echo "<h3>❌ Failed Donors:</h3>";
    echo "<ul>";
    foreach ($failed_donors as $donor) {
        echo "<li>{$donor['first_name']} {$donor['surname']}</li>";
    }
    echo "</ul>";
}
echo "</div>";

// Now create blood type records for the created donors
if (!empty($created_donors)) {
    echo "<h2>🩸 Creating Blood Type Records</h2>";
    
    $blood_types = ['O+', 'A+', 'B+'];
    
    foreach ($created_donors as $index => $donor) {
        if (isset($blood_types[$index])) {
            $blood_type_data = [
                'donor_id' => $donor['donor_id'],
                'blood_type' => $blood_types[$index],
                'tested_at' => date('c'),
                'tested_by' => 'Test System'
            ];
            
            // Try to create blood type record (adjust table name as needed)
            $blood_result = properSupabaseRequest("blood_types", "POST", $blood_type_data);
            
            if ($blood_result['http_code'] == 201 || $blood_result['http_code'] == 200) {
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
                echo "✅ Created blood type record for {$donor['first_name']} {$donor['surname']}: {$blood_types[$index]}";
                echo "</div>";
            } else {
                echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404; margin: 10px 0;'>";
                echo "⚠️ Could not create blood type record for {$donor['first_name']} {$donor['surname']}";
                echo "<br><strong>Note:</strong> Blood type table might have different name or structure";
                echo "</div>";
            }
        }
    }
}

// Test push subscription creation
if (!empty($created_donors)) {
    echo "<h2>📱 Creating Test Push Subscriptions</h2>";
    
    foreach ($created_donors as $donor) {
        $subscription_data = [
            'donor_id' => $donor['donor_id'],
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-' . $donor['donor_id'],
            'p256dh' => 'test-p256dh-key-' . $donor['donor_id'],
            'auth' => 'test-auth-secret-' . $donor['donor_id']
        ];
        
        $sub_result = properSupabaseRequest("push_subscriptions", "POST", $subscription_data);
        
        if ($sub_result['http_code'] == 201 || $sub_result['http_code'] == 200) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724; margin: 10px 0;'>";
            echo "✅ Created push subscription for {$donor['first_name']} {$donor['surname']}";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24; margin: 10px 0;'>";
            echo "❌ Failed to create push subscription for {$donor['first_name']} {$donor['surname']}";
            echo "<br><strong>Error:</strong> " . $sub_result['response'];
            echo "</div>";
        }
    }
}

echo "<h2>🎯 Next Steps</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
echo "<ol>";
echo "<li><strong>Verify donors were created:</strong> Check your Supabase donor_form table</li>";
echo "<li><strong>Test the notification system:</strong> Use the GIS dashboard to send notifications</li>";
echo "<li><strong>Check blood type table:</strong> Verify the correct table name for blood types</li>";
echo "<li><strong>Update notification queries:</strong> Modify queries to join with blood type table</li>";
echo "</ol>";
echo "</div>";

echo "<p><strong>🎉 Test donors created with proper field structure!</strong></p>";
?>
