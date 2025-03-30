<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Function to execute a query
function executeQuery($query, $params = []) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Prefer: return=representation'
    ];
    
    $url = SUPABASE_URL . '/rest/v1/rpc/' . $query;
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if (!empty($params)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    }
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    return json_decode($response, true);
}

// Function to query direct SQL (for non-RPC queries)
function querySQL($table, $select = "*", $filters = null) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Prefer: return=representation'
    ];
    
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=' . urlencode($select);
    
    if ($filters) {
        foreach ($filters as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error];
    }
    
    return json_decode($response, true);
}

// Fetch blood inventory data from eligibility table
$bloodInventory = [];

// Query eligibility table for valid blood units
$eligibilityData = querySQL(
    'eligibility', 
    'eligibility_id,donor_id,blood_type,donation_type,blood_bag_type,collection_successful,unit_serial_number,collection_start_time,start_date,end_date,status',
    ['collection_successful' => 'eq.true']
);

if (is_array($eligibilityData) && !empty($eligibilityData)) {
    foreach ($eligibilityData as $item) {
        // Skip if no serial number
        if (empty($item['unit_serial_number'])) {
            continue;
        }
        
        // Get donor information based on donor_id
        $donorData = querySQL('donor_form', '*', ['donor_id' => 'eq.' . $item['donor_id']]);
        $donor = isset($donorData[0]) ? $donorData[0] : null;
        
        // Calculate expiration date (42 days from collection)
        $collectionDate = new DateTime($item['collection_start_time']);
        $expirationDate = clone $collectionDate;
        $expirationDate->modify('+42 days');
        
        // Create blood bag entry
        $bloodBag = [
            'eligibility_id' => $item['eligibility_id'],
            'donor_id' => $item['donor_id'],
            'serial_number' => $item['unit_serial_number'],
            'blood_type' => $item['blood_type'],
            'bags' => '1 Bag',
            'bag_type' => $item['blood_bag_type'] ?: 'Standard',
            'collection_date' => $collectionDate->format('Y-m-d'),
            'expiration_date' => $expirationDate->format('Y-m-d'),
            'status' => (new DateTime() > $expirationDate) ? 'Expired' : 'Valid',
            'eligibility_status' => $item['status'],
            'eligibility_end_date' => $item['end_date'],
        ];
        
        // Add donor information if available
        if ($donor) {
            // Calculate age based on birthdate
            $age = '';
            if (!empty($donor['birthdate'])) {
                $birthdate = new DateTime($donor['birthdate']);
                $today = new DateTime();
                $age = $birthdate->diff($today)->y;
            }
            
            $bloodBag['donor'] = [
                'surname' => $donor['surname'] ?? '',
                'first_name' => $donor['first_name'] ?? '',
                'middle_name' => $donor['middle_name'] ?? '',
                'birthdate' => !empty($donor['birthdate']) ? date('d/m/Y', strtotime($donor['birthdate'])) : '',
                'age' => $age,
                'sex' => $donor['sex'] ?? '',
                'civil_status' => $donor['civil_status'] ?? ''
            ];
        } else {
            // Default empty donor info if not found
            $bloodBag['donor'] = [
                'surname' => 'Not Found',
                'first_name' => '',
                'middle_name' => '',
                'birthdate' => '',
                'age' => '',
                'sex' => '',
                'civil_status' => ''
            ];
        }
        
        $bloodInventory[] = $bloodBag;
    }
}

// If no records found, use sample data for testing
if (empty($bloodInventory)) {
    $bloodInventory = [
        [
            'serial_number' => 'BC-20250330-0001',
            'blood_type' => 'AB+',
            'bags' => '1 Bag',
            'bag_type' => 'AMI',
            'collection_date' => '2025-03-30',
            'expiration_date' => '2025-05-04',
            'status' => 'Valid',
            'donor' => [
                'surname' => 'Seeker',
                'first_name' => 'Light',
                'middle_name' => 'Devoid',
                'birthdate' => '25/03/2000',
                'age' => '25',
                'sex' => 'Male',
                'civil_status' => 'Single'
            ]
        ],
        [
            'serial_number' => 'BC-20250330-0002',
            'blood_type' => 'O+',
            'bags' => '1 Bag',
            'bag_type' => 'S',
            'collection_date' => '2025-03-30',
            'expiration_date' => '2025-05-04',
            'status' => 'Valid',
            'donor' => [
                'surname' => 'Seeker',
                'first_name' => 'Light',
                'middle_name' => 'Devoid',
                'birthdate' => '25/03/2000',
                'age' => '25',
                'sex' => 'Male',
                'civil_status' => 'Single'
            ]
        ]
    ];
}

// Calculate inventory statistics
$totalBags = count($bloodInventory);
$availableTypes = [];
$expiringBags = 0;
$expiredBags = 0;

$today = new DateTime();
$expiryLimit = (new DateTime())->modify('+7 days');

