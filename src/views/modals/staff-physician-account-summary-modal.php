<?php
/**
 * Staff Physician Account Summary Modal
 * This file contains the HTML structure for viewing physical examination results in read-only mode
 * Shows only the review section without stage indicators or action buttons
 */
?>

<!-- Staff Physician Account Summary Modal -->
<div class="modal fade" id="staffPhysicianAccountSummaryModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-lg" style="max-width:1200px; width:95%; z-index: 1066;">
        <div class="modal-content" style="border: none; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; z-index: 1067; position: relative;">
            <!-- Navigation Sidebar -->
            <div class="modal-nav-sidebar" id="summaryNavSidebar">
                <div class="modal-nav-header">
                    <i class="fas fa-user-md modal-nav-header-icon"></i>
                    <div class="modal-nav-header-text">
                        <div class="modal-nav-header-title">PHYSICIAN</div>
                        <div class="modal-nav-header-subtitle">Workflow</div>
                    </div>
                </div>
                <div class="modal-nav-items">
                    <div class="modal-nav-item" id="navSummaryMedicalHistory" data-nav="medical-history">
                        <i class="fas fa-file-medical modal-nav-item-icon"></i>
                        <span>Medical History</span>
                    </div>
                    <div class="modal-nav-item" id="navSummaryInitialScreening" data-nav="initial-screening">
                        <i class="fas fa-clipboard-list modal-nav-item-icon"></i>
                        <span>Initial Screening</span>
                    </div>
                    <div class="modal-nav-item active" id="navSummaryPhysicalExam" data-nav="physical-examination-summary">
                        <i class="fas fa-stethoscope modal-nav-item-icon"></i>
                        <span>Physical Examination</span>
                    </div>
                    <div class="modal-nav-item" id="navSummaryDonorProfile" data-nav="donor-profile">
                        <i class="fas fa-user modal-nav-item-icon"></i>
                        <span>Donor Profile</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-content-with-nav" style="margin-left: 180px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-bottom: none;">
                    <h5 class="modal-title"><i class="fas fa-stethoscope me-2"></i>Physical Examination Summary</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- No Progress Indicator - Direct to Summary -->
                
                <div class="modal-body" style="padding: 30px;">
                <div class="examination-report">
                    <!-- Header Section -->
                    <div class="report-header">
                        <div class="report-title">
                            <h5>Physical Examination Report</h5>
                            <div class="report-meta">
                                <span class="report-date"><?php echo date('F j, Y'); ?></span>
                                <span class="report-physician">Physician: <span id="summary-physician-name">-</span></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vital Signs Section -->
                    <div class="report-section">
                        <div class="section-header">
                            <i class="fas fa-heartbeat"></i>
                            <span>Vital Signs</span>
                        </div>
                        <div class="section-content">
                            <div class="vital-signs-grid">
                                <div class="vital-item">
                                    <span class="vital-label">Blood Pressure:</span>
                                    <span class="vital-value" id="summary-view-blood-pressure">-</span>
                                </div>
                                <div class="vital-item">
                                    <span class="vital-label">Pulse Rate:</span>
                                    <span class="vital-value" id="summary-view-pulse-rate">-</span>
                                    <span class="vital-unit">BPM</span>
                                </div>
                                <div class="vital-item">
                                    <span class="vital-label">Temperature:</span>
                                    <span class="vital-value" id="summary-view-body-temp">-</span>
                                    <span class="vital-unit">°C</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Physical Examination Section -->
                    <div class="report-section">
                        <div class="section-header">
                            <i class="fas fa-user-md"></i>
                            <span>Physical Examination Findings</span>
                        </div>
                        <div class="section-content">
                            <div class="examination-findings">
                                <div class="finding-row">
                                    <span class="finding-label">General Appearance:</span>
                                    <span class="finding-value" id="summary-view-gen-appearance">-</span>
                                </div>
                                <div class="finding-row">
                                    <span class="finding-label">Skin:</span>
                                    <span class="finding-value" id="summary-view-skin">-</span>
                                </div>
                                <div class="finding-row">
                                    <span class="finding-label">HEENT:</span>
                                    <span class="finding-value" id="summary-view-heent">-</span>
                                </div>
                                <div class="finding-row">
                                    <span class="finding-label">Heart and Lungs:</span>
                                    <span class="finding-value" id="summary-view-heart-lungs">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assessment & Conclusion -->
                    <div class="report-section">
                        <div class="section-header">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Assessment & Conclusion</span>
                        </div>
                        <div class="section-content">
                            <div class="assessment-content">
                                <div class="assessment-result">
                                    <span class="result-label">Medical Assessment:</span>
                                    <span class="result-value" id="summary-view-remarks">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Signature Section -->
                    <div class="report-signature">
                        <div class="signature-content">
                            <div class="signature-line">
                                <span>Examining Physician</span>
                                <span class="physician-name" id="summary-view-physician-signature">
                                    <?php 
                                        // Get the logged-in user's name from the users table
                                        if (isset($_SESSION['user_id'])) {
                                            $user_id = $_SESSION['user_id'];
                                            
                                            // Use the unified API function if available, otherwise fallback to direct CURL
                                            if (function_exists('makeSupabaseApiCall')) {
                                                $user_data = makeSupabaseApiCall(
                                                    'users',
                                                    ['first_name', 'surname'],
                                                    ['user_id' => 'eq.' . $user_id]
                                                );
                                            } else {
                                                // Fallback to direct CURL if unified function not available
                                                $ch = curl_init(SUPABASE_URL . '/rest/v1/users?select=first_name,surname&user_id=eq.' . $user_id);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                                    'apikey: ' . SUPABASE_API_KEY,
                                                    'Authorization: Bearer ' . SUPABASE_API_KEY
                                                ]);
                                                
                                                $response = curl_exec($ch);
                                                curl_close($ch);
                                                $user_data = json_decode($response, true) ?: [];
                                            }
                                            
                                            if (!empty($user_data) && is_array($user_data)) {
                                                $user = $user_data[0];
                                                $first_name = isset($user['first_name']) ? htmlspecialchars($user['first_name']) : '';
                                                $surname = isset($user['surname']) ? htmlspecialchars($user['surname']) : '';
                                                
                                                if ($first_name && $surname) {
                                                    echo $first_name . ' ' . $surname;
                                                } elseif ($first_name) {
                                                    echo $first_name;
                                                } elseif ($surname) {
                                                    echo $surname;
                                                } else {
                                                    echo 'Physician';
                                                }
                                            } else {
                                                echo 'Physician';
                                            }
                        } else {
                                            echo 'Physician';
                                        }
                                    ?>
                                </span>
                            </div>
                            <div class="signature-note">
                                This examination was conducted in accordance with Philippine Red Cross standards and protocols.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                <!-- No Action Buttons - Only Close -->
                <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #dee2e6;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Staff Physician Account Summary Modal Styles */
