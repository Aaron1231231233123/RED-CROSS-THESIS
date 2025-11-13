<?php
/**
 * Mobile Credentials Display Modal
 * 
 * This modal displays the auto-generated mobile app credentials
 * for admin-registered donors after successful registration.
 */

// Start session if not already started and headers haven't been sent
if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
} elseif (session_status() == PHP_SESSION_NONE && headers_sent()) {
    // Headers already sent, cannot start session - log warning but continue
    error_log("Mobile Credentials Modal - Warning: Cannot start session, headers already sent");
}

// Debug: Log that modal file was included
error_log("Mobile Credentials Modal - File included/loaded");

// Check if mobile credentials are available (from session or cookies)
$hasSessionCredentials = false;
if (isset($_SESSION['mobile_account_generated']) && $_SESSION['mobile_account_generated']) {
    if (!empty($_SESSION['mobile_credentials']['email']) && !empty($_SESSION['mobile_credentials']['password'])) {
        $hasSessionCredentials = true;
    } else {
        // Stale flag without data; reset it
        $_SESSION['mobile_account_generated'] = false;
    }
}
$hasCookieCredentials = isset($_COOKIE['mobile_account_generated']) && $_COOKIE['mobile_account_generated'] === 'true';

// Check if credentials have already been acknowledged/shown
$credentialsAlreadyShown = isset($_SESSION['mobile_credentials_shown']) && $_SESSION['mobile_credentials_shown'] === true;
$credentialsShownInCookie = isset($_COOKIE['mobile_credentials_shown']) && $_COOKIE['mobile_credentials_shown'] === 'true';

// Determine if we're on the declaration form (credentials should always show there)
// Check both PHP_SELF and REQUEST_URI to catch all cases
$currentScript = $_SERVER['PHP_SELF'] ?? '';
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$isDeclarationForm = (strpos($currentScript, 'declaration-form-modal.php') !== false) || 
                     (strpos($currentUri, 'declaration-form-modal.php') !== false);

// Debug logging
error_log("Mobile Credentials Modal - isDeclarationForm=" . ($isDeclarationForm ? 'true' : 'false') . 
          ", hasSession=" . ($hasSessionCredentials ? 'true' : 'false') . 
          ", hasCookie=" . ($hasCookieCredentials ? 'true' : 'false') . 
          ", script=" . $currentScript . 
          ", uri=" . $currentUri);

// On declaration form, always show if credentials exist (ignore "shown" flag)
// On other pages (dashboards), only show if credentials exist AND haven't been shown yet
// Detect if we are on any dashboard page; never auto-show there (only show when explicitly triggered)
$isDashboardPage = (strpos($currentScript, '/Dashboards/') !== false) || (strpos($currentUri, '/Dashboards/') !== false);

if ($isDeclarationForm) {
    $showCredentials = $hasSessionCredentials || $hasCookieCredentials;
    error_log("Mobile Credentials Modal - Declaration form: showCredentials=" . ($showCredentials ? 'true' : 'false'));
} elseif ($isDashboardPage) {
    // Always render HTML but do NOT auto-open on dashboards; JS can open explicitly when needed
    $showCredentials = false;
    error_log("Mobile Credentials Modal - Dashboard page detected: forcing showCredentials=false to avoid auto-popup");
} else {
    $showCredentials = ($hasSessionCredentials || $hasCookieCredentials) && !$credentialsAlreadyShown && !$credentialsShownInCookie;
    error_log("Mobile Credentials Modal - Other page: showCredentials=" . ($showCredentials ? 'true' : 'false'));
}

// NOTE: Do NOT set the "shown" flag here - it will be set when the modal is actually displayed
// This allows the modal to show on refresh until the user actually sees and closes it

// Get credentials from session first, then fall back to cookies
// If on declaration form and no credentials in session/cookies, try to get from database
$email = '';
$password = '';

