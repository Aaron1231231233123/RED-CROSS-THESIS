<?php /**
 * NOTE: This file must be parsed by PHP so includes render the modal markup.
 * If you landed here via the previous .html version, rename to .php or open this .php file directly.
 */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: Phlebotomist Process Modals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
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
        
        #bloodCollectionModal {
            z-index: 1060 !important;
        }
        
        #bloodCollectionModal .modal-dialog {
            z-index: 1061 !important;
        }
        
        #bloodCollectionModal .modal-content {
            z-index: 1062 !important;
        }
        
        #phlebotomistBloodCollectionDetailsModal {
            z-index: 1070 !important;
        }
        
        #phlebotomistBloodCollectionDetailsModal .modal-dialog {
            z-index: 1071 !important;
        }
        
        #phlebotomistBloodCollectionDetailsModal .modal-content {
            z-index: 1072 !important;
        }
        
        /* Ensure modal content is clickable */
        .modal-content {
            pointer-events: auto !important;
        }
        
        .modal-content * {
            pointer-events: auto !important;
        }
        
        /* Phlebotomist specific styles */
        .phlebotomist-card {
            border-left: 4px solid #b22222;
        }
        
        .phlebotomist-card .card-header {
            background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
            color: white;
        }
        
        .workflow-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
        }
        
        .status-indicator.pending {
            background: #ffc107;
        }
        
        .status-indicator.completed {
            background: #28a745;
        }
    </style>
