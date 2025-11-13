<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/filter_search_hospital_blood_requests/filter_helpers.php';

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
while (ob_get_level()) { ob_end_clean(); }

if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success'=>false,'message'=>'Not authenticated']); 
    exit; 
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload) $payload = [];

$user_id = $_SESSION['user_id'];
$statuses = isset($payload['status']) && is_array($payload['status']) ? $payload['status'] : [];
$q = isset($payload['q']) ? trim($payload['q']) : '';

// Build filtered rows
$rows = fbr_build_filtered_rows([
    'status' => $statuses
], $user_id, 150, $q);

// Generate HTML for table rows
ob_start();
if (!empty($rows)) {
    $rowNum = 1;
    foreach ($rows as $request) {
        $status = $request['status'] ?? 'Pending';
        $requestId = htmlspecialchars($request['request_id'] ?? '');
        $patientName = htmlspecialchars($request['patient_name'] ?? '');
        $patientAge = htmlspecialchars($request['patient_age'] ?? '');
        $patientGender = htmlspecialchars($request['patient_gender'] ?? '');
        $bloodType = htmlspecialchars($request['patient_blood_type'] ?? '');
        $rhFactor = htmlspecialchars($request['rh_factor'] ?? '');
        $units = htmlspecialchars($request['units_requested'] ?? '');
        $whenNeeded = $request['when_needed'] ?? '';
        $diagnosis = htmlspecialchars($request['patient_diagnosis'] ?? '');
        $approvedBy = htmlspecialchars($request['approved_by'] ?? '');
        $approvedDate = htmlspecialchars($request['approved_date'] ?? '');
        $declinedBy = htmlspecialchars($request['declined_by'] ?? '');
        $declinedDate = htmlspecialchars($request['last_updated'] ?? '');
        $declineReason = htmlspecialchars($request['decline_reason'] ?? '');
        $handedOverBy = htmlspecialchars($request['handed_over_by'] ?? '');
        $handedOverDate = htmlspecialchars($request['handed_over_date'] ?? '');
        $physicianName = htmlspecialchars($request['physician_name'] ?? '');
        
        // Format when_needed date
        $whenNeededFormatted = 'EMPTY_FIELD';
        if (!empty($whenNeeded)) {
            try {
                $whenNeededFormatted = date('m/d/Y', strtotime($whenNeeded));
            } catch (Exception $e) {
                $whenNeededFormatted = 'Invalid Date';
            }
        }
        
        // Determine status badge
        $statusBadge = '';
        if ($status === 'Pending') {
            $statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
        } elseif ($status === 'Approved') {
            $statusBadge = '<span class="badge bg-primary">Approved</span>';
        } elseif ($status === 'Printed') {
            $statusBadge = '<span class="badge bg-primary">Approved</span>';
        } elseif ($status === 'Completed') {
            $statusBadge = '<span class="badge bg-success">Completed</span>';
        } elseif ($status === 'Handed_over') {
            $statusBadge = '<span class="badge bg-primary">Approved</span>';
        } elseif ($status === 'Declined') {
            $statusBadge = '<span class="badge bg-danger">Declined</span>';
        } elseif ($status === 'Rescheduled') {
            $statusBadge = '<span class="badge bg-info text-dark">Rescheduled</span>';
        } else {
            $statusBadge = '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
        }
        
        // Determine action button
        $actionButton = '';
        $isAsap = isset($request['is_asap']) && ($request['is_asap'] === true || $request['is_asap'] === 'true' || $request['is_asap'] === 1 || $request['is_asap'] === '1');
        
        if ($status === 'Pending') {
            $actionButton = '<button class="btn btn-sm btn-primary view-btn" 
                data-request-id="' . $requestId . '"
                data-patient-name="' . $patientName . '"
                data-patient-age="' . $patientAge . '"
                data-patient-gender="' . $patientGender . '"
                data-patient-diagnosis="' . $diagnosis . '"
                data-blood-type="' . $bloodType . '"
                data-rh-factor="' . $rhFactor . '"
                data-component="Whole Blood"
                data-units="' . $units . '"
                data-when-needed="' . htmlspecialchars($whenNeeded) . '"
                data-debug-when-needed="' . htmlspecialchars($whenNeeded ?? 'NULL') . '"
                data-is-asap="' . ($isAsap ? 'true' : 'false') . '"
                data-approved-by="' . $approvedBy . '"
                data-approved-date="' . $approvedDate . '"
                data-declined-by="' . $declinedBy . '"
                data-declined-date="' . $declinedDate . '"
                data-decline-reason="' . $declineReason . '"
                data-handed-over-by="' . $handedOverBy . '"
                data-handed-over-date="' . $handedOverDate . '"
                data-physician-name="' . $physicianName . '"
                data-status="' . htmlspecialchars($status) . '">
                <i class="fas fa-eye"></i>
            </button>';
        } elseif ($status === 'Approved' || $status === 'Accepted' || $status === 'Confirmed') {
            $actionButton = '<button class="btn btn-sm btn-info print-btn" 
                data-request-id="' . $requestId . '"
                data-patient-name="' . $patientName . '"
                data-patient-age="' . $patientAge . '"
                data-patient-gender="' . $patientGender . '"
                data-patient-diagnosis="' . $diagnosis . '"
                data-blood-type="' . $bloodType . '"
                data-rh-factor="' . $rhFactor . '"
                data-component="Whole Blood"
                data-units="' . $units . '"
                data-when-needed="' . htmlspecialchars($whenNeeded) . '"
                data-debug-when-needed="' . htmlspecialchars($whenNeeded ?? 'NULL') . '"
                data-is-asap="' . ($isAsap ? 'true' : 'false') . '"
                data-approved-by="' . $approvedBy . '"
                data-approved-date="' . $approvedDate . '"
                data-declined-by="' . $declinedBy . '"
                data-declined-date="' . $declinedDate . '"
                data-decline-reason="' . $declineReason . '"
                data-handed-over-by="' . $handedOverBy . '"
                data-handed-over-date="' . $handedOverDate . '"
                data-physician-name="' . $physicianName . '"
                data-status="' . htmlspecialchars($status) . '">
                <i class="fas fa-print"></i>
            </button>';
        } elseif ($status === 'Printed') {
            $actionButton = '<button class="btn btn-sm btn-primary view-btn" 
                data-request-id="' . $requestId . '"
                data-patient-name="' . $patientName . '"
                data-patient-age="' . $patientAge . '"
                data-patient-gender="' . $patientGender . '"
                data-patient-diagnosis="' . $diagnosis . '"
                data-blood-type="' . $bloodType . '"
                data-rh-factor="' . $rhFactor . '"
                data-component="Whole Blood"
                data-units="' . $units . '"
                data-when-needed="' . htmlspecialchars($whenNeeded) . '"
                data-debug-when-needed="' . htmlspecialchars($whenNeeded ?? 'NULL') . '"
                data-is-asap="' . ($isAsap ? 'true' : 'false') . '"
                data-approved-by="' . $approvedBy . '"
                data-approved-date="' . $approvedDate . '"
                data-declined-by="' . $declinedBy . '"
                data-declined-date="' . $declinedDate . '"
                data-decline-reason="' . $declineReason . '"
                data-handed-over-by="' . $handedOverBy . '"
                data-handed-over-date="' . $handedOverDate . '"
                data-physician-name="' . $physicianName . '"
                data-status="' . htmlspecialchars($status) . '">
                <i class="fas fa-eye"></i>
            </button>';
        } elseif ($status === 'Handed_over') {
            $actionButton = '<button class="btn btn-sm btn-success handover-btn" 
                title="Confirm Arrival"
                data-request-id="' . $requestId . '"
                data-patient-name="' . $patientName . '"
                data-patient-age="' . $patientAge . '"
                data-patient-gender="' . $patientGender . '"
                data-patient-diagnosis="' . $diagnosis . '"
                data-blood-type="' . $bloodType . '"
                data-rh-factor="' . $rhFactor . '"
                data-component="Whole Blood"
                data-units="' . $units . '"
                data-when-needed="' . htmlspecialchars($whenNeeded) . '"
                data-debug-when-needed="' . htmlspecialchars($whenNeeded ?? 'NULL') . '"
                data-is-asap="' . ($isAsap ? 'true' : 'false') . '"
                data-approved-by="' . $approvedBy . '"
                data-approved-date="' . $approvedDate . '"
                data-declined-by="' . $declinedBy . '"
                data-declined-date="' . $declinedDate . '"
                data-decline-reason="' . $declineReason . '"
                data-handed-over-by="' . $handedOverBy . '"
                data-handed-over-date="' . $handedOverDate . '"
                data-physician-name="' . $physicianName . '"
                data-status="' . htmlspecialchars($status) . '">
                <i class="fas fa-check"></i>
            </button>';
        } else {
            $actionButton = '<button class="btn btn-sm btn-primary view-btn" 
                data-request-id="' . $requestId . '"
                data-patient-name="' . $patientName . '"
                data-patient-age="' . $patientAge . '"
                data-patient-gender="' . $patientGender . '"
                data-patient-diagnosis="' . $diagnosis . '"
                data-blood-type="' . $bloodType . '"
                data-rh-factor="' . $rhFactor . '"
                data-component="Whole Blood"
                data-units="' . $units . '"
                data-when-needed="' . htmlspecialchars($whenNeeded) . '"
                data-debug-when-needed="' . htmlspecialchars($whenNeeded ?? 'NULL') . '"
                data-is-asap="' . ($isAsap ? 'true' : 'false') . '"
                data-declined-by="' . $declinedBy . '"
                data-declined-date="' . $declinedDate . '"
                data-decline-reason="' . $declineReason . '"
                data-status="' . htmlspecialchars($status) . '">
                <i class="fas fa-eye"></i>
            </button>';
        }
        
        ?>
        <tr>
            <td><?php echo $rowNum++; ?></td>
            <td><?php echo $requestId; ?></td>
            <td><?php echo $bloodType . ($rhFactor === 'Positive' ? '+' : '-'); ?></td>
            <td><?php echo $units . ' Bags'; ?></td>
            <td><?php echo $whenNeededFormatted; ?></td>
            <td><?php echo $statusBadge; ?></td>
            <td><?php echo $actionButton; ?></td>
        </tr>
        <?php
    }
} else {
    echo '<tr><td colspan="7" class="text-center">No blood requests found.</td></tr>';
}
$html = ob_get_clean();

echo json_encode(['success'=>true,'count'=>count($rows),'html'=>$html]);
exit;
?>



