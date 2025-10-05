<?php
/**
 * Blood Collection Modal (Admin)
 * Admin-specific copy aligned to assets/js/blood_collection_modal_admin.js DOM hooks
 */
?>

<!-- Blood Collection Modal (Admin) -->
<div class="modal fade" id="bloodCollectionModal" tabindex="-1" aria-labelledby="bloodCollectionModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-tint fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="bloodCollectionModalLabel">Blood Collection Process</h5>
                        <small class="text-white-50">To be accomplished by the phlebotomist</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white blood-close-btn" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Progress Indicator -->
            <div class="blood-progress-container" style="padding: 1rem 1.5rem 0.5rem; background: #fff;">
                <div class="blood-progress-steps" style="display: flex; gap: 10px;">
                    <div class="blood-step active" data-step="1" style="display: flex; align-items: center; gap: 6px; opacity: 1; font-weight: 600;">
                        <div class="blood-step-number" style="width: 26px; height: 26px; border-radius: 50%; background: #b22222; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600;">1</div>
                        <div class="blood-step-label">Bag Selection</div>
                    </div>
                    <div class="blood-step" data-step="2" style="display: flex; align-items: center; gap: 6px; opacity: 0.6;">
                        <div class="blood-step-number" style="width: 26px; height: 26px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 600;">2</div>
                        <div class="blood-step-label">Collection Details</div>
                    </div>
                    <div class="blood-step" data-step="3" style="display: flex; align-items: center; gap: 6px; opacity: 0.6;">
                        <div class="blood-step-number" style="width: 26px; height: 26px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 600;">3</div>
                        <div class="blood-step-label">Timing</div>
                    </div>
                    <div class="blood-step" data-step="4" style="display: flex; align-items: center; gap: 6px; opacity: 0.6;">
                        <div class="blood-step-number" style="width: 26px; height: 26px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 600;">4</div>
                        <div class="blood-step-label">Status</div>
                    </div>
                    <div class="blood-step" data-step="5" style="display: flex; align-items: center; gap: 6px; opacity: 0.6;">
                        <div class="blood-step-number" style="width: 26px; height: 26px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-weight: 600;">5</div>
                        <div class="blood-step-label">Review</div>
                    </div>
                </div>
                <div class="blood-progress-line" style="height: 4px; background: #f1f3f5; border-radius: 2px; margin: 8px 0 0; position: relative; overflow: hidden;">
                    <div class="blood-progress-fill" style="height: 100%; width: 20%; background: #b22222; transition: width .3s ease;"></div>
                </div>
            </div>

            <div class="modal-body" style="padding: 1.5rem; background-color: #ffffff; max-height: 70vh; overflow-y: auto;">
                <!-- Donor quick summary (for JS to populate) -->
                <div class="mb-3" style="display:flex; gap: 1rem; align-items: center;">
                    <div><strong>Donor:</strong> <span id="blood-donor-name-display">-</span></div>
                    <div><strong>Date:</strong> <span id="blood-collection-date-display">-</span></div>
                    <div><strong>Serial:</strong> <span id="blood-unit-serial-display">-</span></div>
                </div>

                <form id="bloodCollectionForm">
                    <input type="hidden" id="blood-bag-type-label" name="blood_bag_type_label" value="">
                    <input type="hidden" name="donor_id" value="">
                    <input type="hidden" name="screening_id" value="">
                    <input type="hidden" name="physical_exam_id" value="">
                    
                    <!-- Step 1: Blood Bag Selection -->
                    <div class="blood-step-content active" data-step="1" id="blood-step-1">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-tint me-2 text-danger"></i>Blood Bag Selection</h6>
                            <p class="text-muted mb-4">Select the appropriate blood bag type for collection</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="bag-option" style="border: 2px solid #e9ecef; border-radius: 10px; padding: 1rem; cursor: pointer; transition: all 0.3s ease;">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 10px;">
                                        <input type="radio" name="blood_bag_type" value="Single" required style="margin: 0;">
                                        <div>
                                            <strong>Single Bag</strong>
                                            <small class="text-muted d-block">Standard single unit collection</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="bag-option" style="border: 2px solid #e9ecef; border-radius: 10px; padding: 1rem; cursor: pointer; transition: all 0.3s ease;">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 10px;">
                                        <input type="radio" name="blood_bag_type" value="Multiple" required style="margin: 0;">
                                        <div>
                                            <strong>Multiple Bags</strong>
                                            <small class="text-muted d-block">Multi-unit collection setup</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="bag-option" style="border: 2px solid #e9ecef; border-radius: 10px; padding: 1rem; cursor: pointer; transition: all 0.3s ease;">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 10px;">
                                        <input type="radio" name="blood_bag_type" value="Top & Bottom" required style="margin: 0;">
                                        <div>
                                            <strong>Top & Bottom</strong>
                                            <small class="text-muted d-block">Special collection method</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="bag-option" style="border: 2px solid #e9ecef; border-radius: 10px; padding: 1rem; cursor: pointer; transition: all 0.3s ease;">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 10px;">
                                        <input type="radio" name="blood_bag_type" value="Apheresis" required style="margin: 0;">
                                        <div>
                                            <strong>Apheresis</strong>
                                            <small class="text-muted d-block">Automated collection process</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Collection Details -->
                    <div class="blood-step-content" data-step="2" id="blood-step-2" style="display: none;">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-clipboard-list me-2 text-danger"></i>Collection Details</h6>
                            <p class="text-muted mb-4">Enter the collection information</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="blood-unit-serial" class="form-label">Unit Serial Number *</label>
                                <input type="text" class="form-control" id="blood-unit-serial" name="unit_serial_number" readonly>
                                <div class="form-text">Auto-generated serial number</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="collection_date" class="form-label">Collection Date *</label>
                                <input type="date" class="form-control" id="collection_date" name="collection_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="blood_type" class="form-label">Blood Type</label>
                                <input type="text" class="form-control" id="blood_type" name="blood_type" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="donor_weight" class="form-label">Donor Weight (kg)</label>
                                <input type="number" class="form-control" id="donor_weight" name="donor_weight" min="50" max="200">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Timing -->
                    <div class="blood-step-content" data-step="3" id="blood-step-3" style="display: none;">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-clock me-2 text-danger"></i>Collection Timing</h6>
                            <p class="text-muted mb-4">Record the start and end times of collection</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="blood-start-time" class="form-label">Start Time *</label>
                                <input type="time" class="form-control" id="blood-start-time" name="start_time" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="blood-end-time" class="form-label">End Time *</label>
                                <input type="time" class="form-control" id="blood-end-time" name="end_time" required>
                                <div class="form-text">Must be at least 5 minutes after start time</div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="collection_notes" class="form-label">Collection Notes</label>
                                <textarea class="form-control" id="collection_notes" name="collection_notes" rows="3" placeholder="Any additional notes about the collection process..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Collection Status -->
                    <div class="blood-step-content" data-step="4" id="blood-step-4" style="display: none;">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-check-circle me-2 text-danger"></i>Collection Status</h6>
                            <p class="text-muted mb-4">Record the outcome of the blood collection</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="blood-status-card" style="border: 2px solid #e9ecef; border-radius: 10px; padding: 1.5rem; cursor: pointer; transition: all 0.3s ease;">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 15px;">
                                        <input type="radio" name="is_successful" value="YES" style="margin: 0;">
                                        <div class="text-center">
                                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                            <div><strong>Successful</strong></div>
                                            <small class="text-muted">Collection completed successfully</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="blood-status-card" style="border: 2px solid #e9ecef; border-radius: 10px; padding: 1.5rem; cursor: pointer; transition: all 0.3s ease;">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 15px;">
                                        <input type="radio" name="is_successful" value="NO" style="margin: 0;">
                                        <div class="text-center">
                                            <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                            <div><strong>Unsuccessful</strong></div>
                                            <small class="text-muted">Collection had issues</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Donor Reaction Section (shown when unsuccessful) -->
                        <div class="blood-reaction-section" style="display: none;">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="donor_reaction" class="form-label">Donor Reaction *</label>
                                    <textarea class="form-control" id="donor_reaction" name="donor_reaction" rows="3" placeholder="Describe any adverse reactions or issues during collection..."></textarea>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="management_done" class="form-label">Management Done</label>
                                    <textarea class="form-control" id="management_done" name="management_done" rows="3" placeholder="Actions taken to manage the reaction..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 5: Review and Submit -->
                    <div class="blood-step-content" data-step="5" id="blood-step-5" style="display: none;">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-eye me-2 text-danger"></i>Review Collection Details</h6>
                            <p class="text-muted mb-4">Please review all information before submitting</p>
                        </div>
                        
                        <div class="collection-summary" style="background: #f8f9fa; border-radius: 10px; padding: 1.5rem;">
                            <h6 class="mb-3">Collection Summary</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Bag Type:</strong> <span id="summary-blood-bag">-</span></p>
                                    <p><strong>Serial Number:</strong> <span id="summary-serial-number">-</span></p>
                                    <p><strong>Status:</strong> <span id="summary-successful">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Start Time:</strong> <span id="summary-start-time">-</span></p>
                                    <p><strong>End Time:</strong> <span id="summary-end-time">-</span></p>
                                    <p><strong>Duration:</strong> <span id="summary-duration">-</span></p>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12" id="summary-reaction-section" style="display:none;">
                                    <p class="mb-1"><strong>Reaction:</strong> <span id="summary-reaction">-</span></p>
                                </div>
                                <div class="col-md-12" id="summary-management-section" style="display:none;">
                                    <p class="mb-0"><strong>Management:</strong> <span id="summary-management">-</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer" style="background: #f8f9fa; border-radius: 0 0 15px 15px;">
                <div class="d-flex justify-content-between w-100">
                    <button type="button" class="btn btn-outline-secondary blood-cancel-btn" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-primary blood-prev-btn" style="display: none;">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        <button type="button" class="btn btn-primary blood-next-btn">
                            Next <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                        <button type="button" class="btn btn-success blood-submit-btn" style="display: none;">
                            <i class="fas fa-check me-2"></i>Submit Collection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Blood Collection Modal Styles (Admin) */
.bag-option.selected {
    border-color: #b22222 !important;
    background-color: rgba(178, 34, 34, 0.05);
}

.blood-status-card.selected {
    border-color: #b22222 !important;
    background-color: rgba(178, 34, 34, 0.05);
}

.blood-step.active .blood-step-number {
    background: #b22222 !important;
    color: #fff !important;
}

.blood-step.completed .blood-step-number {
    background: #28a745 !important;
    color: #fff !important;
}

.blood-step-content {
    display: none;
}

.blood-step-content.active {
    display: block;
}

/* Modal backdrop z-index fixes */
.modal-backdrop {
    z-index: 1055 !important;
}

#bloodCollectionModal {
    z-index: 1060 !important;
}

/* Ensure modal content is clickable */
.modal-content {
    pointer-events: auto !important;
}

.modal-content * {
    pointer-events: auto !important;
}
</style>


