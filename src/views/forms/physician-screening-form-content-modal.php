<!-- Screening Form Modal (Physician Copy) -->
<div class="modal fade" id="screeningFormModal" tabindex="-1" aria-labelledby="screeningFormModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width: 900px;">
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
            
            <!-- Summary Only - No Progress Indicator for Physician View -->
            
            <div class="modal-body screening-modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                <form id="screeningForm">
                    <input type="hidden" name="donor_id" value="">
                    
                    <!-- Summary (Stage 4) Only -->
                    <div class="screening-step-title">
                        <h6><i class="fas fa-clipboard-check me-2 text-danger"></i>Screening Summary</h6>
                        <p class="text-muted mb-4">Review the screening details for this donor</p>
                    </div>
                    <div id="reviewContent"></div>
                </form>
            </div>
            
            <div class="modal-footer screening-modal-footer" style="justify-content: flex-end; align-items: center; position: relative; z-index: 1061; background: white; border-top: 1px solid #dee2e6;">
                
                <!-- Right side - Action buttons -->
                <div style="display: flex; gap: 8px;">
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

        // Render-only: fetch latest screening/physical data and generate summary
        function renderSummary(){
            try {
                const donorInput = formEl.querySelector('input[name="donor_id"]');
                const donorId = (donorInput && donorInput.value) ||
                                (window.currentDonorData && window.currentDonorData.donor_id) ||
                                (window.lastDonorProfileContext && window.lastDonorProfileContext.donorId) || '';
                if (donorInput && !donorInput.value && donorId) donorInput.value = donorId;
                if (!donorId) return;
                const tryFetch = (url) => fetch(url).then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)));
                tryFetch(`../api/get-physician-screening-form-data.php?donor_id=${encodeURIComponent(donorId)}`)
                    .then(data => {
                        const s = (data && data.success && data.screening_form) ? data.screening_form : {};
                        generateSummaryHtml({
                            donation_type: s.donation_type || s.donation_type_new || '',
                            mobile_place: s.mobile_location || '',
                            mobile_organizer: s.mobile_organizer || '',
                            body_weight: s.body_weight || '',
                            specific_gravity: s.specific_gravity || '',
                            blood_type: s.blood_type || '',
                            patient_name: s.patient_name || '',
                            hospital: s.hospital || '',
                            patient_blood_type: s.patient_blood_type || s.patient_bloodtype || '',
                            no_units: s.no_units || s.units || ''
                        });
                        // Disable all controls if any exist
                        setTimeout(() => {
                            formEl.querySelectorAll('input, select, textarea').forEach(el => {
                                el.setAttribute('readonly', 'readonly');
                                el.setAttribute('disabled', 'disabled');
                            });
                        }, 10);
                    })
                    .catch(() => {
                        // Retry legacy endpoint using donor_form_id
                        return tryFetch(`../api/get-screening-form.php?donor_form_id=${encodeURIComponent(donorId)}`)
                            .then(data => {
                                if (!data || !data.success || !data.screening_form) throw new Error('notfound');
                                const s = data.screening_form;
                                generateSummaryHtml({
                                    donation_type: s.donation_type || '',
                                    mobile_place: s.mobile_location || '',
                                    mobile_organizer: s.mobile_organizer || '',
                                    body_weight: s.body_weight || '',
                                    specific_gravity: s.specific_gravity || '',
                                    blood_type: s.blood_type || '',
                                    patient_name: s.patient_name || '',
                                    hospital: s.hospital || '',
                                    patient_blood_type: s.patient_blood_type || s.patient_bloodtype || '',
                                    no_units: s.no_units || s.units || ''
                                });
                            });
                    })
                    .catch(() => {
                        // Best-effort fallback: hydrate from physical examination
                        try {
                            fetch(`../api/get-physical-examination.php?donor_id=${encodeURIComponent(donorId)}`)
                                .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
                                .then(px => {
                                    if (!px || !px.success || !px.physical_exam) return;
                                    const pe = px.physical_exam;
                                    generateSummaryHtml({
                                        donation_type: '',
                                        mobile_place: '',
                                        mobile_organizer: '',
                                        body_weight: pe.body_weight || '',
                                        specific_gravity: pe.specific_gravity || pe.sp_gr || '',
                                        blood_type: pe.blood_type || '',
                                        patient_name: '',
                                        hospital: '',
                                        patient_blood_type: '',
                                        no_units: ''
                                    });
                                })
                                .catch(() => {});
                        } catch(_) {}
                        // Enforce read-only even on fallback
                        formEl.querySelectorAll('input, select, textarea').forEach(el => {
                            el.setAttribute('readonly', 'readonly');
                            el.setAttribute('disabled', 'disabled');
                        });
                    });
            } catch(_) {}
        }

        function ensureApproveVisibility(){
            try {
                if (!approveBtn) return;
                let mhApproved = false;
                try { mhApproved = isMedicalApprovedFor(resolveDonorId()); } catch(_) {}
                approveBtn.style.display = (!mhApproved) ? 'inline-block' : 'none';
                // Schedule a short re-check to catch late data hydration
                try {
                    if (__approveCheckTimer) clearTimeout(__approveCheckTimer);
                    __approveCheckTimer = setTimeout(function(){ try { _recheckApproveVisibility(); } catch(_) {} }, 150);
                } catch(_) {}
                // If still showing and we do not have Approved in cache, try live refresh once
                if (!mhApproved) {
                    const donorId = resolveDonorId();
                    refreshMhApprovalAndUpdate(donorId).then((isApproved) => {
                        try { if (isApproved) _recheckApproveVisibility(); } catch(_) {}
                    }).catch(()=>{});
                }
            } catch(_) {}
        }

        function _recheckApproveVisibility(){
            try {
                if (!approveBtn) return;
                let mhApproved = false;
                try { mhApproved = isMedicalApprovedFor(resolveDonorId()); } catch(_) {}
                approveBtn.style.display = (!mhApproved) ? 'inline-block' : 'none';
            } catch(_) {}
        }

        // Combined approval: approve MH (if cached), then submit screening
        async function handleApproveFlow(){
            try {
                // For physician flow: do not submit or close; confirmation handled by Approve click
                if (window.customConfirm) window.customConfirm('Medical History approved.', function(){});
            } catch(_) {}
        }

        // Build summary HTML
        function generateSummaryHtml(data){
            try {
                const reviewContent = document.getElementById('reviewContent');
                if (!reviewContent) return;
                const clean = (v) => (v == null || v === '') ? 'Not specified' : String(v);
                const finalDonationType = (data.mobile_place || data.mobile_organizer) ? 'Mobile' : (data.donation_type || '');
                let html = '';
                // Donation Type first
                html += '<div class="mb-3">';
                html += '<h6 class="text-danger mb-2">Donation Type</h6>';
                html += `<div class="screening-review-item"><span class="screening-review-label">Type:</span><span class="screening-review-value">${finalDonationType ? finalDonationType : '-'}</span></div>`;
                // Basic Information
                html += '</div>';
                html += '<div class="mb-3">';
                html += '<h6 class="text-danger mb-2">Basic Information</h6>';
                html += `<div class="screening-review-item"><span class="screening-review-label">Body Weight:</span><span class="screening-review-value">${data.body_weight ? data.body_weight + ' kg' : '-'}</span></div>`;
                html += `<div class="screening-review-item"><span class="screening-review-label">Specific Gravity:</span><span class="screening-review-value">${data.specific_gravity ? data.specific_gravity : '-'}</span></div>`;
                html += `<div class="screening-review-item"><span class="screening-review-label">Blood Type:</span><span class="screening-review-value">${data.blood_type ? data.blood_type : '-'}</span></div>`;
                html += '</div>';
                reviewContent.innerHTML = html;
            } catch(_) {}
        }

        // Bind once when modal exists
        if (modalEl) {
            // When shown, render summary and place Approve control
            modalEl.addEventListener('shown.bs.modal', function(){
                renderSummary();
                try { const approveLocal = document.getElementById('physApproveBtn'); if (approveLocal) approveLocal.style.display = 'none'; } catch(_) {}
                    // Immediately enforce approve visibility based on MH status
                setTimeout(function(){ try { ensureApproveVisibility(); } catch(_) {} }, 50);
                // Poll briefly after open to catch async updates to medicalByDonor
                try {
                    let c = 0; const max = 10; // ~1.5s total at 150ms
                    const t = setInterval(function(){
                        try { _recheckApproveVisibility(); } catch(_) {}
                        if (++c >= max) clearInterval(t);
                    }, 150);
                } catch(_) {}
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

            // Removed navigation and step logic for summary-only view
        }
    } catch(_) {}
})();
</script>
<style>
/* Physician screening modal: tighten step spacing for 2-step flow */
#screeningFormModal .modal-content.screening-modal-content { box-shadow: 0 30px 80px rgba(0,0,0,.4); }
#screeningFormModal .modal-backdrop.show { opacity: .6 !important; }

/* Summary rows to match target layout */
#screeningFormModal .screening-review-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}
#screeningFormModal .screening-review-item:last-child { border-bottom: none; }
#screeningFormModal .screening-review-label {
    color: #333;
    font-weight: 600;
}
#screeningFormModal .screening-review-value { color: #6c757d; }
#screeningFormModal h6.text-danger { color: #b22222 !important; }
</style>