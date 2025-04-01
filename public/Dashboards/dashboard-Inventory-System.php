<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check for correct role (example for admin dashboard)
$required_role = 1; // Admin role
if ($_SESSION['role_id'] !== $required_role) {
    header("Location: unauthorized.php");
    exit();
}
?>

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
    margin-top: 30px !important;
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
    width: 240px;
    background-color: #ffffff;
    border-right: 1px solid #ddd;
    padding: 15px;
    transition: width 0.3s ease;
}

.dashboard-home-sidebar .nav-link {
    color: #333;
    padding: 10px 15px;
    margin: 2px 0;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
}

.dashboard-home-sidebar .nav-link i {
    margin-right: 10px;
    font-size: 0.9rem;
    width: 16px;
    text-align: center;
}

.dashboard-home-sidebar .nav-link:hover {
    background-color: #f8f9fa;
    color: #dc3545;
}

.dashboard-home-sidebar .nav-link.active {
    background-color: #dc3545;
    color: white;
}

.dashboard-home-sidebar .collapse-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.dashboard-home-sidebar .collapse-menu .nav-link {
    padding: 8px 15px 8px 40px;
    font-size: 0.85rem;
    margin: 0;
    border-radius: 0;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] {
    background-color: #f8f9fa;
    color: #dc3545;
}

.dashboard-home-sidebar .nav-link[aria-expanded="true"] i.fa-chevron-down {
    transform: rotate(180deg);
}

.dashboard-home-sidebar i.fa-chevron-down {
    font-size: 0.8rem;
    transition: transform 0.2s ease;
}

.dashboard-home-sidebar .form-control {
    font-size: 0.9rem;
    padding: 8px 12px;
    border-radius: 4px;
    margin-bottom: 10px;
}

/* Blood Donations Section */
#bloodDonationsCollapse {
    margin-top: 2px;
    border: none;
}

#bloodDonationsCollapse .nav-link {
    color: #666;
    padding: 8px 15px 8px 40px;
}

#bloodDonationsCollapse .nav-link:hover {
    color: #dc3545;
    font-weight: 600;
    background-color: transparent;
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

/* Welcome Section */
.dashboard-welcome-text {
    display: block !important;  
    margin-top: 10vh !important; /* Reduced margin */
    padding: 10px 0;
    font-size: 24px;
    font-weight: bold;
    color: #333;
    text-align: left; /* Ensures it's left-aligned */
}

/* Card Styling */
.card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    min-height: 120px;
    flex-grow: 1;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 12px rgba(194, 194, 194, 0.7);
}
/* Last card to have a */
/* Add margin to the last column in the row */
#quick-insights{
    margin-bottom: 50px !important;
}
/* Progress Bar Styling */
.progress {
    height: 10px;
    border-radius: 5px;
}

.progress-bar {
    border-radius: 5px;
}

/* GIS Map Styling */
#map {
    height: 600px;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    border-radius: 10px;
    background-color: #f8f9fa;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 50px;
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

    .dashboard-welcome-text {
        margin-top: 5vh;
        font-size: 20px;
    }

    .card {
        min-height: 100px;
        font-size: 14px;
    }

    #map {
        height: 350px;
    }
}

