<?php
/**
 * Auto-transfer stale blood_collection.needs_review flags to medical_history.needs_review.
 *
 * Logic:
 * - Find blood_collection rows where:
 *     - needs_review = true
 *     - updated_at is older than $daysThreshold days
 * - For each row:
 *     - Resolve related medical_history via:
 *         1) blood_collection.screening_id -> screening_form.medical_history_id
 *         2) Fallback: screening_form.donor_form_id -> medical_history.donor_id
 *         3) Fallback: blood_collection.physical_exam_id -> physical_examination.{screening_id,donor_id}
 *     - If a medical_history record is found:
 *         - Set medical_history.needs_review = true and bump updated_at
 *         - Set blood_collection.needs_review = false and align updated_at
 *
 * This helper is used by:
 * - public/Dashboards/dashboard-staff-blood-collection-submission.php (server-side on load)
 * - public/api/auto-transfer-blood-collection-needs-review.php (explicit API trigger)
 */

require_once __DIR__ . '/../conn/db_conn.php';

if (!function_exists('autoTransferBloodCollectionNeedsReview')) {
    /**
     * Scan for stale blood_collection records and transfer needs_review to medical_history.
     *
     * @param int $daysThreshold Number of days after which a needs_review in blood_collection
     *                           should be escalated to medical_history.
     * @return array Summary of work done.
     */
    function autoTransferBloodCollectionNeedsReview($daysThreshold = 1)
    {
        $summary = [
            'cutoff' => null,
            'scanned' => 0,
            'updated_blood_collections' => 0,
            'updated_medical_histories' => 0,
            'skipped_without_links' => 0,
            'errors' => []
        ];

        try {
            // Calculate cutoff timestamp (older than N days from "now")
            $cutoffDate = new DateTime(sprintf('-%d days', (int)$daysThreshold));
            $cutoffIso  = $cutoffDate->format('c'); // ISO 8601
            $summary['cutoff'] = $cutoffIso;

            $headers = [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Accept: application/json',
                'Content-Type: application/json'
            ];

            // STEP 1: Fetch stale blood_collection rows with needs_review = true
            $staleCollections = [];
            $limit        = 1000;
            $offset       = 0;
            $hasMore      = true;
            $maxIterations = 10;
            $iteration    = 0;

            while ($hasMore && $iteration < $maxIterations) {
                $url = SUPABASE_URL
                    . '/rest/v1/blood_collection'
                    . '?select=blood_collection_id,physical_exam_id,screening_id,updated_at,needs_review'
                    . '&needs_review=eq.true'
                    . '&updated_at=lt.' . urlencode($cutoffIso)
                    . '&limit=' . $limit
                    . '&offset=' . $offset;

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err      = curl_error($ch);
                curl_close($ch);

                if ($err) {
                    $summary['errors'][] = 'Error fetching stale blood_collection batch ' . $iteration . ': ' . $err;
                    break;
                }

                if ($httpCode !== 200) {
                    $summary['errors'][] = 'HTTP ' . $httpCode . ' when fetching stale blood_collection batch ' . $iteration . ' - ' . $response;
                    break;
                }

                $batch = json_decode($response, true) ?: [];
                if (empty($batch)) {
                    $hasMore = false;
                } else {
                    $staleCollections = array_merge($staleCollections, $batch);
                    $offset          += $limit;
                    $iteration++;
                    if (count($batch) < $limit) {
                        $hasMore = false;
                    }
                }
            }

            $summary['scanned'] = count($staleCollections);
            if (empty($staleCollections)) {
                return $summary;
            }

            // Helper: resolve medical_history_id for a given collection row
            $resolveMedicalHistoryId = function ($collectionRow) use ($headers, &$summary) {
                $screeningId    = isset($collectionRow['screening_id']) ? $collectionRow['screening_id'] : null;
                $physicalExamId = isset($collectionRow['physical_exam_id']) ? $collectionRow['physical_exam_id'] : null;

                // 1) Primary path: via screening_form.screening_id
                if (!empty($screeningId)) {
                    $url = SUPABASE_URL
                        . '/rest/v1/screening_form'
                        . '?select=medical_history_id,donor_form_id'
                        . '&screening_id=eq.' . urlencode((string)$screeningId)
                        . '&limit=1';

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $resp    = curl_exec($ch);
                    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlErr = curl_error($ch);
                    curl_close($ch);

                    if ($curlErr) {
                        $summary['errors'][] = 'Error resolving screening_form for screening_id ' . $screeningId . ': ' . $curlErr;
                    } elseif ($code === 200 && $resp) {
                        $rows = json_decode($resp, true) ?: [];
                        if (!empty($rows)) {
                            $row = $rows[0];
                            if (!empty($row['medical_history_id'])) {
                                return $row['medical_history_id'];
                            }
                            // Fallback via donor_form_id -> medical_history.donor_id
                            if (!empty($row['donor_form_id'])) {
                                $mhUrl = SUPABASE_URL
                                    . '/rest/v1/medical_history'
                                    . '?select=medical_history_id'
                                    . '&donor_id=eq.' . urlencode((string)$row['donor_form_id'])
                                    . '&order=created_at.desc'
                                    . '&limit=1';

                                $mhCh = curl_init($mhUrl);
                                curl_setopt($mhCh, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($mhCh, CURLOPT_HTTPHEADER, $headers);
                                $mhResp = curl_exec($mhCh);
                                $mhCode = curl_getinfo($mhCh, CURLINFO_HTTP_CODE);
                                $mhErr  = curl_error($mhCh);
                                curl_close($mhCh);

                                if ($mhErr) {
                                    $summary['errors'][] = 'Error resolving medical_history by donor_id for donor_form_id ' . $row['donor_form_id'] . ': ' . $mhErr;
                                } elseif ($mhCode === 200 && $mhResp) {
                                    $mhRows = json_decode($mhResp, true) ?: [];
                                    if (!empty($mhRows) && !empty($mhRows[0]['medical_history_id'])) {
                                        return $mhRows[0]['medical_history_id'];
                                    }
                                }
                            }
                        }
                    }
                }

                // 2) Fallback: via physical_examination -> donor_id / screening_id
                if (!empty($physicalExamId)) {
                    $peUrl = SUPABASE_URL
                        . '/rest/v1/physical_examination'
                        . '?select=donor_id,screening_id'
                        . '&physical_exam_id=eq.' . urlencode((string)$physicalExamId)
                        . '&limit=1';

                    $peCh = curl_init($peUrl);
                    curl_setopt($peCh, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($peCh, CURLOPT_HTTPHEADER, $headers);
                    $peResp = curl_exec($peCh);
                    $peCode = curl_getinfo($peCh, CURLINFO_HTTP_CODE);
                    $peErr  = curl_error($peCh);
                    curl_close($peCh);

                    if ($peErr) {
                        $summary['errors'][] = 'Error resolving physical_examination for physical_exam_id ' . $physicalExamId . ': ' . $peErr;
                        return null;
                    }

                    if ($peCode === 200 && $peResp) {
                        $peRows = json_decode($peResp, true) ?: [];
                        if (!empty($peRows)) {
                            $peRow = $peRows[0];

                            // Try via screening_id if present (mirror primary path logic)
                            if (!empty($peRow['screening_id'])) {
                                $screeningIdFromPe = $peRow['screening_id'];

                                $url = SUPABASE_URL
                                    . '/rest/v1/screening_form'
                                    . '?select=medical_history_id,donor_form_id'
                                    . '&screening_id=eq.' . urlencode((string)$screeningIdFromPe)
                                    . '&limit=1';

                                $ch = curl_init($url);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                $resp    = curl_exec($ch);
                                $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                $curlErr = curl_error($ch);
                                curl_close($ch);

                                if ($curlErr) {
                                    $summary['errors'][] = 'Error resolving screening_form (via physical_examination) for screening_id ' . $screeningIdFromPe . ': ' . $curlErr;
                                } elseif ($code === 200 && $resp) {
                                    $rows = json_decode($resp, true) ?: [];
                                    if (!empty($rows)) {
                                        $row = $rows[0];
                                        if (!empty($row['medical_history_id'])) {
                                            return $row['medical_history_id'];
                                        }
                                        if (!empty($row['donor_form_id'])) {
                                            $mhUrl = SUPABASE_URL
                                                . '/rest/v1/medical_history'
                                                . '?select=medical_history_id'
                                                . '&donor_id=eq.' . urlencode((string)$row['donor_form_id'])
                                                . '&order=created_at.desc'
                                                . '&limit=1';

                                            $mhCh = curl_init($mhUrl);
                                            curl_setopt($mhCh, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($mhCh, CURLOPT_HTTPHEADER, $headers);
                                            $mhResp = curl_exec($mhCh);
                                            $mhCode = curl_getinfo($mhCh, CURLINFO_HTTP_CODE);
                                            $mhErr  = curl_error($mhCh);
                                            curl_close($mhCh);

                                            if ($mhErr) {
                                                $summary['errors'][] = 'Error resolving medical_history by donor_id (via physical_examination) for donor_form_id ' . $row['donor_form_id'] . ': ' . $mhErr;
                                            } elseif ($mhCode === 200 && $mhResp) {
                                                $mhRows = json_decode($mhResp, true) ?: [];
                                                if (!empty($mhRows) && !empty($mhRows[0]['medical_history_id'])) {
                                                    return $mhRows[0]['medical_history_id'];
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            // Fallback via donor_id from physical_examination
                            if (!empty($peRow['donor_id'])) {
                                $mhUrl = SUPABASE_URL
                                    . '/rest/v1/medical_history'
                                    . '?select=medical_history_id'
                                    . '&donor_id=eq.' . urlencode((string)$peRow['donor_id'])
                                    . '&order=created_at.desc'
                                    . '&limit=1';

                                $mhCh = curl_init($mhUrl);
                                curl_setopt($mhCh, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($mhCh, CURLOPT_HTTPHEADER, $headers);
                                $mhResp = curl_exec($mhCh);
                                $mhCode = curl_getinfo($mhCh, CURLINFO_HTTP_CODE);
                                $mhErr  = curl_error($mhCh);
                                curl_close($mhCh);

                                if ($mhErr) {
                                    $summary['errors'][] = 'Error resolving medical_history by donor_id for donor_id ' . $peRow['donor_id'] . ': ' . $mhErr;
                                } elseif ($mhCode === 200 && $mhResp) {
                                    $mhRows = json_decode($mhResp, true) ?: [];
                                    if (!empty($mhRows) && !empty($mhRows[0]['medical_history_id'])) {
                                        return $mhRows[0]['medical_history_id'];
                                    }
                                }
                            }
                        }
                    }
                }

                return null;
            };

            // STEP 2: For each stale collection, transfer needs_review
            foreach ($staleCollections as $row) {
                $bcId = isset($row['blood_collection_id']) ? $row['blood_collection_id'] : null;
                if (!$bcId) {
                    $summary['skipped_without_links']++;
                    continue;
                }

                $medicalHistoryId = $resolveMedicalHistoryId($row);
                if (empty($medicalHistoryId)) {
                    $summary['skipped_without_links']++;
                    continue;
                }

                // Use the same timestamp for both updates so that updated_at stays in sync
                $nowIso = (new DateTime())->format('c');

                // 2a) Update medical_history: needs_review = true
                $mhUrl  = SUPABASE_URL . '/rest/v1/medical_history?medical_history_id=eq.' . urlencode((string)$medicalHistoryId);
                $mhBody = json_encode([
                    'needs_review' => true,
                    'updated_at'   => $nowIso
                ]);

                $mhCh = curl_init($mhUrl);
                curl_setopt($mhCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($mhCh, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($mhCh, CURLOPT_POSTFIELDS, $mhBody);
                curl_setopt($mhCh, CURLOPT_HTTPHEADER, $headers);
                $mhResp = curl_exec($mhCh);
                $mhCode = curl_getinfo($mhCh, CURLINFO_HTTP_CODE);
                $mhErr  = curl_error($mhCh);
                curl_close($mhCh);

                if ($mhErr || $mhCode < 200 || $mhCode >= 300) {
                    $summary['errors'][] = 'Failed to update medical_history ' . $medicalHistoryId
                        . ' for blood_collection ' . $bcId
                        . ' - HTTP ' . $mhCode . ' - ' . ($mhErr ?: $mhResp);
                    continue;
                }

                $summary['updated_medical_histories']++;

                // 2b) Update blood_collection: needs_review = false
                $bcUrl  = SUPABASE_URL . '/rest/v1/blood_collection?blood_collection_id=eq.' . urlencode((string)$bcId);
                $bcBody = json_encode([
                    'needs_review' => false,
                    'updated_at'   => $nowIso
                ]);

                $bcCh = curl_init($bcUrl);
                curl_setopt($bcCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($bcCh, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($bcCh, CURLOPT_POSTFIELDS, $bcBody);
                curl_setopt($bcCh, CURLOPT_HTTPHEADER, $headers);
                $bcResp = curl_exec($bcCh);
                $bcCode = curl_getinfo($bcCh, CURLINFO_HTTP_CODE);
                $bcErr  = curl_error($bcCh);
                curl_close($bcCh);

                if ($bcErr || $bcCode < 200 || $bcCode >= 300) {
                    $summary['errors'][] = 'Failed to update blood_collection ' . $bcId
                        . ' (clear needs_review) - HTTP ' . $bcCode . ' - ' . ($bcErr ?: $bcResp);
                    continue;
                }

                $summary['updated_blood_collections']++;
            }
        } catch (Throwable $e) {
            $summary['errors'][] = 'Fatal error in autoTransferBloodCollectionNeedsReview: ' . $e->getMessage();
        }

        return $summary;
    }
}


