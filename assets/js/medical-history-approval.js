// Medical History Approval JavaScript Functions
// This file handles all medical history approval and decline functionality

// Global variables (avoid redeclaration if script is loaded more than once)
if (typeof window.currentMedicalHistoryData === 'undefined') {
    window.currentMedicalHistoryData = null;
}
// Ensure identifier exists in this scope even if file is included multiple times
if (typeof currentMedicalHistoryData === 'undefined') {
    var currentMedicalHistoryData = window.currentMedicalHistoryData;
}

// Initialize medical history approval functionality
function initializeMedicalHistoryApproval() {
    console.log('Initializing medical history approval functionality...');
    
    // Check if modals are loaded
    const declineModal = document.getElementById('medicalHistoryDeclineModal');
    const approvalModal = document.getElementById('medicalHistoryApprovalModal');
    
    console.log('Decline modal found:', declineModal);
    console.log('Approval modal found:', approvalModal);
    
    if (!declineModal || !approvalModal) {
        console.warn('Medical history modals not found. Waiting for them to load...');
        // Wait a bit and try again
        setTimeout(initializeMedicalHistoryApproval, 500);
        return;
    }
    
    // Handle approve button clicks
    const approveButtons = document.querySelectorAll('.approve-medical-history-btn');
    console.log('Found approve buttons:', approveButtons.length);
    approveButtons.forEach(btn => {
        btn.addEventListener('click', handleApproveClick);
    });

    // Handle decline button clicks
    const declineButtons = document.querySelectorAll('.decline-medical-history-btn');
    console.log('Found decline buttons:', declineButtons.length);
    declineButtons.forEach(btn => {
        btn.addEventListener('click', handleDeclineClick);
    });

    // Handle decline form submission
    const submitDeclineBtn = document.getElementById('submitDeclineBtn');
    if (submitDeclineBtn) {
        submitDeclineBtn.addEventListener('click', handleDeclineSubmit);
        console.log('Decline submit button handler attached');
    }

    // Handle restriction type change
    const restrictionType = document.getElementById('restrictionType');
    if (restrictionType) {
        restrictionType.addEventListener('change', handleRestrictionTypeChange);
        console.log('Restriction type change handler attached');
    }
    
    console.log('Medical history approval functionality initialized successfully');
}

// Intercept any Approve button inside the MH modal to force: Approve -> Confirm -> Process
function bindApproveInterceptors() {
    try {
        const container = document.getElementById('medicalHistoryModal') || document;
        const tryBind = (btn) => {
            if (!btn || btn.__mhApproveIntercepted) return;
            btn.__mhApproveIntercepted = true;
            // Remove inline onclick if present
            try { btn.onclick = null; } catch(_) {}
            btn.addEventListener('click', function(ev){
                try { ev.preventDefault(); ev.stopPropagation(); if (ev.stopImmediatePropagation) ev.stopImmediatePropagation(); } catch(_) {}
                try { window.__mhApproveFromPrimary = true; } catch(_) {}
                // Always show our confirmation first
                // Always show MH confirmation; only proceed on Yes
                if (typeof showConfirmApproveModal === 'function') {
                    showConfirmApproveModal(function(){
                        try { window.__mhApproveConfirmed = true; } catch(_) {}
                        if (typeof window.processFormSubmission === 'function') {
                            window.processFormSubmission('approve');
                        } else if (typeof window.submitModalForm === 'function') {
                            window.submitModalForm('approve');
                        }
                    });
                }
                return false;
            }, true); // capture to beat any existing handlers
        };
        // Primary approve button id
        tryBind(container.querySelector('#modalApproveButton'));
        // Intercept the Next button when it turns into Approve: block any processing
        const nextBtn = container.querySelector('#modalNextButton');
        if (nextBtn && !nextBtn.__mhApproveBlockBound) {
            nextBtn.__mhApproveBlockBound = true;
            nextBtn.addEventListener('click', function(ev){
                const text = (nextBtn.textContent || '').trim().toLowerCase();
                if (text === 'approve') {
                    try { ev.preventDefault(); ev.stopPropagation(); if (ev.stopImmediatePropagation) ev.stopImmediatePropagation(); } catch(_) {}
                    // Do nothing here; user must click the primary Approve button
                    return false;
                }
            }, true);
        }
    } catch(_) {}
}

// Hard gate: wrap any direct calls to processFormSubmission('approve') to force confirmation first
(function hookProcessFormSubmission(){
    try {
        if (typeof window.processFormSubmission === 'function' && !window.__mhProcHooked) {
            window.__mhProcHooked = true;
            const orig = window.processFormSubmission;
            window.processFormSubmission = function(action){
                const a = String(action || '').toLowerCase();
                if (a === 'approve' && !window.__mhApproveConfirmed) return; // block unsolicited submits
                const result = orig.apply(window, arguments);
                try { window.__mhApproveFromPrimary = false; } catch(_) {}
                return result;
            };
        }
    } catch(_) {}
})();

// Block raw form submit of the MH modal until user confirms
(function guardFormSubmit(){
    try {
        document.addEventListener('submit', function(e){
            try {
                const form = e.target;
                if (!form) return;
                const isMH = form.id === 'modalMedicalHistoryForm' || form.closest && form.closest('#medicalHistoryModal');
                if (isMH && window.__mhApproveFromPrimary && !window.__mhApproveConfirmed) {
                    // If submit originates before confirm, block it
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                    return false;
                }
            } catch(_) {}
        }, true);
    } catch(_) {}
})();

// Click handler for Decline buttons (opens the dedicated decline modal)
function handleDeclineClick(e) {
    try { if (e && typeof e.preventDefault === 'function') e.preventDefault(); } catch(_) {}
    try { if (e && typeof e.stopPropagation === 'function') e.stopPropagation(); } catch(_) {}
    showDeclineModal();
    return false;
}

// Handle restriction type change
function handleRestrictionTypeChange() {
    const restrictionType = document.getElementById('restrictionType').value;
    const dateSelectionSection = document.getElementById('dateSelectionSection');
    const donationRestrictionDate = document.getElementById('donationRestrictionDate');
    
    if (restrictionType === 'temporary') {
        dateSelectionSection.style.display = 'block';
        donationRestrictionDate.required = true;
        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        donationRestrictionDate.min = tomorrow.toISOString().split('T')[0];
    } else if (restrictionType === 'permanent') {
        dateSelectionSection.style.display = 'none';
        donationRestrictionDate.required = false;
        donationRestrictionDate.value = '';
    } else {
        dateSelectionSection.style.display = 'none';
        donationRestrictionDate.required = false;
        donationRestrictionDate.value = '';
    }
}

// Removed showApprovalModal - unused function

// Show decline confirmation modal
function showDeclineModal() {
    console.log('showDeclineModal called');
    
    const modalElement = document.getElementById('medicalHistoryDeclineModal');
    console.log('Modal element found:', modalElement);
    
    if (!modalElement) {
        console.error('Medical history decline modal not found!');
        console.log('Trying fallback modal...');
        
        // Try fallback modal for testing
        const fallbackModal = document.getElementById('fallbackDeclineModal');
        if (fallbackModal) {
            console.log('Using fallback modal');
            const modal = new bootstrap.Modal(fallbackModal);
            modal.show();
            return;
        }
        
        showMedicalHistoryToast('Error', 'Modal not found. Please refresh the page.', 'error');
        return;
    }
    
    try {
        // Ensure we have minimal donor context before opening
        ensureMedicalHistoryContextFromPage();
        // Store current scroll position
        const currentScrollPos = window.pageYOffset || document.documentElement.scrollTop;
        
        // Prevent Bootstrap from adding padding to body
        document.body.style.paddingRight = '0px';
        document.body.style.overflow = 'auto';
        
        const declineModal = new bootstrap.Modal(modalElement);
        declineModal.show();
        console.log('Decline modal shown successfully');
        
        // Restore scroll position after modal opens
        setTimeout(() => {
            window.scrollTo(0, currentScrollPos);
            // Ensure body padding stays at 0
            document.body.style.paddingRight = '0px';
        }, 100);
        
        // Force z-index to ensure modal appears on top
        setTimeout(() => {
            if (modalElement) {
                modalElement.style.zIndex = '9999';
                modalElement.style.position = 'relative';
                const modalDialog = modalElement.querySelector('.modal-dialog');
                if (modalDialog) {
                    modalDialog.style.zIndex = '10000';
                }
            }
        }, 100);
        
        // Reset form
        const form = document.getElementById('declineMedicalHistoryForm');
        if (form) {
            form.reset();
            console.log('Form reset successfully');
        }
        
        // Reset restriction type change handler
        handleRestrictionTypeChange();
        
        // Prevent layout shifts by monitoring body changes
        const observer = new MutationObserver(() => {
            if (document.body.classList.contains('modal-open')) {
                document.body.style.paddingRight = '0px';
                document.body.style.overflow = 'auto';
            }
        });
        
        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['class']
        });
        
        // Add cleanup when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function() {
            cleanupModalCompletely();
        });
        
    } catch (error) {
        console.error('Error showing decline modal:', error);
        showMedicalHistoryToast('Error', 'Failed to show modal. Please refresh the page.', 'error');
    }
}

