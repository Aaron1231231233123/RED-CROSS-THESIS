<?php
// Lightweight preview to simulate New vs Returning donor processing without backend
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: Staff Medical History Submissions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
        .card { border-radius: 10px; border: 1px solid #e5e7eb; }
        .card-header { font-weight: 700; background: #f8f9fa; }
    </style>
</head>
<body>
    <h3 class="mb-1">Preview: Staff Medical History Submissions</h3>
    <p class="text-muted mb-4">Simulate flows for New and Returning donors using existing modals.</p>

    <div class="grid mb-4">
        <div class="card">
            <div class="card-header">New Donor Flow</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-danger" id="btnFlowNew">
                    <i class="fas fa-user-plus me-1"></i>Start New Donor
                </button>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Returning Donor Flow</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" id="btnFlowReturning">
                    <i class="fas fa-undo me-1"></i>Start Returning Donor
                </button>
            </div>
        </div>
    </div>

    <?php
        $root = realpath(__DIR__ . '/..');
        @include_once($root . '/../src/views/forms/staff_donor_initial_screening_form_modal.php');
        @include_once($root . '/../src/views/modals/defer-donor-modal.php');
        @include_once($root . '/../src/views/modals/medical-history-approval-modals.php');
    ?>

    <!-- Minimal versions of the three step modals used in the staff page -->
    <div class="modal fade" id="deferralStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-user-md me-2"></i>Donor Status & Donation History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="deferralStatusContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center" style="background-color:#f8f9fa;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn" id="proceedToMedicalHistory" style="background-color:#b22222; color:white; border:none;">
                        <i class="fas fa-clipboard-list me-1"></i>Review Medical History
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="medicalHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i>Medical History Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="medicalHistoryModalContent">
                    <div class="alert alert-info mb-3">Preview mode: form content is loaded from existing component.</div>
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color:#f8f9fa;">
                    <button type="button" class="btn btn-outline-danger" id="btnDeclineMH">Decline</button>
                    <button type="button" class="btn btn-success" id="btnApproveMH">Approve</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="declarationFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                    <h5 class="modal-title">Declaration Form</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="declarationFormModalContent">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/initial-screening-defer-button.js"></script>
    <script src="../../assets/js/screening_form_modal.js"></script>
    <script src="../../assets/js/medical-history-approval.js"></script>

    <script>
    (function(){
        const state = { donorId: null, kind: 'new' }; // kind: 'new' | 'returning'

        function show(elId){ const m = new bootstrap.Modal(document.getElementById(elId)); m.show(); return m; }
        function setDonorIdOnForms(id){
            const el = document.querySelector('#screeningFormModal input[name="donor_id"]');
            if (el) el.value = id;
            const dd = document.getElementById('defer-donor-id'); if (dd) dd.value = id;
        }

        // Entry points
        document.getElementById('btnFlowNew').addEventListener('click', () => {
            state.donorId = '1001';
            state.kind = 'new';
            setDonorIdOnForms(state.donorId);
            // Lightweight donor header
            document.getElementById('deferralStatusContent').innerHTML = `
                <div class="mb-2"><span class="badge bg-danger">New</span></div>
                <h5 class="mb-1">Doe, Jane</h5>
                <div class="text-muted">Age 23, Female • PRC Donor No: -</div>
                <hr>
                <div class="alert alert-info mb-0">No donation history yet for this donor.</div>`;
            show('deferralStatusModal');
        });

        document.getElementById('btnFlowReturning').addEventListener('click', () => {
            state.donorId = '2002';
            state.kind = 'returning';
            setDonorIdOnForms(state.donorId);
            document.getElementById('deferralStatusContent').innerHTML = `
                <div class="mb-2"><span class="badge bg-primary">Returning</span></div>
                <h5 class="mb-1">Smith, John</h5>
                <div class="text-muted">Age 32, Male • PRC Donor No: 000123</div>
                <hr>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-danger"><tr>
                            <th class="text-center">Exam Date</th>
                            <th class="text-center">Vital Signs</th>
                            <th class="text-center">Hematology</th>
                            <th class="text-center">Physician</th>
                            <th class="text-center">Fitness</th>
                            <th class="text-center">Remarks</th>
                        </tr></thead>
                        <tbody>
                            <tr>
                                <td class="text-center">July 12, 2025</td>
                                <td class="text-center">Normal</td>
                                <td class="text-center">Pass</td>
                                <td class="text-center">-</td>
                                <td class="text-center">Eligible</td>
                                <td class="text-center">Approved</td>
                            </tr>
                        </tbody>
                    </table>
                </div>`;
            show('deferralStatusModal');
        });

        // Proceed to Medical History
        document.getElementById('proceedToMedicalHistory').addEventListener('click', () => {
            const mh = show('medicalHistoryModal');
            const body = document.getElementById('medicalHistoryModalContent');
            // Load the shared MH modal content via fetch to avoid duplication
            fetch('../../src/views/forms/medical-history-modal-content.php?donor_id=' + encodeURIComponent(state.donorId))
                .then(r => r.text()).then(html => {
                    body.innerHTML = html;
                    // Execute any inline scripts from the fetched content
                    try {
                        const scripts = body.querySelectorAll('script');
                        scripts.forEach(s => {
                            const n = document.createElement('script');
                            if (s.type) n.type = s.type;
                            if (s.src) { n.src = s.src; } else { n.text = s.textContent || ''; }
                            document.body.appendChild(n);
                        });
                    } catch(_) {}
                    // If modalData is missing, inject sample data and render questions
                    ensureSampleMedicalHistory(state.kind);
                })
                .catch(() => { body.innerHTML = '<div class="alert alert-warning">Preview-only: could not load dynamic form. The next step buttons still demonstrate flow.</div>'; });
        });

        // Medical History Approve/Decline demo
        document.getElementById('btnApproveMH').addEventListener('click', () => {
            const approve = show('medicalHistoryApprovalModal');
            setTimeout(() => { try { approve.hide(); } catch(_){}; showScreening(); }, 1800);
        });
        document.getElementById('btnDeclineMH').addEventListener('click', () => {
            show('medicalHistoryDeclinedModal');
        });

        function showScreening(){
            // Show screening and seed donor
            setDonorIdOnForms(state.donorId);
            const m = new bootstrap.Modal(document.getElementById('screeningFormModal'));
            m.show();
        }

        function ensureSampleMedicalHistory(kind){
            try {
                const existing = document.getElementById('modalData');
                if (existing && existing.textContent && existing.textContent.trim().length > 0) {
                    // Still regenerate questions to ensure UI renders
                    if (typeof generateMedicalHistoryQuestions === 'function') generateMedicalHistoryQuestions();
                    return;
                }
                const sample = {
                    medicalHistoryData: {
                        feels_well: true,
                        previously_refused: false,
                        testing_purpose_only: false,
                        understands_transmission_risk: true,
                        recent_alcohol_consumption: false,
                        recent_aspirin: false,
                        recent_medication: false,
                        recent_donation: false,
                        zika_travel: false,
                        zika_contact: false,
                        zika_sexual_contact: false,
                        blood_transfusion: false,
                        surgery_dental: false,
                        tattoo_piercing: false,
                        risky_sexual_contact: false,
                        unsafe_sex: false,
                        hepatitis_contact: false,
                        imprisonment: false,
                        uk_europe_stay: false,
                        foreign_travel: false,
                        drug_use: false,
                        clotting_factor: false,
                        positive_disease_test: false,
                        malaria_history: false,
                        std_history: false,
                        cancer_blood_disease: false,
                        heart_disease: false,
                        lung_disease: false,
                        kidney_disease: false,
                        chicken_pox: false,
                        chronic_illness: false,
                        recent_fever: false,
                        pregnancy_history: false,
                        last_childbirth: null,
                        recent_miscarriage: false,
                        breastfeeding: false,
                        last_menstruation: null,
                        // default remarks (selects)
                        feels_well_remarks: 'None',
                        previously_refused_remarks: 'None',
                        testing_purpose_only_remarks: 'None',
                        understands_transmission_risk_remarks: 'Understood',
                        recent_alcohol_consumption_remarks: 'None',
                        recent_aspirin_remarks: 'None',
                        recent_medication_remarks: 'None',
                        recent_donation_remarks: 'None',
                        zika_travel_remarks: 'None',
                        zika_contact_remarks: 'None',
                        zika_sexual_contact_remarks: 'None',
                        blood_transfusion_remarks: 'None',
                        surgery_dental_remarks: 'None',
                        tattoo_piercing_remarks: 'None',
                        risky_sexual_contact_remarks: 'None',
                        unsafe_sex_remarks: 'None',
                        hepatitis_contact_remarks: 'None',
                        imprisonment_remarks: 'None',
                        uk_europe_stay_remarks: 'None',
                        foreign_travel_remarks: 'None',
                        drug_use_remarks: 'None',
                        clotting_factor_remarks: 'None',
                        positive_disease_test_remarks: 'None',
                        malaria_history_remarks: 'None',
                        std_history_remarks: 'None',
                        cancer_blood_disease_remarks: 'None',
                        heart_disease_remarks: 'None',
                        lung_disease_remarks: 'None',
                        kidney_disease_remarks: 'None',
                        chicken_pox_remarks: 'None',
                        chronic_illness_remarks: 'None',
                        recent_fever_remarks: 'None',
                        pregnancy_history_remarks: 'None',
                        last_childbirth_remarks: 'None',
                        recent_miscarriage_remarks: 'None',
                        breastfeeding_remarks: 'None',
                        last_menstruation_remarks: 'None'
                    },
                    donorSex: (kind === 'returning' ? 'male' : 'female'),
                    userRole: 'reviewer'
                };
                const script = document.createElement('script');
                script.id = 'modalData';
                script.type = 'application/json';
                script.textContent = JSON.stringify(sample);
                document.getElementById('medicalHistoryModalContent')?.appendChild(script);
                if (typeof generateMedicalHistoryQuestions === 'function') generateMedicalHistoryQuestions();
            } catch(_) {}
        }

        // Auto-start based on URL param ?kind=new|returning
        try {
            const params = new URLSearchParams(window.location.search);
            const kind = params.get('kind');
            if (kind === 'new') {
                document.getElementById('btnFlowNew').click();
            } else if (kind === 'returning') {
                document.getElementById('btnFlowReturning').click();
            }
        } catch(_) {}
    })();
    </script>
</body>
</html>


