<?php
// Preview for Physician/Physical Examination flow (new vs returning donor)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: Staff Physical Submission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
        .card { border-radius: 10px; border: 1px solid #e5e7eb; }
        .card-header { font-weight: 700; background: #f8f9fa; }
        /* Make the included physical examination modal look like a proper modal in preview */
        #physicalExaminationModal .modal-dialog {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 1rem);
            max-height: 80vh;
            width: 90%;
            max-width: 1000px;
            margin: 0 auto;
            z-index: 1060;
        }
        #physicalExaminationModal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            width: 100%;
            max-height: 80vh;
            overflow: hidden;
            pointer-events: auto;
            margin: 0;
        }
        #physicalExaminationModal .modal-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
            border-bottom: none;
        }
        #physicalExaminationModal .modal-header .modal-title {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
        }
        #physicalExaminationModal .modal-body {
            padding: 1.5rem;
            background-color: #ffffff;
            max-height: calc(80vh - 120px);
            overflow-y: auto;
        }
        .modal-backdrop.show { backdrop-filter: blur(4px); }

        /* Step visibility and progress styles (match physician dashboard behavior) */
        .physical-step-content { display: none; }
        .physical-step-content.active { display: block; }
        .physical-progress-container { padding: 1rem 1.5rem 0.5rem; background: #fff; }
        .physical-progress-steps { display: flex; gap: 10px; }
        .physical-step { display: flex; align-items: center; gap: 6px; opacity: 0.6; }
        .physical-step.active { opacity: 1; font-weight: 600; }
        .physical-step.completed { opacity: 1; }
        .physical-step-number { width: 26px; height: 26px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .physical-step.active .physical-step-number, .physical-step.completed .physical-step-number { background: #b22222; color: #fff; }
        .physical-progress-line { height: 4px; background: #f1f3f5; border-radius: 2px; margin: 8px 0 0; position: relative; overflow: hidden; }
        .physical-progress-fill { height: 100%; width: 0; background: #b22222; transition: width .3s ease; }
    </style>
</head>
<body>
    <h3 class="mb-1">Preview: Staff Physical Examination Flow</h3>
    <p class="text-muted mb-4">Simulate flows for New and Returning donors using the physical examination modal.</p>

    <div class="grid mb-4">
        <div class="card">
            <div class="card-header">New Donor Flow</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-danger" id="btnNew">
                    <i class="fas fa-user-plus me-1"></i>Start New Donor
                </button>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Returning Donor Flow</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" id="btnReturning">
                    <i class="fas fa-undo me-1"></i>Start Returning Donor
                </button>
            </div>
        </div>
    </div>

    <?php
        $root = realpath(__DIR__ . '/..');
        @include_once($root . '/../src/views/modals/physical-examination-modal.php');
        @include_once($root . '/../src/views/modals/defer-donor-modal.php');
        // Reuse MH approval modals for continuity if needed
        @include_once($root . '/../src/views/modals/medical-history-approval-modals.php');
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/physical_examination_modal.js"></script>
    <script src="../../assets/js/defer_donor_modal.js"></script>

    <script>
    (function(){
        const state = { donorId: null, kind: 'new' };

        function openPhysical(screeningData){
            if (window.physicalExaminationModal && typeof window.physicalExaminationModal.openModal === 'function') {
                window.physicalExaminationModal.openModal(screeningData);
            } else {
                // Fallback: just show the modal if the helper isn't ready yet
                const m = new bootstrap.Modal(document.getElementById('physicalExaminationModal'));
                m.show();
            }
        }

        // New donor: minimal screening context, no previous exam
        document.getElementById('btnNew').addEventListener('click', () => {
            state.donorId = '3001'; state.kind = 'new';
            const screeningData = {
                donor_form_id: state.donorId,
                screening_id: 'SCRN-NEW-001',
                has_pending_exam: true,
                type: 'screening'
            };
            openPhysical(screeningData);
        });

        // Returning donor: simulate an existing physical exam context
        document.getElementById('btnReturning').addEventListener('click', () => {
            state.donorId = '4002'; state.kind = 'returning';
            const screeningData = {
                donor_form_id: state.donorId,
                screening_id: 'SCRN-RET-002',
                has_pending_exam: false,
                type: 'physical_exam'
            };
            openPhysical(screeningData);
        });
    })();
    </script>
</body>
</html>


