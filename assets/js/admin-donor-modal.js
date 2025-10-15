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

    const createInterviewerRows = (donor, eligibility, interviewerMedical, interviewerScreening) => {
        const baseUrl = '../../src/views/forms/';
        const donorId = encodeURIComponent(safe(donor.donor_id, ''));
        
        // Check individual process statuses
        const isMedicalHistoryPending = interviewerMedical.toLowerCase() === 'pending' || interviewerMedical === '' || interviewerMedical === '-';
        const isMedicalHistoryCompleted = interviewerMedical.toLowerCase() !== 'pending' && interviewerMedical !== '' && interviewerMedical !== '-';
        const isScreeningPending = interviewerScreening.toLowerCase() === 'pending' || interviewerScreening === '' || interviewerScreening === '-';
        const isScreeningCompleted = interviewerScreening.toLowerCase() !== 'pending' && interviewerScreening !== '' && interviewerScreening !== '-';
        
        // Determine action buttons for each process
        let medicalHistoryAction = '';
        let screeningAction = '';
        
        // Medical History Action
        if (isMedicalHistoryPending) {
            medicalHistoryAction = `<button type="button" class="btn btn-sm btn-outline-primary circular-btn" title="Edit Medical History" onclick="editMedicalHistory('${safe(donor.donor_id,'')}')"><i class="fas fa-pen"></i></button>`;
        } else if (isMedicalHistoryCompleted) {
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
                const pmLower = String(physicianMedical || '').toLowerCase();
                const physicianMHAccepted = (
                    pmLower.includes('approved') ||
                    pmLower.includes('accepted') ||
                    pmLower.includes('completed') ||
                    pmLower.includes('passed') ||
                    pmLower.includes('success')
                );
                const btnTitle = physicianMHAccepted ? 'View Medical History' : 'Review Medical History';
                const btnClass = physicianMHAccepted ? 'btn-outline-success' : 'btn-outline-primary';
                const icon = physicianMHAccepted ? 'fa-eye' : 'fa-pen';
                if (physicianMHAccepted) {
                    actionButton = `<button type="button" class="btn btn-sm ${btnClass} circular-btn" title="${btnTitle}" onclick="viewPhysicianDetails('${donor.donor_id || ''}')"><i class="fas ${icon}"></i></button>`;
                } else {
                    actionButton = `<button type="button" class="btn btn-sm ${btnClass} circular-btn" title="${btnTitle}" onclick="openPhysicianMedicalPreview('${donor.donor_id || ''}')"><i class="fas ${icon}"></i></button>`;
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
                actionButton = `<button type="button" class="btn btn-sm btn-outline-secondary circular-btn" title="View Not Available" disabled><i class="fas fa-eye"></i></button>`;
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

            fetch(`../../assets/php_func/donor_details_api.php?donor_id=${donorId}&eligibility_id=${eligibilityId}`)
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
                    const interviewerMedical = isAdminCompleted ? 'Completed' : safe(eligibility.medical_history_status);
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

                    // Create sections
                    const interviewerRows = createInterviewerRows(donor, eligibility, interviewerMedical, interviewerScreening);
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
