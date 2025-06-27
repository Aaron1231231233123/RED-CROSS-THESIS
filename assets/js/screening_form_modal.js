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
            submitButton.addEventListener('click', function() {
                handleScreeningFormSubmission();
            });
        }

        if (cancelButton) {
            cancelButton.addEventListener('click', function() {
                const modal = bootstrap.Modal.getInstance(screeningModal);
                if (modal) {
                    modal.hide();
                }
            });
        }

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
        const currentContent = document.querySelector(`.screening-step-content[data-step="${currentStep}"]`);
        if (!currentContent) return false;

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
        
        // Make sure donor_id is included
        if (window.currentDonorData && window.currentDonorData.donor_id) {
            formData.append('donor_id', window.currentDonorData.donor_id);
        }

        // Submit the form
        fetch('../../assets/php_func/process_screening_form.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Close the modal
                const modal = bootstrap.Modal.getInstance(screeningModal);
                if (modal) {
                    modal.hide();
                }

                // Show success message
                showAlert('Screening form submitted successfully!', 'success');

                // Refresh the page to update the donor list
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(data.error || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error submitting form: ' + error.message, 'danger');
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

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('screeningFormModal'));
    modal.show();
} 