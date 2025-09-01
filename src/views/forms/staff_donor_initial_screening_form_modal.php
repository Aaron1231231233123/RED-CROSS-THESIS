<!-- Screening Form Modal -->
<div class="modal fade" id="screeningFormModal" tabindex="-1" aria-labelledby="screeningFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content screening-modal-content">
            <div class="modal-header screening-modal-header">
                <div class="d-flex align-items-center">
                    <div class="screening-modal-icon me-3">
                        <i class="fas fa-clipboard-list fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="screeningFormModalLabel">Initial Screening Form</h5>
                        <small class="text-white-50">To be filled up by the interviewer</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Progress Indicator -->
            <div class="screening-progress-container">
                <div class="screening-progress-steps">
                    <div class="screening-step active" data-step="1">
                        <div class="screening-step-number">1</div>
                        <div class="screening-step-label">Donation Type</div>
                    </div>
                    <div class="screening-step" data-step="2">
                        <div class="screening-step-number">2</div>
                        <div class="screening-step-label">Basic Info</div>
                    </div>
                    <div class="screening-step" data-step="3">
                        <div class="screening-step-number">3</div>
                        <div class="screening-step-label">Review</div>
                    </div>
                </div>
                <div class="screening-progress-line">
                    <div class="screening-progress-fill"></div>
                </div>
            </div>
            
            <div class="modal-body screening-modal-body">
                <form id="screeningForm">
                    <input type="hidden" name="donor_id" value="">
                    
                    <!-- Step 1: Donation Type -->
                    <div class="screening-step-content active" data-step="1">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-heart me-2 text-danger"></i>Type of Donation</h6>
                            <p class="text-muted mb-4">Please select the donor's choice of donation type</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="screening-label">IN-HOUSE</label>
                                <select name="inhouse-donation-type" id="inhouseDonationTypeSelect" class="screening-input">
                                    <option value="">Select Donation Type</option>
                                    <option value="walk-in">Walk-in</option>
                                    <option value="replacement">Replacement</option>
                                    <option value="patient-directed">Patient-Directed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="screening-label">MOBILE</label>
                                <select name="mobile-donation-type" id="mobileDonationTypeSelect" class="screening-input">
                                    <option value="">Select Donation Type</option>
                                    <option value="mobile-walk-in">Walk-in</option>
                                    <option value="mobile-replacement">Replacement</option>
                                    <option value="mobile-patient-directed">Patient-Directed</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Conditional Sections -->
                        <div id="conditionalSections" style="margin-top: 20px;">
                            <!-- Mobile Donation Details -->
                            <div id="mobileDonationSection" class="screening-detail-card" style="display: none;">
                                <h6 class="screening-detail-title">Mobile Donation Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="screening-label">Place</label>
                                        <input type="text" name="mobile-place" class="screening-input" placeholder="Enter location">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="screening-label">Organizer</label>
                                        <input type="text" name="mobile-organizer" class="screening-input" placeholder="Enter organizer">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Patient Information Table -->
                            <div id="patientDetailsSection" class="screening-detail-card" style="display: none;">
                                <h6 class="screening-detail-title">Patient Information</h6>
                                <div class="screening-patient-table-container">
                                    <table class="table table-bordered screening-patient-table">
                                        <thead>
                                            <tr class="table-danger">
                                                <th>Patient Name</th>
                                                <th>Hospital</th>
                                                <th>Blood Type</th>
                                                <th>WB/Component</th>
                                                <th>No. of units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <input type="text" name="patient-name" class="form-control form-control-sm" placeholder="Enter patient name">
                                                </td>
                                                <td>
                                                    <input type="text" name="hospital" class="form-control form-control-sm" placeholder="Enter hospital">
                                                </td>
                                                <td>
                                                    <select name="blood-type-patient" class="form-control form-control-sm">
                                                        <option value="" disabled selected>Select Blood Type</option>
                                                        <option value="A+">A+</option>
                                                        <option value="A-">A-</option>
                                                        <option value="B+">B+</option>
                                                        <option value="B-">B-</option>
                                                        <option value="O+">O+</option>
                                                        <option value="O-">O-</option>
                                                        <option value="AB+">AB+</option>
                                                        <option value="AB-">AB-</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="wb-component" class="form-control form-control-sm" placeholder="Enter component">
                                                </td>
                                                <td>
                                                    <input type="number" name="no-units" class="form-control form-control-sm" placeholder="0" min="0">
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Basic Information -->
                    <div class="screening-step-content" data-step="2">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-info-circle me-2 text-danger"></i>Basic Screening Information</h6>
                            <p class="text-muted mb-4">Please enter the basic screening measurements</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="screening-label">Body Weight</label>
                                <div class="screening-input-group">
                                    <input type="number" step="0.01" name="body-wt" id="bodyWeightInput" class="screening-input" required min="0">
                                    <span class="screening-input-suffix">kg</span>
                                </div>
                                <div id="bodyWeightAlert" class="text-danger mt-1" style="display: none; font-size: 0.875rem;">
                                    ⚠️ Minimum eligible weight is 50 kg. Donation must be deferred for donor safety.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="screening-label">Specific Gravity</label>
                                <div class="screening-input-group">
                                    <input type="number" step="0.1" name="sp-gr" id="specificGravityInput" class="screening-input" required min="0">
                                    <span class="screening-input-suffix">g/dL</span>
                                </div>
                                <div id="specificGravityAlert" class="text-danger mt-1" style="display: none; font-size: 0.875rem;">
                                    ⚠️ Minimum acceptable specific gravity is 12.5 g/dL. Donation must be deferred for donor safety.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="screening-label">Blood Type</label>
                                <select name="blood-type" class="screening-input" required>
                                    <option value="" disabled selected>Select Blood Type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Review -->
                    <div class="screening-step-content" data-step="3">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-check-double me-2 text-danger"></i>Review & Submit</h6>
                            <p class="text-muted mb-4">Please review all information before submission</p>
                        </div>
                        
                        <div class="screening-review-section">
                            <div class="screening-review-card">
                                <h6 class="screening-review-title">Screening Summary</h6>
                                <div class="screening-review-content" id="reviewContent">
                                    <!-- Content will be populated by JavaScript -->
                                </div>
                            </div>
                            
                            <div class="screening-interviewer-info">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6">
                                        <label class="screening-label">Interviewer</label>
                                        <input type="text" name="interviewer" value="<?php echo htmlspecialchars($interviewer_name ?? 'Interviewer'); ?>" class="screening-input" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="screening-label">Office</label>
                                        <input type="text" value="PRC Office" class="screening-input" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="screening-label">Date</label>
                                        <input type="text" value="<?php echo date('m/d/Y'); ?>" class="screening-input" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer screening-modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="screeningCancelButton">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-outline-danger" id="screeningPrevButton" style="display: none;">
                    <i class="fas fa-arrow-left me-2"></i>Previous
                </button>
                <button type="button" class="btn btn-danger" id="screeningNextButton">
                    <i class="fas fa-arrow-right me-2"></i>Next
                </button>
                <button type="button" class="btn btn-success" id="screeningSubmitButton" style="display: none;">
                    <i class="fas fa-check me-2"></i>Submit Screening
                </button>
            </div>
        </div>
    </div>
</div>
