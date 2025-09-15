<?php
/**
 * Medical History Approval Modals
 * This file contains the HTML structure for medical history approval and decline modals
 * Used in the medical history review process
 */
?>

<!-- Medical History Approval Modal -->
<div class="modal fade" id="medicalHistoryApprovalModal" tabindex="-1" aria-labelledby="medicalHistoryApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryApprovalModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Medical History Approved
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-success mb-3">Approval Successful!</h5>
                    <p class="text-muted mb-0">The donor's medical history has been approved.</p>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Continue
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Decline Modal -->
<div class="modal fade" id="medicalHistoryDeclineModal" tabindex="-1" aria-labelledby="medicalHistoryDeclineModalLabel" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclineModalLabel">
                    <i class="fas fa-times-circle me-2"></i>
                    Decline Medical History?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="mb-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will mark the donor as ineligible for donation.
                    </div>
                    <p class="mb-3">Are you sure you want to decline this donor's medical history?</p>
                    <p class="text-muted small">The donor will be marked as ineligible.</p>
                </div>
                
                <form id="declineMedicalHistoryForm">
                    <div class="mb-3">
                        <label for="declineReason" class="form-label fw-semibold">Reason for declinement: *</label>
                        <textarea class="form-control" id="declineReason" name="decline_reason" rows="4" 
                                placeholder="Please provide detailed reason for declining this donor's medical history..." 
                                minlength="10" maxlength="200" required></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="form-text">Please provide a clear and specific reason for the declinement.</div>
                            <small id="charCount" class="text-muted">0/200 characters</small>
                        </div>
                        <div id="declineReasonError" class="invalid-feedback" style="display: none;">
                            Please provide at least 10 characters for the decline reason.
                        </div>
                        <div id="declineReasonSuccess" class="valid-feedback" style="display: none;">
                            ✓ Reason provided successfully
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="donationRestrictionDate" class="form-label fw-semibold">When can they donate again? *</label>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Defer Type</label>
                                <select class="form-select" id="restrictionType" name="restriction_type" required>
                                    <option value="">Select defer type...</option>
                                    <option value="temporary">Temporary Defer</option>
                                    <option value="permanent">Permanent Defer</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="dateSelectionSection" style="display: none;">
                                <label class="form-label small text-muted">Defer End Date</label>
                                <input type="date" class="form-control" id="donationRestrictionDate" name="donation_restriction_date" 
                                       min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+10 years')); ?>">
                                <div class="form-text">Select when the donor can donate again</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="submitDeclineBtn" disabled>
                    <i class="fas fa-ban me-2"></i>Submit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Declined Confirmation Modal -->
<div class="modal fade" id="medicalHistoryDeclinedModal" tabindex="-1" aria-labelledby="medicalHistoryDeclinedModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclinedModalLabel">
                    <i class="fas fa-ban me-2"></i>
                    Medical History Declined
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-ban text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-danger mb-3">Declinement Recorded</h5>
                    <p class="text-muted mb-0">Donor's medical history has been declined.</p>
                    <p class="text-muted mb-0">Donor marked as ineligible for donation.</p>
                    <div class="mt-3 p-3 bg-light rounded">
                        <strong>Restriction Details:</strong>
                        <div id="restrictionSummary">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>Continue
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modal fade" id="medicalHistoryApproveConfirmModal" tabindex="-1" aria-labelledby="medicalHistoryApproveConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryApproveConfirmLabel">
                    <i class="fas fa-check me-2"></i>
                    Approve Medical History?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0">Are you sure you want to approve this donor's medical history?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmApproveMedicalHistoryBtn" class="btn btn-danger px-4">Approve</button>
            </div>
        </div>
    </div>
</div>
