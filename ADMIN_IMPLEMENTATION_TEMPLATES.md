# Admin Implementation Templates

This file contains detailed templates for creating admin-specific functionality that mirrors the **ACTUAL WORKING** staff-side dashboard system.

## Current Working System Analysis

### Complete Interconnected File Structure (Staff Side)

#### **Core Dashboard Files**
- `public/Dashboards/dashboard-Inventory-System-list-of-donations.php` (Main Dashboard)
- `public/Dashboards/module/donation_pending.php` (Pending Donations Module)
- `public/Dashboards/module/donation_approved.php` (Approved Donations Module)
- `public/Dashboards/module/donation_declined.php` (Declined Donations Module)
- `public/Dashboards/module/optimized_functions.php` (Contains working `supabaseRequest()` function)

#### **Database Connection**
- `assets/conn/db_conn.php` (Supabase configuration)

#### **JavaScript Files (Complete Set)**
- `assets/js/unified-staff-workflow-system.js` (Main workflow system)
- `assets/js/enhanced-workflow-manager.js` (Workflow management)
- `assets/js/enhanced-data-handler.js` (Data handling)
- `assets/js/enhanced-validation-system.js` (Form validation)
- `assets/js/medical-history-approval.js` (Medical history modals)
- `assets/js/screening_form_modal.js` (Screening form modals)
- `assets/js/physical_examination_modal.js` (Physical examination modals)
- `assets/js/defer_donor_modal.js` (Defer donor modals)
- `assets/js/duplicate_donor_check.js` (Duplicate donor checking)
- `assets/js/staff_donor_modal.js` (Staff donor modals)
- `assets/js/phlebotomist_blood_collection_details_modal.js` (Blood collection modals)

#### **CSS Files (Complete Set)**
- `assets/css/bootstrap.css` (Bootstrap framework)
- `assets/css/enhanced-modal-styles.css` (Enhanced modal styling)
- `assets/css/medical-history-approval-modals.css` (Medical history modals)
- `assets/css/screening-form-modal.css` (Screening form styling)
- `assets/css/defer-donor-modal.css` (Defer donor styling)
- `assets/css/physical_examination_modal.css` (Physical examination styling)

#### **PHP API Functions (Complete Set)**
- `assets/php_func/process_physical_examination.php` (Physical examination processing)
- `assets/php_func/process_screening_form.php` (Screening form processing)
- `assets/php_func/process_blood_collection.php` (Blood collection processing)
- `assets/php_func/comprehensive_donor_details_api.php` (Donor details API)
- `assets/php_func/check_duplicate_donor.php` (Duplicate donor checking)
- `assets/php_func/create_eligibility.php` (Eligibility creation)
- `assets/php_func/get_donor_eligibility.php` (Eligibility retrieval)
- `assets/php_func/update_eligibility.php` (Eligibility updates)
- `assets/php_func/user_roles_staff.php` (Staff role management)
- `assets/php_func/staff_donor_modal_handler.php` (Staff modal handling)
- `assets/php_func/fetch_donor_info.php` (Donor information fetching)
- `assets/php_func/medical_history_utils.php` (Medical history utilities)

#### **Modal and Form Views (Complete Set)**
- `src/views/modals/medical-history-approval-modals.php`
- `src/views/modals/physical-examination-modal.php`
- `src/views/modals/defer-donor-modal.php`
- `src/views/modals/blood-collection-modal.php`
- `src/views/modals/interviewer-confirmation-modals.php`
- `src/views/forms/medical-history-modal-content.php`
- `src/views/forms/medical-history-modal-content-simple.php`
- `src/views/forms/medical-history-physical-modal-content.php`
- `src/views/forms/medical-history-modal.php`
- `src/views/forms/staff_donor_initial_screening_form_modal.php`
- `src/views/forms/physician-screening-form-content-modal.php`
- `src/views/forms/physical-examination-form.php`
- `src/views/forms/declaration-form-modal-content.php`
- `src/views/forms/declaration-form-modal.php`
- `src/views/forms/donor-form-modal.php`
- `src/views/forms/screening-form.php`

#### **Donor Information Modal System (Complete)**
- `assets/js/staff_donor_modal.js` (Main donor modal handler)
- `assets/js/donor_information_medical.js` (Medical information modal)
- `assets/js/phlebotomist_blood_collection_details_modal.js` (Blood collection details)
- `assets/php_func/staff_donor_modal_handler.php` (Donor modal API handler)
- `assets/php_func/fetch_donor_info.php` (Donor info fetcher)
- `assets/php_func/donor_details_api.php` (Donor details API)
- `assets/php_func/comprehensive_donor_details_api.php` (Comprehensive donor data)
- `assets/php_func/fetch_donor_medical_info.php` (Medical info fetcher)
- `assets/php_func/fetch_physical_examination_info.php` (Physical exam fetcher)
- `assets/php_func/get_screening_and_donor.php` (Screening data fetcher)

### Admin Implementation Strategy
The admin system should be an **EXACT COPY** of these working files with admin-specific enhancements.

## Admin-Specific PHP Functions

### 1. Admin Donor Management (`assets/php_func/admin/admin_donor_management.php`)

**IMPORTANT**: This should extend the existing working functions, not replace them.

```php
<?php
/**
 * Admin Donor Management API
 * Enhanced donor management functions for administrators
 * Provides override capabilities and bulk operations
 * 
 * IMPORTANT: This extends the existing working system, not replaces it
 */

include_once '../conn/db_conn.php';
// Include the actual working optimized functions
include_once '../../public/Dashboards/module/optimized_functions.php';

class AdminDonorManagement {
    
    /**
     * Get comprehensive donor data with admin privileges
     * Uses the actual working supabaseRequest() function
     */
    public function getAdminDonorData($donorId, $includeDeleted = false) {
        $filters = ['donor_id' => 'eq.' . $donorId];
        
        if ($includeDeleted) {
            // Include soft-deleted records for admin review
            $filters['deleted_at'] = 'is.null';
        }
        
        // Use the actual working supabaseRequest function
        return supabaseRequest('donor_form', 'GET', $filters);
    }
    
    /**
     * Override donor eligibility status
     */
    public function overrideEligibility($donorId, $newStatus, $reason, $adminId) {
        $data = [
            'status' => $newStatus,
            'override_reason' => $reason,
            'overridden_by' => $adminId,
            'overridden_at' => date('c'),
            'needs_review' => false
        ];
        
        return supabaseRequest("eligibility?donor_id=eq.$donorId", 'PATCH', $data);
    }
    
    /**
     * Bulk update donor statuses
     */
    public function bulkUpdateDonorStatus($donorIds, $newStatus, $reason, $adminId) {
        $results = [];
        
        foreach ($donorIds as $donorId) {
            $result = $this->overrideEligibility($donorId, $newStatus, $reason, $adminId);
            $results[$donorId] = $result;
        }
        
        return $results;
    }
    
    /**
     * Get donor workflow history
     */
    public function getDonorWorkflowHistory($donorId) {
        $history = [];
        
        // Get screening history
        $screening = supabaseRequest("screening_form?donor_form_id=eq.$donorId&order=created_at.desc");
        if ($screening['data']) {
            $history['screening'] = $screening['data'];
        }
        
        // Get medical history
        $medical = supabaseRequest("medical_history?donor_id=eq.$donorId&order=created_at.desc");
        if ($medical['data']) {
            $history['medical'] = $medical['data'];
        }
        
        // Get physical examination
        $physical = supabaseRequest("physical_examination?donor_id=eq.$donorId&order=created_at.desc");
        if ($physical['data']) {
            $history['physical'] = $physical['data'];
        }
        
        // Get blood collection
        $collection = supabaseRequest("blood_collection?donor_id=eq.$donorId&order=created_at.desc");
        if ($collection['data']) {
            $history['collection'] = $collection['data'];
        }
        
        return $history;
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $donorId = $_GET['donor_id'] ?? '';
    
    $adminManager = new AdminDonorManagement();
    
    switch ($action) {
        case 'get_donor_data':
            $includeDeleted = $_GET['include_deleted'] === 'true';
            $result = $adminManager->getAdminDonorData($donorId, $includeDeleted);
            break;
            
        case 'get_workflow_history':
            $result = $adminManager->getDonorWorkflowHistory($donorId);
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $adminManager = new AdminDonorManagement();
    
    switch ($action) {
        case 'override_eligibility':
            $result = $adminManager->overrideEligibility(
                $input['donor_id'],
                $input['new_status'],
                $input['reason'],
                $input['admin_id']
            );
            break;
            
        case 'bulk_update':
            $result = $adminManager->bulkUpdateDonorStatus(
                $input['donor_ids'],
                $input['new_status'],
                $input['reason'],
                $input['admin_id']
            );
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>
```

### 2. Admin Workflow Management (`assets/php_func/admin/admin_workflow_management.php`)

