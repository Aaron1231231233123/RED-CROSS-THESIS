<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/filter_search_accont_physical_exam/filter_helpers.php';

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
while (ob_get_level()) { ob_end_clean(); }

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload) $payload = [];

$rows = fpe_build_filtered_rows([
    'donor_type' => isset($payload['donor_type']) && is_array($payload['donor_type']) ? $payload['donor_type'] : [],
    'status' => isset($payload['status']) && is_array($payload['status']) ? $payload['status'] : []
], 150, isset($payload['q']) ? $payload['q'] : '');

ob_start();
if (!empty($rows)) {
    foreach ($rows as $entry) {
        $payloadJson = htmlspecialchars(json_encode($entry['payload'], JSON_HEX_APOS|JSON_HEX_QUOT));
        $dateStr = 'Unknown';
        try { $dt = new DateTime($entry['date']); $dateStr = $dt->format('F j, Y'); } catch (Exception $e) {}
        $statusLower = strtolower($entry['status']);
        $badge = 'bg-warning';
        if ($statusLower === 'accepted') $badge = 'bg-success';
        else if (strpos($statusLower,'defer')!==false || strpos($statusLower,'reject')!==false || strpos($statusLower,'decline')!==false) $badge = 'bg-danger';
        $isEditable = !empty($entry['is_editable']);
        ?>
        <tr class="clickable-row" data-screening='<?php echo $payloadJson; ?>'>
            <td><?php echo (int)$entry['no']; ?></td>
            <td><?php echo htmlspecialchars($dateStr); ?></td>
            <td><?php echo htmlspecialchars(strtoupper($entry['surname'])); ?></td>
            <td><?php echo htmlspecialchars($entry['first_name']); ?></td>
            <td><?php echo htmlspecialchars($entry['physician']); ?></td>
            <td><span class="<?php echo ($entry['donor_type']==='Returning') ? 'type-returning' : 'type-new'; ?>"><?php echo htmlspecialchars($entry['donor_type']); ?></span></td>
            <td><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($entry['status']); ?></span></td>
            <td>
                <?php if ($isEditable): ?>
                    <button type="button" class="btn btn-warning btn-sm edit-btn" data-screening='<?php echo $payloadJson; ?>' title="Edit Physical Examination"><i class="fas fa-edit"></i></button>
                <?php else: ?>
                    <button type="button" class="btn btn-info btn-sm view-btn" data-screening='<?php echo $payloadJson; ?>' title="View Details"><i class="fas fa-eye"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
} else {
    echo '<tr><td colspan="8" class="text-muted">No matching results</td></tr>';
}
$html = ob_get_clean();

echo json_encode(['success'=>true,'count'=>count($rows),'html'=>$html]);
exit;
?>


