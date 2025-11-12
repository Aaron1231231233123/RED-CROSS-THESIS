/**
 * Admin Hospital Request Priority Handler
 * 
 * Applies visual prioritization highlighting to hospital blood request rows
 * based on is_asap flag and when_needed timestamp proximity.
 * 
 * Only applies to requests with status "Pending"
 */

(function() {
    'use strict';

    /**
     * Apply priority styling to request table rows
     */
    function applyPriorityStyling() {
        const tableRows = document.querySelectorAll('#requestTable tbody tr');
        
        tableRows.forEach((row) => {
            // Get data attributes from the row
            const status = (row.dataset.status || '').toLowerCase();
            
            // Only apply to Pending status - reset all styling for non-pending
            if (status !== 'pending') {
                // Explicitly remove all priority styling from non-pending rows
                row.classList.remove(
                    'priority-asap-urgent',
                    'priority-urgent',
                    'priority-normal',
                    'priority-pulse'
                );
                row.style.backgroundColor = '';
                row.style.borderLeft = '';
                row.style.color = '';
                row.removeAttribute('title');
                row.style.cursor = '';
                return;
            }
            
            // For pending rows, apply priority styling
            const isAsap = row.dataset.isAsap === 'true';
            const whenNeeded = row.dataset.whenNeeded || null;
            const priorityClass = row.dataset.priorityClass || '';
            const isUrgent = row.dataset.isUrgent === 'true';
            const isCritical = row.dataset.isCritical === 'true';
            
            // Remove any existing priority classes
            row.classList.remove(
                'priority-asap-urgent',
                'priority-urgent',
                'priority-normal'
            );
            
            // Apply the priority class
            if (priorityClass) {
                row.classList.add(priorityClass);
            }
            
            // Apply background color based on urgency - whole row highlighting
            if (isUrgent) {
                // Red highlight for urgent requests (consistent color)
                row.style.backgroundColor = 'rgba(220, 53, 69, 0.12)';
                row.style.borderLeft = '4px solid #dc3545';
                
                // Add subtle animation for critical items
                if (isCritical) {
                    row.classList.add('priority-pulse');
                }
            } else {
                // Blue highlight for normal priority
                row.style.backgroundColor = 'rgba(13, 110, 253, 0.08)';
                row.style.borderLeft = '4px solid #0d6efd';
            }
            
            // Ensure text is readable
            row.style.color = '#212529';
            
            // Add hover effect
            row.addEventListener('mouseenter', function() {
                if (isUrgent) {
                    this.style.backgroundColor = 'rgba(220, 53, 69, 0.18)';
                } else {
                    this.style.backgroundColor = 'rgba(13, 110, 253, 0.12)';
                }
            });
            
            row.addEventListener('mouseleave', function() {
                if (isUrgent) {
                    this.style.backgroundColor = 'rgba(220, 53, 69, 0.12)';
                } else {
                    this.style.backgroundColor = 'rgba(13, 110, 253, 0.08)';
                }
            });
        });
    }

    /**
     * Add priority indicator badge to urgent rows
     * Adds a tooltip/title attribute to the row for additional info
     */
    function addPriorityBadges() {
        const tableRows = document.querySelectorAll('#requestTable tbody tr');
        
        tableRows.forEach((row) => {
            const status = (row.dataset.status || '').toLowerCase();
            const isUrgent = row.dataset.isUrgent === 'true';
            const isCritical = row.dataset.isCritical === 'true';
            const timeRemaining = row.dataset.timeRemaining || '';
            
            // Only add tooltip for Pending and urgent requests
            if (status === 'pending' && isUrgent && timeRemaining) {
                row.setAttribute('title', `Priority: ${isCritical ? 'CRITICAL' : 'URGENT'} - ${timeRemaining}`);
                row.style.cursor = 'help';
            }
        });
    }

    /**
     * Update priority styling periodically for time-sensitive requests
     */
    function updatePriorityStyling() {
        const tableRows = document.querySelectorAll('#requestTable tbody tr');
        const now = new Date();
        
        tableRows.forEach((row) => {
            const status = (row.dataset.status || '').toLowerCase();
            const whenNeeded = row.dataset.whenNeeded;
            
            if (status !== 'pending' || !whenNeeded) {
                return;
            }
            
            try {
                const deadline = new Date(whenNeeded);
                const hoursRemaining = (deadline.getTime() - now.getTime()) / (1000 * 60 * 60);
                
                // Recalculate if approaching deadline
                if (hoursRemaining <= 72) { // Within 3 days
                    // Force re-styling by updating data attributes
                    // This would require server-side recalculation, so we'll just refresh styling
                    applyPriorityStyling();
                }
            } catch (e) {
                console.error('Error updating priority:', e);
            }
        });
    }

    /**
     * Get dismissed alerts from storage
     */
    function getDismissedAlerts() {
        try {
            const stored = sessionStorage.getItem('dismissedDeadlineAlerts');
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Save dismissed alerts to storage
     */
    function saveDismissedAlerts(dismissed) {
        try {
            sessionStorage.setItem('dismissedDeadlineAlerts', JSON.stringify(dismissed));
        } catch (e) {
            console.error('Error saving dismissed alerts:', e);
        }
    }

    /**
     * Update notification badge
     */
    function updateNotificationBadge() {
        const dismissed = getDismissedAlerts();
        const badge = document.getElementById('deadlineNotificationBadge');
        const count = document.getElementById('deadlineNotificationCount');
        const alertContainer = document.getElementById('deadlineAlertContainer');
        
        if (badge && count) {
            if (dismissed.length > 0) {
                badge.style.display = 'flex';
                count.textContent = dismissed.length;
                // Adjust alert container position to make room for badge
                if (alertContainer) {
                    alertContainer.style.top = '160px';
                }
            } else {
                badge.style.display = 'none';
                // Reset alert container position
                if (alertContainer) {
                    alertContainer.style.top = '100px';
                }
            }
        }
    }

    /**
     * Show dismissed alerts when notification badge is clicked
     */
    function showDismissedAlerts() {
        const dismissed = getDismissedAlerts();
        if (dismissed.length === 0) {
            // Hide badge if no dismissed alerts
            updateNotificationBadge();
            return;
        }
        
        const alertContainer = document.getElementById('deadlineAlertContainer');
        if (!alertContainer) return;
        
        // Clear existing alerts (both active and dismissed)
        alertContainer.innerHTML = '';
        
        // Show all dismissed alerts
        dismissed.forEach((alert) => {
            const alertElement = createAlertElement(alert, true);
            alertContainer.appendChild(alertElement);
        });
    }

    /**
     * Create alert element
     */
    function createAlertElement(alert, isDismissed = false) {
        const alertElement = document.createElement('div');
        alertElement.className = 'deadline-alert';
        alertElement.dataset.requestId = alert.requestId;
        alertElement.innerHTML = `
            <div class="deadline-alert-header">
                <div class="deadline-alert-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Deadline Approaching
                </div>
                <button class="deadline-alert-close" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="deadline-alert-body">
                <strong>Request ID:</strong> ${alert.requestId}<br>
                <strong>Hospital:</strong> ${alert.hospitalName}<br>
                <strong>Time Remaining:</strong> ${alert.timeRemaining}
            </div>
        `;
        
        // Add close handler
        const closeBtn = alertElement.querySelector('.deadline-alert-close');
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!isDismissed) {
                // First time dismissing - add to dismissed list
                dismissAlert(alert.requestId);
            }
            // Remove from view
            alertElement.remove();
            updateNotificationBadge();
            
            // If no more alerts in container, hide it
            const alertContainer = document.getElementById('deadlineAlertContainer');
            if (alertContainer && alertContainer.children.length === 0) {
                alertContainer.innerHTML = '';
            }
        });
        
        // Add click handler to scroll to the row
        alertElement.addEventListener('click', function(e) {
            if (e.target === closeBtn) return;
            const row = document.querySelector(`tr[data-request-id="${alert.requestId}"]`);
            if (row) {
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Highlight the row temporarily
                row.style.boxShadow = '0 0 10px rgba(220, 53, 69, 0.5)';
                setTimeout(() => {
                    row.style.boxShadow = '';
                }, 2000);
            }
        });
        
        return alertElement;
    }

    /**
     * Dismiss an alert
     */
    function dismissAlert(requestId) {
        const dismissed = getDismissedAlerts();
        // Check if already dismissed
        if (!dismissed.find(a => a.requestId === requestId)) {
            // Find the alert data
            const tableRows = document.querySelectorAll('#requestTable tbody tr');
            tableRows.forEach((row) => {
                const rowRequestId = row.dataset.requestId || row.getAttribute('data-request-id') || '';
                if (rowRequestId === requestId) {
                    const status = (row.dataset.status || '').toLowerCase();
                    const isOneDayBefore = row.dataset.isOneDayBefore === 'true' || 
                                           row.getAttribute('data-is-one-day-before') === 'true';
                    if (status === 'pending' && isOneDayBefore) {
                        dismissed.push({
                            requestId: requestId,
                            hospitalName: row.dataset.hospitalName || row.getAttribute('data-hospital-name') || 'Hospital',
                            timeRemaining: row.dataset.timeRemaining || row.getAttribute('data-time-remaining') || ''
                        });
                    }
                }
            });
            saveDismissedAlerts(dismissed);
        }
    }

    /**
     * Show deadline alerts for requests 1 day before deadline
     */
    function showDeadlineAlerts() {
        const alertContainer = document.getElementById('deadlineAlertContainer');
        if (!alertContainer) {
            console.warn('Deadline alert container not found');
            return;
        }
        
        // Get dismissed alerts
        const dismissed = getDismissedAlerts();
        const dismissedIds = dismissed.map(a => a.requestId);
        
        // Clear existing alerts
        alertContainer.innerHTML = '';
        
        const tableRows = document.querySelectorAll('#requestTable tbody tr');
        const alerts = [];
        
        tableRows.forEach((row) => {
            const status = (row.dataset.status || '').toLowerCase();
            // Data attribute is data-is-one-day-before, accessed as isOneDayBefore in dataset
            const isOneDayBefore = row.dataset.isOneDayBefore === 'true' || 
                                   row.getAttribute('data-is-one-day-before') === 'true';
            const requestId = row.dataset.requestId || row.getAttribute('data-request-id') || '';
            const hospitalName = row.dataset.hospitalName || row.getAttribute('data-hospital-name') || 'Hospital';
            const timeRemaining = row.dataset.timeRemaining || row.getAttribute('data-time-remaining') || '';
            
            // Only show alert for Pending requests that are 1 day before deadline AND not dismissed
            if (status === 'pending' && isOneDayBefore && !dismissedIds.includes(requestId)) {
                alerts.push({
                    requestId: requestId,
                    hospitalName: hospitalName,
                    timeRemaining: timeRemaining
                });
            }
        });
        
        // Create alert elements
        alerts.forEach((alert) => {
            const alertElement = createAlertElement(alert, false);
            alertContainer.appendChild(alertElement);
        });
        
        // Update notification badge
        updateNotificationBadge();
        
        // Log for debugging
        if (alerts.length > 0) {
            console.log('Showing', alerts.length, 'deadline alert(s)');
        }
    }

    /**
     * Initialize priority handler
     */
    function initPriorityHandler() {
        // Wait for table to be rendered
        let attempts = 0;
        const maxAttempts = 50; // 5 seconds max wait
        
        const checkTable = setInterval(() => {
            attempts++;
            const table = document.getElementById('requestTable');
            const alertContainer = document.getElementById('deadlineAlertContainer');
            const notificationBadge = document.getElementById('deadlineNotificationBadge');
            
            if (table && table.querySelector('tbody tr')) {
                clearInterval(checkTable);
                
                // Ensure alert container exists
                if (!alertContainer) {
                    console.warn('Alert container not found, creating it');
                    const main = document.querySelector('main');
                    if (main) {
                        const container = document.createElement('div');
                        container.id = 'deadlineAlertContainer';
                        main.appendChild(container);
                    }
                }
                
                // Ensure notification badge exists
                if (!notificationBadge) {
                    const main = document.querySelector('main');
                    if (main) {
                        const badge = document.createElement('div');
                        badge.id = 'deadlineNotificationBadge';
                        badge.title = 'Click to view dismissed deadline alerts';
                        badge.innerHTML = '<i class="fas fa-bell"></i><span class="badge-count" id="deadlineNotificationCount">0</span>';
                        badge.addEventListener('click', function() {
                            showDismissedAlerts();
                        });
                        main.appendChild(badge);
                    }
                } else {
                    // Add click handler to notification badge
                    notificationBadge.addEventListener('click', function() {
                        showDismissedAlerts();
                    });
                }
                
                // Apply initial styling
                applyPriorityStyling();
                addPriorityBadges();
                
                // Show alerts after a short delay to ensure DOM is ready
                setTimeout(() => {
                    showDeadlineAlerts();
                    updateNotificationBadge();
                }, 200);
                
                // Update every 5 minutes for time-sensitive requests
                setInterval(() => {
                    updatePriorityStyling();
                    showDeadlineAlerts();
                    updateNotificationBadge();
                }, 5 * 60 * 1000);
            } else if (attempts >= maxAttempts) {
                clearInterval(checkTable);
                console.warn('Table not found after maximum attempts');
            }
        }, 100);
        
        // Also apply after search/filter operations
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                setTimeout(() => {
                    applyPriorityStyling();
                    addPriorityBadges();
                    showDeadlineAlerts();
                    updateNotificationBadge();
                }, 100);
            });
        }
        
        // Re-apply when modal closes (table might be updated) - but don't re-show dismissed alerts
        document.addEventListener('hidden.bs.modal', function() {
            setTimeout(() => {
                applyPriorityStyling();
                addPriorityBadges();
                // Only show new alerts, not dismissed ones
                showDeadlineAlerts();
                updateNotificationBadge();
            }, 100);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPriorityHandler);
    } else {
        initPriorityHandler();
    }

})();