if ($hasSessionCredentials && isset($_SESSION['mobile_credentials'])) {
    $email = $_SESSION['mobile_credentials']['email'] ?? '';
    $password = $_SESSION['mobile_credentials']['password'] ?? '';
    if (empty($email) || empty($password)) {
        error_log("Mobile Credentials Modal - session credentials empty, resetting flags.");
        $email = '';
        $password = '';
        unset($_SESSION['mobile_credentials']);
        $_SESSION['mobile_account_generated'] = false;
        $hasSessionCredentials = false;
    }
}

if (!$hasSessionCredentials && $hasCookieCredentials) {
    $email = $_COOKIE['mobile_email'] ?? '';
    $password = $_COOKIE['mobile_password'] ?? '';
    if (empty($email) || empty($password)) {
        $hasCookieCredentials = false;
        $email = '';
        $password = '';
    }
}

if (!$hasSessionCredentials && !$hasCookieCredentials && $isDeclarationForm && isset($_SESSION['donor_id'])) {
    // On declaration form, try to retrieve credentials from database if not in session/cookies
    // This allows showing credentials when viewing an existing donor's declaration form
    try {
        require_once '../../../assets/conn/db_conn.php';
        $donor_id = $_SESSION['donor_id'];
        
        // Retrieve donor data to get email and check if mobile account exists
        $ch = curl_init(SUPABASE_URL . '/rest/v1/donor_form?donor_id=eq.' . $donor_id . '&select=email,surname,birthdate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_API_KEY,
            'Authorization: Bearer ' . SUPABASE_API_KEY
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $donorData = json_decode($response, true);
            if (is_array($donorData) && !empty($donorData) && isset($donorData[0])) {
                $donorInfo = $donorData[0];
                $email = $donorInfo['email'] ?? '';
                
                // If email exists, regenerate password using the same algorithm (surname + birth year)
                if (!empty($email) && !empty($donorInfo['surname']) && !empty($donorInfo['birthdate'])) {
                    // Regenerate password using same format as mobile-account-generator
                    $surname = $donorInfo['surname'];
                    $birth_year = date('Y', strtotime($donorInfo['birthdate']));
                    $clean_surname = preg_replace('/[^a-zA-Z]/', '', $surname);
                    $clean_surname = ucfirst(strtolower($clean_surname));
                    $password = $clean_surname . $birth_year;
                    
                    // Found credentials - set flags so modal will show
                    $hasSessionCredentials = true;
                    $hasCookieCredentials = true;
                    $showCredentials = true;
                    error_log("Mobile Credentials Modal - Retrieved and regenerated credentials from database for donor_id: " . $donor_id);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Mobile Credentials Modal - Error retrieving credentials from database: " . $e->getMessage());
    }
}

$isReviewer = isset($_SESSION['role_id'], $_SESSION['user_staff_roles'])
    && $_SESSION['role_id'] == 3
    && $_SESSION['user_staff_roles'] === 'reviewer';

$donorName = $_SESSION['donor_registered_name'] ?? $_COOKIE['donor_name'] ?? 'Donor';
$modalDonorId = isset($_SESSION['donor_id']) ? (string)$_SESSION['donor_id'] : '';

// Final guard: if we still do not have actual credential strings, do not show the modal
$hasCredentialValues = !empty($email) && !empty($password);
if (!$hasCredentialValues) {
    $showCredentials = false;
}

// Debug: Log what we have
error_log("Mobile Credentials Modal - Final check: showCredentials=" . ($showCredentials ? 'true' : 'false') . 
          ", email=" . (!empty($email) ? 'set' : 'empty') . 
          ", password=" . (!empty($password) ? 'set' : 'empty') . 
          ", donorName=" . $donorName);
?>

<!-- Mobile Credentials Modal - Always render HTML, show/hide based on credentials -->
<!-- DEBUG: showCredentials=<?php echo $showCredentials ? 'true' : 'false'; ?>, hasSession=<?php echo $hasSessionCredentials ? 'true' : 'false'; ?>, hasCookie=<?php echo $hasCookieCredentials ? 'true' : 'false'; ?> -->
<div class="modal" id="mobileCredentialsModal" tabindex="-1" aria-labelledby="mobileCredentialsModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" data-auto-show="<?php echo ($showCredentials && $hasCredentialValues) ? 'true' : 'false'; ?>" data-donor-id="<?php echo htmlspecialchars($modalDonorId); ?>" style="<?php echo ($showCredentials && $hasCredentialValues) ? '' : 'display: none;'; ?>">
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
                    <strong>Congratulations!</strong> A mobile app account has been automatically created for <span id="mobileCredentialsDonorName"><?php echo htmlspecialchars($donorName); ?></span>.
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
                            <strong>URL:</strong> <code>localhost/Mobile-Web-Based-App-System/mobile-app/</code>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    <?php if ($isReviewer): ?>
                        Click return to continue working from the reviewer dashboard.
                    <?php else: ?>
                        If the donor is ready for the next step based on the medical history interview,
                        click and proceed with Initial Screening.
                    <?php endif; ?>
                </div>
                <?php if ($isReviewer): ?>
                <button type="button" class="btn btn-danger" onclick="returnToReviewerDashboard()">
                    <i class="fas fa-undo me-2"></i>
                    Return
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-danger" onclick="launchInitialScreening()">
                    <i class="fas fa-arrow-right me-2"></i>
                    Initial Screening
                </button>
                <?php endif; ?>
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
if (typeof window.IS_REVIEWER === 'undefined') {
    window.IS_REVIEWER = <?php echo $isReviewer ? 'true' : 'false'; ?>;
} else if (!window.IS_REVIEWER) {
    window.IS_REVIEWER = <?php echo $isReviewer ? 'true' : 'false'; ?>;
}
var IS_REVIEWER = window.IS_REVIEWER;

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

// Auto-show modal when page loads if credentials are available
window.addEventListener('load', function() {
    console.log('[Mobile Credentials] Page loaded, checking for modal');
    
    setTimeout(function() {
        const modal = document.getElementById('mobileCredentialsModal');
        if (modal) {
            console.log('[Mobile Credentials] Modal element found');
            const shouldAutoShow = modal.getAttribute('data-auto-show') === 'true';
            // Check if modal should be shown based on the modal's existence and visibility
            const emailValue = document.getElementById('mobileEmail')?.value || '';
            const passwordValue = document.getElementById('mobilePassword')?.value || '';
            
            console.log('[Mobile Credentials] Email value:', emailValue);
            console.log('[Mobile Credentials] Password value:', passwordValue ? '***' : 'empty');
            
            if (shouldAutoShow && emailValue && passwordValue) {
                console.log('[Mobile Credentials] Auto-opening modal with credentials');
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            } else {
                console.log('[Mobile Credentials] Auto-show disabled or no credentials to display');
            }
        } else {
            console.log('[Mobile Credentials] Modal element not found');
        }
    }, 500);
});

// Remove backdrop immediately when modal is hidden and clear credentials
const modalElement = document.getElementById('mobileCredentialsModal');
if (modalElement) {
    modalElement.addEventListener('hidden.bs.modal', function() {
        // Skip cleanup if we're launching screening (it will handle its own cleanup)
        if (window.__launchingScreening) {
            console.log('[Mobile Credentials] Skipping cleanup - launching screening modal');
            window.__launchingScreening = false;
            return;
        }

        // Remove all modal backdrops immediately
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        // Mark as shown when modal is hidden (user closed it)
        // This prevents it from showing again on refresh
        const apiPath = window.location.pathname.includes('/Dashboards/') 
            ? '../api/clear-mobile-credentials.php' 
            : '../../api/clear-mobile-credentials.php';
        
        // Mark as shown (but don't clear credentials yet - user might want to see them again)
        fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'mark_as_shown' })
        }).then(() => {
            console.log('[Mobile Credentials] Credentials marked as shown after modal close');
        }).catch((error) => {
            console.error('[Mobile Credentials] Error marking as shown:', error);
        });
        
        console.log('[Mobile Credentials] Backdrop removed instantly');

        if (IS_REVIEWER) {
            setTimeout(() => {
                window.location.reload();
            }, 200);
        }
    });
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

