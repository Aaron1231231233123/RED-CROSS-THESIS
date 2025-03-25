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
                <a href="Dashboard-Inventory-System-Hospital-Request.php"><i class="fas fa-list me-2"></i>Requests</a>
                <a href="#" class="active"><i class="fas fa-check me-2"></i>Handover</a>
            </nav>

           <!-- Main Content -->
           <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="container-fluid p-3 email-container">
                    <h2 class="text-left">List of Handed Over Requests</h2>
                    <div class="d-flex align-items-center mb-2 mt-1 ">
                        <label for="sortSelect" class="me-2 fw-bold text-muted">Sort By:</label>
                        <select id="sortSelect" class="form-select" style="width: 200px; min-width: 180px;">
                            <option value="default">Select an Option</option>
                            <option value="priority">Priority (Eligible)</option>
                            <option value="priority">Priority (Uneligible)</option>
                            <option value="hospital">Donors Name (A-Z)</option>
                        </select>
                    </div>
                     <!-- Divider Line -->
                <hr class="mt-0 mb-3 border-2 border-secondary opacity-50 mb-2">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Request ID</th>
                                    <th>Patient Name</th>
                                    <th>Blood Type</th>
                                    <th>Units</th>
                                    <th>Component</th>
                                    <th>Hospital</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                    <th>Reason (if Declined)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Accepted Request -->
                                <tr>
                                    <td>#001</td>
                                    <td>John Doe</td>
                                    <td>B+</td>
                                    <td>3</td>
                                    <td>Whole Blood</td>
                                    <td>SPH</td>
                                    <td>Dr. Patro</td>
                                    <td><span class="badge bg-success">Accepted</span></td>
                                    <td>-</td>
                                </tr>
            
                                <!-- Delivering Request -->
                                <tr>
                                    <td>#002</td>
                                    <td>Jane Smith</td>
                                    <td>A-</td>
                                    <td>2</td>
                                    <td>Platelet Concentrate</td>
                                    <td>MMC</td>
                                    <td>Dr. Reyes</td>
                                    <td><span class="badge bg-primary">Delivering</span></td>
                                    <td>-</td>
                                </tr>
            
                                <!-- Declined Request -->
                                <tr>
                                    <td>#003</td>
                                    <td>Mark Wilson</td>
                                    <td>O+</td>
                                    <td>5</td>
                                    <td>Plasma</td>
                                    <td>GH</td>
                                    <td>Dr. Santos</td>
                                    <td><span class="badge bg-danger">Declined</span></td>
                                    <td>Insufficient stock</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
            
            
        </div>
    </div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    </script>
</body>
</html>