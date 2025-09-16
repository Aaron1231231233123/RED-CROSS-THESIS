<?php
// Prevent direct access
if (!isset($_GET['donor_id'])) {
    echo '<div class="alert alert-danger">Donor ID is required</div>';
    exit;
}

$donor_id = $_GET['donor_id'];

// Database connection
require_once '../../../assets/conn/db_conn.php';

// Fetch donor information
try {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=*&donor_id=eq.' . $donor_id);
    $headers = array(
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Accept: application/json'
    );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $donor_info = json_decode($response, true);
    $donor_info = $donor_info[0] ?? null;
} catch (Exception $e) {
    $donor_info = null;
}

// Fetch screening information
try {
    $screening_url = SUPABASE_URL . '/rest/v1/screening_form?select=*&donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1';
    $ch = curl_init($screening_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $screening_info = json_decode($response, true);
    $screening_info = $screening_info[0] ?? null;
} catch (Exception $e) {
    $screening_info = null;
}

// Fetch medical history information
try {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/medical_history?select=*&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $medical_history_info = json_decode($response, true);
    $medical_history_info = $medical_history_info[0] ?? null;
} catch (Exception $e) {
    $medical_history_info = null;
}

// Fetch physical examination information
try {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=*&donor_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $physical_exam_info = json_decode($response, true);
    $physical_exam_info = $physical_exam_info[0] ?? null;
} catch (Exception $e) {
    $physical_exam_info = null;
}

// Calculate age from birthdate
$age = '';
if ($donor_info && isset($donor_info['birthdate'])) {
    $birthdate = new DateTime($donor_info['birthdate']);
    $today = new DateTime();
    $age = $today->diff($birthdate)->y;
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
 
 /* Medical History Table - 4 equal columns */
 .status-table.medical-history th,
 .status-table.medical-history td {
     width: 25%;
 }
 
 /* Initial Screening Table - 3 equal columns */
 .status-table.initial-screening th,
 .status-table.initial-screening td {
     width: 33.33%;
 }
 
 /* Physical Examination Table - 3 equal columns */
 .status-table.physical-examination th,
 .status-table.physical-examination td {
     width: 33.33%;
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

.eligibility-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    margin-top: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.eligibility-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    align-items: end;
}

.eligibility-select {
    padding: 12px 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 1rem;
    background-color: white;
    color: #495057;
    width: 100%;
    transition: border-color 0.2s;
}

.eligibility-select:focus {
    outline: none;
    border-color: #b22222;
    box-shadow: 0 0 0 0.2rem rgba(178, 34, 34, 0.15);
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
    
    .eligibility-grid {
        grid-template-columns: 1fr;
    }
    
    .donor-profile-content {
        padding: 15px;
    }
    
    .section-container, .donor-profile-header, .eligibility-section {
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
                <span class="blood-type-display">
                    <?php echo htmlspecialchars($screening_info['blood_type'] ?? 'N/A'); ?>
                </span>
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
                <label class="form-label">Birthdate</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($donor_info['birthdate'] ?? 'N/A'); ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($donor_info['permanent_address'] ?? 'N/A'); ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Mobile Number</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($donor_info['mobile'] ?? 'N/A'); ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Civil Status</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars(ucfirst($donor_info['civil_status'] ?? 'N/A')); ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Nationality</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($donor_info['nationality'] ?? 'N/A'); ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Occupation</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($donor_info['occupation'] ?? 'N/A'); ?>" readonly>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php $mhExists = !empty($medical_history_info); $mhApproved = ($medical_history_info && isset($medical_history_info['medical_approval']) && strtolower(trim($medical_history_info['medical_approval'])) === 'approved'); ?>
                        <span class="status-badge <?php echo $mhExists ? 'status-completed' : 'status-pending'; ?>">
                            <?php echo $mhExists ? 'Completed' : '--'; ?>
                        </span>
                    </td>
                    <td>
                        <?php
                            $screenPassed = false;
                            if (!empty($screening_info)) {
                                $screenPassed = isset($screening_info['screening_id']) && !empty($screening_info['screening_id']);
                            }
                        ?>
                        <span class="status-badge <?php echo ($screening_info ? ($screenPassed ? 'status-completed' : 'status-pending') : 'status-pending'); ?>">
                            <?php echo ($screening_info ? ($screenPassed ? 'Passed' : 'Pending') : '--'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($mhApproved): ?>
                            <button class="btn btn-info btn-sm" id="medicalHistoryViewBtn" title="View Medical History"><i class="fas fa-eye"></i></button>
                        <?php else: ?>
                            <button class="action-button" id="medicalHistoryConfirmBtn">Confirm</button>
                        <?php endif; ?>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php 
                            $mhLabel = isset($medical_history_info['medical_approval']) ? trim((string)$medical_history_info['medical_approval']) : '';
                            $mhBadgeClass = $mhLabel ? ($mhLabel === 'Approved' ? 'status-approved' : 'status-pending') : 'status-pending';
                        ?>
                        <span class="status-badge <?php echo $mhBadgeClass; ?>">
                            <?php echo $mhLabel !== '' ? htmlspecialchars($mhLabel) : '--'; ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                            $peRemarks = isset($physical_exam_info['remarks']) ? trim((string)$physical_exam_info['remarks']) : '';
                            $peBadgeClass = 'status-pending';
                            if (strcasecmp($peRemarks, 'Accepted') === 0) {
                                $peBadgeClass = 'status-completed';
                            } elseif (strcasecmp($peRemarks, 'Pending') === 0) {
                                $peBadgeClass = 'status-pending';
                            } else if ($peRemarks !== '') {
                                $peBadgeClass = 'status-pending';
                            }
                            $peNeedsReview = isset($physical_exam_info['needs_review']) && (
                                $physical_exam_info['needs_review'] === true ||
                                $physical_exam_info['needs_review'] === 1 ||
                                $physical_exam_info['needs_review'] === '1' ||
                                (is_string($physical_exam_info['needs_review']) && in_array(strtolower(trim($physical_exam_info['needs_review'])), ['true','t','yes','y'], true))
                            );
                            $peIsAccepted = (strcasecmp($peRemarks, 'Accepted') === 0);
                        ?>
                        <span class="status-badge <?php echo $peBadgeClass; ?>">
                            <?php echo $peRemarks !== '' ? htmlspecialchars($peRemarks) : 'Pending'; ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($peIsAccepted): ?>
                            <button class="btn btn-info btn-sm" id="physicalExamViewBtn" title="View Physical Examination"><i class="fas fa-eye"></i></button>
                        <?php elseif ($peNeedsReview): ?>
                            <button class="action-button" id="physicalExamConfirmBtn">Confirm</button>
                        <?php else: ?>
                            <button class="btn btn-info btn-sm" id="physicalExamViewBtn" title="View Physical Examination"><i class="fas fa-eye"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Type of Donation & Eligibility Status Section -->
    <div class="eligibility-section">
        <div class="eligibility-grid">
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
            <div class="form-group">
                <label class="form-label">Type of Donation</label>
                <input type="text" class="form-input" value="<?php echo htmlspecialchars($screening_info['donation_type'] ?? 'N/A'); ?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Eligibility Status</label>
                <?php 
                    $mhApprovedFlag = ($medical_history_info && isset($medical_history_info['medical_approval']) && strtolower(trim($medical_history_info['medical_approval'])) === 'approved');
                    // Backend gate: dropdown enabled only when PE remarks/status is Accepted
                    $peRemarksForGate = strtolower(trim($physical_exam_info['remarks'] ?? ''));
                    $peAcceptedFlag = ($peRemarksForGate === 'accepted');
                    $eligibilityEnabled = ($mhApprovedFlag && $peAcceptedFlag);
                ?>
                <select class="eligibility-select" id="eligibilityStatus" <?php echo $eligibilityEnabled ? '' : 'disabled'; ?> data-enabled="<?php echo $eligibilityEnabled ? '1' : '0'; ?>">
                    <option value="approve">Approve to Donate</option>
                    <option value="defer">Defer</option>
                    <option value="decline">Decline</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Event listeners will be handled by the main dashboard JavaScript -->
<script>
    (function(){
        try {
            // NUCLEAR OPTION: Completely override Bootstrap modal behavior
            document.addEventListener('DOMContentLoaded', function() {
                // Override Bootstrap's modal behavior before it can affect the page
                const originalAddClass = Element.prototype.classList.add;
                Element.prototype.classList.add = function(...classes) {
                    if (classes.includes('modal-open')) {
                        // Prevent modal-open class from being added to body
                        return;
                    }
                    return originalAddClass.apply(this, classes);
                };
                
                const originalSetAttribute = Element.prototype.setAttribute;
                Element.prototype.setAttribute = function(name, value) {
                    if (name === 'style' && this === document.body) {
                        // Prevent body style changes
                        if (value.includes('padding-right')) {
                            value = value.replace(/padding-right[^;]*;?/g, '');
                        }
                    }
                    return originalSetAttribute.call(this, name, value);
                };
                
                // Also prevent any existing modal-open class
                if (document.body.classList.contains('modal-open')) {
                    document.body.classList.remove('modal-open');
                }
                
                // Force dashboard header positioning
                const dashboardHeader = document.querySelector('.dashboard-home-header');
                if (dashboardHeader) {
                    dashboardHeader.style.position = 'static';
                    dashboardHeader.style.left = 'auto';
                    dashboardHeader.style.right = 'auto';
                    dashboardHeader.style.top = 'auto';
                    dashboardHeader.style.zIndex = 'auto';
                }
            });
            
            const select = document.getElementById('eligibilityStatus');
            const proceedBtn = document.getElementById('proceedToPhysicalBtn');
            const mhApproved = <?php echo $mhApprovedFlag ? 'true' : 'false'; ?>;
            const peAccepted = <?php echo $peAcceptedFlag ? 'true' : 'false'; ?>;

            function showRequirementAlert() {
                const message = 'Eligibility Status can be set only when:\n\n' +
                                '• Medical History is Approved, and\n' +
                                '• Physical Examination status is Pending.';
                if (window.customConfirm) {
                    window.customConfirm(message, function(){});
                } else {
                    alert(message);
                }
            }

            if (select) {
                // Disable unless MH Approved and PE Accepted
                if (!peAccepted || !mhApproved) {
                    select.disabled = true;
                }
                select.title = 'Enabled only when Medical is Approved and Physical Examination is Accepted';
            }
            if (proceedBtn) {
                // Only allow Confirm when PE is Accepted (backend rule)
                if (!peAccepted) {
                    try {
                        // Hide the actual Confirm button and also its container if present
                        proceedBtn.style.display = 'none';
                        proceedBtn.classList.add('d-none');
                        const footer = proceedBtn.closest('.modal-footer');
                        if (footer) { footer.style.display = 'none'; footer.classList.add('d-none'); }
                    } catch(_) {}
                }
                // Note: Footer confirm button click handling is managed by the dashboard's bindProceedToPhysicalButton function
                // This ensures consistent behavior and prevents duplicate event handlers
            }
        } catch (err) { /* noop */ }
    })();
    
    // Note: Footer confirm button functionality is handled by the dashboard's advanceToCollection function
</script>

<!-- Footer confirmation modals are included in the main dashboard -->
