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

// Check for multiple blood collections per donor
$blood_collections_by_donor = [];
foreach ($blood_collections as $blood) {
    $screening = $screenings_by_id[$blood['screening_id']] ?? null;
    if ($screening) {
        $donor_id = $screening['donor_form_id'];
        if (!isset($blood_collections_by_donor[$donor_id])) {
            $blood_collections_by_donor[$donor_id] = [];
        }
        $blood_collections_by_donor[$donor_id][] = $blood;
    }
}

// Find donors with multiple blood collections
$multiple_blood_donors = [];
foreach ($blood_collections_by_donor as $donor_id => $collections) {
    if (count($collections) > 1) {
        $multiple_blood_donors[$donor_id] = $collections;
    }
}

// Check for specific donors mentioned in the images
$villanueva_donor_id = null;
$oscares_donor_ids = [];

foreach ($donor_forms as $donor) {
    if (strpos($donor['surname'], 'Villanueva') !== false && strpos($donor['first_name'], 'Erica Nicole') !== false) {
        $villanueva_donor_id = $donor['donor_id'];
    }
    if (strpos($donor['surname'], 'Oscares') !== false && strpos($donor['first_name'], 'Nelwin James') !== false) {
        $oscares_donor_ids[] = $donor['donor_id'];
    }
}

// Check blood collections for these specific donors
$villanueva_blood_collections = [];
$oscares_blood_collections = [];

foreach ($blood_collections as $blood) {
    $screening = $screenings_by_id[$blood['screening_id']] ?? null;
    if ($screening) {
        $donor_id = $screening['donor_form_id'];
        
        if ($donor_id == $villanueva_donor_id) {
            $villanueva_blood_collections[] = $blood;
        }
        
        if (in_array($donor_id, $oscares_donor_ids)) {
            $oscares_blood_collections[] = [
                'blood' => $blood,
                'donor_id' => $donor_id
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicate Sources Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .duplicate { background-color: #ffe6e6; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Duplicate Sources Debug</h1>
        
        <div class="debug-section">
            <h3>Summary</h3>
            <ul>
                <li>Total Donor Forms: <?php echo count($donor_forms); ?></li>
                <li>Total Screening Forms: <?php echo count($screening_forms); ?></li>
                <li>Total Blood Collections: <?php echo count($blood_collections); ?></li>
                <li>Donors with Multiple Blood Collections: <?php echo count($multiple_blood_donors); ?></li>
            </ul>
        </div>

        <?php if (!empty($multiple_blood_donors)): ?>
        <div class="debug-section">
            <h3>Donors with Multiple Blood Collections</h3>
            <div class="alert alert-warning">
                Found <?php echo count($multiple_blood_donors); ?> donors with multiple blood collections!
            </div>
            <?php foreach ($multiple_blood_donors as $donor_id => $collections): ?>
                <?php $donor = $donors_by_id[$donor_id] ?? null; ?>
                <div class="duplicate">
                    <strong>Donor ID <?php echo $donor_id; ?>: <?php echo $donor ? $donor['surname'] . ', ' . $donor['first_name'] : 'Unknown'; ?></strong>
                    <ul>
                        <?php foreach ($collections as $collection): ?>
                            <li>Blood Collection ID: <?php echo $collection['blood_collection_id']; ?> - Screening ID: <?php echo $collection['screening_id']; ?> - Date: <?php echo $collection['start_time']; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($villanueva_blood_collections)): ?>
        <div class="debug-section">
            <h3>Villanueva, Erica Nicole Blood Collections</h3>
            <div class="alert alert-info">
                Found <?php echo count($villanueva_blood_collections); ?> blood collections for Villanueva, Erica Nicole (Donor ID: <?php echo $villanueva_donor_id; ?>)
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Blood Collection ID</th>
                        <th>Screening ID</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($villanueva_blood_collections as $blood): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($blood['blood_collection_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['start_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($oscares_blood_collections)): ?>
        <div class="debug-section">
            <h3>Oscares, Nelwin James Blood Collections</h3>
            <div class="alert alert-info">
                Found <?php echo count($oscares_blood_collections); ?> blood collections for Oscares, Nelwin James
            </div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Donor ID</th>
                        <th>Blood Collection ID</th>
                        <th>Screening ID</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oscares_blood_collections as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['blood']['blood_collection_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['blood']['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($item['blood']['start_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="debug-section">
            <h3>All Blood Collections</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Blood Collection ID</th>
                        <th>Screening ID</th>
                        <th>Donor Form ID</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blood_collections as $blood): ?>
                        <?php $screening = $screenings_by_id[$blood['screening_id']] ?? null; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($blood['blood_collection_id']); ?></td>
                            <td><?php echo htmlspecialchars($blood['screening_id']); ?></td>
                            <td><?php echo htmlspecialchars($screening ? $screening['donor_form_id'] : 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($blood['start_time']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>







