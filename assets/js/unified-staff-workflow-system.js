/**
 * Unified Staff Workflow System
 * Complete integration system for dashboard-Inventory-System-list-of-donations.php
 * Combines workflow management, data handling, and validation
 */

class UnifiedStaffWorkflowSystem {
    constructor() {
        this.workflowManager = window.workflowManager || new EnhancedWorkflowManager();
        this.dataHandler = window.dataHandler || new EnhancedDataHandler();
        this.validationSystem = window.validationSystem || new EnhancedValidationSystem();
        
        this.currentDonor = null;
        this.workflowHistory = [];
        this.isInitialized = false;
        
        this.init();
    }

    init() {
        this.setupDashboardIntegration();
        this.setupEventListeners();
        this.isInitialized = true;
    }

    /**
     * Setup dashboard integration
     */
    setupDashboardIntegration() {
        // Override existing dashboard functions
        this.overrideDashboardFunctions();
        
        // Setup modal integration
        this.setupModalIntegration();
        
        // Setup data persistence
        this.setupDataPersistence();
    }

    /**
     * Override existing dashboard functions
     */
    overrideDashboardFunctions() {
        // Override editInterviewerWorkflow
        if (typeof window.editInterviewerWorkflow === 'function') {
            window.originalEditInterviewerWorkflow = window.editInterviewerWorkflow;
            window.editInterviewerWorkflow = (donorId) => this.handleInterviewerWorkflow(donorId);
        }

        // Override editPhysicianWorkflow
        if (typeof window.editPhysicianWorkflow === 'function') {
            window.originalEditPhysicianWorkflow = window.editPhysicianWorkflow;
            window.editPhysicianWorkflow = (donorId) => this.handlePhysicianWorkflow(donorId);
        }

        // Override openPhysicianCombinedWorkflow
        if (typeof window.openPhysicianCombinedWorkflow === 'function') {
            window.originalOpenPhysicianCombinedWorkflow = window.openPhysicianCombinedWorkflow;
            window.openPhysicianCombinedWorkflow = (donor) => this.handleCombinedWorkflow(donor);
        }
    }

    /**
     * Handle interviewer workflow
     */
    async handleInterviewerWorkflow(donorId) {
        try {
            
            // Load donor data
            const donorData = await this.loadDonorData(donorId);
            this.currentDonor = donorData;

            // Start workflow
            this.workflowManager.startWorkflow('interviewer', donorData, {
                onComplete: (result) => this.handleWorkflowComplete('interviewer', result),
                onError: (error) => this.handleWorkflowError('interviewer', error)
            });

        } catch (error) {
            console.error('Error starting interviewer workflow:', error);
            this.showErrorNotification('Failed to start interviewer workflow', error.message);
        }
    }

    /**
     * Handle physician workflow
     */
    async handlePhysicianWorkflow(donorId) {
        try {
            
            // Load donor data
            const donorData = await this.loadDonorData(donorId);
            this.currentDonor = donorData;

            // Start workflow
            this.workflowManager.startWorkflow('physician', donorData, {
                onComplete: (result) => this.handleWorkflowComplete('physician', result),
                onError: (error) => this.handleWorkflowError('physician', error)
            });

        } catch (error) {
            console.error('Error starting physician workflow:', error);
            this.showErrorNotification('Failed to start physician workflow', error.message);
        }
    }

    /**
     * Handle combined workflow
     */
    async handleCombinedWorkflow(donor) {
        try {
            
            this.currentDonor = donor;

            // Start workflow
            this.workflowManager.startWorkflow('combined', donor, {
                onComplete: (result) => this.handleWorkflowComplete('combined', result),
                onError: (error) => this.handleWorkflowError('combined', error)
            });

        } catch (error) {
            console.error('Error starting combined workflow:', error);
            this.showErrorNotification('Failed to start combined workflow', error.message);
        }
    }

    /**
     * Load donor data with enhanced error handling
     */
    async loadDonorData(donorId) {
        try {
            // Try to load from cache first
            const cachedData = await this.dataHandler.loadWorkflowData(`donor_${donorId}`, { useCache: true });
            if (cachedData.success && cachedData.fromCache) {
                return cachedData.data;
            }

            // Load from server
            const response = await fetch(`/api/get-donor-data.php?donor_id=${donorId}`);
            if (!response.ok) {
                throw new Error(`Failed to load donor data: ${response.status}`);
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to load donor data');
            }

            // Cache the data
            await this.dataHandler.saveWorkflowData(`donor_${donorId}`, data.donor, { persist: false });

            return data.donor;

        } catch (error) {
            console.error('Error loading donor data:', error);
            throw error;
        }
    }

    /**
     * Handle workflow completion
     */
    handleWorkflowComplete(workflowType, result) {
        
        // Save workflow history
        this.workflowHistory.push({
            type: workflowType,
            donorId: this.currentDonor?.donor_id,
            result: result,
            timestamp: new Date().toISOString()
        });

        // Show success notification
        this.showSuccessNotification(`${workflowType} workflow completed successfully`);

        // Refresh dashboard if needed
        if (typeof window.refreshDashboard === 'function') {
            setTimeout(() => window.refreshDashboard(), 1000);
        } else if (typeof window.location !== 'undefined') {
            setTimeout(() => window.location.reload(), 1000);
        }
    }