#staffPhysicianAccountSummaryModal {
    z-index: 1065 !important;
}

#staffPhysicianAccountSummaryModal .modal-dialog {
    z-index: 1066 !important;
}

#staffPhysicianAccountSummaryModal .modal-content {
    z-index: 1067 !important;
    position: relative;
}

#staffPhysicianAccountSummaryModal .examination-report {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 25px;
}

#staffPhysicianAccountSummaryModal .report-header {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

#staffPhysicianAccountSummaryModal .report-title h5 {
    color: #2c3e50;
    margin-bottom: 10px;
    font-weight: 600;
}

#staffPhysicianAccountSummaryModal .report-meta {
    display: flex;
    gap: 20px;
    color: #6c757d;
    font-size: 0.9rem;
}

#staffPhysicianAccountSummaryModal .report-section {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

#staffPhysicianAccountSummaryModal .section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #b22222;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

#staffPhysicianAccountSummaryModal .section-header i {
    font-size: 1.2rem;
}

#staffPhysicianAccountSummaryModal .vital-signs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

#staffPhysicianAccountSummaryModal .vital-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

#staffPhysicianAccountSummaryModal .vital-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
}

#staffPhysicianAccountSummaryModal .vital-value {
    color: #2c3e50;
    font-size: 1.3rem;
    font-weight: 600;
}

