<?php
session_start();
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
    exit();
}

// Get sample data
$donor_ch = curl_init();
curl_setopt_array($donor_ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/donor_form?select=*&limit=3',
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
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/screening_form?select=*&limit=3',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);
$screening_response = curl_exec($screening_ch);
$screening_forms = json_decode($screening_response, true) ?: [];
curl_close($screening_ch);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Names Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Field Names Debug</h1>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Donor Forms Fields</h3>
                <?php if (!empty($donor_forms)): ?>
                    <p><strong>Available fields:</strong></p>
                    <ul>
                        <?php foreach (array_keys($donor_forms[0]) as $field): ?>
                            <li><?php echo htmlspecialchars($field); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <h4>Sample Donor Form:</h4>
                    <pre><?php echo json_encode($donor_forms[0], JSON_PRETTY_PRINT); ?></pre>
                <?php else: ?>
                    <p>No donor forms found.</p>
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <h3>Screening Forms Fields</h3>
                <?php if (!empty($screening_forms)): ?>
                    <p><strong>Available fields:</strong></p>
                    <ul>
                        <?php foreach (array_keys($screening_forms[0]) as $field): ?>
                            <li><?php echo htmlspecialchars($field); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <h4>Sample Screening Form:</h4>
                    <pre><?php echo json_encode($screening_forms[0], JSON_PRETTY_PRINT); ?></pre>
                <?php else: ?>
                    <p>No screening forms found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Relationship Analysis</h3>
            <?php if (!empty($donor_forms) && !empty($screening_forms)): ?>
                <p><strong>Donor Form ID field:</strong> <?php echo isset($donor_forms[0]['donor_id']) ? 'donor_id' : 'NOT FOUND'; ?></p>
                <p><strong>Screening Form donor reference field:</strong> <?php echo isset($screening_forms[0]['donor_form_id']) ? 'donor_form_id' : 'NOT FOUND'; ?></p>
                
                <?php if (isset($donor_forms[0]['donor_id']) && isset($screening_forms[0]['donor_form_id'])): ?>
                    <p><strong>Sample relationship:</strong></p>
                    <ul>
                        <li>Donor Form ID: <?php echo htmlspecialchars($donor_forms[0]['donor_id']); ?></li>
                        <li>Screening Form donor_form_id: <?php echo htmlspecialchars($screening_forms[0]['donor_form_id']); ?></li>
                        <li>Match: <?php echo ($donor_forms[0]['donor_id'] == $screening_forms[0]['donor_form_id']) ? 'YES' : 'NO'; ?></li>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>









