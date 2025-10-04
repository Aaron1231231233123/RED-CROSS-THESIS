<?php
/**
 * Defer Donor Modal
 * This file contains the HTML structure for the defer donor modal
 * Used in the physical examination dashboard
 */
?>

<!-- Defer Donor Modal -->
<div class="modal fade" id="deferDonorModal" tabindex="-1" aria-labelledby="deferDonorModalLabel" aria-hidden="true" style="z-index: 10050;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="deferDonorModalLabel">
                    <i class="fas fa-ban me-2"></i>
                    Defer Donor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <form id="deferDonorForm">
                    <input type="hidden" id="defer-donor-id" name="donor_id">
                    <input type="hidden" id="defer-screening-id" name="screening_id">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Specify reason for deferral and duration.</label>
                    </div>

                    <!-- Deferral Type Selection -->
                    <div class="mb-4">
                        <label for="deferralTypeSelect" class="form-label fw-semibold">
                            <i class="fas fa-list-ul me-2 text-primary"></i>Deferral Type *
                        </label>
                        <select class="form-select" id="deferralTypeSelect" name="deferral_type" required>
                            <option value="Temporary Deferral" selected>
                                <i class="fas fa-clock me-2"></i>Temporary Deferral - Donor can donate after specified period
                            </option>
                        </select>
                        <div class="form-text">Temporary deferral is pre-selected for initial screening</div>
                    </div>

                    <!-- Duration Selection (only for Temporary Deferral) -->
                    <div class="mb-4 duration-container" id="durationSection" style="display: block;">
                        <label class="form-label fw-semibold mb-3">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>Deferral Duration *
                        </label>
                        
                        <!-- Quick Duration Options -->
                        <div class="duration-quick-options mb-3">
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="duration-option active" data-days="2">
                                        <div class="duration-number">2</div>
                                        <div class="duration-unit">Days</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="7">
                                        <div class="duration-number">7</div>
                                        <div class="duration-unit">Days</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="14">
                                        <div class="duration-number">14</div>
                                        <div class="duration-unit">Days</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="30">
                                        <div class="duration-number">1</div>
                                        <div class="duration-unit">Month</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="90">
                                        <div class="duration-number">3</div>
                                        <div class="duration-unit">Months</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="180">
                                        <div class="duration-number">6</div>
                                        <div class="duration-unit">Months</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="365">
                                        <div class="duration-number">1</div>
                                        <div class="duration-unit">Year</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option custom-option" data-days="custom">
                                        <div class="duration-number"><i class="fas fa-edit"></i></div>
                                        <div class="duration-unit">Custom</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden select for form submission -->
                        <select class="form-select d-none" id="deferralDuration" name="duration">
                            <option value="2" selected>2 days (Day after tomorrow)</option>
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="21">21 days</option>
                            <option value="30">1 month (30 days)</option>
                            <option value="60">2 months (60 days)</option>
                            <option value="90">3 months (90 days)</option>
                            <option value="180">6 months (180 days)</option>
                            <option value="365">1 year (365 days)</option>
                            <option value="custom">Custom duration...</option>
                        </select>
                    </div>

                    <!-- Custom Duration Input -->
                    <div class="mb-4 custom-duration-container" id="customDurationSection" style="display: none;">
                        <label for="customDuration" class="form-label fw-semibold">
                            <i class="fas fa-keyboard me-2 text-primary"></i>Custom Duration (days) *
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="customDuration" name="custom_duration" min="1" max="3650" placeholder="Enter number of days">
                            <span class="input-group-text">days</span>
                        </div>
                        <div class="form-text">Enter duration between 1 and 3650 days (approximately 10 years)</div>
                    </div>

                    <!-- Disapproval Reason -->
                    <div class="mb-4">
                        <label for="disapprovalReason" class="form-label fw-semibold">Disapproval Reason *</label>
                        <select class="form-select" id="disapprovalReason" name="disapproval_reason" required>
                            <option value="">Select reason for deferral...</option>
                            <option value="Weight out of acceptable range">Weight out of acceptable range</option>
                            <option value="Low Hemoglobin">Low Hemoglobin</option>
                            <option value="Both">Both</option>
                        </select>
                        <div class="form-text">Please select the appropriate reason for deferral.</div>
                    </div>

                    <!-- Duration Summary Display -->
                    <div class="alert alert-info" id="durationSummary" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Deferral Summary:</strong> <span id="summaryText"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;" id="submitDeferral" disabled>
                    <i class="fas fa-ban me-2"></i>Submit Deferral
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Deferral Confirmation Modal -->
<div class="modal fade" id="deferralConfirmedModal" tabindex="-1" aria-labelledby="deferralConfirmedModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 99999 !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important;">
    <div class="modal-dialog modal-dialog-centered" style="z-index: 100000 !important; position: relative !important;">
        <div class="modal-content" style="border-radius: 15px; border: none; z-index: 100001 !important; position: relative !important;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="deferralConfirmedModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Deferral Recorded Successfully
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4 text-center">
                <div class="mb-4">
                    <div class="success-icon mb-3">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745;"></i>
                    </div>
                    <h4 class="text-success mb-3">Deferral Successfully Recorded!</h4>
                    <p class="text-muted mb-0">The donor has been marked as deferred and all relevant data has been updated in the system.</p>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Continue
                </button>
            </div>
        </div>
    </div>
</div>
