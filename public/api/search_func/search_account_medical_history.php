<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/search_account_medical_history/search_helpers.php';

// Ensure clean JSON output
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
// Clear any active buffers that might prepend whitespace/HTML
while (ob_get_level()) { ob_end_clean(); }

// Authz: allow only staff role_id 3 (same as dashboard)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['success' => true, 'count' => 0, 'rows' => [], 'html' => '<tr><td colspan="9" class="text-muted">Name can\'t be found</td></tr>']);
    exit;
}

$rows = shm_search_medical_history_rows($q, 50);

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
    echo '<tr><td colspan="9" class="text-muted">Name can\'t be found</td></tr>';
}
$html = ob_get_clean();

// Return only the rendered HTML to avoid exposing raw data in network payloads
echo json_encode(['success' => true, 'count' => count($rows), 'html' => $html], JSON_UNESCAPED_SLASHES);
exit;
?>


