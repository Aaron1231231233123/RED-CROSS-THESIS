<?php
// Prevent direct access
if (!isset($_GET['donor_id'])) {
    echo '<div class="alert alert-danger">Donor ID is required</div>';
    exit;
}

$donor_id = $_GET['donor_id'];

// Database connection
require_once '../../../assets/conn/db_conn.php';

// OPTIMIZED: Fetch all data in parallel using cURL multi-handle
$headers = array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY,
    'Accept: application/json'
);

// Initialize all cURL handles
$ch_donor = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=*&donor_id=eq.' . $donor_id);
$ch_screening = curl_init(SUPABASE_URL . '/rest/v1/screening_form?select=*&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
$ch_medical = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=*&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
$ch_physical = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=*&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');

// Set options for all handles
$handles = [$ch_donor, $ch_screening, $ch_medical, $ch_physical];
foreach ($handles as $ch) {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 second connection timeout
}

// Execute all requests in parallel
$mh = curl_multi_init();
foreach ($handles as $ch) {
    curl_multi_add_handle($mh, $ch);
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

// Get responses
$donor_response = curl_multi_getcontent($ch_donor);
$screening_response = curl_multi_getcontent($ch_screening);
$medical_response = curl_multi_getcontent($ch_medical);
$physical_response = curl_multi_getcontent($ch_physical);

// Clean up
foreach ($handles as $ch) {
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}
curl_multi_close($mh);

// Parse responses
$donor_info = null;
$screening_info = null;
$medical_history_info = null;
$physical_exam_info = null;

try {
    $donor_data = json_decode($donor_response, true);
    $donor_info = $donor_data[0] ?? null;
} catch (Exception $e) {}

try {
    $screening_data = json_decode($screening_response, true);
    $screening_info = $screening_data[0] ?? null;
} catch (Exception $e) {}

try {
    $medical_data = json_decode($medical_response, true);
    $medical_history_info = $medical_data[0] ?? null;
} catch (Exception $e) {}

try {
    $physical_data = json_decode($physical_response, true);
    $physical_exam_info = $physical_data[0] ?? null;
} catch (Exception $e) {}

// Calculate age from birthdate
$age = '';
if ($donor_info && isset($donor_info['birthdate'])) {
    $birthdate = new DateTime($donor_info['birthdate']);
    $today = new DateTime();
    $age = $today->diff($birthdate)->y;
}
// Flags used by footer/proceed logic
$mhApprovedFlag = ($medical_history_info && isset($medical_history_info['medical_approval']) && strtolower(trim((string)$medical_history_info['medical_approval'])) === 'approved');
$peAcceptedFlag = false;
if ($physical_exam_info && isset($physical_exam_info['remarks'])) {
    $peAcceptedFlag = (strcasecmp((string)$physical_exam_info['remarks'], 'Accepted') === 0);
}
?>

<style>
.donor-profile-content {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    background-color: #f8f9fa;
    padding: 20px;
}

.donor-profile-header {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

 .header-content {
     display: grid;
     grid-template-columns: 1fr 1fr;
     gap: 0;
     width: 100%;
     align-items: start;
 }
 
 .header-left {
     display: flex;
     flex-direction: column;
     gap: 8px;
     align-items: flex-start;
 }
 
 .header-right {
     display: flex;
     flex-direction: column;
     gap: 8px;
     align-items: flex-end;
     text-align: right;
 }
 
 .donor-name-display {
     font-size: 2.2rem;
     font-weight: 700;
     color: #000000;
     margin: 0;
     line-height: 1.2;
 }
 
 .donor-basic-info {
     font-size: 1.1rem;
     color: #6c757d;
     font-weight: 400;
     margin: 0;
     line-height: 1.2;
 }
 
 .donor-id-display {
     font-size: 1.1rem;
     font-weight: 400;
     color: #6c757d;
     margin: 0;
     line-height: 1.2;
 }
 
 .blood-type-display {
     font-size: 1.1rem;
     color: #6c757d;
     font-weight: 400;
     margin: 0;
     line-height: 1.2;
 }

.section-container {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.section-title i {
    margin-right: 12px;
    color: #b22222;
    font-size: 1.3rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
    font-size: 1rem;
}

.form-input {
    padding: 12px 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 1rem;
    background-color: white;
    color: #495057;
    transition: border-color 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #b22222;
    box-shadow: 0 0 0 0.2rem rgba(178, 34, 34, 0.15);
}

 .status-table {
     width: 100%;
     border-collapse: collapse;
     margin-top: 15px;
     border-radius: 8px;
     overflow: hidden;
     box-shadow: 0 1px 4px rgba(0,0,0,0.1);
     table-layout: fixed;
 }

 .status-table th {
     background: #b22222;
     color: white;
     padding: 15px 20px;
     text-align: center;
     font-weight: 600;
     font-size: 0.95rem;
 }

   .status-table td {
      padding: 15px 20px;
      border: 1px solid #e9ecef;
      vertical-align: middle;
      background: white;
      text-align: center;
  }
 
 .status-table th {
     background: #b22222;
     color: white;
     padding: 15px 20px;
     text-align: center;
     font-weight: 600;
     font-size: 0.95rem;
     border: 1px solid #b22222;
     width: 25%;
 }
 
 /* Medical History Table (Interviewer) - 2 equal columns */
 .status-table.medical-history th,
 .status-table.medical-history td {
     width: 50%;
 }
 
 /* Physical Examination Table (Physician) - 2 equal columns */
 .status-table.physical-examination th,
 .status-table.physical-examination td {
     width: 50%;
 }

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    text-align: center;
    min-width: 90px;
    display: inline-block;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-clear {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-approved {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.status-completed {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.action-button {
    background: #ffc107;
    color: #212529;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    min-width: 100px;
}

.action-button:hover {
    background: #e0a800;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}


@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 20px;
    }
    
    .header-right {
        text-align: left;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    
    .donor-profile-content {
        padding: 15px;
    }
    
    .section-container, .donor-profile-header {
        padding: 20px;
    }
}
</style>

<div class="donor-profile-content">
    <!-- Header Section with Donor Name and Basic Info -->
    <div class="donor-profile-header">
        <div class="header-content">
            <div class="header-left">
                <span class="donor-name-display">
                    <?php 
                    $firstName = $donor_info['first_name'] ?? '';
                    $surname = $donor_info['surname'] ?? '';
                    $middleName = $donor_info['middle_name'] ?? '';
                    $fullName = trim($firstName . ' ' . $middleName . ' ' . $surname);
                    echo htmlspecialchars($fullName ?: 'N/A'); 
                    ?>
                </span>
                <span class="donor-basic-info">
                    <?php echo htmlspecialchars($age ? $age : 'Age N/A'); ?>, <?php echo htmlspecialchars(ucfirst($donor_info['sex'] ?? 'N/A')); ?>
                </span>
            </div>
            <div class="header-right">
                                 <span class="donor-id-display">
                     Donor ID: <?php echo htmlspecialchars($donor_info['prc_donor_number'] ?? 'N/A'); ?>
                 </span>
                <div class="badge fs-6 px-3 py-2" style="background-color: #8B0000; color: white; border-radius: 20px; display: inline-flex; flex-direction: column; align-items: center; justify-content: center; min-width: 80px;">
                    <div style="font-size: 0.75rem; font-weight: 500; opacity: 0.9;">Blood Type</div>
                    <div style="font-size: 1.3rem; font-weight: 700; line-height: 1;"><?php echo htmlspecialchars($screening_info['blood_type'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Donor Information Section -->
    <div class="section-container">
        <h3 class="section-title">
            <i class="fas fa-user"></i>
            Donor Information
        </h3>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label" for="donor-birthdate">Birthdate</label>
                <input type="text" id="donor-birthdate" name="donor_birthdate" class="form-input" value="<?php echo htmlspecialchars($donor_info['birthdate'] ?? 'N/A'); ?>" readonly aria-label="Donor birthdate">
            </div>
            <div class="form-group">
                <label class="form-label" for="donor-address">Address</label>
                <input type="text" id="donor-address" name="donor_address" class="form-input" value="<?php echo htmlspecialchars($donor_info['permanent_address'] ?? 'N/A'); ?>" readonly aria-label="Donor address">
            </div>
            <div class="form-group">
                <label class="form-label" for="donor-mobile">Mobile Number</label>
                <input type="text" id="donor-mobile" name="donor_mobile" class="form-input" value="<?php echo htmlspecialchars($donor_info['mobile'] ?? 'N/A'); ?>" readonly aria-label="Donor mobile number">
            </div>
            <div class="form-group">
                <label class="form-label" for="donor-civil-status">Civil Status</label>
                <input type="text" id="donor-civil-status" name="donor_civil_status" class="form-input" value="<?php echo htmlspecialchars(ucfirst($donor_info['civil_status'] ?? 'N/A')); ?>" readonly aria-label="Donor civil status">
            </div>
            <div class="form-group">
                <label class="form-label" for="donor-nationality">Nationality</label>
                <input type="text" id="donor-nationality" name="donor_nationality" class="form-input" value="<?php echo htmlspecialchars($donor_info['nationality'] ?? 'N/A'); ?>" readonly aria-label="Donor nationality">
            </div>
            <div class="form-group">
                <label class="form-label" for="donor-occupation">Occupation</label>
                <input type="text" id="donor-occupation" name="donor_occupation" class="form-input" value="<?php echo htmlspecialchars($donor_info['occupation'] ?? 'N/A'); ?>" readonly aria-label="Donor occupation">
            </div>
        </div>
    </div>

    <!-- Interviewer Summary Table -->
    <div class="section-container">
        <h3 class="section-title">
            <i class="fas fa-user-check"></i>
            Interviewer
        </h3>
        <table class="status-table medical-history">
            <thead>
                <tr>
                    <th>Medical History</th>
                    <th>Initial Screening</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php 
                            $mhExists = !empty($medical_history_info); 
                            $mhApproved = ($medical_history_info && isset($medical_history_info['medical_approval']) && strtolower(trim($medical_history_info['medical_approval'])) === 'approved'); 
                        ?>
                        <div style="display: flex; flex-direction: row; align-items: center; gap: 15px; justify-content: center;">
                            <span class="status-badge <?php echo $mhExists ? 'status-completed' : 'status-pending'; ?>">
                                <?php echo $mhExists ? 'Completed' : '--'; ?>
                            </span>
                            <?php if ($mhExists): ?>
                                <?php if ($mhApproved): ?>
                                    <button class="btn btn-info btn-sm" id="medicalHistoryViewBtn" title="View Medical History" style="padding: 6px 12px; font-size: 0.875rem; min-width: 40px; border-radius: 20px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-warning btn-sm" id="medicalHistoryConfirmBtn" title="Edit Medical History" style="padding: 6px 12px; font-size: 0.875rem; min-width: 40px; border-radius: 20px;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php
                            $screenPassed = false;
                            if (!empty($screening_info)) {
                                $screenPassed = isset($screening_info['screening_id']) && !empty($screening_info['screening_id']);
                            }
                        ?>
                        <div style="display: flex; flex-direction: row; align-items: center; gap: 15px; justify-content: center;">
                            <span class="status-badge <?php echo ($screening_info ? ($screenPassed ? 'status-completed' : 'status-pending') : 'status-pending'); ?>">
                                <?php echo ($screening_info ? ($screenPassed ? 'Passed' : 'Pending') : '--'); ?>
                            </span>
                            <?php if ($screening_info): ?>
                                <button class="btn btn-info btn-sm" id="initialScreeningViewBtn" title="View Initial Screening" style="padding: 6px 12px; font-size: 0.875rem; min-width: 40px; border-radius: 20px;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Physician Summary Table -->
    <div class="section-container">
        <h3 class="section-title">
            <i class="fas fa-user-md"></i>
            Physician
        </h3>
        <table class="status-table physical-examination">
            <thead>
                <tr>
                    <th>Medical History</th>
                    <th>Physical Examination</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php 
                            $mhLabel = isset($medical_history_info['medical_approval']) ? trim((string)$medical_history_info['medical_approval']) : '';
                            $mhBadgeClass = $mhLabel ? ($mhLabel === 'Approved' ? 'status-approved' : 'status-pending') : 'status-pending';
                            $mhIsApproved = ($mhLabel === 'Approved');
                            
                            // Get Physical Examination remarks for controlling button state
                            $peRemarks = isset($physical_exam_info['remarks']) ? trim((string)$physical_exam_info['remarks']) : '';
                            $peIsPending = (strcasecmp($peRemarks, 'Pending') === 0 || $peRemarks === '');
                        ?>
                        <div style="display: flex; flex-direction: row; align-items: center; gap: 15px; justify-content: center;">
                            <span class="status-badge <?php echo $mhBadgeClass; ?>">
                                <?php echo $mhLabel !== '' ? htmlspecialchars($mhLabel) : '--'; ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <?php 
                            $peBadgeClass = 'status-pending';
                            if (strcasecmp($peRemarks, 'Accepted') === 0) {
                                $peBadgeClass = 'status-completed';
                            } elseif (strcasecmp($peRemarks, 'Pending') === 0) {
                                $peBadgeClass = 'status-pending';
                            } else if ($peRemarks !== '') {
                                $peBadgeClass = 'status-pending';
                            }
                            $peIsAccepted = (strcasecmp($peRemarks, 'Accepted') === 0);
                        ?>
                        <div style="display: flex; flex-direction: row; align-items: center; gap: 15px; justify-content: center;">
                            <span class="status-badge <?php echo $peBadgeClass; ?>">
                                <?php echo $peRemarks !== '' ? htmlspecialchars($peRemarks) : 'Pending'; ?>
                            </span>
                            <?php if ($peRemarks !== ''): ?>
                                <?php if ($peIsPending): ?>
                                    <button class="btn btn-warning btn-sm" id="physicalExamConfirmBtn" title="Edit Physical Examination" style="padding: 6px 12px; font-size: 0.875rem; min-width: 40px; border-radius: 20px;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-info btn-sm" id="physicalExamViewBtn" title="View Physical Examination" style="padding: 6px 12px; font-size: 0.875rem; min-width: 40px; border-radius: 20px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Hidden fields for functionality -->
    <div style="display: none;">
        <input type="hidden" id="dp-donor-id-flag" value="<?php echo htmlspecialchars($donor_id); ?>">
        <input type="hidden" id="pe-remarks-flag" value="<?php echo htmlspecialchars(strtolower($physical_exam_info['remarks'] ?? '')); ?>">
        <?php 
            $peNeedsReview = isset($physical_exam_info['needs_review']) && (
                $physical_exam_info['needs_review'] === true ||
                $physical_exam_info['needs_review'] === 1 ||
                $physical_exam_info['needs_review'] === '1' ||
                (is_string($physical_exam_info['needs_review']) && in_array(strtolower(trim($physical_exam_info['needs_review'])), ['true','t','yes','y'], true))
            );
        ?>
        <input type="hidden" id="pe-needs-review-flag" value="<?php echo $peNeedsReview ? '1' : '0'; ?>">
        <!-- Default values: Type of Donation = "walk-in", Eligibility Status = "Approve to Donate" -->
        <input type="hidden" id="default-donation-type" value="walk-in">
        <input type="hidden" id="default-eligibility-status" value="approve">
    </div>
</div>

<!-- Event listeners will be handled by the main dashboard JavaScript -->
<script>
    (function(){
        try {
            const proceedBtn = document.getElementById('proceedToPhysicalBtn');
            const mhApproved = <?php echo $mhApprovedFlag ? 'true' : 'false'; ?>;
            const peAccepted = <?php echo $peAcceptedFlag ? 'true' : 'false'; ?>;
            if (proceedBtn) {
                if (!peAccepted) {
                    try {
                        proceedBtn.style.display = 'none';
                        proceedBtn.classList.add('d-none');
                        const footer = proceedBtn.closest('.modal-footer');
                        if (footer) { footer.style.display = 'none'; footer.classList.add('d-none'); }
                    } catch(_) {}
                }
            }
            // Backdrop/body lifecycle is managed by the parent dashboard. Avoid touching it here to prevent flicker.
        } catch (_) { /* noop */ }
    })();
    // Footer confirm button functionality is handled by the dashboard's functions
</script>

<!-- Footer confirmation modals are included in the main dashboard -->