<?php
session_start();
require_once '../../assets/conn/db_conn.php';

// Add pagination settings
$records_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Modify your Supabase query to include pagination
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => SUPABASE_URL . '/rest/v1/blood_collection?select=*&order=submitted_at.desc',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY
    ]
]);

$response = curl_exec($ch);
$collections = json_decode($response, true) ?: [];
$total_records = count($collections);
$total_pages = ceil($total_records / $records_per_page);

// Slice the array to get only the records for the current page
$collections = array_slice($collections, $offset, $records_per_page);

// Close cURL session
curl_close($ch);

// Get physical examination records with donor information
$ch = curl_init(SUPABASE_URL . '/rest/v1/physical_examination?select=physical_exam_id,donor_id,remarks,blood_bag_type,disapproval_reason,donor_form(surname,first_name)&order=created_at.desc');

$headers = array(
    'apikey: ' . SUPABASE_API_KEY,
    'Authorization: Bearer ' . SUPABASE_API_KEY,
    'Accept: application/json'
);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$examinations = json_decode($response, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    $examinations = array();
}

curl_close($ch);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --bg-color: #f8f9fa;
            --text-color: #000;
            --sidebar-bg: #ffffff;
            --hover-bg: #dee2e6;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
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

        /* Main Content */
        .main-content {
            padding: 1.5rem;
            margin-left: 16.66666667%;
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
            border-top: 8px solid #d9534f;
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
            color: #d9534f;
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
            background: #d9534f;
            color: white;
        }

        .confirm-action:hover {
            background: #c9302c;
        }

        /* Clickable Table Row */
        .clickable-row {
            cursor: pointer;
        }

        .clickable-row:hover {
            background-color: #f5f5f5;
        }

        /* Search Bar Styling */
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

        #searchInput {
            border: 2px solid #ced4da;
            border-left: none;
            padding: 1.5rem;
            font-size: 1.2rem;
            flex: 1;
        }

        /* Pagination Styles */
        .pagination-container {
            margin-top: 1.5rem;
        }

        .pagination {
            justify-content: center;
        }

        .page-link {
            color: #000;
            border-color: #000;
            padding: 0.5rem 1rem;
        }

        .page-link:hover {
            background-color: #000;
            color: #fff;
            border-color: #000;
        }

        .page-item.active .page-link {
            background-color: #000;
            border-color: #000;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            border-color: #dee2e6;
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
                    <li class="nav-item"><a class="nav-link" href="#">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Donor Interviews Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Physical Exams Submissions</a></li>
                    <li class="nav-item"><a class="nav-link active" href="#">Blood Collection Submissions</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Submit a Letter</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Logout</a></li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <div class="container-fluid p-4 custom-margin">
                    <h2 class="mt-1 mb-4">Blood Collection</h2>
                    
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
                                        <option value="surname">Surname</option>
                                        <option value="firstname">First Name</option>
                                        <option value="remarks">Physical Exam Remarks</option>
                                        <option value="blood_bag_type">Blood Bag Type</option>
                                    </select>
                                    <input type="text" 
                                        class="form-control form-control-lg" 
                                        id="searchInput" 
                                        placeholder="Search records..."
                                        style="height: 60px; font-size: 1.2rem;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <?php if (!empty($debug_info)): ?>
                            <div class="alert alert-info">
                                <?php echo htmlspecialchars($debug_info); ?>
                            </div>
                        <?php endif; ?>
                        
                        <table class="dashboard-staff-tables table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Surname</th>
                                    <th>First Name</th>
                                    <th>Physical Exam Remarks</th>
                                    <th>Blood Bag Type</th>
                                </tr>
                            </thead>
                            <tbody id="bloodCollectionTableBody">
                                <?php 
                                if (is_array($examinations) && !empty($examinations)) {
                                    $counter = 1; // Initialize counter
                                    foreach ($examinations as $examination) {
                                        if (!is_array($examination)) continue;
                                        
                                        // Skip records that have a disapproval reason
                                        if (!empty($examination['disapproval_reason'])) continue;
                                        
                                        ?>
                                        <tr class="clickable-row" data-examination='<?php echo htmlspecialchars(json_encode([
                                            'donor_id' => $examination['donor_id'] ?? '',
                                            'physical_exam_id' => $examination['physical_exam_id'] ?? ''
                                        ]), JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo isset($examination['donor_form']['surname']) ? htmlspecialchars($examination['donor_form']['surname']) : 'N/A'; ?></td>
                                            <td><?php echo isset($examination['donor_form']['first_name']) ? htmlspecialchars($examination['donor_form']['first_name']) : 'N/A'; ?></td>
                                            <td><?php echo isset($examination['remarks']) ? htmlspecialchars($examination['remarks']) : 'N/A'; ?></td>
                                            <td><?php echo isset($examination['blood_bag_type']) ? htmlspecialchars($examination['blood_bag_type']) : 'N/A'; ?></td>
                                        </tr>
                                <?php 
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">No approved records found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Add Pagination Controls -->
                        <div class="pagination-container">
                            <nav aria-label="Blood collection submissions navigation">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous button -->
                                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" <?php echo $current_page <= 1 ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Previous</a>
                                    </li>
                                    
                                    <!-- Page numbers -->
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Next button -->
                                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" <?php echo $current_page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            let confirmationDialog = document.getElementById("confirmationDialog");
            let loadingSpinner = document.getElementById("loadingSpinner");
            let cancelButton = document.getElementById("cancelButton");
            let confirmButton = document.getElementById("confirmButton");
            const searchInput = document.getElementById('searchInput');
            const searchCategory = document.getElementById('searchCategory');
            const bloodCollectionTableBody = document.getElementById('bloodCollectionTableBody');
            let currentCollectionData = null;

            // Store original rows for search reset
            const originalRows = Array.from(bloodCollectionTableBody.getElementsByTagName('tr'));

            // Attach click event to all rows
            function attachRowClickHandlers() {
                document.querySelectorAll(".clickable-row").forEach(row => {
                    row.addEventListener("click", function() {
                        currentCollectionData = JSON.parse(this.dataset.examination);
                        confirmationDialog.classList.remove("hide");
                        confirmationDialog.classList.add("show");
                        confirmationDialog.style.display = "block";
                    });
                });
            }

            attachRowClickHandlers();

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
                if (!currentCollectionData) {
                    console.error('No collection data available');
                    return;
                }

                closeModal();
                loadingSpinner.style.display = "block";
                
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../../src/views/forms/blood-collection-form.php';

                // Add donor_id and physical_exam_id as hidden inputs
                const donorIdInput = document.createElement('input');
                donorIdInput.type = 'hidden';
                donorIdInput.name = 'donor_id';
                donorIdInput.value = currentCollectionData.donor_id;

                const physicalExamIdInput = document.createElement('input');
                physicalExamIdInput.type = 'hidden';
                physicalExamIdInput.name = 'physical_exam_id';
                physicalExamIdInput.value = currentCollectionData.physical_exam_id;

                form.appendChild(donorIdInput);
                form.appendChild(physicalExamIdInput);
                document.body.appendChild(form);

                setTimeout(() => {
                    loadingSpinner.style.display = "none";
                    form.submit();
                }, 2000);
            });

            // No Button (Closes Modal)
            cancelButton.addEventListener("click", closeModal);

            // Search functionality
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const category = searchCategory.value;
                
                if (!searchTerm) {
                    originalRows.forEach(row => row.style.display = '');
                    return;
                }

                originalRows.forEach(row => {
                    const cells = Array.from(row.getElementsByTagName('td'));
                    let shouldShow = false;

                    if (category === 'all') {
                        shouldShow = cells.some(cell => 
                            cell.textContent.toLowerCase().includes(searchTerm)
                        );
                    } else {
                        const columnIndex = {
                            'date': 1,
                            'surname': 2,
                            'firstname': 3,
                            'remarks': 4,
                            'blood_bag_type': 5
                        }[category];

                        if (columnIndex !== undefined) {
                            const cellText = cells[columnIndex].textContent.toLowerCase();
                            shouldShow = cellText.includes(searchTerm);
                        }
                    }

                    row.style.display = shouldShow ? '' : 'none';
                });
            }

            // Update placeholder based on selected category
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'date': placeholder += 'date...'; break;
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'remarks': placeholder += 'physical exam remarks...'; break;
                    case 'blood_bag_type': placeholder += 'blood bag type...'; break;
                    default: placeholder = 'Search records...';
                }
                searchInput.placeholder = placeholder;
                performSearch();
            });

            // Debounce function
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
        });
    </script>
</body>
</html>