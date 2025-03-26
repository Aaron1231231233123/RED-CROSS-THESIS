<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Initialize cURL session for Supabase to get donors ready for physical examination
$ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?status=eq.pending_physical&select=*');

// Set the headers
$headers = array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY
);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Execute the request
$response = curl_exec($ch);
curl_close($ch);

// Decode the JSON response
$donors = json_decode($response, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Staff Dashboard - Physical Examination</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
               :root {
            --bg-color: #f8f9fa; /* Light background */
            --text-color: #000; /* Dark text */
            --sidebar-bg: #ffffff; /* White sidebar */
            --card-bg: #e9ecef; /* Light gray cards */
            --hover-bg: #dee2e6; /* Light gray hover */
        }

        body.light-mode {
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .sidebar {
            background: var(--sidebar-bg);
            height: 100vh;
            padding: 1rem;
        }

        .sidebar .nav-link {
            color: #666; /* Darker text for links */
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: var(--text-color);
            background: var(--hover-bg);
            border-radius: 5px;
        }

        .main-content {
            padding: 1.5rem;
        }

        .card {
            background: var(--card-bg);
            color: var(--text-color);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: scale(1.03);
        }



/* Table Styling */
.dashboard-staff-tables {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    
}

.dashboard-staff-tables thead th {
    background-color: #242b31; /* Blue header */
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: 600;
}

.dashboard-staff-tables tbody td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
}

/* Alternating Row Colors */
.dashboard-staff-tables tbody tr:nth-child(odd) {
    background-color: #f8f9fa; /* Light gray for odd rows */
}

.dashboard-staff-tables tbody tr:nth-child(even) {
    background-color: #ffffff; /* White for even rows */
}

/* Hover Effect */
.dashboard-staff-tables tbody tr:hover {
    background-color: #e9f5ff; /* Light blue on hover */
    transition: background-color 0.3s ease;
}

.custom-margin {
            margin: 30px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
}
/* General Styling */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa; /* Light background for better contrast */
    color: #333; /* Dark text for readability */
    margin: 0;
    padding: 0;
}


/* Donor Form Header */
.donor_form_header {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    align-items: center;
    text-align: center;
    margin-bottom: 20px;
    color: #b22222; /* Red color for emphasis */
}

.donor_form_header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
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
/* Loader Animation -- Modal Design */
.loading-spinner {
    position: fixed;
    top: 50%;
    left: 50%;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 8px solid #ddd;
    border-top: 8px solid #a82020;
    animation: rotateSpinner 1s linear infinite;
    display: none;
    z-index: 10000;
    transform: translate(-50%, -50%);
}

@keyframes rotateSpinner {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Confirmation Modal */
.confirmation-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 25px;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
    text-align: center;
    z-index: 9999;
    border-radius: 10px;
    width: 300px;
    display: none;
    opacity: 0;
}

/* Fade-in and Fade-out Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translate(-50%, -55%); }
    to { opacity: 1; transform: translate(-50%, -50%); }
}

@keyframes fadeOut {
    from { opacity: 1; transform: translate(-50%, -50%); }
    to { opacity: 0; transform: translate(-50%, -55%); }
}

.confirmation-modal.show {
    display: block;
    animation: fadeIn 0.3s ease-in-out forwards;
}

.confirmation-modal.hide {
    animation: fadeOut 0.3s ease-in-out forwards;
}

.modal-headers {
    font-size: 18px;
    font-weight: bold;
    color: #d50000;
    margin-bottom: 15px;
}

.modal-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.modal-button {
    width: 45%;
    padding: 10px;
    font-size: 16px;
    font-weight: bold;
    border: none;
    cursor: pointer;
    border-radius: 5px;
}

.cancel-action {
    background: #aaa;
    color: white;
}

.cancel-action:hover {
    background: #888;
}

.confirm-action {
    background: #c9302c;
    color: white;
}

.confirm-action:hover {
    background: #691b19;
}

/* Clickable Table Row */
.clickable-row {
    cursor: pointer;
}

