// Staff Donor Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    let currentDonorData = null;
    
    // Function to populate modal fields with donor data
    function populateModalFields(donorData) {
        if (!donorData) return;
        
        // Helper function to safely set field values
        function setFieldValue(name, value) {
            const field = document.querySelector(`[name="${name}"]`);
            if (field) {
                console.log(`Setting field ${name} to value: ${value}`);
                if (field.tagName === 'DIV' || field.tagName === 'SPAN' || field.tagName === 'H3') {
                    field.textContent = value || '-';
                } else {
                    field.value = value || '';
                }
            } else {
                console.log(`Field with name="${name}" not found`);
            }
        }
        
        // Populate donor header section
        console.log('Donor data for name:', {
            surname: donorData.surname,
            first_name: donorData.first_name,
            middle_name: donorData.middle_name
        });
        
        // Build the full name properly
        let fullName = '';
        if (donorData.surname && donorData.first_name) {
            fullName = `${donorData.surname}, ${donorData.first_name}`;
            if (donorData.middle_name) {
                fullName += ` ${donorData.middle_name}`;
            }
        } else if (donorData.first_name) {
            fullName = donorData.first_name;
            if (donorData.middle_name) {
                fullName += ` ${donorData.middle_name}`;
            }
            if (donorData.surname) {
                fullName += ` ${donorData.surname}`;
            }
        } else if (donorData.surname) {
            fullName = donorData.surname;
        } else {
            // If no name is available, show donor ID as fallback
            fullName = `Donor ID: ${donorData.donor_id || 'Unknown'}`;
        }
        
        console.log('Constructed full name:', fullName);
        
        // Debug: Check if the donor_name element exists
        const donorNameElement = document.querySelector('[name="donor_name"]');
        console.log('Donor name element found:', donorNameElement);
        console.log('Donor name element tag:', donorNameElement ? donorNameElement.tagName : 'not found');
        
        setFieldValue('donor_name', fullName);
        
        // Populate badges
        setFieldValue('age_badge', donorData.age ? `${donorData.age}` : 'N/A');
        setFieldValue('gender_badge', donorData.sex ? donorData.sex.charAt(0).toUpperCase() + donorData.sex.slice(1) : 'N/A');
        
        // Fix blood type badge - check multiple possible sources
        let bloodType = donorData.blood_type || donorData.blood_type_screening || donorData.blood_type_donor || 'N/A';
        console.log('Blood type sources:', {
            blood_type: donorData.blood_type,
            blood_type_screening: donorData.blood_type_screening,
            blood_type_donor: donorData.blood_type_donor,
            final: bloodType
        });
        
        if (bloodType === 'N/A' || bloodType === null || bloodType === '' || bloodType === undefined) {
            bloodType = 'N/A';
        }
        setFieldValue('blood_badge', bloodType);
        
        // Set screening date (using submitted_at as fallback)
        const screeningDate = donorData.screening_date || donorData.submitted_at || donorData.created_at;
        if (screeningDate) {
            const date = new Date(screeningDate);
            const formattedDate = date.toLocaleDateString('en-US', { 
                month: 'numeric', 
                day: 'numeric', 
                year: 'numeric' 
            });
            setFieldValue('screening_date', formattedDate);
        } else {
            setFieldValue('screening_date', 'N/A');
        }
        
        // Populate screening results (these would come from screening form data)
        // For now, show N/A since screening data is not available in donor form
        setFieldValue('donation_type', 'N/A');
        setFieldValue('body_weight', 'N/A');
        setFieldValue('specific_gravity', 'N/A');
        setFieldValue('blood_type', donorData.blood_type || 'N/A');
        
        // Populate contact & background
        setFieldValue('civil_status', donorData.civil_status ? donorData.civil_status.charAt(0).toUpperCase() + donorData.civil_status.slice(1) : 'N/A');
        setFieldValue('mobile', donorData.mobile || 'N/A');
        setFieldValue('occupation', donorData.occupation || 'N/A');
        setFieldValue('nationality', donorData.nationality || 'N/A');
        setFieldValue('religion', donorData.religion || 'N/A');
        setFieldValue('education', donorData.education || 'N/A');
        
        // Populate address
        setFieldValue('permanent_address', donorData.permanent_address || 'N/A');
        
        // Populate additional information
        if (donorData.birthdate) {
            const date = new Date(donorData.birthdate);
            const formattedDate = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            setFieldValue('birthdate', formattedDate);
        } else {
            setFieldValue('birthdate', 'N/A');
        }
        
        setFieldValue('office_address', donorData.office_address || 'N/A');
        setFieldValue('email', donorData.email || 'N/A');
    }

    // Function to fetch and populate screening data
    async function fetchScreeningData(donorId) {
        try {
            const response = await fetch(`../../assets/php_func/get_screening_details.php?donor_id=${donorId}`);
            if (response.ok) {
                const screeningData = await response.json();
                if (screeningData.success && screeningData.data) {
                    console.log('Screening data received:', screeningData.data);
                    
                    // Update screening fields with actual data
                    const setFieldValue = (name, value) => {
                        const field = document.querySelector(`[name="${name}"]`);
                        if (field) {
                            field.textContent = value || 'N/A';
                        }
                    };
                    
                    setFieldValue('donation_type', screeningData.data.donation_type || 'N/A');
                    setFieldValue('body_weight', screeningData.data.body_weight ? `${screeningData.data.body_weight} kg` : 'N/A');
                    setFieldValue('specific_gravity', screeningData.data.specific_gravity || 'N/A');
                    setFieldValue('blood_type', screeningData.data.blood_type || 'N/A');
                    
                    // Update blood type badge if we have screening data
                    if (screeningData.data.blood_type) {
                        const bloodBadge = document.querySelector('[name="blood_badge"]');
                        if (bloodBadge) {
                            bloodBadge.textContent = screeningData.data.blood_type;
                            console.log('Updated blood badge to:', screeningData.data.blood_type);
                        }
                    }
                } else {
                    console.log('No screening data found for donor:', donorId);
                }
            }
        } catch (error) {
            console.log('No screening data available for this donor yet:', error);
        }
    }

    // Function to show error messages
    function showError(message) {
        alert(message);
        console.error("ERROR: " + message);
    }

    // Function to show alerts
    function showAlert(message, type = 'info') {
        // Create a simple alert div
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Handle view button click to populate modal
    document.querySelectorAll('.view-donor-btn').forEach(button => {
        button.addEventListener('click', async function() {
            try {
                const donorDataStr = this.getAttribute('data-donor');
                const donorId = this.getAttribute('data-donor-id');
                
                console.log("View button clicked, data attribute value:", donorDataStr);
                console.log("Donor ID:", donorId);
                
                // Check if we have donor data
                if (!donorDataStr || donorDataStr === 'null' || donorDataStr === '{}') {
                    showError('No donor data available. Please try refreshing the page.');
                    return;
                }
                
                // Try to parse the donor data
                currentDonorData = JSON.parse(donorDataStr);
                console.log("Parsed donor data:", currentDonorData);
                console.log("Name fields check:", {
                    surname: currentDonorData.surname,
                    first_name: currentDonorData.first_name,
                    middle_name: currentDonorData.middle_name,
                    hasSurname: !!currentDonorData.surname,
                    hasFirstName: !!currentDonorData.first_name
                });
                
                // Debug: Show all available fields
                console.log("All donor data fields:", Object.keys(currentDonorData));
                console.log("Sample donor data values:", {
                    donor_id: currentDonorData.donor_id,
                    surname: currentDonorData.surname,
                    first_name: currentDonorData.first_name,
                    age: currentDonorData.age,
                    sex: currentDonorData.sex,
                    blood_type: currentDonorData.blood_type
                });
                
                // Check for donor_id
                if (!currentDonorData || !currentDonorData.donor_id) {
                    // Fallback to using the donor_id from the attribute
                    if (donorId) {
                        currentDonorData = { donor_id: donorId };
                        console.log("Using fallback donor_id:", donorId);
                    } else {
                        showError('Missing donor_id in parsed data. This will cause issues with approval.');
                        return;
                    }
                }
                
                // Show the modal first
                const modal = new bootstrap.Modal(document.getElementById('donorDetailsModal'));
                modal.show();
                
                // Wait a moment for the modal to be fully rendered, then populate
                setTimeout(() => {
                    console.log('Modal should be visible now, populating fields...');
                    populateModalFields(currentDonorData);
                }, 100);
                
                // Try to fetch screening data if available
                if (currentDonorData.donor_id) {
                    await fetchScreeningData(currentDonorData.donor_id);
                }
                
            } catch (error) {
                console.error('Error details:', error);
                showError('Error parsing donor data: ' + error.message);
            }
        });
    });

    // Handle edit button click
    document.querySelectorAll('.edit-donor-btn').forEach(button => {
        button.addEventListener('click', function() {
            const donorId = this.getAttribute('data-donor-id');
            console.log('Edit button clicked for donor ID:', donorId);
            // Add your edit functionality here
            alert('Edit functionality will be implemented for donor ID: ' + donorId);
        });
    });

    // Approve button click handler
    const approveButton = document.getElementById('Approve');
    if (approveButton) {
        approveButton.addEventListener('click', function() {
            console.log("Approve button clicked");
            
            if (!currentDonorData) {
                showError('Error: No donor selected');
                console.error("No donor data available. Cannot proceed.");
                return;
            }
            
            console.log("Current donor data:", currentDonorData);
            
            // Get the donor_id from the data
            const donorId = currentDonorData.donor_id;
            if (!donorId) {
                showError('Error: Could not process approval - missing donor ID');
                console.error("Missing donor_id in data");
                return;
            }
            
            console.log("Opening screening modal for donor ID:", donorId);
            
            // Close the donor details modal first
            const donorModal = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal'));
            if (donorModal) {
                donorModal.hide();
            }
            
            // Wait a moment for the modal to close, then open the screening modal
            setTimeout(function() {
                // Open the screening form modal
                if (typeof openScreeningModal === 'function') {
                    openScreeningModal(currentDonorData);
                } else {
                    showError('Screening modal function not available');
                }
            }, 300);
        });
    } else {
        console.error("ERROR: Approve button not found in the DOM!");
    }

    // Toggle additional information functionality
    const toggleAdditionalInfoBtn = document.getElementById('toggleAdditionalInfo');
    const additionalInfoSection = document.getElementById('additionalInfo');
    
    if (toggleAdditionalInfoBtn && additionalInfoSection) {
        toggleAdditionalInfoBtn.addEventListener('click', function() {
            const isExpanded = additionalInfoSection.style.display !== 'none';
            
            if (isExpanded) {
                // Collapse
                additionalInfoSection.style.display = 'none';
                toggleAdditionalInfoBtn.innerHTML = '<i class="fas fa-chevron-down me-1"></i>Show More Details';
                toggleAdditionalInfoBtn.classList.remove('expanded');
            } else {
                // Expand
                additionalInfoSection.style.display = 'block';
                toggleAdditionalInfoBtn.innerHTML = '<i class="fas fa-chevron-up me-1"></i>Show Less Details';
                toggleAdditionalInfoBtn.classList.add('expanded');
            }
        });
    }

    // Fix modal cleanup and prevent page freezing
    const donorModal = document.getElementById('donorDetailsModal');
    
    // Proper cleanup for donor modal
    if (donorModal) {
        donorModal.addEventListener('hidden.bs.modal', function() {
            console.log('Donor modal hidden, cleaning up...');
            
            // Reset current donor data
            currentDonorData = null;
            
            // Reset all field values
            const fields = donorModal.querySelectorAll('[name]');
            fields.forEach(field => {
                if (field.tagName === 'H3' || field.tagName === 'SPAN' || field.tagName === 'DIV') {
                    field.textContent = '-';
                } else {
                    field.value = '';
                }
            });
            
            // Hide additional info section
            if (additionalInfoSection) {
                additionalInfoSection.style.display = 'none';
            }
            
            // Reset toggle button
            if (toggleAdditionalInfoBtn) {
                toggleAdditionalInfoBtn.innerHTML = '<i class="fas fa-chevron-down me-1"></i>Show More Details';
                toggleAdditionalInfoBtn.classList.remove('expanded');
            }
            
            console.log('Donor modal cleanup completed');
        });
    }

    // Global cleanup function to fix any modal backdrop issues
    function cleanupModalBackdrops() {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => {
            backdrop.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    
    // Add cleanup on page load and window focus
    window.addEventListener('load', cleanupModalBackdrops);
    window.addEventListener('focus', cleanupModalBackdrops);

    // Make functions globally available
    window.populateModalFields = populateModalFields;
    window.fetchScreeningData = fetchScreeningData;
    window.showError = showError;
    window.showAlert = showAlert;
    window.cleanupModalBackdrops = cleanupModalBackdrops;
});
