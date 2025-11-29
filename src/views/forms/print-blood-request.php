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

// Fetch user image from users table
$user_image = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ch_user = curl_init();
    $user_headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ];
    $user_url = SUPABASE_URL . '/rest/v1/users?user_id=eq.' . $user_id . '&select=user_image';
    curl_setopt($ch_user, CURLOPT_URL, $user_url);
    curl_setopt($ch_user, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_user, CURLOPT_HTTPHEADER, $user_headers);
    $user_response = curl_exec($ch_user);
    curl_close($ch_user);
    $user_data = json_decode($user_response, true);
    if (!empty($user_data) && isset($user_data[0]['user_image']) && !empty($user_data[0]['user_image'])) {
        $user_image = $user_data[0]['user_image'];
    }
}
// Default avatar if no user image
$default_avatar = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100%" height="100%" fill="#f5f5f5"/><circle cx="50" cy="40" r="25" fill="#d9d9d9"/><rect width="70" height="35" x="15" y="70" rx="17" fill="#d9d9d9"/></svg>');
$user_image_url = $user_image ? $user_image : $default_avatar;

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
            padding: 20px 20px 10px 20px;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .receipt-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 3px;
            color: #333;
            line-height: 1.2;
        }
        .organization-name {
            font-size: 16px;
            color: #666;
            margin-bottom: 0;
            line-height: 1.2;
        }
        .receipt-info {
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 220px;
            color: #333;
        }
        .info-value {
            color: #333;
        }
        .instructions-section {
            margin: 20px 0;
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
            margin-top: 25px;
            margin-bottom: 0;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 25px;
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
            margin-top: 10px;
            margin-bottom: 0;
            text-align: center;
            font-style: italic;
            color: #666;
            font-size: 12px;
        }
        .no-print {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .no-print .left-buttons {
            display: flex;
            gap: 15px;
        }
        .no-print .right-buttons {
            display: flex;
            gap: 15px;
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
        .receipt-header-images {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .receipt-header-images .user-image-container {
            width: 100px;
            height: 100px;
            min-width: 100px;
            min-height: 100px;
            max-width: 100px;
            max-height: 100px;
            aspect-ratio: 1 / 1;
            border: 2px solid #ddd;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-sizing: border-box;
            padding: 0;
            margin: 0;
        }
        .receipt-header-images .user-image-container:hover {
            border-color: #941022;
            box-shadow: 0 2px 8px rgba(148, 16, 34, 0.3);
        }
        .receipt-header-images .user-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
            margin: 0;
            padding: 0;
        }
        .receipt-header-images .prc-logo-container {
            width: 100px;
            height: 100px;
            min-width: 100px;
            min-height: 100px;
            max-width: 100px;
            max-height: 100px;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background-color: transparent;
            box-sizing: border-box;
            border: none;
            border-radius: 50%;
            overflow: hidden;
            padding: 0;
            margin: 0;
        }
        .receipt-header-images .prc-logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            display: block;
            margin: 0;
            padding: 0;
            border-radius: 0;
        }
        .receipt-header-content {
            text-align: center;
            flex: 1;
            margin: 0 15px;
            padding: 0;
        }
        .download-btn {
            background-color: #28a745;
            color: white;
        }
        .download-btn:hover {
            background-color: #218838;
            color: white;
        }
        /* Custom Toast Notification Styles */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            max-width: 400px;
            z-index: 9999;
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s ease-in-out;
        }
        .toast-notification.show {
            opacity: 1;
            transform: translateX(0);
        }
        .toast-notification .toast-header {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            border-radius: 0.375rem 0.375rem 0 0;
        }
        .toast-notification.success .toast-header {
            background-color: #28a745;
            color: white;
        }
        .toast-notification.error .toast-header {
            background-color: #dc3545;
            color: white;
        }
        .toast-notification.info .toast-header {
            background-color: #17a2b8;
            color: white;
        }
        .toast-notification.warning .toast-header {
            background-color: #ffc107;
            color: #212529;
        }
        .toast-icon {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        .toast-notification .toast-body {
            padding: 12px 16px;
            background-color: white;
            border-radius: 0 0 0.375rem 0.375rem;
        }
        .toast-notification.success .toast-body {
            border-left: 4px solid #28a745;
        }
        .toast-notification.error .toast-body {
            border-left: 4px solid #dc3545;
        }
        .toast-notification.info .toast-body {
            border-left: 4px solid #17a2b8;
        }
        .toast-notification.warning .toast-body {
            border-left: 4px solid #ffc107;
        }
        .toast-notification .btn-close {
            filter: brightness(0) invert(1);
        }
        .toast-notification.warning .btn-close {
            filter: none;
        }
        /* PDF-specific optimizations */
        .receipt-container {
            box-sizing: border-box;
        }
        .receipt-container * {
            box-sizing: border-box;
        }
         .receipt-container.pdf-export {
             padding: 20px !important;
         }
         .receipt-container.pdf-export .no-print {
             display: none !important;
             visibility: hidden !important;
             opacity: 0 !important;
             height: 0 !important;
             overflow: hidden !important;
         }
         .receipt-container.pdf-export .receipt-header-images {
             margin-bottom: 15px !important;
             display: flex !important;
             justify-content: space-between !important;
             align-items: center !important;
             gap: 0 !important;
         }
         .receipt-container.pdf-export .receipt-header-images .user-image-container {
             width: 100px !important;
             height: 100px !important;
             min-width: 100px !important;
             min-height: 100px !important;
             max-width: 100px !important;
             max-height: 100px !important;
             aspect-ratio: 1 / 1 !important;
             box-sizing: border-box !important;
             border: none !important;
             padding: 0 !important;
             margin: 0 !important;
             display: flex !important;
             align-items: center !important;
             justify-content: center !important;
             border-radius: 50% !important;
             overflow: hidden !important;
             flex-shrink: 0 !important;
         }
         .receipt-container.pdf-export .receipt-header-images .prc-logo-container {
             width: 130px !important;
             height: 130px !important;
             min-width: 130px !important;
             min-height: 130px !important;
             max-width: 130px !important;
             max-height: 130px !important;
             aspect-ratio: 1 / 1 !important;
             box-sizing: border-box !important;
             border: none !important;
             padding: 0 !important;
             margin: 0 !important;
             margin-left: auto !important;
             display: flex !important;
             align-items: center !important;
             justify-content: center !important;
             border-radius: 50% !important;
             overflow: hidden !important;
             flex-shrink: 0 !important;
             flex-grow: 0 !important;
             position: relative !important;
         }
         .receipt-container.pdf-export .receipt-header-images .user-image-container img {
             width: 100% !important;
             height: 100% !important;
             display: block !important;
             margin: 0 !important;
             padding: 0 !important;
             object-fit: contain !important;
             object-position: center !important;
             border-radius: 0 !important;
         }
         .receipt-container.pdf-export .receipt-header-images .prc-logo-container img {
             max-width: 100% !important;
             max-height: 100% !important;
             width: auto !important;
             height: auto !important;
             display: block !important;
             margin: 0 auto !important;
             padding: 0 !important;
             object-fit: contain !important;
             object-position: center center !important;
             border-radius: 0 !important;
         }
         .receipt-container.pdf-export .receipt-header-content {
             margin: 0 20px !important;
         }
         .receipt-container.pdf-export .receipt-title {
             font-size: 28px !important;
             margin-bottom: 6px !important;
             line-height: 1.3 !important;
         }
         .receipt-container.pdf-export .organization-name {
             font-size: 18px !important;
             margin-bottom: 0 !important;
             line-height: 1.3 !important;
         }
         .receipt-container.pdf-export .receipt-info {
             margin-bottom: 14px !important;
         }
         .receipt-container.pdf-export .info-row {
             margin-bottom: 8px !important;
             font-size: 15px !important;
             line-height: 1.5 !important;
         }
         .receipt-container.pdf-export .info-label {
             font-size: 15px !important;
             width: 220px !important;
         }
         .receipt-container.pdf-export .info-value {
             font-size: 15px !important;
         }
         .receipt-container.pdf-export {
             padding-bottom: 10px !important;
         }
         .receipt-container.pdf-export .instructions-section {
             margin: 18px 0 !important;
         }
         .receipt-container.pdf-export .instructions-title {
             font-size: 17px !important;
             margin-bottom: 8px !important;
             font-weight: bold !important;
         }
         .receipt-container.pdf-export .instructions-text {
             font-size: 15px !important;
             line-height: 1.6 !important;
             margin-left: 20px !important;
         }
         .receipt-container.pdf-export .signature-section {
             margin-top: 20px !important;
             margin-bottom: 0 !important;
         }
         .receipt-container.pdf-export .signature-block {
             width: 45% !important;
         }
         .receipt-container.pdf-export .signature-line {
             margin-top: 30px !important;
             border-top: 2px solid #333 !important;
         }
         .receipt-container.pdf-export .signature-label {
             font-size: 15px !important;
             font-weight: bold !important;
             margin-top: 8px !important;
         }
         .receipt-container.pdf-export .signature-subtitle {
             font-size: 13px !important;
             margin-top: 4px !important;
         }
         .receipt-container.pdf-export .note-section {
             margin-top: 15px !important;
             margin-bottom: 0 !important;
             font-size: 14px !important;
             line-height: 1.5 !important;
        }
        @media print {
            .no-print { display: none !important; }
            .toast-notification { display: none !important; }
            .modal { display: none !important; }
            body { 
                background: white !important; 
                margin: 0 !important;
                padding: 0 !important;
            }
            .receipt-container {
                margin: 0 !important;
                padding: 15px 15px 10px 15px !important;
                max-width: none !important;
                page-break-inside: avoid;
            }
            .receipt-header-images {
                margin-bottom: 15px !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 0 !important;
            }
            .receipt-header-images .user-image-container {
                width: 100px !important;
                height: 100px !important;
                min-width: 100px !important;
                min-height: 100px !important;
                max-width: 100px !important;
                max-height: 100px !important;
                aspect-ratio: 1 / 1 !important;
                box-sizing: border-box !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 50% !important;
                overflow: hidden !important;
                flex-shrink: 0 !important;
            }
            .receipt-header-images .prc-logo-container {
                width: 130px !important;
                height: 130px !important;
                min-width: 130px !important;
                min-height: 130px !important;
                max-width: 130px !important;
                max-height: 130px !important;
                aspect-ratio: 1 / 1 !important;
                box-sizing: border-box !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                margin-left: auto !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 50% !important;
                overflow: hidden !important;
                flex-shrink: 0 !important;
                flex-grow: 0 !important;
                position: relative !important;
            }
            .receipt-header-images .user-image-container img {
                width: 100% !important;
                height: 100% !important;
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                object-fit: contain !important;
                object-position: center !important;
                border-radius: 0 !important;
            }
            .receipt-header-images .prc-logo-container img {
                max-width: 100% !important;
                max-height: 100% !important;
                width: auto !important;
                height: auto !important;
                display: block !important;
                margin: 0 auto !important;
                padding: 0 !important;
                object-fit: contain !important;
                object-position: center center !important;
                border-radius: 0 !important;
            }
            .receipt-header-content {
                margin: 0 20px !important;
            }
            .receipt-title {
                font-size: 28px !important;
                margin-bottom: 6px !important;
                line-height: 1.3 !important;
            }
            .organization-name {
                font-size: 18px !important;
                margin-bottom: 0 !important;
                line-height: 1.3 !important;
            }
            .receipt-info {
                margin-bottom: 14px !important;
            }
            .info-row {
                margin-bottom: 8px !important;
                font-size: 15px !important;
                line-height: 1.5 !important;
            }
            .info-label {
                font-size: 15px !important;
                width: 220px !important;
            }
            .info-value {
                font-size: 15px !important;
            }
            .instructions-section {
                margin: 18px 0 !important;
            }
            .instructions-title {
                font-size: 17px !important;
                margin-bottom: 8px !important;
                font-weight: bold !important;
            }
            .instructions-text {
                margin-left: 20px !important;
                font-size: 15px !important;
                line-height: 1.6 !important;
            }
            .signature-section {
                margin-top: 20px !important;
                margin-bottom: 0 !important;
            }
            .signature-block {
                width: 45% !important;
            }
            .signature-line {
                margin-top: 30px !important;
                border-top: 2px solid #333 !important;
            }
            .signature-label {
                font-size: 15px !important;
                font-weight: bold !important;
                margin-top: 8px !important;
            }
            .signature-subtitle {
                font-size: 13px !important;
                margin-top: 4px !important;
            }
            .note-section {
                margin-top: 15px !important;
                margin-bottom: 0 !important;
                font-size: 14px !important;
                line-height: 1.5 !important;
            }
            @page { 
                size: A4; 
                margin: 0.8cm 0.8cm 0.6cm 0.8cm; 
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header with Images -->
        <div class="receipt-header-images">
            <div class="user-image-container" id="userImageContainer" title="Click to change image">
                <img src="<?php echo h($user_image_url); ?>" alt="User Image" id="userImagePreview">
            </div>
            <div class="receipt-header-content">
                <div class="receipt-title">Blood Request Receipt</div>
                <div class="organization-name">Philippine Red Cross – Blood Services</div>
            </div>
            <div class="prc-logo-container">
                <?php 
                // Get absolute URL for PRC logo
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                // Go up 3 levels from current script location
                $basePath = dirname(dirname(dirname($scriptPath)));
                $logoPath = $protocol . '://' . $host . $basePath . '/assets/image/PRC_Logo.png';
                ?>
                <img src="<?php echo $logoPath; ?>" alt="Philippine Red Cross Logo" id="prcLogoImg" crossorigin="anonymous">
            </div>
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
            <div class="left-buttons">
                <button class="btn btn-secondary close-btn" onclick="closePage()">Close</button>
            </div>
            <div class="right-buttons">
                <button class="btn btn-success download-btn" onclick="downloadReceipt()">
                    <i class="fas fa-download me-2"></i>Download
                </button>
                <button class="btn btn-danger print-btn" onclick="handlePrint()">Print</button>
            </div>
        </div>
    </div>

    <!-- Change Image Modal -->
    <div class="modal fade" id="changeImageModal" tabindex="-1" aria-labelledby="changeImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #941022; color: white;">
                    <h5 class="modal-title" id="changeImageModalLabel">Change Profile Image</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Image</label>
                        <input type="file" class="form-control" id="imageFileInput" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">Max size 2MB. JPG, PNG, or WEBP.</div>
                    </div>
                    <div id="imagePreviewContainer" class="mb-3" style="display: none;">
                        <label class="form-label">Preview</label>
                        <div class="text-center">
                            <img id="imagePreview" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                    <div id="imageUploadError" class="alert alert-danger" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmChangeImageBtn" disabled>
                        <i class="fas fa-upload me-2"></i>Upload Image
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Change Image Modal -->
    <div class="modal fade" id="confirmChangeImageModal" tabindex="-1" aria-labelledby="confirmChangeImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #941022; color: white;">
                    <h5 class="modal-title" id="confirmChangeImageModalLabel">Confirm Image Change</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to change the image?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="finalConfirmChangeImageBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Download Success Modal -->
    <div class="modal fade" id="pdfSuccessModal" tabindex="-1" aria-labelledby="pdfSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #941022; color: white;">
                    <h5 class="modal-title" id="pdfSuccessModalLabel">PDF Downloaded Successfully</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="pdfSuccessMessage">PDF downloaded successfully!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Download Error Modal -->
    <div class="modal fade" id="pdfErrorModal" tabindex="-1" aria-labelledby="pdfErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #dc3545; color: white;">
                    <h5 class="modal-title" id="pdfErrorModalLabel">
                        <i class="fas fa-exclamation-circle me-2"></i>Error
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="pdfErrorMessage">Error generating PDF. Please try again.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Generating Modal -->
    <div class="modal fade" id="pdfGeneratingModal" tabindex="-1" aria-labelledby="pdfGeneratingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #17a2b8; color: white;">
                    <h5 class="modal-title" id="pdfGeneratingModalLabel">
                        <i class="fas fa-spinner fa-spin me-2"></i>Generating PDF
                    </h5>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Please wait while we generate your PDF...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Notification Toast -->
    <div class="toast-notification" id="notificationToast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="toast-icon" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            <!-- Message will be inserted here -->
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- html2pdf.js for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        const userImageUrl = <?php echo json_encode($user_image_url); ?>;
        let selectedImageFile = null;

        // Custom notification function
        function showNotification(message, type = 'info', duration = 3000) {
            const toast = document.getElementById('notificationToast');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            
            // Remove all type classes
            toast.classList.remove('success', 'error', 'info', 'warning');
            toast.classList.add(type);
            
            // Set icon based on type
            const icons = {
                success: '<i class="fas fa-check-circle"></i>',
                error: '<i class="fas fa-exclamation-circle"></i>',
                info: '<i class="fas fa-info-circle"></i>',
                warning: '<i class="fas fa-exclamation-triangle"></i>'
            };
            
            // Set title based on type
            const titles = {
                success: 'Success',
                error: 'Error',
                info: 'Information',
                warning: 'Warning'
            };
            
            toastIcon.innerHTML = icons[type] || icons.info;
            toastTitle.textContent = titles[type] || titles.info;
            toastMessage.textContent = message;
            
            // Show toast
            toast.classList.add('show');
            
            // Auto-hide after duration
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.classList.remove('success', 'error', 'info', 'warning');
                }, 300);
            }, duration);
        }

        function closePage() {
            // Close the print page and return to dashboard
            window.close();
        }

        function downloadReceipt() {
            // Get patient name and date issued for filename
            const patientName = <?php echo json_encode($request['patient_name'] ?? 'Patient'); ?>;
            const dateIssued = <?php echo json_encode(date('Y-m-d', strtotime($request['when_needed']))); ?>;
            
            // Clean patient name for filename (remove special characters)
            const cleanPatientName = patientName.replace(/[^a-zA-Z0-9]/g, '_').substring(0, 30);
            const fileName = `Blood_Request_${cleanPatientName}_${dateIssued}.pdf`;
            
            // Get the element to convert
            const element = document.querySelector('.receipt-container');
            
            // Temporarily add PDF-specific class for styling
            element.classList.add('pdf-export');
            
            // Show generating modal
            const generatingModal = new bootstrap.Modal(document.getElementById('pdfGeneratingModal'));
            generatingModal.show();
            
            // Safety timeout to ensure modal closes even if something goes wrong
            const safetyTimeout = setTimeout(() => {
                console.warn('Safety timeout: Forcing modal to close');
                generatingModal.hide();
                
                // Remove PDF class
                element.classList.remove('pdf-export');
                
                // Restore no-print elements
                noPrintElements.forEach((el, index) => {
                    if (originalStyles[index]) {
                        el.style.removeProperty('display');
                        el.style.removeProperty('visibility');
                        el.style.removeProperty('height');
                        el.style.removeProperty('overflow');
                        el.style.removeProperty('opacity');
                        el.style.removeProperty('position');
                        el.style.removeProperty('left');
                    }
                });
            }, 35000); // 35 second safety timeout
            
            // Clear safety timeout when modal is successfully closed
            const originalHide = generatingModal.hide.bind(generatingModal);
            generatingModal.hide = function() {
                clearTimeout(safetyTimeout);
                originalHide();
            };
            
            // Hide no-print elements before PDF generation - use multiple methods to ensure they're hidden
            const noPrintElements = element.querySelectorAll('.no-print');
            const originalStyles = [];
            noPrintElements.forEach((el, index) => {
                originalStyles[index] = {
                    display: window.getComputedStyle(el).display,
                    visibility: window.getComputedStyle(el).visibility,
                    height: window.getComputedStyle(el).height,
                    opacity: window.getComputedStyle(el).opacity
                };
                el.style.setProperty('display', 'none', 'important');
                el.style.setProperty('visibility', 'hidden', 'important');
                el.style.setProperty('height', '0', 'important');
                el.style.setProperty('overflow', 'hidden', 'important');
                el.style.setProperty('opacity', '0', 'important');
                el.style.setProperty('position', 'absolute', 'important');
                el.style.setProperty('left', '-9999px', 'important');
            });
            
            // Convert relative image paths to absolute URLs before PDF generation
            const prcLogoImg = document.getElementById('prcLogoImg');
            if (prcLogoImg) {
                const currentSrc = prcLogoImg.getAttribute('src') || prcLogoImg.src;
                // If it's a relative path, convert to absolute
                if (currentSrc && !currentSrc.startsWith('http') && !currentSrc.startsWith('data:') && !currentSrc.startsWith('/')) {
                    const baseUrl = window.location.origin;
                    const currentPath = window.location.pathname;
                    const pathParts = currentPath.split('/').filter(p => p);
                    // Remove the last 3 parts (src/views/forms)
                    if (pathParts.length >= 3) {
                        pathParts.pop(); // forms
                        pathParts.pop(); // views
                        pathParts.pop(); // src
                    }
                    const basePath = '/' + pathParts.join('/');
                    const newSrc = baseUrl + basePath + '/assets/image/PRC_Logo.png';
                    prcLogoImg.src = newSrc;
                    prcLogoImg.setAttribute('crossorigin', 'anonymous');
                } else if (currentSrc && currentSrc.startsWith('../../../')) {
                    // Handle relative path with ../
                    const baseUrl = window.location.origin;
                    const currentPath = window.location.pathname;
                    const pathParts = currentPath.split('/').filter(p => p);
                    // Go up 3 levels
                    for (let i = 0; i < 3 && pathParts.length > 0; i++) {
                        pathParts.pop();
                    }
                    const basePath = '/' + pathParts.join('/');
                    const newSrc = baseUrl + basePath + '/assets/image/PRC_Logo.png';
                    prcLogoImg.src = newSrc;
                    prcLogoImg.setAttribute('crossorigin', 'anonymous');
        }
            }
            
            // Wait for all images to load before generating PDF
            const images = element.querySelectorAll('img');
            const imagePromises = Array.from(images).map(img => {
                if (img.complete && img.naturalWidth > 0) return Promise.resolve();
                return new Promise((resolve) => {
                    const timeout = setTimeout(() => {
                        console.warn('Image load timeout:', img.src);
                        resolve();
                    }, 3000);
                    img.onload = () => {
                        clearTimeout(timeout);
                        resolve();
                    };
                    img.onerror = () => {
                        clearTimeout(timeout);
                        console.error('Image failed to load:', img.src);
                        resolve(); // Continue even if image fails
                    };
                    // Force reload if image hasn't loaded
                    if (!img.complete) {
                        const src = img.src;
                        img.src = '';
                        setTimeout(() => {
                            img.src = src;
                        }, 100);
                    }
                });
            });
            
            Promise.all(imagePromises).then(() => {
                // Additional wait for styles to apply
                setTimeout(() => {
                    // Configure PDF options
                    const opt = {
                        margin: [0.6, 0.6, 0.4, 0.6],
                        filename: fileName,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { 
                            scale: 2,
                            useCORS: true,
                            logging: false,
                            letterRendering: true,
                            allowTaint: false,
                            backgroundColor: '#ffffff',
                            width: element.scrollWidth,
                            height: element.scrollHeight,
                            windowWidth: element.scrollWidth,
                            windowHeight: element.scrollHeight,
                            x: 0,
                            y: 0,
                            scrollX: 0,
                            scrollY: 0,
                            onclone: function(clonedDoc) {
                                // Ensure no-print elements are hidden in cloned document
                                const clonedNoPrint = clonedDoc.querySelectorAll('.no-print');
                                clonedNoPrint.forEach(el => {
                                    el.style.display = 'none';
                                    el.style.visibility = 'hidden';
                                    el.style.height = '0';
                                    el.style.overflow = 'hidden';
                                });
                                
                                // Fix image paths in cloned document - ensure absolute URLs
                                const clonedImages = clonedDoc.querySelectorAll('img');
                                clonedImages.forEach(clonedImg => {
                                    // Find the original image by ID or src
                                    const clonedId = clonedImg.id;
                                    let originalImg = null;
                                    if (clonedId) {
                                        originalImg = document.getElementById(clonedId);
        }
                                    if (!originalImg) {
                                        // Try to find by matching src
                                        const allImages = document.querySelectorAll('img');
                                        allImages.forEach(img => {
                                            if (img.src === clonedImg.src || img.getAttribute('src') === clonedImg.getAttribute('src')) {
                                                originalImg = img;
        }
                                        });
                                    }
                                    
                                    if (originalImg && originalImg.src) {
                                        // Use the original image's absolute URL
                                        clonedImg.src = originalImg.src;
                                        clonedImg.setAttribute('crossorigin', 'anonymous');
                                    } else if (clonedImg.src && !clonedImg.src.startsWith('http') && !clonedImg.src.startsWith('data:')) {
                                        // Convert relative path to absolute
                                        const baseUrl = window.location.origin;
                                        const currentPath = window.location.pathname;
                                        const pathParts = currentPath.split('/').filter(p => p);
                                        if (pathParts.length >= 3) {
                                            pathParts.pop(); // forms
                                            pathParts.pop(); // views
                                            pathParts.pop(); // src
                                        }
                                        const basePath = '/' + pathParts.join('/');
                                        clonedImg.src = baseUrl + basePath + '/assets/image/PRC_Logo.png';
                                        clonedImg.setAttribute('crossorigin', 'anonymous');
        }
                                });
                            }
                        },
                    jsPDF: { 
                        unit: 'cm', 
                        format: 'a4', 
                        orientation: 'portrait',
                        compress: true
                    },
                    pagebreak: { 
                        mode: ['avoid-all', 'css', 'legacy'],
                        before: '.page-break-before',
                        after: '.page-break-after',
                        avoid: ['.receipt-container', '.signature-section', '.instructions-section']
                    }
                };
                
                // Add timeout to prevent hanging
                const pdfTimeout = setTimeout(() => {
                    console.error('PDF generation timeout');
                    // Close generating modal
                    generatingModal.hide();
                    
                    // Remove PDF class
                    element.classList.remove('pdf-export');
                    
                    // Restore no-print elements
                    noPrintElements.forEach((el, index) => {
                        if (originalStyles[index]) {
                            el.style.removeProperty('display');
                            el.style.removeProperty('visibility');
                            el.style.removeProperty('height');
                            el.style.removeProperty('overflow');
                            el.style.removeProperty('opacity');
                            el.style.removeProperty('position');
                            el.style.removeProperty('left');
                        }
                    });
                    
                    // Show error modal
                    const errorModal = new bootstrap.Modal(document.getElementById('pdfErrorModal'));
                    const errorMessage = document.getElementById('pdfErrorMessage');
                    if (errorMessage) {
                        errorMessage.textContent = 'PDF generation timed out. Please try again.';
                    }
                    errorModal.show();
                    
                    // Auto-close error modal after 3 seconds
                    setTimeout(() => {
                        errorModal.hide();
                    }, 3000);
                }, 30000); // 30 second timeout
                
                // Generate and download PDF
                html2pdf().set(opt).from(element).save().then(() => {
                    clearTimeout(pdfTimeout);
                    
                    // Remove PDF class
                    element.classList.remove('pdf-export');
                    
                    // Restore no-print elements
                    noPrintElements.forEach((el, index) => {
                        if (originalStyles[index]) {
                            el.style.removeProperty('display');
                            el.style.removeProperty('visibility');
                            el.style.removeProperty('height');
                            el.style.removeProperty('overflow');
                            el.style.removeProperty('opacity');
                            el.style.removeProperty('position');
                            el.style.removeProperty('left');
                        }
                    });
                    
                    // Close generating modal
                    generatingModal.hide();
                    
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('pdfSuccessModal'), {
                        backdrop: 'static',
                        keyboard: false
                    });
                    const successMessage = document.getElementById('pdfSuccessMessage');
                    if (successMessage) {
                        successMessage.textContent = 'PDF downloaded successfully!';
                    }
                    successModal.show();
                    
                    // Auto-close success modal after 2 seconds
                    setTimeout(() => {
                        successModal.hide();
                    }, 2000);
                }).catch((error) => {
                    clearTimeout(pdfTimeout);
                    
                    // Remove PDF class on error
                    element.classList.remove('pdf-export');
            
                    // Restore no-print elements on error
                    noPrintElements.forEach((el, index) => {
                        if (originalStyles[index]) {
                            el.style.removeProperty('display');
                            el.style.removeProperty('visibility');
                            el.style.removeProperty('height');
                            el.style.removeProperty('overflow');
                            el.style.removeProperty('opacity');
                            el.style.removeProperty('position');
                            el.style.removeProperty('left');
                        }
                    });
                    
                    console.error('PDF generation error:', error);
                    
                    // Close generating modal
                    generatingModal.hide();
                    
                    // Show error modal
                    const errorModal = new bootstrap.Modal(document.getElementById('pdfErrorModal'));
                    const errorMessage = document.getElementById('pdfErrorMessage');
                    if (errorMessage) {
                        errorMessage.textContent = 'Error generating PDF: ' + (error.message || 'Please try again.');
                    }
                    errorModal.show();
                    
                    // Auto-close error modal after 3 seconds
                    setTimeout(() => {
                        errorModal.hide();
                    }, 3000);
                });
                }, 500);
            }).catch(error => {
                console.error('Image preloading failed:', error);
                
                // Remove PDF class
                element.classList.remove('pdf-export');
                
                // Restore no-print elements
                noPrintElements.forEach((el, index) => {
                    if (originalStyles[index]) {
                        el.style.removeProperty('display');
                        el.style.removeProperty('visibility');
                        el.style.removeProperty('height');
                        el.style.removeProperty('overflow');
                        el.style.removeProperty('opacity');
                        el.style.removeProperty('position');
                        el.style.removeProperty('left');
                    }
                });
                
                // Close generating modal
                generatingModal.hide();
                
                // Show error modal
                const errorModal = new bootstrap.Modal(document.getElementById('pdfErrorModal'));
                const errorMessage = document.getElementById('pdfErrorMessage');
                if (errorMessage) {
                    errorMessage.textContent = 'Error loading images for PDF. Please try again.';
                }
                errorModal.show();
                setTimeout(() => {
                    errorModal.hide();
                }, 3000);
            });
            
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

        // Image upload functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userImageContainer = document.getElementById('userImageContainer');
            const changeImageModal = new bootstrap.Modal(document.getElementById('changeImageModal'));
            const confirmChangeImageModal = new bootstrap.Modal(document.getElementById('confirmChangeImageModal'));
            const imageFileInput = document.getElementById('imageFileInput');
            const imagePreview = document.getElementById('imagePreview');
            const imagePreviewContainer = document.getElementById('imagePreviewContainer');
            const confirmChangeImageBtn = document.getElementById('confirmChangeImageBtn');
            const finalConfirmChangeImageBtn = document.getElementById('finalConfirmChangeImageBtn');
            const imageUploadError = document.getElementById('imageUploadError');
            const userImagePreview = document.getElementById('userImagePreview');

            // Open change image modal when clicking on user image
            userImageContainer.addEventListener('click', function() {
                imageFileInput.value = '';
                imagePreviewContainer.style.display = 'none';
                confirmChangeImageBtn.disabled = true;
                imageUploadError.style.display = 'none';
                changeImageModal.show();
            });

            // Preview image when file is selected
            imageFileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        imageUploadError.textContent = 'Please select a JPG, PNG, or WEBP image.';
                        imageUploadError.style.display = 'block';
                        confirmChangeImageBtn.disabled = true;
                        return;
                    }

                    // Validate file size (2MB)
                    if (file.size > 2 * 1024 * 1024) {
                        imageUploadError.textContent = 'Image must be 2MB or smaller.';
                        imageUploadError.style.display = 'block';
                        confirmChangeImageBtn.disabled = true;
                        return;
                    }

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.style.display = 'block';
                        selectedImageFile = file;
                        confirmChangeImageBtn.disabled = false;
                        imageUploadError.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Show confirmation modal when clicking upload button
            confirmChangeImageBtn.addEventListener('click', function() {
                if (selectedImageFile) {
                    changeImageModal.hide();
                    setTimeout(() => {
                        confirmChangeImageModal.show();
                    }, 300);
                }
            });

            // Handle final confirmation and upload
            finalConfirmChangeImageBtn.addEventListener('click', function() {
                if (!selectedImageFile) {
                    showNotification('No image selected.', 'warning');
                    return;
                }

                // Show loading state
                const originalText = finalConfirmChangeImageBtn.innerHTML;
                finalConfirmChangeImageBtn.disabled = true;
                finalConfirmChangeImageBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading...';

                // Create FormData for file upload
                const formData = new FormData();
                formData.append('user_image', selectedImageFile);

                // Upload image - try multiple possible paths
                const possiblePaths = [
                    '../../../public/api/update_user_image.php',
                    '../../public/api/update_user_image.php',
                    '../api/update_user_image.php'
                ];
                
                let uploadPromise = null;
                let lastError = null;
                
                // Try each path until one works
                for (const path of possiblePaths) {
                    try {
                        uploadPromise = fetch(path, {
                            method: 'POST',
                            body: formData
                        });
                        break;
                    } catch (e) {
                        lastError = e;
                        continue;
                    }
                }
                
                if (!uploadPromise) {
                    console.error('Could not determine correct path for image upload');
                    showNotification('Could not connect to server. Please check the console for details.', 'error');
                    finalConfirmChangeImageBtn.disabled = false;
                    finalConfirmChangeImageBtn.innerHTML = originalText;
                    return;
                }
                
                uploadPromise
                .then(response => {
                    // Check if response is ok
                    if (!response.ok) {
                        return response.text().then(text => {
                            let errorData;
                            try {
                                errorData = JSON.parse(text);
                            } catch {
                                errorData = { success: false, message: `HTTP ${response.status}: ${text.substring(0, 200)}` };
                            }
                            throw errorData;
                        });
                    }
                    // Try to parse JSON
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw { success: false, message: 'Invalid response from server: ' + text.substring(0, 200) };
                        }
                    });
                })
                .then(data => {
                    if (data && data.success) {
                        // Update preview image
                        if (data.user_image) {
                            userImagePreview.src = data.user_image;
                        }
                        
                        // Close modals
                        confirmChangeImageModal.hide();
                        changeImageModal.hide();
                        
                        // Show success notification
                        showNotification('Image updated successfully!', 'success', 3000);
                    } else {
                        const errorMsg = (data && data.message) ? data.message : 'Failed to update image.';
                        showNotification(errorMsg, 'error', 4000);
                        confirmChangeImageModal.hide();
                        changeImageModal.show();
                    }
                })
                .catch(error => {
                    console.error('Upload Error:', error);
                    let errorMsg = 'An error occurred while uploading the image.';
                    if (error && error.message) {
                        errorMsg = error.message;
                    } else if (typeof error === 'string') {
                        errorMsg = error;
                    } else if (error && typeof error === 'object') {
                        errorMsg = error.message || JSON.stringify(error);
                    }
                    showNotification(errorMsg, 'error', 4000);
                    confirmChangeImageModal.hide();
                    changeImageModal.show();
                })
                .finally(() => {
                    finalConfirmChangeImageBtn.disabled = false;
                    finalConfirmChangeImageBtn.innerHTML = originalText;
                });
            });
        });
    </script>
</body>
</html> 