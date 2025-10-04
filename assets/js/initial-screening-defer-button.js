// Initial Screening Defer Button JavaScript Functions
// This file handles defer donor functionality specifically for the medical history submissions dashboard
// Includes medical history table updates

// Global variables for defer modal state
let currentDeferData = null;

// Comprehensive function to fetch data from all source tables for screening deferral
async function fetchAllSourceDataForScreeningDeferral(donorId) {
    try {
        console.log('Fetching comprehensive data for donor:', donorId);
        
        const [screeningForm, physicalExam, donorForm] = await Promise.all([
            fetchScreeningFormDataForScreeningDeferral(donorId),
            fetchPhysicalExamDataForScreeningDeferral(donorId),
            fetchDonorFormDataForScreeningDeferral(donorId)
        ]);
        
        return {
            screeningForm,
            physicalExam,
            donorForm
        };
    } catch (error) {
        console.error('Error fetching all source data:', error);
        return {
            screeningForm: null,
            physicalExam: null,
            donorForm: null
        };
    }
}

// Fetch data from screening_form table for screening deferral
async function fetchScreeningFormDataForScreeningDeferral(donorId) {
    try {
        console.log('Fetching screening form data for donor_id:', donorId);
        const response = await fetch(`../api/get-screening-form.php?donor_id=${donorId}`);
        const data = await response.json();
        console.log('Screening form API response:', data);
        
        if (data.success && data.screening_form) {
            return data.screening_form;
        }
        return null;
    } catch (error) {
        console.error('Error fetching screening form data:', error);
        return null;
    }
}

// Fetch data from physical_examination table for screening deferral
async function fetchPhysicalExamDataForScreeningDeferral(donorId) {
    try {
        console.log('Fetching physical exam data for donor_id:', donorId);
        const response = await fetch(`../api/get-physical-examination.php?donor_id=${donorId}`);
        const data = await response.json();
        console.log('Physical exam API response:', data);
        
        if (data.success && data.physical_exam) {
            return data.physical_exam;
        }
        return null;
    } catch (error) {
        console.error('Error fetching physical exam data:', error);
        return null;
    }
}

// Fetch data from donor_form table for screening deferral
async function fetchDonorFormDataForScreeningDeferral(donorId) {
    try {
        console.log('Fetching donor form data for donor_id:', donorId);
        const response = await fetch(`../api/get-donor-form.php?donor_id=${donorId}`);
        const data = await response.json();
        console.log('Donor form API response:', data);
        
        if (data.success && data.donor_form) {
            return data.donor_form;
        }
        return null;
    } catch (error) {
        console.error('Error fetching donor form data:', error);
        return null;
    }
}