function clearMobileCredentials(onComplete) {
    const apiPath = window.location.pathname.includes('/Dashboards/') 
        ? '../api/clear-mobile-credentials.php' 
        : '../../api/clear-mobile-credentials.php';

    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_as_shown' })
    }).then(() => {
        return fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'clear_credentials' })
        });
    }).then(() => {
        console.log('[Mobile Credentials] Credentials marked as shown and cleared');
        if (typeof onComplete === 'function') {
            onComplete();
        }
    }).catch((error) => {
        console.error('[Mobile Credentials] Error clearing credentials:', error);
        if (typeof onComplete === 'function') {
            onComplete();
        }
    });
}

function returnToReviewerDashboard() {
    const modal = document.getElementById('mobileCredentialsModal');

    clearMobileCredentials(() => {
        if (modal) {
            const instance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
            instance.hide();
        } else {
            window.location.reload();
        }
    });
}

function launchInitialScreening() {
    if (IS_REVIEWER) {
        returnToReviewerDashboard();
        return;
    }

    const modal = document.getElementById('mobileCredentialsModal');
    const donorId = modal?.getAttribute('data-donor-id') || window.__latestRegisteredDonorId || '';

    if (!donorId) {
        console.warn('[Mobile Credentials] No donor_id available for initial screening; redirecting to dashboard instead.');
        return goBackToDashboard();
    }

    const screeningModal = document.getElementById('adminScreeningFormModal');
    if (!screeningModal || typeof window.openAdminScreeningModal !== 'function') {
        alert('Initial screening form is not ready yet. Please refresh the page and try again.');
        return;
    }

    console.log('[Mobile Credentials] Launching initial screening for donor_id:', donorId);

    // Set flag to prevent credentials modal cleanup from interfering
    window.__launchingScreening = true;
    
    // Store dashboard URL for reload after screening submission
    // Use the same logic as goBackToDashboard to determine which dashboard to reload
    const redirectUrl = '<?php 
        $redirect_url = '';
        
        // Get user staff role for role-based redirects (for staff users)
        $user_staff_roles = '';
        if (isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3) {
            $user_id = $_SESSION['user_id'];
            $url = SUPABASE_URL . "/rest/v1/user_roles?select=user_staff_roles&user_id=eq." . urlencode($user_id);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ));
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if (!empty($data) && isset($data[0]['user_staff_roles'])) {
                    $user_staff_roles = strtolower(trim($data[0]['user_staff_roles']));
                }
            }
        }
        
        // For staff users, use role-based redirect
        if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3 && !empty($user_staff_roles)) {
            switch($user_staff_roles) {
                case 'reviewer':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-medical-history-submissions.php';
                    break;
                case 'interviewer':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-donor-submission.php';
                    break;
                case 'physician':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-physical-submission.php';
                    break;
                case 'phlebotomist':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-blood-collection-submission.php';
                    break;
                default:
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-donor-submission.php';
                    break;
            }
        } elseif (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
            // For admin users (role_id 1), use admin dashboard
            // Prioritize session referrer, but default to admin dashboard
            if (isset($_SESSION['declaration_form_referrer']) && stripos($_SESSION['declaration_form_referrer'], 'dashboard') !== false) {
                $redirect_url = $_SESSION['declaration_form_referrer'];
            } elseif (isset($_SESSION['donor_form_referrer']) && stripos($_SESSION['donor_form_referrer'], 'dashboard') !== false) {
                $redirect_url = $_SESSION['donor_form_referrer'];
            } elseif (isset($_SESSION['post_registration_redirect']) && stripos($_SESSION['post_registration_redirect'], 'dashboard') !== false) {
                $redirect_url = $_SESSION['post_registration_redirect'];
            } else {
                // Default admin dashboard
                $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-Inventory-System-list-of-donations.php';
            }
        } else {
            // Fallback for other roles
            if (isset($_SESSION['declaration_form_referrer'])) {
                $redirect_url = $_SESSION['declaration_form_referrer'];
            } elseif (isset($_SESSION['donor_form_referrer'])) {
                $redirect_url = $_SESSION['donor_form_referrer'];
            } elseif (isset($_SESSION['post_registration_redirect'])) {
                $redirect_url = $_SESSION['post_registration_redirect'];
            } else {
                $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-Inventory-System-list-of-donations.php';
            }
        }
        echo $redirect_url;
    ?>';
    window.__registrationDashboardUrl = redirectUrl;

    // Close credentials modal first
    if (modal) {
        const instance = bootstrap.Modal.getInstance(modal);
        if (instance) {
            instance.hide();
        } else {
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    }

    // Clean up all backdrops immediately
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());

    // Clear credentials in background (don't wait for it)
    clearMobileCredentials(() => {
        console.log('[Mobile Credentials] Credentials cleared');
    });

    // Wait for modal to fully close and backdrop to be removed before opening screening modal
    setTimeout(() => {
        // Double-check backdrop cleanup
        const remainingBackdrops = document.querySelectorAll('.modal-backdrop');
        remainingBackdrops.forEach(backdrop => backdrop.remove());
        
        // Ensure body classes are clean
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';

        try {
            console.log('[Mobile Credentials] Opening admin screening modal for donor:', donorId);
            window.openAdminScreeningModal({ donor_id: donorId });

            // Verify modal opened successfully after a short delay
            setTimeout(() => {
                const isOpen = screeningModal.classList.contains('show') ||
                               screeningModal.style.display !== 'none';
                if (!isOpen) {
                    console.warn('[Mobile Credentials] Screening modal may not have opened properly - re-showing credentials modal.');
                    const credentialsInstance = bootstrap.Modal.getOrCreateInstance(modal);
                    credentialsInstance?.show();
                }
                window.__launchingScreening = false;
            }, 250);
        } catch (error) {
            console.error('[Mobile Credentials] Error opening screening modal:', error);
            window.__launchingScreening = false;
            alert('Error opening initial screening form. Please try again.');
            const credentialsInstance = bootstrap.Modal.getOrCreateInstance(modal);
            credentialsInstance?.show();
        }
    }, 400); // Increased delay to ensure proper cleanup
}

