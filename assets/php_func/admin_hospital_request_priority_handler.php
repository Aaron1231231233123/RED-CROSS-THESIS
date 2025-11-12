<?php
/**
 * Admin Hospital Request Priority Handler
 * 
 * Calculates priority levels for hospital blood requests based on:
 * - is_asap flag
 * - when_needed timestamp
 * - Current time proximity to deadline
 * 
 * Only applies to requests with status "Pending"
 */

/**
 * Calculate priority level and urgency for a blood request
 * 
 * @param bool $is_asap Whether the request is marked as ASAP
 * @param string|null $when_needed Timestamp when blood is needed (ISO format)
 * @param string $status Current status of the request
 * @return array Associative array with priority information:
 *   - 'priority_level': int (1-5, 5 being most urgent)
 *   - 'urgency_class': string (CSS class name)
 *   - 'urgency_color': string (hex color code)
 *   - 'time_remaining': string (human-readable time remaining)
 *   - 'is_urgent': bool (whether to show red highlight)
 *   - 'is_critical': bool (whether it's critical/overdue)
 */
function calculateHospitalRequestPriority($is_asap, $when_needed, $status) {
    // Only apply prioritization to Pending requests
    if (strtolower($status) !== 'pending') {
        return [
            'priority_level' => 0,
            'urgency_class' => 'priority-normal',
            'urgency_color' => '',
            'time_remaining' => '',
            'is_urgent' => false,
            'is_critical' => false
        ];
    }
    
    // Calculate time remaining
    $timeInfo = calculateTimeRemaining($when_needed);
    $hoursRemaining = $timeInfo['hours_remaining'];
    $daysRemaining = $hoursRemaining !== null ? ($hoursRemaining / 24) : null;
    
    // Check if 1 day before deadline (for alert) - between 0 and 1 day remaining (not overdue)
    $isOneDayBefore = ($daysRemaining !== null && $daysRemaining > 0 && $daysRemaining <= 1 && !$timeInfo['is_overdue']);
    
    // If ASAP is true, always mark as urgent (red) with consistent color
    if ($is_asap) {
        return [
            'priority_level' => 5,
            'urgency_class' => 'priority-asap-urgent',
            'urgency_color' => '#dc3545', // Consistent red
            'time_remaining' => $timeInfo['display'],
            'is_urgent' => true,
            'is_critical' => $timeInfo['is_overdue'] || ($hoursRemaining !== null && $hoursRemaining < 6),
            'hours_remaining' => $hoursRemaining,
            'is_overdue' => $timeInfo['is_overdue'],
            'is_one_day_before' => $isOneDayBefore
        ];
    }
    
    // For non-ASAP requests, calculate based on when_needed timestamp
    if (empty($when_needed) || $hoursRemaining === null) {
        // No deadline specified, normal priority (blue)
        return [
            'priority_level' => 1,
            'urgency_class' => 'priority-normal',
            'urgency_color' => '#0d6efd', // Blue
            'time_remaining' => 'No deadline specified',
            'is_urgent' => false,
            'is_critical' => false,
            'is_one_day_before' => false
        ];
    }
    
    // For non-ASAP: Red only if 3 days or less before deadline, blue otherwise
    if ($daysRemaining <= 3) {
        // 3 days or less - urgent (red) with consistent color
        return [
            'priority_level' => 3,
            'urgency_class' => 'priority-urgent',
            'urgency_color' => '#dc3545', // Consistent red
            'time_remaining' => $timeInfo['display'],
            'is_urgent' => true,
            'is_critical' => $timeInfo['is_overdue'] || $hoursRemaining < 6,
            'hours_remaining' => $hoursRemaining,
            'is_overdue' => $timeInfo['is_overdue'],
            'is_one_day_before' => $isOneDayBefore
        ];
    } else {
        // More than 3 days - normal (blue)
        return [
            'priority_level' => 2,
            'urgency_class' => 'priority-normal',
            'urgency_color' => '#0d6efd', // Blue
            'time_remaining' => $timeInfo['display'],
            'is_urgent' => false,
            'is_critical' => false,
            'hours_remaining' => $hoursRemaining,
            'is_overdue' => false,
            'is_one_day_before' => false
        ];
    }
}

/**
 * Calculate time remaining until when_needed timestamp
 * 
 * @param string|null $when_needed ISO timestamp
 * @return array Time information
 */
function calculateTimeRemaining($when_needed) {
    if (empty($when_needed)) {
        return [
            'hours_remaining' => null,
            'is_overdue' => false,
            'display' => 'No deadline specified'
        ];
    }
    
    try {
        $deadline = new DateTime($when_needed);
        $now = new DateTime();
        $diff = $now->diff($deadline);
        
        $isOverdue = $now > $deadline;
        $totalHours = ($deadline->getTimestamp() - $now->getTimestamp()) / 3600;
        
        // Format display string
        $display = '';
        if ($isOverdue) {
            $hoursOverdue = abs($totalHours);
            if ($hoursOverdue < 1) {
                $display = 'Overdue: ' . round($hoursOverdue * 60) . ' minutes';
            } elseif ($hoursOverdue < 24) {
                $display = 'Overdue: ' . round($hoursOverdue) . ' hours';
            } else {
                $display = 'Overdue: ' . round($hoursOverdue / 24) . ' days';
            }
        } else {
            if ($totalHours < 1) {
                $display = round($totalHours * 60) . ' minutes remaining';
            } elseif ($totalHours < 24) {
                $display = round($totalHours) . ' hours remaining';
            } else {
                $days = floor($totalHours / 24);
                $hours = round($totalHours % 24);
                if ($hours > 0) {
                    $display = $days . ' days, ' . $hours . ' hours remaining';
                } else {
                    $display = $days . ' days remaining';
                }
            }
        }
        
        return [
            'hours_remaining' => $totalHours,
            'is_overdue' => $isOverdue,
            'display' => $display
        ];
    } catch (Exception $e) {
        error_log("Error calculating time remaining: " . $e->getMessage());
        return [
            'hours_remaining' => null,
            'is_overdue' => false,
            'display' => 'Invalid date'
        ];
    }
}

/**
 * Get priority data for a request array
 * Processes multiple requests at once for efficiency
 * 
 * @param array $request Single request array or array of requests
 * @return array|array[] Priority data (single array or array of arrays)
 */
function getRequestPriorityData($request) {
    if (isset($request[0]) && is_array($request[0])) {
        // Multiple requests
        $results = [];
        foreach ($request as $req) {
            $results[] = calculateHospitalRequestPriority(
                !empty($req['is_asap']),
                $req['when_needed'] ?? null,
                $req['status'] ?? 'pending'
            );
        }
        return $results;
    } else {
        // Single request
        return calculateHospitalRequestPriority(
            !empty($request['is_asap']),
            $request['when_needed'] ?? null,
            $request['status'] ?? 'pending'
        );
    }
}

