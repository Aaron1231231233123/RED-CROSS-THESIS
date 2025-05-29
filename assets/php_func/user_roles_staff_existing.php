<?php
// user_roles_staff_existing.php
// Sets $user_staff_roles_existing for staff roles: reviewer, interviewer, phlebotomist, physician

$user_staff_roles_existing = null;
if (isset($_SESSION['role_id'])) {
    switch ($_SESSION['role_id']) {
        case 3:
            $user_staff_roles_existing = 'reviewer';
            break;
        case 4:
            $user_staff_roles_existing = 'interviewer';
            break;
        case 5:
            $user_staff_roles_existing = 'phlebotomist';
            break;
        case 6:
            $user_staff_roles_existing = 'physician';
            break;
        default:
            $user_staff_roles_existing = null;
    }
} elseif (isset($_SESSION['user_staff_role'])) {
    $role = strtolower($_SESSION['user_staff_role']);
    if (in_array($role, ['reviewer', 'interviewer', 'phlebotomist', 'physician'])) {
        $user_staff_roles_existing = $role;
    }
} 