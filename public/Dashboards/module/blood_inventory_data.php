<?php
/**
 * Shared blood inventory loader used by both the Blood Bank page and the home dashboard.
 * Returns a normalized snapshot containing inventory lists, buffer metadata, stats, and lookups.
 */
if (!function_exists('loadBloodInventorySnapshot')) {
    function loadBloodInventorySnapshot() {
        $result = [
            'bloodInventory' => [],
            'activeInventory' => [],
            'bufferOnlyInventory' => [],
            'bufferContext' => [
                'buffer_units' => [],
                'buffer_types' => [],
                'buffer_lookup' => ['by_id' => [], 'by_serial' => []]
            ],
            'bufferReserveCount' => 0,
            'bufferTypes' => [],
            'bloodInStockCount' => 0,
            'bloodByType' => [
                'A+' => 0, 'A-' => 0, 'B+' => 0, 'B-' => 0,
                'O+' => 0, 'O-' => 0, 'AB+' => 0, 'AB-' => 0
            ],
            'bloodReceivedCount' => 0,
            'totalDonorCount' => 0,
            'donorLookup' => [],
        ];

        $bloodInventory = [];
        $bloodBankUnitsData = [];
        $seenDonorIds = [];

        try {
            // Fetch all blood_bank_units entries (paginated to bypass 1000 limit)
            $offset = 0;
            $limit = 1000;
            $hasMore = true;
            $maxIterations = 10;
            $iteration = 0;

            while ($hasMore && $iteration < $maxIterations) {
                $endpoint = "blood_bank_units"
                    . "?select=unit_id,unit_serial_number,donor_id,blood_type,bag_type,bag_brand,collected_at,expires_at,status,hospital_request_id,created_at,updated_at"
                    . "&unit_serial_number=not.is.null"
                    . "&order=collected_at.desc&limit={$limit}&offset={$offset}";
                $bloodBankUnitsResponse = supabaseRequest($endpoint);
                $batchData = isset($bloodBankUnitsResponse['data']) ? $bloodBankUnitsResponse['data'] : [];

                if (empty($batchData)) {
                    $hasMore = false;
                } else {
                    $bloodBankUnitsData = array_merge($bloodBankUnitsData, $batchData);
                    $offset += $limit;
                    $iteration++;
                    if (count($batchData) < $limit) {
                        $hasMore = false;
                    }
                }
            }

            // Build donor lookup
            if (!empty($bloodBankUnitsData)) {
                $donorIds = array_unique(array_column($bloodBankUnitsData, 'donor_id'));
                if (!empty($donorIds)) {
                    $donorIdsFilter = implode(',', array_map('intval', $donorIds));
                    $donorResponse = supabaseRequest("donor_form?select=donor_id,surname,first_name,middle_name,birthdate,sex,civil_status&donor_id=in.({$donorIdsFilter})");
                    $donorData = isset($donorResponse['data']) ? $donorResponse['data'] : [];
                    $donorLookup = [];
                    foreach ($donorData as $donor) {
                        $donorLookup[$donor['donor_id']] = $donor;
                    }
                } else {
                    $donorLookup = [];
                }
            } else {
                $donorLookup = [];
            }

            if (is_array($bloodBankUnitsData) && !empty($bloodBankUnitsData)) {
                $filteredUnits = array_filter($bloodBankUnitsData, function($item) {
                    return !empty($item['unit_serial_number']);
                });

                $bloodInventory = array_map(function($item) use ($donorLookup, &$seenDonorIds) {
                    $collectionDate = new DateTime($item['collected_at']);
                    $expirationDate = new DateTime($item['expires_at']);
                    $today = new DateTime();

                    $status = 'Valid';
                    $unitStatusRaw = strtolower($item['status'] ?? '');
                    switch ($unitStatusRaw) {
                        case 'handed_over':
                        case 'handed over':
                            $status = 'Handed Over';
                            break;
                        case 'disposed':
                            $status = 'Disposed';
                            break;
                        case 'used':
                            $status = 'Used';
                            break;
                        case 'reserved':
                            $status = 'Reserved';
                            break;
                        case 'quarantined':
                            $status = 'Quarantined';
                            break;
                        case 'expired':
                            $status = 'Expired';
                            break;
                        case 'buffer':
                            $status = 'Buffer';
                            break;
                        case 'valid':
                        case '':
                        default:
                            $status = 'Valid';
                            break;
                    }
                    if ($status === 'Valid' && $today > $expirationDate) {
                        $status = 'Expired';
                    }

                    $donorId = $item['donor_id'];
                    if ($donorId && !isset($seenDonorIds[$donorId])) {
                        $seenDonorIds[$donorId] = true;
                    }

                    $donor = $donorLookup[$donorId] ?? null;
                    $donorInfo = [
                        'surname' => 'Not Found',
                        'first_name' => '',
                        'middle_name' => '',
                        'birthdate' => '',
                        'age' => '',
                        'sex' => '',
                        'civil_status' => ''
                    ];
                    if ($donor) {
                        $age = '';
                        if (!empty($donor['birthdate'])) {
                            $birthdate = new DateTime($donor['birthdate']);
                            $age = $birthdate->diff(new DateTime())->y;
                        }
                        $donorInfo = [
                            'surname' => $donor['surname'] ?? '',
                            'first_name' => $donor['first_name'] ?? '',
                            'middle_name' => $donor['middle_name'] ?? '',
                            'birthdate' => !empty($donor['birthdate']) ? date('d/m/Y', strtotime($donor['birthdate'])) : '',
                            'age' => $age,
                            'sex' => $donor['sex'] ?? '',
                            'civil_status' => $donor['civil_status'] ?? ''
                        ];
                    }

                    return [
                        'unit_id' => $item['unit_id'],
                        'donor_id' => $donorId,
                        'serial_number' => $item['unit_serial_number'],
                        'blood_type' => $item['blood_type'],
                        'bags' => 1,
                        'bag_type' => $item['bag_type'] ?: 'Standard',
                        'bag_brand' => $item['bag_brand'] ?: 'N/A',
                        'collection_date' => $collectionDate->format('Y-m-d'),
                        'expiration_date' => $expirationDate->format('Y-m-d'),
                        'status' => $status,
                        'unit_status' => $item['status'],
                        'hospital_request_id' => $item['hospital_request_id'] ?? null,
                        'created_at' => $item['created_at'],
                        'updated_at' => $item['updated_at'],
                        'donor' => $donorInfo
                    ];
                }, $filteredUnits);

                $bloodInventory = array_values($bloodInventory);
            }
        } catch (Exception $e) {
            error_log("Blood Inventory Loader: " . $e->getMessage());
            $bloodInventory = [];
            $donorLookup = [];
        }

        if (empty($bloodInventory)) {
            $bloodInventory = [];
        }

        $bufferContext = getBufferBloodContext(BUFFER_BLOOD_PER_TYPE_LIMIT, $bloodInventory);
        syncBufferBloodStatuses($bloodInventory, $bufferContext);
        $bloodInventory = tagBufferBloodInInventory($bloodInventory, $bufferContext['buffer_lookup']);
        $bufferOnlyInventory = array_values(array_filter($bloodInventory, function($unit) {
            return !empty($unit['is_buffer']);
        }));
        $activeInventory = array_values(array_filter($bloodInventory, function($unit) {
            return empty($unit['is_buffer']);
        }));
        $bufferReserveCount = count($bufferOnlyInventory);
        $bufferTypes = $bufferContext['buffer_types'];

        $bloodInStockCount = 0;
        $bloodByType = $result['bloodByType'];
        foreach ($activeInventory as $unit) {
            if ($unit['status'] === 'Valid') {
                $bloodInStockCount += 1;
                $bt = $unit['blood_type'] ?? '';
                if (isset($bloodByType[$bt])) {
                    $bloodByType[$bt] += 1;
                }
            }
        }

        $bloodReceivedCount = count($seenDonorIds);

        return [
            'bloodInventory' => $bloodInventory,
            'activeInventory' => $activeInventory,
            'bufferOnlyInventory' => $bufferOnlyInventory,
            'bufferContext' => $bufferContext,
            'bufferReserveCount' => $bufferReserveCount,
            'bufferTypes' => $bufferTypes,
            'bloodInStockCount' => $bloodInStockCount,
            'bloodByType' => $bloodByType,
            'bloodReceivedCount' => $bloodReceivedCount,
            'totalDonorCount' => $bloodReceivedCount,
            'donorLookup' => $donorLookup ?? [],
        ];
    }
}

