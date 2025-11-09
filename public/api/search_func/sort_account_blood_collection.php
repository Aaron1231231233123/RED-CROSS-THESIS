<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/filter_search_account_blood_collection/search_helpers.php';

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
while (ob_get_level()) { ob_end_clean(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = [];
}

$allowedColumns = ['no', 'donor_id', 'surname', 'first_name', 'phlebotomist'];
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
    'status' => [],
    'q' => ''
];
if (isset($payload['filters']) && is_array($payload['filters'])) {
    $filters['status'] = isset($payload['filters']['status']) && is_array($payload['filters']['status'])
        ? array_values(array_filter($payload['filters']['status'], 'strlen'))
        : [];
    $filters['q'] = isset($payload['filters']['q']) ? trim((string)$payload['filters']['q']) : '';
}

$status_filter = isset($payload['status_filter']) ? trim((string)$payload['status_filter']) : 'all';
$query = isset($payload['query']) ? trim((string)$payload['query']) : '';
if ($query !== '') {
    $filters['q'] = $query;
}

$records_per_page = isset($payload['per_page']) ? (int)$payload['per_page'] : 15;
if ($records_per_page <= 0) $records_per_page = 15;
if ($records_per_page > 200) $records_per_page = 200;

$requested_page = isset($payload['page']) ? (int)$payload['page'] : 1;
if ($requested_page < 1) $requested_page = 1;

$sortOptions = null;
if ($direction !== 'default') {
    $sortOptions = ['column' => $column, 'direction' => $direction];
}

$rows = sabc_filter_rows($filters, 5000, $sortOptions, $status_filter);

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
        $payloadAttr = htmlspecialchars(json_encode($entry['payload'], JSON_HEX_APOS | JSON_HEX_QUOT));
        ?>
        <tr class="clickable-row" data-examination='<?php echo $payloadAttr; ?>'>
            <td><?php echo (int)$entry['no']; ?></td>
            <td><?php echo htmlspecialchars($entry['display_id']); ?></td>
            <td><?php echo htmlspecialchars($entry['surname']); ?></td>
            <td><?php echo htmlspecialchars($entry['first_name']); ?></td>
            <td><?php echo htmlspecialchars($entry['phlebotomist']); ?></td>
            <td><?php echo $entry['status_html']; ?></td>
            <td style="text-align: center;">
                <?php if (!empty($entry['needs_review'])): ?>
                    <button type='button' class='btn btn-success btn-sm collect-btn' data-examination='<?php echo $payloadAttr; ?>' title='Collect Blood'>
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
], JSON_UNESCAPED_SLASHES);
exit;


