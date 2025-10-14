// Donor Information Medical Modal JavaScript
// Handles the display and functionality of the donor information medical modal

// Global variable to store current donor data
let currentDonorMedicalData = null;

// Function to show the donor information medical modal
function showDonorInformationMedicalModal(eligibilityId) {
    
    // Fetch donor medical information
    fetchDonorMedicalInfo(eligibilityId)
        .then(data => {
            if (data && data.success) {
                currentDonorMedicalData = data.data;
                try {
                    displayDonorMedicalInfo(data.data);
                    const modal = new bootstrap.Modal(document.getElementById('donorInformationMedicalModal'));
                    modal.show();
                } catch (error) {
                    console.error('Error in displayDonorMedicalInfo or modal show:', error);
                    showError('Error displaying donor information: ' + error.message);
                }
            } else {
                console.error('Failed to fetch donor medical info:', data);
                console.error('Response details:', {
                    success: data?.success,
                    message: data?.message,
                    data: data?.data
                });
                showError('Failed to load donor medical information. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error fetching donor medical info:', error);
            showError('An error occurred while loading donor information. Please try again.');
        });
}

// Function to fetch donor medical information from API
async function fetchDonorMedicalInfo(eligibilityId) {
    try {
        const response = await fetch(`../../assets/php_func/fetch_donor_medical_info.php?eligibility_id=${eligibilityId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching donor medical info:', error);
        return { success: false, message: error.message };
    }
}

// Function to display donor medical information in the modal
function displayDonorMedicalInfo(donorData) {
    const modalContent = document.getElementById('donorMedicalModalContent');
    if (!modalContent) {
        console.error('Modal content element not found');
        return;
    }
    
    // Safe function to handle null/undefined values
    const safe = (value, fallback = 'N/A') => {
        return value !== null && value !== undefined && value !== '' ? value : fallback;
    };
    
    // Format date
    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (error) {
            return dateString;
        }
    };
    
    // Calculate age from birthdate
    const calculateAge = (birthdate) => {
        if (!birthdate) return 'N/A';
        try {
            const birth = new Date(birthdate);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            return age;
        } catch (error) {
            return 'N/A';
        }
    };
    
    // Format interviewer notes in a structured manner
    const formatInterviewerNotes = (notes) => {
        if (!notes || notes === 'N/A') return 'No notes provided';
        
        // Split by periods and clean up
        const sentences = notes.split('.').filter(sentence => sentence.trim().length > 0);
        
        if (sentences.length === 0) return 'No notes provided';
        
        // Format each sentence as a structured statement
        const formattedNotes = sentences.map(sentence => {
            const trimmed = sentence.trim();
            // Capitalize first letter and ensure it ends with a period
            const capitalized = trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
            return capitalized.endsWith('.') ? capitalized : capitalized + '.';
        }).join(' ');
        
        return formattedNotes;
    };
    
    // Build the modal content HTML
    const donorMedicalHTML = `
        <div class="modal-header" style="background: #b22222; color: white; border: none; padding: 1rem 1.5rem; margin: 0;">
            <h5 class="modal-title fw-bold mb-0">
                <i class="fas fa-user-md me-2"></i>Donor Information Medical
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        
         <div class="modal-body" style="padding: 1rem; background-color: #ffffff; max-height: 70vh; overflow-y: auto;">
            <!-- Donor Header Information -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h4 class="mb-1" style="color:#b22222; font-weight:700;">
                            ${safe(donorData.surname)}, ${safe(donorData.first_name)} ${safe(donorData.middle_name)}
                        </h4>
                        <div class="text-muted fw-medium">
                            <i class="fas fa-user me-1"></i>
                            ${calculateAge(donorData.birthdate)}, ${safe(donorData.sex)}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="mb-1">
                            <div class="fw-bold text-dark mb-1">
                                <i class="fas fa-id-card me-1"></i>
                                Donor ID: ${safe(donorData.prc_donor_number)}
                            </div>
                            <div class="badge bg-danger fs-6 px-3 py-2">
                                <i class="fas fa-tint me-1"></i>${safe(donorData.blood_type)}
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-3" style="border-color: #b22222; opacity: 0.3;"/>
            </div>
            
            <!-- Donor Information Section -->
            <div class="mb-3">
                <h6 class="mb-3" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">
                    Donor Information
                </h6>
                 <div class="row g-3">
                     <!-- First row: Birthdate and Civil Status side by side -->
                     <div class="col-md-6">
                         <div class="mb-2">
                             <label class="form-label text-muted mb-1">Birthdate:</label>
                             <div class="bg-light px-3 py-2 rounded" style="border: 1px solid #e9ecef;">${formatDate(donorData.birthdate)}</div>
                         </div>
                     </div>
                     <div class="col-md-6">
                         <div class="mb-2">
                             <label class="form-label text-muted mb-1">Civil Status:</label>
                             <div class="bg-light px-3 py-2 rounded" style="border: 1px solid #e9ecef;">${safe(donorData.civil_status)}</div>
                         </div>
                     </div>
                     <!-- Second row: Address spans full width -->
                     <div class="col-12">
                         <div class="mb-2">
                             <label class="form-label text-muted mb-1">Address:</label>
                             <div class="bg-light px-3 py-2 rounded" style="border: 1px solid #e9ecef;">${safe(donorData.permanent_address)}</div>
                         </div>
                     </div>
                     <!-- Third row: Nationality, Mobile Number, and Occupation -->
                     <div class="col-md-4">
                         <div class="mb-2">
                             <label class="form-label text-muted mb-1">Nationality:</label>
                             <div class="bg-light px-3 py-2 rounded" style="border: 1px solid #e9ecef;">${safe(donorData.nationality)}</div>
                         </div>
                     </div>
                     <div class="col-md-4">
                         <div class="mb-2">
                             <label class="form-label text-muted mb-1">Mobile Number:</label>
                             <div class="bg-light px-3 py-2 rounded" style="border: 1px solid #e9ecef;">${safe(donorData.mobile)}</div>
                         </div>
                     </div>
                     <div class="col-md-4">
                         <div class="mb-2">
                             <label class="form-label text-muted mb-1">Occupation:</label>
                             <div class="bg-light px-3 py-2 rounded" style="border: 1px solid #e9ecef;">${safe(donorData.occupation)}</div>
                         </div>
                     </div>
                 </div>
            </div>
            
            <!-- Medical History Section -->
            <div class="mb-3">
                <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">
                    <i class="fas fa-clipboard-list me-2"></i>Medical History
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="border-radius: 10px; overflow: hidden;">
                        <thead style="background: #b22222 !important; color: white !important;">
                            <tr>
                                <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Medical History Result</th>
                                <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Interviewer Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center align-middle">
                                    <span class="badge ${donorData.medical_approval === 'Approved' ? 'bg-success' : donorData.medical_approval === 'Pending' ? 'bg-warning' : 'bg-secondary'} fs-6 px-3 py-2">
                                        ${safe(donorData.medical_approval)}
                                    </span>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge ${donorData.medical_notes && donorData.medical_notes.toLowerCase().includes('approved') ? 'bg-success' : 'bg-warning'} fs-6 px-3 py-2">
                                        ${safe(donorData.medical_notes)}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Initial Screening Section -->
            <div class="mb-3">
                <h6 class="mb-2" style="color:#b22222; font-weight:600; border-bottom: 2px solid #b22222; padding-bottom: 0.3rem;">
                    <i class="fas fa-stethoscope me-2"></i>Initial Screening
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" style="border-radius: 10px; overflow: hidden;">
                        <thead style="background: #b22222 !important; color: white !important;">
                            <tr>
                                <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Body Weight</th>
                                <th class="text-center" style="background: #b22222 !important; color: white !important; font-weight: 600 !important; padding: 0.75rem !important; border: none !important; vertical-align: middle !important; line-height: 1.2 !important;">Blood Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-center">${safe(donorData.body_weight)}</td>
                                <td class="text-center">${safe(donorData.blood_type)}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Additional Screening Fields -->
                <div class="row g-2 mt-2">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Type of Donation</label>
                        <div class="form-control-plaintext bg-light p-2 rounded">${safe(donorData.donation_type)}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Screening Status</label>
                        <div class="form-control-plaintext bg-light p-2 rounded">${safe(donorData.screening_status)}</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer" style="background-color: #f8f9fa; border: none; padding: 1rem 1.5rem; margin: 0;">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-1"></i>Close
            </button>
        </div>
    `;
    
    modalContent.innerHTML = donorMedicalHTML;
}

// Function to show error messages
function showError(message) {
    // You can implement a toast notification or alert here
    alert(message);
}

// Event listener for when the modal is shown
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for modal show event
    const modal = document.getElementById('donorInformationMedicalModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            currentDonorMedicalData = null;
        });
    }
});
