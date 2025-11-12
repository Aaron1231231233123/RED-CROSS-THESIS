<?php
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../../assets/conn/db_conn.php';
// Shared helpers (provides supabaseRequest)
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

// Fetch users from Supabase users table with subroles
$users = [];
try {
    if (function_exists('supabaseRequest')) {
        // Get users data including timestamps
        $usersResponse = supabaseRequest('users?select=user_id,email,first_name,surname,middle_name,role_id,is_active,created_at,last_login_at', 'GET');
        
        if (isset($usersResponse['data']) && is_array($usersResponse['data'])) {
            // Get user_roles data for subroles
            $userRolesResponse = supabaseRequest('user_roles?select=user_id,user_staff_roles', 'GET');
            $userRoles = [];
            if (isset($userRolesResponse['data']) && is_array($userRolesResponse['data'])) {
                foreach ($userRolesResponse['data'] as $role) {
                    $userRoles[$role['user_id']] = $role['user_staff_roles'] ?? '';
                }
            }
            
            foreach ($usersResponse['data'] as $u) {
                $roleId = isset($u['role_id']) ? (int)$u['role_id'] : null;
                $userId = $u['user_id'] ?? '';
                
                
                // Map role for display with subroles
                $roleDisplay = '';
                if ($roleId === 1) {
                    $roleDisplay = 'Admin';
                } elseif ($roleId === 2) {
                    $roleDisplay = 'Hospital';
                } elseif ($roleId === 3) {
                    $staffRole = $userRoles[$userId] ?? '';
                    if (!empty($staffRole)) {
                        $roleDisplay = 'Staff - ' . ucfirst($staffRole);
                    } else {
                        $roleDisplay = 'Staff';
                    }
                }
                
                // Build full name
                $fullName = '';
                if (!empty($u['surname']) || !empty($u['first_name'])) {
                    $fullName = trim(($u['surname'] ?? '') . ', ' . ($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? ''));
                } else {
                    $fullName = $u['email'] ?? '';
                }
                
                $users[] = [
                    'id' => $userId,
                    'name' => $fullName,
                    'email' => $u['email'] ?? '',
                    'role' => $roleDisplay,
                    'is_active' => isset($u['is_active']) ? (bool)$u['is_active'] : true,
                    'created_at' => $u['created_at'] ?? null,
                    'last_login_at' => $u['last_login_at'] ?? null,
                ];
            }
        }
    }
} catch (Exception $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
body {
	background-color: #f8f9fa;
	margin: 0;
	padding: 0;
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
.logout-link {
	color: #dc3545 !important;
}
.logout-link:hover {
	background-color: #dc3545 !important;
	color: #fff !important;
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
.content-wrapper {
	background: #fff;
	box-shadow: 0 0 15px rgba(0,0,0,0.05);
	border-radius: 12px;
	padding: 24px;
	margin-top: 0;
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
	</style>
</head>
<body>
    <?php include '../../src/views/modals/admin-donor-registration-modal.php'; ?>
    <div class="container-fluid">
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <button class="btn btn-danger" onclick="showConfirmationModal()">
                    <i class="fas fa-plus me-2"></i>Register Donor
                </button>
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
                    <a href="../../assets/php_func/logout.php" class="nav-link logout-link">
                        <span><i class="fas fa-sign-out-alt me-2"></i>Logout</span>
                    </a>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-wrapper">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-1">Manage Users</h2>
                            <p class="text-muted mb-0">Create, edit, and deactivate system users</p>
                        </div>
                        <div>
                            <button class="btn btn-danger" id="openCreateUserModalBtn"><i class="fa fa-user-plus me-2"></i>Add User</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" id="searchUser" class="form-control" placeholder="Search users...">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select id="roleFilter" class="form-select">
                                        <option value="">All Roles</option>
                                        <option value="Admin">Admin</option>
                                        <option value="Staff">Staff</option>
                                        <option value="Hospital">Hospital</option>
                                    </select>
                                </div>
                                <div class="col-md-3 text-end">
                                    <button class="btn btn-outline-secondary" id="refreshBtn"><i class="fas fa-rotate"></i></button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th style="background-color: #b22222; color: white; font-weight: 700;">No.</th>
                                            <th style="background-color: #b22222; color: white; font-weight: 700;">Name</th>
                                            <th style="background-color: #b22222; color: white; font-weight: 700;">Email</th>
                                            <th style="background-color: #b22222; color: white; font-weight: 700;">Role</th>
                                            <th style="background-color: #b22222; color: white; font-weight: 700;">Status</th>
                                            <th style="background-color: #b22222; color: white; font-weight: 700;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No users found. Connect to your users table to populate.</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($users as $index => $u): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($u['name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($u['role'] ?? ''); ?></td>
                                            <td>
                                                <?php if (isset($u['is_active']) && $u['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button
                                                    class="btn btn-sm btn-info me-1 view-user-btn"
                                                    data-user-id="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-name="<?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-email="<?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-role="<?php echo htmlspecialchars($u['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-status="<?php echo isset($u['is_active']) && $u['is_active'] ? 'Active' : 'Inactive'; ?>"
                                                    data-user-created="<?php echo htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-user-last-login="<?php echo htmlspecialchars($u['last_login_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (isset($u['is_active']) && $u['is_active']): ?>
                                                    <button
                                                        class="btn btn-sm btn-danger deactivate-user-btn"
                                                        data-user-id="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-user-name="<?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    >Deactivate</button>
                                                <?php else: ?>
                                                    <button
                                                        class="btn btn-sm btn-success activate-user-btn"
                                                        data-user-id="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-user-name="<?php echo htmlspecialchars($u['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                    >Activate</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Staff/User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createUserModalLabel" style="color:#b22222; font-weight: 700;">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label" style="font-weight:600; font-size:1.1rem;">First Name</label>
                            <input type="text" class="form-control" id="newUserFirstName" placeholder="First Name">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-weight:600; font-size:1.1rem;">Surname</label>
                            <input type="text" class="form-control" id="newUserSurname" placeholder="Surname">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label" style="font-weight:600; font-size:1.1rem;">Middle Name <span class="text-muted">(Optional)</span></label>
                            <input type="text" class="form-control" id="newUserMiddleName" placeholder="Middle Name">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-weight:600; font-size:1.1rem;">Suffix <span class="text-muted">(Optional)</span></label>
                            <select class="form-select" id="newUserSuffix">
                                <option value="">None</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; font-size:1.1rem;">Email Address</label>
                        <input type="email" class="form-control" id="newUserEmail" placeholder="Your email address">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; font-size:1.1rem;">Role</label>
                        <select class="form-select" id="newUserRole">
                            <option value="">-</option>
                            <option value="1">Admin</option>
                            <option value="2">Hospital</option>
                            <option value="3">Staff</option>
                        </select>
                    </div>
                    <div class="mb-3" id="subroleContainer" style="display: none;">
                        <label class="form-label" style="font-weight:600; font-size:1.1rem;">Staff Subrole</label>
                        <select class="form-select" id="newUserSubrole">
                            <option value="">-</option>
                            <option value="reviewer">Reviewer</option>
                            <option value="interviewer">Interviewer</option>
                            <option value="phlebotomist">Phlebotomist</option>
                            <option value="physician">Physician</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" style="font-weight:600; font-size:1.1rem;">Password</label>
                        <input type="password" class="form-control" id="newUserPassword" placeholder="Your password">
                    </div>
                    <div class="small text-muted mt-2" id="createUserError" style="display:none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="createUserSubmitBtn">Add User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Add User Modal -->
    <div class="modal fade" id="confirmAddUserModal" tabindex="-1" aria-labelledby="confirmAddUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmAddUserModalLabel">
                        <i class="fas fa-user-plus me-2"></i>
                        Confirm Add User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center">Are you sure you want to add this new user to the system?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmAddUserBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid #dee2e6;">
                    <h5 class="modal-title" id="viewUserModalLabel" style="color: #b22222; font-weight: 700; font-size: 1.5rem;">
                        User Details
                    </h5>
                    <div id="viewUserStatusBadge" style="margin-left: auto;"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <!-- User Name and ID Section -->
                    <div class="mb-4">
                        <h4 id="viewUserName" style="font-weight: 700; color: #000; margin-bottom: 0.5rem;"></h4>
                        <p id="viewUserId" style="color: #6c757d; margin: 0; font-size: 0.9rem;">User ID: <span id="viewUserIdValue"></span></p>
                    </div>
                    
                    <hr style="margin: 1rem 0; border-color: #dee2e6;">
                    
                    <!-- Form Fields Section -->
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #000;">Email Address</label>
                        <input type="email" class="form-control" id="viewUserEmail" readonly style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #000;">Role</label>
                        <select class="form-select" id="viewUserRole" disabled style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; color: #000;">Password</label>
                        <input type="password" class="form-control" id="viewUserPassword" readonly style="background-color: #f8f9fa; border: 1px solid #dee2e6;" value="********">
                    </div>
                    
                    <!-- Footer Information -->
                    <div class="row mt-4" style="font-size: 0.85rem; color: #6c757d;">
                        <div class="col-6">
                            <span>Last Login: <span id="viewUserLastLogin">Not available</span></span>
                        </div>
                        <div class="col-6 text-end">
                            <span>Account Creation Date: <span id="viewUserCreatedDate">Not available</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivate Confirmation Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deactivateModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Deactivation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-user-times text-danger" style="font-size: 2.5rem;"></i>
                    </div>
                    <p class="text-center mb-2">Are you sure you want to deactivate this account?</p>
                    <p class="text-center text-muted">The user will lose access.</p>
                    <div class="alert alert-warning mt-3">
                        <strong>User:</strong> <span id="deactivateUserName"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeactivateBtn">
                        <i class="fas fa-user-times me-2"></i>Deactivate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Activate Confirmation Modal -->
    <div class="modal fade" id="activateModal" tabindex="-1" aria-labelledby="activateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="activateModalLabel">
                        <i class="fas fa-user-check me-2"></i>
                        Confirm Reactivation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-user-check text-success" style="font-size: 2.5rem;"></i>
                    </div>
                    <p class="text-center mb-2">Are you sure you want to reactivate this account?</p>
                    <p class="text-center text-muted">The user will regain access.</p>
                    <div class="alert alert-info mt-3">
                        <strong>User:</strong> <span id="activateUserName"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmActivateBtn">Activate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Deactivated Success Modal -->
    <div class="modal fade" id="userDeactivatedModal" tabindex="-1" aria-labelledby="userDeactivatedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="userDeactivatedModalLabel">
                        <i class="fas fa-user-times me-2"></i>
                        User Deactivated
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center">User account has been deactivated.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- User Reactivated Success Modal -->
    <div class="modal fade" id="userActivatedModal" tabindex="-1" aria-labelledby="userActivatedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="userActivatedModalLabel">
                        <i class="fas fa-user-check me-2"></i>
                        User Reactivated
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-center">User account has been reactivated.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentUserId = null;
        let currentUserName = null;

        // Debug: Check if elements exist
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking elements...');
            console.log('confirmActivateBtn exists:', document.getElementById('confirmActivateBtn') !== null);
            console.log('activateModal exists:', document.getElementById('activateModal') !== null);
            
            window.showConfirmationModal = function() {
                if (typeof window.openAdminDonorRegistrationModal === 'function') {
                    window.openAdminDonorRegistrationModal();
                } else {
                    console.error('Admin donor registration modal not available yet');
                    alert('Registration modal is still loading. Please try again in a moment.');
                }
            };

            document.addEventListener('click', function(event) {
                const viewBtn = event.target.closest('.view-user-btn');
                if (viewBtn) {
                    const dataset = viewBtn.dataset;
                    showViewUserModal(
                        dataset.userId || '',
                        dataset.userName || '',
                        dataset.userEmail || '',
                        dataset.userRole || '',
                        dataset.userStatus || '',
                        dataset.userCreated || '',
                        dataset.userLastLogin || ''
                    );
                    return;
                }

                const deactivateBtn = event.target.closest('.deactivate-user-btn');
                if (deactivateBtn) {
                    const dataset = deactivateBtn.dataset;
                    showDeactivateModal(dataset.userId || '', dataset.userName || '');
                    return;
                }

                const activateBtn = event.target.closest('.activate-user-btn');
                if (activateBtn) {
                    const dataset = activateBtn.dataset;
                    showActivateModal(dataset.userId || '', dataset.userName || '');
                    return;
                }
            });
        });

        // Show deactivate confirmation modal
        function showDeactivateModal(userId, userName) {
            currentUserId = userId;
            currentUserName = userName;
            document.getElementById('deactivateUserName').textContent = userName;
            const modal = new bootstrap.Modal(document.getElementById('deactivateModal'));
            modal.show();
        }

        // Open Create User modal
        document.getElementById('openCreateUserModalBtn').addEventListener('click', function() {
            document.getElementById('newUserFirstName').value = '';
            document.getElementById('newUserSurname').value = '';
            document.getElementById('newUserMiddleName').value = '';
            document.getElementById('newUserSuffix').value = '';
            document.getElementById('newUserEmail').value = '';
            document.getElementById('newUserRole').value = '';
            document.getElementById('newUserSubrole').value = '';
            document.getElementById('newUserPassword').value = '';
            document.getElementById('subroleContainer').style.display = 'none';
            const err = document.getElementById('createUserError');
            err.style.display = 'none';
            err.textContent = '';
            const modal = new bootstrap.Modal(document.getElementById('createUserModal'));
            modal.show();
        });

        // Show/hide subrole dropdown based on role selection
        document.getElementById('newUserRole').addEventListener('change', function() {
            const roleId = this.value;
            const subroleContainer = document.getElementById('subroleContainer');
            const subroleSelect = document.getElementById('newUserSubrole');
            
            if (roleId === '3') { // Staff role
                subroleContainer.style.display = 'block';
                subroleSelect.required = true;
            } else {
                subroleContainer.style.display = 'none';
                subroleSelect.required = false;
                subroleSelect.value = '';
            }
        });

        // Submit create user - show confirmation modal first
        document.getElementById('createUserSubmitBtn').addEventListener('click', function() {
            const firstName = (document.getElementById('newUserFirstName').value || '').trim();
            const surname = (document.getElementById('newUserSurname').value || '').trim();
            const middleName = (document.getElementById('newUserMiddleName').value || '').trim();
            const suffix = document.getElementById('newUserSuffix').value;
            const email = (document.getElementById('newUserEmail').value || '').trim();
            const roleId = document.getElementById('newUserRole').value;
            const subrole = document.getElementById('newUserSubrole').value;
            const password = document.getElementById('newUserPassword').value;
            const err = document.getElementById('createUserError');

            // Basic validation
            if (!firstName || !surname || !email || !roleId || !password) {
                err.textContent = 'Please fill in First Name, Surname, Email, Role, and Password.';
                err.style.display = 'block';
                err.className = 'small text-danger mt-2';
                return;
            }

            // Additional validation for Staff role
            if (roleId === '3' && !subrole) {
                err.textContent = 'Please select a Staff subrole.';
                err.style.display = 'block';
                err.className = 'small text-danger mt-2';
                return;
            }

            // Store form data for later use
            window.pendingUserData = { firstName, surname, middleName, suffix, email, roleId, subrole, password };

            // Close create user modal and show confirmation modal
            const createModal = bootstrap.Modal.getInstance(document.getElementById('createUserModal'));
            createModal.hide();
            
            setTimeout(() => {
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmAddUserModal'));
                confirmModal.show();
            }, 300);
        });

        // Handle confirmation modal
        document.getElementById('confirmAddUserBtn').addEventListener('click', function() {
            const { firstName, surname, middleName, suffix, email, roleId, subrole, password } = window.pendingUserData;
            const err = document.getElementById('createUserError');

            const btn = document.getElementById('confirmAddUserBtn');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';

            fetch('../api/create_staff_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    first_name: firstName,
                    surname: surname,
                    middle_name: middleName || null,
                    suffix: suffix || null,
                    email, 
                    password, 
                    role_id: parseInt(roleId, 10),
                    subrole: subrole || null
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmAddUserModal'));
                    confirmModal.hide();
                    
                    // Show success modal
                    setTimeout(() => {
                        const successModal = new bootstrap.Modal(document.getElementById('userActivatedModal'));
                        document.querySelector('#userActivatedModal .modal-title').innerHTML = '<i class="fas fa-user-check me-2"></i>User Added';
                        document.querySelector('#userActivatedModal .modal-body').innerHTML = '<p class="text-center">User successfully added.</p>';
                        successModal.show();
                        setTimeout(() => location.reload(), 1500);
                    }, 300);
                } else {
                    // Show error in create user modal
                    const createModal = new bootstrap.Modal(document.getElementById('createUserModal'));
                    createModal.show();
                    err.textContent = (data && data.message) ? data.message : 'Failed to create user.';
                    err.style.display = 'block';
                    err.className = 'small text-danger mt-2';
                }
            })
            .catch(e => {
                // Show error in create user modal
                const createModal = new bootstrap.Modal(document.getElementById('createUserModal'));
                createModal.show();
                err.textContent = 'Network error while creating user.';
                err.style.display = 'block';
                err.className = 'small text-danger mt-2';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
        });

        // Show activate confirmation modal
        function showActivateModal(userId, userName) {
            console.log('showActivateModal called with:', userId, userName);
            currentUserId = userId;
            currentUserName = userName;
            document.getElementById('activateUserName').textContent = userName;
            const modal = new bootstrap.Modal(document.getElementById('activateModal'));
            modal.show();
        }

        // Show view user modal
        function showViewUserModal(userId, userName, userEmail, userRole, userStatus, createdAt, lastLoginAt) {
            // Set user name and ID
            document.getElementById('viewUserName').textContent = userName;
            document.getElementById('viewUserIdValue').textContent = userId;
            
            // Set email
            document.getElementById('viewUserEmail').value = userEmail;
            
            // Set role in dropdown
            const roleSelect = document.getElementById('viewUserRole');
            roleSelect.innerHTML = `<option value="${userRole}" selected>${userRole}</option>`;
            
            // Display the status badge in header
            const statusBadge = document.getElementById('viewUserStatusBadge');
            if (userStatus === 'Active') {
                statusBadge.innerHTML = '<span class="badge bg-success">Active</span>';
            } else {
                statusBadge.innerHTML = '<span class="badge bg-secondary">Inactive</span>';
            }
            
            // Format and display dates
            document.getElementById('viewUserLastLogin').textContent = formatDate(lastLoginAt, true);
            document.getElementById('viewUserCreatedDate').textContent = formatDate(createdAt, false);
            
            const modal = new bootstrap.Modal(document.getElementById('viewUserModal'));
            modal.show();
        }

        // Format dates for display
        function formatDate(dateString, includeTime = false) {
            if (!dateString || dateString === '' || dateString === 'null' || dateString === 'undefined') {
                return 'Not available';
            }
            
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    return 'Not available';
                }
                
                const day = date.getDate().toString().padStart(2, '0');
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const year = date.getFullYear();
                
                if (includeTime) {
                    const hours = date.getHours();
                    const minutes = date.getMinutes().toString().padStart(2, '0');
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    const displayHours = hours % 12 || 12;
                    return `${day}/${month}/${year} ${displayHours}:${minutes} ${ampm}`;
                }
                
                return `${day}/${month}/${year}`;
            } catch (error) {
                console.error('Date formatting error:', error);
                return 'Not available';
            }
        }

        // Handle deactivate confirmation
        document.getElementById('confirmDeactivateBtn').addEventListener('click', function() {
            if (currentUserId) {
                // Close the confirmation modal
                const confirmModal = bootstrap.Modal.getInstance(document.getElementById('deactivateModal'));
                confirmModal.hide();
                
                // Show loading state
                const deactivateBtn = document.getElementById('confirmDeactivateBtn');
                const originalText = deactivateBtn.innerHTML;
                deactivateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deactivating...';
                deactivateBtn.disabled = true;
                
                // Call API to deactivate user
                fetch('../api/update_user_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: currentUserId,
                        is_active: false
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success modal
                        setTimeout(() => {
                            const successModal = new bootstrap.Modal(document.getElementById('userDeactivatedModal'));
                            successModal.show();
                            
                            // Auto-reload after modal is shown
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }, 300);
                    } else {
                        alert('Error: ' + data.message);
                        // Reset button
                        deactivateBtn.innerHTML = originalText;
                        deactivateBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deactivating the user.');
                    // Reset button
                    deactivateBtn.innerHTML = originalText;
                    deactivateBtn.disabled = false;
                });
            }
        });

        // Handle activate confirmation
        document.getElementById('confirmActivateBtn').addEventListener('click', function() {
            console.log('Activate button clicked, currentUserId:', currentUserId);
            if (currentUserId) {
                // Close the confirmation modal
                const confirmModal = bootstrap.Modal.getInstance(document.getElementById('activateModal'));
                confirmModal.hide();
                
                // Show loading state
                const activateBtn = document.getElementById('confirmActivateBtn');
                const originalText = activateBtn.innerHTML;
                activateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Activating...';
                activateBtn.disabled = true;
                
                // Call API to activate user
                fetch('../api/update_user_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: currentUserId,
                        is_active: true
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success modal
                        setTimeout(() => {
                            const successModal = new bootstrap.Modal(document.getElementById('userActivatedModal'));
                            successModal.show();
                            
                            // Auto-reload after modal is shown
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }, 300);
                    } else {
                        alert('Error: ' + data.message);
                        // Reset button
                        activateBtn.innerHTML = originalText;
                        activateBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while activating the user.');
                    // Reset button
                    activateBtn.innerHTML = originalText;
                    activateBtn.disabled = false;
                });
            }
        });
    </script>
    <script src="../../assets/js/admin-donor-registration-modal.js"></script>
</body>
</html>

