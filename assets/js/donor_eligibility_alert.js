function showDonorEligibilityAlert(donorId) {
    // Get or create modal
    let modalElement = document.getElementById('donorEligibilityModal');
    if (!modalElement) {
        // Create modal if it doesn't exist
        modalElement = document.createElement('div');
        modalElement.id = 'donorEligibilityModal';
        modalElement.className = 'modal fade';
        modalElement.setAttribute('tabindex', '-1');
        modalElement.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div id="eligibilityModalHeader" class="modal-header">
                        <h5 class="modal-title"></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div id="eligibilityModalBody" class="modal-body">
                        <div class="d-flex justify-content-center align-items-center" style="min-height: 300px;">
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="text-muted fs-5">Loading eligibility information...</p>
                                <p class="text-muted small">Please wait while we fetch the latest data</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalElement);
        
        // Add styles
        const style = document.createElement('style');
        style.textContent = `
            #donorEligibilityModal .modal-dialog {
                max-width: 450px;
                margin: 1.75rem auto;
            }
            #donorEligibilityModal .modal-content {
                border: none;
                border-radius: 0;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                overflow: hidden;
            }
            #donorEligibilityModal .modal-header {
                display: none;
            }
            #donorEligibilityModal .modal-body {
                padding: 2rem;
            }
            #donorEligibilityModal .modal-footer {
                display: none;
            }
            #donorEligibilityModal .btn-close {
                opacity: 0.75;
            }
            #donorEligibilityModal .btn-close:hover {
                opacity: 1;
            }
            @media (max-width: 576px) {
                #donorEligibilityModal .modal-dialog {
                    margin: 0;
                    height: 100vh;
                    max-width: none;
                }
                #donorEligibilityModal .modal-content {
                    height: 100%;
                    border-radius: 0;
                }
                #donorEligibilityModal .modal-body {
                    padding: 1.5rem;
                }
                #donorEligibilityModal [style*="display: flex"] {
                    flex-direction: column;
                }
                #donorEligibilityModal [style*="flex: 1"] {
                    margin-bottom: 1rem;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Initialize Bootstrap modal
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: true,
        keyboard: true
    });
    
    // Ensure backdrop is removed when modal is hidden
    modalElement.addEventListener('hidden.bs.modal', function () {
        const backdrops = document.getElementsByClassName('modal-backdrop');
        for (let backdrop of backdrops) {
            backdrop.remove();
        }
    });

    // Hide the "Mark for Medical Review" button when showing eligibility alert
    const markReviewButton = document.getElementById('markReviewFromMain');
    if (markReviewButton) {
        markReviewButton.style.display = 'none !important';
        markReviewButton.style.visibility = 'hidden';
        markReviewButton.style.opacity = '0';
    }

    // Show modal with loading state
    modal.show();

    // Fetch eligibility data with delay to prevent flickering
    setTimeout(() => {
        fetch('../../assets/php_func/get_donor_eligibility_status.php?donor_id=' + donorId)
            .then(response => response.json())
            .then(data => {
            // Double-check we're still showing the correct donor
            if (window.currentDonorId !== donorId) {
                return; // Don't update if user clicked on a different donor
            }
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to fetch eligibility status');
            }

            const eligibility = data.data;
            const today = new Date();
            const startDate = new Date(eligibility.start_date);
            let endDate, message;
            let alertStyle;

            // Set default alert style
            alertStyle = {
                bgColor: '#ffebee',
                borderColor: '#c62828',
                iconColor: '#c62828',
                icon: 'fa-clock',
                title: 'Waiting Period Required'
            };

            // Handle different statuses - check status first, then temporary_deferred
            if (eligibility.status === 'approved') {
                // For approved status, check 3 months waiting period
                endDate = new Date(startDate);
                endDate.setMonth(endDate.getMonth() + 3);
                message = 'This donor has donated recently and must complete the required waiting period.';
            } 
            else if (eligibility.status === 'temporary_deferred' || eligibility.status === 'temporarily_deferred') {
                // Temporary deferral - use temporary_deferred duration
                const durationParts = eligibility.temporary_deferred ? eligibility.temporary_deferred.split(' ') : [];
                endDate = new Date(startDate);
                
                if (durationParts.includes('month')) {
                    const monthIndex = durationParts.indexOf('month');
                    const months = parseInt(durationParts[monthIndex - 1]);
                    endDate.setMonth(endDate.getMonth() + months);
                }
                if (durationParts.includes('days')) {
                    const daysIndex = durationParts.indexOf('days');
                    const days = parseInt(durationParts[daysIndex - 1]);
                    endDate.setDate(endDate.getDate() + days);
                }

                alertStyle = {
                    bgColor: '#fff3e0',
                    borderColor: '#ef6c00',
                    iconColor: '#ef6c00',
                    icon: 'fa-ban',
                    title: 'Temporarily Deferred'
                };
                message = `This donor is temporarily deferred for ${eligibility.temporary_deferred}.`;
            } 
            else if (eligibility.status === 'refused') {
                // Refused status - different design from ineligible
                endDate = null;
                alertStyle = {
                    bgColor: '#f3e5f5',
                    borderColor: '#9c27b0',
                    iconColor: '#9c27b0',
                    icon: 'fa-times-circle',
                    title: 'Donation Refused'
                };
                message = 'This donor has refused to donate blood.';
            }
            else if (eligibility.status === 'permanently_deferred' || eligibility.status === 'deferred' || eligibility.status === 'ineligible' || 
                     (eligibility.temporary_deferred && eligibility.temporary_deferred.includes('Ineligible/Indefinite'))) {
                // Permanent deferral
                endDate = null;
                alertStyle = {
                    bgColor: '#ffebee',
                    borderColor: '#d32f2f',
                    iconColor: '#d32f2f',
                    icon: 'fa-ban',
                    title: 'Permanently Ineligible'
                };
                message = 'This donor is permanently ineligible for donation.';
            }

            // Calculate remaining time if applicable
            const remainingDays = endDate ? Math.ceil((endDate - today) / (1000 * 60 * 60 * 24)) : null;

            // Add disapproval reason if exists
            if (eligibility.disapproval_reason) {
                const match = eligibility.disapproval_reason.match(/(Medical|Physician|Screening):\s*(.+)/);
                if (match) {
                    const [_, stage, stageReason] = match;
                    message = `${message}<br><small class="mt-2 d-block"><strong>Deferred at ${stage} stage:</strong> ${stageReason}</small>`;
                } else {
                    message = `${message}<br><small class="mt-2 d-block"><strong>Reason:</strong> ${eligibility.disapproval_reason}</small>`;
                }
            }

            // Update modal content
            const body = modalElement.querySelector('#eligibilityModalBody');
            body.innerHTML = `
                <div class="eligibility-alert" style="position: relative;">
                    <!-- Close button -->
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                            style="position: absolute; top: 1rem; right: 1rem; z-index: 10;"></button>

                    <!-- Main alert section -->
                    <div style="background-color: ${alertStyle.bgColor}; padding: 2rem 1.5rem; margin: -2rem -2rem 1.5rem -2rem;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <i class="fas ${alertStyle.icon}" style="font-size: 1.75rem; color: ${alertStyle.borderColor};"></i>
                            <h5 style="color: ${alertStyle.borderColor}; font-weight: 600; margin: 0;">${alertStyle.title}</h5>
                        </div>
                        <p style="color: #555; margin: 0;">${message}</p>
                    </div>

                    ${endDate ? `
                        <!-- Dates section -->
                        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="flex: 1;">
                                <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.25rem;">Last Donation/Deferral Date</div>
                                <div style="font-size: 1rem;">${startDate.toLocaleDateString()}</div>
                            </div>
                            <div style="flex: 1;">
                                <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.25rem;">Next Eligible Date</div>
                                <div style="font-size: 1rem;">${endDate.toLocaleDateString()}</div>
                            </div>
                        </div>

                        <!-- Days remaining section -->
                        <div style="background: ${alertStyle.borderColor}; color: white; padding: 1.5rem; text-align: center;">
                            <div style="font-size: 0.9rem; margin-bottom: 0.5rem;">Days Until Next Eligible Donation</div>
                            <div style="font-size: 2rem; font-weight: 700;">${remainingDays} days</div>
                        </div>
                    ` : `
                        <!-- Permanent deferral/refused - no dates or countdown -->
                        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="flex: 1;">
                                <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.25rem;">${eligibility.status === 'refused' ? 'Refusal Date' : 'Deferral Date'}</div>
                                <div style="font-size: 1rem;">${startDate.toLocaleDateString()}</div>
                            </div>
                            <div style="flex: 1;">
                                <div style="color: #666; font-size: 0.875rem; margin-bottom: 0.25rem;">Status</div>
                                <div style="font-size: 1rem; color: ${eligibility.status === 'refused' ? '#9c27b0' : '#d32f2f'}; font-weight: 600;">${eligibility.status === 'refused' ? 'Donation Refused' : 'Permanently Ineligible'}</div>
                            </div>
                        </div>
                    `}
                </div>
            `;
        })
        .catch(error => {
            console.error('Error:', error);
            // Only update if we're still showing the correct donor
            if (window.currentDonorId === donorId) {
                const body = modalElement.querySelector('#eligibilityModalBody');
                body.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        Failed to load eligibility status: ${error.message}
                    </div>
                `;
            }
        });
    }, 800); // 800ms delay to prevent flickering and ensure smooth loading
}