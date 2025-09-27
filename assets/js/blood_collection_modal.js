class BloodCollectionModal {
    constructor() {
        this.modal = null;
        this.currentStep = 1;
        this.totalSteps = 5;
        this.bloodCollectionData = null;
        this.cachedDonorData = null;
        this.cachedPhysicalExamData = null;
        this.isSubmitting = false; // Prevent duplicate submissions
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
        // Navigation buttons (scoped to this modal)
        const prevBtn = this.modal.querySelector('.blood-prev-btn');
        const nextBtn = this.modal.querySelector('.blood-next-btn');
        const submitBtn = this.modal.querySelector('.blood-submit-btn');
        const cancelBtn = this.modal.querySelector('.blood-cancel-btn');
        const closeBtn = this.modal.querySelector('.blood-close-btn');

        if (prevBtn) prevBtn.addEventListener('click', () => this.previousStep());
        if (nextBtn) nextBtn.addEventListener('click', () => this.nextStep());
        if (submitBtn) {
            // Add debounced click handler to prevent rapid double-clicks
            let submitTimeout = null;
            submitBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Clear any existing timeout
                if (submitTimeout) {
                    clearTimeout(submitTimeout);
                }
                
                // Add a small delay to prevent rapid clicks
                submitTimeout = setTimeout(() => {
                    this.showCollectionCompleteConfirmation();
                    submitTimeout = null;
                }, 300);
            });
        }
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal());
        if (closeBtn) closeBtn.addEventListener('click', () => this.closeModal());

        // Step navigation
        this.modal.querySelectorAll('.blood-step').forEach(step => {
            step.addEventListener('click', (e) => {
                const stepNumber = parseInt(e.currentTarget.dataset.step);
                if (stepNumber <= this.currentStep) {
                    this.goToStep(stepNumber);
                }
            });
        });

        // Modern bag option selection
        this.modal.querySelectorAll('.bag-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', () => {
                // Remove selected class from all bag options
                this.modal.querySelectorAll('.bag-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to current option parent
                if (radio.checked) {
                    radio.closest('.bag-option').classList.add('selected');
                }
            });
        });

        // Blood status option selection and reaction visibility
        this.modal.querySelectorAll('input[name="is_successful"]').forEach(radio => {
            radio.addEventListener('change', () => {
                // Remove selected class from all status options
                this.modal.querySelectorAll('.blood-status-card').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to current option parent
                if (radio.checked) {
                    radio.closest('.blood-status-card').classList.add('selected');
                }
                
                // Toggle donor reaction section when unsuccessful (value === 'false')
                this.updateReactionVisibility(radio.value === 'false');
            });
        });

        // Initialize modern form elements
        this.initializeModernFormElements();
        
        // Setup time validation
        this.setupTimeValidation();
    }

    setupTimeValidation() {
        const startTimeInput = this.modal.querySelector('#start_time');
        const endTimeInput = this.modal.querySelector('#end_time');

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
        this.modal.querySelectorAll('.bag-option').forEach(option => {
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
        this.modal.querySelectorAll('.blood-status-card').forEach(option => {
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
        const reactionSection = this.modal.querySelector('#donorReactionSection');
        const reactionTextarea = this.modal.querySelector('#donor_reaction');
        if (reactionSection) {
            reactionSection.style.display = showReaction ? 'block' : 'none';
        }
        if (reactionTextarea) {
            if (showReaction) {
                reactionTextarea.setAttribute('required', 'required');
            } else {
                reactionTextarea.removeAttribute('required');
                reactionTextarea.value = '';
            }
        }
    }

    generateUnitSerialNumber() {
        const today = new Date();
        const dateStr = today.getFullYear().toString() + 
                       (today.getMonth() + 1).toString().padStart(2, '0') + 
                       today.getDate().toString().padStart(2, '0');
        
        // Generate more unique sequence using timestamp + random
        const timestamp = Date.now().toString().slice(-4); // Last 4 digits of timestamp
        const random = Math.floor(Math.random() * 99).toString().padStart(2, '0');
        const sequence = timestamp + random;
        const serialNumber = `BC-${dateStr}-${sequence}`;
        
        const serialInput = this.modal.querySelector('#unit_serial_number');
        if (serialInput) {
            serialInput.value = serialNumber;
        }
        // No separate serial display element in current HTML; skip optional update
    }

    openModal(collectionData) {
        this.bloodCollectionData = collectionData || {};
        this.currentStep = 1;
        
        // Reset submission flag when opening modal
        this.isSubmitting = false;
        
        // Generate new serial number for each modal opening
        this.generateUnitSerialNumber();
        
        // Populate summary data
        this.populateSummary();

        // Seed hidden context ids into the form if provided
        this.seedContextIds(this.bloodCollectionData);
        
        // Show modal (custom modal, not Bootstrap)
        this.modal.style.display = 'flex';
        setTimeout(() => {
            this.modal.classList.add('show');
        }, 10);
        
        // Initialize first step
        this.showStep(1);
        this.updateProgressIndicator();
        this.updateNavigationButtons();
    }

    seedContextIds(context) {
        try {
            const setIf = (name, value) => {
                const el = this.modal.querySelector(`#bloodCollectionForm input[name="${name}"]`);
                if (el && value) el.value = value;
            };
            setIf('donor_id', context?.donor_id || '');
            setIf('screening_id', context?.screening_id || '');
            setIf('physical_exam_id', context?.physical_exam_id || '');
        } catch (e) {
            console.warn('Failed seeding context ids:', e);
        }
    }

    async populateSummary() {
        if (!this.bloodCollectionData) return;

        try {
            // Use comprehensive API (aggregates donor, screening, physical exam, etc.)
            const donorId = this.bloodCollectionData.donor_id;
            if (!donorId) return;

            const response = await fetch(`../../assets/php_func/comprehensive_donor_details_api.php?donor_id=${encodeURIComponent(donorId)}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            if (data && !data.error) {
                const donorForm = data.donor_form || null;
                const screeningForm = data.screening_form || null;
                const physicalExam = data.physical_examination || null;

                this.cachedDonorData = donorForm;
                // No longer prefilling from physical examination; rely on screening form for prefill

                if (donorForm) {
                    this.populateDonorInfo(donorForm);
                }
                // Prefer screening form values for prefill (blood_type, body_weight)
                if (screeningForm && Object.keys(screeningForm).length) {
                    this.seedCollectionDetailsFromData(screeningForm, null);
                }
                // Fallback to donor form if screening doesn't have values
                if (donorForm) {
                    this.seedCollectionDetailsFromData(donorForm, null);
                }

                // Seed hidden identifiers if missing (backend requires physical_exam_id)
                try {
                    const physId = physicalExam && (physicalExam.physical_exam_id || physicalExam.id);
                    const screenId = screeningForm && (screeningForm.screening_id || screeningForm.id);
                    const physEl = this.modal.querySelector('#bloodCollectionForm input[name="physical_exam_id"]');
                    const scrEl = this.modal.querySelector('#bloodCollectionForm input[name="screening_id"]');
                    if (physEl && !physEl.value && physId) physEl.value = String(physId);
                    if (scrEl && !scrEl.value && screenId) scrEl.value = String(screenId);
                    this.cachedPhysicalExamData = physicalExam || null;
                } catch (_) {}
            } else if (data && data.error) {
                // Non-fatal; still allow manual entry
                console.warn('Comprehensive API error:', data.error);
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
            // Display donor's birthdate instead of current date
            if (donorData.birthdate) {
                const birthdate = new Date(donorData.birthdate);
                collectionDateDisplay.textContent = birthdate.toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                });
            } else {
                collectionDateDisplay.textContent = 'Birthdate not available';
            }
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

    seedCollectionDetailsFromData(donorData, physicalData) {
        try {
            const bloodTypeInput = this.modal.querySelector('#blood_type');
            const donorWeightInput = this.modal.querySelector('#donor_weight');
            const collectionDateInput = this.modal.querySelector('#collection_date');

            // Blood type from donor or screening/physical data
            const bloodType = (physicalData && (physicalData.blood_type || physicalData.bloodType)) ||
                              (donorData && (donorData.blood_type || donorData.bloodType));
            if (bloodTypeInput && bloodType && !bloodTypeInput.value) {
                bloodTypeInput.value = bloodType;
            }

            // Weight preference: physical exam > screening > donor
            const weight = (physicalData && (physicalData.donor_weight || physicalData.weight || physicalData.body_weight)) ||
                           (donorData && (donorData.weight || donorData.body_weight));
            if (donorWeightInput && weight && !donorWeightInput.value) {
                donorWeightInput.value = weight;
            }

            // Default collection date to today if empty
            if (collectionDateInput && !collectionDateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                collectionDateInput.value = today;
            }
        } catch (e) {
            console.warn('Failed to seed collection details:', e);
        }
    }

    showStep(stepNumber) {
        // Hide all steps and enforce display none to override inline styles
        this.modal.querySelectorAll('.blood-step-content').forEach(step => {
            step.classList.remove('active');
            step.style.display = 'none';
        });

        // Show current step by matching data-step on step content
        const currentStepContent = this.modal.querySelector(`.blood-step-content[data-step="${stepNumber}"]`);
        if (currentStepContent) {
            currentStepContent.classList.add('active');
            currentStepContent.style.display = 'block';
        }

        this.currentStep = stepNumber;

        // Ensure Step 2 always shows prefills after it becomes active
        if (this.currentStep === 2) {
            this.seedCollectionDetailsFromData(this.cachedDonorData, this.cachedPhysicalExamData);
        }
    }

    updateProgressIndicator() {
        this.modal.querySelectorAll('.blood-step').forEach((step, index) => {
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
        const progressFill = this.modal.querySelector('.blood-progress-fill');
        if (progressFill) {
            const percentage = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
            progressFill.style.width = percentage + '%';
        }
    }

    updateNavigationButtons() {
        const prevBtn = this.modal.querySelector('.blood-prev-btn');
        const nextBtn = this.modal.querySelector('.blood-next-btn');
        const submitBtn = this.modal.querySelector('.blood-submit-btn');

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
        const currentStepElement = this.modal.querySelector(`.blood-step-content[data-step="${this.currentStep}"]`);
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
                try { field.focus(); } catch(_) {}
                isValid = false;
            }
        });

        // Additional per-step validation
        if (!isValid) return false;

        // Step 1: require a bag type selection
        if (this.currentStep === 1) {
            const radios = this.modal.querySelectorAll('input[name="blood_bag_type"]');
            const hasSelected = Array.from(radios).some(r => r.checked);
            if (!hasSelected) {
                this.showToast('Please select a blood bag type', 'error');
                return false;
            }
        }

        // Step 4: require status and conditionally donor reaction
        if (this.currentStep === 4) {
            const statusRadios = this.modal.querySelectorAll('input[name="is_successful"]');
            const selected = Array.from(statusRadios).find(r => r.checked);
            if (!selected) {
                this.showToast('Please select collection status', 'error');
                return false;
            }
            if (selected.value === 'false') {
                const reaction = this.modal.querySelector('#donor_reaction');
                if (reaction && !reaction.value.trim()) {
                    this.showToast('Please provide donor reaction details', 'error');
                    try { reaction.focus(); } catch(_) {}
                    return false;
                }
            }
        }

        return true;
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
        
        // Map to current summary element IDs in HTML
        const map = {
            summary_bag_type: formData.blood_bag_type || '-',
            summary_serial: formData.unit_serial_number || '-',
            summary_date: formData.collection_date || '-',
            summary_start: formData.start_time || '-',
            summary_end: formData.end_time || '-',
            summary_status: formData.is_successful === 'true' ? 'Successful' : (formData.is_successful === 'false' ? 'Unsuccessful' : '-')
        };

        Object.entries(map).forEach(([id, value]) => {
            const el = this.modal.querySelector(`#${id}`);
            if (el) el.textContent = value;
        });

        // Also compute and append duration text if both times are present
        if (formData.start_time && formData.end_time) {
            const startTime = new Date(`2000-01-01T${formData.start_time}`);
            const endTime = new Date(`2000-01-01T${formData.end_time}`);
            const diffMinutes = Math.max(0, Math.round((endTime - startTime) / (1000 * 60)));
            const durationText = diffMinutes > 0 ? `${diffMinutes} minutes` : '-';
            const target = this.modal.querySelector('#summary_duration');
            if (target) target.textContent = durationText;
        }
    }

    getFormData() {
        const form = this.modal.querySelector('#bloodCollectionForm');
        const formData = new FormData(form);
        const data = {};

        // Convert FormData to object
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        // Add hidden data (guard against missing context)
        const ctx = this.bloodCollectionData || {};
        data.physical_exam_id = data.physical_exam_id || ctx.physical_exam_id || null;
        data.donor_id = data.donor_id || ctx.donor_id || null;
        data.screening_id = data.screening_id || ctx.screening_id || null;
        // Hint to backend: if a row exists, increment amount_taken instead of replacing
        // and update other fields.
        data.update_mode = 'increment_on_existing';

        // Normalize/derive blood bag brand and type to match backend expectations
        try {
            const selectedTypeRaw = (data.blood_bag_type || '').toString();
            const normalized = this.computeBagBrandAndType(selectedTypeRaw);
            if (normalized) {
                data.blood_bag_type = normalized.typeCode; // e.g., S/D/T/Q, FK T&B, FRES
                data.blood_bag_brand = normalized.brand;   // e.g., KARMI, SPECIAL BAG, APHERESIS
            }
        } catch (_) {}

        return data;
    }

    computeBagBrandAndType(selected) {
        if (!selected) return null;
        const t = String(selected).trim().toLowerCase();
        // Default mapping rules:
        // - Single/Multiple/Triple/Quad → brand KARMI; type S/D/T/Q
        // - Top & Bottom → brand SPECIAL BAG; type FK T&B (default)
        // - Apheresis → brand APHERESIS; type FRES (default variant)
        if (t.includes('apheresis')) {
            return { brand: 'APHERESIS', typeCode: 'FRES' };
        }
        if (t.includes('top') && t.includes('bottom')) {
            return { brand: 'SPECIAL BAG', typeCode: 'FK T&B' };
        }
        if (t.includes('single')) {
            return { brand: 'KARMI', typeCode: 'S' };
        }
        if (t.includes('double') || t.includes('multiple')) {
            return { brand: 'KARMI', typeCode: 'D' };
        }
        if (t.includes('triple')) {
            return { brand: 'KARMI', typeCode: 'T' };
        }
        if (t.includes('quad')) {
            return { brand: 'KARMI', typeCode: 'Q' };
        }
        // Fallback: keep original text as type, and brand KARMI to satisfy backend
        return { brand: 'KARMI', typeCode: selected };
    }


    async retryUpdateWithIncrement(formData) {
        try {
            // Ensure flag present
            formData.update_mode = 'increment_on_existing';

            const response = await fetch('../../assets/php_func/process_blood_collection.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const raw = await response.text();
            let result;
            try { result = JSON.parse(raw); } catch (e) { result = { success: false, error: raw || 'Non-JSON response' }; }

            if (result.success) {
                this.showToast('Existing collection updated (amount added).', 'success');
                this.disableAllFormInteraction();
                setTimeout(() => {
                    this.closeModal();
                    window.location.reload();
                }, 1500);
            } else {
                const msg = result.error || result.message || 'Failed to update collection';
                throw new Error(msg);
            }
        } catch (err) {
            console.error('Update with increment failed:', err);
            this.showToast(err.message || 'Update failed', 'error');
            this.isSubmitting = false;
            this.showLoading(false);
        }
    }

    validateFormData(data) {
        const requiredFields = [
            'blood_bag_type',
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

        // Amount is automatically set to 1 unit (standard donation)
        // No validation needed as it's a hidden field with fixed value

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

    showCollectionCompleteConfirmation() {
        // Show the collection complete confirmation modal
        if (window.showCollectionCompleteModal) {
            window.showCollectionCompleteModal();
        } else {
            // Fallback to direct submission if modal function not available
            this.submitForm();
        }
    }

    submitForm() {
        // This method will be called from the final confirmation modal
        const formData = this.getFormData();
        
        if (!this.validateFormData(formData)) {
            return;
        }

        this.showLoading(true);
        this.isSubmitting = true;

        // Submit to backend
        fetch('../../assets/php_func/process_blood_collection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            this.isSubmitting = false;
            this.showLoading(false);
            
            if (data.success) {
                this.showSuccessModal();
            } else {
                this.showToast(data.message || 'Submission failed', 'error');
            }
        })
        .catch(error => {
            this.isSubmitting = false;
            this.showLoading(false);
            console.error('Error:', error);
            this.showToast('Network error occurred', 'error');
        });
    }

    showSuccessModal() {
        // Close the blood collection modal first
        this.closeModal();
        
        // Show the donation success modal
        if (window.showDonationSuccessModal) {
            window.showDonationSuccessModal();
        } else {
            // Fallback to toast message
            this.showToast('Donation completed successfully!', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    }

    resetForm() {
        const form = this.modal.querySelector('#bloodCollectionForm');
        if (form) {
            form.reset();
            
            // Re-enable all form elements
            const formElements = form.querySelectorAll('input, select, textarea, button');
            formElements.forEach(element => {
                element.disabled = false;
            });
        }
        
        // Reset submission flag
        this.isSubmitting = false;
        
        // Reset progress
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
        this.updateNavigationButtons();
        
        // Clear modern form selections
        this.modal.querySelectorAll('.bag-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        this.modal.querySelectorAll('.blood-status-card').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Re-enable and reset navigation buttons
        const navButtons = this.modal.querySelectorAll('.blood-prev-btn, .blood-next-btn, .blood-submit-btn, .blood-cancel-btn');
        navButtons.forEach(btn => {
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.cursor = '';
        });
        
        // Reset submit button appearance
        const submitBtn = this.modal.querySelector('.blood-submit-btn');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Submit Blood Collection';
            submitBtn.classList.remove('btn-outline-success');
            submitBtn.classList.add('btn-success');
        }
        
        // Generate new serial number
        this.generateUnitSerialNumber();
    }

    showLoading(show) {
        const submitBtn = this.modal.querySelector('.blood-submit-btn');
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

    disableAllFormInteraction() {
        // Disable all form elements
        const form = this.modal.querySelector('#bloodCollectionForm');
        if (form) {
            const formElements = form.querySelectorAll('input, select, textarea, button');
            formElements.forEach(element => {
                element.disabled = true;
            });
        }

        // Disable navigation buttons
        const navButtons = this.modal.querySelectorAll('.blood-prev-btn, .blood-next-btn, .blood-submit-btn, .blood-cancel-btn');
        navButtons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
        });

        // Update submit button to show success state
        const submitBtn = this.modal.querySelector('.blood-submit-btn');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Submitted Successfully';
            submitBtn.classList.remove('btn-success');
            submitBtn.classList.add('btn-outline-success');
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