foreach ($bloodInventory as $bag) {
    // Track unique blood types
    if (!in_array($bag['blood_type'], $availableTypes)) {
        $availableTypes[] = $bag['blood_type'];
    }
    
    // Calculate expiration status
    $expirationDate = new DateTime($bag['expiration_date']);
    if ($today > $expirationDate) {
        $expiredBags++;
    } elseif ($expirationDate <= $expiryLimit) {
        $expiringBags++;
    }
}

// Get default sorting
$sortBy = "Default (Latest)";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Inventory</title>
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
    margin: 30px auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
    width: 100%;
    margin-top: 100px;
}

/* Inventory Dashboard Cards */
.inventory-card {
    border-radius: 8px;
    color: white;
    padding: 20px;
    margin-bottom: 20px;
    text-align: center;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.inventory-card h2 {
    font-size: 64px;
    font-weight: bold;
    margin: 10px 0;
}

.inventory-card-blue {
    background-color: #0d6efd;
}

.inventory-card-green {
    background-color: #198754;
}

.inventory-card-yellow {
    background-color: #ffc107;
}

.inventory-card-red {
    background-color: #dc3545;
}

.inventory-card-subtitle {
    font-size: 14px;
    margin-top: 5px;
}

.inventory-card-title {
    font-size: 18px;
    margin-bottom: 10px;
}

/* Action buttons */
.action-btn {
    font-size: 1.2rem;
    color: white;
    background-color: #0d6efd;
    border: none;
    border-radius: 4px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.2s;
}

.action-btn:hover {
    background-color: #0b5ed7;
}

/* Modal styling */
.donor-details-modal .modal-header {
    background-color: #212529;
    color: white;
}

.donor-details-modal .close-btn {
    color: white;
    background: none;
    border: none;
    font-size: 1.5rem;
}

.donor-details-section {
    margin-bottom: 20px;
}

.donor-details-section h3 {
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 5px;
}

.donor-details-form-group {
    margin-bottom: 15px;
}

.donor-details-form-label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
}

.donor-details-form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: #f8f9fa;
}

