<?php
/**
 * Blood Collection View Modal - Admin
 * Display-only modal showing blood collection details from the summary
 */
?>

<!-- Blood Collection View Modal - Admin -->
<div class="modal fade" id="bloodCollectionViewModalAdmin" tabindex="-1" aria-labelledby="bloodCollectionViewModalAdminLabel" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="background: #941022; color: white; border-radius: 15px 15px 0 0;">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-tint fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="bloodCollectionViewModalAdminLabel">Blood Collection Details (Admin)</h5>
                        <small class="text-white-50">Collection information and status</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body" style="padding: 1.5rem;">
                <!-- Donor Information -->
                <div class="donor-info-section mb-4">
                    <h6 class="text-danger mb-3"><i class="fas fa-user me-2"></i>Donor Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <span id="admin-view-donor-name">-</span></p>
                            <p><strong>Age & Gender:</strong> <span id="admin-view-donor-age-gender">-</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Donor ID:</strong> <span id="admin-view-donor-id">-</span></p>
                            <p><strong>Blood Type:</strong> <span id="admin-view-blood-type">-</span></p>
                        </div>
                    </div>
                </div>
                
                <!-- Collection Details -->
                <div class="collection-details-section">
                    <h6 class="text-danger mb-3"><i class="fas fa-clipboard-list me-2"></i>Collection Details</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Collection Date</label>
                            <input type="text" class="form-control" id="admin-view-collection-date" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bag Type</label>
                            <input type="text" class="form-control" id="admin-view-bag-type" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Serial Number</label>
                            <input type="text" class="form-control" id="admin-view-unit-serial" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Collection Status</label>
                            <input type="text" class="form-control" id="admin-view-collection-status" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="text" class="form-control" id="admin-view-start-time" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="text" class="form-control" id="admin-view-end-time" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount Taken</label>
                            <input type="text" class="form-control" id="admin-view-amount-taken" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phlebotomist</label>
                            <input type="text" class="form-control" id="admin-view-phlebotomist" readonly>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Donor Reaction</label>
                            <textarea class="form-control" id="admin-view-donor-reaction" rows="3" readonly></textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Management Done</label>
                            <textarea class="form-control" id="admin-view-management-done" rows="3" readonly></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="background: #f8f9fa; border-radius: 0 0 15px 15px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin Blood Collection View Modal Styles */
#bloodCollectionViewModalAdmin .form-control[readonly] {
    background-color: #f8f9fa;
    cursor: default;
}

#bloodCollectionViewModalAdmin .modal-header {
    border-bottom: none;
}

#bloodCollectionViewModalAdmin .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}
</style>