// Initialize defer modal functionality for screening
function initializeScreeningDeferModal() {
    const deferralTypeSelect = document.getElementById('deferralTypeSelect');
    const durationSection = document.getElementById('durationSection');
    const customDurationSection = document.getElementById('customDurationSection');
    const durationSelect = document.getElementById('deferralDuration');
    const customDurationInput = document.getElementById('customDuration');
    const submitBtn = document.getElementById('submitDeferral');
    const durationSummary = document.getElementById('durationSummary');
    const summaryText = document.getElementById('summaryText');
    const durationOptions = document.querySelectorAll('.duration-option');
    
    // Validation elements
    const disapprovalReasonSelect = document.getElementById('disapprovalReason');
    
    // Set default values
    deferralTypeSelect.value = 'Temporary Deferral';
    durationSelect.value = '2';
    document.querySelector('.duration-option[data-days="2"]').classList.add('active');

    // Update disapproval reason validation
    function updateScreeningDeferValidation() {
        if (!disapprovalReasonSelect) return;
        
        const isValid = disapprovalReasonSelect.value !== '';
        
        // Update validation feedback
        if (disapprovalReasonSelect.value === '') {
            disapprovalReasonSelect.classList.remove('is-valid', 'is-invalid');
        } else {
            disapprovalReasonSelect.classList.add('is-valid');
            disapprovalReasonSelect.classList.remove('is-invalid');
        }
        
        // Update submit button state
        updateScreeningDeferSubmitButtonState();
    }
    
    // Update submit button state based on all form validation
    function updateScreeningDeferSubmitButtonState() {
        if (!disapprovalReasonSelect) return;
        
        const reasonValid = disapprovalReasonSelect.value !== '';
        const deferralTypeValid = deferralTypeSelect.value !== '';
        
        // For temporary deferral, also check duration
        let durationValid = true;
        if (deferralTypeSelect.value === 'Temporary Deferral') {
            durationValid = durationSelect.value !== '' || customDurationInput.value !== '';
        }
        
        const allValid = reasonValid && deferralTypeValid && durationValid;
        
        submitBtn.disabled = !allValid;
        
        if (allValid) {
            submitBtn.style.backgroundColor = '#b22222';
            submitBtn.style.borderColor = '#b22222';
        } else {
            submitBtn.style.backgroundColor = '#6c757d';
            submitBtn.style.borderColor = '#6c757d';
        }
    }

    // Handle deferral type change
    deferralTypeSelect.addEventListener('change', function() {
        console.log('Deferral type changed to:', this.value);
        if (this.value === 'Temporary Deferral') {
            durationSection.style.display = 'block';
            setTimeout(() => {
                durationSection.classList.add('show');
                durationSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
        } else {
            durationSection.classList.remove('show');
            customDurationSection.classList.remove('show');
            setTimeout(() => {
                if (!durationSection.classList.contains('show')) {
                    durationSection.style.display = 'none';
                }
                if (!customDurationSection.classList.contains('show')) {
                    customDurationSection.style.display = 'none';
                }
            }, 400);
            durationSummary.style.display = 'none';
            // Clear duration selections
            durationOptions.forEach(opt => opt.classList.remove('active'));
            durationSelect.value = '';
            customDurationInput.value = '';
        }
        updateScreeningSummary();
    });

    // Handle duration option clicks
    durationOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            durationOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');
            
            const days = this.getAttribute('data-days');
            
            if (days === 'custom') {
                durationSelect.value = 'custom';
                customDurationSection.style.display = 'block';
                setTimeout(() => {
                    customDurationSection.classList.add('show');
                    customDurationSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    customDurationInput.focus();
                }, 50);
            } else {
                durationSelect.value = days;
                customDurationSection.classList.remove('show');
                setTimeout(() => {
                    if (!customDurationSection.classList.contains('show')) {
                        customDurationSection.style.display = 'none';
                    }
                }, 300);
                customDurationInput.value = '';
            }
            
            updateScreeningSummary();
        });
    });

    // Handle custom duration input
    customDurationInput.addEventListener('input', function() {
        updateScreeningSummary();
        
        // Update the custom option to show the entered value
        const customOption = document.querySelector('.duration-option[data-days="custom"]');
        if (customOption && this.value) {
            const numberDiv = customOption.querySelector('.duration-number');
            numberDiv.innerHTML = this.value;
            const unitDiv = customOption.querySelector('.duration-unit');
            unitDiv.textContent = this.value == 1 ? 'Day' : 'Days';
        } else if (customOption) {
            const numberDiv = customOption.querySelector('.duration-number');
            numberDiv.innerHTML = '<i class="fas fa-edit"></i>';
            const unitDiv = customOption.querySelector('.duration-unit');
            unitDiv.textContent = 'Custom';
        }
    });

    // Handle form submission
    submitBtn.addEventListener('click', function() {
        if (validateScreeningDeferForm()) {
            submitScreeningDeferral();
        }
    });

    function updateScreeningSummary() {
        const selectedType = deferralTypeSelect.value;
        const durationValue = durationSelect.value;
        const customDuration = customDurationInput.value;
        
        if (!selectedType) {
            durationSummary.style.display = 'none';
            return;
        }

        let summaryMessage = '';
        
        if (selectedType === 'Temporary Deferral') {
            let days = 0;
            if (durationValue && durationValue !== 'custom') {
                days = parseInt(durationValue);
            } else if (durationValue === 'custom' && customDuration) {
                days = parseInt(customDuration);
            }
            
            if (days > 0) {
                const endDate = new Date();
                endDate.setDate(endDate.getDate() + days);
                
                const dayText = days === 1 ? 'day' : 'days';
                summaryMessage = `Donor will be deferred for ${days} ${dayText} until ${endDate.toLocaleDateString()}.`;
            }
        } else if (selectedType === 'Permanent Deferral') {
            summaryMessage = 'Donor will be permanently deferred from future donations.';
        } else if (selectedType === 'Refuse') {
            summaryMessage = 'Donor donation will be refused for this session.';
        }

        if (summaryMessage) {
            summaryText.textContent = summaryMessage;
            durationSummary.style.display = 'block';
        } else {
            durationSummary.style.display = 'none';
        }
        
        // Update submit button state when summary changes
        updateScreeningDeferSubmitButtonState();
    }
    
    // Add validation event listeners
    if (disapprovalReasonSelect) {
        disapprovalReasonSelect.addEventListener('change', updateScreeningDeferValidation);
    }
    
    // Update validation when deferral type changes
    deferralTypeSelect.addEventListener('change', updateScreeningDeferSubmitButtonState);
    
    // Update validation when duration changes
    if (customDurationInput) {
        customDurationInput.addEventListener('input', updateScreeningDeferSubmitButtonState);
    }
    
    // Initial validation
    updateScreeningDeferValidation();
    
    console.log('Screening defer modal with validation initialized');
}

