/**
 * Duplicate Donor Registration Check Modal
 * A professional registration form interface for Red Cross staff and admins
 * to check for existing donors during registration
 */
class DuplicateDonorRegistrationCheck {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || 'assets/php_func/check_duplicate_donor.php';
        this.updateApiEndpoint = options.updateApiEndpoint || 'assets/php_func/update_donor_needs_review.php';
        this.modalId = options.modalId || 'donorRegistrationCheckModal';
        this.onContinueCallback = options.onContinue || null;
        this.onCancelCallback = options.onCancel || null;
        this.isInitialized = false;
        this.currentDonorId = null;
    }

    /**
     * Initialize the modal
     */
    init() {
        if (this.isInitialized) {
            return;
        }

        this.createModal();
        this.attachEventListeners();
        this.isInitialized = true;
    }

    /**
     * Create the modal HTML structure
     */
    createModal() {
        if (document.getElementById(this.modalId)) {
            return;
        }

        const modalHTML = `
            <div class="modal fade" id="${this.modalId}" tabindex="-1" aria-labelledby="${this.modalId}Label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content" style="border-radius: 10px; overflow: hidden;">
                        <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); border-bottom: none; padding: 1.5rem;">
                            <div class="d-flex align-items-center w-100">
                                <div class="me-3">
                                    <i class="fas fa-user-check fa-2x text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="modal-title fw-bold text-white mb-0" id="${this.modalId}Label">
                                        Existing Donor Record Found
                                    </h5>
                                    <small class="text-white-50">A donor with matching information already exists in our system.</small>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0">
                            <!-- Loading State -->
                            <div id="checkLoadingState" class="text-center p-5">
                                <div class="spinner-border text-danger mb-3" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Checking...</span>
                                </div>
                                <h6 class="text-muted">Checking donor records...</h6>
                                <p class="text-muted small mb-0">Please wait while we verify this information</p>
                            </div>

                            <!-- No Duplicate Found -->
                            <div id="noDuplicateState" style="display: none;" class="p-4">
                                <div class="text-center mb-4">
                                    <div class="mb-3">
                                        <i class="fas fa-check-circle fa-4x text-success"></i>
                                    </div>
                                    <h5 class="text-success mb-2">No Existing Record Found</h5>
                                    <p class="text-muted">This donor is not in our system. You may proceed with registration.</p>
                                </div>
                            </div>

                            <!-- Duplicate Found -->
                            <div id="duplicateFoundState" style="display: none;">
                                <!-- Organized Information Display -->
                                <div class="p-4">
                                    <!-- Main Donor Profile Section -->
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;">
                                            <h5 class="mb-0 fw-bold">
                                                <i class="fas fa-user me-2"></i>Donor Profile
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row align-items-center mb-4">
                                                <div class="col-12">
                                                    <h4 class="text-danger mb-2" id="duplicateDonorName" style="font-weight: 600;">
                                                        <i class="fas fa-user-circle me-2"></i>
                                                    </h4>
                                                    <p class="text-muted mb-0" id="duplicateDonorId" style="font-size: 0.9rem;">Donor ID: -</p>
                                                </div>
                                            </div>
                                            <hr class="my-3">
                                            <div class="row g-3" style="display: flex;">
                                                <div class="col-md-3" style="display: flex;">
                                                    <div class="p-3 bg-light rounded w-100" style="display: flex; flex-direction: column;">
                                                        <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Age</small>
                                                        <strong id="duplicateDonorAge" class="fs-5 d-block">-</strong>
                                                    </div>
                                                </div>
                                                <div class="col-md-3" style="display: flex;">
                                                    <div class="p-3 bg-light rounded w-100" style="display: flex; flex-direction: column;">
                                                        <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Sex</small>
                                                        <strong id="duplicateDonorSex" class="fs-5 d-block">-</strong>
                                                    </div>
                                                </div>
                                                <div class="col-md-3" style="display: flex;">
                                                    <div class="p-3 bg-light rounded w-100" style="display: flex; flex-direction: column;">
                                                        <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Mobile</small>
                                                        <strong id="duplicateMobile" class="fs-6 d-block">Not provided</strong>
                                                    </div>
                                                </div>
                                                <div class="col-md-3" style="display: flex;">
                                                    <div class="p-3 bg-light rounded w-100" style="display: flex; flex-direction: column;">
                                                        <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Email</small>
                                                        <strong id="duplicateEmail" class="fs-6 d-block text-break">Not provided</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status & Eligibility Section -->
                                    <div class="card border-0 shadow-sm mb-4" id="eligibilityStatusCard">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0 fw-bold">
                                                <i class="fas fa-clipboard-check me-2 text-danger"></i>Eligibility & Status Information
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <!-- Eligibility Status -->
                                            <div id="eligibilityInfoSection" class="mb-3">
                                                <div id="eligibilityAlert"></div>
                                            </div>

                                            <!-- Deferral Information -->
                                            <div id="deferralSection" style="display: none;">
                                                <div class="alert alert-warning border-start border-3 border-warning mb-0">
                                                    <div class="d-flex align-items-start">
                                                        <i class="fas fa-clock fa-lg me-3 mt-1"></i>
                                                        <div class="flex-grow-1">
                                                            <strong>Temporary Deferral Information</strong>
                                                            <p class="mb-1 mt-2" id="deferralPeriod"></p>
                                                            <small class="text-muted" id="deferralDaysRemaining"></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Donation Stage (for new donors) -->
                                            <div id="donationStageSection" style="display: none;">
                                                <div class="alert alert-info border-start border-3 border-info mb-0">
                                                    <div class="d-flex align-items-start">
                                                        <i class="fas fa-info-circle fa-lg me-3 mt-1"></i>
                                                        <div class="flex-grow-1">
                                                            <strong id="donationStageTitle"></strong>
                                                            <p class="mb-0 mt-2" id="donationStageDescription"></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Donation History Section -->
                                    <div class="card border-0 shadow-sm mb-4" id="donationHistoryCard" style="display: none;">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0 fw-bold">
                                                <i class="fas fa-history me-2 text-danger"></i>Donation History
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 rounded" style="background: linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%);">
                                                        <div class="me-3">
                                                            <i class="fas fa-tint fa-2x text-danger"></i>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase;">Total Donations</small>
                                                            <h3 class="text-danger mb-0 fw-bold" id="totalDonations">0</h3>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center p-3 rounded" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                                        <div class="me-3">
                                                            <i class="fas fa-file-medical fa-2x text-primary"></i>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase;">Total Records</small>
                                                            <h3 class="text-primary mb-0 fw-bold" id="totalRecords">0</h3>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row" id="lastDonationRow" style="display: none;">
                                                <div class="col-12">
                                                    <hr class="my-2">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="p-2 bg-light rounded">
                                                                <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Last Donation Date</small>
                                                                <strong id="lastDonationDate" class="fs-6">-</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="p-2 bg-light rounded">
                                                                <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Last Submission Date</small>
                                                                <strong id="duplicateRegistrationDate" class="fs-6">-</strong>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row" id="registrationDateRow" style="display: none;">
                                                <div class="col-12">
                                                    <hr class="my-2">
                                                    <div class="p-2 bg-light rounded">
                                                        <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">First Registration Date</small>
                                                        <strong id="duplicateRegistrationDateOnly" class="fs-6">-</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recommendation Section -->
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="alert mb-0" id="recommendationAlert" style="border-left: 4px solid;">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-lightbulb fa-lg me-3 mt-1"></i>
                                                    <div class="flex-grow-1">
                                                        <strong>Staff Advisory:</strong>
                                                        <p class="mb-0 mt-2" id="recommendationText"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top" id="modalFooter" style="display: none;">
                            <button type="button" class="btn btn-outline-secondary" id="cancelRegistrationBtn">
                                <i class="fas fa-times me-2"></i>Return
                            </button>
                            <button type="button" class="btn btn-danger" id="updateDonorInfoBtn" style="display: none;">
                                <i class="fas fa-edit me-2"></i>Update Donor Information
                                <span class="spinner-border spinner-border-sm ms-2 d-none" id="updateSpinner"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.addStyles();
    }

    /**
     * Add custom styles
     */
    addStyles() {
        if (document.getElementById('donorRegistrationCheckStyles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'donorRegistrationCheckStyles';
        style.textContent = `
            #${this.modalId} .modal-content {
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            }
            #${this.modalId} .badge {
                font-weight: 600;
                letter-spacing: 0.5px;
            }
            #${this.modalId} .card {
                transition: transform 0.2s;
            }
            #${this.modalId} .card:hover {
                transform: translateY(-2px);
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        // Update Donor Information button
        const updateBtn = document.getElementById('updateDonorInfoBtn');
        if (updateBtn) {
            updateBtn.addEventListener('click', () => {
                this.updateDonorInformation();
            });
        }

        // Cancel/Return button
        const cancelBtn = document.getElementById('cancelRegistrationBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                if (this.onCancelCallback) {
                    this.onCancelCallback();
                }
                this.hide();
            });
        }
    }

    /**
     * Show modal and check for duplicates
     */
    async checkDonor(donorData) {
        if (!this.isInitialized) {
            this.init();
        }

        const modal = document.getElementById(this.modalId);
        if (!modal) return;

        // Show loading state
        this.showLoadingState();

        // Show modal
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();

        try {
            // Call API
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(donorData)
            });

            const result = await response.json();

            if (result.duplicate_found && result.data) {
                this.showDuplicateFound(result.data);
            } else {
                this.showNoDuplicate();
            }
        } catch (error) {
            console.error('Error checking donor:', error);
            this.showError(error.message);
        }
    }

    /**
     * Show loading state
     */
    showLoadingState() {
        document.getElementById('checkLoadingState').style.display = 'block';
        document.getElementById('noDuplicateState').style.display = 'none';
        document.getElementById('duplicateFoundState').style.display = 'none';
        document.getElementById('modalFooter').style.display = 'none';
    }

    /**
     * Show no duplicate found
     */
    showNoDuplicate() {
        document.getElementById('checkLoadingState').style.display = 'none';
        document.getElementById('noDuplicateState').style.display = 'block';
        document.getElementById('duplicateFoundState').style.display = 'none';
        document.getElementById('modalFooter').style.display = 'flex';
        
        // Only show return button - no proceed button
        document.getElementById('updateDonorInfoBtn').style.display = 'none';
        this.currentDonorId = null;
    }

    /**
     * Show duplicate found
     */
    showDuplicateFound(data) {
        document.getElementById('checkLoadingState').style.display = 'none';
        document.getElementById('noDuplicateState').style.display = 'none';
        document.getElementById('duplicateFoundState').style.display = 'block';
        document.getElementById('modalFooter').style.display = 'flex';

        // Store donor ID for update functionality
        this.currentDonorId = data.donor_id;

        // Populate donor information
        document.getElementById('duplicateDonorName').innerHTML = `<i class="fas fa-user-circle me-2"></i>${data.full_name}`;
        document.getElementById('duplicateDonorAge').textContent = `${data.age} years`;
        document.getElementById('duplicateDonorSex').textContent = data.sex;
        // Display PRC Donor Number if available, otherwise fallback to donor_id
        const donorIdentifier = data.prc_donor_number || `#${data.donor_id}`;
        document.getElementById('duplicateDonorId').textContent = `Donor ID: ${donorIdentifier}`;
        document.getElementById('duplicateMobile').textContent = data.mobile || 'Not provided';
        document.getElementById('duplicateEmail').textContent = data.email || 'Not provided';
        document.getElementById('duplicateRegistrationDate').textContent = data.time_description || 'Unknown';

        // Donation history
        if (data.has_eligibility_history && data.total_donations !== undefined) {
            document.getElementById('donationHistoryCard').style.display = 'block';
            document.getElementById('totalDonations').textContent = data.total_donations;
            document.getElementById('totalRecords').textContent = data.total_eligibility_records || 0;
        } else {
            document.getElementById('donationHistoryCard').style.display = 'none';
        }

        // Eligibility information
        this.populateEligibilityInfo(data);

        // Deferral information
        if (data.temporary_deferred) {
            document.getElementById('deferralSection').style.display = 'block';
            document.getElementById('deferralPeriod').textContent = `Deferral Period: ${data.temporary_deferred_text || data.temporary_deferred}`;
            if (data.temporary_deferred_days_remaining !== null && data.temporary_deferred_days_remaining > 0) {
                document.getElementById('deferralDaysRemaining').textContent = `${data.temporary_deferred_days_remaining} day(s) remaining`;
            } else if (data.temporary_deferred_days_remaining === 0) {
                document.getElementById('deferralDaysRemaining').innerHTML = '<span class="text-success">Deferral period has ended - re-evaluation needed</span>';
            }
        } else {
            document.getElementById('deferralSection').style.display = 'none';
        }

        // Donation stage (for new donors)
        if (!data.has_eligibility_history && data.donation_stage) {
            document.getElementById('donationStageSection').style.display = 'block';
            document.getElementById('donationStageTitle').textContent = `Donor Stage: ${data.donation_stage}`;
            document.getElementById('donationStageDescription').textContent = data.reason || `This donor was in the ${data.donation_stage} stage.`;
        } else {
            document.getElementById('donationStageSection').style.display = 'none';
        }

        // Last donation date and registration date
        if (data.latest_donation_date) {
            document.getElementById('lastDonationRow').style.display = 'block';
            document.getElementById('registrationDateRow').style.display = 'none';
            const lastDonation = new Date(data.latest_donation_date);
            document.getElementById('lastDonationDate').textContent = lastDonation.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('duplicateRegistrationDate').textContent = data.time_description || 'Unknown';
        } else {
            document.getElementById('lastDonationRow').style.display = 'none';
            // Show registration date separately if no donation history
            if (data.has_eligibility_history) {
                document.getElementById('registrationDateRow').style.display = 'block';
                document.getElementById('duplicateRegistrationDateOnly').textContent = data.time_description || 'Unknown';
            } else {
                document.getElementById('registrationDateRow').style.display = 'none';
            }
        }

        // Recommendation
        const recommendationAlert = document.getElementById('recommendationAlert');
        const recommendationText = document.getElementById('recommendationText');
        recommendationText.textContent = data.suggestion;

        if (data.can_donate_today) {
            recommendationAlert.className = 'alert alert-success';
            recommendationAlert.style.borderLeftColor = '#198754';
        } else {
            recommendationAlert.className = 'alert alert-warning';
            recommendationAlert.style.borderLeftColor = '#ffc107';
        }

        // Show "Update Donor Information" button conditionally
        // Only show if:
        // 1. Donor has eligibility history (not a new donor)
        // 2. Can donate today (no waiting period or deferral)
        // 3. No days remaining for deferral or waiting period
        const updateBtn = document.getElementById('updateDonorInfoBtn');
        if (updateBtn && this.currentDonorId) {
            // Check if it's a new donor (no eligibility history)
            const isNewDonor = !data.has_eligibility_history;
            
            // Check if deferral period has days remaining
            const hasDeferralDaysRemaining = data.temporary_deferred_days_remaining !== null && 
                                            data.temporary_deferred_days_remaining !== undefined && 
                                            data.temporary_deferred_days_remaining > 0;
            
            // Check if suggestion indicates waiting period (e.g., "Wait X more day(s)" or "Must wait X more day(s)")
            const suggestion = data.suggestion || '';
            const hasWaitingPeriod = /(?:Wait|Must wait)\s+\d+\s+more\s+day/i.test(suggestion);
            
            // Show button only if:
            // - Not a new donor (has eligibility history)
            // - Can donate today (no waiting period)
            // - No deferral days remaining
            // - No waiting period mentioned in suggestion
            if (!isNewDonor && data.can_donate_today && !hasDeferralDaysRemaining && !hasWaitingPeriod) {
                updateBtn.style.display = 'inline-block';
                updateBtn.innerHTML = '<i class="fas fa-edit me-2"></i>Update Donor Information';
                updateBtn.className = 'btn btn-danger';
                updateBtn.title = 'Update existing donor information and mark for review';
            } else {
                updateBtn.style.display = 'none';
            }
        }
    }

    /**
     * Populate eligibility information
     */
    populateEligibilityInfo(data) {
        const eligibilityCard = document.getElementById('eligibilityStatusCard');
        const eligibilityAlert = document.getElementById('eligibilityAlert');

        // Only show eligibility card for new donors (no eligibility history)
        if (!data.has_eligibility_history) {
            // Show the entire card for new donors
            if (eligibilityCard) {
                eligibilityCard.style.display = 'block';
            }
            if (eligibilityAlert) {
                eligibilityAlert.innerHTML = `
                    <div class="alert alert-info border-start border-3 border-info">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-info-circle fa-lg me-3 mt-1"></i>
                            <div class="flex-grow-1">
                                <strong>New Donor</strong>
                                <p class="mb-0">This donor is registered but has no donation history yet.</p>
                            </div>
                        </div>
                    </div>
                `;
            }
        } else {
            // Hide entire eligibility card for all donors with eligibility history
            if (eligibilityCard) {
                eligibilityCard.style.display = 'none';
            }
        }
    }

    /**
     * Show error
     */
    showError(message) {
        document.getElementById('checkLoadingState').style.display = 'none';
        document.getElementById('noDuplicateState').style.display = 'none';
        document.getElementById('duplicateFoundState').style.display = 'block';
        document.getElementById('duplicateFoundState').innerHTML = `
            <div class="alert alert-danger m-4">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error:</strong> ${message}
            </div>
        `;
    }

    /**
     * Get status badge class
     */
    getStatusBadgeClass(alertType) {
        switch (alertType?.toLowerCase()) {
            case 'success':
                return 'bg-success text-white';
            case 'warning':
                return 'bg-warning text-dark';
            case 'danger':
                return 'bg-danger text-white';
            case 'info':
                return 'bg-info text-white';
            default:
                return 'bg-secondary text-white';
        }
    }

    /**
     * Update donor information - sets needs_review to true in medical_history
     */
    async updateDonorInformation() {
        if (!this.currentDonorId) {
            alert('Error: Donor ID not available');
            return;
        }

        const updateBtn = document.getElementById('updateDonorInfoBtn');
        const spinner = document.getElementById('updateSpinner');
        
        if (!updateBtn || !spinner) return;

        // Show loading state
        updateBtn.disabled = true;
        spinner.classList.remove('d-none');

        try {
            const response = await fetch(this.updateApiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    donor_id: this.currentDonorId
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                // Show success message
                const recommendationAlert = document.getElementById('recommendationAlert');
                if (recommendationAlert) {
                    recommendationAlert.className = 'alert alert-success';
                    recommendationAlert.style.borderLeftColor = '#198754';
                    document.getElementById('recommendationText').innerHTML = `
                        <strong class="text-success">
                            <i class="fas fa-check-circle me-2"></i>Donor information updated successfully!
                        </strong><br>
                        The medical history record has been marked for review. Staff can now proceed with updating the donor's information.
                    `;
                }

                // Hide update button after successful update
                updateBtn.style.display = 'none';

                // Show success notification
                setTimeout(() => {
                    if (this.onContinueCallback) {
                        this.onContinueCallback();
                    }
                    this.hide();
                }, 2000);
            } else {
                throw new Error(result.message || 'Failed to update donor information');
            }
        } catch (error) {
            console.error('Error updating donor:', error);
            alert('Error updating donor information: ' + error.message);
        } finally {
            updateBtn.disabled = false;
            spinner.classList.add('d-none');
        }
    }

    /**
     * Hide modal
     */
    hide() {
        const modal = document.getElementById(this.modalId);
        if (modal) {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
        // Reset current donor ID
        this.currentDonorId = null;
    }
}

// Export for global use
if (typeof window !== 'undefined') {
    window.DuplicateDonorRegistrationCheck = DuplicateDonorRegistrationCheck;
}

