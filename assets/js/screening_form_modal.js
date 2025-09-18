// Screening Form Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const screeningModal = document.getElementById('screeningFormModal');
    const screeningForm = document.getElementById('screeningForm');
    
    if (!screeningModal || !screeningForm) return;

    let currentStep = 1;
    const totalSteps = 3;

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
                const modal = bootstrap.Modal.getInstance(screeningModal);
                if (modal) {
                    modal.hide();
                } else {
                    // Fallback: force close the modal
                    screeningModal.style.display = 'none';
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    document.body.classList.remove('modal-open');
                }
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
                
                // When IN-HOUSE is selected, clear mobile fields
                if (value && value !== '') {
                    if (mobilePlaceInput) mobilePlaceInput.value = '';
                    if (mobileOrganizerInput) mobileOrganizerInput.value = '';
                }
                
                handleDonationTypeChange('inhouse', value);
            });
        }
        
        // Add change handlers for mobile fields to clear IN-HOUSE when mobile is used
        if (mobilePlaceInput) {
            mobilePlaceInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeSelect) {
                    inhouseDonationTypeSelect.value = '';
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('patientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                }
            });
        }
        
        if (mobileOrganizerInput) {
            mobileOrganizerInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeSelect) {
                    inhouseDonationTypeSelect.value = '';
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('patientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                }
            });
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

        // Show/hide defer button (show on all steps)
        if (deferButton) {
            deferButton.style.display = 'inline-block';
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
            // Show warning alert
            alert.style.display = 'block';
            input.style.borderColor = '#dc3545';
        } else {
            // Hide alert and reset border
            alert.style.display = 'none';
            input.style.borderColor = gravity > 0 ? '#28a745' : '#e9ecef';
        }
    }

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

        // Submit the form data to the backend
        fetch('../../assets/php_func/process_screening_form.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            //console.log('Screening form submission response:', data);
            
            if (data.success) {
                // Create and show the success modal
                const successModalHtml = `
                    <div class="modal fade" id="screeningSuccessModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                                    <h5 class="modal-title">Screening Submitted Successfully</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-0">Screening submitted. Please print the declaration form and guide the donor to the next stage.</p>
                                </div>
                                <div class="modal-footer border-0 justify-content-end">
                                    <button type="button" class="btn" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;" onclick="printDeclarationForm()">Print Form</button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                
                // Remove existing modal if any
                const existingModal = document.getElementById('screeningSuccessModal');
                if (existingModal) {
                    existingModal.remove();
                }
                
                // Add the modal to the document
                document.body.insertAdjacentHTML('beforeend', successModalHtml);
                
                // Show the modal
                const successModal = new bootstrap.Modal(document.getElementById('screeningSuccessModal'));
                successModal.show();
                
                // Add event listener to remove modal from DOM after it's hidden
                document.getElementById('screeningSuccessModal').addEventListener('hidden.bs.modal', function () {
                    this.remove();
                });

                // Function to print declaration form
                window.printDeclarationForm = function() {
                    // Close the success modal first
                    const successModal = bootstrap.Modal.getInstance(document.getElementById('screeningSuccessModal'));
                    if (successModal) {
                        // Add event listener to show declaration form after success modal is fully hidden
                        document.getElementById('screeningSuccessModal').addEventListener('hidden.bs.modal', function showDeclaration() {
                            // Remove the listener to prevent memory leaks
                            this.removeEventListener('hidden.bs.modal', showDeclaration);
                            
                            // Show the declaration form modal after a short delay
                            setTimeout(() => {
                                if (typeof window.showDeclarationFormModal === 'function') {
                                    window.showDeclarationFormModal(window.currentDonorData.donor_id);
                                } else {
                                    console.error('showDeclarationFormModal function is not defined');
                                }
                            }, 150); // Small delay to ensure smooth transition
                        });
                        
                        // Hide the success modal
                        successModal.hide();
                    }
                };
                
                // Close any existing modals first
                const screeningFormModal = bootstrap.Modal.getInstance(document.getElementById('screeningFormModal'));
                if (screeningFormModal) {
                    screeningFormModal.hide();
                }
                
                // Remove any existing success modals
                const existingSuccessModal = document.getElementById('screeningSuccessModal');
                if (existingSuccessModal) {
                    existingSuccessModal.remove();
                }
                
                // Update physical examination record with screening_id
                const transitionData = new FormData();
                transitionData.append('action', 'transition_to_physical');
                transitionData.append('donor_id', window.currentDonorData.donor_id);
                if (data.screening_id) {
                    transitionData.append('screening_id', data.screening_id);
                    
                    // Send the transition data to the new handler
                    fetch('../../assets/php_func/handle_screening_transition.php', {
                        method: 'POST',
                        body: transitionData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (!result.success) {
                            console.error('Transition error:', result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Transition error:', error);
                    });
                }

                fetch('dashboard-staff-medical-history-submissions.php', {
                    method: 'POST',
                    body: transitionData
                })
                .then(response => response.json())
                .then(transitionResult => {
                    //console.log('Transition response:', transitionResult);
                    if (transitionResult.success) {
                        //console.log('✅ Transition successful - Physical examination updated');
                        showAlert('Donor transitioned to physical examination review!', 'success');
                    } else {
                        //console.warn('❌ Transition failed:', transitionResult.message);
                        showAlert('Warning: Transition failed - ' + transitionResult.message, 'warning');
                    }
                })
                .catch(transitionError => {
                    //console.error('Transition error:', transitionError);
                })
                .finally(() => {
                    // Close the screening modal
                    const modal = bootstrap.Modal.getInstance(screeningModal);
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Just close the screening modal and show success modal
                    // Declaration form will only open when Print Form is clicked
                });
            } else {
                showAlert('Error submitting screening form: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            //console.error('Error submitting screening form:', error);
            showAlert('Error submitting screening form. Please try again.', 'danger');
        })
        .finally(() => {
            // Reset button state
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        });
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
        const modal = bootstrap.Modal.getInstance(screeningModal);
        if (modal) {
            modal.hide();
        }
    }
    
    // Wait a moment for modal to close, then open defer modal
    setTimeout(() => {
        // Check if screening defer modal functions are available
        if (typeof handleScreeningDeferDonor === 'function') {
            handleScreeningDeferDonor();
        } else {
            //console.error('handleScreeningDeferDonor function not found. Make sure initial-screening-defer-button.js is loaded.');
            alert('Error: Defer functionality not available. Please refresh the page and try again.');
        }
    }, 300);
}

// Make openScreeningModal function globally available
window.openScreeningModal = openScreeningModal;