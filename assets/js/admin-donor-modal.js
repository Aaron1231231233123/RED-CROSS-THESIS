/**
 * Admin Donor Modal Module
 * Handles donor details modal functionality for admin dashboard
 * Extracted from dashboard-Inventory-System-list-of-donations.php for better performance
 */

window.AdminDonorModal = (function() {
    'use strict';

    // Private variables
    let currentDonorId = null;
    let currentEligibilityId = null;

    // Private helper functions
    const safe = (v, fb = '-') => (v === null || v === undefined || v === '' ? fb : v);

    const badge = (text) => {
        const t = String(text || '').toLowerCase();
        let cls = 'bg-secondary';
        if (t.includes('pending')) cls = 'bg-warning text-dark';
        else if (t.includes('approved') || t.includes('eligible') || t.includes('success')) cls = 'bg-success';
        else if (t.includes('declined') || t.includes('defer') || t.includes('fail') || t.includes('ineligible')) cls = 'bg-danger';
        else if (t.includes('review') || t.includes('medical') || t.includes('physical')) cls = 'bg-info text-dark';
        return `<span class="badge ${cls}">${safe(text)}</span>`;
    };

    const getStatusDisplay = (status, stage) => {
        const statusLower = String(status || '').toLowerCase();
        if (statusLower.includes('permanently deferred')) {
            return `<span class="badge bg-danger">${stage} - Permanently Deferred</span>`;
        } else if (statusLower.includes('temporarily deferred')) {
            return `<span class="badge bg-warning text-dark">${stage} - Temporarily Deferred</span>`;
        } else if (statusLower.includes('refused')) {
            return `<span class="badge bg-danger">${stage} - Refused</span>`;
        } else if (statusLower.includes('declined') || statusLower.includes('defer') || statusLower.includes('not approved')) {
            return `<span class="badge bg-danger">${stage} - ${status}</span>`;
        } else if (statusLower.includes('pending')) {
            return `<span class="badge bg-warning text-dark">${status}</span>`;
        } else if (statusLower.includes('accepted') || statusLower.includes('approved') || statusLower.includes('completed') || statusLower.includes('passed') || statusLower.includes('successful')) {
            return `<span class="badge bg-success">${status}</span>`;
        } else {
            return badge(status);
        }
    };

    const createSection = (title, rows, headerColor = 'bg-danger') => `
        <div class="card mb-3 shadow-sm" style="border:none">
            <div class="card-header ${headerColor} text-white py-2 px-3" style="border:none">
                <h6 class="mb-0" style="font-weight:600;">${title}</h6>
            </div>
            <div class="card-body py-2 px-3">
                ${rows}
            </div>
        </div>`;

    const createInterviewerRows = (donor, eligibility, interviewerMedical, interviewerScreening, screeningForm, medicalHistoryData) => {
        const baseUrl = '../../src/views/forms/';
        const donorId = encodeURIComponent(safe(donor.donor_id, ''));
        
        // Check individual process statuses
        const isMedicalHistoryPending = interviewerMedical.toLowerCase() === 'pending' || interviewerMedical === '' || interviewerMedical === '-';
        const isMedicalHistoryCompleted = interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-';
        const isScreeningPending = interviewerScreening.toLowerCase() === 'pending' || interviewerScreening === '' || interviewerScreening === '-';
        const isScreeningCompleted = interviewerScreening.toLowerCase() !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-';
        
        // Check if screening record exists
        const hasScreeningRecord = screeningForm && Object.keys(screeningForm).length > 0 && screeningForm.screening_id;
        
        // Check if medical history is approved
        const medicalApproval = medicalHistoryData?.medical_approval || medicalHistoryData?.status || '';
        const isMedicalHistoryApproved = medicalApproval.toLowerCase() === 'approved';
        
        // Determine action buttons for each process
        let medicalHistoryAction = '';
        let screeningAction = '';
        
        // Medical History Action
        if (isMedicalHistoryPending) {
            medicalHistoryAction = `<button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="Edit Medical History" onclick="editMedicalHistory('${safe(donor.donor_id,'')}')"><i class="fas fa-pen"></i></button>`;
        } else if (isMedicalHistoryCompleted) {
            // Always show view button - approve/decline buttons will be shown inside the MH modal
            medicalHistoryAction = `<button type="button" class="btn btn-sm btn-outline-success circular-btn" title="View Medical History" onclick="viewMedicalHistory('${safe(donor.donor_id,'')}')"><i class="fas fa-eye"></i></button>`;
        }
        
        // Initial Screening Action
        if (isScreeningPending) {
            screeningAction = `<button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="Edit Initial Screening" onclick="editInitialScreening('${safe(donor.donor_id,'')}')"><i class="fas fa-pen"></i></button>`;
        } else if (isScreeningCompleted) {
            screeningAction = `<button type="button" class="btn btn-sm btn-outline-success circular-btn" title="View Initial Screening" onclick="viewInitialScreening('${safe(donor.donor_id,'')}')"><i class="fas fa-eye"></i></button>`;
        }

        return `
        <div class="donor-role-tables-container">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="donor-role-table">
                        <table class="table table-sm align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center py-2">Medical History</th>
                                    <th class="text-center py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center status-cell py-2">${getStatusDisplay(interviewerMedical, 'MH')}</td>
                                    <td class="text-center action-cell py-2">${medicalHistoryAction}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="donor-role-table">
                        <table class="table table-sm align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center py-2">Initial Screening</th>
                                    <th class="text-center py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center status-cell py-2">${getStatusDisplay(interviewerScreening, 'Initial Screening')}</td>
                                    <td class="text-center action-cell py-2">${screeningAction}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>`;
    };

    const createPhysicianPhlebotomistSections = (donor, eligibility, interviewerMedical, interviewerScreening, physicianMedical, physicianPhysical, phlebStatus) => {
        // Get physician action button
        const physicianActionButton = (() => {
            const baseUrl = '../../src/views/forms/';
            const donorId = encodeURIComponent(safe(donor.donor_id, ''));
            
            // Check if interviewer phase is completed
            const interviewerCompleted = (interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-') &&
                                      (interviewerScreening.toLowerCase() !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-');
            
            // Check physician phase status
            const isPendingPhysicianWork = interviewerCompleted &&
                                          ((physicianMedical.toLowerCase() === 'pending' || physicianMedical === '' || physicianMedical === '-') ||
                                           (physicianPhysical.toLowerCase() === 'pending' || physicianPhysical === '' || physicianPhysical === '-'));
            const isCompletedPhysicianWork = interviewerCompleted &&
                                            (physicianMedical.toLowerCase() !== 'pending' && physicianMedical !== '' && physicianMedical !== '-') &&
                                            (physicianPhysical.toLowerCase() !== 'pending' && physicianPhysical !== '' && physicianPhysical !== '-');
            
            // Determine action button based on status
            let actionButton = '';
            if (!interviewerCompleted) {
                actionButton = `<button type="button" class="btn btn-sm btn-outline-secondary circular-btn" title="Complete Interviewer Phase First" disabled><i class="fas fa-lock"></i></button>`;
            } else {
                // Physical examination should only show PE form (no approval process)
                // Check if physical examination is completed
                const ppLower = String(physicianPhysical || '').toLowerCase();
                const isPhysicalExamCompleted = (
                    ppLower.includes('approved') ||
                    ppLower.includes('accepted') ||
                    ppLower.includes('completed') ||
                    ppLower.includes('passed') ||
                    ppLower.includes('success') ||
                    (ppLower !== 'pending' && ppLower !== '' && ppLower !== '-')
                );
                
                const btnTitle = isPhysicalExamCompleted ? 'View Physical Examination' : 'Edit Physical Examination';
                const btnClass = isPhysicalExamCompleted ? 'btn-outline-success' : 'btn-outline-primary';
                const icon = isPhysicalExamCompleted ? 'fa-eye' : 'fa-pen';
                
                // Directly open physical examination form (no medical history approval)
                if (isPhysicalExamCompleted) {
                    actionButton = `<button type="button" class="btn btn-sm ${btnClass} circular-btn" title="${btnTitle}" onclick="openPhysicalExaminationView('${donor.donor_id || ''}')"><i class="fas ${icon}"></i></button>`;
                } else {
                    actionButton = `<button type="button" class="btn btn-sm ${btnClass} circular-btn" title="${btnTitle}" onclick="openPhysicalExaminationForm('${donor.donor_id || ''}')"><i class="fas ${icon}"></i></button>`;
                }
            }
            return actionButton;
        })();
        
        // Get phlebotomist action button
        // ROOT CAUSE FIX: Lock blood collection when any stage is declined/deferred
        const phlebotomistActionButton = (() => {
            // Check for decline/deferral at any stage
            const interviewerMedicalLower = String(interviewerMedical || '').toLowerCase();
            const interviewerScreeningLower = String(interviewerScreening || '').toLowerCase();
            const physicianMedicalLower = String(physicianMedical || '').toLowerCase();
            const physicianPhysicalLower = String(physicianPhysical || '').toLowerCase();
            
            // Check if any stage has decline/deferral status
            const hasMedicalHistoryDecline = interviewerMedicalLower.includes('declined') || 
                                           interviewerMedicalLower.includes('not approved') ||
                                           physicianMedicalLower.includes('declined') ||
                                           physicianMedicalLower.includes('not approved');
            const hasScreeningDecline = interviewerScreeningLower.includes('declined') || 
                                      interviewerScreeningLower.includes('not approved');
            const hasPhysicalDeclineDefer = physicianPhysicalLower.includes('declined') ||
                                          physicianPhysicalLower.includes('temporarily deferred') ||
                                          physicianPhysicalLower.includes('permanently deferred') ||
                                          physicianPhysicalLower.includes('refused') ||
                                          physicianPhysicalLower.includes('not approved');
            
            const hasAnyDeclineDefer = hasMedicalHistoryDecline || hasScreeningDecline || hasPhysicalDeclineDefer;
            
            const physicianCompleted = (interviewerMedicalLower !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-') &&
                                    (interviewerScreeningLower !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-') &&
                                    (physicianMedicalLower !== 'pending' && physicianMedical !== '' && physicianMedical !== '-') &&
                                    (physicianPhysicalLower !== 'pending' && physicianPhysical !== '' && physicianPhysical !== '-');
            
            const isPendingBloodCollection = physicianCompleted &&
                                           (phlebStatus.toLowerCase() === 'pending' || phlebStatus === '' || phlebStatus === '-');
            const isCompletedBloodCollection = physicianCompleted &&
                                             phlebStatus.toLowerCase() !== 'pending' && phlebStatus !== '' && phlebStatus !== '-';
            
            let actionButton = '';
            // ROOT CAUSE FIX: Lock blood collection if any stage is declined/deferred
            if (hasAnyDeclineDefer) {
                // Determine which stage caused the lock
                let lockReason = 'Donor Declined/Deferred';
                if (hasMedicalHistoryDecline) {
                    lockReason = 'Medical History Declined';
                } else if (hasScreeningDecline) {
                    lockReason = 'Initial Screening Declined';
                } else if (hasPhysicalDeclineDefer) {
                    lockReason = 'Physical Examination Declined/Deferred';
                }
                actionButton = `<button type="button" class="btn btn-sm btn-outline-secondary circular-btn" title="${lockReason} - Blood Collection Locked" disabled><i class="fas fa-lock"></i></button>`;
            } else if (!physicianCompleted) {
                actionButton = `<button type="button" class="btn btn-sm btn-outline-secondary circular-btn" title="Complete Physician Phase First" disabled><i class="fas fa-lock"></i></button>`;
            } else if (isPendingBloodCollection) {
                actionButton = `<button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="Edit Blood Collection" onclick="editBloodCollection('${donor.donor_id || ''}')"><i class="fas fa-pen"></i></button>`;
            } else if (isCompletedBloodCollection) {
                // Collection is completed/successful - enable view button
                const donorId = donor.donor_id || '';
                // Directly fetch and open the collection view modal, not the edit modal
                actionButton = `<button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="View Blood Collection" onclick="openBloodCollectionView('${donorId}')"><i class="fas fa-eye"></i></button>`;
            } else {
                actionButton = `<button type="button" class="btn btn-sm btn-outline-secondary circular-btn" title="View Not Available" disabled><i class="fas fa-eye"></i></button>`;
            }
            return actionButton;
        })();
        
        // ROOT CAUSE FIX: Update phlebStatus display to show "Locked" when any stage is declined/deferred
        const getPhlebStatusDisplay = () => {
            const interviewerMedicalLower = String(interviewerMedical || '').toLowerCase();
            const interviewerScreeningLower = String(interviewerScreening || '').toLowerCase();
            const physicianMedicalLower = String(physicianMedical || '').toLowerCase();
            const physicianPhysicalLower = String(physicianPhysical || '').toLowerCase();
            
            // Check if any stage has decline/deferral status
            const hasMedicalHistoryDecline = interviewerMedicalLower.includes('declined') || 
                                           interviewerMedicalLower.includes('not approved') ||
                                           physicianMedicalLower.includes('declined') ||
                                           physicianMedicalLower.includes('not approved');
            const hasScreeningDecline = interviewerScreeningLower.includes('declined') || 
                                      interviewerScreeningLower.includes('not approved');
            const hasPhysicalDeclineDefer = physicianPhysicalLower.includes('declined') ||
                                          physicianPhysicalLower.includes('temporarily deferred') ||
                                          physicianPhysicalLower.includes('permanently deferred') ||
                                          physicianPhysicalLower.includes('refused') ||
                                          physicianPhysicalLower.includes('not approved');
            
            const hasAnyDeclineDefer = hasMedicalHistoryDecline || hasScreeningDecline || hasPhysicalDeclineDefer;
            
            // If any stage is declined/deferred, show "Locked" instead of "Pending"
            if (hasAnyDeclineDefer) {
                return `<span class="badge bg-secondary">Locked</span>`;
            }
            
            // Otherwise, use normal status display
            return getStatusDisplay(phlebStatus, 'Blood Collection');
        };

        return `
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                ${createSection('Physician', `
                    <div class="donor-role-table">
                        <table class="table table-sm align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center py-2">Physical Examination</th>
                                    <th class="text-center py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center status-cell py-2">${getStatusDisplay(physicianPhysical, 'Physical Exam')}</td>
                                    <td class="text-center action-cell py-2">${physicianActionButton}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                `, 'bg-danger')}
            </div>
            <div class="col-md-6">
                ${createSection('Phlebotomist', `
                    <div class="donor-role-table">
                        <table class="table table-sm align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center py-2">Blood Collection Status</th>
                                    <th class="text-center py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center status-cell py-2">${getPhlebStatusDisplay()}</td>
                                    <td class="text-center action-cell py-2">${phlebotomistActionButton}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                `, 'bg-danger')}
            </div>
        </div>`;
    };

    // Public API
    return {
        // Main function to fetch and display donor details
        fetchDonorDetails: function(donorId, eligibilityId) {
            console.log(`Fetching details for donor: ${donorId}, eligibility: ${eligibilityId}`);
            
            // Update current tracking variables
            currentDonorId = donorId;
            currentEligibilityId = eligibilityId;
            window.currentDetailsDonorId = donorId;
            window.currentDetailsEligibilityId = eligibilityId;

            // Immediately show loading state in the modal to ensure it's visible right away
            const donorDetailsContainer = document.getElementById('donorDetails');
            if (donorDetailsContainer) {
                donorDetailsContainer.innerHTML = '<div class="text-center my-4"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading donor details...</p></div>';
            }

            // Ensure modal is shown (in case it wasn't shown yet)
            const donorModal = document.getElementById('donorModal');
            if (donorModal) {
                const modalInstance = bootstrap.Modal.getInstance(donorModal) || new bootstrap.Modal(donorModal);
                if (!donorModal.classList.contains('show')) {
                    modalInstance.show();
                }
            }

            // Use setTimeout to ensure the modal and loading state are rendered before starting the fetch
            setTimeout(() => {
                // Update physical_examination needs_review=TRUE and access='2' when admin opens donor modal
                fetch(`../../assets/php_func/update_physical_exam_admin_access.php?donor_id=${encodeURIComponent(donorId)}`, {
                    method: 'GET',
                    cache: 'no-cache'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log(`Physical examination updated for admin access - donor_id: ${donorId}`);
                    } else {
                        console.warn(`Failed to update physical examination for admin access:`, data.error);
                    }
                })
                .catch(error => {
                    console.warn(`Error updating physical examination for admin access:`, error);
                    // Don't block the modal from opening if this fails
                });

                // Update blood_collection needs_review=TRUE and access='2' when admin opens donor modal
                fetch(`../../assets/php_func/admin/update_blood_collection_admin_access.php?donor_id=${encodeURIComponent(donorId)}`, {
                    method: 'GET',
                    cache: 'no-cache'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log(`Blood collection updated for admin access - donor_id: ${donorId}`);
                    } else {
                        console.warn(`Failed to update blood collection for admin access:`, data.error);
                    }
                })
                .catch(error => {
                    console.warn(`Error updating blood collection for admin access:`, error);
                    // Don't block the modal from opening if this fails
                });

                // Add cache-busting timestamp to ensure fresh data
                const timestamp = Date.now();
                const url = `../../assets/php_func/donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}&_t=${timestamp}`;
                
                console.log(`Fetching from URL: ${url}`);

                fetch(url, {
                    method: 'GET',
                    cache: 'no-cache',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        const donorDetailsContainer = document.getElementById('donorDetails');
                    if (data.error) {
                        donorDetailsContainer.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }

                    const donor = data.donor || {};
                    const eligibility = data.eligibility || {};
                    
                    // Check medical history completion status from medical_history table
                    const medicalHistoryData = data.medical_history || {};
                    const isAdminCompleted = medicalHistoryData.is_admin === true || medicalHistoryData.is_admin === 'true' || medicalHistoryData.is_admin === 'True';
                    const medicalApproval = medicalHistoryData.medical_approval || '';
                    const hasMedicalHistory = medicalHistoryData && Object.keys(medicalHistoryData).length > 0;
                    
                    // ROOT CAUSE FIX: Determine interviewer medical status - preserve decline/deferral statuses from API
                    let interviewerMedicalStatus = safe(eligibility.medical_history_status);
                    const medicalHistoryStatusLower = String(interviewerMedicalStatus || '').toLowerCase();
                    
                    // Check if API returned a decline/deferral status - preserve it
                    if (medicalHistoryStatusLower.includes('declined') || medicalHistoryStatusLower.includes('not approved')) {
                        // Keep the decline status from API
                        interviewerMedicalStatus = interviewerMedicalStatus;
                    } else if (medicalApproval.toLowerCase() === 'approved') {
                        interviewerMedicalStatus = 'Approved';
                    } else if (isAdminCompleted || hasMedicalHistory) {
                        // If admin completed or medical history exists, show "Completed"
                        interviewerMedicalStatus = 'Completed';
                    } else if (!interviewerMedicalStatus || interviewerMedicalStatus === '-' || interviewerMedicalStatus === '') {
                        interviewerMedicalStatus = 'Pending';
                    }
                    
                    // ROOT CAUSE FIX: Extract statuses from API - these should already contain decline/deferral statuses
                    const interviewerMedical = interviewerMedicalStatus;
                    const interviewerScreening = safe(eligibility.screening_status); // Should be "Declined/Not Approved" if declined
                    const physicianMedical = safe(eligibility.review_status); // Medical history approval status from physician
                    const physicianPhysical = safe(eligibility.physical_status); // Should be "Temporarily Deferred", "Permanently Deferred", etc. if deferred
                    // Get phlebotomist status - ensure we use the value from API response
                    let phlebStatus = safe(eligibility.collection_status);
                    // If collection_status is empty/pending but we have blood collection data, check it directly
                    if ((!phlebStatus || phlebStatus === '-' || phlebStatus.toLowerCase() === 'pending') && data.blood_collection) {
                        const bc = data.blood_collection;
                        if (bc.is_successful === true || bc.is_successful === 'true' || bc.is_successful === 1) {
                            phlebStatus = 'Successful';
                            console.log('Phlebotomist Status - Overriding from blood_collection.is_successful: Successful');
                        } else if (bc.status && bc.status.toLowerCase() === 'successful') {
                            phlebStatus = 'Successful';
                            console.log('Phlebotomist Status - Overriding from blood_collection.status: Successful');
                        }
                    }
                    const eligibilityStatus = String(safe(eligibility.status, '')).toLowerCase();
                    const isFullyApproved = eligibilityStatus === 'approved' || eligibilityStatus === 'eligible';

                    // Debug logging
                    console.log('Donor Information Modal - Status Values:', {
                        interviewerMedical,
                        interviewerScreening,
                        physicianMedical,
                        physicianPhysical,
                        phlebStatus,
                        eligibilityStatus,
                        isAdminCompleted,
                        hasMedicalHistory,
                        medicalApproval,
                        medicalHistoryData: medicalHistoryData,
                        eligibility: eligibility
                    });
                    
                    // Additional debug for phlebotomist status
                    console.log('Phlebotomist Status Debug:', {
                        'eligibility.collection_status (raw)': eligibility.collection_status,
                        'phlebStatus (after safe)': phlebStatus,
                        'phlebStatus type': typeof phlebStatus,
                        'phlebStatus length': phlebStatus ? phlebStatus.length : 0,
                        'phlebStatus.toLowerCase()': phlebStatus ? phlebStatus.toLowerCase() : 'null'
                    });

                    // Derive blood type from donor, fallback to eligibility
                    const derivedBloodType = safe(donor.blood_type || eligibility.blood_type);
                    
                    // Create header
                    const header = `
                        <div class="donor-header-wireframe">
                            <div class="donor-header-left">
                                <h3 class="donor-name-wireframe">${safe(donor.surname)}, ${safe(donor.first_name)} ${safe(donor.middle_name)}</h3>
                                <div class="donor-age-gender">${safe(donor.age)}, ${safe(donor.sex)}</div>
                            </div>
                            <div class="donor-header-right">
                                <div class="donor-id-wireframe">Donor ID ${safe(donor.donor_id)}</div>
                                <div class="donor-blood-type">
                                    <div class="blood-type-display" style="display: inline-block !important; background-color: #8B0000 !important; color: white !important; padding: 8px 16px !important; border-radius: 20px !important; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important; text-align: center !important; min-width: 80px !important; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important; border: none !important;">
                                        <div class="blood-type-label" style="font-size: 0.75rem !important; font-weight: 500 !important; line-height: 1 !important; margin-bottom: 2px !important; opacity: 0.9 !important; color: white !important;">Blood Type</div>
                                        <div class="blood-type-value" style="font-size: 1.1rem !important; font-weight: bold !important; line-height: 1 !important; letter-spacing: 0.5px !important; color: white !important;">${derivedBloodType}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Donor Information Section -->
                        <div class="section-wireframe">
                            <h6 class="section-title">Donor Information:</h6>
                            <div class="form-fields-grid">
                                <div class="form-field">
                                    <label>Birthdate</label>
                                    <input type="text" class="form-control form-control-sm donor-info-input" value="${safe(donor.birthdate)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Address</label>
                                    <input type="text" class="form-control form-control-sm donor-info-input" value="${safe(donor.permanent_address || donor.current_address || donor.office_address || donor.address_line || donor.address)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Mobile Number</label>
                                    <input type="text" class="form-control form-control-sm donor-info-input" value="${safe(donor.mobile || donor.mobile_number || donor.contact_number || donor.phone)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Civil Status</label>
                                    <input type="text" class="form-control form-control-sm donor-info-input" value="${safe(donor.civil_status)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Nationality</label>
                                    <input type="text" class="form-control form-control-sm donor-info-input" value="${safe(donor.nationality)}" disabled>
                                </div>
                                <div class="form-field">
                                    <label>Occupation</label>
                                    <input type="text" class="form-control form-control-sm donor-info-input" value="${safe(donor.occupation)}" disabled>
                                </div>
                            </div>
                        </div>`;

                    // Create status summary
                    // ROOT CAUSE FIX: Properly detect decline/deferral at each stage and show correct overall status
                    const getOverallStatus = () => {
                        const statusLower = String(eligibilityStatus || '').toLowerCase();
                        const interviewerMedicalLower = String(interviewerMedical || '').toLowerCase();
                        const interviewerScreeningLower = String(interviewerScreening || '').toLowerCase();
                        const physicianMedicalLower = String(physicianMedical || '').toLowerCase();
                        const physicianPhysicalLower = String(physicianPhysical || '').toLowerCase();
                        const phlebStatusLower = String(phlebStatus || '').toLowerCase();

                        // ROOT CAUSE FIX: Check for declined/deferred/refused status in each stage in priority order
                        // Priority 1: Medical History (check both interviewer and physician medical)
                        if (interviewerMedicalLower.includes('declined') || interviewerMedicalLower.includes('not approved') ||
                            physicianMedicalLower.includes('declined') || physicianMedicalLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'Medical History', color: 'danger' };
                        }
                        
                        // Priority 2: Initial Screening
                        if (interviewerScreeningLower.includes('declined') || interviewerScreeningLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'Initial Screening', color: 'danger' };
                        }
                        
                        // Priority 3: Physical Examination (check for specific deferral types first)
                        if (physicianPhysicalLower.includes('permanently deferred')) {
                            return { status: 'Permanently Deferred', stage: 'Physical Examination', color: 'danger' };
                        }
                        if (physicianPhysicalLower.includes('temporarily deferred')) {
                            return { status: 'Temporarily Deferred', stage: 'Physical Examination', color: 'warning' };
                        }
                        if (physicianPhysicalLower.includes('refused')) {
                            return { status: 'Refused', stage: 'Physical Examination', color: 'danger' };
                        }
                        if (physicianPhysicalLower.includes('declined') || physicianPhysicalLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'Physical Examination', color: 'danger' };
                        }
                        
                        // Priority 4: Blood Collection (should rarely happen if other checks work)
                        if (phlebStatusLower.includes('declined') || phlebStatusLower.includes('defer') || phlebStatusLower.includes('refused') || phlebStatusLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'Blood Collection', color: 'danger' };
                        }
                        
                        // Check if all processes are complete
                        const isMedicalHistoryComplete = interviewerMedicalLower.includes('approved') || interviewerMedicalLower.includes('completed');
                        const isScreeningPassed = interviewerScreeningLower.includes('passed') || interviewerScreeningLower.includes('approved');
                        const isPhysicalExamAccepted = physicianPhysicalLower.includes('accepted') || physicianPhysicalLower.includes('approved');
                        const isBloodCollectionSuccessful = phlebStatusLower.includes('successful') || phlebStatusLower.includes('approved');
                        
                        // If all processes are complete, show Approved
                        if (isMedicalHistoryComplete && isScreeningPassed && isPhysicalExamAccepted && isBloodCollectionSuccessful) {
                            return { status: 'Approved', stage: 'All Stages', color: 'success' };
                        }
                        
                        // Check eligibility status from database
                        if (statusLower === 'approved' || statusLower === 'eligible') {
                            return { status: 'Approved', stage: 'All Stages', color: 'success' };
                        }
                        if (statusLower === 'pending') {
                            return { status: 'Pending', stage: 'In Progress', color: 'warning' };
                        }
                        return { status: eligibilityStatus || 'Unknown', stage: 'Unknown', color: 'secondary' };
                    };

                    const overallStatus = getOverallStatus();
                    const statusSummary = `
                        <div class="card mb-3" style="border-left: 4px solid var(--bs-${overallStatus.color});">
                            <div class="card-body py-2 px-3">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1" style="font-weight: 600; color: #212529;">Overall Status</h6>
                                        <span class="badge bg-${overallStatus.color} me-2">${overallStatus.status}</span>
                                        <small class="text-muted">${overallStatus.stage}</small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <small class="text-muted">Eligibility ID: ${safe(eligibility.eligibility_id, 'N/A')}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    // Get screening form data (medicalHistoryData already declared above)
                    const screeningForm = data.screening_form || {};
                    
                    // Create sections
                    const interviewerRows = createInterviewerRows(donor, eligibility, interviewerMedical, interviewerScreening, screeningForm, medicalHistoryData);
                    const physicianPhlebotomistSections = createPhysicianPhlebotomistSections(donor, eligibility, interviewerMedical, interviewerScreening, physicianMedical, physicianPhysical, phlebStatus);

                    // Generate final HTML
                    const html = `
                        ${header}
                        ${statusSummary}
                        ${createSection('Interviewer', interviewerRows, 'bg-danger')}
                        ${physicianPhlebotomistSections}
                    `;

                    // Debug: Log the phlebotomist section HTML before rendering
                    console.log('Phlebotomist Section HTML:', physicianPhlebotomistSections);
                    console.log('Final phlebStatus being used:', phlebStatus);
                    
                    donorDetailsContainer.innerHTML = html;
                    
                    // Debug: Verify what was actually rendered
                    setTimeout(() => {
                        const renderedPhlebStatus = document.querySelector('.phlebotomist-section .status-cell, [class*="phlebotomist"] .status-cell');
                        if (renderedPhlebStatus) {
                            console.log('Rendered phlebotomist status element:', renderedPhlebStatus.innerHTML);
                        } else {
                            console.log('Could not find rendered phlebotomist status element');
                        }
                    }, 100);

                    // Store current donor info for admin actions
                    window.currentDonorId = donorId;
                    window.currentEligibilityId = eligibilityId;

                    // Hide approve CTA in footer when fully approved (view-only state)
                    try {
                        const approveBtn = document.getElementById('Approve');
                        if (approveBtn) approveBtn.style.display = isFullyApproved ? 'none' : '';
                    } catch (_) {}
                })
                .catch(error => {
                    console.error('Error fetching donor details:', error);
                    const errorContainer = document.getElementById('donorDetails');
                    if (errorContainer) {
                        errorContainer.innerHTML = '<div class="alert alert-danger">Error loading donor details. Please try again.</div>';
                    }
                });
            }, 0); // Use 0 delay to ensure modal renders first, then fetch
        },

        // Get current donor ID
        getCurrentDonorId: function() {
            return currentDonorId;
        },

        // Get current eligibility ID
        getCurrentEligibilityId: function() {
            return currentEligibilityId;
        }
    };
})();

// Function to view initial screening (called from donor modal)
window.viewInitialScreening = function(donorId) {
    console.log('=== VIEWING INITIAL SCREENING ===');
    console.log('Donor ID:', donorId);
    
    const eligibilityId = window.currentEligibilityId || 'pending_' + donorId;
    console.log('Eligibility ID:', eligibilityId);
    
    // Show the screening summary modal
    window.showScreeningSummary(donorId, eligibilityId);
};

// Function to open blood collection view modal
window.openBloodCollectionView = async function(donorId) {
    console.log('[Admin] Opening blood collection view for donor:', donorId);
    
    try {
        // Step 1: Get physical_exam_id
        const examResp = await fetch(`../../assets/php_func/admin/get_physical_exam_details_admin.php?donor_id=${encodeURIComponent(donorId)}`);
        if (!examResp.ok) {
            throw new Error('Failed to fetch physical exam details');
        }
        
        const examData = await examResp.json();
        if (!examData.success || !examData.data || !examData.data.physical_exam_id) {
            console.error('[Admin] No physical exam found for donor:', donorId);
            alert('No physical examination found for this donor');
            return;
        }
        
        const physicalExamId = examData.data.physical_exam_id;
        console.log('[Admin] Found physical_exam_id:', physicalExamId);
        
        // Step 2: Get blood collection data - use direct Supabase query
        // Create collection data object with the physical_exam_id
        const collectionData = {
            physical_exam_id: physicalExamId,
            donor_id: donorId
        };
        
        console.log('[Admin] Opening view modal with collection data:', collectionData);
        
        // Step 3: Open the view modal - fetch the actual collection data in the modal
        if (window.bloodCollectionViewModalAdmin && typeof window.bloodCollectionViewModalAdmin.openModal === 'function') {
            await window.bloodCollectionViewModalAdmin.openModal(collectionData);
        } else {
            console.error('[Admin] bloodCollectionViewModalAdmin not available');
            alert('Blood collection view modal not available');
        }
        
    } catch (error) {
        console.error('[Admin] Error opening blood collection view:', error);
        alert('Error loading blood collection data: ' + error.message);
    }
};

// Function to refresh donor details modal after medical history approval/decline
function refreshDonorDetailsAfterMHApproval(donorId) {
    console.log('Refreshing donor details after medical history approval/decline for donor_id:', donorId);
    
    const eligibilityId = window.currentDetailsEligibilityId || `pending_${donorId}`;
    
    // Helper function to attempt refresh
    const attemptRefresh = () => {
        // Try using the dashboard's fetchDonorDetails function first (it has proper error handling)
        if (typeof window.fetchDonorDetails === 'function') {
            console.log('Calling window.fetchDonorDetails for donor_id:', donorId, 'eligibility_id:', eligibilityId);
            try {
                window.fetchDonorDetails(donorId, eligibilityId);
                return true;
            } catch (error) {
                console.error('Error calling fetchDonorDetails:', error);
                return false;
            }
        } else if (typeof AdminDonorModal !== 'undefined' && AdminDonorModal && AdminDonorModal.fetchDonorDetails) {
            // Direct call to AdminDonorModal (matching dashboard's check pattern)
            console.log('Calling AdminDonorModal.fetchDonorDetails directly for donor_id:', donorId, 'eligibility_id:', eligibilityId);
            try {
                AdminDonorModal.fetchDonorDetails(donorId, eligibilityId);
                return true;
            } catch (error) {
                console.error('Error calling AdminDonorModal.fetchDonorDetails:', error);
                return false;
            }
        } else if (typeof window.AdminDonorModal !== 'undefined' && window.AdminDonorModal && window.AdminDonorModal.fetchDonorDetails) {
            // Fallback: try window.AdminDonorModal
            console.log('Calling window.AdminDonorModal.fetchDonorDetails for donor_id:', donorId, 'eligibility_id:', eligibilityId);
            try {
                window.AdminDonorModal.fetchDonorDetails(donorId, eligibilityId);
                return true;
            } catch (error) {
                console.error('Error calling window.AdminDonorModal.fetchDonorDetails:', error);
                return false;
            }
        }
        return false;
    };
    
    // Small delay to ensure database has time to update
    setTimeout(() => {
        // Try to refresh immediately
        if (attemptRefresh()) {
            return; // Success
        }
        
        // If not available, wait a bit more and try again (module might still be loading)
        let attempts = 0;
        const maxAttempts = 5;
        const retryInterval = 200; // 200ms between attempts
        
        const retryRefresh = setInterval(() => {
            attempts++;
            console.log(`Retry attempt ${attempts} to refresh donor details...`);
            
            if (attemptRefresh()) {
                clearInterval(retryRefresh);
                return; // Success
            }
            
            if (attempts >= maxAttempts) {
                clearInterval(retryRefresh);
                console.warn('AdminDonorModal not available after multiple attempts, reloading page');
                window.location.reload();
            }
        }, retryInterval);
    }, 800); // Initial delay to ensure database update
}

// Function to handle medical history approval/decline from interviewer section
window.handleMedicalHistoryApprovalFromInterviewer = function(donorId, action) {
    console.log(`Handling medical history ${action} for donor:`, donorId);
    
    const currentDonorId = donorId;
    
    // Function to close the medical history modal
    const closeMedicalHistoryModal = () => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryModal'));
        if (modal) {
            modal.hide();
        } else {
            // Fallback: manual close
            const modalEl = document.getElementById('medicalHistoryModal');
            if (modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                document.body.classList.remove('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
            }
        }
    };
    
    if (action === 'approve') {
        const proceedApproval = () => {
            const approveBtn = document.getElementById('viewMHApproveBtn');
            const originalText = approveBtn ? approveBtn.innerHTML : '';
            if (approveBtn) {
                approveBtn.disabled = true;
                approveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            }
            
            // Use the process_medical_history_approval.php endpoint
            const formData = new FormData();
            formData.append('action', 'approve_medical_history');
            formData.append('donor_id', donorId);
            
            fetch(`../../assets/php_func/admin/process_medical_history_approval_admin.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (approveBtn) {
                    approveBtn.disabled = false;
                    approveBtn.innerHTML = originalText;
                }
                
                if (data.success) {
                    console.log('Medical history approved successfully');
                    // Refresh donor modal if it's open
                    const donorModal = document.getElementById('donorModal');
                    if (donorModal && donorModal.classList.contains('show')) {
                        const eligibilityId = window.currentDetailsEligibilityId || window.currentEligibilityId || `pending_${currentDonorId}`;
                        if (typeof AdminDonorModal !== 'undefined' && AdminDonorModal && AdminDonorModal.fetchDonorDetails) {
                            setTimeout(() => {
                                AdminDonorModal.fetchDonorDetails(currentDonorId, eligibilityId);
                            }, 500);
                        } else if (typeof window.fetchDonorDetails === 'function') {
                            setTimeout(() => {
                                window.fetchDonorDetails(currentDonorId, eligibilityId);
                            }, 500);
                        }
                    }
                    // Close approve confirmation modal if open
                    const approveModal = bootstrap.Modal.getInstance(document.getElementById('medicalHistoryApproveConfirmModal'));
                    if (approveModal) {
                        approveModal.hide();
                    }
                    // Close medical history modal
                    closeMedicalHistoryModal();
                    // Refresh donor details - no success modal needed
                    refreshDonorDetailsAfterMHApproval(currentDonorId);
                } else {
                    alert('Failed to approve medical history: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                if (approveBtn) {
                    approveBtn.disabled = false;
                    approveBtn.innerHTML = originalText;
                }
                console.error('Error approving medical history:', error);
                alert('Error approving medical history: ' + error.message);
            });
        };

        // Use the medicalHistoryApproveConfirmModal (red gradient modal with "Please Confirm")
        const approveModal = document.getElementById('medicalHistoryApproveConfirmModal');
        if (approveModal) {
            // Use the modal stacking utility if available, otherwise calculate dynamically
            if (typeof applyModalStacking === 'function') {
                applyModalStacking(approveModal);
            } else {
                // Fallback: Calculate z-index based on open modals
                const openModals = document.querySelectorAll('.modal.show, .medical-history-modal.show');
                let maxZIndex = 1050;
                openModals.forEach(m => {
                    if (m === approveModal) return;
                    const z = parseInt(window.getComputedStyle(m).zIndex) || parseInt(m.style.zIndex) || 0;
                    if (z > maxZIndex) maxZIndex = z;
                });
                const newZIndex = maxZIndex + 10;
                approveModal.style.zIndex = newZIndex.toString();
                approveModal.style.position = 'fixed';
                const dialog = approveModal.querySelector('.modal-dialog');
                if (dialog) dialog.style.zIndex = (newZIndex + 1).toString();
                const content = approveModal.querySelector('.modal-content');
                if (content) content.style.zIndex = (newZIndex + 2).toString();
                
                setTimeout(() => {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 0) {
                        backdrops[backdrops.length - 1].style.zIndex = (newZIndex - 1).toString();
                    }
                }, 10);
            }
            
            const modal = bootstrap.Modal.getOrCreateInstance(approveModal);
            modal.show();
            
            // Bind confirmation handler - remove any existing handlers first
            const confirmBtn = document.getElementById('confirmApproveMedicalHistoryBtn');
            if (confirmBtn) {
                // Remove existing event listeners by cloning
                const newConfirmBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                
                newConfirmBtn.addEventListener('click', function() {
                    proceedApproval();
                    modal.hide();
                });
            }
        } else if (window.adminModal && typeof window.adminModal.confirm === 'function') {
            window.adminModal.confirm('Are you sure you want to approve this donor\'s medical history?', proceedApproval, {
                confirmText: 'Approve',
                cancelText: 'Keep Reviewing'
            });
        } else {
            proceedApproval();
        }
    } else if (action === 'decline') {
        // Use the correct medicalHistoryDeclineModal (from medical-history-approval-modals.php)
        // This modal has deferral type and duration options like the staff dashboard
        if (typeof showMedicalHistoryDeclineModal === 'function') {
            showMedicalHistoryDeclineModal(donorId);
        } else {
            // Fallback: try to open modal directly
            const declineModal = document.getElementById('medicalHistoryDeclineModal');
            if (declineModal) {
                window.currentDonorId = donorId;
                
                // Set donor ID in hidden input
                const donorIdInput = declineModal.querySelector('input[name="donor_id"]');
                if (!donorIdInput) {
                    const form = declineModal.querySelector('form') || declineModal.querySelector('.modal-body');
                    if (form) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'donor_id';
                        hiddenInput.value = donorId;
                        form.appendChild(hiddenInput);
                    }
                } else {
                    donorIdInput.value = donorId;
                }
                
                if (typeof applyModalStacking === 'function') {
                    applyModalStacking(declineModal);
                }
                
                const modal = new bootstrap.Modal(declineModal);
                modal.show();
                
                // Initialize submit handler
                declineModal.addEventListener('shown.bs.modal', function() {
                    setTimeout(() => {
                        const submitDeclineBtn = document.getElementById('submitDeclineBtn');
                        if (submitDeclineBtn && typeof handleMedicalHistoryDeclineSubmit === 'function') {
                            const newSubmitBtn = submitDeclineBtn.cloneNode(true);
                            submitDeclineBtn.parentNode.replaceChild(newSubmitBtn, submitDeclineBtn);
                            newSubmitBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                handleMedicalHistoryDeclineSubmit();
                            });
                        }
                    }, 100);
                }, { once: true });
            } else {
                console.error('Medical history decline modal not found');
                alert('Error: Decline modal not found. Please refresh the page.');
            }
        }
    }
};

