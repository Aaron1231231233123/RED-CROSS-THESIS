// Physical Examination Modal JavaScript
class PhysicalExaminationModal {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 5; // Updated to 5 steps since we removed remarks
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
            
            // Populate initial screening summary
            this.populateInitialScreeningSummary(screeningData);
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
                if (this.currentStep === 5) {
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
            
            if (step === 5) {
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
            if (this.currentStep === 4) {
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
            const screeningResponse = await fetch(`../../assets/php_func/get_screening_details.php?screening_id=${screeningId}`);
            const screeningData = await screeningResponse.json();
            
            // Fetch donor form data  
            const donorResponse = await fetch(`../../assets/php_func/get_donor_details.php?donor_id=${donorId}`);
            const donorData = await donorResponse.json();
            
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
        
        // Show loading state
        const submitBtn = document.querySelector('.physical-submit-btn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        try {
            // Collect all form data
            const formData = new FormData(document.getElementById('physicalExaminationForm'));
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // Add screening data
            if (this.screeningData) {
                data.donor_id = this.screeningData.donor_form_id;
                data.screening_id = this.screeningData.screening_id;
            }
            
            // Auto-set remarks as "Accepted" since donor passed physical examination
            data.remarks = 'Accepted';
            
            // Flag to indicate this is an accepted examination (don't update eligibility table)
            data.is_accepted_examination = true;
            
            // Submit to server
            const response = await fetch('../../assets/php_func/process_physical_examination.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('Physical examination submitted successfully!', 'success');
                
                // Add interviewer name to summary if available
                if (result.interviewer_name) {
                    document.getElementById('summary-interviewer').textContent = result.interviewer_name;
                }
                
                setTimeout(() => {
                    this.closeModal();
                    // Reload the page to update the table
                    window.location.reload();
                }, 2000);
            } else {
                throw new Error(result.message || 'Submission failed');
            }
            
        } catch (error) {
            console.error('Submission error:', error);
            this.showToast('Error: ' + error.message, 'error');
        } finally {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
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