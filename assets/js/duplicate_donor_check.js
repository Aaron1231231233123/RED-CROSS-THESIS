/**
 * Duplicate Donor Check System
 * 
 * This module provides functionality to check for existing donors
 * based on personal information (surname, first_name, middle_name, birthdate)
 * and displays alerts when duplicates are found.
 */

class DuplicateDonorChecker {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || 'assets/php_func/check_duplicate_donor.php';
        this.debounceDelay = options.debounceDelay || 1000; // 1 second delay
        this.enableAutoCheck = options.enableAutoCheck !== false; // Default to true
        
        // State management
        this.isChecking = false;
        this.lastCheckData = null;
        this.debounceTimer = null;
        
        // DOM elements will be set when initialized
        this.formElements = {};
        
        // Initialize if DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    /**
     * Initialize the duplicate checker
     */
    init() {
        this.setupFormElements();
        this.createModal();
        this.attachEventListeners();
        console.log('Duplicate Donor Checker initialized');
    }
    
    /**
     * Setup form element references
     */
    setupFormElements() {
        this.formElements = {
            surname: document.getElementById('surname'),
            firstName: document.getElementById('first_name'),
            middleName: document.getElementById('middle_name'),
            birthdate: document.getElementById('birthdate'),
            form: document.getElementById('donorForm')
        };
        
        // Validate that required elements exist
        const requiredElements = ['surname', 'firstName', 'birthdate', 'form'];
        for (const element of requiredElements) {
            if (!this.formElements[element]) {
                console.warn(`Duplicate checker: Required element '${element}' not found`);
            }
        }
    }
    
