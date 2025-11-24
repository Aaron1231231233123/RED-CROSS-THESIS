<?php
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../../assets/conn/db_conn.php';
require_once 'module/optimized_functions.php';

// Admin only
$required_role = 1;
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] !== $required_role) {
    header("Location: ../unauthorized.php");
    exit();
}

$userId = isset($_GET['id']) ? trim($_GET['id']) : '';
$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
if (empty($userId) || !preg_match($uuidPattern, $userId)) {
    header("Location: Dashboard-Inventory-System-Users.php?error=invalid-user");
    exit();
}

$errors = [];
$successMessage = '';
$fatalError = '';
$staffSubroleOptions = [
    'Interviewer' => 'Interviewer',
    'Physician' => 'Physician',
    'Phlebotomist' => 'Phlebotomist',
    'Reviewer' => 'Reviewer'
];
$defaultProfileAvatar = 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220"><rect width="100%" height="100%" fill="#f5f5f5"/><circle cx="110" cy="90" r="50" fill="#d9d9d9"/><rect width="140" height="70" x="40" y="140" rx="35" fill="#d9d9d9"/></svg>');

function supabaseSucceeded($response) {
    if (!is_array($response)) {
        return false;
    }
    if (isset($response['code']) && $response['code'] >= 200 && $response['code'] < 300) {
        return true;
    }
    return isset($response['data']) && !empty($response['data']);
}

function fetchUserProfileById($userId) {
    $userResponse = supabaseRequest(
        "users?select=user_id,email,first_name,surname,middle_name,suffix,user_image,role_id,is_active,created_at,last_login_at&user_id=eq.$userId",
        'GET'
    );

    if (isset($userResponse['data']) && !empty($userResponse['data'])) {
        $user = $userResponse['data'][0];
        $roleResponse = supabaseRequest(
            "user_roles?select=user_staff_roles,role_id&user_id=eq.$userId",
            'GET',
            null,
            true
        );
        if (isset($roleResponse['data'][0]['user_staff_roles'])) {
            $user['user_staff_roles'] = $roleResponse['data'][0]['user_staff_roles'];
        }
        return $user;
    }

    return null;
}

