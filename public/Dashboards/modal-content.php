<!-- Donor Details Modal Content -->
<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="donorDetailsModalLabel">Donor Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <!-- Simple donor details display -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <span id="modalDonorName"></span></p>
                    <p><strong>Age:</strong> <span id="modalDonorAge"></span></p>
                    <p><strong>Sex:</strong> <span id="modalDonorSex"></span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Contact:</strong> <span id="modalDonorContact"></span></p>
                    <p><strong>Email:</strong> <span id="modalDonorEmail"></span></p>
                    <p><strong>Address:</strong> <span id="modalDonorAddress"></span></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-danger" id="Approve">Approve</button>
        </div>
    </div>
</div>

<script>
    // Add script to populate modal data
    document.addEventListener('DOMContentLoaded', function() {
        const donorDetailsModal = document.getElementById('donorDetailsModal');
        let currentDonorData = null;
        
        // Handle modal show event to populate data
        donorDetailsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const row = button.closest('tr');
            const donorDataStr = row.getAttribute('data-donor');
            
            try {
                currentDonorData = JSON.parse(donorDataStr);
                
                // Populate modal fields
                document.getElementById('modalDonorName').textContent = 
                    (currentDonorData.first_name || '') + ' ' + 
                    (currentDonorData.middle_name || '') + ' ' + 
                    (currentDonorData.surname || '');
                
                document.getElementById('modalDonorAge').textContent = currentDonorData.age || 'N/A';
                document.getElementById('modalDonorSex').textContent = currentDonorData.sex || 'N/A';
                document.getElementById('modalDonorContact').textContent = currentDonorData.mobile || 'N/A';
                document.getElementById('modalDonorEmail').textContent = currentDonorData.email || 'N/A';
                document.getElementById('modalDonorAddress').textContent = currentDonorData.permanent_address || 'N/A';
                
            } catch (error) {
                console.error('Error parsing donor data:', error);
            }
        });
        
        // Approve button click handler
        const approveButton = document.getElementById('Approve');
        if (approveButton) {
            approveButton.addEventListener('click', function() {
                if (!currentDonorData || !currentDonorData.donor_id) {
                    alert('Error: Could not process approval - missing donor data');
                    return;
                }
                
                // Get donor name
                const donorName = 
                    (currentDonorData.first_name || '') + ' ' + 
                    (currentDonorData.surname || '');
                
                // Show loading modal
                const donorDetailsModal = bootstrap.Modal.getInstance(document.getElementById('donorDetailsModal'));
                if (donorDetailsModal) {
                    donorDetailsModal.hide();
                }
                
                const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                loadingModal.show();
                
                // Navigate after a short delay
                setTimeout(() => {
                    const url = `dashboard-staff-donor-submission.php?approve_donor=${currentDonorData.donor_id}&donor_name=${encodeURIComponent(donorName)}`;
                    window.location.href = url;
                }, 800);
            });
        }
    });
</script> 