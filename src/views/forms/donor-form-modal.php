<?php
// donor-form-modal.php

// Store the referrer URL to use it for the close button
$referrer = '';

// Check HTTP_REFERER first
if (isset($_SERVER['HTTP_REFERER'])) {
    $referrer = $_SERVER['HTTP_REFERER'];
}

// If no referrer or it's not from a dashboard, check for a passed parameter
if (!$referrer || !stripos($referrer, 'dashboard')) {
    if (isset($_GET['source'])) {
        $referrer = $_GET['source'];
    }
}

// Default fallback
if (!$referrer) {
    $referrer = '../../public/Dashboards/dashboard-Inventory-System.php';
}

// Store the referrer in a session variable to maintain it across form submissions
session_start();
if (!isset($_SESSION['donor_form_referrer'])) {
    $_SESSION['donor_form_referrer'] = $referrer;
} else {
    // Use the stored referrer if available
    $referrer = $_SESSION['donor_form_referrer'];
}

// Clean up abandoned donor form data (older than 30 minutes)
if (isset($_SESSION['donor_form_data']) && isset($_SESSION['donor_form_timestamp'])) {
    $currentTime = time();
    $formSubmitTime = $_SESSION['donor_form_timestamp'];
    $timeDifference = $currentTime - $formSubmitTime;
    
    // If form data is older than 30 minutes, remove it
    if ($timeDifference > 1800) { // 1800 seconds = 30 minutes
        error_log("Removing stale donor form data (older than 30 minutes)");
        unset($_SESSION['donor_form_data']);
        unset($_SESSION['donor_form_timestamp']);
    }
}

// Include database connection
try {
    include_once '../../../assets/conn/db_conn.php';
    
    // Make the constants available as global variables for use in cURL requests
    $SUPABASE_URL = SUPABASE_URL;
    $SUPABASE_API_KEY = SUPABASE_API_KEY;
    
    // Check if Supabase connection variables are defined
    if (empty($SUPABASE_URL) || empty($SUPABASE_API_KEY)) {
        throw new Exception("Database connection parameters are not properly defined");
    }
} catch (Exception $e) {
    $errorMessage = "Database connection error: " . $e->getMessage();
    error_log("DB Connection Error: " . $e->getMessage());
}

// Generate a unique PRC donor number (format: PRC-YYYY-XXXXX)
function generateDonorNumber() {
    $year = date('Y');
    $randomNumber = mt_rand(10000, 99999); // 5-digit random number
    return "PRC-$year-$randomNumber";
}

