<?php
/**
 * Medical History Modal with Approve/Decline Buttons Example
 * This file shows how to add approve and decline buttons to a medical history modal
 * Use this as a reference when implementing in your actual medical history modals
 */
?>

<!-- Example: Where to add the DECLINE BUTTON in your HEALTH & RISK ASSESSMENT modal -->

<!-- 
IN YOUR EXISTING HEALTH & RISK ASSESSMENT MODAL, 
ADD THIS BUTTON IN THE MODAL FOOTER WHERE YOU CURRENTLY HAVE:

"Edit" button and "Next →" button

REPLACE OR ADD TO YOUR MODAL FOOTER:
-->

<!-- Modal Footer with Approve/Decline Buttons -->
<div class="modal-footer border-0">
    <!-- Your existing buttons -->
    <button type="button" class="btn btn-info px-4">
        <i class="fas fa-pencil me-2"></i>Edit
    </button>
    
    <!-- ADD THIS DECLINE BUTTON HERE -->
    <button type="button" class="btn btn-outline-danger decline-medical-history-btn px-4" 
            data-donor-id="[YOUR_DONOR_ID]" data-screening-id="[YOUR_SCREENING_ID]">
        <i class="fas fa-times-circle me-2"></i>Decline
    </button>
    
    <!-- ADD THIS APPROVE BUTTON HERE -->
    <button type="button" class="btn btn-success approve-medical-history-btn px-4"
            data-donor-id="[YOUR_DONOR_ID]" data-screening-id="[YOUR_SCREENING_ID]">
        <i class="fas fa-check-circle me-2"></i>Approve
    </button>
    
    <!-- Your existing Next button -->
    <button type="button" class="btn btn-danger px-4">
        <i class="fas fa-arrow-right me-2"></i>Next →
    </button>
</div>

<!-- 
COMPLETE EXAMPLE OF YOUR MODAL FOOTER SHOULD LOOK LIKE THIS:

<div class="modal-footer border-0">
    <button type="button" class="btn btn-info px-4">
        <i class="fas fa-pencil me-2"></i>Edit
    </button>
    
    <button type="button" class="btn btn-outline-danger decline-medical-history-btn px-4" 
            data-donor-id="<?php echo $donor_id; ?>" data-screening-id="<?php echo $screening_id; ?>">
        <i class="fas fa-times-circle me-2"></i>Decline
    </button>
    
    <button type="button" class="btn btn-success approve-medical-history-btn px-4"
            data-donor-id="<?php echo $donor_id; ?>" data-screening-id="<?php echo $screening_id; ?>">
        <i class="fas fa-check-circle me-2"></i>Approve
    </button>
    
    <button type="button" class="btn btn-danger px-4">
        <i class="fas fa-arrow-right me-2"></i>Next →
    </button>
</div>

IMPORTANT: Replace [YOUR_DONOR_ID] and [YOUR_SCREENING_ID] with your actual PHP variables
-->

<!-- 
WHAT HAPPENS WHEN BUTTONS ARE CLICKED:

1. DECLINE BUTTON:
   - Shows "Decline Medical History?" modal
   - User must provide reason for declining
   - User selects restriction type (Temporary/Permanent)
   - If Temporary: User picks exact date when they can donate again
   - User can add additional notes
   - Shows "Medical History Declined" confirmation

2. APPROVE BUTTON:
   - Shows "Medical History Approved" modal
   - Auto-closes after 3 seconds
   - Proceeds with approval process

3. BOTH BUTTONS:
   - Automatically get donor_id and screening_id from data attributes
   - Show appropriate modals
   - Handle form validation
   - Provide user feedback
-->

<!-- 
TO MAKE THIS WORK IN YOUR EXISTING MODAL:

1. Add the buttons to your modal footer (as shown above)

2. Include these files in your main page:
   <?php include '../../src/views/modals/medical-history-approval-modals.php'; ?>
   <link rel="stylesheet" href="../../assets/css/medical-history-approval-modals.css">
   <script src="../../assets/js/medical-history-approval.js"></script>

3. Initialize the functionality:
   if (typeof initializeMedicalHistoryApproval === 'function') {
       initializeMedicalHistoryApproval();
   }

4. Make sure your buttons have the correct CSS classes:
   - decline-medical-history-btn
   - approve-medical-history-btn

5. Make sure your buttons have the correct data attributes:
   - data-donor-id="[actual_donor_id]"
   - data-screening-id="[actual_screening_id]"
-->
