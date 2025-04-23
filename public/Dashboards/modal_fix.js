// Function to show the registration confirmation modal
function showConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    const confirmationModal = new bootstrap.Modal(modal, {
        backdrop: 'static',
        keyboard: false
    });
    
    // Ensure proper z-index
    document.querySelector('.modal-backdrop').style.zIndex = '1040';
    modal.style.zIndex = '1050';
    modal.querySelector('.modal-dialog').style.zIndex = '1051';
    modal.querySelector('.modal-content').style.boxShadow = '0 5px 15px rgba(0,0,0,0.5)';
    
    // Show the modal
    confirmationModal.show();
}

// Function to proceed to donor form after confirmation
function proceedToDonorForm() {
    // Hide confirmation modal
    const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
    confirmationModal.hide();

    // Show loading modal
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    loadingModal.show();

    // Redirect after a short delay
    setTimeout(() => {
        window.location.href = '../forms/donor-form-modal.php';
    }, 800);
} 