// Generate a unique DOH NNBNETS barcode (format: DOH-YYYYXXXX)
function generateNNBNetBarcode() {
    $year = date('Y');
    $randomNumber = mt_rand(1000, 9999); // 4-digit random number
    return "DOH-$year$randomNumber";
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_donor_form'])) {
    // Process form data
    // Combine address fields into a single permanent_address field
    $permanent_address = "";
    if (!empty($_POST['street'])) {
        $permanent_address .= $_POST['street'];
    }
    if (!empty($_POST['barangay'])) {
        $permanent_address .= ($permanent_address ? ", " : "") . $_POST['barangay'];
    }
    if (!empty($_POST['town_municipality'])) {
        $permanent_address .= ($permanent_address ? ", " : "") . $_POST['town_municipality'];
    }
    if (!empty($_POST['province_city'])) {
        $permanent_address .= ($permanent_address ? ", " : "") . $_POST['province_city'];
    }
    
    // Prepare data for Supabase
    $formData = [
        'surname' => $_POST['surname'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'middle_name' => $_POST['middle_name'] ?? '',
        'birthdate' => $_POST['birthdate'] ?? '',
        'age' => intval($_POST['age'] ?? 0),
        'sex' => $_POST['sex'] ?? '',
        'civil_status' => $_POST['civil_status'] ?? '',
        'permanent_address' => $permanent_address,
        'nationality' => $_POST['nationality'] ?? '',
        'religion' => $_POST['religion'] ?? '',
        'education' => $_POST['education'] ?? '',
        'occupation' => $_POST['occupation'] ?? '',
        'mobile' => $_POST['mobile'] ?? '',
        'email' => $_POST['email'] ?? '',
        // Generate unique donor number and barcode
        'prc_donor_number' => generateDonorNumber(),
        'doh_nnbnets_barcode' => generateNNBNetBarcode()
    ];
    
    // Log the data being processed
    error_log("Processing donor form data: " . json_encode($formData));
    
    try {
        // Store formData in session instead of inserting into database
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Store the form data in session
        $_SESSION['donor_form_data'] = $formData;
        $_SESSION['donor_form_timestamp'] = time(); // Track when the form was submitted
        
        error_log("Donor form data stored in session. Redirecting to medical history form.");
        
        // Show loading modal before redirecting to medical history form
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showLoadingModal();
                setTimeout(function() {
                    window.location.href = "medical-history-modal.php";
                }, 1500);
            });
        </script>';
    } catch (Exception $e) {
        error_log("Exception handling donor form: " . $e->getMessage());
        // Add user-facing error message
        echo '<div class="alert alert-danger mt-3" role="alert">
            An error occurred: ' . htmlspecialchars($e->getMessage()) . '
        </div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donor Form</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #a00000;
            --primary-dark: #800000;
            --light-gray: #f8f9fa;
            --border-color: #dee2e6;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: var(--light-gray);
            margin: 0;
            padding: 0;
        }

        .modal-dialog {
            max-width: 800px;
            margin: 30px auto;
        }

        .modal-content {
            border-radius: 8px;
            border: none;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px;
            text-align: center;
            display: block;
            position: relative;
        }

        .modal-title {
            color: var(--primary);
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            text-align: center;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            text-decoration: none;
        }

        .steps-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
        }

        .step-item {
            display: flex;
            align-items: center;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            z-index: 1;
            position: relative;
        }

        .step-number.active {
            background-color: var(--primary);
            color: white;
        }

        .step-number.inactive {
            background-color: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        
        .step-number.completed {
            background-color: var(--primary);
            color: white;
        }

        .step-line {
            width: 80px;
            height: 2px;
            background-color: var(--border-color);
            position: relative;
            top: 0;
        }

        .step-line.active {
            background-color: var(--primary);
        }

        .section-title {
            color: var(--primary);
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .section-details {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 5px;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px;
            height: auto;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(160, 0, 0, 0.25);
            border-color: var(--primary);
        }

        .form-select {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px;
            height: auto;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }
        
        .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(160, 0, 0, 0.25);
            border-color: var(--primary);
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            padding-top: 20px;
            margin-top: 30px;
            border-top: 1px solid var(--border-color);
        }
        
        /* Invalid field highlighting */
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-select.is-invalid {
            padding-right: 4.125rem;
            background-position: right 0.75rem center, center right 2.25rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e"), url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        }

        .btn-navigate {
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .horizontal-line {
            height: 1px;
            background-color: var(--border-color);
            margin: 15px 0;
        }
        
        .completion-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1100;
            justify-content: center;
            align-items: center;
        }
        
        .completion-modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .completion-modal-title {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .completion-modal-button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="modal show d-block" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Personal Data</h5>
                <a href="javascript:void(0)" class="modal-close" id="closeButton">&times;</a>
            </div>
            
            <div class="modal-body">
                <!-- Progress Steps -->
                <div class="steps-container">
                    <div class="step-item">
                        <div class="step-number active" id="step1">1</div>
                    </div>
                    <div class="step-line" id="line1-2"></div>
                    <div class="step-item">
                        <div class="step-number inactive" id="step2">2</div>
                    </div>
                    <div class="step-line" id="line2-3"></div>
                    <div class="step-item">
                        <div class="step-number inactive" id="step3">3</div>
                    </div>
                    <div class="step-line" id="line3-4"></div>
                    <div class="step-item">
                        <div class="step-number inactive" id="step4">4</div>
                    </div>
                    <div class="step-line" id="line4-5"></div>
                    <div class="step-item">
                        <div class="step-number inactive" id="step5">5</div>
                    </div>
                </div>
                
                <!-- Form Starts -->
                <form id="donorForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return handleFormSubmit()" onkeydown="return preventEnterSubmit(event)" data-allow-enter="false">
                    <!-- Section 1: NAME -->
                    <div class="form-section active" id="section1">
                        <h3 class="section-title">NAME</h3>
                        <p class="section-details">Complete the details below.</p>
                        <div class="horizontal-line"></div>
                        
                        <div class="mb-3">
                            <label for="surname" class="form-label">Surname</label>
                            <input type="text" class="form-control" id="surname" name="surname" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="middle_name" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="middle_name" name="middle_name">
                        </div>
                        
                        <div class="navigation-buttons">
                            <div></div> <!-- Placeholder for alignment -->
                            <button type="button" class="btn btn-primary btn-navigate" onclick="nextSection(1)">Next &gt;</button>
                        </div>
                    </div>
                    
                    <!-- Section 2: PROFILE DETAILS -->
                    <div class="form-section" id="section2">
                        <h3 class="section-title">PROFILE DETAILS</h3>
                        <p class="section-details">Complete the details below.</p>
                        <div class="horizontal-line"></div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="birthdate" class="form-label">Birthdate</label>
                                <input type="date" class="form-control" id="birthdate" name="birthdate" onchange="calculateAge()" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="age" name="age" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="civil_status" class="form-label">Civil Status</label>
                            <select class="form-select" id="civil_status" name="civil_status" required>
                                <option value="" disabled selected>Select Civil Status</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sex" class="form-label">Sex</label>
                            <select class="form-select" id="sex" name="sex" required>
                                <option value="" disabled selected>Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-navigate" onclick="prevSection(2)">&lt; Previous</button>
                            <button type="button" class="btn btn-primary btn-navigate" onclick="nextSection(2)">Next &gt;</button>
                        </div>
                    </div>
                    
                    <!-- Section 3: PERMANENT ADDRESS -->
                    <div class="form-section" id="section3">
                        <h3 class="section-title">PERMANENT ADDRESS</h3>
                        <p class="section-details">Complete the details below.</p>
                        <div class="horizontal-line"></div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3">
                                <label for="address_no" class="form-label">No.</label>
                                <input type="text" class="form-control" id="address_no" name="address_no">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="zip_code" class="form-label">ZIP Code</label>
                                <input type="text" class="form-control" id="zip_code" name="zip_code">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="street" class="form-label">Street</label>
                            <input type="text" class="form-control" id="street" name="street">
                        </div>
                        
                        <div class="mb-3">
                            <label for="barangay" class="form-label">Barangay</label>
                            <input type="text" class="form-control" id="barangay" name="barangay" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="town_municipality" class="form-label">Town/Municipality</label>
                            <input type="text" class="form-control" id="town_municipality" name="town_municipality" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="province_city" class="form-label">Province/City</label>
                            <input type="text" class="form-control" id="province_city" name="province_city" required>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-navigate" onclick="prevSection(3)">&lt; Previous</button>
                            <button type="button" class="btn btn-primary btn-navigate" onclick="nextSection(3)">Next &gt;</button>
                        </div>
                    </div>
                    
                    <!-- Section 4: ADDITIONAL INFORMATION -->
                    <div class="form-section" id="section4">
                        <h3 class="section-title">ADDITIONAL INFORMATION</h3>
                        <p class="section-details">Complete the details below.</p>
                        <div class="horizontal-line"></div>
                        
                        <div class="mb-3">
                            <label for="nationality" class="form-label">Nationality</label>
                            <input type="text" class="form-control" id="nationality" name="nationality" value="Filipino" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="religion" class="form-label">Religion</label>
                            <input type="text" class="form-control" id="religion" name="religion" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="education" class="form-label">Education</label>
                            <select class="form-select" id="education" name="education" required>
                                <option value="" selected disabled>Select Education Level</option>
                                <option value="Elementary">Elementary</option>
                                <option value="High School">High School</option>
                                <option value="College">College</option>
                                <option value="Post Graduate">Post Graduate</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="occupation" class="form-label">Occupation</label>
                            <input type="text" class="form-control" id="occupation" name="occupation" required>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-navigate" onclick="prevSection(4)">&lt; Previous</button>
                            <button type="button" class="btn btn-primary btn-navigate" onclick="nextSection(4)">Next &gt;</button>
                        </div>
                    </div>
                    
                    <!-- Section 5: CONTACT INFORMATION -->
                    <div class="form-section" id="section5">
                        <h3 class="section-title">CONTACT INFORMATION</h3>
                        <p class="section-details">Complete the details below.</p>
                        <div class="horizontal-line"></div>
                        
                        <div class="mb-3">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-muted">(optional)</span></label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="navigation-buttons">
                            <button type="button" class="btn btn-secondary btn-navigate" onclick="prevSection(5)">&lt; Previous</button>
                            <button type="submit" class="btn btn-primary btn-navigate" id="submitButton" name="submit_donor_form">Submit &gt;</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
            <div class="modal-body text-center">
                <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-white bg-dark p-2 rounded">Proceeding to Medical History Form...</p>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function calculateAge() {
        const birthdateInput = document.getElementById('birthdate');
        const ageInput = document.getElementById('age');
        
        if (birthdateInput.value) {
            const birthdate = new Date(birthdateInput.value);
            const today = new Date();
            
            let age = today.getFullYear() - birthdate.getFullYear();
            const monthDifference = today.getMonth() - birthdate.getMonth();
            
            if (monthDifference < 0 || (monthDifference === 0 && today.getDate() < birthdate.getDate())) {
                age--;
            }
            
            ageInput.value = age;
        } else {
            ageInput.value = '';
        }
    }
    
    // Function to update the form's data-allow-enter attribute based on the current section
    function updateEnterKeyBehavior(sectionNumber) {
        const form = document.getElementById('donorForm');
        if (form) {
            // Allow Enter key submission only on the last section
            form.setAttribute('data-allow-enter', sectionNumber === 5 ? 'true' : 'false');
        }
    }
    
    function nextSection(currentSection) {
        // Basic validation for required fields in current section
        const section = document.getElementById(`section${currentSection}`);
        const requiredFields = section.querySelectorAll('[required]');
        let valid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                valid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!valid) {
            alert('Please fill all required fields');
            return;
        }
        
        // Special validation for section 3 (permanent address)
        if (currentSection === 3) {
            const barangay = document.getElementById('barangay').value.trim();
            const town = document.getElementById('town_municipality').value.trim();
            const province = document.getElementById('province_city').value.trim();
            
            if (!barangay || !town || !province) {
                if (!barangay) document.getElementById('barangay').classList.add('is-invalid');
                if (!town) document.getElementById('town_municipality').classList.add('is-invalid');
                if (!province) document.getElementById('province_city').classList.add('is-invalid');
                alert('Please fill all required address fields');
                return;
            }
        }
        
        // Hide current section and show next section
        document.getElementById(`section${currentSection}`).classList.remove('active');
        document.getElementById(`section${currentSection + 1}`).classList.add('active');
        
        // Mark current step as completed
        const currentStepEl = document.getElementById(`step${currentSection}`);
        currentStepEl.classList.remove('active');
        currentStepEl.classList.add('completed');
        
        // Then make the next step active
        const nextStepEl = document.getElementById(`step${currentSection + 1}`);
        nextStepEl.classList.remove('inactive');
        nextStepEl.classList.add('active');
        
        // Activate the connecting line
        const lineEl = document.getElementById(`line${currentSection}-${currentSection + 1}`);
        lineEl.classList.add('active');
        
        // Update the modal title
        updateModalTitle(currentSection + 1);
        
        // Update Enter key behavior
        updateEnterKeyBehavior(currentSection + 1);
    }
    
    function prevSection(currentSection) {
        // Hide current section and show previous section
        document.getElementById(`section${currentSection}`).classList.remove('active');
        document.getElementById(`section${currentSection - 1}`).classList.add('active');
        
        // First make the current step inactive
        const currentStepEl = document.getElementById(`step${currentSection}`);
        currentStepEl.classList.remove('active');
        currentStepEl.classList.add('inactive');
        
        // Then make the previous step active (it should already be completed)
        const prevStepEl = document.getElementById(`step${currentSection - 1}`);
        prevStepEl.classList.remove('completed');
        prevStepEl.classList.add('active');
        
        // Deactivate the connecting line
        const lineEl = document.getElementById(`line${currentSection - 1}-${currentSection}`);
        lineEl.classList.remove('active');
        
        // Update the modal title
        updateModalTitle(currentSection - 1);
        
        // Update Enter key behavior
        updateEnterKeyBehavior(currentSection - 1);
    }
    
    function updateModalTitle(sectionNumber) {
        const titleEl = document.querySelector('.modal-title');
        
        // Update modal title based on the section
        switch(sectionNumber) {
            case 1:
                titleEl.textContent = 'Personal Data';
                break;
            case 2:
                titleEl.textContent = 'Personal Data';
                break;
            case 3:
                titleEl.textContent = 'Personal Data';
                break;
            case 4:
                titleEl.textContent = 'Personal Data';
                break;
            case 5:
                titleEl.textContent = 'Personal Data';
                break;
            default:
                titleEl.textContent = 'Personal Data';
        }
    }
    
    function showLoadingModal() {
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();
    }
    
    // Handle form submission
    function handleFormSubmit() {
        // Perform any client-side validation here
        
        // Store the current URL to maintain form state and 
        // set a flag indicating form has been submitted
        if (typeof(Storage) !== "undefined") {
            localStorage.setItem("donorFormReferrer", "<?php echo $referrer; ?>");
            localStorage.setItem("donorFormSubmitted", "true");
        }
        
        return true; // Allow the form to submit
    }
    
    // Prevent form submission when Enter key is pressed
    function preventEnterSubmit(event) {
        // Only prevent if Enter key is pressed
        if (event.key !== 'Enter') {
            return true;
        }
        
        // Get form and check if Enter submission is allowed
        const form = document.getElementById('donorForm');
        const allowEnter = form && form.getAttribute('data-allow-enter') === 'true';
        
        // Get the current active section
        const activeSection = document.querySelector('.form-section.active');
        const isLastSection = activeSection && activeSection.id === 'section5';
        
        // If on the last section and form allows enter, or if the target is the submit button, allow submission
        if ((isLastSection && allowEnter) || (event.target && event.target.id === 'submitButton')) {
            return true;
        }
        
        // Prevent form submission on Enter key press in all other cases
        event.preventDefault();
        
        // If focus is on an input, move to the next input
        if (event.target && (event.target.tagName === 'INPUT' || event.target.tagName === 'SELECT')) {
            // Find the next input to focus
            const inputs = Array.from(form.elements);
            const currentIndex = inputs.indexOf(event.target);
            
            if (currentIndex > -1 && currentIndex < inputs.length - 1) {
                const nextInput = inputs[currentIndex + 1];
                if (nextInput) {
                    nextInput.focus();
                }
            }
            
            // If this is the last input in a section other than section5, click the Next button
            if (activeSection && !isLastSection) {
                const isLastInputInSection = Array.from(activeSection.querySelectorAll('input, select')).pop() === event.target;
                if (isLastInputInSection) {
                    const nextButton = activeSection.querySelector('.btn-primary.btn-navigate');
                    if (nextButton) {
                        nextButton.click();
                    }
                }
            }
        }
        
        return false;
    }
    
    // Function to go back to previous page
    function goBackToDashboard(skipConfirmation = false) {
        // First check if the user has entered data
        if (!skipConfirmation) {
            const formInputs = document.querySelectorAll('input[type="text"], input[type="date"], input[type="email"], select');
            let hasData = false;
            
            formInputs.forEach(input => {
                if ((input.type === 'text' || input.type === 'date' || input.type === 'email') && input.value.trim() !== '') {
                    hasData = true;
                } else if (input.tagName === 'SELECT' && input.selectedIndex > 0) {
                    hasData = true;
                }
            });
            
            if (hasData) {
                const confirmLeave = confirm('You have unsaved data. Are you sure you want to leave this page?');
                if (!confirmLeave) {
                    return false;
                }
            }
        }
        
        // If we get here, either there's no data or user confirmed leaving
        let referrerUrl = "<?php echo $referrer; ?>";
        
        // Check if we have a stored referrer in localStorage
        if (typeof(Storage) !== "undefined" && localStorage.getItem("donorFormReferrer")) {
            referrerUrl = localStorage.getItem("donorFormReferrer");
        }
        
        // Check if referrer is valid, otherwise go to default dashboard
        if (referrerUrl && (referrerUrl.includes('Dashboard') || referrerUrl.includes('dashboard'))) {
            window.location.href = referrerUrl;
        } else {
            // Default fallback
            window.location.href = "../../public/Dashboards/dashboard-Inventory-System.php";
        }
    }
    
    // Auto-calculate age on page load if birthdate already exists
    document.addEventListener('DOMContentLoaded', function() {
        const birthdateInput = document.getElementById('birthdate');
        if (birthdateInput.value) {
            calculateAge();
        }
        
        // Initialize step indicators
        document.getElementById('step1').classList.add('active');
        
        // Start with only the first section active
        const sections = document.querySelectorAll('.form-section');
        sections.forEach((section, index) => {
            if (index === 0) {
                section.classList.add('active');
            } else {
                section.classList.remove('active');
            }
        });
        
        // Initialize Enter key behavior - disable on first section
        updateEnterKeyBehavior(1);
        
        // Ensure the submit button triggers the handleFormSubmit function
        const submitButton = document.getElementById('submitButton');
        if (submitButton) {
            submitButton.addEventListener('click', function(e) {
                // Allow regular form submission only from the final section
                const activeSection = document.querySelector('.form-section.active');
                if (activeSection && activeSection.id !== 'section5') {
                    e.preventDefault();
                    return false;
                }
                
                // Final validation of all required fields
                const form = document.getElementById('donorForm');
                const requiredFields = form.querySelectorAll('[required]');
                let valid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        valid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please fill all required fields before submitting');
                    return false;
                }
                
                return handleFormSubmit();
            });
        }
        
        // Ensure close button works - but don't add multiple event listeners
        const closeButton = document.getElementById('closeButton');
        if (closeButton) {
            // Remove inline onclick attribute to prevent double execution
            closeButton.removeAttribute('onclick');
            
            // Add a single event listener
            closeButton.addEventListener('click', function(e) {
                e.preventDefault();
                goBackToDashboard();
                return false;
            });
        }
        
        // Clear any existing click handlers for other modal-close buttons
        document.querySelectorAll('.modal-close:not(#closeButton)').forEach(button => {
            // Remove inline onclick attributes
            button.removeAttribute('onclick');
            
            // Add a single event listener
            button.addEventListener('click', function(e) {
                e.preventDefault();
                goBackToDashboard();
                return false;
            });
        });
        
        // Add ESC key listener to close the form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Check if we're after a form submission
                const formSubmitted = localStorage.getItem("donorFormSubmitted") === "true";
                goBackToDashboard(!formSubmitted);
            }
        });
        
        // If this is a page reload after form submission, check localStorage for referrer
        if (window.location.href.includes('donor-form-modal.php') && typeof(Storage) !== "undefined") {
            const storedReferrer = localStorage.getItem("donorFormReferrer");
            if (storedReferrer) {
                // We have a stored referrer from a previous form submission
                console.log("Using stored referrer:", storedReferrer);
            }
        }
    });
</script>
</body>
</html> 