// Function to go back to dashboard
function goBackToDashboard() {
    // Get the redirect URL from PHP session - use the same referrer system as declaration form
    // Also handle role-based redirects for staff users
    const redirectUrl = '<?php 
        $redirect_url = '';
        
        // Get user staff role for role-based redirects (for staff users)
        $user_staff_roles = '';
        if (isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3) {
            $user_id = $_SESSION['user_id'];
            $url = SUPABASE_URL . "/rest/v1/user_roles?select=user_staff_roles&user_id=eq." . urlencode($user_id);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'apikey: ' . SUPABASE_API_KEY,
                'Authorization: Bearer ' . SUPABASE_API_KEY,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ));
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if (!empty($data) && isset($data[0]['user_staff_roles'])) {
                    $user_staff_roles = strtolower(trim($data[0]['user_staff_roles']));
                }
            }
        }
        
        // For staff users, use role-based redirect
        if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3 && !empty($user_staff_roles)) {
            switch($user_staff_roles) {
                case 'reviewer':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-medical-history-submissions.php';
                    break;
                case 'interviewer':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-donor-submission.php';
                    break;
                case 'physician':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-physical-submission.php';
                    break;
                case 'phlebotomist':
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-blood-collection-submission.php';
                    break;
                default:
                    $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-staff-donor-submission.php';
                    break;
            }
        } elseif (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
            // For admin users (role_id 1), use admin dashboard
            // Prioritize session referrer, but default to admin dashboard
            if (isset($_SESSION['declaration_form_referrer']) && stripos($_SESSION['declaration_form_referrer'], 'dashboard') !== false) {
                $redirect_url = $_SESSION['declaration_form_referrer'];
            } elseif (isset($_SESSION['donor_form_referrer']) && stripos($_SESSION['donor_form_referrer'], 'dashboard') !== false) {
                $redirect_url = $_SESSION['donor_form_referrer'];
            } elseif (isset($_SESSION['post_registration_redirect']) && stripos($_SESSION['post_registration_redirect'], 'dashboard') !== false) {
                $redirect_url = $_SESSION['post_registration_redirect'];
            } else {
                // Default admin dashboard
                $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-Inventory-System.php';
            }
        } else {
            // Fallback for other roles
            if (isset($_SESSION['declaration_form_referrer'])) {
                $redirect_url = $_SESSION['declaration_form_referrer'];
            } elseif (isset($_SESSION['donor_form_referrer'])) {
                $redirect_url = $_SESSION['donor_form_referrer'];
            } elseif (isset($_SESSION['post_registration_redirect'])) {
                $redirect_url = $_SESSION['post_registration_redirect'];
            } else {
                $redirect_url = '/RED-CROSS-THESIS/public/Dashboards/dashboard-Inventory-System.php';
            }
        }
        echo $redirect_url;
    ?>';
    
    // Add success parameter
    const dashboardUrl = redirectUrl + (redirectUrl.includes('?') ? '&' : '?') + 'donor_registered=true';
    
    clearMobileCredentials(() => {
        window.location.href = dashboardUrl;
    });
}

