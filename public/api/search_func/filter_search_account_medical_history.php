<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/filter_search_account_medical_history/filter_helpers.php';

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
while (ob_get_level()) { ob_end_clean(); }

if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit; }
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

// Expect JSON body with arrays: donor_type, status, via
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload) $payload = [];

$rows = fsh_build_filtered_rows([
    'donor_type' => isset($payload['donor_type']) && is_array($payload['donor_type']) ? $payload['donor_type'] : [],
    'status' => isset($payload['status']) && is_array($payload['status']) ? $payload['status'] : [],
    'via' => isset($payload['via']) && is_array($payload['via']) ? $payload['via'] : []
], 150, isset($payload['q']) ? $payload['q'] : '');

ob_start();
if (!empty($rows)) {
    foreach ($rows as $entry) {
        ?>
        <tr data-donor-id="<?php echo $entry['donor_id']; ?>" data-stage="<?php echo htmlspecialchars($entry['stage']); ?>" data-donor-type="<?php echo htmlspecialchars($entry['donor_type']); ?>">
            <td><?php echo $entry['no']; ?></td>
            <td><?php try { $date = new DateTime($entry['date']); echo $date->format('F d, Y'); } catch (Exception $e) { echo 'N/A'; } ?></td>
            <td><?php echo htmlspecialchars($entry['surname']); ?></td>
            <td><?php echo htmlspecialchars($entry['first_name']); ?></td>
            <td><?php echo htmlspecialchars($entry['interviewer']); ?></td>
            <td><span class="<?php echo stripos($entry['donor_type'],'Returning')===0 ? 'type-returning' : 'type-new'; ?>"><?php echo htmlspecialchars($entry['donor_type']); ?></span></td>
            <td><span class="status-text"><?php echo $entry['status'] === '-' ? '-' : '<strong>' . htmlspecialchars($entry['status']) . '</strong>'; ?></span></td>
            <td><span class="badge-tag badge-registered <?php echo strtolower($entry['registered_via'])==='mobile' ? 'badge-mobile' : 'badge-system'; ?>"><?php echo htmlspecialchars($entry['registered_via']); ?></span></td>
            <td class="text-center">
                <button type="button" class="btn btn-info btn-sm view-donor-btn me-1" 
                        onclick="viewDonorFromRow('<?php echo $entry['donor_id']; ?>','<?php echo htmlspecialchars($entry['stage']); ?>','<?php echo htmlspecialchars($entry['donor_type']); ?>')"
                        title="View Details"
                        style="width: 35px; height: 30px;">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
        <?php
    }
} else {
    echo '<tr><td colspan="9" class="text-muted">No matching results</td></tr>';
}
$html = ob_get_clean();

// Return only HTML so sensitive fields are not exposed in transit logs
echo json_encode(['success' => true, 'count' => count($rows), 'html' => $html]);
exit;
?>