// Handle decline form submission
function handleDeclineSubmit(e) {
    e.preventDefault();
    // Make sure we have donor_id/screening_id context even if globals were cleared
    ensureMedicalHistoryContextFromPage();
    
    const declineReason = document.getElementById('declineReason').value.trim();
    const restrictionType = document.getElementById('restrictionType').value;
    const donationRestrictionDate = document.getElementById('donationRestrictionDate').value;
    
    if (!declineReason) {
        showMedicalHistoryToast('Validation Error', 'Please provide a reason for declining.', 'error');
        document.getElementById('declineReason').focus();
        return;
    }
    
    if (declineReason.length < 10) {
        showMedicalHistoryToast('Validation Error', 'Please provide a more detailed reason (minimum 10 characters).', 'error');
        document.getElementById('declineReason').focus();
        return;
    }
    
    if (!restrictionType) {
        showMedicalHistoryToast('Validation Error', 'Please select a restriction type.', 'error');
        document.getElementById('restrictionType').focus();
        return;
    }
    
    if (restrictionType === 'temporary' && !donationRestrictionDate) {
        showMedicalHistoryToast('Validation Error', 'Please select a date when the donor can donate again.', 'error');
        document.getElementById('donationRestrictionDate').focus();
        return;
    }
    
    // Close decline modal
    const declineModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryDeclineModal'));
    declineModal.hide();
    
            // Process the decline
        processMedicalHistoryDecline(declineReason, restrictionType, donationRestrictionDate);
        
        // Clean up modal completely
        cleanupModalCompletely();
}

// Populate currentMedicalHistoryData from the currently loaded Medical History modal if missing
function ensureMedicalHistoryContextFromPage() {
    try {
        if (!window.currentMedicalHistoryData) window.currentMedicalHistoryData = {};
        const ctx = window.currentMedicalHistoryData || {};
        // Try to pull IDs from the medical history modal form
        const donorIdEl = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"], #medicalHistoryModal input[name="donor_id"]');
        const screeningIdEl = document.querySelector('#modalMedicalHistoryForm input[name="screening_id"], #medicalHistoryModal input[name="screening_id"]');
        const mhIdEl = document.querySelector('#modalMedicalHistoryForm input[name="medical_history_id"], #medicalHistoryModal input[name="medical_history_id"]');
        const peIdEl = document.querySelector('#modalMedicalHistoryForm input[name="physical_exam_id"], #medicalHistoryModal input[name="physical_exam_id"]');
        const donorId = (ctx.donor_id) || (donorIdEl && donorIdEl.value) || window.currentDonorId || null;
        const screening_id = ctx.screening_id || (screeningIdEl && screeningIdEl.value) || null;
        const medical_history_id = ctx.medical_history_id || (mhIdEl && mhIdEl.value) || null;
        const physical_exam_id = ctx.physical_exam_id || (peIdEl && peIdEl.value) || null;
        window.currentMedicalHistoryData = {
            donor_id: donorId,
            screening_id: screening_id || ctx.screening_id || null,
            medical_history_id: medical_history_id || ctx.medical_history_id || null,
            physical_exam_id: physical_exam_id || ctx.physical_exam_id || null
        };
        return window.currentMedicalHistoryData;
    } catch (err) {
        // Do not block; fallback logic later can fetch by donor
        return window.currentMedicalHistoryData || null;
    }
}

// Removed processMedicalHistoryApproval - unused function

// Process medical history decline
async function processMedicalHistoryDecline(declineReason, restrictionType, donationRestrictionDate) {
    if (!currentMedicalHistoryData) {
        showMedicalHistoryToast('Error', 'No medical history data available.', 'error');
        return;
    }
    
    // Show loading state
    showMedicalHistoryToast('Processing', 'Processing medical history decline...', 'info');
    
    // Calculate temporary_deferred text based on restriction type
    let temporaryDeferredText;
    if (restrictionType === 'temporary') {
        // Calculate the difference between selected date and today
        const selectedDate = new Date(donationRestrictionDate);
        const today = new Date();
        const diffTime = selectedDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays > 0) {
            // Calculate months and remaining days
            const months = Math.floor(diffDays / 30);
            const remainingDays = diffDays % 30;
            
            if (months > 0 && remainingDays > 0) {
                temporaryDeferredText = `${months} month${months > 1 ? 's' : ''} ${remainingDays} day${remainingDays > 1 ? 's' : ''}`;
            } else if (months > 0) {
                temporaryDeferredText = `${months} month${months > 1 ? 's' : ''}`;
            } else {
                temporaryDeferredText = `${diffDays} day${diffDays > 1 ? 's' : ''}`;
            }
        } else {
            temporaryDeferredText = 'Immediate';
        }
    } else if (restrictionType === 'permanent') {
        temporaryDeferredText = 'Permanent/Indefinite';
    } else {
        temporaryDeferredText = 'Not specified';
    }
    
    // Prepare data for submission
    const submitData = {
        donor_id: currentMedicalHistoryData.donor_id,
        screening_id: currentMedicalHistoryData.screening_id,
        decline_reason: declineReason,
        restriction_type: restrictionType,
        donation_restriction_date: donationRestrictionDate,
        action: 'decline_medical_history'
    };
    
    // Check if we have a screening_id, if not, we need to create one or use a different approach
    if (!currentMedicalHistoryData.screening_id || currentMedicalHistoryData.screening_id === 'no-screening-id') {
        console.log('No screening_id available, using update-eligibility endpoint instead');
        
        // Fetch ALL data from source tables
        try {
            console.log('Fetching data for donor_id:', currentMedicalHistoryData.donor_id);
            const allSourceData = await fetchAllSourceData(currentMedicalHistoryData.donor_id);
            console.log('Fetched all source data:', allSourceData);
            console.log('Screening form data:', allSourceData.screeningForm);
            console.log('Physical exam data:', allSourceData.physicalExam);
            console.log('Donor form data:', allSourceData.donorForm);
            
            // Calculate temporary_deferred text based on restriction type
            let temporaryDeferredText;
            if (restrictionType === 'temporary') {
                // Calculate the difference between selected date and today
                const selectedDate = new Date(donationRestrictionDate);
                const today = new Date();
                const diffTime = selectedDate.getTime() - today.getTime();
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 0) {
                    // Calculate months and remaining days
                    const months = Math.floor(diffDays / 30);
                    const remainingDays = diffDays % 30;
                    
                    if (months > 0 && remainingDays > 0) {
                        temporaryDeferredText = `${months} month${months > 1 ? 's' : ''} ${remainingDays} day${remainingDays > 1 ? 's' : ''}`;
                    } else if (months > 0) {
                        temporaryDeferredText = `${months} month${months > 1 ? 's' : ''}`;
                    } else {
                        temporaryDeferredText = `${diffDays} day${diffDays > 1 ? 's' : ''}`;
                    }
                } else {
                    temporaryDeferredText = 'Immediate';
                }
            } else if (restrictionType === 'permanent') {
                temporaryDeferredText = 'Permanent/Indefinite';
            } else {
                temporaryDeferredText = 'Not specified';
            }
            
            // Use the update-eligibility endpoint for medical history declines without screening
            // Try to get the IDs from multiple sources
            const medicalHistoryId = getMedicalHistoryId() || 
                                   currentMedicalHistoryData.medical_history_id || 
                                   allSourceData.screeningForm?.medical_history_id || 
                                   null;
            const screeningId = getScreeningId() || 
                              allSourceData.screeningForm?.screening_id || 
                              currentMedicalHistoryData.screening_id || 
                              null;
            const physicalExamId = getPhysicalExamId() || 
                                 allSourceData.physicalExam?.physical_exam_id || 
                                 currentMedicalHistoryData.physical_exam_id || 
                                 null;
            
            console.log('ID values being used:');
            console.log('medical_history_id:', medicalHistoryId);
            console.log('screening_id:', screeningId);
            console.log('physical_exam_id:', physicalExamId);
            
            const eligibilityData = {
                donor_id: currentMedicalHistoryData.donor_id,
                medical_history_id: medicalHistoryId,
                screening_id: screeningId,
                physical_exam_id: physicalExamId,
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
                disapproval_reason: `Medical: ${declineReason}`,
                start_date: new Date().toISOString(),
                end_date: restrictionType === 'temporary' ? donationRestrictionDate : null,
                status: restrictionType === 'temporary' ? 'temporary deferred' : 'permanently deferred',
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
            
            console.log('Using update-eligibility with data:', eligibilityData);
            console.log('Screening form data being used:', allSourceData.screeningForm);
            console.log('Physical exam data being used:', allSourceData.physicalExam);
            console.log('Donor form data being used:', allSourceData.donorForm);
            
                               // Submit to update-eligibility endpoint
                   fetch('../api/update-eligibility.php', {
                       method: 'POST',
                       headers: {
                           'Content-Type': 'application/json',
                       },
                       body: JSON.stringify(eligibilityData)
                   })
                   .then(response => {
                       if (!response.ok) {
                           throw new Error(`HTTP error! status: ${response.status}`);
                       }
                       return response.text().then(text => {
                           try {
                               return JSON.parse(text);
                           } catch (e) {
                               console.error('Response text:', text);
                               throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                           }
                       });
                   })
            .then(result => {
                       if (result.success) {
                           console.log('Decline recorded successfully:', result);
                           
                           // Update physical examination remarks and needs_review
                           const donorId = allSourceData?.donorForm?.donor_id || null;
                           updatePhysicalExaminationAfterDecline(physicalExamId, restrictionType, donorId);
                           
                           showDeclinedModal(restrictionType, donationRestrictionDate);
                           
                           // Refresh the page after modal is closed
                           document.getElementById('medicalHistoryDeclinedModal').addEventListener('hidden.bs.modal', function() {
                               // Clean up completely before refreshing
                               cleanupModalCompletely();
                               window.location.reload();
                           }, { once: true });
                       } else {
                           console.error('Failed to record decline:', result.error);
                           showMedicalHistoryToast('Error', result.message || 'Failed to record decline. Please try again.', 'error');
                       }
                   })
            .catch(error => {
                console.error('Error processing decline:', error);
                showMedicalHistoryToast('Error', 'An error occurred while processing the decline.', 'error');
            });
            
        } catch (error) {
            console.error('Error fetching source data:', error);
            showMedicalHistoryToast('Error', 'Failed to fetch donor data. Please try again.', 'error');
        }
        
        return; // Exit early since we handled the case without screening_id
    }
    
    // Use the same defer functionality as the working defer button (when screening_id is available)
    const deferData = {
        action: 'create_eligibility_defer',
        donor_id: currentMedicalHistoryData.donor_id,
        screening_id: currentMedicalHistoryData.screening_id,
        deferral_type: restrictionType === 'temporary' ? 'Temporary Deferral' : 'Permanent Deferral',
        disapproval_reason: `Medical: ${declineReason}`,
        duration: restrictionType === 'temporary' ? 
            Math.ceil((new Date(donationRestrictionDate) - new Date()) / (1000 * 60 * 60 * 24)) : null
    };
    
    console.log('Using defer functionality with data:', deferData);
    
    // Submit to the same endpoint as the defer button
    fetch('../../assets/php_func/create_eligibility.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(deferData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Response text:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(result => {
        if (result.success) {
            console.log('Decline recorded successfully:', result);
                   
                   // Update physical examination remarks and needs_review
                   const physicalExamId = getPhysicalExamId() || 
                                        allSourceData?.physicalExam?.physical_exam_id || 
                                        currentMedicalHistoryData.physical_exam_id || 
                                        null;
                   const donorId = allSourceData?.donorForm?.donor_id || null;
                   updatePhysicalExaminationAfterDecline(physicalExamId, restrictionType, donorId);
                   
            showDeclinedModal(restrictionType, donationRestrictionDate);
            
            // Refresh the page after modal is closed
            document.getElementById('medicalHistoryDeclinedModal').addEventListener('hidden.bs.modal', function() {
                // Clean up completely before refreshing
                cleanupModalCompletely();
                window.location.reload();
            }, { once: true });
        } else {
            console.error('Failed to record decline:', result.error);
                   showMedicalHistoryToast('Error', result.message || 'Failed to record decline. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Error processing decline:', error);
        showMedicalHistoryToast('Error', 'An error occurred while processing the decline.', 'error');
    });
}

