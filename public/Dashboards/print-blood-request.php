<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// CSV file path
$csv_file = __DIR__ . '/print_history.csv';

// Get request_id from query string
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
if (!$request_id) {
    die('Invalid request ID.');
}

// Fetch request details from Supabase or DB
$ch = curl_init();
$headers = [
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
];
$url = SUPABASE_URL . '/rest/v1/blood_requests?request_id=eq.' . $request_id;
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
curl_close($ch);
$request_data = json_decode($response, true);
if (empty($request_data)) {
    die('Request not found.');
}
$request = $request_data[0];

// Prepare data for CSV logging
$log_data = [
    date('Y-m-d H:i:s'),
    $request['request_id'],
    $request['patient_name'],
    $request['patient_age'],
    $request['patient_gender'],
    $request['patient_blood_type'] . ($request['rh_factor'] === 'Positive' ? '+' : '-'),
    $request['units_requested'],
    $request['hospital_admitted'],
    $request['physician_name'],
    $request['requested_on'],
    $_SESSION['user_id'] ?? '',
    $_SESSION['user_first_name'] ?? '',
    $_SESSION['user_surname'] ?? ''
];

// Write to CSV (append mode)
$csv_exists = file_exists($csv_file);
$csv = fopen($csv_file, 'a');
if (!$csv_exists) {
    // Write header
    fputcsv($csv, ['Timestamp','Request ID','Patient Name','Age','Gender','Blood Type','Units','Hospital','Physician','Requested On','User ID','User First Name','User Surname']);
}
fputcsv($csv, $log_data);
fclose($csv);

// Format for display
function h($v) { return htmlspecialchars($v); }
$blood_type = h($request['patient_blood_type']) . ($request['rh_factor'] === 'Positive' ? '+' : '-');
$requested_on = date('F d, Y \a\t h:i A', strtotime($request['requested_on']));
$last_updated = $request['last_updated'] ? date('F d, Y \a\t h:i A', strtotime($request['last_updated'])) : '-';
$current_date = date('F d, Y');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Red Cross Blood Release Statement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        .print-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: stretch;
        }
        .statement-box {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 92vh;
            max-width: 1000px;
            width: 100%;
            margin: 5px auto;
            padding: 30px 30px 30px 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0,0,0,0.15);
            background: #fff;
            border-radius: 10px;
            font-size: 1.05rem;
        }
        .rc-header {
            margin-bottom: 1.2rem;
        }
        .rc-title {
            font-size: 1.35rem;
        }
        .rc-section-title {
            margin-top: 1.2rem;
            margin-bottom: 0.3rem;
            font-size: 1.08rem;
        }
        .signature-line {
            margin-top: 80px;
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-label {
            min-width: 180px;
            font-size: 1rem;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 4px;
        }
        .rc-footer {
            margin-top: 40px;
            font-size: 0.95rem;
            color: #888;
        }
        ul.mb-4 {
            margin-bottom: 1.2rem !important;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .statement-box { box-shadow: none !important; border: none !important; width: 100% !important; }
            html, body { height: auto; }
            @page { size: A4; margin: 0.7cm; }
            .print-container, .statement-box, .signature-line, .rc-footer {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body style="height: 100vh; margin: 0;">
    <div class="print-container">
        <div class="statement-box">
            <div class="rc-header">
                <div class="rc-title">Philippine Red Cross</div>
                <img src="../../assets/img/redcross-logo.png" alt="Red Cross Logo" class="rc-logo">
            </div>
            <div class="text-center mb-4">
                <h4 class="fw-bold" style="color:#941022;">Official Blood Release Statement</h4>
                <div class="mb-2">Date: <strong><?php echo $current_date; ?></strong></div>
            </div>
            <p>To whom it may concern,</p>
            <p>
                This is to certify that the following blood request has been <strong>reviewed and approved</strong> by the Philippine Red Cross. The named patient or their authorized representative is hereby permitted to receive the specified blood units from the Red Cross blood bank.
            </p>
            <div class="rc-section-title">Request Details</div>
            <ul class="mb-4" style="list-style:none; padding-left:0;">
                <li><strong>Patient Name:</strong> <?php echo h($request['patient_name']); ?></li>
                <li><strong>Age/Gender:</strong> <?php echo h($request['patient_age']) . ', ' . h($request['patient_gender']); ?></li>
                <li><strong>Blood Type:</strong> <?php echo $blood_type; ?></li>
                <li><strong>Units Approved:</strong> <?php echo h($request['units_requested']); ?></li>
                <li><strong>Diagnosis:</strong> <?php echo h($request['patient_diagnosis']); ?></li>
                <li><strong>Hospital:</strong> <?php echo h($request['hospital_admitted']); ?></li>
                <li><strong>Requesting Physician:</strong> <?php echo h($request['physician_name']); ?></li>
                <li><strong>Requested On:</strong> <?php echo $requested_on; ?></li>
                <li><strong>Last Updated:</strong> <?php echo $last_updated; ?></li>
                <li><strong>Status:</strong> <?php echo h($request['status']); ?></li>
            </ul>
            <p>
                The above-named patient (or their authorized representative) is allowed to claim the approved blood units from the Philippine Red Cross. Please present this document at the blood bank for verification and release.
            </p>
            <div class="rc-footer">
                This document is system-generated and does not require a physical signature from Red Cross staff.<br>
                For any questions, please contact your local Red Cross chapter.
            </div>
            <div class="signature-line">
                <span class="signature-label">Signature of Recipient</span>
                <span class="signature-label">Date Released</span>
            </div>
            <div class="mt-4 d-flex justify-content-between">
                <button class="btn btn-secondary no-print" onclick="window.location.href='dashboard-hospital-history.php'">Back</button>
                <button class="btn btn-danger no-print" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</body>
</html> 