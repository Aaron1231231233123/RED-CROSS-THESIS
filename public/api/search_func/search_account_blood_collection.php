<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/search_account_blood_collection/search_helpers.php';

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
while (ob_get_level()) { ob_end_clean(); }

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit; }
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] != 3) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(['success'=>true,'count'=>0,'html'=>'<tr><td colspan="7" class="text-muted">Name can\'t be found</td></tr>']);
    exit;
}

$rows = sabc_search_rows($q, 80);

ob_start();
if (!empty($rows)) {
    foreach ($rows as $entry) {
        $payload = htmlspecialchars(json_encode($entry['payload'], JSON_HEX_APOS|JSON_HEX_QUOT));
        ?>
        <tr class="clickable-row" data-examination='<?php echo $payload; ?>'>
            <td><?php echo (int)$entry['no']; ?></td>
            <td><?php echo htmlspecialchars($entry['display_id']); ?></td>
            <td><?php echo htmlspecialchars($entry['surname']); ?></td>
            <td><?php echo htmlspecialchars($entry['first_name']); ?></td>
            <td><?php echo $entry['status_html']; ?></td>
            <td><?php echo htmlspecialchars($entry['phlebotomist']); ?></td>
            <td style="text-align: center;">
                <?php if (!empty($entry['needs_review'])): ?>
                    <button type='button' class='btn btn-success btn-sm collect-btn' data-examination='<?php echo $payload; ?>' title='Collect Blood'>
                        <i class='fas fa-tint'></i> Collect
                    </button>
                <?php else: ?>
                    <button type='button' class='btn btn-info btn-sm view-donor-btn' data-donor-id='<?php echo (int)$entry['payload']['donor_id']; ?>' title='View Blood Collection Details'>
                        <i class='fas fa-eye'></i>
                    </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
} else {
    echo '<tr><td colspan="7" class="text-muted">No records found</td></tr>';
}
$html = ob_get_clean();

echo json_encode(['success'=>true,'count'=>count($rows),'html'=>$html], JSON_UNESCAPED_SLASHES);
exit;
?>


