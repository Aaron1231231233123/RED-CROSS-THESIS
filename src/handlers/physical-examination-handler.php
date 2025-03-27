<?php
session_start();
require_once '../../assets/conn/db_conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $donor_id = $_POST['donor_id'];
        $blood_pressure = $_POST['blood_pressure'];
        $pulse_rate = $_POST['pulse_rate'];
        $body_temp = $_POST['body_temp'];
        $gen_appearance = $_POST['gen_appearance'];
        $skin = $_POST['skin'];
        $heent = $_POST['heent'];
        $heart_and_lungs = $_POST['heart_and_lungs'];
        $remarks = $_POST['remarks'];
        $reason = $_POST['reason'];
        $blood_bag_type = $_POST['blood_bag_type'];

        // Prepare the SQL query
        $query = "INSERT INTO physical_examination (
            donor_id,
            blood_pressure, 
            pulse_rate, 
            body_temp, 
            gen_appearance, 
            skin, 
            heent, 
            heart_and_lungs, 
            remarks, 
            reason, 
            blood_bag_type
        ) VALUES (
            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11
        ) RETURNING physical_exam_id";

        // Execute the query using Supabase
        $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination');
        
        $data = array(
            'donor_id' => intval($donor_id),
            'blood_pressure' => $blood_pressure,
            'pulse_rate' => intval($pulse_rate),
            'body_temp' => floatval($body_temp),
            'gen_appearance' => $gen_appearance,
            'skin' => $skin,
            'heent' => $heent,
            'heart_and_lungs' => $heart_and_lungs,
            'remarks' => $remarks,
            'reason' => $reason,
            'blood_bag_type' => $blood_bag_type
        );

        $headers = array(
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        );

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code === 201) {
            // Success - redirect to the dashboard
            header('Location: ../../public/Dashboards/dashboard-staff-physical-submission.php');
            exit;
        } else {
            // Log the error
            error_log("Error inserting physical examination. HTTP Code: " . $http_code . " Response: " . $response);
            throw new Exception("Failed to save physical examination data");
        }

    } catch (Exception $e) {
        // Log the error and redirect with error message
        error_log("Error in physical-examination-handler.php: " . $e->getMessage());
        header('Location: ../views/forms/physical-examination-form.php?error=1');
        exit;
    }
} else {
    // Not a POST request - redirect back to form
    header('Location: ../views/forms/physical-examination-form.php');
    exit;
}
?> 