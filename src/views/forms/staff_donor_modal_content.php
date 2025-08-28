<!-- Donor Details Modal -->
<div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content modern-modal">
            <div class="modal-header modern-header">
                <div class="d-flex align-items-center">
                    <div class="donor-avatar me-3">
                        <i class="fas fa-user-circle fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="donorDetailsModalLabel">Donor Information</h5>
                        <small class="text-white-50">Complete donor profile and submission details</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body modern-body">
                <!-- Donor Header Section -->
                <div class="donor-header-section mb-4">
                    <div class="donor-header-card">
                        <div class="donor-header-content">
                            <div class="donor-name-section">
                                <h3 class="donor-name" name="donor_name">-</h3>
                                <div class="donor-badges">
                                    <span class="donor-badge age-badge" name="age_badge">-</span>
                                    <span class="donor-badge gender-badge" name="gender_badge">-</span>
                                    <span class="donor-badge blood-badge" name="blood_badge">-</span>
                                </div>
                            </div>
                            <div class="donor-date-section">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <span>Date Screened: <span name="screening_date">-</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="donor-content-grid">
                    <!-- Left Column -->
                    <div class="donor-content-column">
                        <!-- Screening Results -->
                        <div class="donor-section-card">
                            <div class="donor-section-header">
                                <i class="fas fa-clipboard-check me-2"></i>
                                <span>Screening Results</span>
                            </div>
                            <div class="donor-field-list">
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Donation Type</span>
                                    <span class="donor-field-value" name="donation_type">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Body Weight</span>
                                    <span class="donor-field-value" name="body_weight">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Specific Gravity</span>
                                    <span class="donor-field-value" name="specific_gravity">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Blood Type</span>
                                    <span class="donor-field-value" name="blood_type">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="donor-content-column">
                        <!-- Contact & Background -->
                        <div class="donor-section-card">
                            <div class="donor-section-header">
                                <i class="fas fa-user me-2"></i>
                                <span>Contact & Background</span>
                            </div>
                            <div class="donor-field-list">
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Civil Status</span>
                                    <span class="donor-field-value" name="civil_status">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Mobile</span>
                                    <span class="donor-field-value" name="mobile">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Occupation</span>
                                    <span class="donor-field-value" name="occupation">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Nationality</span>
                                    <span class="donor-field-value" name="nationality">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Religion</span>
                                    <span class="donor-field-value" name="religion">-</span>
                                </div>
                                <div class="donor-field-item">
                                    <span class="donor-field-label">Education</span>
                                    <span class="donor-field-value" name="education">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Full Width Address Section -->
                <div class="donor-section-card mt-4">
                    <div class="donor-section-header">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <span>Address</span>
                    </div>
                    <div class="donor-address-field">
                        <span class="donor-field-value" name="permanent_address">-</span>
                    </div>
                </div>

                <!-- Additional Information (Hidden by default, can be expanded) -->
                <div class="donor-additional-info mt-4" id="additionalInfo" style="display: none;">
                    <div class="donor-section-card">
                        <div class="donor-section-header">
                            <i class="fas fa-info-circle me-2"></i>
                            <span>Additional Information</span>
                        </div>
                        <div class="donor-content-grid">
                            <div class="donor-content-column">
                                <div class="donor-field-list">
                                    <div class="donor-field-item">
                                        <span class="donor-field-label">Birth Date</span>
                                        <span class="donor-field-value" name="birthdate">-</span>
                                    </div>
                                    <div class="donor-field-item">
                                        <span class="donor-field-label">Office Address</span>
                                        <span class="donor-field-value" name="office_address">-</span>
                                    </div>
                                    <div class="donor-field-item">
                                        <span class="donor-field-label">Email Address</span>
                                        <span class="donor-field-value" name="email">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expand/Collapse Button -->
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleAdditionalInfo">
                        <i class="fas fa-chevron-down me-1"></i>
                        Show More Details
                    </button>
                </div>

            </div>
            <div class="modal-footer modern-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-success px-4" id="Approve">
                    <i class="fas fa-check me-2"></i>Approve Donor
                </button>
            </div>
        </div>
    </div>
</div>