// Show declined confirmation modal
function showDeclinedModal(restrictionType, donationRestrictionDate) {
    // First, close the decline modal if it's open
    const declineModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryDeclineModal'));
    if (declineModal) {
        declineModal.hide();
    }
    
    // Wait a moment for the decline modal to close, then show the confirmation modal
    setTimeout(() => {
        const declinedModal = new bootstrap.Modal(document.getElementById('medicalHistoryDeclinedModal'));
        
        // Update restriction summary
        const restrictionSummary = document.getElementById('restrictionSummary');
        if (restrictionType === 'permanent') {
            restrictionSummary.innerHTML = '<span class="text-danger">Permanent Defer</span>';
        } else if (restrictionType === 'temporary') {
            const date = new Date(donationRestrictionDate);
            const formattedDate = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            restrictionSummary.innerHTML = `<span class="text-warning">Temporary Defer until ${formattedDate}</span>`;
        }
        
        declinedModal.show();
    }, 300); // Wait 300ms for the decline modal to close
}

// Clean up modal completely to restore original design
function cleanupModalCompletely() {
    // Only remove modal-open class if it's from our modals
    if (document.querySelector('#medicalHistoryDeclineModal, #medicalHistoryApprovalModal, #medicalHistoryDeclinedModal')) {
        document.body.classList.remove('modal-open');
    }
    
    // Reset body styles ONLY if they were changed by our modals
    if (document.body.style.paddingRight === '0px') {
        document.body.style.paddingRight = '';
    }
    if (document.body.style.overflow === 'auto') {
        document.body.style.overflow = '';
    }
    
    // Remove ONLY our modal backdrops
    const ourBackdrops = document.querySelectorAll('#medicalHistoryDeclineModal + .modal-backdrop, #medicalHistoryApprovalModal + .modal-backdrop, #medicalHistoryDeclinedModal + .modal-backdrop');
    ourBackdrops.forEach(backdrop => backdrop.remove());
    
    // Remove any inline styles that might have been added by our modals
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
    
    // Force a reflow to ensure styles are applied
    document.body.offsetHeight;
    
    console.log('Modal cleanup completed - design should be restored');
}

