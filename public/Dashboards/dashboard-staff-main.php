<?php
session_start(); // Start the session
require_once '../../assets/conn/db_conn.php';
require '../../assets/php_func/user_roles_staff.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .welcome-section {
            background: linear-gradient(to right, #ffffff, #f8f9fa);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .stats-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .card {
            transition: transform 0.2s ease-in-out;
            border-radius: 15px;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .text-primary {
            color: #dc3545 !important;
        }

        .bg-primary {
            background-color: #dc3545 !important;
        }
    </style>
</head>
<body class="light-mode">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4 class="text-dark">Red Cross Staff</h4> 
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard-staff-main.php">Dashboard</a>
                    </li>
                    
                    <?php if ($user_staff_roles === 'interviewer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-donor-submission.php">
                                Donor Interviews Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'physician'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-physical-submission.php">
                                Physical Exams Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($user_staff_roles === 'phlebotomist'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard-staff-blood-collection-submission.php">
                                Blood Collection Submissions
                            </a>
                        </li>
                    <?php endif; ?>
                    

                    <li class="nav-item">
                        <a class="nav-link" href="../../assets/php_func/logout.php">Logout</a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Welcome Section -->
                <div class="welcome-section mb-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h1 class="display-4 mb-0">Welcome, 
                                <span class="text-primary">
                                    <?php
                                    if ($user_staff_roles === 'interviewer') {
                                        echo 'Blood Bank Interviewer';
                                    } elseif ($user_staff_roles === 'physician') {
                                        echo 'Blood Bank Physician';
                                    } elseif ($user_staff_roles === 'phlebotomist') {
                                        echo 'Blood Bank Phlebotomist';
                                    }
                                    ?>
                                </span>
                            </h1>
                            <p class="text-muted lead">
                                <?php
                                if ($user_staff_roles === 'interviewer') {
                                    echo 'Manage donor interviews and ensure smooth screening process';
                                } elseif ($user_staff_roles === 'physician') {
                                    echo 'Oversee medical examinations and donor eligibility';
                                } elseif ($user_staff_roles === 'phlebotomist') {
                                    echo 'Handle blood collection procedures with care';
                                }
                                ?>
                            </p>
                        </div>
                        <div class="welcome-icon">
                            <?php
                            if ($user_staff_roles === 'interviewer') {
                                echo '<i class="fas fa-comments fa-3x text-primary"></i>';
                            } elseif ($user_staff_roles === 'physician') {
                                echo '<i class="fas fa-user-md fa-3x text-primary"></i>';
                            } elseif ($user_staff_roles === 'phlebotomist') {
                                echo '<i class="fas fa-syringe fa-3x text-primary"></i>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Quick Stats Card -->
                    <div class="col-12 mb-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <?php if ($user_staff_roles === 'interviewer'): ?>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="stats-icon bg-primary bg-opacity-10 rounded-circle p-3">
                                                        <i class="fas fa-clipboard-list text-primary"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h3 class="mb-1">5</h3>
                                                    <p class="text-muted mb-0">Pending Interviews</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($user_staff_roles === 'physician'): ?>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="stats-icon bg-primary bg-opacity-10 rounded-circle p-3">
                                                        <i class="fas fa-stethoscope text-primary"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h3 class="mb-1">3</h3>
                                                    <p class="text-muted mb-0">Pending Examinations</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($user_staff_roles === 'phlebotomist'): ?>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="stats-icon bg-primary bg-opacity-10 rounded-circle p-3">
                                                        <i class="fas fa-vial text-primary"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h3 class="mb-1">7</h3>
                                                    <p class="text-muted mb-0">Pending Collections</p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts Section with new styling -->
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-danger border-0 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Urgent:</strong>&nbsp;Donor needs immediate attention.
                            </div>
                        </div>
                        <div class="alert alert-warning border-0 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Reminder:</strong>&nbsp;Pending blood test results.
                            </div>
                        </div>
                        <div class="alert alert-info border-0 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>New:</strong>&nbsp;Donor registered for screening.
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    

    <script>
    </script>
</body>
</html>