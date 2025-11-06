// Defer Donor Modal JavaScript Functions
// This file handles all defer donor functionality for the physical examination dashboard

// Global variables for defer modal state
let deferModalData = null;

// Note: fetchAllSourceData, fetchScreeningFormData, fetchPhysicalExamData, and fetchDonorFormData
// are imported from medical-history-approval.js to avoid duplication

// Comprehensive function to fetch data from all source tables for physical examination deferral
async function fetchAllSourceData(donorId) {
    try {
        const [screeningForm, physicalExam, donorForm] = await Promise.all([
            fetchScreeningFormData(donorId),
            fetchPhysicalExamData(donorId),
            fetchDonorFormData(donorId)
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

// Fetch data from screening_form table for physical examination deferral
async function fetchScreeningFormData(donorId) {
    try {
        const response = await fetch(`../api/get-screening-form.php?donor_id=${donorId}`);
        const data = await response.json();
        
        if (data.success && data.screening_form) {
            return data.screening_form;
        }
        return null;
    } catch (error) {
        console.error('Error fetching screening form data:', error);
        return null;
    }
}

// Fetch data from physical_examination table for physical examination deferral
async function fetchPhysicalExamData(donorId) {
    try {
        const response = await fetch(`../api/get-physical-examination.php?donor_id=${donorId}`);
        const data = await response.json();
        
        if (data.success && data.physical_exam) {
            return data.physical_exam;
        }
        return null;
    } catch (error) {
        console.error('Error fetching physical exam data:', error);
        return null;
    }
}

// Fetch data from donor_form table for physical examination deferral
async function fetchDonorFormData(donorId) {
    try {
        const response = await fetch(`../api/get-donor-form.php?donor_id=${donorId}`);
        const data = await response.json();
        
        if (data.success && data.donor_form) {
            return data.donor_form;
        }
        return null;
    } catch (error) {
        console.error('Error fetching donor form data:', error);
        return null;
    }
}

// Initialize defer modal functionality
function initializeDeferModal() {
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

    // Update disapproval reason validation
    function updateDeferValidation() {
        if (!disapprovalReasonSelect) return;
        
        const hasReason = !!disapprovalReasonSelect.value;
        
        // Update validation feedback
        if (!hasReason) {
            disapprovalReasonSelect.classList.remove('is-valid', 'is-invalid');
        } else {
            disapprovalReasonSelect.classList.add('is-valid');
            disapprovalReasonSelect.classList.remove('is-invalid');
        }
        
        // Update submit button state
        updateDeferSubmitButtonState();
    }
    
    // Update submit button state based on all form validation
    function updateDeferSubmitButtonState() {
        if (!disapprovalReasonSelect) return;
        
        const reasonValid = !!disapprovalReasonSelect.value;
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
            submitBtn.style.color = 'white';
        } else {
            submitBtn.style.backgroundColor = '#6c757d';
            submitBtn.style.borderColor = '#6c757d';
            submitBtn.style.color = 'white';
        }
    }

    // Handle deferral type change
    deferralTypeSelect.addEventListener('change', function() {
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
            updateSummary();
    });

    // Note: Card click handlers removed since we're using dropdown now

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
            
            updateSummary();
        });
    });

    // Handle custom duration input
    customDurationInput.addEventListener('input', function() {
        updateSummary();
        
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
        if (validateDeferForm()) {
            submitDeferral();
        }
    });

    function updateSummary() {
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
        updateDeferSubmitButtonState();
    }
    
    // Add validation event listeners
    if (disapprovalReasonSelect) {
        disapprovalReasonSelect.addEventListener('change', updateDeferValidation);
    }
    
    // Update validation when deferral type changes
    deferralTypeSelect.addEventListener('change', updateDeferSubmitButtonState);
    
    // Update validation when duration changes
    if (customDurationInput) {
        customDurationInput.addEventListener('input', updateDeferSubmitButtonState);
    }
    
    // Initial validation
    updateDeferValidation();
    
}

