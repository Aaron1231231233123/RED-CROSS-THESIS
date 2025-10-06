<?php
header('Content-Type: application/json; charset=UTF-8');
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

require_once '../../assets/conn/db_conn.php';
@include_once __DIR__ . '/../Dashboards/module/optimized_functions.php';

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function get_param($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

$action = get_param('action');
$q = get_param('q');
$category = get_param('category', 'all');
$page = max(1, (int) get_param('page', '1'));
$limit = max(1, min(200, (int) get_param('limit', '50')));
$offset = ($page - 1) * $limit;

// NOTE: This is a scaffold. Replace with real dataset-specific queries.
// It returns empty results by default to avoid breaking pages.
switch ($action) {
    case 'donors':
        // Basic donor search against donor_form using Supabase REST filters
        try {
            if (!function_exists('supabaseRequest')) {
                respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
            }

            $select = 'donor_id,surname,first_name,donor_type,registered_via';
            $orParts = [];
            $searchTerm = trim($q);
            if ($searchTerm !== '') {
                $encoded = rawurlencode('%' . $searchTerm . '%');
                // Use ilike across several columns; if a column doesn't exist, Supabase will ignore that filter
                $orParts[] = 'donor_id.ilike.' . $encoded;
                $orParts[] = 'surname.ilike.' . $encoded;
                $orParts[] = 'first_name.ilike.' . $encoded;
                $orParts[] = 'donor_type.ilike.' . $encoded;
                $orParts[] = 'registered_via.ilike.' . $encoded;
            }

            $query = 'donor_form?select=' . $select;
            if (!empty($orParts)) {
                $query .= '&or=(' . implode(',', $orParts) . ')';
            }
            $query .= '&order=donor_id.desc&limit=' . (int)$limit . '&offset=' . (int)$offset;

            $resp = supabaseRequest($query, 'GET');
            $rows = isset($resp['data']) && is_array($resp['data']) ? $resp['data'] : [];
            // Map to array-of-arrays compatible with default renderer [donor_id, surname, first_name, donor_type, registered_via, status]
            $results = [];
            foreach ($rows as $r) {
                $results[] = [
                    isset($r['donor_id']) ? $r['donor_id'] : '',
                    isset($r['surname']) ? $r['surname'] : '',
                    isset($r['first_name']) ? $r['first_name'] : '',
                    isset($r['donor_type']) ? $r['donor_type'] : '',
                    isset($r['registered_via']) ? $r['registered_via'] : '',
                    '' // status placeholder
                ];
            }
            respond(['success' => true, 'results' => $results, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => count($results)]]);
        } catch (Exception $e) {
            respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
        }
        break;
    case 'blood_inventory':
        respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
        break;
    case 'hospital_requests':
        respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
        break;
    case 'users':
        respond(['success' => true, 'results' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0]]);
        break;
    default:
        respond(['success' => false, 'message' => 'Invalid action'], 400);
}
?>


