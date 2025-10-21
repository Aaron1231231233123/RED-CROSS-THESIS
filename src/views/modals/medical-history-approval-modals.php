<?php
/**
 * Enhanced Medical History Approval Modals
 * This file contains the enhanced HTML structure for medical history approval and decline modals
 * Used in the medical history review process with improved UX and workflow management
 */
?>

<!-- Enhanced Medical History Approval Success Modal -->
<div class="modal fade enhanced-modal" id="medicalHistoryApprovalModal" tabindex="-1" aria-labelledby="medicalHistoryApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <h5 class="modal-title" id="medicalHistoryApprovalModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Medical History Approved
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-success mb-3">Approval Successful!</h5>
                    <p class="text-muted mb-0">The donor's medical history has been approved.</p>
                    <p class="text-muted mb-0">The donor can now proceed to the next step in the evaluation process.</p>
                </div>
            </div>
            <div class="modal-footer">
                <div class="workflow-nav-buttons w-100">
                    <button type="button" class="btn enhanced-btn enhanced-btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn enhanced-btn enhanced-btn-primary" id="proceedToPhysicalExamBtn">
                        <i class="fas fa-arrow-right me-2"></i>Proceed to Physical Examination
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Decline Modal -->
<div class="modal fade" id="medicalHistoryDeclineModal" tabindex="-1" aria-labelledby="medicalHistoryDeclineModalLabel" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclineModalLabel">
                    <i class="fas fa-times-circle me-2"></i>
                    Decline Medical History?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <h5 class="mb-3">Are you sure you want to decline this donor's medical history?</h5>
                    <p class="text-muted mb-4">The donor will be marked as ineligible for donation.</p>
                    
                    <div class="mb-3">
                        <label for="declineType" class="form-label fw-semibold">Decline Type:</label>
                        <select class="form-select" id="declineType" name="decline_type" required>
                            <option value="Permanently Deferred" selected>Permanently Deferred</option>
                        </select>
                        <div class="form-text">The donor will be permanently deferred from donation.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="declineReason" class="form-label fw-semibold">Reason for declinement:</label>
                        <textarea class="form-control" id="declineReason" rows="4" 
                                  placeholder="Please provide a detailed reason for declining this donor's medical history..." 
                                  required maxlength="500" style="min-height: 100px;"></textarea>
                        <div class="form-text">
                            <span id="charCount">0/500 characters</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="submitDeclineBtn">
                    <i class="fas fa-ban me-2"></i>Decline Medical History
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Declined Confirmation Modal -->
<div class="modal fade" id="medicalHistoryDeclinedModal" tabindex="-1" aria-labelledby="medicalHistoryDeclinedModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclinedModalLabel">
                    <i class="fas fa-ban me-2"></i>
                    Medical History Declined
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-times-circle text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Donor's medical history has been declined.</h5>
                    <p class="text-muted mb-4">Donor marked as ineligible for donation.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modal fade" id="medicalHistoryApproveConfirmModal" tabindex="-1" aria-labelledby="medicalHistoryApproveConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryApproveConfirmLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Approve Medical History?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Are you sure you want to approve this donor's medical history?</h5>
                    <p class="text-muted mb-4">This will allow the donor to proceed to the next step in the evaluation process.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" id="confirmApproveMedicalHistoryBtn" class="btn btn-success px-4">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript functionality is handled by the dashboard -->

