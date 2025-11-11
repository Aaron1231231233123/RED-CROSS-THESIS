<?php
/**
 * Physical Examination Modal - Admin Version
 * This file contains the HTML structure for the physical examination modal
 * Used specifically for admin workflows
 */
?>

<!-- Physical Examination Modal - Admin -->
<div class="modal fade" id="physicalExaminationModalAdmin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width:1100px; width:95%;">
        <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-bottom: none;">
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
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-gen-appearance-admin" 
                                   name="gen_appearance" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-skin-admin" class="form-label">Skin *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-skin-admin" 
                                   name="skin" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heent-admin" class="form-label">HEENT *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-heent-admin" 
                                   name="heent" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heart-lungs-admin" class="form-label">Heart and Lungs *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="physical-heart-lungs-admin" 
                                   name="heart_and_lungs" 
                                   placeholder="Enter observation" 
                                   required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Review and Submit -->
            <div class="modal-body physical-step-content" id="physical-step-3-admin">
                <div class="physical-step-inner">
                    <h4>Step 3: Review & Submit</h4>
                    <p class="text-muted">Please review all information before submitting</p>
                    
                    <div class="examination-report">
                        <!-- Header Section -->
                        <div class="report-header">
                            <div class="report-title">
                                <h5>Physical Examination Report - Admin</h5>
                                <div class="report-meta">
                                    <span class="report-date"><?php echo date('F j, Y'); ?></span>
                                    <span class="report-physician">Physician: <span id="summary-interviewer-admin">-</span></span>
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
                                        <span class="vital-value" id="summary-blood-pressure-admin">-</span>
                                    </div>
                                    <div class="vital-item">
                                        <span class="vital-label">Pulse Rate:</span>
                                        <span class="vital-value" id="summary-pulse-rate-admin">-</span>
                                        <span class="vital-unit">BPM</span>
                                    </div>
                                    <div class="vital-item">
                                        <span class="vital-label">Temperature:</span>
                                        <span class="vital-value" id="summary-body-temp-admin">-</span>
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
                                        <span class="finding-value" id="summary-gen-appearance-admin">-</span>
                                    </div>
                                    <div class="finding-row">
                                        <span class="finding-label">Skin:</span>
                                        <span class="finding-value" id="summary-skin-admin">-</span>
                                    </div>
                                    <div class="finding-row">
                                        <span class="finding-label">HEENT:</span>
                                        <span class="finding-value" id="summary-heent-admin">-</span>
                                    </div>
                                    <div class="finding-row">
                                        <span class="finding-label">Heart and Lungs:</span>
                                        <span class="finding-value" id="summary-heart-lungs-admin">-</span>
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
                                        <span class="collection-label">Next Step:</span>
                                        <span class="collection-value">Proceed to Blood Collection</span>
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