    /**
     * Handle workflow error
     */
    handleWorkflowError(workflowType, error) {
        console.error(`${workflowType} workflow error:`, error);
        
        // Show error notification
        this.showErrorNotification(`${workflowType} workflow failed`, error.message);

        // Log error for debugging
        this.logError(workflowType, error);
    }

    /**
     * Setup modal integration
     */
    setupModalIntegration() {
        // Enhanced medical history modal
        this.setupMedicalHistoryModal();
        
        // Enhanced physical examination modal
        this.setupPhysicalExaminationModal();
        
        // Enhanced screening form modal
        this.setupScreeningFormModal();
        
        // Enhanced defer modal
        this.setupDeferModal();
    }

    /**
     * Setup medical history modal
     */
    setupMedicalHistoryModal() {
        const modalElement = document.getElementById('medicalHistoryModal');
        if (!modalElement) return;

        // Setup real-time validation
        this.validationSystem.setupRealTimeValidation(modalElement, 'medical_history');

        // Enhanced form submission
        const form = modalElement.querySelector('form');
        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                await this.handleMedicalHistorySubmission(form);
            });
        }
    }

    /**
     * Setup physical examination modal
     */
    setupPhysicalExaminationModal() {
        const modalElement = document.getElementById('physicalExaminationModal');
        if (!modalElement) return;

        // Setup real-time validation
        this.validationSystem.setupRealTimeValidation(modalElement, 'physical_examination');

        // Enhanced form submission
        const form = modalElement.querySelector('form');
        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                await this.handlePhysicalExaminationSubmission(form);
            });
        }
    }

    /**
     * Setup screening form modal
     */
    setupScreeningFormModal() {
        const modalElement = document.getElementById('screeningFormModal');
        if (!modalElement) return;

        // Setup real-time validation
        this.validationSystem.setupRealTimeValidation(modalElement, 'screening_form');

        // Enhanced form submission
        const form = modalElement.querySelector('form');
        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                await this.handleScreeningFormSubmission(form);
            });
        }
    }

    /**
     * Setup defer modal
     */
    setupDeferModal() {
        const modalElement = document.getElementById('deferDonorModal');
        if (!modalElement) return;

        // Setup real-time validation
        this.validationSystem.setupRealTimeValidation(modalElement, 'deferral');

        // Enhanced form submission
        const form = modalElement.querySelector('form');
        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                await this.handleDeferralSubmission(form);
            });
        }
    }

    /**
     * Handle medical history submission
     */
    async handleMedicalHistorySubmission(form) {
        try {
            const formData = this.getFormData(form);
            
            // Validate data
            const validation = this.validationSystem.validateData('medical_history', formData);
            if (!validation.valid) {
                this.showValidationErrors(validation);
                return;
            }

            // Show warnings if any
            if (validation.warnings.length > 0) {
                this.showValidationWarnings(validation);
            }

            // Submit data
            const result = await this.submitMedicalHistory(formData);
            
            if (result.success) {
                this.showSuccessNotification('Medical history submitted successfully');
                this.closeModal('medicalHistoryModal');
            } else {
                throw new Error(result.message || 'Submission failed');
            }

        } catch (error) {
            console.error('Medical history submission error:', error);
            this.showErrorNotification('Failed to submit medical history', error.message);
        }
    }

    /**
     * Handle physical examination submission
     */
    async handlePhysicalExaminationSubmission(form) {
        try {
            const formData = this.getFormData(form);
            
            // Validate data
            const validation = this.validationSystem.validateData('physical_examination', formData);
            if (!validation.valid) {
                this.showValidationErrors(validation);
                return;
            }

            // Show warnings if any
            if (validation.warnings.length > 0) {
                this.showValidationWarnings(validation);
            }

            // Submit data
            const result = await this.submitPhysicalExamination(formData);
            
            if (result.success) {
                this.showSuccessNotification('Physical examination submitted successfully');
                this.closeModal('physicalExaminationModal');
            } else {
                throw new Error(result.message || 'Submission failed');
            }

        } catch (error) {
            console.error('Physical examination submission error:', error);
            this.showErrorNotification('Failed to submit physical examination', error.message);
        }
    }

    /**
     * Handle screening form submission
     */
    async handleScreeningFormSubmission(form) {
        try {
            const formData = this.getFormData(form);
            
            // Validate data
            const validation = this.validationSystem.validateData('screening_form', formData);
            if (!validation.valid) {
                this.showValidationErrors(validation);
                return;
            }

            // Show warnings if any
            if (validation.warnings.length > 0) {
                this.showValidationWarnings(validation);
            }

            // Submit data
            const result = await this.submitScreeningForm(formData);
            
            if (result.success) {
                this.showSuccessNotification('Screening form submitted successfully');
                this.closeModal('screeningFormModal');
            } else {
                throw new Error(result.message || 'Submission failed');
            }

        } catch (error) {
            console.error('Screening form submission error:', error);
            this.showErrorNotification('Failed to submit screening form', error.message);
        }
    }

    /**
     * Handle deferral submission
     */
    async handleDeferralSubmission(form) {
        try {
            const formData = this.getFormData(form);
            
            // Validate data
            const validation = this.validationSystem.validateData('deferral', formData);
            if (!validation.valid) {
                this.showValidationErrors(validation);
                return;
            }

            // Submit data
            const result = await this.submitDeferral(formData);
            
            if (result.success) {
                this.showSuccessNotification('Deferral submitted successfully');
                this.closeModal('deferDonorModal');
            } else {
                throw new Error(result.message || 'Submission failed');
            }

        } catch (error) {
            console.error('Deferral submission error:', error);
            this.showErrorNotification('Failed to submit deferral', error.message);
        }
    }

    /**
     * Submit medical history data
     */
    async submitMedicalHistory(data) {
        const response = await fetch('/api/submit-medical-history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    /**
     * Submit physical examination data
     */
    async submitPhysicalExamination(data) {
        const response = await fetch('/api/submit-physical-examination.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    /**
     * Submit screening form data
     */
    async submitScreeningForm(data) {
        const response = await fetch('/api/submit-screening-form.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    /**
     * Submit deferral data
     */
    async submitDeferral(data) {
        const response = await fetch('/api/submit-deferral.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        return await response.json();
    }

    /**
     * Get form data
     */
    getFormData(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    }

    /**
     * Show validation errors
     */
    showValidationErrors(validation) {
        const errorMessages = validation.errors.map(error => error.message).join('\n');
        this.showErrorNotification('Validation Errors', errorMessages);
    }

    /**
     * Show validation warnings
     */
    showValidationWarnings(validation) {
        const warningMessages = validation.warnings.map(warning => warning.message).join('\n');
        this.showWarningNotification('Validation Warnings', warningMessages);
    }

    /**
     * Show success notification
     */
    showSuccessNotification(message, details = '') {
        this.showNotification('success', message, details);
    }

    /**
     * Show error notification
     */
    showErrorNotification(message, details = '') {
        this.showNotification('error', message, details);
    }

    /**
     * Show warning notification
     */
    showWarningNotification(message, details = '') {
        this.showNotification('warning', message, details);
    }

    /**
     * Show notification
     */
    showNotification(type, message, details = '') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
        
        const icon = type === 'success' ? 'fa-check-circle' : 
                    type === 'error' ? 'fa-exclamation-circle' : 
                    type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        notification.innerHTML = `
            <i class="fas ${icon} me-2"></i>
            <strong>${message}</strong>
            ${details ? `<br><small>${details}</small>` : ''}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    /**
     * Close modal
     */
    closeModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    }

    /**
     * Setup data persistence
     */
    setupDataPersistence() {
        // Auto-save form data periodically
        setInterval(() => {
            this.autoSaveFormData();
        }, 30000); // Every 30 seconds

        // Save data before page unload
        window.addEventListener('beforeunload', () => {
            this.autoSaveFormData();
        });
    }

    /**
     * Auto-save form data
     */
    autoSaveFormData() {
        const activeModals = ['medicalHistoryModal', 'physicalExaminationModal', 'screeningFormModal'];
        
        activeModals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && modal.classList.contains('show')) {
                const form = modal.querySelector('form');
                if (form) {
                    const formData = this.getFormData(form);
                    const workflowId = `autosave_${modalId}_${this.currentDonor?.donor_id}`;
                    
                    this.dataHandler.saveWorkflowData(workflowId, formData, { persist: false });
                }
            }
        });
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for workflow events
        window.addEventListener('workflowCompleted', (event) => {
        });

        // Listen for validation events
        window.addEventListener('validationError', (event) => {
        });
    }

    /**
     * Log error for debugging
     */
    logError(workflowType, error) {
        const errorLog = {
            workflowType: workflowType,
            error: error.message,
            stack: error.stack,
            timestamp: new Date().toISOString(),
            donorId: this.currentDonor?.donor_id
        };

        // Send to server for logging
        fetch('/api/log-error.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(errorLog)
        }).catch(err => {
            console.error('Failed to log error:', err);
        });
    }

    /**
     * Get system status
     */
    getStatus() {
        return {
            isInitialized: this.isInitialized,
            currentDonor: this.currentDonor,
            workflowHistory: this.workflowHistory,
            workflowManager: this.workflowManager.getStatus(),
            dataHandler: this.dataHandler.getCacheStats(),
            validationSystem: this.validationSystem.getValidationResult()
        };
    }

    /**
     * Reset system
     */
    reset() {
        this.currentDonor = null;
        this.workflowHistory = [];
        this.workflowManager.cleanupModals();
        this.dataHandler.clearAll();
        this.validationSystem.clearValidationErrors();
    }
}

// Initialize global instance
window.staffWorkflowSystem = new UnifiedStaffWorkflowSystem();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UnifiedStaffWorkflowSystem;
}
