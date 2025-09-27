<?php /**
 * Complete Staff Workflow Preview
 * This file demonstrates the complete staff workflow from interviewer to physician to phlebotomist
 */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: Complete Staff Workflow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/medical-history-approval-modals.css" rel="stylesheet">
    <link href="../../assets/css/defer-donor-modal.css" rel="stylesheet">
    <link href="../../assets/css/screening-form-modal.css" rel="stylesheet">
    <link href="../../assets/css/enhanced-modal-styles.css" rel="stylesheet">
    <style>
        body { padding: 24px; background: #f8f9fa; }
        .workflow-container { max-width: 1200px; margin: 0 auto; }
        .workflow-header { text-align: center; margin-bottom: 2rem; }
        .workflow-steps { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .workflow-step { 
            flex: 1; 
            text-align: center; 
            padding: 1rem; 
            margin: 0 0.5rem; 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }
        .workflow-step.completed { border-left: 4px solid #28a745; }
        .workflow-step.active { border-left: 4px solid #007bff; }
        .workflow-step.pending { border-left: 4px solid #ffc107; }
        .workflow-step-icon { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0 auto 1rem; 
            font-size: 1.5rem; 
            color: white;
        }
        .workflow-step.completed .workflow-step-icon { background: #28a745; }
        .workflow-step.active .workflow-step-icon { background: #007bff; }
        .workflow-step.pending .workflow-step-icon { background: #ffc107; }
        .workflow-arrow { 
            position: absolute; 
            right: -20px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #6c757d; 
            font-size: 1.5rem; 
        }
        .workflow-step:last-child .workflow-arrow { display: none; }
        .demo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .card { border-radius: 10px; border: 1px solid #e5e7eb; }
        .card-header { font-weight: 700; background: #f8f9fa; }
        .interviewer-card { border-left: 4px solid #007bff; }
        .physician-card { border-left: 4px solid #6f42c1; }
        .phlebotomist-card { border-left: 4px solid #b22222; }
        .interviewer-card .card-header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; }
        .physician-card .card-header { background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%); color: white; }
        .phlebotomist-card .card-header { background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; }
        
        /* Modal z-index fixes */
        .modal-backdrop { z-index: 1055 !important; }
        #medicalHistoryApprovalModal, #medicalHistoryDeclineModal { z-index: 1060 !important; }
        #deferDonorModal { z-index: 1065 !important; }
        #screeningFormModal { z-index: 1070 !important; }
        #physicalExaminationModal { z-index: 1075 !important; }
        #bloodCollectionModal { z-index: 1080 !important; }
        #phlebotomistBloodCollectionDetailsModal { z-index: 1085 !important; }
        
        .modal-content { pointer-events: auto !important; }
        .modal-content * { pointer-events: auto !important; }
        
        .workflow-status { 
            background: white; 
            border-radius: 10px; 
            padding: 1.5rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-item { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            margin-bottom: 0.5rem; 
        }
        .status-indicator { 
            width: 12px; 
            height: 12px; 
            border-radius: 50%; 
        }
        .status-indicator.completed { background: #28a745; }
        .status-indicator.active { background: #007bff; }
        .status-indicator.pending { background: #ffc107; }
    </style>
</head>
<body>
    <div class="workflow-container">
        <div class="workflow-header">
            <h2 class="mb-3">Complete Staff Workflow Preview</h2>
            <p class="text-muted">Demonstrates the complete donor processing workflow from interviewer to physician to phlebotomist</p>
        </div>

        <!-- Workflow Steps -->
        <div class="workflow-steps">
            <div class="workflow-step completed">
                <div class="workflow-step-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h5>Interviewer</h5>
                <p class="text-muted mb-0">Medical History & Screening</p>
                <div class="workflow-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
            <div class="workflow-step completed">
                <div class="workflow-step-icon">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <h5>Physician</h5>
                <p class="text-muted mb-0">Physical Examination</p>
                <div class="workflow-arrow">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
            <div class="workflow-step active">
                <div class="workflow-step-icon">
                    <i class="fas fa-tint"></i>
                </div>
                <h5>Phlebotomist</h5>
                <p class="text-muted mb-0">Blood Collection</p>
            </div>
        </div>

        <!-- Workflow Status -->
        <div class="workflow-status">
            <h5 class="mb-3">Current Workflow Status</h5>
            <div class="status-item">
                <div class="status-indicator completed"></div>
                <span><strong>Interviewer Phase:</strong> Medical history approved, initial screening completed</span>
            </div>
            <div class="status-item">
                <div class="status-indicator completed"></div>
                <span><strong>Physician Phase:</strong> Physical examination completed, donor approved for collection</span>
            </div>
            <div class="status-item">
                <div class="status-indicator active"></div>
                <span><strong>Phlebotomist Phase:</strong> Ready for blood collection</span>
            </div>
        </div>

        <!-- Sample Donor Information -->
        <div class="card mb-4">
            <div class="card-header">Sample Donor Information</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2"><strong>Donor ID:</strong> 3001</div>
                    <div class="col-md-3"><strong>Name:</strong> Juan Dela Cruz</div>
                    <div class="col-md-2"><strong>Age:</strong> 28</div>
                    <div class="col-md-2"><strong>Blood Type:</strong> O+</div>
                    <div class="col-md-3"><strong>Current Status:</strong> Ready for Collection</div>
                </div>
            </div>
        </div>

        <!-- Workflow Phase Cards -->
        <div class="demo-grid">
            <!-- Interviewer Phase -->
            <div class="card interviewer-card">
                <div class="card-header">Interviewer Phase</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-light" id="btnShowMHApprove">
                        <i class="fas fa-check-circle me-1"></i>Medical History Approve
                    </button>
                    <button class="btn btn-outline-light" id="btnShowMHDecline">
                        <i class="fas fa-times-circle me-1"></i>Medical History Decline
                    </button>
                    <button class="btn btn-outline-light" id="btnShowDefer">
                        <i class="fas fa-ban me-1"></i>Defer Donor
                    </button>
                    <button class="btn btn-outline-light" id="btnShowScreening">
                        <i class="fas fa-clipboard-list me-1"></i>Initial Screening
                    </button>
                </div>
            </div>

            <!-- Physician Phase -->
            <div class="card physician-card">
                <div class="card-header">Physician Phase</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-light" id="btnShowPhysicalNew">
                        <i class="fas fa-user-plus me-1"></i>New Donor Physical
                    </button>
                    <button class="btn btn-outline-light" id="btnShowPhysicalReturning">
                        <i class="fas fa-undo me-1"></i>Returning Donor Physical
                    </button>
                </div>
            </div>

            <!-- Phlebotomist Phase -->
            <div class="card phlebotomist-card">
                <div class="card-header">Phlebotomist Phase</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-light" id="btnShowBloodCollection">
                        <i class="fas fa-tint me-1"></i>Blood Collection
                    </button>
                    <button class="btn btn-outline-light" id="btnShowCollectionDetails">
                        <i class="fas fa-clipboard-list me-1"></i>Collection Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php
        $root = realpath(__DIR__ . '/..'); // points to public
        @include_once($root . '/../src/views/modals/medical-history-approval-modals.php');
        @include_once($root . '/../src/views/modals/defer-donor-modal.php');
        @include_once($root . '/../src/views/forms/staff_donor_initial_screening_form_modal.php');
        @include_once($root . '/../src/views/modals/physical-examination-modal.php');
        @include_once($root . '/../src/views/modals/blood-collection-modal.php');
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
    <script src="../../assets/js/physical_examination_modal.js"></script>
    <script src="../../assets/js/blood_collection_modal.js"></script>
    <script src="../../assets/js/phlebotomist_blood_collection_details_modal.js"></script>
    
    <script>
    (function(){
        // Sample data for testing
        const sampleDonorData = {
            donor_id: '3001',
            screening_id: 'SCRN-3001-001',
            physical_exam_id: 'PE-3001-001',
            name: 'Juan Dela Cruz',
            age: 28,
            gender: 'Male',
            blood_type: 'O+',
            weight: 70
        };
        
        const sampleCollectionData = {
            donor: {
                donor_id: '3001',
                name: 'Juan Dela Cruz',
                age: 28,
                gender: 'Male',
                blood_type: 'O+'
            },
            blood_collection: {
                collection_id: 'BC-20250101-0001',
                collection_date: '2025-01-01',
                blood_bag_type: 'Single',
                unit_serial_number: 'BC-20250101-0001',
                start_time: '09:30',
                end_time: '09:45',
                is_successful: true,
                donor_reaction: 'No adverse reactions observed',
                expiration_date: '2025-03-01'
            }
        };

        function showModalById(id){
            var el = document.getElementById(id);
            if(!el) return;
            var m = new bootstrap.Modal(el);
            m.show();
        }

        // Interviewer Phase Handlers
        document.getElementById('btnShowMHApprove')?.addEventListener('click', function(){
            showModalById('medicalHistoryApprovalModal');
        });

        document.getElementById('btnShowMHDecline')?.addEventListener('click', function(){
            showModalById('medicalHistoryDeclineModal');
        });

        document.getElementById('btnShowDefer')?.addEventListener('click', function(){
            // Seed defer form
            var donorId = document.getElementById('defer-donor-id');
            var screeningId = document.getElementById('defer-screening-id');
            if(donorId) donorId.value = sampleDonorData.donor_id;
            if(screeningId) screeningId.value = sampleDonorData.screening_id;
            
            showModalById('deferDonorModal');
            setTimeout(() => {
                if (typeof initializeDeferModal === 'function') {
                    initializeDeferModal();
                }
            }, 300);
        });

        document.getElementById('btnShowScreening')?.addEventListener('click', function(){
            // Seed screening form
            var form = document.getElementById('screeningForm');
            if(form) {
                var donorId = form.querySelector('input[name="donor_id"]');
                if(donorId) donorId.value = sampleDonorData.donor_id;
            }
            
            showModalById('screeningFormModal');
            setTimeout(() => {
                if (typeof window.initializeScreeningForm === 'function') {
                    window.initializeScreeningForm();
                }
            }, 300);
        });

        // Physician Phase Handlers
        document.getElementById('btnShowPhysicalNew')?.addEventListener('click', function(){
            const screeningData = {
                donor_form_id: sampleDonorData.donor_id,
                screening_id: sampleDonorData.screening_id,
                has_pending_exam: true,
                type: 'screening'
            };
            
            if (window.physicalExaminationModal && typeof window.physicalExaminationModal.openModal === 'function') {
                window.physicalExaminationModal.openModal(screeningData);
            } else {
                showModalById('physicalExaminationModal');
            }
        });

        document.getElementById('btnShowPhysicalReturning')?.addEventListener('click', function(){
            const screeningData = {
                donor_form_id: sampleDonorData.donor_id,
                screening_id: sampleDonorData.screening_id,
                has_pending_exam: false,
                type: 'physical_exam'
            };
            
            if (window.physicalExaminationModal && typeof window.physicalExaminationModal.openModal === 'function') {
                window.physicalExaminationModal.openModal(screeningData);
            } else {
                showModalById('physicalExaminationModal');
            }
        });

        // Phlebotomist Phase Handlers
        document.getElementById('btnShowBloodCollection')?.addEventListener('click', function(){
            // Seed blood collection form
            const donorIdInput = document.querySelector('#bloodCollectionForm input[name="donor_id"]');
            const screeningIdInput = document.querySelector('#bloodCollectionForm input[name="screening_id"]');
            const physicalExamIdInput = document.querySelector('#bloodCollectionForm input[name="physical_exam_id"]');
            
            if(donorIdInput) donorIdInput.value = sampleDonorData.donor_id;
            if(screeningIdInput) screeningIdInput.value = sampleDonorData.screening_id;
            if(physicalExamIdInput) physicalExamIdInput.value = sampleDonorData.physical_exam_id;
            
            // Set collection date to today
            const collectionDateInput = document.getElementById('collection_date');
            if(collectionDateInput) {
                const today = new Date().toISOString().split('T')[0];
                collectionDateInput.value = today;
            }
            
            // Set blood type and weight
            const bloodTypeInput = document.getElementById('blood_type');
            const donorWeightInput = document.getElementById('donor_weight');
            if(bloodTypeInput) bloodTypeInput.value = sampleDonorData.blood_type;
            if(donorWeightInput) donorWeightInput.value = sampleDonorData.weight;
            
            // Generate unit serial number
            const unitSerialInput = document.getElementById('unit_serial_number');
            if(unitSerialInput) {
                const today = new Date();
                const dateStr = today.getFullYear() + 
                               String(today.getMonth() + 1).padStart(2, '0') + 
                               String(today.getDate()).padStart(2, '0');
                unitSerialInput.value = `BC-${dateStr}-0001`;
            }
            
            showModalById('bloodCollectionModal');
            setTimeout(() => {
                if (typeof BloodCollectionModal !== 'undefined') {
                    new BloodCollectionModal();
                }
            }, 300);
        });

        document.getElementById('btnShowCollectionDetails')?.addEventListener('click', function(){
            // Populate collection details
            const donorNameEl = document.getElementById('phlebotomist-donor-name');
            const donorAgeGenderEl = document.getElementById('phlebotomist-donor-age-gender');
            const donorIdEl = document.getElementById('phlebotomist-donor-id');
            const bloodTypeEl = document.getElementById('phlebotomist-blood-type');

            if(donorNameEl) donorNameEl.textContent = sampleCollectionData.donor.name;
            if(donorAgeGenderEl) donorAgeGenderEl.textContent = `${sampleCollectionData.donor.age}, ${sampleCollectionData.donor.gender}`;
            if(donorIdEl) donorIdEl.textContent = `Donor ID ${sampleCollectionData.donor.donor_id}`;
            if(bloodTypeEl) bloodTypeEl.textContent = sampleCollectionData.donor.blood_type;

            // Populate collection details
            const collectionDateEl = document.getElementById('phlebotomist-collection-date');
            const bagTypeEl = document.getElementById('phlebotomist-bag-type');
            const unitSerialEl = document.getElementById('phlebotomist-unit-serial');
            const startTimeEl = document.getElementById('phlebotomist-start-time');
            const endTimeEl = document.getElementById('phlebotomist-end-time');
            const donorReactionEl = document.getElementById('phlebotomist-donor-reaction');
            const expirationDateEl = document.getElementById('phlebotomist-expiration-date');
            const collectionStatusEl = document.getElementById('phlebotomist-collection-status');

            if(collectionDateEl) collectionDateEl.value = sampleCollectionData.blood_collection.collection_date;
            if(bagTypeEl) bagTypeEl.value = sampleCollectionData.blood_collection.blood_bag_type;
            if(unitSerialEl) unitSerialEl.value = sampleCollectionData.blood_collection.unit_serial_number;
            if(startTimeEl) startTimeEl.value = sampleCollectionData.blood_collection.start_time;
            if(endTimeEl) endTimeEl.value = sampleCollectionData.blood_collection.end_time;
            if(donorReactionEl) donorReactionEl.value = sampleCollectionData.blood_collection.donor_reaction;
            if(expirationDateEl) expirationDateEl.value = sampleCollectionData.blood_collection.expiration_date;
            if(collectionStatusEl) {
                const status = sampleCollectionData.blood_collection.is_successful ? 'Successful' : 'Unsuccessful';
                collectionStatusEl.value = status;
            }
            
            showModalById('phlebotomistBloodCollectionDetailsModal');
            setTimeout(() => {
                if (typeof PhlebotomistBloodCollectionDetailsModal !== 'undefined') {
                    new PhlebotomistBloodCollectionDetailsModal();
                }
            }, 300);
        });

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Complete staff workflow preview loaded');
            
            // Initialize enhanced workflow system if available
            if (typeof UnifiedStaffWorkflowSystem !== 'undefined') {
                window.unifiedSystem = new UnifiedStaffWorkflowSystem();
                console.log('Unified Staff Workflow System initialized for complete workflow preview');
            }
        });
    })();
    </script>
</body>
<noscript>Enable JavaScript to preview complete staff workflow.</noscript>
</html>
