<!-- Blood Request Modal -->
<div class="modal fade" id="bloodRequestModal" tabindex="-1" aria-labelledby="bloodRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bloodRequestModalLabel">Blood Request Form</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bloodRequestForm">
                    <!-- Patient Information Section -->
                    <h6 class="mb-3 fw-bold">Patient Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Patient Name</label>
                        <input type="text" class="form-control" name="patient_name" required>
                    </div>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Age</label>
                            <input type="number" class="form-control" name="patient_age" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="patient_gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis</label>
                        <input type="text" class="form-control" name="patient_diagnosis" placeholder="e.g., T/E, FTE, Septic Shock" required>
                    </div>

                    <!-- Blood Request Details Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Blood Request Details</h6>
                    <div class="mb-3 row">
                        <div class="col">
                            <label class="form-label">Blood Type</label>
                            <select class="form-select" name="patient_blood_type" required>
                                <option value="">Select Type</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="O">O</option>
                                <option value="AB">AB</option>
                            </select>
                        </div>
                        <div class="col">
                            <label class="form-label">RH Factor</label>
                            <select class="form-select" name="rh_factor" required>
                                <option value="">Select RH</option>
                                <option value="Positive">Positive</option>
                                <option value="Negative">Negative</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 row gx-3">
                        <div class="col-md-4">
                            <label class="form-label">Component</label>
                            <input type="hidden" name="blood_component" value="Whole Blood">
                            <input type="text" class="form-control" value="Whole Blood" readonly style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Number of Units</label>
                            <input type="number" class="form-control" name="units_requested" min="1" required style="width: 105%;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">When Needed</label>
                            <select id="whenNeeded" class="form-select" name="when_needed" required style="width: 105%;">
                                <option value="ASAP">ASAP</option>
                                <option value="Scheduled">Scheduled</option>
                            </select>
                        </div>
                    </div>
                    <div id="scheduleDateTime" class="mb-3 d-none">
                        <label class="form-label">Scheduled Date & Time</label>
                        <input type="datetime-local" class="form-control" name="scheduled_datetime">
                    </div>

                    <!-- Additional Information Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Additional Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Hospital Admitted</label>
                        <input type="text" class="form-control" name="hospital_admitted" value="<?php echo $_SESSION['user_first_name'] ?? ''; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Requesting Physician</label>
                        <input type="text" class="form-control" name="physician_name" value="<?php echo $_SESSION['user_surname'] ?? ''; ?>" readonly>
                    </div>

                    <!-- File Upload and Signature Section -->
                    <h6 class="mb-3 mt-4 fw-bold">Supporting Documents & Signature</h6>
                    <div class="mb-3">
                        <label class="form-label">Upload Supporting Documents (Images only)</label>
                        <input type="file" class="form-control" name="supporting_docs[]" accept="image/*" multiple>
                        <small class="text-muted">Accepted formats: .jpg, .jpeg, .png</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Physician's Signature</label>
                        <div class="signature-method-selector mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="signature_method" id="uploadSignature" value="upload" checked>
                                <label class="form-check-label" for="uploadSignature">Upload Signature</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="signature_method" id="drawSignature" value="draw">
                                <label class="form-check-label" for="drawSignature">Draw Signature</label>
                            </div>
                        </div>

                        <div id="signatureUpload" class="mb-3">
                            <input type="file" class="form-control" name="signature_file" accept="image/*">
                        </div>

                        <div id="signaturePad" class="d-none">
                            <div class="border rounded p-3 mb-2">
                                <canvas id="physicianSignaturePad" class="w-100" style="height: 200px; border: 1px solid #dee2e6;"></canvas>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary btn-sm" id="clearSignature">Clear</button>
                                <button type="button" class="btn btn-primary btn-sm" id="saveSignature">Save Signature</button>
                            </div>
                            <input type="hidden" name="signature_data" id="signatureData">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Blood Request Form JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add blood request form submission handler
    const bloodRequestForm = document.getElementById('bloodRequestForm');
    if (bloodRequestForm) {
        bloodRequestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            // Create FormData object
            const formData = new FormData(this);
            
            // Add additional data
            formData.append('user_id', '<?php echo $_SESSION['user_id']; ?>');
            formData.append('status', 'Pending');
            formData.append('physician_name', '<?php echo $_SESSION['user_surname']; ?>');
            formData.append('requested_on', new Date().toISOString());
            
            // Handle "when needed" logic
            const whenNeeded = document.getElementById('whenNeeded').value;
            const isAsap = whenNeeded === 'ASAP';
            formData.append('is_asap', isAsap ? 'true' : 'false');
            
            // Always set when_needed as a timestamp
            if (isAsap) {
                // For ASAP, use current date/time
                formData.set('when_needed', new Date().toISOString());
            } else {
                // For Scheduled, use the selected date/time
                const scheduledDate = document.querySelector('#scheduleDateTime input').value;
                if (scheduledDate) {
                    formData.set('when_needed', new Date(scheduledDate).toISOString());
                } else {
                    // If no date selected for scheduled, default to current date
                    formData.set('when_needed', new Date().toISOString());
                }
            }
            
            // Define exact fields from the database schema
            const validFields = [
                'request_id', 'user_id', 'patient_name', 'patient_age', 'patient_gender', 
                'patient_diagnosis', 'patient_blood_type', 'rh_factor', 'blood_component', 
                'units_requested', 'when_needed', 'is_asap', 'hospital_admitted', 
                'physician_name', 'requested_on', 'status'
            ];
            
            // Convert FormData to JSON object, only including valid fields
            const data = {};
            validFields.forEach(field => {
                if (formData.has(field)) {
                    const value = formData.get(field);
                    
                    // Convert numeric values to numbers
                    if (field === 'patient_age' || field === 'units_requested') {
                        data[field] = parseInt(value, 10);
                    } 
                    // Convert boolean strings to actual booleans
                    else if (field === 'is_asap') {
                        data[field] = value === 'true';
                    }
                    // Format timestamps properly
                    else if (field === 'when_needed' || field === 'requested_on') {
                        try {
                            // Ensure we have a valid date
                            const dateObj = new Date(value);
                            if (isNaN(dateObj.getTime())) {
                                throw new Error(`Invalid date for ${field}: ${value}`);
                            }
                            // Format as ISO string with timezone
                            data[field] = dateObj.toISOString();
                        } catch (err) {
                            console.error(`Error formatting date for ${field}:`, err);
                            // Default to current time if invalid
                            data[field] = new Date().toISOString();
                        }
                    }
                    // All other fields as strings
                    else {
                        data[field] = value;
                    }
                }
            });
            
            console.log('Submitting request data:', data);
            console.log('Valid fields in database:', validFields);
            console.log('FormData keys:', Array.from(formData.keys()));
            console.log('when_needed value:', data.when_needed);
            console.log('requested_on value:', data.requested_on);
            console.log('is_asap value:', data.is_asap);
            
            // Send data to server
            fetch('<?php echo SUPABASE_URL; ?>/rest/v1/blood_requests', {
                method: 'POST',
                headers: {
                    'apikey': '<?php echo SUPABASE_API_KEY; ?>',
                    'Authorization': 'Bearer <?php echo SUPABASE_API_KEY; ?>',
                    'Content-Type': 'application/json',
                    'Prefer': 'return=minimal'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log('Request response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Error response body:', text);
                        // Try to parse as JSON to extract more details
                        try {
                            const errorJson = JSON.parse(text);
                            throw new Error(`Error ${response.status}: ${errorJson.message || errorJson.error || text}`);
                        } catch (jsonError) {
                            // If can't parse as JSON, use the raw text
                            throw new Error(`Error ${response.status}: ${text}`);
                        }
                    });
                }
                return response.text();
            })
            .then(result => {
                console.log('Request submitted successfully:', result);
                
                // Show success message
                alert('Blood request submitted successfully!');
                
                // Reset form and close modal
                bloodRequestForm.reset();
                const modal = bootstrap.Modal.getInstance(document.getElementById('bloodRequestModal'));
                modal.hide();
                
                // Reload the page to show the new request
                window.location.reload();
            })
            .catch(error => {
                console.error('Error submitting request:', error);
                alert('Error submitting request: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
    
    // Handle when needed change
    const whenNeededSelect = document.getElementById('whenNeeded');
    const scheduleDateTimeDiv = document.getElementById('scheduleDateTime');
    
    if (whenNeededSelect && scheduleDateTimeDiv) {
        whenNeededSelect.addEventListener('change', function() {
            if (this.value === 'Scheduled') {
                scheduleDateTimeDiv.classList.remove('d-none');
                scheduleDateTimeDiv.style.opacity = 1;
                scheduleDateTimeDiv.querySelector('input').required = true;
            } else {
                scheduleDateTimeDiv.style.opacity = 0;
                setTimeout(() => {
                    scheduleDateTimeDiv.classList.add('d-none');
                    scheduleDateTimeDiv.querySelector('input').required = false;
                }, 500);
            }
        });
    }
    
    // Handle signature method toggle
    const uploadSignatureRadio = document.getElementById('uploadSignature');
    const drawSignatureRadio = document.getElementById('drawSignature');
    const signatureUploadDiv = document.getElementById('signatureUpload');
    const signaturePadDiv = document.getElementById('signaturePad');
    
    if (uploadSignatureRadio && drawSignatureRadio) {
        uploadSignatureRadio.addEventListener('change', function() {
            if (this.checked) {
                signatureUploadDiv.classList.remove('d-none');
                signaturePadDiv.classList.add('d-none');
            }
        });
        
        drawSignatureRadio.addEventListener('change', function() {
            if (this.checked) {
                signatureUploadDiv.classList.add('d-none');
                signaturePadDiv.classList.remove('d-none');
                initSignaturePad();
            }
        });
    }
});

// Initialize signature pad
function initSignaturePad() {
    const canvas = document.getElementById('physicianSignaturePad');
    if (!canvas) return;
    
    const signaturePad = new SignaturePad(canvas, {
        backgroundColor: 'white',
        penColor: 'black'
    });
    
    // Clear button
    document.getElementById('clearSignature').addEventListener('click', function() {
        signaturePad.clear();
    });
    
    // Save button
    document.getElementById('saveSignature').addEventListener('click', function() {
        if (signaturePad.isEmpty()) {
            alert('Please provide a signature first.');
            return;
        }
        
        const signatureData = signaturePad.toDataURL();
        document.getElementById('signatureData').value = signatureData;
        alert('Signature saved!');
    });
    
    // Resize canvas
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        signaturePad.clear(); // Clear the canvas
    }
    
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();
}
</script> 