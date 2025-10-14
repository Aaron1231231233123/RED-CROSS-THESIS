// Physical Examination Modal JavaScript
class PhysicalExaminationModal {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 3; // Steps now: 1 Vital, 2 Exam, 3 Review (Blood Bag removed)
        this.formData = {};
        this.screeningData = null;
        this.isReadonly = false; // prevents submit visibility when terminal state
        
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
            if (e.target.closest('#physicalExaminationModal')) {
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
    
    // Alias: open() calls openModal() for compatibility
    async open(screeningData) {
        return this.openModal(screeningData);
    }
    
    async openModal(screeningData) {
        this.screeningData = screeningData;
        this.resetForm();
        this.isReadonly = false;
        
        // Ensure any parent modal (e.g., donor profile) is closed before showing PE to avoid stacked backdrops
        try {
            const dpEl = document.getElementById('donorProfileModal');
            if (dpEl && dpEl.classList.contains('show')) {
                const dpInst = bootstrap.Modal.getInstance(dpEl) || bootstrap.Modal.getOrCreateInstance(dpEl, { backdrop: 'static', keyboard: false });
                try { dpInst.hide(); } catch(_) {}
            }
        } catch(_) {}

        // Pre-populate donor information
        if (screeningData) {
            document.getElementById('physical-donor-id').value = screeningData.donor_form_id || '';
            document.getElementById('physical-screening-id').value = screeningData.screening_id || '';
            
            // Always populate summary basics; then hydrate existing exam for readonly or resume
            this.populateInitialScreeningSummary(screeningData);
        }
        
        const modalEl = document.getElementById('physicalExaminationModal');
        
        // Show via Bootstrap only; do not manually toggle classes/backdrops
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false });
        
        modal.show();
        // No manual backdrop management; rely on Bootstrap stacking
        
        this.currentStep = 1;
        this.updateProgressIndicator();
        this.showStep(1);
        
        // Add event listener to track when modal is actually shown
        modalEl.addEventListener('shown.bs.modal', () => {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach((backdrop, index) => {
            });
            
            // Ensure all backdrops have correct z-index
            backdrops.forEach((backdrop, index) => {
                if (backdrop.style.zIndex !== '1064') {
                    backdrop.style.zIndex = '1064';
                }
            });

            // Visually hide the Blood Bag stage and renumber Review to step 3
            try {
                // Hide the indicator whose label reads "Blood Bag" (keep code intact for future use)
                const indicators = Array.from(document.querySelectorAll('.physical-step'));
                const step3Indicator = indicators.find(el => /blood\s*bag/i.test(el.textContent || '')) || document.querySelector('.physical-step[data-step="3"]');
                const step3Content = document.getElementById('physical-step-3');
                if (step3Indicator) {
                    // NOTE: Hidden by physician flow — step 3 (Blood Bag) is removed; Review becomes step 3. (UI hidden only)
                    step3Indicator.style.display = 'none';
                }
                if (step3Content) {
                    // NOTE: Hidden by physician flow — Blood Bag content removed. (Code kept for future use)
                    step3Content.style.display = 'none';
                }
                // Find Review indicator (usually data-step="4") and relabel to 3
                let reviewIndicator = indicators.find(el => {
                    try { return /review/i.test(el.textContent || ''); } catch(_) { return false; }
                }) || document.querySelector('.physical-step[data-step="4"]');
                if (reviewIndicator) {
                    reviewIndicator.setAttribute('data-step', '3');
                    const numEl = reviewIndicator.querySelector('.physical-step-number');
                    if (numEl) numEl.textContent = '3';
                }
                // Extra hardening: scan progress steps for any stray node showing 'Blood Bag' and hide its closest step container
                const progress = document.querySelector('.physical-progress-steps');
                if (progress) {
                    Array.from(progress.querySelectorAll('.physical-step')).forEach(step => {
                        try { if (/blood\s*bag/i.test(step.textContent || '')) { step.style.display = 'none'; } } catch(_) {}
                    });
                }
            } catch(e) { console.warn('[PE DEBUG] Failed to hide/renumber steps:', e); }
        }, { once: true });

