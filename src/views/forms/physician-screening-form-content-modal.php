<!-- Screening Form Modal (Physician Copy) -->
<div class="modal fade" id="screeningFormModal" tabindex="-1" aria-labelledby="screeningFormModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg" style="max-width: 900px; margin: 2.25rem auto;">
        <div class="modal-content screening-modal-content" style="position: relative; z-index: 1066; pointer-events: auto;">
            <div class="modal-header screening-modal-header">
                <div class="d-flex align-items-center">
                    <div class="screening-modal-icon me-3">
                        <i class="fas fa-clipboard-list fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="screeningFormModalLabel">Initial Screening Form</h5>
                        <small class="text-white-50">To be filled up by the interviewer</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Progress Indicator -->
            <div class="screening-progress-container">
                <div class="screening-progress-steps">
                    <div class="screening-step active" data-step="1">
                        <div class="screening-step-number">1</div>
                        <div class="screening-step-label">Donation Type</div>
                    </div>
                    <div class="screening-step" data-step="2">
                        <div class="screening-step-number">2</div>
                        <div class="screening-step-label">Basic Info</div>
                    </div>
                </div>
                <div class="screening-progress-line">
                    <div class="screening-progress-fill"></div>
                </div>
            </div>
            
            <div class="modal-body screening-modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                <form id="screeningForm">
                    <input type="hidden" name="donor_id" value="">
                    
                    <!-- Step 1: Donation Type -->
                    <div class="screening-step-content active" data-step="1">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-heart me-2 text-danger"></i>Type of Donation</h6>
                            <p class="text-muted mb-4">Please select the donor's choice of donation type</p>
                        </div>
                        
                        <!-- IN-HOUSE Section -->
                        <div id="phys-inhouse-card" class="screening-detail-card" style="background: #e9ecef; border: 1px solid #ddd; margin-bottom: 20px;">
                            <div class="screening-category-title" style="background: #e9ecef; color: #b22222; font-weight: bold; text-align: center; padding: 10px; margin: -20px -20px 15px -20px;">IN-HOUSE</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <select name="donation-type" id="inhouseDonationTypeSelect" class="screening-input">
                                        <option value="">Select Donation Type</option>
                                        <option value="Walk-in">Walk-in</option>
                                        <option value="Replacement">Replacement</option>
                                        <option value="Patient-Directed">Patient-Directed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Patient Information Table (shows when Patient-Directed is selected) -->
                        <div id="patientDetailsSection" style="display: none; margin-bottom: 20px;">
                            <h6 style="color: #b22222; font-weight: bold; margin-bottom: 15px;">Patient Information</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered" style="margin-bottom: 0;">
                                    <thead>
                                        <tr style="background: #b22222; color: white;">
                                            <th style="text-align: center; font-weight: bold;">Patient Name</th>
                                            <th style="text-align: center; font-weight: bold;">Hospital</th>
                                            <th style="text-align: center; font-weight: bold;">Blood Type</th>
                                            <th style="text-align: center; font-weight: bold;">No. of Units</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="text" name="patient-name" class="form-control form-control-sm" placeholder="Enter patient name">
                                            </td>
                                            <td>
                                                <input type="text" name="hospital" class="form-control form-control-sm" placeholder="Enter hospital">
                                            </td>
                                            <td>
                                                <select name="patient-blood-type" class="form-control form-control-sm">
                                                    <option value="">Select Blood Type</option>
                                                    <option value="A+">A+</option>
                                                    <option value="A-">A-</option>
                                                    <option value="B+">B+</option>
                                                    <option value="B-">B-</option>
                                                    <option value="AB+">AB+</option>
                                                    <option value="AB-">AB-</option>
                                                    <option value="O+">O+</option>
                                                    <option value="O-">O-</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="no-units" class="form-control form-control-sm" placeholder="1" min="1">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- MOBILE BLOOD DONATION Section (conditionally visible) -->
                        <div id="phys-mobile-card" class="screening-detail-card" style="background: #e9ecef; border: 1px solid #ddd;">
                            <div class="screening-category-title" style="background: #e9ecef; color: #b22222; font-weight: bold; text-align: center; padding: 10px; margin: -20px -20px 15px -20px;">MOBILE BLOOD DONATION</div>
                            <h6 style="color: #b22222; font-weight: bold; margin-bottom: 15px;">Mobile Donation Details</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="screening-label">Place</label>
                                    <input type="text" name="mobile-place" id="mobilePlaceInput" class="screening-input" placeholder="Enter location">
                                </div>
                                <div class="col-md-6">
                                    <label class="screening-label">Organizer</label>
                                    <input type="text" name="mobile-organizer" id="mobileOrganizerInput" class="screening-input" placeholder="Enter organizer">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Basic Information -->
                    <div class="screening-step-content" data-step="2">
                        <div class="screening-step-title">
                            <h6><i class="fas fa-info-circle me-2 text-danger"></i>Basic Screening Information</h6>
                            <p class="text-muted mb-4">Please enter the basic screening measurements</p>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="screening-label">Body Weight</label>
                                <div class="screening-input-group">
                                    <input type="number" step="0.01" name="body-wt" id="bodyWeightInput" class="screening-input" required min="0">
                                    <span class="screening-input-suffix">kg</span>
                                </div>
                                <div id="bodyWeightAlert" class="text-danger mt-1" style="display: none; font-size: 0.875rem;">
                                    ⚠️ Minimum eligible weight is 50 kg. Donation must be deferred for donor safety.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="screening-label">Specific Gravity</label>
                                <div class="screening-input-group">
                                    <input type="number" step="0.1" name="sp-gr" id="specificGravityInput" class="screening-input" required min="0">
                                    <span class="screening-input-suffix">g/dL</span>
                                </div>
                                <div id="specificGravityAlert" class="text-danger mt-1" style="display: none; font-size: 0.875rem;">
                                    ⚠️ Specific gravity should be between 12.5-18.0 g/dL for donor safety. Values outside this range require deferral.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="screening-label">Blood Type</label>
                                <select name="blood-type" class="screening-input" required>
                                    <option value="" disabled selected>Select Blood Type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 removed for physician read-only view -->
                </form>
            </div>
            
            <div class="modal-footer screening-modal-footer" style="justify-content: flex-end; align-items: center; position: relative; z-index: 1061; background: white; border-top: 1px solid #dee2e6;">
                
                <!-- Right side - Action buttons -->
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-outline-danger" id="screeningPrevButton" style="display: none;">
                        <i class="fas fa-arrow-left me-1"></i>Previous
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="screeningDeferButton" style="display: none;">
                        <i class="fas fa-ban me-1"></i>Defer Donor
                    </button>
                    <button type="button" class="btn btn-danger" id="physNextBtn">
                        <i class="fas fa-arrow-right me-1"></i>Next
                    </button>
                    <button type="button" class="btn btn-success" id="physApproveBtn" style="display: none;">
                        <i class="fas fa-check me-1"></i>Approve
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// This script is local to the physician screening modal only.
(function(){
    try {
        const modalEl = document.getElementById('screeningFormModal');
        const formEl = document.getElementById('screeningForm');
        const nextBtn = document.getElementById('physNextBtn');
        const submitBtn = document.getElementById('screeningSubmitButton');
        const approveBtn = document.getElementById('physApproveBtn');
        let __approveCheckTimer = null;

        // Resolve donor id robustly from multiple sources
        function resolveDonorId(){
            try {
                // Priority 1: Form input
                const direct = (formEl && formEl.querySelector('input[name="donor_id"]')) ? formEl.querySelector('input[name="donor_id"]').value : '';
                if (direct) return direct;
                
                // Priority 2: Current donor data
                if (window.currentDonorData?.donor_id) return window.currentDonorData.donor_id;
                if (window.currentDonorId) return window.currentDonorId;
                
                // Priority 3: Last donor profile context
                if (window.lastDonorProfileContext?.donorId) return window.lastDonorProfileContext.donorId;
                if (window.lastDonorProfileContext?.screeningData?.donor_form_id) return window.lastDonorProfileContext.screeningData.donor_form_id;
                
                return '';
            } catch(_) { return ''; }
        }

        // Normalize and check Approved state from medicalByDonor
        function isMedicalApprovedFor(donorId){
            try {
                if (!donorId || !window.medicalByDonor) return false;
                const rec = window.medicalByDonor[donorId] || window.medicalByDonor[String(donorId)] || null;
                if (!rec || rec.medical_approval == null) return false;
                const raw = String(rec.medical_approval).trim().toLowerCase();
                return raw === 'approved';
            } catch(_) { return false; }
        }

        // Fallback: fetch live medical approval from server and update cache
        async function refreshMhApprovalAndUpdate(donorId){
            try {
                if (!donorId) return false;
                // Try preferred helper if available in this project
                let status = null;
                try {
                    const res = await fetch(`../../assets/php_func/fetch_medical_history_info.php?donor_id=${encodeURIComponent(donorId)}`);
                    if (res && res.ok) {
                        const json = await res.json().catch(()=>null);
                        if (json && json.success && json.data) {
                            status = json.data.medical_approval || json.data.medicalApproval || json.data.status || null;
                        }
                    }
                } catch(_) {}
                if (status == null) return false;
                const raw = String(status).trim();
                // Update in-memory cache for both numeric and string keys
                try {
                    window.medicalByDonor = window.medicalByDonor || {};
                    const k1 = donorId; const k2 = String(donorId);
                    window.medicalByDonor[k1] = window.medicalByDonor[k1] || {};
                    window.medicalByDonor[k1].medical_approval = raw;
                    window.medicalByDonor[k2] = window.medicalByDonor[k2] || {};
                    window.medicalByDonor[k2].medical_approval = raw;
                } catch(_) {}
                return (raw.toLowerCase() === 'approved');
            } catch(_) { return false; }
        }

        // Helper: simple confirm UI fallback
        function confirmProceed(message, onConfirm){
            if (window.customConfirm) return window.customConfirm(message, onConfirm);
            if (confirm(message)) onConfirm && onConfirm();
        }

        // Prefill read-only fields from DB by donor_id (screening_form uses donor_form_id)
        function prefillFromMedicalHistoryCache(){
            try {
                const donorInput = formEl.querySelector('input[name="donor_id"]');
                const donorId = (donorInput && donorInput.value) ||
                                (window.currentDonorData && window.currentDonorData.donor_id) ||
                                (window.lastDonorProfileContext && window.lastDonorProfileContext.donorId) || '';
                if (donorInput && !donorInput.value && donorId) donorInput.value = donorId;
                if (!donorId) return;
                // Use dedicated physician endpoint (maps donor_id to donor_form_id internally)
                const tryFetch = (url) => fetch(url).then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)));
                tryFetch(`../api/get-physician-screening-form-data.php?donor_id=${encodeURIComponent(donorId)}`)
                    .then(data => {
                // removed debug log
                        if (!data || !data.success || !data.screening_form) return;
                        const s = data.screening_form || {};
                        // Donation type
                        const dtInhouse = document.getElementById('inhouseDonationTypeSelect');
                        const dtVal = s.donation_type || s.donation_type_new || '';
                        if (dtInhouse && dtVal) {
                            const formatLabel = (v) => String(v)
                                .replace(/_/g, ' ')
                                .replace(/-/g, ' ')
                                .split(' ')
                                .filter(Boolean)
                                .map(w => w.charAt(0).toUpperCase() + w.slice(1))
                                .join(' ');
                            // If option not present, inject it so value shows
                            let found = false;
                            Array.from(dtInhouse.options).forEach(o => {
                                if (String(o.value) === String(dtVal)) {
                                    found = true;
                                    // Normalize label to Capitalized form
                                    o.textContent = formatLabel(dtVal);
                                }
                            });
                            if (!found) {
                                const opt = document.createElement('option');
                                opt.value = String(dtVal);
                                opt.textContent = formatLabel(dtVal);
                                dtInhouse.appendChild(opt);
                            }
                            dtInhouse.value = String(dtVal);
                        }
                        // Mobile fields
                        const place = document.getElementById('mobilePlaceInput');
                        if (place && s.mobile_location) place.value = s.mobile_location;
                        const org = document.getElementById('mobileOrganizerInput');
                        if (org && s.mobile_organizer) org.value = s.mobile_organizer;
                        // Basic info
                        const bw = document.getElementById('bodyWeightInput');
                        if (bw && s.body_weight) bw.value = s.body_weight;
                        const sg = document.getElementById('specificGravityInput');
                        if (sg && s.specific_gravity) sg.value = s.specific_gravity;
                        const bt = formEl.querySelector('select[name="blood-type"]');
                        if (bt && s.blood_type) bt.value = s.blood_type;
                        // Conditional visibility: show only sections that have data
                        try {
                            const inhouseCard = document.getElementById('phys-inhouse-card');
                            const mobileCard = document.getElementById('phys-mobile-card');
                            const hasInhouse = !!dtVal;
                            const hasMobile = !!(s.mobile_location || s.mobile_organizer);
                            if (inhouseCard && mobileCard) {
                                if (hasInhouse && !hasMobile) {
                                    mobileCard.style.display = 'none';
                                } else if (!hasInhouse && hasMobile) {
                                    inhouseCard.style.display = 'none';
                                } else {
                                    inhouseCard.style.display = '';
                                    mobileCard.style.display = '';
                                }
                            }
                        } catch(_) {}
                        // If API returned nothing usable, explicitly keep fields empty
                        // Ensure Step 1 shows some value to indicate read-only state
                        if (dtInhouse && !dtInhouse.value) dtInhouse.value = '';
                        // Make the entire form read-only
                        setTimeout(() => {
                            formEl.querySelectorAll('input, select, textarea').forEach(el => {
                                el.setAttribute('readonly', 'readonly');
                                el.setAttribute('disabled', 'disabled');
                            });
                        }, 10);
                    })
                    .catch(() => {
                        // Retry using donor_form_id as some legacy endpoints might expect it
                        return tryFetch(`../api/get-screening-form.php?donor_form_id=${encodeURIComponent(donorId)}`)
                            .then(data => {
                                if (!data || !data.success || !data.screening_form) throw new Error('notfound');
                                const s = data.screening_form;
                                const dtInhouse = document.getElementById('inhouseDonationTypeSelect');
                                if (dtInhouse && s.donation_type) dtInhouse.value = s.donation_type;
                                const place = document.getElementById('mobilePlaceInput');
                                if (place && s.mobile_location) place.value = s.mobile_location;
                                const org = document.getElementById('mobileOrganizerInput');
                                if (org && s.mobile_organizer) org.value = s.mobile_organizer;
                                const bw = document.getElementById('bodyWeightInput');
                                if (bw && s.body_weight) bw.value = s.body_weight;
                                const sg = document.getElementById('specificGravityInput');
                                if (sg && s.specific_gravity) sg.value = s.specific_gravity;
                                const bt = formEl.querySelector('select[name="blood-type"]');
                                if (bt && s.blood_type) bt.value = s.blood_type;
                            });
                    })
                    .catch(() => {
                        // If fetch fails, still enforce read-only
                        formEl.querySelectorAll('input, select, textarea').forEach(el => {
                            el.setAttribute('readonly', 'readonly');
                            el.setAttribute('disabled', 'disabled');
                        });
                        // Best-effort fallback: try to hydrate from physical examination
                        try {
                            fetch(`../api/get-physical-examination.php?donor_id=${encodeURIComponent(donorId)}`)
                                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                                .then(px => {
                                    if (!px || !px.success || !px.physical_exam) return;
                                    const pe = px.physical_exam;
                                    const bw = document.getElementById('bodyWeightInput');
                                    if (bw && pe.body_weight) bw.value = pe.body_weight;
                                    const bt = formEl.querySelector('select[name="blood-type"]');
                                    if (bt && pe.blood_type) bt.value = pe.blood_type;
                                })
                                .catch(() => {});
                        } catch(_) {}
                    });
            } catch(_) {}
        }

        // When modal shows, ensure Approve button only on step 2 and hide on step 1
        function ensureApproveOnFinalStep(){
            try {
                if (!approveBtn) return;
                if (submitBtn) submitBtn.style.display = 'none';
                // Determine current step from visible panel
                const step2Active = !!document.querySelector('.screening-step-content[data-step="2"].active');
                // Hide Approve if MH is already Approved
                let mhApproved = false;
                try { mhApproved = isMedicalApprovedFor(resolveDonorId()); } catch(_) {}
                approveBtn.style.display = (step2Active && !mhApproved) ? 'inline-block' : 'none';
                // Hide Next on step 2
                if (nextBtn) nextBtn.style.display = step2Active ? 'none' : '';
                // Schedule a short re-check to catch late data hydration
                try {
                    if (__approveCheckTimer) clearTimeout(__approveCheckTimer);
                    __approveCheckTimer = setTimeout(function(){ try { _recheckApproveVisibility(); } catch(_) {} }, 150);
                } catch(_) {}
                // If still showing and we do not have Approved in cache, try live refresh once
                if (step2Active && !mhApproved) {
                    const donorId = resolveDonorId();
                    refreshMhApprovalAndUpdate(donorId).then((isApproved) => {
                        try { if (isApproved) _recheckApproveVisibility(); } catch(_) {}
                    }).catch(()=>{});
                }
            } catch(_) {}
        }

        function _recheckApproveVisibility(){
            try {
                const step2Active = !!document.querySelector('.screening-step-content[data-step="2"].active');
                if (!approveBtn) return;
                let mhApproved = false;
                try { mhApproved = isMedicalApprovedFor(resolveDonorId()); } catch(_) {}
                approveBtn.style.display = (step2Active && !mhApproved) ? 'inline-block' : 'none';
            } catch(_) {}
        }

        // Combined approval: approve MH (if cached), then submit screening
        async function handleApproveFlow(){
            try {
                // For physician flow: do not submit or close; confirmation handled by Approve click
                if (window.customConfirm) window.customConfirm('Medical History approved.', function(){});
            } catch(_) {}
        }

        // Bind once when modal exists
        if (modalEl) {
            // When shown, prefill from MH cache and place Approve control
            modalEl.addEventListener('shown.bs.modal', function(){
                prefillFromMedicalHistoryCache();
                // Always reset to Step 1 on open
                try {
                    document.querySelectorAll('#screeningFormModal .screening-step-content').forEach(function(p){ p.classList.remove('active'); });
                    const s1 = document.querySelector('#screeningFormModal .screening-step-content[data-step="1"]');
                    if (s1) s1.classList.add('active');
                    // Progress indicators
                    document.querySelectorAll('#screeningFormModal .screening-step').forEach(function(ind){ ind.classList.remove('active','completed'); });
                    const ind1 = document.querySelector('#screeningFormModal .screening-step[data-step="1"]');
                    if (ind1) ind1.classList.add('active');
                    // Ensure inactive step 2 number is gray
                    const ind2num = document.querySelector('#screeningFormModal .screening-step[data-step="2"] .screening-step-number');
                    if (ind2num) { ind2num.style.background = '#e9ecef'; ind2num.style.color = '#6c757d'; }
                    // Reset progress fill
                    const fill = document.querySelector('#screeningFormModal .screening-progress-fill');
                    if (fill) fill.style.width = '0%';
                    const nextBtnLocal = document.getElementById('physNextBtn');
                    if (nextBtnLocal) nextBtnLocal.style.display = '';
                    // Ensure Previous button exists and is hidden on step 1
                    let prevBtn = document.getElementById('physPrevBtn');
                    if (!prevBtn) {
                        prevBtn = document.createElement('button');
                        prevBtn.type = 'button';
                        prevBtn.id = 'physPrevBtn';
                        prevBtn.className = 'btn btn-outline-danger';
                        prevBtn.innerHTML = '<i class="fas fa-arrow-left me-1"></i>Previous';
                        const actions = document.querySelector('#screeningFormModal .screening-modal-footer .d-flex, #screeningFormModal .screening-modal-footer');
                        if (actions) actions.insertBefore(prevBtn, actions.firstChild);
                        prevBtn.addEventListener('click', function(){
                            try { document.querySelectorAll('#screeningFormModal .screening-step-content').forEach(p=>p.classList.remove('active')); document.querySelector('#screeningFormModal .screening-step-content[data-step="1"]').classList.add('active'); } catch(_) {}
                            try { goToStep(1); } catch(_) {}
                        });
                    }
                    prevBtn.style.display = 'none';
                    const approveLocal = document.getElementById('physApproveBtn');
                    if (approveLocal) approveLocal.style.display = 'none';
                    // Immediately enforce approve visibility based on MH status
                    setTimeout(function(){ try { ensureApproveOnFinalStep(); } catch(_) {} }, 50);
                } catch(_) {}
                // Update Approve visibility based on current step
                ensureApproveOnFinalStep();
                // Poll briefly after open to catch async updates to medicalByDonor
                try {
                    let c = 0; const max = 10; // ~1.5s total at 150ms
                    const t = setInterval(function(){
                        try { _recheckApproveVisibility(); } catch(_) {}
                        if (++c >= max) clearInterval(t);
                    }, 150);
                } catch(_) {}
                // Let Bootstrap handle backdrop management naturally
                // No need for aggressive backdrop deduplication
            });

            // On hide, only normalize body; do not remove global backdrops
            modalEl.addEventListener('hidden.bs.modal', function(){
                try {
                    const otherModals = document.querySelectorAll('.modal.show:not(#screeningFormModal)');
                    if (otherModals.length === 0) {
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }
                } catch(_) {}
            });

            // Bind approve button
            if (approveBtn) approveBtn.addEventListener('click', function(){
                try {
                    // Guard: prevent modal from closing during approve flow
                    try { window.__physApproveActive = true; } catch(_) {}
                    const donorId = (formEl.querySelector('input[name="donor_id"]').value) || (window.lastDonorProfileContext && window.lastDonorProfileContext.donorId) || '';
                    if (!donorId) {
                        try { window.__physApproveActive = false; } catch(_) {}
                        if (window.customConfirm) window.customConfirm('Unable to resolve donor. Please close and reopen this record.', function(){});
                        return;
                    }
                    // Show confirmation modal first
                    if (window.customConfirm) {
                        window.customConfirm('Approve Medical History for this donor?', async function(){
                            try {
                                try { if (window.showProcessingModal) window.showProcessingModal('Approving medical history...'); } catch(_) {}
                                const fd = new FormData();
                                fd.append('donor_id', donorId);
                                fd.append('medical_approval', 'Approved');
                                const res = await fetch('../../public/api/update-medical-approval.php', { method: 'POST', body: fd });
                                const json = await res.json().catch(() => ({ success:false }));
                                if (!json || !json.success) {
                                    if (window.customConfirm) window.customConfirm('Failed to approve Medical History.', function(){});
                                } else {
                                    // Success - close modal and redirect to donor profile
                                    try {
                                        const sEl = document.getElementById('screeningFormModal');
                                        if (sEl) {
                                            const sInst = bootstrap.Modal.getInstance(sEl) || new bootstrap.Modal(sEl);
                                            try { sInst.hide(); } catch(_) {}
                                            try { sEl.classList.remove('show'); sEl.style.display='none'; sEl.setAttribute('aria-hidden','true'); } catch(_) {}
                                        }
                                        // Leave backdrop management to Bootstrap
                                    } catch(_) {}
                                }
                            } catch(_) {
                                if (window.customConfirm) window.customConfirm('Network error while approving Medical History.', function(){});
                            }
                            // Release guard after confirm completes
                            try { window.__physApproveActive = false; } catch(_) {}
                            // Ensure donor profile context and refresh on reopen
                            try { 
                                window.lastDonorProfileContext = { donorId: donorId, screeningData: { donor_form_id: donorId } }; 
                                refreshDonorProfileModal({ donorId, screeningData: { donor_form_id: donorId } }); 
                            } catch(_) {}
                            // Don't close modal immediately - let the success confirmation handle it
                            // The modal will be closed after the user confirms the success message
                            try { if (window.hideProcessingModal) window.hideProcessingModal(); } catch(_) {}
                        });
                    } else {
                        if (confirm('Approve Medical History for this donor?')) {
                            // Fallback without custom modal
                            fetch('../../public/api/update-medical-approval.php', {
                                method: 'POST',
                                body: new URLSearchParams({ donor_id: donorId, medical_approval: 'Approved' })
                            }).then(() => {
                                    // Close modal and redirect to donor profile
                                try {
                                    const sEl = document.getElementById('screeningFormModal');
                                    if (sEl) {
                                        const sInst = bootstrap.Modal.getInstance(sEl) || new bootstrap.Modal(sEl);
                                        try { sInst.hide(); } catch(_) {}
                                        try { sEl.classList.remove('show'); sEl.style.display='none'; sEl.setAttribute('aria-hidden','true'); } catch(_) {}
                                    }
                                        // Leave backdrop management to Bootstrap
                                } catch(_) {}
                            });
                        }
                        try { window.__physApproveActive = false; } catch(_) {}
                    }
                } catch(_) { /* noop */ }
            });

            // Removed close guard to allow consistent closing behavior

            // Force step navigation for read-only: allow Next without validation
            function goToStep(step){
                try {
                    const all = Array.from(document.querySelectorAll('#screeningFormModal .screening-step-content'));
                    all.forEach(el => el.classList.remove('active'));
                    const target = document.querySelector(`#screeningFormModal .screening-step-content[data-step="${step}"]`);
                    if (target) target.classList.add('active');
                    // Indicators
                    const s1 = document.querySelector('#screeningFormModal .screening-step[data-step="1"]');
                    const s2 = document.querySelector('#screeningFormModal .screening-step[data-step="2"]');
                    if (s1 && s2) {
                        if (step === 1) {
                            s1.classList.add('active'); s1.classList.remove('completed');
                        s2.classList.remove('active','completed');
                        } else {
                            s1.classList.add('completed'); s1.classList.remove('active');
                            s2.classList.add('active');
                        }
                    }
                // Adjust step number colors for inactive/active states
                const n1 = document.querySelector('#screeningFormModal .screening-step[data-step="1"] .screening-step-number');
                const n2 = document.querySelector('#screeningFormModal .screening-step[data-step="2"] .screening-step-number');
                if (n1 && n2) {
                    if (step === 1) {
                        n1.style.background = '#b22222'; n1.style.color = '#fff';
                        n2.style.background = '#e9ecef'; n2.style.color = '#6c757d';
                    } else {
                        n1.style.background = '#b22222'; n1.style.color = '#fff';
                        n2.style.background = '#b22222'; n2.style.color = '#fff';
                    }
                }
                    // Progress fill (2-step)
                    const fill = document.querySelector('#screeningFormModal .screening-progress-fill');
                    if (fill) fill.style.width = (step === 2 ? '100%' : '0%');
                    // Toggle buttons
                const nextBtnLocal = document.getElementById('physNextBtn');
                    if (nextBtnLocal) nextBtnLocal.style.display = (step === 2 ? 'none' : '');
                    // Do not force-show Approve here; delegate to ensureApproveOnFinalStep()
                const prevBtn = document.getElementById('physPrevBtn');
                if (prevBtn) prevBtn.style.display = (step === 2 ? 'inline-block' : 'none');
                // Enforce approve visibility against MH state when arriving on step 2
                try { ensureApproveOnFinalStep(); } catch(_) {}
                } catch(_) {}
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function(e){
                    try { e.preventDefault(); e.stopPropagation(); } catch(_) {}
                    goToStep(2);
                });
            }
        }
    } catch(_) {}
})();
</script>
<style>
/* Physician screening modal: tighten step spacing for 2-step flow */
#screeningFormModal .screening-progress-steps {
    justify-content: center;
    gap: 40px;
}
#screeningFormModal .screening-progress-line {
    max-width: 420px;
    margin: 4px auto 0;
}
#screeningFormModal .screening-step-number {
    width: 40px; height: 40px; border-radius: 50%;
    background: #e9ecef; color: #6c757d; font-weight: 700; display:flex; align-items:center; justify-content:center;
}
#screeningFormModal .screening-step-label { color: #b22222; font-weight: 600; }
#screeningFormModal .screening-modal-body label.screening-label { color: #333; font-weight: 600; }
#screeningFormModal .screening-input { color: #222; }
#screeningFormModal .modal-content.screening-modal-content { box-shadow: 0 30px 80px rgba(0,0,0,.4); }
#screeningFormModal .modal-backdrop.show { opacity: .6 !important; }
</style>