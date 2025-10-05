// Physical Examination Modal JavaScript - Admin Version
class PhysicalExaminationModalAdmin {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 3; // Admin: 1 Vital, 2 Exam, 3 Review (Blood Bag step 3 hidden)
        this.formData = {};
        this.screeningData = null;
        this.isReadonly = false;
        
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
        
        // Step indicator clicks - Admin specific
        document.addEventListener('click', (e) => {
            if (e.target.closest('#physicalExaminationModalAdmin .physical-step')) {
                const stepElement = e.target.closest('.physical-step');
                const step = parseInt(stepElement.dataset.step);
                if (step <= this.currentStep) {
                    this.goToStep(step);
                }
            }
        });
    }
    
    async openModal(screeningData) {
        console.log('[PE ADMIN DEBUG] openModal called with screeningData:', screeningData);
        this.screeningData = screeningData;
        this.resetForm();
        this.isReadonly = false;
        
        // Pre-populate donor information
        if (screeningData) {
            document.getElementById('physical-donor-id-admin').value = screeningData.donor_form_id || '';
            document.getElementById('physical-screening-id-admin').value = screeningData.screening_id || '';
            
            // Populate summary basics
            this.populateInitialScreeningSummary(screeningData);
        }
        
        const modalEl = document.getElementById('physicalExaminationModalAdmin');
        console.log('[PE ADMIN DEBUG] Modal element found:', modalEl);
        
        if (!modalEl) {
            console.error('[PE ADMIN DEBUG] Modal element not found!');
            return;
        }
        
        // Show via Bootstrap
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false });
        console.log('[PE ADMIN DEBUG] Bootstrap modal instance:', modal);
        
        modal.show();
        
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
        
        // Add event listener to track when modal is actually shown
        modalEl.addEventListener('shown.bs.modal', () => {
            console.log('[PE ADMIN DEBUG] Modal is now shown');
        }, { once: true });
    }
    
    populateInitialScreeningSummary(screeningData) {
        console.log('[PE ADMIN DEBUG] Populating initial screening summary:', screeningData);
        
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
        console.log('[PE ADMIN DEBUG] Resetting form');
        
        // Reset form data
        this.formData = {};
        
        // Reset all form fields
        const form = document.getElementById('physicalExaminationFormAdmin');
        if (form) {
            form.reset();
        }
        
        // Reset step indicators
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
        
        // Clear summary fields
        this.clearSummaryFields();
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
        console.log('[PE ADMIN DEBUG] Next step clicked, current step:', this.currentStep);
        
        if (this.validateCurrentStep()) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                // Skip step 3 (blood bag) for admin - go directly to step 4 (review)
                if (this.currentStep === 3) {
                    this.currentStep = 4;
                }
                this.showStep(this.currentStep);
                this.updateProgressIndicator();
                
                if (this.currentStep === 4) {
                    this.updateSummary();
                }
            }
        }
    }
    
    prevStep() {
        console.log('[PE ADMIN DEBUG] Previous step clicked, current step:', this.currentStep);
        
        if (this.currentStep > 1) {
            this.currentStep--;
            // Skip step 3 (blood bag) for admin - go directly to step 2
            if (this.currentStep === 3) {
                this.currentStep = 2;
            }
            this.showStep(this.currentStep);
            this.updateProgressIndicator();
        }
    }
    
    goToStep(step) {
        console.log('[PE ADMIN DEBUG] Going to step:', step);
        
        if (step >= 1 && step <= this.currentStep && step <= this.totalSteps) {
            // Skip step 3 (blood bag) for admin
            if (step === 3) {
                step = 4; // Go to review step instead
            }
            this.currentStep = step;
            this.showStep(this.currentStep);
            this.updateProgressIndicator();
            
            if (this.currentStep === 4) {
                this.updateSummary();
            }
        }
    }
    
    showStep(step) {
        console.log('[PE ADMIN DEBUG] Showing step:', step);
        
        // Hide all step contents
        const stepContents = document.querySelectorAll('#physicalExaminationModalAdmin .physical-step-content');
        stepContents.forEach(content => {
            content.classList.remove('active');
        });
        
        // Show current step
        const currentStepEl = document.getElementById(`physical-step-${step}-admin`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
        }
        
        // Update navigation buttons
        this.updateNavigationButtons();
    }
    
    updateNavigationButtons() {
        const prevBtn = document.querySelector('#physicalExaminationModalAdmin .physical-prev-btn-admin');
        const nextBtn = document.querySelector('#physicalExaminationModalAdmin .physical-next-btn-admin');
        const submitBtn = document.querySelector('#physicalExaminationModalAdmin .physical-submit-btn-admin');
        
        if (prevBtn) prevBtn.style.display = this.currentStep === 1 ? 'none' : 'inline-block';
        if (nextBtn) nextBtn.style.display = this.currentStep === 4 ? 'none' : 'inline-block'; // Step 4 is final
        
        if (submitBtn) {
            submitBtn.style.display = this.currentStep === 4 ? 'inline-block' : 'none'; // Step 4 is final
        }
    }
    
    updateProgressIndicator() {
        console.log('[PE ADMIN DEBUG] Updating progress indicator, current step:', this.currentStep);
        
        const steps = document.querySelectorAll('#physicalExaminationModalAdmin .physical-step');
        steps.forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');
            
            // Hide step 3 (blood bag) for admin
            if (stepNumber === 3) {
                step.style.display = 'none';
                return;
            }
            
            // Adjust step numbers for display (step 4 becomes step 3 visually)
            let visualStep = stepNumber;
            if (stepNumber > 3) {
                visualStep = stepNumber - 1; // Step 4 becomes step 3 visually
            }
            
            if (visualStep < this.currentStep) {
                step.classList.add('completed');
            } else if (visualStep === this.currentStep) {
                step.classList.add('active');
            }
        });
        
        // Update progress fill
        const progressFill = document.querySelector('#physicalExaminationModalAdmin .physical-progress-fill');
        if (progressFill) {
            const progressPercentage = ((this.currentStep - 1) / (Math.max(this.totalSteps, 2) - 1)) * 100;
            progressFill.style.width = progressPercentage + '%';
        }
    }
    
    validateCurrentStep() {
        console.log('[PE ADMIN DEBUG] Validating current step:', this.currentStep);
        
        const currentStepEl = document.getElementById(`physical-step-${this.currentStep}-admin`);
        if (currentStepEl) {
            const requiredFields = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
            
            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    field.focus();
                    this.showToast('Please fill in all required fields', 'error');
                    return false;
                }
            }
        }
        
        return true;
    }
    
    updateSummary() {
        console.log('[PE ADMIN DEBUG] Updating summary');
        
        // Update vital signs
        const bloodPressure = document.getElementById('physical-blood-pressure-admin')?.value || '-';
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
        console.log('[PE ADMIN DEBUG] Submitting form');
        
        if (!this.validateCurrentStep()) {
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
        
        // Get form field values
        const fields = [
            'blood_pressure',
            'pulse_rate', 
            'body_temp',
            'gen_appearance',
            'skin',
            'heent',
            'heart_and_lungs'
        ];
        
        fields.forEach(field => {
            const element = document.getElementById(`physical-${field}-admin`);
            if (element && element.value) {
                formData.append(field, element.value);
            }
        });
        
        // Set default values for admin
        formData.append('remarks', 'Accepted');
        formData.append('blood_bag_type', 'Single');
        
        try {
            this.showToast('Submitting physical examination...', 'info');
            
            const response = await fetch('../../src/handlers/physical-examination-handler-admin.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('Physical examination completed successfully!', 'success');
                
                // Close modal after a short delay
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('physicalExaminationModalAdmin'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Refresh the page to update the donor status from "Pending (Examination)" to "Pending (Collection)"
                    window.location.reload();
                }, 1500);
            } else {
                this.showToast(result.message || 'Failed to submit physical examination', 'error');
            }
            
        } catch (error) {
            console.error('[PE ADMIN DEBUG] Submission error:', error);
            this.showToast('Error: ' + error.message, 'error');
        }
    }
    
    closeModal() {
        console.log('[PE ADMIN DEBUG] Closing modal');
        
        const modalEl = document.getElementById('physicalExaminationModalAdmin');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
        }
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
    
    handleFieldChange(field) {
        console.log('[PE ADMIN DEBUG] Field changed:', field.name, field.value);
        
        // Store field data
        this.formData[field.name] = field.value;
        
        // Real-time validation could be added here
    }
}

// Initialize admin modal when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.physicalExaminationModalAdmin = new PhysicalExaminationModalAdmin();
    console.log('[PE ADMIN DEBUG] PhysicalExaminationModalAdmin initialized');
});
