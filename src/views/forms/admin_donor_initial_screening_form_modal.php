<!-- Admin Screening Form Modal -->
<div class="modal fade" id="adminScreeningFormModal" tabindex="-1" aria-labelledby="adminScreeningFormModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg" style="max-width: 800px; margin: 1.75rem auto;">
        <div class="modal-content admin-screening-modal-content" style="position: relative; z-index: 1070; pointer-events: auto;">
            <div class="modal-header admin-screening-modal-header">
                <div class="d-flex align-items-center">
                    <div class="admin-screening-modal-icon me-3">
                        <i class="fas fa-clipboard-list fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="adminScreeningFormModalLabel">Initial Screening Form</h5>
                        <small class="text-white-50">To be filled up by the interviewer</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Progress Indicator -->
            <div class="admin-screening-progress-container">
                <div class="admin-screening-progress-steps">
                    <div class="admin-screening-step active" data-step="1">
                        <div class="admin-screening-step-number">1</div>
                        <div class="admin-screening-step-label">Donation Type</div>
                    </div>
                    <div class="admin-screening-step" data-step="2">
                        <div class="admin-screening-step-number">2</div>
                        <div class="admin-screening-step-label">Basic Info</div>
                    </div>
                    <div class="admin-screening-step" data-step="3">
                        <div class="admin-screening-step-number">3</div>
                        <div class="admin-screening-step-label">Review</div>
                    </div>
                </div>
                <div class="admin-screening-progress-line">
                    <div class="admin-screening-progress-fill"></div>
                </div>
            </div>
            
            <div class="modal-body admin-screening-modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                <form id="adminScreeningForm">
                    <input type="hidden" name="donor_id" value="">
                    
                    <!-- Step 1: Donation Type -->
                    <div class="admin-screening-step-content active" data-step="1">
                        <div class="admin-screening-step-title">
                            <h6><i class="fas fa-heart me-2 text-danger"></i>Type of Donation</h6>
                            <p class="text-muted mb-4">Please select the donor's choice of donation type</p>
                        </div>
                        
                        <!-- IN-HOUSE Section -->
                        <div class="admin-screening-detail-card">
                            <div class="admin-screening-category-title">IN-HOUSE</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="admin-screening-label d-block">Donation Type</label>
                                    <div class="form-check">
                                        <input type="radio"
                                            class="form-check-input"
                                            name="donation-type"
                                            id="adminDonationTypeWalkIn"
                                            value="Walk-in"
                                            required>
                                        <label class="form-check-label" for="adminDonationTypeWalkIn">Walk-in</label>
                                    </div>
                                    <small class="text-muted">Select walk-in for in-house donations.</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- MOBILE BLOOD DONATION Section (always visible) -->
                        <div class="admin-screening-detail-card admin-mobile-donation-section">
                            <div class="admin-screening-category-title">MOBILE BLOOD DONATION</div>
                            <h6 class="admin-screening-section-subtitle">Mobile Donation Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="admin-screening-label">Place</label>
                                    <input type="text" name="mobile-place" id="adminMobilePlaceInput" class="admin-screening-input" placeholder="Enter location">
                                </div>
                                <div class="col-md-6">
                                    <label class="admin-screening-label">Organizer</label>
                                    <input type="text" name="mobile-organizer" id="adminMobileOrganizerInput" class="admin-screening-input" placeholder="Enter organizer">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Basic Information -->
                    <div class="admin-screening-step-content" data-step="2">
                        <div class="admin-screening-step-title">
                            <h6><i class="fas fa-info-circle me-2 text-danger"></i>Basic Screening Information</h6>
                            <p class="text-muted mb-4">Please enter the basic screening measurements</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="admin-screening-label">Body Weight</label>
                                <div class="admin-screening-input-group">
                                    <input type="number" step="0.01" name="body-wt" id="adminBodyWeightInput" class="admin-screening-input" required min="0">
                                    <span class="admin-screening-input-suffix">kg</span>
                                </div>
                                <div id="adminBodyWeightAlert" class="text-danger mt-1" style="display: none; font-size: 0.875rem;">
                                    ⚠️ Body weight must be between 50-120 kg for donor safety.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="admin-screening-label">Specific Gravity</label>
                                <div class="admin-screening-input-group">
                                    <input type="number" step="0.1" name="sp-gr" id="adminSpecificGravityInput" class="admin-screening-input" required min="0">
                                    <span class="admin-screening-input-suffix">g/dL</span>
                                </div>
                                <div id="adminSpecificGravityAlert" class="text-danger mt-1" style="display: none; font-size: 0.875rem;">
                                    ⚠️ Specific gravity should be between 12.5-18.0 g/dL for donor safety. Values outside this range require deferral.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="admin-screening-label">Blood Type</label>
                                <select name="blood-type" class="admin-screening-input" required>
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
                    <div class="admin-screening-step-content" data-step="3">
                        <div class="admin-screening-step-title">
                            <h6><i class="fas fa-check-double me-2 text-danger"></i>Review & Submit</h6>
                            <p class="text-muted mb-4">Please review all information before submission</p>
                        </div>
                        
                        <div class="admin-screening-review-section">
                            <div class="admin-screening-review-card">
                                <h6 class="admin-screening-review-title">Screening Summary</h6>
                                <div class="admin-screening-review-content" id="adminReviewContent">
                                    <!-- Content will be populated by JavaScript -->
                                </div>
                            </div>
                            
                            <div class="admin-screening-interviewer-info">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-6">
                                        <label class="admin-screening-label">Interviewer</label>
                                        <input type="text" name="interviewer" value="<?php echo htmlspecialchars($interviewer_name ?? 'Interviewer'); ?>" class="admin-screening-input" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="admin-screening-label">Office</label>
                                        <input type="text" value="PRC Office" class="admin-screening-input" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="admin-screening-label">Date</label>
                                        <input type="text" value="<?php echo date('m/d/Y'); ?>" class="admin-screening-input" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer admin-screening-modal-footer">
                <!-- Left side - Cancel button (removed) -->
                <div class="admin-screening-footer-left" style="display:none;">
                    <button type="button" class="btn btn-outline-secondary" id="adminScreeningCancelButton" style="display:none;" aria-hidden="true" tabindex="-1"></button>
                </div>
                
                <!-- Right side - Action buttons -->
                <div class="admin-screening-footer-right">
                    <button type="button" class="btn btn-outline-danger" id="adminScreeningPrevButton" style="display: none;">
                        <i class="fas fa-arrow-left me-1"></i>Previous
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="adminScreeningDeferButton" style="display: none;">
                        <i class="fas fa-ban me-1"></i>Defer Donor
                    </button>
                    <button type="button" class="btn btn-danger" id="adminScreeningNextButton">
                        <i class="fas fa-arrow-right me-1"></i>Next
                    </button>
                    <button type="button" class="btn btn-success" id="adminScreeningSubmitButton" style="display: none;">
                        <i class="fas fa-check me-1"></i>Submit Screening
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
