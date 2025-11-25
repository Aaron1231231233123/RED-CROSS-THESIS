/**
 * Duplicate Donor Check System (Admin-specific)
 * 
 * This module provides functionality to check for existing donors
 * based on personal information (surname, first_name, middle_name, birthdate)
 * and displays alerts when duplicates are found.
 * 
 * Admin-specific version with admin-specific naming to avoid conflicts with staff side
 */

class DuplicateDonorCheckerAdmin {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || '../../assets/php_func/check_duplicate_donor_admin.php';
        this.updateApiEndpoint = options.updateApiEndpoint || '../../assets/php_func/update_donor_needs_review.php';
        this.debounceDelay = options.debounceDelay || 1000; // 1 second delay
        this.enableAutoCheck = options.enableAutoCheck !== false; // Default to true
        
        // State management
        this.isChecking = false;
        this.lastCheckData = null;
        this.debounceTimer = null;
        this.currentDonorId = null;
        
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
        const elementsFound = this.setupFormElements();
        this.createModal();
        
        if (elementsFound) {
            this.attachEventListeners();
            console.log('Duplicate Donor Checker (Admin) initialized successfully');
        } else {
            console.warn('Duplicate Donor Checker (Admin): Form elements not found during init. Will retry when form loads.');
            // Retry after a delay if elements not found
            setTimeout(() => {
                if (this.setupFormElements()) {
                    this.attachEventListeners();
                    console.log('Duplicate Donor Checker (Admin): Successfully initialized after retry');
                }
            }, 500);
        }
    }
    
    /**
     * Setup form element references (admin-specific IDs)
     */
    setupFormElements() {
        this.formElements = {
            surname: document.getElementById('surname'),
            firstName: document.getElementById('first_name'),
            middleName: document.getElementById('middle_name'),
            birthdate: document.getElementById('birthdate'),
            age: document.getElementById('age'),
            form: document.getElementById('adminDonorPersonalDataForm')
        };
        
        // Validate that required elements exist
        const requiredElements = ['surname', 'firstName', 'birthdate', 'form'];
        let missingElements = [];
        for (const element of requiredElements) {
            if (!this.formElements[element]) {
                missingElements.push(element);
                console.warn(`Duplicate checker (admin): Required element '${element}' not found`);
            }
        }
        
        // If elements are missing, log for debugging
        if (missingElements.length > 0) {
            console.warn('Duplicate checker (admin): Missing elements:', missingElements);
            console.warn('Form available:', !!this.formElements.form);
            console.warn('Current form HTML:', this.formElements.form ? this.formElements.form.innerHTML.substring(0, 200) : 'N/A');
        }
        
        return missingElements.length === 0;
    }
    
    /**
     * Create the duplicate alert modal (admin-specific)
     */
    createModal() {
        // Check if modal already exists
        if (document.getElementById('duplicateDonorModalAdmin')) {
            console.log('Duplicate checker (admin): Modal already exists');
            return;
        }
        
        console.log('Duplicate checker (admin): Creating modal...');
        
        const modalHTML = `
            <div class="modal fade" id="duplicateDonorModalAdmin" tabindex="-1" aria-labelledby="duplicateDonorModalAdminLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #a00000 100%) !important; border-bottom: none; padding: 1.5rem;">
                            <div class="d-flex align-items-center w-100">
                                <div class="me-3">
                                    <i class="fas fa-user-check fa-2x text-white"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="modal-title fw-bold mb-0" id="duplicateDonorModalAdminLabel" style="color: white !important;">
                                        Existing Donor Record Found
                                    </h5>
                                    <small class="text-white-50 d-block mt-1">A donor with matching information already exists in our system.</small>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" id="duplicateModalCloseBtnAdmin" aria-label="Close"></button>
                        </div>
                        <div class="modal-body bg-white p-0">
                            <div id="duplicateDonorInfoAdmin" class="p-4">
                                <!-- Donor information will be populated here -->
                            </div>
                            
                            <div class="modal-footer bg-light border-top d-flex justify-content-end p-3">
                                <button type="button" class="btn btn-outline-secondary px-4" id="goBackAdminDonorRegistration" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Return
                                </button>
                                <button type="button" class="btn btn-danger px-4" id="updateDonorInfoBtnAdmin" style="display: none;">
                                    <i class="fas fa-edit me-2"></i>Update Donor Information
                                    <span class="spinner-border spinner-border-sm ms-2 d-none" id="updateSpinnerAdmin"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Verify modal was created
        const createdModal = document.getElementById('duplicateDonorModalAdmin');
        if (createdModal) {
            console.log('Duplicate checker (admin): Modal created successfully in DOM');
        } else {
            console.error('Duplicate checker (admin): Failed to create modal in DOM');
        }
        
        // Apply Red Cross theme - override any Bootstrap defaults
        setTimeout(() => {
            const modal = document.getElementById('duplicateDonorModalAdmin');
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
        }, 10);
        
        // Setup modal event listeners
        this.setupModalEventListeners();
    }
    
    /**
     * Setup event listeners for the modal (admin-specific)
     */
    setupModalEventListeners() {
        const modal = document.getElementById('duplicateDonorModalAdmin');
        const goBackBtn = document.getElementById('goBackAdminDonorRegistration');
        const closeBtn = document.getElementById('duplicateModalCloseBtnAdmin');
        const updateBtn = document.getElementById('updateDonorInfoBtnAdmin');
        
        if (updateBtn) {
            updateBtn.addEventListener('click', () => {
                this.updateDonorInformation();
            });
        }
        
        if (goBackBtn) {
            goBackBtn.addEventListener('click', () => {
                // Add confirmation dialog for duplicate modal close
                if (confirm('Are you sure you want to cancel? You will lose any progress made on this form.')) {
                    // Hide the modal
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    
                    // Close the admin registration modal
                    const adminModal = document.getElementById('adminDonorRegistrationModal');
                    if (adminModal) {
                        const adminModalInstance = bootstrap.Modal.getInstance(adminModal);
                        if (adminModalInstance) {
                            adminModalInstance.hide();
                        }
                    }
                }
            });
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                // Hide the modal (no confirmation needed for X button)
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
        }
    }
    
    /**
     * Attach event listeners to form elements
     */
    attachEventListeners() {
        if (!this.enableAutoCheck) {
            console.log('Duplicate checker (admin): Auto-check is disabled');
            return;
        }
        
        // Remove existing listeners first to avoid duplicates
        this.removeEventListeners();
        
        const elements = [
            { el: this.formElements.surname, name: 'surname' },
            { el: this.formElements.firstName, name: 'first_name' },
            { el: this.formElements.middleName, name: 'middle_name' },
            { el: this.formElements.birthdate, name: 'birthdate' }
        ];
        
        let attachedCount = 0;
        elements.forEach(({ el, name }) => {
            if (el) {
                // Create bound handlers
                const inputHandler = () => {
                    console.log(`Duplicate checker (admin): Input detected on ${name}`);
                    this.scheduleCheck();
                };
                const blurHandler = () => {
                    console.log(`Duplicate checker (admin): Blur detected on ${name}`);
                    this.scheduleCheck();
                };
                
                // Store handlers for later removal
                el._duplicateCheckInputHandler = inputHandler;
                el._duplicateCheckBlurHandler = blurHandler;
                
                // Use input event for real-time checking
                el.addEventListener('input', inputHandler);
                el.addEventListener('blur', blurHandler);
                attachedCount++;
                console.log(`Duplicate checker (admin): Event listeners attached to ${name}`);
            } else {
                console.warn(`Duplicate checker (admin): Element ${name} not found for event listener`);
            }
        });
        
        // Also listen to email field for duplicate email checking
        const emailInput = document.getElementById('email');
        if (emailInput) {
            const emailInputHandler = () => {
                console.log('Duplicate checker (admin): Input detected on email');
                this.scheduleCheck();
            };
            const emailBlurHandler = () => {
                console.log('Duplicate checker (admin): Blur detected on email');
                this.scheduleCheck();
            };
            
            emailInput._duplicateCheckInputHandler = emailInputHandler;
            emailInput._duplicateCheckBlurHandler = emailBlurHandler;
            
            emailInput.addEventListener('input', emailInputHandler);
            emailInput.addEventListener('blur', emailBlurHandler);
            attachedCount++;
            console.log('Duplicate checker (admin): Event listeners attached to email');
        }
        
        console.log(`Duplicate checker (admin): Attached ${attachedCount} event listeners`);
        
        // Note: Form submission is handled by admin-donor-registration-modal.js
        // We don't attach a submit listener here to avoid conflicts
        // The admin modal will manually call performCheck() before submission
    }
    
    /**
     * Remove event listeners to prevent duplicates
     */
    removeEventListeners() {
        const elements = [
            this.formElements.surname,
            this.formElements.firstName,
            this.formElements.middleName,
            this.formElements.birthdate
        ];
        
        elements.forEach(el => {
            if (el && el._duplicateCheckInputHandler) {
                el.removeEventListener('input', el._duplicateCheckInputHandler);
                el.removeEventListener('blur', el._duplicateCheckBlurHandler);
                delete el._duplicateCheckInputHandler;
                delete el._duplicateCheckBlurHandler;
            }
        });
        
        const emailInput = document.getElementById('email');
        if (emailInput && emailInput._duplicateCheckInputHandler) {
            emailInput.removeEventListener('input', emailInput._duplicateCheckInputHandler);
            emailInput.removeEventListener('blur', emailInput._duplicateCheckBlurHandler);
            delete emailInput._duplicateCheckInputHandler;
            delete emailInput._duplicateCheckBlurHandler;
        }
    }
    
    /**
     * Schedule a duplicate check with debouncing
     */
    scheduleCheck() {
        // Make sure form elements are still available
        if (!this.formElements.form || !this.formElements.surname || !this.formElements.firstName || !this.formElements.birthdate) {
            // Try to re-setup elements
            console.log('Duplicate checker (admin): Re-setting up form elements');
            this.setupFormElements();
        }
        
        // Clear existing timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        // Schedule new check
        this.debounceTimer = setTimeout(() => {
            console.log('Duplicate checker (admin): Performing scheduled check');
            this.performCheck();
        }, this.debounceDelay);
    }
    
    /**
     * Handle form submission and check for duplicates
     */
    handleFormSubmit(event) {
        // If duplicate has been acknowledged, allow submission
        if (this.formElements.form && this.formElements.form.getAttribute('data-duplicate-acknowledged-admin') === 'true') {
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
        const emailInput = document.getElementById('email');
        return {
            surname: this.formElements.surname?.value?.trim() || '',
            first_name: this.formElements.firstName?.value?.trim() || '',
            middle_name: this.formElements.middleName?.value?.trim() || '',
            birthdate: this.formElements.birthdate?.value?.trim() || '',
            email: emailInput?.value?.trim() || ''
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
        // Check if we have email (can check email duplicates)
        if (data.email && data.email.trim()) {
            return true;
        }
        // Or check if we have name and birthdate (can check name/birthdate duplicates)
        return data.surname && data.first_name && data.birthdate;
    }
    
    /**
     * Perform the duplicate check
     * Returns a Promise that resolves when check is complete
     */
    async performCheck(forceSubmit = false) {
        // Re-setup form elements in case they weren't found initially or form was reloaded
        if (!this.formElements.form || !this.formElements.surname || !this.formElements.birthdate) {
            console.log('Duplicate checker (admin): Re-setting up form elements before check');
            const elementsFound = this.setupFormElements();
            if (elementsFound) {
                // Re-attach listeners if elements are now found
                this.attachEventListeners();
            } else {
                console.warn('Duplicate checker (admin): Form elements still not found, cannot perform check');
                if (forceSubmit) {
                    if (this.formElements.form) {
                        this.formElements.form.setAttribute('data-duplicate-checked-admin', 'true');
                    }
                }
                return Promise.resolve();
            }
        }
        
        const currentData = this.getCurrentFormData();
        console.log('Duplicate checker (admin): Performing check with data:', {
            surname: currentData.surname,
            first_name: currentData.first_name,
            birthdate: currentData.birthdate,
            email: currentData.email ? 'provided' : 'not provided'
        });
        
        // Don't check if we don't have enough data
        if (!this.isDataComplete(currentData)) {
            console.log('Duplicate checker (admin): Data incomplete, skipping check. Need: surname, first_name, birthdate OR email');
            this.lastCheckData = currentData;
            // Return resolved promise so .then() works
            if (forceSubmit) {
                // If forceSubmit and no data, mark as checked and allow submission
                if (this.formElements.form) {
                    this.formElements.form.setAttribute('data-duplicate-checked-admin', 'true');
                }
            }
            return Promise.resolve();
        }
        
        // Don't check if data hasn't changed (unless forced)
        if (this.lastCheckData && this.isDataEqual(currentData, this.lastCheckData) && !forceSubmit) {
            return Promise.resolve();
        }
        
        // Don't check if already checking
        if (this.isChecking) {
            return Promise.resolve();
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
                const errorText = await response.text();
                console.error('Duplicate checker (admin): HTTP error response:', errorText);
                throw new Error(`HTTP error! status: ${response.status}. Response: ${errorText.substring(0, 200)}`);
            }
            
            // Get response as text first to check for errors
            const responseText = await response.text();
            console.log('Duplicate checker (admin): Raw API response:', responseText.substring(0, 500));
            
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Duplicate checker (admin): JSON parse error:', parseError);
                console.error('Duplicate checker (admin): Response text:', responseText);
                throw new Error(`Invalid JSON response from server. Response: ${responseText.substring(0, 300)}`);
            }
            
            this.hideCheckingIndicator();
            
            console.log('Duplicate checker (admin): Check result:', {
                status: result.status,
                duplicate_found: result.duplicate_found,
                message: result.message
            });
            
            if (result.status === 'success' && result.duplicate_found) {
                console.log('Duplicate checker (admin): Duplicate found, showing modal');
                this.showDuplicateAlert(result.data, forceSubmit);
                // Return promise that resolves when user acknowledges (or rejects if they cancel)
                return Promise.resolve();
            } else if (forceSubmit) {
                // No duplicate found and this was a form submit check, mark as checked
                console.log('Duplicate checker (admin): No duplicate found, allowing submission');
                if (this.formElements.form) {
                    this.formElements.form.setAttribute('data-duplicate-checked-admin', 'true');
                }
                return Promise.resolve();
            }
            
            console.log('Duplicate checker (admin): Check complete, no duplicate found');
            return Promise.resolve();
            
        } catch (error) {
            console.error('Duplicate check error (admin):', error);
            this.hideCheckingIndicator();
            
            // On error, if this was a form submit, show a warning but allow submission
            if (forceSubmit) {
                this.showErrorAlert(error.message);
                // Mark as checked so submission can proceed
                if (this.formElements.form) {
                    this.formElements.form.setAttribute('data-duplicate-checked-admin', 'true');
                }
            }
            return Promise.resolve(); // Always resolve, even on error
        } finally {
            this.isChecking = false;
        }
    }
    
    /**
     * Show checking indicator
     */
    showCheckingIndicator() {
        // Add a subtle loading indicator to the form
        let indicator = document.getElementById('duplicateCheckIndicatorAdmin');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'duplicateCheckIndicatorAdmin';
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
        const indicator = document.getElementById('duplicateCheckIndicatorAdmin');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    /**
     * Show duplicate alert modal
     */
    showDuplicateAlert(duplicateData, forceSubmit = false) {
        console.log('Duplicate checker (admin): showDuplicateAlert called with data:', duplicateData);
        
        const modal = document.getElementById('duplicateDonorModalAdmin');
        const infoContainer = document.getElementById('duplicateDonorInfoAdmin');
        
        if (!modal) {
            console.error('Duplicate checker (admin): Modal element not found! Creating modal...');
            // Try to create modal if it doesn't exist
            this.createModal();
            // Try again
            const modalRetry = document.getElementById('duplicateDonorModalAdmin');
            if (!modalRetry) {
                console.error('Duplicate checker (admin): Failed to create modal');
                alert('A duplicate donor was found, but the modal could not be displayed. Please check the console for details.');
                return;
            }
        }
        
        if (!infoContainer) {
            console.error('Duplicate checker (admin): Info container not found in modal');
            return;
        }
        
        console.log('Duplicate checker (admin): Modal and container found, populating data');
        
        // Store current donor ID for update functionality
        this.currentDonorId = duplicateData.donor_id || null;
        
        // Populate modal with design matching the images
        const isEmailDuplicate = duplicateData.duplicate_type === 'email';
        const hasDonationHistory = duplicateData.has_eligibility_history;
        
        // Format dates
        const formatDateForDisplay = (dateString) => {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            } catch (e) {
                return dateString;
            }
        };
        
        infoContainer.innerHTML = `
            ${isEmailDuplicate ? `
                <div class="alert alert-warning border-start border-3 border-warning mb-3">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-envelope me-2 fs-4"></i>
                        <div class="flex-grow-1">
                            <strong>Duplicate Email Address Detected</strong><br>
                            <small>This email address is already registered to another donor in the system.</small>
                        </div>
                    </div>
                </div>
            ` : ''}
            
            <!-- Donor Profile Section -->
            <div class="card mb-3 border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #dc3545 0%, #a00000 100%); color: white;">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-user me-2"></i>Donor Profile
                    </h5>
                </div>
                <div class="card-body">
                    <h4 class="text-danger mb-2 fw-bold">${duplicateData.full_name}</h4>
                    ${duplicateData.prc_donor_number ? `
                        <p class="mb-3 text-muted"><strong>Donor ID:</strong> ${duplicateData.prc_donor_number}</p>
                    ` : ''}
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded h-100">
                                <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">AGE</small>
                                <strong class="fs-5 d-block">${duplicateData.age || 'N/A'} ${duplicateData.age ? 'years' : ''}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded h-100">
                                <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">SEX</small>
                                <strong class="fs-5 d-block">${duplicateData.sex || 'N/A'}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded h-100">
                                <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">MOBILE</small>
                                <strong class="fs-6 d-block">${duplicateData.mobile || 'Not provided'}</strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 bg-light rounded h-100">
                                <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">EMAIL</small>
                                <strong class="fs-6 d-block text-break">${duplicateData.email || 'Not provided'}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            ${hasDonationHistory ? `
                <!-- Donation History Section (for donors with history) -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2"></i>Donation History
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 rounded" style="background: linear-gradient(135deg, #fff5f5 0%, #ffe0e0 100%);">
                                    <div class="me-3">
                                        <i class="fas fa-tint fa-2x text-danger"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase;">TOTAL DONATIONS</small>
                                        <h3 class="text-danger mb-0 fw-bold">${duplicateData.total_donations || 0}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 rounded" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                                    <div class="me-3">
                                        <i class="fas fa-file-medical fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase;">TOTAL RECORDS</small>
                                        <h3 class="text-primary mb-0 fw-bold">${duplicateData.total_eligibility_records || 0}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ${duplicateData.latest_donation_date || duplicateData.time_description ? `
                            <div class="row g-3">
                                ${duplicateData.latest_donation_date ? `
                                    <div class="col-md-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Last Donation Date</small>
                                            <strong class="fs-6">${formatDateForDisplay(duplicateData.latest_donation_date)}</strong>
                                        </div>
                                    </div>
                                ` : ''}
                                ${duplicateData.time_description ? `
                                    <div class="col-md-6">
                                        <div class="p-2 bg-light rounded">
                                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Last Submission Date</small>
                                            <strong class="fs-6">${duplicateData.time_description}</strong>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                </div>
            ` : `
                <!-- Eligibility & Status Information Section (for new donors) -->
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-check-circle me-2 text-danger"></i>Eligibility & Status Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- New Donor Box -->
                        <div class="alert alert-info border-start border-3 border-info mb-3" style="background-color: #e3f2fd !important; border-left-color: #2196F3 !important;">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle fa-lg me-3 mt-1 text-info"></i>
                                <div class="flex-grow-1">
                                    <strong>New Donor</strong>
                                    <p class="mb-0 mt-2">This donor is registered but has no donation history yet.</p>
                                </div>
                            </div>
                        </div>
                        
                        ${duplicateData.donation_stage ? `
                            <!-- Donor Stage Box -->
                            <div class="alert alert-info border-start border-3 border-info mb-3" style="background-color: #e3f2fd !important; border-left-color: #2196F3 !important;">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-info-circle fa-lg me-3 mt-1 text-info"></i>
                                    <div class="flex-grow-1">
                                        <strong>Donor Stage: ${duplicateData.donation_stage}</strong>
                                        <p class="mb-0 mt-2">${duplicateData.reason || `This data was in the ${duplicateData.donation_stage} stage.`}</p>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `}
            
            <!-- Staff Advisory Section -->
            <div class="alert mb-0 ${duplicateData.can_donate_today ? 'alert-success' : 'alert-warning'}" style="border-left: 4px solid ${duplicateData.can_donate_today ? '#28a745' : '#ffc107'};">
                <div class="d-flex align-items-start">
                    <i class="fas fa-lightbulb fa-lg me-3 mt-1"></i>
                    <div class="flex-grow-1">
                        <strong>Staff Advisory:</strong>
                        <p class="mb-0 mt-2">${duplicateData.suggestion || 'Please review the donor information before proceeding.'}</p>
                    </div>
                </div>
            </div>
        `;
        
        // Show "Update Donor Information" button conditionally (same logic as staff side)
        // Only show if:
        // 1. Donor has eligibility history (not a new donor)
        // 2. Can donate today (no waiting period or deferral)
        // 3. No days remaining for deferral or waiting period
        const updateBtn = document.getElementById('updateDonorInfoBtnAdmin');
        if (updateBtn && this.currentDonorId) {
            // Check if it's a new donor (no eligibility history)
            const isNewDonor = !duplicateData.has_eligibility_history;
            
            // Check if deferral period has days remaining
            const hasDeferralDaysRemaining = duplicateData.temporary_deferred_days_remaining !== null && 
                                            duplicateData.temporary_deferred_days_remaining !== undefined && 
                                            duplicateData.temporary_deferred_days_remaining > 0;
            
            // Check if suggestion indicates waiting period (e.g., "Wait X more day(s)" or "Must wait X more day(s)")
            const suggestion = duplicateData.suggestion || '';
            const hasWaitingPeriod = /(?:Wait|Must wait)\s+\d+\s+more\s+day/i.test(suggestion);
            
            // Show button only if:
            // - Not a new donor (has eligibility history)
            // - Can donate today (no waiting period)
            // - No deferral days remaining
            // - No waiting period mentioned in suggestion
            if (!isNewDonor && duplicateData.can_donate_today && !hasDeferralDaysRemaining && !hasWaitingPeriod) {
                updateBtn.style.display = 'inline-block';
                updateBtn.innerHTML = '<i class="fas fa-edit me-2"></i>Update Donor Information<span class="spinner-border spinner-border-sm ms-2 d-none" id="updateSpinnerAdmin"></span>';
                updateBtn.className = 'btn btn-danger px-4';
                updateBtn.title = 'Update existing donor information and mark for review';
            } else {
                updateBtn.style.display = 'none';
            }
        } else if (updateBtn) {
            updateBtn.style.display = 'none';
        }
        
        console.log('Duplicate checker (admin): Modal content populated, showing modal');
        console.log('Duplicate checker (admin): Modal element:', modal);
        console.log('Duplicate checker (admin): Bootstrap available:', typeof bootstrap !== 'undefined');
        
        // Show the modal
        try {
            // Check if Bootstrap is available
            if (typeof bootstrap === 'undefined') {
                throw new Error('Bootstrap is not loaded');
            }
            
            // Get or create modal instance
            let modalInstance = bootstrap.Modal.getInstance(modal);
            if (!modalInstance) {
                console.log('Duplicate checker (admin): Creating new Bootstrap Modal instance');
                modalInstance = new bootstrap.Modal(modal, {
                    backdrop: true,
                    keyboard: true
                });
            }
            
            console.log('Duplicate checker (admin): Showing modal with Bootstrap...');
            modalInstance.show();
            
            // Verify modal is visible after a short delay
            setTimeout(() => {
                const isVisible = modal.classList.contains('show');
                console.log('Duplicate checker (admin): Modal visible after show():', isVisible);
                if (!isVisible) {
                    console.warn('Duplicate checker (admin): Modal may not be visible. Checking DOM...');
                    console.log('Duplicate checker (admin): Modal classes:', modal.className);
                    console.log('Duplicate checker (admin): Modal style.display:', modal.style.display);
                }
            }, 100);
            
        } catch (error) {
            console.error('Duplicate checker (admin): Error showing modal:', error);
            console.error('Duplicate checker (admin): Error details:', error.message, error.stack);
            
            // Fallback: try to show modal manually
            if (modal) {
                console.log('Duplicate checker (admin): Attempting manual modal display...');
                modal.style.display = 'block';
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                modal.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                
                // Create backdrop manually
                let backdrop = document.getElementById('duplicateModalBackdropAdmin');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'duplicateModalBackdropAdmin';
                    document.body.appendChild(backdrop);
                }
                
                console.log('Duplicate checker (admin): Manual modal display attempted');
            }
        }
        
        // If this was triggered by form submission, we need to handle it specially
        if (forceSubmit) {
            // Store the form submission intent
            this.pendingSubmission = true;
        }
    }
    
    /**
     * Show error alert
     */
    /**
     * Update donor information - sets needs_review to true in medical_history
     */
    async updateDonorInformation() {
        if (!this.currentDonorId) {
            alert('Error: Donor ID not available');
            return;
        }

        const updateBtn = document.getElementById('updateDonorInfoBtnAdmin');
        const spinner = document.getElementById('updateSpinnerAdmin');
        
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
                // Show success message in staff advisory
                const staffAdvisory = document.querySelector('#duplicateDonorInfoAdmin .alert:last-child');
                if (staffAdvisory) {
                    staffAdvisory.className = 'alert alert-success mb-0';
                    staffAdvisory.style.borderLeftColor = '#28a745';
                    const advisoryContent = staffAdvisory.querySelector('.flex-grow-1');
                    if (advisoryContent) {
                        advisoryContent.innerHTML = `
                            <strong>Staff Advisory:</strong>
                            <p class="mb-0 mt-2">
                                <strong class="text-success">
                                    <i class="fas fa-check-circle me-2"></i>Donor information updated successfully!
                                </strong><br>
                                The medical history record has been marked for review. Staff can now proceed with updating the donor's information.
                            </p>
                        `;
                    }
                }

                // Hide update button after successful update
                updateBtn.style.display = 'none';

                // Show success notification and close modal after delay
                setTimeout(() => {
                    const modal = document.getElementById('duplicateDonorModalAdmin');
                    if (modal) {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }
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
    
    showErrorAlert(errorMessage) {
        // Create a simple alert for errors
        const alertHTML = `
            <div class="alert alert-warning alert-dismissible fade show" role="alert" id="duplicateCheckErrorAdmin">
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
                const alert = document.getElementById('duplicateCheckErrorAdmin');
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
     * Note: This is called by showErrorAlert, but for admin form,
     * submission is handled by admin-donor-registration-modal.js
     * We just mark it as checked so submitPersonalData can proceed
     */
    allowFormSubmission() {
        if (this.formElements.form) {
            // Mark that duplicate check is complete
            this.formElements.form.setAttribute('data-duplicate-checked-admin', 'true');
            
            // For admin form, we don't dispatch submit event here
            // The admin modal's submitPersonalData will check the flag and proceed
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

// Global instance - will be initialized manually for admin
let duplicateCheckerAdmin;

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DuplicateDonorCheckerAdmin;
}