.eligibility-status {
    display: inline-block;
    color: white;
    background-color: #dc3545;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: bold;
    margin-right: 10px;
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
                    <h2 class="mb-4">Blood Bank Inventory</h2>
                    
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <!-- Total Blood Bags -->
                        <div class="col-md-3">
                            <div class="inventory-card inventory-card-blue">
                                <div class="inventory-card-title">Total Blood Bags</div>
                                <h2><?php echo $totalBags; ?></h2>
                            </div>
                        </div>
                        
                        <!-- Available Types -->
                        <div class="col-md-3">
                            <div class="inventory-card inventory-card-green">
                                <div class="inventory-card-title">Available Types</div>
                                <h2><?php echo count($availableTypes); ?></h2>
                                <div class="inventory-card-subtitle">
                                    <?php echo implode(', ', $availableTypes); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bags Expiring Soon -->
                        <div class="col-md-3">
                            <div class="inventory-card inventory-card-yellow">
                                <div class="inventory-card-title">Bags Expiring Soon</div>
                                <h2><?php echo $expiringBags; ?></h2>
                                <div class="inventory-card-subtitle">Within 7 days</div>
                            </div>
                        </div>
                        
                        <!-- Expired Bags -->
                        <div class="col-md-3">
                            <div class="inventory-card inventory-card-red">
                                <div class="inventory-card-title">Expired Bags</div>
                                <h2><?php echo $expiredBags; ?></h2>
                                <div class="inventory-card-subtitle">Needs disposal</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sort Options -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="me-2">Sort By:</span>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Default (Latest)
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                                <li><a class="dropdown-item" href="#">Default (Latest)</a></li>
                                <li><a class="dropdown-item" href="#">Blood Type (A-Z)</a></li>
                                <li><a class="dropdown-item" href="#">Expiry Date (Soonest First)</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Blood Bank Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Serial Number</th>
                                    <th>Blood Type</th>
                                    <th>Bags</th>
                                    <th>Bag Type</th>
                                    <th>Collection Date</th>
                                    <th>Expiration Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bloodInventory as $index => $bag): ?>
                                <tr>
                                    <td><?php echo $bag['serial_number']; ?></td>
                                    <td><?php echo $bag['blood_type']; ?></td>
                                    <td><?php echo $bag['bags']; ?></td>
                                    <td><?php echo $bag['bag_type']; ?></td>
                                    <td><?php echo $bag['collection_date']; ?></td>
                                    <td><?php echo $bag['expiration_date']; ?></td>
                                    <td><?php echo $bag['status']; ?></td>
                                    <td>
                                        <button class="action-btn" onclick="showDonorDetails(<?php echo $index; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Donor Details Modal -->
    <div class="modal fade donor-details-modal" id="donorDetailsModal" tabindex="-1" aria-labelledby="donorDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="donorDetailsModalLabel">
                        <i class="fas fa-eye me-2"></i> View Blood Unit & Donor Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Blood Unit Information -->
                    <div class="donor-details-section">
                        <h3>Blood Unit Information</h3>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Serial Number</label>
                                    <input type="text" class="donor-details-form-control" id="modal-serial-number" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Blood Type</label>
                                    <input type="text" class="donor-details-form-control" id="modal-blood-type" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Bags</label>
                                    <input type="text" class="donor-details-form-control" id="modal-bags" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Collection Date</label>
                                    <input type="text" class="donor-details-form-control" id="modal-collection-date" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Expiration Date</label>
                                    <input type="text" class="donor-details-form-control" id="modal-expiration-date" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Status</label>
                                    <input type="text" class="donor-details-form-control" id="modal-status" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Donor Information -->
                    <div class="donor-details-section">
                        <h3>Donor Information</h3>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Surname</label>
                                    <input type="text" class="donor-details-form-control" id="modal-surname" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">First Name</label>
                                    <input type="text" class="donor-details-form-control" id="modal-first-name" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Middle Name</label>
                                    <input type="text" class="donor-details-form-control" id="modal-middle-name" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Birthdate</label>
                                    <input type="text" class="donor-details-form-control" id="modal-birthdate" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Age</label>
                                    <input type="text" class="donor-details-form-control" id="modal-age" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Sex</label>
                                    <input type="text" class="donor-details-form-control" id="modal-sex" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="donor-details-form-group">
                                    <label class="donor-details-form-label">Civil Status</label>
                                    <input type="text" class="donor-details-form-control" id="modal-civil-status" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Donor Eligibility Status -->
                    <div class="donor-details-section">
                        <h3>Donor Eligibility Status</h3>
                        <div>
                            <span class="eligibility-status" id="modal-eligibility-status">INELIGIBLE</span>
                            <span id="modal-eligibility-message">Unknown status</span>
                        </div>
                        <div class="mt-2 text-muted" id="modal-eligibility-note">
                            Donor can donate blood now
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sample data for demonstration
        const bloodInventory = <?php echo json_encode($bloodInventory); ?>;
        
        // Function to display donor details in modal
        function showDonorDetails(index) {
            const bag = bloodInventory[index];
            const donor = bag.donor;
            
            // Blood Unit Information
            document.getElementById('modal-serial-number').value = bag.serial_number;
            document.getElementById('modal-blood-type').value = bag.blood_type;
            document.getElementById('modal-bags').value = bag.bags;
            document.getElementById('modal-collection-date').value = bag.collection_date;
            document.getElementById('modal-expiration-date').value = bag.expiration_date;
            document.getElementById('modal-status').value = bag.status;
            
            // Donor Information
            document.getElementById('modal-surname').value = donor.surname;
            document.getElementById('modal-first-name').value = donor.first_name;
            document.getElementById('modal-middle-name').value = donor.middle_name;
            document.getElementById('modal-birthdate').value = donor.birthdate;
            document.getElementById('modal-age').value = donor.age;
            document.getElementById('modal-sex').value = donor.sex;
            document.getElementById('modal-civil-status').value = donor.civil_status;
            
            // Check eligibility status via AJAX
            fetchEligibilityStatus(bag.donor_id);
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('donorDetailsModal'));
            modal.show();
        }
        
        // Function to fetch eligibility status for a donor
        function fetchEligibilityStatus(donorId) {
            if (!donorId) {
                updateEligibilityStatus({
                    is_eligible: false,
                    status_message: 'Unknown status',
                    remaining_days: 0
                });
                return;
            }
            
            // AJAX call to get eligibility status
            fetch(`get_donor_eligibility.php?donor_id=${donorId}`)
                .then(response => response.json())
                .then(data => {
                    updateEligibilityStatus(data);
                })
                .catch(error => {
                    console.error('Error fetching eligibility:', error);
                    updateEligibilityStatus({
                        is_eligible: false,
                        status_message: 'Error retrieving status',
                        remaining_days: 0
                    });
                });
        }
        
        // Update the eligibility status in the modal
        function updateEligibilityStatus(data) {
            const statusElem = document.getElementById('modal-eligibility-status');
            const messageElem = document.getElementById('modal-eligibility-message');
            const noteElem = document.getElementById('modal-eligibility-note');
            
            if (data.is_eligible) {
                statusElem.textContent = 'ELIGIBLE';
                statusElem.style.backgroundColor = '#198754'; // green
            } else {
                statusElem.textContent = 'INELIGIBLE';
                statusElem.style.backgroundColor = '#dc3545'; // red
            }
            
            messageElem.textContent = data.status_message;
            
            if (data.remaining_days > 0) {
                noteElem.textContent = `Donor can donate after ${data.remaining_days} days`;
            } else {
                noteElem.textContent = 'Donor can donate blood now';
            }
        }
    </script>
</body>
</html>