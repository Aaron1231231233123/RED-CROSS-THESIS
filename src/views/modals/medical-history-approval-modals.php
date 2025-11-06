<?php
/**
 * Enhanced Medical History Approval Modals
 * This file contains the enhanced HTML structure for medical history approval and decline modals
 * Used in the medical history review process with improved UX and workflow management
 */
?>

<!-- Enhanced Medical History Approval Success Modal -->
<div class="modal fade enhanced-modal" id="medicalHistoryApprovalModal" tabindex="-1" aria-labelledby="medicalHistoryApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <h5 class="modal-title" id="medicalHistoryApprovalModalLabel">
                    <i class="fas fa-check-circle me-2"></i>
                    Medical History Approved
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-success mb-3">Approval Successful!</h5>
                    <p class="text-muted mb-0">The donor's medical history has been approved.</p>
                    <p class="text-muted mb-0">The donor can now proceed to the next step in the evaluation process.</p>
                </div>
            </div>
            <div class="modal-footer">
                <div class="workflow-nav-buttons w-100">
                    <button type="button" class="btn enhanced-btn enhanced-btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn enhanced-btn enhanced-btn-primary" id="proceedToPhysicalExamBtn">
                        <i class="fas fa-arrow-right me-2"></i>Proceed to Physical Examination
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Decline Modal -->
<div class="modal fade" id="medicalHistoryDeclineModal" tabindex="-1" aria-labelledby="medicalHistoryDeclineModalLabel" aria-hidden="true" data-bs-backdrop="false" style="z-index: 10090 !important; position: fixed !important;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclineModalLabel">
                    <i class="fas fa-times-circle me-2"></i>
                    Decline Medical History?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div class="text-center">
                    <h5 class="mb-3">Are you sure you want to decline this donor's medical history?</h5>
                    <p class="text-muted mb-4">The donor will be marked as ineligible for donation.</p>
                    
                    <div class="mb-4 text-start">
                        <label for="restrictionType" class="form-label fw-semibold">
                            <i class="fas fa-list-ul me-2 text-primary"></i>Deferral Type *
                        </label>
                        <select class="form-select" id="restrictionType" name="restriction_type" required>
                            <option value="" selected disabled style="color:#6c757d;">Please select deferral type</option>
                            <option value="temporary">Temporarily Deferred</option>
                            <option value="permanent">Permanently Deferred</option>
                        </select>
                        <div class="form-text" id="deferralTypeHelp">Please select a deferral type.</div>
                    </div>
                    
                    <!-- Duration Selection (only for Temporary Deferral) -->
                    <div class="duration-container text-start" id="mhDurationSection" data-initialized="true" style="margin-bottom: 0; padding: 0;">
                        <label class="form-label fw-semibold mb-3">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>Deferral Duration *
                        </label>
                        
                        <!-- Quick Duration Options -->
                        <div class="duration-quick-options mb-3">
                            <div class="row g-2">
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="2">
                                        <div class="duration-number">2</div>
                                        <div class="duration-unit">Days</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="7">
                                        <div class="duration-number">7</div>
                                        <div class="duration-unit">Days</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="14">
                                        <div class="duration-number">14</div>
                                        <div class="duration-unit">Days</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="30">
                                        <div class="duration-number">1</div>
                                        <div class="duration-unit">Month</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="90">
                                        <div class="duration-number">3</div>
                                        <div class="duration-unit">Months</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="180">
                                        <div class="duration-number">6</div>
                                        <div class="duration-unit">Months</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option" data-days="365">
                                        <div class="duration-number">1</div>
                                        <div class="duration-unit">Year</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="duration-option custom-option" data-days="custom">
                                        <div class="duration-number"><i class="fas fa-edit"></i></div>
                                        <div class="duration-unit">Custom</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden select for form submission -->
                        <select class="form-select d-none" id="mhDeclineDuration" name="duration">
                            <option value="2" selected>2 days</option>
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="30">1 month (30 days)</option>
                            <option value="90">3 months (90 days)</option>
                            <option value="180">6 months (180 days)</option>
                            <option value="365">1 year (365 days)</option>
                            <option value="custom">Custom duration...</option>
                        </select>
                    </div>

                    <!-- Custom Duration Input -->
                    <div class="mb-4 custom-duration-container text-start" id="customDurationSection" style="display: none;">
                        <label for="customDuration" class="form-label fw-semibold">
                            <i class="fas fa-keyboard me-2 text-primary"></i>Custom Duration (days) *
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="customDuration" name="custom_duration" min="1" max="3650" placeholder="Enter number of days">
                            <span class="input-group-text">days</span>
                        </div>
                        <div class="form-text">Enter duration between 1 and 3650 days (approximately 10 years)</div>
                    </div>
                    
                    <!-- Duration Summary Display -->
                    <div class="alert alert-info mb-4" id="durationSummary" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Deferral Summary:</strong> <span id="summaryText"></span>
                    </div>
                    
                    <div class="mb-3 text-start">
                        <label for="declineReason" class="form-label fw-semibold">Reason for declinement:</label>
                        <textarea class="form-control" id="declineReason" rows="4" 
                                  placeholder="Please provide a detailed reason for declining this donor's medical history..." 
                                  required maxlength="500" style="min-height: 100px;"></textarea>
                        <div class="form-text">
                            <span id="charCount">0/500 characters</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger px-4" id="submitDeclineBtn">
                    <i class="fas fa-ban me-2"></i>Decline Medical History
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Medical History Decline Modal with duration grid
(function() {
    // Prevent duplicate initialization
    let isInitialized = false;
    let modalInitialized = false;
    
    function initializeMHDDeclineModal() {
        // Prevent multiple initializations
        if (isInitialized) {
            console.log('[MH Decline] Already initialized, skipping...');
            return;
        }
        
        const restrictionType = document.getElementById('restrictionType');
        const durationSection = document.getElementById('mhDurationSection');
        const customDurationSection = document.getElementById('customDurationSection');
        const durationSelect = document.getElementById('mhDeclineDuration');
        const customDurationInput = document.getElementById('customDuration');
        const deferralTypeHelp = document.getElementById('deferralTypeHelp');
        const durationSummary = document.getElementById('durationSummary');
        const summaryText = document.getElementById('summaryText');
        const durationOptions = durationSection ? durationSection.querySelectorAll('.duration-option') : [];
        
        if (!restrictionType || !durationSection) {
            setTimeout(initializeMHDDeclineModal, 100);
            return;
        }
        
        // Mark as initialized
        isInitialized = true;
        console.log('[MH Decline] Initializing modal handlers...');
        
        // Handle deferral type change
        function handleRestrictionTypeChange() {
            const restrictionTypeEl = document.getElementById('restrictionType');
            if (!restrictionTypeEl || !durationSection) {
                console.warn('[MH Decline] Missing elements:', { restrictionType: !!restrictionTypeEl, durationSection: !!durationSection });
                return;
            }
            
            const selectedType = restrictionTypeEl.value;
            console.log('[MH Decline] Restriction type changed to:', selectedType);
            
            if (selectedType === 'temporary') {
                console.log('[MH Decline] Showing duration section for temporary');
                
                // Remove any inline styles that might hide the element
                durationSection.removeAttribute('style');
                
                // Force reflow to ensure style removal takes effect
                void durationSection.offsetHeight;
                
                // Add show class to trigger CSS transition (CSS handles visibility)
                durationSection.classList.add('show');
                
                // Update help text
                if (deferralTypeHelp) {
                    deferralTypeHelp.textContent = 'The donor will be temporarily deferred for the selected duration.';
                }
            } else if (selectedType === 'permanent') {
                console.log('[MH Decline] Hiding duration section for permanent');
                // Hide duration section - remove show class first to trigger transition
                durationSection.classList.remove('show');
                if (customDurationSection) {
                    customDurationSection.classList.remove('show');
                }
                // Wait for transition to complete, then remove spacing
                setTimeout(() => {
                    if (!durationSection.classList.contains('show')) {
                        durationSection.style.display = 'none';
                        durationSection.style.marginBottom = '0';
                        durationSection.style.padding = '0';
                    }
                    if (customDurationSection && !customDurationSection.classList.contains('show')) {
                        customDurationSection.style.display = 'none';
                    }
                }, 400);
                if (durationSummary) {
                    durationSummary.style.display = 'none';
                }
                // Clear duration selections
                durationOptions.forEach(opt => opt.classList.remove('active'));
                if (durationSelect) durationSelect.value = '';
                if (customDurationInput) customDurationInput.value = '';
                if (deferralTypeHelp) {
                    deferralTypeHelp.textContent = 'The donor will be permanently deferred from donation.';
                }
            }
            updateSummary();
        }
        
        // Handle duration option clicks
        durationOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                durationOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active class to clicked option
                this.classList.add('active');
                
                const days = this.getAttribute('data-days');
                
                if (days === 'custom') {
                    if (durationSelect) durationSelect.value = 'custom';
                    customDurationSection.style.display = 'block';
                    setTimeout(() => {
                        customDurationSection.classList.add('show');
                        if (customDurationInput) customDurationInput.focus();
                    }, 50);
                } else {
                    if (durationSelect) durationSelect.value = days;
                    customDurationSection.classList.remove('show');
                    setTimeout(() => {
                        if (!customDurationSection.classList.contains('show')) {
                            customDurationSection.style.display = 'none';
                        }
                    }, 300);
                    if (customDurationInput) customDurationInput.value = '';
                }
                
                updateSummary();
            });
        });
        
        // Handle custom duration input
        if (customDurationInput) {
            customDurationInput.addEventListener('input', function() {
                updateSummary();
                
                // Update the custom option to show the entered value
                const customOption = durationSection ? durationSection.querySelector('.duration-option[data-days="custom"]') : null;
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
        }
        
        function updateSummary() {
            const restrictionTypeEl = document.getElementById('restrictionType');
            if (!restrictionTypeEl || !durationSummary || !summaryText) return;
            
            const selectedType = restrictionTypeEl.value;
            const durationValue = durationSelect ? durationSelect.value : '';
            const customDuration = customDurationInput ? customDurationInput.value : '';
            
            if (!selectedType) {
                durationSummary.style.display = 'none';
                return;
            }

            let summaryMessage = '';
            
            if (selectedType === 'temporary') {
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
            } else if (selectedType === 'permanent') {
                summaryMessage = 'Donor will be permanently deferred from future donations.';
            }

            if (summaryMessage) {
                summaryText.textContent = summaryMessage;
                durationSummary.style.display = 'block';
            } else {
                durationSummary.style.display = 'none';
            }
        }
        
        // Remove any existing listeners by using a unique handler function
        // Store handler reference for removal
        if (restrictionType._mhDeclineHandler) {
            restrictionType.removeEventListener('change', restrictionType._mhDeclineHandler);
        }
        
        // Create new handler and store reference
        restrictionType._mhDeclineHandler = handleRestrictionTypeChange;
        restrictionType.addEventListener('change', handleRestrictionTypeChange);
        
        // Also add input event for immediate feedback
        restrictionType.addEventListener('input', handleRestrictionTypeChange);
        
        // Mark as initialized to prevent other scripts from interfering
        if (durationSection) {
            durationSection.setAttribute('data-initialized', 'true');
        }
        
        // Initialize based on current value - call directly to ensure it runs
        const currentValue = restrictionType.value;
        if (currentValue === 'temporary') {
            // Remove any inline styles that might hide it
            durationSection.removeAttribute('style');
            void durationSection.offsetHeight; // Force reflow
            // Add show class immediately
            durationSection.classList.add('show');
            
            // Update help text
            if (deferralTypeHelp) {
                deferralTypeHelp.textContent = 'The donor will be temporarily deferred for the selected duration.';
            }
            
            // Set default active option (2 days)
            const defaultOption = durationSection ? durationSection.querySelector('.duration-option[data-days="2"]') : null;
            if (defaultOption) {
                defaultOption.classList.add('active');
                if (durationSelect) durationSelect.value = '2';
            }
            updateSummary();
        } else {
            // Hide for permanent - remove show class, let CSS handle it
            durationSection.classList.remove('show');
            if (deferralTypeHelp) {
                deferralTypeHelp.textContent = 'The donor will be permanently deferred from donation.';
            }
        }
    }
    
    // Initialize when DOM is ready - only once
    function setupModalHandlers() {
        const declineModal = document.getElementById('medicalHistoryDeclineModal');
        if (!declineModal) {
            setTimeout(setupModalHandlers, 100);
            return;
        }
        
        // Prevent duplicate event listeners
        if (modalInitialized) {
            return;
        }
        modalInitialized = true;
        
        // Initialize on modal show - only attach listener once
        declineModal.addEventListener('shown.bs.modal', function onModalShown() {
            console.log('[MH Decline] Modal shown, re-initializing handlers...');
            // Reset initialization flag to allow re-initialization when modal opens
            isInitialized = false;
            
            // Wait for modal to fully render
            setTimeout(() => {
                initializeMHDDeclineModal();
                
                // Force update UI based on current selection
                const restrictionType = document.getElementById('restrictionType');
                const durationSection = document.getElementById('mhDurationSection');
                
                if (restrictionType && durationSection) {
                    const currentValue = restrictionType.value;
                    
                    // If temporary is selected, show duration section
                    if (currentValue === 'temporary') {
                        // Remove any inline styles
                        durationSection.removeAttribute('style');
                        void durationSection.offsetHeight; // Force reflow
                        // Add show class - CSS handles visibility
                        durationSection.classList.add('show');
                        
                        // Update help text
                        const deferralTypeHelp = document.getElementById('deferralTypeHelp');
                        if (deferralTypeHelp) {
                            deferralTypeHelp.textContent = 'The donor will be temporarily deferred for the selected duration.';
                        }
                        
                        // Set default selection if none selected
                        const durationSelect = document.getElementById('mhDeclineDuration');
                        if (durationSelect && !durationSelect.value) {
                            durationSelect.value = '2';
                            const defaultOption = durationSection ? durationSection.querySelector('.duration-option[data-days="2"]') : null;
                            if (defaultOption) {
                                defaultOption.classList.add('active');
                            }
                        }
                    } else {
                        // Hide for permanent - ensure no spacing
                        durationSection.classList.remove('show');
                        durationSection.style.display = 'none';
                        durationSection.style.marginBottom = '0';
                        durationSection.style.padding = '0';
                    }
                    
                    // Trigger change to update summary
                    restrictionType.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, 150);
        }, { once: false }); // Allow multiple times but only one listener
        
        // Reset when modal is about to be shown
        declineModal.addEventListener('show.bs.modal', function onModalShow() {
            const durationSection = document.getElementById('mhDurationSection');
            if (durationSection) {
                // Remove show class to hide it
                durationSection.classList.remove('show');
                durationSection.style.display = 'none';
            }
        }, { once: false });
    }
    
    // Setup modal handlers when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setupModalHandlers();
            // Try initial initialization
            setTimeout(initializeMHDDeclineModal, 200);
        });
    } else {
        setupModalHandlers();
        // Try initial initialization
        setTimeout(initializeMHDDeclineModal, 200);
    }
})();

