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

// Find all Nelwin James records
$nelwin_records = [];
foreach ($donor_forms as $donor) {
    if (strpos($donor['first_name'], 'Nelwin') !== false && strpos($donor['surname'], 'Oscares') !== false) {
        $nelwin_records[] = $donor;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Relationships Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .highlight { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Donor Relationships Debug - Nelwin James Analysis</h1>
        
        <div class="debug-section">
            <h3>All Nelwin James Records</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Donor ID</th>
                        <th>Name</th>
                        <th>Birthdate</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nelwin_records as $donor): ?>
                        <tr class="highlight">
                            <td><?php echo htmlspecialchars($donor['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($donor['surname'] . ', ' . $donor['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($donor['birthdate']); ?></td>
                            <td><?php echo htmlspecialchars($donor['submitted_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="debug-section">
            <h3>Blood Collections for Nelwin James</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Blood Collection ID</th>
                        <th>Screening ID</th>
                        <th>Start Time</th>
                        <th>Related Donor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blood_collections as $blood): ?>
                        <?php 
                        $screening = $screenings_by_id[$blood['screening_id']] ?? null;
                        if ($screening) {
                            $donor = $donors_by_id[$screening['donor_form_id']] ?? null;
                            if ($donor && strpos($donor['first_name'], 'Nelwin') !== false && strpos($donor['surname'], 'Oscares') !== false) {
                        ?>
                            <tr class="highlight">
                                <td><?php echo htmlspecialchars($blood['blood_collection_id']); ?></td>
                                <td><?php echo htmlspecialchars($blood['screening_id']); ?></td>
                                <td><?php echo htmlspecialchars($blood['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['surname'] . ', ' . $donor['first_name']); ?></td>
                            </tr>
                        <?php 
                            }
                        }
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="debug-section">
            <h3>Screening Forms for Nelwin James</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Screening ID</th>
                        <th>Donor Form ID</th>
                        <th>Created At</th>
                        <th>Related Donor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($screening_forms as $screening): ?>
                        <?php 
                        $donor = $donors_by_id[$screening['donor_form_id']] ?? null;
                        if ($donor && strpos($donor['first_name'], 'Nelwin') !== false && strpos($donor['surname'], 'Oscares') !== false) {
                        ?>
                            <tr class="highlight">
                                <td><?php echo htmlspecialchars($screening['screening_id']); ?></td>
                                <td><?php echo htmlspecialchars($screening['donor_form_id']); ?></td>
                                <td><?php echo htmlspecialchars($screening['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['surname'] . ', ' . $donor['first_name']); ?></td>
                            </tr>
                        <?php } ?>
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
                    <?php foreach ($blood_collections as $blood): ?>
                        <?php 
                        $screening = $screenings_by_id[$blood['screening_id']] ?? null;
                        $donor = null;
                        if ($screening) {
                            $donor = $donors_by_id[$screening['donor_form_id']] ?? null;
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($blood['blood_collection_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['screening_id']); ?></td>
                            <td><?php echo $screening ? htmlspecialchars($screening['donor_form_id']) : 'N/A'; ?></td>
                            <td><?php echo $donor ? htmlspecialchars($donor['donor_id']) : 'N/A'; ?></td>
                            <td><?php echo $donor ? htmlspecialchars($donor['surname'] . ', ' . $donor['first_name']) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($blood['start_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>