```php
<?php
/**
 * Admin Workflow Management API
 * Advanced workflow control and monitoring for administrators
 */

include_once '../conn/db_conn.php';

class AdminWorkflowManagement {
    
    /**
     * Get system-wide workflow statistics
     */
    public function getWorkflowStatistics($dateRange = null) {
        $stats = [];
        
        // Get pending counts by stage
        $pendingScreening = supabaseRequest("donor_form?select=count&needs_screening=eq.true");
        $pendingMedical = supabaseRequest("medical_history?select=count&needs_review=eq.true");
        $pendingPhysical = supabaseRequest("physical_examination?select=count&needs_review=eq.true");
        $pendingCollection = supabaseRequest("blood_collection?select=count&needs_review=eq.true");
        
        $stats['pending'] = [
            'screening' => $pendingScreening['data'][0]['count'] ?? 0,
            'medical' => $pendingMedical['data'][0]['count'] ?? 0,
            'physical' => $pendingPhysical['data'][0]['count'] ?? 0,
            'collection' => $pendingCollection['data'][0]['count'] ?? 0
        ];
        
        // Get completion rates
        $totalDonors = supabaseRequest("donor_form?select=count");
        $completedDonors = supabaseRequest("eligibility?select=count&status=eq.approved");
        
        $stats['completion_rate'] = [
            'total' => $totalDonors['data'][0]['count'] ?? 0,
            'completed' => $completedDonors['data'][0]['count'] ?? 0,
            'percentage' => 0
        ];
        
        if ($stats['completion_rate']['total'] > 0) {
            $stats['completion_rate']['percentage'] = 
                ($stats['completion_rate']['completed'] / $stats['completion_rate']['total']) * 100;
        }
        
        return $stats;
    }
    
    /**
     * Force workflow progression
     */
    public function forceWorkflowProgression($donorId, $targetStage, $adminId, $reason) {
        $workflowData = [
            'donor_id' => $donorId,
            'target_stage' => $targetStage,
            'forced_by' => $adminId,
            'force_reason' => $reason,
            'forced_at' => date('c')
        ];
        
        // Update all relevant tables to skip to target stage
        switch ($targetStage) {
            case 'medical_review':
                // Mark screening as completed
                supabaseRequest("screening_form?donor_form_id=eq.$donorId", 'PATCH', [
                    'needs_review' => false,
                    'completed_at' => date('c')
                ]);
                break;
                
            case 'physical_examination':
                // Mark medical as completed
                supabaseRequest("medical_history?donor_id=eq.$donorId", 'PATCH', [
                    'needs_review' => false,
                    'completed_at' => date('c')
                ]);
                break;
                
            case 'blood_collection':
                // Mark physical as completed
                supabaseRequest("physical_examination?donor_id=eq.$donorId", 'PATCH', [
                    'needs_review' => false,
                    'completed_at' => date('c')
                ]);
                break;
        }
        
        // Log the forced progression
        supabaseRequest('admin_workflow_log', 'POST', $workflowData);
        
        return ['success' => true, 'message' => 'Workflow progression forced successfully'];
    }
    
    /**
     * Get workflow bottlenecks
     */
    public function getWorkflowBottlenecks() {
        $bottlenecks = [];
        
        // Check for donors stuck in screening
        $stuckScreening = supabaseRequest("donor_form?needs_screening=eq.true&created_at=lt." . date('c', strtotime('-24 hours')));
        if ($stuckScreening['data']) {
            $bottlenecks['screening'] = count($stuckScreening['data']);
        }
        
        // Check for donors stuck in medical review
        $stuckMedical = supabaseRequest("medical_history?needs_review=eq.true&created_at=lt." . date('c', strtotime('-48 hours')));
        if ($stuckMedical['data']) {
            $bottlenecks['medical'] = count($stuckMedical['data']);
        }
        
        // Check for donors stuck in physical examination
        $stuckPhysical = supabaseRequest("physical_examination?needs_review=eq.true&created_at=lt." . date('c', strtotime('-24 hours')));
        if ($stuckPhysical['data']) {
            $bottlenecks['physical'] = count($stuckPhysical['data']);
        }
        
        return $bottlenecks;
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    $workflowManager = new AdminWorkflowManagement();
    
    switch ($action) {
        case 'get_statistics':
            $dateRange = $_GET['date_range'] ?? null;
            $result = $workflowManager->getWorkflowStatistics($dateRange);
            break;
            
        case 'get_bottlenecks':
            $result = $workflowManager->getWorkflowBottlenecks();
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $workflowManager = new AdminWorkflowManagement();
    
    switch ($action) {
        case 'force_progression':
            $result = $workflowManager->forceWorkflowProgression(
                $input['donor_id'],
                $input['target_stage'],
                $input['admin_id'],
                $input['reason']
            );
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>
```

### 3. Admin Data Export (`assets/php_func/admin/admin_data_export.php`)

```php
<?php
/**
 * Admin Data Export API
 * Comprehensive data export functionality for administrators
 */

include_once '../conn/db_conn.php';

class AdminDataExport {
    
    /**
     * Export donor data in various formats
     */
    public function exportDonorData($format = 'csv', $filters = []) {
        $donors = $this->getFilteredDonors($filters);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCSV($donors);
            case 'excel':
                return $this->exportToExcel($donors);
            case 'json':
                return $this->exportToJSON($donors);
            default:
                throw new Exception('Unsupported export format');
        }
    }
    
    /**
     * Export workflow statistics
     */
    public function exportWorkflowStatistics($dateRange = null) {
        $stats = [];
        
        // Get comprehensive statistics
        $stats['donor_counts'] = $this->getDonorCounts($dateRange);
        $stats['workflow_progression'] = $this->getWorkflowProgression($dateRange);
        $stats['completion_rates'] = $this->getCompletionRates($dateRange);
        $stats['bottlenecks'] = $this->getBottlenecks($dateRange);
        
        return $stats;
    }
    
    /**
     * Export audit trail
     */
    public function exportAuditTrail($dateRange = null, $adminId = null) {
        $filters = [];
        
        if ($dateRange) {
            $filters['timestamp'] = 'gte.' . $dateRange['start'];
            $filters['timestamp'] .= ',lte.' . $dateRange['end'];
        }
        
        if ($adminId) {
            $filters['admin_id'] = 'eq.' . $adminId;
        }
        
        return supabaseRequest('admin_audit_log', 'GET', $filters);
    }
    
    private function getFilteredDonors($filters) {
        $query = 'donor_form?select=*';
        
        if (isset($filters['status'])) {
            $query .= '&status=eq.' . $filters['status'];
        }
        
        if (isset($filters['date_range'])) {
            $query .= '&created_at=gte.' . $filters['date_range']['start'];
            $query .= '&created_at=lte.' . $filters['date_range']['end'];
        }
        
        $response = supabaseRequest($query);
        return $response['data'] ?? [];
    }
    
    private function exportToCSV($data) {
        $filename = 'donor_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
    
    private function exportToJSON($data) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="donor_export_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $format = $_GET['format'] ?? 'csv';
    
    $exportManager = new AdminDataExport();
    
    switch ($action) {
        case 'export_donors':
            $filters = json_decode($_GET['filters'] ?? '{}', true);
            $exportManager->exportDonorData($format, $filters);
            break;
            
        case 'export_statistics':
            $dateRange = json_decode($_GET['date_range'] ?? 'null', true);
            $stats = $exportManager->exportWorkflowStatistics($dateRange);
            header('Content-Type: application/json');
            echo json_encode($stats);
            break;
            
        case 'export_audit':
            $dateRange = json_decode($_GET['date_range'] ?? 'null', true);
            $adminId = $_GET['admin_id'] ?? null;
            $audit = $exportManager->exportAuditTrail($dateRange, $adminId);
            header('Content-Type: application/json');
            echo json_encode($audit);
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>
```

## Admin-Specific CSS Files

### 1. Admin Dashboard Styles (`assets/css/admin/admin-dashboard-styles.css`)

```css
/**
 * Admin Dashboard Styles
 * Enhanced styling for administrative interface
 */

/* Admin Dashboard Layout */
.admin-dashboard {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
}

.admin-sidebar {
    background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    min-height: 100vh;
}

.admin-sidebar .nav-link {
    color: #ecf0f1;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin: 0.25rem 0.5rem;
    transition: all 0.3s ease;
}

.admin-sidebar .nav-link:hover {
    background: rgba(52, 152, 219, 0.2);
    color: #3498db;
    transform: translateX(5px);
}

.admin-sidebar .nav-link.active {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
}

/* Admin Content Area */
.admin-content {
    padding: 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    margin: 1rem;
}

/* Admin Statistics Cards */
.admin-stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 5px solid;
}

.admin-stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.admin-stats-card.pending {
    border-left-color: #f39c12;
}

.admin-stats-card.approved {
    border-left-color: #27ae60;
}

.admin-stats-card.declined {
    border-left-color: #e74c3c;
}

.admin-stats-card.total {
    border-left-color: #3498db;
}

.admin-stats-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.admin-stats-label {
    color: #7f8c8d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Admin Action Buttons */
.admin-action-btn {
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.admin-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.admin-action-btn.override {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.admin-action-btn.bulk {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    color: white;
}

.admin-action-btn.export {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
}

/* Admin Table Enhancements */
.admin-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.admin-table thead th {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    border: none;
    padding: 1.25rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.admin-table tbody td {
    padding: 1rem 1.25rem;
    border-top: 1px solid #ecf0f1;
    vertical-align: middle;
}

.admin-table tbody tr:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: scale(1.01);
    transition: all 0.3s ease;
}

/* Admin Modal Enhancements */
.admin-modal .modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 25px 80px rgba(0,0,0,0.3);
    overflow: hidden;
}

.admin-modal .modal-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
}

.admin-modal .modal-body {
    padding: 2rem;
}

.admin-modal .modal-footer {
    border-top: none;
    padding: 1.5rem 2rem;
    background: #f8f9fa;
}

/* Admin Form Controls */
.admin-form-control {
    border-radius: 10px;
    border: 2px solid #e9ecef;
    padding: 0.875rem 1.25rem;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.admin-form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    transform: translateY(-2px);
}

/* Admin Status Badges */
.admin-status-badge {
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.admin-status-badge.pending {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
}

.admin-status-badge.approved {
    background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    color: white;
}

.admin-status-badge.declined {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
}

.admin-status-badge.override {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
    color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
    .admin-content {
        padding: 1rem;
        margin: 0.5rem;
    }
    
    .admin-stats-card {
        margin-bottom: 1rem;
    }
    
    .admin-stats-number {
        font-size: 2rem;
    }
    
    .admin-table {
        font-size: 0.875rem;
    }
    
    .admin-modal .modal-body {
        padding: 1rem;
    }
}
```

