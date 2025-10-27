<?php
// OPTIMIZATION: Shared utility functions for all modules
// This prevents function redeclaration errors and ensures consistency

if (!function_exists('getStatusClass')) {
    function getStatusClass($statusText) {
        if (strpos($statusText, 'Approved') !== false || strpos($statusText, 'eligible') !== false) {
            return 'bg-success';
        } elseif (strpos($statusText, 'Declined') !== false || strpos($statusText, 'refused') !== false) {
            return 'bg-danger';
        } elseif (strpos($statusText, 'Deferred') !== false || strpos($statusText, 'ineligible') !== false) {
            return 'bg-warning';
        } elseif (strpos($statusText, 'Pending (Examination)') !== false || strpos($statusText, 'Physical Examination') !== false) {
            return 'bg-info';
        } elseif (strpos($statusText, 'Pending (Collection)') !== false) {
            return 'bg-primary';
        } else {
            return 'bg-warning';
        }
    }
}

if (!function_exists('calculateAge')) {
    function calculateAge($birthdate) {
        if (empty($birthdate)) return '';
        try {
            $birth = new DateTime($birthdate);
            $today = new DateTime();
            return $birth->diff($today)->y;
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('isPerfModeEnabled')) {
    function isPerfModeEnabled() {
        return (isset($_GET['perf_mode']) && $_GET['perf_mode'] === 'on');
    }
}
?>