</head>
<body>
    <h3 class="mb-1">Preview: Phlebotomist Process Modals</h3>
    <p class="text-muted mb-4">Click buttons below to open phlebotomist modals with sample data.</p>

    <!-- Workflow Status -->
    <div class="workflow-status mb-4">
        <div class="status-indicator completed"></div>
        <span><strong>Interviewer Phase:</strong> Completed</span>
        <div class="status-indicator completed"></div>
        <span><strong>Physician Phase:</strong> Completed</span>
        <div class="status-indicator pending"></div>
        <span><strong>Phlebotomist Phase:</strong> Ready for Collection</span>
    </div>

    <div class="demo-grid mb-4">
        <div class="card phlebotomist-card">
            <div class="card-header">Blood Collection Process</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-danger" id="btnShowBloodCollection">
                    <i class="fas fa-tint me-1"></i>Start Blood Collection
                </button>
            </div>
        </div>
        <div class="card phlebotomist-card">
            <div class="card-header">Collection Details</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" id="btnShowCollectionDetails">
                    <i class="fas fa-clipboard-list me-1"></i>View Collection Details
                </button>
            </div>
        </div>
        <div class="card phlebotomist-card">
            <div class="card-header">Collection Status</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-outline-success" id="btnShowCompletedCollection">
                    <i class="fas fa-check-circle me-1"></i>View Completed Collection
                </button>
            </div>
        </div>
    </div>

    <!-- Sample Donor Information -->
    <div class="card mb-4">
        <div class="card-header">Sample Donor Information</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Donor ID:</strong> 3001
                </div>
                <div class="col-md-3">
                    <strong>Name:</strong> Juan Dela Cruz
                </div>
                <div class="col-md-3">
                    <strong>Blood Type:</strong> O+
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong> Ready for Collection
                </div>
            </div>
        </div>
    </div>

    <?php
        $root = realpath(__DIR__ . '/..'); // points to public
        @include_once($root . '/../src/views/modals/blood-collection-modal.php');
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Enhanced JavaScript files -->
    <script src="../../assets/js/enhanced-workflow-manager.js"></script>
    <script src="../../assets/js/enhanced-data-handler.js"></script>
    <script src="../../assets/js/enhanced-validation-system.js"></script>
    <script src="../../assets/js/unified-staff-workflow-system.js"></script>
    <!-- Phlebotomist specific scripts -->
    <script src="../../assets/js/blood_collection_modal.js"></script>
    <script src="../../assets/js/phlebotomist_blood_collection_details_modal.js"></script>
    
    <script>
    (function(){
        // Initialize global instances
        let bloodCollectionModal = null;
        let phlebotomistDetailsModal = null;
        
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

        function seedBloodCollectionForm(){
            // Set donor data
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
            
            // Set blood type
            const bloodTypeInput = document.getElementById('blood_type');
            if(bloodTypeInput) bloodTypeInput.value = sampleDonorData.blood_type;
            
            // Set donor weight
            const donorWeightInput = document.getElementById('donor_weight');
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
        }

        function populateCollectionDetails(data) {
            // Populate donor information
            const donorNameEl = document.getElementById('phlebotomist-donor-name');
            const donorAgeGenderEl = document.getElementById('phlebotomist-donor-age-gender');
            const donorIdEl = document.getElementById('phlebotomist-donor-id');
            const bloodTypeEl = document.getElementById('phlebotomist-blood-type');

            if(donorNameEl) donorNameEl.textContent = data.donor.name || 'Unknown';
            if(donorAgeGenderEl) donorAgeGenderEl.textContent = `${data.donor.age}, ${data.donor.gender}`;
            if(donorIdEl) donorIdEl.textContent = `Donor ID ${data.donor.donor_id}`;
            if(bloodTypeEl) bloodTypeEl.textContent = data.donor.blood_type;

            // Populate collection details
            const collectionDateEl = document.getElementById('phlebotomist-collection-date');
            const bagTypeEl = document.getElementById('phlebotomist-bag-type');
            const unitSerialEl = document.getElementById('phlebotomist-unit-serial');
            const startTimeEl = document.getElementById('phlebotomist-start-time');
            const endTimeEl = document.getElementById('phlebotomist-end-time');
            const donorReactionEl = document.getElementById('phlebotomist-donor-reaction');
            const expirationDateEl = document.getElementById('phlebotomist-expiration-date');
            const collectionStatusEl = document.getElementById('phlebotomist-collection-status');

            if(collectionDateEl) collectionDateEl.value = data.blood_collection.collection_date || '';
            if(bagTypeEl) bagTypeEl.value = data.blood_collection.blood_bag_type || '';
            if(unitSerialEl) unitSerialEl.value = data.blood_collection.unit_serial_number || '';
            if(startTimeEl) startTimeEl.value = data.blood_collection.start_time || '';
            if(endTimeEl) endTimeEl.value = data.blood_collection.end_time || '';
            if(donorReactionEl) donorReactionEl.value = data.blood_collection.donor_reaction || '';
            if(expirationDateEl) expirationDateEl.value = data.blood_collection.expiration_date || '';
            if(collectionStatusEl) {
                const status = data.blood_collection.is_successful ? 'Successful' : 'Unsuccessful';
                collectionStatusEl.value = status;
            }
        }

        // Debug function to check blood collection modal state
        function debugBloodCollectionModal() {
            console.log('=== BLOOD COLLECTION MODAL DEBUG ===');
            console.log('Modal element:', document.getElementById('bloodCollectionModal'));
            console.log('Form element:', document.getElementById('bloodCollectionForm'));
            console.log('Blood collection modal instance:', bloodCollectionModal);
            console.log('Phlebotomist details modal instance:', phlebotomistDetailsModal);
            console.log('=== END DEBUG ===');
        }

        // Blood Collection Modal Button
        document.getElementById('btnShowBloodCollection')?.addEventListener('click', function(){
            seedBloodCollectionForm();
            showModalById('bloodCollectionModal');
            
            // Initialize blood collection modal functionality after modal is shown
            setTimeout(() => {
                debugBloodCollectionModal();
                
                // Initialize the blood collection modal if the class exists
                if (typeof BloodCollectionModal !== 'undefined' && !bloodCollectionModal) {
                    bloodCollectionModal = new BloodCollectionModal();
                    console.log('Blood collection modal initialized');
                } else if (bloodCollectionModal) {
                    console.log('Blood collection modal already initialized');
                } else {
                    console.error('BloodCollectionModal class not found');
                }
            }, 300);
        });

        // Collection Details Modal Button
        document.getElementById('btnShowCollectionDetails')?.addEventListener('click', function(){
            populateCollectionDetails(sampleCollectionData);
            showModalById('phlebotomistBloodCollectionDetailsModal');
            
            // Initialize phlebotomist details modal functionality after modal is shown
            setTimeout(() => {
                if (typeof PhlebotomistBloodCollectionDetailsModal !== 'undefined' && !phlebotomistDetailsModal) {
                    phlebotomistDetailsModal = new PhlebotomistBloodCollectionDetailsModal();
                    console.log('Phlebotomist details modal initialized');
                } else if (phlebotomistDetailsModal) {
                    console.log('Phlebotomist details modal already initialized');
                } else {
                    console.error('PhlebotomistBloodCollectionDetailsModal class not found');
                }
            }, 300);
        });

        // Completed Collection Button
        document.getElementById('btnShowCompletedCollection')?.addEventListener('click', function(){
            // Modify sample data to show completed collection
            const completedData = {
                ...sampleCollectionData,
                blood_collection: {
                    ...sampleCollectionData.blood_collection,
                    is_successful: true,
                    donor_reaction: 'Collection completed successfully. No adverse reactions.',
                    collection_date: '2025-01-01',
                    start_time: '09:30',
                    end_time: '09:45'
                }
            };
            
            populateCollectionDetails(completedData);
            showModalById('phlebotomistBloodCollectionDetailsModal');
            
            setTimeout(() => {
                if (typeof PhlebotomistBloodCollectionDetailsModal !== 'undefined' && !phlebotomistDetailsModal) {
                    phlebotomistDetailsModal = new PhlebotomistBloodCollectionDetailsModal();
                    console.log('Phlebotomist details modal initialized for completed collection');
                }
            }, 300);
        });

        // Initialize modals when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Phlebotomist preview page loaded');
            
            // Initialize enhanced workflow system if available
            if (typeof UnifiedStaffWorkflowSystem !== 'undefined') {
                window.unifiedSystem = new UnifiedStaffWorkflowSystem();
                console.log('Unified Staff Workflow System initialized for phlebotomist preview');
            }
        });
    })();
    </script>
</body>
<noscript>Enable JavaScript to preview phlebotomist modals.</noscript>
</html>
