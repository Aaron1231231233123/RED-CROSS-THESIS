// Screening Form Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const screeningModal = document.getElementById('screeningFormModal');
    const screeningForm = document.getElementById('screeningForm');
    
    if (!screeningModal || !screeningForm) return;

    let currentStep = 1;
    const totalSteps = 3;

    // Centralized modal cleanup function
    function cleanupModalBackdrops() {
        // Remove all modal backdrops
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // Remove modal-open class and restore body styles
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        console.log('Modal backdrops cleaned up');
    }

    // Centralized modal close function
    function closeModalSafely(modalElement) {
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
        setTimeout(cleanupModalBackdrops, 100);
    }

    // Initialize form functionality when modal is shown
    screeningModal.addEventListener('shown.bs.modal', function() {
        // Idempotent init to avoid duplicate listeners on reopen
        if (!screeningModal.__screeningInit) {
            initializeScreeningForm();
            screeningModal.__screeningInit = true;
        }
        resetToStep(1);
    });

    function initializeScreeningForm() {
        // Get all the necessary elements
        const nextButton = document.getElementById('screeningNextButton');
        const prevButton = document.getElementById('screeningPrevButton');
        const submitButton = document.getElementById('screeningSubmitButton');
        const cancelButton = document.getElementById('screeningCancelButton');
        const deferButton = document.getElementById('screeningDeferButton');

        // Navigation handlers
        if (nextButton) {
            nextButton.addEventListener('click', function() {
                if (validateCurrentStep()) {
                    goToStep(currentStep + 1);
                }
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', function() {
                goToStep(currentStep - 1);
            });
        }

        if (submitButton) {
            submitButton.addEventListener('click', function() {
                handleScreeningFormSubmission();
            });
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                closeModalSafely(screeningModal);
            });
        }

        if (deferButton) {
            deferButton.addEventListener('click', function() {
                handleDeferDonor();
            });
        }

        // Prefill blood type from existing data (donation type stays fresh)
        setTimeout(() => {
            prefillFromExisting();
        }, 100);

        // Add donation type change handler for IN-HOUSE dropdown
        const inhouseDonationTypeSelect = document.getElementById('inhouseDonationTypeSelect');
        const mobilePlaceInput = document.getElementById('mobilePlaceInput');
        const mobileOrganizerInput = document.getElementById('mobileOrganizerInput');
        
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
                
                handleDonationTypeChange('inhouse', value);
                checkMutualExclusivity();
            });
        }
        
        // Add change handlers for mobile fields to clear and disable IN-HOUSE when mobile is used
        if (mobilePlaceInput) {
            mobilePlaceInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeSelect) {
                    inhouseDonationTypeSelect.value = '';
                    inhouseDonationTypeSelect.disabled = true;
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('patientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                } else if (this.value.trim() === '' && inhouseDonationTypeSelect) {
                    // Re-enable IN-HOUSE dropdown if mobile field is cleared
                    inhouseDonationTypeSelect.disabled = false;
                }
                checkMutualExclusivity();
            });
        }
        
        if (mobileOrganizerInput) {
            mobileOrganizerInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeSelect) {
                    inhouseDonationTypeSelect.value = '';
                    inhouseDonationTypeSelect.disabled = true;
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('patientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                } else if (this.value.trim() === '' && inhouseDonationTypeSelect) {
                    // Re-enable IN-HOUSE dropdown if mobile field is cleared
                    inhouseDonationTypeSelect.disabled = false;
                }
                checkMutualExclusivity();
            });
        }

        // Add mutual exclusivity check function
        function checkMutualExclusivity() {
            const inhouseValue = inhouseDonationTypeSelect ? inhouseDonationTypeSelect.value : '';
            const mobilePlace = mobilePlaceInput ? mobilePlaceInput.value.trim() : '';
            const mobileOrganizer = mobileOrganizerInput ? mobileOrganizerInput.value.trim() : '';
            
            const hasInhouseSelection = inhouseValue && inhouseValue !== '';
            const hasMobileSelection = mobilePlace !== '' || mobileOrganizer !== '';
            
            // If both are selected, show warning
            if (hasInhouseSelection && hasMobileSelection) {
                showMutualExclusivityWarning();
            } else {
                hideMutualExclusivityWarning();
            }
        }
        
        function showMutualExclusivityWarning() {
            // Create or show warning message
            let warningDiv = document.getElementById('donationTypeWarning');
            if (!warningDiv) {
                warningDiv = document.createElement('div');
                warningDiv.id = 'donationTypeWarning';
                warningDiv.className = 'alert alert-warning mt-2';
                warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Please select either In-House donation type OR fill mobile donation details, not both.';
                
                // Insert after the mobile donation section
                const mobileSection = document.querySelector('.mobile-donation-section');
                if (mobileSection) {
                    mobileSection.parentNode.insertBefore(warningDiv, mobileSection.nextSibling);
                }
            }
            warningDiv.style.display = 'block';
        }
        
        function hideMutualExclusivityWarning() {
            const warningDiv = document.getElementById('donationTypeWarning');
            if (warningDiv) {
                warningDiv.style.display = 'none';
            }
        }

        // Add real-time validation for basic screening fields
        const bodyWeightInput = document.getElementById('bodyWeightInput');
        const specificGravityInput = document.getElementById('specificGravityInput');
        
        if (bodyWeightInput) {
            bodyWeightInput.addEventListener('input', function() {
                validateBodyWeight(this.value);
            });
        }
        
        if (specificGravityInput) {
            specificGravityInput.addEventListener('input', function() {
                validateSpecificGravity(this.value);
            });
        }
    }

    // Expose initializer globally so callers can trigger if needed (no-op after first time)
    if (typeof window !== 'undefined') {
        window.initializeScreeningForm = function() {
            if (!screeningModal.__screeningInit) {
                initializeScreeningForm();
                screeningModal.__screeningInit = true;
            }
        };
    }

    function resetToStep(step) {
        currentStep = step;
        updateStepDisplay();
        updateProgressBar();
        updateButtons();
    }
    
    // Make functions globally accessible
    window.resetToStep = resetToStep;
    window.cleanupModalBackdrops = cleanupModalBackdrops;
    window.closeModalSafely = closeModalSafely;

    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;
        
        currentStep = step;
        updateStepDisplay();
        updateProgressBar();
        updateButtons();

        // Ensure prefill has run by the time user navigates
        if (step === 1 || step === 3) {
            prefillFromExisting();
        }

        // Generate review content if going to step 3
        if (step === 3) {
            setTimeout(() => {
                generateReviewContent();
            }, 100);
        }
    }

    function updateStepDisplay() {
        // Hide all step contents
        document.querySelectorAll('.screening-step-content').forEach(content => {
            content.classList.remove('active');
        });

        // Show current step content
        const currentContent = document.querySelector(`.screening-step-content[data-step="${currentStep}"]`);
        if (currentContent) {
            currentContent.classList.add('active');
        }

        // Update step indicators
        document.querySelectorAll('.screening-step').forEach((step, index) => {
            step.classList.remove('active', 'completed');
            if (index + 1 === currentStep) {
                step.classList.add('active');
            } else if (index + 1 < currentStep) {
                step.classList.add('completed');
            }
        });
    }

    function updateProgressBar() {
        const progressFill = document.querySelector('.screening-progress-fill');
        if (progressFill) {
            const progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressFill.style.width = `${progressPercentage}%`;
        }
    }

    function updateButtons() {
        const nextButton = document.getElementById('screeningNextButton');
        const prevButton = document.getElementById('screeningPrevButton');
        const submitButton = document.getElementById('screeningSubmitButton');
        const deferButton = document.getElementById('screeningDeferButton');

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
        } else {
            if (nextButton) nextButton.style.display = 'none';
            if (submitButton) submitButton.style.display = 'inline-block';
        }
    }

    function validateCurrentStep() {
        console.log('validateCurrentStep called for step:', currentStep);
        
        if (currentStep === 1) {
            // Validate donation type selection - either IN-HOUSE dropdown OR mobile fields filled
            const inhouseSelect = document.getElementById('inhouseDonationTypeSelect');
            const mobilePlaceInput = document.getElementById('mobilePlaceInput');
            const mobileOrganizerInput = document.getElementById('mobileOrganizerInput');
            
            const inhouseValue = inhouseSelect ? inhouseSelect.value : '';
            const mobilePlace = mobilePlaceInput ? mobilePlaceInput.value.trim() : '';
            const mobileOrganizer = mobileOrganizerInput ? mobileOrganizerInput.value.trim() : '';
            
            // Check if either IN-HOUSE is selected OR at least one mobile field is filled
            const hasInhouseSelection = inhouseValue && inhouseValue !== '';
            const hasMobileSelection = mobilePlace !== '' || mobileOrganizer !== '';
            
            if (!hasInhouseSelection && !hasMobileSelection) {
                showAlert('Please select an IN-HOUSE donation type OR fill in mobile donation details (Place or Organizer) before proceeding.', 'warning');
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
                    showAlert('Please fill in all patient information fields for Patient-Directed donations.', 'warning');
                    return false;
                }
            }
            
            return true;
        } else if (currentStep === 2) {
            // Validate step 2 - Basic Info with specific validation for weight and specific gravity
            const bodyWeightInput = document.getElementById('bodyWeightInput');
            const specificGravityInput = document.getElementById('specificGravityInput');
            const bloodTypeSelect = document.querySelector('select[name="blood-type"]');
            
            // Check required fields first
            if (!bodyWeightInput?.value.trim() || !specificGravityInput?.value.trim() || !bloodTypeSelect?.value) {
                showAlert('Please fill in all required fields (Body Weight, Specific Gravity, and Blood Type) before proceeding.', 'warning');
                return false;
            }
            
            // Check for validation errors (even if fields are filled)
            const weight = parseFloat(bodyWeightInput.value);
            const gravity = parseFloat(specificGravityInput.value);
            
            const hasWeightError = weight < 50 && weight > 0;
            const hasGravityError = (gravity < 12.5 || gravity > 18.0) && gravity > 0;
            
            // Debug logging
            console.log('Step 2 Validation:', {
                weight: weight,
                gravity: gravity,
                hasWeightError: hasWeightError,
                hasGravityError: hasGravityError
            });
            
            if (hasWeightError || hasGravityError) {
                console.log('Showing validation error modal');
                window.showValidationErrorModal(hasWeightError, hasGravityError);
                return false;
            }
            
            return true;
        } else {
            // For other steps, use the original validation
            const currentContent = document.querySelector(`.screening-step-content[data-step="${currentStep}"]`);
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
                showAlert('Please fill in all required fields before proceeding.', 'warning');
            }

            return isValid;
        }
    }


    function validateBodyWeight(value) {
        const alert = document.getElementById('bodyWeightAlert');
        const input = document.getElementById('bodyWeightInput');
        
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

    function validateSpecificGravity(value) {
        const alert = document.getElementById('specificGravityAlert');
        const input = document.getElementById('specificGravityInput');
        
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

    // Make showValidationErrorModal globally accessible
    window.showValidationErrorModal = function(hasWeightError, hasGravityError) {
        console.log('showValidationErrorModal called with:', { hasWeightError, hasGravityError });
        
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="validationErrorModal" tabindex="-1" aria-labelledby="validationErrorModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="validationErrorModalLabel">
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
                            <button type="button" class="btn btn-danger" id="deferFromModal">
                                <i class="fas fa-ban me-1"></i>
                                Defer Donor
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        const existingModal = document.getElementById('validationErrorModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal with proper z-index management
        const modalElement = document.getElementById('validationErrorModal');
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
                console.log('Validation modal and backdrop z-index set');
            }
        }, 50);
        
        // Handle defer button click
        document.getElementById('deferFromModal').addEventListener('click', function() {
            // Clean up validation modal properly
            modal.hide();
            setTimeout(() => {
                modalElement.remove();
                cleanupModalBackdrops();
            }, 150);
            
            // Store the screening form modal state so we can restore it if defer modal is closed
            window.pendingScreeningModal = {
                hasWeightError: hasWeightError,
                hasGravityError: hasGravityError,
                currentStep: 2 // We want to return to step 2 (Basic Info)
            };
            // Trigger defer donor functionality
            handleDeferDonor();
        });
        
        // Clean up modal when hidden
        modalElement.addEventListener('hidden.bs.modal', function() {
            this.remove();
            cleanupModalBackdrops();
        });
    };

    function handleDonationTypeChange(type, value) {
        
        // Only show Patient Information table when Patient-Directed is selected
        const patientDetailsSection = document.getElementById('patientDetailsSection');
        
        if (patientDetailsSection) {
            if (value === 'Patient-Directed') {
                patientDetailsSection.style.display = 'block';
            } else {
                patientDetailsSection.style.display = 'none';
            }
        }
    }

    // Removed unused updateConditionalSections and updateStep3Content to avoid confusion

    function updateHistoryDetails(historyValue) {
        const historyDetails = document.getElementById('historyDetails');
        if (!historyDetails) return;

        if (historyValue === 'yes') {
            historyDetails.style.display = 'block';
            // Enable all inputs in history details
            const historyInputs = historyDetails.querySelectorAll('input');
            historyInputs.forEach(input => {
                input.removeAttribute('disabled');
                if (input.type === 'number' && !input.value) {
                    input.value = '0';
                }
            });
        } else {
            historyDetails.style.display = 'none';
            // Reset and disable all inputs
            const historyInputs = historyDetails.querySelectorAll('input');
            historyInputs.forEach(input => {
                input.setAttribute('disabled', 'disabled');
                if (input.type === 'number') {
                    input.value = '0';
                } else {
                    input.value = '';
                }
            });
        }
    }

    function generateReviewContent() {
        const reviewContent = document.getElementById('reviewContent');
        if (!reviewContent) return;

        const formData = new FormData(screeningForm);
        let reviewHtml = '';

        // Basic Information
        reviewHtml += '<div class="mb-3">';
        reviewHtml += '<h6 class="text-danger mb-2">Basic Information</h6>';
        reviewHtml += `<div class="screening-review-item">
            <span class="screening-review-label">Body Weight:</span>
            <span class="screening-review-value">${formData.get('body-wt') || 'Not specified'} kg</span>
        </div>`;
        reviewHtml += `<div class="screening-review-item">
            <span class="screening-review-label">Specific Gravity:</span>
            <span class="screening-review-value">${formData.get('sp-gr') || 'Not specified'}</span>
        </div>`;
        reviewHtml += `<div class="screening-review-item">
            <span class="screening-review-label">Blood Type:</span>
            <span class="screening-review-value">${formData.get('blood-type') || 'Not specified'}</span>
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
        reviewHtml += `<div class="screening-review-item">
            <span class="screening-review-label">Type:</span>
            <span class="screening-review-value">${finalDonationType || 'Not selected'}</span>
        </div>`;

        // Mobile details if applicable
        if (finalDonationType === 'Mobile') {
            if (formData.get('mobile-place')) {
                reviewHtml += `<div class="screening-review-item">
                    <span class="screening-review-label">Place:</span>
                    <span class="screening-review-value">${formData.get('mobile-place')}</span>
                </div>`;
            }
            if (formData.get('mobile-organizer')) {
                reviewHtml += `<div class="screening-review-item">
                    <span class="screening-review-label">Organizer:</span>
                    <span class="screening-review-value">${formData.get('mobile-organizer')}</span>
                </div>`;
            }
        }

        // Patient details if applicable
        if (finalDonationType === 'Patient-Directed') {
            if (formData.get('patient-name')) {
                reviewHtml += `<div class="screening-review-item">
                    <span class="screening-review-label">Patient Name:</span>
                    <span class="screening-review-value">${formData.get('patient-name')}</span>
                </div>`;
            }
            if (formData.get('hospital')) {
                reviewHtml += `<div class="screening-review-item">
                    <span class="screening-review-label">Hospital:</span>
                    <span class="screening-review-value">${formData.get('hospital')}</span>
                </div>`;
            }
            if (formData.get('blood-type-patient')) {
                reviewHtml += `<div class="screening-review-item">
                    <span class="screening-review-label">Patient Blood Type:</span>
                    <span class="screening-review-value">${formData.get('blood-type-patient')}</span>
                </div>`;
            }
            if (formData.get('wb-component')) {
                reviewHtml += `<div class="screening-review-item">
                    <span class="screening-review-label">WB/Component:</span>
                    <span class="screening-review-value">${formData.get('wb-component')}</span>
                </div>`;
            }
            if (formData.get('no-units')) {
                reviewHtml += `<div class="screening-review-item">
                    <span class="screening-review-label">No. of Units:</span>
                    <span class="screening-review-value">${formData.get('no-units')}</span>
                </div>`;
            }
        }
        reviewHtml += '</div>';

        // History
        const hasHistory = formData.get('history');
        reviewHtml += '<div class="mb-3">';
        reviewHtml += '<h6 class="text-danger mb-2">Donation History</h6>';
        reviewHtml += `<div class="screening-review-item">
            <span class="screening-review-label">Previous Donations:</span>
            <span class="screening-review-value">${hasHistory === 'yes' ? 'Yes' : 'No'}</span>
        </div>`;

        if (hasHistory === 'yes') {
            reviewHtml += `<div class="screening-review-item">
                <span class="screening-review-label">Red Cross Donations:</span>
                <span class="screening-review-value">${formData.get('red-cross') || '0'} times</span>
            </div>`;
            reviewHtml += `<div class="screening-review-item">
                <span class="screening-review-label">Hospital Donations:</span>
                <span class="screening-review-value">${formData.get('hospital-history') || '0'} times</span>
            </div>`;
        }
        reviewHtml += '</div>';

        reviewContent.innerHTML = reviewHtml;
    }

    function showAlert(message, type = 'info') {
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

    function handleScreeningFormSubmission() {
        // Final validation
        if (!validateCurrentStep()) {
            return;
        }

        // Show loading state
        const submitButton = document.getElementById('screeningSubmitButton');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitButton.disabled = true;

        // Get all form data
        const formData = new FormData(screeningForm);
        
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
        if (window.currentDonorData && window.currentDonorData.donor_id) {
            formData.append('donor_id', window.currentDonorData.donor_id);
        }

        // Store form data in session/localStorage for later submission
        const formDataObj = {};
        for (let [key, value] of formData.entries()) {
            formDataObj[key] = value;
        }
        
        // Store screening data globally for declaration form
        window.currentScreeningData = formDataObj;
        
        // Close the screening modal
        const screeningModalInstance = bootstrap.Modal.getInstance(document.getElementById('screeningFormModal'));
        if (screeningModalInstance) {
            screeningModalInstance.hide();
        }
        
        // Show declaration form modal with confirmation (no data submission yet)
        if (window.showDeclarationFormModal) {
            window.showDeclarationFormModal(window.currentDonorData.donor_id);
        } else {
            // Fallback: show success message
            showAlert('Screening data saved! Please proceed to declaration form.', 'success');
        }
        
        // Reset button state
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    }
});

// Function to open screening modal with donor data
function openScreeningModal(donorData) {
    // Store donor data globally for form submission
    window.currentDonorData = donorData;
    
    //console.log('Opening screening modal for donor:', donorData.donor_id);
    
    // Clear form
    const form = document.getElementById('screeningForm');
    if (form) {
        form.reset();
    }

    // Set donor_id in hidden field if it exists
    const donorIdInput = document.querySelector('input[name="donor_id"]');
    if (donorIdInput && donorData && donorData.donor_id) {
        donorIdInput.value = donorData.donor_id;
    }
    
    // Note: Auto-increment logic is now handled in the prefillFromExisting() function

    // Reset modal state
    const screeningModal = document.getElementById('screeningFormModal');
    if (screeningModal) {
        // Reset to step 1
        const stepContents = screeningModal.querySelectorAll('.screening-step-content');
        stepContents.forEach((content, index) => {
            if (index === 0) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
        
        // Reset progress
        const progressFill = screeningModal.querySelector('.screening-progress-fill');
        if (progressFill) {
            progressFill.style.width = '20%';
        }
        
        // Reset step indicators
        const steps = screeningModal.querySelectorAll('.screening-step');
        steps.forEach((step, index) => {
            if (index === 0) {
                step.classList.add('active');
            } else {
                step.classList.remove('active', 'completed');
            }
        });
        
        // Reset buttons
        const nextBtn = screeningModal.querySelector('#screeningNextButton');
        const prevBtn = screeningModal.querySelector('#screeningPrevButton');
        const submitBtn = screeningModal.querySelector('#screeningSubmitButton');
        
        if (nextBtn) nextBtn.style.display = 'inline-block';
        if (prevBtn) prevBtn.style.display = 'none';
        if (submitBtn) submitBtn.style.display = 'none';
    }

    // Show the modal
    const modal = new bootstrap.Modal(screeningModal);
    modal.show();
    
    // Attempt prefill on show
    setTimeout(() => {
        prefillFromExisting();
    }, 100);
    
    // Also try prefill after a longer delay in case of timing issues
    setTimeout(() => {
        //console.log('[Screening Modal] Delayed prefill attempt');
        prefillFromExisting();
    }, 500);
    
    // Direct auto-increment for Red Cross donations
    setTimeout(() => {
        //console.log('[Direct Auto-Increment] Starting direct auto-increment...');
        
        // Get the Red Cross input field
        const rcInput = document.querySelector('input[name="red-cross"]');
        if (rcInput) {
            //console.log('[Direct Auto-Increment] Found RC input, current value:', rcInput.value);
            
            // Get current value and increment it
            const currentValue = parseInt(rcInput.value) || 0;
            const newValue = currentValue + 1;
            rcInput.value = newValue;
            
            //console.log('[Direct Auto-Increment] Incremented RC from', currentValue, 'to', newValue);
        } else {
            //console.log('[Direct Auto-Increment] RC input not found!');
        }
    }, 200);
} 

// Centralized prefill routine
function prefillFromExisting() {
    try {
        if (!window.currentDonorData || !window.currentDonorData.donor_id) return;
        const donorId = window.currentDonorData.donor_id;
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
                const bodyWeightInput = document.getElementById('bodyWeightInput');
                const specificGravityInput = document.getElementById('specificGravityInput');
                
                if (bodyWeightInput && bodyWeightInput.value) {
                    try {
                        validateBodyWeight(bodyWeightInput.value);
                    } catch (e) {
                        // Skip validation if not available yet
                    }
                }
                if (specificGravityInput && specificGravityInput.value) {
                    try {
                        validateSpecificGravity(specificGravityInput.value);
                    } catch (e) {
                        // Skip validation if not available yet
                    }
                }

                // Don't prefill donation type, mobile details, or patient details - only blood type
            })
            .catch((e) => { /* Prefill failed silently */ });
    } catch (e) {}
}

// Handle defer donor functionality
function handleDeferDonor() {
    //console.log('Defer donor button clicked in screening form');
    
    // Get donor ID from the form
    const donorIdInput = document.querySelector('input[name="donor_id"]');
    const donorId = donorIdInput ? donorIdInput.value : null;
    
    if (!donorId) {
        //console.error('No donor ID found in screening form');
        alert('Error: No donor ID found. Please try again.');
        return;
    }
    
    //console.log('Opening defer modal for donor ID:', donorId);
    
    // Close the screening modal first
    const screeningModal = document.getElementById('screeningFormModal');
    if (screeningModal) {
        closeModalSafely(screeningModal);
    }
    
    // Wait a moment for modal to close, then open defer modal
    setTimeout(() => {
        // Clean up any remaining backdrops before opening defer modal
        cleanupModalBackdrops();
        
        // Check if screening defer modal functions are available
        if (typeof handleScreeningDeferDonor === 'function') {
            handleScreeningDeferDonor();
            
            // Add listener to defer modal to restore screening form modal if closed
            setTimeout(() => {
                const deferModal = document.getElementById('deferDonorModal');
                console.log('Looking for defer modal:', deferModal);
                console.log('Pending screening modal state:', window.pendingScreeningModal);
                
                if (deferModal && window.pendingScreeningModal) {
                    console.log('Adding listener to defer modal for screening form restoration');
                    deferModal.addEventListener('hidden.bs.modal', function() {
                        console.log('Defer modal closed, checking if screening form modal should be restored');
                        // If defer modal is closed and we have pending screening modal, restore it
                        if (window.pendingScreeningModal) {
                            console.log('Restoring screening form modal with state:', window.pendingScreeningModal);
                            setTimeout(() => {
                                console.log('About to reopen screening form modal');
                                // Clean up any remaining backdrops first
                                cleanupModalBackdrops();
                                
                                // Reopen the screening form modal
                                const screeningModal = document.getElementById('screeningFormModal');
                                if (screeningModal) {
                                    const modal = new bootstrap.Modal(screeningModal);
                                    modal.show();
                                    
                                    // Set the form back to step 2 (Basic Info)
                                    setTimeout(() => {
                                        if (window.resetToStep) {
                                            window.resetToStep(2);
                                        }
                                        console.log('Screening form modal restored to step 2');
                                    }, 100);
                                }
                                // Clear the pending modal state
                                window.pendingScreeningModal = null;
                                console.log('Screening form modal restoration completed');
                            }, 300);
                        }
                    }, { once: true }); // Use once: true to only listen once
                }
            }, 100);
        } else {
            //console.error('handleScreeningDeferDonor function not found. Make sure initial-screening-defer-button.js is loaded.');
            alert('Error: Defer functionality not available. Please refresh the page and try again.');
        }
    }, 300);
}

// Function to clear pending screening modal (call this when deferral is completed successfully)
window.clearPendingScreeningModal = function() {
    window.pendingScreeningModal = null;
};

// Make openScreeningModal function globally available
window.openScreeningModal = openScreeningModal;