// Show medical history toast notification
function showMedicalHistoryToast(title, message, type = 'success') {
    // Remove existing toasts
    document.querySelectorAll('.medical-history-toast').forEach(toast => {
        toast.remove();
    });

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `medical-history-toast medical-history-toast-${type}`;
    
    const icon = type === 'success' ? 'fas fa-check-circle' : 
                 type === 'error' ? 'fas fa-exclamation-circle' : 
                 type === 'info' ? 'fas fa-info-circle' : 'fas fa-info-circle';
    
    toast.innerHTML = `
        <div class="medical-history-toast-content">
            <i class="${icon}"></i>
            <div class="medical-history-toast-text">
                <div class="medical-history-toast-title">${title}</div>
                <div class="medical-history-toast-message">${message}</div>
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

// Helper function to get donor registration channel from donor_form
async function getDonorRegistrationChannel(donorId) {
    try {
        // This should be an API call to get the registration channel from donor_form
        // For now, we'll simulate it - you need to implement the actual API endpoint
        const response = await fetch(`/api/get-donor-info.php?donor_id=${donorId}`);
        const data = await response.json();
        
        if (data.success && data.donor_info && data.donor_info.registration_channel) {
            return data.donor_info.registration_channel;
        }
        
        // Return default if not found
        return 'PRC Portal';
    } catch (error) {
        console.error('Error fetching donor registration channel:', error);
        return 'PRC Portal'; // Default fallback
    }
}

// Helper function to get physical exam ID from current context
function getPhysicalExamId() {
    try {
        // Since we're on the physical examination dashboard, try to get the ID from various sources
        // 1. Check if there's a physical exam ID in the current page context
        const physicalExamId = document.querySelector('[data-physical-exam-id]')?.getAttribute('data-physical-exam-id');
        if (physicalExamId) return physicalExamId;
        
        // 2. Check if there's a physical exam ID in the URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const urlPhysicalExamId = urlParams.get('physical_exam_id');
        if (urlPhysicalExamId) return urlPhysicalExamId;
        
        // 3. Check if there's a physical exam ID in the current medical history data
        if (currentMedicalHistoryData && currentMedicalHistoryData.physical_exam_id) {
            return currentMedicalHistoryData.physical_exam_id;
        }
        
        // 4. Try to find it in the page content (if it's embedded somewhere)
        const pageContent = document.body.textContent;
        const physicalExamMatch = pageContent.match(/physical_exam_id["\s]*[:=]\s*["']?([^"'\s,}]+)["']?/i);
        if (physicalExamMatch) return physicalExamMatch[1];
        
        // 5. Try to find it in any form or hidden input fields
        const hiddenInput = document.querySelector('input[name="physical_exam_id"], input[name="physical_examination_id"]');
        if (hiddenInput && hiddenInput.value) return hiddenInput.value;
        
        // 6. Try to get physical exam ID from the current page context (we're on physical exam dashboard)
        // Look for any data attributes or hidden fields that might contain the physical exam ID
        const pageElements = document.querySelectorAll('[data-physical-exam-id], [data-physical-examination-id], input[name*="physical"], input[id*="physical"]');
        for (let element of pageElements) {
            const value = element.getAttribute('data-physical-exam-id') || 
                         element.getAttribute('data-physical-examination-id') || 
                         element.value;
            if (value && value !== '' && value !== 'null') {
                console.log('Found physical exam ID from page elements:', value);
                return value;
            }
        }
        
        // 7. If still not found, try to extract from the current URL or page data
        const currentUrl = window.location.href;
        const urlMatch = currentUrl.match(/physical[_-]?exam[_-]?id[=:]([^&\s]+)/i);
        if (urlMatch) {
            console.log('Found physical exam ID from URL:', urlMatch[1]);
            return urlMatch[1];
        }
        
        // 8. Last resort: return null since we can't find a valid UUID
        console.log('Physical exam ID not found, returning null');
        return null;
    } catch (error) {
        console.error('Error getting physical exam ID:', error);
        return null;
    }
}

// Helper function to get screening ID from current context
function getScreeningId() {
    try {
        // Try to get screening ID from various sources
        // 1. Check if there's a screening ID in the current page context
        const screeningId = document.querySelector('[data-screening-id]')?.getAttribute('data-screening-id');
        if (screeningId && screeningId !== 'no-screening-id') return screeningId;
        
        // 2. Check if there's a screening ID in the URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const urlScreeningId = urlParams.get('screening_id');
        if (urlScreeningId) return urlScreeningId;
        
        // 3. Check if there's a screening ID in the current medical history data
        if (currentMedicalHistoryData && currentMedicalHistoryData.screening_id && currentMedicalHistoryData.screening_id !== 'no-screening-id') {
            return currentMedicalHistoryData.screening_id;
        }
        
        // 4. Try to find it in any form or hidden input fields
        const hiddenInput = document.querySelector('input[name="screening_id"], input[name="screening_form_id"]');
        if (hiddenInput && hiddenInput.value) return hiddenInput.value;
        
        // 5. Try to find it in the page content
        const pageContent = document.body.textContent;
        const screeningMatch = pageContent.match(/screening_id["\s]*[:=]\s*["']?([^"'\s,}]+)["']?/i);
        if (screeningMatch) return screeningMatch[1];
        
        console.log('Screening ID not found, returning null');
        return null;
    } catch (error) {
        console.error('Error getting screening ID:', error);
        return null;
    }
}

// Helper function to get medical history ID from current context
function getMedicalHistoryId() {
    try {
        // Try to get medical history ID from various sources
        // 1. Check if there's a medical history ID in the current page context
        const medicalHistoryId = document.querySelector('[data-medical-history-id]')?.getAttribute('data-medical-history-id');
        if (medicalHistoryId && medicalHistoryId !== 'no-medical-history-id') return medicalHistoryId;
        
        // 2. Check if there's a medical history ID in the URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const urlMedicalHistoryId = urlParams.get('medical_history_id');
        if (urlMedicalHistoryId) return urlMedicalHistoryId;
        
        // 3. Check if there's a medical history ID in the current medical history data
        if (currentMedicalHistoryData && currentMedicalHistoryData.medical_history_id && currentMedicalHistoryData.medical_history_id !== 'no-medical-history-id') {
            return currentMedicalHistoryData.medical_history_id;
        }
        
        // 4. Try to find it in any form or hidden input fields
        const hiddenInput = document.querySelector('input[name="medical_history_id"], input[name="medical_history_form_id"]');
        if (hiddenInput && hiddenInput.value) return hiddenInput.value;
        
        // 5. Try to find it in the page content
        const pageContent = document.body.textContent;
        const medicalHistoryMatch = pageContent.match(/medical_history_id["\s]*[:=]\s*["']?([^"'\s,}]+)["']?/i);
        if (medicalHistoryMatch) return medicalHistoryMatch[1];
        
        console.log('Medical history ID not found, returning null');
        return null;
    } catch (error) {
        console.error('Error getting medical history ID:', error);
        return null;
    }
}

// Helper function to update or create physical examination after decline
function updatePhysicalExaminationAfterDecline(physicalExamId, restrictionType, donorId = null) {
    if (!physicalExamId && !donorId) {
        console.log('No physical_exam_id or donor_id available, skipping physical examination update/create');
        return;
    }
    
    // Determine remarks based on restriction type
    let remarks;
    if (restrictionType === 'temporary') {
        remarks = 'Temporarily Deferred';
    } else if (restrictionType === 'permanent') {
        remarks = 'Permanently Deferred';
    } else {
        remarks = 'Deferred';
    }
    
    if (physicalExamId) {
        // Update existing physical examination record
        console.log('Updating existing physical examination:', {
            physical_exam_id: physicalExamId,
            remarks: remarks,
            needs_review: false
        });
        
        fetch('../api/update-physical-examination.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                physical_exam_id: physicalExamId,
                remarks: remarks,
                needs_review: false
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                console.log('Physical examination updated successfully:', result);
            } else {
                console.error('Failed to update physical examination:', result.error);
            }
        })
        .catch(error => {
            console.error('Error updating physical examination:', error);
        });
    } else if (donorId) {
        // Create new physical examination record
        console.log('Creating new physical examination record:', {
            donor_id: donorId,
            remarks: remarks,
            needs_review: false
        });
        
        fetch('../api/create-physical-examination.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                donor_id: donorId,
                remarks: remarks,
                needs_review: false
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                console.log('Physical examination record created successfully:', result);
            } else {
                console.error('Failed to create physical examination record:', result.error);
            }
        })
        .catch(error => {
            console.error('Error creating physical examination record:', error);
        });
    }
}

// Helper function to get screening form data from page context
function getScreeningFormDataFromPage() {
    try {
        console.log('Trying to get screening form data from page context');
        
        // Try to find screening form data in the page
        const pageContent = document.body.textContent;
        
        // Look for common screening form fields in the page content
        const bloodTypeMatch = pageContent.match(/blood_type["\s]*[:=]\s*["']?([^"'\s,}]+)["']?/i);
        const donationTypeMatch = pageContent.match(/donation_type["\s]*[:=]\s*["']?([^"'\s,}]+)["']?/i);
        const bodyWeightMatch = pageContent.match(/body_weight["\s]*[:=]\s*["']?([^"'\s,}]+)["']?/i);
        
        // Try to find in form fields
        const bloodTypeInput = document.querySelector('input[name*="blood_type"], select[name*="blood_type"]');
        const donationTypeInput = document.querySelector('input[name*="donation_type"], select[name*="donation_type"]');
        const bodyWeightInput = document.querySelector('input[name*="body_weight"], input[name*="weight"]');
        
        return {
            screening_id: getScreeningId(),
            donor_form_id: null,
            medical_history_id: getMedicalHistoryId(),
            interviewer_id: null,
            body_weight: bodyWeightInput?.value || bodyWeightMatch?.[1] || null,
            specific_gravity: null,
            blood_type: bloodTypeInput?.value || bloodTypeMatch?.[1] || null,
            mobile_organizer: null,
            patient_name: null,
            hospital: null,
            patient_blood_type: null,
            component_type: null,
            units_needed: null,
            has_previous_donation: null,
            red_cross_donations: null,
            hospital_donations: null,
            last_rc_donation_date: null,
            last_hosp_donation_date: null,
            last_rc_donation_place: null,
            last_hosp_donation_place: null,
            interview_date: null,
            disapproval_reason: null,
            mobile_location: null,
            donation_type: donationTypeInput?.value || donationTypeMatch?.[1] || null,
            needs_review: null,
            staff: null
        };
    } catch (error) {
        console.error('Error getting screening form data from page:', error);
        return {
            screening_id: null,
            donor_form_id: null,
            medical_history_id: null,
            interviewer_id: null,
            body_weight: null,
            specific_gravity: null,
            blood_type: null,
            mobile_organizer: null,
            patient_name: null,
            hospital: null,
            patient_blood_type: null,
            component_type: null,
            units_needed: null,
            has_previous_donation: null,
            red_cross_donations: null,
            hospital_donations: null,
            last_rc_donation_date: null,
            last_hosp_donation_date: null,
            last_rc_donation_place: null,
            last_hosp_donation_place: null,
            interview_date: null,
            disapproval_reason: null,
            mobile_location: null,
            donation_type: null,
            needs_review: null,
            staff: null
        };
    }
}

// Comprehensive function to fetch data from all source tables
async function fetchAllSourceData(donorId) {
    try {
        console.log('Fetching comprehensive data for donor:', donorId);
        
        // Fetch data from all source tables in parallel
        const [screeningData, physicalExamData, donorData] = await Promise.all([
            fetchScreeningFormData(donorId),
            fetchPhysicalExamData(donorId),
            fetchDonorFormData(donorId)
        ]);
        
        return {
            screeningForm: screeningData,
            physicalExam: physicalExamData,
            donorForm: donorData
        };
    } catch (error) {
        console.error('Error fetching comprehensive source data:', error);
        throw error;
    }
}

// Fetch data from screening_form table
async function fetchScreeningFormData(donorId) {
    try {
        console.log('Fetching screening form data for donor_id:', donorId);
        
        // First, let's test what's in the screening_form table
        try {
            const testResponse = await fetch(`../api/test-screening-form.php?donor_id=${donorId}`);
            const testData = await testResponse.json();
            console.log('Screening form test results:', testData);
        } catch (testError) {
            console.error('Error testing screening form:', testError);
        }
        
        const response = await fetch(`../api/get-screening-form.php?donor_id=${donorId}`);
        const data = await response.json();
        console.log('Screening form API response:', data);
        
        if (data.success && data.screening_form) {
            console.log('Successfully retrieved screening form data:', data.screening_form);
            return {
                // Key fields that were missing
                screening_id: data.screening_form.screening_id || null,
                blood_type: data.screening_form.blood_type || null,
                donation_type: data.screening_form.donation_type || null,
                body_weight: data.screening_form.body_weight || null,
                
                // All other fields from the schema
                donor_form_id: data.screening_form.donor_form_id || null,
                medical_history_id: data.screening_form.medical_history_id || null,
                interviewer_id: data.screening_form.interviewer_id || null,
                specific_gravity: data.screening_form.specific_gravity || null,
                mobile_organizer: data.screening_form.mobile_organizer || null,
                patient_name: data.screening_form.patient_name || null,
                hospital: data.screening_form.hospital || null,
                patient_blood_type: data.screening_form.patient_blood_type || null,
                component_type: data.screening_form.component_type || null,
                units_needed: data.screening_form.units_needed || null,
                has_previous_donation: data.screening_form.has_previous_donation || null,
                red_cross_donations: data.screening_form.red_cross_donations || null,
                hospital_donations: data.screening_form.hospital_donations || null,
                last_rc_donation_date: data.screening_form.last_rc_donation_date || null,
                last_hosp_donation_date: data.screening_form.last_hosp_donation_date || null,
                last_rc_donation_place: data.screening_form.last_rc_donation_place || null,
                last_hosp_donation_place: data.screening_form.last_hosp_donation_place || null,
                interview_date: data.screening_form.interview_date || null,
                disapproval_reason: data.screening_form.disapproval_reason || null,
                mobile_location: data.screening_form.mobile_location || null,
                needs_review: data.screening_form.needs_review || null,
                staff: data.screening_form.staff || null,
                created_at: data.screening_form.created_at || null,
                updated_at: data.screening_form.updated_at || null
            };
        }
        
        console.log('No screening form data found from API, trying to get from page context');
        
        // Try to get screening form data from page context
        const pageScreeningData = getScreeningFormDataFromPage();
        console.log('Screening form data from page context:', pageScreeningData);
        
        return pageScreeningData;
    } catch (error) {
        console.error('Error fetching screening form data:', error);
        return {
            screening_id: null,
            donor_form_id: null,
            medical_history_id: null,
            interviewer_id: null,
            body_weight: null,
            specific_gravity: null,
            blood_type: null,
            mobile_organizer: null,
            patient_name: null,
            hospital: null,
            patient_blood_type: null,
            component_type: null,
            units_needed: null,
            has_previous_donation: null,
            red_cross_donations: null,
            hospital_donations: null,
            last_rc_donation_date: null,
            last_hosp_donation_date: null,
            last_rc_donation_place: null,
            last_hosp_donation_place: null,
            interview_date: null,
            disapproval_reason: null,
            mobile_location: null,
            donation_type: null,
            needs_review: null,
            staff: null
        };
    }
}

// Fetch data from physical_examination table
async function fetchPhysicalExamData(donorId) {
    try {
        console.log('Fetching physical exam data for donor_id:', donorId);
        const response = await fetch(`../api/get-physical-examination.php?donor_id=${donorId}`);
        const data = await response.json();
        console.log('Physical exam API response:', data);
        
        if (data.success && data.physical_exam) {
            return {
                physical_exam_id: data.physical_exam.physical_exam_id || null,
                blood_pressure: data.physical_exam.blood_pressure || null,
                pulse_rate: data.physical_exam.pulse_rate || null,
                body_temp: data.physical_exam.body_temp || null,
                gen_appearance: data.physical_exam.gen_appearance || null,
                skin: data.physical_exam.skin || null,
                heent: data.physical_exam.heent || null,
                heart_and_lungs: data.physical_exam.heart_and_lungs || null,
                body_weight: data.physical_exam.body_weight || null,
                remarks: data.physical_exam.remarks || null,
                reason: data.physical_exam.reason || null,
                blood_bag_type: data.physical_exam.blood_bag_type || null,
                disapproval_reason: data.physical_exam.disapproval_reason || null,
                needs_review: data.physical_exam.needs_review || null,
                physician: data.physical_exam.physician || null,
                screening_id: data.physical_exam.screening_id || null,
                status: data.physical_exam.status || null
            };
        }
        
        return {
            physical_exam_id: null,
            blood_pressure: null,
            pulse_rate: null,
            body_temp: null,
            gen_appearance: null,
            skin: null,
            heent: null,
            heart_and_lungs: null,
            body_weight: null,
            remarks: null,
            reason: null,
            blood_bag_type: null,
            disapproval_reason: null,
            needs_review: null,
            physician: null,
            screening_id: null,
            status: null
        };
    } catch (error) {
        console.error('Error fetching physical examination data:', error);
        return {
            physical_exam_id: null,
            blood_pressure: null,
            pulse_rate: null,
            body_temp: null,
            gen_appearance: null,
            skin: null,
            heent: null,
            heart_and_lungs: null,
            body_weight: null,
            remarks: null,
            reason: null,
            blood_bag_type: null,
            disapproval_reason: null,
            needs_review: null,
            physician: null,
            screening_id: null,
            status: null
        };
    }
}

// Fetch data from donor_form table
async function fetchDonorFormData(donorId) {
    try {
        const response = await fetch(`../api/get-donor-form.php?donor_id=${donorId}`);
        const data = await response.json();
        
        if (data.success && data.donor_form) {
            return {
                registration_channel: data.donor_form.registration_channel || 'PRC Portal'
            };
        }
        
        return {
            registration_channel: 'PRC Portal'
        };
    } catch (error) {
        console.error('Error fetching donor form data:', error);
        return {
            registration_channel: 'PRC Portal'
        };
    }
}

// Helper function to update eligibility table
async function updateEligibilityTable(eligibilityData) {
    try {
        // This should be an API call to update the eligibility table
        // For now, we'll simulate it - you need to implement the actual API endpoint
        const response = await fetch('../api/update-eligibility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(eligibilityData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Eligibility table updated successfully:', result);
            showMedicalHistoryToast('Success', 'Eligibility status updated to declined', 'success');
        } else {
            console.error('Failed to update eligibility table:', result.error);
            showMedicalHistoryToast('Error', 'Failed to update eligibility status', 'error');
        }
    } catch (error) {
        console.error('Error updating eligibility table:', error);
        showMedicalHistoryToast('Error', 'Failed to update eligibility status', 'error');
    }
}

// Initialize decline form validation
function initializeDeclineFormValidation() {
    const declineReasonTextarea = document.getElementById('declineReason');
    const charCountElement = document.getElementById('charCount');
    const declineReasonError = document.getElementById('declineReasonError');
    const declineReasonSuccess = document.getElementById('declineReasonSuccess');
    const submitDeclineBtn = document.getElementById('submitDeclineBtn');
    const restrictionTypeSelect = document.getElementById('restrictionType');
    const dateSelectionSection = document.getElementById('dateSelectionSection');
    const donationRestrictionDate = document.getElementById('donationRestrictionDate');
    
    if (!declineReasonTextarea) return; // Exit if elements don't exist
    
    const MIN_LENGTH = 10;
    const MAX_LENGTH = 200;
    
    // Update character count and validation
    function updateValidation() {
        const currentLength = declineReasonTextarea.value.length;
        const isValid = currentLength >= MIN_LENGTH && currentLength <= MAX_LENGTH;
        
        // Update character count
        charCountElement.textContent = `${currentLength}/${MAX_LENGTH} characters`;
        
        // Update character count color with Red Cross theme
        if (currentLength < MIN_LENGTH) {
            charCountElement.className = 'text-muted'; // Gray for incomplete
        } else if (currentLength > MAX_LENGTH) {
            charCountElement.className = 'text-danger'; // Red for over limit
        } else {
            charCountElement.className = 'text-success'; // Green for valid
        }
        
        // Update validation feedback
        if (currentLength === 0) {
            declineReasonError.style.display = 'none';
            declineReasonSuccess.style.display = 'none';
            declineReasonTextarea.classList.remove('is-valid', 'is-invalid');
        } else if (currentLength < MIN_LENGTH) {
            declineReasonError.style.display = 'block';
            declineReasonSuccess.style.display = 'none';
            declineReasonTextarea.classList.add('is-invalid');
            declineReasonTextarea.classList.remove('is-valid');
        } else if (currentLength > MAX_LENGTH) {
            declineReasonError.textContent = `Please keep the reason under ${MAX_LENGTH} characters.`;
            declineReasonError.style.display = 'block';
            declineReasonSuccess.style.display = 'none';
            declineReasonTextarea.classList.add('is-invalid');
            declineReasonTextarea.classList.remove('is-valid');
        } else {
            declineReasonError.style.display = 'none';
            declineReasonSuccess.style.display = 'block';
            declineReasonTextarea.classList.add('is-valid');
            declineReasonTextarea.classList.remove('is-invalid');
        }
        
        // Update submit button state
        updateSubmitButtonState();
    }
    
    // Update submit button state based on all form validation
    function updateSubmitButtonState() {
        const reasonValid = declineReasonTextarea.value.length >= MIN_LENGTH && declineReasonTextarea.value.length <= MAX_LENGTH;
        const restrictionTypeValid = restrictionTypeSelect.value !== '';
        const dateValid = restrictionTypeSelect.value !== 'temporary' || (donationRestrictionDate.value !== '');
        
        const allValid = reasonValid && restrictionTypeValid && dateValid;
        
        submitDeclineBtn.disabled = !allValid;
        
        if (allValid) {
            submitDeclineBtn.classList.remove('btn-secondary');
            submitDeclineBtn.classList.add('btn-danger');
        } else {
            submitDeclineBtn.classList.remove('btn-danger');
            submitDeclineBtn.classList.add('btn-secondary');
        }
    }
    
    // Handle restriction type change
    function handleRestrictionTypeChange() {
        if (restrictionTypeSelect.value === 'temporary') {
            dateSelectionSection.style.display = 'block';
            donationRestrictionDate.required = true;
        } else {
            dateSelectionSection.style.display = 'none';
            donationRestrictionDate.required = false;
        }
        updateSubmitButtonState();
    }
    
    // Event listeners
    declineReasonTextarea.addEventListener('input', updateValidation);
    declineReasonTextarea.addEventListener('paste', () => {
        setTimeout(updateValidation, 10); // Small delay to allow paste to complete
    });
    
    restrictionTypeSelect.addEventListener('change', handleRestrictionTypeChange);
    donationRestrictionDate.addEventListener('change', updateSubmitButtonState);
    
    // Initial validation
    updateValidation();
    handleRestrictionTypeChange();
    
    console.log('Decline form validation initialized');
}

// Show confirm approve modal
function showConfirmApproveModal(onConfirm) {
    const confirmEl = document.getElementById('medicalHistoryApproveConfirmModal');
    if (!confirmEl) return false;
    const m = new bootstrap.Modal(confirmEl);
    m.show();
    // Raise z-index above the custom medical history modal (which uses ~10080)
    try {
        confirmEl.style.zIndex = '10110';
        const dlg = confirmEl.querySelector('.modal-dialog');
        if (dlg) dlg.style.zIndex = '10111';
        setTimeout(() => {
            // Pick the top-most backdrop and raise it just below the dialog
            const backs = document.querySelectorAll('.modal-backdrop');
            if (backs && backs.length) {
                backs[backs.length - 1].style.zIndex = '10105';
            }
        }, 10);
    } catch (_) {}
    const confirmBtn = document.getElementById('confirmApproveMedicalHistoryBtn');
    if (confirmBtn) {
        confirmBtn.onclick = null;
        confirmBtn.onclick = () => {
            try { m.hide(); } catch(_) {}
            // Close the main Medical History modal before showing success
            try {
                const mhEl = document.getElementById('medicalHistoryModal');
                if (mhEl) {
                    const mh = bootstrap.Modal.getInstance(mhEl) || new bootstrap.Modal(mhEl);
                    // Remove any guard observers that might re-show it
                    try { (mhEl.__observer || window.__mhObserver)?.disconnect?.(); } catch(_) {}
                    try { mh.hide(); } catch(_) {}
                    // Hard cleanup to ensure it's fully closed
                    setTimeout(() => {
                        try { mhEl.classList.remove('show'); mhEl.style.display = 'none'; mhEl.setAttribute('aria-hidden','true'); } catch(_) {}
                        try { document.querySelectorAll('.modal-backdrop').forEach(b => b.remove()); } catch(_) {}
                        try { document.body.classList.remove('modal-open'); document.body.style.overflow = ''; document.body.style.paddingRight = ''; } catch(_) {}
                    }, 40);
                }
            } catch(_) {}
            if (typeof onConfirm === 'function') onConfirm();
        };
    }
    return true;
}

// Attach handler for Approve button inside the loaded Medical History modal content
function attachInnerModalApproveHandler() {
    try {
        const container = document.getElementById('medicalHistoryModalContent') || document;
        const bind = (btn) => {
            if (!btn || btn.__mhBound) return;
            btn.__mhBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                // Use the single Confirm Action modal defined as mhCustomConfirm
                const message = "Are you sure you want to approve this donor's medical history?";
                if (typeof window.mhCustomConfirm === 'function') {
                    window.mhCustomConfirm(message, function(){
                        // Submit directly without triggering another confirm
                        try { window.__mhApprovePending = true; } catch(_) {}
                        if (typeof window.processFormSubmission === 'function') {
                            window.processFormSubmission('approve');
                        } else if (typeof window.submitModalForm === 'function') {
                            // Fallback if direct submission isn't available
                            window.__mhApprovePending = true;
                            window.submitModalForm('approve');
                        }
                    });
                } else {
                    // Ultimate fallback
                    try { window.__mhApprovePending = true; } catch(_) {}
                    if (typeof window.processFormSubmission === 'function') {
                        window.processFormSubmission('approve');
                    } else if (typeof window.submitModalForm === 'function') {
                        window.submitModalForm('approve');
                    }
                }
                return false;
            });
        };
        // Primary approve button if present
        bind(container.querySelector('#modalApproveButton'));
        // Or when Next button is turned into Approve
        const nextBtn = container.querySelector('#modalNextButton');
        if (nextBtn && !nextBtn.__mhApproveCheckBound) {
            nextBtn.__mhApproveCheckBound = true;
            nextBtn.addEventListener('click', function(e){
                const text = (nextBtn.textContent || '').trim().toLowerCase();
                if (text === 'approve') {
                    e.preventDefault();
                    e.stopPropagation();
                    const message = "Are you sure you want to approve this donor's medical history?";
                    if (typeof window.mhCustomConfirm === 'function') {
                        window.mhCustomConfirm(message, function(){
                            try { window.__mhApprovePending = true; } catch(_) {}
                            if (typeof window.processFormSubmission === 'function') {
                                window.processFormSubmission('approve');
                            } else if (typeof window.submitModalForm === 'function') {
                                window.submitModalForm('approve');
                            }
                        });
                    } else {
                        try { window.__mhApprovePending = true; } catch(_) {}
                        if (typeof window.processFormSubmission === 'function') {
                            window.processFormSubmission('approve');
                        } else if (typeof window.submitModalForm === 'function') {
                            window.submitModalForm('approve');
                        }
                    }
                    return false;
                }
            });
        }
    } catch (e) { console.warn('attachInnerModalApproveHandler error', e); }
}

// Observe dynamic content injection for the medical history modal
(function observeMedicalHistoryModal() {
    try {
        const target = document.getElementById('medicalHistoryModal') || document.body;
        const observer = new MutationObserver(() => {
            attachInnerModalApproveHandler();
        });
        observer.observe(target, { childList: true, subtree: true });
        // Initial attempt
        attachInnerModalApproveHandler();
    } catch (e) { /* noop */ }
})();

// Override submitModalForm to route Approve -> confirm modal -> approved banner -> original submit
(function hookSubmitModalForm() {
    function applyHook() {
        if (typeof window.submitModalForm !== 'function') return false;
        if (window.__origSubmitMH) return true; // already hooked
        window.__origSubmitMH = window.submitModalForm;
        window.submitModalForm = function(action) {
            // Set flag when approving so fetch hook can react on success
            if (String(action).toLowerCase() === 'approve') {
                window.__mhApprovePending = true;
            }
            return window.__origSubmitMH.apply(this, arguments);
        };
        return true;
    }
    if (!applyHook()) {
        let tries = 0;
        const id = setInterval(() => {
            tries++;
            if (applyHook() || tries > 10) clearInterval(id);
        }, 200);
    }
})();

// Hook fetch to detect successful approve submission
(function hookFetchForApproveSuccess(){
    try {
        const orig = window.fetch;
        if (!orig || window.__mhFetchHooked) return;
        window.__mhFetchHooked = true;
        window.fetch = function(input, init) {
            const url = (typeof input === 'string') ? input : (input && input.url) ? input.url : '';
            const isMHProc = url.includes('medical-history-process.php');
            return orig.apply(this, arguments).then(async (resp) => {
                try {
                    if (isMHProc && window.__mhApprovePending) {
                        const clone = resp.clone();
                        let data = null;
                        try { data = await clone.json(); } catch(_) { data = null; }
                        if (data && data.success) {
                            window.__mhApprovePending = false;
                            const donorId = window.lastDonorProfileContext && window.lastDonorProfileContext.donorId;
                            showApprovedThenReturn(donorId, window.lastDonorProfileContext && window.lastDonorProfileContext.screeningData);
                        }
                    }
                } catch(_) {}
                return resp;
            });
        };
    } catch(_) {}
})();

// Queue donor profile reopen while success modal is visible
(function hookOpenDonorProfile(){
    try {
        if (window.__mhOpenHooked) return;
        window.__mhOpenHooked = true;
        const origOpen = window.openDonorProfileModal;
        if (typeof origOpen === 'function') {
            window.__origOpenDonorProfile = origOpen;
            window.openDonorProfileModal = function(screeningData){
                if (window.__mhSuccessActive) {
                    // Queue context until medical history success modal is done
                    const donorId = (screeningData && (screeningData.donor_form_id || screeningData.donor_id)) || (window.currentMedicalHistoryData && window.currentMedicalHistoryData.donor_id);
                    window.lastDonorProfileContext = { donorId: donorId, screeningData: screeningData };
                    window.__mhQueuedReopen = true;
                    console.log('[MH] Queued donor profile reopen due to medical history success state');
                    return; // swallow during success phase
                }
                return window.__origOpenDonorProfile.apply(this, arguments);
            };
        }
    } catch(_) {}
})();

// Prevent any other modal from opening while success modal is active
(function preventOtherModalsDuringSuccess(){
    try {
        if (window.__mhModalGuard) return; window.__mhModalGuard = true;
        document.addEventListener('show.bs.modal', function(ev){
            try {
                // Check for medical history success state only
                const successActive = window.__mhSuccessActive;
                if (successActive) {
                    const target = ev.target;
                    if (target && target.id !== 'medicalHistoryApprovalModal' && target.id !== 'physicalExamAcceptedModal') {
                        // Stop any modal from showing while success is active
                        if (typeof ev.preventDefault === 'function') ev.preventDefault();
                        // Ensure it's hidden in case it already started
                        try {
                            const inst = bootstrap.Modal.getInstance(target) || new bootstrap.Modal(target);
                            inst.hide();
                        } catch(_) {}
                        // Queue donor profile if that's the one
                        if (target.id === 'donorProfileModal') {
                            const ctx = window.lastDonorProfileContext || null;
                            if (ctx) {
                                window.__mhQueuedReopen = true;
                                console.log('[MH] Queued donor profile reopen due to modal guard');
                            }
                        }
                    }
                }
            } catch(_) {}
        }, true);
    } catch(_) {}
})();

// Listen for central approval event and run the success flow
(function listenForApprovalEvent(){
    try {
        window.addEventListener('mh-approved', function(ev){
            try {
                const donorId = ev && ev.detail && ev.detail.donorId ? ev.detail.donorId : (currentMedicalHistoryData && currentMedicalHistoryData.donor_id);
                if (donorId) {
                    window.__mhLastDonorId = String(donorId);
                    window.lastDonorProfileContext = { donorId: String(donorId), screeningData: { donor_form_id: String(donorId) } };
                }
            } catch(_) {}
            const donorId = ev && ev.detail && ev.detail.donorId ? ev.detail.donorId : (currentMedicalHistoryData && currentMedicalHistoryData.donor_id);
            showApprovedThenReturn(donorId, { donor_form_id: donorId });
        });
    } catch(_) {}
})();

function showApprovedThenReturn(donorId, screeningData) {
    console.log('[MH] showApprovedThenReturn called with:', { donorId, screeningData });
    const approvedEl = document.getElementById('medicalHistoryApprovalModal');
    if (!approvedEl) {
        console.error('[MH] medicalHistoryApprovalModal not found!');
        return false;
    }
    
    // Ensure last donor context is set
    try {
        // Use provided parameters or fallback to existing logic
        if (donorId) {
            window.__mhLastDonorId = String(donorId);
            window.lastDonorProfileContext = { donorId: String(donorId), screeningData: screeningData || { donor_form_id: String(donorId) } };
        } else {
            let resolvedDonorId = (window.lastDonorProfileContext && window.lastDonorProfileContext.donorId) || null;
            if (!resolvedDonorId) {
                const formDonor = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                if (formDonor && formDonor.value) resolvedDonorId = formDonor.value;
            }
            if (!resolvedDonorId && window.currentDonorId) resolvedDonorId = window.currentDonorId;
            if (!resolvedDonorId && window.currentMedicalHistoryData && window.currentMedicalHistoryData.donor_id) resolvedDonorId = window.currentMedicalHistoryData.donor_id;
            console.log('[MH] Resolved donorId for success flow:', resolvedDonorId);
            if (resolvedDonorId) {
                window.__mhLastDonorId = String(resolvedDonorId);
                window.lastDonorProfileContext = { donorId: String(resolvedDonorId), screeningData: screeningData || { donor_form_id: String(resolvedDonorId) } };
            }
        }
    } catch(e) { console.warn('[MH] donorId resolve error', e); }
    
    // Mark success phase active and clear any conflicting states
    window.__mhSuccessActive = true;
    window.__peSuccessActive = false; // Clear physical examination success state

    // If Donor Profile modal is currently visible, hide it temporarily and remember to restore
    let donorProfileWasOpen = false;
    try {
        const dpEl = document.getElementById('donorProfileModal');
        if (dpEl && dpEl.classList.contains('show')) {
            donorProfileWasOpen = true;
            const dp = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
            dp.hide();
        }
    } catch(_) {}

    // Ensure any other modals are fully closed, then place success modal on top
    try {
        // Close every visible modal except the success modal
        document.querySelectorAll('.modal.show').forEach(el => {
            if (el !== approvedEl) {
                try {
                    const inst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                    inst.hide();
                } catch(_) {}
                el.classList.remove('show');
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            }
        });
        // Remove all backdrops so success gets a fresh, topmost one
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        // Make sure body is ready for one active modal
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        // Bring success modal to the very top
        approvedEl.style.zIndex = '20010';
        const dlg = approvedEl.querySelector('.modal-dialog');
        if (dlg) dlg.style.zIndex = '20011';
        setTimeout(() => {
            const backs = document.querySelectorAll('.modal-backdrop');
            const backdrop = backs && backs.length ? backs[backs.length - 1] : null;
            if (backdrop) backdrop.style.zIndex = '20005';
        }, 10);
    } catch(_) {}

    const a = new bootstrap.Modal(approvedEl);
    let returned = false;
    function returnToProfile() {
        if (returned) return; returned = true;
        window.__mhSuccessActive = false;
        const ctx = window.lastDonorProfileContext;
        
        // Full cleanup so the page logic won't think another modal is still open
        try {
            // Hide any .modal.show except Donor Profile
            document.querySelectorAll('.modal.show').forEach(el => {
                if (el.id !== 'donorProfileModal') {
                    try {
                        const inst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                        inst.hide();
                    } catch(_) {}
                    el.classList.remove('show');
                    el.style.display = 'none';
                    el.setAttribute('aria-hidden', 'true');
                }
            });
            // Remove all backdrops
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            console.log('[MH] Modal environment cleaned');
        } catch(e) { console.warn('[MH] Cleanup error', e); }
        
        try { if (typeof closeMedicalHistoryModal === 'function') closeMedicalHistoryModal(); } catch(_) {}
        try { window.isOpeningDonorProfile = false; } catch(_) {}
        try { window.skipDonorProfileCleanup = false; } catch(_) {}
        
        // Robust reopen with retries
        let attempts = 0;
        const tryOpen = () => {
            attempts++;
            const donorId = (ctx && (ctx.donorId || (ctx.screeningData && (ctx.screeningData.donor_form_id || ctx.screeningData.donor_id))))
                             || window.__mhLastDonorId
                             || (currentMedicalHistoryData && currentMedicalHistoryData.donor_id);
            console.log('[MH] Reopen attempt', attempts, 'donorId=', donorId);
            if (!donorId) return;
            const dataArg = (ctx && ctx.screeningData) ? ctx.screeningData : { donor_form_id: donorId };
            if (typeof window.openDonorProfileModal === 'function') { try { window.openDonorProfileModal(dataArg); } catch(err) { console.warn('[MH] openDonorProfileModal error', err); } return; }
            if (typeof window.__origOpenDonorProfile === 'function') { try { window.__origOpenDonorProfile(dataArg); } catch(err) { console.warn('[MH] __origOpenDonorProfile error', err); } return; }
            if (forceShowDonorProfileElement()) { console.log('[MH] Forced Donor Profile element visible'); return; }
            if (attempts < 20) setTimeout(tryOpen, 150);
        };
        setTimeout(tryOpen, 80);
    }
    
    // Show success modal after a short tick to ensure others have closed
    try { setTimeout(() => { try { a.show(); } catch(_) {} }, 50); } catch(_) {}
    
    // Bind continue button inside success modal to ensure it closes -> reopen
    setTimeout(() => {
        try {
            const btn = approvedEl.querySelector('button[data-bs-dismiss], .btn, .modal-footer .btn-primary');
            if (btn && !btn.__mhBound) {
                btn.__mhBound = true;
                btn.addEventListener('click', () => { try { a.hide(); } catch(_) {} });
            }
        } catch(_) {}
    }, 20);
    
    approvedEl.addEventListener('hidden.bs.modal', returnToProfile, { once: true });
    setTimeout(() => {
        const backs = document.querySelectorAll('.modal-backdrop');
        const backdrop = backs && backs.length ? backs[backs.length - 1] : null;
        if (backdrop && !backdrop.__mhSuccessBound) {
            backdrop.__mhSuccessBound = true;
            backdrop.addEventListener('click', () => { try { a.hide(); } catch(_) {} }, { once: true });
        }
    }, 20);
    // Keep success visible for 3 seconds, then auto-close (this triggers donor profile reopen)
    setTimeout(() => { try { a.hide(); } catch(_) {} }, 3000);
    return true;
}

function forceShowDonorProfileElement() {
    try {
        const dpEl = document.getElementById('donorProfileModal');
        if (!dpEl) return false;
        // Force visible if not already
        dpEl.style.display = 'block';
        dpEl.classList.add('show');
        dpEl.removeAttribute('aria-hidden');
        dpEl.setAttribute('aria-modal', 'true');
        dpEl.setAttribute('role', 'dialog');
        // Ensure a backdrop exists
        let backdrop = document.querySelector('.modal-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.style.zIndex = '20000';
            document.body.appendChild(backdrop);
        }
        // Body modal-open state (but you suppress padding shifts elsewhere)
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        return true;
    } catch (_) { return false; }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Medical history approval system initialized');
    
    // Initialize decline form validation
    initializeDeclineFormValidation();
    // Bind interceptors for Approve flow ordering
    bindApproveInterceptors();
    // Global capture: block any non-primary Approve inside MH modal
    try {
        const root = document.getElementById('medicalHistoryModal') || document;
        root.addEventListener('click', function(e){
            try {
                const target = e.target.closest('button');
                if (!target) return;
                // Only within MH modal
                if (!target.closest('#medicalHistoryModal')) return;
                const label = (target.textContent || '').trim().toLowerCase();
                const isPrimary = target.id === 'modalApproveButton';
                if (label === 'approve' && !isPrimary) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                    return false;
                }
            } catch(_) {}
        }, true);
        // Block Enter key implicit submits in MH modal unless confirmed
        root.addEventListener('keydown', function(e){
            try {
                if (e.key === 'Enter' && e.target && e.target.closest && e.target.closest('#medicalHistoryModal')) {
                    if (!window.__mhApproveConfirmed) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                        return false;
                    }
                }
            } catch(_) {}
        }, true);
    } catch(_) {}
});

// Footer confirmation functions
function showFooterConfirmModal(onConfirm) {
    console.log('[MH] showFooterConfirmModal called');
    try {
        const modal = document.getElementById('footerConfirmModal');
        if (!modal) {
            console.warn('[MH] Footer confirm modal not found');
            if (onConfirm) onConfirm();
            return;
        }
        
        const m = new bootstrap.Modal(modal);
        
        // Ensure this confirmation modal stacks above donor profile
        try {
            modal.style.zIndex = '20020';
            const dlg = modal.querySelector('.modal-dialog');
            if (dlg) dlg.style.zIndex = '20021';
            // Nudge the newest backdrop just under the dialog
            setTimeout(() => {
                const backs = document.querySelectorAll('.modal-backdrop');
                if (backs && backs.length) {
                    backs[backs.length - 1].style.zIndex = '20015';
                }
            }, 10);
        } catch (_) {}
        
        // Clear any existing handlers
        const confirmBtn = document.getElementById('confirmFooterActionBtn');
        if (confirmBtn) {
            confirmBtn.onclick = null;
        }
        
        // Bind new handler
        if (confirmBtn && onConfirm) {
            confirmBtn.onclick = function() {
                console.log('[MH] Footer confirm button clicked');
                m.hide();
                setTimeout(() => {
                    if (onConfirm) onConfirm();
                }, 100);
            };
        }
        
        m.show();
    } catch(e) {
        console.warn('[MH] Error showing footer confirm modal:', e);
        if (onConfirm) onConfirm();
    }
}

function showFooterActionSuccessModal() {
    console.log('[MH] showFooterActionSuccessModal called');
    try {
        const modal = document.getElementById('footerActionSuccessModal');
        if (!modal) {
            console.warn('[MH] Footer success modal not found');
            return;
        }
        
        const m = new bootstrap.Modal(modal);
        m.show();
        
        // Auto-close after 3 seconds
        setTimeout(() => {
            try { m.hide(); } catch(_) {}
        }, 3000);
    } catch(e) {
        console.warn('[MH] Error showing footer success modal:', e);
    }
}

function showFooterActionFailureModal(message = 'Unable to process the eligibility decision. Please try again.') {
    console.log('[MH] showFooterActionFailureModal called with message:', message);
    try {
        const modal = document.getElementById('footerActionFailureModal');
        if (!modal) {
            console.warn('[MH] Footer failure modal not found');
            return;
        }
        
        // Update message if provided
        const messageEl = document.getElementById('footerActionFailureMessage');
        if (messageEl) {
            messageEl.textContent = message;
        }
        
        const m = new bootstrap.Modal(modal);
        m.show();
        
        // Auto-close after 5 seconds
        setTimeout(() => {
            try { m.hide(); } catch(_) {}
        }, 5000);
    } catch(e) {
        console.warn('[MH] Error showing footer failure modal:', e);
    }
}

// Make footer functions globally available
window.showFooterConfirmModal = showFooterConfirmModal;
window.showFooterActionSuccessModal = showFooterActionSuccessModal;
window.showFooterActionFailureModal = showFooterActionFailureModal;

// Export functions for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeMedicalHistoryApproval,
        handleApproveClick,
        handleDeclineClick,
        showDeclineModal,
        processMedicalHistoryDecline,
        showDeclinedModal,
        showMedicalHistoryToast,
        getDonorRegistrationChannel,
        updateEligibilityTable,
        fetchAllSourceData,
        fetchScreeningFormData,
        fetchPhysicalExamData,
        fetchDonorFormData,
        initializeDeclineFormValidation,
        showFooterConfirmModal,
        showFooterActionSuccessModal,
        showFooterActionFailureModal
    };
}
