/**
 * Medical History Decline Handler
 * This file handles medical history decline with full database operations:
 * - Updates medical_history table
 * - Creates/updates eligibility record
 * - Creates/updates physical examination record
 * Similar to defer donor functionality
 */

// Fetch all source data for medical history decline
async function fetchAllSourceDataForMHDecline(donorId) {
    try {
        const [screeningForm, physicalExam, donorForm] = await Promise.all([
            fetchScreeningFormDataForMHDecline(donorId),
            fetchPhysicalExamDataForMHDecline(donorId),
            fetchDonorFormDataForMHDecline(donorId)
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

// Fetch screening form data
async function fetchScreeningFormDataForMHDecline(donorId) {
    try {
        // Try to get base path from current location
        const basePath = window.location.pathname.includes('/Dashboards/') ? '../api/' : '../../api/';
        const response = await fetch(`${basePath}get-screening-form.php?donor_id=${donorId}`);
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

// Fetch physical examination data
async function fetchPhysicalExamDataForMHDecline(donorId) {
    try {
        // Try to get base path from current location
        const basePath = window.location.pathname.includes('/Dashboards/') ? '../api/' : '../../api/';
        const response = await fetch(`${basePath}get-physical-examination.php?donor_id=${donorId}`);
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

// Fetch donor form data
async function fetchDonorFormDataForMHDecline(donorId) {
    try {
        // Try to get base path from current location
        const basePath = window.location.pathname.includes('/Dashboards/') ? '../api/' : '../../api/';
        const response = await fetch(`${basePath}get-donor-form.php?donor_id=${donorId}`);
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

// Process medical history decline with full database operations
async function processMedicalHistoryDeclineFull(declineReason, restrictionType, duration, endDate, donorId, screeningId = null) {
    try {
        // Show loading state
        const submitBtn = document.getElementById('submitDeclineBtn');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;
        }
        
        console.log('Processing medical history decline:', {
            donor_id: donorId,
            decline_reason: declineReason,
            restriction_type: restrictionType,
            duration: duration,
            end_date: endDate,
            screening_id: screeningId
        });
        
        // Fetch all source data (same as defer donor)
        const allSourceData = await fetchAllSourceDataForMHDecline(donorId);
        
        // Calculate temporary_deferred text (same as defer donor)
        let temporaryDeferredText = null;
        if (restrictionType === 'temporary' && duration) {
            const days = parseInt(duration);
            if (days > 0) {
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
                temporaryDeferredText = 'Not specified';
            }
        } else if (restrictionType === 'permanent') {
            temporaryDeferredText = 'Permanent/Indefinite';
        } else {
            temporaryDeferredText = 'Not specified';
        }
        
        // Prepare eligibility data (same structure as defer donor)
        const eligibilityData = {
            donor_id: parseInt(donorId),
            medical_history_id: allSourceData.screeningForm?.medical_history_id || null,
            screening_id: screeningId || allSourceData.screeningForm?.screening_id || null,
            physical_exam_id: allSourceData.physicalExam?.physical_exam_id || null,
            blood_collection_id: null, // Only field allowed to be null
            blood_type: allSourceData.screeningForm?.blood_type || null,
            donation_type: allSourceData.screeningForm?.donation_type || null,
            blood_bag_type: allSourceData.screeningForm?.blood_bag_type || allSourceData.physicalExam?.blood_bag_type || 'Declined - Medical History',
            blood_bag_brand: allSourceData.screeningForm?.blood_bag_brand || 'Declined - Medical History',
            amount_collected: 0, // Default for declined donors
            collection_successful: false, // Default for declined donors
            donor_reaction: 'Declined - Medical History',
            management_done: 'Donor marked as ineligible due to medical history decline',
            collection_start_time: null,
            collection_end_time: null,
            unit_serial_number: null,
            disapproval_reason: declineReason,
            start_date: new Date().toISOString(),
            end_date: endDate || (restrictionType === 'temporary' && duration ? 
                new Date(Date.now() + parseInt(duration) * 24 * 60 * 60 * 1000).toISOString() : null),
            status: restrictionType === 'temporary' ? 'temporary deferred' : 
                   restrictionType === 'permanent' ? 'permanently deferred' : 'declined',
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
        
        // Submit to update-eligibility endpoint (same as defer donor)
        const basePath = window.location.pathname.includes('/Dashboards/') ? '../api/' : '../../api/';
        const response = await fetch(`${basePath}update-eligibility.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(eligibilityData)
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error response:', errorText);
            throw new Error(`HTTP error! status: ${response.status} - ${errorText.substring(0, 200)}`);
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
            console.log('Eligibility record created/updated successfully:', result);
            
            // Update medical_history table (decline reason and approval status)
            // Use the same API endpoint but with all required fields
            const medicalHistoryUpdate = {
                donor_id: parseInt(donorId),
                medical_history_id: allSourceData.screeningForm?.medical_history_id || null,
                medical_approval: 'Not Approved',
                needs_review: false,
                decline_reason: declineReason,
                decline_date: new Date().toISOString(),
                restriction_type: restrictionType,
                deferral_duration: duration || null,
                deferral_end_date: endDate || null,
                updated_at: new Date().toISOString()
            };
            
            // Update medical history via API
            const mhUpdatePath = window.location.pathname.includes('/Dashboards/') ? '../api/' : '../../api/';
            const mhResponse = await fetch(`${mhUpdatePath}update-medical-history.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(medicalHistoryUpdate)
            });
            
            if (mhResponse.ok) {
                const mhResult = await mhResponse.json();
                if (mhResult.success) {
                    console.log('Medical history updated successfully');
                } else {
                    console.warn('Failed to update medical history:', mhResult.error);
                }
            } else {
                const errorText = await mhResponse.text();
                console.warn('Failed to update medical history:', mhResponse.status, errorText.substring(0, 200));
            }
            
            // Update or create physical examination (same as defer donor)
            const physicalExamId = allSourceData.physicalExam?.physical_exam_id || null;
            await updatePhysicalExaminationAfterDecline(physicalExamId, restrictionType, donorId, declineReason);
            
            // Don't close decline modal or medical history modal - let them stick
            // Don't reopen donor profile - just show success modal and reload
            
            // Show success modal (informational, auto-reloads)
            setTimeout(() => {
                showMedicalHistoryDeclinedSuccessModal();
            }, 300);
            
            return result;
        } else {
            throw new Error(result.error || 'Unknown error occurred');
        }
        
    } catch (error) {
        console.error('Error processing medical history decline:', error);
        
        // Reset button state
        const submitBtn = document.getElementById('submitDeclineBtn');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-ban me-2"></i>Decline Medical History';
            submitBtn.disabled = false;
        }
        
        // Show error message
        if (typeof showMedicalHistoryToast === 'function') {
            showMedicalHistoryToast('Error', error.message || 'An error occurred while processing the decline.', 'error');
        } else {
            alert('Error: ' + (error.message || 'An error occurred while processing the decline.'));
        }
        
        throw error;
    }
}

// Helper function to update or create physical examination after decline (same as defer donor)
async function updatePhysicalExaminationAfterDecline(physicalExamId, restrictionType, donorId, declineReason) {
    // Determine remarks based on restriction type (same as defer donor)
    let remarks;
    if (restrictionType === 'temporary') {
        remarks = 'Temporarily Deferred';
    } else if (restrictionType === 'permanent') {
        remarks = 'Permanently Deferred';
    } else {
        remarks = 'Declined';
    }
    
    try {
        const basePath = window.location.pathname.includes('/Dashboards/') ? '../api/' : '../../api/';
        
        if (physicalExamId) {
            // Update existing physical examination record (same as defer donor)
            const response = await fetch(`${basePath}update-physical-examination.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    physical_exam_id: physicalExamId,
                    remarks: remarks,
                    needs_review: false,
                    disapproval_reason: declineReason
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                console.log('Physical examination updated successfully');
                return true;
            } else {
                console.warn('Failed to update physical examination:', result.error);
                return false;
            }
        } else if (donorId) {
            // Create new physical examination record (same as defer donor)
            const response = await fetch(`${basePath}create-physical-examination.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    remarks: remarks,
                    needs_review: false,
                    disapproval_reason: declineReason
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                console.log('Physical examination created successfully');
                return true;
            } else {
                console.warn('Failed to create physical examination:', result.error);
                return false;
            }
        }
    } catch (error) {
        console.error('Error updating/creating physical examination:', error);
        return false;
    }
}

// Show confirmation modal before declining
function showMedicalHistoryDeclineConfirmModal(onConfirm) {
    const confirmEl = document.getElementById('medicalHistoryDeclineConfirmModal');
    if (!confirmEl) {
        // If modal doesn't exist, proceed directly
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
        return false;
    }
    
    const m = new bootstrap.Modal(confirmEl, {
        backdrop: true,
        keyboard: true
    });
    
    // Set very high z-index to appear above all other modals (higher than decline modal, physical exam, donor profile)
    confirmEl.style.zIndex = '10100';
    confirmEl.style.position = 'fixed';
    const dlg = confirmEl.querySelector('.modal-dialog');
    if (dlg) dlg.style.zIndex = '10101';
    const content = confirmEl.querySelector('.modal-content');
    if (content) content.style.zIndex = '10102';
    
    // Ensure backdrop is behind this modal
    setTimeout(() => {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
            backdrop.style.zIndex = '10099';
        });
    }, 10);
    
    try {
        m.show();
    } catch (_) {}
    
    // After modal is shown, ensure z-index is still correct
    confirmEl.addEventListener('shown.bs.modal', function() {
        confirmEl.style.zIndex = '10100';
        confirmEl.style.position = 'fixed';
        if (dlg) dlg.style.zIndex = '10101';
        if (content) content.style.zIndex = '10102';
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
            backdrop.style.zIndex = '10099';
        });
    }, { once: true });
    
    const confirmBtn = document.getElementById('confirmDeclineMedicalHistoryBtn');
    if (confirmBtn) {
        // Remove any existing listeners
        const newBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
        
        newBtn.addEventListener('click', () => {
            try {
                m.hide();
            } catch (_) {}
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });
    }
    
    return true;
}

// Show success modal after successful decline (text only, auto-reloads)
function showMedicalHistoryDeclinedSuccessModal() {
    const successEl = document.getElementById('medicalHistoryDeclinedSuccessModal');
    if (!successEl) {
        // Fallback: just reload immediately
        window.location.reload();
        return;
    }
    
    const m = new bootstrap.Modal(successEl, {
        backdrop: 'static',
        keyboard: false
    });
    
    // Set very high z-index to appear above everything
    successEl.style.zIndex = '10080';
    const dlg = successEl.querySelector('.modal-dialog');
    if (dlg) dlg.style.zIndex = '10081';
    const content = successEl.querySelector('.modal-content');
    if (content) content.style.zIndex = '10082';
    
    // Ensure backdrop is behind this modal
    setTimeout(() => {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
            backdrop.style.zIndex = '10079';
        });
    }, 10);
    
    try {
        m.show();
    } catch (_) {}
    
    // Auto-reload after 2 seconds (no button needed)
    setTimeout(() => {
        window.location.reload();
    }, 2000);
}

// Show validation error modal (matching Confirm Action design)
function showMedicalHistoryValidationModal(message) {
    const modalEl = document.getElementById('medicalHistoryValidationModal');
    const messageEl = document.getElementById('medicalHistoryValidationMessage');
    
    if (!modalEl) {
        // Fallback to alert if modal doesn't exist
        alert(message);
        return;
    }
    
    // Set message
    if (messageEl) {
        messageEl.textContent = message;
    }
    
    // Show modal
    const modal = new bootstrap.Modal(modalEl, {
        backdrop: true,
        keyboard: true
    });
    
    // Set z-index to appear above other modals
    modalEl.style.zIndex = '10110';
    modalEl.style.position = 'fixed';
    
    // Ensure backdrop is behind this modal
    setTimeout(() => {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
            backdrop.style.zIndex = '10109';
        });
    }, 10);
    
    try {
        modal.show();
    } catch (_) {}
    
    // Focus OK button for accessibility
    const okBtn = document.getElementById('medicalHistoryValidationOkBtn');
    if (okBtn) {
        // Auto-focus after modal is shown
        modalEl.addEventListener('shown.bs.modal', function() {
            okBtn.focus();
        }, { once: true });
    }
}

