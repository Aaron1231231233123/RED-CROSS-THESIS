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
        const phlebotomistActionButton = (() => {
            const physicianCompleted = (interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-') &&
                                    (interviewerScreening.toLowerCase() !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-') &&
                                    (physicianMedical.toLowerCase() !== 'pending' && physicianMedical !== '' && physicianMedical !== '-') &&
                                    (physicianPhysical.toLowerCase() !== 'pending' && physicianPhysical !== '' && physicianPhysical !== '-');
            
            const isPendingBloodCollection = physicianCompleted &&
                                           (phlebStatus.toLowerCase() === 'pending' || phlebStatus === '' || phlebStatus === '-');
            const isCompletedBloodCollection = physicianCompleted &&
                                             phlebStatus.toLowerCase() !== 'pending' && phlebStatus !== '' && phlebStatus !== '-';
            
            let actionButton = '';
            if (!physicianCompleted) {
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
                                    <td class="text-center status-cell py-2">${getStatusDisplay(phlebStatus, 'Blood Collection')}</td>
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
                    
                    // Determine interviewer medical status
                    let interviewerMedicalStatus = safe(eligibility.medical_history_status);
                    
                    // Priority: Approved > Completed (if admin completed) > eligibility status
                    if (medicalApproval.toLowerCase() === 'approved') {
                        interviewerMedicalStatus = 'Approved';
                    } else if (isAdminCompleted || hasMedicalHistory) {
                        // If admin completed or medical history exists, show "Completed"
                        interviewerMedicalStatus = 'Completed';
                    } else if (!interviewerMedicalStatus || interviewerMedicalStatus === '-' || interviewerMedicalStatus === '') {
                        interviewerMedicalStatus = 'Pending';
                    }
                    
                    const interviewerMedical = interviewerMedicalStatus;
                    const interviewerScreening = safe(eligibility.screening_status);
                    const physicianMedical = safe(eligibility.review_status);
                    const physicianPhysical = safe(eligibility.physical_status);
                    const phlebStatus = safe(eligibility.collection_status);
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
                    const getOverallStatus = () => {
                        const statusLower = String(eligibilityStatus || '').toLowerCase();
                        const interviewerMedicalLower = String(interviewerMedical || '').toLowerCase();
                        const interviewerScreeningLower = String(interviewerScreening || '').toLowerCase();
                        const physicianMedicalLower = String(physicianMedical || '').toLowerCase();
                        const physicianPhysicalLower = String(physicianPhysical || '').toLowerCase();
                        const phlebStatusLower = String(phlebStatus || '').toLowerCase();

                        // Check for declined/deferred/refused status in each stage
                        if (interviewerMedicalLower.includes('declined') || interviewerMedicalLower.includes('defer') || interviewerMedicalLower.includes('refused') || interviewerMedicalLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'MH', color: 'danger' };
                        }
                        if (interviewerScreeningLower.includes('declined') || interviewerScreeningLower.includes('defer') || interviewerScreeningLower.includes('refused') || interviewerScreeningLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'Initial Screening', color: 'danger' };
                        }
                        if (physicianMedicalLower.includes('declined') || physicianMedicalLower.includes('defer') || physicianMedicalLower.includes('refused') || physicianMedicalLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'MH', color: 'danger' };
                        }
                        if (physicianPhysicalLower.includes('permanently deferred')) {
                            return { status: 'Permanently Deferred', stage: 'Physical Exam', color: 'danger' };
                        }
                        if (physicianPhysicalLower.includes('temporarily deferred')) {
                            return { status: 'Temporarily Deferred', stage: 'Physical Exam', color: 'warning' };
                        }
                        if (physicianPhysicalLower.includes('refused')) {
                            return { status: 'Refused', stage: 'Physical Exam', color: 'danger' };
                        }
                        if (physicianPhysicalLower.includes('declined') || physicianPhysicalLower.includes('defer') || physicianPhysicalLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'Physical Exam', color: 'danger' };
                        }
                        if (phlebStatusLower.includes('declined') || phlebStatusLower.includes('defer') || phlebStatusLower.includes('refused') || phlebStatusLower.includes('not approved')) {
                            return { status: 'Declined/Not Approved', stage: 'Blood Collection', color: 'danger' };
                        }
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

                    donorDetailsContainer.innerHTML = html;

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
                    document.getElementById('donorDetails').innerHTML = '<div class="alert alert-danger">Error loading donor details. Please try again.</div>';
                });
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
        // Show confirmation first
        if (confirm('Are you sure you want to approve this donor\'s medical history?')) {
            // Show loading state
            const approveBtn = document.getElementById('viewMHApproveBtn');
            const originalText = approveBtn ? approveBtn.innerHTML : '';
            if (approveBtn) {
                approveBtn.disabled = true;
                approveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            }
            
            // Direct API call to update medical history
            fetch(`../../assets/php_func/update_medical_history.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    medical_approval: 'Approved',
                    updated_at: new Date().toISOString()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (approveBtn) {
                    approveBtn.disabled = false;
                    approveBtn.innerHTML = originalText;
                }
                
                if (data.success) {
                    console.log('Medical history approved successfully');
                    // Close modal first
                    closeMedicalHistoryModal();
                    // Then refresh donor details modal to show updated status
                    setTimeout(() => {
                        refreshDonorDetailsAfterMHApproval(currentDonorId);
                    }, 300);
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
        }
    } else if (action === 'decline') {
        // Get decline reason
        const reason = prompt('Please provide a reason for declining this donor\'s medical history:');
        if (reason && reason.trim()) {
            // Show loading state
            const declineBtn = document.getElementById('viewMHDeclineBtn');
            const originalText = declineBtn ? declineBtn.innerHTML : '';
            if (declineBtn) {
                declineBtn.disabled = true;
                declineBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            }
            
            // Direct API call to update medical history
            fetch(`../../assets/php_func/update_medical_history.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    donor_id: donorId,
                    medical_approval: 'Declined',
                    disapproval_reason: reason.trim(),
                    updated_at: new Date().toISOString()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (declineBtn) {
                    declineBtn.disabled = false;
                    declineBtn.innerHTML = originalText;
                }
                
                if (data.success) {
                    console.log('Medical history declined successfully');
                    // Close modal first
                    closeMedicalHistoryModal();
                    // Then refresh donor details modal to show updated status
                    setTimeout(() => {
                        refreshDonorDetailsAfterMHApproval(currentDonorId);
                    }, 300);
                } else {
                    alert('Failed to decline medical history: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                if (declineBtn) {
                    declineBtn.disabled = false;
                    declineBtn.innerHTML = originalText;
                }
                console.error('Error declining medical history:', error);
                alert('Error declining medical history: ' + error.message);
            });
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
                
                // Use the compact summary modal instead of the form modal
                const summaryModal = document.getElementById('staffPhysicianAccountSummaryModal');
                if (summaryModal) {
                    // Populate the compact summary modal with data
                    if (examData.blood_pressure) {
                        const bpEl = document.getElementById('summary-view-blood-pressure');
                        if (bpEl) bpEl.textContent = examData.blood_pressure;
                    }
                    
                    if (examData.pulse_rate) {
                        const pulseEl = document.getElementById('summary-view-pulse-rate');
                        if (pulseEl) pulseEl.textContent = examData.pulse_rate;
                    }
                    
                    if (examData.body_temp) {
                        const tempEl = document.getElementById('summary-view-body-temp');
                        if (tempEl) tempEl.textContent = examData.body_temp;
                    }
                    
                    if (examData.gen_appearance) {
                        const genAppEl = document.getElementById('summary-view-gen-appearance');
                        if (genAppEl) genAppEl.textContent = examData.gen_appearance;
                    }
                    
                    if (examData.skin) {
                        const skinEl = document.getElementById('summary-view-skin');
                        if (skinEl) skinEl.textContent = examData.skin;
                    }
                    
                    if (examData.heent) {
                        const heentEl = document.getElementById('summary-view-heent');
                        if (heentEl) heentEl.textContent = examData.heent;
                    }
                    
                    if (examData.heart_and_lungs) {
                        const heartLungsEl = document.getElementById('summary-view-heart-lungs');
                        if (heartLungsEl) heartLungsEl.textContent = examData.heart_and_lungs;
                    }
                    
                    if (examData.remarks) {
                        const remarksEl = document.getElementById('summary-view-remarks');
                        if (remarksEl) remarksEl.textContent = examData.remarks;
                    }
                    
                    // Populate physician name if available
                    if (examData.physician) {
                        const physicianNameEl = document.getElementById('summary-physician-name');
                        const physicianSigEl = document.getElementById('summary-view-physician-signature');
                        if (physicianNameEl) physicianNameEl.textContent = examData.physician;
                        if (physicianSigEl) physicianSigEl.textContent = examData.physician;
                    }
                    
                    // Update examination date if available
                    if (examData.created_at || examData.updated_at) {
                        const examDate = examData.updated_at || examData.created_at;
                        if (examDate) {
                            try {
                                const dateObj = new Date(examDate);
                                const formattedDate = dateObj.toLocaleDateString('en-US', { 
                                    year: 'numeric', 
                                    month: 'long', 
                                    day: 'numeric' 
                                });
                                const dateEl = summaryModal.querySelector('.report-date');
                                if (dateEl) dateEl.textContent = formattedDate;
                            } catch (e) {
                                console.warn('[Admin] Could not format examination date:', e);
                            }
                        }
                    }
                    
                    // Open the compact summary modal
                    const modal = bootstrap.Modal.getOrCreateInstance(summaryModal, { backdrop: 'static', keyboard: false });
                    modal.show();
                    console.log('[Admin] Opened compact physical examination summary modal');
                } else {
                    console.error('[Admin] Compact summary modal not found, falling back to form modal');
                    // Fallback to form modal if compact modal doesn't exist
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
