// Screening Form Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const screeningModal = document.getElementById('screeningFormModal');
    // If some global guard is preventing close, make sure we allow it after approve
    try { window.releaseScreeningApproveGuard = function(){ try { window.__screeningApproveActive = false; } catch(_) {} }; } catch(_) {}
    const screeningForm = document.getElementById('screeningForm');
    
    if (!screeningModal || !screeningForm) return;

    let currentStep = 1;
    const totalSteps = 3;

    // Centralized modal cleanup function
    function cleanupModalBackdrops() {
        // Only remove backdrops if no other modals are open
        const otherModals = document.querySelectorAll('.modal.show:not(#screeningFormModal)');
        if (otherModals.length === 0) {
            // Remove all modal backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Remove modal-open class and restore body styles
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
        }
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

        // Add donation type change handler for IN-HOUSE radio button
        const inhouseDonationTypeRadio = document.getElementById('inhouseDonationTypeRadio');
        const mobilePlaceInput = document.getElementById('mobilePlaceInput');
        const mobileOrganizerInput = document.getElementById('mobileOrganizerInput');
        
        // Initialize: Radio button starts unchecked, so mobile fields are enabled by default
        if (mobilePlaceInput) {
            mobilePlaceInput.disabled = false;
            mobilePlaceInput.placeholder = 'Enter location.';
        }
        if (mobileOrganizerInput) {
            mobileOrganizerInput.disabled = false;
            mobileOrganizerInput.placeholder = 'Enter organizer.';
        }
        
        if (inhouseDonationTypeRadio) {
            // Store previous state to detect toggle
            let wasCheckedBeforeClick = false;
            
            // Capture state before click
            inhouseDonationTypeRadio.addEventListener('mousedown', function(e) {
                wasCheckedBeforeClick = this.checked;
            });
            
            // Handle toggle behavior: uncheck if already checked
            inhouseDonationTypeRadio.addEventListener('click', function(e) {
                // Use setTimeout to check state after browser's default behavior
                setTimeout(() => {
                    // If it was checked before click and is still checked, uncheck it
                    if (wasCheckedBeforeClick && this.checked) {
                        this.checked = false;
                        // Trigger change event manually to update UI
                        const changeEvent = new Event('change', { bubbles: true });
                        this.dispatchEvent(changeEvent);
                    }
                }, 0);
            });
            
            inhouseDonationTypeRadio.addEventListener('change', function() {
                const value = this.checked ? this.value : '';
                
                // When IN-HOUSE is selected, clear and disable mobile fields
                if (value && value !== '') {
                    if (mobilePlaceInput) {
                        mobilePlaceInput.value = '';
                        mobilePlaceInput.disabled = true;
                        mobilePlaceInput.placeholder = 'Disabled';
                    }
                    if (mobileOrganizerInput) {
                        mobileOrganizerInput.value = '';
                        mobileOrganizerInput.disabled = true;
                        mobileOrganizerInput.placeholder = 'Disabled';
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
        
        // Add change handlers for mobile fields to clear IN-HOUSE when mobile is used
        if (mobilePlaceInput) {
            mobilePlaceInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeRadio) {
                    // Uncheck IN-HOUSE radio when mobile field is filled
                    inhouseDonationTypeRadio.checked = false;
                    // Trigger change event to update UI
                    const changeEvent = new Event('change', { bubbles: true });
                    inhouseDonationTypeRadio.dispatchEvent(changeEvent);
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('patientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                }
                // Don't auto-check radio when mobile field is cleared - let it stay unchecked
                checkMutualExclusivity();
            });
        }
        
        if (mobileOrganizerInput) {
            mobileOrganizerInput.addEventListener('input', function() {
                if (this.value.trim() !== '' && inhouseDonationTypeRadio) {
                    // Uncheck IN-HOUSE radio when mobile field is filled
                    inhouseDonationTypeRadio.checked = false;
                    // Trigger change event to update UI
                    const changeEvent = new Event('change', { bubbles: true });
                    inhouseDonationTypeRadio.dispatchEvent(changeEvent);
                    // Hide patient details if shown
                    const patientDetailsSection = document.getElementById('patientDetailsSection');
                    if (patientDetailsSection) {
                        patientDetailsSection.style.display = 'none';
                    }
                }
                // Don't auto-check radio when mobile field is cleared - let it stay unchecked
                checkMutualExclusivity();
            });
        }

        // Add mutual exclusivity check function
        function checkMutualExclusivity() {
            const inhouseValue = inhouseDonationTypeRadio && inhouseDonationTypeRadio.checked ? inhouseDonationTypeRadio.value : '';
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
        const bloodTypeSelect = document.querySelector('select[name="blood-type"]');
        
        // Function to check and update Next button state in real-time
        function updateNextButtonState() {
            const nextButton = document.getElementById('screeningNextButton');
            if (!nextButton) {
                return;
            }
            
            // Check if we're on step 2 by checking if step 2 content is active
            if (currentStep !== 2) {
                // Not on step 2, don't modify button
                return;
            }

            const step2Content = document.querySelector('.screening-step-content[data-step="2"]');
            
            // Get elements fresh each time to ensure we have the latest values
            // Always scope to the modal to avoid conflicts
            const screeningModal = document.getElementById('screeningFormModal');
            if (!screeningModal) {
                return;
            }
            
            // Try to find elements in step 2 content first, then fallback to modal
            const step2Container = step2Content || screeningModal;
            const bodyWeightInputEl = step2Container.querySelector('#bodyWeightInput') || screeningModal.querySelector('#bodyWeightInput');
            const specificGravityInputEl = step2Container.querySelector('#specificGravityInput') || screeningModal.querySelector('#specificGravityInput');
            
            // Find blood type select - be very specific to avoid conflicts
            // Priority: step 2 content > modal > all selects in modal
            let bloodTypeSelectEl = null;
            
            // First try: find in step 2 content
            if (step2Content) {
                bloodTypeSelectEl = step2Content.querySelector('select[name="blood-type"]');
            }
            
            // Second try: find in modal
            if (!bloodTypeSelectEl && screeningModal) {
                bloodTypeSelectEl = screeningModal.querySelector('select[name="blood-type"]');
            }
            
            // Last resort: search all selects and find the one in step 2
            if (!bloodTypeSelectEl) {
                const allSelects = document.querySelectorAll('select[name="blood-type"]');
                for (let sel of allSelects) {
                    const inStep2 = sel.closest('.screening-step-content[data-step="2"]');
                    const inModal = sel.closest('#screeningFormModal');
                    if (inStep2 || inModal) {
                        bloodTypeSelectEl = sel;
                        break;
                    }
                }
            }
            
            if (!bodyWeightInputEl || !specificGravityInputEl || !bloodTypeSelectEl) {
                // Elements not found, disable button
                nextButton.disabled = true;
                nextButton.style.backgroundColor = '#dc3545';
                nextButton.style.borderColor = '#dc3545';
                nextButton.style.opacity = '0.8';
                nextButton.style.cursor = 'not-allowed';
                return;
            }
            
            const bodyWeightValue = bodyWeightInputEl.value ? String(bodyWeightInputEl.value).trim() : '';
            const specificGravityValue = specificGravityInputEl.value ? String(specificGravityInputEl.value).trim() : '';
            
            // Get blood type value - check multiple ways to ensure we get it
            const bloodTypeValue = bloodTypeSelectEl.value ? String(bloodTypeSelectEl.value).trim() : '';
            const selectedIndex = bloodTypeSelectEl.selectedIndex;
            const selectedOption = selectedIndex >= 0 && selectedIndex < bloodTypeSelectEl.options.length 
                                 ? bloodTypeSelectEl.options[selectedIndex] 
                                 : null;
            
            // Also check the selected option's value directly
            const selectedOptionValue = selectedOption ? String(selectedOption.value || '').trim() : '';
            
            // Blood type is valid if:
            // 1. selectedIndex > 0 (not the placeholder which is index 0)
            // 2. The selected option exists and has a value
            // 3. The selected option is not disabled
            // 4. Either bloodTypeValue or selectedOptionValue is not empty
            const hasBloodTypeValue = (bloodTypeValue && bloodTypeValue !== '') || (selectedOptionValue && selectedOptionValue !== '');
            const isBloodTypeSelected = selectedIndex > 0 && // Must be greater than 0 (not the placeholder)
                                       selectedOption && 
                                       hasBloodTypeValue &&
                                       !selectedOption.disabled;
            
            // Parse values
            const weight = bodyWeightValue ? parseFloat(bodyWeightValue) : NaN;
            const gravity = specificGravityValue ? parseFloat(specificGravityValue) : NaN;
            
            // Check if all fields are filled AND valid
            const hasWeight = !isNaN(weight) && weight > 0;
            const hasGravity = !isNaN(gravity) && gravity > 0;
            const hasBloodType = isBloodTypeSelected;
            
            // Check ranges: weight 50-120kg, specific gravity 12.5-18.0
            const isWeightValid = hasWeight && weight >= 50 && weight <= 120;
            const isGravityValid = hasGravity && gravity >= 12.5 && gravity <= 18.0;
            
            // All conditions must be met
            const allValid = isWeightValid && isGravityValid && hasBloodType;
            
            // If ALL conditions are met: weight in range AND gravity in range AND blood type selected
            if (allValid) {
                // Green inputs: button enabled with 100% opacity and clickable
                nextButton.disabled = false;
                nextButton.style.backgroundColor = '';
                nextButton.style.borderColor = '';
                nextButton.style.opacity = '1';
                nextButton.style.cursor = 'pointer';
            } else {
                // Red inputs: button disabled with 80% opacity (20% less than 100%)
                nextButton.disabled = true;
                nextButton.style.backgroundColor = '#dc3545';
                nextButton.style.borderColor = '#dc3545';
                nextButton.style.opacity = '0.8';
                nextButton.style.cursor = 'not-allowed';
            }
        }
        
        if (bodyWeightInput) {
            bodyWeightInput.addEventListener('input', function() {
                validateBodyWeight(this.value);
                updateNextButtonState();
            });
        }
        
        if (specificGravityInput) {
            specificGravityInput.addEventListener('input', function() {
                validateSpecificGravity(this.value);
                updateNextButtonState();
            });
        }
        
        if (bloodTypeSelect) {
            bloodTypeSelect.addEventListener('change', function() {
                updateNextButtonState();
            });
            
            // Also listen for input event (some browsers fire this instead)
            bloodTypeSelect.addEventListener('input', function() {
                updateNextButtonState();
            });
        }
        
        // Also add a global listener for blood type changes in case the element wasn't found during init
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'blood-type' && e.target.closest('#screeningFormModal')) {
                if (typeof updateNextButtonState === 'function') {
                    updateNextButtonState();
                } else if (typeof window.updateNextButtonState === 'function') {
                    window.updateNextButtonState();
                }
            }
        });
        
        // Expose updateNextButtonState globally so it can be called from goToStep
        // This must be done after the function is defined
        if (typeof window !== 'undefined') {
            window.updateNextButtonState = updateNextButtonState;
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
        
        // Initialize button state when entering step 2
        if (step === 2) {
            setTimeout(() => {
                // Call the update function to set button state
                if (typeof window.updateNextButtonState === 'function') {
                    window.updateNextButtonState();
                }
            }, 150);
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
        
        if (currentStep === 1) {
            // Validate donation type selection - either IN-HOUSE radio OR mobile fields filled
            const inhouseRadio = document.getElementById('inhouseDonationTypeRadio');
            const mobilePlaceInput = document.getElementById('mobilePlaceInput');
            const mobileOrganizerInput = document.getElementById('mobileOrganizerInput');
            
            const inhouseValue = inhouseRadio && inhouseRadio.checked ? inhouseRadio.value : '';
            const mobilePlace = mobilePlaceInput ? mobilePlaceInput.value.trim() : '';
            const mobileOrganizer = mobileOrganizerInput ? mobileOrganizerInput.value.trim() : '';
            
            // Check if either IN-HOUSE is selected OR at least one mobile field is filled
            const hasInhouseSelection = inhouseValue && inhouseValue !== '';
            const hasMobileSelection = mobilePlace !== '' || mobileOrganizer !== '';
            
            if (!hasInhouseSelection && !hasMobileSelection) {
                showAlert('Please select an IN-HOUSE donation type OR fill in mobile donation details (Place or Organizer) before proceeding.', 'warning');
                return false;
            }
            
            // Patient-Directed is no longer available, so no need to validate it
            
            return true;
        } else if (currentStep === 2) {
            // Validate step 2 - Basic Info with specific validation for weight and specific gravity
            // Scope queries to the screening modal to avoid conflicts
            const screeningModal = document.getElementById('screeningFormModal');
            if (!screeningModal) {
                showAlert('Form not found. Please refresh the page and try again.', 'warning');
                return false;
            }
            
            const bodyWeightInput = screeningModal.querySelector('#bodyWeightInput');
            const specificGravityInput = screeningModal.querySelector('#specificGravityInput');
            const bloodTypeSelect = screeningModal.querySelector('select[name="blood-type"]');
            
            // Check if elements exist
            if (!bodyWeightInput || !specificGravityInput || !bloodTypeSelect) {
                console.error('[Validation Error] Missing form elements:', {
                    bodyWeightInput: !!bodyWeightInput,
                    specificGravityInput: !!specificGravityInput,
                    bloodTypeSelect: !!bloodTypeSelect
                });
                showAlert('Please fill in all required fields (Body Weight, Specific Gravity, and Blood Type) before proceeding.', 'warning');
                return false;
            }
            
            // Get values safely - for number inputs, check if value exists and is not empty
            const bodyWeightValue = bodyWeightInput.value ? String(bodyWeightInput.value).trim() : '';
            const specificGravityValue = specificGravityInput.value ? String(specificGravityInput.value).trim() : '';
            const bloodTypeValue = bloodTypeSelect.value ? String(bloodTypeSelect.value).trim() : '';
            
            // Debug logging to help identify the issue
            console.log('[Screening Validation Step 2]', {
                bodyWeightInput: bodyWeightInput.value,
                bodyWeightValue: bodyWeightValue,
                specificGravityInput: specificGravityInput.value,
                specificGravityValue: specificGravityValue,
                bloodTypeSelect: bloodTypeSelect.value,
                bloodTypeValue: bloodTypeValue,
                bodyWeightEmpty: !bodyWeightValue,
                specificGravityEmpty: !specificGravityValue,
                bloodTypeEmpty: !bloodTypeValue
            });
            
            // Check required fields - must have non-empty values
            if (!bodyWeightValue || bodyWeightValue === '' || bodyWeightValue === '0') {
                showAlert('Please enter a valid Body Weight value.', 'warning');
                if (bodyWeightInput) bodyWeightInput.focus();
                return false;
            }
            
            if (!specificGravityValue || specificGravityValue === '' || specificGravityValue === '0') {
                showAlert('Please enter a valid Specific Gravity value.', 'warning');
                if (specificGravityInput) specificGravityInput.focus();
                return false;
            }
            
            // Check if blood type is selected (not the default disabled option)
            const selectedOption = bloodTypeSelect.options[bloodTypeSelect.selectedIndex];
            if (!bloodTypeValue || bloodTypeValue === '' || !selectedOption || selectedOption.disabled || selectedOption.value === '') {
                showAlert('Please select a Blood Type.', 'warning');
                if (bloodTypeSelect) bloodTypeSelect.focus();
                return false;
            }
            
            // Parse numeric values
            const weight = parseFloat(bodyWeightValue);
            const gravity = parseFloat(specificGravityValue);
            
            // Check if values are valid numbers (not NaN)
            if (isNaN(weight) || weight <= 0) {
                showAlert('Please enter a valid numeric value for Body Weight (greater than 0).', 'warning');
                if (bodyWeightInput) bodyWeightInput.focus();
                return false;
            }
            
            if (isNaN(gravity) || gravity <= 0) {
                showAlert('Please enter a valid numeric value for Specific Gravity (greater than 0).', 'warning');
                if (specificGravityInput) specificGravityInput.focus();
                return false;
            }
            
            // Check for validation errors - weight must be between 50-120kg, specific gravity 12.5-18.0
            // Red if: weight < 50 OR weight > 120, gravity < 12.5 OR gravity > 18.0
            const hasWeightError = weight < 50 || weight > 120;
            const hasGravityError = gravity < 12.5 || gravity > 18.0;
            
            // Check if inputs are green (valid range)
            const isWeightGreen = !hasWeightError && weight >= 50 && weight <= 120;
            const isGravityGreen = !hasGravityError && gravity >= 12.5 && gravity <= 18.0;
            
            // Update Next button state based on validation
            const nextButton = document.getElementById('screeningNextButton');
            if (nextButton) {
                if (hasWeightError || hasGravityError) {
                    // Red inputs: button disabled with 80% opacity (20% less than 100%)
                    nextButton.disabled = true;
                    nextButton.style.backgroundColor = '#dc3545';
                    nextButton.style.borderColor = '#dc3545';
                    nextButton.style.opacity = '0.8';
                    nextButton.style.cursor = 'not-allowed';
                } else if (isWeightGreen && isGravityGreen) {
                    // Green inputs: button enabled with 100% opacity and clickable
                    nextButton.disabled = false;
                    nextButton.style.backgroundColor = '';
                    nextButton.style.borderColor = '';
                    nextButton.style.opacity = '1';
                    nextButton.style.cursor = 'pointer';
                } else {
                    // Default enabled state
                    nextButton.disabled = false;
                    nextButton.style.backgroundColor = '';
                    nextButton.style.borderColor = '';
                    nextButton.style.opacity = '1';
                    nextButton.style.cursor = 'pointer';
                }
            }
            
            if (hasWeightError || hasGravityError) {
                // Don't show modal, just return false and let the disabled button indicate the issue
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
        
        // Updated range: 50-120kg
        // Red if: weight < 50 OR weight > 120
        if (weight > 0 && (weight < 50 || weight > 120)) {
            // Show warning alert - RED border
            alert.style.display = 'block';
            alert.textContent = '⚠️ Body weight must be between 50-120 kg for donor safety.';
            input.style.borderColor = '#dc3545';
        } else if (weight >= 50 && weight <= 120) {
            // Valid range - GREEN border
            alert.style.display = 'none';
            input.style.borderColor = '#28a745';
        } else {
            // Empty or zero - default border
            alert.style.display = 'none';
            input.style.borderColor = '#e9ecef';
        }
    }

    function validateSpecificGravity(value) {
        const alert = document.getElementById('specificGravityAlert');
        const input = document.getElementById('specificGravityInput');
        
        if (!alert || !input) return;
        
        const gravity = parseFloat(value);
        
        // Updated range: 12.5-18.0 g/dL
        // Red if: gravity < 12.5 OR gravity > 18.0
        if (gravity > 0 && (gravity < 12.5 || gravity > 18.0)) {
            // Show warning alert - RED border
            alert.style.display = 'block';
            alert.textContent = '⚠️ Specific gravity must be between 12.5-18.0 g/dL for donor safety.';
            input.style.borderColor = '#dc3545';
        } else if (gravity >= 12.5 && gravity <= 18.0) {
            // Valid range - GREEN border
            alert.style.display = 'none';
            input.style.borderColor = '#28a745';
        } else {
            // Empty or zero - default border
            alert.style.display = 'none';
            input.style.borderColor = '#e9ecef';
        }
    }

    // Validation error modal removed - using disabled button instead
    // The Next button will be disabled with red color and 80% opacity (20% less than 100%) when validation fails
    // When inputs are green (valid), button is enabled with 100% opacity and clickable

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
    // Debounce: prevent duplicate open calls within 800ms
    try {
        const now = Date.now();
        if (window.__screeningOpenAt && (now - window.__screeningOpenAt) < 800) {
            return; // ignore duplicate rapid calls
        }
        window.__screeningOpenAt = now;
    } catch(_) {}

    // Store donor data globally for form submission
    window.currentDonorData = donorData;
    
    //console.log('Opening screening modal for donor:', donorData.donor_id);
    
    // Clear form
    const form = document.getElementById('screeningForm');
    if (form) {
        form.reset();
        // After reset, ensure blood type goes back to placeholder
        const bloodTypeSelect = form.querySelector('select[name="blood-type"]');
        if (bloodTypeSelect) {
            bloodTypeSelect.selectedIndex = 0;
        }
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
                // Find blood type select in the screening modal only
                const screeningModal = document.getElementById('screeningFormModal');
                const bt = screeningModal ? screeningModal.querySelector('select[name="blood-type"]') : null;
                if (bt && (bt.value === '' || bt.value === null || bt.selectedIndex === 0)) {
                    bt.value = d.blood_type || '';
                    // Trigger change event to update button state
                    if (typeof window.updateNextButtonState === 'function') {
                        setTimeout(() => window.updateNextButtonState(), 100);
                    }
                }

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
    
    // Get donor ID from multiple sources - prioritize window.currentDonorData
    let donorId = null;
    
    // First try to get from window.currentDonorData (most reliable)
    if (window.currentDonorData && window.currentDonorData.donor_id) {
        donorId = window.currentDonorData.donor_id;
    }
    
    // Fallback to form input
    if (!donorId) {
        const donorIdInput = document.querySelector('input[name="donor_id"]');
        donorId = donorIdInput ? donorIdInput.value : null;
    }
    
    // Fallback to screening modal scope
    if (!donorId) {
        const screeningModal = document.getElementById('screeningFormModal');
        if (screeningModal) {
            const donorIdInput = screeningModal.querySelector('input[name="donor_id"]');
            donorId = donorIdInput ? donorIdInput.value : null;
        }
    }
    
    if (!donorId) {
        console.error('No donor ID found in screening form');
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
                
                if (deferModal && window.pendingScreeningModal) {
                    deferModal.addEventListener('hidden.bs.modal', function() {
                        // If defer modal is closed and we have pending screening modal, restore it
                        if (window.pendingScreeningModal) {
                            setTimeout(() => {
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
                                    }, 100);
                                }
                                // Clear the pending modal state
                                window.pendingScreeningModal = null;
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
