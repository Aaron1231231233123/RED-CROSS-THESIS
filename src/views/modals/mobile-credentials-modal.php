<?php
/**
 * Mobile Credentials Display Modal
 * 
 * This modal displays the auto-generated mobile app credentials
 * for admin-registered donors after successful registration.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if mobile credentials are available
$showCredentials = isset($_SESSION['mobile_account_generated']) && $_SESSION['mobile_account_generated'];
$email = $_SESSION['mobile_credentials']['email'] ?? '';
$password = $_SESSION['mobile_credentials']['password'] ?? '';
$donorName = $_SESSION['donor_registered_name'] ?? 'Donor';
?>

<!-- Mobile Credentials Modal -->
<div class="modal fade" id="mobileCredentialsModal" tabindex="-1" aria-labelledby="mobileCredentialsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="mobileCredentialsModalLabel">
                    <i class="fas fa-mobile-alt me-2"></i>
                    Mobile App Account Created Successfully
                </h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Congratulations!</strong> A mobile app account has been automatically created for <?php echo htmlspecialchars($donorName); ?>.
                </div>
                
                <div class="credentials-container">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-key me-2"></i>
                        Mobile App Login Credentials
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="credential-item">
                                <label class="form-label fw-bold">Email Address:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="mobileEmail" value="<?php echo htmlspecialchars($email); ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('mobileEmail')">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="credential-item">
                                <label class="form-label fw-bold">Password:</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="mobilePassword" value="<?php echo htmlspecialchars($password); ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">
                                        <i class="fas fa-eye" id="passwordToggleIcon"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('mobilePassword')">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Important:</strong> Please provide these credentials to the donor so they can access the mobile app immediately. 
                            The email is already verified and no additional verification is required.
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6 class="text-secondary">Mobile App Access:</h6>
                        <p class="text-muted mb-0">
                            <i class="fas fa-globe me-2"></i>
                            <strong>URL:</strong> <code>http://localhost/Mobile-Web-Based-App-System/mobile-app/</code>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.credentials-container {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}

.credential-item {
    margin-bottom: 15px;
}

.credential-item label {
    color: #495057;
    margin-bottom: 5px;
}

.input-group .btn {
    border-color: #ced4da;
}

.input-group .btn:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

#mobileEmail, #mobilePassword {
    background-color: #fff;
    font-family: 'Courier New', monospace;
    font-weight: 500;
}

.alert-info {
    border-left: 4px solid #17a2b8;
}

.alert-success {
    border-left: 4px solid #28a745;
}
</style>

<script>
// Function to copy text to clipboard
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.value;
    const button = event.target.closest('button');
    
    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showCopySuccess(button);
        }).catch(function(err) {
            console.error('Clipboard API failed:', err);
            fallbackCopyToClipboard(text, button);
        });
    } else {
        // Use fallback method
        fallbackCopyToClipboard(text, button);
    }
}

// Fallback copy method that works in all browsers
function fallbackCopyToClipboard(text, button) {
    // Create a temporary textarea element
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    
    try {
        textArea.focus();
        textArea.select();
        const successful = document.execCommand('copy');
        
        if (successful) {
            showCopySuccess(button);
        } else {
            showCopyError(text, button);
        }
    } catch (err) {
        console.error('Fallback copy failed:', err);
        showCopyError(text, button);
    } finally {
        document.body.removeChild(textArea);
    }
}

// Show success feedback
function showCopySuccess(button) {
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    button.classList.remove('btn-outline-secondary');
    button.classList.add('btn-success');
    
    // Show success message
    showSuccessMessage('Copied to clipboard!');
    
    // Reset button after 2 seconds
    setTimeout(function() {
        button.innerHTML = originalHTML;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}

// Show success message
function showSuccessMessage(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.style.minWidth = '300px';
    alert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        <strong>Success!</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    // Auto-dismiss after 3 seconds
    setTimeout(function() {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 3000);
}

// Show error feedback
function showCopyError(text, button) {
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
    button.classList.remove('btn-outline-secondary');
    button.classList.add('btn-warning');
    
    // Show a more user-friendly message
    const modal = document.createElement('div');
    modal.className = 'alert alert-info alert-dismissible fade show position-fixed';
    modal.style.top = '20px';
    modal.style.right = '20px';
    modal.style.zIndex = '9999';
    modal.innerHTML = `
        <i class="fas fa-info-circle me-2"></i>
        <strong>Copy manually:</strong> ${text}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(modal);
    
    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        if (modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
    }, 5000);
    
    // Reset button after 3 seconds
    setTimeout(function() {
        button.innerHTML = originalHTML;
        button.classList.remove('btn-warning');
        button.classList.add('btn-outline-secondary');
    }, 3000);
}

// Function to toggle password visibility
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('mobilePassword');
    const toggleIcon = document.getElementById('passwordToggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

// Function to go back to dashboard
function goBackToDashboard() {
    // Get the redirect URL from PHP session - use the same referrer system as declaration form
    const redirectUrl = '<?php 
        $redirect_url = '';
        if (isset($_SESSION['declaration_form_referrer'])) {
            $redirect_url = $_SESSION['declaration_form_referrer'];
        } elseif (isset($_SESSION['donor_form_referrer'])) {
            $redirect_url = $_SESSION['donor_form_referrer'];
        } elseif (isset($_SESSION['post_registration_redirect'])) {
            $redirect_url = $_SESSION['post_registration_redirect'];
        } else {
            $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-Inventory-System.php';
        }
        echo $redirect_url;
    ?>';
    
    // Add success parameter
    const dashboardUrl = redirectUrl + (redirectUrl.includes('?') ? '&' : '?') + 'donor_registered=true';
    
    // Clear mobile credentials from session
    fetch('../../api/clear-mobile-credentials.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'clear_credentials' })
    }).then(() => {
        window.location.href = dashboardUrl;
    }).catch(() => {
        // Fallback redirect even if clearing fails
        window.location.href = dashboardUrl;
    });
}

// Show modal when page loads if credentials are available
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($showCredentials): ?>
    const modal = new bootstrap.Modal(document.getElementById('mobileCredentialsModal'));
    modal.show();
    <?php endif; ?>
});
</script>
