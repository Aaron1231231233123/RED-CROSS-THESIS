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
    box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
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
                <a href="#" class="active"><i class="fas fa-home me-2"></i>Home</a>
                <a href="dashboard-Inventory-System-list-of-donations.php"><i class="fas fa-tint me-2"></i>Blood Donations</a>
                <a href="Dashboard-Inventory-System-Bloodbank.php"><i class="fas fa-tint me-2"></i>Blood Bank</a>
                <a href="#"><i class="fas fa-users me-2"></i>Donors</a>
                <a href="Dashboard-Inventory-System-Hospital-Request.php"><i class="fas fa-list me-2"></i>Requests</a>
                <a href="Dashboard-Inventory-System-Handed-Over.php"><i class="fas fa-check me-2"></i>Handover</a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <h2 class="fs-1 dashboard-welcome-text">Welcome back!</h2>
                <br>

                <!-- Blood Availability Cards -->
                <div class="row row-cols-1 row-cols-md-4 g-3" id="blood-availability">
                    <!-- JS will generate these -->
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-map-marked-alt me-2"></i>GIS Mapping</h5>
                        <div id="map">
                            <!-- Placeholder for GIS Map -->
                            <p class="text-center text-muted mt-5">GIS Map Integration Placeholder</p>
                        </div>
                    </div>
                </div>

                <!-- DSS Quick Insights (4 Cards) -->
                <div class="row row-cols-1 row-cols-md-4 g-3 mt-4" id="quick-insights">
                    <!-- JS will generate these -->
                </div>

                <!-- GIS Mapping Placeholder -->
                
            </main>
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