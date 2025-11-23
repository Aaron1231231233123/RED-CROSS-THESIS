<?php
session_start();
require_once '../../../assets/conn/db_conn.php';

// CSV file path
$csv_file = __DIR__ . '/print_history.csv';

// Get request_id from query string
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
if (!$request_id) {
    die('Invalid request ID.');
}

// Function to generate Red Cross ID based on service type
function generateRedCrossID($type, $id, $date) {
    $year = date('Y', strtotime($date));
    $yearly_codes = ['AA', 'BB', 'CC', 'DD', 'EE', 'FF', 'GG'];
    $year_index = ($year - 2025) % 7; // Rotate through codes each year starting from 2025
    $code = $yearly_codes[$year_index];
    
    $date_formatted = date('md', strtotime($date)); // MMDD format
    $id_padded = str_pad($id, 4, '0', STR_PAD_LEFT);
    
    // Red Cross service type prefixes
    $prefixes = [
        'blood_request' => 'REQ-BR',      // Blood Request (request-centric prefix)
        'donor_form' => 'PRC-DF',         // Donor Form
        'hospital' => 'PRC-HOS',          // Hospital
        'staff' => 'PRC-STF',             // Staff
        'volunteer' => 'PRC-VOL',         // Volunteer
        'disaster' => 'PRC-DIS',          // Disaster Response
        'training' => 'PRC-TRN',         // Training
        'equipment' => 'PRC-EQP'          // Equipment
    ];
    
    $prefix = $prefixes[$type] ?? 'PRC';
    
    return $prefix . $code . $date_formatted . $id_padded;
}

// Function to generate request reference (blood request specific)
function generateRequestReference($request_id, $date) {
    return generateRedCrossID('blood_request', $request_id, $date);
}

