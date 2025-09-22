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
    <style>
        body { padding: 24px; }
        .demo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
        .card { border-radius: 10px; border: 1px solid #e5e7eb; }
        .card-header { font-weight: 700; background: #f8f9fa; }
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

        document.getElementById('btnShowApprove')?.addEventListener('click', function(){
            showModalById('medicalHistoryApprovalModal');
        });

        document.getElementById('btnShowDeclineConfirm')?.addEventListener('click', function(){
            showModalById('medicalHistoryDeclinedModal');
        });

        document.getElementById('btnShowDefer')?.addEventListener('click', function(){
            seedDeferForm();
            showModalById('deferDonorModal');
        });

        document.getElementById('btnShowScreening')?.addEventListener('click', function(){
            seedScreeningForm();
            showModalById('screeningFormModal');
        });
    })();
    </script>
</body>
<noscript>Enable JavaScript to preview modals.</noscript>
</html>