#staffPhysicianAccountSummaryModal .vital-unit {
    color: #6c757d;
    font-size: 0.85rem;
    margin-left: 5px;
}

#staffPhysicianAccountSummaryModal .examination-findings {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

#staffPhysicianAccountSummaryModal .finding-row {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 15px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

#staffPhysicianAccountSummaryModal .finding-label {
    color: #6c757d;
    font-weight: 500;
}

#staffPhysicianAccountSummaryModal .finding-value {
    color: #2c3e50;
    font-weight: 400;
}

#staffPhysicianAccountSummaryModal .assessment-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

#staffPhysicianAccountSummaryModal .assessment-result,
#staffPhysicianAccountSummaryModal .assessment-collection {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

#staffPhysicianAccountSummaryModal .result-label,
#staffPhysicianAccountSummaryModal .collection-label {
    color: #6c757d;
    font-weight: 500;
    min-width: 180px;
}

#staffPhysicianAccountSummaryModal .result-value,
#staffPhysicianAccountSummaryModal .collection-value {
    color: #b22222;
    font-weight: 600;
    font-size: 1.1rem;
}

#staffPhysicianAccountSummaryModal .report-signature {
    background: white;
    border-radius: 8px;
    padding: 25px;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

#staffPhysicianAccountSummaryModal .signature-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 15px;
    margin-bottom: 15px;
    border-bottom: 2px solid #2c3e50;
}

#staffPhysicianAccountSummaryModal .signature-line span:first-child {
    color: #6c757d;
    font-size: 0.9rem;
}

#staffPhysicianAccountSummaryModal .physician-name {
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
}

#staffPhysicianAccountSummaryModal .signature-note {
    color: #6c757d;
    font-size: 0.85rem;
    font-style: italic;
    text-align: center;
}
</style>

<script>
// Staff Physician Account Summary Modal - Return to Donor Profile on Close
(function() {
    const modalEl = document.getElementById('staffPhysicianAccountSummaryModal');
    if (!modalEl) return;
    
    // When summary modal closes, return to donor profile modal (unless sidebar navigation is active)
    const onSummaryHidden = () => {
        try {
            // Don't reopen if we're in a success/approval flow, sidebar navigation, or explicitly prevented
            if (window.__suppressReturnToProfile || window.__peSuccessActive || window.__sidebarNavigationActive || window.__preventDonorProfileOpen) {
                window.__suppressReturnToProfile = false;
                return;
            }
            
            const dpEl = document.getElementById('donorProfileModal');
            if (dpEl) {
                // Clear any hide prevention flags
                try { window.allowDonorProfileHide = false; } catch(_) {}
                
                const dp = bootstrap.Modal.getOrCreateInstance(dpEl, { backdrop: 'static', keyboard: false });
                dp.show();
                
                // Refresh with last context if available
                setTimeout(() => {
                    try {
                        if (window.lastDonorProfileContext && typeof refreshDonorProfileModal === 'function') {
                            refreshDonorProfileModal(window.lastDonorProfileContext);
                        }
                    } catch(e) {
                        console.error('[SUMMARY MODAL] Error refreshing donor profile:', e);
                    }
                }, 100);
            }
            
            // Clean backdrops only if no other modals are open
            setTimeout(() => {
                try {
                    const anyOpen = document.querySelector('.modal.show');
                    if (!anyOpen) {
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }
                } catch(_) {}
            }, 50);
        } catch(e) {
            console.error('[SUMMARY MODAL] Error in onSummaryHidden handler:', e);
        }
    };
    
    // Attach the listener
    modalEl.addEventListener('hidden.bs.modal', onSummaryHidden);
})();
</script>

