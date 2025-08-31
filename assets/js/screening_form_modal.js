// Screening Form Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const screeningModal = document.getElementById('screeningFormModal');
    const screeningForm = document.getElementById('screeningForm');
    
    if (!screeningModal || !screeningForm) return;

    let currentStep = 1;
    const totalSteps = 5;

    // Initialize form functionality when modal is shown
    screeningModal.addEventListener('shown.bs.modal', function() {
        initializeScreeningForm();
        resetToStep(1);
    });

    function initializeScreeningForm() {
        // Get all the necessary elements
        const nextButton = document.getElementById('screeningNextButton');
        const prevButton = document.getElementById('screeningPrevButton');
        const submitButton = document.getElementById('screeningSubmitButton');
        const cancelButton = document.getElementById('screeningCancelButton');

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
            console.log('Submit button found, adding event listener');
            submitButton.addEventListener('click', function() {
                console.log('Submit button clicked!');
                handleScreeningFormSubmission();
            });
        } else {
            console.error('Submit button not found!');
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                console.log('Cancel button clicked, closing screening modal...');
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

        // Attempt prefill immediately on init
        prefillFromExisting();

        // Add donation type change handlers
        const donationTypeRadios = document.querySelectorAll('input[name="donation-type"]');
        donationTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateStep3Content(this.value);
            });
        });

        // Add history change handlers
        const historyRadios = document.querySelectorAll('input[name="history"]');
        historyRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateHistoryDetails(this.value);
            });
        });
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
        if (step === 1 || step === 4) {
            prefillFromExisting();
        }

        // Update Step 3 content based on selected donation type when entering step 3
        if (step === 3) {
            const selectedDonationType = document.querySelector('input[name="donation-type"]:checked');
            if (selectedDonationType) {
                updateStep3Content(selectedDonationType.value);
            } else {
                // If no donation type selected, show the default "no additional details"
                updateStep3Content('');
            }
        }

        // Generate review content if going to step 5
        if (step === 5) {
            generateReviewContent();
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

        // Show/hide previous button
        if (prevButton) {
            prevButton.style.display = currentStep > 1 ? 'inline-block' : 'none';
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
        console.log('Validating current step:', currentStep);
        const currentContent = document.querySelector(`.screening-step-content[data-step="${currentStep}"]`);
        if (!currentContent) {
            console.log('No content found for step:', currentStep);
            return false;
        }

        const requiredFields = currentContent.querySelectorAll('input[required], select[required]');
        console.log('Found', requiredFields.length, 'required fields');
        let isValid = true;

        requiredFields.forEach(field => {
            console.log('Validating field:', field.name, 'value:', field.value);
            if (!field.value.trim()) {
                console.log('Field', field.name, 'is empty');
                field.focus();
                field.style.borderColor = '#dc3545';
                isValid = false;
                return false;
            } else {
                console.log('Field', field.name, 'is valid');
                field.style.borderColor = '#e9ecef';
            }
        });

        if (!isValid) {
            showAlert('Please fill in all required fields before proceeding.', 'warning');
        }

        return isValid;
    }

    function updateStep3Content(donationType) {
        const mobileDonationSection = document.getElementById('mobileDonationSection');
        const patientDetailsSection = document.getElementById('patientDetailsSection');
        const noAdditionalDetails = document.getElementById('noAdditionalDetails');
        
        // Hide all sections first
        if (mobileDonationSection) mobileDonationSection.style.display = 'none';
        if (patientDetailsSection) patientDetailsSection.style.display = 'none';
        if (noAdditionalDetails) noAdditionalDetails.style.display = 'none';

        // If no donation type is selected, show default "no additional details"
        if (!donationType) {
            if (noAdditionalDetails) noAdditionalDetails.style.display = 'block';
            return;
        }

        // Check donation type conditions
        const isMobile = donationType.startsWith('mobile-');
        const isPatientDirected = donationType === 'patient-directed' || donationType === 'mobile-patient-directed';
        const isWalkInOrReplacement = donationType === 'walk-in' || donationType === 'replacement';
        
        // Show mobile section for ANY mobile donation type
        if (isMobile && mobileDonationSection) {
            mobileDonationSection.style.display = 'block';
        }
        
        // Show patient details section for ANY patient-directed donation (mobile or in-house)
        if (isPatientDirected && patientDetailsSection) {
            patientDetailsSection.style.display = 'block';
        }
        
        // Show "no additional details" only for walk-in/replacement (non-patient-directed in-house donations)
        if (isWalkInOrReplacement && noAdditionalDetails) {
            noAdditionalDetails.style.display = 'block';
        }
    }

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
        const donationType = formData.get('donation-type');
        reviewHtml += '<div class="mb-3">';
        reviewHtml += '<h6 class="text-danger mb-2">Donation Type</h6>';
        reviewHtml += `<div class="screening-review-item">
            <span class="screening-review-label">Type:</span>
            <span class="screening-review-value">${donationType ? donationType.replace('-', ' ').toUpperCase() : 'Not selected'}</span>
        </div>`;

        // Mobile details if applicable
        if (donationType && donationType.startsWith('mobile-')) {
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
        if (donationType === 'patient-directed' || donationType === 'mobile-patient-directed') {
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
        console.log('Starting form submission...');
        
        // Final validation
        if (!validateCurrentStep()) {
            console.log('Form validation failed, stopping submission');
            return;
        }
        
        console.log('Form validation passed, proceeding with submission');

        // Show loading state
        const submitButton = document.getElementById('screeningSubmitButton');
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitButton.disabled = true;

        // Get all form data
        const formData = new FormData(screeningForm);
        
        // Apply auto-increment logic for Red Cross donations before submission
        const rcInput = document.querySelector('input[name="red-cross"]');
        if (rcInput && rcInput.hasAttribute('data-incremented-value')) {
            const incrementedValue = rcInput.getAttribute('data-incremented-value');
            formData.set('red-cross', incrementedValue);
            console.log('[Auto-Increment] Submitting incremented Red Cross value:', incrementedValue);
        }
        
        // Make sure donor_id is included
        if (window.currentDonorData && window.currentDonorData.donor_id) {
            formData.append('donor_id', window.currentDonorData.donor_id);
        }

        // Submit the form data to the backend
        console.log('Submitting screening form data...');
        console.log('Form data entries:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ':', value);
        }
        
        fetch('../../assets/php_func/process_screening_form.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Screening form submission response:', data);
            
            if (data.success) {
                showAlert('Screening form submitted successfully!', 'success');
                
                // Call the transition endpoint to update needs_review flags
                console.log('Calling transition endpoint...');
                console.log('Current donor data:', window.currentDonorData);
                console.log('Screening ID from response:', data.screening_id);
                
                const transitionData = new FormData();
                transitionData.append('action', 'transition_to_physical');
                transitionData.append('donor_id', window.currentDonorData.donor_id);
                if (data.screening_id) {
                    transitionData.append('screening_id', data.screening_id);
                }
                
                console.log('Transition data being sent:');
                for (let [key, value] of transitionData.entries()) {
                    console.log(key + ':', value);
                }
                
                fetch('dashboard-staff-medical-history-submissions.php', {
                    method: 'POST',
                    body: transitionData
                })
                .then(response => response.json())
                .then(transitionResult => {
                    console.log('Transition response:', transitionResult);
                    if (transitionResult.success) {
                        console.log('✅ Transition successful - Physical examination updated');
                        showAlert('Donor transitioned to physical examination review!', 'success');
                    } else {
                        console.warn('❌ Transition failed:', transitionResult.message);
                        showAlert('Warning: Transition failed - ' + transitionResult.message, 'warning');
                    }
                })
                .catch(transitionError => {
                    console.error('Transition error:', transitionError);
                })
                .finally(() => {
                    // Close the screening modal
                    const modal = bootstrap.Modal.getInstance(screeningModal);
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Show declaration form modal instead of refreshing
                    if (window.showDeclarationFormModal && window.currentDonorData && window.currentDonorData.donor_id) {
                        setTimeout(() => {
                            window.showDeclarationFormModal(window.currentDonorData.donor_id);
                        }, 500);
                    } else {
                        // Fallback: refresh the page
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                });
            } else {
                showAlert('Error submitting screening form: ' + (data.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error submitting screening form:', error);
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
    
    console.log('Opening screening modal for donor:', donorData.donor_id);
    
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
        console.log('[Screening Modal] Delayed prefill attempt');
        prefillFromExisting();
    }, 500);
    
    // Direct auto-increment for Red Cross donations
    setTimeout(() => {
        console.log('[Direct Auto-Increment] Starting direct auto-increment...');
        
        // Get the Red Cross input field
        const rcInput = document.querySelector('input[name="red-cross"]');
        if (rcInput) {
            console.log('[Direct Auto-Increment] Found RC input, current value:', rcInput.value);
            
            // Get current value and increment it
            const currentValue = parseInt(rcInput.value) || 0;
            const newValue = currentValue + 1;
            rcInput.value = newValue;
            
            console.log('[Direct Auto-Increment] Incremented RC from', currentValue, 'to', newValue);
        } else {
            console.log('[Direct Auto-Increment] RC input not found!');
        }
    }, 200);
} 

// Centralized prefill routine
function prefillFromExisting() {
    try {
        if (!window.currentDonorData || !window.currentDonorData.donor_id) return;
        const donorId = window.currentDonorData.donor_id;
        console.log('[Screening Prefill] Fetching latest screening for donor', donorId);
        fetch(`../../assets/php_func/get_latest_screening_by_donor.php?donor_id=${donorId}`)
            .then(r => r.json())
            .then(j => {
                console.log('[Screening Prefill] Response', j);
                if (!j.success || !j.data) {
                    console.log('[Screening Prefill] No data found or request failed');
                    return;
                }
                const d = j.data;
                // Basic Info (do not autofill body weight or specific gravity)
                const bt = document.querySelector('select[name="blood-type"]');
                if (bt && (bt.value === '' || bt.value === null)) bt.value = d.blood_type || '';

                // Donation type
                const dt = document.querySelector(`input[name="donation-type"][value="${d.donation_type}"]`);
                if (dt && !dt.checked) {
                    dt.checked = true;
                    // Trigger content update for step 3 sections
                    const event = new Event('change');
                    dt.dispatchEvent(event);
                }

                // History (infer yes if any signals present)
                const inferredPrev = (
                    d.has_previous_donation === true ||
                    (typeof d.red_cross_donations === 'number' && d.red_cross_donations > 0) ||
                    (typeof d.hospital_donations === 'number' && d.hospital_donations > 0) ||
                    (d.last_donated_at && d.last_donated_at !== '0001-01-01' && d.last_donated_at !== '0001-01-01T00:00:00Z')
                );
                const hasPrev = inferredPrev;
                const yesRadio = document.querySelector('input[name="history"][value="yes"]');
                const noRadio = document.querySelector('input[name="history"][value="no"]');
                if (hasPrev && yesRadio && !yesRadio.checked) yesRadio.checked = true;
                if (!hasPrev && noRadio && !noRadio.checked) noRadio.checked = true;
                const historyDetails = document.getElementById('historyDetails');
                if (hasPrev) {
                    if (historyDetails) historyDetails.style.display = 'block';
                    const rcTimes = document.querySelector('input[name="red-cross"]');
                    const hospTimes = document.querySelector('input[name="hospital-history"]');
                    const lastRcDate = document.querySelector('input[name="last-rc-donation-date"]');
                    const lastHospDate = document.querySelector('input[name="last-hosp-donation-date"]');
                    const lastRcPlace = document.querySelector('input[name="last-rc-donation-place"]');
                    const lastHospPlace = document.querySelector('input[name="last-hosp-donation-place"]');
                    
                    console.log('[Screening Prefill] Reached donation history section, hasPrev:', hasPrev);
                    
                    // Set donation counts - show original value in form but prepare incremented value for submission
                    if (rcTimes) {
                        const currentRcCount = (typeof d.red_cross_donations === 'number') ? d.red_cross_donations : (d.red_cross_donations ? parseInt(d.red_cross_donations, 10) || 0 : 0);
                        // Show original value in form for debugging
                        rcTimes.value = currentRcCount;
                        // Store incremented value for submission
                        rcTimes.setAttribute('data-incremented-value', currentRcCount + 1);
                        console.log('[Auto-Increment] Red Cross: Form shows', currentRcCount, 'but will submit', currentRcCount + 1);
                    }
                    
                    if (hospTimes) {
                        // Hospital donations should only be filled manually by user
                        // Keep existing value or set to 0 if not set
                        const currentHospCount = (typeof d.hospital_donations === 'number') ? d.hospital_donations : (d.hospital_donations ? parseInt(d.hospital_donations, 10) || 0 : 0);
                        hospTimes.value = currentHospCount;
                        console.log('[Auto-Increment] Hospital:', currentHospCount, '(no auto-increment)');
                    }
                    // Prefer last_donated_at (timestampz) if present, fallback to per-source dates
                    let isoDate = null;
                    if (d.last_donated_at && typeof d.last_donated_at === 'string' && !d.last_donated_at.includes('0001-01-01')) {
                        try {
                            const dt = new Date(d.last_donated_at);
                            const year = dt.getUTCFullYear();
                            if (!isNaN(dt.getTime()) && year > 1) {
                                // yyyy-mm-dd
                                const yyyy = year;
                                const mm = String(dt.getUTCMonth() + 1).padStart(2, '0');
                                const dd = String(dt.getUTCDate()).padStart(2, '0');
                                isoDate = `${yyyy}-${mm}-${dd}`;
                            }
                        } catch (_) {}
                    }
                    if (lastRcDate) {
                        if (isoDate && (lastRcDate.value === '' || lastRcDate.value === null)) lastRcDate.value = isoDate;
                        else if (d.last_rc_donation_date && typeof d.last_rc_donation_date === 'string' && !d.last_rc_donation_date.includes('0001-01-01')) lastRcDate.value = d.last_rc_donation_date;
                    }
                    if (lastHospDate) {
                        if (isoDate && (lastHospDate.value === '' || lastHospDate.value === null)) lastHospDate.value = isoDate;
                        else if (d.last_hosp_donation_date && typeof d.last_hosp_donation_date === 'string' && !d.last_hosp_donation_date.includes('0001-01-01')) lastHospDate.value = d.last_hosp_donation_date;
                    }
                    if (lastRcPlace && d.last_rc_donation_place) lastRcPlace.value = d.last_rc_donation_place;
                    if (lastHospPlace && d.last_hosp_donation_place) lastHospPlace.value = d.last_hosp_donation_place;
                } else if (historyDetails) {
                    historyDetails.style.display = 'none';
                }
            })
            .catch((e) => { console.warn('[Screening Prefill] Fetch failed', e); });
    } catch (e) {}
}

// Make openScreeningModal function globally available
window.openScreeningModal = openScreeningModal;