    /**
     * Create the duplicate alert modal
     */
    createModal() {
        // Check if modal already exists
        if (document.getElementById('duplicateDonorModal')) {
            return;
        }
        
        const modalHTML = `
            <div class="modal fade" id="duplicateDonorModal" tabindex="-1" aria-labelledby="duplicateDonorModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #a00000 100%) !important; border-bottom: none;">
                            <h5 class="modal-title fw-bold" id="duplicateDonorModalLabel" style="color: white !important;">
                                <i class="fas fa-user-search me-2"></i>
                                Donor Record Found
                            </h5>
                            <a href="javascript:void(0)" class="modal-close" id="duplicateModalCloseBtn">&times;</a>
                        </div>
                        <div class="modal-body bg-white p-4">
                            <div id="duplicateDonorInfo">
                                <!-- Donor information will be populated here -->
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center pt-3 mt-4" style="border-top: 1px solid #dee2e6;">
                                <button type="button" class="btn btn-outline-secondary px-4" id="goBackToDashboard" data-bs-dismiss="modal">
                                    <i class="fas fa-arrow-left me-2"></i>Go Back
                                </button>
                                <button type="button" class="btn btn-danger px-4" id="continueDonorRegistration">
                                    Register as New Donor<i class="fas fa-user-plus ms-2"></i>
                                </button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    If this is a different person, you may register them as a new donor.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Apply Red Cross theme - override any Bootstrap defaults
        setTimeout(() => {
            const modal = document.getElementById('duplicateDonorModal');
            if (modal) {
                const header = modal.querySelector('.modal-header');
                if (header) {
                    header.style.setProperty('background', 'linear-gradient(135deg, #dc3545 0%, #a00000 100%)', 'important');
                    header.style.setProperty('background-image', 'linear-gradient(135deg, #dc3545 0%, #a00000 100%)', 'important');
                    header.style.setProperty('color', 'white', 'important');
                    header.classList.remove('bg-warning', 'alert-warning', 'text-warning');
                }
                
                const title = modal.querySelector('.modal-title');
                if (title) {
                    title.style.setProperty('color', 'white', 'important');
                    title.style.setProperty('text-shadow', '0 1px 2px rgba(0,0,0,0.1)', 'important');
                }
                
                // Remove any yellow/warning classes from all elements
                const yellowElements = modal.querySelectorAll('.bg-warning, .alert-warning, .text-warning');
                yellowElements.forEach(el => {
                    el.classList.remove('bg-warning', 'alert-warning', 'text-warning');
                    el.style.setProperty('background-color', '#f8f9fa', 'important');
                });
            }
            
            // Handle close button (×) with confirmation
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    // Add confirmation dialog for duplicate modal close
                    if (confirm('Are you sure you want to return to the dashboard? You will lose any progress made on this form.')) {
                        // Hide the modal
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                        
                        // Navigate back to dashboard
                        if (typeof goBackToDashboard === 'function') {
                            goBackToDashboard(true); // Skip confirmation since user already confirmed
                        } else {
                            // Fallback: try to go back in history
                            window.history.back();
                        }
                    }
                });
            }
        }, 10);
        
        // Setup modal event listeners
        this.setupModalEventListeners();
    }
    
    /**
     * Setup event listeners for the modal
     */
            setupModalEventListeners() {
            const modal = document.getElementById('duplicateDonorModal');
            const continueBtn = document.getElementById('continueDonorRegistration');
            const goBackBtn = document.getElementById('goBackToDashboard');
            const closeBtn = document.getElementById('duplicateModalCloseBtn');
        
        if (continueBtn) {
            continueBtn.addEventListener('click', () => {
                // Hide the modal and allow form submission
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
                
                // Mark that the user has been warned about the duplicate
                this.formElements.form.setAttribute('data-duplicate-acknowledged', 'true');
                
                console.log('User chose to continue despite duplicate warning');
                
                // Proceed to next section (section 3) if we're coming from section 2
                if (typeof proceedToNextSection === 'function') {
                    proceedToNextSection(2);
                } else {
                    console.log('proceedToNextSection function not found');
                }
            });
        }
        
                    if (goBackBtn) {
                goBackBtn.addEventListener('click', () => {
                    // Add confirmation dialog for duplicate modal close
                    if (confirm('Are you sure you want to return to the dashboard? You will lose any progress made on this form.')) {
                        // Navigate back to dashboard
                        if (typeof goBackToDashboard === 'function') {
                            goBackToDashboard(true); // Skip confirmation since user already confirmed
                        } else {
                            // Fallback: try to go back in history
                            window.history.back();
                        }
                    }
                });
            }
    }
    
    /**
     * Attach event listeners to form elements
     */
    attachEventListeners() {
        if (!this.enableAutoCheck) return;
        
        const elements = [
            this.formElements.surname,
            this.formElements.firstName,
            this.formElements.middleName,
            this.formElements.birthdate
        ];
        
        elements.forEach(element => {
            if (element) {
                // Use input event for real-time checking
                element.addEventListener('input', () => this.scheduleCheck());
                element.addEventListener('blur', () => this.scheduleCheck());
            }
        });
        
        // Also check when form is about to be submitted
        if (this.formElements.form) {
            this.formElements.form.addEventListener('submit', (e) => {
                this.handleFormSubmit(e);
            });
        }
    }
    
    /**
     * Schedule a duplicate check with debouncing
     */
    scheduleCheck() {
        // Clear existing timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        // Schedule new check
        this.debounceTimer = setTimeout(() => {
            this.performCheck();
        }, this.debounceDelay);
    }
    
    /**
     * Handle form submission and check for duplicates
     */
    handleFormSubmit(event) {
        // If duplicate has been acknowledged, allow submission
        if (this.formElements.form.getAttribute('data-duplicate-acknowledged') === 'true') {
            return true;
        }
        
        // If we haven't checked yet or data has changed, check now
        const currentData = this.getCurrentFormData();
        if (!this.lastCheckData || !this.isDataEqual(currentData, this.lastCheckData)) {
            event.preventDefault();
            this.performCheck(true); // Force check on submit
            return false;
        }
        
        return true;
    }
    
    /**
     * Get current form data
     */
    getCurrentFormData() {
        return {
            surname: this.formElements.surname?.value?.trim() || '',
            first_name: this.formElements.firstName?.value?.trim() || '',
            middle_name: this.formElements.middleName?.value?.trim() || '',
            birthdate: this.formElements.birthdate?.value?.trim() || ''
        };
    }
    
    /**
     * Check if two data objects are equal
     */
    isDataEqual(data1, data2) {
        return JSON.stringify(data1) === JSON.stringify(data2);
    }
    
    /**
     * Validate if we have enough data to perform a check
     */
    isDataComplete(data) {
        return data.surname && data.first_name && data.birthdate;
    }
    
    /**
     * Perform the duplicate check
     */
    async performCheck(forceSubmit = false) {
        const currentData = this.getCurrentFormData();
        
        // Don't check if we don't have enough data
        if (!this.isDataComplete(currentData)) {
            this.lastCheckData = currentData;
            return;
        }
        
        // Don't check if data hasn't changed
        if (this.lastCheckData && this.isDataEqual(currentData, this.lastCheckData) && !forceSubmit) {
            return;
        }
        
        // Don't check if already checking
        if (this.isChecking) {
            return;
        }
        
        this.isChecking = true;
        this.lastCheckData = currentData;
        
        try {
            this.showCheckingIndicator();
            
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(currentData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            this.hideCheckingIndicator();
            
            if (result.status === 'success' && result.duplicate_found) {
                this.showDuplicateAlert(result.data, forceSubmit);
            } else if (forceSubmit) {
                // No duplicate found and this was a form submit check, allow submission
                this.allowFormSubmission();
            }
            
        } catch (error) {
            console.error('Duplicate check error:', error);
            this.hideCheckingIndicator();
            
            // On error, if this was a form submit, show a warning but allow submission
            if (forceSubmit) {
                this.showErrorAlert(error.message);
            }
        } finally {
            this.isChecking = false;
        }
    }
    
    /**
     * Show checking indicator
     */
    showCheckingIndicator() {
        // Add a subtle loading indicator to the form
        let indicator = document.getElementById('duplicateCheckIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'duplicateCheckIndicator';
            indicator.className = 'text-muted small';
            indicator.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking for existing donor...';
            
            // Insert after the birthdate field
            if (this.formElements.birthdate && this.formElements.birthdate.parentNode) {
                this.formElements.birthdate.parentNode.appendChild(indicator);
            }
        }
        indicator.style.display = 'block';
    }
    
    /**
     * Hide checking indicator
     */
    hideCheckingIndicator() {
        const indicator = document.getElementById('duplicateCheckIndicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    /**
     * Show duplicate alert modal
     */
    showDuplicateAlert(duplicateData, forceSubmit = false) {
        const modal = document.getElementById('duplicateDonorModal');
        const infoContainer = document.getElementById('duplicateDonorInfo');
        
        if (!modal || !infoContainer) {
            console.error('Duplicate modal not found');
            return;
        }
        
        // Populate modal with friendly, card-based donor data
        const statusIcon = this.getStatusIcon(duplicateData.alert_type);
        const reasonSection = duplicateData.reason ? 
            `<div class="alert alert-light border-start border-3 mt-3" style="border-left-color: var(--primary) !important;">
                <small class="text-muted fw-semibold">
                    <i class="fas fa-info-circle me-1"></i>Additional Information:
                </small><br>
                <span class="text-dark">${duplicateData.reason}</span>
            </div>` : '';
        
        infoContainer.innerHTML = `
            <div class="text-center mb-3">
                <h4 class="mb-2 text-danger">
                    <i class="fas fa-user me-2"></i>${duplicateData.full_name}
                </h4>
                <p class="text-muted mb-1">${duplicateData.age} years old, ${duplicateData.sex}</p>
                <small class="text-muted">Born ${this.formatDate(duplicateData.birthdate)}</small>
            </div>
            
            <div class="text-center mb-3">
                <span class="badge ${this.getStatusBadgeClass(duplicateData.alert_type)} px-3 py-2">
                    ${statusIcon} ${duplicateData.status_message}
                </span>
            </div>
            
            ${reasonSection}
            
            <div class="alert ${duplicateData.can_donate_today ? 'alert-success' : 'alert-warning'} border-0 mt-3 mb-3">
                <i class="fas fa-${duplicateData.can_donate_today ? 'check-circle' : 'clock'} me-2"></i>
                <strong>${duplicateData.suggestion}</strong>
            </div>
            
            <div class="contact-info-section mt-3">
                <div class="contact-item d-flex align-items-center mb-2">
                    <div class="contact-icon me-3">
                        <i class="fas fa-phone text-danger"></i>
                    </div>
                    <div class="contact-details flex-grow-1">
                        <small class="text-muted d-block">Mobile Number</small>
                        <strong class="contact-value">${duplicateData.mobile || 'Not provided'}</strong>
                    </div>
                </div>
                
                <div class="contact-item d-flex align-items-center mb-2">
                    <div class="contact-icon me-3">
                        <i class="fas fa-envelope text-danger"></i>
                    </div>
                    <div class="contact-details flex-grow-1">
                        <small class="text-muted d-block">Email Address</small>
                        <strong class="contact-value text-break">${duplicateData.email || 'Not provided'}</strong>
                    </div>
                </div>
                
                <div class="contact-item d-flex align-items-center">
                    <div class="contact-icon me-3">
                        <i class="fas fa-calendar text-danger"></i>
                    </div>
                    <div class="contact-details flex-grow-1">
                        <small class="text-muted d-block">Registration Date</small>
                        <strong class="contact-value">${duplicateData.time_description}</strong>
                    </div>
                </div>
            </div>
        `;
        
        // Show the modal
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // If this was triggered by form submission, we need to handle it specially
        if (forceSubmit) {
            // Store the form submission intent
            this.pendingSubmission = true;
        }
    }
    
    /**
     * Show error alert
     */
    showErrorAlert(errorMessage) {
        // Create a simple alert for errors
        const alertHTML = `
            <div class="alert alert-warning alert-dismissible fade show" role="alert" id="duplicateCheckError">
                <strong>Warning:</strong> Unable to check for existing donors due to a system error. 
                You may continue with registration, but please verify manually if needed.
                <br><small class="text-muted">Error: ${errorMessage}</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        // Insert the alert before the form
        if (this.formElements.form) {
            this.formElements.form.insertAdjacentHTML('beforebegin', alertHTML);
            
            // Auto-dismiss after 10 seconds
            setTimeout(() => {
                const alert = document.getElementById('duplicateCheckError');
                if (alert) {
                    alert.remove();
                }
            }, 10000);
        }
        
        // Allow form submission to continue
        this.allowFormSubmission();
    }
    