// Validate defer form
function validateDeferForm() {
    const selectedType = document.getElementById('deferralTypeSelect').value;
    const durationValue = document.getElementById('deferralDuration').value;
    const customDuration = document.getElementById('customDuration').value;
    const disapprovalReason = document.getElementById('disapprovalReason').value;

    if (!selectedType) {
        showDeferToast('Validation Error', 'Please select a deferral type.', 'error');
        // Scroll to deferral type section
        document.getElementById('deferralTypeSelect').scrollIntoView({ behavior: 'smooth' });
        return false;
    }

    if (selectedType === 'Temporary Deferral') {
        const durationSection = document.getElementById('durationSection');
        // Only validate duration if duration section is visible (physical examination mode)
        if (durationSection.style.display !== 'none') {
            if (!durationValue) {
                showDeferToast('Validation Error', 'Please select a duration for temporary deferral.', 'error');
                document.getElementById('durationSection').scrollIntoView({ behavior: 'smooth' });
                return false;
            }
            
            if (durationValue === 'custom' && (!customDuration || customDuration < 1)) {
                showDeferToast('Validation Error', 'Please enter a valid custom duration (minimum 1 day).', 'error');
                document.getElementById('customDuration').focus();
                return false;
            }

            if (durationValue === 'custom' && customDuration > 3650) {
                showDeferToast('Validation Error', 'Custom duration cannot exceed 3650 days (10 years).', 'error');
                document.getElementById('customDuration').focus();
                return false;
            }
        }
    }

    if (!disapprovalReason) {
        showDeferToast('Validation Error', 'Please select a reason for the deferral.', 'error');
        document.getElementById('disapprovalReason').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('disapprovalReason').focus();
        return false;
    }

    return true;
}

