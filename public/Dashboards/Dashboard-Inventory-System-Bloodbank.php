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
.custom-margin {
    margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            margin-top: 100px;
}
.Bloodbank-info-hover{
    cursor: pointer;
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
                        <a href="#" class="active"><i class="fas fa-tint me-2"></i>Blood Bank</a>
                        <a href="Dashboard-Inventory-System-Hospital-Request.php"><i class="fas fa-list me-2"></i>Requests</a>
                        <a href="Dashboard-Inventory-System-Handed-Over.php"><i class="fas fa-check me-2"></i>Handover</a>
                    </nav>

                <!-- Main Content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div class="container-fluid p-4 custom-margin">
                        <h2 class="card-title">Bloodbanks</h2>
                    <div class="d-flex align-items-center mb-3 mt-1">
                        <label for="sortSelect" class="me-2 fw-bold text-muted">Sort By:</label>
                        <select id="sortSelect" class="form-select" style="width: 200px; min-width: 180px;">
                            <option value="default">Select an Option</option>
                            <option value="priority">Priority (Urgent First)</option>
                            <option value="hospital">Hospital (A-Z)</option>
                        </select>
                    </div>
                    <!-- Divider Line -->
                        <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Blood Type</th>
                                        <th>Quantity</th>
                                        <th>Expiration Date</th>
                                        <th>Screening Status</th>
                                        <th>Collection Date</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Example Row (Click to Open Modal) -->
                                    <tr data-bs-toggle="modal" data-bs-target="#viewDonorForm" data-bs-target="#viewDonorForm" class="Bloodbank-info-hover">
                                        <td>O+</td>
                                        <td>5 Bags</td>
                                        <td>2025-04-10</td>
                                        <td>Passed</td>
                                        <td>2025-03-05</td>
                                        <td>Urgent Use</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
                
            <!-- View Donor Details Modal -->
             <div class="modal fade" id="viewDonorForm" tabindex="-1" aria-labelledby="viewDonorFormLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <!-- Modal Header -->
                        <div class="modal-header bg-dark text-white">
                            <h4 class="modal-title w-100"><i class="fas fa-eye me-2"></i> View Donor Details</h4>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <!-- Modal Body -->
                        <div class="modal-body">
                            <div class="donor_form_container">

                                <div class="donor_form_grid grid-3">
                                    <div>
                                        <label class="donor_form_label">Surname</label>
                                        <input type="text" class="donor_form_input" name="surname" value="Doe" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">First Name</label>
                                        <input type="text" class="donor_form_input" name="first_name" value="John" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Middle Name</label>
                                        <input type="text" class="donor_form_input" name="middle_name" value="Michael" readonly>
                                    </div>
                                </div>

                                <div class="donor_form_grid grid-4">
                                    <div>
                                        <label class="donor_form_label">Birthdate</label>
                                        <input type="date" class="donor_form_input" name="birthdate" value="1990-05-15" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Age</label>
                                        <input type="number" class="donor_form_input" name="age" value="34" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Sex</label>
                                        <select class="donor_form_input" name="sex" disabled>
                                            <option value="male" selected>Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Civil Status</label>
                                            <select class="donor_form_input" name="civil_status" disabled>
                                            <option value="single" selected>Single</option>
                                            <option value="married">Married</option>
                                            <option value="widowed">Widowed</option>
                                            <option value="divorced">Divorced</option>
                                        </select>
                                    </div>
                                </div>

                                <h3>PERMANENT ADDRESS</h3>
                                <input type="text" class="donor_form_input" name="permanent_address" value="123 Main St, Iloilo" readonly>

                                <h3>OFFICE ADDRESS</h3>
                                <input type="text" class="donor_form_input" name="office_address" value="XYZ Corp, Iloilo" readonly>

                                <div class="donor_form_grid grid-4">
                                    <div>
                                        <label class="donor_form_label">Nationality</label>
                                        <input type="text" class="donor_form_input" name="nationality" value="Filipino" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Religion</label>
                                        <input type="text" class="donor_form_input" name="religion" value="Christian" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Education</label>
                                        <input type="text" class="donor_form_input" name="education" value="College Graduate" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Occupation</label>
                                        <input type="text" class="donor_form_input" name="occupation" value="Software Engineer" readonly>
                                    </div>
                                </div>

                                <h3>CONTACT No.:</h3>
                                <div class="donor_form_grid grid-3">
                                    <div>
                                        <label class="donor_form_label">Telephone No.</label>
                                        <input type="text" class="donor_form_input" name="telephone" value="033-1234567" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Mobile No.</label>
                                        <input type="text" class="donor_form_input" name="mobile" value="09123456789" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Email Address</label>
                                        <input type="email" class="donor_form_input" name="email" value="johndoe@example.com" readonly>
                                    </div>
                                </div>

                                    <h3>IDENTIFICATION No.:</h3>
                                <div class="donor_form_grid grid-6">
                                    <div>
                                        <label class="donor_form_label">School</label>
                                        <input type="text" class="donor_form_input" name="id_school" value="University of Iloilo" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Company</label>
                                        <input type="text" class="donor_form_input" name="id_company" value="XYZ Corporation" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">PRC</label>
                                        <input type="text" class="donor_form_input" name="id_prc" value="123456" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Driver's License</label>
                                        <input type="text" class="donor_form_input" name="id_drivers" value="987654" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">SSS/GSIS/BIR</label>
                                        <input type="text" class="donor_form_input" name="id_sss_gsis_bir" value="456789" readonly>
                                    </div>
                                    <div>
                                        <label class="donor_form_label">Others</label>
                                        <input type="text" class="donor_form_input" name="id_others" value="None" readonly>
                                    </div>
                                </div>

                                <div class="text-end mt-3">
                                     <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>


    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadDonorInfo(name, id) {
        document.getElementById("donorName").innerText = name;
        document.getElementById("donorID").innerText = id;

        // Example static data (Replace this with an AJAX call to fetch actual data)
        let donationHistory = [
            "2025-03-05 - O+ (Passed Screening)",
            "2024-12-10 - O+ (Passed Screening)",
            "2024-08-20 - O+ (Passed Screening)"
        ];
        
        let historyList = document.getElementById("donationHistory");
        historyList.innerHTML = ""; // Clear previous data
        donationHistory.forEach(item => {
            let li = document.createElement("li");
            li.innerText = item;
            historyList.appendChild(li);
        });
    }
    </script>
</body>
</html>