    /**
     * Allow form submission to proceed
     */
    allowFormSubmission() {
        if (this.formElements.form) {
            // Mark that duplicate check is complete
            this.formElements.form.setAttribute('data-duplicate-checked', 'true');
            
            // Trigger form submission
            this.formElements.form.submit();
        }
    }
    
    /**
     * Get appropriate CSS class for status badge
     */
    getStatusBadgeClass(status) {
        switch (status?.toLowerCase()) {
            case 'success':
            case 'eligible':
                return 'bg-success text-white';
            case 'approved':
                return 'bg-primary text-white';
            case 'warning':
            case 'ineligible':
            case 'deferred':
                return 'bg-warning text-dark';
            case 'danger':
            case 'disapproved':
            case 'refused':
                return 'bg-danger text-white';
            case 'info':
                return 'bg-info text-white';
            default:
                return 'bg-secondary text-white';
        }
    }
    
    /**
     * Get appropriate icon for status
     */
    getStatusIcon(status) {
        switch (status?.toLowerCase()) {
            case 'success':
                return '<i class="fas fa-check-circle"></i>';
            case 'warning':
                return '<i class="fas fa-exclamation-triangle"></i>';
            case 'danger':
                return '<i class="fas fa-times-circle"></i>';
            case 'info':
                return '<i class="fas fa-info-circle"></i>';
            default:
                return '<i class="fas fa-user"></i>';
        }
    }
    
    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (error) {
            return dateString;
        }
    }
    
    /**
     * Public method to manually trigger a check
     */
    checkNow() {
        this.performCheck(false);
    }
    
    /**
     * Public method to disable/enable auto-checking
     */
    setAutoCheck(enabled) {
        this.enableAutoCheck = enabled;
        if (!enabled && this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
    }
}

// Global instance - will be initialized automatically
let duplicateChecker;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the donor form page
    if (document.getElementById('donorForm')) {
        duplicateChecker = new DuplicateDonorChecker({
            enableAutoCheck: true,
            debounceDelay: 1500 // Increased delay to reduce API calls
        });
        
        // Make it globally accessible for manual checks
        window.duplicateDonorChecker = duplicateChecker;
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DuplicateDonorChecker;
} 