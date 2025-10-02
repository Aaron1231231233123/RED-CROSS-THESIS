<?php
/**
 * Physical Examination Modal
 * This file contains the HTML structure for the physical examination modal
 * Used in the physical examination dashboard
 */
?>

<!-- Physical Examination Modal -->
<div class="modal fade" id="physicalExaminationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width:1100px; width:95%;">
        <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-bottom: none;">
                <h5 class="modal-title"><i class="fas fa-stethoscope me-2"></i>Physical Examination Form</h5>
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
                <!-- Blood Bag step is kept in code but hidden for physician flow -->

                <!-- Review is now visual Stage 3 -->
                <div class="physical-step" data-step="3">
                    <div class="physical-step-number">3</div>
                    <div class="physical-step-label">Review</div>
                </div>
            </div>
                <div class="physical-progress-line">
                    <div class="physical-progress-fill"></div>
                </div>
            </div>

            <form id="physicalExaminationForm" class="physical-modal-form">
            <input type="hidden" id="physical-donor-id" name="donor_id">
            <input type="hidden" id="physical-screening-id" name="screening_id">

            <!-- Step 1: Vital Signs -->
            <div class="modal-body physical-step-content active" id="physical-step-1">
                <div class="physical-step-inner">
                    <h4>Step 1: Vital Signs</h4>
                    <p class="text-muted">Please enter the patient's vital signs</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="physical-blood-pressure" class="form-label">Blood Pressure *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-blood-pressure" 
                                   name="blood_pressure" 
                                   placeholder="e.g., 120/80" 
                                   pattern="[0-9]{2,3}/[0-9]{2,3}" 
                                   title="Format: systolic/diastolic e.g. 120/80" 
                                   required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="physical-pulse-rate" class="form-label">Pulse Rate (BPM) *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="physical-pulse-rate" 
                                   name="pulse_rate" 
                                   placeholder="BPM" 
                                   min="40" 
                                   max="200" 
                                   required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="physical-body-temp" class="form-label">Body Temperature (°C) *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="physical-body-temp" 
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
            <div class="modal-body physical-step-content" id="physical-step-2">
                <div class="physical-step-inner">
                    <h4>Step 2: Physical Examination</h4>
                    <p class="text-muted">Please enter examination findings</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="physical-gen-appearance" class="form-label">General Appearance *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-gen-appearance" 
                                   name="gen_appearance" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-skin" class="form-label">Skin *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-skin" 
                                   name="skin" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heent" class="form-label">HEENT *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-heent" 
                                   name="heent" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heart-lungs" class="form-label">Heart and Lungs *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-heart-lungs" 
                                   name="heart_and_lungs" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Blood Bag Selection (kept for future use; hidden in physician flow) -->
            <div class="modal-body physical-step-content" id="physical-step-3" style="display:none;">
                <div class="physical-step-inner">
                    <h4>Step 3: Blood Bag Selection</h4>
                    <p class="text-muted">Please select the appropriate blood bag type</p>
                    
                    <div class="physical-blood-bag-section">
                        <div class="physical-blood-bag-options">
                            <label class="physical-option-card">
                                <input type="radio" name="blood_bag_type" value="Single">
                                <div class="physical-option-content">
                                    <i class="fas fa-square"></i>
                                    <span>Single</span>
                                </div>
                            </label>
                            <label class="physical-option-card">
                                <input type="radio" name="blood_bag_type" value="Multiple">
                                <div class="physical-option-content">
                                    <i class="fas fa-th"></i>
                                    <span>Multiple</span>
                                </div>
                            </label>
                            <label class="physical-option-card">
                                <input type="radio" name="blood_bag_type" value="Top & Bottom">
                                <div class="physical-option-content">
                                    <i class="fas fa-align-justify"></i>
                                    <span>Top & Bottom</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Review and Submit (visual Stage 3) -->
            <div class="modal-body physical-step-content" id="physical-step-4">
                <div class="physical-step-inner">
                    <h4>Step 4: Review & Submit</h4>
                    <p class="text-muted">Please review all information before submitting</p>
                    
                    <div class="examination-report">
                        <!-- Header Section -->
                        <div class="report-header">
                            <div class="report-title">
                                <h5>Physical Examination Report</h5>
                                <div class="report-meta">
                                    <span class="report-date"><?php echo date('F j, Y'); ?></span>
                                    <span class="report-physician">Physician: <span id="summary-interviewer">-</span></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Vital Signs Section -->
                        <div class="report-section">
                            <div class="section-header">
                                <i class="fas fa-heartbeat"></i>
                                <span>Vital Signs</span>
                            </div>
                            <div class="section-content">
                                <div class="vital-signs-grid">
                                    <div class="vital-item">
                                        <span class="vital-label">Blood Pressure:</span>
                                        <span class="vital-value" id="summary-blood-pressure">-</span>
                                    </div>
                                    <div class="vital-item">
                                        <span class="vital-label">Pulse Rate:</span>
                                        <span class="vital-value" id="summary-pulse-rate">-</span>
                                        <span class="vital-unit">BPM</span>
                                    </div>
                                    <div class="vital-item">
                                        <span class="vital-label">Temperature:</span>
                                        <span class="vital-value" id="summary-body-temp">-</span>
                                        <span class="vital-unit">°C</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Physical Examination Section -->
                        <div class="report-section">
                            <div class="section-header">
                                <i class="fas fa-user-md"></i>
                                <span>Physical Examination Findings</span>
                            </div>
                            <div class="section-content">
                                <div class="examination-findings">
                                    <div class="finding-row">
                                        <span class="finding-label">General Appearance:</span>
                                        <span class="finding-value" id="summary-gen-appearance">-</span>
                                    </div>
                                    <div class="finding-row">
                                        <span class="finding-label">Skin:</span>
                                        <span class="finding-value" id="summary-skin">-</span>
                                    </div>
                                    <div class="finding-row">
                                        <span class="finding-label">HEENT:</span>
                                        <span class="finding-value" id="summary-heent">-</span>
                                    </div>
                                    <div class="finding-row">
                                        <span class="finding-label">Heart and Lungs:</span>
                                        <span class="finding-value" id="summary-heart-lungs">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assessment & Conclusion -->
                        <div class="report-section">
                            <div class="section-header">
                                <i class="fas fa-clipboard-check"></i>
                                <span>Assessment & Conclusion</span>
                            </div>
                            <div class="section-content">
                                <div class="assessment-content">
                                    <div class="assessment-result">
                                        <span class="result-label">Medical Assessment:</span>
                                        <span class="result-value">Accepted for Blood Collection</span>
                                    </div>
                                    <div class="assessment-collection">
                                        <span class="collection-label">Blood Collection:</span>
                                        <span class="collection-value" id="summary-blood-bag">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Signature Section -->
                        <div class="report-signature">
                            <div class="signature-content">
                                <div class="signature-line">
                                    <span>Examining Physician</span>
                                    <span class="physician-name"><?php 
                                        // Get the logged-in user's name from the users table
                                        if (isset($_SESSION['user_id'])) {
                                            $user_id = $_SESSION['user_id'];
                                            
                                            // Use the unified API function if available, otherwise fallback to direct CURL
                                            if (function_exists('makeSupabaseApiCall')) {
                                                $user_data = makeSupabaseApiCall(
                                                    'users',
                                                    ['first_name', 'surname'],
                                                    ['user_id' => 'eq.' . $user_id]
                                                );
                                            } else {
                                                // Fallback to direct CURL if unified function not available
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
                                            
                                            if (!empty($user_data) && is_array($user_data)) {
                                                $user = $user_data[0];
                                                $first_name = isset($user['first_name']) ? htmlspecialchars($user['first_name']) : '';
                                                $surname = isset($user['surname']) ? htmlspecialchars($user['surname']) : '';
                                                
                                                if ($first_name && $surname) {
                                                    echo $first_name . ' ' . $surname;
                                                } elseif ($first_name) {
                                                    echo $first_name;
                                                } elseif ($surname) {
                                                    echo $surname;
                                                } else {
                                                    echo 'Physician';
                                                }
                                            } else {
                                                echo 'Physician';
                                            }
                                        } else {
                                            echo 'Physician';
                                        }
                                    ?></span>
                                </div>
                                <div class="signature-note">
                                    This examination was conducted in accordance with Philippine Red Cross standards and protocols.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Navigation -->
            <div class="modal-footer">
                <div class="physical-nav-buttons w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-danger physical-defer-btn">
                        <i class="fas fa-ban me-2"></i>Defer Donor
                    </button>
                    <button type="button" class="btn btn-outline-danger physical-prev-btn" style="display: none;">
                        <i class="fas fa-arrow-left me-2"></i>Previous
                    </button>
                    <button type="button" class="btn physical-next-btn" style="background-color: #b22222; border-color: #b22222; color: white;">
                        <i class="fas fa-arrow-right me-2"></i>Next
                    </button>
                    <button type="button" class="btn btn-success physical-submit-btn" style="display: none;">
                        <i class="fas fa-check me-2"></i>Submit Physical Examination
                    </button>
                </div>
            </div>
            </form>
        </div>
    </div>
</div>