## Admin-Specific JavaScript Files

### 1. Admin Workflow Manager (`assets/js/admin/admin-workflow-manager.js`)

```javascript
/**
 * Admin Workflow Manager
 * Advanced workflow management for administrators
 * Extends the base workflow manager with admin-specific capabilities
 */

class AdminWorkflowManager extends EnhancedWorkflowManager {
    constructor() {
        super();
        this.adminPrivileges = true;
        this.overrideHistory = [];
        this.bulkOperations = [];
    }
    
    /**
     * Override donor eligibility with admin privileges
     */
    async overrideDonorEligibility(donorId, newStatus, reason, adminId) {
        try {
            const response = await fetch('../../assets/php_func/admin/admin_donor_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'override_eligibility',
                    donor_id: donorId,
                    new_status: newStatus,
                    reason: reason,
                    admin_id: adminId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.logOverride(donorId, newStatus, reason, adminId);
                this.showAdminNotification('Eligibility Override', 'Donor eligibility successfully overridden', 'success');
                return result;
            } else {
                throw new Error(result.error || 'Override failed');
            }
        } catch (error) {
            console.error('Error overriding eligibility:', error);
            this.showAdminNotification('Override Error', error.message, 'error');
            throw error;
        }
    }
    
    /**
     * Perform bulk operations on multiple donors
     */
    async performBulkOperation(donorIds, operation, parameters) {
        try {
            const response = await fetch('../../assets/php_func/admin/admin_donor_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'bulk_update',
                    donor_ids: donorIds,
                    operation: operation,
                    parameters: parameters
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.logBulkOperation(donorIds, operation, parameters);
                this.showAdminNotification('Bulk Operation', `Successfully processed ${donorIds.length} donors`, 'success');
                return result;
            } else {
                throw new Error(result.error || 'Bulk operation failed');
            }
        } catch (error) {
            console.error('Error performing bulk operation:', error);
            this.showAdminNotification('Bulk Operation Error', error.message, 'error');
            throw error;
        }
    }
    
    /**
     * Force workflow progression
     */
    async forceWorkflowProgression(donorId, targetStage, reason, adminId) {
        try {
            const response = await fetch('../../assets/php_func/admin/admin_workflow_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'force_progression',
                    donor_id: donorId,
                    target_stage: targetStage,
                    reason: reason,
                    admin_id: adminId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.logForcedProgression(donorId, targetStage, reason, adminId);
                this.showAdminNotification('Workflow Progression', 'Workflow progression forced successfully', 'success');
                return result;
            } else {
                throw new Error(result.error || 'Force progression failed');
            }
        } catch (error) {
            console.error('Error forcing workflow progression:', error);
            this.showAdminNotification('Progression Error', error.message, 'error');
            throw error;
        }
    }
    
    /**
     * Get comprehensive workflow statistics
     */
    async getWorkflowStatistics(dateRange = null) {
        try {
            const url = new URL('../../assets/php_func/admin/admin_workflow_management.php', window.location.origin);
            url.searchParams.append('action', 'get_statistics');
            if (dateRange) {
                url.searchParams.append('date_range', dateRange);
            }
            
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.error);
            }
            
            return result;
        } catch (error) {
            console.error('Error fetching workflow statistics:', error);
            throw error;
        }
    }
    
    /**
     * Get workflow bottlenecks
     */
    async getWorkflowBottlenecks() {
        try {
            const url = new URL('../../assets/php_func/admin/admin_workflow_management.php', window.location.origin);
            url.searchParams.append('action', 'get_bottlenecks');
            
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.error);
            }
            
            return result;
        } catch (error) {
            console.error('Error fetching workflow bottlenecks:', error);
            throw error;
        }
    }
    
    /**
     * Log admin actions for audit trail
     */
    logOverride(donorId, newStatus, reason, adminId) {
        const logEntry = {
            timestamp: new Date().toISOString(),
            action: 'eligibility_override',
            donor_id: donorId,
            new_status: newStatus,
            reason: reason,
            admin_id: adminId
        };
        
        this.overrideHistory.push(logEntry);
        this.saveAuditLog(logEntry);
    }
    
    logBulkOperation(donorIds, operation, parameters) {
        const logEntry = {
            timestamp: new Date().toISOString(),
            action: 'bulk_operation',
            donor_ids: donorIds,
            operation: operation,
            parameters: parameters
        };
        
        this.bulkOperations.push(logEntry);
        this.saveAuditLog(logEntry);
    }
    
    logForcedProgression(donorId, targetStage, reason, adminId) {
        const logEntry = {
            timestamp: new Date().toISOString(),
            action: 'forced_progression',
            donor_id: donorId,
            target_stage: targetStage,
            reason: reason,
            admin_id: adminId
        };
        
        this.saveAuditLog(logEntry);
    }
    
    /**
     * Save audit log to server
     */
    async saveAuditLog(logEntry) {
        try {
            await fetch('../../assets/php_func/admin/admin_audit_log.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'log_entry',
                    log_entry: logEntry
                })
            });
        } catch (error) {
            console.error('Error saving audit log:', error);
        }
    }
    
    /**
     * Show admin-specific notifications
     */
    showAdminNotification(title, message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `admin-notification admin-notification-${type}`;
        notification.innerHTML = `
            <div class="admin-notification-content">
                <div class="admin-notification-icon">
                    <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                </div>
                <div class="admin-notification-text">
                    <div class="admin-notification-title">${title}</div>
                    <div class="admin-notification-message">${message}</div>
                </div>
                <button class="admin-notification-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
}

// Initialize admin workflow manager
window.adminWorkflowManager = new AdminWorkflowManager();
```

## Complete Donor Information Modal System

### Admin Donor Information Modal (`src/views/admin-modals/admin-donor-information-modal.php`)

```php
<?php
/**
 * Admin Donor Information Modal
 * Complete donor information display with admin-specific actions
 * Includes all action buttons and close functionality
 */
?>

<!-- Admin Donor Information Modal -->
<div class="modal fade admin-modal" id="adminDonorInformationModal" tabindex="-1" aria-labelledby="adminDonorInformationModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content admin-modal-content" style="border-radius: 15px; border: none; box-shadow: 0 25px 80px rgba(0,0,0,0.3);">
            <div class="modal-header admin-modal-header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white; border-radius: 15px 15px 0 0;">
                <div class="d-flex align-items-center">
                    <div class="admin-modal-icon me-3">
                        <i class="fas fa-user-md fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="adminDonorInformationModalLabel">
                            <i class="fas fa-user-shield me-2"></i>Admin - Donor Information
                        </h5>
                        <small class="text-white-50">Complete donor details with administrative actions</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="adminDonorModalClose"></button>
            </div>
            
            <div class="modal-body admin-modal-body" style="padding: 2rem; background-color: #ffffff; max-height: 80vh; overflow-y: auto;">
                <!-- Loading State -->
                <div id="adminDonorLoadingState" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading donor information...</p>
                </div>
                
                <!-- Donor Information Content -->
                <div id="adminDonorContent" style="display: none;">
                    <!-- Donor Header Information -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h4 class="mb-1" style="color:#2c3e50; font-weight:700;" id="adminDonorName">
                                    <!-- Populated by JavaScript -->
                                </h4>
                                <div class="text-muted fw-medium" id="adminDonorBasicInfo">
                                    <!-- Populated by JavaScript -->
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="mb-2">
                                    <div class="fw-bold text-dark mb-1" id="adminDonorId">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                    <div class="badge bg-danger fs-6 px-3 py-2" id="adminDonorBloodType">
                                        <!-- Populated by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3" style="border-color: #2c3e50; opacity: 0.3;"/>
                    </div>
                    
                    <!-- Donor Information Tabs -->
                    <ul class="nav nav-tabs admin-nav-tabs" id="adminDonorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                                <i class="fas fa-user me-2"></i>Personal Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="medical-tab" data-bs-toggle="tab" data-bs-target="#medical" type="button" role="tab">
                                <i class="fas fa-stethoscope me-2"></i>Medical History
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="physical-tab" data-bs-toggle="tab" data-bs-target="#physical" type="button" role="tab">
                                <i class="fas fa-heartbeat me-2"></i>Physical Exam
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="collection-tab" data-bs-toggle="tab" data-bs-target="#collection" type="button" role="tab">
                                <i class="fas fa-tint me-2"></i>Blood Collection
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="workflow-tab" data-bs-toggle="tab" data-bs-target="#workflow" type="button" role="tab">
                                <i class="fas fa-tasks me-2"></i>Workflow Status
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content admin-tab-content" id="adminDonorTabContent">
                        <!-- Personal Information Tab -->
                        <div class="tab-pane fade show active" id="personal" role="tabpanel">
                            <div class="row g-3" id="adminPersonalInfo">
                                <!-- Populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Medical History Tab -->
                        <div class="tab-pane fade" id="medical" role="tabpanel">
                            <div id="adminMedicalInfo">
                                <!-- Populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Physical Examination Tab -->
                        <div class="tab-pane fade" id="physical" role="tabpanel">
                            <div id="adminPhysicalInfo">
                                <!-- Populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Blood Collection Tab -->
                        <div class="tab-pane fade" id="collection" role="tabpanel">
                            <div id="adminCollectionInfo">
                                <!-- Populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Workflow Status Tab -->
                        <div class="tab-pane fade" id="workflow" role="tabpanel">
                            <div id="adminWorkflowInfo">
                                <!-- Populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Error State -->
                <div id="adminDonorErrorState" class="text-center py-5" style="display: none;">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="adminDonorErrorMessage">Failed to load donor information</span>
                    </div>
                </div>
            </div>
            
            <!-- Admin Action Buttons -->
            <div class="modal-footer admin-modal-footer" style="border-top: 1px solid #e9ecef; padding: 1.5rem 2rem; background: #f8f9fa;">
                <div class="d-flex justify-content-between w-100">
                    <div class="admin-action-buttons">
                        <button type="button" class="btn btn-outline-primary admin-action-btn" id="adminEditDonorBtn" onclick="adminEditDonor()">
                            <i class="fas fa-edit me-2"></i>Edit Donor
                        </button>
                        <button type="button" class="btn btn-outline-warning admin-action-btn" id="adminOverrideEligibilityBtn" onclick="adminOverrideEligibility()">
                            <i class="fas fa-gavel me-2"></i>Override Eligibility
                        </button>
                        <button type="button" class="btn btn-outline-info admin-action-btn" id="adminViewHistoryBtn" onclick="adminViewHistory()">
                            <i class="fas fa-history me-2"></i>View History
                        </button>
                    </div>
                    <div class="admin-close-buttons">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                        <button type="button" class="btn btn-primary" onclick="adminRefreshDonorData()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Donor Information Modal JavaScript -->
