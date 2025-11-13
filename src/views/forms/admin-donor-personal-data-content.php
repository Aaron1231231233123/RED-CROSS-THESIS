<?php
/**
 * Admin Donor Personal Data Form Content
 * Step 1 of admin registration flow
 * This is a partial that returns only the form HTML (no <html>, <head>, <body> tags)
 */

// This file is included by the API endpoint, so session should already be started
// No functions needed here - they're handled by the submit API
?>

<!-- Personal Data Form -->
<form id="adminDonorPersonalDataForm" method="post">
    <!-- Progress Steps -->
    <div class="steps-container mb-4">
        <div class="step-item">
            <div class="step-number active" id="step1">1</div>
        </div>
        <div class="step-line" id="line1-2"></div>
        <div class="step-item">
            <div class="step-number inactive" id="step2">2</div>
        </div>
        <div class="step-line" id="line2-3"></div>
        <div class="step-item">
            <div class="step-number inactive" id="step3">3</div>
        </div>
        <div class="step-line" id="line3-4"></div>
        <div class="step-item">
            <div class="step-number inactive" id="step4">4</div>
        </div>
        <div class="step-line" id="line4-5"></div>
        <div class="step-item">
            <div class="step-number inactive" id="step5">5</div>
        </div>
    </div>
    
    <!-- Section 1: NAME -->
    <div class="form-section active" id="section1">
        <h3 class="section-title">NAME</h3>
        <p class="section-details">Complete the details below.</p>
        <div class="horizontal-line"></div>
        
        <div class="mb-3">
            <label for="surname" class="form-label">Surname <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="surname" name="surname" required>
        </div>
        
        <div class="mb-3">
            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="first_name" name="first_name" required>
        </div>
        
        <div class="mb-3">
            <label for="middle_name" class="form-label">Middle Name</label>
            <input type="text" class="form-control" id="middle_name" name="middle_name">
        </div>
        
        <div class="navigation-buttons">
            <div></div>
            <button type="button" class="btn btn-primary btn-navigate" onclick="adminRegistrationNextSection(1)">Next &gt;</button>
        </div>
    </div>
    
    <!-- Section 2: PROFILE DETAILS -->
    <div class="form-section" id="section2">
        <h3 class="section-title">PROFILE DETAILS</h3>
        <p class="section-details">Complete the details below.</p>
        <div class="horizontal-line"></div>
        
        <div class="row mb-3">
            <div class="col-md-6 mb-3">
                <label for="birthdate" class="form-label">Birthdate <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="birthdate" name="birthdate" onchange="adminRegistrationCalculateAge()" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="age" class="form-label">Age</label>
                <input type="number" class="form-control" id="age" name="age" readonly>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="civil_status" class="form-label">Civil Status <span class="text-danger">*</span></label>
            <select class="form-select" id="civil_status" name="civil_status" required>
                <option value="" selected disabled>Select Civil Status</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Divorced">Divorced</option>
                <option value="Widowed">Widowed</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="sex" class="form-label">Sex <span class="text-danger">*</span></label>
            <select class="form-select" id="sex" name="sex" required>
                <option value="" selected disabled>Select Sex</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>
        
        <div class="navigation-buttons">
            <button type="button" class="btn btn-secondary btn-navigate" onclick="adminRegistrationPrevSection(2)">&lt; Previous</button>
            <button type="button" class="btn btn-primary btn-navigate" onclick="adminRegistrationNextSection(2)">Next &gt;</button>
        </div>
    </div>
    
    <!-- Section 3: PERMANENT ADDRESS -->
    <div class="form-section" id="section3">
        <h3 class="section-title">PERMANENT ADDRESS</h3>
        <p class="section-details">Complete the details below.</p>
        <div class="horizontal-line"></div>

        <!-- Compact top row: Province, Municipality, Barangay -->
        <div class="row mb-3">
            <div class="col-md-4 mb-3">
                <label for="province_city" class="form-label">Province/City <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="province_city" name="province_city" list="provinceList" autocomplete="off" required>
                <datalist id="provinceList"></datalist>
            </div>
            <div class="col-md-4 mb-3">
                <label for="town_municipality" class="form-label">Town/Municipality <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="town_municipality" name="town_municipality" list="municipalityList" autocomplete="off" required>
                <datalist id="municipalityList"></datalist>
            </div>
            <div class="col-md-4 mb-3">
                <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="barangay" name="barangay" list="barangayList" autocomplete="off" required>
                <datalist id="barangayList"></datalist>
            </div>
        </div>

        <!-- Street on its own row for clarity -->
        <div class="mb-3">
            <label for="street" class="form-label">Street</label>
            <input type="text" class="form-control" id="street" name="street" autocomplete="off">
        </div>

        <!-- Compact bottom row: House/Unit No. and ZIP -->
        <div class="row mb-3">
            <div class="col-md-6 mb-3">
                <label for="address_no" class="form-label">House/Unit No.</label>
                <input type="text" class="form-control" id="address_no" name="address_no">
            </div>
                            <div class="col-md-6 mb-3 position-relative">
                                <label for="zip_code" class="form-label">ZIP Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="zip_code" name="zip_code" autocomplete="off" required maxlength="4" pattern="[0-9]{4}" placeholder="e.g., 5000">
                                <div id="zipCodeSuggestions" class="list-group position-absolute w-100" style="z-index: 1050; max-height: 220px; overflow-y: auto; display:none; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>
                                <small class="form-text text-muted">4-digit ZIP code (auto-filled from address or type to search)</small>
                            </div>
        </div>

        <div class="navigation-buttons">
            <button type="button" class="btn btn-secondary btn-navigate" onclick="adminRegistrationPrevSection(3)">&lt; Previous</button>
            <button type="button" class="btn btn-primary btn-navigate" onclick="adminRegistrationNextSection(3)">Next &gt;</button>
        </div>
    </div>
    
    <!-- Section 4: ADDITIONAL INFORMATION -->
    <div class="form-section" id="section4">
        <h3 class="section-title">ADDITIONAL INFORMATION</h3>
        <p class="section-details">Complete the details below.</p>
        <div class="horizontal-line"></div>
        
        <div class="mb-3">
            <label for="nationality" class="form-label">Nationality <span class="text-danger">*</span></label>
            <select class="form-select" id="nationality" name="nationality" required style="appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-right:36px;background:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2214%22 height=%2214%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23666%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>') no-repeat right 12px center/14px;">
                <option value="" selected disabled>Select Nationality</option>
                <option value="Filipino">Filipino</option>
                <option value="American">American</option>
                <option value="Chinese">Chinese</option>
                <option value="Japanese">Japanese</option>
                <option value="Malaysian">Malaysian</option>
                <option value="Others">Others</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="religion" class="form-label">Religion <span class="text-danger">*</span></label>
            <select class="form-select" id="religion" name="religion" required style="appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-right:36px;background:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2214%22 height=%2214%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23666%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>') no-repeat right 12px center/14px;">
                <option value="" selected disabled>Select Religion</option>
                <option value="Roman Catholic">Roman Catholic</option>
                <option value="Christianity">Christianity</option>
                <option value="Islam">Islam</option>
                <option value="Others">Others</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="education" class="form-label">Education <span class="text-danger">*</span></label>
            <select class="form-select" id="education" name="education" required style="appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-right:36px;background:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2214%22 height=%2214%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23666%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>') no-repeat right 12px center/14px;">
                <option value="" selected disabled>Select Education</option>
                <option value="Elementary">Elementary</option>
                <option value="High School">High School</option>
                <option value="College">College</option>
                <option value="Graduate">Graduate</option>
                <option value="Post Graduate">Post Graduate</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="occupation" class="form-label">Occupation <span class="text-danger">*</span></label>
            <select class="form-select" id="occupation" name="occupation" required style="appearance:none;-webkit-appearance:none;-moz-appearance:none;padding-right:36px;background:url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2214%22 height=%2214%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23666%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><polyline points=%226 9 12 15 18 9%22/></svg>') no-repeat right 12px center/14px;">
                <option value="" selected disabled>Select Occupation</option>
                <option value="Employed">Employed</option>
                <option value="Unemployed">Unemployed</option>
                <option value="Student">Student</option>
                <option value="Retired">Retired</option>
            </select>
        </div>
        
        <div class="navigation-buttons">
            <button type="button" class="btn btn-secondary btn-navigate" onclick="adminRegistrationPrevSection(4)">&lt; Previous</button>
            <button type="button" class="btn btn-primary btn-navigate" onclick="adminRegistrationNextSection(4)">Next &gt;</button>
        </div>
    </div>
    
    <!-- Section 5: CONTACT INFORMATION -->
    <div class="form-section" id="section5">
        <h3 class="section-title">CONTACT INFORMATION</h3>
        <p class="section-details">Complete the details below.</p>
        <div class="horizontal-line"></div>
        
        <div class="mb-3">
            <label for="mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="mobile" name="mobile" required>
        </div>
        
        <div class="mb-3">
            <label for="email" class="form-label">
                Email <span class="text-danger">*</span>
                <small class="text-muted d-block">Required for mobile app account generation</small>
            </label>
            <input type="email" class="form-control" id="email" name="email" required>
            <div class="form-text">
                <i class="fas fa-info-circle text-primary"></i>
                A mobile app account will be automatically created using this email address.
            </div>
        </div>
        
        <div class="navigation-buttons">
            <button type="button" class="btn btn-secondary btn-navigate" onclick="adminRegistrationPrevSection(5)">&lt; Previous</button>
            <button type="submit" class="btn btn-primary btn-navigate" id="submitButton">Submit &gt;</button>
        </div>
    </div>
</form>

<script>
// Note: Address autofill and age calculation are now handled by 
// admin-donor-registration-modal.js after the HTML is injected.
// This script block is kept for compatibility but the actual initialization
// happens in initializePersonalDataStep() function.

// Section navigation (handled by the modal JS)
function adminRegistrationNextSection(currentSection) {
    if (window.adminRegistrationNextSection) {
        window.adminRegistrationNextSection(currentSection);
    }
}

function adminRegistrationPrevSection(currentSection) {
    if (window.adminRegistrationPrevSection) {
        window.adminRegistrationPrevSection(currentSection);
    }
}

function adminRegistrationCalculateAge() {
    const birthdateInput = document.getElementById('birthdate');
    const ageInput = document.getElementById('age');
    
    if (!birthdateInput || !ageInput) {
        return;
    }
    
    if (!birthdateInput.value) {
        ageInput.value = '';
        return;
    }
    
    const birthdate = new Date(birthdateInput.value);
    const today = new Date();
    
    let age = today.getFullYear() - birthdate.getFullYear();
    const monthDiff = today.getMonth() - birthdate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
        age--;
    }
    
    ageInput.value = age;
}
</script>