// Function to clear credentials when modal is closed
function clearCredentialsOnClose() {
    // Use absolute path that works from both dashboard and modal contexts
    const apiPath = window.location.pathname.includes('/Dashboards/') 
        ? '../api/clear-mobile-credentials.php' 
        : '../../api/clear-mobile-credentials.php';
    
    // Mark as shown first, then clear credentials
    // This prevents modal from showing on dashboards
    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_as_shown' })
    }).then(() => {
        // Now clear credentials
        return fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'clear_credentials' })
        });
    }).then(() => {
        console.log('[Mobile Credentials] Credentials marked as shown and cleared on close');
    }).catch((error) => {
        console.error('[Mobile Credentials] Error:', error);
    });
}

// Show modal when page loads if credentials are available
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Mobile Credentials] DOMContentLoaded - Checking for modal...');
    const modalElement = document.getElementById('mobileCredentialsModal');
    
    if (!modalElement) {
        console.error('[Mobile Credentials] Modal element NOT found in DOM!');
        return;
    }
    
    console.log('[Mobile Credentials] Modal element found');
    
    <?php if ($showCredentials): ?>
    console.log('[Mobile Credentials] Credentials exist - showing modal');
    console.log('[Mobile Credentials] hasSession=', <?php echo $hasSessionCredentials ? 'true' : 'false'; ?>);
    console.log('[Mobile Credentials] hasCookie=', <?php echo $hasCookieCredentials ? 'true' : 'false'; ?>);
    console.log('[Mobile Credentials] email=', '<?php echo !empty($email) ? 'set' : 'empty'; ?>');
    console.log('[Mobile Credentials] password=', '<?php echo !empty($password) ? 'set' : 'empty'; ?>');
    
    try {
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        
        // Show the modal
        modal.show();
        console.log('[Mobile Credentials] Modal.show() called successfully');
        
        // Verify modal is actually shown
        setTimeout(function() {
            if (modalElement.classList.contains('show')) {
                console.log('[Mobile Credentials] Modal is now visible');
            } else {
                console.error('[Mobile Credentials] Modal.show() was called but modal is not visible!');
            }
        }, 500);
    } catch (error) {
        console.error('[Mobile Credentials] Error showing modal:', error);
    }
    
    // NOTE: Do NOT mark as shown when modal is displayed
    // Only mark as shown when user closes the modal (handled in hidden.bs.modal event)
    // This allows the modal to show on refresh until the user actually closes it
    <?php else: ?>
    console.log('[Mobile Credentials] Modal will NOT be shown - showCredentials is false');
    console.log('[Mobile Credentials] hasSessionCredentials:', <?php echo $hasSessionCredentials ? 'true' : 'false'; ?>);
    console.log('[Mobile Credentials] hasCookieCredentials:', <?php echo $hasCookieCredentials ? 'true' : 'false'; ?>);
    console.log('[Mobile Credentials] isDeclarationForm:', <?php echo $isDeclarationForm ? 'true' : 'false'; ?>);
    console.log('[Mobile Credentials] credentialsAlreadyShown:', <?php echo $credentialsAlreadyShown ? 'true' : 'false'; ?>);
    console.log('[Mobile Credentials] credentialsShownInCookie:', <?php echo $credentialsShownInCookie ? 'true' : 'false'; ?>);
    <?php endif; ?>
});
</script>
