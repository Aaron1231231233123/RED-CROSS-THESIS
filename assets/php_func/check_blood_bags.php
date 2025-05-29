<?php
require_once __DIR__ . '/../conn/db_conn.php';

function checkAndDeductBloodBags($request_id, $units_requested) {
    // Fetch the request details
    $request_url = SUPABASE_URL . '/rest/v1/blood_requests?request_id=eq.' . $request_id;
    $ch = curl_init();
    $headers = [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    curl_setopt($ch, CURLOPT_URL, $request_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code !== 200) {
        curl_close($ch);
        return [
            'success' => false,
            'message' => 'Failed to fetch blood request. HTTP Code: ' . $http_code
        ];
    }
    $request_data = json_decode($response, true);
    if (empty($request_data)) {
        curl_close($ch);
        return [
            'success' => false,
            'message' => 'No blood request found with ID: ' . $request_id
        ];
    }
    $request_data = $request_data[0];
    $requested_blood_type = $request_data['patient_blood_type'];
    $requested_rh_factor = $request_data['rh_factor'];
    $blood_type_full = $requested_blood_type . ($requested_rh_factor === 'Positive' ? '+' : '-');

    // Fetch eligible blood bags
    $eligibilityData = querySQL(
        'eligibility',
        'eligibility_id,donor_id,blood_type,donation_type,blood_bag_type,collection_successful,unit_serial_number,collection_start_time,start_date,end_date,status,blood_collection_id',
        ['collection_successful' => 'eq.true']
    );
    $available_bags = [];
    $today = new DateTime();
    foreach ($eligibilityData as $item) {
        if (!empty($item['blood_collection_id'])) {
            $bloodCollectionData = querySQL('blood_collection', '*', ['blood_collection_id' => 'eq.' . $item['blood_collection_id']]);
            $bloodCollectionData = isset($bloodCollectionData[0]) ? $bloodCollectionData[0] : null;
        } else {
            $bloodCollectionData = null;
        }
        $collectionDate = new DateTime($item['collection_start_time']);
        $expirationDate = clone $collectionDate;
        $expirationDate->modify('+35 days');
        $isExpired = ($today > $expirationDate);
        $amount_taken = $bloodCollectionData && isset($bloodCollectionData['amount_taken']) ? intval($bloodCollectionData['amount_taken']) : 0;
        if ($amount_taken > 0 && !$isExpired) {
            $available_bags[] = [
                'eligibility_id' => $item['eligibility_id'],
                'blood_collection_id' => $item['blood_collection_id'],
                'blood_type' => $item['blood_type'],
                'amount_taken' => $amount_taken,
                'collection_start_time' => $item['collection_start_time'],
                'expiration_date' => $expirationDate->format('Y-m-d'),
            ];
        }
    }
    $units_found = 0;
    $collections_to_update = [];
    $remaining_units = $units_requested;
    $deducted_by_type = [];
    foreach ($available_bags as $bag) {
        if ($remaining_units <= 0) break;
        if ($bag['blood_type'] === $blood_type_full) {
            $available_units = $bag['amount_taken'];
            if ($available_units > 0) {
                $units_to_take = min($available_units, $remaining_units);
                $units_found += $units_to_take;
                $remaining_units -= $units_to_take;
                if (!isset($deducted_by_type[$bag['blood_type']])) {
                    $deducted_by_type[$bag['blood_type']] = 0;
                }
                $deducted_by_type[$bag['blood_type']] += $units_to_take;
                $bag['units_to_take'] = $units_to_take;
                $collections_to_update[] = $bag;
            }
        }
    }
    if ($remaining_units > 0) {
        if (!function_exists('getCompatibleBloodTypes')) {
            function getCompatibleBloodTypes($blood_type, $rh_factor) {
                $is_positive = $rh_factor === 'Positive';
                $compatible_types = [];
                switch ($blood_type) {
                    case 'O':
                        if ($is_positive) {
                            $compatible_types = [
                                ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        } else {
                            $compatible_types = [
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        }
                        break;
                    case 'A':
                        if ($is_positive) {
                            $compatible_types = [
                                ['type' => 'A', 'rh' => 'Positive', 'priority' => 4],
                                ['type' => 'A', 'rh' => 'Negative', 'priority' => 3],
                                ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        } else {
                            $compatible_types = [
                                ['type' => 'A', 'rh' => 'Negative', 'priority' => 2],
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        }
                        break;
                    case 'B':
                        if ($is_positive) {
                            $compatible_types = [
                                ['type' => 'B', 'rh' => 'Positive', 'priority' => 4],
                                ['type' => 'B', 'rh' => 'Negative', 'priority' => 3],
                                ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        } else {
                            $compatible_types = [
                                ['type' => 'B', 'rh' => 'Negative', 'priority' => 2],
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        }
                        break;
                    case 'AB':
                        if ($is_positive) {
                            $compatible_types = [
                                ['type' => 'AB', 'rh' => 'Positive', 'priority' => 8],
                                ['type' => 'AB', 'rh' => 'Negative', 'priority' => 7],
                                ['type' => 'A', 'rh' => 'Positive', 'priority' => 6],
                                ['type' => 'A', 'rh' => 'Negative', 'priority' => 5],
                                ['type' => 'B', 'rh' => 'Positive', 'priority' => 4],
                                ['type' => 'B', 'rh' => 'Negative', 'priority' => 3],
                                ['type' => 'O', 'rh' => 'Positive', 'priority' => 2],
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        } else {
                            $compatible_types = [
                                ['type' => 'AB', 'rh' => 'Negative', 'priority' => 4],
                                ['type' => 'A', 'rh' => 'Negative', 'priority' => 3],
                                ['type' => 'B', 'rh' => 'Negative', 'priority' => 2],
                                ['type' => 'O', 'rh' => 'Negative', 'priority' => 1]
                            ];
                        }
                        break;
                }
                usort($compatible_types, function($a, $b) {
                    return $a['priority'] - $b['priority'];
                });
                return $compatible_types;
            }
        }
        $compatible_types = getCompatibleBloodTypes($requested_blood_type, $requested_rh_factor);
        foreach ($compatible_types as $compatible_type) {
            if ($remaining_units <= 0) break;
            $compatible_blood_type = $compatible_type['type'] . ($compatible_type['rh'] === 'Positive' ? '+' : '-');
            foreach ($available_bags as $bag) {
                if ($remaining_units <= 0) break;
                if ($bag['blood_type'] === $compatible_blood_type &&
                    !in_array($bag['blood_collection_id'], array_column($collections_to_update, 'blood_collection_id'))) {
                    $available_units = $bag['amount_taken'];
                    if ($available_units > 0) {
                        $units_to_take = min($available_units, $remaining_units);
                        $units_found += $units_to_take;
                        $remaining_units -= $units_to_take;
                        if (!isset($deducted_by_type[$bag['blood_type']])) {
                            $deducted_by_type[$bag['blood_type']] = 0;
                        }
                        $deducted_by_type[$bag['blood_type']] += $units_to_take;
                        $bag['units_to_take'] = $units_to_take;
                        $collections_to_update[] = $bag;
                    }
                }
            }
        }
    }
    // SAFETY CHECK: Ensure we never deduct more than requested
    $total_to_deduct = 0;
    foreach ($collections_to_update as $col) {
        $total_to_deduct += $col['units_to_take'];
    }
    if ($total_to_deduct > $units_requested) {
        $over = $total_to_deduct - $units_requested;
        $collections_to_update[count($collections_to_update)-1]['units_to_take'] -= $over;
    }
    // Filter out any bags with units_to_take <= 0
    $collections_to_update = array_filter($collections_to_update, function($col) {
        return isset($col['units_to_take']) && $col['units_to_take'] > 0;
    });
    // Now update the database
    foreach ($collections_to_update as $collection) {
        $new_amount = intval($collection['amount_taken']) - intval($collection['units_to_take']);
        if ($new_amount < 0) $new_amount = 0;
        $update_data = json_encode([
            'amount_taken' => $new_amount,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        $update_url = SUPABASE_URL . '/rest/v1/blood_collection';
        $update_url .= '?blood_collection_id=eq.' . $collection['blood_collection_id'];
        curl_setopt($ch, CURLOPT_URL, $update_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        $update_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200 && $http_code !== 204) {
            curl_close($ch);
            return [
                'success' => false,
                'message' => 'Failed to update blood collection ' . $collection['blood_collection_id'] . '. HTTP Code: ' . $http_code
            ];
        }
    }
    // Update the request status
    $request_update_data = json_encode([
        'status' => 'Confirmed',
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    $update_url = SUPABASE_URL . '/rest/v1/blood_requests';
    $update_url .= '?request_id=eq.' . $request_id;
    curl_setopt($ch, CURLOPT_URL, $update_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_update_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_API_KEY,
        'Authorization: Bearer ' . SUPABASE_API_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    $request_update = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code !== 200 && $http_code !== 204) {
        return [
            'success' => false,
            'message' => 'Failed to update request status. HTTP Code: ' . $http_code
        ];
    }
    return [
        'success' => true,
        'request_id' => $request_id,
        'units_deducted' => $units_requested,
        'deducted_by_type' => $deducted_by_type,
        'collections_updated' => count($collections_to_update)
    ];
} 