<?php
// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
require_once '../../assets/conn/db_conn.php';
// Shared helpers (provides supabaseRequest)
@include_once __DIR__ . '/module/optimized_functions.php';

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

// Fetch users directly from users table (populate from provided schema)
$users = [];
try {
    if (function_exists('querySQL')) {
        $records = querySQL('users', 'user_id,email,role_id,user_staff_roles');
        if (is_array($records)) {
            foreach ($records as $u) {
                $roleId = isset($u['role_id']) ? (int)$u['role_id'] : null;
                $staffRole = $u['user_staff_roles'] ?? null;
                // Map role for display
                $roleDisplay = '';
                if ($roleId === 1) {
                    $roleDisplay = 'Admin';
                } elseif ($roleId === 2) {
                    $roleDisplay = 'Hospital';
                } elseif ($roleId === 3) {
                    $roleDisplay = !empty($staffRole) ? $staffRole : 'Staff';
                }
                $users[] = [
                    'id' => $u['user_id'] ?? '',
                    'email' => $u['email'] ?? '',
                    'role' => $roleDisplay,
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
main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 {
	margin-left: 280px !important;
	margin-top: 70px !important;
	padding-top: 20px;
}
.dashboard-home-header {
	position: fixed;
	top: 0;
	left: 240px;
	width: calc(100% - 240px);
	background-color: #f8f9fa;
	padding: 15px;
	min-height: 70px;
	display: flex;
	align-items: center;
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
.sidebar-main-content { flex-grow: 1; padding-bottom: 80px; }
.logout-container { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px 15px; border-top: 1px solid #ddd; background-color: #ffffff; }
.logout-link { color: #dc3545 !important; }
.dashboard-home-sidebar .nav-link { color: #333; padding: 10px 15px; margin: 2px 0; border-radius: 4px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; font-size: 0.9rem; }
.dashboard-home-sidebar .nav-link i { margin-right: 10px; font-size: 0.9rem; width: 16px; text-align: center; }
.dashboard-home-sidebar .nav-link:hover { background-color: #f8f9fa; color: #dc3545; }
.dashboard-home-sidebar .nav-link.active { background-color: #dc3545; color: #fff; }
.content-wrapper { background: #fff; box-shadow: 0 0 15px rgba(0,0,0,0.05); margin-top: 0; border-radius: 12px; padding: 24px; }
/* Match responsive behavior with other dashboards */
@media (max-width: 992px) {
	.dashboard-home-sidebar { width: 200px; }
	.dashboard-home-header { left: 200px; width: calc(100% - 200px); }
	main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 { margin-left: 200px; }
}
@media (max-width: 768px) {
	.dashboard-home-sidebar { width: 0; padding: 0; overflow: hidden; }
	.dashboard-home-header { left: 0; width: 100%; }
	main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 { margin-left: 0 !important; padding: 10px; }
}
/* Ensure mid-size behavior matches */
@media (max-width: 991px) {
    main.col-md-9.ms-sm-auto.col-lg-10.px-md-4 { margin-left: 240px !important; }
}
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="dashboard-home-header bg-light p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Blood Donor Management System</h4>
                <div></div>
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
                        <a href="#" class="nav-link">
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
                            <button class="btn btn-danger"><i class="fa fa-user-plus me-2"></i>Add User</button>
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
                                        <tr class="bg-danger text-white">
                                            <th>User ID</th>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No users found. Connect to your users table to populate.</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($u['id'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($u['role'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                            <td></td>
                                            <td>
                                                <button class="btn btn-sm btn-success me-1">Activate</button>
                                                <button class="btn btn-sm btn-secondary">Deactivate</button>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

