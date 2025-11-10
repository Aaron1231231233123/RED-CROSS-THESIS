<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/filter_search_accont_physical_exam/filter_helpers.php';

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
while (ob_get_level()) { ob_end_clean(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = [];
}

$allowedColumns = ['no', 'date', 'surname', 'first_name', 'physician'];
$allowedDirections = ['asc', 'desc', 'default'];

$column = isset($payload['column']) ? strtolower(trim($payload['column'])) : 'no';
$direction = isset($payload['direction']) ? strtolower(trim($payload['direction'])) : 'default';

if (!in_array($column, $allowedColumns, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid column']);
    exit;
}
if (!in_array($direction, $allowedDirections, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid direction']);
    exit;
}

$filters = [
    'donor_type' => [],
    'status' => []
];
if (isset($payload['filters']) && is_array($payload['filters'])) {
    $filters['donor_type'] = isset($payload['filters']['donor_type']) && is_array($payload['filters']['donor_type'])
        ? array_values(array_filter($payload['filters']['donor_type'], 'strlen'))
        : [];
    $filters['status'] = isset($payload['filters']['status']) && is_array($payload['filters']['status'])
        ? array_values(array_filter($payload['filters']['status'], 'strlen'))
        : [];
}

$status_filter = isset($payload['status_filter']) ? trim($payload['status_filter']) : 'all';
$query = isset($payload['query']) ? trim($payload['query']) : '';

$records_per_page = isset($payload['per_page']) ? (int)$payload['per_page'] : 15;
if ($records_per_page <= 0) $records_per_page = 15;
if ($records_per_page > 200) $records_per_page = 200;

$requested_page = isset($payload['page']) ? (int)$payload['page'] : 1;
if ($requested_page < 1) $requested_page = 1;

$sortOptions = null;
if ($direction !== 'default') {
    $sortOptions = ['column' => $column, 'direction' => $direction];
}

$rows = fpe_build_filtered_rows($filters, 5000, $query, $sortOptions, $status_filter);

$total_records = count($rows);
$total_pages = $total_records > 0 ? (int)ceil($total_records / $records_per_page) : 0;

if ($total_pages > 0 && $requested_page > $total_pages) {
    $requested_page = $total_pages;
}

$page_start = ($requested_page - 1) * $records_per_page;
if ($page_start < 0) $page_start = 0;

$pageRows = array_slice($rows, $page_start, $records_per_page);
$displayRows = [];
$counter = 1;
foreach ($pageRows as $entry) {
    $entry['no'] = $counter++;
    $displayRows[] = $entry;
}

ob_start();
if (!empty($displayRows)) {
    foreach ($displayRows as $entry) {
        $payloadJson = htmlspecialchars(json_encode($entry['payload'], JSON_HEX_APOS | JSON_HEX_QUOT));
        $dateStr = 'Unknown';
        try {
            $dt = isset($entry['date']) ? new DateTime($entry['date']) : null;
            if ($dt) $dateStr = $dt->format('F j, Y');
        } catch (Exception $e) {}
        $statusLower = strtolower($entry['status'] ?? '');
        $badge = 'bg-warning';
        if ($statusLower === 'accepted') $badge = 'bg-success';
        elseif ($statusLower === 'ineligible') $badge = 'bg-danger';
        elseif ($statusLower === 'deferred') $badge = 'bg-secondary';
        $isEditable = !empty($entry['is_editable']);
        ?>
        <tr class="clickable-row" data-screening='<?php echo $payloadJson; ?>'>
            <td><?php echo (int)$entry['no']; ?></td>
            <td><?php echo htmlspecialchars($dateStr); ?></td>
            <td><?php echo htmlspecialchars(strtoupper($entry['surname'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($entry['first_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($entry['physician'] ?? ''); ?></td>
            <td><span class="<?php echo ($entry['donor_type'] === 'Returning') ? 'type-returning' : 'type-new'; ?>"><?php echo htmlspecialchars($entry['donor_type'] ?? 'New'); ?></span></td>
            <td><span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($entry['status'] ?? 'Pending'); ?></span></td>
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
    echo '<tr><td colspan="8" class="text-muted">No records found</td></tr>';
}
$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'count' => count($displayRows),
    'html' => $html,
    'column' => $column,
    'direction' => $direction,
    'current_page' => $requested_page,
    'records_per_page' => $records_per_page,
    'total_pages' => $total_pages,
    'total_records' => $total_records
]);
exit;