// Function to open physical examination form (edit mode)
window.openPhysicalExaminationForm = function(donorId) {
    console.log('[Admin] Opening physical examination form for donor:', donorId);
    
    // Get screening data for the donor
    fetch(`../../assets/php_func/get_screening_details.php?donor_id=${encodeURIComponent(donorId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const screeningData = data.data;
                // Open physical examination modal
                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                    window.physicalExaminationModalAdmin.openModal(screeningData);
                } else {
                    console.error('[Admin] physicalExaminationModalAdmin not available');
                    alert('Physical examination modal not available');
                }
            } else {
                // If no screening data, create a basic screening data object
                const screeningData = {
                    donor_form_id: donorId
                };
                if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                    window.physicalExaminationModalAdmin.openModal(screeningData);
                } else {
                    console.error('[Admin] physicalExaminationModalAdmin not available');
                    alert('Physical examination modal not available');
                }
            }
        })
        .catch(error => {
            console.error('[Admin] Error fetching screening data:', error);
            // Try to open modal anyway with basic data
            const screeningData = {
                donor_form_id: donorId
            };
            if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                window.physicalExaminationModalAdmin.openModal(screeningData);
            } else {
                alert('Error loading physical examination: ' + error.message);
            }
        });
};

// Function to open physical examination view (read-only mode) - Uses compact summary modal
window.openPhysicalExaminationView = function(donorId) {
    console.log('[Admin] Opening physical examination view for donor:', donorId);
    
    // Get physical exam data
    fetch(`../../assets/php_func/admin/get_physical_exam_details_admin.php?donor_id=${encodeURIComponent(donorId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const examData = data.data;
                // Check if we actually have physical examination data (not just null)
                if (!examData || Object.keys(examData).length === 0) {
                    console.warn('[Admin] Physical examination data is empty for donor:', donorId);
                    alert('No physical examination found for this donor');
                    return;
                }
                console.log('[Admin] Physical examination data loaded:', examData);
                
                // Use the compact physicianSectionModal (matching Initial Screening Form style)
                // This is the standalone view modal, not the edit modal with steps
                if (typeof window.viewPhysicianDetails === 'function') {
                    window.viewPhysicianDetails(donorId);
                    console.log('[Admin] Opened compact physical examination summary modal via viewPhysicianDetails');
                } else {
                    console.error('[Admin] viewPhysicianDetails function not found, falling back to form modal');
                    // Fallback to form modal if viewPhysicianDetails doesn't exist
                    if (window.physicalExaminationModalAdmin && typeof window.physicalExaminationModalAdmin.openModal === 'function') {
                        examData.viewMode = true;
                        window.physicalExaminationModalAdmin.openModal(examData);
                    } else {
                        alert('Physical examination modal not available');
                    }
                }
            } else {
                console.warn('[Admin] API returned unsuccessful response:', data);
                alert('No physical examination found for this donor');
            }
        })
        .catch(error => {
            console.error('[Admin] Error fetching physical exam data:', error);
            alert('Error loading physical examination: ' + error.message);
        });
};
