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
                        <div class="screening-step-label">Basic Info</div>
                    </div>
                    <div class="screening-step" data-step="2">
                        <div class="screening-step-number">2</div>
                        <div class="screening-step-label">Donation Type</div>
                    </div>
                    <div class="screening-step" data-step="3">
                        <div class="screening-step-number">3</div>
                        <div class="screening-step-label">Details</div>
                    </div>
                    <div class="screening-step" data-step="4">
                        <div class="screening-step-number">4</div>
                        <div class="screening-step-label">History</div>
                    </div>
                    <div class="screening-step" data-step="5">
                        <div class="screening-step-number">5</div>
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
                    
                    <!-- Step 1: Basic Information -->
                    <div class="screening-step-content active" data-step="1">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-info-circle me-2 text-danger"></i>Basic Screening Information</h6>
                            <p class="text-muted mb-4">Please enter the basic screening measurements</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="screening-label">Body Weight (kg)</label>
                                <div class="screening-input-group">
                                    <input type="number" step="0.01" name="body-wt" class="screening-input" required>
                                    <span class="screening-input-suffix">kg</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="screening-label">Specific Gravity</label>
                                <input type="text" name="sp-gr" class="screening-input" required>
                            </div>
                            <div class="col-md-4">
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

                    <!-- Step 2: Donation Type -->
                    <div class="screening-step-content" data-step="2">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-heart me-2 text-danger"></i>Type of Donation</h6>
                            <p class="text-muted mb-4">Please select the donor's choice of donation type</p>
                        </div>
                        
                        <div class="screening-donation-categories">
                            <div class="screening-category-card">
                                <h6 class="screening-category-title">IN-HOUSE</h6>
                                <div class="screening-donation-options">
                                    <label class="screening-donation-option">
                                        <input type="radio" name="donation-type" value="walk-in" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">Walk-in/Voluntary</span>
                                    </label>
                                    <label class="screening-donation-option">
                                        <input type="radio" name="donation-type" value="replacement" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">Replacement</span>
                                    </label>
                                    <label class="screening-donation-option">
                                        <input type="radio" name="donation-type" value="patient-directed" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">Patient-Directed</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="screening-category-card">
                                <h6 class="screening-category-title">MOBILE BLOOD DONATION</h6>
                                <div class="screening-donation-options">
                                    <label class="screening-donation-option">
                                        <input type="radio" name="donation-type" value="mobile-walk-in" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">Walk-in/Voluntary</span>
                                    </label>
                                    <label class="screening-donation-option">
                                        <input type="radio" name="donation-type" value="mobile-replacement" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">Replacement</span>
                                    </label>
                                    <label class="screening-donation-option">
                                        <input type="radio" name="donation-type" value="mobile-patient-directed" required>
                                        <span class="screening-radio-custom"></span>
                                        <span class="screening-option-text">Patient-Directed</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Additional Details -->
                    <div class="screening-step-content" data-step="3">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-edit me-2 text-danger"></i>Additional Details</h6>
                            <p class="text-muted mb-4">Additional information based on donation type</p>
                        </div>
                        
                        <!-- Mobile Location Fields (shown for any mobile donation type) -->
                        <div class="screening-mobile-section" id="mobileDonationSection" style="display: none;">
                            <div class="screening-detail-card">
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
                        </div>
                        
                        <!-- Patient Details Table (shown for patient-directed donations) -->
                        <div class="screening-patient-section" id="patientDetailsSection" style="display: none;">
                            <div class="screening-detail-card">
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
                        
                        <!-- No Additional Details Message (shown for walk-in/replacement) -->
                        <div class="screening-no-details" id="noAdditionalDetails">
                            <div class="screening-detail-card text-center">
                                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                <h6>No Additional Details Required</h6>
                                <p class="text-muted mb-0">This donation type doesn't require additional information.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Donation History -->
                    <div class="screening-step-content" data-step="4">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-history me-2 text-danger"></i>Donation History</h6>
                            <p class="text-muted mb-4">Previous donation information (Donor's Opinion)</p>
                        </div>
                        
                        <div class="screening-history-question">
                            <label class="screening-label mb-3">Has the donor donated blood before?</label>
                            <div class="screening-radio-group">
                                <label class="screening-radio-option">
                                    <input type="radio" name="history" value="yes" required>
                                    <span class="screening-radio-custom"></span>
                                    <span class="screening-option-text">Yes</span>
                                </label>
                                <label class="screening-radio-option">
                                    <input type="radio" name="history" value="no" required>
                                    <span class="screening-radio-custom"></span>
                                    <span class="screening-option-text">No</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="screening-history-details" id="historyDetails" style="display: none;">
                            <div class="screening-detail-card">
                                <h6 class="screening-detail-title">Donation History Details</h6>
                                <div class="screening-history-table">
                                    <div class="screening-history-grid">
                                        <!-- Header Row -->
                                        <div class="screening-history-header">
                                            <div class="screening-history-label"></div>
                                            <div class="screening-history-column">
                                                <span class="screening-history-header-text">Red Cross</span>
                                            </div>
                                            <div class="screening-history-column">
                                                <span class="screening-history-header-text">Hospital</span>
                                            </div>
                                        </div>
                                        
                                        <!-- No. of times Row -->
                                        <div class="screening-history-row">
                                            <div class="screening-history-label">
                                                <span>No. of times</span>
                                            </div>
                                            <div class="screening-history-column">
                                                <input type="number" name="red-cross" min="0" value="0" class="screening-input screening-history-input" readonly>
                                            </div>
                                            <div class="screening-history-column">
                                                <input type="number" name="hospital-history" min="0" value="0" class="screening-input screening-history-input" readonly>
                                            </div>
                                        </div>
                                        
                                        <!-- Date of last donation Row -->
                                        <div class="screening-history-row">
                                            <div class="screening-history-label">
                                                <span>Date of last donation</span>
                                            </div>
                                            <div class="screening-history-column">
                                                <div class="screening-input-group">
                                                    <input type="date" name="last-rc-donation-date" class="screening-input screening-history-input">
                                                    <span class="screening-input-icon">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="screening-history-column">
                                                <div class="screening-input-group">
                                                    <input type="date" name="last-hosp-donation-date" class="screening-input screening-history-input">
                                                    <span class="screening-input-icon">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Place of last donation Row -->
                                        <div class="screening-history-row">
                                            <div class="screening-history-label">
                                                <span>Place of last donation</span>
                                            </div>
                                            <div class="screening-history-column">
                                                <input type="text" name="last-rc-donation-place" class="screening-input screening-history-input" placeholder="Enter location">
                                            </div>
                                            <div class="screening-history-column">
                                                <input type="text" name="last-hosp-donation-place" class="screening-input screening-history-input" placeholder="Enter location">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Review -->
                    <div class="screening-step-content" data-step="5">
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
