<?php
/**
 * Interviewer Confirmation Modals
 * This file contains confirmation modals for the interviewer workflow
 * Used in the interviewer process to confirm actions before proceeding
 */
?>

<!-- Process Medical History Confirmation Modal -->
<div class="modal fade" id="processMedicalHistoryConfirmModal" tabindex="-1" aria-labelledby="processMedicalHistoryConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="processMedicalHistoryConfirmLabel">
                    <i class="fas fa-file-medical me-2"></i>
                    Process Medical History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <p class="mb-0">This will redirect you to the medical history the donor just submitted. Do you want to proceed?</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary px-4" id="interviewerProceedToMedicalHistoryBtn">
                    Proceed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Submit Medical History Confirmation Modal -->
<div class="modal fade" id="submitMedicalHistoryConfirmModal" tabindex="-1" aria-labelledby="submitMedicalHistoryConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="submitMedicalHistoryConfirmLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Submit Medical History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <p class="mb-0">Please confirm if the donor is ready for the next step based on the medical history interview, and proceed with Initial Screening.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-danger px-4" id="interviewerProceedToInitialScreeningBtn">
                    Initial Screening
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Screening Submitted Successfully Modal -->
<div class="modal fade" id="screeningSubmittedSuccessModal" tabindex="-1" aria-labelledby="screeningSubmittedSuccessLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="screeningSubmittedSuccessLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Screening Submitted Successfully
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <p class="mb-0">Screening submitted. Please print the declaration form and guide the donor to the next stage.</p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-primary px-4" id="printDeclarationFormBtn">
                    Print Form
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Declaration Form Modal -->
<div class="modal fade" id="declarationFormModal" tabindex="-1" aria-labelledby="declarationFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                <h5 class="modal-title" id="declarationFormModalLabel">
                    <i class="fas fa-file-alt me-2"></i>
                    Declaration Form
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="declarationFormModalContent">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
