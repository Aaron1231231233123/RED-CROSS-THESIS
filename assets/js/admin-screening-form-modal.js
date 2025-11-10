// Admin Screening Form Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const adminScreeningModal = document.getElementById('adminScreeningFormModal');
    // If some global guard is preventing close, make sure we allow it after approve
    try { window.releaseAdminScreeningApproveGuard = function(){ try { window.__adminScreeningApproveActive = false; } catch(_) {} }; } catch(_) {}
    const adminScreeningForm = document.getElementById('adminScreeningForm');
    
    if (!adminScreeningModal || !adminScreeningForm) return;

    let currentStep = 1;
    const totalSteps = 3;

    // Centralized modal cleanup function
    function cleanupAdminModalBackdrops() {
        // Only remove backdrops if no other modals are open
        const otherModals = document.querySelectorAll('.modal.show:not(#adminScreeningFormModal)');
        if (otherModals.length === 0) {
            // Remove all modal backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Remove modal-open class and restore body styles
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            console.log('Admin modal backdrops cleaned up');
        }
    }

    // Centralized modal close function
    function closeAdminModalSafely(modalElement) {
        if (!modalElement) return;
        
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        } else {
            // Fallback: force close the modal
            modalElement.style.display = 'none';
            modalElement.classList.remove('show');
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.removeAttribute('aria-modal');
        }
        
        // Clean up backdrops after a short delay
        setTimeout(cleanupAdminModalBackdrops, 100);
    }

    // Initialize form functionality when modal is shown
    adminScreeningModal.addEventListener('shown.bs.modal', function() {
        // Idempotent init to avoid duplicate listeners on reopen
        if (!adminScreeningModal.__adminScreeningInit) {
            initializeAdminScreeningForm();
            adminScreeningModal.__adminScreeningInit = true;
        }
        resetToAdminStep(1);
    });

    function initializeAdminScreeningForm() {
        // Get all the necessary elements
        const nextButton = document.getElementById('adminScreeningNextButton');
        const prevButton = document.getElementById('adminScreeningPrevButton');
        const submitButton = document.getElementById('adminScreeningSubmitButton');
        const cancelButton = document.getElementById('adminScreeningCancelButton');
        const deferButton = document.getElementById('adminScreeningDeferButton');

        // Navigation handlers
        if (nextButton) {
            nextButton.addEventListener('click', function() {
                if (validateAdminCurrentStep()) {
                    goToAdminStep(currentStep + 1);
                }
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', function() {
                goToAdminStep(currentStep - 1);
            });
        }

        if (submitButton) {
            submitButton.addEventListener('click', function() {
                handleAdminScreeningFormSubmission();
            });
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                closeAdminModalSafely(adminScreeningModal);
            });
        }

        if (deferButton) {
            deferButton.addEventListener('click', function() {
                handleAdminDeferDonor();
            });
        }

        // Prefill blood type from existing data (donation type stays fresh)
        setTimeout(() => {
            prefillAdminFromExisting();
        }, 100);

        // Add donation type change handler for IN-HOUSE dropdown
        const inhouseDonationTypeSelect = document.getElementById('adminInhouseDonationTypeSelect');
        const mobilePlaceInput = document.getElementById('adminMobilePlaceInput');
        const mobileOrganizerInput = document.getElementById('adminMobileOrganizerInput');
        
        if (inhouseDonationTypeSelect) {
            inhouseDonationTypeSelect.addEventListener('change', function() {
                const value = this.value;
                
                // When IN-HOUSE is selected, clear and disable mobile fields
                if (value && value !== '') {
                    if (mobilePlaceInput) {
                        mobilePlaceInput.value = '';
                        mobilePlaceInput.disabled = true;
                        mobilePlaceInput.placeholder = 'Disabled - In-House selected';
                    }
                    if (mobileOrganizerInput) {
                        mobileOrganizerInput.value = '';
                        mobileOrganizerInput.disabled = true;
                        mobileOrganizerInput.placeholder = 'Disabled - In-House selected';
                    }
                } else {
                    // When IN-HOUSE is cleared, re-enable mobile fields
                    if (mobilePlaceInput) {
                        mobilePlaceInput.disabled = false;
                        mobilePlaceInput.placeholder = 'Enter location.';
                    }
                    if (mobileOrganizerInput) {
                        mobileOrganizerInput.disabled = false;
                        mobileOrganizerInput.placeholder = 'Enter organizer.';
                    }
                }
                
                handleAdminDonationTypeChange('inhouse', value);
                checkAdminMutualExclusivity();
            });
        }
        
        // Add change handlers for mobile fields to clear and disable IN-HOUSE when mobile is used
        if (mobilePlaceInput) {
            mobilePlaceInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeSelect) {
                    inhouseDonationTypeSelect.value = '';
                    inhouseDonationTypeSelect.disabled = true;
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('adminPatientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                } else if (this.value.trim() === '' && inhouseDonationTypeSelect) {
                    // Re-enable IN-HOUSE dropdown if mobile field is cleared
                    inhouseDonationTypeSelect.disabled = false;
                }
                checkAdminMutualExclusivity();
            });
        }
        
        if (mobileOrganizerInput) {
            mobileOrganizerInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeSelect) {
                    inhouseDonationTypeSelect.value = '';
                    inhouseDonationTypeSelect.disabled = true;
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('adminPatientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                } else if (this.value.trim() === '' && inhouseDonationTypeSelect) {
                    // Re-enable IN-HOUSE dropdown if mobile field is cleared
                    inhouseDonationTypeSelect.disabled = false;
                }
                checkAdminMutualExclusivity();
            });
        }

        // Add mutual exclusivity check function
        function checkAdminMutualExclusivity() {
            const inhouseValue = inhouseDonationTypeSelect ? inhouseDonationTypeSelect.value : '';
            const mobilePlace = mobilePlaceInput ? mobilePlaceInput.value.trim() : '';
            const mobileOrganizer = mobileOrganizerInput ? mobileOrganizerInput.value.trim() : '';
            
            const hasInhouseSelection = inhouseValue && inhouseValue !== '';
            const hasMobileSelection = mobilePlace !== '' || mobileOrganizer !== '';
            
            // If both are selected, show warning
            if (hasInhouseSelection && hasMobileSelection) {
                showAdminMutualExclusivityWarning();
            } else {
                hideAdminMutualExclusivityWarning();
            }
        }
        
        function showAdminMutualExclusivityWarning() {
            // Create or show warning message
            let warningDiv = document.getElementById('adminDonationTypeWarning');
            if (!warningDiv) {
                warningDiv = document.createElement('div');
                warningDiv.id = 'adminDonationTypeWarning';
                warningDiv.className = 'alert alert-warning mt-2';
                warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Please select either In-House donation type OR fill mobile donation details, not both.';
                
                // Insert after the mobile donation section
                const mobileSection = document.querySelector('.admin-mobile-donation-section');
                if (mobileSection) {
                    mobileSection.parentNode.insertBefore(warningDiv, mobileSection.nextSibling);
                }
            }
            warningDiv.style.display = 'block';
        }
        
        function hideAdminMutualExclusivityWarning() {
            const warningDiv = document.getElementById('adminDonationTypeWarning');
            if (warningDiv) {
                warningDiv.style.display = 'none';
            }
        }

        // Add real-time validation for basic screening fields
        const bodyWeightInput = document.getElementById('adminBodyWeightInput');
        const specificGravityInput = document.getElementById('adminSpecificGravityInput');
        
        if (bodyWeightInput) {
            bodyWeightInput.addEventListener('input', function() {
                validateAdminBodyWeight(this.value);
            });
        }
        
        if (specificGravityInput) {
            specificGravityInput.addEventListener('input', function() {
                validateAdminSpecificGravity(this.value);
                // Recompute button state in real-time on step 2
                updateAdminButtons();
            });
        }

        // Also recompute Next button state when weight changes
        if (bodyWeightInput) {
            bodyWeightInput.addEventListener('input', function() {
                updateAdminButtons();
            });
        }
    }

    // Expose initializer globally so callers can trigger if needed (no-op after first time)
    if (typeof window !== 'undefined') {
        window.initializeAdminScreeningForm = function() {
            if (!adminScreeningModal.__adminScreeningInit) {
                initializeAdminScreeningForm();
                adminScreeningModal.__adminScreeningInit = true;
            }
        };
    }

    function resetToAdminStep(step) {
        currentStep = step;
        updateAdminStepDisplay();
        updateAdminProgressBar();
        updateAdminButtons();
    }
    
    // Make functions globally accessible
    window.resetToAdminStep = resetToAdminStep;
    window.cleanupAdminModalBackdrops = cleanupAdminModalBackdrops;
    window.closeAdminModalSafely = closeAdminModalSafely;

    function goToAdminStep(step) {
        if (step < 1 || step > totalSteps) return;
        
        currentStep = step;
        updateAdminStepDisplay();
        updateAdminProgressBar();
        updateAdminButtons();

        // Ensure prefill has run by the time user navigates
        if (step === 1 || step === 3) {
            prefillAdminFromExisting();
        }

        // Generate review content if going to step 3
        if (step === 3) {
            setTimeout(() => {
                generateAdminReviewContent();
            }, 100);
        }
    }

    function updateAdminStepDisplay() {
        // Hide all step contents
        document.querySelectorAll('.admin-screening-step-content').forEach(content => {
            content.classList.remove('active');
        });

        // Show current step content
        const currentContent = document.querySelector(`.admin-screening-step-content[data-step="${currentStep}"]`);
        if (currentContent) {
            currentContent.classList.add('active');
        }

        // Update step indicators
        document.querySelectorAll('.admin-screening-step').forEach((step, index) => {
            step.classList.remove('active', 'completed');
            if (index + 1 === currentStep) {
                step.classList.add('active');
            } else if (index + 1 < currentStep) {
                step.classList.add('completed');
            }
        });
    }

    function updateAdminProgressBar() {
        const progressFill = document.querySelector('.admin-screening-progress-fill');
        if (progressFill) {
            const progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressFill.style.width = `${progressPercentage}%`;
        }
    }

    function updateAdminButtons() {
        const nextButton = document.getElementById('adminScreeningNextButton');
        const prevButton = document.getElementById('adminScreeningPrevButton');
        const submitButton = document.getElementById('adminScreeningSubmitButton');
        const deferButton = document.getElementById('adminScreeningDeferButton');

        // Show/hide previous button
        if (prevButton) {
            prevButton.style.display = currentStep > 1 ? 'inline-block' : 'none';
        }

        // Show/hide defer button (ONLY on step 2 - Basic Info)
        if (deferButton) {
            deferButton.style.display = currentStep === 2 ? 'inline-block' : 'none';
        }

        // Show/hide next vs submit button
        if (currentStep < totalSteps) {
            if (nextButton) nextButton.style.display = 'inline-block';
            if (submitButton) submitButton.style.display = 'none';

            // On step 2, disable Next when criteria not met (deferrable)
            if (nextButton && currentStep === 2) {
                const allowNext = isAdminStep2Eligible();
                nextButton.disabled = !allowNext;
            } else if (nextButton) {
                nextButton.disabled = false;
            }
        } else {
            if (nextButton) nextButton.style.display = 'none';
            if (submitButton) submitButton.style.display = 'inline-block';
        }
    }

    function isAdminStep2Eligible() {
        const bodyWeightInput = document.getElementById('adminBodyWeightInput');
        const specificGravityInput = document.getElementById('adminSpecificGravityInput');
        const bloodTypeSelect = document.querySelector('select[name="blood-type"]');

        if (!bodyWeightInput || !specificGravityInput || !bloodTypeSelect) return false;

        const weight = parseFloat(bodyWeightInput.value);
        const gravity = parseFloat(specificGravityInput.value);
        const hasAll = bodyWeightInput.value.trim() !== '' && specificGravityInput.value.trim() !== '' && !!bloodTypeSelect.value;
        if (!hasAll) return false;

        const weightOk = weight >= 50;
        const gravityOk = gravity >= 12.5 && gravity <= 18.0;
        return weightOk && gravityOk;
    }

    function validateAdminCurrentStep() {
        console.log('validateAdminCurrentStep called for step:', currentStep);
        
        if (currentStep === 1) {
            // Validate donation type selection - either IN-HOUSE dropdown OR mobile fields filled
            const inhouseSelect = document.getElementById('adminInhouseDonationTypeSelect');
            const mobilePlaceInput = document.getElementById('adminMobilePlaceInput');
            const mobileOrganizerInput = document.getElementById('adminMobileOrganizerInput');
            
            const inhouseValue = inhouseSelect ? inhouseSelect.value : '';
            const mobilePlace = mobilePlaceInput ? mobilePlaceInput.value.trim() : '';
            const mobileOrganizer = mobileOrganizerInput ? mobileOrganizerInput.value.trim() : '';
            
            // Check if either IN-HOUSE is selected OR at least one mobile field is filled
            const hasInhouseSelection = inhouseValue && inhouseValue !== '';
            const hasMobileSelection = mobilePlace !== '' || mobileOrganizer !== '';
            
            if (!hasInhouseSelection && !hasMobileSelection) {
                showAdminAlert('Please select an IN-HOUSE donation type OR fill in mobile donation details (Place or Organizer) before proceeding.', 'warning');
                return false;
            }
            
            // If Patient-Directed is selected, validate patient information
            if (inhouseValue === 'Patient-Directed') {
                const patientName = document.querySelector('input[name="patient-name"]');
                const hospital = document.querySelector('input[name="hospital"]');
                const patientBloodType = document.querySelector('select[name="patient-blood-type"]');
                const noUnits = document.querySelector('input[name="no-units"]');
                
                if (!patientName?.value.trim() || !hospital?.value.trim() || 
                    !patientBloodType?.value || !noUnits?.value) {
                    showAdminAlert('Please fill in all patient information fields for Patient-Directed donations.', 'warning');
                    return false;
                }
            }
            
            return true;
        } else if (currentStep === 2) {
            // Validate step 2 - Basic Info with specific validation for weight and specific gravity
            const bodyWeightInput = document.getElementById('adminBodyWeightInput');
            const specificGravityInput = document.getElementById('adminSpecificGravityInput');
            const bloodTypeSelect = document.querySelector('select[name="blood-type"]');
            
            // Check required fields first
            if (!bodyWeightInput?.value.trim() || !specificGravityInput?.value.trim() || !bloodTypeSelect?.value) {
                showAdminAlert('Please fill in all required fields (Body Weight, Specific Gravity, and Blood Type) before proceeding.', 'warning');
                return false;
            }
            
            // Check for validation errors (even if fields are filled)
            const weight = parseFloat(bodyWeightInput.value);
            const gravity = parseFloat(specificGravityInput.value);
            
            const hasWeightError = weight < 50 && weight > 0;
            const hasGravityError = (gravity < 12.5 || gravity > 18.0) && gravity > 0;
            
            // Debug logging
            console.log('Admin Step 2 Validation:', {
                weight: weight,
                gravity: gravity,
                hasWeightError: hasWeightError,
                hasGravityError: hasGravityError
            });
            
            if (hasWeightError || hasGravityError) {
                console.log('Showing admin validation error modal');
                window.showAdminValidationErrorModal(hasWeightError, hasGravityError);
                return false;
            }
            
            return true;
        } else {
            // For other steps, use the original validation
            const currentContent = document.querySelector(`.admin-screening-step-content[data-step="${currentStep}"]`);
            if (!currentContent) {
                return false;
            }

            const requiredFields = currentContent.querySelectorAll('input[required], select[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.focus();
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                    return false;
                } else {
                    field.style.borderColor = '#e9ecef';
                }
            });

            if (!isValid) {
                showAdminAlert('Please fill in all required fields before proceeding.', 'warning');
            }

            return isValid;
        }
    }

    function validateAdminBodyWeight(value) {
        const alert = document.getElementById('adminBodyWeightAlert');
        const input = document.getElementById('adminBodyWeightInput');
        
        if (!alert || !input) return;
        
        const weight = parseFloat(value);
        
        if (weight < 50 && weight > 0) {
            // Show warning alert
            alert.style.display = 'block';
            input.style.borderColor = '#dc3545';
        } else {
            // Hide alert and reset border
            alert.style.display = 'none';
            input.style.borderColor = weight > 0 ? '#28a745' : '#e9ecef';
        }
    }

    function validateAdminSpecificGravity(value) {
        const alert = document.getElementById('adminSpecificGravityAlert');
        const input = document.getElementById('adminSpecificGravityInput');
        
        if (!alert || !input) return;
        
        const gravity = parseFloat(value);
        
        if (gravity < 12.5 && gravity > 0) {
            // Show warning alert with range information
            alert.style.display = 'block';
            input.style.borderColor = '#dc3545';
        } else if (gravity > 18.0 && gravity > 0) {
            // Show warning for high specific gravity
            alert.style.display = 'block';
            input.style.borderColor = '#dc3545';
        } else {
            // Hide alert and reset border
            alert.style.display = 'none';
            input.style.borderColor = gravity > 0 ? '#28a745' : '#e9ecef';
        }
    }

    // Make showAdminValidationErrorModal globally accessible
    window.showAdminValidationErrorModal = function(hasWeightError, hasGravityError) {
        console.log('showAdminValidationErrorModal called with:', { hasWeightError, hasGravityError });
        
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="adminValidationErrorModal" tabindex="-1" aria-labelledby="adminValidationErrorModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="adminValidationErrorModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Donor Safety Alert
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger mb-3">
                                <i class="fas fa-heartbeat me-2"></i>
                                <strong>Donor Safety Concern Detected</strong>
                            </div>
                            
                            <p class="mb-3">The following screening measurements indicate potential safety concerns:</p>
                            
                            <ul class="list-unstyled">
                                ${hasWeightError ? '<li><i class="fas fa-times-circle text-danger me-2"></i><strong>Body Weight:</strong> Below minimum requirement (50 kg)</li>' : ''}
                                ${hasGravityError ? '<li><i class="fas fa-times-circle text-danger me-2"></i><strong>Specific Gravity:</strong> Outside acceptable range (12.5-18.0 g/dL)</li>' : ''}
                            </ul>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Recommendation:</strong> For donor safety, we recommend deferring this donor at this time. 
                                The donor may be eligible for future donations once their health parameters improve.
                            </div>
                            
                            <p class="text-muted small">
                                <i class="fas fa-shield-alt me-1"></i>
                                This is a safety measure to protect both the donor and potential recipients.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-arrow-left me-1"></i>
                                Go Back & Adjust
                            </button>
                            <button type="button" class="btn btn-danger" id="adminDeferFromModal">
                                <i class="fas fa-ban me-1"></i>
                                Defer Donor
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        const existingModal = document.getElementById('adminValidationErrorModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal with proper z-index management
        const modalElement = document.getElementById('adminValidationErrorModal');
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        
        // Set z-index before showing
        modalElement.style.zIndex = '10560';
        modal.show();
        
        // Ensure backdrop has proper z-index
        setTimeout(() => {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = '10550';
                console.log('Admin validation modal and backdrop z-index set');
            }
        }, 50);
        
        // Handle defer button click
        document.getElementById('adminDeferFromModal').addEventListener('click', function() {
            // Clean up validation modal properly
            modal.hide();
            setTimeout(() => {
                modalElement.remove();
                cleanupAdminModalBackdrops();
            }, 150);
            
            // Store the screening form modal state so we can restore it if defer modal is closed
            window.pendingAdminScreeningModal = {
                hasWeightError: hasWeightError,
                hasGravityError: hasGravityError,
                currentStep: 2 // We want to return to step 2 (Basic Info)
            };
            // Trigger defer donor functionality
            handleAdminDeferDonor();
        });
        
        // Clean up modal when hidden
        modalElement.addEventListener('hidden.bs.modal', function() {
            this.remove();
            cleanupAdminModalBackdrops();
        });
    };

    function handleAdminDonationTypeChange(type, value) {
        // Only show Patient Information table when Patient-Directed is selected
        const patientDetailsSection = document.getElementById('adminPatientDetailsSection');
        
        if (patientDetailsSection) {
            if (value === 'Patient-Directed') {
                patientDetailsSection.style.display = 'block';
            } else {
                patientDetailsSection.style.display = 'none';
            }
        }
    }

    function generateAdminReviewContent() {
        const reviewContent = document.getElementById('adminReviewContent');
        if (!reviewContent) return;

        const formData = new FormData(adminScreeningForm);
        let reviewHtml = '';

        // Basic Information
        reviewHtml += '<div class="mb-3">';
        reviewHtml += '<h6 class="text-danger mb-2">Basic Information</h6>';
        reviewHtml += `<div class="admin-screening-review-item">
            <span class="admin-screening-review-label">Body Weight:</span>
            <span class="admin-screening-review-value">${formData.get('body-wt') || 'Not specified'} kg</span>
        </div>`;
        reviewHtml += `<div class="admin-screening-review-item">
            <span class="admin-screening-review-label">Specific Gravity:</span>
            <span class="admin-screening-review-value">${formData.get('sp-gr') || 'Not specified'}</span>
        </div>`;
        reviewHtml += `<div class="admin-screening-review-item">
            <span class="admin-screening-review-label">Blood Type:</span>
            <span class="admin-screening-review-value">${formData.get('blood-type') || 'Not specified'}</span>
        </div>`;
        reviewHtml += '</div>';

        // Donation Type
        const inhouseDonationType = formData.get('donation-type');
        const mobilePlace = formData.get('mobile-place');
        const mobileOrganizer = formData.get('mobile-organizer');
        
        // Determine final donation type
        let finalDonationType = '';
        if (mobilePlace || mobileOrganizer) {
            finalDonationType = 'Mobile';
        } else if (inhouseDonationType) {
            finalDonationType = inhouseDonationType;
        }
        
        reviewHtml += '<div class="mb-3">';
        reviewHtml += '<h6 class="text-danger mb-2">Donation Type</h6>';
        reviewHtml += `<div class="admin-screening-review-item">
            <span class="admin-screening-review-label">Type:</span>
            <span class="admin-screening-review-value">${finalDonationType || 'Not selected'}</span>
        </div>`;

        // Mobile details if applicable
        if (finalDonationType === 'Mobile') {
            if (formData.get('mobile-place')) {
                reviewHtml += `<div class="admin-screening-review-item">
                    <span class="admin-screening-review-label">Place:</span>
                    <span class="admin-screening-review-value">${formData.get('mobile-place')}</span>
                </div>`;
            }
            if (formData.get('mobile-organizer')) {
                reviewHtml += `<div class="admin-screening-review-item">
                    <span class="admin-screening-review-label">Organizer:</span>
                    <span class="admin-screening-review-value">${formData.get('mobile-organizer')}</span>
                </div>`;
            }
        }

        // Patient details if applicable
        if (finalDonationType === 'Patient-Directed') {
            if (formData.get('patient-name')) {
                reviewHtml += `<div class="admin-screening-review-item">
                    <span class="admin-screening-review-label">Patient Name:</span>
                    <span class="admin-screening-review-value">${formData.get('patient-name')}</span>
                </div>`;
            }
            if (formData.get('hospital')) {
                reviewHtml += `<div class="admin-screening-review-item">
                    <span class="admin-screening-review-label">Hospital:</span>
                    <span class="admin-screening-review-value">${formData.get('hospital')}</span>
                </div>`;
            }
            if (formData.get('blood-type-patient')) {
                reviewHtml += `<div class="admin-screening-review-item">
                    <span class="admin-screening-review-label">Patient Blood Type:</span>
                    <span class="admin-screening-review-value">${formData.get('blood-type-patient')}</span>
                </div>`;
            }
            if (formData.get('wb-component')) {
                reviewHtml += `<div class="admin-screening-review-item">
                    <span class="admin-screening-review-label">WB/Component:</span>
                    <span class="admin-screening-review-value">${formData.get('wb-component')}</span>
                </div>`;
            }
            if (formData.get('no-units')) {
                reviewHtml += `<div class="admin-screening-review-item">
                    <span class="admin-screening-review-label">No. of Units:</span>
                    <span class="admin-screening-review-value">${formData.get('no-units')}</span>
                </div>`;
            }
        }
        reviewHtml += '</div>';

        reviewContent.innerHTML = reviewHtml;
    }

    function showAdminAlert(message, type = 'info') {
        // Create a simple alert that auto-dismisses
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        // Auto dismiss after 3 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 3000);
    }

    function handleAdminScreeningFormSubmission() {
        // Final validation
        if (!validateAdminCurrentStep()) {
            return;
        }

        // Show loading state
        const submitButton = document.getElementById('adminScreeningSubmitButton');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitButton.disabled = true;

        // Get all form data
        const formData = new FormData(adminScreeningForm);
        
        // Get donation type from IN-HOUSE dropdown
        const selectedDonationType = formData.get('donation-type');
        
        // Check if mobile donation fields are filled (Place OR Organizer)
        const mobilePlace = formData.get('mobile-place');
        const mobileOrganizer = formData.get('mobile-organizer');
        
        if (mobilePlace || mobileOrganizer) {
            // If either mobile field is filled, set donor_type to "Mobile" in the database
            formData.set('donor_type', 'Mobile');
            formData.set('donation-type', 'Mobile');
        } else if (selectedDonationType) {
            // Use the IN-HOUSE selection
            formData.set('donation-type', selectedDonationType);
        }
        
        // Apply auto-increment logic for Red Cross donations before submission
        const rcInput = document.querySelector('input[name="red-cross"]');
        if (rcInput && rcInput.hasAttribute('data-incremented-value')) {
            const incrementedValue = rcInput.getAttribute('data-incremented-value');
            formData.set('red-cross', incrementedValue);
        }
        
        // Make sure donor_id is included
        if (window.currentAdminDonorData && window.currentAdminDonorData.donor_id) {
            formData.append('donor_id', window.currentAdminDonorData.donor_id);
        }

        // Store form data in session/localStorage for later submission
        const formDataObj = {};
        for (let [key, value] of formData.entries()) {
            formDataObj[key] = value;
        }
        
        // Store screening data globally for declaration form
        window.currentAdminScreeningData = formDataObj;
        
        // Close the screening modal
        const screeningModalInstance = bootstrap.Modal.getInstance(document.getElementById('adminScreeningFormModal'));
        if (screeningModalInstance) {
            screeningModalInstance.hide();
        }
        
        // Show declaration form modal with confirmation (no data submission yet)
        if (window.showAdminDeclarationFormModal) {
            window.showAdminDeclarationFormModal(window.currentAdminDonorData.donor_id);
        } else {
            // Fallback: show success message
            showAdminAlert('Screening data saved! Please proceed to declaration form.', 'success');
        }
        
        // Reset button state
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    }
});