// Submit deferral - using same approach as medical history decline
async function submitDeferral() {
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
        const durationSection = document.getElementById('durationSection');
        // Only calculate duration if duration section is visible (physical examination mode)
        if (durationSection.style.display !== 'none') {
            const durationValue = document.getElementById('deferralDuration').value;
            if (durationValue === 'custom') {
                finalDuration = document.getElementById('customDuration').value;
            } else {
                finalDuration = durationValue;
            }
        } else {
            // For initial screening, use a default duration of 2 days
            finalDuration = '2';
        }
    }

    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    submitBtn.disabled = true;

    try {
        // Fetch all source data (same as medical history decline)
        const allSourceData = await fetchAllSourceData(donorId);
        
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
        
        // Prepare eligibility data (same structure as medical history decline)
        const eligibilityData = {
            donor_id: parseInt(donorId),
            medical_history_id: allSourceData.screeningForm?.medical_history_id || null,
            screening_id: finalScreeningId || allSourceData.screeningForm?.screening_id || null,
            physical_exam_id: allSourceData.physicalExam?.physical_exam_id || null,
            blood_collection_id: null, // Only field allowed to be null
            blood_type: allSourceData.screeningForm?.blood_type || null,
            donation_type: allSourceData.screeningForm?.donation_type || null,
            blood_bag_type: allSourceData.screeningForm?.blood_bag_type || allSourceData.physicalExam?.blood_bag_type || 'Deferred',
            blood_bag_brand: allSourceData.screeningForm?.blood_bag_brand || 'Deferred',
            amount_collected: 0, // Default for deferred donors
            collection_successful: false, // Default for deferred donors
            donor_reaction: 'Deferred',
            management_done: 'Donor marked as deferred',
            collection_start_time: null,
            collection_end_time: null,
            unit_serial_number: null,
            disapproval_reason: disapprovalReason,
            start_date: new Date().toISOString(),
            end_date: deferralType === 'Temporary Deferral' && finalDuration ? 
                new Date(Date.now() + parseInt(finalDuration) * 24 * 60 * 60 * 1000).toISOString() : null,
            status: deferralType === 'Temporary Deferral' ? 'temporary deferred' : 
                   deferralType === 'Permanent Deferral' ? 'permanently deferred' : 'refused',
            registration_channel: allSourceData.donorForm?.registration_channel || 'PRC Portal',
            blood_pressure: allSourceData.physicalExam?.blood_pressure || null,
            pulse_rate: allSourceData.physicalExam?.pulse_rate || null,
            body_temp: allSourceData.physicalExam?.body_temp || null,
            gen_appearance: allSourceData.physicalExam?.gen_appearance || null,
            skin: allSourceData.physicalExam?.skin || null,
            heent: allSourceData.physicalExam?.heent || null,
            heart_and_lungs: allSourceData.physicalExam?.heart_and_lungs || null,
            body_weight: allSourceData.screeningForm?.body_weight || allSourceData.physicalExam?.body_weight || null,
            temporary_deferred: temporaryDeferredText,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
        };
        
        
        // Submit to update-eligibility endpoint (same as medical history decline)
        const response = await fetch('../api/update-eligibility.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
            body: JSON.stringify(eligibilityData)
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
            
            // Update or create physical examination remarks and needs_review
            const physicalExamId = allSourceData.physicalExam?.physical_exam_id || null;
            await updatePhysicalExaminationAfterDeferral(physicalExamId, deferralType, donorId);
            
            // Close only the deferral modal, keep physical examination modal open
            const deferModal = bootstrap.Modal.getInstance(document.getElementById('deferDonorModal'));
            if (deferModal) {
            deferModal.hide();
            }
            
            // Wait for deferral modal to close, then show confirmation modal on top
            setTimeout(() => {
                showDeferralConfirmedModal();
            }, 300);
        } else {
            console.error('Failed to record deferral:', result.error || result.message);
            showDeferToast('Error', result.message || result.error || 'Failed to record deferral. Please try again.', 'error');
        }
        
    } catch (error) {
        console.error('Error processing deferral:', error);
        showDeferToast('Error', 'An error occurred while processing the deferral.', 'error');
    } finally {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Helper function to update or create physical examination after deferral
async function updatePhysicalExaminationAfterDeferral(physicalExamId, deferralType, donorId) {
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
            
            if (!result.success) {
                console.error('Failed to update physical examination:', result.error);
            }
        } else if (donorId) {
            // Create new physical examination record
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
            
            if (!result.success) {
                console.error('Failed to create physical examination record:', result.error);
            }
        }
    } catch (error) {
        console.error('Error updating/creating physical examination:', error);
    }
}

// Show deferral confirmation modal
function showDeferralConfirmedModal() {
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
    
    // Show confirmation modal with highest priority - keep physical examination modal open
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
    document.getElementById('deferralConfirmedModal').addEventListener('hidden.bs.modal', function() {
        window.location.reload();
    }, { once: true });
}