// Validate screening defer form
function validateScreeningDeferForm() {
    const selectedType = document.getElementById('deferralTypeSelect').value;
    const durationValue = document.getElementById('deferralDuration').value;
    const customDuration = document.getElementById('customDuration').value;
    const disapprovalReason = document.getElementById('disapprovalReason').value.trim();

    if (!selectedType) {
        showScreeningDeferToast('Validation Error', 'Please select a deferral type.', 'error');
        // Scroll to deferral type section
        document.getElementById('deferralTypeSelect').scrollIntoView({ behavior: 'smooth' });
        return false;
    }

    if (selectedType === 'Temporary Deferral') {
        if (!durationValue) {
            showScreeningDeferToast('Validation Error', 'Please select a duration for temporary deferral.', 'error');
            document.getElementById('durationSection').scrollIntoView({ behavior: 'smooth' });
            return false;
        }
        
        if (durationValue === 'custom' && (!customDuration || customDuration < 1)) {
            showScreeningDeferToast('Validation Error', 'Please enter a valid custom duration (minimum 1 day).', 'error');
            document.getElementById('customDuration').focus();
            return false;
        }

        if (durationValue === 'custom' && customDuration > 3650) {
            showScreeningDeferToast('Validation Error', 'Custom duration cannot exceed 3650 days (10 years).', 'error');
            document.getElementById('customDuration').focus();
            return false;
        }
    }

    if (!disapprovalReason) {
        showScreeningDeferToast('Validation Error', 'Please select a reason for the deferral.', 'error');
        document.getElementById('disapprovalReason').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('disapprovalReason').focus();
        return false;
    }

    return true;
}