.clickable-row:hover {
    background-color: #f5f5f5;
}

    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4>Red Cross Staff</h4>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="dashboard-staff-main.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard-staff-donor-submission.php">Donor Interviews</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Physical Examinations</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Blood Collection</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Submit a Letter</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Logout</a></li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid p-4 custom-margin">
                    <h2 class="mt-1 mb-4">Pending Physical Examinations</h2>
                    
                    <!-- Search Bar -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="search-container text-center">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">
                                        <i class="fas fa-search fa-lg"></i>
                                    </span>
                                    <select class="form-select form-select-lg category-select" id="searchCategory" style="max-width: 200px;">
                                        <option value="all">All Fields</option>
                                        <option value="date">Date</option>
                                        <option value="surname">Surname</option>
                                        <option value="firstname">First Name</option>
                                        <option value="birthdate">Birthdate</option>
                                        <option value="sex">Sex</option>
                                    </select>
                                    <input type="text" 
                                        class="form-control form-control-lg" 
                                        id="searchInput" 
                                        placeholder="Search donors..."
                                        style="height: 60px; font-size: 1.2rem;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Surname</th>
                                    <th>Firstname</th>
                                    <th>Birthdate</th>
                                    <th>Sex</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donors && is_array($donors)): ?>
                                    <?php foreach($donors as $donor): ?>
                                        <tr data-bs-toggle="modal" 
                                            data-bs-target="#donorDetailsModal" 
                                            data-donor='<?php echo htmlspecialchars(json_encode($donor, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                                            <td><?php echo isset($donor['screening_completed_at']) ? htmlspecialchars($donor['screening_completed_at']) : ''; ?></td>
                                            <td><?php echo isset($donor['surname']) ? htmlspecialchars($donor['surname']) : ''; ?></td>
                                            <td><?php echo isset($donor['first_name']) ? htmlspecialchars($donor['first_name']) : ''; ?></td>
                                            <td><?php echo isset($donor['birthdate']) ? htmlspecialchars($donor['birthdate']) : ''; ?></td>
                                            <td><?php echo isset($donor['sex']) ? htmlspecialchars($donor['sex']) : ''; ?></td>
                                            <td><span class="badge bg-warning">Pending Physical</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Confirmation Modal -->
                <div class="confirmation-modal" id="confirmationDialog">
                    <div class="modal-headers">Do you want to continue?</div>
                    <div class="modal-actions">
                        <button class="modal-button cancel-action" id="cancelButton">No</button>
                        <button class="modal-button confirm-action" id="confirmButton">Yes</button>
                    </div>
                </div>    
                
                <!-- Loading Spinner -->
                <div class="loading-spinner" id="loadingSpinner"></div>
                
            </main>
        </div>
    </div>


    <script>
        let confirmationDialog = document.getElementById("confirmationDialog");
        let loadingSpinner = document.getElementById("loadingSpinner");
        let cancelButton = document.getElementById("cancelButton");
        let confirmButton = document.getElementById("confirmButton");

        // Attach click event to all rows
        document.querySelectorAll(".clickable-row").forEach(row => {
            row.addEventListener("click", function() {
                confirmationDialog.classList.remove("hide");
                confirmationDialog.classList.add("show");
                confirmationDialog.style.display = "block";
            });
        });

        // Close Modal Function
        function closeModal() {
            confirmationDialog.classList.remove("show");
            confirmationDialog.classList.add("hide");
            setTimeout(() => {
                confirmationDialog.style.display = "none";
            }, 300);
        }

        // Yes Button (Triggers Loading Spinner & Redirects)
        confirmButton.addEventListener("click", function() {
            closeModal();
            loadingSpinner.style.display = "block"; // Show loader
            setTimeout(() => {
                loadingSpinner.style.display = "none"; // Hide loader after 2 seconds
                window.location.href = "../../src/views/forms/physical-examination-form.html"; // Redirect after loading
            }, 2000);
        });

        // No Button (Closes Modal)
        cancelButton.addEventListener("click", function() {
            closeModal();
        });

    </script>
</body>
</html>