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

    </style>
</head>
<body class="light-mode"> <!-- Add light-mode class by default -->
    <div class="container-fluid">


        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4 class="text-dark">Red Cross Staff</h4> 
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="#">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Donor Interviews Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Physical Exams Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Blood Collection Submissions</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Submit a Letter</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Logout</a></li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">

                        <h1 class="fw-bold text-dark mb-4">Submit a Request</h1>
                        
                        <form id="requestForm" action="submit_request.php" method="POST" enctype="multipart/form-data">
                            
                            <div class="mb-3">
                                <label for="request_type" class="form-label fw-semibold">Request Type</label>
                                <select class="form-select border-2 p-2" id="request_type" name="request_type" required>
                                    <option value="Donor Info">Donor Submissions</option>
                                    <option value="Exam Results">Physical Exam Results</option>
                                    <option value="Blood Collection">Blood Collection</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="justification" class="form-label fw-semibold">Justification</label>
                                <textarea class="form-control border-2 p-3" id="justification" name="justification" rows="8" required placeholder="Provide a detailed reason..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="upload_photo" class="form-label fw-semibold">Upload Photo for Justification</label>
                                <input class="form-control border-2" type="file" id="upload_photo" name="upload_photo" accept="image/*" required>
                            </div>
                            
                            <input type="hidden" name="staff_id" value="">
                            
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">Submit Request</button>

                        </form>
                    </main>
                
                
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
    </script>
</body>
</html>