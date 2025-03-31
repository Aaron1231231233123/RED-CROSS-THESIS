<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
/* General Body Styling */
body {
    background-color: #f8f9fa;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}
/* Reduce Left Margin for Main Content */
main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
    margin-left: 280px !important; 
}
/* Header */
.dashboard-home-header {
    position: fixed;
    top: 0;
    left: 240px; /* Adjusted sidebar width */
    width: calc(100% - 240px);
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    z-index: 1000;
    transition: left 0.3s ease, width 0.3s ease;
}

/* Sidebar Styling */
.inventory-sidebar {
    height: 100vh;
    overflow-y: auto;
    position: fixed;
    width: 240px;
    background-color: #ffffff;
    border-right: 1px solid #ddd;
    padding: 15px;
    transition: width 0.3s ease;
}

.inventory-sidebar .nav-link {
    color: #333;
    padding: 10px 15px;
    margin: 2px 0;
    border-radius: 4px;
    transition: background-color 0.2s ease, color 0.2s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
}

.inventory-sidebar .nav-link i {
    margin-right: 10px;
    font-size: 0.9rem;
    width: 16px;
    text-align: center;
}

.inventory-sidebar .nav-link:hover {
    background-color: #f8f9fa;
    color: #dc3545;
}

.inventory-sidebar .nav-link.active {
    background-color: #dc3545;
    color: white;
}

.inventory-sidebar .collapse-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: transparent;
    border-radius: 4px;
}

.inventory-sidebar .collapse-menu .nav-link {
    padding: 8px 15px 8px 40px;
    font-size: 0.85rem;
    margin: 0;
    border-radius: 4px;
}

.inventory-sidebar .nav-link[aria-expanded="true"] {
    background-color: transparent;
    color: #333;
}

.inventory-sidebar .nav-link[aria-expanded="true"] i.fa-chevron-down {
    transform: rotate(180deg);
}

.inventory-sidebar i.fa-chevron-down {
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}

.inventory-sidebar .form-control {
    font-size: 0.9rem;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
}

/* Blood Donations Section */
#bloodDonationsCollapse {
    margin-top: 2px;
    border: none;
    background-color: transparent;
}

#bloodDonationsCollapse .nav-link {
    color: #333;
    padding: 8px 15px 8px 40px;
}

#bloodDonationsCollapse .nav-link:hover {
    background-color: #dc3545;
    color: white;
}

/* Updated styles for the search bar */
.search-container {
    width: 100%;
    margin: 0 auto;
}

.input-group {
    width: 100%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-radius: 6px;
    margin-bottom: 1rem;
}

.input-group-text {
    background-color: #fff;
    border: 1px solid #ced4da;
    border-right: none;
    padding: 0.5rem 1rem;
}

.category-select {
    border: 1px solid #ced4da;
    border-right: none;
    border-left: none;
    background-color: #f8f9fa;
    cursor: pointer;
    min-width: 120px;
    height: 45px;
    font-size: 0.95rem;
}

.category-select:focus {
    box-shadow: none;
    border-color: #ced4da;
}

#searchInput {
    border: 1px solid #ced4da;
    border-left: none;
    padding: 0.5rem 1rem;
    font-size: 0.95rem;
    height: 45px;
    flex: 1;
}

#searchInput::placeholder {
    color: #adb5bd;
    font-size: 0.95rem;
}

#searchInput:focus {
    box-shadow: none;
    border-color: #ced4da;
}

.input-group:focus-within {
    box-shadow: 0 0 0 0.15rem rgba(0,123,255,.25);
}

.input-group-text i {
    font-size: 1.1rem;
    color: #6c757d;
}

/* Main Content Styling */
.dashboard-home-main {
    margin-left: 240px; /* Matches sidebar */
    margin-top: 70px;
    min-height: 100vh;
    overflow-x: hidden;
    padding-bottom: 20px;
    padding-top: 20px;
    padding-left: 20px; /* Adjusted padding for balance */
    padding-right: 20px;
    transition: margin-left 0.3s ease;
}


