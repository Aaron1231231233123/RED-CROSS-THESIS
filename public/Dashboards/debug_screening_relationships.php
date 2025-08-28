<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
    exit();
}

// Get all data
$donor_ch = curl_init();
curl_setopt_array($donor_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?select=*&order=submitted_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$donor_response = curl_exec($donor_ch);
$donor_forms = json_decode($donor_response, true) ?: [];
curl_close($donor_ch);

$screening_ch = curl_init();
curl_setopt_array($screening_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=*&order=created_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$screening_response = curl_exec($screening_ch);
$screening_forms = json_decode($screening_response, true) ?: [];
curl_close($screening_ch);

$blood_ch = curl_init();
curl_setopt_array($blood_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?select=*&order=start_time.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$blood_response = curl_exec($blood_ch);
$blood_collections = json_decode($blood_response, true) ?: [];
curl_close($blood_ch);

// Create lookup arrays
$donors_by_id = [];
foreach ($donor_forms as $donor) {
    $donors_by_id[$donor['donor_id']] = $donor;
}

$screenings_by_id = [];
foreach ($screening_forms as $screening) {
    $screenings_by_id[$screening['screening_id']] = $screening;
}

// Check for mismatched donor_form_id vs donor_id
$mismatched_screenings = [];
$valid_screenings = [];

foreach ($screening_forms as $screening) {
    $donor_form_id = $screening['donor_form_id'];
    $donor = $donors_by_id[$donor_form_id] ?? null;
    
    if ($donor) {
        $valid_screenings[] = [
            'screening_id' => $screening['screening_id'],
            'donor_form_id' => $donor_form_id,
            'donor_id' => $donor['donor_id'],
            'donor_name' => $donor['surname'] . ', ' . $donor['first_name'],
            'match' => ($donor_form_id == $donor['donor_id']) ? 'YES' : 'NO'
        ];
        
        if ($donor_form_id != $donor['donor_id']) {
            $mismatched_screenings[] = [
                'screening_id' => $screening['screening_id'],
                'donor_form_id' => $donor_form_id,
                'actual_donor_id' => $donor['donor_id'],
                'donor_name' => $donor['surname'] . ', ' . $donor['first_name']
            ];
        }
    } else {
        $mismatched_screenings[] = [
            'screening_id' => $screening['screening_id'],
            'donor_form_id' => $donor_form_id,
            'actual_donor_id' => 'NOT FOUND',
            'donor_name' => 'NOT FOUND'
        ];
    }
}

// Check blood collections
$blood_with_donor_info = [];
foreach ($blood_collections as $blood) {
    $screening = $screenings_by_id[$blood['screening_id']] ?? null;
    $donor = null;
    $donor_form_id = null;
    
    if ($screening) {
        $donor_form_id = $screening['donor_form_id'];
        $donor = $donors_by_id[$donor_form_id] ?? null;
    }
    
    $blood_with_donor_info[] = [
        'blood_collection_id' => $blood['blood_collection_id'],
        'screening_id' => $blood['screening_id'],
        'donor_form_id' => $donor_form_id,
        'donor_id' => $donor ? $donor['donor_id'] : 'N/A',
        'donor_name' => $donor ? $donor['surname'] . ', ' . $donor['first_name'] : 'N/A',
        'start_time' => $blood['start_time']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Screening Relationships Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .mismatch { background-color: #ffe6e6; }
        .match { background-color: #e6ffe6; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Screening Form Relationships Debug</h1>
        
        <div class="debug-section">
            <h3>Summary</h3>
            <ul>
                <li>Total Donor Forms: <?php echo count($donor_forms); ?></li>
                <li>Total Screening Forms: <?php echo count($screening_forms); ?></li>
                <li>Total Blood Collections: <?php echo count($blood_collections); ?></li>
                <li>Mismatched Screening Forms: <?php echo count($mismatched_screenings); ?></li>
            </ul>
        </div>

        <div class="debug-section">
            <h3>Mismatched Screening Forms (donor_form_id ≠ donor_id)</h3>
            <?php if (empty($mismatched_screenings)): ?>
                <div class="alert alert-success">No mismatched screening forms found!</div>
            <?php else: ?>
                <div class="alert alert-danger">Found <?php echo count($mismatched_screenings); ?> mismatched screening forms!</div>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Screening ID</th>
                            <th>Donor Form ID</th>
                            <th>Actual Donor ID</th>
                            <th>Donor Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mismatched_screenings as $mismatch): ?>
                            <tr class="mismatch">
                                <td><?php echo htmlspecialchars($mismatch['screening_id']); ?></td>
                                <td><?php echo htmlspecialchars($mismatch['donor_form_id']); ?></td>
                                <td><?php echo htmlspecialchars($mismatch['actual_donor_id']); ?></td>
                                <td><?php echo htmlspecialchars($mismatch['donor_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="debug-section">
            <h3>All Screening Forms with Donor Info</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Screening ID</th>
                        <th>Donor Form ID</th>
                        <th>Donor ID</th>
                        <th>Donor Name</th>
                        <th>Match?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($valid_screenings as $screening): ?>
                        <tr class="<?php echo $screening['match'] === 'YES' ? 'match' : 'mismatch'; ?>">
                            <td><?php echo htmlspecialchars($screening['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($screening['donor_form_id']); ?></td>
                            <td><?php echo htmlspecialchars($screening['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($screening['donor_name']); ?></td>
                            <td><?php echo htmlspecialchars($screening['match']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="debug-section">
            <h3>All Blood Collections with Donor Info</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Blood Collection ID</th>
                        <th>Screening ID</th>
                        <th>Donor Form ID</th>
                        <th>Donor ID</th>
                        <th>Donor Name</th>
                        <th>Start Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blood_with_donor_info as $blood): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($blood['blood_collection_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['donor_form_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['donor_name']); ?></td>
                            <td><?php echo htmlspecialchars($blood['start_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>








