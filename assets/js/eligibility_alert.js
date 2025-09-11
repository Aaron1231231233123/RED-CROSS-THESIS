// Function to show eligibility alert modal
function showEligibilityAlert(donorId) {
    console.log('Showing eligibility alert for donor:', donorId);
    
    // Get modal elements
    const modalElement = document.getElementById('eligibilityAlertModal');
    const alertContent = document.getElementById('eligibilityAlertContent');
    const alertTitle = document.getElementById('eligibilityAlertTitle');
    
    if (!modalElement || !alertContent || !alertTitle) {
        console.error('Modal elements not found:', { modalElement, alertContent, alertTitle });
        return;
    }
    
    // Initialize Bootstrap modal
    const alertModal = new bootstrap.Modal(modalElement);
    
    // Show loading state
    alertContent.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    alertTitle.innerHTML = '<h5 class="modal-title">Loading...</h5>';
    alertModal.show();
    
    // Fetch eligibility data
    fetch('../../assets/php_func/fetch_eligibility_alert_info.php?donor_id=' + donorId)
        .then(response => response.json())
        .then(data => {
            console.log('Received eligibility data:', data);
            
            if (!data.success || !data.data) {
                throw new Error(data.message || 'Failed to fetch eligibility data');
            }
            
            const { status, temporary_deferred, start_date } = data.data;
            let titleText = '';
            let contentText = '';
            let titleColor = '';
            
            const today = new Date();
            const donationDate = new Date(start_date);
            
            if (status === 'deferred' || status === 'ineligible') {
                titleText = 'Donor is Currently Deferred';
                titleColor = '#dc3545'; // red
                
                if (temporary_deferred) {
                    // Parse the temporary_deferred duration (assuming format like "1 month 2 days")
                    const durationParts = temporary_deferred.split(' ');
                    let deferralEndDate = new Date(donationDate);
                    
                    // Add months if specified
                    if (durationParts.includes('month')) {
                        const monthIndex = durationParts.indexOf('month');
                        const months = parseInt(durationParts[monthIndex - 1]);
                        deferralEndDate.setMonth(deferralEndDate.getMonth() + months);
                    }
                    
                    // Add days if specified
                    if (durationParts.includes('days')) {
                        const daysIndex = durationParts.indexOf('days');
                        const days = parseInt(durationParts[daysIndex - 1]);
                        deferralEndDate.setDate(deferralEndDate.getDate() + days);
                    }
                    
                    // Calculate remaining time
                    const remainingTime = deferralEndDate - today;
                    if (remainingTime > 0) {
                        const remainingDays = Math.ceil(remainingTime / (1000 * 60 * 60 * 24));
                        contentText = `This donor is temporarily deferred. They can donate again after ${deferralEndDate.toLocaleDateString()}. (${remainingDays} days remaining)`;
                    } else {
                        contentText = 'The deferral period has ended. The donor can now be reassessed for donation.';
                        titleText = 'Deferral Period Ended';
                        titleColor = '#28a745'; // green
                    }
                } else {
                    contentText = 'This donor is permanently deferred from donation.';
                }
            } else if (status === 'eligible') {
                // Check if 3 months have passed since last donation
                const threeMonthsLater = new Date(donationDate);
                threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);
                
                if (today >= threeMonthsLater) {
                    titleText = 'Donor Eligible for Donation';
                    titleColor = '#28a745'; // green
                    contentText = 'This donor has completed the 3-month interval since their last donation and is now eligible to donate again.';
                } else {
                    titleText = 'Donor Must Wait';
                    titleColor = '#ffc107'; // yellow
                    const remainingDays = Math.ceil((threeMonthsLater - today) / (1000 * 60 * 60 * 24));
                    contentText = `This donor must wait ${remainingDays} more days before their next donation. (Next eligible date: ${threeMonthsLater.toLocaleDateString()})`;
                }
            }
            
            // Update modal content
            alertTitle.style.backgroundColor = titleColor;
            alertTitle.innerHTML = `<h5 class="modal-title" style="color: white;">${titleText}</h5>`;
            alertContent.innerHTML = `<p class="mb-0">${contentText}</p>`;
            
        })
        .catch(error => {
            console.error('Error:', error);
            alertTitle.style.backgroundColor = '#dc3545';
            alertTitle.innerHTML = '<h5 class="modal-title" style="color: white;">Error</h5>';
            alertContent.innerHTML = `<p class="mb-0">Failed to load eligibility status: ${error.message}</p>`;
        });
}