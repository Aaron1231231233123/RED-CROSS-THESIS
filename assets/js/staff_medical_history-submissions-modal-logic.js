        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchLoading = document.getElementById('searchLoading');
            const searchCategory = document.getElementById('searchCategory');
            const donorTableBody = document.getElementById('donorTableBody');
            // Cache modal instances for better performance
            const deferralStatusModalEl = document.getElementById('deferralStatusModal');
            const deferralStatusModal = deferralStatusModalEl ? new bootstrap.Modal(deferralStatusModalEl) : null;
            const deferralStatusContent = document.getElementById('deferralStatusContent');
            const stageNoticeModalEl = document.getElementById('stageNoticeModal');
            const stageNoticeModal = stageNoticeModalEl ? new bootstrap.Modal(stageNoticeModalEl) : null;
            const stageNoticeBody = document.getElementById('stageNoticeBody');
            const stageNoticeViewBtn = document.getElementById('stageNoticeViewBtn');
            const returningInfoModalEl = document.getElementById('returningInfoModal');
            const returningInfoModal = returningInfoModalEl ? new bootstrap.Modal(returningInfoModalEl) : null;
            const returningInfoViewBtn = document.getElementById('returningInfoViewBtn');
            const markReturningReviewBtn = document.getElementById('markReturningReviewBtn');
            const markReviewFromMain = document.getElementById('markReviewFromMain');
            
            let currentDonorId = null;
            let allowProcessing = false;
            let modalContextType = 'new_medical'; // 'new_medical' | 'new_other_stage' | 'returning' | 'other'
            let currentStage = null; // 'medical_review' | 'screening_form' | 'physical_examination' | 'blood_collection'
            
            // Backdrop cleanup utility to prevent stuck overlays (expose globally)
            window.cleanupModalBackdrops = function() {
                try {
                    document.body.classList.remove('modal-open');
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(el => {
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    });
                } catch (e) {}
            }
            
            // Search functionality now handled by external JS files:
            // - search_account_medical_history.js
            // - filter_search_account_medical_history.js
            
            // Use event delegation for row clicks to work with dynamically loaded search results
            donorTableBody.addEventListener('click', function(e) {
                // Find the closest tr with clickable-row class
                const row = e.target.closest('tr.clickable-row');
                if (!row) return;
                
                const donorId = row.getAttribute('data-donor-id');
                const stageAttr = row.getAttribute('data-stage');
                const donorTypeLabel = row.getAttribute('data-donor-type');
                if (!donorId) return;
                    
                    // Set global variables for modal context
                    window.currentDonorId = donorId;
                    window.currentDonorType = donorTypeLabel || 'New';
                    window.currentDonorStage = stageAttr || 'Medical';
                    
                        currentDonorId = donorId;
                    const lowerType = (donorTypeLabel || '').toLowerCase();
                    const isNew = lowerType.startsWith('new');
                    const isReturning = lowerType.startsWith('returning');
                    // Derive stage from donor type text to avoid mismatches
                    const typeText = lowerType;
                    let stageFromType = 'unknown';
                    if (typeText.includes('medical')) stageFromType = 'medical_review';
                    else if (typeText.includes('screening')) stageFromType = 'screening_form';
                    else if (typeText.includes('physical')) stageFromType = 'physical_examination';
                    else if (typeText.includes('collection') || typeText.includes('completed')) stageFromType = 'blood_collection';
                    const effectiveStage = stageFromType !== 'unknown' ? stageFromType : stageAttr;
                    currentStage = effectiveStage;
                    // Allow processing for new donors in medical_review OR any donor with needs_review=true
                    allowProcessing = (isNew && (effectiveStage === 'medical_review')) || 
                                    (effectiveStage === 'medical_review' && currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review);
                    // Determine modal context type
                    if (allowProcessing) {
                        modalContextType = 'new_medical'; // Can process medical history
                    } else if (isNew) {
                        modalContextType = 'new_other_stage';
                    } else if (isReturning) {
                        modalContextType = 'returning';
                    } else {
                        modalContextType = 'other';
                    }
                    window.modalContextType = modalContextType;
                    
                    if (!allowProcessing && !isReturning) {
                        // Show read-only notice modal
                        const stageTitleMap = {
                            'screening_form': 'Screening Stage',
                            'physical_examination': 'Physical Examination Stage',
                            'blood_collection': 'Blood Collection Stage'
                        };
                        const friendlyStage = stageTitleMap[effectiveStage] || 'Different Stage';
                        const newOrReturningNote = isNew
                            ? `This record is <strong>New</strong> but not in the Medical stage (<strong>${friendlyStage}</strong>).`
                            : `This record is <strong>Returning</strong>. This page is dedicated to processing <strong>New (Medical)</strong> only.`;
                        stageNoticeBody.innerHTML = `
                            <p>${newOrReturningNote}</p>
                            <p><strong>Note:</strong> Medical history processing on this page is available only for <strong>New (Medical)</strong> records.</p>
                            <div class="alert alert-info mb-0">
                                <div><strong>Donor type:</strong> ${donorTypeLabel || ''}</div>
                                <div class="small text-muted">You can view read-only details for reference.</div>
                            </div>`;
                        if (stageNoticeModal) stageNoticeModal.show();
                        
                        // Bind view details to open the existing details modal without processing
                        if (stageNoticeViewBtn) {
                            stageNoticeViewBtn.onclick = () => {
                            if (stageNoticeModal) stageNoticeModal.hide();
                            // Prepare details modal in read-only mode
                            deferralStatusContent.innerHTML = `
                                <div class=\"d-flex justify-content-center\">\n                                    <div class=\"spinner-border text-primary\" role=\"status\">\n                                        <span class=\"visually-hidden\">Loading...</span>\n                                    </div>\n                                </div>`;
                            
                            // Hide proceed button in read-only mode
                            const proceedButton = getProceedButton();
                            if (proceedButton && proceedButton.style) {
                                proceedButton.style.display = 'none';
                                proceedButton.textContent = 'Proceed to Medical History';
                            }
                            if (deferralStatusModal) deferralStatusModal.show();
                            fetchDonorStatusInfo(donorId);
                        };
                        }
                        return;
                    }
                    
                    if (isReturning) {
                        // Check if returning donor has needs_review=true
                        const hasNeedsReview = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review;
                        
                        if (effectiveStage === 'medical_review' || hasNeedsReview) {
                            // Returning (Medical) OR Returning with needs_review: go straight to details with Review available
                        deferralStatusContent.innerHTML = `
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                    </div>
                            </div>`;
                            const proceedButton = getProceedButton();
                            if (proceedButton && proceedButton.style) {
                                proceedButton.style.display = 'inline-block';
                                proceedButton.textContent = 'Proceed to Medical History';
                            }
                            if (markReviewFromMain) markReviewFromMain.style.display = 'none';
                        if (deferralStatusModal) deferralStatusModal.show();
                        fetchDonorStatusInfo(donorId);
                            return;
                        }
                        // Returning but not Medical and no needs_review: directly show donor modal
                        deferralStatusContent.innerHTML = `
                            <div class="d-flex justify-content-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>`;
                        const proceedButton = getProceedButton();
                        if (proceedButton && proceedButton.style) proceedButton.style.display = 'none';
                        if (deferralStatusModal) deferralStatusModal.show();
                        fetchDonorStatusInfo(donorId);
                        // Mark for review handler
                        if (markReturningReviewBtn) {
                            markReturningReviewBtn.onclick = () => {
                                const confirmMsg = 'This action will mark the donor for Medical Review and move them back to the medical stage for reassessment. Do you want to proceed?';
                                if (window.customConfirm) {
                                    window.customConfirm(confirmMsg, function() {
                                        fetch('../../assets/php_func/update_needs_review.php', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json' },
                                            body: new URLSearchParams({ donor_id: donorId })
                                        })
                                        .then(r => r.json())
                                        .then(res => {
                                            if (res && res.success) {
                                                returningInfoModal.hide();
                                                // Silent success + refresh without opening another modal
                                                // Show centered success modal (auto-closes, no buttons)
                                                try {
                                                    const existing = document.getElementById('successAutoModal');
                                                    if (existing) existing.remove();
                                                    const successHTML = `
                                                        <div id="successAutoModal" style="
                                                            position: fixed;
                                                            top: 0;
                                                            left: 0;
                                                            width: 100%;
                                                            height: 100%;
                                                            background: rgba(0,0,0,0.5);
                                                            z-index: 99999;
                                                            display: flex;
                                                            align-items: center;
                                                            justify-content: center;
                                                        ">
                                                            <div style="
                                                                background: #ffffff;
                                                                border-radius: 10px;
                                                                max-width: 520px;
                                                                width: 90%;
                                                                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                                                                overflow: hidden;
                                                            ">
                                                                <div style="
                                                                    background: #9c0000;
                                                                    color: white;
                                                                    padding: 14px 18px;
                                                                    font-weight: 700;
                                                                ">Marked</div>
                                                                <div style="padding: 22px;">
                                                                    <p style="margin: 0;">The donor is medically cleared for donation.</p>
                                                                </div>
                                                            </div>
                                                        </div>`;
                                                    document.body.insertAdjacentHTML('beforeend', successHTML);
                                                } catch(_) {}
                                                setTimeout(() => { 
                                                    const m = document.getElementById('successAutoModal');
                                                    if (m) m.remove();
                                                    window.location.href = window.location.pathname + '?page=1'; 
                                                }, 1800);
                                                const row = document.querySelector(`tr[data-donor-id="${donorId}"]`);
                                                if (row) {
                                                    const donorTypeCell = row.querySelector('td:nth-child(6)');
                                                    if (donorTypeCell && donorTypeCell.textContent.toLowerCase().includes('returning')) {
                                                        donorTypeCell.textContent = 'Returning (Medical)';
                                                        row.setAttribute('data-donor-type', 'Returning (Medical)');
                                                    }
                                                }
                                            } else {
                                                window.customConfirm('Failed to mark for review.', function() {});
                                            }
                                        })
                                        .catch(() => {
                                            window.customConfirm('Failed to mark for review.', function() {});
                                        });
                                    });
                                }
                            };
                        }
                        // Enable main modal mark button only for returning
                        if (markReviewFromMain) {
                            // Don't force display here - let button control logic handle it
                            markReviewFromMain.onclick = () => {
                                const confirmMsg = 'This action will mark the donor for Medical Review and return them to the medical stage for reassessment. Do you want to proceed?';
                                if (window.customConfirm) {
                                    window.customConfirm(confirmMsg, function() {
                                        fetch('../../assets/php_func/update_needs_review.php', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json' },
                                            body: new URLSearchParams({ donor_id: donorId })
                                        })
                                        .then(r => r.json())
                                        .then(res => {
                                            if (res && res.success) {
                                                // Silent success + refresh without opening another modal
                                                // Show centered success modal (auto-closes, no buttons)
                                                try {
                                                    const existing = document.getElementById('successAutoModal');
                                                    if (existing) existing.remove();
                                                    const successHTML = `
                                                        <div id="successAutoModal" style="
                                                            position: fixed;
                                                            top: 0;
                                                            left: 0;
                                                            width: 100%;
                                                            height: 100%;
                                                            background: rgba(0,0,0,0.5);
                                                            z-index: 99999;
                                                            display: flex;
                                                            align-items: center;
                                                            justify-content: center;
                                                        ">
                                                            <div style="
                                                                background: #ffffff;
                                                                border-radius: 10px;
                                                                max-width: 520px;
                                                                width: 90%;
                                                                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                                                                overflow: hidden;
                                                            ">
                                                                <div style="
                                                                    background: #9c0000;
                                                                    color: white;
                                                                    padding: 14px 18px;
                                                                    font-weight: 700;
                                                                ">Marked</div>
                                                                <div style="padding: 22px;">
                                                                    <p style="margin: 0;">The donor is medically cleared for donation.</p>
                                                                </div>
                                                            </div>
                                                        </div>`;
                                                    document.body.insertAdjacentHTML('beforeend', successHTML);
                                                } catch(_) {}
                                                setTimeout(() => { 
                                                    const m = document.getElementById('successAutoModal');
                                                    if (m) m.remove();
                                                    window.location.href = window.location.pathname + '?page=1'; 
                                                }, 1800);
                                                const row = document.querySelector(`tr[data-donor-id="${donorId}"]`);
                                                if (row) {
                                                    const donorTypeCell = row.querySelector('td:nth-child(6)');
                                                    if (donorTypeCell && donorTypeCell.textContent.toLowerCase().includes('returning')) {
                                                        donorTypeCell.textContent = 'Returning (Medical)';
                                                        row.setAttribute('data-donor-type', 'Returning (Medical)');
                                                    }
                                                    const dateCell = row.querySelector('td:nth-child(2)');
                                                    if (dateCell && res.updated_at) {
                                                        const d = new Date(res.updated_at);
                                                        const options = { year: 'numeric', month: 'long', day: 'numeric' };
                                                        dateCell.textContent = d.toLocaleDateString('en-US', options);
                                                    }
                                                }
                                            } else {
                                                window.customConfirm('Failed to mark for review.', function() {});
                                            }
                                        })
                                        .catch(() => {
                                            window.customConfirm('Failed to mark for review.', function() {});
                                        });
                                    });
                                }
                            };
                        }
                        return;
                    }
                    
                    // Allow processing: show details and keep proceed button visible
                    deferralStatusContent.innerHTML = `
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>`;
                    
                    const proceedButton = getProceedButton();
                    if (proceedButton && proceedButton.style) {
                        proceedButton.style.display = 'inline-block';
                        proceedButton.textContent = 'Proceed to Medical History';
                    }
                    // Hide mark button for non-returning/new-medical flow
                    if (markReviewFromMain) markReviewFromMain.style.display = 'none';
                    if (deferralStatusModal) deferralStatusModal.show();
                    fetchDonorStatusInfo(donorId);
            });
            
            // Function to fetch donor status information
            // OPTIMIZED: Single unified endpoint with parallel backend processing for instant data loading
            function fetchDonorStatusInfo(donorId) {
                // Fetch all data with one request - backend handles parallel API calls
                fetch('../../assets/php_func/fetch_donor_complete_info_staff-medical-history.php?donor_id=' + donorId)
                    .then(r => r.json())
                    .then(response => {
                        if (response && response.success && response.data) {
                            // Extract the data and deferral info from unified response
                            const donorData = { success: true, data: response.data };
                            const deferralData = response.deferral || null;
                    // Display immediately with all data ready
                                displayDonorInfo(donorData, deferralData);
                        } else {
                            deferralStatusContent.innerHTML = `<div class="alert alert-danger">Failed to load donor information</div>`;
                        }
                            })
                            .catch(error => {
                    deferralStatusContent.innerHTML = `<div class="alert alert-danger">Error loading donor information: ${error.message}</div>`;
                    });
            }
            
            // Function to display donor and deferral information (exposed globally)
            // Accepts either a full API response { success, data } or the donor object directly
            window.displayDonorInfo = function(donorData, deferralData) {
                let donorInfoHTML = '';
                const safe = (v) => v || 'N/A';
                
                // Helper function to get blood type from eligibility records
                const getBloodTypeFromEligibility = (donor) => {
                    if (!donor || !donor.eligibility) return null;
                    
                    const eligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : [donor.eligibility];
                    
                    // Get the most recent eligibility record with blood type
                    for (let i = eligibilityRecords.length - 1; i >= 0; i--) {
                        const record = eligibilityRecords[i];
                        if (record && record.blood_type) {
                            return record.blood_type;
                        }
                    }
                    
                    return null;
                };
                
                // Store donor data globally for eye button access
                window.currentDonorData = donorData && donorData.data ? donorData.data : donorData;
                
                // Debug logging
                
                // Check if we have donor data, regardless of success field
                const donor = (donorData && donorData.data) ? donorData.data : donorData;
                if (donor && (typeof donor === 'object')) {
                    const fullName = `${safe(donor.surname)}, ${safe(donor.first_name)} ${safe(donor.middle_name)}`.trim();
                    const currentStatus = (() => {
                        // Get donor type from the row data
                        const donorType = window.currentDonorType || 'New';
                        const donorStage = window.currentDonorStage || 'Medical';
                        
                        if (donorType === 'New') {
                            if (donorStage === 'Medical') return 'Medical';
                            if (donorStage === 'Screening') return 'Screening';
                            if (donorStage === 'Physical') return 'Physical';
                            if (donorStage === 'Collection') return 'Collection';
                            return 'Medical'; // Default
                        } else if (donorType === 'Returning') {
                            if (donorStage === 'Medical') return 'Medical';
                            if (donorStage === 'Screening') return 'Screening';
                            if (donorStage === 'Physical') return 'Physical';
                            if (donorStage === 'Collection') return 'Collection';
                            return 'Medical'; // Default
                        }
                        return 'Medical'; // Default fallback
                    })();
                    
                     // Donor Information Header (Clean Design - Match Physical Exam Modal)
                        donorInfoHTML += `
                        <div class="mb-3">
                             <div class="d-flex justify-content-between align-items-start">
                                 <div class="flex-grow-1">
                                     <div class="text-muted small mb-1">
                                         <i class="fas fa-calendar-alt me-1"></i>
                                         Current Status: ${currentStatus}
                                         ${(() => {
                                             if (donor.eligibility && donor.eligibility.length > 0) {
                                                 const latestEligibility = donor.eligibility[donor.eligibility.length - 1];
                                                 const status = String(latestEligibility.status || '').toLowerCase();
                                                 const startDate = latestEligibility.start_date ? new Date(latestEligibility.start_date) : null;
                                                 const endDate = latestEligibility.end_date ? new Date(latestEligibility.end_date) : null;
                                                 const today = new Date();
                                                 
                                                 function calculateRemainingDays() {
                                                     if (status === 'approved' && startDate) {
                                                         const threeMonthsLater = new Date(startDate);
                                                         threeMonthsLater.setMonth(threeMonthsLater.getMonth() + 3);
                                                         const endOfDay = new Date(threeMonthsLater);
                                                         endOfDay.setHours(23, 59, 59, 999);
                                                         return Math.ceil((endOfDay - today) / (1000 * 60 * 60 * 24));
                                                     } else if (endDate) {
                                                         const endOfDay = new Date(endDate);
                                                         endOfDay.setHours(23, 59, 59, 999);
                                                         return Math.ceil((endOfDay - today) / (1000 * 60 * 60 * 24));
                                                     }
                                                     return null;
                                                 }
                                                 
                                                 const remainingDays = calculateRemainingDays();
                                                 if (remainingDays !== null && remainingDays > 0) {
                                                     // Color based on eligibility status
                                                     let color = '#17a2b8'; // Default blue
                                                     if (status === 'refused') {
                                                         color = '#dc3545'; // Red
                                                     } else if (status === 'deferred' || status === 'temporary_deferred') {
                                                         color = '#ffc107'; // Yellow
                                                     } else if (status === 'approved' || status === 'eligible') {
                                                         color = '#28a745'; // Green
                                                     }
                                                     return ` • <span style="font-weight: bold; color: ${color};">${remainingDays} days left</span>`;
                                                 }
                                             }
                                             return '';
                                         })()}
                            </div>
                                     <h4 class="mb-1" style="color:#b22222; font-weight:700;">
                                         ${fullName}
                                     </h4>
                                     <div class="text-muted fw-medium">
                                         <i class="fas fa-user me-1"></i>
                                         ${safe(donor.age)}${donor.sex ? ', ' + donor.sex : ''}
                            </div>
                                 </div>
                                 <div class="text-end">
                                     <div class="mb-1">
                                         <div class="fw-bold text-dark mb-1">
                                             <i class="fas fa-id-card me-1"></i>
                                             Donor ID: ${safe(donor.prc_donor_number || 'N/A')}
                                         </div>
                                         <div class="badge fs-6 px-3 py-2" style="background-color: #b22222; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; min-width: 80px;">
                                             <div style="font-size: 1.3rem; font-weight: 700; line-height: 1;">${safe(getBloodTypeFromEligibility(donor) || 'N/A')}</div>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <hr class="my-2" style="border-color: #b22222; opacity: 0.3;"/>
                            </div>`;
                        
                     // Donor Information Section (Match Physical Exam Modal Style)
                            donorInfoHTML += `
                        <div class="mb-3">
                             <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Donor Information</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                     <label class="form-label fw-semibold">Birthdate</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.birthdate)}</div>
                                    </div>
                                <div class="col-md-6">
                                     <label class="form-label fw-semibold">Civil Status</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.civil_status)}</div>
                                            </div>
                                 <div class="col-md-12">
                                     <label class="form-label fw-semibold">Address</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.permanent_address)}</div>
                                        </div>
                                 <div class="col-md-4">
                                     <label class="form-label fw-semibold">Nationality</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.nationality)}</div>
                                            </div>
                                 <div class="col-md-4">
                                     <label class="form-label fw-semibold">Mobile Number</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.mobile || donor.telephone)}</div>
                                            </div>
                                 <div class="col-md-4">
                                     <label class="form-label fw-semibold">Occupation</label>
                                     <div class="form-control-plaintext bg-light p-2 rounded">${safe(donor.occupation)}</div>
                                        </div>
                                    </div>
                                </div>`;
                    
                    
                    // Determine if donor is New; if so, hide history sections
                    const __elig = Array.isArray(donor.eligibility) ? donor.eligibility : (donor.eligibility ? [donor.eligibility] : []);
                    const isNewDonor = ((window.currentDonorType || '').toLowerCase().startsWith('new')) || __elig.length === 0;
                    if (!isNewDonor) {
                    // Physical Assessment Table (based on eligibility table - show all records) - FIRST
                    const eligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : (donor.eligibility ? [donor.eligibility] : []);
                    let assessmentRows = '';
                    
                    if (eligibilityRecords.length > 0) {
                        eligibilityRecords.forEach((eligibility, index) => {
                            // Map eligibility data to match the image format
                            const examDate = formatDate(eligibility.start_date || eligibility.created_at || donor.latest_submission);
                            
                            // Vital Signs - assess actual values against normal ranges
                            let vitalSigns = 'Normal';
                            if (eligibility.blood_pressure && eligibility.pulse_rate && eligibility.body_temp) {
                                // Check blood pressure (normal: 90-140/60-90)
                                const bp = eligibility.blood_pressure;
                                const bpMatch = bp.match(/(\d+)\/(\d+)/);
                                if (bpMatch) {
                                    const systolic = parseInt(bpMatch[1]);
                                    const diastolic = parseInt(bpMatch[2]);
                                    if (systolic < 90 || systolic > 140 || diastolic < 60 || diastolic > 90) {
                                        vitalSigns = 'Abnormal';
                                    }
                                }
                                
                                // Check pulse rate (normal: 60-100 bpm)
                                const pulse = parseInt(eligibility.pulse_rate);
                                if (pulse < 60 || pulse > 100) {
                                    vitalSigns = 'Abnormal';
                                }
                                
                                // Check temperature (normal: 36.1-37.2°C or 97-99°F)
                                const temp = parseFloat(eligibility.body_temp);
                                if (temp < 36.1 || temp > 37.2) {
                                    vitalSigns = 'Abnormal';
                                }
                            } else {
                                vitalSigns = 'Incomplete';
                            }
                            
                            // Hematology - assess based on collection success and medical reasons
                            let hematology = 'Pass';
                            if (eligibility.collection_successful === false) {
                                hematology = 'Fail';
                            } else if (eligibility.disapproval_reason) {
                                const reason = eligibility.disapproval_reason.toLowerCase();
                                if (reason.includes('hemoglobin') || reason.includes('hematocrit') || 
                                    reason.includes('blood count') || reason.includes('anemia') ||
                                    reason.includes('low iron') || reason.includes('blood disorder')) {
                                    hematology = 'Fail';
                                }
                            }
                            
                            // Fitness Result - map from eligibility status
                            let fitnessResult = 'Eligible';
                            if (eligibility.status === 'deferred' || eligibility.status === 'temporary_deferred') {
                                fitnessResult = 'Deferred';
                            } else if (eligibility.status === 'eligible') {
                                fitnessResult = 'Eligible';
                            }
                            
                            // Remarks - show Approved if both medical_history_id and screening_form_id exist
                            let remarks = 'Pending';
                            if (eligibility.medical_history_id && eligibility.screening_id) {
                                remarks = 'Approved';
                            }
                            
                            // Get physician name from physical_examination table
                            let physician = '-';
                            if (eligibility.physical_examination && eligibility.physical_examination.physician) {
                                physician = eligibility.physical_examination.physician;
                            }
                            
                            assessmentRows += `
                                 <tr>
                                     <td class="text-center">${safe(examDate)}</td>
                                     <td class="text-center">${safe(vitalSigns)}</td>
                                     <td class="text-center">${safe(hematology)}</td>
                                     <td class="text-center">${safe(physician)}</td>
                                     <td class="text-center">${safe(fitnessResult)}</td>
                                     <td class="text-center">${safe(remarks)}</td>
                                     <td class="text-center">
                                         <button type="button" class="btn btn-sm btn-outline-primary" onclick="showPhysicalExaminationModal('${eligibility.eligibility_id}')" title="View Physical Examination Results">
                                             <i class="fas fa-eye"></i>
                                         </button>
                                     </td>
                                </tr>`;
                            });
                    }
                    
                    if (!assessmentRows) {
                        assessmentRows = `<tr><td colspan="7" class="text-center text-muted">No physical assessment recorded</td></tr>`;
                    }
                            donorInfoHTML += `
                        <div class="mb-3">
                             <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Physical Assessment</h6>
                                        <div class="table-responsive">
                                 <table class="table table-sm table-bordered mb-0" style="border-radius: 10px; overflow: hidden;">
                                     <thead style="background: #b22222 !important; color: white !important;">
                                         <tr>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Examination Date</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Vital Signs</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Hematology</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Physician</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Fitness Result</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Remarks</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Action</th>
                                                    </tr>
                                                </thead>
                                     <tbody id="returningAssessmentRows">${assessmentRows}</tbody>
                                            </table>
                                    </div>
                                </div>`;
                    
                    // Donation History Table (based on eligibility table - show all records) - SECOND
                    const donationEligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : (donor.eligibility ? [donor.eligibility] : []);
                    let donationRows = '';
                    
                    // Add a new empty row at the TOP if needs_review is true (for pending review)
                    if (donor.needs_review === true || donor.needs_review === 'true' || donor.needs_review === 1) {
                         donationRows += `
                             <tr>
                                 <td class="text-center">-</td>
                                 <td class="text-center">-</td>
                                 <td class="text-center">-</td>
                                 <td class="text-center"><span class="text-warning">Pending</span></td>
                            </tr>`;
                    }
                    
                    if (donationEligibilityRecords.length > 0) {
                        donationEligibilityRecords.forEach((el, index) => {
                            // Only show records that have actual donation data
                            if (el.start_date || el.created_at) {
                                // Determine medical history status with color coding
                                let medicalStatus = 'Pending';
                                let statusClass = 'text-warning';
                                
                                // If medical_history_id exists, show Successful
                                if (el.medical_history_id) {
                                    medicalStatus = 'Successful';
                                    statusClass = 'text-success';
                                }
                                // Default fallback
                                else {
                                    medicalStatus = 'Pending';
                                    statusClass = 'text-warning';
                                }
                                
                                 donationRows += `
                                     <tr>
                                         <td class="text-center">${safe(formatDate(el.start_date || el.created_at))}</td>
                                         <td class="text-center">${safe(el.registration_channel || 'System')}</td>
                                         <td class="text-center">${safe(formatDate(el.end_date) || '-')}</td>
                                         <td class="text-center"><span class="${statusClass}">${safe(medicalStatus)}</span></td>
                                     </tr>`;
                            }
                        });
                    }
                    
                    if (!donationRows) {
                        donationRows = `<tr><td colspan="4" class="text-center text-muted">No donation history available</td></tr>`;
                    }
                        donorInfoHTML += `
                         <div class="mb-3">
                             <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">Donation History</h6>
                            <div class="table-responsive">
                                 <table class="table table-sm table-bordered mb-0" style="border-radius: 10px; overflow: hidden;">
                                     <thead style="background: #b22222 !important; color: white !important;">
                                         <tr>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Last Donation Date</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Gateway</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Next Eligible Date</th>
                                             <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Medical History</th>
                                        </tr>
                                    </thead>
                                     <tbody>${donationRows}</tbody>
                                </table>
                                </div>
                        </div>`;
                    }
                    
                    // If returning, enrich assessment section with eligibility details (all records)
                    if (modalContextType === 'returning' && donor.donor_id && donor.eligibility) {
                        const eligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : [donor.eligibility];
                        let allRowsHtml = '';
                        
                        eligibilityRecords.forEach((el, index) => {
                            // Build rows for all eligibility records
                            const examDate = formatDate(el.start_date || el.created_at || donor.latest_submission) || 'N/A';
                            // Vital Signs - assess actual values against normal ranges
                            let vitalSigns = 'Normal';
                            if (el.blood_pressure && el.pulse_rate && el.body_temp) {
                                // Check blood pressure (normal: 90-140/60-90)
                                const bp = el.blood_pressure;
                                const bpMatch = bp.match(/(\d+)\/(\d+)/);
                                if (bpMatch) {
                                    const systolic = parseInt(bpMatch[1]);
                                    const diastolic = parseInt(bpMatch[2]);
                                    if (systolic < 90 || systolic > 140 || diastolic < 60 || diastolic > 90) {
                                        vitalSigns = 'Abnormal';
                                    }
                                }
                                
                                // Check pulse rate (normal: 60-100 bpm)
                                const pulse = parseInt(el.pulse_rate);
                                if (pulse < 60 || pulse > 100) {
                                    vitalSigns = 'Abnormal';
                                }
                                
                                // Check temperature (normal: 36.1-37.2°C)
                                const temp = parseFloat(el.body_temp);
                                if (temp < 36.1 || temp > 37.2) {
                                    vitalSigns = 'Abnormal';
                                }
                            } else {
                                vitalSigns = 'Incomplete';
                            }
                            const hematology = el.collection_successful ? 'Pass' : (el.collection_successful === false ? 'Fail' : 'Pass');
                            const physician = '-'; // Default as shown in image
                            const fitnessResult = el.status === 'eligible' ? 'Eligible' : 
                                                el.status === 'deferred' ? 'Deferred' : 
                                                el.status === 'temporary_deferred' ? 'Temporary Deferred' : 'Eligible';
                            let remarks = 'Pending';
                            const parts = [];
                            if (el.disapproval_reason) parts.push(el.disapproval_reason);
                            if (el.donor_reaction) parts.push(el.donor_reaction);
                            const details = parts.join(' | ');
                            const success = el.collection_successful === true || el.status === 'eligible';
                            const fail = el.collection_successful === false || el.status === 'deferred' || el.status === 'temporary_deferred';
                            if (success) {
                                remarks = 'Successful';
                            } else if (fail) {
                                remarks = details ? `Failed - ${details}` : 'Failed';
                            }
                            
                            allRowsHtml += '<tr><td>' + examDate + '</td><td>' + vitalSigns + '</td><td>' + hematology + '</td><td>' + physician + '</td><td>' + fitnessResult + '</td><td>' + remarks + '</td><td><button type="button" class="btn btn-sm btn-outline-primary" onclick="showPhysicalExaminationModal(\'' + el.eligibility_id + '\')"><i class="fas fa-eye"></i></button></td></tr>';
                        });
                        
                        const tbody = document.getElementById('returningAssessmentRows');
                        if (tbody) tbody.innerHTML = allRowsHtml;
                    }
                } else {
                    // Fallback when no donor data is available
                    donorInfoHTML = `
                        <div class="alert alert-warning">
                            <h6>No Donor Data Available</h6>
                            <p>Unable to load donor information. Please try again or contact support if the problem persists.</p>
                            <div class="small text-muted">
                                <strong>Debug Info:</strong><br>
                                Donor Data: ${donorData ? 'Available' : 'Not available'}<br>
                                Success: ${donorData && typeof donorData === 'object' && 'success' in donorData ? donorData.success : 'N/A'}<br>
                                Has Data: ${donorData && donorData.data ? 'Yes' : 'No'}<br>
                                Error: ${donorData && donorData.error ? donorData.error : 'None'}<br>
                                <details>
                                    <summary>Raw Data (click to expand)</summary>
                                    <pre>${JSON.stringify(donorData, null, 2)}</pre>
                                </details>
                            </div>
                        </div>`;
                        
                }
                
                // Returning banner removed as requested
                
                deferralStatusContent.innerHTML = donorInfoHTML;
                
                // Ensure proceed button visibility reflects current stage capability
                try {
                    const proceedButton = getProceedButton();
                    if (proceedButton && proceedButton.style) {
                        // Show button for donors who can process OR have needs_review=true
                        const hasNeedsReview = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review;
                        
                        // Check if latest Next Eligible Date is today or in the past
                        let isEligibleDateReached = false;
                        if (donor && donor.eligibility) {
                            const eligibilityRecords = Array.isArray(donor.eligibility) ? donor.eligibility : (donor.eligibility ? [donor.eligibility] : []);
                            
                            // Find the latest end_date (Next Eligible Date)
                            let latestEndDate = null;
                            eligibilityRecords.forEach((el) => {
                                if (el.end_date) {
                                    const endDate = new Date(el.end_date);
                                    if (!latestEndDate || endDate > latestEndDate) {
                                        latestEndDate = endDate;
                                    }
                                }
                            });
                            
                            // Compare with today's date
                            if (latestEndDate) {
                                const today = new Date();
                                today.setHours(0, 0, 0, 0); // Set to start of day for accurate comparison
                                latestEndDate.setHours(0, 0, 0, 0);
                                
                                // Show button if latest eligible date is today or in the past
                                isEligibleDateReached = latestEndDate <= today;
                            }
                        }
                        
                        const showReview = allowProcessing || currentStage === 'medical_review' || hasNeedsReview || isEligibleDateReached;
                        proceedButton.style.display = showReview ? 'inline-block' : 'none';
                        proceedButton.textContent = 'Proceed to Medical History';
                    }
            // Hide Mark for Medical Review button when needs_review is already true
            try {
                const markBtn = document.getElementById('markReviewFromMain');
                const hasNeedsReviewFlag = currentDonorId && medicalByDonor[currentDonorId] && medicalByDonor[currentDonorId].needs_review === true;
                if (markBtn && hasNeedsReviewFlag) {
                    markBtn.style.display = 'none';
                    markBtn.style.visibility = 'hidden';
                    markBtn.style.opacity = '0';
                }
            } catch (_) {}
                } catch (e) {}
            }
            
            // Helper function to capitalize first letter
            function ucfirst(string) {
                if (!string) return '';
                return string.charAt(0).toUpperCase() + string.slice(1);
            }
            
            // Format date helper function
            function formatDate(dateString) {
                if (!dateString) return null;
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                });
            }
            
            // Handle proceed button click with confirmation
            function openMedicalHistoryForCurrentDonor() {
                if (!currentDonorId) return;
                
                // Show confirmation modal before proceeding
                showProcessMedicalHistoryConfirmation();
            }
            
            // Show confirmation modal for processing medical history
            function showProcessMedicalHistoryConfirmation() {
                const message = 'This will redirect you to the medical history the donor just submitted. Do you want to proceed?';
                
                // Create confirmation modal matching the Submit Medical History design
                const modalHTML = `
                    <div id="processMedicalHistoryModal" style="
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.5);
                        z-index: 99999;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <div style="
                            background: white;
                            border-radius: 10px;
                            max-width: 500px;
                            width: 90%;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                            overflow: hidden;
                        ">
                            <div style="
                                background: #9c0000;
                                color: white;
                                padding: 15px 20px;
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                            ">
                                <h5 style="margin: 0; font-weight: bold;">
                                    Process Medical History
                                </h5>
                                <button onclick="closeProcessMedicalHistoryModal()" style="
                                    background: none;
                                    border: none;
                                    color: white;
                                    font-size: 20px;
                                    cursor: pointer;
                                ">&times;</button>
                            </div>
                            <div style="padding: 20px;">
                                <p style="font-size: 14px; line-height: 1.5; margin-bottom: 20px; color: #333;">${message}</p>
                                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                                    <button onclick="closeProcessMedicalHistoryModal()" style="
                                        background: #6c757d;
                                        color: white;
                                        border: none;
                                        padding: 8px 20px;
                                        border-radius: 4px;
                                        cursor: pointer;
                                        font-size: 14px;
                                    ">Cancel</button>
                                    <button onclick="confirmProcessMedicalHistory()" style="
                                        background: #9c0000;
                                        color: white;
                                        border: none;
                                        padding: 8px 20px;
                                        border-radius: 4px;
                                        cursor: pointer;
                                        font-size: 14px;
                                    ">Proceed</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Remove any existing modal
                const existingModal = document.getElementById('processMedicalHistoryModal');
                if (existingModal) {
                    existingModal.remove();
                }
                
                // Add modal to page
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                
                // Set up confirmation function
                window.confirmProcessMedicalHistory = function() {
                    closeProcessMedicalHistoryModal();
                    // Always proceed directly to Medical History (skip Physical Examination preview)
                    proceedToMedicalHistoryModal();
                };
                
                window.closeProcessMedicalHistoryModal = function() {
                    const modal = document.getElementById('processMedicalHistoryModal');
                    if (modal) {
                        modal.remove();
                    }
                };
            }
            
            
            // Deprecated duplicate renderer removed. Use the unified renderer defined later in the file.
            
            // Function to proceed to medical history modal
            function proceedToMedicalHistoryModal() {
                // Hide the deferral status modal first
                const modalEl = document.getElementById('deferralStatusModal');
                const modalInstance = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
                if (modalInstance) modalInstance.hide();
                
                // Reset initialization flags to ensure fresh initialization
                window.editFunctionalityInitialized = false;
                window.medicalHistoryQuestionsGenerated = false;
                // Allow MH modal script to re-initialize cleanly on reopen
                try { window.__mhEditInit = false; } catch (e) {}
                
                // Get or create the medical history modal instance - reuse for better performance
                const medicalHistoryModalEl = document.getElementById('medicalHistoryModal');
                let medicalHistoryModal = medicalHistoryModalEl ? bootstrap.Modal.getInstance(medicalHistoryModalEl) : null;
                if (!medicalHistoryModal) {
                    medicalHistoryModal = new bootstrap.Modal(medicalHistoryModalEl);
                }
                const modalContent = document.getElementById('medicalHistoryModalContent');
                
                // Reset modal content to loading state
                modalContent.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                // Show the modal
                medicalHistoryModal.show();
                
                // Load the medical history form content
                fetch('../../src/views/forms/medical-history-modal-content.php?donor_id=' + currentDonorId)
                    .then(response => response.text())
                    .then(data => {
                        modalContent.innerHTML = data;
                        
                        // Execute any script tags in the loaded content
                        // Remove all script tags to prevent CSP violations
                        const scripts = modalContent.querySelectorAll('script');
                        scripts.forEach(script => {
                            try {
                                script.remove();
                            } catch (e) {
                                console.warn('Could not remove script tag:', e);
                            }
                        });
                        
                        // Manually call known functions that might be needed
                        try {
                            if (typeof window.initializeMedicalHistoryApproval === 'function') {
                                window.initializeMedicalHistoryApproval();
                            }
                        } catch(e) {
                            console.warn('Could not execute initializeMedicalHistoryApproval:', e);
                        }
                        
                        // Add form submission interceptor to prevent submissions without proper action
                        const form = document.getElementById('modalMedicalHistoryForm');
                        if (form) {
                            
                            // Remove any existing submit event listeners
                            const newForm = form.cloneNode(true);
                            form.parentNode.replaceChild(newForm, form);
                            
                            // Add our controlled submit handler
                            newForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                
                                // For form submissions, always use quiet save
                                saveFormDataQuietly();
                                
                                return false;
                            });
                        }
                        
                        // After loading content, generate the questions
                        generateMedicalHistoryQuestions();
                    })
                    .catch(error => {
                        modalContent.innerHTML = '<div class="alert alert-danger"><h6>Error Loading Form</h6><p>Unable to load the medical history form. Please try again.</p><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>';
                    });
            }

            // Helper function to get proceed button
            function getProceedButton() {
                return document.getElementById('proceedToMedicalHistory');
            }
            
            // Bind proceed button event listener immediately - no delay needed
                const proceedButton = getProceedButton();
                if (proceedButton && proceedButton.addEventListener) {
                    proceedButton.addEventListener('click', function() {
                        openMedicalHistoryForCurrentDonor();
                    });
                }
                
                // Bind physical examination modal proceed button
                // Remove duplicate proceed button from Physical Examination modal footer if present
                try {
                    const dupBtn = document.getElementById('proceedToMedicalHistoryFromPhysical');
                    if (dupBtn && dupBtn.parentNode) {
                        dupBtn.parentNode.removeChild(dupBtn);
                    }
                } catch (_) {}

            // Ensure backdrops are cleaned up when key modals are closed
            ['deferralStatusModal', 'eligibilityAlertModal', 'stageNoticeModal', 'returningInfoModal']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.addEventListener('hidden.bs.modal', cleanupModalBackdrops);
                    }
                });
            
            // performOptimizedSearch function removed - now handled by external JS files
            
            // Update placeholder based on selected category
            if (searchCategory && searchCategory.addEventListener) {
            searchCategory.addEventListener('change', function() {
                const category = this.value;
                let placeholder = 'Search by ';
                switch(category) {
                    case 'surname': placeholder += 'surname...'; break;
                    case 'firstname': placeholder += 'first name...'; break;
                    case 'age': placeholder += 'age...'; break;
                    default: placeholder = 'Search donors...';
                }
                    if (searchInput) {
                searchInput.placeholder = placeholder;
                    }
            });
            }

            // Handle Mark for Medical Review from main details modal
            // Do not bind a global handler; enabled per-row only for returning
        });



        // Function to generate medical history questions in the modal
        function generateMedicalHistoryQuestions() {
            
            // Prevent multiple initialization
            if (window.medicalHistoryQuestionsGenerated) {
                return;
            }
            window.medicalHistoryQuestionsGenerated = true;
            
            // Get data from the JSON script tag
            const modalDataScript = document.getElementById('modalData');
            if (!modalDataScript) {
                return;
            }
            
            let modalData;
            try {
                modalData = JSON.parse(modalDataScript.textContent);
            } catch (e) {
                return;
            }
            
            
            const modalMedicalHistoryData = modalData.medicalHistoryData;
            const modalDonorSex = modalData.donorSex;
            const modalUserRole = modalData.userRole;
            const modalIsMale = modalDonorSex === 'male';
            
            
            // Only make fields required for reviewers (who can edit)
            const modalIsReviewer = modalUserRole === 'reviewer';
            const modalRequiredAttr = modalIsReviewer ? 'required' : '';
            
            // Define questions by step
            const questionsByStep = {
                1: [
                    { q: 1, text: "Do you feel well and healthy today?" },
                    { q: 2, text: "Have you ever been refused as a blood donor or told not to donate blood for any reasons?" },
                    { q: 3, text: "Are you giving blood only because you want to be tested for HIV or the AIDS virus or Hepatitis virus?" },
                    { q: 4, text: "Are you aware that an HIV/Hepatitis infected person can still transmit the virus despite a negative HIV/Hepatitis test?" },
                    { q: 5, text: "Have you within the last 12 HOURS had taken liquor, beer or any drinks with alcohol?" },
                    { q: 6, text: "In the last 3 DAYS have you taken aspirin?" },
                    { q: 7, text: "In the past 4 WEEKS have you taken any medications and/or vaccinations?" },
                    { q: 8, text: "In the past 3 MONTHS have you donated whole blood, platelets or plasma?" }
                ],
                2: [
                    { q: 9, text: "Been to any places in the Philippines or countries infected with ZIKA Virus?" },
                    { q: 10, text: "Had sexual contact with a person who was confirmed to have ZIKA Virus Infection?" },
                    { q: 11, text: "Had sexual contact with a person who has been to any places in the Philippines or countries infected with ZIKA Virus?" }
                ],
                3: [
                    { q: 12, text: "Received blood, blood products and/or had tissue/organ transplant or graft?" },
                    { q: 13, text: "Had surgical operation or dental extraction?" },
                    { q: 14, text: "Had a tattoo applied, ear and body piercing, acupuncture, needle stick Injury or accidental contact with blood?" },
                    { q: 15, text: "Had sexual contact with high risks individuals or in exchange for material or monetary gain?" },
                    { q: 16, text: "Engaged in unprotected, unsafe or casual sex?" },
                    { q: 17, text: "Had jaundice/hepatitis/personal contact with person who had hepatitis?" },
                    { q: 18, text: "Been incarcerated, Jailed or imprisoned?" },
                    { q: 19, text: "Spent time or have relatives in the United Kingdom or Europe?" }
                ],
                4: [
                    { q: 20, text: "Travelled or lived outside of your place of residence or outside the Philippines?" },
                    { q: 21, text: "Taken prohibited drugs (orally, by nose, or by injection)?" },
                    { q: 22, text: "Used clotting factor concentrates?" },
                    { q: 23, text: "Had a positive test for the HIV virus, Hepatitis virus, Syphilis or Malaria?" },
                    { q: 24, text: "Had Malaria or Hepatitis in the past?" },
                    { q: 25, text: "Had or was treated for genital wart, syphilis, gonorrhea or other sexually transmitted diseases?" }
                ],
                5: [
                    { q: 26, text: "Cancer, blood disease or bleeding disorder (haemophilia)?" },
                    { q: 27, text: "Heart disease/surgery, rheumatic fever or chest pains?" },
                    { q: 28, text: "Lung disease, tuberculosis or asthma?" },
                    { q: 29, text: "Kidney disease, thyroid disease, diabetes, epilepsy?" },
                    { q: 30, text: "Chicken pox and/or cold sores?" },
                    { q: 31, text: "Any other chronic medical condition or surgical operations?" },
                    { q: 32, text: "Have you recently had rash and/or fever? Was/were this/these also associated with arthralgia or arthritis or conjunctivitis?" }
                ],
                6: [
                    { q: 33, text: "Are you currently pregnant or have you ever been pregnant?" },
                    { q: 34, text: "When was your last childbirth?" },
                    { q: 35, text: "In the past 1 YEAR, did you have a miscarriage or abortion?" },
                    { q: 36, text: "Are you currently breastfeeding?" },
                    { q: 37, text: "When was your last menstrual period?" }
                ]
            };
            
            // Define remarks options based on question type
            const modalRemarksOptions = {
                1: ["None", "Feeling Unwell", "Fatigue", "Fever", "Other Health Issues"],
                2: ["None", "Low Hemoglobin", "Medical Condition", "Recent Surgery", "Other Refusal Reason"],
                3: ["None", "HIV Test", "Hepatitis Test", "Other Test Purpose"],
                4: ["None", "Understood", "Needs More Information"],
                5: ["None", "Beer", "Wine", "Liquor", "Multiple Types"],
                6: ["None", "Pain Relief", "Fever", "Other Medication Purpose"],
                7: ["None", "Antibiotics", "Vitamins", "Vaccines", "Other Medications"],
                8: ["None", "Red Cross Donation", "Hospital Donation", "Other Donation Type"],
                9: ["None", "Local Travel", "International Travel", "Specific Location"],
                10: ["None", "Direct Contact", "Indirect Contact", "Suspected Case"],
                11: ["None", "Partner Travel History", "Unknown Exposure", "Other Risk"],
                12: ["None", "Blood Transfusion", "Organ Transplant", "Other Procedure"],
                13: ["None", "Major Surgery", "Minor Surgery", "Dental Work"],
                14: ["None", "Tattoo", "Piercing", "Acupuncture", "Blood Exposure"],
                15: ["None", "High Risk Contact", "Multiple Partners", "Other Risk Factors"],
                16: ["None", "Unprotected Sex", "Casual Contact", "Other Risk Behavior"],
                17: ["None", "Personal History", "Family Contact", "Other Exposure"],
                18: ["None", "Short Term", "Long Term", "Other Details"],
                19: ["None", "UK Stay", "Europe Stay", "Duration of Stay"],
                20: ["None", "Local Travel", "International Travel", "Duration"],
                21: ["None", "Recreational", "Medical", "Other Usage"],
                22: ["None", "Treatment History", "Current Use", "Other Details"],
                23: ["None", "HIV", "Hepatitis", "Syphilis", "Malaria"],
                24: ["None", "Past Infection", "Treatment History", "Other Details"],
                25: ["None", "Current Infection", "Past Treatment", "Other Details"],
                26: ["None", "Cancer Type", "Blood Disease", "Bleeding Disorder"],
                27: ["None", "Heart Disease", "Surgery History", "Current Treatment"],
                28: ["None", "Active TB", "Asthma", "Other Respiratory Issues"],
                29: ["None", "Kidney Disease", "Thyroid Issue", "Diabetes", "Epilepsy"],
                30: ["None", "Recent Infection", "Past Infection", "Other Details"],
                31: ["None", "Condition Type", "Treatment Status", "Other Details"],
                32: ["None", "Recent Fever", "Rash", "Joint Pain", "Eye Issues"],
                33: ["None", "Current Pregnancy", "Past Pregnancy", "Other Details"],
                34: ["None", "Less than 6 months", "6-12 months ago", "More than 1 year ago"],
                35: ["None", "Less than 3 months ago", "3-6 months ago", "6-12 months ago"],
                36: ["None", "Currently Breastfeeding", "Recently Stopped", "Other"],
                37: ["None", "Within last week", "1-2 weeks ago", "2-4 weeks ago", "More than 1 month ago"]
            };
            
            // Get the field name based on the data structure
            const getModalFieldName = (count) => {
                const fields = {
                    1: 'feels_well', 2: 'previously_refused', 3: 'testing_purpose_only', 4: 'understands_transmission_risk',
                    5: 'recent_alcohol_consumption', 6: 'recent_aspirin', 7: 'recent_medication', 8: 'recent_donation',
                    9: 'zika_travel', 10: 'zika_contact', 11: 'zika_sexual_contact', 12: 'blood_transfusion',
                    13: 'surgery_dental', 14: 'tattoo_piercing', 15: 'risky_sexual_contact', 16: 'unsafe_sex',
                    17: 'hepatitis_contact', 18: 'imprisonment', 19: 'uk_europe_stay', 20: 'foreign_travel',
                    21: 'drug_use', 22: 'clotting_factor', 23: 'positive_disease_test', 24: 'malaria_history',
                    25: 'std_history', 26: 'cancer_blood_disease', 27: 'heart_disease', 28: 'lung_disease',
                    29: 'kidney_disease', 30: 'chicken_pox', 31: 'chronic_illness', 32: 'recent_fever',
                    33: 'pregnancy_history', 34: 'last_childbirth', 35: 'recent_miscarriage', 36: 'breastfeeding',
                    37: 'last_menstruation'
                };
                return fields[count];
            };
            
                         // Generate questions for each step
             for (let step = 1; step <= 6; step++) {
                 // Skip step 6 for male donors
                 if (step === 6 && modalIsMale) {
                     continue;
                 }
                 
                 const stepContainer = document.querySelector(`[data-step-container="${step}"]`);
                 if (!stepContainer) {
                     //console.error(`Step container ${step} not found`);
                     continue;
                 }
                 
                const stepQuestions = questionsByStep[step] || [];

                // Helper to normalize various DB representations to strict boolean/null
                const normalizeToBool = (val) => {
                    if (val === true || val === false) return val;
                    if (val === null || typeof val === 'undefined' || val === '') return null;
                    const s = String(val).trim().toLowerCase();
                    if (['yes','y','true','t','1'].includes(s)) return true;
                    if (['no','n','false','f','0'].includes(s)) return false;
                    return null;
                };

                stepQuestions.forEach(questionData => {
                    const fieldName = getModalFieldName(questionData.q);
                    const rawValue = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName] : null;
                    const value = normalizeToBool(rawValue);
                    const remarks = modalMedicalHistoryData ? modalMedicalHistoryData[fieldName + '_remarks'] : null;

                    // Create a form group for each question
                    const questionRow = document.createElement('div');
                    questionRow.className = 'form-group';
                    questionRow.innerHTML = `
                        <div class="question-number">${questionData.q}</div>
                        <div class="question-text">${questionData.text}</div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q${questionData.q}" value="Yes" ${value === true ? 'checked' : ''} ${modalRequiredAttr} aria-label="Question ${questionData.q} - Yes">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="radio-cell">
                            <label class="radio-container">
                                <input type="radio" name="q${questionData.q}" value="No" ${value === false ? 'checked' : ''} ${modalRequiredAttr} aria-label="Question ${questionData.q} - No">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div class="remarks-cell">
                            <select class="remarks-input" name="q${questionData.q}_remarks" ${modalRequiredAttr} aria-label="Remarks for question ${questionData.q}">
                                ${modalRemarksOptions[questionData.q].map(option => 
                                    `<option value="${option}" ${remarks === option ? 'selected' : ''}>${option}</option>`
                                ).join('')}
                            </select>
                        </div>
                    `;

                    stepContainer.appendChild(questionRow);
                });
             }
            
            // Initialize step navigation
            dashboardInitializeModalStepNavigation(modalUserRole, modalIsMale);
            
            // Default behavior: make all fields read-only until Edit is pressed (for all roles)
            // Use requestAnimationFrame for instant DOM update without blocking
            requestAnimationFrame(() => {
                const radioButtons = document.querySelectorAll('#modalMedicalHistoryForm input[type="radio"]');
                const selectFields = document.querySelectorAll('#modalMedicalHistoryForm select.remarks-input');
                const textFields = document.querySelectorAll('#modalMedicalHistoryForm input[type="text"], #modalMedicalHistoryForm textarea');

                radioButtons.forEach(el => { el.disabled = true; el.setAttribute('data-originally-disabled', 'true'); });
                selectFields.forEach(el => { el.disabled = true; el.setAttribute('data-originally-disabled', 'true'); });
                textFields.forEach(el => { el.disabled = true; el.setAttribute('data-originally-disabled', 'true'); });

                // Initialize edit functionality after locking inputs
                dashboardInitializeEditFunctionality();
            });
        }
        
        // Single edit functionality function to avoid duplicates
        function dashboardInitializeEditFunctionality() {
            //console.log('Initializing edit functionality...');
            
            // Remove any existing event listeners to prevent duplicates
            if (window.editFunctionalityInitialized) {
                //console.log('Edit functionality already initialized, skipping...');
                return;
            }
            window.editFunctionalityInitialized = true;
            
            // Add event listener for edit buttons
            document.addEventListener('click', function(e) {
                if (e.target && (e.target.classList.contains('edit-button') || e.target.closest('.edit-button'))) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    //console.log('Edit button clicked - enabling form fields');
                    
                    // Enable all form fields
                    const form = document.getElementById('modalMedicalHistoryForm');
                    if (form) {
                        // Enable radio buttons
                        form.querySelectorAll('input[type="radio"]').forEach(radio => {
                            radio.disabled = false;
                            radio.removeAttribute('data-originally-disabled');
                        });
                        
                        // Enable select fields
                        form.querySelectorAll('select.remarks-input').forEach(select => {
                            select.disabled = false;
                            select.removeAttribute('data-originally-disabled');
                        });
                        
                        // Enable text inputs
                        form.querySelectorAll('input[type="text"]').forEach(input => {
                            input.readOnly = false;
                            input.removeAttribute('data-originally-readonly');
                        });
                        
                        //console.log('Form fields enabled for editing');
                        
                        // Hide edit button; do not expose Save in MH modal
                        const editButton = e.target.classList.contains('edit-button') ? e.target : e.target.closest('.edit-button');
                        if (editButton) {
                            // Keep layout space and dimensions to avoid shifting Next button
                            const rect = editButton.getBoundingClientRect();
                            editButton.style.width = rect.width + 'px';
                            editButton.style.height = rect.height + 'px';
                            editButton.style.visibility = 'hidden';
                            editButton.style.pointerEvents = 'none';
                        }
                        // Explicitly hide any legacy save button if present
                        const saveButton = form.querySelector('.save-button');
                        if (saveButton) {
                            saveButton.style.display = 'none';
                        }
                    }
                    
                    return false;
                }
            });
            
            // Remove save behavior in MH modal: swallow any Save button clicks if they exist
            document.addEventListener('click', function(e) {
                if (e.target && (e.target.classList.contains('save-button') || (typeof e.target.textContent === 'string' && e.target.textContent.trim() === 'Save'))) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        }
        
        // Initialize step navigation for the modal
        function dashboardInitializeModalStepNavigation(userRole, isMale) {
            let currentStep = 1;
            const totalSteps = isMale ? 5 : 6;
            
            const stepIndicators = document.querySelectorAll('#modalStepIndicators .step');
            const stepConnectors = document.querySelectorAll('#modalStepIndicators .step-connector');
            const formSteps = document.querySelectorAll('#modalMedicalHistoryForm .form-step');
            const prevButton = document.getElementById('modalPrevButton');
            const nextButton = document.getElementById('modalNextButton');
            const errorMessage = document.getElementById('modalValidationError');
            
            // Hide step 6 for male donors
            if (isMale) {
                const step6 = document.getElementById('modalStep6');
                const line56 = document.getElementById('modalLine5-6');
                if (step6) step6.style.display = 'none';
                if (line56) line56.style.display = 'none';
            }
            
            function updateStepDisplay() {
                // Hide all steps
                formSteps.forEach(step => {
                    step.classList.remove('active');
                });
                
                // Show current step
                const activeStep = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                if (activeStep) {
                    activeStep.classList.add('active');
                }
                
                // Update step indicators
                stepIndicators.forEach(indicator => {
                    const step = parseInt(indicator.getAttribute('data-step'));
                    
                    if (step < currentStep) {
                        indicator.classList.add('completed');
                        indicator.classList.add('active');
                    } else if (step === currentStep) {
                        indicator.classList.add('active');
                        indicator.classList.remove('completed');
                    } else {
                        indicator.classList.remove('active');
                        indicator.classList.remove('completed');
                    }
                });
                
                // Update step connectors
                stepConnectors.forEach((connector, index) => {
                    if (index + 1 < currentStep) {
                        connector.classList.add('active');
                    } else {
                        connector.classList.remove('active');
                    }
                });
                
                // Update buttons
                if (currentStep === 1) {
                    prevButton.style.display = 'none';
                } else {
                    prevButton.style.display = 'block';
                }
                
                if (currentStep === totalSteps) {
                    if (userRole === 'reviewer') {
                        nextButton.innerHTML = 'DECLINE';
                        nextButton.onclick = (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            submitModalForm('decline');
                            return false;
                        };
                        
                        // Add approve button
                        if (!document.getElementById('modalApproveButton')) {
                            const approveBtn = document.createElement('button');
                            approveBtn.className = 'next-button';
                            approveBtn.innerHTML = 'APPROVE';
                            approveBtn.id = 'modalApproveButton';
                            approveBtn.onclick = (e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                submitModalForm('approve');
                                return false;
                            };
                            nextButton.parentNode.appendChild(approveBtn);
                        }
                    } else {
                        nextButton.innerHTML = 'NEXT';
                        nextButton.onclick = (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            submitModalForm('next');
                            return false;
                        };
                    }
                } else {
                    nextButton.innerHTML = 'Next →';
                    nextButton.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        if (validateCurrentModalStep()) {
                            currentStep++;
                            updateStepDisplay();
                            errorMessage.style.display = 'none';
                        }
                        return false;
                    };
                    
                    // Remove approve button if it exists
                    const approveBtn = document.getElementById('modalApproveButton');
                    if (approveBtn) {
                        approveBtn.remove();
                    }
                }
            }
            
            function validateCurrentModalStep() {
                const currentStepElement = document.querySelector(`#modalMedicalHistoryForm .form-step[data-step="${currentStep}"]`);
                if (!currentStepElement) return false;
                
                const radioGroups = {};
                const radios = currentStepElement.querySelectorAll('input[type="radio"]');
                
                radios.forEach(radio => {
                    radioGroups[radio.name] = true;
                });
                
                let allAnswered = true;
                for (const groupName in radioGroups) {
                    const answered = document.querySelector(`input[name="${groupName}"]:checked`) !== null;
                    if (!answered) {
                        allAnswered = false;
                        break;
                    }
                }
                
                if (!allAnswered) {
                    errorMessage.style.display = 'block';
                    errorMessage.textContent = 'Please answer all questions before proceeding to the next step.';
                    return false;
                }
                
                return true;
            }
            
            // Bind event handlers
            prevButton.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    updateStepDisplay();
                    errorMessage.style.display = 'none';
                }
            });
            
            // Initialize display
            updateStepDisplay();
        }

        // Function to handle modal form submission
        function submitModalForm(action) {
            let message = '';
            if (action === 'approve') {
                message = 'Are you sure you want to approve this donor and proceed to the declaration form?';
            } else if (action === 'decline') {
                message = 'Are you sure you want to decline this donor?';
            } else if (action === 'next') {
                message = 'Please confirm if the donor is ready for the next step based on the medical history interview, and proceed with Initial Screening.';
            }
            
            // Use custom confirmation instead of browser confirm
            if (window.customConfirm) {
                window.customConfirm(message, function() {
                    // Mark that user already confirmed to avoid double confirmation later
                    try { window.__mhConfirmed = true; } catch (_) {}
                    processFormSubmission(action);
                });
            } else {
                // Fallback to browser confirm if custom confirm is not available
                if (confirm(message)) {
                    try { window.__mhConfirmed = true; } catch (_) {}
                    processFormSubmission(action);
                }
            }
        }
        
        // Quiet save function - just updates the database without any UI changes
        function saveFormDataQuietly() {
            //console.log('Saving edited data...');
            
            const form = document.getElementById('modalMedicalHistoryForm');
            if (!form) {
                //console.error('modalMedicalHistoryForm not found');
                return;
            }
            
            const formData = new FormData(form);
            
            // Set action to 'next' for saving without approval changes
            formData.set('action', 'next');
            formData.set('modalSelectedAction', 'next');
            
            // Make a quiet AJAX request to save the data
            fetch('../../src/views/forms/medical-history-process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    //console.log('Data saved successfully');
                    // Optionally show a small success indicator
                    showQuietSuccessMessage();
                } else {
                    //console.error('Save failed:', data.message);
                    // Show error message
                    showQuietErrorMessage(data.message || 'Save failed');
                }
            })
            .catch(error => {
                //console.error('Save error:', error);
                showQuietErrorMessage('Network error occurred');
            });
        }
        
        // Show a small, non-intrusive success message
        function showQuietSuccessMessage() {
            // Create a small toast-like message
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                font-size: 14px;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            toast.textContent = 'Changes saved';
            document.body.appendChild(toast);
            
            // Show and auto-hide
            setTimeout(() => toast.style.opacity = '1', 10);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 2000);
        }
        
        // Show a small, non-intrusive error message
        function showQuietErrorMessage(message) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #dc3545;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                font-size: 14px;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            `;
            toast.textContent = 'Error: ' + message;
            document.body.appendChild(toast);
            
            // Show and auto-hide
            setTimeout(() => toast.style.opacity = '1', 10);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 4000);
        }

        // Separate function to handle the actual form submission
        function processFormSubmission(action) {
                // Set the action - if no action provided, default to 'next' for saving
                const finalAction = action || 'next';
                
                //console.log('processFormSubmission called with action:', action, 'final action:', finalAction);
                
                // Try to set the modalSelectedAction if it exists
                const modalSelectedActionInput = document.getElementById('modalSelectedAction');
                if (modalSelectedActionInput) {
                    modalSelectedActionInput.value = finalAction;
                    //console.log('Set modalSelectedAction to:', finalAction);
                } else {
                    //console.log('modalSelectedAction input not found');
                }
                
                // Submit the form via AJAX
                const form = document.getElementById('modalMedicalHistoryForm');
                if (!form) {
                    //console.error('modalMedicalHistoryForm not found');
                    return;
                }
                
                const formData = new FormData(form);
                
                // Make sure the action is set in the form data - this is the key fix
                formData.set('action', finalAction);
                formData.set('modalSelectedAction', finalAction);
                
                //console.log('Form data being sent:');
                for (let [key, value] of formData.entries()) {
                    if (key.includes('action')) {
                        //console.log(key + ':', value);
                    }
                }
                
                fetch('../../src/views/forms/medical-history-process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (action === 'next' || action === 'approve') {
                            // Close medical history modal first
                            const medicalModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                            medicalModal.hide();
                            
                            // Resolve donor_id from the form
                            const donorIdInput = document.querySelector('#modalMedicalHistoryForm input[name="donor_id"]');
                            const donorId = donorIdInput ? donorIdInput.value : null;
                            
                            // If Returning (Medical), ask to mark screening for review
                            try {
                                if (donorId && window.currentStage === 'medical_review' && (window.modalContextType === 'returning' || document.querySelector(`tr[data-donor-id="${donorId}"]`)?.getAttribute('data-donor-type')?.toLowerCase().includes('returning'))) {
                                    const csm = new bootstrap.Modal(document.getElementById('confirmScreeningMarkModal'));
                                    const btn = document.getElementById('confirmScreeningMarkBtn');
                                    btn.onclick = () => {
                                        fetch('../../assets/php_func/update_screening_needs_review.php', {
                                            method: 'POST',
                                            headers: { 'Accept': 'application/json' },
                                            body: new URLSearchParams({ donor_id: donorId })
                                        }).then(r => r.json()).finally(() => { csm.hide(); });
                                    };
                                    csm.show();
                                }
                            } catch (e) {}
                            
                            // After saving MH, require explicit confirmation before opening Initial Screening
                            if (donorId) {
                                try {
                                    const confirmModalEl = document.getElementById('dataProcessingConfirmModal');
                                    const confirmBtn = document.getElementById('confirmProcessingBtn');
                                    // If the action already went through a confirmation, skip this second confirmation
                                    const alreadyConfirmed = !!window.__mhConfirmed;
                                    if (alreadyConfirmed) {
                                        // Reset the flag and open screening immediately
                                        try { window.__mhConfirmed = false; } catch (_) {}
                                        showScreeningFormModal(donorId);
                                    } else if (confirmModalEl && confirmBtn && window.bootstrap) {
                                        // Rebind click to avoid duplicate handlers
                                        const newBtn = confirmBtn.cloneNode(true);
                                        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
                                        newBtn.addEventListener('click', function() {
                                            const cm = window.bootstrap.Modal.getInstance(confirmModalEl) || new window.bootstrap.Modal(confirmModalEl);
                                            cm.hide();
                                            // Only now open the Initial Screening modal
                                            showScreeningFormModal(donorId);
                                        });
                                        const cm = window.bootstrap.Modal.getInstance(confirmModalEl) || new window.bootstrap.Modal(confirmModalEl);
                                        cm.show();
                                    } else {
                                        // Fallback directly to screening when modal not available
                                        showScreeningFormModal(donorId);
                                    }
                                } catch (_) {
                                    // Fallback on any error
                                    showScreeningFormModal(donorId);
                                }
                            } else {
                                // Use custom modal instead of browser alert
                                if (window.customConfirm) {
                                    window.customConfirm('Error: Donor ID not found', function() {
                                        // Just close the modal, no additional action needed
                                    });
                                } else {
                                    // Use custom modal instead of browser alert
                                    if (window.customConfirm) {
                                        window.customConfirm('Error: Donor ID not found', function() {
                                            // Just close the modal, no additional action needed
                                        });
                            } else {
                                alert('Error: Donor ID not found');
                                    }
                                }
                            }
                        } else if (action === 'decline') {
                            // Close modal and refresh the main page for decline only
                            const modal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
                            modal.hide();
                            window.location.reload();
                        }
                    } else {
                        // Use custom modal instead of browser alert
                        if (window.customConfirm) {
                            window.customConfirm('Error: ' + (data.message || 'Unknown error occurred'), function() {
                                // Just close the modal, no additional action needed
                            });
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                        }
                    }
                })
                .catch(error => {
                    //console.error('Error:', error);
                    // Use custom modal instead of browser alert
                    if (window.customConfirm) {
                        window.customConfirm('An error occurred while processing the form.', function() {
                            // Just close the modal, no additional action needed
                        });
                    } else {
                    alert('An error occurred while processing the form.');
                    }
                });
        }
        
        // Function to show screening form modal
        function showScreeningFormModal(donorId) {
            //console.log('Showing screening form modal for donor ID:', donorId);
            
            // Set donor data for the screening form
            window.currentDonorData = { donor_id: donorId };
            
            // Show the screening form modal with static backdrop
            const screeningModalElement = document.getElementById('screeningFormModal');
            const screeningModal = new bootstrap.Modal(screeningModalElement, {
                backdrop: 'static',
                keyboard: false
            });
            screeningModal.show();
            
            // Prevent modal from closing when clicking on content
            screeningModalElement.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            // Set the donor ID in the screening form
            const donorIdInput = document.querySelector('#screeningFormModal input[name="donor_id"]');
            if (donorIdInput) {
                donorIdInput.value = donorId;
            }
            
            // Initialize the screening form
            if (window.initializeScreeningForm) {
                window.initializeScreeningForm(donorId);
            }
        }
        
        // Function to show declaration form modal
        window.showDeclarationFormModal = function(donorId) {
            //console.log('Showing declaration form modal for donor ID:', donorId);
            
            // Show confirmation modal first
            const confirmationModalHtml = `
                <div class="modal fade" id="screeningToDeclarationConfirmationModal" tabindex="-1" aria-labelledby="screeningToDeclarationConfirmationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white; border-radius: 0.375rem 0.375rem 0 0;">
                                <h5 class="modal-title" id="screeningToDeclarationConfirmationModalLabel">Screening Submitted Successfully</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">Screening submitted. Please proceed to the declaration form to complete the donor registration process.</p>
                            </div>
                            <div class="modal-footer border-0 justify-content-end">
                                <button type="button" class="btn" style="background: linear-gradient(135deg, #b22222 0%, #8b0000 100%); color: white;" onclick="proceedToDeclarationForm('${donorId}')">Proceed to Declaration Form</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('screeningToDeclarationConfirmationModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add the modal to the document
            document.body.insertAdjacentHTML('beforeend', confirmationModalHtml);
            
            // Show the confirmation modal
            const confirmationModal = new bootstrap.Modal(document.getElementById('screeningToDeclarationConfirmationModal'));
            confirmationModal.show();
            
            // Add event listener to remove modal from DOM after it's hidden
            document.getElementById('screeningToDeclarationConfirmationModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        };
        
        // Function to proceed to declaration form after confirmation
        window.proceedToDeclarationForm = function(donorId) {
            // Close confirmation modal
            const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('screeningToDeclarationConfirmationModal'));
            if (confirmationModal) {
                confirmationModal.hide();
            }
            
            const declarationModal = new bootstrap.Modal(document.getElementById('declarationFormModal'));
            const modalContent = document.getElementById('declarationFormModalContent');
            
            // Reset modal content to loading state
            modalContent.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="min-height: 200px;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 mb-0">Loading Declaration Form...</p>
                    </div>
                </div>`;
            
            // Show the modal
            declarationModal.show();
            
            // Load the declaration form content
            fetch('../../src/views/forms/declaration-form-modal-content.php?donor_id=' + donorId)
                .then(response => {
                    //console.log('Declaration form response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    //console.log('Declaration form content loaded successfully');
                    modalContent.innerHTML = data;
                    
                    // Ensure print function is available globally
                    window.printDeclaration = function() {
                        //console.log('Print function called');
                        const printWindow = window.open('', '_blank');
                        const content = document.querySelector('.declaration-header').outerHTML + 
                                       document.querySelector('.donor-info').outerHTML + 
                                       document.querySelector('.declaration-content').outerHTML + 
                                       document.querySelector('.signature-section').outerHTML;
                        
                        printWindow.document.write(`
                            <!DOCTYPE html>
                            <html>
                            <head>
                                <title>Declaration Form - Philippine Red Cross</title>
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
                        // Print immediately when ready
                            printWindow.print();
                    };
                    
                    // Re-introduce explicit confirmation: only proceed and close after user agrees
                    
                    // Ensure submit function is available globally
                    window.submitDeclarationForm = function(event) {
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
                            
                            // Include screening data if available
                            if (window.currentScreeningData) {
                                formData.append('screening_data', JSON.stringify(window.currentScreeningData));
                                formData.append('debug_log', 'Including screening data: ' + JSON.stringify(window.currentScreeningData));
                            } else {
                                formData.append('debug_log', 'No screening data available');
                            }
                            
                            formData.append('debug_log', 'Submitting form data...');
                            
                            // Debug: Log what we're sending
                            const debugFormData = new FormData();
                            debugFormData.append('debug_log', 'FormData contents:');
                            for (let [key, value] of formData.entries()) {
                                debugFormData.append('debug_log', '  ' + key + ': ' + value);
                            }
                            fetch('../../src/views/forms/declaration-form-process.php', {
                                method: 'POST',
                                body: debugFormData
                            }).catch(() => {});
                            
                            fetch('../../src/views/forms/declaration-form-process.php', {
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
                                    // Close declaration form modal ONLY after explicit confirmation (already given)
                                    const declarationModal = bootstrap.Modal.getInstance(document.getElementById('declarationFormModal'));
                                    if (declarationModal) {
                                        declarationModal.hide();
                                    }
                                    
                                    // Show success modal with requested copy and behavior
                                    if (window.showSuccessModal) {
                                        // Title should indicate forwarded to physician; content should not claim cleared
                                        showSuccessModal('Submitted', 'The donor has been forwarded to the physician for physical examination.', { autoCloseMs: 1600, reloadOnClose: true });
                                    } else {
                                        // Fallback
                                        alert('Submitted: The donor has been forwarded to the physician for physical examination.');
                                        window.location.reload();
                                    }
                                } else {
                                    // Show error modal (different styling)
                                    const msg = 'Failed to complete registration. ' + (data.message || 'Please try again.');
                                    if (window.showErrorModal) {
                                        showErrorModal('Submission Failed', msg, { autoCloseMs: null, reloadOnClose: false });
                                    } else {
                                        alert(msg);
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error submitting declaration form:', error);
                                
                                // Log error to server
                                const errorFormData = new FormData();
                                errorFormData.append('debug_log', 'JavaScript Error: ' + error.message);
                                fetch('../../src/views/forms/declaration-form-process.php', {
                                    method: 'POST',
                                    body: errorFormData
                                }).catch(() => {});
                                
                                const emsg = 'An error occurred while processing the form: ' + error.message;
                                if (window.showErrorModal) {
                                    showErrorModal('Submission Error', emsg, { autoCloseMs: null, reloadOnClose: false });
                                } else {
                                    alert(emsg);
                                }
                            });
                        };
                        
                        // Ask for explicit confirmation before proceeding
                        const message = 'Are you sure you want to complete the registration?';
                        if (window.customConfirm) {
                            window.customConfirm(message, proceedSubmission);
                        } else {
                            if (confirm(message)) {
                                proceedSubmission();
                            }
                        }
                    };
                })
                .catch(error => {
                    //console.error('Error loading declaration form:', error);
                    modalContent.innerHTML = `
                        <div class="alert alert-danger text-center" style="margin: 50px 20px;">
                            <h5 class="alert-heading">
                                <i class="fas fa-exclamation-triangle"></i> Error Loading Form
                            </h5>
                            <p>Unable to load the declaration form. Please try again.</p>
                            <hr>
                            <p class="mb-0">Error details: ' + error.message + '</p>
                            <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>`;
                });
        }
        
        // Add loading functionality for data processing
        function showProcessingModal(message = 'Processing medical history data...') {
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            const loadingText = document.querySelector('#loadingModal p');
            if (loadingText) {
                loadingText.textContent = message;
            }
            loadingModal.show();
        }
        
        function hideProcessingModal() {
            const loadingModal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
            if (loadingModal) {
                loadingModal.hide();
            }
        }
        
        // Make functions globally available
        window.showProcessingModal = showProcessingModal;
        window.hideProcessingModal = hideProcessingModal;
        
        // Show loading when medical history forms are submitted
        document.addEventListener('submit', function(e) {
            if (e.target && (e.target.classList.contains('medical-form') || e.target.id.includes('medical'))) {
                showProcessingModal('Submitting medical history data...');
            }
        });
        
        // Removed risky global fetch override that caused duplicate loaders and race conditions
        
        // Custom confirmation function to replace browser confirm
        function customConfirm(message, onConfirm) {
            // Create a simple modal matching the Submit Medical History design
            const modalHTML = `
                <div id="simpleCustomModal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white;
                        border-radius: 10px;
                        max-width: 500px;
                        width: 90%;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                        overflow: hidden;
                    ">
                        <div style="
                            background: #9c0000;
                            color: white;
                            padding: 15px 20px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <h5 style="margin: 0; font-weight: bold;">
                                Confirm Action
                            </h5>
                            <button onclick="closeSimpleModal()" style="
                                background: none;
                                border: none;
                                color: white;
                                font-size: 20px;
                                cursor: pointer;
                            ">&times;</button>
                        </div>
                        <div style="padding: 20px;">
                            <p style="font-size: 14px; line-height: 1.5; margin-bottom: 20px; color: #333;">${message}</p>
                            <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                                <button onclick="closeSimpleModal()" style="
                                    background: #6c757d;
                                    color: white;
                                    border: none;
                                    padding: 8px 20px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    font-size: 14px;">Cancel</button>
                                <button onclick="confirmSimpleModal()" style="
                                    background: #9c0000;
                                    color: white;
                                    border: none;
                                    padding: 8px 20px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    font-size: 14px;
                                ">Yes, proceed</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal
            const existingModal = document.getElementById('simpleCustomModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Set up confirmation function
            window.confirmSimpleModal = function() {
                closeSimpleModal();
                if (onConfirm) onConfirm();
            };
            
            window.closeSimpleModal = function() {
                const modal = document.getElementById('simpleCustomModal');
                if (modal) {
                    modal.remove();
                }
            };
        }

        // Make customConfirm globally available
        window.customConfirm = customConfirm;