<script>
class AdminDonorInformationModal {
    constructor() {
        this.modal = null;
        this.currentDonorId = null;
        this.currentDonorData = null;
        this.isLoading = false;
    }
    
    /**
     * Open the admin donor information modal
     */
    async openModal(donorId) {
        if (this.isLoading) return;
        
        this.currentDonorId = donorId;
        this.isLoading = true;
        this.showLoading(true);
        
        try {
            // Fetch comprehensive donor data
            const response = await fetch(`../../assets/php_func/admin/comprehensive_donor_details_api.php?donor_id=${donorId}`);
            const result = await response.json();
            
            if (result.error) {
                throw new Error(result.error);
            }
            
            this.currentDonorData = result;
            this.populateModal(result);
            this.showModal();
            
        } catch (error) {
            console.error('Error fetching donor details:', error);
            this.showError('Failed to load donor information: ' + error.message);
        } finally {
            this.isLoading = false;
            this.showLoading(false);
        }
    }
    
    /**
     * Populate modal with donor data
     */
    populateModal(data) {
        const { donor_form, screening_form, medical_history, physical_examination, eligibility, blood_collection } = data;
        
        // Populate header information
        this.populateHeader(donor_form);
        
        // Populate tab content
        this.populatePersonalInfo(donor_form);
        this.populateMedicalInfo(medical_history);
        this.populatePhysicalInfo(physical_examination);
        this.populateCollectionInfo(blood_collection);
        this.populateWorkflowInfo(eligibility);
        
        // Show content
        document.getElementById('adminDonorContent').style.display = 'block';
    }
    
    /**
     * Populate header information
     */
    populateHeader(donor) {
        if (!donor) return;
        
        const fullName = `${donor.surname || ''}, ${donor.first_name || ''} ${donor.middle_name || ''}`.trim();
        const basicInfo = `${this.calculateAge(donor.birthdate)}, ${donor.sex || 'N/A'}`;
        const donorId = `Donor ID: ${donor.prc_donor_number || donor.donor_id}`;
        const bloodType = `${donor.blood_type || 'Unknown'}`;
        
        document.getElementById('adminDonorName').textContent = fullName;
        document.getElementById('adminDonorBasicInfo').innerHTML = `<i class="fas fa-user me-1"></i>${basicInfo}`;
        document.getElementById('adminDonorId').innerHTML = `<i class="fas fa-id-card me-1"></i>${donorId}`;
        document.getElementById('adminDonorBloodType').innerHTML = `<i class="fas fa-tint me-1"></i>${bloodType}`;
    }
    
    /**
     * Populate personal information tab
     */
    populatePersonalInfo(donor) {
        if (!donor) return;
        
        const personalInfo = `
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Birthdate:</label>
                    <div class="bg-light px-3 py-2 rounded">${donor.birthdate || 'N/A'}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Civil Status:</label>
                    <div class="bg-light px-3 py-2 rounded">${donor.civil_status || 'N/A'}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Nationality:</label>
                    <div class="bg-light px-3 py-2 rounded">${donor.nationality || 'N/A'}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Occupation:</label>
                    <div class="bg-light px-3 py-2 rounded">${donor.occupation || 'N/A'}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Permanent Address:</label>
                    <div class="bg-light px-3 py-2 rounded">${donor.permanent_address || 'N/A'}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Mobile:</label>
                    <div class="bg-light px-3 py-2 rounded">${donor.mobile || 'N/A'}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label text-muted mb-1">Email:</label>
                    <div class="bg-light px-3 py-2 rounded">${donor.email || 'N/A'}</div>
                </div>
            </div>
        `;
        
        document.getElementById('adminPersonalInfo').innerHTML = personalInfo;
    }
    
