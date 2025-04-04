<?php
session_start(); // Start the session


if (!isset($_SESSION['user_id'])) {
    header("Location: /REDCROSS/public/login.php");
    exit(); // Important to stop script execution
}
// Check for correct role (example for admin dashboard)
$required_role = 3; // Staff Role
if ($_SESSION['role_id'] !== $required_role) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Staff Dashboard</title>
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
            position: fixed;
            top: 0;
            left: 0;
            width: 16.66666667%;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar h4 {
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #dee2e6;
            color: #000;
            font-weight: bold;
        }

        .sidebar .nav-link {
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: all 0.3s ease;
            color: #000 !important;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--hover-bg);
            transform: translateX(5px);
            color: #000 !important;
        }

        .main-content {
            padding: 1.5rem;
            margin-left: 16.66666667%;
        }

        .card {
            background: var(--card-bg);
            color: var(--text-color);
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: scale(1.03);
        }

        .alert {
            font-size: 1.1rem;
        }

        .btn-toggle {
            position: absolute;
            top: 10px;
            right: 20px;
        }

        .table-hover tbody tr {
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .table-hover tbody tr:hover {
            background-color: var(--hover-bg); /* Light gray hover for rows */
        }

        /* Additional styles for light mode */
        .table-dark {
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .table-dark thead th {
            background-color: var(--sidebar-bg);
            color: var(--text-color);
        }

        .table-dark tbody tr:hover {
            background-color: var(--hover-bg);
        }

        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .modal-header, .modal-footer {
            border-color: var(--hover-bg);
        }

        .donor_form_input[readonly], .donor_form_input[disabled] {
            background-color: var(--bg-color);
            cursor: not-allowed;
            border: 1px solid #ddd;
        }

        .donor-declaration-button[disabled] {
            background-color: var(--hover-bg);
            cursor: not-allowed;
        }

        /* Responsive Design */
        @media (max-width: 991.98px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="light-mode"> <!-- Add light-mode class by default -->
    <button class="btn btn-secondary btn-toggle" onclick="toggleMode()">Toggle Mode</button>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4 class="text-dark">Red Cross Staff</h4> 
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link active" href="dashboard-staff-main.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard-staff-donor-submission.php">Donor Interviews Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard-staff-physical-submission.php">Physical Exams Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard-staff-blood-collection-submission.php">Blood Collection Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard-staff-submit-letter.php">Submit a Letter</a></li>
                    <li class="nav-item"><a class="nav-link" href="../../assets/php_func/logout.php">Logout</a></li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <h2>Dashboard</h2>
                
                <!-- Alerts Section -->
                <div class="alert alert-danger">⚠️ Urgent: Donor needs immediate attention.</div>
                <div class="alert alert-warning">⏳ Reminder: Pending blood test results.</div>
                <div class="alert alert-info">🆕 New donor registered for screening.</div>
                
                <!-- Dashboard Panels -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card p-3">
                            <h5>Interviewer</h5>
                            <p>Pending Interviews: <strong>5</strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3">
                            <h5>Blood Bank Physician</h5>
                            <p>Pending Exams: <strong>3</strong></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card p-3">
                            <h5>Phlebotomist</h5>
                            <p>Pending Collections: <strong>7</strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- Latest Donor Submissions -->
            </main>
        </div>
    </div>

    

    <script>
    </script>
</body>
</html>