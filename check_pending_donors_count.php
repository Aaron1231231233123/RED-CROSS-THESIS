<?php
/**
 * Check how many donors are truly "Pending" according to the logic
 */

require_once 'assets/conn/db_conn.php';

echo "=== CHECKING PENDING DONORS COUNT ===\n\n";

// Fetch all donors
$donorResponse = supabaseRequest("donor_form?select=donor_id&order=submitted_at.desc");

if (!isset($donorResponse['data']) || !is_array($donorResponse['data'])) {
    echo "Failed to fetch donors\n";
    exit;
}

$allDonorIds = array_column($donorResponse['data'], 'donor_id');
echo "Total donors in database: " . count($allDonorIds) . "\n\n";

// Fetch medical history
$medicalResponse = supabaseRequest("medical_history?select=donor_id,needs_review,is_admin,medical_approval");
$medicalByDonorId = [];
if (isset($medicalResponse['data']) && is_array($medicalResponse['data'])) {
    foreach ($medicalResponse['data'] as $row) {
        if (!empty($row['donor_id'])) {
            $medicalByDonorId[$row['donor_id']] = $row;
        }
    }
}

// Fetch screening
$screeningResponse = supabaseRequest("screening_form?select=screening_id,donor_form_id,needs_review,disapproval_reason");
$screeningByDonorId = [];
if (isset($screeningResponse['data']) && is_array($screeningResponse['data'])) {
    foreach ($screeningResponse['data'] as $row) {
        if (!empty($row['donor_form_id'])) {
            $screeningByDonorId[$row['donor_form_id']] = $row;
        }
    }
}

// Fetch physical examination
$physicalResponse = supabaseRequest("physical_examination?select=physical_exam_id,donor_id,needs_review,remarks");
$physicalByDonorId = [];
if (isset($physicalResponse['data']) && is_array($physicalResponse['data'])) {
    foreach ($physicalResponse['data'] as $row) {
        if (!empty($row['donor_id'])) {
            $physicalByDonorId[$row['donor_id']] = $row;
        }
    }
}

// Fetch blood collection
$collectionResponse = supabaseRequest("blood_collection?select=physical_exam_id,needs_review,is_successful");
$collectionByPhysicalExamId = [];
if (isset($collectionResponse['data']) && is_array($collectionResponse['data'])) {
    foreach ($collectionResponse['data'] as $row) {
        if (!empty($row['physical_exam_id'])) {
            $collectionByPhysicalExamId[$row['physical_exam_id']] = $row;
        }
    }
}

// Also need physical exam ID by donor ID for collection lookup
$physicalExamIdByDonorId = [];
if (isset($physicalResponse['data']) && is_array($physicalResponse['data'])) {
    foreach ($physicalResponse['data'] as $row) {
        if (!empty($row['donor_id']) && !empty($row['physical_exam_id'])) {
            $physicalExamIdByDonorId[$row['donor_id']] = $row['physical_exam_id'];
        }
    }
}

$pendingCount = 0;
$statuses = [];

foreach ($allDonorIds as $donorId) {
    // Skip if has eligibility
    $eligibilityCheck = supabaseRequest("eligibility?donor_id=eq.{$donorId}&select=donor_id&limit=1");
    if (isset($eligibilityCheck['data']) && is_array($eligibilityCheck['data']) && !empty($eligibilityCheck['data'])) {
        continue; // Has eligibility - skip
    }
    
    // Check Medical History
    $isMedicalHistoryCompleted = false;
    if (isset($medicalByDonorId[$donorId])) {
        $medRecord = $medicalByDonorId[$donorId];
        $medNeeds = $medRecord['needs_review'] ?? null;
        $isAdmin = $medRecord['is_admin'] ?? null;
        
        $isMedicalHistoryCompleted = ($isAdmin === true || $isAdmin === 'true' || $isAdmin === 'True') || 
                                    ($medNeeds === false || $medNeeds === null || $medNeeds === 0);
    }
    
    // Check Screening
    $isScreeningPassed = false;
    if (isset($screeningByDonorId[$donorId])) {
        $screen = $screeningByDonorId[$donorId];
        $screenNeeds = $screen['needs_review'] ?? null;
        $disapprovalReason = $screen['disapproval_reason'] ?? '';
        
        $isScreeningPassed = ($screenNeeds === false || $screenNeeds === null || $screenNeeds === 0) && empty($disapprovalReason);
    }
    
    // Check Physical Exam
    $isPhysicalExamApproved = false;
    if (isset($physicalByDonorId[$donorId])) {
        $phys = $physicalByDonorId[$donorId];
        $physNeeds = $phys['needs_review'] ?? null;
        $remarks = $phys['remarks'] ?? '';
        
        $isPhysicalExamApproved = ($physNeeds !== true) && 
            !empty($remarks) && 
            !in_array($remarks, ['Pending', 'Temporarily Deferred', 'Permanently Deferred', 'Declined', 'Refused']);
    }
    
    // Determine status
    $statusLabel = 'Pending (Screening)';
    if ($isMedicalHistoryCompleted && $isScreeningPassed && $isPhysicalExamApproved) {
        $statusLabel = 'Pending (Collection)';
    } else if ($isMedicalHistoryCompleted && $isScreeningPassed) {
        $statusLabel = 'Pending (Examination)';
    }
    
    if (!isset($statuses[$statusLabel])) {
        $statuses[$statusLabel] = 0;
    }
    $statuses[$statusLabel]++;
    $pendingCount++;
}

echo "=== STATUS BREAKDOWN ===\n";
foreach ($statuses as $status => $count) {
    echo "$status: $count donors\n";
}
echo "\nTotal Pending Donors: $pendingCount\n";

echo "\n=== SUMMARY ===\n";
echo "Total donors in database: " . count($allDonorIds) . "\n";
echo "Pending donors (no eligibility): $pendingCount\n";
echo "Approved/completed donors: " . (count($allDonorIds) - $pendingCount) . "\n";

/**
 * Supabase Request Helper
 */
function supabaseRequest($endpoint) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SUPABASE_URL . '/rest/v1/' . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Accept: application/json'
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code === 200) {
        return ['data' => json_decode($response, true)];
    } else {
        return ['error' => "HTTP $http_code", 'data' => []];
    }
}

?>

