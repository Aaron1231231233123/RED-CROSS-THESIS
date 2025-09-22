<?php
/**
 * Fix Workflow Progression - Ensures smooth transition from Physical Examination to Blood Collection
 * This script identifies and fixes donors who are stuck between physical examination and blood collection
 */

session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit();
}

try {
    $fixed_count = 0;
    $errors = [];
    
    // 1. Find donors with completed physical examinations but no blood collection record
    $physicalCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,donor_id,screening_id,remarks,needs_review&needs_review=eq.false&remarks=eq.Accepted');
    curl_setopt($physicalCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($physicalCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $physicalResponse = curl_exec($physicalCurl);
    $physicalHttpCode = curl_getinfo($physicalCurl, CURLINFO_HTTP_CODE);
    curl_close($physicalCurl);
    
    if ($physicalHttpCode !== 200) {
        throw new Exception('Failed to fetch physical examination records');
    }
    
    $physicalExams = json_decode($physicalResponse, true) ?: [];
    
    foreach ($physicalExams as $exam) {
        $physicalExamId = $exam['physical_exam_id'];
        $donorId = $exam['donor_id'];
        $screeningId = $exam['screening_id'];
        
        // 2. Check if blood collection record already exists for this physical exam
        $collectionCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&physical_exam_id=eq.' . $physicalExamId);
        curl_setopt($collectionCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($collectionCurl, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json'
        ]);
        
        $collectionResponse = curl_exec($collectionCurl);
        $collectionHttpCode = curl_getinfo($collectionCurl, CURLINFO_HTTP_CODE);
        curl_close($collectionCurl);
        
        if ($collectionHttpCode !== 200) {
            $errors[] = "Failed to check blood collection for physical exam $physicalExamId";
            continue;
        }
        
        $existingCollections = json_decode($collectionResponse, true) ?: [];
        
        // 3. If no blood collection exists, create one
        if (empty($existingCollections)) {
            $now = gmdate('c');
            
            $collectionData = [
                'physical_exam_id' => $physicalExamId,
                'donor_id' => $donorId,
                'needs_review' => true,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now
            ];
            
            if ($screeningId) {
                $collectionData['screening_id'] = $screeningId;
            }
            
            $createCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
            curl_setopt($createCurl, CURLOPT_POST, true);
            curl_setopt($createCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($createCurl, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            curl_setopt($createCurl, CURLOPT_POSTFIELDS, json_encode($collectionData));
            
            $createResponse = curl_exec($createCurl);
            $createHttpCode = curl_getinfo($createCurl, CURLINFO_HTTP_CODE);
            curl_close($createCurl);
            
            if ($createHttpCode >= 200 && $createHttpCode < 300) {
                $fixed_count++;
                error_log("Created blood collection record for physical exam $physicalExamId, donor $donorId");
            } else {
                $errors[] = "Failed to create blood collection for physical exam $physicalExamId: HTTP $createHttpCode";
            }
        }
    }
    
    // 4. Also check for donors with physical exams that need status updates
    $updateCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,donor_id,remarks,needs_review&remarks=eq.Accepted&needs_review=eq.true');
    curl_setopt($updateCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($updateCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $updateResponse = curl_exec($updateCurl);
    $updateHttpCode = curl_getinfo($updateCurl, CURLINFO_HTTP_CODE);
    curl_close($updateCurl);
    
    if ($updateHttpCode === 200) {
        $updateExams = json_decode($updateResponse, true) ?: [];
        
        foreach ($updateExams as $exam) {
            $physicalExamId = $exam['physical_exam_id'];
            $donorId = $exam['donor_id'];
            
            // Update physical exam to mark as completed
            $patchCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $physicalExamId);
            curl_setopt($patchCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($patchCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($patchCurl, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            curl_setopt($patchCurl, CURLOPT_POSTFIELDS, json_encode([
                'needs_review' => false,
                'updated_at' => gmdate('c')
            ]));
            
            $patchResponse = curl_exec($patchCurl);
            $patchHttpCode = curl_getinfo($patchCurl, CURLINFO_HTTP_CODE);
            curl_close($patchCurl);
            
            if ($patchHttpCode >= 200 && $patchHttpCode < 300) {
                error_log("Updated physical exam $physicalExamId to completed status");
            } else {
                $errors[] = "Failed to update physical exam $physicalExamId: HTTP $patchHttpCode";
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Workflow progression fix completed",
        'fixed_count' => $fixed_count,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("Workflow progression fix error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fixing workflow progression: ' . $e->getMessage()
    ]);
}
?>
/**
 * Fix Workflow Progression - Ensures smooth transition from Physical Examination to Blood Collection
 * This script identifies and fixes donors who are stuck between physical examination and blood collection
 */

session_start();
require_once '../conn/db_conn.php';

header('Content-Type: application/json');

// Only allow admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit();
}

try {
    $fixed_count = 0;
    $errors = [];
    
    // 1. Find donors with completed physical examinations but no blood collection record
    $physicalCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,donor_id,screening_id,remarks,needs_review&needs_review=eq.false&remarks=eq.Accepted');
    curl_setopt($physicalCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($physicalCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $physicalResponse = curl_exec($physicalCurl);
    $physicalHttpCode = curl_getinfo($physicalCurl, CURLINFO_HTTP_CODE);
    curl_close($physicalCurl);
    
    if ($physicalHttpCode !== 200) {
        throw new Exception('Failed to fetch physical examination records');
    }
    
    $physicalExams = json_decode($physicalResponse, true) ?: [];
    
    foreach ($physicalExams as $exam) {
        $physicalExamId = $exam['physical_exam_id'];
        $donorId = $exam['donor_id'];
        $screeningId = $exam['screening_id'];
        
        // 2. Check if blood collection record already exists for this physical exam
        $collectionCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection?select=blood_collection_id&physical_exam_id=eq.' . $physicalExamId);
        curl_setopt($collectionCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($collectionCurl, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json'
        ]);
        
        $collectionResponse = curl_exec($collectionCurl);
        $collectionHttpCode = curl_getinfo($collectionCurl, CURLINFO_HTTP_CODE);
        curl_close($collectionCurl);
        
        if ($collectionHttpCode !== 200) {
            $errors[] = "Failed to check blood collection for physical exam $physicalExamId";
            continue;
        }
        
        $existingCollections = json_decode($collectionResponse, true) ?: [];
        
        // 3. If no blood collection exists, create one
        if (empty($existingCollections)) {
            $now = gmdate('c');
            
            $collectionData = [
                'physical_exam_id' => $physicalExamId,
                'donor_id' => $donorId,
                'needs_review' => true,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now
            ];
            
            if ($screeningId) {
                $collectionData['screening_id'] = $screeningId;
            }
            
            $createCurl = curl_init(SUPABASE_URL . '/rest/v1/blood_collection');
            curl_setopt($createCurl, CURLOPT_POST, true);
            curl_setopt($createCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($createCurl, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            curl_setopt($createCurl, CURLOPT_POSTFIELDS, json_encode($collectionData));
            
            $createResponse = curl_exec($createCurl);
            $createHttpCode = curl_getinfo($createCurl, CURLINFO_HTTP_CODE);
            curl_close($createCurl);
            
            if ($createHttpCode >= 200 && $createHttpCode < 300) {
                $fixed_count++;
                error_log("Created blood collection record for physical exam $physicalExamId, donor $donorId");
            } else {
                $errors[] = "Failed to create blood collection for physical exam $physicalExamId: HTTP $createHttpCode";
            }
        }
    }
    
    // 4. Also check for donors with physical exams that need status updates
    $updateCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,donor_id,remarks,needs_review&remarks=eq.Accepted&needs_review=eq.true');
    curl_setopt($updateCurl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($updateCurl, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json'
    ]);
    
    $updateResponse = curl_exec($updateCurl);
    $updateHttpCode = curl_getinfo($updateCurl, CURLINFO_HTTP_CODE);
    curl_close($updateCurl);
    
    if ($updateHttpCode === 200) {
        $updateExams = json_decode($updateResponse, true) ?: [];
        
        foreach ($updateExams as $exam) {
            $physicalExamId = $exam['physical_exam_id'];
            $donorId = $exam['donor_id'];
            
            // Update physical exam to mark as completed
            $patchCurl = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?physical_exam_id=eq.' . $physicalExamId);
            curl_setopt($patchCurl, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($patchCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($patchCurl, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            curl_setopt($patchCurl, CURLOPT_POSTFIELDS, json_encode([
                'needs_review' => false,
                'updated_at' => gmdate('c')
            ]));
            
            $patchResponse = curl_exec($patchCurl);
            $patchHttpCode = curl_getinfo($patchCurl, CURLINFO_HTTP_CODE);
            curl_close($patchCurl);
            
            if ($patchHttpCode >= 200 && $patchHttpCode < 300) {
                error_log("Updated physical exam $physicalExamId to completed status");
            } else {
                $errors[] = "Failed to update physical exam $physicalExamId: HTTP $patchHttpCode";
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Workflow progression fix completed",
        'fixed_count' => $fixed_count,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    error_log("Workflow progression fix error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fixing workflow progression: ' . $e->getMessage()
    ]);
}
?>
