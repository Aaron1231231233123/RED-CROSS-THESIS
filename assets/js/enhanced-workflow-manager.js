/**
 * Enhanced Workflow Manager
 * Unified modal and data management system for staff workflow processes
 * Designed for integration with dashboard-Inventory-System-list-of-donations.php
 */

class EnhancedWorkflowManager {
    constructor() {
        this.currentWorkflow = null;
        this.workflowData = {};
        this.modalStack = [];
        this.isProcessing = false;
        this.eventListeners = new Map();
        
        this.init();
    }

    init() {
        this.setupGlobalEventListeners();
        this.initializeModalCleanup();
        this.setupErrorHandling();
        console.log('Enhanced Workflow Manager initialized');
    }

    /**
     * Start a new workflow session
     * @param {string} workflowType - Type of workflow (interviewer, physician, combined)
     * @param {Object} donorData - Donor information
     * @param {Object} options - Additional options
     */
    startWorkflow(workflowType, donorData, options = {}) {
        if (this.isProcessing) {
            console.warn('Workflow already in progress, queuing request');
            this.queueWorkflow(workflowType, donorData, options);
            return;
        }

        this.isProcessing = true;
        this.currentWorkflow = {
            type: workflowType,
            donorData: donorData,
            options: options,
            startTime: Date.now(),
            steps: [],
            currentStep: 0,
            status: 'active'
        };

        this.workflowData = {
            donor_id: donorData.donor_id,
            screening_id: donorData.screening_id || null,
            medical_history_id: donorData.medical_history_id || null,
            physical_exam_id: donorData.physical_exam_id || null,
            session_id: this.generateSessionId(),
            timestamp: new Date().toISOString()
        };

        console.log('Starting workflow:', this.currentWorkflow);
        this.executeWorkflow();
    }

    /**
     * Execute the workflow based on type
     */
    executeWorkflow() {
        const { type, donorData } = this.currentWorkflow;
        
        try {
            switch (type) {
                case 'interviewer':
                    this.executeInterviewerWorkflow(donorData);
                    break;
                case 'physician':
                    this.executePhysicianWorkflow(donorData);
                    break;
                case 'combined':
                    this.executeCombinedWorkflow(donorData);
                    break;
                default:
                    throw new Error(`Unknown workflow type: ${type}`);
            }
        } catch (error) {
            this.handleWorkflowError(error);
        }
    }

    /**
     * Execute interviewer workflow
     */
    executeInterviewerWorkflow(donorData) {
        this.addStep('medical_history_review');
        this.openMedicalHistoryModal(donorData);
    }

    /**
     * Execute physician workflow
     */
    executePhysicianWorkflow(donorData) {
        this.addStep('physical_examination');
        this.openPhysicalExaminationModal(donorData);
    }

    /**
     * Execute combined workflow (medical history + physical examination)
     */
    executeCombinedWorkflow(donorData) {
        this.addStep('medical_history_review');
        this.openMedicalHistoryModal(donorData);
    }

    /**
     * Open medical history modal with enhanced management
     */
    openMedicalHistoryModal(donorData) {
        this.showModal('medicalHistoryModal', {
            donorData: donorData,
            onApprove: (data) => this.handleMedicalHistoryApproval(data),
            onDecline: (data) => this.handleMedicalHistoryDecline(data),
            onClose: () => this.handleModalClose('medical_history')
        });
    }

    /**
     * Open physical examination modal with enhanced management
     */
    openPhysicalExaminationModal(donorData) {
        this.showModal('physicalExaminationModal', {
            donorData: donorData,
            onComplete: (data) => this.handlePhysicalExaminationComplete(data),
            onDefer: (data) => this.handlePhysicalExaminationDefer(data),
            onClose: () => this.handleModalClose('physical_examination')
        });
    }

