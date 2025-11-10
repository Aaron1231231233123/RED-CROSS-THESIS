<?php
session_start();
require_once '../../../assets/conn/db_conn.php';
require_once '../../../assets/php_func/search_func/filter_search_account_medical_history/filter_helpers.php';

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

$column = isset($payload['column']) ? strtolower(trim($payload['column'])) : '';
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
    'status' => [],
    'via' => []
];
if (isset($payload['filters']) && is_array($payload['filters'])) {
    $filters['donor_type'] = isset($payload['filters']['donor_type']) && is_array($payload['filters']['donor_type'])
        ? array_values(array_filter($payload['filters']['donor_type'], 'strlen'))
        : [];
    $filters['status'] = isset($payload['filters']['status']) && is_array($payload['filters']['status'])
        ? array_values(array_filter($payload['filters']['status'], 'strlen'))
        : [];
    $filters['via'] = isset($payload['filters']['via']) && is_array($payload['filters']['via'])
        ? array_values(array_filter($payload['filters']['via'], 'strlen'))
        : [];
}

$query = isset($payload['query']) ? trim($payload['query']) : '';

$sortOptions = null;
if ($direction !== 'default') {
    $sortOptions = ['column' => $column, 'direction' => $direction];
}

$records_per_page = isset($payload['per_page']) ? (int)$payload['per_page'] : 15;
if ($records_per_page <= 0) $records_per_page = 15;
if ($records_per_page > 200) $records_per_page = 200;

$requested_page = isset($payload['page']) ? (int)$payload['page'] : 1;
if ($requested_page < 1) $requested_page = 1;

// Increase limit to capture dashboard-sized dataset while respecting pagination
$status_filter = isset($payload['status_filter']) ? trim($payload['status_filter']) : 'all';

$rows = fsh_build_filtered_rows($filters, 5000, $query, $sortOptions, $status_filter);

// Ensure unique donor-stage combinations, matching dashboard behavior
$seenKeys = [];
$uniqueRows = [];
foreach ($rows as $entry) {
    $key = ($entry['donor_id'] ?? '') . '_' . ($entry['stage'] ?? '');
    if (!isset($seenKeys[$key])) {
        $seenKeys[$key] = true;
        $uniqueRows[] = $entry;
    }
}

$total_records = count($uniqueRows);
$total_pages = $total_records > 0 ? (int)ceil($total_records / $records_per_page) : 0;

if ($total_pages > 0 && $requested_page > $total_pages) {
    $requested_page = $total_pages;
}

$page_start = ($requested_page - 1) * $records_per_page;
if ($page_start < 0) $page_start = 0;

$pageRows = array_slice($uniqueRows, $page_start, $records_per_page);
$displayRows = [];
$counter = 1;
foreach ($pageRows as $entry) {
    $entry['no'] = $counter++;
    $displayRows[] = $entry;
}

ob_start();
if (!empty($displayRows)) {
    foreach ($displayRows as $entry) {
        ?>
        <tr class="clickable-row" data-donor-id="<?php echo $entry['donor_id']; ?>" data-stage="<?php echo htmlspecialchars($entry['stage']); ?>" data-donor-type="<?php echo htmlspecialchars($entry['donor_type']); ?>">
            <td class="text-center"><?php echo $entry['no']; ?></td>
            <td class="text-center">
                <?php
                if (!empty($entry['date'])) {
                    try {
                        $date = new DateTime($entry['date']);
                        echo $date->format('F d, Y');
                    } catch (Exception $e) {
                        echo 'N/A';
                    }
                } else {
                    echo 'N/A';
                }
                ?>
            </td>
            <td class="text-center"><?php echo htmlspecialchars($entry['surname']); ?></td>
            <td class="text-center"><?php echo htmlspecialchars($entry['first_name']); ?></td>
            <td class="text-center"><?php echo htmlspecialchars($entry['physician'] ?? $entry['interviewer'] ?? 'N/A'); ?></td>
            <td class="text-center">
                <span class="<?php echo stripos($entry['donor_type'], 'returning') === 0 ? 'type-returning' : 'type-new'; ?>">
                    <?php echo htmlspecialchars($entry['donor_type']); ?>
                </span>
            </td>
            <td class="text-center">
                <span style="display: block; text-align: center; width: 100%;">
                    <?php
                    $status = $entry['status'] ?? '-';
                    if ($status === '-') {
                        echo '-';
                    } else {
                        $lower = strtolower($status);
                        if ($lower === 'ineligible') {
                            echo '<i class="fas fa-flag me-1" style="color:#dc3545"></i><strong>' . htmlspecialchars($status) . '</strong>';
                        } else {
                            echo '<strong>' . htmlspecialchars($status) . '</strong>';
                        }
                    }
                    ?>
                </span>
            </td>
            <td class="text-center">
                <span class="badge-tag badge-registered <?php echo strtolower($entry['registered_via']) === 'mobile' ? 'badge-mobile' : 'badge-system'; ?>">
                    <?php echo htmlspecialchars($entry['registered_via']); ?>
                </span>
            </td>
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

echo json_encode([
    'success' => true,
    'count' => count($displayRows),
    'html' => $html,
    'direction' => $direction,
    'column' => $column,
    'current_page' => $requested_page,
    'records_per_page' => $records_per_page,
    'total_pages' => $total_pages,
    'total_records' => $total_records,
    'status_filter' => $status_filter
]);
exit;

