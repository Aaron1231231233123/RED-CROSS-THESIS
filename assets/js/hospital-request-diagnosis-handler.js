/**
 * Hospital Request Diagnosis Handler
 * Handles diagnosis dropdown and auto-sets "when needed" based on urgency
 */

(function() {
    'use strict';

    /**
     * Initialize diagnosis handler for hospital request form
     * @param {Object} options Configuration options
     * @param {string} options.diagnosisSelectId ID of the diagnosis dropdown (default: 'patient_diagnosis')
     * @param {string} options.whenNeededSelectId ID of the when needed dropdown (default: 'whenNeeded')
     * @param {string} options.scheduleDateTimeId ID of the scheduled date/time container (default: 'scheduleDateTime')
     * @param {string} options.otherDiagnosisInputId ID of the "Other" diagnosis text input (default: 'other_diagnosis_input')
     */
    function initDiagnosisHandler(options = {}) {
        const config = {
            diagnosisSelectId: options.diagnosisSelectId || 'patient_diagnosis',
            whenNeededSelectId: options.whenNeededSelectId || 'whenNeeded',
            scheduleDateTimeId: options.scheduleDateTimeId || 'scheduleDateTime',
            otherDiagnosisInputId: options.otherDiagnosisInputId || 'other_diagnosis_input'
        };

        const diagnosisSelect = document.getElementById(config.diagnosisSelectId);
        const whenNeededSelect = document.getElementById(config.whenNeededSelectId);
        const scheduleDateTimeDiv = document.getElementById(config.scheduleDateTimeId);
        let otherDiagnosisInput = document.getElementById(config.otherDiagnosisInputId);
        // If not found directly, try to get it from the container
        if (!otherDiagnosisInput) {
            const container = document.getElementById('other_diagnosis_container');
            if (container) {
                otherDiagnosisInput = container.querySelector('input');
            }
        }

        if (!diagnosisSelect || !whenNeededSelect) {
            console.warn('Diagnosis handler: Required elements not found');
            return;
        }

        /**
         * Handle diagnosis change
         */
        function handleDiagnosisChange() {
            const selectedOption = diagnosisSelect.options[diagnosisSelect.selectedIndex];
            const urgency = selectedOption ? selectedOption.getAttribute('data-urgency') : null;
            const diagnosisValue = diagnosisSelect.value;

            // Handle "Other" option - show text input
            if (diagnosisValue === 'Other') {
                showOtherDiagnosisInput();
            } else {
                hideOtherDiagnosisInput();
            }

            // Auto-set when_needed based on urgency
            if (urgency === 'urgent') {
                // Set to ASAP and disable the dropdown
                whenNeededSelect.value = 'ASAP';
                whenNeededSelect.disabled = true;
                whenNeededSelect.style.cursor = 'not-allowed';
                whenNeededSelect.style.opacity = '0.6';
                
                // Show scheduled date/time input even for ASAP
                if (scheduleDateTimeDiv) {
                    scheduleDateTimeDiv.classList.remove('d-none');
                    scheduleDateTimeDiv.style.opacity = 1;
                    const dateInput = scheduleDateTimeDiv.querySelector('input');
                    if (dateInput) {
                        dateInput.required = true;
                    }
                }
            } else if (diagnosisValue) {
                // Set to Scheduled and disable the dropdown
                whenNeededSelect.value = 'Scheduled';
                whenNeededSelect.disabled = true;
                whenNeededSelect.style.cursor = 'not-allowed';
                whenNeededSelect.style.opacity = '0.6';
                
                // Show scheduled date/time input since we set it to Scheduled
                if (scheduleDateTimeDiv) {
                    scheduleDateTimeDiv.classList.remove('d-none');
                    scheduleDateTimeDiv.style.opacity = 1;
                    const dateInput = scheduleDateTimeDiv.querySelector('input');
                    if (dateInput) {
                        dateInput.required = true;
                    }
                }
            } else {
                // Reset defaults when nothing is selected
                whenNeededSelect.value = '';
                whenNeededSelect.disabled = false;
                whenNeededSelect.style.cursor = 'pointer';
                whenNeededSelect.style.opacity = '1';

                if (scheduleDateTimeDiv) {
                    scheduleDateTimeDiv.style.opacity = 0;
                    setTimeout(() => {
                        scheduleDateTimeDiv.classList.add('d-none');
                        const dateInput = scheduleDateTimeDiv.querySelector('input');
                        if (dateInput) {
                            dateInput.required = false;
                            dateInput.value = '';
                        }
                    }, 200);
                }
            }
        }

        /**
         * Show "Other" diagnosis text input
         */
        function showOtherDiagnosisInput() {
            const container = document.getElementById('other_diagnosis_container');
            if (container) {
                container.classList.remove('d-none');
                container.style.display = 'block';
                if (otherDiagnosisInput) {
                    otherDiagnosisInput.required = true;
                } else {
                    const input = container.querySelector('input');
                    if (input) {
                        input.required = true;
                    }
                }
            }
        }

        /**
         * Hide "Other" diagnosis text input
         */
        function hideOtherDiagnosisInput() {
            const container = document.getElementById('other_diagnosis_container');
            if (container) {
                container.classList.add('d-none');
                container.style.display = 'none';
                if (otherDiagnosisInput) {
                    otherDiagnosisInput.required = false;
                    otherDiagnosisInput.value = '';
                } else {
                    const input = container.querySelector('input');
                    if (input) {
                        input.required = false;
                        input.value = '';
                    }
                }
            }
        }

        /**
         * Handle "Other" diagnosis input change
         */
        function handleOtherDiagnosisInput() {
            const otherValue = otherDiagnosisInput ? otherDiagnosisInput.value : '';
            // Update the diagnosis select value to include "Other" specification
            if (otherValue.trim()) {
                diagnosisSelect.setAttribute('data-other-value', otherValue);
            }
        }

        /**
         * Handle when needed change (for non-urgent cases)
         */
        function handleWhenNeededChange() {
            if (whenNeededSelect.disabled) {
                return; // Don't handle if disabled (urgent case)
            }

            // Show scheduled date/time for both ASAP and Scheduled
            if ((whenNeededSelect.value === 'Scheduled' || whenNeededSelect.value === 'ASAP') && scheduleDateTimeDiv) {
                scheduleDateTimeDiv.classList.remove('d-none');
                scheduleDateTimeDiv.style.opacity = 1;
                const dateInput = scheduleDateTimeDiv.querySelector('input');
                if (dateInput) {
                    dateInput.required = true;
                }
            } else if (scheduleDateTimeDiv) {
                scheduleDateTimeDiv.style.opacity = 0;
                setTimeout(() => {
                    scheduleDateTimeDiv.classList.add('d-none');
                    const dateInput = scheduleDateTimeDiv.querySelector('input');
                    if (dateInput) {
                        dateInput.required = false;
                        dateInput.value = '';
                    }
                }, 500);
            }
        }

        /**
         * Prepare form data for submission
         * Combines diagnosis select value with "Other" specification if applicable
         */
        function prepareDiagnosisForSubmission() {
            const diagnosisValue = diagnosisSelect.value;
            
            if (diagnosisValue === 'Other') {
                const otherValue = otherDiagnosisInput ? otherDiagnosisInput.value.trim() : '';
                if (otherValue) {
                    // Update the hidden field or form data with combined value
                    const combinedValue = `Other: ${otherValue}`;
                    diagnosisSelect.setAttribute('data-combined-value', combinedValue);
                    
                    // Also update the actual form field if it exists
                    const hiddenField = document.querySelector('input[name="patient_diagnosis"]');
                    if (hiddenField) {
                        hiddenField.value = combinedValue;
                    }
                }
            }
        }

        // Event listeners
        diagnosisSelect.addEventListener('change', handleDiagnosisChange);
        whenNeededSelect.addEventListener('change', handleWhenNeededChange);
        
        // Add event listener for "Other" diagnosis input if it exists
        if (otherDiagnosisInput) {
            otherDiagnosisInput.addEventListener('input', handleOtherDiagnosisInput);
        }

        // Handle form submission to combine "Other" diagnosis
        const form = diagnosisSelect.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                prepareDiagnosisForSubmission();
            });
        }

        // Initialize on page load if there's a pre-selected value
        if (diagnosisSelect.value) {
            handleDiagnosisChange();
        }

        // Expose public API
        return {
            handleDiagnosisChange,
            prepareDiagnosisForSubmission,
            getDiagnosisValue: function() {
                const diagnosisValue = diagnosisSelect.value;
                if (diagnosisValue === 'Other' && otherDiagnosisInput) {
                    const otherValue = otherDiagnosisInput.value.trim();
                    return otherValue ? `Other: ${otherValue}` : diagnosisValue;
                }
                return diagnosisValue;
            }
        };
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we're on a hospital request page
            const diagnosisSelect = document.getElementById('patient_diagnosis');
            if (diagnosisSelect) {
                initDiagnosisHandler();
            }
        });
    } else {
        // DOM already loaded
        const diagnosisSelect = document.getElementById('patient_diagnosis');
        if (diagnosisSelect) {
            initDiagnosisHandler();
        }
    }

    // Export for manual initialization if needed
    if (typeof window !== 'undefined') {
        window.HospitalRequestDiagnosisHandler = {
            init: initDiagnosisHandler
        };
    }
})();