        // Widen modal for better review layout (UI only; does not alter logic)
        try {
            const dlg = modalEl.querySelector('.modal-dialog');
            if (dlg) {
                dlg.style.maxWidth = '1100px';
                dlg.style.width = '95%';
            }
        } catch(_) {}

        // When PE modal closes (X button or otherwise), return to donor profile modal
        const onHidden = () => {
            try {
                // Don't reopen if we're in a success/approval flow
                if (window.__suppressReturnToProfile || window.__peSuccessActive) {
                    window.__suppressReturnToProfile = false;
                    return;
                }
                
                const dpEl = document.getElementById('donorProfileModal');
                if (dpEl) {
                    
                    // Clear any hide prevention flags
                    try { window.allowDonorProfileHide = false; } catch(_) {}
                    
                    const dp = bootstrap.Modal.getOrCreateInstance(dpEl, { backdrop: 'static', keyboard: false });
                    dp.show();
                    
                    // Refresh with last context if available
                    setTimeout(() => {
                        try {
                            if (window.lastDonorProfileContext && typeof refreshDonorProfileModal === 'function') {
                                refreshDonorProfileModal(window.lastDonorProfileContext);
                            } else {
                                console.warn('[PE MODAL] No context or refreshDonorProfileModal function not found');
                            }
                        } catch(e) {
                            console.error('[PE MODAL] Error refreshing donor profile:', e);
                        }
                    }, 100);
                } else {
                    console.error('[PE MODAL] Donor profile modal element not found!');
                }
                
                // Clean backdrops only if no other modals are open
                setTimeout(() => {
                    try {
                        const anyOpen = document.querySelector('.modal.show');
                        if (!anyOpen) {
                            // Let Bootstrap manage backdrops; avoid manual removal to prevent missing overlays
                            document.body.classList.remove('modal-open');
                            document.body.style.overflow = '';
                            document.body.style.paddingRight = '';
                        }
                    } catch(_) {}
                }, 50);
            } catch(e) {
                console.error('[PE MODAL] Error in onHidden handler:', e);
            }
            // Detach once
            try { modalEl.removeEventListener('hidden.bs.modal', onHiddenCapture, true); } catch(_) {}
        };
        const onHiddenCapture = () => onHidden();
        modalEl.addEventListener('hidden.bs.modal', onHiddenCapture, true);
        // Default blood bag to 'Single' and hide Blood Bag step (3)
        try {
            // NOTE: Default blood bag as 'Single' for submission/summary even if UI is hidden.
            this.setBloodBagSelection('Single');
            const root = document.getElementById('physicalExaminationModal');
            if (root) {
                const step3Content = document.getElementById('physical-step-3');
                if (step3Content) { /* hidden by physician flow (UI only; code intact) */ step3Content.style.display = 'none'; }
                // Prefer hiding by label to avoid mismatches
                const indicators = Array.from(document.querySelectorAll('.physical-step'));
                const bloodBagIndicator = indicators.find(el => /blood\s*bag/i.test(el.textContent || '')) || root.querySelector('.physical-step[data-step="3"]');
                if (bloodBagIndicator) { /* hidden by physician flow (UI only; code intact) */ bloodBagIndicator.style.display = 'none'; }
            }
        } catch(_) {}
        // Re-assert prefilled blood bag shortly after show to avoid race with layout/render
        try {
            const reapply = () => { if (this.formData && this.formData.__bagPrefill) { this.setBloodBagSelection(this.formData.__bagPrefill); } };
            setTimeout(reapply, 200);
            setTimeout(reapply, 600);
            setTimeout(reapply, 1200);
        } catch(_) {}

