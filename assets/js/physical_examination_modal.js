// Physical Examination Modal JavaScript
class PhysicalExaminationModal {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 4; // Steps: 1 Vital, 2 Exam, 3 Blood Bag, 4 Review
        this.formData = {};
        this.screeningData = null;
        
        this.init();
    }
    
    init() {
        // Initialize event listeners
        this.bindEvents();
        this.updateProgressIndicator();
    }
    
    bindEvents() {
        // Next/Previous/Cancel buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('physical-next-btn')) {
                this.nextStep();
            }
            if (e.target.classList.contains('physical-prev-btn')) {
                this.prevStep();
            }
            if (e.target.classList.contains('physical-submit-btn')) {
                this.submitForm();
            }
            if (e.target.classList.contains('physical-cancel-btn')) {
                this.closeModal();
            }
        });
        
        // Form field changes
        document.addEventListener('change', (e) => {
            if (e.target.closest('.physical-examination-modal')) {
                this.handleFieldChange(e.target);
            }
        });
        
        // Step indicator clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.physical-step')) {
                const stepElement = e.target.closest('.physical-step');
                const step = parseInt(stepElement.dataset.step);
                if (step <= this.currentStep) {
                    this.goToStep(step);
                }
            }
        });
        
        // Blood bag type radio button change
        document.addEventListener('change', (e) => {
            if (e.target.name === 'blood_bag_type') {
                this.updateOptionCardSelection(e.target);
            }
        });
    }
    
    openModal(screeningData) {
        this.screeningData = screeningData;
        this.resetForm();
        
        // Pre-populate donor information
        if (screeningData) {
            document.getElementById('physical-donor-id').value = screeningData.donor_form_id || '';
            document.getElementById('physical-screening-id').value = screeningData.screening_id || '';
            
            // By default, DO NOT prefill from database. Enable only if explicitly requested.
            const prefillEnabled = (window.PE_PREFILL === true);
            if (prefillEnabled) {
                // Populate initial screening summary
                this.populateInitialScreeningSummary(screeningData);
                // If a physical examination exists with status pending, fetch and hydrate form
                if (screeningData.donor_form_id) {
                    this.fetchExistingPhysicalExamination(screeningData.donor_form_id);
                }
            }
        }
        
        const modal = document.getElementById('physicalExaminationModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
        
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
    }
    
    closeModal() {
        const modal = document.getElementById('physicalExaminationModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            this.resetForm();
        }, 300);
    }

    async fetchExistingPhysicalExamination(donorId) {
        try {
            // Fetch the latest physical examination record for this donor (includes status)
            const json = await makeApiCall(`../../assets/php_func/fetch_physical_examination_info.php?donor_id=${donorId}`);
            if (!json || !json.success || !json.data) return;
            const exam = json.data;
            const status = (exam.status || '').toString().toLowerCase();
            if (status !== 'pending') return;

            // Hydrate fields
            const setVal = (id, value) => {
                const el = document.getElementById(id);
                if (el && value !== undefined && value !== null && value !== '') {
                    el.value = value;
                    // mark valid for visuals
                    el.classList.add('is-valid');
                }
            };

            setVal('physical-blood-pressure', exam.blood_pressure);
            setVal('physical-pulse-rate', exam.pulse_rate);
            setVal('physical-body-temp', exam.body_temp);
            setVal('physical-gen-appearance', exam.gen_appearance);
            setVal('physical-skin', exam.skin);
            setVal('physical-heent', exam.heent);
            setVal('physical-heart-lungs', exam.heart_and_lungs);

            // Blood bag type
            if (exam.blood_bag_type) {
                const radio = document.querySelector(`input[name="blood_bag_type"][value="${exam.blood_bag_type}"]`);
                if (radio) {
                    radio.checked = true;
                    this.updateOptionCardSelection(radio);
                }
            }

            // Update review summary if already on final step
            this.updateSummary();
        } catch (e) {
            console.warn('Failed to fetch existing physical exam:', e);
        }
    }
    
    resetForm() {
        this.currentStep = 1;
        this.formData = {};
        
        // Reset all form fields
        const form = document.getElementById('physicalExaminationForm');
        if (form) {
            form.reset();
        }
        
        // Reset validation states
        document.querySelectorAll('.physical-step-content .form-control').forEach(field => {
            field.classList.remove('is-invalid', 'is-valid');
        });
        
        this.updateProgressIndicator();
        this.showStep(1);
    }
    
    nextStep() {
        if (this.validateCurrentStep()) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateProgressIndicator();
                this.showStep(this.currentStep);
                
                // Update summary if we're at the review step
                if (this.currentStep === 4) {
                    this.updateSummary();
                }
            }
        }
    }
    
    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateProgressIndicator();
            this.showStep(this.currentStep);
        }
    }
    
    goToStep(step) {
        if (step >= 1 && step <= this.currentStep && step <= this.totalSteps) {
            this.currentStep = step;
            this.updateProgressIndicator();
            this.showStep(step);
            
            if (step === 4) {
                this.updateSummary();
            }
        }
    }
    
    showStep(step) {
        // Hide all step contents
        document.querySelectorAll('.physical-step-content').forEach(stepEl => {
            stepEl.classList.remove('active');
        });
        
        // Show current step content
        const currentStepEl = document.getElementById(`physical-step-${step}`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
        }
        
        // Update button visibility
        this.updateButtons();
    }
    
    updateButtons() {
        const prevBtn = document.querySelector('.physical-prev-btn');
        const nextBtn = document.querySelector('.physical-next-btn');
        const submitBtn = document.querySelector('.physical-submit-btn');
        
        if (prevBtn) prevBtn.style.display = this.currentStep === 1 ? 'none' : 'inline-block';
        if (nextBtn) nextBtn.style.display = this.currentStep === this.totalSteps ? 'none' : 'inline-block';
        if (submitBtn) submitBtn.style.display = this.currentStep === this.totalSteps ? 'inline-block' : 'none';
    }
    
    updateProgressIndicator() {
        for (let i = 1; i <= this.totalSteps; i++) {
            const step = document.querySelector(`.physical-step[data-step="${i}"]`);
            if (step) {
                step.classList.remove('active', 'completed');
                
                if (i < this.currentStep) {
                    step.classList.add('completed');
                } else if (i === this.currentStep) {
                    step.classList.add('active');
                }
            }
        }
        
        // Update progress fill
        const progressFill = document.querySelector('.physical-progress-fill');
        if (progressFill) {
            const progressPercentage = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
            progressFill.style.width = progressPercentage + '%';
        }
    }
    
    validateCurrentStep() {
        let isValid = true;
        const currentStepEl = document.getElementById(`physical-step-${this.currentStep}`);
        
        if (currentStepEl) {
            const requiredFields = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
            
            requiredFields.forEach(field => {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            });
            
            // Additional step-specific validation
            if (this.currentStep === 3) {
                // Validate blood bag type selection (step 4 is now blood bag selection)
                const bloodBagSelected = document.querySelector('input[name="blood_bag_type"]:checked');
                if (!bloodBagSelected) {
                    this.showToast('Please select a blood bag type', 'error');
                    isValid = false;
                }
            }
        }
        
        return isValid;
    }
    
    validateField(field) {
        let isValid = true;
        const value = field.value.trim();
        
        // Check required fields
        if (field.hasAttribute('required') && !value) {
            this.markFieldInvalid(field, 'This field is required');
            isValid = false;
        }
        // Validate blood pressure format
        else if (field.name === 'blood_pressure' && value) {
            const bpPattern = /^[0-9]{2,3}\/[0-9]{2,3}$/;
            if (!bpPattern.test(value)) {
                this.markFieldInvalid(field, 'Format: systolic/diastolic (e.g., 120/80)');
                isValid = false;
            } else {
                this.markFieldValid(field);
            }
        }
        // Validate pulse rate
        else if (field.name === 'pulse_rate' && value) {
            const pulse = parseInt(value);
            if (pulse < 40 || pulse > 200) {
                this.markFieldInvalid(field, 'Pulse rate should be between 40-200 BPM');
                isValid = false;
            } else {
                this.markFieldValid(field);
            }
        }
        // Validate body temperature
        else if (field.name === 'body_temp' && value) {
            const temp = parseFloat(value);
            if (temp < 35 || temp > 42) {
                this.markFieldInvalid(field, 'Temperature should be between 35-42°C');
                isValid = false;
            } else {
                this.markFieldValid(field);
            }
        }
        else if (value) {
            this.markFieldValid(field);
        }
        
        return isValid;
    }
    
    markFieldValid(field) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) feedback.remove();
    }
    
    markFieldInvalid(field, message) {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
        
        let feedback = field.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    }
    
    handleFieldChange(field) {
        // Store field value in formData
        this.formData[field.name] = field.value;
        
        // Validate the field
        this.validateField(field);
        
        // Handle special cases (none for now)
    }
    

    
    updateOptionCardSelection(radioInput) {
        // Remove selected class from all cards in the same group
        const groupName = radioInput.name;
        const allCards = document.querySelectorAll(`input[name="${groupName}"]`);
        
        allCards.forEach(input => {
            const card = input.closest('.physical-option-card');
            if (card) {
                card.classList.remove('selected');
            }
        });
        
        // Add selected class to the current card
        const selectedCard = radioInput.closest('.physical-option-card');
        if (selectedCard) {
            selectedCard.classList.add('selected');
        }
    }
    
    populateInitialScreeningSummary(screeningData) {
        // This method will be called to fetch and populate screening data
        // For now, we'll populate with placeholder data and the screeningData we have
        if (screeningData) {
            // Basic screening information (we'll need to fetch this from the API)
            this.fetchScreeningDetails(screeningData.screening_id, screeningData.donor_form_id);
        }
    }
    
    async fetchScreeningDetails(screeningId, donorId) {
        try {
            // Fetch screening form data
            const screeningData = await makeApiCall(`../../assets/php_func/get_screening_details.php?screening_id=${screeningId}`);
            
            // Fetch donor form data  
            const donorData = await makeApiCall(`../../assets/php_func/get_donor_details.php?donor_id=${donorId}`);
            
            if (screeningData.success && donorData.success) {
                this.populateScreeningFields(screeningData.data, donorData.data);
            }
        } catch (error) {
            console.error('Error fetching screening details:', error);
            // Set default values if fetch fails
            this.setDefaultScreeningValues();
        }
    }
    
    populateScreeningFields(screeningData, donorData) {
        // Populate screening information
        document.getElementById('screening-date').textContent = 
            screeningData.created_at ? new Date(screeningData.created_at).toLocaleDateString() : 'N/A';
        document.getElementById('donor-blood-type').textContent = screeningData.blood_type || 'N/A';
        document.getElementById('donation-type').textContent = screeningData.donation_type || 'N/A';
        document.getElementById('body-weight').textContent = 
            screeningData.body_weight ? screeningData.body_weight + ' kg' : 'N/A';
        document.getElementById('specific-gravity').textContent = screeningData.specific_gravity || 'N/A';
        
        // Populate donor information
        const fullName = `${donorData.surname || ''}, ${donorData.first_name || ''} ${donorData.middle_name || ''}`.trim();
        document.getElementById('donor-name').textContent = fullName || 'N/A';
        document.getElementById('donor-id').textContent = donorData.prc_donor_number || 'N/A';
        document.getElementById('donor-age').textContent = donorData.age || 'N/A';
        document.getElementById('donor-sex').textContent = donorData.sex || 'N/A';
        document.getElementById('donor-civil-status').textContent = donorData.civil_status || 'N/A';
        document.getElementById('donor-mobile').textContent = donorData.mobile || 'N/A';
        document.getElementById('donor-address').textContent = donorData.permanent_address || 'N/A';
        document.getElementById('donor-occupation').textContent = donorData.occupation || 'N/A';
        
        
    }
    
    setDefaultScreeningValues() {
        // Set default values if data fetch fails
        document.getElementById('screening-date').textContent = new Date().toLocaleDateString();
        document.getElementById('donor-name').textContent = 'Loading...';
        document.getElementById('donor-id').textContent = 'Loading...';
        document.getElementById('donor-blood-type').textContent = 'Loading...';
        document.getElementById('donation-type').textContent = 'Loading...';
        document.getElementById('body-weight').textContent = 'Loading...';
        document.getElementById('specific-gravity').textContent = 'Loading...';
        document.getElementById('donor-age').textContent = 'Loading...';
        document.getElementById('donor-sex').textContent = 'Loading...';
        document.getElementById('donor-civil-status').textContent = 'Loading...';
        document.getElementById('donor-mobile').textContent = 'Loading...';
        document.getElementById('donor-address').textContent = 'Loading...';
        document.getElementById('donor-occupation').textContent = 'Loading...';
        
        
    }

    updateSummary() {
        // Update vital signs summary
        document.getElementById('summary-blood-pressure').textContent = 
            document.getElementById('physical-blood-pressure').value || 'Not specified';
        document.getElementById('summary-pulse-rate').textContent = 
            document.getElementById('physical-pulse-rate').value || 'Not specified';
        document.getElementById('summary-body-temp').textContent = 
            document.getElementById('physical-body-temp').value || 'Not specified';
        
        // Update examination findings
        document.getElementById('summary-gen-appearance').textContent = 
            document.getElementById('physical-gen-appearance').value || 'Not specified';
        document.getElementById('summary-skin').textContent = 
            document.getElementById('physical-skin').value || 'Not specified';
        document.getElementById('summary-heent').textContent = 
            document.getElementById('physical-heent').value || 'Not specified';
        document.getElementById('summary-heart-lungs').textContent = 
            document.getElementById('physical-heart-lungs').value || 'Not specified';
        
        // Auto-set remarks as "Accepted" since donor passed physical examination
        // (No UI element needed - this is handled automatically)
        
        // Update blood bag type
        const selectedBloodBag = document.querySelector('input[name="blood_bag_type"]:checked');
        document.getElementById('summary-blood-bag').textContent = 
            selectedBloodBag ? selectedBloodBag.value : 'Not selected';
    }
    
    async submitForm() {
        if (!this.validateCurrentStep()) {
            return;
        }
        // Show confirmation modal first, then proceed
        this.confirmAndSubmit();
    }

    confirmAndSubmit() {
        const donorId = (this.screeningData && (this.screeningData.donor_form_id || this.screeningData.donor_id)) || null;
        if (donorId) {
            try {
                window.lastDonorProfileContext = { donorId: String(donorId), screeningData: this.screeningData };
                window.__peLastDonorId = String(donorId);
            } catch(_) {}
        }
        const confirmEl = document.getElementById('physicalExamApproveConfirmModal');
        if (!confirmEl) {
            this.doSubmit();
            return;
        }
        const modal = new bootstrap.Modal(confirmEl);
        try {
            confirmEl.style.zIndex = '20010';
            const dlg = confirmEl.querySelector('.modal-dialog');
            if (dlg) dlg.style.zIndex = '20011';
            setTimeout(() => {
                const backs = document.querySelectorAll('.modal-backdrop');
                if (backs.length) backs[backs.length - 1].style.zIndex = '20005';
            }, 10);
        } catch(_) {}
        const approveBtn = document.getElementById('confirmApprovePhysicalExamBtn');
        if (approveBtn) {
            approveBtn.onclick = null;
            approveBtn.onclick = () => { try { modal.hide(); } catch(_) {} this.doSubmit(); };
        }
        modal.show();
    }

    async doSubmit() {
        const submitBtn = document.querySelector('.physical-submit-btn');
        const originalText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        }
        try {
            const formData = new FormData(document.getElementById('physicalExaminationForm'));
            const data = {};
            for (let [key, value] of formData.entries()) { data[key] = value; }
            if (this.screeningData) {
                data.donor_id = this.screeningData.donor_form_id;
                data.screening_id = this.screeningData.screening_id;
            }
            data.status = 'Pending';
            // Set remarks to Accepted upon physician approval/submit
            data.remarks = 'Accepted';
            data.is_accepted_examination = true;
            const result = await makeApiCall('../../assets/php_func/process_physical_examination.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (result && result.success) {
                const donorId = (this.screeningData && (this.screeningData.donor_form_id || this.screeningData.donor_id)) || window.__peLastDonorId || null;
                this.showAcceptedThenReturn(donorId, this.screeningData);
            } else {
                throw new Error((result && result.message) || 'Submission failed');
            }
        } catch (error) {
            console.error('Submission error:', error);
            this.showToast('Error: ' + error.message, 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }

    showAcceptedThenReturn(donorId, screeningData) {
        console.log('[PE] showAcceptedThenReturn called with donorId:', donorId);
        try { 
            window.__peSuccessActive = true; 
            window.__mhSuccessActive = false; // Clear medical history success state
        } catch(_) {}
        try {
            const modal = document.getElementById('physicalExaminationModal');
            if (modal) {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    // Fallback to manual closing
                    modal.classList.remove('show');
                    setTimeout(() => { modal.style.display = 'none'; }, 250);
                }
            }
        } catch(_) {}
        if (donorId) {
            try { 
                window.lastDonorProfileContext = { donorId: String(donorId), screeningData: screeningData }; 
                window.__peLastDonorId = String(donorId);
            } catch(_) {}
        }
        const successEl = document.getElementById('physicalExamAcceptedModal');
        if (!successEl) { 
            this.reopenDonorProfileAfterSuccess(donorId, screeningData); 
            return; 
        }
        const m = new bootstrap.Modal(successEl);
        try {
            successEl.style.zIndex = '20010';
            const dlg = successEl.querySelector('.modal-dialog');
            if (dlg) dlg.style.zIndex = '20011';
            setTimeout(() => {
                const backs = document.querySelectorAll('.modal-backdrop');
                if (backs.length) backs[backs.length - 1].style.zIndex = '20005';
            }, 10);
        } catch(_) {}
        
        // Set up single finalize function to prevent duplicate calls
        let finalized = false;
        const finalize = () => { 
            if (finalized) {
                console.log('[PE] Finalize already called, skipping');
                return;
            }
            finalized = true;
            console.log('[PE] Finalizing success modal');
            try { m.hide(); } catch(e) { console.warn('[PE] Error hiding modal:', e); } 
            this.reopenDonorProfileAfterSuccess(donorId, screeningData); 
        };
        
        // Listen for modal hidden event (user closes with X or outside click)
        successEl.addEventListener('hidden.bs.modal', () => {
            console.log('[PE] Success modal hidden event triggered');
            finalize();
        }, { once: true });
        
        // Auto-close after 3 seconds (only if not already finalized)
        setTimeout(() => {
            if (!finalized) {
                console.log('[PE] Auto-closing success modal after 3 seconds');
                finalize(); // Call finalize directly instead of m.hide()
            }
        }, 3000);
        
        m.show();
    }

    reopenDonorProfileAfterSuccess(donorId, screeningData) {
        console.log('[PE] reopenDonorProfileAfterSuccess called with donorId:', donorId);
        try { 
            window.__peSuccessActive = false; 
            window.__mhSuccessActive = false; // Clear medical history success state
        } catch(_) {}
        
        // Clean up all modals properly
        try {
            document.querySelectorAll('.modal.show').forEach(el => {
                try { 
                    const modalInstance = bootstrap.Modal.getInstance(el);
                    if (modalInstance) modalInstance.hide();
                } catch(_) {}
            });
            // Remove all backdrops
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            // Reset body state
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        } catch(_) {}
        
        // Store context for reopening
        const ctx = { donorId: String(donorId), screeningData: screeningData };
        if (donorId) {
            try { 
                window.lastDonorProfileContext = ctx; 
                window.__peLastDonorId = String(donorId);
            } catch(_) {}
        }
        
        // Use the same robust reopening system as medical history modal
        console.log('[PE] Using robust reopening system like medical history modal');
        
        // Robust reopen with retries (same as medical history)
        let attempts = 0;
        const tryOpen = () => {
            attempts++;
            const donorId = (ctx && (ctx.donorId || (ctx.screeningData && (ctx.screeningData.donor_form_id || ctx.screeningData.donor_id))))
                             || window.__peLastDonorId
                             || (window.currentPhysicalExaminationData && window.currentPhysicalExaminationData.donor_id);
            console.log('[PE] Reopen attempt', attempts, 'donorId=', donorId);
            if (!donorId) return;
            const dataArg = (ctx && ctx.screeningData) ? ctx.screeningData : { donor_form_id: donorId };
            if (typeof window.openDonorProfileModal === 'function') { 
                try { 
                    window.openDonorProfileModal(dataArg); 
                    console.log('[PE] Successfully reopened donor profile via openDonorProfileModal');
                    return; 
                } catch(err) { 
                    console.warn('[PE] openDonorProfileModal error', err); 
                } 
            }
            if (typeof window.__origOpenDonorProfile === 'function') { 
                try { 
                    window.__origOpenDonorProfile(dataArg); 
                    console.log('[PE] Successfully reopened donor profile via __origOpenDonorProfile');
                    return; 
                } catch(err) { 
                    console.warn('[PE] __origOpenDonorProfile error', err); 
                } 
            }
            if (this.forceShowDonorProfileElement()) { 
                console.log('[PE] Forced Donor Profile element visible'); 
                return; 
            }
            if (attempts < 20) setTimeout(tryOpen, 150);
        };
        setTimeout(tryOpen, 80);
    }
    
    forceShowDonorProfileElement() {
        try {
            const el = document.getElementById('donorProfileModal');
            if (!el) return false;
            el.classList.add('show');
            el.style.display = 'block';
            el.setAttribute('aria-hidden', 'false');
            el.setAttribute('aria-modal', 'true');
            el.setAttribute('role', 'dialog');
            document.body.classList.add('modal-open');
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.style.zIndex = '1040';
            document.body.appendChild(backdrop);
            return true;
        } catch(_) {
            return false;
        }
    }
    
    showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `physical-toast physical-toast-${type}`;
        toast.innerHTML = `
            <div class="physical-toast-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(toast);
        
        // Show toast
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Remove toast after 4 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 4000);
    }
}

// Initialize the modal when the page loads
document.addEventListener('DOMContentLoaded', function() {
    window.physicalExaminationModal = new PhysicalExaminationModal();
}); 