<?php /**
 * NOTE: This file must be parsed by PHP so includes render the modal markup.
 * If you landed here via the previous .html version, rename to .php or open this .php file directly.
 */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: Interviewer Process Modals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/medical-history-approval-modals.css" rel="stylesheet">
    <link href="../../assets/css/defer-donor-modal.css" rel="stylesheet">
    <link href="../../assets/css/screening-form-modal.css" rel="stylesheet">
    <link href="../../assets/css/enhanced-modal-styles.css" rel="stylesheet">
    <style>
        body { padding: 24px; }
        .demo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
        .card { border-radius: 10px; border: 1px solid #e5e7eb; }
        .card-header { font-weight: 700; background: #f8f9fa; }
        
        /* Fix modal backdrop z-index issues */
        .modal-backdrop {
            z-index: 1055 !important;
        }
        
        #screeningFormModal {
            z-index: 1060 !important;
        }
        
        #screeningFormModal .modal-dialog {
            z-index: 1061 !important;
        }
        
        #screeningFormModal .modal-content {
            z-index: 1062 !important;
        }
        
        /* Ensure modal content is clickable */
        .modal-content {
            pointer-events: auto !important;
        }
        
        .modal-content * {
            pointer-events: auto !important;
        }
    </style>
</head>
<body>
    <h3 class="mb-1">Preview: Interviewer Process Modals</h3>
    <p class="text-muted mb-4">Click buttons below to open modals with sample data.</p>

    <div class="demo-grid mb-4">
        <div class="card">
            <div class="card-header">Medical History Decision</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-success" id="btnShowApprove"><i class="fas fa-check-circle me-1"></i>Approve</button>
                <button class="btn btn-outline-danger" id="btnShowDeclineConfirm"><i class="fas fa-times-circle me-1"></i>Decline</button>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Defer Donor</div>
            <div class="card-body">
                <button class="btn btn-warning" id="btnShowDefer"><i class="fas fa-ban me-1"></i>Open Defer Modal</button>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Initial Screening Form</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-danger" id="btnShowScreening"><i class="fas fa-clipboard-list me-1"></i>Open Screening</button>
            </div>
        </div>
    </div>

    <?php
        $root = realpath(__DIR__ . '/..'); // points to public
        @include_once($root . '/../src/views/modals/medical-history-approval-modals.php');
        @include_once($root . '/../src/views/modals/defer-donor-modal.php');
        @include_once($root . '/../src/views/forms/staff_donor_initial_screening_form_modal.php');
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Enhanced JavaScript files -->
    <script src="../../assets/js/enhanced-workflow-manager.js"></script>
    <script src="../../assets/js/enhanced-data-handler.js"></script>
    <script src="../../assets/js/enhanced-validation-system.js"></script>
    <script src="../../assets/js/unified-staff-workflow-system.js"></script>
    <!-- Project scripts that power these modals -->
    <script src="../../assets/js/medical-history-approval.js"></script>
    <script src="../../assets/js/defer_donor_modal.js"></script>
    <script src="../../assets/js/initial-screening-defer-button.js"></script>
    <script src="../../assets/js/screening_form_modal.js"></script>
    <script>
    (function(){
        function showModalById(id){
            var el = document.getElementById(id);
            if(!el) return;
            var m = new bootstrap.Modal(el);
            m.show();
        }

        function seedDeferForm(){
            var donorId = document.getElementById('defer-donor-id');
            var screeningId = document.getElementById('defer-screening-id');
            if(donorId) donorId.value = 'demo-donor-uuid-1234';
            if(screeningId) screeningId.value = 'demo-screening-id-5678';
        }

        function seedScreeningForm(){
            var form = document.getElementById('screeningForm');
            if(!form) return;
            var donorId = form.querySelector('input[name="donor_id"]');
            if(donorId) donorId.value = 'demo-donor-uuid-1234';
        }

        // Debug function to check defer modal state
        function debugDeferModal() {
            console.log('=== DEFER MODAL DEBUG ===');
            console.log('Modal element:', document.getElementById('deferDonorModal'));
            console.log('Form element:', document.getElementById('deferDonorForm'));
            console.log('Submit button:', document.getElementById('submitDeferral'));
            console.log('Disapproval reason:', document.getElementById('disapprovalReason'));
            console.log('Deferral type select:', document.getElementById('deferralTypeSelect'));
            console.log('Initialize function available:', typeof initializeDeferModal);
            
            const submitBtn = document.getElementById('submitDeferral');
            if (submitBtn) {
                console.log('Submit button state:', {
                    disabled: submitBtn.disabled,
                    backgroundColor: submitBtn.style.backgroundColor,
                    borderColor: submitBtn.style.borderColor,
                    color: submitBtn.style.color
                });
            }
            console.log('=== END DEBUG ===');
        }

        document.getElementById('btnShowApprove')?.addEventListener('click', function(){
            showModalById('medicalHistoryApprovalModal');
        });

        document.getElementById('btnShowDeclineConfirm')?.addEventListener('click', function(){
            showModalById('medicalHistoryDeclinedModal');
        });

        document.getElementById('btnShowDefer')?.addEventListener('click', function(){
            seedDeferForm();
            showModalById('deferDonorModal');
            // Initialize defer modal functionality after modal is shown
            setTimeout(() => {
                debugDeferModal(); // Debug the modal state
                if (typeof initializeDeferModal === 'function') {
                    initializeDeferModal();
                    console.log('Defer modal initialized');
                } else {
                    console.error('initializeDeferModal function not found');
                }
            }, 300);
        });

        document.getElementById('btnShowScreening')?.addEventListener('click', function(){
            seedScreeningForm();
            showModalById('screeningFormModal');
            // Initialize screening modal functionality after modal is shown
            setTimeout(() => {
                // Fix modal backdrop z-index issue
                const modal = document.getElementById('screeningFormModal');
                const backdrop = document.querySelector('.modal-backdrop');
                
                if (modal && backdrop) {
                    // Set proper z-index values
                    modal.style.zIndex = '1060';
                    backdrop.style.zIndex = '1055';
                    console.log('Fixed modal backdrop z-index');
                }
                
                if (typeof window.initializeScreeningForm === 'function') {
                    window.initializeScreeningForm();
                }
            }, 300);
        });
    })();
    </script>
</body>
<noscript>Enable JavaScript to preview modals.</noscript>
</html>


