# Data Transfer Implementation: Screening Form → Medical History → Declaration Form

## Overview

This implementation enables the transfer of screening form data through the medical history review process and into the final declaration form. The data flows through the same file processing system, ensuring all screening information (body weight, specific gravity, blood type, donation type, etc.) is preserved and available throughout the entire donor registration workflow.

## Data Flow Architecture

```
Screening Form → Medical History Review → Declaration Form
     ↓                    ↓                    ↓
Initial Data        Transfer & Store      Display & Use
```

## Key Components Modified

### 1. Medical History Process (`src/views/forms/medical-history-process.php`)

**New Features:**
- **Screening Data Transfer**: Automatically fetches screening form data for the donor
- **Data Storage**: Stores screening data in medical history record with `screening_` prefix
- **Session Management**: Stores transferred data in session for declaration form

**Data Fields Transferred:**
- `body_weight` → `screening_body_weight`
- `specific_gravity` → `screening_specific_gravity`
- `blood_type` → `screening_blood_type`
- `donation_type` → `screening_donation_type`
- `mobile_location` → `screening_mobile_location`
- `mobile_organizer` → `screening_mobile_organizer`
- `patient_name` → `screening_patient_name`
- `hospital` → `screening_hospital`
- `patient_blood_type` → `screening_patient_blood_type`
- `component_type` → `screening_component_type`
- `units_needed` → `screening_units_needed`

**Code Implementation:**
```php
// NEW: Transfer screening form data to medical history
$screening_data_to_transfer = [
    'body_weight', 'specific_gravity', 'blood_type', 'donation_type',
    'mobile_location', 'mobile_organizer', 'patient_name', 'hospital',
    'patient_blood_type', 'component_type', 'units_needed'
];

// Fetch screening data for this donor
$ch = curl_init(SUPABASE_URL . '/rest/v1/screening_form?donor_form_id=eq.' . $donor_id . '&order=created_at.desc&limit=1');
// ... fetch and transfer data
```

### 2. Declaration Form Process (`src/views/forms/declaration-form-process.php`)

**New Features:**
- **Data Retrieval**: Retrieves transferred screening data from session
- **Complete Data Package**: Creates comprehensive declaration data including all screening information
- **Session Cleanup**: Properly cleans up session data after completion

**Code Implementation:**
```php
// Get transferred screening data from session
if (isset($_SESSION['transferred_screening_data'])) {
    $screening_data = $_SESSION['transferred_screening_data'];
    
    // Transfer screening fields to declaration
    $screening_fields_to_transfer = [
        'screening_body_weight', 'screening_specific_gravity', 'screening_blood_type', 
        'screening_donation_type', 'screening_mobile_location', 'screening_mobile_organizer',
        'screening_patient_name', 'screening_hospital', 'screening_patient_blood_type',
        'screening_component_type', 'screening_units_needed'
    ];
    
    foreach ($screening_fields_to_transfer as $field) {
        if (isset($screening_data[$field])) {
            $declaration_data[$field] = $screening_data[$field];
        }
    }
}
```

### 3. Declaration Form Modal Content (`src/views/forms/declaration-form-modal-content.php`)

**New Features:**
- **Screening Information Display**: Shows all transferred screening data in a dedicated section
- **Visual Design**: Green-themed section to distinguish screening data from donor info
- **Conditional Display**: Only shows relevant fields based on donation type
- **Medical Approval Status**: Displays the medical review outcome

**Visual Implementation:**
```html
<div class="screening-info">
    <h4><i class="fas fa-clipboard-check me-2"></i>Screening Information</h4>
    <div class="screening-grid">
        <!-- Body Weight, Specific Gravity, Blood Type, etc. -->
    </div>
</div>
```

## Database Schema Considerations

The implementation uses the existing database structure with the following approach:

1. **Medical History Table**: Stores screening data with `screening_` prefix
2. **Session Storage**: Temporary storage during the workflow
3. **No Schema Changes**: Uses existing fields and adds new prefixed fields

## Workflow Process

### Step 1: Initial Screening Form
- User fills out screening form with basic measurements
- Data stored in `screening_form` table
- Includes: body weight, specific gravity, blood type, donation type, etc.

### Step 2: Medical History Review
- System automatically fetches screening data for the donor
- Transfers screening data to medical history record
- Medical reviewer can see all screening information
- Approval/decline decision made

### Step 3: Declaration Form
- All screening data automatically available
- Displayed in dedicated "Screening Information" section
- Medical approval status shown
- Complete data package for final declaration

## Benefits

1. **Data Integrity**: All screening information preserved throughout workflow
2. **User Experience**: No need to re-enter data at each step
3. **Audit Trail**: Complete record of all information in final declaration
4. **Efficiency**: Streamlined process with automatic data transfer
5. **Consistency**: Same data processing file handles entire workflow

## Error Handling

- **Missing Screening Data**: Graceful handling if screening data not found
- **Database Errors**: Proper error logging and user feedback
- **Session Issues**: Fallback mechanisms for session data
- **Network Issues**: Retry logic for API calls

## Security Considerations

- **Data Validation**: All transferred data validated and sanitized
- **Session Security**: Proper session management and cleanup
- **Access Control**: Role-based access maintained throughout
- **Audit Logging**: All data transfers logged for security

## Testing Recommendations

1. **End-to-End Testing**: Complete workflow from screening to declaration
2. **Data Validation**: Verify all fields transfer correctly
3. **Error Scenarios**: Test missing data, network failures
4. **User Roles**: Test with different user permissions
5. **Mobile Responsiveness**: Test on different screen sizes

## Future Enhancements

1. **Real-time Validation**: Validate screening data during medical review
2. **Data Export**: Export complete donor package with all information
3. **Advanced Filtering**: Filter donors based on screening criteria
4. **Reporting**: Generate reports with screening and medical data
5. **Integration**: Connect with external systems using transferred data

## Conclusion

This implementation successfully creates a unified data flow from the initial screening form through medical history review to the final declaration form. All screening information is preserved and available throughout the process, ensuring data integrity and improving user experience. The solution uses existing database structures and maintains security while providing a seamless workflow for donor registration.