/* Container Fluid Fix */
.container-fluid {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ============================== */
/* Responsive Design Adjustments  */
/* ============================== */

@media (max-width: 992px) {
    /* Adjust sidebar and header for tablets */
    .inventory-sidebar {
        width: 200px;
    }

    .dashboard-home-header {
        left: 200px;
        width: calc(100% - 200px);
    }

    .dashboard-home-main {
        margin-left: 200px;
    }
}

@media (max-width: 768px) {
    /* Collapse sidebar and expand content on smaller screens */
    .inventory-sidebar {
        width: 0;
        padding: 0;
        overflow: hidden;
    }

    .dashboard-home-header {
        left: 0;
        width: 100%;
    }

    .dashboard-home-main {
        margin-left: 0;
        padding: 10px;
    }


    .card {
        min-height: 100px;
        font-size: 14px;
    }

    
}



/* Medium Screens (Tablets) */
@media (max-width: 991px) {
    main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
        margin-left: 240px !important; 
    }
}

/* Small Screens (Mobile) */
@media (max-width: 768px) {
    main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
        margin-left: 0 !important; 
    }
}

.custom-margin {
    margin-top: 80px;
}

        .donor_form_container {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 1400px;
            width: 100%;
            font-size: 14px;
        }

        .donor_form_label {
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .donor_form_input {
            width: 100%;
            padding: 6px;
            margin-bottom: 10px;
            border: 1px solid #000;
            border-radius: 3px;
            font-size: 14px;
            box-sizing: border-box;
            color: #757272;
        }

        .donor_form_grid {
            display: grid;
            gap: 5px;
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .grid-1 {
            grid-template-columns: 1fr;
        }
        .grid-6 {
    grid-template-columns: repeat(6, 1fr); 
}
.email-container {
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
        }

        .email-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
            transition: background 0.3s;
        }

        .email-item:hover {
            background: #f1f1f1;
        }

        .email-header {
            position: left;
            font-weight: bold;
            color: #000000;
        }

        .email-subtext {
            font-size: 14px;
            color: gray;
        }

        .modal-header {
            background: #000000;;
            color: white;
        }

        .modal-body label {
            font-weight: bold;
        }
    .custom-alert {
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out;
    }

    .show-alert {
        opacity: 1;
        transform: translateY(0);
    }

    /* Sidebar Collapsible Styling */

    #bloodDonationsCollapse .nav-link:hover {
        background-color: #f8f9fa;
        color: #dc3545;
    }

    /* Add these modal styles */
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1040;
    }

    .modal {
        z-index: 1050;
    }

    .modal-dialog {
        margin: 1.75rem auto;
    }

    .modal-content {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    </style>
</head>
<body>
    <!-- Move modals to top level, right after body tag -->
    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to proceed to the donor form?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="proceedToDonorForm()">Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="false" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <button class="btn btn-danger" onclick="showConfirmationModal()">
                    <i class="fas fa-plus me-2"></i>Add Walk-in Donor
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block inventory-sidebar">
                <input type="text" class="form-control" placeholder="Search...">
                <a href="dashboard-Inventory-System.php" class="nav-link">
                    <span><i class="fas fa-home"></i>Home</span>
                </a>
                
                <a class="nav-link" data-bs-toggle="collapse" href="#bloodDonationsCollapse" role="button" aria-expanded="false" aria-controls="bloodDonationsCollapse">
                    <span><i class="fas fa-tint"></i>Blood Donations</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <div class="collapse" id="bloodDonationsCollapse">
                    <div class="collapse-menu">
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=pending" class="nav-link">Pending</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=approved" class="nav-link">Approved</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=walk-in" class="nav-link">Walk-in</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=donated" class="nav-link">Donated</a>
                        <a href="dashboard-Inventory-System-list-of-donations.php?status=declined" class="nav-link">Declined</a>
                    </div>
                </div>

                <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                    <span><i class="fas fa-tint"></i>Blood Bank</span>
                </a>
                <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link active">
                    <span><i class="fas fa-list"></i>Requests</span>
                </a>
                <a href="Dashboard-Inventory-System-Handed-Over.php" class="nav-link">
                    <span><i class="fas fa-check"></i>Handover</span>
                </a>
                <a href="../../assets/php_func/logout.php" class="nav-link">
                        <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                </a>
            </nav>

           <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="container-fluid p-3 email-container">
                <h2 class="text-left">Hospital Blood Requests</h2>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="search-container">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <select class="form-select category-select" id="searchCategory" style="max-width: 150px;">
                                    <option value="all">All Fields</option>
                                    <option value="priority">Priority</option>
                                    <option value="hospital">Hospital</option>
                                    <option value="date">Date</option>
                                </select>
                                <input type="text" 
                                    class="form-control" 
                                    id="searchInput" 
                                    placeholder="Search requests...">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
            
                    <!-- Data Placeholder Design --KELLY UDPUTA KAW UMPISAHA NA NI-- -->
                    <div class="email-item d-flex justify-content-between align-items-center" data-bs-toggle="modal" data-bs-target="#requestModal" 
                        onclick="loadRequestDetails('John Doe', 'B+', 'Platelet Concentrate', 'Positive', 5, 'Septic Shock', 'SPH', 'Dr. Patro', 'Urgent')">
                        <div>
                            <span class="email-header text-decoration-underline">SPH - Urgent Request</span><br>
                            <span class="email-subtext">Blood Type: B+ | 5 Units | Platelet Concentrate</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <button class="btn btn-success btn-sm p-2 me-3" onclick="event.stopPropagation(); acceptRequest('John Doe', 'SPH')">Accept Request</button>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </div>
                    </div>
            
                    <div class="email-item d-flex justify-content-between align-items-center" data-bs-toggle="modal" data-bs-target="#requestModal" 
                        onclick="loadRequestDetails('Jane Smith', 'A-', 'Whole Blood', 'Negative', 3, 'Surgery', 'MMC', 'Dr. Reyes', 'Routine')">
                        <div>
                            <span class="email-header text-decoration-underline">MMC - Routine Request</span><br>
                            <span class="email-subtext">Blood Type: A- | 3 Units | Whole Blood</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <button class="btn btn-success btn-sm p-2 me-3" onclick="event.stopPropagation(); acceptRequest('Jane Smith', 'MMC')">Accept Request</button>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </div>
                    </div>
                </div>
            
            <script>
                function acceptRequest(patientName, hospital) {
                    alert(`Request for ${patientName} from ${hospital} has been accepted.`);
                    // Implement backend request handling here
                }
            </script>
            
        
        <!-- Modal for Full Request Details -->
        <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
            
            <div class="modal-dialog modal-lg">
                <div id="alertContainer"></div>
                <div class="modal-content">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Blood Request Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Patient Name:</label>
                                    <input type="text" class="form-control" id="patientName" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label>Blood Type:</label>
                                    <input type="text" class="form-control" id="bloodType" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label>RH Factor:</label>
                                    <input type="text" class="form-control" id="rhFactor" readonly>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Component:</label>
                                    <input type="text" class="form-control" id="component" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label>Units Needed:</label>
                                    <input type="number" class="form-control" id="unitsNeeded" readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Diagnosis:</label>
                                <input type="text" class="form-control" id="diagnosis" readonly>
                            </div>
        
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label>Hospital:</label>
                                    <input type="text" class="form-control" id="hospital" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label>Requesting Physician:</label>
                                    <input type="text" class="form-control" id="physician" readonly>
                                </div>
                            </div>
        
                            <div class="mb-3">
                                <label>Priority:</label>
                                <input type="text" class="form-control" id="priority" readonly>
                            </div>
        
                            <div class="mb-3">
                                <label for="responseSelect">Reason for Declining:</label>
                                <select id="responseSelect" class="form-control">
                                    <option value="">-- Select a Reason --</option>
                                    <option value="Low Blood Supply">Low Blood Supply</option>
                                    <option value="Ineligible Requestor">Ineligible Requestor</option>
                                    <option value="Medical Restrictions">Medical Restrictions</option>
                                    <option value="Pending Verification">Pending Verification</option>
                                    <option value="Duplicate Request">Duplicate Request</option>
                                    <option value="other">Other (Specify Below)</option>
                                    <option value="noReason">No Reason</option> <!-- Option for no reason -->
                                </select>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-danger" id="declineRequest">
                                    <i class="fas fa-times-circle"></i> Unable to Process
                                </button>
                                <button type="button" class="btn btn-success">
                                    <i class="fas fa-check-circle"></i> Accept Request
                                </button>
                            </div>
                            
                </div>
               
            </div>
            
</main>
            
        </div>
    </div>
    
    <div class="modal fade" id="confirmDeclineModal" tabindex="-1" aria-labelledby="confirmDeclineLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Decline</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="modalBodyText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No</button>

                    <button type="button" class="btn btn-danger" id="confirmDeclineBtn">Yes</button>
                </div>
            </div>
        </div>
    
    <!-- jQuery first (if needed) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Consolidate all JavaScript into one script tag
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize modals
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'), {
            backdrop: false,
            keyboard: false
        });

        // Function to show confirmation modal
        window.showConfirmationModal = function() {
            confirmationModal.show();
        };

        // Function to handle form submission
        window.proceedToDonorForm = function() {
            confirmationModal.hide();
            loadingModal.show();
            
            setTimeout(() => {
                window.location.href = '../../src/views/forms/donor-form.php';
            }, 1500);
        };

        let declineButton = document.getElementById("declineRequest");
        let responseSelect = document.getElementById("responseSelect");
        let responseText = document.getElementById("responseText");
        let alertContainer = document.getElementById("alertContainer");
        let confirmDeclineModalEl = document.getElementById("confirmDeclineModal");
        let confirmDeclineModal = new bootstrap.Modal(confirmDeclineModalEl);
        let modalBodyText = document.getElementById("modalBodyText");
        let confirmDeclineBtn = document.getElementById("confirmDeclineBtn");

        // Handle decline button click
        declineButton.addEventListener("click", function () {
            let reason = responseSelect.value;
            let customReason = responseText ? responseText.value.trim() : "";

            // Show alert if no reason is selected
            if (!reason || reason === "noReason") {
                showAlert("danger", "⚠️ Please select a valid reason for declining.");
                return;
            }

            // If "Other" is selected but no custom reason is provided
            if (reason === "other" && !customReason) {
                showAlert("danger", "⚠️ Please provide a reason for declining.");
                return;
            }

            // Show confirmation modal with selected reason
            let finalReason = reason === "other" ? customReason : reason;
            modalBodyText.innerHTML = `Are you sure you want to decline the request for the following reason? <br><strong>("${finalReason}")</strong>`;
            confirmDeclineModal.show();
        });

        function showAlert(type, message) {
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show mt-2" role="alert">
                    <strong>${message}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;

            setTimeout(() => {
                let alertBox = alertContainer.querySelector(".alert");
                if (alertBox) {
                    alertBox.classList.remove("show");
                    setTimeout(() => alertBox.remove(), 500);
                }
            }, 5000);
        }

        function loadRequestDetails(name, bloodType, component, rhFactor, units, diagnosis, hospital, physician, priority) {
            document.getElementById('patientName').value = name;
            document.getElementById('bloodType').value = bloodType;
            document.getElementById('component').value = component;
            document.getElementById('rhFactor').value = rhFactor;
            document.getElementById('unitsNeeded').value = units;
            document.getElementById('diagnosis').value = diagnosis;
            document.getElementById('hospital').value = hospital;
            document.getElementById('physician').value = physician;
            document.getElementById('priority').value = priority;
        }

        function searchRequests() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const emailItems = document.getElementsByClassName('email-item');

            Array.from(emailItems).forEach(item => {
                const headerText = item.querySelector('.email-header').textContent.toLowerCase();
                const subtextContent = item.querySelector('.email-subtext').textContent.toLowerCase();
                const found = headerText.includes(searchInput) || subtextContent.includes(searchInput);
                item.style.display = found ? '' : 'none';
            });
        }

        // Add event listener for real-time search
        document.getElementById('searchInput').addEventListener('keyup', searchRequests);
    });
    </script>
</body>
</html>