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
                const stepElement = e.target.closest('.physical-step');
                const step = parseInt(stepElement.dataset.step);
                if (step <= this.currentStep) {
                    this.goToStep(step);
                }
            }
        });
    }
    
    async openModal(screeningData) {
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
        
        // Add event listener to track when modal is actually shown
        modalEl.addEventListener('shown.bs.modal', () => {
        }, { once: true });
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
        
        if (this.validateCurrentStep()) {
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
        
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
            this.updateProgressIndicator();
        }
    }
    
    goToStep(step) {
        
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
        if (nextBtn) nextBtn.style.display = this.currentStep === 3 ? 'none' : 'inline-block'; // Step 3 is final
        
        if (submitBtn) {
            submitBtn.style.display = this.currentStep === 3 ? 'inline-block' : 'none'; // Step 3 is final
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
            }
        } else {
            if (hiddenField) {
                hiddenField.value = '';
            }
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
            } else {
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
                
                // Close modal after a short delay
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('physicalExaminationModalAdmin'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Refresh the page to ensure all data is synchronized
                    window.location.reload();
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
        
        // Store field data
        this.formData[field.name] = field.value;
        
        // Real-time validation could be added here
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