    /**
     * Enhanced modal management with proper state handling
     */
    showModal(modalId, options = {}) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            throw new Error(`Modal ${modalId} not found`);
        }

        // Clean up any existing modals
        this.cleanupModals();

        // Add to modal stack
        this.modalStack.push({
            id: modalId,
            element: modalElement,
            options: options,
            timestamp: Date.now()
        });

        // Set up modal event listeners
        this.setupModalEventListeners(modalElement, options);

        // Show modal with proper z-index management
        this.displayModal(modalElement);

        console.log(`Modal ${modalId} opened with options:`, options);
    }

    /**
     * Display modal with enhanced z-index and backdrop management
     */
    displayModal(modalElement) {
        // Calculate z-index based on modal stack
        const zIndex = 1050 + (this.modalStack.length * 10);
        
        // Set z-index
        modalElement.style.zIndex = zIndex;
        const modalDialog = modalElement.querySelector('.modal-dialog');
        if (modalDialog) {
            modalDialog.style.zIndex = zIndex + 1;
        }

        // Create and show modal
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });

        modal.show();

        // Handle backdrop z-index
        setTimeout(() => {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = zIndex - 1;
            }
        }, 10);

        // Store modal instance
        modalElement._workflowModal = modal;
    }

    /**
     * Setup event listeners for modal
     */
    setupModalEventListeners(modalElement, options) {
        const eventId = `modal_${Date.now()}`;
        
        // Handle modal close
        const closeHandler = (event) => {
            if (options.onClose) {
                options.onClose(event);
            }
            this.handleModalClose(modalElement.id);
        };

        // Handle approval actions
        const approveButtons = modalElement.querySelectorAll('[data-action="approve"], .approve-btn');
        approveButtons.forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                if (options.onApprove) {
                    const formData = this.collectFormData(modalElement);
                    options.onApprove(formData);
                }
            });
        });

        // Handle decline actions
        const declineButtons = modalElement.querySelectorAll('[data-action="decline"], .decline-btn');
        declineButtons.forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                if (options.onDecline) {
                    const formData = this.collectFormData(modalElement);
                    options.onDecline(formData);
                }
            });
        });

        // Store event listeners for cleanup
        this.eventListeners.set(eventId, {
            modalElement,
            handlers: [
                { element: modalElement, event: 'hidden.bs.modal', handler: closeHandler }
            ]
        });
    }

    /**
     * Handle medical history approval
     */
    handleMedicalHistoryApproval(data) {
        console.log('Medical history approved:', data);
        
        this.addStep('medical_history_approved', data);
        
        // Show success modal
        this.showSuccessModal('Medical History Approved', 'The donor can now proceed to physical examination.', () => {
            // Proceed to next step based on workflow type
            if (this.currentWorkflow.type === 'combined') {
                this.addStep('physical_examination');
                this.openPhysicalExaminationModal(this.currentWorkflow.donorData);
            } else {
                this.completeWorkflow();
            }
        });
    }

    /**
     * Handle medical history decline
     */
    handleMedicalHistoryDecline(data) {
        console.log('Medical history declined:', data);
        
        this.addStep('medical_history_declined', data);
        
        // Show decline confirmation
        this.showConfirmationModal('Medical History Declined', 'The donor has been marked as ineligible.', () => {
            this.completeWorkflow();
        });
    }

    /**
     * Handle physical examination completion
     */
    handlePhysicalExaminationComplete(data) {
        console.log('Physical examination completed:', data);
        
        this.addStep('physical_examination_completed', data);
        
        // Show success modal
        this.showSuccessModal('Physical Examination Completed', 'The donor is ready for blood collection.', () => {
            this.completeWorkflow();
        });
    }

    /**
     * Handle physical examination deferral
     */
    handlePhysicalExaminationDefer(data) {
        console.log('Physical examination deferred:', data);
        
        this.addStep('physical_examination_deferred', data);
        
        // Show deferral confirmation
        this.showConfirmationModal('Donor Deferred', 'The donor has been deferred from donation.', () => {
            this.completeWorkflow();
        });
    }

    /**
     * Handle modal close
     */
    handleModalClose(modalId) {
        console.log(`Modal ${modalId} closed`);
        
        // Remove from modal stack
        this.modalStack = this.modalStack.filter(modal => modal.id !== modalId);
        
        // Clean up event listeners
        this.cleanupModalEventListeners(modalId);
        
        // If no modals left and workflow is active, check if we should continue
        if (this.modalStack.length === 0 && this.currentWorkflow && this.currentWorkflow.status === 'active') {
            this.checkWorkflowContinuation();
        }
    }

    /**
     * Show success modal
     */
    showSuccessModal(title, message, onContinue) {
        const modalHtml = `
            <div class="modal fade" id="workflowSuccessModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 15px;">
                        <div class="modal-header bg-success text-white" style="border-radius: 15px 15px 0 0;">
                            <h5 class="modal-title">
                                <i class="fas fa-check-circle me-2"></i>${title}
                            </h5>
                        </div>
                        <div class="modal-body text-center py-4">
                            <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                            <p class="mb-0">${message}</p>
                        </div>
                        <div class="modal-footer border-0 justify-content-center">
                            <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                                <i class="fas fa-check me-2"></i>Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing success modal
        const existingModal = document.getElementById('workflowSuccessModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modalElement = document.getElementById('workflowSuccessModal');
        const modal = new bootstrap.Modal(modalElement);
        
        modalElement.addEventListener('hidden.bs.modal', () => {
            modalElement.remove();
            if (onContinue) {
                onContinue();
            }
        }, { once: true });

        modal.show();
    }

    /**
     * Show confirmation modal
     */
    showConfirmationModal(title, message, onConfirm) {
        const modalHtml = `
            <div class="modal fade" id="workflowConfirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 15px;">
                        <div class="modal-header bg-warning text-dark" style="border-radius: 15px 15px 0 0;">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle me-2"></i>${title}
                            </h5>
                        </div>
                        <div class="modal-body text-center py-4">
                            <i class="fas fa-info-circle text-info mb-3" style="font-size: 3rem;"></i>
                            <p class="mb-0">${message}</p>
                        </div>
                        <div class="modal-footer border-0 justify-content-center">
                            <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">
                                <i class="fas fa-check me-2"></i>OK
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing confirmation modal
        const existingModal = document.getElementById('workflowConfirmModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modalElement = document.getElementById('workflowConfirmModal');
        const modal = new bootstrap.Modal(modalElement);
        
        modalElement.addEventListener('hidden.bs.modal', () => {
            modalElement.remove();
            if (onConfirm) {
                onConfirm();
            }
        }, { once: true });

        modal.show();
    }

    /**
     * Collect form data from modal
     */
    collectFormData(modalElement) {
        const form = modalElement.querySelector('form');
        if (!form) return {};

        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        return data;
    }

    /**
     * Add step to workflow
     */
    addStep(stepType, data = null) {
        const step = {
            type: stepType,
            timestamp: Date.now(),
            data: data
        };

        this.currentWorkflow.steps.push(step);
        this.currentWorkflow.currentStep = this.currentWorkflow.steps.length;

        console.log('Workflow step added:', step);
    }

    /**
     * Complete workflow
     */
    completeWorkflow() {
        if (!this.currentWorkflow) return;

        this.currentWorkflow.status = 'completed';
        this.currentWorkflow.endTime = Date.now();
        this.currentWorkflow.duration = this.currentWorkflow.endTime - this.currentWorkflow.startTime;

        console.log('Workflow completed:', this.currentWorkflow);

        // Clean up
        this.cleanupModals();
        this.isProcessing = false;

        // Trigger workflow completion event
        this.triggerEvent('workflowCompleted', this.currentWorkflow);

        // Reset for next workflow
        this.currentWorkflow = null;
        this.workflowData = {};
    }

    /**
     * Handle workflow errors
     */
    handleWorkflowError(error) {
        console.error('Workflow error:', error);
        
        this.currentWorkflow.status = 'error';
        this.currentWorkflow.error = error.message;

        // Show error modal
        this.showErrorModal('Workflow Error', error.message);

        // Clean up
        this.cleanupModals();
        this.isProcessing = false;
    }

    /**
     * Show error modal
     */
    showErrorModal(title, message) {
        const modalHtml = `
            <div class="modal fade" id="workflowErrorModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 15px;">
                        <div class="modal-header bg-danger text-white" style="border-radius: 15px 15px 0 0;">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-circle me-2"></i>${title}
                            </h5>
                        </div>
                        <div class="modal-body text-center py-4">
                            <i class="fas fa-times-circle text-danger mb-3" style="font-size: 3rem;"></i>
                            <p class="mb-0">${message}</p>
                        </div>
                        <div class="modal-footer border-0 justify-content-center">
                            <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing error modal
        const existingModal = document.getElementById('workflowErrorModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        const modalElement = document.getElementById('workflowErrorModal');
        const modal = new bootstrap.Modal(modalElement);
        
        modalElement.addEventListener('hidden.bs.modal', () => {
            modalElement.remove();
        }, { once: true });

        modal.show();
    }

    /**
     * Clean up all modals
     */
    cleanupModals() {
        // Close all modals in stack
        this.modalStack.forEach(modal => {
            if (modal.element._workflowModal) {
                modal.element._workflowModal.hide();
            }
        });

        // Remove all modal backdrops
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.remove();
        });

        // Reset body classes
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';

        // Clear modal stack
        this.modalStack = [];

        // Clean up event listeners
        this.eventListeners.forEach((listeners, eventId) => {
            this.cleanupModalEventListeners(eventId);
        });
        this.eventListeners.clear();
    }

    /**
     * Clean up modal event listeners
     */
    cleanupModalEventListeners(eventId) {
        const listeners = this.eventListeners.get(eventId);
        if (listeners) {
            listeners.handlers.forEach(({ element, event, handler }) => {
                element.removeEventListener(event, handler);
            });
            this.eventListeners.delete(eventId);
        }
    }

    /**
     * Setup global event listeners
     */
    setupGlobalEventListeners() {
        // Handle page unload
        window.addEventListener('beforeunload', () => {
            this.cleanupModals();
        });

        // Handle escape key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.modalStack.length > 0) {
                const topModal = this.modalStack[this.modalStack.length - 1];
                this.handleModalClose(topModal.id);
            }
        });
    }

    /**
     * Initialize modal cleanup on page load
     */
    initializeModalCleanup() {
        // Clean up any existing modals on page load
        document.addEventListener('DOMContentLoaded', () => {
            this.cleanupModals();
        });
    }

    /**
     * Setup error handling
     */
    setupErrorHandling() {
        window.addEventListener('error', (event) => {
            if (this.isProcessing) {
                console.error('Error during workflow:', event.error);
                this.handleWorkflowError(new Error(event.error.message || 'Unknown error'));
            }
        });

        window.addEventListener('unhandledrejection', (event) => {
            if (this.isProcessing) {
                console.error('Unhandled promise rejection during workflow:', event.reason);
                this.handleWorkflowError(new Error(event.reason.message || 'Promise rejection'));
            }
        });
    }

    /**
     * Check if workflow should continue
     */
    checkWorkflowContinuation() {
        // Override in subclasses for specific workflow logic
    }

    /**
     * Queue workflow for later execution
     */
    queueWorkflow(workflowType, donorData, options) {
        // Simple queue implementation - can be enhanced
        setTimeout(() => {
            this.startWorkflow(workflowType, donorData, options);
        }, 1000);
    }

    /**
     * Generate unique session ID
     */
    generateSessionId() {
        return 'workflow_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Trigger custom event
     */
    triggerEvent(eventName, data) {
        const event = new CustomEvent(eventName, { detail: data });
        window.dispatchEvent(event);
    }

    /**
     * Get workflow status
     */
    getStatus() {
        return {
            isProcessing: this.isProcessing,
            currentWorkflow: this.currentWorkflow,
            modalStack: this.modalStack,
            workflowData: this.workflowData
        };
    }
}

// Initialize global instance
window.workflowManager = new EnhancedWorkflowManager();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EnhancedWorkflowManager;
}