// Handle medical history decline form submission
async function handleMedicalHistoryDeclineSubmit() {
    const declineReason = document.getElementById('declineReason');
    const restrictionType = document.getElementById('restrictionType');
    const durationSelect = document.getElementById('mhDeclineDuration');
    const customDurationInput = document.getElementById('customDuration');
    
    // Validation
    if (!declineReason || !declineReason.value.trim()) {
        showMedicalHistoryValidationModal('Please provide a reason for declining.');
        if (declineReason) declineReason.focus();
        return;
    }
    
    if (declineReason.value.trim().length < 10) {
        showMedicalHistoryValidationModal('Please provide a more detailed reason (minimum 10 characters).');
        declineReason.focus();
        return;
    }
    
    if (!restrictionType || !restrictionType.value) {
        showMedicalHistoryValidationModal('Please select a deferral type.');
        if (restrictionType) restrictionType.focus();
        return;
    }
    
    // Calculate final duration
    let finalDuration = null;
    let endDate = null;
    
    if (restrictionType.value === 'temporary') {
        const durationSection = document.getElementById('mhDurationSection');
        if (durationSection && durationSection.style.display !== 'none') {
            const durationValue = durationSelect ? durationSelect.value : '';
            if (durationValue === 'custom') {
                if (!customDurationInput || !customDurationInput.value) {
                    showMedicalHistoryValidationModal('Please enter a custom duration in days.');
                    if (customDurationInput) customDurationInput.focus();
                    return;
                }
                finalDuration = customDurationInput.value;
            } else if (durationValue) {
                finalDuration = durationValue;
            } else {
                showMedicalHistoryValidationModal('Please select a deferral duration.');
                return;
            }
            
            // Calculate end_date from duration
            if (finalDuration) {
                endDate = new Date(Date.now() + parseInt(finalDuration) * 24 * 60 * 60 * 1000).toISOString();
            }
        } else {
            showMedicalHistoryValidationModal('Please select a deferral duration.');
            return;
        }
    }
    
    // Get donor ID from various sources
    let donorId = null;
    const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"], #medicalHistoryModal input[name="donor_id"]');
    if (donorIdInput) {
        donorId = donorIdInput.value;
    } else if (window.currentDonorId) {
        donorId = window.currentDonorId;
    } else if (window.currentMedicalHistoryData && window.currentMedicalHistoryData.donor_id) {
        donorId = window.currentMedicalHistoryData.donor_id;
    }
    
    if (!donorId) {
        showMedicalHistoryValidationModal('Error: Donor ID not found. Please try again.');
        return;
    }
    
    // Get screening_id if available
    let screeningId = null;
    const screeningIdInput = document.querySelector('#modalMedicalHistoryForm input[name="screening_id"], #medicalHistoryModal input[name="screening_id"]');
    if (screeningIdInput && screeningIdInput.value) {
        screeningId = screeningIdInput.value;
    }
    
    // Show confirmation modal first
    showMedicalHistoryDeclineConfirmModal(async () => {
        // User confirmed, proceed with decline
        await processMedicalHistoryDeclineFull(
            declineReason.value.trim(),
            restrictionType.value,
            finalDuration,
            endDate,
            donorId,
            screeningId
        );
    });
}

// Initialize medical history decline handler
function initializeMedicalHistoryDeclineHandler() {
    // This function can be called to set up the decline handler
    // The submit button is already wired up in the modal JavaScript
    console.log('Medical history decline handler initialized');
}

// Export functions for use in other files
if (typeof window !== 'undefined') {
    window.handleMedicalHistoryDeclineSubmit = handleMedicalHistoryDeclineSubmit;
    window.processMedicalHistoryDeclineFull = processMedicalHistoryDeclineFull;
    window.initializeMedicalHistoryDeclineHandler = initializeMedicalHistoryDeclineHandler;
}