// Show defer toast notification
function showDeferToast(title, message, type = 'success') {
    // Remove existing toasts
    document.querySelectorAll('.defer-toast').forEach(toast => {
        toast.remove();
    });

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `defer-toast defer-toast-${type}`;
    
    const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
    
    toast.innerHTML = `
        <div class="defer-toast-content">
            <i class="${icon}"></i>
            <div class="defer-toast-text">
                <div class="defer-toast-title">${title}</div>
                <div class="defer-toast-message">${message}</div>
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

// Open defer modal
function openDeferModal(screeningData, source = 'physical') {
    // Set the hidden fields
    document.getElementById('defer-donor-id').value = screeningData.donor_form_id || '';
    document.getElementById('defer-screening-id').value = screeningData.screening_id || '';
    
    // Reset form
    document.getElementById('deferDonorForm').reset();
    
    // Configure modal based on source
    configureDeferModalForSource(source);
    
    // Hide conditional sections
    const durationSection = document.getElementById('durationSection');
    const customDurationSection = document.getElementById('customDurationSection');
    
    durationSection.classList.remove('show');
    customDurationSection.classList.remove('show');
    durationSection.style.display = 'none';
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
        initializeDeferModal();
    }, 200);
}

// Configure defer modal based on source (physical examination vs initial screening)
function configureDeferModalForSource(source) {
    const deferralTypeSelect = document.getElementById('deferralTypeSelect');
    const permanentOption = document.getElementById('permanentOption');
    const deferralTypeHelp = document.getElementById('deferralTypeHelp');
    const durationSection = document.getElementById('durationSection');
    
    // Get all reason options
    const screeningReasons = document.querySelectorAll('.screening-reason');
    const physicalReasons = document.querySelectorAll('.physical-reason');
    
    if (source === 'physical') {
        // Physical examination mode - show both permanent and temporary options
        permanentOption.style.display = 'block';
        deferralTypeSelect.value = '';
        deferralTypeHelp.textContent = 'Please select a deferral type.';
        
        // Show physical examination reasons, hide screening reasons
        screeningReasons.forEach(option => option.style.display = 'none');
        physicalReasons.forEach(option => option.style.display = 'block');
        
        // Duration section is always visible for physical examination
        durationSection.style.display = 'block';
        
    } else {
        // Initial screening mode - only temporary deferral, no duration needed
        permanentOption.style.display = 'none';
        deferralTypeSelect.value = '';
        deferralTypeHelp.textContent = 'Please select a deferral type.';
        
        // Show screening reasons, hide physical examination reasons
        screeningReasons.forEach(option => option.style.display = 'block');
        physicalReasons.forEach(option => option.style.display = 'none');
        
        // Hide duration section for initial screening
        durationSection.style.display = 'none';
    }
}

// Handle defer button click from physical examination modal
function handleDeferClick(e) {
    console.log('Defer button clicked!');
    e.preventDefault();
    
    // Get current screening data from the physical examination modal
    const donorId = document.getElementById('physical-donor-id')?.value;
    const screeningId = document.getElementById('physical-screening-id')?.value;
    
    console.log('Donor ID:', donorId, 'Screening ID:', screeningId);
    
    if (!donorId || !screeningId) {
        console.error('Missing donor or screening ID');
        showDeferToast('Error', 'Unable to get donor information. Please try again.', 'error');
        return;
    }
    
    const screeningData = {
        donor_form_id: donorId,
        screening_id: screeningId
    };
    
    console.log('Opening defer modal with data:', screeningData);
    openDeferModal(screeningData, 'physical');
}

// Initialize defer button in physical examination modal
function initializePhysicalExamDeferButton() {
    console.log('Initializing physical exam defer button...');
    
    // Since this is called after modal is shown, we can try immediately
    const physicalDeferBtn = document.querySelector('.physical-defer-btn');
    
    if (physicalDeferBtn) {
        console.log('Physical defer button found, attaching event listener');
        
        // Remove any existing event listeners
        physicalDeferBtn.removeEventListener('click', handleDeferClick);
        
        // Add the event listener
        physicalDeferBtn.addEventListener('click', handleDeferClick);
        
        console.log('Event listener attached to defer button');
    } else {
        console.error('Physical defer button not found in DOM, trying with timeout...');
        // Try again after a short delay in case DOM isn't fully ready
        setTimeout(() => {
            const retryBtn = document.querySelector('.physical-defer-btn');
            if (retryBtn) {
                console.log('Physical defer button found on retry, attaching event listener');
                retryBtn.removeEventListener('click', handleDeferClick);
                retryBtn.addEventListener('click', handleDeferClick);
            } else {
                console.error('Physical defer button still not found after retry');
            }
        }, 100);
    }
}

// Export functions for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeDeferModal,
        validateDeferForm,
        submitDeferral,
        showDeferToast,
        openDeferModal,
        handleDeferClick,
        initializePhysicalExamDeferButton
    };
}
