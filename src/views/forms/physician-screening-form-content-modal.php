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
                    <!-- Approved button removed -->
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
        // Approved button removed

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

        // Approval functions removed

        // Approval functions removed

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

        // Approval functions removed

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
            // When shown, render summary
            modalEl.addEventListener('shown.bs.modal', function(){
                renderSummary();
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

            // Approve button removed

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