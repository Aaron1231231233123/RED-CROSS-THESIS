class BloodCollectionModalAdmin {
    constructor() {
        this.modal = null;
        this.currentStep = 1;
        this.totalSteps = 4; // Admin: 4 steps only
        this.bloodCollectionData = null;
        this.isSubmitting = false;
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeModal());
        } else {
            this.initializeModal();
        }
    }

    initializeModal() {
        this.modal = document.getElementById('bloodCollectionModalAdmin');
        if (!this.modal) {
            console.warn('[Admin] Blood collection modal element not found');
            return;
        }
        this.setupEventListeners();
        this.generateUnitSerialNumber();
        const dateEl = document.getElementById('blood-collection-date-admin');
        if (dateEl) {
            const today = new Date();
            dateEl.value = today.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        }
        
        console.log('[Admin] BloodCollectionModalAdmin ready');
    }

    setupEventListeners() {
        const prevBtn = this.modal?.querySelector('.blood-prev-btn-admin');
        const nextBtn = this.modal?.querySelector('.blood-next-btn-admin');
        const submitBtn = this.modal?.querySelector('.blood-submit-btn-admin');
        const cancelBtn = this.modal?.querySelector('.blood-cancel-btn-admin');
        const closeBtn = this.modal?.querySelector('.blood-close-btn-admin');

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
                    this.submitForm();
                    submitTimeout = null;
                }, 300);
            });
        }
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal());
        if (closeBtn) closeBtn.addEventListener('click', () => this.closeModal());

        if (this.modal) {
            this.modal.querySelectorAll('.blood-step').forEach(step => {
                step.addEventListener('click', (e) => {
                    const stepNumber = parseInt(e.currentTarget.dataset.step);
                    if (stepNumber <= this.currentStep) this.goToStep(stepNumber);
                });
            });

            // Bag card selection visual
            this.modal.querySelectorAll('.bag-option input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', () => {
                    this.modal.querySelectorAll('.bag-option').forEach(opt => opt.classList.remove('selected'));
                    if (radio.checked) radio.closest('.bag-option')?.classList.add('selected');
                });
            });
        }

        // Monitor time inputs to prevent them from being disabled
        this.watchTimeInputs();
        
        // Setup time validation
        this.setupTimeValidation();
        
        // Initialize modern form elements
        this.initializeModernFormElements();
    }
    
    setupTimeValidation() {
        const startTimeInput = document.getElementById('blood-start-time-admin');
        const endTimeInput = document.getElementById('blood-end-time-admin');

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
        if (!this.modal) return;
        
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
    }

    watchTimeInputs() {
        // Simple approach: add click handlers that force enable
        setTimeout(() => {
            const startTimeInput = document.getElementById('blood-start-time-admin');
            const endTimeInput = document.getElementById('blood-end-time-admin');
            
            const enableInput = (input) => {
                if (!input) return;
                input.disabled = false;
                input.readOnly = false;
                input.removeAttribute('disabled');
                input.removeAttribute('readonly');
                input.style.pointerEvents = 'auto';
                input.style.backgroundColor = '#fff';
                input.style.opacity = '1';
                input.style.cursor = 'text';
                input.style.userSelect = 'text';
                input.setAttribute('contenteditable', 'false');
            };
            
            if (startTimeInput) {
                enableInput(startTimeInput);
                // Force enable on any interaction
                ['click', 'focus', 'mousedown', 'touchstart'].forEach(eventType => {
                    startTimeInput.addEventListener(eventType, () => enableInput(startTimeInput), true);
                });
            }
            
            if (endTimeInput) {
                enableInput(endTimeInput);
                // Force enable on any interaction
                ['click', 'focus', 'mousedown', 'touchstart'].forEach(eventType => {
                    endTimeInput.addEventListener(eventType, () => enableInput(endTimeInput), true);
                });
            }
        }, 100);
    }

    ensureTimeInputsEditable() {
        // Use a small delay to ensure elements are in DOM
        setTimeout(() => {
            const startTimeInput = document.getElementById('blood-start-time-admin');
            const endTimeInput = document.getElementById('blood-end-time-admin');
            
            if (startTimeInput) {
                console.log('[Admin] Enabling start time input');
                startTimeInput.disabled = false;
                startTimeInput.readOnly = false;
                startTimeInput.removeAttribute('disabled');
                startTimeInput.removeAttribute('readonly');
                startTimeInput.style.pointerEvents = 'auto';
                startTimeInput.style.backgroundColor = '#fff';
                startTimeInput.style.opacity = '1';
                startTimeInput.style.cursor = 'text';
            } else {
                console.warn('[Admin] Start time input not found');
            }
            
            if (endTimeInput) {
                console.log('[Admin] Enabling end time input');
                endTimeInput.disabled = false;
                endTimeInput.readOnly = false;
                endTimeInput.removeAttribute('disabled');
                endTimeInput.removeAttribute('readonly');
                endTimeInput.style.pointerEvents = 'auto';
                endTimeInput.style.backgroundColor = '#fff';
                endTimeInput.style.opacity = '1';
                endTimeInput.style.cursor = 'text';
            } else {
                console.warn('[Admin] End time input not found');
            }
        }, 100);
    }

    generateUnitSerialNumber() {
        const today = new Date();
        const dateStr = today.getFullYear().toString() + (today.getMonth() + 1).toString().padStart(2, '0') + today.getDate().toString().padStart(2, '0');
        const timestamp = Date.now().toString().slice(-4);
        const random = Math.floor(Math.random() * 99).toString().padStart(2, '0');
        const sequence = timestamp + random;
        const serialNumber = `BC-${dateStr}-${sequence}`;
        const serialInput = document.getElementById('blood-unit-serial-admin');
        if (serialInput) serialInput.value = serialNumber;
    }

    async openModal(collectionData) {
        this.bloodCollectionData = collectionData || {};
        this.currentStep = 1;
        this.isSubmitting = false;
        
        // If physical_exam_id is missing or empty, try to resolve it
        const hasPhysicalExamId = this.bloodCollectionData.physical_exam_id && 
                                  String(this.bloodCollectionData.physical_exam_id).trim() !== '';
        
        if (!hasPhysicalExamId && this.bloodCollectionData.donor_id) {
            try {
                console.log('[Admin] Attempting to resolve physical_exam_id for donor:', this.bloodCollectionData.donor_id);
                const resp = await fetch(`../../assets/php_func/admin/get_physical_exam_details_admin.php?donor_id=${encodeURIComponent(this.bloodCollectionData.donor_id)}`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data?.success && data?.data?.physical_exam_id) {
                        this.bloodCollectionData.physical_exam_id = data.data.physical_exam_id;
                        console.log('[Admin] Resolved physical_exam_id:', this.bloodCollectionData.physical_exam_id);
                    } else {
                        console.warn('[Admin] No physical_exam_id found in response:', data);
                    }
                } else {
                    console.warn('[Admin] Failed to fetch physical_exam_id, HTTP status:', resp.status);
                }
            } catch (e) {
                console.warn('[Admin] Failed to resolve physical_exam_id:', e);
            }
        } else if (hasPhysicalExamId) {
            console.log('[Admin] Using provided physical_exam_id:', this.bloodCollectionData.physical_exam_id);
        }
        
        // Seed hidden fields
        const donorInput = this.modal?.querySelector('input[name="donor_id"]');
        const peInput = this.modal?.querySelector('input[name="physical_exam_id"]');
        if (donorInput && this.bloodCollectionData.donor_id) {
            donorInput.value = this.bloodCollectionData.donor_id;
            console.log('[Admin] Set donor_id hidden field:', this.bloodCollectionData.donor_id);
        }
        if (peInput && this.bloodCollectionData.physical_exam_id) {
            peInput.value = this.bloodCollectionData.physical_exam_id;
            console.log('[Admin] Set physical_exam_id hidden field:', this.bloodCollectionData.physical_exam_id);
        } else if (peInput) {
            console.warn('[Admin] physical_exam_id is missing or empty, hidden field not set');
        }

        // Generate new serial number
        this.generateUnitSerialNumber();
        
        // Populate summary data (blood type, weight, etc.)
        await this.populateSummary();
        
        if (this.modal) {
            // Use Bootstrap Modal API if available, otherwise use custom show
            try {
                const bsModal = bootstrap.Modal.getOrCreateInstance(this.modal);
                if (bsModal) {
                    bsModal.show();
                } else {
                    // Fallback to custom show
                    this.modal.style.display = 'flex';
                    setTimeout(() => { this.modal.classList.add('show'); }, 10);
                }
            } catch (e) {
                // Fallback to custom show
                this.modal.style.display = 'flex';
                setTimeout(() => { this.modal.classList.add('show'); }, 10);
            }
        }
        this.showStep(1);
        this.updateProgressIndicator();
        this.updateNavigationButtons();
        console.log('[Admin] BloodCollectionModal opened', this.bloodCollectionData);
    }

    async populateSummary() {
        if (!this.bloodCollectionData?.donor_id) return;
        
        try {
            // Fetch from screening_form instead of donor_form
            const screeningResponse = await fetch(`../../assets/php_func/get_screening_details.php?donor_id=${this.bloodCollectionData.donor_id}`);
            
            if (screeningResponse.ok) {
                const screeningData = await screeningResponse.json();
                if (screeningData.success) {
                    console.log('[Admin] Screening data fetched:', screeningData.data);
                    this.populateDonorInfo(screeningData.data);
                }
            }
        } catch (error) {
            console.error('[Admin] Error fetching screening data:', error);
        }
    }

    populateDonorInfo(donorData) {
        console.log('[Admin] populateDonorInfo called with data:', donorData);
        
        // Populate blood type - try multiple field names
        const bloodTypeEl = document.getElementById('blood-type-display-admin');
        if (bloodTypeEl) {
            const bloodType = donorData.blood_type || donorData.blood_group || donorData.bloodType || '';
            if (bloodType) {
                bloodTypeEl.value = bloodType;
                console.log('[Admin] Populated blood type:', bloodType);
            } else {
                console.warn('[Admin] Blood type not found in donor data. Available fields:', Object.keys(donorData));
            }
        } else {
            console.error('[Admin] blood-type-display-admin element not found!');
        }
        
        // Populate weight - try multiple field names
        const weightEl = document.getElementById('donor-weight-admin');
        if (weightEl) {
            const weight = donorData.weight || donorData.body_weight || donorData.weight_kg || '';
            if (weight) {
                weightEl.value = weight;
                console.log('[Admin] Populated weight:', weight);
            } else {
                console.warn('[Admin] Weight not found in donor data. Available fields:', Object.keys(donorData));
            }
        } else {
            console.error('[Admin] donor-weight element not found!');
        }
    }

    showStep(stepNumber) {
        if (!this.modal) return;
        const allSteps = this.modal.querySelectorAll('.blood-step-content-admin');
        allSteps.forEach(step => {
            step.classList.remove('active');
            step.style.display = 'none';
        });
        const currentStepContent = document.getElementById(`blood-step-${stepNumber}-admin`);
        if (currentStepContent) {
            currentStepContent.classList.add('active');
            currentStepContent.style.display = 'block';
        }
        
        // Ensure all inputs on current step are enabled (remove any disabled/readonly states)
        if (currentStepContent) {
            const inputs = currentStepContent.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                // Only enable if not explicitly marked as readonly in HTML
                if (!input.hasAttribute('readonly')) {
                    input.disabled = false;
                    input.removeAttribute('disabled');
                    input.removeAttribute('readonly');
                    input.readOnly = false;
                    input.style.pointerEvents = 'auto';
                    input.style.backgroundColor = '#fff';
                    input.style.opacity = '1';
                    input.style.cursor = 'text';
                }
            });
        }
        
        // Special handling for Step 3 (Timing) - ensure time inputs are always editable
        if (stepNumber === 3) {
            // Force enable after a short delay to ensure DOM is ready
            setTimeout(() => {
                const startTime = document.getElementById('blood-start-time-admin');
                const endTime = document.getElementById('blood-end-time-admin');
                
                if (startTime) {
                    startTime.disabled = false;
                    startTime.readOnly = false;
                    startTime.removeAttribute('disabled');
                    startTime.removeAttribute('readonly');
                    startTime.style.pointerEvents = 'auto';
                    startTime.style.userSelect = 'text';
                    startTime.style.opacity = '1';
                    startTime.style.backgroundColor = '#fff';
                    startTime.removeAttribute('tabindex');
                    startTime.tabIndex = 0;
                    startTime.setAttribute('contenteditable', 'true');
                    
                    console.log('[Admin] Forced start time input enabled');
                    console.log('Start time - disabled:', startTime.disabled, 'readOnly:', startTime.readOnly, 'tabIndex:', startTime.tabIndex);
                    
                    // Add a mousedown event to force enable
                    startTime.addEventListener('mousedown', function() {
                        this.disabled = false;
                        this.readOnly = false;
                        console.log('[Admin] Mouse down on start time - forced enabled');
                    }, true);
                    
                    // Try to focus to test if it's editable
                    setTimeout(() => {
                        startTime.focus();
                        console.log('[Admin] Attempted focus on start time input');
                    }, 100);
                }
                
                if (endTime) {
                    endTime.disabled = false;
                    endTime.readOnly = false;
                    endTime.removeAttribute('disabled');
                    endTime.removeAttribute('readonly');
                    endTime.style.pointerEvents = 'auto';
                    endTime.style.userSelect = 'text';
                    endTime.style.opacity = '1';
                    endTime.style.backgroundColor = '#fff';
                    endTime.removeAttribute('tabindex');
                    endTime.tabIndex = 0;
                    endTime.setAttribute('contenteditable', 'true');
                    
                    console.log('[Admin] Forced end time input enabled');
                    console.log('End time - disabled:', endTime.disabled, 'readOnly:', endTime.readOnly, 'tabIndex:', endTime.tabIndex);
                    
                    // Add a mousedown event to force enable
                    endTime.addEventListener('mousedown', function() {
                        this.disabled = false;
                        this.readOnly = false;
                        console.log('[Admin] Mouse down on end time - forced enabled');
                    }, true);
                }
            }, 200);
        }
        
        this.currentStep = stepNumber;
    }

    updateProgressIndicator() {
        if (!this.modal) return;
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
        const progressFill = this.modal.querySelector('.blood-progress-fill');
        if (progressFill) {
            const percentage = ((this.currentStep - 1) / (this.totalSteps - 1)) * 100;
            progressFill.style.width = percentage + '%';
        }
    }

    updateNavigationButtons() {
        if (!this.modal) return;
        const prevBtn = this.modal.querySelector('.blood-prev-btn-admin');
        const nextBtn = this.modal.querySelector('.blood-next-btn-admin');
        const submitBtn = this.modal.querySelector('.blood-submit-btn-admin');
        if (prevBtn) prevBtn.style.display = this.currentStep > 1 ? 'inline-block' : 'none';
        if (nextBtn) nextBtn.style.display = this.currentStep < this.totalSteps ? 'inline-block' : 'none';
        if (submitBtn) submitBtn.style.display = this.currentStep === this.totalSteps ? 'inline-block' : 'none';
        if (this.currentStep === this.totalSteps) this.updateSummary();
    }

    nextStep() {
        console.log('[Admin] Next clicked - validating step', this.currentStep);
        if (!this.validateCurrentStep()) return;
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.updateProgressIndicator();
            this.showStep(this.currentStep);
            this.updateNavigationButtons();
            
            // Update summary if we're at the review step
            if (this.currentStep === 4) {
                this.updateSummary();
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
            
            // Update summary if we're at the review step
            if (step === 4) {
                this.updateSummary();
            }
        }
    }

    validateCurrentStep() {
        const currentStepElement = document.getElementById(`blood-step-${this.currentStep}-admin`);
        if (!currentStepElement) return false;
        const requiredFields = currentStepElement.querySelectorAll('[required]');
        let isValid = true;

        for (const field of requiredFields) {
            if (field.type === 'radio') {
                const radioGroup = currentStepElement.querySelectorAll(`input[name="${field.name}"]`);
                const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                if (!isChecked) {
                    this.showToast(`Please select ${field.name.replace('_', ' ')}`, 'error');
                    isValid = false;
                }
            } else if (!String(field.value || '').trim()) {
                this.showToast(`Please fill in ${field.placeholder || field.name}`, 'error');
                field.focus();
                isValid = false;
            }
        }
        return isValid;
    }

    getFormData() {
        const form = document.getElementById('bloodCollectionFormAdmin');
        if (!form) return {};
        const fd = new FormData(form);
        const data = {};
        for (let [k, v] of fd.entries()) data[k] = v;
        
        // Admin: force success (no status step) - match staff format
        data.is_successful = 'YES'; // Use string format like staff version
        
        // Set default blood bag values if not present (matching staff behavior)
        if (!data.blood_bag_brand) {
            data.blood_bag_brand = 'KARMI';
        }
        if (!data.blood_bag_type) {
            data.blood_bag_type = 'S-KARMI';
        }
        
        // Get physical_exam_id from form data first, then fallback to bloodCollectionData
        const peInput = form.querySelector('input[name="physical_exam_id"]');
        if (peInput && peInput.value) {
            data.physical_exam_id = peInput.value;
            console.log('[Admin] Got physical_exam_id from form input:', data.physical_exam_id);
        } else if (this.bloodCollectionData?.physical_exam_id) {
            data.physical_exam_id = this.bloodCollectionData.physical_exam_id;
            console.log('[Admin] Got physical_exam_id from bloodCollectionData:', data.physical_exam_id);
        }
        
        // Get donor_id from form data first, then fallback to bloodCollectionData
        const donorInput = form.querySelector('input[name="donor_id"]');
        if (donorInput && donorInput.value) {
            data.donor_id = donorInput.value;
        } else if (this.bloodCollectionData?.donor_id) {
            data.donor_id = this.bloodCollectionData.donor_id;
        }
        
        // Add update mode hint like staff version
        data.update_mode = 'increment_on_existing';
        
        console.log('[Admin] Form data prepared:', { ...data, physical_exam_id: data.physical_exam_id ? 'present' : 'missing' });
        return data;
    }

    updateSummary() {
        const d = this.getFormData();
        
        // Calculate duration if both times are provided
        let duration = '-';
        if (d.start_time && d.end_time) {
            const startTime = new Date(`2000-01-01T${d.start_time}`);
            const endTime = new Date(`2000-01-01T${d.end_time}`);
            const diffMinutes = Math.round((endTime - startTime) / (1000 * 60));
            if (diffMinutes > 0) {
                duration = `${diffMinutes} minutes`;
            }
        }
        
        const map = {
            'summary-blood-bag-admin': d.blood_bag_type || '-',
            'summary-serial-number-admin': d.unit_serial_number || '-',
            'summary-start-time-admin': d.start_time || '-',
            'summary-end-time-admin': d.end_time || '-',
        };
        Object.entries(map).forEach(([id, val]) => { 
            const el = document.getElementById(id); 
            if (el) el.textContent = val; 
        });
    }

    async submitForm() {
        if (this.isSubmitting) return;
        
        // Ensure physical_exam_id is resolved before submission
        if (!this.bloodCollectionData.physical_exam_id && this.bloodCollectionData.donor_id) {
            console.log('[Admin] physical_exam_id missing, attempting to resolve before submission...');
            try {
                const resp = await fetch(`../../assets/php_func/admin/get_physical_exam_details_admin.php?donor_id=${encodeURIComponent(this.bloodCollectionData.donor_id)}`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data?.success && data?.data?.physical_exam_id) {
                        this.bloodCollectionData.physical_exam_id = data.data.physical_exam_id;
                        // Update the hidden input field
                        const form = document.getElementById('bloodCollectionFormAdmin');
                        const peInput = form?.querySelector('input[name="physical_exam_id"]');
                        if (peInput) {
                            peInput.value = this.bloodCollectionData.physical_exam_id;
                            console.log('[Admin] Set physical_exam_id in hidden field before submission:', this.bloodCollectionData.physical_exam_id);
                        }
                    } else {
                        console.error('[Admin] Could not resolve physical_exam_id for donor:', this.bloodCollectionData.donor_id);
                        this.showToast('Error: Could not find physical examination record for this donor. Please ensure the physical examination has been completed.', 'error');
                        return;
                    }
                } else {
                    console.error('[Admin] Failed to fetch physical_exam_id, HTTP status:', resp.status);
                    this.showToast('Error: Failed to retrieve physical examination data. Please try again.', 'error');
                    return;
                }
            } catch (e) {
                console.error('[Admin] Error resolving physical_exam_id:', e);
                this.showToast('Error: Could not retrieve physical examination data. Please try again.', 'error');
                return;
            }
        }
        
        const data = this.getFormData();
        
        // Validate time format (HH:MM from type="time" input)
        if (!data.start_time || !data.end_time) {
            this.showToast('Please enter both start and end times', 'error');
            return;
        }
        
        // Validate time difference
        const startTime = new Date(`2000-01-01T${data.start_time}`);
        const endTime = new Date(`2000-01-01T${data.end_time}`);
        const diffMinutes = (endTime - startTime) / (1000 * 60);
        if (diffMinutes < 5) {
            this.showToast('End time must be at least 5 minutes after start time', 'error');
            return;
        }
        
        // Convert time inputs to HH:MM format (type="time" returns HH:MM)
        console.log('[Admin] Submitting with start_time:', data.start_time, 'end_time:', data.end_time);
        
        // Required keys
        const required = ['blood_bag_type','unit_serial_number','physical_exam_id','start_time','end_time'];
        for (const k of required) {
            if (!data[k] || (typeof data[k] === 'string' && data[k].trim() === '')) { 
                console.error('[Admin] Missing required field:', k, 'Value:', data[k]);
                this.showToast(`Missing required field: ${k}`, 'error'); 
                return; 
            }
        }

        this.isSubmitting = true;
        const btn = this.modal?.querySelector('.blood-submit-btn-admin');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...'; }

        fetch('../../assets/php_func/admin/process_blood_collection_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP error! status: ${r.status}`);
            }
            return r.json();
        })
        .then(res => {
            console.log('[Admin] Submit response:', res);
            if (res && res.success) {
                this.showToast('Blood collection submitted successfully!', 'success');
                this.closeModal();
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                this.showToast(res?.message || 'Submission failed', 'error');
            }
        })
        .catch(error => {
            console.error('[Admin] Submit error:', error);
            this.showToast('Network error: ' + error.message, 'error');
        })
        .finally(() => {
            this.isSubmitting = false;
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check me-2"></i>Submit Collection'; }
        });
    }

    closeModal() {
        if (this.modal) {
            // Use Bootstrap Modal API if available, otherwise use custom close
            try {
                const bsModal = bootstrap.Modal.getInstance(this.modal);
                if (bsModal) {
                    bsModal.hide();
                } else {
                    // Fallback to custom close
                    this.modal.classList.remove('show');
                    setTimeout(() => { 
                        this.modal.style.display = 'none';
                        // Remove backdrop if exists
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    }, 300);
                }
            } catch (e) {
                // Fallback to custom close
                this.modal.classList.remove('show');
                setTimeout(() => { 
                    this.modal.style.display = 'none';
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }, 300);
            }
        }
        const form = document.getElementById('bloodCollectionFormAdmin');
        if (form) form.reset();
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
        this.updateNavigationButtons();
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
                if (toast.parentNode) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 5000);
    }
}

// Global instance - admin specific
window.bloodCollectionModalAdmin = new BloodCollectionModalAdmin();