// Function to open admin screening modal with donor data
function openAdminScreeningModal(donorData) {
    // Debounce: prevent duplicate open calls within 800ms
    try {
        const now = Date.now();
        if (window.__adminScreeningOpenAt && (now - window.__adminScreeningOpenAt) < 800) {
            return; // ignore duplicate rapid calls
        }
        window.__adminScreeningOpenAt = now;
    } catch(_) {}

    // Store donor data globally for form submission
    window.currentAdminDonorData = donorData;
    
    console.log('Opening admin screening modal for donor:', donorData.donor_id);
    
    // Clear form
    const form = document.getElementById('adminScreeningForm');
    if (form) {
        form.reset();
    }

    // Set donor_id in hidden field if it exists
    const donorIdInput = document.querySelector('input[name="donor_id"]');
    if (donorIdInput && donorData && donorData.donor_id) {
        donorIdInput.value = donorData.donor_id;
    }

    // Reset modal state
    const adminScreeningModal = document.getElementById('adminScreeningFormModal');
    if (adminScreeningModal) {
        // Reset to step 1
        const stepContents = adminScreeningModal.querySelectorAll('.admin-screening-step-content');
        stepContents.forEach((content, index) => {
            if (index === 0) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
        
        // Reset progress
        const progressFill = adminScreeningModal.querySelector('.admin-screening-progress-fill');
        if (progressFill) {
                // Align with step calculation ((step-1)/(totalSteps-1))*100 at step=1 should be 0%
                progressFill.style.width = '0%';
        }
        
        // Reset step indicators
        const steps = adminScreeningModal.querySelectorAll('.admin-screening-step');
        steps.forEach((step, index) => {
            if (index === 0) {
                step.classList.add('active');
            } else {
                step.classList.remove('active', 'completed');
            }
        });
        
        // Reset buttons
        const nextBtn = adminScreeningModal.querySelector('#adminScreeningNextButton');
        const prevBtn = adminScreeningModal.querySelector('#adminScreeningPrevButton');
        const submitBtn = adminScreeningModal.querySelector('#adminScreeningSubmitButton');
        
        if (nextBtn) nextBtn.style.display = 'inline-block';
        if (prevBtn) prevBtn.style.display = 'none';
        if (submitBtn) submitBtn.style.display = 'none';
    }

    // Show the modal
    const modal = new bootstrap.Modal(adminScreeningModal);
    modal.show();
    
    // Attempt prefill on show
    setTimeout(() => {
        prefillAdminFromExisting();
    }, 100);
    
    // Also try prefill after a longer delay in case of timing issues
    setTimeout(() => {
        prefillAdminFromExisting();
    }, 500);
}

// Centralized prefill routine
function prefillAdminFromExisting() {
    try {
        if (!window.currentAdminDonorData || !window.currentAdminDonorData.donor_id) return;
        const donorId = window.currentAdminDonorData.donor_id;
        fetch(`../../assets/php_func/get_latest_screening_by_donor.php?donor_id=${donorId}`)
            .then(r => r.json())
            .then(j => {
                if (!j.success || !j.data) {
                    return;
                }
                const d = j.data;
                // Basic Info (do not autofill body weight or specific gravity)
                const bt = document.querySelector('select[name="blood-type"]');
                if (bt && (bt.value === '' || bt.value === null)) bt.value = d.blood_type || '';

                // Trigger validation for any existing values (safely)
                const bodyWeightInput = document.getElementById('adminBodyWeightInput');
                const specificGravityInput = document.getElementById('adminSpecificGravityInput');
                
                if (bodyWeightInput && bodyWeightInput.value) {
                    try {
                        validateAdminBodyWeight(bodyWeightInput.value);
                    } catch (e) {
                        // Skip validation if not available yet
                    }
                }
                if (specificGravityInput && specificGravityInput.value) {
                    try {
                        validateAdminSpecificGravity(specificGravityInput.value);
                    } catch (e) {
                        // Skip validation if not available yet
                    }
                }
            })
            .catch((e) => { /* Prefill failed silently */ });
    } catch (e) {}
}

// Handle admin defer donor functionality
function handleAdminDeferDonor() {
    console.log('Admin defer donor button clicked in screening form');
    
    // Get donor ID from the form
    const donorIdInput = document.querySelector('input[name="donor_id"]');
    const donorId = donorIdInput ? donorIdInput.value : null;
    
    if (!donorId) {
        console.error('No donor ID found in admin screening form');
        alert('Error: No donor ID found. Please try again.');
        return;
    }
    
    console.log('Opening defer modal for admin donor ID:', donorId);
    
    // Close the screening modal first
    const adminScreeningModal = document.getElementById('adminScreeningFormModal');
    if (adminScreeningModal) {
        closeAdminModalSafely(adminScreeningModal);
    }
    
    // Wait a moment for modal to close, then open defer modal
    setTimeout(() => {
        // Clean up any remaining backdrops before opening defer modal
        cleanupAdminModalBackdrops();
        
        // Check if screening defer modal functions are available
        if (typeof handleScreeningDeferDonor === 'function') {
            handleScreeningDeferDonor();
        } else {
            console.error('handleScreeningDeferDonor function not found. Make sure initial-screening-defer-button.js is loaded.');
            alert('Error: Defer functionality not available. Please refresh the page and try again.');
        }
    }, 300);
}

// Function to clear pending admin screening modal (call this when deferral is completed successfully)
window.clearPendingAdminScreeningModal = function() {
    window.pendingAdminScreeningModal = null;
};

// Make openAdminScreeningModal function globally available
window.openAdminScreeningModal = openAdminScreeningModal;
