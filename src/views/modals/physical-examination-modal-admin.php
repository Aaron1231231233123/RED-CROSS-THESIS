<?php
/**
 * Physical Examination Modal - Admin Version
 * This file contains the HTML structure for the physical examination modal
 * Used specifically for admin workflows
 */

$physical_exam_physician_name = 'Physician';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_data = [];

    if (function_exists('makeSupabaseApiCall')) {
        $user_data = makeSupabaseApiCall(
            'users',
            ['first_name', 'surname'],
            ['user_id' => 'eq.' . $user_id]
        );
    } else {
        $ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=first_name,surname&user_id=eq.' . $user_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $user_data = json_decode($response, true) ?: [];
    }

    if (!empty($user_data) && isset($user_data[0]) && is_array($user_data[0])) {
        $first_name = trim($user_data[0]['first_name'] ?? '');
        $surname = trim($user_data[0]['surname'] ?? '');
        $combined = trim($first_name . ' ' . $surname);

        if ($combined !== '') {
            $physical_exam_physician_name = $combined;
        } elseif ($first_name !== '') {
            $physical_exam_physician_name = $first_name;
        } elseif ($surname !== '') {
            $physical_exam_physician_name = $surname;
        }
    }
}
?>

<!-- Physical Examination Modal - Admin -->
<div class="modal fade" id="physicalExaminationModalAdmin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width:1100px; width:95%;">
        <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden;">
            <div class="modal-header" style="background: #941022; color: white; border-bottom: none;">
                <h5 class="modal-title"><i class="fas fa-stethoscope me-2"></i>Physical Examination Form - Admin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Progress Indicator -->
            <div class="physical-progress-container">
            <div class="physical-progress-steps">
                <div class="physical-step active" data-step="1">
                    <div class="physical-step-number">1</div>
                    <div class="physical-step-label">Vital Signs</div>
                </div>
                <div class="physical-step" data-step="2">
                    <div class="physical-step-number">2</div>
                    <div class="physical-step-label">Examination</div>
                </div>
                <div class="physical-step" data-step="3">
                    <div class="physical-step-number">3</div>
                    <div class="physical-step-label">Review & Submit</div>
                </div>
            </div>
                <div class="physical-progress-line">
                    <div class="physical-progress-fill"></div>
                </div>
            </div>

            <form id="physicalExaminationFormAdmin" class="physical-modal-form">
            <input type="hidden" id="physical-donor-id-admin" name="donor_id">
            <input type="hidden" id="physical-screening-id-admin" name="screening_id">

            <!-- Step 1: Vital Signs -->
            <div class="modal-body physical-step-content active" id="physical-step-1-admin">
                <div class="physical-step-inner">
                    <h4>Step 1: Vital Signs</h4>
                    <p class="text-muted">Please enter the patient's vital signs</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="physical-blood-pressure-admin" class="form-label">Blood Pressure *</label>
                            <div class="bp-input-container">
                                <input type="number" 
                                       class="form-control bp-input bp-systolic" 
                                       id="physical-blood-pressure-systolic-admin" 
                                       name="blood_pressure_systolic" 
                                       placeholder="Systolic" 
                                       min="60" 
                                       max="250" 
                                       required>
                                <span class="bp-separator">/</span>
                                <input type="number" 
                                       class="form-control bp-input bp-diastolic" 
                                       id="physical-blood-pressure-diastolic-admin" 
                                       name="blood_pressure_diastolic" 
                                       placeholder="Diastolic" 
                                       min="40" 
                                       max="150" 
                                       required>
                            </div>
                            <input type="hidden" 
                                   id="physical-blood-pressure-admin" 
                                   name="blood_pressure">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="physical-pulse-rate-admin" class="form-label">Pulse Rate (BPM) *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="physical-pulse-rate-admin" 
                                   name="pulse_rate" 
                                   placeholder="BPM" 
                                   min="40" 
                                   max="200" 
                                   required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="physical-body-temp-admin" class="form-label">Body Temperature (°C) *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="physical-body-temp-admin" 
                                   name="body_temp" 
                                   placeholder="°C" 
                                   step="0.1" 
                                   min="35" 
                                   max="42" 
                                   required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Physical Examination -->
            <div class="modal-body physical-step-content" id="physical-step-2-admin">
                <div class="physical-step-inner">
                    <h4>Step 2: Physical Examination</h4>
                    <p class="text-muted">Please enter examination findings</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="physical-gen-appearance-admin" class="form-label">General Appearance *</label>
                            <select class="form-control" 
                                    id="physical-gen-appearance-admin" 
                                    name="gen_appearance" 
                                    required>
                                <option value="" disabled selected hidden>Select observation</option>
                                <option value="Appears healthy">Appears healthy</option>
                                <option value="Weak/pale">Weak/pale</option>
                                <option value="Anxious/nervous">Anxious/nervous</option>
                                <option value="Ill-looking">Ill-looking</option>
                                <option value="Deferred for further assessment">Deferred for further assessment (defer)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-skin-admin" class="form-label">Skin *</label>
                            <select class="form-control" 
                                    id="physical-skin-admin" 
                                    name="skin" 
                                    required>
                                <option value="" disabled selected hidden>Select observation</option>
                                <option value="Normal">Normal</option>
                                <option value="With lesion/rash">With lesion/rash</option>
                                <option value="With jaundice">With jaundice</option>
                                <option value="With puncture marks (defer)">With puncture marks (defer)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heent-admin" class="form-label">HEENT *</label>
                            <select class="form-control" 
                                    id="physical-heent-admin" 
                                    name="heent" 
                                    required>
                                <option value="" disabled selected hidden>Select observation</option>
                                <option value="Normal">Normal</option>
                                <option value="With congestion">With congestion</option>
                                <option value="With infection">With infection</option>
                                <option value="With abnormal findings">With abnormal findings</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heart-lungs-admin" class="form-label">Heart and Lungs *</label>
                            <select class="form-control" 
                                    id="physical-heart-lungs-admin" 
                                    name="heart_and_lungs" 
                                    required>
                                <option value="" disabled selected hidden>Select observation</option>
                                <option value="Normal">Normal</option>
                                <option value="With wheezing/crackles">With wheezing/crackles</option>
                                <option value="Abnormal">Abnormal</option>
                            </select>
                        </div>
                    </div>
                    <div id="physical-deferral-warning-admin" class="alert alert-warning mt-3" role="alert" style="display:none;"></div>
                </div>
            </div>

            <!-- Step 3: Review and Submit -->
            <div class="modal-body physical-step-content" id="physical-step-3-admin">
                <div class="physical-step-inner">
                    <h4>Step 3: Review & Submit</h4>
                    <p class="text-muted">Please review all information before submitting</p>
                    
                    <div class="physical-review-section">
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="physical-review-card">
                                    <h6 class="physical-review-title">Physical Examination Summary</h6>
                                    <div class="physical-review-group">
                                        <div class="physical-review-subtitle"><i class="fas fa-heartbeat me-2"></i>Vital Signs</div>
                                        <div class="physical-review-item">
                                            <span class="physical-review-label">Blood Pressure</span>
                                            <span class="physical-review-value" id="summary-blood-pressure-admin">-</span>
                                        </div>
                                        <div class="physical-review-item">
                                            <span class="physical-review-label">Pulse Rate</span>
                                            <span class="physical-review-value" id="summary-pulse-rate-admin">-</span>
                                        </div>
                                        <div class="physical-review-item">
                                            <span class="physical-review-label">Body Temperature</span>
                                            <span class="physical-review-value" id="summary-body-temp-admin">-</span>
                                        </div>
                                    </div>
                                    <div class="physical-review-group">
                                        <div class="physical-review-subtitle"><i class="fas fa-user-md me-2"></i>Examination Findings</div>
                                        <div class="physical-review-item">
                                            <span class="physical-review-label">General Appearance</span>
                                            <span class="physical-review-value" id="summary-gen-appearance-admin">-</span>
                                        </div>
                                        <div class="physical-review-item">
                                            <span class="physical-review-label">Skin</span>
                                            <span class="physical-review-value" id="summary-skin-admin">-</span>
                                        </div>
                                        <div class="physical-review-item">
                                            <span class="physical-review-label">HEENT</span>
                                            <span class="physical-review-value" id="summary-heent-admin">-</span>
                                        </div>
                                        <div class="physical-review-item">
                                            <span class="physical-review-label">Heart &amp; Lungs</span>
                                            <span class="physical-review-value" id="summary-heart-lungs-admin">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="physical-review-card">
                                    <h6 class="physical-review-title">Assessment &amp; Next Step</h6>
                                    <div class="physical-review-item">
                                        <span class="physical-review-label">Medical Assessment</span>
                                        <span class="physical-review-value">Accepted for Blood Collection</span>
                                    </div>
                                    <div class="physical-review-item">
                                        <span class="physical-review-label">Next Step</span>
                                        <span class="physical-review-value">Proceed to Blood Collection</span>
                                    </div>
                                </div>
                                <div class="physical-review-card mt-4">
                                    <h6 class="physical-review-title">Physician Details</h6>
                                    <div class="physical-review-item">
                                        <span class="physical-review-label">Physician</span>
                                        <span class="physical-review-value" id="summary-interviewer-admin"><?php echo htmlspecialchars($physical_exam_physician_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="physical-review-item">
                                        <span class="physical-review-label">Examination Date</span>
                                        <span class="physical-review-value"><?php echo date('F j, Y'); ?></span>
                                    </div>
                                    <p class="physical-review-note">
                                        This examination was conducted in accordance with Philippine Red Cross standards and protocols.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Navigation -->
            <div class="modal-footer">
                <div class="physical-nav-buttons w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-danger physical-defer-btn-admin">
                        <i class="fas fa-ban me-2"></i>Defer Donor
                    </button>
                    <button type="button" class="btn btn-outline-danger physical-prev-btn-admin" style="display: none;">
                        <i class="fas fa-arrow-left me-2"></i>Previous
                    </button>
                    <button type="button" class="btn physical-next-btn-admin" style="background-color: #b22222; border-color: #b22222; color: white;">
                        <i class="fas fa-arrow-right me-2"></i>Next
                    </button>
                    <button type="button" class="btn btn-success physical-submit-btn-admin" style="display: none;">
                        <i class="fas fa-check me-2"></i>Submit Physical Examination
                    </button>
                </div>
            </div>
            </form>
        </div>
    </div>
</div>