// Submit screening deferral - includes medical history updates
async function submitScreeningDeferral() {
    const formData = new FormData(document.getElementById('deferDonorForm'));
    const submitBtn = document.getElementById('submitDeferral');
    const originalText = submitBtn.innerHTML;
    
    const donorId = formData.get('donor_id');
    const screeningId = formData.get('screening_id');
    const deferralType = document.getElementById('deferralTypeSelect').value;
    const disapprovalReason = formData.get('disapproval_reason');
    
    // Convert empty string to null for screening_id
    const finalScreeningId = screeningId && screeningId.trim() !== '' ? screeningId : null;
    
    // Calculate final duration
    let finalDuration = null;
    if (deferralType === 'Temporary Deferral') {
        const durationValue = document.getElementById('deferralDuration').value;
        if (durationValue === 'custom') {
            finalDuration = document.getElementById('customDuration').value;
        } else {
            finalDuration = durationValue;
        }
    }

    // Collect screening form data from the Initial Screening Form modal
    const screeningFormData = {
        body_weight: document.querySelector('input[name="body-wt"]')?.value || null,
        specific_gravity: document.querySelector('input[name="sp-gr"]')?.value || null,
        blood_type: document.querySelector('select[name="blood-type"]')?.value || null,
        donation_type: document.querySelector('select[name="donation-type"]')?.value || null,
        has_previous_donation: document.querySelector('input[name="history"]:checked')?.value === 'yes' || false,
        red_cross_donations: document.querySelector('input[name="red-cross"]')?.value || 0,
        hospital_donations: document.querySelector('input[name="hospital-history"]')?.value || 0,
        last_rc_donation_place: document.querySelector('input[name="last-rc-donation-place"]')?.value || null,
        last_hosp_donation_place: document.querySelector('input[name="last-hosp-donation-place"]')?.value || null,
        last_rc_donation_date: document.querySelector('input[name="last-rc-donation-date"]')?.value || null,
        last_hosp_donation_date: document.querySelector('input[name="last-hosp-donation-date"]')?.value || null,
        mobile_location: document.querySelector('input[name="mobile-location"]')?.value || null,
        mobile_organizer: document.querySelector('input[name="mobile-organizer"]')?.value || null,
        patient_name: document.querySelector('input[name="patient-name"]')?.value || null,
        hospital: document.querySelector('input[name="hospital"]')?.value || null,
        patient_blood_type: document.querySelector('select[name="patient-blood-type"]')?.value || null,
        component_type: document.querySelector('select[name="component-type"]')?.value || null,
        units_needed: document.querySelector('input[name="units-needed"]')?.value || null
    };

    console.log('Submitting screening deferral data:', {
        donor_id: donorId,
        screening_id: screeningId,
        deferral_type: deferralType,
        disapproval_reason: disapprovalReason,
        duration: finalDuration,
        screening_form_data: screeningFormData
    });

    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    submitBtn.disabled = true;

    try {
        // Fetch all source data
        console.log('Fetching comprehensive data for donor:', donorId);
        const allSourceData = await fetchAllSourceDataForScreeningDeferral(donorId);
        console.log('Fetched all source data:', allSourceData);
        console.log('Screening form data:', allSourceData.screeningForm);
        console.log('Medical history ID from screening form:', allSourceData.screeningForm?.medical_history_id);
        
        // Calculate temporary_deferred text based on deferral type
        let temporaryDeferredText;
        if (deferralType === 'Temporary Deferral' && finalDuration) {
            const days = parseInt(finalDuration);
            if (days > 0) {
                // Calculate months and remaining days
                const months = Math.floor(days / 30);
                const remainingDays = days % 30;
                
                if (months > 0 && remainingDays > 0) {
                    temporaryDeferredText = `${months} month${months > 1 ? 's' : ''} ${remainingDays} day${remainingDays > 1 ? 's' : ''}`;
                } else if (months > 0) {
                    temporaryDeferredText = `${months} month${months > 1 ? 's' : ''}`;
                } else {
                    temporaryDeferredText = `${days} day${days > 1 ? 's' : ''}`;
                }
            } else {
                temporaryDeferredText = 'Immediate';
            }
        } else if (deferralType === 'Permanent Deferral') {
            temporaryDeferredText = 'Permanent/Indefinite';
        } else if (deferralType === 'Refuse') {
            temporaryDeferredText = 'Session Refused';
        } else {
            temporaryDeferredText = 'Not specified';
        }
        
        // Prepare deferral data for create_eligibility.php
        const deferData = {
            action: 'create_eligibility_defer',
            donor_id: parseInt(donorId),
            screening_id: finalScreeningId || allSourceData.screeningForm?.screening_id || null,
            deferral_type: deferralType,
            disapproval_reason: disapprovalReason,
            duration: finalDuration,
            screening_form_data: screeningFormData // Pass the screening form data
        };
        
        console.log('Using create_eligibility with defer data:', deferData);
        
        // Validate required fields before sending
        if (!deferData.donor_id || !deferData.deferral_type || !deferData.disapproval_reason) {
            throw new Error(`Missing required fields: donor_id=${deferData.donor_id}, deferral_type=${deferData.deferral_type}, disapproval_reason=${deferData.disapproval_reason}`);
        }
        
        // Submit to create_eligibility.php endpoint
        const response = await fetch('../../assets/php_func/create_eligibility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(deferData)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Response text:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
        
        if (result.success) {
            console.log('Screening deferral recorded successfully:', result);
            
            // Update or create physical examination remarks and needs_review
            const physicalExamId = allSourceData.physicalExam?.physical_exam_id || null;
            const medicalHistoryId = allSourceData.screeningForm?.medical_history_id || null;
            console.log('Physical examination ID:', physicalExamId, 'Medical History ID:', medicalHistoryId, 'Donor ID:', donorId);
            await updateScreeningPhysicalExaminationAfterDeferral(physicalExamId, deferralType, donorId, medicalHistoryId);
            
            // Close the deferral modal
            const deferModal = bootstrap.Modal.getInstance(document.getElementById('deferDonorModal'));
            if (deferModal) {
                deferModal.hide();
            }
            
            // Wait for deferral modal to close, then show confirmation modal
            setTimeout(() => {
                showScreeningDeferralConfirmedModal();
            }, 300);
        } else {
            console.error('Failed to record screening deferral:', result.error || result.message);
            showScreeningDeferToast('Error', result.message || result.error || 'Failed to record deferral. Please try again.', 'error');
        }
        
    } catch (error) {
        console.error('Error processing screening deferral:', error);
        showScreeningDeferToast('Error', 'An error occurred while processing the deferral.', 'error');
    } finally {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Helper function to update or create physical examination and medical history after screening deferral
async function updateScreeningPhysicalExaminationAfterDeferral(physicalExamId, deferralType, donorId, medicalHistoryId = null) {
    // Determine remarks based on deferral type
    let remarks;
    if (deferralType === 'Temporary Deferral') {
        remarks = 'Temporarily Deferred';
    } else if (deferralType === 'Permanent Deferral') {
        remarks = 'Permanently Deferred';
    } else if (deferralType === 'Refuse') {
        remarks = 'Refused';
    } else {
        remarks = 'Deferred';
    }
    
    try {
        if (physicalExamId) {
            // Update existing physical examination record
            console.log('Updating existing physical examination:', {
                physical_exam_id: physicalExamId,
                remarks: remarks,
                needs_review: false
            });
            
            const response = await fetch('../api/update-physical-examination.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    physical_exam_id: physicalExamId,
                    remarks: remarks,
                    needs_review: false
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                console.log('Physical examination updated successfully:', result);
            } else {
                console.error('Failed to update physical examination:', result.error);
            }
        } else if (donorId) {
            // Create new physical examination record
            console.log('Creating new physical examination record:', {
                donor_id: donorId,
                remarks: remarks,
                needs_review: false
            });
            
            const response = await fetch('../api/create-physical-examination.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    remarks: remarks,
                    needs_review: false
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                console.log('Physical examination record created successfully:', result);
            } else {
                console.error('Failed to create physical examination record:', result.error);
            }
        } else {
            console.log('No physical_exam_id or donor_id available, skipping physical examination update/create');
        }
        
        // Update medical history if medical_history_id is available
        if (medicalHistoryId) {
            console.log('Updating medical history:', {
                medical_history_id: medicalHistoryId,
                needs_review: false
            });
            console.log('Medical history ID is valid, proceeding with update...');
            
            try {
                const response = await fetch('../api/update-medical-history.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        medical_history_id: medicalHistoryId,
                        needs_review: false,
                        medical_approval: 'Not Approved'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    console.log('Medical history updated successfully:', result);
                } else {
                    console.error('Failed to update medical history:', result.error);
                }
            } catch (error) {
                console.error('Error updating medical history:', error);
            }
        } else {
            console.log('No medical_history_id available, skipping medical history update');
            console.log('Medical history ID was:', medicalHistoryId);
            console.log('This means the screening form did not contain a medical_history_id');
        }
    } catch (error) {
        console.error('Error updating/creating physical examination:', error);
    }
}

// Show screening deferral confirmation modal
function showScreeningDeferralConfirmedModal() {
    // Clean up any existing backdrops first
    if (window.cleanupModalBackdrops) {
        window.cleanupModalBackdrops();
    }
    
    // Add CSS rule to ensure confirmation modal is always on top
    const style = document.createElement('style');
    style.textContent = `
        #deferralConfirmedModal {
            z-index: 99999 !important;
            position: fixed !important;
        }
        #deferralConfirmedModal .modal-dialog {
            z-index: 100000 !important;
        }
        #deferralConfirmedModal .modal-content {
            z-index: 100001 !important;
        }
    `;
    document.head.appendChild(style);
    
    // Show confirmation modal with highest priority
    const modalElement = document.getElementById('deferralConfirmedModal');
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: false
    });
    
    // Force the modal to the front with ultra-high z-index
    modalElement.style.zIndex = '99999';
    modalElement.style.position = 'fixed';
    modalElement.style.top = '0';
    modalElement.style.left = '0';
    modalElement.style.width = '100%';
    modalElement.style.height = '100%';
    
    // Also force the modal dialog and content to have high z-index
    const modalDialog = modalElement.querySelector('.modal-dialog');
    const modalContent = modalElement.querySelector('.modal-content');
    if (modalDialog) {
        modalDialog.style.zIndex = '100000';
    }
    if (modalContent) {
        modalContent.style.zIndex = '100001';
    }
    
    modal.show();
    
    // Add event listener for when modal is closed to reload page
    modalElement.addEventListener('hidden.bs.modal', function() {
        console.log('Screening deferral confirmation modal closed, reloading page...');
        // Clean up backdrops before reload
        if (window.cleanupModalBackdrops) {
            window.cleanupModalBackdrops();
        }
        window.location.reload();
    }, { once: true });
}

// Show screening defer toast notification
function showScreeningDeferToast(title, message, type = 'success') {
    // Remove existing toasts
    document.querySelectorAll('.screening-defer-toast').forEach(toast => {
        toast.remove();
    });

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `screening-defer-toast screening-defer-toast-${type}`;
    
    const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    
    toast.innerHTML = `
        <div class="screening-defer-toast-content">
            <i class="${icon}"></i>
            <div class="screening-defer-toast-text">
                <div class="screening-defer-toast-title">${title}</div>
                <div class="screening-defer-toast-message">${message}</div>
            </div>
        </div>
    `;

    // Add to page
    document.body.appendChild(toast);

    // Show toast
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);

    // Auto-hide toast
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 400);
    }, 4000);
}