$userData = fetchUserProfileById($userId);
if (!$userData) {
    $fatalError = 'User record not found or has been removed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($fatalError)) {
    $firstName = trim($_POST['first_name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $isActive = isset($_POST['is_active']);
    $subroleInput = trim($_POST['user_staff_roles'] ?? '');
    $currentImagePath = $userData['user_image'] ?? '';
    $newImageUploaded = false;

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($surname === '') {
        $errors[] = 'Surname is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (!in_array($roleId, [1, 2, 3], true)) {
        $errors[] = 'Please select a valid role.';
    }
    if ($roleId === 3 && ($subroleInput === '' || !array_key_exists($subroleInput, $staffSubroleOptions))) {
        $errors[] = 'Please choose a valid staff subrole.';
    }

    // Handle optional profile photo upload
    if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['user_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload profile photo.';
        } else {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];
            $tmpPath = $_FILES['user_image']['tmp_name'];
            $mime = mime_content_type($tmpPath);
            if (!isset($allowedTypes[$mime])) {
                $errors[] = 'Profile photo must be a JPG, PNG, or WEBP image.';
            } elseif ($_FILES['user_image']['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Profile photo must be 2MB or smaller.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/user_profiles/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
                    $errors[] = 'Unable to create upload directory.';
                } else {
                    $fileName = $userId . '_' . time() . '.' . $allowedTypes[$mime];
                    $destination = $uploadDir . $fileName;
                    if (!move_uploaded_file($tmpPath, $destination)) {
                        $errors[] = 'Failed to save profile photo.';
                    } else {
                        if (!empty($currentImagePath)) {
                            $existingPath = __DIR__ . '/../' . ltrim($currentImagePath, '/\\');
                            if (is_file($existingPath)) {
                                @unlink($existingPath);
                            }
                        }
                        $currentImagePath = 'uploads/user_profiles/' . $fileName;
                        $newImageUploaded = true;
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $updatePayload = [
            'first_name' => $firstName,
            'surname' => $surname,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'suffix' => $suffix !== '' ? $suffix : null,
            'email' => $email,
            'role_id' => $roleId,
            'is_active' => $isActive
        ];
        if ($newImageUploaded) {
            $updatePayload['user_image'] = $currentImagePath;
        }

        $userUpdateResponse = supabaseRequest("users?user_id=eq.$userId", 'PATCH', $updatePayload);

        if (!supabaseSucceeded($userUpdateResponse)) {
            $errors[] = 'Failed to update user record.';
        } else {
            // Future enhancement: attach audit logging payload here when backend hook is ready.
            $upsertPayload = [
                'user_id' => $userId,
                'email' => $email,
                'role_id' => $roleId,
                'user_staff_roles' => $roleId === 3 ? $subroleInput : null
            ];

            $roleResponse = supabaseRequest(
                "user_roles?on_conflict=user_id",
                'POST',
                $upsertPayload,
                true,
                'resolution=merge-duplicates,return=representation'
            );

            if (!supabaseSucceeded($roleResponse)) {
                $errors[] = 'Failed to update staff role details.';
            }

            if (empty($errors)) {
                $_SESSION['user_update_success'] = 'User profile updated successfully.';
                header('Location: Dashboard-Inventory-System-Users.php');
                exit();
            }
        }
    }
}

$fullName = '';
if ($userData) {
    $fullName = trim(
        ($userData['surname'] ?? '') . ', ' .
        ($userData['first_name'] ?? '') . ' ' .
        ($userData['middle_name'] ?? '')
    );
    $fullName = $fullName !== ',  ' ? $fullName : ($userData['email'] ?? 'User');
}

$isActiveFlag = isset($userData['is_active']) ? (bool)$userData['is_active'] : false;
$roleIdCurrent = isset($userData['role_id']) ? (int)$userData['role_id'] : 0;
$staffSubroleCurrent = $userData['user_staff_roles'] ?? '';
$profileImagePath = $userData['user_image'] ?? '';
$hasProfileImage = !empty($profileImagePath);
$profileImageUrl = $hasProfileImage ? '../' . ltrim($profileImagePath, '/\\') : $defaultProfileAvatar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            margin: 0;
            font-family: Arial, sans-serif;
        }
        .dashboard-home-header {
            position: fixed;
            top: 0;
            left: 240px;
            width: calc(100% - 240px);
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
            z-index: 1000;
            transition: left 0.3s ease, width 0.3s ease;
        }
        .dashboard-home-sidebar {
            height: 100vh;
            overflow-y: auto;
            position: fixed;
            width: 240px;
            background-color: #ffffff;
            border-right: 1px solid #ddd;
            padding: 15px;
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
        }
        .dashboard-home-sidebar .nav-link {
            color: #333;
            padding: 10px 15px;
            margin: 2px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
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
            color: #fff;
        }
        .logout-link {
            color: #dc3545 !important;
        }
        .logout-link:hover {
            background-color: #dc3545 !important;
            color: #fff !important;
        }
        .sidebar-main-content {
            flex-grow: 1;
            padding-bottom: 80px;
        }
        .logout-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px 15px;
            border-top: 1px solid #ddd;
            background-color: #ffffff;
        }
        .content-wrapper {
            background: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 24px;
            margin-top: 0;
        }
        .profile-photo-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .profile-photo-preview {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #f1f1f1;
            background-color: #fff;
        }
        main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
            margin-left: 280px !important;
            margin-top: 70px !important;
            padding-top: 20px;
        }
        @media (max-width: 992px) {
            .dashboard-home-sidebar { width: 200px; }
            .dashboard-home-header { left: 200px; width: calc(100% - 200px); }
            main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 { margin-left: 200px !important; }
        }
        @media (max-width: 768px) {
            .dashboard-home-sidebar { width: 0; padding: 0; overflow: hidden; }
            .dashboard-home-header { left: 0; width: 100%; }
            main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 { margin-left: 0 !important; padding: 10px; }
        }
        .form-section-title {
            font-weight: 600;
            color: #b22222;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <a href="Dashboard-Inventory-System-Users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Manage Users
                </a>
            </div>
        </div>

        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block dashboard-home-sidebar">
                <div class="sidebar-main-content">
                    <div class="d-flex align-items-center ps-1 mb-3 mt-2">
                        <img src="../../assets/image/PRC_Logo.png" alt="Red Cross Logo" style="width: 65px; height: 65px; object-fit: contain;">
                        <span class="text-primary ms-1" style="font-size: 1.5rem; font-weight: 600;">Dashboard</span>
                    </div>
                    <ul class="nav flex-column">
                        <a href="dashboard-Inventory-System.php" class="nav-link">
                            <span><i class="fas fa-home"></i>Home</span>
                        </a>
                        <a href="dashboard-Inventory-System-list-of-donations.php" class="nav-link">
                            <span><i class="fas fa-users"></i>Donor Management</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Bloodbank.php" class="nav-link">
                            <span><i class="fas fa-tint"></i>Blood Bank</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Hospital-Request.php" class="nav-link">
                            <span><i class="fas fa-list"></i>Hospital Requests</span>
                        </a>
                        <a href="dashboard-Inventory-System-Reports-reports-admin.php" class="nav-link">
                            <span><i class="fas fa-chart-line"></i>Forecast Reports</span>
                        </a>
                        <a href="Dashboard-Inventory-System-Users.php" class="nav-link active">
                            <span><i class="fas fa-user-cog"></i>Manage Users</span>
                        </a>
                    </ul>
                </div>
                <div class="logout-container">
                    <a href="../../assets/php_func/logout.php" class="nav-link logout-link text-danger">
                        <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                    </a>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-1"><?php echo htmlspecialchars($fullName ?: 'User Profile'); ?></h2>
                            <p class="text-muted mb-0">Edit user information, roles, and account status.</p>
                        </div>
                        <span class="badge <?php echo $isActiveFlag ? 'bg-success' : 'bg-secondary'; ?> ms-3">
                            <?php echo $isActiveFlag ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($fatalError)): ?>
                        <div class="alert alert-warning mb-0">
                            <?php echo htmlspecialchars($fatalError); ?>
                        </div>
                    <?php else: ?>
                    <form method="POST" enctype="multipart/form-data" novalidate>
                        <div class="row g-4">
                            <div class="col-lg-8">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="form-section-title mb-3">Personal Details</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                            <label class="form-label">First Name<span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Surname<span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="surname" value="<?php echo htmlspecialchars($userData['surname'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Middle Name</label>
                                                <input type="text" class="form-control" name="middle_name" value="<?php echo htmlspecialchars($userData['middle_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Suffix</label>
                                                <select class="form-select" name="suffix">
                                                    <?php
                                                        $suffixes = ['', 'Jr.', 'Sr.', 'III', 'IV'];
                                                        $currentSuffix = $userData['suffix'] ?? '';
                                                    ?>
                                                    <?php foreach ($suffixes as $suffixOption): ?>
                                                        <option value="<?php echo htmlspecialchars($suffixOption); ?>" <?php echo ($currentSuffix === $suffixOption) ? 'selected' : ''; ?>>
                                                            <?php echo $suffixOption === '' ? 'None' : htmlspecialchars($suffixOption); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Email Address<span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                            </div>
                                        </div>

                                        <hr class="my-4">
                                        <h5 class="form-section-title mb-3">Role & Access</h5>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Role<span class="text-danger">*</span></label>
                                                <select class="form-select" id="role_id" name="role_id" required>
                                                    <?php
                                                        $roleOptions = [
                                                            1 => 'Admin',
                                                            2 => 'Hospital',
                                                            3 => 'Staff'
                                                        ];
                                                    ?>
                                                    <option value="">Select Role</option>
                                                    <?php foreach ($roleOptions as $roleValue => $roleLabel): ?>
                                                        <option value="<?php echo $roleValue; ?>" <?php echo ($roleIdCurrent === $roleValue) ? 'selected' : ''; ?>>
                                                            <?php echo $roleLabel; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6" id="staffSubroleGroup" style="<?php echo ($roleIdCurrent === 3) ? '' : 'display:none;'; ?>">
                                                <label class="form-label">Staff Subrole<span class="text-danger" id="staffSubroleRequiredIndicator" style="<?php echo ($roleIdCurrent === 3) ? '' : 'display:none;'; ?>">*</span></label>
                                                <select class="form-select" name="user_staff_roles" id="user_staff_roles">
                                                    <option value="">Select Subrole</option>
                                                    <?php foreach ($staffSubroleOptions as $value => $label): ?>
                                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($staffSubroleCurrent === $value) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-body profile-photo-wrapper">
                                        <h5 class="form-section-title mb-2">Profile Photo</h5>
                                        <img
                                            src="<?php echo htmlspecialchars($profileImageUrl); ?>"
                                            alt="Profile photo"
                                            class="profile-photo-preview"
                                            id="profileImagePreview"
                                        >
                                        <p class="text-muted small mb-0 <?php echo $hasProfileImage ? 'd-none' : ''; ?>" id="profileImagePlaceholderText">
                                            No photo uploaded yet.
                                        </p>
                                        <div class="w-100">
                                            <input
                                                class="form-control"
                                                type="file"
                                                name="user_image"
                                                id="user_image"
                                                accept="image/jpeg,image/png,image/webp"
                                            >
                                            <div class="form-text">Max size 2MB. JPG, PNG, or WEBP.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-body">
                                        <h5 class="form-section-title mb-3">Account Status</h5>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" <?php echo $isActiveFlag ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_active">
                                                <?php echo $isActiveFlag ? '<strong >Active</strong>' : '<strong>Inactive</strong>'; ?>
                                            </label>
                                        </div>
                                        <div class="mt-4">
                                            <p class="mb-1"><strong>User ID:</strong></p>
                                            <p class="text-muted"><?php echo htmlspecialchars($userData['user_id']); ?></p>
                                            <p class="mb-1"><strong>Last Login:</strong></p>
                                            <p class="text-muted"><?php echo $userData['last_login_at'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($userData['last_login_at']))) : 'Not available'; ?></p>
                                            <p class="mb-1"><strong>Account Created:</strong></p>
                                            <p class="text-muted"><?php echo $userData['created_at'] ? htmlspecialchars(date('d/m/Y', strtotime($userData['created_at']))) : 'Not available'; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="Dashboard-Inventory-System-Users.php" class="btn btn-outline-secondary flex-grow-1">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-danger flex-grow-1">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role_id');
            const subroleGroup = document.getElementById('staffSubroleGroup');
            const subroleSelect = document.getElementById('user_staff_roles');
            const requiredIndicator = document.getElementById('staffSubroleRequiredIndicator');
            const imageInput = document.getElementById('user_image');
            const imagePreview = document.getElementById('profileImagePreview');
            const imagePlaceholder = document.getElementById('profileImagePlaceholderText');
            const isActiveSwitch = document.getElementById('is_active');
            const isActiveLabel = document.querySelector('label[for="is_active"]');

            function toggleSubroleVisibility() {
                if (!roleSelect || !subroleGroup || !subroleSelect || !requiredIndicator) {
                    return;
                }
                if (roleSelect.value === '3') {
                    subroleGroup.style.display = 'block';
                    subroleSelect.required = true;
                    requiredIndicator.style.display = 'inline';
                } else {
                    subroleGroup.style.display = 'none';
                    subroleSelect.required = false;
                    subroleSelect.value = '';
                    requiredIndicator.style.display = 'none';
                }
            }

            function attachPhotoPreview() {
                if (!imageInput || !imagePreview) {
                    return;
                }
                imageInput.addEventListener('change', function() {
                    const file = this.files && this.files[0];
                    if (!file) {
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function(evt) {
                        imagePreview.src = evt.target?.result || imagePreview.src;
                        if (imagePlaceholder) {
                            imagePlaceholder.classList.add('d-none');
                        }
                    };
                    reader.readAsDataURL(file);
                });
            }

            function updateStatusLabel() {
                if (!isActiveSwitch || !isActiveLabel) {
                    return;
                }
                if (isActiveSwitch.checked) {
                    isActiveLabel.innerHTML = '<strong>Active</strong>';
                } else {
                    isActiveLabel.innerHTML = '<strong>Inactive</strong>';
                }
            }

            toggleSubroleVisibility();
            attachPhotoPreview();
            updateStatusLabel();

            if (roleSelect) {
                roleSelect.addEventListener('change', toggleSubroleVisibility);
            }
            if (isActiveSwitch) {
                isActiveSwitch.addEventListener('change', updateStatusLabel);
            }
        });
    </script>
</body>
</html>

