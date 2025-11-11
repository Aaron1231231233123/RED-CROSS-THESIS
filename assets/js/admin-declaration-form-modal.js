// Admin Declaration Form Modal JavaScript
// This file provides the admin-specific declaration form modal functionality
// It follows the same pattern as the staff declaration form but with admin-specific styling and behavior

// Function to show admin declaration form modal (called after admin screening form submission)
window.showAdminDeclarationFormModal = function(donorId) {
    console.log('Showing admin declaration form modal for donor ID:', donorId);
    
    // Show confirmation modal first (same as staff version)
    const confirmationModalHtml = `
        <div class="modal fade" id="adminScreeningToDeclarationConfirmationModal" tabindex="-1" aria-labelledby="adminScreeningToDeclarationConfirmationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 0.375rem 0.375rem 0 0;">
                        <h5 class="modal-title" id="adminScreeningToDeclarationConfirmationModalLabel">
                            <i class="fas fa-clipboard-check me-2"></i>
                            Admin Screening Submitted Successfully
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-check-circle text-success me-3" style="font-size: 2rem;"></i>
                            <div>
                                <h6 class="mb-1">Screening Form Completed</h6>
                                <p class="mb-0 text-muted">Admin screening data has been recorded successfully.</p>
                            </div>
                        </div>
                        <p class="mb-0">Please proceed to the declaration form to complete the donor registration process.</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;" onclick="proceedToAdminDeclarationForm('${donorId}')">
                            <i class="fas fa-arrow-right me-1"></i>Proceed to Declaration Form
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('adminScreeningToDeclarationConfirmationModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add the modal to the document
    document.body.insertAdjacentHTML('beforeend', confirmationModalHtml);
    
    // Show the confirmation modal
    const confirmationModal = new bootstrap.Modal(document.getElementById('adminScreeningToDeclarationConfirmationModal'));
    confirmationModal.show();
    
    // Add event listener to remove modal from DOM after it's hidden
    document.getElementById('adminScreeningToDeclarationConfirmationModal').addEventListener('hidden.bs.modal', function () {
        this.remove();
    });
};

// Function to proceed to admin declaration form after confirmation
window.proceedToAdminDeclarationForm = function(donorId) {
    console.log('Proceeding to admin declaration form for donor ID:', donorId);
    
    // Close confirmation modal
    const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('adminScreeningToDeclarationConfirmationModal'));
    if (confirmationModal) {
        confirmationModal.hide();
    }
    
    // Create admin declaration modal if it doesn't exist
    let adminDeclarationModal = document.getElementById('adminDeclarationFormModal');
    if (!adminDeclarationModal) {
        const modalHtml = `
            <div class="modal fade" id="adminDeclarationFormModal" tabindex="-1" aria-labelledby="adminDeclarationFormModalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-xl" style="max-width: 1200px; width: 95%;">
                    <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden;">
                        <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-bottom: none;">
                            <h5 class="modal-title" id="adminDeclarationFormModalLabel">
                                <i class="fas fa-file-contract me-2"></i>
                                Declaration Form - Admin
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="adminDeclarationFormModalContent" style="padding: 0; max-height: 80vh; overflow-y: auto;">
                            <!-- Content will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        adminDeclarationModal = document.getElementById('adminDeclarationFormModal');
    }
    
    const modalContent = document.getElementById('adminDeclarationFormModalContent');
    
    // Reset modal content to loading state
    modalContent.innerHTML = `
        <div class="d-flex justify-content-center align-items-center" style="min-height: 300px;">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 mb-0 text-muted">Loading Admin Declaration Form...</p>
            </div>
        </div>`;
    
    // Show the modal
    const declarationModal = new bootstrap.Modal(adminDeclarationModal);
    declarationModal.show();
    
    // Load the declaration form content
    fetch('../../src/views/forms/declaration-form-modal-content.php?donor_id=' + donorId)
        .then(response => {
            console.log('Admin declaration form response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            console.log('Admin declaration form content loaded successfully');
            modalContent.innerHTML = data;
            
            // Ensure print function is available globally for admin
            window.printAdminDeclaration = function() {
                console.log('Admin print function called');
                const printWindow = window.open('', '_blank');
                const content = document.querySelector('.declaration-header').outerHTML + 
                               document.querySelector('.donor-info').outerHTML + 
                               document.querySelector('.declaration-content').outerHTML + 
                               document.querySelector('.signature-section').outerHTML;
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Declaration Form - Philippine Red Cross (Admin)</title>
                        <style>
                            body { 
                                font-family: Arial, sans-serif; 
                                padding: 20px; 
                                line-height: 1.5;
                            }
                            .declaration-header { 
                                text-align: center; 
                                margin-bottom: 30px;
                                padding: 20px;
                                background: linear-gradient(135deg, #b22222 0%, #8b0000 100%);
                                color: white;
                                padding-bottom: 20px;
                            }
                            .declaration-header h2, .declaration-header h3 { 
                                color: white; 
                                margin: 5px 0;
                                text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
                                font-weight: bold;
                            }
                            .admin-badge {
                                background: rgba(255,255,255,0.2);
                                padding: 5px 15px;
                                border-radius: 20px;
                                font-size: 12px;
                                font-weight: bold;
                                margin-top: 10px;
                                display: inline-block;
                            }
                            .donor-info { 
                                background-color: #f8f9fa; 
                                padding: 20px; 
                                margin: 20px 0; 
                                border: 1px solid #ddd; 
                                border-radius: 8px;
                            }
                            .donor-info-row { 
                                display: flex; 
                                margin-bottom: 15px; 
                                gap: 20px; 
                                flex-wrap: wrap;
                            }
                            .donor-info-item { 
                                flex: 1; 
                                min-width: 200px;
                            }
                            .donor-info-label { 
                                font-weight: bold; 
                                font-size: 14px; 
                                color: #555; 
                                margin-bottom: 5px;
                            }
                            .donor-info-value { 
                                font-size: 16px; 
                                color: #333; 
                            }
                            .declaration-content { 
                                line-height: 1.8; 
                                margin: 30px 0; 
                                text-align: justify;
                            }
                            .declaration-content p { 
                                margin-bottom: 20px; 
                            }
                            .bold { 
                                font-weight: bold; 
                                color: #9c0000; 
                            }
                            .signature-section { 
                                margin-top: 40px; 
                                display: flex; 
                                justify-content: space-between; 
                                page-break-inside: avoid;
                            }
                            .signature-box { 
                                text-align: center; 
                                padding: 15px 0; 
                                border-top: 2px solid #333; 
                                width: 250px; 
                                font-weight: 500;
                            }
                            @media print {
                                body { margin: 0; }
                                .declaration-header { page-break-after: avoid; }
                                .signature-section { page-break-before: avoid; }
                            }
                        </style>
                    </head>
                    <body>${content}</body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => {
                    printWindow.print();
                }, 500);
            };
            
            // Ensure submit function is available globally for admin
            window.submitAdminDeclarationForm = function(event) {
                // Prevent default form submission immediately to keep modal open
                if (event) {
                    event.preventDefault();
                }
                
                const proceedSubmission = function() {
                    // Process the declaration form
                    const form = document.getElementById('modalDeclarationForm');
                    if (!form) {
                        if (window.customConfirm) {
                            window.customConfirm('Form not found. Please try again.', function() {});
                        } else {
                            alert('Form not found. Please try again.');
                        }
                        return;
                    }
                    
                    document.getElementById('modalDeclarationAction').value = 'complete';
                    
                    // Submit the form via AJAX
                    const formData = new FormData(form);
                    
                    // Include admin screening data if available
                    if (window.currentAdminScreeningData) {
                        formData.append('screening_data', JSON.stringify(window.currentAdminScreeningData));
                        formData.append('debug_log', 'Including admin screening data: ' + JSON.stringify(window.currentAdminScreeningData));
                    } else if (window.currentScreeningData) {
                        // Fallback to regular screening data
                        formData.append('screening_data', JSON.stringify(window.currentScreeningData));
                        formData.append('debug_log', 'Including screening data: ' + JSON.stringify(window.currentScreeningData));
                    } else {
                        formData.append('debug_log', 'No screening data available');
                    }
                    
                    formData.append('debug_log', 'Submitting admin declaration form data...');
                    
                    // Debug: Log what we're sending
                    const debugFormData = new FormData();
                    debugFormData.append('debug_log', 'Admin FormData contents:');
                    for (let [key, value] of formData.entries()) {
                        debugFormData.append('debug_log', '  ' + key + ': ' + value);
                    }
                    fetch('../../src/views/forms/admin-declaration-form-process.php', {
                        method: 'POST',
                        body: debugFormData
                    }).catch(() => {});
                    
                    fetch('../../src/views/forms/admin-declaration-form-process.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Close declaration form modal
                            const declarationModal = bootstrap.Modal.getInstance(document.getElementById('adminDeclarationFormModal'));
                            if (declarationModal) {
                                declarationModal.hide();
                            }
                            
                            // Show success modal with admin-specific message
                            if (window.showSuccessModal) {
                                showSuccessModal('Admin Registration Completed', 'The donor has been successfully registered and forwarded to the physician for physical examination.', { autoCloseMs: 2000, reloadOnClose: true });
                            } else {
                                // Fallback
                                alert('Admin Registration Completed: The donor has been successfully registered and forwarded to the physician for physical examination.');
                                window.location.reload();
                            }
                        } else {
                            // Show error modal
                            const msg = 'Failed to complete admin registration. ' + (data.message || 'Please try again.');
                            if (window.showErrorModal) {
                                showErrorModal('Admin Submission Failed', msg, { autoCloseMs: null, reloadOnClose: false });
                            } else {
                                alert(msg);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error submitting admin declaration form:', error);
                        
                        // Log error to server
                        const errorFormData = new FormData();
                        errorFormData.append('debug_log', 'Admin JavaScript Error: ' + error.message);
                        fetch('../../src/views/forms/admin-declaration-form-process.php', {
                            method: 'POST',
                            body: errorFormData
                        }).catch(() => {});
                        
                        const emsg = 'An error occurred while processing the admin form: ' + error.message;
                        if (window.showErrorModal) {
                            showErrorModal('Admin Submission Error', emsg, { autoCloseMs: null, reloadOnClose: false });
                        } else {
                            alert(emsg);
                        }
                    });
                };
                
                // Ask for explicit confirmation before proceeding
                const message = 'Are you sure you want to complete the admin registration?';
                if (window.customConfirm) {
                    window.customConfirm(message, proceedSubmission);
                } else {
                    if (confirm(message)) {
                        proceedSubmission();
                    }
                }
            };
            
            // Update the print button to use admin print function
            const printButton = document.querySelector('button[onclick="printDeclaration()"]');
            if (printButton) {
                printButton.setAttribute('onclick', 'printAdminDeclaration()');
                printButton.innerHTML = '<i class="fas fa-print"></i> Print Declaration (Admin)';
            }
            
            // Update the submit button to be a close button instead
            const submitButton = document.querySelector('button[onclick="submitDeclarationForm(event)"]');
            if (submitButton) {
                submitButton.setAttribute('onclick', '');
                submitButton.setAttribute('data-bs-dismiss', 'modal');
                submitButton.setAttribute('aria-label', 'Close');
                submitButton.className = 'modal-btn modal-btn-secondary';
                submitButton.innerHTML = '<i class="fas fa-times"></i> Close';
            }
            
            // Also update the admin declaration form button if it exists
            const adminSubmitButton = document.querySelector('button[onclick="submitAdminDeclarationForm(event)"]');
            if (adminSubmitButton) {
                adminSubmitButton.setAttribute('onclick', '');
                adminSubmitButton.setAttribute('data-bs-dismiss', 'modal');
                adminSubmitButton.setAttribute('aria-label', 'Close');
                adminSubmitButton.className = 'modal-btn modal-btn-secondary';
                adminSubmitButton.innerHTML = '<i class="fas fa-times"></i> Close';
            }
            
            // Add admin badge to the declaration header
            const declarationHeader = document.querySelector('.declaration-header');
            if (declarationHeader) {
                const adminBadge = document.createElement('div');
                adminBadge.className = 'admin-badge';
                adminBadge.innerHTML = '<i class="fas fa-user-shield me-1"></i>ADMIN PROCESSED';
                declarationHeader.appendChild(adminBadge);
            }
        })
        .catch(error => {
            console.error('Error loading admin declaration form:', error);
            modalContent.innerHTML = `
                <div class="alert alert-danger m-4" role="alert">
                    <h4 class="alert-heading">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error Loading Declaration Form
                    </h4>
                    <p>An error occurred while loading the declaration form. Please try again.</p>
                    <hr>
                    <p class="mb-0">Error details: ${error.message}</p>
                </div>
            `;
        });
};

// Make functions globally available
window.showAdminDeclarationFormModal = window.showAdminDeclarationFormModal;
window.proceedToAdminDeclarationForm = window.proceedToAdminDeclarationForm;