// Open screening defer modal
function openScreeningDeferModal(screeningData) {
    // Set the hidden fields
    document.getElementById('defer-donor-id').value = screeningData.donor_form_id || '';
    document.getElementById('defer-screening-id').value = screeningData.screening_id || '';
    
    // Reset form
    document.getElementById('deferDonorForm').reset();
    
    // Set default values
    document.getElementById('deferralTypeSelect').value = 'Temporary Deferral';
    document.getElementById('deferralDuration').value = '2';
    document.querySelector('.duration-option[data-days="2"]').classList.add('active');
    
    // Show duration section since Temporary Deferral is pre-selected
    const durationSection = document.getElementById('durationSection');
    const customDurationSection = document.getElementById('customDurationSection');
    
    durationSection.style.display = 'block';
    customDurationSection.style.display = 'none';
    document.getElementById('durationSummary').style.display = 'none';
    
    // Reset all visual elements
    document.querySelectorAll('.deferral-card').forEach(card => {
        card.classList.remove('active');
    });
    
    document.querySelectorAll('.duration-option').forEach(option => {
        option.classList.remove('active');
    });
    
    // Reset custom duration display
    const customOption = document.querySelector('.duration-option[data-days="custom"]');
    if (customOption) {
        const numberDiv = customOption.querySelector('.duration-number');
        numberDiv.innerHTML = '<i class="fas fa-edit"></i>';
        const unitDiv = customOption.querySelector('.duration-unit');
        unitDiv.textContent = 'Custom';
    }
    
    // Clear any validation states
    document.querySelectorAll('.form-control').forEach(control => {
        control.classList.remove('is-invalid', 'is-valid');
    });
    
    // Show the modal
    const deferModal = new bootstrap.Modal(document.getElementById('deferDonorModal'));
    deferModal.show();
    
    // Re-initialize defer modal functionality when it opens
    setTimeout(() => {
        initializeScreeningDeferModal();
    }, 200);
}