// Function to generate UUID v4
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Function to update request status to 'Printed' and generate receipt_no
function updateRequestStatusToPrinted($request_id) {
    // First, fetch the request to get request_reference from database
    $ch = curl_init();
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    
    $fetch_url = SUPABASE_URL . '/rest/v1/blood_requests?request_id=eq.' . $request_id . '&select=request_reference';
    curl_setopt($ch, CURLOPT_URL, $fetch_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        curl_close($ch);
        error_log("Failed to fetch request data. HTTP Code: " . $http_code);
        return false;
    }
    
    $request_data = json_decode($response, true);
    curl_close($ch);
    
    if (empty($request_data) || !isset($request_data[0])) {
        error_log("Request not found or request_reference missing for request ID: " . $request_id);
        return false;
    }
    
    // Get request_reference from database (it should already be there from submission)
    $request_reference = $request_data[0]['request_reference'] ?? null;
    
    if (!$request_reference) {
        error_log("Warning: request_reference not found in database for request ID: " . $request_id);
    }
    
    // Generate UUID for receipt_no
    $receipt_no = generateUUID();
    
    // Update status to 'Printed' and set receipt_no
    $ch = curl_init();
    $update_headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ];
    
    $update_data = json_encode([
        'status' => 'Printed',
        'receipt_no' => $receipt_no,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
    $update_url = SUPABASE_URL . '/rest/v1/blood_requests?request_id=eq.' . $request_id;
    
    curl_setopt($ch, CURLOPT_URL, $update_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $update_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $update_headers);
    
    $update_response = curl_exec($ch);
    $update_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($update_http_code === 200 || $update_http_code === 204) {
        error_log("Successfully updated request ID {$request_id} with receipt_no: {$receipt_no}");
        return true;
    } else {
        error_log("Failed to update request status. HTTP Code: " . $update_http_code);
        return false;
    }
}

// Check if this is a print action (when user actually prints)
$is_print_action = isset($_GET['print']) && $_GET['print'] === 'true';

if ($is_print_action) {
    // Update status to 'Printed' and generate receipt_no when actually printing
    $status_updated = updateRequestStatusToPrinted($request_id);
    if (!$status_updated) {
        error_log("Failed to update status to Printed for request ID: " . $request_id);
    }
}

// Fetch request details from Supabase or DB (including receipt_no if it was just generated)
$ch = curl_init();
$headers = [
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
];
$url = SUPABASE_URL . '/rest/v1/blood_requests?request_id=eq.' . $request_id . '&select=*';
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

// Debug: Check what's in when_needed field
error_log("Debug - when_needed field value: " . print_r($request['when_needed'], true));
error_log("Debug - when_needed field type: " . gettype($request['when_needed']));
error_log("Debug - when_needed strtotime result: " . strtotime($request['when_needed']));
error_log("Debug - when_needed date format test: " . date('m/d/Y', strtotime($request['when_needed'])));
error_log("Debug - Full request data: " . print_r($request, true));

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
    <title>Blood Request Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .receipt-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        .organization-name {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }
        .receipt-info {
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            width: 200px;
            color: #333;
        }
        .info-value {
            color: #333;
        }
        .instructions-section {
            margin: 30px 0;
        }
        .instructions-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        .instructions-text {
            margin-left: 20px;
            line-height: 1.5;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
        }
        .signature-label {
            font-size: 14px;
            color: #333;
            font-weight: bold;
        }
        .signature-subtitle {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .note-section {
            margin-top: 20px;
            text-align: center;
            font-style: italic;
            color: #666;
            font-size: 14px;
        }
        .no-print {
            margin-top: 20px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 600px;
        }
        .no-print button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .no-print .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .no-print .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .no-print .btn-secondary:hover {
            background-color: #5a6268;
        }
        .no-print .btn-danger:hover {
            background-color: #c82333;
        }
        @media print {
            .no-print { display: none !important; }
            body { 
                background: white !important; 
                margin: 0 !important;
                padding: 0 !important;
            }
            .receipt-container {
                margin: 0 !important;
                padding: 20px !important;
                max-width: none !important;
            }
            @page { 
                size: A4; 
                margin: 1cm; 
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <div class="receipt-title">Blood Request Receipt</div>
            <div class="organization-name">Philippine Red Cross – Blood Services</div>
        </div>

        <!-- Receipt and Date Information -->
        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Receipt No.:</div>
                <div class="info-value"><?php echo h($request['receipt_no'] ?? 'Not yet generated'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Request ID:</div>
                <div class="info-value"><?php echo h($request['request_id']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date Issued:</div>
                <div class="info-value"><?php echo date('m/d/Y', strtotime($request['when_needed'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Request Status:</div>
                <div class="info-value"><?php echo h($request['status']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Priority:</div>
                <div class="info-value"><?php echo h($request['is_asap'] ? 'ASAP' : 'Scheduled'); ?></div>
            </div>
        </div>

        <!-- Hospital and Physician Information -->
        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Hospital Name:</div>
                <div class="info-value"><?php echo h($request['hospital_admitted']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Requesting Physician:</div>
                <div class="info-value"><?php echo h($request['physician_name']); ?></div>
            </div>
        </div>

        <!-- Patient Information -->
        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Patient Name:</div>
                <div class="info-value"><?php echo h($request['patient_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Age / Sex:</div>
                <div class="info-value"><?php echo h($request['patient_age']) . ' / ' . h($request['patient_gender']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Diagnosis / Reason:</div>
                <div class="info-value"><?php echo h($request['patient_diagnosis']); ?></div>
            </div>
        </div>

        <!-- Blood Request Details -->
        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Blood Type Requested:</div>
                <div class="info-value"><?php echo $blood_type; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">RH Factor:</div>
                <div class="info-value"><?php echo h($request['rh_factor']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Blood Component:</div>
                <div class="info-value"><?php echo h($request['blood_component'] ?? 'Whole Blood'); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Quantity:</div>
                <div class="info-value"><?php echo h($request['units_requested']); ?> bags</div>
            </div>
            <div class="info-row">
                <div class="info-label">When Needed:</div>
                <div class="info-value"><?php echo date('m/d/Y H:i', strtotime($request['when_needed'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Request Reference No.:</div>
                <div class="info-value"><?php echo h($request['request_reference'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Requested On:</div>
                <div class="info-value"><?php 
                    if (!empty($request['requested_on'])) {
                        echo date('m/d/Y H:i', strtotime($request['requested_on']));
                    } else {
                        echo 'N/A';
                    }
                ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Last Updated:</div>
                <div class="info-value"><?php 
                    if (!empty($request['last_updated'])) {
                        echo date('m/d/Y H:i', strtotime($request['last_updated']));
                    } else {
                        echo 'N/A';
                    }
                ?></div>
            </div>
            <?php if (!empty($request['decline_reason'])): ?>
            <div class="info-row">
                <div class="info-label">Decline Reason:</div>
                <div class="info-value"><?php echo h($request['decline_reason']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Instructions for Claiming -->
        <div class="instructions-section">
            <div class="instructions-title">Instructions for Claiming:</div>
            <div class="instructions-text">
                Please present this receipt at the Philippine Red Cross Blood Center to claim the approved blood unit(s).
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Authorized Representative</div>
                <div class="signature-subtitle">Approved By</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">Hospital Authorized Officer</div>
                <div class="signature-subtitle">Handed Over By</div>
            </div>
        </div>

        <!-- Physician Information -->
        <?php if (!empty($request['physician_signature'])): ?>
        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Physician Signature:</div>
                <div class="info-value">✓ Digital Signature Provided</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Note Section -->
        <div class="note-section">
            Note: This receipt is valid only for the specified patient and approved request.
        </div>

        <!-- Print Controls -->
        <div class="no-print">
            <button class="btn btn-secondary close-btn" onclick="closePage()">Close</button>
            <button class="btn btn-danger print-btn" onclick="handlePrint()">Print</button>
        </div>
    </div>
    
    <script>
        function closePage() {
            // Close the print page and return to dashboard
            window.close();
        }

        function handlePrint() {
            // Update status to 'Printed' before printing
            fetch(window.location.href + '&print=true', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (response.ok) {
                    console.log('Status updated to Printed successfully');
                    // Notify parent window that print is complete
                    if (window.opener) {
                        window.opener.postMessage({
                            type: 'print_completed',
                            requestId: '<?php echo $request_id; ?>'
                        }, '*');
                    }
                } else {
                    console.error('Failed to update status to Printed');
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
            })
            .finally(() => {
                // Show print dialog - user controls when to close
                window.print();
            });
        }
    </script>
</body>
</html> 