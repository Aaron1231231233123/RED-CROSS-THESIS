// Admin Screening Form Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const adminScreeningModal = document.getElementById('adminScreeningFormModal');
    // If some global guard is preventing close, make sure we allow it after approve
    try { window.releaseAdminScreeningApproveGuard = function(){ try { window.__adminScreeningApproveActive = false; } catch(_) {} }; } catch(_) {}
    const adminScreeningForm = document.getElementById('adminScreeningForm');
    
    if (!adminScreeningModal || !adminScreeningForm) return;

    let currentStep = 1;
    const totalSteps = 3;
    const SPECIFIC_GRAVITY_GDL_MIN = 12.5;
    const SPECIFIC_GRAVITY_GDL_MAX = 18.0;
    const SPECIFIC_GRAVITY_RELATIVE_MIN = 1.045;
    const SPECIFIC_GRAVITY_RELATIVE_MAX = 1.075;

    function isAdminSpecificGravityInRange(value) {
        if (typeof value !== 'number' || isNaN(value)) return false;
        if (value >= SPECIFIC_GRAVITY_GDL_MIN && value <= SPECIFIC_GRAVITY_GDL_MAX) return true;
        if (value >= SPECIFIC_GRAVITY_RELATIVE_MIN && value <= SPECIFIC_GRAVITY_RELATIVE_MAX) return true;
        return false;
    }

    function getAdminSpecificGravityRangeText() {
        return `${SPECIFIC_GRAVITY_GDL_MIN}-${SPECIFIC_GRAVITY_GDL_MAX} g/dL or ${SPECIFIC_GRAVITY_RELATIVE_MIN.toFixed(3)}-${SPECIFIC_GRAVITY_RELATIVE_MAX.toFixed(3)} specific gravity`;
    }

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

        const donationTypeInputs = document.querySelectorAll('input[name="donation-type"]');
        const mobilePlaceInput = document.getElementById('adminMobilePlaceInput');
        const mobileOrganizerInput = document.getElementById('adminMobileOrganizerInput');
        const bloodTypeSelect = document.querySelector('.admin-screening-step-content[data-step="2"] select[name="blood-type"]') || document.querySelector('select[name="blood-type"]');

        // Ensure mobile donation inputs remain disabled and cleared.
        if (mobilePlaceInput) {
            mobilePlaceInput.value = '';
            mobilePlaceInput.disabled = true;
            mobilePlaceInput.placeholder = 'Mobile donation disabled';
        }

        if (mobileOrganizerInput) {
            mobileOrganizerInput.value = '';
            mobileOrganizerInput.disabled = true;
            mobileOrganizerInput.placeholder = 'Mobile donation disabled';
        }

        donationTypeInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (input.checked) {
                    // Always keep mobile inputs disabled when Walk-in is chosen.
                    if (mobilePlaceInput) {
                        mobilePlaceInput.value = '';
                        mobilePlaceInput.disabled = true;
                    }
                    if (mobileOrganizerInput) {
                        mobileOrganizerInput.value = '';
                        mobileOrganizerInput.disabled = true;
                    }
                }
            });
        });

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

        if (bloodTypeSelect) {
            const handleBloodTypeInteraction = () => {
                updateAdminButtons();
            };
            bloodTypeSelect.addEventListener('change', handleBloodTypeInteraction);
            bloodTypeSelect.addEventListener('input', handleBloodTypeInteraction);
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
        const gravityOk = isAdminSpecificGravityInRange(gravity);
        return weightOk && gravityOk;
    }

    function validateAdminCurrentStep() {
        console.log('validateAdminCurrentStep called for step:', currentStep);
        
        if (currentStep === 1) {
            const selectedDonation = document.querySelector('input[name="donation-type"]:checked');
            if (!selectedDonation) {
                showAdminAlert('Please select a donation type before proceeding.', 'warning');
                return false;
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
            const hasGravityError = gravity > 0 && !isAdminSpecificGravityInRange(gravity);
            
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
        
        if (gravity > 0 && !isAdminSpecificGravityInRange(gravity)) {
            alert.style.display = 'block';
            alert.textContent = `⚠️ Specific gravity must stay within ${getAdminSpecificGravityRangeText()} for donor safety.`;
            input.style.borderColor = '#dc3545';
        } else if (gravity > 0) {
            alert.style.display = 'none';
            input.style.borderColor = '#28a745';
        } else {
            alert.style.display = 'none';
            input.style.borderColor = '#e9ecef';
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
                                ${hasGravityError ? `<li><i class="fas fa-times-circle text-danger me-2"></i><strong>Specific Gravity:</strong> Outside acceptable range (${getAdminSpecificGravityRangeText()})</li>` : ''}
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
        const selectedDonationType = formData.get('donation-type') || 'Walk-in';

        reviewHtml += '<div class="mb-3">';
        reviewHtml += '<h6 class="text-danger mb-2">Donation Type</h6>';
        reviewHtml += `<div class="admin-screening-review-item">
            <span class="admin-screening-review-label">Type:</span>
            <span class="admin-screening-review-value">${selectedDonationType}</span>
        </div>`;
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
        
        // Get donation type (defaults to Walk-in)
        const selectedDonationType = formData.get('donation-type') || 'Walk-in';
        formData.set('donation-type', selectedDonationType);
        
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
        
        // Immediately submit screening data to Supabase
        // Try multiple possible paths to handle different dashboard locations
        const possiblePaths = [
            '../../assets/php_func/process_admin_screening_form.php',
            '../../../assets/php_func/process_admin_screening_form.php',
            'assets/php_func/process_admin_screening_form.php'
        ];
        
        let submissionPromise = null;
        let lastError = null;
        
        // Try each path until one works
        for (const path of possiblePaths) {
            try {
                submissionPromise = fetch(path, {
                    method: 'POST',
                    body: formData
                });
                break;
            } catch (e) {
                lastError = e;
                continue;
            }
        }
        
        if (!submissionPromise) {
            console.error('Could not determine correct path for screening form submission');
            showAdminAlert('Error: Could not submit screening form. Please check the console for details.', 'error');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
            return;
        }
        
        submissionPromise
        .then(response => {
            console.log('Screening form submission response status:', response.status);
            if (!response.ok) {
                return response.text().then(text => {
                    console.error('Screening form submission error response:', text);
                    throw new Error('Network response was not ok: ' + response.status + ' - ' + text);
                });
            }
            return response.json().catch(e => {
                console.error('Failed to parse JSON response:', e);
                return response.text().then(text => {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response: ' + text);
                });
            });
        })
        .then(data => {
            console.log('Admin screening form submission result:', data);
            if (data && data.success) {
                console.log('Admin screening form submitted successfully:', data);
                showAdminAlert('Screening data saved successfully!', 'success');
                
                // Close the screening modal
                const screeningModalInstance = bootstrap.Modal.getInstance(document.getElementById('adminScreeningFormModal'));
                if (screeningModalInstance) {
                    screeningModalInstance.hide();
                }
                
                // Check if launched from registration - if so, show declaration form as part of registration flow
                if (window.__launchingScreening && window.__registrationDashboardUrl) {
                    console.log('Screening launched from registration - showing declaration form as part of registration flow');
                    // Clear the flag but keep the dashboard URL for later use
                    window.__launchingScreening = false;
                    // Set flag to indicate we're in registration flow for declaration form
                    window.__inRegistrationFlow = true;
                    // Show declaration form modal (part of registration process)
                    if (window.showAdminDeclarationFormModal) {
                        window.showAdminDeclarationFormModal(window.currentAdminDonorData.donor_id);
                    } else {
                        // Fallback: show success message
                        showAdminAlert('Screening data saved! Please proceed to declaration form.', 'success');
                    }
                } else {
                    // Normal flow: Show declaration form modal with confirmation
                    if (window.showAdminDeclarationFormModal) {
                        window.showAdminDeclarationFormModal(window.currentAdminDonorData.donor_id);
                    } else {
                        // Fallback: show success message
                        showAdminAlert('Screening data saved! Please proceed to declaration form.', 'success');
                    }
                }
            } else {
                const errorMsg = (data && data.error) ? data.error : 'Failed to submit screening form';
                console.error('Screening form submission failed:', errorMsg);
                throw new Error(errorMsg);
            }
        })
        .catch(error => {
            console.error('Error submitting admin screening form:', error);
            showAdminAlert('Error submitting screening form: ' + error.message + '. Please check the console for details.', 'error');
            // Reset button state on error
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
            // Clear registration flag on error
            if (window.__launchingScreening) {
                window.__launchingScreening = false;
            }
        });
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