        // Hydrate existing exam and enforce readonly when status is terminal (anything not Pending)
        try {
            let donorId = screeningData && (screeningData.donor_form_id || screeningData.donor_id);
            // Robust fallback sources for donor id
            if (!donorId) {
                try { donorId = document.getElementById('physical-donor-id')?.value || donorId; } catch(_) {}
                try { donorId = (window.lastDonorProfileContext && (window.lastDonorProfileContext.donorId || (window.lastDonorProfileContext.screeningData && window.lastDonorProfileContext.screeningData.donor_form_id))) || donorId; } catch(_) {}
                try { donorId = window.__peLastDonorId || donorId; } catch(_) {}
            }
            if (donorId) {
                // Try both sources. Prefer fallback (returns physical_exam) because it's what your console shows
                const exam = await this.getLatestExamWithFallback(donorId) || await this.fetchExistingPhysicalExamination(donorId);
                const getState = (ex) => {
                    if (!ex) return '';
                    // Prefer remarks (PE column). If absent, use pe_remarks from combined payload.
                    // If still absent, use medical_approval (from combined/eligibility flow), then status.
                    let source = 'remarks';
                    let raw = ex.remarks;
                    if (raw == null) { source = 'pe_remarks'; raw = ex.pe_remarks; }
                    if (raw == null) { source = 'medical_approval'; raw = ex.medical_approval; }
                    if (raw == null) { source = 'status'; raw = ex.status; }
                    const norm = String(raw ?? '').trim().toLowerCase();
                    return norm;
                };
                const st = getState(exam);
                // Treat any not-pending as terminal
                const terminal = (!!st && st !== 'pending');
                if (terminal || window.forcePhysicalReadonly) {
                    this.hideReviewStage();
                    this.applyReadonlyMode();
                }
                // Perform explicit prefill ONLY when terminal (readonly)
                if (exam && (terminal || window.forcePhysicalReadonly)) {
                    const valOf = (keys) => {
                        for (const k of keys) { if (exam[k] != null && exam[k] !== '') return exam[k]; }
                        return '';
                    };
                    const bp = valOf(['blood_pressure','bp','bloodPres','blood_pres']);
                    const pr = valOf(['pulse_rate','pulse']);
                    const bt = valOf(['body_temp','temperature','temp']);
                    const ga = valOf(['gen_appearance','general_appearance']);
                    const sk = valOf(['skin']);
                    const he = valOf(['heent']);
                    const hl = valOf(['heart_and_lungs','heart_lungs']);
                    const bag = valOf(['blood_bag_type','bag_type']);
                    const setVal = (id, value) => { const el = document.getElementById(id); if (el && value !== undefined && value !== null && String(value).length) { el.value = value; el.classList.add('is-valid'); } };
                    setVal('physical-blood-pressure', bp);
                    setVal('physical-pulse-rate', pr);
                    setVal('physical-body-temp', bt);
                    setVal('physical-gen-appearance', ga);
                    setVal('physical-skin', sk);
                    setVal('physical-heent', he);
                    setVal('physical-heart-lungs', hl);
                    // Blood bag selection
                    this.setBloodBagSelection(bag);
                }
                // If still pending, ensure no prefill leaks
                if (!terminal && !window.forcePhysicalReadonly) {
                    try {
                        document.querySelectorAll('#physicalExaminationForm input, #physicalExaminationForm select, #physicalExaminationForm textarea').forEach(el => {
                            if (el.name === 'blood_bag_type') { el.checked = false; const c = el.closest('.physical-option-card'); if (c) c.classList.remove('selected'); }
                        });
                    } catch(_) {}
                }
            } else if (window.forcePhysicalReadonly) {
                this.hideReviewStage();
                this.applyReadonlyMode();
            }
        } catch(_) {}
    }

    async getLatestExamWithFallback(donorId){
        // Prefer the direct physical_examination row first so we get true PE columns
        try {
            const primary = await makeApiCall(`../api/get-physical-examination.php?donor_id=${donorId}`);
            if (primary) {
                if (primary.physical_exam) return primary.physical_exam;
                if (primary.success && primary.data) return primary.data;
            }
        } catch(_) {}
        try {
            // Fallback: combined donor/screening/medical view
            const fallback = await makeApiCall(`../../assets/php_func/fetch_physical_examination_info.php?donor_id=${donorId}`);
            if (fallback && fallback.success && fallback.data) return fallback.data;
        } catch(_) {}
        return null;
    }
    
    closeModal() {
        const modalEl = document.getElementById('physicalExaminationModal');
        const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
        try { modal.hide(); } catch(_) {}
        this.resetForm();
    }

    async fetchExistingPhysicalExamination(donorId) {
        try {
            // Fetch the latest physical examination record for this donor (includes status)
            const json = await makeApiCall(`../../assets/php_func/fetch_physical_examination_info.php?donor_id=${donorId}&_=${Date.now()}`);
            if (!json || !json.success || !json.data) return;
            const exam = json.data;
            const status = (exam.status || '').toString().toLowerCase();

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
            // Also try to populate vital signs from alternate keys (compat)
            setVal('physical-blood-pressure', exam.blood_pressure_value || exam.bp || document.getElementById('physical-blood-pressure')?.value);
            setVal('physical-pulse-rate', exam.pulse || exam.pulse_rate || document.getElementById('physical-pulse-rate')?.value);
            setVal('physical-body-temp', exam.temperature || exam.body_temp || document.getElementById('physical-body-temp')?.value);

            // Blood bag type (robust normalization) — only prefill when terminal
            const st = (exam.remarks || exam.pe_remarks || exam.medical_approval || exam.status || '').toString().trim().toLowerCase();
            const terminal = (!!st && st !== 'pending');
            if (terminal || this.isReadonly || window.forcePhysicalReadonly) {
                this.setBloodBagSelection(exam.blood_bag_type);
            } else {
                this.setBloodBagSelection('');
            }

            // Update review summary if already on final step
            this.updateSummary();
            return exam;
        } catch (e) {
            console.warn('Failed to fetch existing physical exam:', e);
        }
    }

    setBloodBagSelection(value) {
        try {
            const valRaw = (value == null) ? '' : String(value);
            const normalized = valRaw.trim().toLowerCase().replace(/[^a-z]/g, '');
            // Store for reapplication after render
            try { this.formData.__bagPrefill = valRaw; } catch(_) {}
            try { console.log('[PE] setBloodBagSelection input=', valRaw, 'normalized=', normalized); } catch(_) {}
            // If Pending or empty, clear selection
            if (!normalized || normalized === 'pending') {
                document.querySelectorAll('input[name="blood_bag_type"]').forEach(inp => {
                    inp.checked = false;
                    const card = inp.closest('.physical-option-card');
                    if (card) card.classList.remove('selected');
                });
                try { console.log('[PE] cleared blood_bag_type selection (pending/empty)'); } catch(_) {}
                return;
            }
            const synonyms = {
                'single': 'Single',
                'multiple': 'Multiple',
                'topbottom': 'Top & Bottom',
                'topandbottom': 'Top & Bottom',
                'topbottomset': 'Top & Bottom'
            };
            let radio = null;
            // First pass: exact normalized match on existing radio values
            document.querySelectorAll('input[name="blood_bag_type"]').forEach(inp => {
                if (!radio) {
                    const v = inp.value ? String(inp.value).trim().toLowerCase().replace(/[^a-z]/g, '') : '';
                    if (v === normalized) radio = inp;
                }
            });
            if (!radio) {
                const mapped = synonyms[normalized];
                if (mapped) {
                    radio = document.querySelector(`input[name="blood_bag_type"][value="${mapped}"]`);
                }
            }
            if (radio) {
                // Delay to ensure DOM/labels are fully rendered
                const apply = () => {
                    try {
                        // Uncheck others and remove selection class
                        document.querySelectorAll('input[name="blood_bag_type"]').forEach(inp => {
                            if (inp !== radio) {
                                inp.checked = false;
                                const c = inp.closest('.physical-option-card');
                                if (c) c.classList.remove('selected');
                            }
                        });
                        radio.checked = true;
                        this.updateOptionCardSelection(radio);
                        try { console.log('[PE] applied selection to', radio.value); } catch(_) {}
                        try { radio.dispatchEvent(new Event('change', { bubbles: true })); } catch(_) {}
                    } catch(_) {}
                };
                try { requestAnimationFrame(apply); } catch(_) { setTimeout(apply, 0); }
            } else {
                try {
                    const all = Array.from(document.querySelectorAll('input[name="blood_bag_type"]')).map(i => i.value);
                    console.warn('[PE] No matching radio for blood_bag_type=', valRaw, 'available=', all);
                } catch(_) {}
            }
        } catch(_) {}
    }

    applyReadonlyMode() {
        try {
            const modalRoot = document.getElementById('physicalExaminationModal');
            if (!modalRoot) return;
            this.isReadonly = true;
            // Hide Submit button always in readonly
            const submitBtn = modalRoot.querySelector('.physical-submit-btn');
            if (submitBtn) submitBtn.style.display = 'none';
            // Hide Defer button in readonly
            const deferBtn = modalRoot.querySelector('.physical-defer-btn');
            if (deferBtn) deferBtn.style.display = 'none';
            // Disable all form controls but keep nav buttons active
            modalRoot.querySelectorAll('input, select, textarea, button').forEach(el => {
                const isNav = el.classList.contains('physical-prev-btn') || el.classList.contains('physical-next-btn') || el.classList.contains('physical-close-btn');
                if (!isNav) {
                    if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') {
                        el.disabled = true;
                    }
                }
            });
        } catch(_) {}
    }

    hideReviewStage() {
        try {
            // Limit to 3 steps and hide step 4 indicator/content
            this.totalSteps = 3;
            this.isReadonly = true;
            const s4 = document.querySelector('.physical-step[data-step="4"]');
            if (s4) {
                try { s4.parentNode && s4.parentNode.removeChild(s4); } catch(_) { s4.style.display = 'none'; }
            }
            const step4Content = document.getElementById('physical-step-4');
            if (step4Content) {
                try { step4Content.parentNode && step4Content.parentNode.removeChild(step4Content); } catch(_) { step4Content.style.display = 'none'; }
            }
            // Keep normal flow from step 1; update indicators normally
            this.currentStep = Math.min(this.currentStep || 1, 3);
            this.updateProgressIndicator();
            this.showStep(this.currentStep);
            // Hide Submit when review stage is removed (readonly).
            const submitBtn = document.querySelector('.physical-submit-btn');
            if (submitBtn) submitBtn.style.display = 'none';
            // Ensure any stray step-4 indicator cannot be seen
            try {
                document.querySelectorAll('.physical-step[data-step="4"]').forEach(el => el.remove());
            } catch(_) {}
        } catch(_) {}
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
                // Review is now step 3
                this.updateProgressIndicator();
                this.showStep(this.currentStep);
                if (this.currentStep === 3) {
                    this.updateSummary();
                }
            }
        }
    }
    
    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            // Normal backward step; review is 3
            this.updateProgressIndicator();
            this.showStep(this.currentStep);
        }
    }
    
    goToStep(step) {
        if (step >= 1 && step <= this.currentStep && step <= this.totalSteps) {
            // Go directly to requested step (review is step 3)
            this.currentStep = step;
            this.updateProgressIndicator();
            this.showStep(this.currentStep);
            
            if (this.currentStep === 3) {
                this.updateSummary();
            }
        }
    }
    
    showStep(step) {
        // Hide all step contents
        document.querySelectorAll('.physical-step-content').forEach(stepEl => {
            stepEl.classList.remove('active');
        });
        
        // Stage 3 is now Review (keep Blood Bag code but hide its UI)
        let targetId = `physical-step-${step}`;
        if (step === 3) {
            // Prefer showing the review content if present
            const reviewEl = document.getElementById('physical-step-4');
            if (reviewEl) {
                targetId = 'physical-step-4';
            }
            // Ensure Blood Bag content (step 3) is hidden in UI
            const bloodBagEl = document.getElementById('physical-step-3');
            if (bloodBagEl) { /* physician flow: hide Blood Bag UI only */ bloodBagEl.style.display = 'none'; }
        }
        
        // Show current step content (after remap)
        const currentStepEl = document.getElementById(targetId);
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
        const deferBtn = document.querySelector('.physical-defer-btn');
        
        if (prevBtn) prevBtn.style.display = this.currentStep === 1 ? 'none' : 'inline-block';
        if (nextBtn) nextBtn.style.display = this.currentStep === this.totalSteps ? 'none' : 'inline-block';
        if (submitBtn) {
            if (this.isReadonly) {
                submitBtn.style.display = 'none';
            } else {
                submitBtn.style.display = this.currentStep === this.totalSteps ? 'inline-block' : 'none';
            }
        }
        // Defer visibility rules
        // Readonly mode: hide Defer at all times
        // Editable mode: show except on step 4 (Review)
        if (deferBtn) {
            if (this.isReadonly) {
                deferBtn.style.display = 'none';
            } else {
                deferBtn.style.display = this.currentStep === this.totalSteps ? 'none' : 'inline-block';
            }
        }
    }
    
    updateProgressIndicator() {
        // Always hide the Blood Bag progress stage (UI only; code kept for future use)
        try {
            const steps = document.querySelectorAll('#physicalExaminationModal .physical-progress-steps .physical-step');
            steps.forEach(step => {
                const label = step.querySelector('.physical-step-label');
                const text = (label ? label.textContent : step.textContent) || '';
                if (/\bBlood\s*Bag\b/i.test(text.trim())) {
                    step.style.display = 'none';
                }
            });
        } catch(_) {}

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
                const progressPercentage = ((this.currentStep - 1) / (Math.max(this.totalSteps, 2) - 1)) * 100;
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
            
            // Step 3 removed; no extra validation
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
            const screeningData = await makeApiCall(`../../assets/php_func/get_screening_details.php?screening_id=${screeningId}&_=${Date.now()}`);
            
            // Fetch donor form data  
            const donorData = await makeApiCall(`../../assets/php_func/get_donor_details.php?donor_id=${donorId}&_=${Date.now()}`);
            
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
        const setText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
        // Populate screening information (guard all DOM writes)
        setText('screening-date', screeningData && screeningData.created_at ? new Date(screeningData.created_at).toLocaleDateString() : 'N/A');
        setText('donor-blood-type', (screeningData && (screeningData.blood_type || screeningData.bloodtype)) || 'N/A');
        setText('donation-type', (screeningData && (screeningData.donation_type || screeningData.donation_type_new || screeningData.donationtype)) || 'N/A');
        setText('body-weight', (screeningData && (screeningData.body_weight || screeningData.weight)) ? (screeningData.body_weight || screeningData.weight) + ' kg' : 'N/A');
        setText('specific-gravity', (screeningData && (screeningData.specific_gravity || screeningData.sp_gr || screeningData.hemoglobin)) || 'N/A');

        // Populate donor information
        const fullName = `${(donorData && donorData.surname) || ''}, ${(donorData && donorData.first_name) || ''} ${(donorData && donorData.middle_name) || ''}`.trim();
        setText('donor-name', fullName || 'N/A');
        setText('donor-id', (donorData && (donorData.prc_donor_number || donorData.prc_id || donorData.donor_id)) || 'N/A');
        setText('donor-age', (donorData && donorData.age) || 'N/A');
        setText('donor-sex', (donorData && donorData.sex) || 'N/A');
        setText('donor-civil-status', (donorData && donorData.civil_status) || 'N/A');
        setText('donor-mobile', (donorData && (donorData.mobile || donorData.contact_no)) || 'N/A');
        setText('donor-address', (donorData && (donorData.permanent_address || donorData.address)) || 'N/A');
        setText('donor-occupation', (donorData && donorData.occupation) || 'N/A');
    }
    
    setDefaultScreeningValues() {
        const setText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
        // Set default values if data fetch fails
        setText('screening-date', new Date().toLocaleDateString());
        setText('donor-name', 'Loading...');
        setText('donor-id', 'Loading...');
        setText('donor-blood-type', 'Loading...');
        setText('donation-type', 'Loading...');
        setText('body-weight', 'Loading...');
        setText('specific-gravity', 'Loading...');
        setText('donor-age', 'Loading...');
        setText('donor-sex', 'Loading...');
        setText('donor-civil-status', 'Loading...');
        setText('donor-mobile', 'Loading...');
        setText('donor-address', 'Loading...');
        setText('donor-occupation', 'Loading...');
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
        
        // Update blood bag type (default to Single when hidden)
        const selectedBloodBag = document.querySelector('input[name="blood_bag_type"]:checked');
        const bagText = selectedBloodBag ? selectedBloodBag.value : 'Single';
        const sumEl = document.getElementById('summary-blood-bag');
        if (sumEl) sumEl.textContent = bagText;
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
            // Enforce default Single if missing
            if (!data.blood_bag_type || String(data.blood_bag_type).trim().length === 0) {
                data.blood_bag_type = 'Single';
            }
            if (this.screeningData) {
                data.donor_id = this.screeningData.donor_form_id;
                data.screening_id = this.screeningData.screening_id;
            }
            data.status = 'Pending';
            // Set remarks to Accepted upon physician approval/submit
            data.remarks = 'Accepted';
            // Use makeApiCall if available, otherwise use fetch
            let result;
            if (typeof makeApiCall === 'function') {
                result = await makeApiCall('../../assets/php_func/process_physical_examination.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            } else {
                const response = await fetch('../../assets/php_func/process_physical_examination.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                result = await response.json();
            }
            if (result && result.success) {
                const donorId = (this.screeningData && (this.screeningData.donor_form_id || this.screeningData.donor_id)) || window.__peLastDonorId || null;
                
                // Update medical approval status to "Approved" when physical examination is accepted
                if (donorId) {
                    console.log('Updating medical approval status for donor:', donorId);
                    try {
                        const updateFormData = new FormData();
                        updateFormData.append('donor_id', donorId);
                        updateFormData.append('medical_approval', 'Approved');
                        
                        console.log('Sending medical approval update request...');
                        const updateResponse = await fetch('../../public/api/update-medical-approval.php', {
                            method: 'POST',
                            body: updateFormData
                        });
                        
                        console.log('Update response status:', updateResponse.status);
                        if (updateResponse.ok) {
                            const updateResult = await updateResponse.json();
                            console.log('Update result:', updateResult);
                            if (updateResult && updateResult.success) {
                                // Update in-memory cache so UI doesn't flash Not Approved
                                try {
                                    if (typeof window.medicalByDonor === 'object') {
                                        const key = donorId; const k2 = String(donorId);
                                        window.medicalByDonor[key] = window.medicalByDonor[key] || {};
                                        window.medicalByDonor[key].medical_approval = 'Approved';
                                        window.medicalByDonor[k2] = window.medicalByDonor[k2] || {};
                                        window.medicalByDonor[k2].medical_approval = 'Approved';
                                        console.log('Updated medicalByDonor cache for keys:', key, k2);
                                    }
                                } catch(_) {}
                                console.log('Medical approval status updated successfully');
                            } else {
                                console.error('Failed to update medical approval status:', updateResult);
                            }
                        } else {
                            console.error('HTTP error updating medical approval status:', updateResponse.status);
                        }
                    } catch(error) {
                        console.error('Failed to update medical approval status:', error);
                    }
                } else {
                    console.error('No donor ID available for medical approval update');
                }
                
                // Show minimalist success modal (no buttons), then redirect
                await this.showTransientResultModal({
                    title: 'Accepted',
                    message: 'The donor is medically cleared for donation.',
                    variant: 'success',
                    durationMs: 1800
                });
                this.redirectToDonorProfile(donorId, this.screeningData);
            } else {
                const errMsg = (result && (result.message || result.error)) || 'Submission failed';
                await this.showTransientResultModal({
                    title: 'Submission Error',
                    message: errMsg,
                    variant: 'error',
                    durationMs: 2200
                });
                throw new Error(errMsg);
            }
        } catch (error) {
            console.error('Submission error:', error);
            // Also toast for visibility
            this.showToast('Error: ' + error.message, 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }

    // Create and show a simple, buttonless transient modal (returns after it hides)
    showTransientResultModal({ title = '', message = '', variant = 'info', durationMs = 1500 } = {}) {
        return new Promise((resolve) => {
            try {
                let el = document.getElementById('peTransientModal');
                if (!el) {
                    el = document.createElement('div');
                    el.id = 'peTransientModal';
                    el.className = 'modal fade';
                    el.innerHTML = `
                        <div class="modal-dialog modal-dialog-centered" style="max-width: 520px;">
                            <div class="modal-content" style="border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                                <div class="modal-header" id="peTransientHeader" style="background: #b22222; color: #fff;">
                                    <h5 class="modal-title" id="peTransientTitle"></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body" id="peTransientBody"></div>
                            </div>
                        </div>`;
                    document.body.appendChild(el);
                }
                const titleEl = el.querySelector('#peTransientTitle');
                const bodyEl = el.querySelector('#peTransientBody');
                const headerEl = el.querySelector('#peTransientHeader');
                if (titleEl) titleEl.textContent = title || '';
                if (bodyEl) bodyEl.textContent = message || '';
                if (headerEl) {
                    // Simple variants
                    headerEl.style.background = (variant === 'success') ? '#b22222' : (variant === 'error' ? '#8b0000' : '#6c757d');
                }
                const m = bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: true });
                // Ensure on top of PE modal
                try {
                    el.style.zIndex = '20020';
                    const dlg = el.querySelector('.modal-dialog');
                    if (dlg) dlg.style.zIndex = '20021';
                } catch(_) {}
                // Auto hide after duration
                el.addEventListener('shown.bs.modal', function onShow(){
                    el.removeEventListener('shown.bs.modal', onShow);
                    setTimeout(() => { try { m.hide(); } catch(_) {} }, Math.max(800, durationMs));
                });
                el.addEventListener('hidden.bs.modal', function onHidden(){
                    el.removeEventListener('hidden.bs.modal', onHidden);
                    resolve();
                });
                m.show();
            } catch(_) { resolve(); }
        });
    }
    // Simplified redirect to donor profile (no reload)
    redirectToDonorProfile(donorId, screeningData) {
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
            // Only remove backdrops if no other modals are open
            const otherModals = document.querySelectorAll('.modal.show:not(#physicalExaminationModal)');
            if (otherModals.length === 0) {
                // Avoid force-removing backdrops globally
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        } catch(_) {}
        
        // Store context for reopening
        const ctx = { donorId: String(donorId), screeningData: screeningData };
        if (donorId) {
            try { 
                window.lastDonorProfileContext = ctx; 
                window.__peLastDonorId = String(donorId);
            } catch(_) {}
        }
        
        // Hide PE modal, then show donor profile directly
        try { window.__peRedirecting = true; } catch(_) {}
        try {
            const pe = document.getElementById('physicalExaminationModal');
            if (pe) {
                const inst = bootstrap.Modal.getInstance(pe) || bootstrap.Modal.getOrCreateInstance(pe);
                try { inst.hide(); } catch(_) {}
            }
        } catch(_) {}
        setTimeout(() => {
            const dpEl = document.getElementById('donorProfileModal');
            if (!dpEl) return;
            const dp = bootstrap.Modal.getInstance(dpEl) || new bootstrap.Modal(dpEl);
            dp.show();
            // Refresh content after show
            setTimeout(() => { try { refreshDonorProfileModal(ctx); } catch(_) {} }, 120);
            try { window.__peRedirecting = false; } catch(_) {}
        }, 120);
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
            backdrop.style.zIndex = '1064';
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
