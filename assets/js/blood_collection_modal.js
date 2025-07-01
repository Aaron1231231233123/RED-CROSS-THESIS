class BloodCollectionModal {
    constructor() {
        this.modal = null;
        this.currentStep = 1;
        this.totalSteps = 5;
        this.bloodCollectionData = null;
        this.init();
    }

    init() {
        // Initialize modal when DOM is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeModal());
        } else {
            this.initializeModal();
        }
    }

    initializeModal() {
        this.modal = document.getElementById('bloodCollectionModal');
        if (!this.modal) {
            console.error('Blood collection modal not found');
            return;
        }

        this.setupEventListeners();
        this.generateUnitSerialNumber();
    }

    setupEventListeners() {
        // Navigation buttons
        const prevBtn = document.querySelector('.blood-prev-btn');
        const nextBtn = document.querySelector('.blood-next-btn');
        const submitBtn = document.querySelector('.blood-submit-btn');
        const cancelBtn = document.querySelector('.blood-cancel-btn');
        const closeBtn = document.querySelector('.blood-close-btn');

        if (prevBtn) prevBtn.addEventListener('click', () => this.previousStep());
        if (nextBtn) nextBtn.addEventListener('click', () => this.nextStep());
        if (submitBtn) submitBtn.addEventListener('click', () => this.submitForm());
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal());
        if (closeBtn) closeBtn.addEventListener('click', () => this.closeModal());

        // Step navigation
        document.querySelectorAll('.blood-step').forEach(step => {
            step.addEventListener('click', (e) => {
                const stepNumber = parseInt(e.currentTarget.dataset.step);
                if (stepNumber <= this.currentStep) {
                    this.goToStep(stepNumber);
                }
            });
        });

        // Modern bag option selection
        document.querySelectorAll('.bag-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', () => {
                // Remove selected class from all bag options
                document.querySelectorAll('.bag-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to current option parent
                if (radio.checked) {
                    radio.closest('.bag-option').classList.add('selected');
                }
            });
        });

        // Blood status option selection and reaction visibility
        document.querySelectorAll('input[name="is_successful"]').forEach(radio => {
            radio.addEventListener('change', () => {
                // Remove selected class from all status options
                document.querySelectorAll('.blood-status-card').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to current option parent
                if (radio.checked) {
                    radio.closest('.blood-status-card').classList.add('selected');
                }
                
                // Show/hide reaction management based on selection
                this.updateReactionVisibility(radio.value === 'NO');
            });
        });

        // Initialize modern form elements
        this.initializeModernFormElements();
        
        // Setup time validation
        this.setupTimeValidation();
    }

    setupTimeValidation() {
        const startTimeInput = document.getElementById('blood-start-time');
        const endTimeInput = document.getElementById('blood-end-time');

        if (startTimeInput && endTimeInput) {
            // Validate end time is at least 5 minutes after start time
            const validateTimes = () => {
                if (startTimeInput.value && endTimeInput.value) {
                    const startTime = new Date(`2000-01-01T${startTimeInput.value}`);
                    const endTime = new Date(`2000-01-01T${endTimeInput.value}`);
                    const diffMinutes = (endTime - startTime) / (1000 * 60);

                    if (diffMinutes < 5) {
                        // Auto-adjust end time to be 5 minutes after start time
                        const adjustedEndTime = new Date(startTime.getTime() + 5 * 60000);
                        const hours = adjustedEndTime.getHours().toString().padStart(2, '0');
                        const minutes = adjustedEndTime.getMinutes().toString().padStart(2, '0');
                        endTimeInput.value = `${hours}:${minutes}`;
                        
                        this.showToast('End time adjusted to be at least 5 minutes after start time', 'info');
                    }
                }
            };

            startTimeInput.addEventListener('change', validateTimes);
            endTimeInput.addEventListener('change', validateTimes);
        }
    }

    initializeModernFormElements() {
        // Add click handlers for bag option labels
        document.querySelectorAll('.bag-option').forEach(option => {
            option.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT') {
                    const radio = option.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change'));
                    }
                }
            });
        });

        // Add click handlers for blood status card labels
        document.querySelectorAll('.blood-status-card').forEach(option => {
            option.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT') {
                    const radio = option.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change'));
                    }
                }
            });
        });
    }

    updateReactionVisibility(showReaction) {
        const reactionSection = document.querySelector('.blood-reaction-section');
        if (reactionSection) {
            reactionSection.style.display = showReaction ? 'block' : 'none';
        }
    }

    generateUnitSerialNumber() {
        const today = new Date();
        const dateStr = today.getFullYear().toString() + 
                       (today.getMonth() + 1).toString().padStart(2, '0') + 
                       today.getDate().toString().padStart(2, '0');
        
        // Generate random sequence for demo (in real implementation, should be sequential)
        const sequence = Math.floor(Math.random() * 9999).toString().padStart(4, '0');
        const serialNumber = `BC-${dateStr}-${sequence}`;
        
        const serialInput = document.getElementById('blood-unit-serial');
        if (serialInput) {
            serialInput.value = serialNumber;
        }

        // Also update the display in Step 1
        const serialDisplay = document.getElementById('blood-unit-serial-display');
        if (serialDisplay) {
            serialDisplay.textContent = serialNumber;
        }
    }

    openModal(collectionData) {
        this.bloodCollectionData = collectionData;
        this.currentStep = 1;
        
        // Populate summary data
        this.populateSummary();
        
        // Show modal
        this.modal.style.display = 'flex';
        setTimeout(() => {
            this.modal.classList.add('show');
        }, 10);
        
        // Initialize first step
        this.showStep(1);
        this.updateProgressIndicator();
        this.updateNavigationButtons();
    }

    async populateSummary() {
        if (!this.bloodCollectionData) return;

        try {
            // Fetch additional donor and physical exam data
            const [donorResponse, physicalExamResponse] = await Promise.all([
                fetch(`../../assets/php_func/get_donor_details.php?donor_id=${this.bloodCollectionData.donor_id}`),
                fetch(`../../assets/php_func/get_physical_exam_details.php?physical_exam_id=${this.bloodCollectionData.physical_exam_id}`)
            ]);

            if (donorResponse.ok) {
                const donorData = await donorResponse.json();
                if (donorData.success) {
                    this.populateDonorInfo(donorData.data);
                }
            }

            if (physicalExamResponse.ok) {
                const physicalData = await physicalExamResponse.json();
                if (physicalData.success) {
                    this.populatePhysicalExamInfo(physicalData.data);
                }
            }

        } catch (error) {
            console.error('Error fetching summary data:', error);
            this.showToast('Error loading summary data', 'error');
        }
    }

    populateDonorInfo(donorData) {
        // Populate the donor info
        const donorNameDisplay = document.getElementById('blood-donor-name-display');
        const collectionDateDisplay = document.getElementById('blood-collection-date-display');
        const unitSerialDisplay = document.getElementById('blood-unit-serial-display');

        if (donorNameDisplay) {
            const fullName = `${donorData.surname || ''} ${donorData.first_name || ''} ${donorData.middle_name || ''}`.trim();
            donorNameDisplay.textContent = fullName || 'Unknown Donor';
        }

        if (collectionDateDisplay) {
            const today = new Date();
            collectionDateDisplay.textContent = today.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }

        if (unitSerialDisplay) {
            const serialInput = document.getElementById('blood-unit-serial');
            unitSerialDisplay.textContent = serialInput?.value || 'Generating...';
        }

        // Also update the serial display in Step 1
        const serialDisplayStep1 = document.getElementById('blood-unit-serial-display');
        if (serialDisplayStep1) {
            const serialInput = document.getElementById('blood-unit-serial');
            serialDisplayStep1.textContent = serialInput?.value || 'Generating...';
        }
    }

    populatePhysicalExamInfo(physicalData) {
        // Since we simplified Step 1, we don't need to populate detailed physical exam info
        // The collection details form only shows basic information
        console.log('Physical exam data available for reference:', physicalData);
    }

    showStep(stepNumber) {
        // Hide all steps
        document.querySelectorAll('.blood-step-content').forEach(step => {
            step.classList.remove('active');
        });
        
        // Show current step
        const currentStepContent = document.getElementById(`blood-step-${stepNumber}`);
        if (currentStepContent) {
            currentStepContent.classList.add('active');
        }
        
        this.currentStep = stepNumber;
    }

    updateProgressIndicator() {
        document.querySelectorAll('.blood-step').forEach((step, index) => {
            const stepNumber = index + 1;
            if (stepNumber < this.currentStep) {
                step.classList.add('completed');
                step.classList.remove('active');
            } else if (stepNumber === this.currentStep) {
                step.classList.add('active');
                step.classList.remove('completed');
            } else {
                step.classList.remove('active', 'completed');
            }
        });

        // Update progress bar
        const progressFill = document.querySelector('.blood-progress-fill');
        if (progressFill) {
            const percentage = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
            progressFill.style.width = percentage + '%';
        }
    }

    updateNavigationButtons() {
        const prevBtn = document.querySelector('.blood-prev-btn');
        const nextBtn = document.querySelector('.blood-next-btn');
        const submitBtn = document.querySelector('.blood-submit-btn');

        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'inline-block' : 'none';
        }

        if (nextBtn) {
            nextBtn.style.display = this.currentStep < this.totalSteps ? 'inline-block' : 'none';
        }

        if (submitBtn) {
            submitBtn.style.display = this.currentStep === this.totalSteps ? 'inline-block' : 'none';
        }
    }

    nextStep() {
        if (this.validateCurrentStep()) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateProgressIndicator();
                this.showStep(this.currentStep);
                this.updateNavigationButtons();
                
                // Update summary if we're at the review step
                if (this.currentStep === 5) {
                    this.updateFormSummary();
                }
            }
        }
    }

    previousStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateProgressIndicator();
            this.showStep(this.currentStep);
            this.updateNavigationButtons();
        }
    }

    goToStep(step) {
        if (step >= 1 && step <= this.currentStep && step <= this.totalSteps) {
            this.currentStep = step;
            this.updateProgressIndicator();
            this.showStep(step);
            this.updateNavigationButtons();
            
            if (step === 5) {
                this.updateFormSummary();
            }
        }
    }

    validateCurrentStep() {
        const currentStepElement = document.getElementById(`blood-step-${this.currentStep}`);
        if (!currentStepElement) return false;

        const requiredFields = currentStepElement.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (field.type === 'radio') {
                const radioGroup = currentStepElement.querySelectorAll(`input[name="${field.name}"]`);
                const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                if (!isChecked) {
                    this.showToast(`Please select ${field.name.replace('_', ' ')}`, 'error');
                    isValid = false;
                }
            } else if (!field.value.trim()) {
                this.showToast(`Please fill in ${field.placeholder || field.name}`, 'error');
                field.focus();
                isValid = false;
            }
        });

        return isValid;
    }

    updateFormSummary() {
        // Update summary with form values
        const formData = this.getFormData();
        
        // Calculate duration if both times are provided
        let duration = '-';
        if (formData.start_time && formData.end_time) {
            const startTime = new Date(`2000-01-01T${formData.start_time}`);
            const endTime = new Date(`2000-01-01T${formData.end_time}`);
            const diffMinutes = Math.round((endTime - startTime) / (1000 * 60));
            if (diffMinutes > 0) {
                duration = `${diffMinutes} minutes`;
            }
        }
        
        const summaryElements = {
            'summary-blood-bag': formData.blood_bag_type || '-',
            'summary-amount': formData.amount_taken || '-',
            'summary-successful': formData.is_successful === 'YES' ? 'Successful' : 'Failed',
            'summary-start-time': formData.start_time || '-',
            'summary-end-time': formData.end_time || '-',
            'summary-duration': duration,
            'summary-serial-number': formData.unit_serial_number || '-',
            'summary-reaction': formData.donor_reaction || 'None',
            'summary-management': formData.management_done || 'None'
        };

        Object.entries(summaryElements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        });

        // Show/hide reaction and management sections based on success status
        const reactionSection = document.getElementById('summary-reaction-section');
        const managementSection = document.getElementById('summary-management-section');
        
        if (formData.is_successful === 'NO') {
            if (reactionSection) reactionSection.style.display = 'block';
            if (managementSection) managementSection.style.display = 'block';
        } else {
            if (reactionSection) reactionSection.style.display = 'none';
            if (managementSection) managementSection.style.display = 'none';
        }
    }

    getFormData() {
        const form = document.getElementById('bloodCollectionForm');
        const formData = new FormData(form);
        const data = {};

        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        // Add hidden data
        data.physical_exam_id = this.bloodCollectionData.physical_exam_id;
        data.donor_id = this.bloodCollectionData.donor_id;

        return data;
    }

    async submitForm() {
        try {
            const formData = this.getFormData();
            
            // Validate final data
            if (!this.validateFormData(formData)) {
                return;
            }

            // Show loading
            this.showLoading(true);

            const response = await fetch('../../assets/php_func/process_blood_collection.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showToast('Blood collection recorded successfully!', 'success');
                setTimeout(() => {
                    this.closeModal();
                    window.location.reload(); // Refresh the page to show updated data
                }, 2000);
            } else {
                throw new Error(result.error || 'Failed to record blood collection');
            }

        } catch (error) {
            console.error('Error submitting form:', error);
            this.showToast(error.message || 'Error recording blood collection', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    validateFormData(data) {
        const requiredFields = [
            'blood_bag_type',
            'amount_taken', 
            'is_successful',
            'start_time',
            'end_time',
            'unit_serial_number'
        ];

        for (let field of requiredFields) {
            if (!data[field]) {
                this.showToast(`Missing required field: ${field}`, 'error');
                return false;
            }
        }

        // Validate amount is a positive number
        const amount = parseInt(data.amount_taken);
        if (isNaN(amount) || amount <= 0 || amount > 10) {
            this.showToast('Amount must be between 1 and 10 units', 'error');
            return false;
        }

        return true;
    }

    closeModal() {
        this.modal.classList.remove('show');
        setTimeout(() => {
            this.modal.style.display = 'none';
        }, 300);
        
        // Reset form
        this.resetForm();
    }

    resetForm() {
        const form = document.getElementById('bloodCollectionForm');
        if (form) {
            form.reset();
        }
        
        // Reset progress
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
        this.updateNavigationButtons();
        
        // Clear modern form selections
        document.querySelectorAll('.bag-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        document.querySelectorAll('.blood-status-card').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Generate new serial number
        this.generateUnitSerialNumber();
    }

    showLoading(show) {
        const submitBtn = document.querySelector('.blood-submit-btn');
        if (submitBtn) {
            if (show) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Submit Blood Collection';
            }
        }
    }

    showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `blood-toast blood-toast-${type}`;
        
        const icon = type === 'success' ? 'check-circle' : 
                    type === 'error' ? 'exclamation-circle' : 'info-circle';
        
        toast.innerHTML = `
            <div class="blood-toast-content">
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Show toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Hide toast after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 5000);
    }
}

// Initialize the blood collection modal
window.bloodCollectionModal = new BloodCollectionModal(); 