    /**
     * Populate medical history tab
     */
    populateMedicalInfo(medical) {
        if (!medical) {
            document.getElementById('adminMedicalInfo').innerHTML = '<div class="text-center text-muted py-4">No medical history data available</div>';
            return;
        }
        
        const medicalInfo = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Medical History Status:</label>
                        <div class="bg-light px-3 py-2 rounded">${medical.status || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Review Status:</label>
                        <div class="bg-light px-3 py-2 rounded">${medical.needs_review ? 'Needs Review' : 'Reviewed'}</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Notes:</label>
                        <div class="bg-light px-3 py-2 rounded">${medical.notes || 'No notes available'}</div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('adminMedicalInfo').innerHTML = medicalInfo;
    }
    
    /**
     * Populate physical examination tab
     */
    populatePhysicalInfo(physical) {
        if (!physical) {
            document.getElementById('adminPhysicalInfo').innerHTML = '<div class="text-center text-muted py-4">No physical examination data available</div>';
            return;
        }
        
        const physicalInfo = `
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Blood Pressure:</label>
                        <div class="bg-light px-3 py-2 rounded">${physical.blood_pressure || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Pulse Rate:</label>
                        <div class="bg-light px-3 py-2 rounded">${physical.pulse_rate || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Temperature:</label>
                        <div class="bg-light px-3 py-2 rounded">${physical.temperature || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Body Weight:</label>
                        <div class="bg-light px-3 py-2 rounded">${physical.body_weight || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Height:</label>
                        <div class="bg-light px-3 py-2 rounded">${physical.height || 'N/A'}</div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('adminPhysicalInfo').innerHTML = physicalInfo;
    }
    
    /**
     * Populate blood collection tab
     */
    populateCollectionInfo(collection) {
        if (!collection) {
            document.getElementById('adminCollectionInfo').innerHTML = '<div class="text-center text-muted py-4">No blood collection data available</div>';
            return;
        }
        
        const collectionInfo = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Collection Status:</label>
                        <div class="bg-light px-3 py-2 rounded">${collection.status || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Collection Time:</label>
                        <div class="bg-light px-3 py-2 rounded">${collection.start_time || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Blood Bag Type:</label>
                        <div class="bg-light px-3 py-2 rounded">${collection.blood_bag_type || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Volume Collected:</label>
                        <div class="bg-light px-3 py-2 rounded">${collection.volume_collected || 'N/A'}</div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('adminCollectionInfo').innerHTML = collectionInfo;
    }
    
    /**
     * Populate workflow status tab
     */
    populateWorkflowInfo(eligibility) {
        if (!eligibility) {
            document.getElementById('adminWorkflowInfo').innerHTML = '<div class="text-center text-muted py-4">No workflow data available</div>';
            return;
        }
        
        const workflowInfo = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Current Status:</label>
                        <div class="bg-light px-3 py-2 rounded">${eligibility.status || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Donation Type:</label>
                        <div class="bg-light px-3 py-2 rounded">${eligibility.donation_type || 'N/A'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Collection Successful:</label>
                        <div class="bg-light px-3 py-2 rounded">${eligibility.collection_successful ? 'Yes' : 'No'}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted mb-1">Created At:</label>
                        <div class="bg-light px-3 py-2 rounded">${eligibility.created_at || 'N/A'}</div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('adminWorkflowInfo').innerHTML = workflowInfo;
    }
    
    /**
     * Show loading state
     */
    showLoading(show) {
        document.getElementById('adminDonorLoadingState').style.display = show ? 'block' : 'none';
        document.getElementById('adminDonorContent').style.display = show ? 'none' : 'block';
        document.getElementById('adminDonorErrorState').style.display = 'none';
    }
    
    /**
     * Show error state
     */
    showError(message) {
        document.getElementById('adminDonorErrorMessage').textContent = message;
        document.getElementById('adminDonorErrorState').style.display = 'block';
        document.getElementById('adminDonorContent').style.display = 'none';
        document.getElementById('adminDonorLoadingState').style.display = 'none';
    }
    
    /**
     * Show modal
     */
    showModal() {
        this.modal = new bootstrap.Modal(document.getElementById('adminDonorInformationModal'));
        this.modal.show();
    }
    
    /**
     * Calculate age from birthdate
     */
    calculateAge(birthdate) {
        if (!birthdate) return 'N/A';
        const today = new Date();
        const birth = new Date(birthdate);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age + ' years old';
    }
}

// Global admin donor modal instance
window.adminDonorModal = new AdminDonorInformationModal();

// Admin action functions
function adminEditDonor() {
    console.log('Edit donor:', window.adminDonorModal.currentDonorId);
    // Implement edit functionality
}

function adminOverrideEligibility() {
    console.log('Override eligibility:', window.adminDonorModal.currentDonorId);
    // Implement override functionality
}

function adminViewHistory() {
    console.log('View history:', window.adminDonorModal.currentDonorId);
    // Implement history view functionality
}

function adminRefreshDonorData() {
    if (window.adminDonorModal.currentDonorId) {
        window.adminDonorModal.openModal(window.adminDonorModal.currentDonorId);
    }
}

// Event listeners for close buttons
document.addEventListener('DOMContentLoaded', function() {
    // Close button functionality
    document.getElementById('adminDonorModalClose').addEventListener('click', function() {
        if (window.adminDonorModal.modal) {
            window.adminDonorModal.modal.hide();
        }
    });
    
    // Modal close event
    document.getElementById('adminDonorInformationModal').addEventListener('hidden.bs.modal', function() {
        // Clean up when modal is closed
        window.adminDonorModal.currentDonorId = null;
        window.adminDonorModal.currentDonorData = null;
    });
});
</script>
```

## Implementation Instructions

### Step 1: Create Admin Dashboard Files (WITH ACTUAL CONTENT)

**CRITICAL**: Create admin versions with the actual working file contents included below:

```bash
# Copy the main dashboard file
cp public/Dashboards/dashboard-Inventory-System-list-of-donations.php public/Dashboards/admin-dashboard-Inventory-System-list-of-donations.php

# Copy ALL working modules
cp -r public/Dashboards/module public/Dashboards/admin-module

# Copy ALL JavaScript files
cp assets/js/unified-staff-workflow-system.js assets/js/admin-unified-staff-workflow-system.js
cp assets/js/enhanced-workflow-manager.js assets/js/admin-enhanced-workflow-manager.js
cp assets/js/enhanced-data-handler.js assets/js/admin-enhanced-data-handler.js
cp assets/js/enhanced-validation-system.js assets/js/admin-enhanced-validation-system.js
cp assets/js/medical-history-approval.js assets/js/admin-medical-history-approval.js
cp assets/js/screening_form_modal.js assets/js/admin-screening_form_modal.js
cp assets/js/physical_examination_modal.js assets/js/admin-physical_examination_modal.js
cp assets/js/defer_donor_modal.js assets/js/admin-defer_donor_modal.js
cp assets/js/duplicate_donor_check.js assets/js/admin-duplicate_donor_check.js
cp assets/js/staff_donor_modal.js assets/js/admin-staff_donor_modal.js
cp assets/js/phlebotomist_blood_collection_details_modal.js assets/js/admin-phlebotomist_blood_collection_details_modal.js

# Copy ALL CSS files
cp assets/css/enhanced-modal-styles.css assets/css/admin-enhanced-modal-styles.css
cp assets/css/medical-history-approval-modals.css assets/css/admin-medical-history-approval-modals.css
cp assets/css/screening-form-modal.css assets/css/admin-screening-form-modal.css
cp assets/css/defer-donor-modal.css assets/css/admin-defer-donor-modal.css
cp assets/css/physical_examination_modal.css assets/css/admin-physical_examination_modal.css

# Copy ALL PHP API functions
cp assets/php_func/process_physical_examination.php assets/php_func/admin/process_physical_examination.php
cp assets/php_func/process_screening_form.php assets/php_func/admin/process_screening_form.php
cp assets/php_func/process_blood_collection.php assets/php_func/admin/process_blood_collection.php
cp assets/php_func/comprehensive_donor_details_api.php assets/php_func/admin/comprehensive_donor_details_api.php
cp assets/php_func/check_duplicate_donor.php assets/php_func/admin/check_duplicate_donor.php
cp assets/php_func/create_eligibility.php assets/php_func/admin/create_eligibility.php
cp assets/php_func/get_donor_eligibility.php assets/php_func/admin/get_donor_eligibility.php
cp assets/php_func/update_eligibility.php assets/php_func/admin/update_eligibility.php
cp assets/php_func/user_roles_staff.php assets/php_func/admin/user_roles_staff.php
cp assets/php_func/staff_donor_modal_handler.php assets/php_func/admin/staff_donor_modal_handler.php
cp assets/php_func/fetch_donor_info.php assets/php_func/admin/fetch_donor_info.php
cp assets/php_func/medical_history_utils.php assets/php_func/admin/medical_history_utils.php

# Copy ALL modal and form views
cp -r src/views/modals src/views/admin-modals
cp -r src/views/forms src/views/admin-forms
```

#### 1.1 Admin Main Dashboard File

**File**: `public/Dashboards/admin-dashboard-Inventory-System-list-of-donations.php`

```php
<?php
// Include database connection
include_once '../../assets/conn/db_conn.php';

// Light HTTP caching to improve TTFB on slow links (HTML only, app data still fresh)
header('Cache-Control: public, max-age=300, stale-while-revalidate=60');
header('Vary: Accept-Encoding');

// Get the status parameter from URL
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

$donations = [];
$error = null;
$pageTitle = "All Donors";

// File cache (short TTL) to avoid recomputing merged arrays repeatedly while keeping data complete
$cacheTtlSeconds = 180; // short TTL improves responsiveness while reducing recomputation
$cacheKey = 'admin_donations_list_' . ($status ?: 'all');
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
$useCache = false;

if (file_exists($cacheFile)) {
    $age = time() - filemtime($cacheFile);
    if ($age < $cacheTtlSeconds) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            $donations = $cached;
            $useCache = true;
        }
    }
}

// Based on status, include the appropriate module (refresh cache when stale)
try {
    if (!$useCache) {
        switch ($status) {
        case 'pending':
            include_once 'admin-module/donation_pending.php';
            $donations = $pendingDonations ?? [];
            $pageTitle = "Pending Donations";
            break;
        case 'approved':
            include_once 'admin-module/donation_approved.php';
            $donations = $approvedDonations ?? [];
            $pageTitle = "Approved Donations";
            break;
        case 'declined':
        case 'deferred':
            include_once 'admin-module/donation_declined.php';
            $donations = $declinedDonations ?? [];
            $pageTitle = "Declined/Deferred Donations";
            break;
        default:
            // For 'all' status, combine all types
            include_once 'admin-module/donation_pending.php';
            $pending = $pendingDonations ?? [];
            
            include_once 'admin-module/donation_approved.php';
            $approved = $approvedDonations ?? [];
            
            include_once 'admin-module/donation_declined.php';
            $declined = $declinedDonations ?? [];
            
            $donations = array_merge($pending, $approved, $declined);
            $pageTitle = "All Donations";
            break;
        }
        
        // Cache the results
        file_put_contents($cacheFile, json_encode($donations));
    }
} catch (Exception $e) {
    $error = "Error loading donations: " . $e->getMessage();
    error_log("Dashboard error: " . $e->getMessage());
}

// Admin-specific enhancements
$adminCapabilities = [
    'override_eligibility' => true,
    'bulk_operations' => true,
    'advanced_analytics' => true,
    'audit_trail' => true,
    'system_settings' => true
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Admin-specific CSS -->
    <link rel="stylesheet" href="../../assets/css/admin-enhanced-modal-styles.css">
    <link rel="stylesheet" href="../../assets/css/admin-medical-history-approval-modals.css">
    <link rel="stylesheet" href="../../assets/css/admin-screening-form-modal.css">
    <link rel="stylesheet" href="../../assets/css/admin-defer-donor-modal.css">
    <link rel="stylesheet" href="../../assets/css/admin-physical_examination_modal.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Admin Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block admin-sidebar">
                <div class="position-sticky pt-3">
                    <h5 class="text-white mb-3">Admin Dashboard</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin-dashboard-Inventory-System-list-of-donations.php?status=all">
                                <i class="fas fa-tachometer-alt"></i> All Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin-dashboard-Inventory-System-list-of-donations.php?status=pending">
                                <i class="fas fa-clock"></i> Pending
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin-dashboard-Inventory-System-list-of-donations.php?status=approved">
                                <i class="fas fa-check-circle"></i> Approved
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="admin-dashboard-Inventory-System-list-of-donations.php?status=declined">
                                <i class="fas fa-times-circle"></i> Declined
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="#" onclick="showAdminAnalytics()">
                                <i class="fas fa-chart-bar"></i> Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="#" onclick="showAdminSettings()">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshData()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-primary" onclick="showBulkActions()">
                                <i class="fas fa-tasks"></i> Bulk Actions
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Admin Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card admin-stats-card pending">
                            <div class="card-body">
                                <div class="admin-stats-number"><?php echo count(array_filter($donations, function($d) { return $d['status'] === 'pending'; })); ?></div>
                                <div class="admin-stats-label">Pending</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card admin-stats-card approved">
                            <div class="card-body">
                                <div class="admin-stats-number"><?php echo count(array_filter($donations, function($d) { return $d['status'] === 'approved'; })); ?></div>
                                <div class="admin-stats-label">Approved</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card admin-stats-card declined">
                            <div class="card-body">
                                <div class="admin-stats-number"><?php echo count(array_filter($donations, function($d) { return $d['status'] === 'declined'; })); ?></div>
                                <div class="admin-stats-label">Declined</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card admin-stats-card total">
                            <div class="card-body">
                                <div class="admin-stats-number"><?php echo count($donations); ?></div>
                                <div class="admin-stats-label">Total</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Donations Table -->
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-0"><?php echo $pageTitle; ?></h5>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search donations..." id="searchInput">
                                    <button class="btn btn-outline-secondary" type="button" onclick="performSearch()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="donationsTable">
                                    <thead>
                                        <tr>
                                            <th>
                                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                            </th>
                                            <th>Donor ID</th>
                                            <th>Name</th>
                                            <th>Blood Type</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="donation-checkbox" value="<?php echo $donation['donor_id']; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['donor_id']); ?></td>
                                            <td><?php echo htmlspecialchars($donation['name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($donation['blood_type'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="admin-status-badge <?php echo $donation['status']; ?>">
                                                    <?php echo ucfirst($donation['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($donation['created_at'] ?? 'now')); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewDonorDetails('<?php echo $donation['donor_id']; ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="editDonor('<?php echo $donation['donor_id']; ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="overrideEligibility('<?php echo $donation['donor_id']; ?>')">
                                                        <i class="fas fa-gavel"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Admin-specific JavaScript -->
    <script src="../../assets/js/admin-unified-staff-workflow-system.js"></script>
    <script src="../../assets/js/admin-enhanced-workflow-manager.js"></script>
    <script src="../../assets/js/admin-enhanced-data-handler.js"></script>
    <script src="../../assets/js/admin-enhanced-validation-system.js"></script>
    <script src="../../assets/js/admin-medical-history-approval.js"></script>
    <script src="../../assets/js/admin-screening_form_modal.js"></script>
    <script src="../../assets/js/admin-physical_examination_modal.js"></script>
    <script src="../../assets/js/admin-defer_donor_modal.js"></script>

    <script>
        // Admin-specific functions
        function refreshData() {
            location.reload();
        }

        function exportData() {
            // Export functionality
            console.log('Export data');
        }

        function showBulkActions() {
            // Show bulk actions modal
            console.log('Show bulk actions');
        }

        function showAdminAnalytics() {
            // Show admin analytics
            console.log('Show admin analytics');
        }

        function showAdminSettings() {
            // Show admin settings
            console.log('Show admin settings');
        }

        function viewDonorDetails(donorId) {
            // View donor details
            console.log('View donor details:', donorId);
        }

        function editDonor(donorId) {
            // Edit donor
            console.log('Edit donor:', donorId);
        }

        function overrideEligibility(donorId) {
            // Override eligibility
            console.log('Override eligibility:', donorId);
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.donation-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function performSearch() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#donationsTable tbody tr');
            rows.forEach(row => {
                const cells = row.cells;
                let match = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().includes(searchValue)) {
                        match = true;
                        break;
                    }
                }
                row.style.display = match ? '' : 'none';
            });
        }
    </script>
</body>
</html>
```

#### 1.2 Admin Module Files

**File**: `public/Dashboards/admin-module/donation_pending.php`

```php
<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once '../../assets/conn/db_conn.php';

// OPTIMIZATION: Include shared optimized functions
include_once __DIR__ . '/optimized_functions.php';

// OPTIMIZATION: Performance monitoring
$startTime = microtime(true);

// Array to hold donor data
$pendingDonations = [];
$error = null;

try {
    // OPTIMIZATION 1: Pull latest eligibility records to determine donor type (New vs Returning)
    $donorsWithEligibility = [];
    $eligibilityResponse = supabaseRequest("eligibility?select=donor_id,created_at&order=created_at.desc");
    if (isset($eligibilityResponse['data']) && is_array($eligibilityResponse['data'])) {
        foreach ($eligibilityResponse['data'] as $eligibility) {
            if (!empty($eligibility['donor_id'])) {
                $donorsWithEligibility[$eligibility['donor_id']] = true; // set for quick lookup
            }
        }
    }
    
    // OPTIMIZATION 2: Fetch minimal related process tables to compute current pending status
    // Include needs_review flags from each stage to drive pending status logic
    $screeningResponse = supabaseRequest("screening_form?select=screening_id,donor_form_id,needs_review,created_at");
    $medicalResponse   = supabaseRequest("medical_history?select=donor_id,needs_review,updated_at");
    $physicalResponse  = supabaseRequest("physical_examination?select=donor_id,needs_review,created_at");
    $collectionResponse = supabaseRequest("blood_collection?select=screening_id,needs_review,start_time");

    // Build lookup maps
    $screeningByDonorId = [];
    if (isset($screeningResponse['data']) && is_array($screeningResponse['data'])) {
        foreach ($screeningResponse['data'] as $screening) {
            if (!empty($screening['donor_form_id'])) {
                $screeningByDonorId[$screening['donor_form_id']] = $screening;
            }
        }
    }

    $medicalByDonorId = [];
    if (isset($medicalResponse['data']) && is_array($medicalResponse['data'])) {
        foreach ($medicalResponse['data'] as $medical) {
            if (!empty($medical['donor_id'])) {
                $medicalByDonorId[$medical['donor_id']] = $medical;
            }
        }
    }

    $physicalByDonorId = [];
    if (isset($physicalResponse['data']) && is_array($physicalResponse['data'])) {
        foreach ($physicalResponse['data'] as $physical) {
            if (!empty($physical['donor_id'])) {
                $physicalByDonorId[$physical['donor_id']] = $physical;
            }
        }
    }

    $collectionByScreeningId = [];
    if (isset($collectionResponse['data']) && is_array($collectionResponse['data'])) {
        foreach ($collectionResponse['data'] as $collection) {
            if (!empty($collection['screening_id'])) {
                $collectionByScreeningId[$collection['screening_id']] = $collection;
            }
        }
    }

    // OPTIMIZATION 3: Fetch donor_form data with pagination
    $limit = isset($GLOBALS['DONATION_LIMIT']) ? intval($GLOBALS['DONATION_LIMIT']) : 100;
    $offset = isset($GLOBALS['DONATION_OFFSET']) ? intval($GLOBALS['DONATION_OFFSET']) : 0;
    
    $donorResponse = supabaseRequest("donor_form?select=*&order=created_at.desc&limit={$limit}&offset={$offset}");
    
    if (isset($donorResponse['data']) && is_array($donorResponse['data'])) {
        foreach ($donorResponse['data'] as $donor) {
            $donorId = $donor['donor_id'];
            
            // Determine donor type (New vs Returning)
            $donorType = isset($donorsWithEligibility[$donorId]) ? 'Returning' : 'New';
            
            // Compute current process status using needs_review flags
            $currentStatus = 'pending';
            $statusDetails = [];
            
            // Check screening status
            if (isset($screeningByDonorId[$donorId])) {
                $screening = $screeningByDonorId[$donorId];
                if ($screening['needs_review']) {
                    $currentStatus = 'screening_pending';
                    $statusDetails[] = 'Screening Review';
                }
            } else {
                $currentStatus = 'screening_pending';
                $statusDetails[] = 'Screening Required';
            }
            
            // Check medical history status
            if (isset($medicalByDonorId[$donorId])) {
                $medical = $medicalByDonorId[$donorId];
                if ($medical['needs_review']) {
                    $currentStatus = 'medical_pending';
                    $statusDetails[] = 'Medical Review';
                }
            }
            
            // Check physical examination status
            if (isset($physicalByDonorId[$donorId])) {
                $physical = $physicalByDonorId[$donorId];
                if ($physical['needs_review']) {
                    $currentStatus = 'physical_pending';
                    $statusDetails[] = 'Physical Review';
                }
            }
            
            // Check blood collection status
            if (isset($screeningByDonorId[$donorId])) {
                $screening = $screeningByDonorId[$donorId];
                if (isset($collectionByScreeningId[$screening['screening_id']])) {
                    $collection = $collectionByScreeningId[$screening['screening_id']];
                    if ($collection['needs_review']) {
                        $currentStatus = 'collection_pending';
                        $statusDetails[] = 'Collection Review';
                    }
                }
            }
            
            // Build comprehensive donor record
            $pendingDonations[] = [
                'donor_id' => $donorId,
                'name' => trim($donor['first_name'] . ' ' . $donor['surname']),
                'blood_type' => $donor['blood_type'] ?? 'Unknown',
                'donor_type' => $donorType,
                'status' => $currentStatus,
                'status_details' => implode(', ', $statusDetails),
                'created_at' => $donor['created_at'],
                'updated_at' => $donor['updated_at'] ?? $donor['created_at'],
                'admin_actions' => [
                    'can_override' => true,
                    'can_bulk_process' => true,
                    'can_view_history' => true
                ]
            ];
        }
    }

    // Sort by oldest first (FIFO)
    usort($pendingDonations, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });

} catch (Exception $e) {
    $error = "Error loading pending donations: " . $e->getMessage();
    error_log("Pending donations error: " . $e->getMessage());
}

// Performance monitoring
$endTime = microtime(true);
$executionTime = $endTime - $startTime;
error_log("Admin pending donations loaded in {$executionTime} seconds");
?>
```

**File**: `public/Dashboards/admin-module/donation_approved.php`

```php
<?php
// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once '../../assets/conn/db_conn.php';

$approvedDonations = [];
$error = null;

try {
    // OPTIMIZATION 1: Use a single optimized query with joins instead of multiple API calls
    // This fetches all approved donations in one request with donor information
    $optimizedCurl = curl_init();
    
    // FIX: Query for all approved donations - includes status='approved', 'eligible', and collection_successful=true
    // Use Supabase's built-in join capabilities to fetch all data in one request
    $limit = isset($GLOBALS['DONATION_LIMIT']) ? intval($GLOBALS['DONATION_LIMIT']) : 100;
    $offset = isset($GLOBALS['DONATION_OFFSET']) ? intval($GLOBALS['DONATION_OFFSET']) : 0;
    $queryUrl = SUPABASE_URL . "/rest/v1/eligibility?" . http_build_query([
        'select' => 'eligibility_id,donor_id,blood_type,donation_type,created_at,status,collection_successful',
        'or' => '(status.eq.approved,status.eq.eligible,collection_successful.eq.true)',
        'order' => 'created_at.desc',
        'limit' => $limit,
        'offset' => $offset
    ]);
    
    // OPTIMIZATION FOR SLOW INTERNET: Enhanced timeout and retry settings
    curl_setopt_array($optimizedCurl, [
        CURLOPT_URL => $queryUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 60, // Increased timeout for slow connections
        CURLOPT_CONNECTTIMEOUT => 20, // Increased connection timeout
        CURLOPT_TCP_KEEPALIVE => 1, // Enable TCP keepalive
        CURLOPT_TCP_KEEPIDLE => 120, // Keep connection alive for 2 minutes
        CURLOPT_TCP_KEEPINTVL => 60, // Check connection every minute
        CURLOPT_FOLLOWLOCATION => true, // Follow redirects
        CURLOPT_MAXREDIRS => 3, // Limit redirects
        CURLOPT_SSL_VERIFYPEER => false, // Skip SSL verification for faster connection
        CURLOPT_SSL_VERIFYHOST => false, // Skip host verification
        CURLOPT_ENCODING => 'gzip,deflate', // Accept compressed responses
        CURLOPT_USERAGENT => 'BloodDonorSystem/1.0' // Add user agent
    ]);
    
    $response = curl_exec($optimizedCurl);
    $httpCode = curl_getinfo($optimizedCurl, CURLINFO_HTTP_CODE);
    $err = curl_error($optimizedCurl);
    curl_close($optimizedCurl);
    
    if ($err) {
        throw new Exception("cURL Error: " . $err);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: " . $httpCode);
    }
    
    $eligibilityData = json_decode($response, true);
    
    if (!is_array($eligibilityData)) {
        throw new Exception("Invalid response format");
    }
    
    // OPTIMIZATION 2: Batch fetch donor information for all approved donations
    $donorIds = array_column($eligibilityData, 'donor_id');
    $donorIds = array_unique($donorIds);
    
    if (!empty($donorIds)) {
        $donorIdsStr = implode(',', $donorIds);
        $donorCurl = curl_init();
        
        curl_setopt_array($donorCurl, [
            CURLOPT_URL => SUPABASE_URL . "/rest/v1/donor_form?donor_id=in.({$donorIdsStr})&select=donor_id,first_name,surname,blood_type,created_at",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => 120,
            CURLOPT_TCP_KEEPINTVL => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_USERAGENT => 'BloodDonorSystem/1.0'
        ]);
        
        $donorResponse = curl_exec($donorCurl);
        $donorHttpCode = curl_getinfo($donorCurl, CURLINFO_HTTP_CODE);
        $donorErr = curl_error($donorCurl);
        curl_close($donorCurl);
        
        if ($donorErr) {
            throw new Exception("Donor fetch cURL Error: " . $donorErr);
        }
        
        if ($donorHttpCode !== 200) {
            throw new Exception("Donor fetch HTTP Error: " . $donorHttpCode);
        }
        
        $donorData = json_decode($donorResponse, true);
        
        if (!is_array($donorData)) {
            throw new Exception("Invalid donor response format");
        }
        
        // Create lookup array for efficient data processing
        $donorLookup = [];
        foreach ($donorData as $donor) {
            $donorLookup[$donor['donor_id']] = $donor;
        }
        
        // Combine eligibility and donor data
        foreach ($eligibilityData as $eligibility) {
            $donorId = $eligibility['donor_id'];
            $donor = $donorLookup[$donorId] ?? null;
            
            if ($donor) {
                $approvedDonations[] = [
                    'donor_id' => $donorId,
                    'name' => trim($donor['first_name'] . ' ' . $donor['surname']),
                    'blood_type' => $eligibility['blood_type'] ?? $donor['blood_type'] ?? 'Unknown',
                    'donation_type' => $eligibility['donation_type'] ?? 'Unknown',
                    'status' => $eligibility['status'],
                    'collection_successful' => $eligibility['collection_successful'] ?? false,
                    'created_at' => $eligibility['created_at'],
                    'updated_at' => $donor['created_at'],
                    'admin_actions' => [
                        'can_override' => true,
                        'can_view_details' => true,
                        'can_export' => true
                    ]
                ];
            }
        }
    }

} catch (Exception $e) {
    $error = "Error loading approved donations: " . $e->getMessage();
    error_log("Approved donations error: " . $e->getMessage());
}
?>
```

### Step 2: Create Admin-Specific Directories

```bash
# Create admin-specific directories
mkdir -p assets/php_func/admin
mkdir -p assets/css/admin
mkdir -p assets/js/admin
mkdir -p src/views/admin/modals
mkdir -p src/views/admin/forms
mkdir -p public/Dashboards/admin
```

### Step 3: Update File References in Copied Files

After copying the working files, update all file references to use admin versions:

#### 3.1 Update Admin Dashboard Main File
```php
// In admin-dashboard-Inventory-System-list-of-donations.php
// Change the module includes to use admin modules:
switch ($status) {
    case 'pending':
        include_once 'admin-module/donation_pending.php';
        break;
    case 'approved':
        include_once 'admin-module/donation_approved.php';
        break;
    case 'declined':
        include_once 'admin-module/donation_declined.php';
        break;
}

// Update CSS references
echo '<link rel="stylesheet" href="../../assets/css/admin-enhanced-modal-styles.css">';
echo '<link rel="stylesheet" href="../../assets/css/admin-medical-history-approval-modals.css">';
echo '<link rel="stylesheet" href="../../assets/css/admin-screening-form-modal.css">';
echo '<link rel="stylesheet" href="../../assets/css/admin-defer-donor-modal.css">';

// Update JavaScript references
echo '<script src="../../assets/js/admin-unified-staff-workflow-system.js"></script>';
echo '<script src="../../assets/js/admin-enhanced-workflow-manager.js"></script>';
echo '<script src="../../assets/js/admin-enhanced-data-handler.js"></script>';
echo '<script src="../../assets/js/admin-enhanced-validation-system.js"></script>';
echo '<script src="../../assets/js/admin-medical-history-approval.js"></script>';
echo '<script src="../../assets/js/admin-screening_form_modal.js"></script>';
echo '<script src="../../assets/js/admin-physical_examination_modal.js"></script>';
echo '<script src="../../assets/js/admin-defer_donor_modal.js"></script>';
```

#### 3.2 Update Admin Module Files
```php
// In admin-module/donation_pending.php
// Update API function references
include_once '../../assets/php_func/admin/process_physical_examination.php';
include_once '../../assets/php_func/admin/process_screening_form.php';
include_once '../../assets/php_func/admin/comprehensive_donor_details_api.php';
include_once '../../assets/php_func/admin/check_duplicate_donor.php';
include_once '../../assets/php_func/admin/create_eligibility.php';
include_once '../../assets/php_func/admin/get_donor_eligibility.php';
include_once '../../assets/php_func/admin/update_eligibility.php';
include_once '../../assets/php_func/admin/user_roles_staff.php';
include_once '../../assets/php_func/admin/staff_donor_modal_handler.php';
include_once '../../assets/php_func/admin/fetch_donor_info.php';
include_once '../../assets/php_func/admin/medical_history_utils.php';
```

#### 3.3 Update Admin JavaScript Files
```javascript
// In admin-unified-staff-workflow-system.js
// Update API endpoint references
const API_ENDPOINTS = {
    processPhysicalExamination: '../../assets/php_func/admin/process_physical_examination.php',
    processScreeningForm: '../../assets/php_func/admin/process_screening_form.php',
    processBloodCollection: '../../assets/php_func/admin/process_blood_collection.php',
    comprehensiveDonorDetails: '../../assets/php_func/admin/comprehensive_donor_details_api.php',
    checkDuplicateDonor: '../../assets/php_func/admin/check_duplicate_donor.php',
    createEligibility: '../../assets/php_func/admin/create_eligibility.php',
    getDonorEligibility: '../../assets/php_func/admin/get_donor_eligibility.php',
    updateEligibility: '../../assets/php_func/admin/update_eligibility.php',
    staffDonorModalHandler: '../../assets/php_func/admin/staff_donor_modal_handler.php',
    fetchDonorInfo: '../../assets/php_func/admin/fetch_donor_info.php',
    medicalHistoryUtils: '../../assets/php_func/admin/medical_history_utils.php'
};
```

#### 3.4 Update Admin PHP API Functions
```php
// In assets/php_func/admin/process_physical_examination.php
// Update include paths to use admin versions
include_once '../conn/db_conn.php';
include_once '../../public/Dashboards/admin-module/optimized_functions.php';

// Update modal references
$modalPath = '../../src/views/admin-modals/physical-examination-modal.php';
$formPath = '../../src/views/admin-forms/physical-examination-form.php';
```

#### 3.2 Update Admin Module Files
```php
// In admin-module/donation_pending.php
// Add admin-specific functionality while keeping the working supabaseRequest()
include_once '../../assets/conn/db_conn.php';
include_once __DIR__ . '/optimized_functions.php'; // Keep the working function

// Add admin-specific features
class AdminDonationPending extends DonationPending {
    // Add admin override capabilities
    public function adminOverrideEligibility($donorId, $newStatus, $reason) {
        // Use the working supabaseRequest function
        return supabaseRequest("eligibility?donor_id=eq.$donorId", 'PATCH', [
            'status' => $newStatus,
            'override_reason' => $reason,
            'overridden_by' => $_SESSION['user_id'],
            'overridden_at' => date('c')
        ]);
    }
}
```

### Step 4: Database Schema Updates

```sql
-- Create admin audit log table
CREATE TABLE admin_audit_log (
    id SERIAL PRIMARY KEY,
    timestamp TIMESTAMP DEFAULT NOW(),
    action VARCHAR(100) NOT NULL,
    donor_id VARCHAR(50),
    admin_id VARCHAR(50) NOT NULL,
    details JSONB,
    ip_address INET,
    user_agent TEXT
);

-- Create admin workflow statistics view
CREATE VIEW admin_workflow_statistics AS
SELECT 
    COUNT(*) FILTER (WHERE status = 'pending') as pending_count,
    COUNT(*) FILTER (WHERE status = 'approved') as approved_count,
    COUNT(*) FILTER (WHERE status = 'declined') as declined_count,
    COUNT(*) as total_count
FROM eligibility;

-- Add admin override fields to eligibility table
ALTER TABLE eligibility 
ADD COLUMN override_reason TEXT,
ADD COLUMN overridden_by VARCHAR(50),
ADD COLUMN overridden_at TIMESTAMP;
```

### Step 5: Create Admin-Specific Files

1. **DO NOT** replace the working staff files
2. Create admin versions that extend the working system
3. Use the actual working `supabaseRequest()` function from `optimized_functions.php`
4. Test each function individually
5. Implement proper error handling and logging

### Step 6: Testing and Validation

**CRITICAL**: Test that the staff system still works after admin implementation:

1. **Staff System Test**: Verify `dashboard-Inventory-System-list-of-donations.php` still works
2. **Admin System Test**: Test all admin functions individually
3. **Integration Test**: Test admin system with existing staff system
4. **Database Test**: Verify Supabase connections work for both systems
5. **Security Test**: Validate admin access controls
6. **UI Test**: Test responsive design for both systems

### Step 7: Complete Admin File Structure

After implementation, you should have this complete structure:

```
REDCROSS/
├── public/Dashboards/
│   ├── dashboard-Inventory-System-list-of-donations.php (ORIGINAL - DO NOT MODIFY)
│   ├── admin-dashboard-Inventory-System-list-of-donations.php (ADMIN COPY)
│   ├── module/ (ORIGINAL - DO NOT MODIFY)
│   │   ├── donation_pending.php
│   │   ├── donation_approved.php
│   │   ├── donation_declined.php
│   │   └── optimized_functions.php (Contains working supabaseRequest())
│   └── admin-module/ (ADMIN COPIES)
│       ├── donation_pending.php
│       ├── donation_approved.php
│       ├── donation_declined.php
│       └── optimized_functions.php
├── assets/
│   ├── conn/
│   │   └── db_conn.php (SHARED - DO NOT MODIFY)
│   ├── css/
│   │   ├── enhanced-modal-styles.css (ORIGINAL)
│   │   ├── admin-enhanced-modal-styles.css (ADMIN COPY)
│   │   ├── medical-history-approval-modals.css (ORIGINAL)
│   │   ├── admin-medical-history-approval-modals.css (ADMIN COPY)
│   │   ├── screening-form-modal.css (ORIGINAL)
│   │   ├── admin-screening-form-modal.css (ADMIN COPY)
│   │   ├── defer-donor-modal.css (ORIGINAL)
│   │   ├── admin-defer-donor-modal.css (ADMIN COPY)
│   │   ├── physical_examination_modal.css (ORIGINAL)
│   │   └── admin-physical_examination_modal.css (ADMIN COPY)
│   ├── js/
│   │   ├── unified-staff-workflow-system.js (ORIGINAL)
│   │   ├── admin-unified-staff-workflow-system.js (ADMIN COPY)
│   │   ├── enhanced-workflow-manager.js (ORIGINAL)
│   │   ├── admin-enhanced-workflow-manager.js (ADMIN COPY)
│   │   ├── enhanced-data-handler.js (ORIGINAL)
│   │   ├── admin-enhanced-data-handler.js (ADMIN COPY)
│   │   ├── enhanced-validation-system.js (ORIGINAL)
│   │   ├── admin-enhanced-validation-system.js (ADMIN COPY)
│   │   ├── medical-history-approval.js (ORIGINAL)
│   │   ├── admin-medical-history-approval.js (ADMIN COPY)
│   │   ├── screening_form_modal.js (ORIGINAL)
│   │   ├── admin-screening_form_modal.js (ADMIN COPY)
│   │   ├── physical_examination_modal.js (ORIGINAL)
│   │   ├── admin-physical_examination_modal.js (ADMIN COPY)
│   │   ├── defer_donor_modal.js (ORIGINAL)
│   │   ├── admin-defer_donor_modal.js (ADMIN COPY)
│   │   ├── duplicate_donor_check.js (ORIGINAL)
│   │   ├── admin-duplicate_donor_check.js (ADMIN COPY)
│   │   ├── staff_donor_modal.js (ORIGINAL)
│   │   ├── admin-staff_donor_modal.js (ADMIN COPY)
│   │   ├── phlebotomist_blood_collection_details_modal.js (ORIGINAL)
│   │   └── admin-phlebotomist_blood_collection_details_modal.js (ADMIN COPY)
│   └── php_func/
│       ├── process_physical_examination.php (ORIGINAL)
│       ├── admin/process_physical_examination.php (ADMIN COPY)
│       ├── process_screening_form.php (ORIGINAL)
│       ├── admin/process_screening_form.php (ADMIN COPY)
│       ├── process_blood_collection.php (ORIGINAL)
│       ├── admin/process_blood_collection.php (ADMIN COPY)
│       ├── comprehensive_donor_details_api.php (ORIGINAL)
│       ├── admin/comprehensive_donor_details_api.php (ADMIN COPY)
│       ├── check_duplicate_donor.php (ORIGINAL)
│       ├── admin/check_duplicate_donor.php (ADMIN COPY)
│       ├── create_eligibility.php (ORIGINAL)
│       ├── admin/create_eligibility.php (ADMIN COPY)
│       ├── get_donor_eligibility.php (ORIGINAL)
│       ├── admin/get_donor_eligibility.php (ADMIN COPY)
│       ├── update_eligibility.php (ORIGINAL)
│       ├── admin/update_eligibility.php (ADMIN COPY)
│       ├── user_roles_staff.php (ORIGINAL)
│       ├── admin/user_roles_staff.php (ADMIN COPY)
│       ├── staff_donor_modal_handler.php (ORIGINAL)
│       ├── admin/staff_donor_modal_handler.php (ADMIN COPY)
│       ├── fetch_donor_info.php (ORIGINAL)
│       ├── admin/fetch_donor_info.php (ADMIN COPY)
│       ├── medical_history_utils.php (ORIGINAL)
│       └── admin/medical_history_utils.php (ADMIN COPY)
└── src/views/
    ├── modals/ (ORIGINAL - DO NOT MODIFY)
    │   ├── medical-history-approval-modals.php
    │   ├── physical-examination-modal.php
    │   ├── defer-donor-modal.php
    │   └── blood-collection-modal.php
    ├── admin-modals/ (ADMIN COPIES)
    │   ├── medical-history-approval-modals.php
    │   ├── physical-examination-modal.php
    │   ├── defer-donor-modal.php
    │   └── blood-collection-modal.php
    ├── forms/ (ORIGINAL - DO NOT MODIFY)
    │   ├── medical-history-modal-content.php
    │   ├── staff_donor_initial_screening_form_modal.php
    │   └── declaration-form-modal-content.php
    └── admin-forms/ (ADMIN COPIES)
        ├── medical-history-modal-content.php
        ├── staff_donor_initial_screening_form_modal.php
        └── declaration-form-modal-content.php
```

### Step 8: Preserve Working System

**CRITICAL**: The admin implementation should:
- **NOT** modify existing staff files (marked as ORIGINAL)
- **NOT** change the working `supabaseRequest()` function
- **NOT** alter the current database schema
- **ONLY** add new admin-specific files and features (marked as ADMIN COPY)
- **SHARE** the database connection (`db_conn.php`)
- **SHARE** the optimized functions (`optimized_functions.php`)

---

*This implementation guide provides comprehensive templates for creating admin-specific functionality that mirrors the staff-side dashboard while providing enhanced administrative capabilities.*
