<?php
/**
 * Admin Donor Registration Modal
 * Modal wrapper for the admin-only donor registration flow
 * This modal loads step 1 (Personal Data) and step 2 (Medical History) dynamically
 */
?>

<!-- Admin Donor Registration Modal -->
<div class="modal fade" id="adminDonorRegistrationModal" tabindex="-1" aria-labelledby="adminDonorRegistrationModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 900px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #b22222; color: white; border-bottom: none;">
                <h5 class="modal-title" id="adminDonorRegistrationModalLabel">
                    <i class="fas fa-user-plus me-2"></i>
                    <span id="modalStepTitle">Register New Donor</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" aria-label="Close" id="adminRegistrationCloseBtn"></button>
            </div>
            <div class="modal-body" id="adminRegistrationModalBody" style="min-height: 400px; max-height: 70vh; overflow-y: auto;">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-5">
                    <div class="spinner-border text-danger" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading registration form...</p>
                </div>
            </div>
            <div class="modal-footer" id="adminRegistrationModalFooter" style="display: none;">
                <!-- Footer buttons will be managed by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Include Mobile Credentials Modal -->
<?php include 'mobile-credentials-modal.php'; ?>

<!-- Include Admin Screening Form Modal -->
<?php 
$screeningModalPath = __DIR__ . '/../forms/admin_donor_initial_screening_form_modal.php';
if (file_exists($screeningModalPath)) {
    include $screeningModalPath;
}
?>

<!-- Styles for the registration modal -->
<style>
/* Import styles from donor-form-modal for consistency */
#adminDonorRegistrationModal .steps-container {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px 0;
    margin-bottom: 20px;
}

#adminDonorRegistrationModal .step-item {
    display: flex;
    align-items: center;
}

#adminDonorRegistrationModal .step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    z-index: 1;
    position: relative;
}

#adminDonorRegistrationModal .step-number.active {
    background-color: #b22222;
    color: white;
}

#adminDonorRegistrationModal .step-number.inactive {
    background-color: white;
    color: #b22222;
    border: 2px solid #b22222;
}

#adminDonorRegistrationModal .step-number.completed {
    background-color: #b22222;
    color: white;
}

#adminDonorRegistrationModal .step-line {
    width: 80px;
    height: 2px;
    background-color: #dee2e6;
    position: relative;
    top: 0;
}

#adminDonorRegistrationModal .step-line.active {
    background-color: #b22222;
}

#adminDonorRegistrationModal .section-title {
    color: #b22222;
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 5px;
}

#adminDonorRegistrationModal .section-details {
    color: #666;
    margin-bottom: 20px;
    font-size: 14px;
}

#adminDonorRegistrationModal .form-section {
    display: none;
}

#adminDonorRegistrationModal .form-section.active {
    display: block;
}

#adminDonorRegistrationModal .navigation-buttons {
    display: flex;
    justify-content: space-between;
    padding-top: 20px;
    margin-top: 30px;
    border-top: 1px solid #dee2e6;
}

#adminDonorRegistrationModal .horizontal-line {
    height: 1px;
    background-color: #dee2e6;
    margin: 15px 0;
}
</style>

<!-- Admin duplicate checker - Loaded in dashboard to prevent multiple loads -->