// Character counter for decline reason
(function() {
    function initializeCharCounter() {
        const declineReason = document.getElementById('declineReason');
        const charCount = document.getElementById('charCount');
        
        if (!declineReason || !charCount) {
            setTimeout(initializeCharCounter, 100);
            return;
        }
        
        function updateCharCount() {
            const length = declineReason.value.length;
            charCount.textContent = `${length}/500 characters`;
        }
        
        declineReason.addEventListener('input', updateCharCount);
        updateCharCount(); // Initialize
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCharCounter);
    } else {
        initializeCharCounter();
    }
})();
</script>

<!-- Medical History Decline Confirmation Modal (Before Submission) -->
<div class="modal fade" id="medicalHistoryDeclineConfirmModal" tabindex="-1" aria-labelledby="medicalHistoryDeclineConfirmModalLabel" aria-hidden="true" style="z-index: 10100 !important; position: fixed !important;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclineConfirmModalLabel">
                    <i class="fas fa-question-circle me-2"></i>
                    Confirm Action
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0" style="font-size: 1.1rem;">Decline Medical History for this donor?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn px-4" style="background-color: #6c757d; border-color: #6c757d; color: white;" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;" id="confirmDeclineMedicalHistoryBtn">
                    Yes, Proceed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Declined Success Modal (After Successful Submission) -->