@media (max-width: 480px) {
    /* Optimize layout for mobile */
    .dashboard-welcome-text {
        margin-top: 3vh;
        font-size: 18px;
    }

    #map {
        height: 250px;
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

/* Add these styles to your existing CSS */
.card {
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.bg-danger.bg-opacity-10 {
    background-color: rgba(220, 53, 69, 0.1) !important;
}

.text-danger {
    color: #dc3545 !important;
}

h6 {
    color: #333;
    font-weight: 600;
}

.card-subtitle {
    font-size: 0.9rem;
}

.text-muted {
    color: #6c757d !important;
}

#map {
    border: 1px solid #dee2e6;
}

.content-wrapper {
    background: #fff;
    box-shadow: 0 0 15px rgba(0,0,0,0.05);
    margin-top: 80px;
    border-radius: 12px;
}

.bg-danger.bg-opacity-10 {
    background-color: #FFE9E9 !important;
}

.text-danger {
    color: #941022 !important;
}

.card {
    border-radius: 8px;
}

.shadow-sm {
    box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;
}

/* Add new styles for statistics cards */
.statistics-card {
    transition: transform 0.2s;
}

.statistics-card:hover {
    transform: translateY(-2px);
}

/* Statistics Cards Styling */
.inventory-system-stats-container {
    display: flex;
    flex-direction: column;
}

.inventory-system-stats-card {
    background-color: #FFE9E9;
    border-radius: 12px;
    border: none;
}

.inventory-system-stats-body {
    padding: 1.5rem;
}

.inventory-system-stats-label {
    color: #941022;
    font-size: 1.875rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.inventory-system-stats-value {
    color: #941022;
    font-size: 5rem;
    font-weight: 600;
    line-height: 1;
    margin-top: 10px;
    text-align: right;
    margin-right: 18px;
}

/* Add these styles in the style section */
.inventory-system-blood-card {
    background-color: #f8f8f8;  /* slightly darker white */
    border-radius: 15px;
    border: none;
    transition: all 0.3s ease;
    box-shadow: rgba(0, 0, 0, 0.15) 0px 8px 16px, 
                rgba(0, 0, 0, 0.1) 0px 4px 8px,
                rgba(0, 0, 0, 0.05) 0px 1px 3px;
}

.inventory-system-blood-card:hover {
    transform: translateY(-5px);
    box-shadow: rgba(0, 0, 0, 0.25) 0px 14px 28px, 
                rgba(0, 0, 0, 0.22) 0px 10px 10px,
                rgba(0, 0, 0, 0.18) 0px 4px 6px;
}

.inventory-system-blood-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
}

.inventory-system-blood-availability {
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0;
}

/* Blood type specific colors */
.blood-type-a-pos { border-left: 4px solid #8B0000; }  /* dark red */
.blood-type-a-neg { border-left: 4px solid #8B0000; }  /* dark red */
.blood-type-b-pos { border-left: 4px solid #FF8C00; }  /* orange */
.blood-type-b-neg { border-left: 4px solid #FF8C00; }  /* orange */
.blood-type-o-pos { border-left: 4px solid #00008B; }  /* dark blue */
.blood-type-o-neg { border-left: 4px solid #00008B; }  /* dark blue */
.blood-type-ab-pos { border-left: 4px solid #9D94FF; }
.blood-type-ab-neg { border-left: 4px solid #8A82E8; }

    </style>
</head>
<body>
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
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <div class="position-sticky">
                    <div class="d-flex align-items-center ps-1 mb-4 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    
                    <ul class="nav flex-column">
                        <input type="text" class="form-control" placeholder="Search...">
                        <a href="dashboard-Inventory-System.php" class="nav-link active">
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
                                <a href="dashboard-Inventory-System-list-of-donations.php?status=declined" class="nav-link">Declined</a>
                            </div>
                        </div>

                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                            <span><i class="fas fa-tint"></i>Blood Bank</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                            <span><i class="fas fa-list"></i>Requests</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Handed-Over.php" class="nav-link">
                            <span><i class="fas fa-check"></i>Handover</span>
                        </a>
                        <a href="../../assets/php_func/logout.php" class="nav-link">
                                <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                        </a>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0" style="font-weight: 700; font-size: 2rem;">Welcome back!</h2>
                        <span class="text-muted"><?php echo date('d F Y'); ?> ■</span>
                    </div>

                    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card inventory-system-stats-card">
                <div class="card-body inventory-system-stats-body">
                    <div class="inventory-system-stats-container">
                        <span class="inventory-system-stats-label">Hospital Requests</span>
                        <span class="inventory-system-stats-value">15</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card inventory-system-stats-card">
                <div class="card-body inventory-system-stats-body">
                    <div class="inventory-system-stats-container">
                        <span class="inventory-system-stats-label">Blood Received</span>
                        <span class="inventory-system-stats-value">12</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card inventory-system-stats-card">
                <div class="card-body inventory-system-stats-body">
                    <div class="inventory-system-stats-container">
                        <span class="inventory-system-stats-label">In Stock</span>
                        <span class="inventory-system-stats-value">20</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

                    <!-- Available Blood per Unit Section -->
                    <div class="mb-4">
                        <h6 class="mb-3" style="font-weight: 500;">Available Blood per Unit</h6>
                        <div class="row g-4">
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-pos">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type A+</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-a-neg">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type A-</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-pos">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type B+</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-b-neg">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type B-</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-pos">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type O+</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-o-neg">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type O-</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-pos">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type AB+</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card inventory-system-blood-card blood-type-ab-neg">
                                    <div class="card-body p-3">
                                        <h6 class="inventory-system-blood-title">Blood Type AB-</h6>
                                        <p class="inventory-system-blood-availability">Availability</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- GIS Mapping Section -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <span class="me-2">■</span>
                            <h6 class="mb-0" style="font-weight: 500;">GIS Mapping</h6>
                        </div>
                        <div id="map" class="bg-light rounded-3" style="height: 600px; width: 100%; max-width: 100%; margin: 0 auto;">
                            <!-- Map will be loaded here -->
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

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

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Blood type availability data (Placeholder data can be removed)
        const bloodAvailabilityData = [
            { type: "A+", level: 85 },
            { type: "A-", level: 40 }, // Critical
            { type: "B+", level: 60 },
            { type: "B-", level: 30 }, // Critical
            { type: "O+", level: 90 },
            { type: "O-", level: 45 }, // Critical
            { type: "AB+", level: 75 },
            { type: "AB-", level: 25 } // Critical
        ];

        // DSS Quick Insights (Static Data)
        const quickInsightsData = [
            { title: "Total Donors", info: "5,245 active donors", icon: "fas fa-users" },
            { title: "Blood Units Collected", info: "1,280 this month", icon: "fas fa-tint" },
            { title: "Pending Requests", info: "45 waiting for match", icon: "fas fa-list" },
            { title: "Successful Matches", info: "92% fulfillment rate", icon: "fas fa-check" }
        ];

        // Function to generate blood availability cards
        function generateBloodAvailability() {
            const container = document.getElementById("blood-availability");
            bloodAvailabilityData.forEach(item => {
                const criticalClass = item.level < 50 ? "text-danger fw-bold" : "";
                const criticalText = item.level < 50 ? "⚠️ Critical" : "";

                let progressColor = "bg-success"; // Green
                if (item.level < 50) progressColor = "bg-danger"; // Red for critical
                else if (item.level < 70) progressColor = "bg-warning"; // Yellow for medium levels

                let card = `
                    <div class="col">
                        <div class="card h-100 p-3">
                            <h5 class="card-title"><i class="fas fa-tint me-2"></i>Blood Type: ${item.type}</h5>
                            <p class="card-text">Availability: <span class="${criticalClass}">${item.level}% ${criticalText}</span></p>
                            <div class="progress">
                                <div class="progress-bar ${progressColor}" role="progressbar" style="width: ${item.level}%" aria-valuenow="${item.level}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += card;
            });
        }

        // Function to generate DSS quick insights
        function generateDSSInsights() {
            const container = document.getElementById("quick-insights");
            quickInsightsData.forEach(item => {
                let card = `
                    <div class="col">
                        <div class="card h-100 p-3">
                            <h5 class="card-title"><i class="${item.icon} me-2"></i>${item.title}</h5>
                            <p class="card-text">${item.info}</p>
                        </div>
                    </div>
                `;
                container.innerHTML += card;
            });
        }

        // Call functions to populate UI
        generateBloodAvailability();
        generateDSSInsights();

        // Initialize modals and add button functionality
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
        });
    </script>
        <!-- 🔗 PHP Integration Guide -->
        <!--
        1 Remove the JavaScript static data in bloodAvailabilityData[].
        2 Connect your PHP backend to fetch blood stock levels from the database.
        3 Use PHP to echo the data inside the <div id="blood-availability">.
    
        Example PHP Code:
        ---------------------------------
        <?php
            include '../src/config/database/php'; // Your database connection file
            $result = mysqli_query($conn, "SELECT blood_type, stock_level FROM blood_inventory");
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<div class="col">
                          <div class="card p-3 shadow-sm">
                              <h5>🩸 Blood Type: ' . $row['blood_type'] . '</h5>
                              <p>Availability: <span>' . $row['stock_level'] . '%</span></p>
                              <div class="progress" style="height: 10px;">
                                  <div class="progress-bar bg-success" role="progressbar" style="width: ' . $row['stock_level'] . '%" aria-valuenow="' . $row['stock_level'] . '" aria-valuemin="0" aria-valuemax="100"></div>
                              </div>
                          </div>
                      </div>';
            }
        ?>
        ---------------------------------
        4️⃣ Insert this PHP snippet inside the <div id="blood-availability"> to replace JS-generated cards.
        -->
</body>
</html>