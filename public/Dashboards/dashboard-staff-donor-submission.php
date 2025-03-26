<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Initialize cURL session for Supabase
$ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?select=*');

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

// Add this function to handle donor approval
function storeDonorIdInSession($donorData) {
    $_SESSION['donor_id'] = $donorData['id'];
    $_SESSION['donor_name'] = $donorData['first_name'] . ' ' . $donorData['surname'];
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
        /* Modern Font */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

/* Rounded Corners for First and Last Rows */
.dashboard-staff-tables tbody tr:first-child td:first-child {
    border-top-left-radius: 10px;
}

.dashboard-staff-tables tbody tr:first-child td:last-child {
    border-top-right-radius: 10px;
}

.dashboard-staff-tables tbody tr:last-child td:first-child {
    border-bottom-left-radius: 10px;
}

.dashboard-staff-tables tbody tr:last-child td:last-child {
    border-bottom-right-radius: 10px;
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

/* Labels */
.donor_form_label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
    color: #333; /* Dark text for readability */
}

/* Input Fields */
.donor_form_input {
    width: 100%;
    padding: 8px;
    margin-bottom: 15px;
    border: 1px solid #ddd; /* Light border */
    border-radius: 5px;
    font-size: 14px;
    box-sizing: border-box;
    color: #555; /* Slightly lighter text for inputs */
    background-color: #f8f9fa; /* Light background for inputs */
    transition: border-color 0.3s ease;
}

.donor_form_input:focus {
    border-color: #007bff; /* Blue border on focus */
    outline: none;
}

/* Grid Layout */
.donor_form_grid {
    display: grid;
    gap: 10px; /* Increased gap for better spacing */
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

/* Read-Only and Disabled Inputs */
.donor_form_input[readonly], .donor_form_input[disabled] {
    background-color: #e9ecef; /* Light gray for read-only fields */
    cursor: not-allowed;
}

/* Select Dropdowns */
.donor_form_input[disabled] {
    color: #555; /* Ensure text is visible */
}

/* Hover Effects for Interactive Elements */
.donor_form_input:not([readonly]):not([disabled]):hover {
    border-color: #007bff; /* Blue border on hover */
}

/* Responsive Design */
@media (max-width: 768px) {
    .donor_form_header {
        grid-template-columns: 1fr; /* Stack header items on small screens */
        text-align: left;
    }

    .grid-3, .grid-4, .grid-6 {
        grid-template-columns: 1fr; /* Stack grid items on small screens */
    }
}
.modal-xxl {
    max-width: 1200px; /* Set your desired width */
    width: 100%; /* Ensure it's responsive */
}

/* Add these styles for read-only inputs */
.donor_form_input[readonly] {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    cursor: default;
    color: #495057;
}

.donor_form_input[readonly]:focus {
    outline: none;
    box-shadow: none;
    border-color: #dee2e6;
}

select.donor_form_input[disabled] {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    cursor: default;
    color: #495057;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <h4 >Red Cross Staff</h4>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="dashboard-staff-main.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Donor Interviews Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Physical Exams Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Blood Collection Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Submit a Letter</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Logout</a></li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Latest Donor Submissions -->
                <div class="container-fluid p-4 custom-margin">
                    <h2 class="mt-1 mb-4">Latest Donor Submissions</h2>
                    
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
                                </tr>
                            </thead>
                            <tbody id="donorTableBody">
                                <?php if($donors && is_array($donors)): ?>
                                    <?php foreach($donors as $donor): ?>
                                        <?php
                                        // Ensure we have the donor ID in the expected format
                                        $donorData = array_merge($donor, [
                                            'donor_id' => $donor['donor_id'] ?? null
                                        ]);
                                        ?>
                                        <tr data-bs-toggle="modal" 
                                            data-bs-target="#donorDetailsModal" 
                                            data-donor='<?php echo htmlspecialchars(json_encode($donorData, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>
                                            <td><?php echo isset($donor['submitted_at']) ? htmlspecialchars($donor['submitted_at']) : ''; ?></td>
                                            <td><?php echo isset($donor['surname']) ? htmlspecialchars($donor['surname']) : ''; ?></td>
                                            <td><?php echo isset($donor['first_name']) ? htmlspecialchars($donor['first_name']) : ''; ?></td>
                                            <td><?php echo isset($donor['birthdate']) ? htmlspecialchars($donor['birthdate']) : ''; ?></td>
                                            <td><?php echo isset($donor['sex']) ? htmlspecialchars($donor['sex']) : ''; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <!-- Donor Details Modal -->
<div class="modal fade" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xxl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="donorDetailsModalLabel">Donor Submission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Donor Form -->
                <form class="donor_form_container">
                    <div class="donor_form_header">
                        <div>
                            <label class="donor_form_label">PRC BLOOD DONOR NUMBER:</label>
                            <input type="text" class="donor_form_input" name="prc_donor_number" value="<?php echo isset($donor['prc_donor_number']) ? htmlspecialchars($donor['prc_donor_number']) : ''; ?>" readonly>
                        </div>
                        <h2>BLOOD DONOR INTERVIEW SHEET</h2>
                        <div>
                            <label class="donor_form_label">DOH NNBNets Barcode:</label>
                            <input type="text" class="donor_form_input" name="doh_nnbnets_barcode" value="<?php echo isset($donor['doh_nnbnets_barcode']) ? htmlspecialchars($donor['doh_nnbnets_barcode']) : ''; ?>" readonly>
                        </div>
                    </div>
                    <div class="donor_form_section">
                        <h6>NAME:</h6>
                        <div class="donor_form_grid grid-3">
                            <div>
                                <label class="donor_form_label">Surname</label>
                                <input type="text" class="donor_form_input" name="surname" value="<?php echo isset($donor['surname']) ? htmlspecialchars($donor['surname']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">First Name</label>
                                <input type="text" class="donor_form_input" name="first_name" value="<?php echo isset($donor['first_name']) ? htmlspecialchars($donor['first_name']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Middle Name</label>
                                <input type="text" class="donor_form_input" name="middle_name" value="<?php echo isset($donor['middle_name']) ? htmlspecialchars($donor['middle_name']) : ''; ?>" readonly>
                            </div>
                        </div>
                        <div class="donor_form_grid grid-4">
                            <div>
                                <label class="donor_form_label">Birthdate</label>
                                <input type="date" class="donor_form_input" name="birthdate" value="<?php echo isset($donor['birthdate']) ? htmlspecialchars($donor['birthdate']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Age</label>
                                <input type="number" class="donor_form_input" name="age" value="<?php echo isset($donor['age']) ? htmlspecialchars($donor['age']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Sex</label>
                                <select class="donor_form_input" name="sex" disabled>
                                    <option value="male" <?php echo (isset($donor['sex']) && strtolower($donor['sex']) == 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (isset($donor['sex']) && strtolower($donor['sex']) == 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div>
                                <label class="donor_form_label">Civil Status</label>
                                <select class="donor_form_input" name="civil_status" disabled>
                                    <option value="single" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="married" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="widowed" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="divorced" <?php echo (isset($donor['civil_status']) && strtolower($donor['civil_status']) == 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                                </select>
                            </div>
                        </div>
                    </div>
            
                    <div class="donor_form_section">
                        <h6>PERMANENT ADDRESS</h6>
                        <input type="text" class="donor_form_input" name="permanent_address" value="<?php echo isset($donor['permanent_address']) ? htmlspecialchars($donor['permanent_address']) : ''; ?>" readonly>
                        
                        <h6>OFFICE ADDRESS</h6>
                        <div class="donor_form_grid grid-1">
                            <input type="text" class="donor_form_input" name="office_address" value="<?php echo isset($donor['office_address']) ? htmlspecialchars($donor['office_address']) : ''; ?>" readonly>
                        </div>
                        <div class="donor_form_grid grid-4">
                            <div>
                                <label class="donor_form_label">Nationality</label>
                                <input type="text" class="donor_form_input" name="nationality" value="<?php echo isset($donor['nationality']) ? htmlspecialchars($donor['nationality']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Religion</label>
                                <input type="text" class="donor_form_input" name="religion" value="<?php echo isset($donor['religion']) ? htmlspecialchars($donor['religion']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Education</label>
                                <input type="text" class="donor_form_input" name="education" value="<?php echo isset($donor['education']) ? htmlspecialchars($donor['education']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Occupation</label>
                                <input type="text" class="donor_form_input" name="occupation" value="<?php echo isset($donor['occupation']) ? htmlspecialchars($donor['occupation']) : ''; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="donor_form_section">
                        <h6>CONTACT No.:</h6>
                        <div class="donor_form_grid grid-3">
                            <div>
                                <label class="donor_form_label">Telephone No.</label>
                                <input type="text" class="donor_form_input" name="telephone" value="<?php echo isset($donor['telephone']) ? htmlspecialchars($donor['telephone']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Mobile No.</label>
                                <input type="text" class="donor_form_input" name="mobile" value="<?php echo isset($donor['mobile']) ? htmlspecialchars($donor['mobile']) : ''; ?>" readonly>
                            </div>
                            <div>
                                <label class="donor_form_label">Email Address</label>
                                <input type="email" class="donor_form_input" name="email" value="<?php echo isset($donor['email']) ? htmlspecialchars($donor['email']) : ''; ?>" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="donor_form_section">
                        <h6>IDENTIFICATION No.:</h6>
                        <div class="donor_form_grid grid-6">
                        <div>
                            <label class="donor_form_label">School</label>
                                <input type="text" class="donor_form_input" name="id_school" value="<?php echo isset($donor['id_school']) ? htmlspecialchars($donor['id_school']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">Company</label>
                                <input type="text" class="donor_form_input" name="id_company" value="<?php echo isset($donor['id_company']) ? htmlspecialchars($donor['id_company']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">PRC</label>
                                <input type="text" class="donor_form_input" name="id_prc" value="<?php echo isset($donor['id_prc']) ? htmlspecialchars($donor['id_prc']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">Driver's</label>
                                <input type="text" class="donor_form_input" name="id_drivers" value="<?php echo isset($donor['id_drivers']) ? htmlspecialchars($donor['id_drivers']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">SSS/GSIS/BIR</label>
                                <input type="text" class="donor_form_input" name="id_sss_gsis_bir" value="<?php echo isset($donor['id_sss_gsis_bir']) ? htmlspecialchars($donor['id_sss_gsis_bir']) : ''; ?>" readonly>
                        </div>
                        <div>
                            <label class="donor_form_label">Others</label>
                                <input type="text" class="donor_form_input" name="id_others" value="<?php echo isset($donor['id_others']) ? htmlspecialchars($donor['id_others']) : ''; ?>" readonly>
                            </div>
                    </div>
                    </div>
                </form>

                <!-- Donor Declaration -->
                <div class="donor-declaration">
                    <!-- Donor's Signature Image -->
                    <div class="donor-declaration-row">
                        <div><strong>Donor's Signature:</strong></div>
                        <?php if(isset($donor['donor_signature']) && !empty($donor['donor_signature'])): ?>
                            <img src="../../src/views/forms/uploads/<?php echo htmlspecialchars($donor['donor_signature']); ?>" 
                                alt="Donor's Signature" class="donor-declaration-img" style="max-width: 200px; height: auto;">
                        <?php else: ?>
                            <p>No donor signature available</p>
                        <?php endif; ?>
                    </div>

                    <?php if(isset($donor['guardian_signature']) && !empty($donor['guardian_signature'])): ?>
                    <!-- Parent/Guardian Section -->
                    <div class="donor-declaration-row">
                        <div><strong>Signature of Parent/Guardian:</strong></div>
                        <img src="../../src/views/forms/uploads/<?php echo htmlspecialchars($donor['guardian_signature']); ?>" 
                            alt="Parent/Guardian Signature" class="donor-declaration-img" style="max-width: 200px; height: auto;">
                        <?php if(isset($donor['relationship']) && !empty($donor['relationship'])): ?>
                        <div class="relationship-container">
                            <strong>Relationship to Blood Donor: </strong>
                            <input class="donor-declaration-input" type="text" 
                                value="<?php echo htmlspecialchars($donor['relationship']); ?>" 
                                readonly>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary px-4 py-2 fw-bold" id="Approve">Approve</button>
            </div>
        </div>
    </div>
</div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const donorDetailsModal = document.getElementById('donorDetailsModal');
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const donorTableBody = document.getElementById('donorTableBody');
            
            // Store the original table rows for reset
            const originalRows = Array.from(donorTableBody.getElementsByTagName('tr'));
            
            // Update placeholder based on selected category
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'date': placeholder += 'date...'; break;
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'birthdate': placeholder += 'birthdate...'; break;
                    case 'sex': placeholder += 'sex (male/female)...'; break;
                    default: placeholder = 'Search donors...';
                }
                searchInput.placeholder = placeholder;
                performSearch();
            });

            // Enhanced search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const category = searchCategory.value;
                
                // If search is empty, show all rows
                if (!searchTerm) {
                    originalRows.forEach(row => row.style.display = '');
                    removeNoResultsMessage();
                    return;
                }

                let visibleCount = 0;

                originalRows.forEach(row => {
                    const cells = Array.from(row.getElementsByTagName('td'));
                    let shouldShow = false;

                    if (category === 'all') {
                        // Search in all columns
                        shouldShow = cells.some(cell => 
                            cell.textContent.toLowerCase().trim().includes(searchTerm)
                        );
                    } else {
                        // Get column index based on category
                        const columnIndex = {
                            'date': 0,
                            'surname': 1,
                            'firstname': 2,
                            'birthdate': 3,
                            'sex': 4
                        }[category];

                        if (columnIndex !== undefined) {
                            const cellText = cells[columnIndex].textContent.toLowerCase().trim();
                            
                            // Special handling for different column types
                            switch(category) {
                                case 'surname':
                                case 'firstname':
                                    shouldShow = cellText.startsWith(searchTerm);
                                    break;
                                case 'sex':
                                    shouldShow = cellText === searchTerm;
                                    break;
                                default:
                                    shouldShow = cellText.includes(searchTerm);
                            }
                        }
                    }

                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });

                // Handle no results message
                if (visibleCount === 0) {
                    showNoResultsMessage(searchTerm, category);
                } else {
                    removeNoResultsMessage();
                }
            }

            function showNoResultsMessage(searchTerm, category) {
                removeNoResultsMessage();
                const messageRow = document.createElement('tr');
                messageRow.className = 'no-results';
                const categoryText = category === 'all' ? '' : ` in ${category}`;
                messageRow.innerHTML = `<td colspan="5" class="text-center py-3">
                    No donors found matching "${searchTerm}"${categoryText}
                </td>`;
                donorTableBody.appendChild(messageRow);
            }

            function removeNoResultsMessage() {
                const noResultsRow = donorTableBody.querySelector('.no-results');
                if (noResultsRow) noResultsRow.remove();
            }

            // Debounce function to improve performance
            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

            // Apply debounced search
            const debouncedSearch = debounce(performSearch, 300);

            // Event listeners
            searchInput.addEventListener('input', debouncedSearch);
            searchCategory.addEventListener('change', debouncedSearch);

            // Handle row click and populate modal
            document.querySelectorAll('.table-hover tbody tr').forEach(row => {
                row.addEventListener('click', function() {
                    const donorData = JSON.parse(this.getAttribute('data-donor'));
                    
                    // Update signatures
                    const donorDeclaration = donorDetailsModal.querySelector('.donor-declaration');
                    let html = '';
                    
                    // Donor Signature Section
                    html += `
                        <div class="donor-declaration-row">
                            <div><strong>Donor's Signature:</strong></div>
                            ${donorData.donor_signature ? 
                                `<img src="../../src/views/forms/uploads/${donorData.donor_signature.replace('uploads/', '')}" 
                                    alt="Donor's Signature" class="donor-declaration-img" style="max-width: 200px; height: auto;">` :
                                `<p>No donor signature available</p>`
                            }
                        </div>
                    `;

                    // Parent/Guardian Section - only show if guardian signature exists
                    if (donorData.guardian_signature) {
                        const guardianSignaturePath = donorData.guardian_signature.replace('uploads/', '');
                        html += `
                            <div class="donor-declaration-row">
                                <div><strong>Signature of Parent/Guardian:</strong></div>
                                <img src="../../src/views/forms/uploads/${guardianSignaturePath}" 
                                    alt="Parent/Guardian Signature" class="donor-declaration-img" style="max-width: 200px; height: auto;">
                                ${donorData.relationship ? 
                                    `<div class="relationship-container">
                                        <strong>Relationship to Blood Donor: </strong>
                                        <input class="donor-declaration-input" type="text" 
                                            value="${donorData.relationship}" readonly>
                                    </div>` : ''
                                }
                            </div>
                        `;
                    }

                    donorDeclaration.innerHTML = html;
                    
                    // Populate all form fields with donor data
                    donorDetailsModal.querySelector('input[name="prc_donor_number"]').value = donorData.prc_donor_number || '';
                    donorDetailsModal.querySelector('input[name="doh_nnbnets_barcode"]').value = donorData.doh_nnbnets_barcode || '';
                    donorDetailsModal.querySelector('input[name="surname"]').value = donorData.surname || '';
                    donorDetailsModal.querySelector('input[name="first_name"]').value = donorData.first_name || '';
                    donorDetailsModal.querySelector('input[name="middle_name"]').value = donorData.middle_name || '';
                    donorDetailsModal.querySelector('input[name="birthdate"]').value = donorData.birthdate || '';
                    donorDetailsModal.querySelector('input[name="age"]').value = donorData.age || '';
                    donorDetailsModal.querySelector('select[name="sex"]').value = donorData.sex?.toLowerCase() || '';
                    donorDetailsModal.querySelector('select[name="civil_status"]').value = donorData.civil_status?.toLowerCase() || '';
                    donorDetailsModal.querySelector('input[name="permanent_address"]').value = donorData.permanent_address || '';
                    donorDetailsModal.querySelector('input[name="office_address"]').value = donorData.office_address || '';
                    donorDetailsModal.querySelector('input[name="nationality"]').value = donorData.nationality || '';
                    donorDetailsModal.querySelector('input[name="religion"]').value = donorData.religion || '';
                    donorDetailsModal.querySelector('input[name="education"]').value = donorData.education || '';
                    donorDetailsModal.querySelector('input[name="occupation"]').value = donorData.occupation || '';
                    donorDetailsModal.querySelector('input[name="telephone"]').value = donorData.telephone || '';
                    donorDetailsModal.querySelector('input[name="mobile"]').value = donorData.mobile || '';
                    donorDetailsModal.querySelector('input[name="email"]').value = donorData.email || '';
                    donorDetailsModal.querySelector('input[name="id_school"]').value = donorData.id_school || '';
                    donorDetailsModal.querySelector('input[name="id_company"]').value = donorData.id_company || '';
                    donorDetailsModal.querySelector('input[name="id_prc"]').value = donorData.id_prc || '';
                    donorDetailsModal.querySelector('input[name="id_drivers"]').value = donorData.id_drivers || '';
                    donorDetailsModal.querySelector('input[name="id_sss_gsis_bir"]').value = donorData.id_sss_gsis_bir || '';
                    donorDetailsModal.querySelector('input[name="id_others"]').value = donorData.id_others || '';
                });
            });
        });

        function toggleMode() {
            document.body.classList.toggle("light-mode");
        }

        document.addEventListener('DOMContentLoaded', function() {
            let currentDonorData = null;

            // Function to handle approve button click
            function handleApprove(donorData) {
                console.log('Approving donor:', donorData);

                if (!donorData) {
                    console.error('No donor data available');
                    alert('Error: Could not process approval - missing donor data');
                    return;
                }

                // Get the donor_id from the data
                const donorId = donorData.donor_id;
                if (!donorId) {
                    console.error('No donor_id found in data:', donorData);
                    alert('Error: Could not process approval - missing donor ID');
                    return;
                }

                // First store the donor ID in session
                fetch('store_donor_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        donor_id: donorId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = '../../src/views/forms/medical-history.php';
                    } else {
                        console.error('Server response:', data);
                        alert('Error: Could not process approval');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: Could not process approval');
                });
            }

            // Table row click handler to store current donor data
            document.querySelectorAll('.table-hover tbody tr').forEach(row => {
                row.addEventListener('click', function() {
                    try {
                        const donorDataStr = this.getAttribute('data-donor');
                        console.log('Raw donor data:', donorDataStr);
                        currentDonorData = JSON.parse(donorDataStr);
                        console.log('Parsed donor data:', currentDonorData);
                    } catch (error) {
                        console.error('Error parsing donor data:', error);
                    }
                });
            });

            // Add click event listener to the modal's approve button
            document.getElementById('Approve').addEventListener('click', function() {
                console.log('Current donor data on approve:', currentDonorData);
                if (currentDonorData) {
                    handleApprove(currentDonorData);
                } else {
                    alert('Error: No donor selected');
                }
            });
        });
    </script>

    <!-- Add Font Awesome for search icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        /* Updated styles for the search bar */
        .search-container {
            width: 100%;
            margin: 0 auto;
        }

        .input-group {
            width: 100%;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .input-group-text {
            background-color: #fff;
            border: 2px solid #ced4da;
            border-right: none;
            padding: 0.75rem 1.5rem;
        }

        .category-select {
            border: 2px solid #ced4da;
            border-right: none;
            border-left: none;
            background-color: #f8f9fa;
            cursor: pointer;
            min-width: 150px;
        }

        .category-select:focus {
            box-shadow: none;
            border-color: #ced4da;
        }

        #searchInput {
            border: 2px solid #ced4da;
            border-left: none;
            padding: 1.5rem;
            font-size: 1.2rem;
            flex: 1;
        }

        #searchInput::placeholder {
            color: #adb5bd;
            font-size: 1.1rem;
        }

        #searchInput:focus {
            box-shadow: none;
            border-color: #ced4da;
        }

        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(0,123,255,.25);
        }

        .input-group-text i {
            font-size: 1.5rem;
            color: #6c757d;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            background-color: #f8f9fa;
            border-radius: 8px;
            width: 100%;
        }
    </style>
</body>
</html>