<?php
/**
 * Blood Collection Modal (Admin Only)
 * Four-step flow: Bag Selection → Collection Details → Timing → Review
 * Status step removed; admin submissions are always treated as successful.
 */
?>

<!-- Admin Blood Collection Modal -->
<div class="modal fade" id="bloodCollectionModal" tabindex="-1" aria-labelledby="bloodCollectionModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 15px 15px 0 0;">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-tint fa-2x text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="bloodCollectionModalLabel">Blood Collection (Admin)</h5>
                        <small class="text-white-50">Admin submission (auto-success on submit)</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white blood-close-btn" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Progress Indicator (4 steps) -->
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
                        <div class="blood-step-label">Review</div>
                    </div>
                </div>
                <div class="blood-progress-line" style="height: 4px; background: #f1f3f5; border-radius: 2px; margin: 8px 0 0; position: relative; overflow: hidden;">
                    <div class="blood-progress-fill" style="height: 100%; width: 0%; background: #b22222; transition: width .3s ease;"></div>
                </div>
            </div>

            <div class="modal-body" style="padding: 1.5rem; background-color: #ffffff; max-height: 70vh; overflow-y: auto;">
                <form id="bloodCollectionForm">
                    <input type="hidden" name="donor_id" value="">
                    <input type="hidden" name="physical_exam_id" value="">
                    <input type="hidden" name="amount_taken" value="1">

                    <!-- Step 1: Bag Selection -->
                    <div class="blood-step-content active" id="blood-step-1" data-step="1">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-tint me-2 text-danger"></i>Blood Bag Selection</h6>
                            <p class="text-muted mb-4">Select the appropriate blood bag type for collection</p>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="bag-option" style="border: 2px solid #e9ecef; border-radius: 10px; padding: 1rem; cursor: pointer; transition: all 0.3s ease;">
                                    <label style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 10px;">
                                        <input type="radio" name="blood_bag_type" value="Single" style="margin: 0;" required>
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
                                        <input type="radio" name="blood_bag_type" value="Multiple" style="margin: 0;" required>
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
                                        <input type="radio" name="blood_bag_type" value="Top & Bottom" style="margin: 0;" required>
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
                                        <input type="radio" name="blood_bag_type" value="Apheresis" style="margin: 0;" required>
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
                    <div class="blood-step-content" id="blood-step-2" data-step="2" style="display: none;">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-clipboard-list me-2 text-danger"></i>Collection Details</h6>
                            <p class="text-muted mb-4">Enter collection information</p>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="blood-unit-serial" class="form-label">Unit Serial Number *</label>
                                <input type="text" class="form-control" id="blood-unit-serial" name="unit_serial_number" readonly>
                                <div class="form-text">Auto-generated</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Collection Date</label>
                                <input type="text" class="form-control" id="blood-collection-date" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="blood-type-display" class="form-label">Blood Type</label>
                                <input type="text" class="form-control" id="blood-type-display" name="blood_type" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="donor-weight" class="form-label">Donor Weight (kg)</label>
                                <input type="number" class="form-control" id="donor-weight" name="donor_weight" min="50" max="200">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Timing (editable text, not read-only) -->
                    <div class="blood-step-content" id="blood-step-3" data-step="3" style="display: none;">
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
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Review -->
                    <div class="blood-step-content" id="blood-step-4" data-step="4" style="display: none;">
                        <div class="blood-step-title mb-4">
                            <h6><i class="fas fa-eye me-2 text-danger"></i>Review</h6>
                            <p class="text-muted mb-4">Confirm details before submitting</p>
                        </div>
                        <div class="collection-summary" style="background: #f8f9fa; border-radius: 10px; padding: 1.5rem;">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Bag Type:</strong> <span id="summary-blood-bag">-</span></p>
                                    <p><strong>Serial Number:</strong> <span id="summary-serial-number">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Start Time:</strong> <span id="summary-start-time">-</span></p>
                                    <p><strong>End Time:</strong> <span id="summary-end-time">-</span></p>
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
.bag-option.selected { border-color: #b22222 !important; background-color: rgba(178, 34, 34, 0.05); }
.blood-step.active .blood-step-number { background: #b22222 !important; color: #fff !important; }
.blood-step.completed .blood-step-number { background: #28a745 !important; color: #fff !important; }
.blood-step-content { display: none; }
.blood-step-content.active { display: block; }
.modal-backdrop { z-index: 1055 !important; }
#bloodCollectionModal { z-index: 1060 !important; }

/* Force time inputs to be always editable */
#blood-start-time,
#blood-end-time {
    pointer-events: auto !important;
    cursor: text !important;
    background-color: #fff !important;
    opacity: 1 !important;
    color: #000 !important;
}

#blood-start-time:disabled,
#blood-end-time:disabled {
    background-color: #fff !important;
    opacity: 1 !important;
}
</style>