<div class="modal fade" id="medicalHistoryDeclinedSuccessModal" tabindex="-1" aria-labelledby="medicalHistoryDeclinedSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 10120 !important;">
    <div class="modal-dialog modal-dialog-centered" style="z-index: 10081 !important;">
        <div class="modal-content" style="border-radius: 15px; border: none; z-index: 10082 !important;">
            <div class="modal-header" style="background: #b22222; color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryDeclinedSuccessModalLabel">Medical History Declined Successfully</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0" style="font-size: 1.05rem;">Medical History Declined Successfully</p>
            </div>
            <div class="modal-footer border-0" style="display: none;">
            </div>
        </div>
    </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modal fade" id="medicalHistoryApproveConfirmModal" tabindex="-1" aria-labelledby="medicalHistoryApproveConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryApproveConfirmLabel">
                    <i class="fas fa-question-circle me-2"></i>
                    Confirm Action
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0" style="font-size: 1.1rem;">Approve Medical History for this donor?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn px-4" style="background-color: #6c757d; border-color: #6c757d; color: white;" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmApproveMedicalHistoryBtn" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;">
                    Yes, Proceed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Medical History Validation Error Modal -->
<div class="modal fade" id="medicalHistoryValidationModal" tabindex="-1" aria-labelledby="medicalHistoryValidationModalLabel" aria-hidden="true" style="z-index: 10110 !important; position: fixed !important;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <h5 class="modal-title" id="medicalHistoryValidationModalLabel">
                    <i class="fas fa-question-circle me-2"></i>
                    Confirm Action
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0" style="font-size: 1.1rem;" id="medicalHistoryValidationMessage"></p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn px-4" style="background-color: #b22222; border-color: #b22222; color: white;" data-bs-dismiss="modal" id="medicalHistoryValidationOkBtn">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript functionality is handled by the dashboard -->

