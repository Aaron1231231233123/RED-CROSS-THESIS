// Physical Examination Modal JavaScript - Admin Version
class PhysicalExaminationModalAdmin {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 3; // Admin: 1 Vital, 2 Exam, 3 Review (Blood Bag step 3 hidden)
        this.formData = {};
        this.screeningData = null;
        this.isReadonly = false;
        this.forcedDeferralReasons = [];
        this.forcedDeferral = false;
        
        this.init();
    }
    
    init() {
        // Initialize event listeners
        this.bindEvents();
        this.updateProgressIndicator();
    }
    
    bindEvents() {
        // Next/Previous/Cancel buttons - Admin specific
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('physical-next-btn-admin')) {
                this.nextStep();
            }
            if (e.target.classList.contains('physical-prev-btn-admin')) {
                this.prevStep();
            }
            if (e.target.classList.contains('physical-submit-btn-admin')) {
                this.submitForm();
            }
            if (e.target.classList.contains('physical-cancel-btn-admin')) {
                this.closeModal();
            }
        });
        
        // Form field changes - Admin specific
        document.addEventListener('change', (e) => {
            if (e.target.closest('#physicalExaminationModalAdmin')) {
                this.handleFieldChange(e.target);
            }
        });
        
        // Blood pressure input changes - combine systolic and diastolic
        document.addEventListener('input', (e) => {
            if (e.target.id === 'physical-blood-pressure-systolic-admin' || 
                e.target.id === 'physical-blood-pressure-diastolic-admin') {
                this.updateBloodPressure();
            }
        });
        
        // Step indicator clicks - Admin specific
        document.addEventListener('click', (e) => {
            if (e.target.closest('#physicalExaminationModalAdmin .physical-step')) {
                // Prevent step navigation in view mode
                if (this.isReadonly) {
                    return;
                }
                const stepElement = e.target.closest('.physical-step');
                const step = parseInt(stepElement.dataset.step);
                if (step <= this.currentStep) {
                    this.goToStep(step);
                }
            }
        });
    }
    
    async openModal(screeningData) {
        // Check if this is view mode (when physical examination data is passed)
        const isViewMode = screeningData && (screeningData.viewMode === true || screeningData.physical_exam_id || (screeningData.blood_pressure && screeningData.pulse_rate));
        
        if (isViewMode) {
            // VIEW MODE: Show summary of existing physical examination
            this.isReadonly = true;
            this.screeningData = screeningData;
            
            // Populate form with existing data
            this.populateFormFromData(screeningData);
            
            // Update summary with actual data
            this.updateSummaryFromData(screeningData);
            
            const modalEl = document.getElementById('physicalExaminationModalAdmin');
            if (!modalEl) {
                console.error('[PE ADMIN] Modal element not found!');
                return;
            }
            
            // Show via Bootstrap
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false });
            modal.show();
            
            // Go directly to step 3 (summary/review)
            this.currentStep = 3;
            this.updateProgressIndicator();
            this.showStep(3);
            
            // Hide form inputs and make them readonly
            this.setReadonlyMode(true);
            
            // Hide navigation buttons in view mode
            this.updateNavigationButtons();
            
            console.log('[PE ADMIN] Opened in VIEW MODE with data:', screeningData);
        } else {
            // EDIT MODE: Show form for new/existing physical examination
            this.screeningData = screeningData;
            this.resetForm();
            this.isReadonly = false;
            
            // Pre-populate donor information
            if (screeningData) {
                document.getElementById('physical-donor-id-admin').value = screeningData.donor_form_id || screeningData.donor_id || '';
                document.getElementById('physical-screening-id-admin').value = screeningData.screening_id || '';
                
                // If we have existing physical exam data, populate it
                if (screeningData.physical_exam_id || screeningData.blood_pressure) {
                    this.populateFormFromData(screeningData);
                }
                
                // Populate summary basics
                this.populateInitialScreeningSummary(screeningData);
            }
            
            const modalEl = document.getElementById('physicalExaminationModalAdmin');
            
            if (!modalEl) {
                console.error('[PE ADMIN DEBUG] Modal element not found!');
                return;
            }
            
            // Show via Bootstrap
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false });
            
            modal.show();
            
            this.currentStep = 1;
            this.updateProgressIndicator();
            this.showStep(1);
            
            // Make sure form is editable
            this.setReadonlyMode(false);
        }
    }
    
    populateFormFromData(data) {
        const setControlValue = (id, value) => {
            if (value == null) return;
            const el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                const exists = Array.from(el.options || []).some(opt => opt.value === value);
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = value;
                    opt.dataset.dynamic = 'true';
                    el.appendChild(opt);
                }
            }
            el.value = value;
            const placeholder = el.querySelector('option[disabled][hidden]');
            if (placeholder && placeholder.selected) {
                placeholder.selected = false;
            }
        };
        // Populate blood pressure
        if (data.blood_pressure) {
            this.parseAndSetBloodPressure(data.blood_pressure);
        }
        
        // Populate other vital signs
        if (data.pulse_rate) {
            const pulseInput = document.getElementById('physical-pulse-rate-admin');
            if (pulseInput) pulseInput.value = data.pulse_rate;
        }
        
        if (data.body_temp) {
            const tempInput = document.getElementById('physical-body-temp-admin');
            if (tempInput) tempInput.value = data.body_temp;
        }
        
        // Populate examination findings
        setControlValue('physical-gen-appearance-admin', data.gen_appearance);
        setControlValue('physical-skin-admin', data.skin);
        setControlValue('physical-heent-admin', data.heent);
        setControlValue('physical-heart-lungs-admin', data.heart_and_lungs);
        
        // Set donor and screening IDs
        if (data.donor_id) {
            const donorInput = document.getElementById('physical-donor-id-admin');
            if (donorInput) donorInput.value = data.donor_id;
        }
        
        if (data.screening_id) {
            const screeningInput = document.getElementById('physical-screening-id-admin');
            if (screeningInput) screeningInput.value = data.screening_id;
        }
        this.evaluateDeferralState();
    }
    
    updateSummaryFromData(data) {
        // Update vital signs in summary
        const bpValue = data.blood_pressure || '-';
        const pulseRate = data.pulse_rate || '-';
        const bodyTemp = data.body_temp || '-';
        
        const bpEl = document.getElementById('summary-blood-pressure-admin');
        const pulseEl = document.getElementById('summary-pulse-rate-admin');
        const tempEl = document.getElementById('summary-body-temp-admin');
        
        if (bpEl) bpEl.textContent = bpValue;
        if (pulseEl) pulseEl.textContent = pulseRate;
        if (tempEl) tempEl.textContent = bodyTemp;
        
        // Update examination findings in summary
        const genAppEl = document.getElementById('summary-gen-appearance-admin');
        const skinEl = document.getElementById('summary-skin-admin');
        const heentEl = document.getElementById('summary-heent-admin');
        const heartLungsEl = document.getElementById('summary-heart-lungs-admin');
        
        if (genAppEl) genAppEl.textContent = data.gen_appearance || '-';
        if (skinEl) skinEl.textContent = data.skin || '-';
        if (heentEl) heentEl.textContent = data.heent || '-';
        if (heartLungsEl) heartLungsEl.textContent = data.heart_and_lungs || '-';
        
        // Update physician name if available
        if (data.physician) {
            const physicianEl = document.getElementById('summary-interviewer-admin');
            if (physicianEl) physicianEl.textContent = data.physician;
        }
        
        // Update blood bag type if available
        if (data.blood_bag_type) {
            const bloodBagEl = document.getElementById('summary-blood-bag-admin');
            if (bloodBagEl) bloodBagEl.textContent = data.blood_bag_type;
        }
    }
    
    setReadonlyMode(readonly) {
        const modal = document.getElementById('physicalExaminationModalAdmin');
        if (!modal) return;
        
        // Get all form inputs, selects, and textareas
        const formElements = modal.querySelectorAll('input, select, textarea');
        
        formElements.forEach(element => {
            if (readonly) {
                element.disabled = true;
                element.readOnly = true;
                element.style.backgroundColor = '#f8f9fa';
                element.style.cursor = 'not-allowed';
            } else {
                element.disabled = false;
                element.readOnly = false;
                element.style.backgroundColor = '';
                element.style.cursor = '';
            }
        });
        
        // Hide step indicators in view mode
        const stepIndicators = modal.querySelectorAll('.physical-step');
        stepIndicators.forEach(step => {
            if (readonly) {
                step.style.display = 'none';
            } else {
                step.style.display = 'block';
            }
        });
        
        // Hide progress line in view mode
        const progressLine = modal.querySelector('.physical-progress-line');
        if (progressLine) {
            progressLine.style.display = readonly ? 'none' : 'block';
        }
        
        // Update modal title
        const modalTitle = modal.querySelector('.modal-title');
        if (modalTitle) {
            if (readonly) {
                modalTitle.innerHTML = '<i class="fas fa-stethoscope me-2"></i>Physical Examination Summary';
            } else {
                modalTitle.innerHTML = '<i class="fas fa-stethoscope me-2"></i>Physical Examination Form - Admin';
            }
        }
    }
    
    populateInitialScreeningSummary(screeningData) {
        
        // Populate donor information in summary
        const interviewerEl = document.getElementById('summary-interviewer-admin');
        if (interviewerEl && screeningData.interviewer_name) {
            interviewerEl.textContent = screeningData.interviewer_name;
        }
        
        // Set default blood bag type for admin
        const bloodBagEl = document.getElementById('summary-blood-bag-admin');
        if (bloodBagEl) {
            bloodBagEl.textContent = 'Single (Default)';
        }
    }
    
    resetForm() {
        
        // Reset form data
        this.formData = {};
        
        // Reset all form fields
        const form = document.getElementById('physicalExaminationFormAdmin');
        if (form) {
            form.reset();
        }
        try {
            document.querySelectorAll('#physicalExaminationFormAdmin select option[data-dynamic="true"]').forEach(opt => opt.remove());
        } catch (_) {}
        this.forcedDeferralReasons = [];
        this.forcedDeferral = false;
        
        // Reset BP inputs specifically
        const systolicInput = document.getElementById('physical-blood-pressure-systolic-admin');
        const diastolicInput = document.getElementById('physical-blood-pressure-diastolic-admin');
        const hiddenBPInput = document.getElementById('physical-blood-pressure-admin');
        if (systolicInput) systolicInput.value = '';
        if (diastolicInput) diastolicInput.value = '';
        if (hiddenBPInput) hiddenBPInput.value = '';
        
        // Reset step indicators
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
        
        // Clear summary fields
        this.clearSummaryFields();
        this.evaluateDeferralState();
    }
    
    parseAndSetBloodPressure(bpValue) {
        // Parse BP value from "120/80" format and set individual inputs
        if (!bpValue || typeof bpValue !== 'string') return;
        
        const parts = bpValue.split('/');
        if (parts.length === 2) {
            const systolic = parts[0].trim();
            const diastolic = parts[1].trim();
            
            const systolicInput = document.getElementById('physical-blood-pressure-systolic-admin');
            const diastolicInput = document.getElementById('physical-blood-pressure-diastolic-admin');
            
            if (systolicInput && systolic) {
                systolicInput.value = systolic;
            }
            if (diastolicInput && diastolic) {
                diastolicInput.value = diastolic;
            }
            
            // Update the hidden field
            this.updateBloodPressure();
        }
    }
    
    clearSummaryFields() {
        const summaryFields = [
            'summary-blood-pressure-admin',
            'summary-pulse-rate-admin',
            'summary-body-temp-admin',
            'summary-gen-appearance-admin',
            'summary-skin-admin',
            'summary-heent-admin',
            'summary-heart-lungs-admin'
        ];
        
        summaryFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.textContent = '-';
            }
        });
    }
    
    nextStep() {
        // Prevent navigation in view mode
        if (this.isReadonly) {
            return;
        }
        
        if (this.validateCurrentStep()) {
            if (this.currentStep === 2 && this.evaluateDeferralState()) {
                this.showToast('This donor must be deferred based on the findings. Please use the Defer action.', 'error');
                return;
            }
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.showStep(this.currentStep);
                this.updateProgressIndicator();
                
                if (this.currentStep === 3) {
                    this.updateSummary();
                }
            }
        }
    }
    
    prevStep() {
        // Prevent navigation in view mode
        if (this.isReadonly) {
            return;
        }
        
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
            this.updateProgressIndicator();
        }
    }
    
    goToStep(step) {
        // Prevent navigation in view mode
        if (this.isReadonly) {
            return;
        }
        
        if (step === 3 && this.evaluateDeferralState()) {
            this.showToast('This donor must be deferred based on the findings. Please use the Defer action.', 'error');
            return;
        }
        
        if (step >= 1 && step <= this.currentStep && step <= this.totalSteps) {
            this.currentStep = step;
            this.showStep(this.currentStep);
            this.updateProgressIndicator();
            
            if (this.currentStep === 3) {
                this.updateSummary();
            }
        }
    }
    
    showStep(step) {
        
        // Hide all step contents
        const stepContents = document.querySelectorAll('#physicalExaminationModalAdmin .physical-step-content');
        stepContents.forEach(content => {
            content.classList.remove('active');
            content.style.display = 'none';
        });
        
        // Show current step
        const currentStepEl = document.getElementById(`physical-step-${step}-admin`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
            currentStepEl.style.display = 'block';
        }
        
        // In view mode, hide steps 1 and 2 completely
        if (this.isReadonly && step === 3) {
            const step1 = document.getElementById('physical-step-1-admin');
            const step2 = document.getElementById('physical-step-2-admin');
            if (step1) step1.style.display = 'none';
            if (step2) step2.style.display = 'none';
        }
        
        // Update navigation buttons
        this.updateNavigationButtons();
    }
    
    updateNavigationButtons() {
        const prevBtn = document.querySelector('#physicalExaminationModalAdmin .physical-prev-btn-admin');
        const nextBtn = document.querySelector('#physicalExaminationModalAdmin .physical-next-btn-admin');
        const submitBtn = document.querySelector('#physicalExaminationModalAdmin .physical-submit-btn-admin');
        const deferBtn = document.querySelector('#physicalExaminationModalAdmin .physical-defer-btn-admin');
        
        if (this.isReadonly) {
            // In view mode, hide all navigation buttons except close (handled by Bootstrap)
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'none';
            if (deferBtn) deferBtn.style.display = 'none';
        } else {
            // In edit mode, show appropriate buttons
            if (prevBtn) prevBtn.style.display = this.currentStep === 1 ? 'none' : 'inline-block';
            if (nextBtn) nextBtn.style.display = this.currentStep === 3 ? 'none' : 'inline-block'; // Step 3 is final
            
            if (submitBtn) {
                submitBtn.style.display = this.currentStep === 3 ? 'inline-block' : 'none'; // Step 3 is final
            }
            
            if (deferBtn) {
                deferBtn.style.display = this.currentStep === 3 ? 'inline-block' : 'none'; // Show defer on review step
            }
        }
        const deferralRequired = this.evaluateDeferralState();
        if (!this.isReadonly) {
            if (deferralRequired) {
                if (nextBtn && this.currentStep < this.totalSteps) {
                    nextBtn.style.display = 'inline-block';
                    nextBtn.disabled = true;
                    nextBtn.classList.add('disabled');
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('disabled');
                }
                if (deferBtn) {
                    deferBtn.style.display = 'inline-block';
                    deferBtn.disabled = false;
                }
            } else {
                if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.classList.remove('disabled');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('disabled');
                }
            }
        }
    }
    
    updateProgressIndicator() {
        
        const steps = document.querySelectorAll('#physicalExaminationModalAdmin .physical-step');
        steps.forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');
            
            // Show all 3 steps (no hiding needed)
            step.style.display = 'block';
            
            if (stepNumber < this.currentStep) {
                step.classList.add('completed');
            } else if (stepNumber === this.currentStep) {
                step.classList.add('active');
            }
        });
        
        // Update progress fill
        const progressFill = document.querySelector('#physicalExaminationModalAdmin .physical-progress-fill');
        if (progressFill) {
            const progressPercentage = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
            progressFill.style.width = progressPercentage + '%';
        }
    }
    
    validateCurrentStep() {
        
        // Skip validation on review step (step 3) - we'll validate all fields during submission
        if (this.currentStep === 3) {
            return true;
        }
        
        const currentStepEl = document.getElementById(`physical-step-${this.currentStep}-admin`);
        if (currentStepEl) {
            const requiredFields = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
            
            for (let field of requiredFields) {
                // Skip hidden fields
                if (field.type === 'hidden') continue;
                
                if (!field.value.trim()) {
                    field.focus();
                    this.showToast('Please fill in all required fields', 'error');
                    return false;
                }
            }
            
            // Special validation for blood pressure in step 1
            if (this.currentStep === 1) {
                const systolic = document.getElementById('physical-blood-pressure-systolic-admin')?.value;
                const diastolic = document.getElementById('physical-blood-pressure-diastolic-admin')?.value;
                if (!systolic || !diastolic) {
                    const firstEmpty = !systolic ? 
                        document.getElementById('physical-blood-pressure-systolic-admin') : 
                        document.getElementById('physical-blood-pressure-diastolic-admin');
                    if (firstEmpty) firstEmpty.focus();
                    this.showToast('Please enter both systolic and diastolic blood pressure values', 'error');
                    return false;
                }
            }
        }
        
        return true;
    }
    
    updateBloodPressure() {
        const systolic = document.getElementById('physical-blood-pressure-systolic-admin')?.value || '';
        const diastolic = document.getElementById('physical-blood-pressure-diastolic-admin')?.value || '';
        const hiddenField = document.getElementById('physical-blood-pressure-admin');
        
        if (systolic && diastolic) {
            const combinedValue = `${systolic}/${diastolic}`;
            if (hiddenField) {
                hiddenField.value = combinedValue;
                console.log('[PE ADMIN] Updated blood pressure hidden field:', combinedValue);
            } else {
                console.error('[PE ADMIN] Blood pressure hidden field not found!');
            }
        } else {
            if (hiddenField) {
                hiddenField.value = '';
            }
            console.warn('[PE ADMIN] Blood pressure values incomplete - systolic:', systolic, 'diastolic:', diastolic);
        }
    }
    
    updateSummary() {
        
        // Update vital signs
        const systolic = document.getElementById('physical-blood-pressure-systolic-admin')?.value || '';
        const diastolic = document.getElementById('physical-blood-pressure-diastolic-admin')?.value || '';
        const bloodPressure = (systolic && diastolic) ? `${systolic}/${diastolic}` : '-';
        const pulseRate = document.getElementById('physical-pulse-rate-admin')?.value || '-';
        const bodyTemp = document.getElementById('physical-body-temp-admin')?.value || '-';
        
        document.getElementById('summary-blood-pressure-admin').textContent = bloodPressure;
        document.getElementById('summary-pulse-rate-admin').textContent = pulseRate;
        document.getElementById('summary-body-temp-admin').textContent = bodyTemp;
        
        // Update examination findings
        const genAppearance = document.getElementById('physical-gen-appearance-admin')?.value || '-';
        const skin = document.getElementById('physical-skin-admin')?.value || '-';
        const heent = document.getElementById('physical-heent-admin')?.value || '-';
        const heartLungs = document.getElementById('physical-heart-lungs-admin')?.value || '-';
        
        document.getElementById('summary-gen-appearance-admin').textContent = genAppearance;
        document.getElementById('summary-skin-admin').textContent = skin;
        document.getElementById('summary-heent-admin').textContent = heent;
        document.getElementById('summary-heart-lungs-admin').textContent = heartLungs;
    }
    
    async submitForm() {
        if (!this.isReadonly && this.evaluateDeferralState()) {
            this.showToast('This donor must be deferred based on the findings. Please use the Defer action.', 'error');
            return;
        }
        
        if (!this.validateCurrentStep()) {
            return;
        }
        
        // Update blood pressure before validation
        this.updateBloodPressure();
        
        // Validate all required fields before submission
        const requiredFields = [
            { name: 'blood_pressure', id: 'physical-blood-pressure-admin' },
            { name: 'pulse_rate', id: 'physical-pulse-rate-admin' },
            { name: 'body_temp', id: 'physical-body-temp-admin' },
            { name: 'gen_appearance', id: 'physical-gen-appearance-admin' },
            { name: 'skin', id: 'physical-skin-admin' },
            { name: 'heent', id: 'physical-heent-admin' },
            { name: 'heart_and_lungs', id: 'physical-heart-lungs-admin' }
        ];
        
        const missingFields = [];
        for (const field of requiredFields) {
            const element = document.getElementById(field.id);
            if (!element || !element.value.trim()) {
                missingFields.push(field.name);
            }
        }
        
        // Also check individual BP fields
        const systolic = document.getElementById('physical-blood-pressure-systolic-admin')?.value;
        const diastolic = document.getElementById('physical-blood-pressure-diastolic-admin')?.value;
        if (!systolic || !diastolic) {
            if (!missingFields.includes('blood_pressure')) {
                missingFields.push('blood_pressure');
            }
        }
        
        if (missingFields.length > 0) {
            this.showToast(`Missing required fields: ${missingFields.join(', ')}`, 'error');
            return;
        }
        
        // Collect form data
        const formData = new FormData();
        
        // Get donor and screening IDs
        const donorId = document.getElementById('physical-donor-id-admin')?.value;
        const screeningId = document.getElementById('physical-screening-id-admin')?.value;
        
        if (!donorId) {
            this.showToast('Missing donor ID', 'error');
            return;
        }
        
        formData.append('donor_id', donorId);
        if (screeningId) formData.append('screening_id', screeningId);
        
        // Ensure blood pressure is updated before submission
        this.updateBloodPressure();
        
        // Double-check blood pressure is set correctly
        const bpHiddenField = document.getElementById('physical-blood-pressure-admin');
        const bpSystolic = document.getElementById('physical-blood-pressure-systolic-admin')?.value || '';
        const bpDiastolic = document.getElementById('physical-blood-pressure-diastolic-admin')?.value || '';
        
        // If hidden field is empty but we have both values, combine them
        if ((!bpHiddenField?.value || bpHiddenField.value.trim() === '') && bpSystolic && bpDiastolic) {
            const combinedBP = `${bpSystolic}/${bpDiastolic}`;
            if (bpHiddenField) {
                bpHiddenField.value = combinedBP;
                console.log('[PE ADMIN] Combined BP values into hidden field:', combinedBP);
            }
        }
        
        // Verify blood pressure value before submission
        const finalBPValue = bpHiddenField?.value || '';
        if (!finalBPValue || finalBPValue.trim() === '') {
            this.showToast('Blood pressure is required. Please enter both systolic and diastolic values.', 'error');
            return;
        }
        console.log('[PE ADMIN] Final blood pressure value to submit:', finalBPValue);
        
        // Get form field values
        const fields = [
            { name: 'blood_pressure', id: 'physical-blood-pressure-admin' },
            { name: 'pulse_rate', id: 'physical-pulse-rate-admin' },
            { name: 'body_temp', id: 'physical-body-temp-admin' },
            { name: 'gen_appearance', id: 'physical-gen-appearance-admin' },
            { name: 'skin', id: 'physical-skin-admin' },
            { name: 'heent', id: 'physical-heent-admin' },
            { name: 'heart_and_lungs', id: 'physical-heart-lungs-admin' }
        ];
        
        fields.forEach(field => {
            const element = document.getElementById(field.id);
            if (element && element.value) {
                formData.append(field.name, element.value);
                console.log(`[PE ADMIN] Added ${field.name}:`, element.value);
            } else {
                console.warn(`[PE ADMIN] Field ${field.name} (${field.id}) is missing or empty`);
            }
        });
        
        // Set default values for admin
        formData.append('remarks', 'Accepted');
        formData.append('blood_bag_type', 'Single');
        
        // Debug: Log all form data being sent
        for (let [key, value] of formData.entries()) {
        }
        
        try {
            this.showToast('Submitting physical examination...', 'info');
            
            const response = await fetch('../../src/handlers/physical-examination-handler-admin.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('Physical examination completed successfully!', 'success');
                
                // Check if both medical history and physical examination are completed
                this.checkAndUpdateDonorStatus(result.donor_id);
                
                // Refresh donor modal if it's open
                const donorId = result.donor_id;
                if (donorId) {
                    const donorModal = document.getElementById('donorModal');
                    if (donorModal && donorModal.classList.contains('show')) {
                        const eligibilityId = window.currentDetailsEligibilityId || window.currentEligibilityId || `pending_${donorId}`;
                        if (typeof AdminDonorModal !== 'undefined' && AdminDonorModal && AdminDonorModal.fetchDonorDetails) {
                            setTimeout(() => {
                                AdminDonorModal.fetchDonorDetails(donorId, eligibilityId);
                            }, 500);
                        } else if (typeof window.fetchDonorDetails === 'function') {
                            setTimeout(() => {
                                window.fetchDonorDetails(donorId, eligibilityId);
                            }, 500);
                        }
                    }
                }
                
                // Close modal after a short delay
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('physicalExaminationModalAdmin'));
                    if (modal) {
                        modal.hide();
                    }
                }, 800);
            } else {
                this.showToast(result.message || 'Failed to submit physical examination', 'error');
            }
            
        } catch (error) {
            console.error('[PE ADMIN DEBUG] Submission error:', error);
            this.showToast('Error: ' + error.message, 'error');
        }
    }
    
    closeModal() {
        const modalEl = document.getElementById('physicalExaminationModalAdmin');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
        }
        
        // Reset readonly state when closing
        this.isReadonly = false;
        this.setReadonlyMode(false);
    }
    
    showToast(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    evaluateDeferralState() {
        try {
            const normalize = (val) => (val || '').toString().trim().toLowerCase();
            const genVal = normalize(document.getElementById('physical-gen-appearance-admin')?.value);
            const skinVal = normalize(document.getElementById('physical-skin-admin')?.value);
            const reasons = [];
            if (genVal === 'deferred for further assessment') {
                reasons.push('General appearance requires donor deferral.');
            }
            if (skinVal === 'with puncture marks (defer)') {
                reasons.push('Skin findings require donor deferral.');
            }
            this.forcedDeferralReasons = reasons;
            this.forcedDeferral = reasons.length > 0;
            const warningEl = document.getElementById('physical-deferral-warning-admin');
            if (warningEl) {
                if (this.forcedDeferral) {
                    const listHtml = reasons.map(reason => `<li>${reason}</li>`).join('');
                    warningEl.innerHTML = `<strong>Deferral required:</strong><ul style="margin: 0 0 0.5rem 1.2rem; padding: 0;">${listHtml}</ul><span>Please use the Defer action to proceed.</span>`;
                    warningEl.style.display = 'block';
                } else {
                    warningEl.innerHTML = '';
                    warningEl.style.display = 'none';
                }
            }
        } catch (_) {
            this.forcedDeferral = false;
            this.forcedDeferralReasons = [];
        }
        return this.forcedDeferral;
    }
    
    handleFieldChange(field) {
        
        // Store field data
        this.formData[field.name] = field.value;
        
        const needsDeferral = this.evaluateDeferralState();
        if (!this.isReadonly && needsDeferral && this.currentStep > 2) {
            this.currentStep = 2;
            this.showStep(2);
            this.updateProgressIndicator();
            return;
        }
        this.updateNavigationButtons();
    }
    
    async checkAndUpdateDonorStatus(donorId) {
        
        try {
            // Fetch eligibility data to check all section statuses
            const eligibilityResponse = await fetch(`../../assets/php_func/fetch_donor_medical_info.php?donor_id=${donorId}&_=${Date.now()}`);
            const eligibilityData = await eligibilityResponse.json();
            
            
            if (eligibilityData && eligibilityData.success && eligibilityData.data) {
                const eligibility = eligibilityData.data;
                
                // Get all section status values (same as donor information modal)
                const interviewerMedical = eligibility.medical_history_status || '';
                const interviewerScreening = eligibility.screening_status || '';
                const physicianPhysical = eligibility.physical_status || '';
                
                console.log('[PE ADMIN DEBUG] Status values:', {
                    interviewerMedical,
                    interviewerScreening,
                    physicianPhysical
                });
                
                // Determine new status based on the new workflow logic
                let newStatus;
                
                // Check if Medical History is Completed and Initial Screening is Passed
                const isMedicalHistoryCompleted = this.isCompletedStatus(interviewerMedical);
                const isScreeningPassed = this.isPassedStatus(interviewerScreening);
                const isPhysicalExamApproved = this.isAcceptedStatus(physicianPhysical);
                
                if (isMedicalHistoryCompleted && isScreeningPassed && isPhysicalExamApproved) {
                    // All interviewer and physician phases complete -> Pending (Collection)
                    newStatus = 'Pending (Collection)';
                } else if (isMedicalHistoryCompleted && isScreeningPassed) {
                    // Interviewer phase complete, physician phase pending -> Pending (Examination)
                    newStatus = 'Pending (Examination)';
                } else {
                    // Interviewer phase pending -> Pending (Screening)
                    newStatus = 'Pending (Screening)';
                }
                
                console.log('[PE ADMIN DEBUG] Determined new status:', newStatus);
                this.updateDonorStatusBadge(donorId, newStatus);
                
            } else {
                console.warn('[PE ADMIN DEBUG] No eligibility data found, using fallback');
                this.updateDonorStatusBadge(donorId, 'Pending (Screening)');
            }
            
        } catch (error) {
            console.error('[PE ADMIN DEBUG] Error checking donor status:', error);
            // Fallback to Pending (Screening) if we can't check
            this.updateDonorStatusBadge(donorId, 'Pending (Screening)');
        }
    }
    
    isAcceptedStatus(status) {
        if (!status) return false;
        const statusLower = String(status).toLowerCase();
        return (
            statusLower.includes('approved') ||
            statusLower.includes('accepted') ||
            statusLower.includes('completed') ||
            statusLower.includes('passed') ||
            statusLower.includes('success')
        );
    }
    
    isCompletedStatus(status) {
        if (!status) return false;
        const statusLower = String(status).toLowerCase();
        return (
            statusLower.includes('completed') ||
            statusLower.includes('approved') ||
            statusLower.includes('accepted')
        );
    }
    
    isPassedStatus(status) {
        if (!status) return false;
        const statusLower = String(status).toLowerCase();
        return (
            statusLower.includes('passed') ||
            statusLower.includes('approved') ||
            statusLower.includes('accepted') ||
            statusLower.includes('completed')
        );
    }
    
    updateDonorStatusBadge(donorId, newStatus) {
        
        // Find the donor row by donor ID
        const donorRows = document.querySelectorAll('tr[data-donor-id]');
        let targetRow = null;
        
        for (let row of donorRows) {
            if (row.getAttribute('data-donor-id') === String(donorId)) {
                targetRow = row;
                break;
            }
        }
        
        if (targetRow) {
            // Find the status badge in the row
            const statusBadge = targetRow.querySelector('.badge');
            if (statusBadge) {
                // Update the badge text
                statusBadge.textContent = newStatus;
                
                // Update the badge class based on status
                statusBadge.className = 'badge'; // Reset classes
                const statusLower = newStatus.toLowerCase();
                
                if (statusLower.includes('pending')) {
                    statusBadge.classList.add('bg-warning', 'text-dark');
                } else if (statusLower.includes('approved') || statusLower.includes('eligible') || statusLower.includes('success')) {
                    statusBadge.classList.add('bg-success');
                } else if (statusLower.includes('declined') || statusLower.includes('defer') || statusLower.includes('fail') || statusLower.includes('ineligible')) {
                    statusBadge.classList.add('bg-danger');
                } else if (statusLower.includes('review') || statusLower.includes('medical') || statusLower.includes('physical')) {
                    statusBadge.classList.add('bg-info', 'text-dark');
                } else {
                    statusBadge.classList.add('bg-secondary');
                }
                
            } else {
                console.warn('[PE ADMIN DEBUG] Status badge not found in donor row');
            }
        } else {
            console.warn('[PE ADMIN DEBUG] Donor row not found for ID:', donorId);
        }
    }
    
    // Make status update functions globally available
    static async updateDonorStatusGlobally(donorId) {
        try {
            // Fetch eligibility data to check all section statuses
            const eligibilityResponse = await fetch(`../../assets/php_func/fetch_donor_medical_info.php?donor_id=${donorId}&_=${Date.now()}`);
            const eligibilityData = await eligibilityResponse.json();
            
            if (eligibilityData && eligibilityData.success && eligibilityData.data) {
                const eligibility = eligibilityData.data;
                
                // Get all section status values
                const interviewerMedical = eligibility.medical_history_status || '';
                const interviewerScreening = eligibility.screening_status || '';
                const physicianPhysical = eligibility.physical_status || '';
                
                console.log('[STATUS UPDATE] Status values:', {
                    interviewerMedical,
                    interviewerScreening,
                    physicianPhysical
                });
                
                // Determine new status based on the new workflow logic
                let newStatus;
                
                // Check if Medical History is Completed and Initial Screening is Passed
                const isMedicalHistoryCompleted = PhysicalExaminationModalAdmin.prototype.isCompletedStatus(interviewerMedical);
                const isScreeningPassed = PhysicalExaminationModalAdmin.prototype.isPassedStatus(interviewerScreening);
                const isPhysicalExamApproved = PhysicalExaminationModalAdmin.prototype.isAcceptedStatus(physicianPhysical);
                
                if (isMedicalHistoryCompleted && isScreeningPassed && isPhysicalExamApproved) {
                    // All phases complete -> Pending (Collection)
                    newStatus = 'Pending (Collection)';
                } else if (isMedicalHistoryCompleted && isScreeningPassed) {
                    // Interviewer phase complete, physician phase pending -> Pending (Examination)
                    newStatus = 'Pending (Examination)';
                } else {
                    // Interviewer phase pending -> Pending (Screening)
                    newStatus = 'Pending (Screening)';
                }
                
                console.log('[STATUS UPDATE] Determined new status:', newStatus);
                
                // Update the badge
                const instance = new PhysicalExaminationModalAdmin();
                instance.updateDonorStatusBadge(donorId, newStatus);
                
            } else {
                console.warn('[STATUS UPDATE] No eligibility data found, using fallback');
                const instance = new PhysicalExaminationModalAdmin();
                instance.updateDonorStatusBadge(donorId, 'Pending (Screening)');
            }
            
        } catch (error) {
            console.error('[STATUS UPDATE] Error checking donor status:', error);
            // Fallback to Pending (Screening) if we can't check
            const instance = new PhysicalExaminationModalAdmin();
            instance.updateDonorStatusBadge(donorId, 'Pending (Screening)');
        }
    }
}

// Initialize admin modal when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.physicalExaminationModalAdmin = new PhysicalExaminationModalAdmin();
    
    // Make status update function globally available
    window.updateDonorStatusGlobally = PhysicalExaminationModalAdmin.updateDonorStatusGlobally;
});
