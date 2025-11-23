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
        <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; position: relative;">
            <!-- Navigation Sidebar -->
            <div class="modal-nav-sidebar" id="physicalExamNavSidebar">
                <div class="modal-nav-header">
                    <i class="fas fa-user-md modal-nav-header-icon"></i>
                    <div class="modal-nav-header-text">
                        <div class="modal-nav-header-title">PHYSICIAN</div>
                        <div class="modal-nav-header-subtitle">Workflow</div>
                    </div>
                </div>
                <div class="modal-nav-items">
                    <div class="modal-nav-item" id="navPEMedicalHistory" data-nav="medical-history">
                        <i class="fas fa-file-medical modal-nav-item-icon"></i>
                        <span>Medical History</span>
                    </div>
                    <div class="modal-nav-item" id="navPEInitialScreening" data-nav="initial-screening">
                        <i class="fas fa-clipboard-list modal-nav-item-icon"></i>
                        <span>Initial Screening</span>
                    </div>
                    <div class="modal-nav-item active" id="navPEPhysicalExam" data-nav="physical-examination">
                        <i class="fas fa-stethoscope modal-nav-item-icon"></i>
                        <span>Physical Examination</span>
                    </div>
                    <div class="modal-nav-item" id="navPEDonorProfile" data-nav="donor-profile">
                        <i class="fas fa-user modal-nav-item-icon"></i>
                        <span>Donor Profile</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-content-with-nav" id="physicalExamModalContentWrapper">
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
                            <div class="d-flex align-items-center gap-2">
                                <input type="number" 
                                       class="form-control" 
                                       id="physical-blood-pressure-systolic" 
                                       placeholder="Systolic" 
                                       required
                                       novalidate
                                       style="flex: 1;">
                                <span class="text-muted" style="font-size: 1.2rem; font-weight: bold;">/</span>
                                <input type="number" 
                                       class="form-control" 
                                       id="physical-blood-pressure-diastolic" 
                                       placeholder="Diastolic" 
                                       required
                                       novalidate
                                       style="flex: 1;">
                            </div>
                            <div id="blood-pressure-error" class="text-danger" style="display: none; font-size: 0.875rem; margin-top: 0.25rem;">
                                Blood pressure should be between 90-120 / 60-100
                            </div>
                            <input type="hidden" 
                                   id="physical-blood-pressure" 
                                   name="blood_pressure">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="physical-pulse-rate" class="form-label">Pulse Rate (BPM) *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="physical-pulse-rate" 
                                   name="pulse_rate" 
                                   placeholder="BPM" 
                                   required
                                   novalidate>
                            <div id="pulse-rate-error" class="text-danger" style="display: none; font-size: 0.875rem; margin-top: 0.25rem;">
                                Pulse rate should be between 60-100 BPM
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="physical-body-temp" class="form-label">Body Temperature (°C) *</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="physical-body-temp" 
                                   name="body_temp" 
                                   placeholder="°C" 
                                   step="0.1" 
                                   required
                                   novalidate>
                            <div id="body-temp-error" class="text-danger" style="display: none; font-size: 0.875rem; margin-top: 0.25rem;">
                                Temperature should be between 30-37°C
                            </div>
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
                            <select class="form-control" 
                                    id="physical-gen-appearance" 
                                    name="gen_appearance" 
                                    required>
                                <option value="">Select observation</option>
                                <option value="Appears healthy">Appears healthy</option>
                                <option value="Weak/pale">Weak/pale</option>
                                <option value="Anxious/nervous">Anxious/nervous</option>
                                <option value="Ill-looking">Ill-looking</option>
                                <option value="Deferred for further assessment">Deferred for further assessment (defer)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-skin" class="form-label">Skin *</label>
                            <select class="form-control" 
                                    id="physical-skin" 
                                    name="skin" 
                                    required>
                                <option value="">Select observation</option>
                                <option value="Normal">Normal</option>
                                <option value="With lesion/rash">With lesion/rash</option>
                                <option value="With jaundice">With jaundice</option>
                                <option value="With puncture marks (defer)">With puncture marks (defer)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heent" class="form-label">HEENT *</label>
                            <select class="form-control" 
                                    id="physical-heent" 
                                    name="heent" 
                                    required>
                                <option value="">Select observation</option>
                                <option value="Normal">Normal</option>
                                <option value="With congestion">With congestion</option>
                                <option value="With infection">With infection</option>
                                <option value="With abnormal findings">With abnormal findings</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="physical-heart-lungs" class="form-label">Heart and Lungs *</label>
                            <select class="form-control" 
                                    id="physical-heart-lungs" 
                                    name="heart_and_lungs" 
                                    required>
                                <option value="">Select observation</option>
                                <option value="Normal">Normal</option>
                                <option value="With wheezing/crackles">With wheezing/crackles</option>
                                <option value="Abnormal">Abnormal</option>
                            </select>
                        </div>
                    </div>
                    <div id="physical-deferral-warning" class="alert alert-warning mt-3" role="alert" style="display:none;"></div>
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
</div>

