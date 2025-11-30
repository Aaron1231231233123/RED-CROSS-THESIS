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
            <div class="modal-header" style="background: #941022; color: white; border-radius: 15px 15px 0 0;">
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

<!-- Medical History Completion Confirmation Modal -->
<div class="modal fade" id="submitMedicalHistoryConfirmModal" tabindex="-1" aria-labelledby="submitMedicalHistoryConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="submitMedicalHistoryConfirmLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Medical History Completed
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="mb-3">Medical History Review Complete</h5>
                    <p class="text-muted mb-4">
                        The medical history has been successfully submitted and will be marked as completed.
                    </p>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                    <i class="fas fa-check me-2"></i>OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Screening Submitted Successfully Modal -->
<div class="modal fade" id="screeningSubmittedSuccessModal" tabindex="-1" aria-labelledby="screeningSubmittedSuccessLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: #941022; color: white; border-radius: 15px 15px 0 0;">
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
    <div class="modal-dialog modal-xl" style="max-width: 90%; margin: 1.75rem auto;">
        <div class="modal-content" style="position: relative; overflow: hidden;">
            <!-- Navigation Sidebar (always visible for Declaration Form) -->
            <div class="modal-nav-sidebar" id="declarationFormNavSidebar">
                <div class="modal-nav-header">
                    <i class="fas fa-user-md modal-nav-header-icon"></i>
                    <div class="modal-nav-header-text">
                        <div class="modal-nav-header-title">INTERVIEWER</div>
                        <div class="modal-nav-header-subtitle">Workflow</div>
                    </div>
                </div>
                <div class="modal-nav-items">
                    <div class="modal-nav-item" id="navMedicalHistoryFromDeclaration" data-nav="medical-history">
                        <i class="fas fa-file-medical modal-nav-item-icon"></i>
                        <span>Medical History</span>
                    </div>
                    <div class="modal-nav-item" id="navInitialScreeningFromDeclaration" data-nav="initial-screening">
                        <i class="fas fa-clipboard-list modal-nav-item-icon"></i>
                        <span>Initial Screening</span>
                    </div>
                    <div class="modal-nav-item disabled" id="navDeclarationFormFromDeclaration" data-nav="declaration-form">
                        <i class="fas fa-file-alt modal-nav-item-icon"></i>
                        <span>Declaration Form</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-content-with-nav" id="declarationFormModalContentWrapper">
                <div class="modal-header" style="background: #941022; color: white;">
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
</div>
