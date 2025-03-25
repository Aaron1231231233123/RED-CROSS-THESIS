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
.dashboard-home-sidebar {
    height: 100vh;
    overflow-y: auto;
    position: fixed;
    width: 240px; /* Adjusted for better balance */
    background-color: #ffffff;
    border-right: 1px solid #ddd;
    padding: 20px;
    transition: width 0.3s ease;
}

.dashboard-home-sidebar a {
    text-decoration: none;
    color: #333;
    display: block;
    padding: 10px;
    border-radius: 5px;
}

.dashboard-home-sidebar a.active, 
.dashboard-home-sidebar a:hover {
    background-color: #e9ecef;
    font-weight: bold;
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
    .dashboard-home-sidebar {
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
    .dashboard-home-sidebar {
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


    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <input type="text" class="form-control mb-3" placeholder="Search...">
                <a href="dashboard-Inventory-System.php"><i class="fas fa-home me-2"></i>Home</a>
                <a href="dashboard-Inventory-System-list-of-donations.php"><i class="fas fa-tint me-2"></i>Blood Donations</a>
                <a href="Dashboard-Inventory-System-Bloodbank.php"><i class="fas fa-tint me-2"></i>Blood Bank</a>
                <a href="#" class="active"><i class="fas fa-list me-2"></i>Requests</a>
                <a href="Dashboard-Inventory-System-Handed-Over.php"><i class="fas fa-check me-2"></i>Handover</a>
            </nav>

           <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="container-fluid p-3 email-container">
                <h2 class="text-left">Hospital Blood Requests</h2>
                <div class="d-flex align-items-center mb-2">
                    <label for="sortSelect" class="me-2 fw-bold text-muted">Sort By:</label>
                    <select id="sortSelect" class="form-select" style="width: 200px; min-width: 180px;">
                        <option value="default">Select an Option</option>
                        <option value="priority">Priority (Urgent First)</option>
                        <option value="hospital">Hospital (A-Z)</option>
                    </select>
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
    
    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
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
});

    

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
    </script>
</body>
</html>