// Handle screening defer button click
function handleScreeningDeferDonor() {
    console.log('Screening defer donor button clicked');
    const donorIdInput = document.querySelector('input[name="donor_id"]');
    const donorId = donorIdInput ? donorIdInput.value : null;
    
    if (!donorId) {
        console.error('No donor ID found in screening form');
        showScreeningDeferToast('Error', 'No donor ID found. Please try again.', 'error');
        return;
    }
    
    console.log('Opening screening defer modal for donor ID:', donorId);
    
    const screeningModal = document.getElementById('screeningFormModal');
    if (screeningModal) {
        if (window.closeModalSafely) {
            window.closeModalSafely(screeningModal);
        } else {
            const modal = bootstrap.Modal.getInstance(screeningModal);
            if (modal) {
                modal.hide();
            }
        }
    }
    
    setTimeout(() => {
        const screeningData = {
            donor_form_id: donorId,
            screening_id: null // Will be fetched from API
        };
        openScreeningDeferModal(screeningData);
    }, 300);
}

// Export functions for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeScreeningDeferModal,
        validateScreeningDeferForm,
        submitScreeningDeferral,
        showScreeningDeferToast,
        openScreeningDeferModal,
        handleScreeningDeferDonor,
        fetchAllSourceDataForScreeningDeferral
    };
}
