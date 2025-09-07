class PhlebotomistBloodCollectionDetailsModal {
    constructor() {
        this.modal = null;
        this.isLoading = false;
        this.init();
    }

    init() {
        // Initialize modal when DOM is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeModal());
        } else {
            this.initializeModal();
        }
    }

    initializeModal() {
        this.modal = document.getElementById('phlebotomistBloodCollectionDetailsModal');
        if (!this.modal) {
            console.error('Phlebotomist blood collection details modal not found');
            return;
        }

        this.setupEventListeners();
    }

    setupEventListeners() {
        // Close button
        const closeBtn = document.querySelector('.phlebotomist-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeModal());
        }

        // Close on backdrop click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.closeModal();
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                this.closeModal();
            }
        });
    }

    async openModal(donorId) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading(true);

        try {
            // Fetch donor and collection details
            const response = await fetch(`../../assets/php_func/phlebotomist_blood_collection_details.php?donor_id=${donorId}`);
            const result = await response.json();

            if (result.success) {
                console.log('Donor data received:', result.data);
                this.populateModal(result.data);
                this.showModal();
            } else {
                console.error('Failed to load donor details:', result.message);
                this.showToast(result.message || 'Failed to load donor details', 'error');
            }
        } catch (error) {
            console.error('Error fetching donor details:', error);
            this.showToast('Network error occurred', 'error');
        } finally {
            this.isLoading = false;
            this.showLoading(false);
        }
    }

    populateModal(data) {
        const { donor, blood_collection } = data;

        // Populate donor information
        const donorNameEl = document.getElementById('phlebotomist-donor-name');
        const donorAgeGenderEl = document.getElementById('phlebotomist-donor-age-gender');
        const donorIdEl = document.getElementById('phlebotomist-donor-id');
        const bloodTypeEl = document.getElementById('phlebotomist-blood-type');

        if (donorNameEl) donorNameEl.textContent = donor.name || 'Unknown';
        if (donorAgeGenderEl) donorAgeGenderEl.textContent = `${donor.age}, ${donor.gender}`;
        if (donorIdEl) donorIdEl.textContent = `Donor ID ${donor.donor_id}`;
        if (bloodTypeEl) bloodTypeEl.textContent = donor.blood_type;

        // Populate collection details
        this.populateCollectionDetails(blood_collection);
    }

    populateCollectionDetails(blood_collection) {
        console.log('Populating collection details with:', blood_collection);
        
        // Collection Date
        const collectionDateEl = document.getElementById('phlebotomist-collection-date');
        if (collectionDateEl) {
            if (blood_collection && blood_collection.created_at) {
                const date = new Date(blood_collection.created_at);
                collectionDateEl.value = date.toLocaleDateString('en-US'); // MM/DD/YYYY format
            } else {
                collectionDateEl.value = '';
            }
        }

        // Bag Type
        const bagTypeEl = document.getElementById('phlebotomist-bag-type');
        if (bagTypeEl) {
            if (blood_collection && blood_collection.blood_bag_type) {
                bagTypeEl.value = blood_collection.blood_bag_type;
            } else {
                bagTypeEl.value = '';
            }
        }

        // Unit Serial Number
        const unitSerialEl = document.getElementById('phlebotomist-unit-serial');
        if (unitSerialEl) {
            if (blood_collection && blood_collection.unit_serial_number) {
                unitSerialEl.value = blood_collection.unit_serial_number;
            } else {
                unitSerialEl.value = '';
            }
        }

        // Start Time
        const startTimeEl = document.getElementById('phlebotomist-start-time');
        if (startTimeEl) {
            if (blood_collection && blood_collection.start_time) {
                // Convert timestamp to 12-hour format
                const startTime = new Date(blood_collection.start_time);
                const timeString = startTime.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                }); // HH:MM AM/PM format
                startTimeEl.value = timeString;
            } else {
                startTimeEl.value = '';
            }
        }

        // End Time
        const endTimeEl = document.getElementById('phlebotomist-end-time');
        if (endTimeEl) {
            if (blood_collection && blood_collection.end_time) {
                // Convert timestamp to 12-hour format
                const endTime = new Date(blood_collection.end_time);
                const timeString = endTime.toLocaleTimeString('en-US', { 
                    hour: '2-digit', 
                    minute: '2-digit',
                    hour12: true 
                }); // HH:MM AM/PM format
                endTimeEl.value = timeString;
            } else {
                endTimeEl.value = '';
            }
        }

        // Donor Reaction
        const donorReactionEl = document.getElementById('phlebotomist-donor-reaction');
        if (donorReactionEl) {
            if (blood_collection && blood_collection.donor_reaction) {
                donorReactionEl.value = blood_collection.donor_reaction;
            } else {
                donorReactionEl.value = '';
            }
        }

        // Expiration Date (use blood_expiration field or calculate 42 days from collection date)
        const expirationDateEl = document.getElementById('phlebotomist-expiration-date');
        if (expirationDateEl) {
            if (blood_collection && blood_collection.blood_expiration) {
                const expirationDate = new Date(blood_collection.blood_expiration);
                expirationDateEl.value = expirationDate.toLocaleDateString('en-US'); // MM/DD/YYYY format
            } else if (blood_collection && blood_collection.created_at) {
                const collectionDate = new Date(blood_collection.created_at);
                const expirationDate = new Date(collectionDate);
                expirationDate.setDate(expirationDate.getDate() + 42); // 42 days shelf life
                expirationDateEl.value = expirationDate.toLocaleDateString('en-US'); // MM/DD/YYYY format
            } else {
                expirationDateEl.value = '';
            }
        }

        // Phlebotomist Name
        const phlebotomistEl = document.querySelector('.phlebotomist-phlebotomist-label');
        if (phlebotomistEl) {
            if (blood_collection && blood_collection.phlebotomist) {
                phlebotomistEl.textContent = `Phlebotomist Name: ${blood_collection.phlebotomist}`;
            } else {
                phlebotomistEl.textContent = 'Phlebotomist Name: -';
            }
        }
    }

    showModal() {
        this.modal.style.display = 'flex';
        setTimeout(() => {
            this.modal.classList.add('show');
        }, 10);
    }

    closeModal() {
        this.modal.classList.remove('show');
        setTimeout(() => {
            this.modal.style.display = 'none';
        }, 300);
    }

    showLoading(show) {
        const loadingEl = document.getElementById('phlebotomistModalLoading');
        if (loadingEl) {
            loadingEl.style.display = show ? 'flex' : 'none';
        }
    }

    showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `phlebotomist-toast phlebotomist-toast-${type}`;
        toast.innerHTML = `
            <div class="phlebotomist-toast-content">
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                <span>${message}</span>
            </div>
        `;

        // Add to page
        document.body.appendChild(toast);

        // Show toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);

        // Remove toast after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
}

// Initialize modal when DOM is loaded
let phlebotomistBloodCollectionDetailsModal = null;

document.addEventListener('DOMContentLoaded', function() {
    phlebotomistBloodCollectionDetailsModal = new PhlebotomistBloodCollectionDetailsModal();
    
    // Make it globally available
    window.phlebotomistBloodCollectionDetailsModal = phlebotomistBloodCollectionDetailsModal;
});

// Add CSS for the modal and toast
const style = document.createElement('style');
style.textContent = `
    /* Phlebotomist Blood Collection Details Modal */
    .phlebotomist-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10002;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .phlebotomist-modal.show {
        opacity: 1;
    }

    .phlebotomist-modal-content {
        background: white;
        border-radius: 8px;
        max-width: 900px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        transform: translateY(-20px);
        transition: transform 0.3s ease;
    }

    .phlebotomist-modal.show .phlebotomist-modal-content {
        transform: translateY(0);
    }

    .phlebotomist-modal-header {
        background: #b22222;
        color: white;
        padding: 15px 25px;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .phlebotomist-modal-header h3 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
    }

    .phlebotomist-close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s ease;
    }

    .phlebotomist-close-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .phlebotomist-modal-body {
        padding: 25px;
    }

    .phlebotomist-donor-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e0e0e0;
    }

    .phlebotomist-donor-info {
        flex: 1;
    }

    .phlebotomist-donor-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: #721c24;
        margin: 0 0 4px 0;
        line-height: 1.2;
    }

    .phlebotomist-donor-age-gender {
        font-size: 0.95rem;
        color: #495057;
        margin: 0;
        font-weight: 400;
    }

    .phlebotomist-donor-meta {
        text-align: right;
    }

    .phlebotomist-donor-id {
        font-size: 0.95rem;
        font-weight: 600;
        color: #721c24;
        margin: 0 0 4px 0;
    }

    .phlebotomist-blood-type {
        font-size: 1rem;
        font-weight: 700;
        color: #b22222;
        margin: 0;
    }

    .phlebotomist-section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #333;
        margin: 20px 0 8px 0;
        text-align: left;
    }

    .phlebotomist-title-line {
        border: none;
        height: 1px;
        background-color: #e0e0e0;
        margin: 0 0 15px 0;
    }

    .phlebotomist-section-subtitle {
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
        margin: 18px 0 8px 0;
    }

    .phlebotomist-details-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        border-radius: 4px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .phlebotomist-details-table th {
        background: #b22222;
        color: white;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        font-size: 0.9rem;
        letter-spacing: 0.3px;
    }

    .phlebotomist-details-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #e9ecef;
        background: white;
    }

    .phlebotomist-details-table input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 0.9rem;
        background: #f8f9fa;
        color: #495057;
        font-weight: 400;
    }

    .phlebotomist-details-table input:focus {
        outline: none;
        border-color: #b22222;
        box-shadow: 0 0 0 2px rgba(178, 34, 34, 0.1);
        background: white;
    }

    .phlebotomist-details-table input::placeholder {
        color: #6c757d;
        font-style: italic;
    }

    .phlebotomist-time-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: #007bff;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        color: white;
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .phlebotomist-time-btn:hover {
        background: #0056b3;
    }

    .phlebotomist-phlebotomist-section {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }

    .phlebotomist-phlebotomist-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.95rem;
    }


    /* Toast Messages */
    .phlebotomist-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        padding: 15px 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        z-index: 10004;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        border-left: 4px solid #b22222;
    }

    .phlebotomist-toast.show {
        transform: translateX(0);
    }

    .phlebotomist-toast-error {
        border-left-color: #dc3545;
    }

    .phlebotomist-toast-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .phlebotomist-toast-content i {
        font-size: 1.2rem;
    }

    .phlebotomist-toast-error i {
        color: #dc3545;
    }

    .phlebotomist-toast i:not(.phlebotomist-toast-error i) {
        color: #28a745;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .phlebotomist-modal-content {
            width: 95%;
            margin: 15px;
        }

        .phlebotomist-donor-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .phlebotomist-donor-name {
            font-size: 1.2rem;
        }

        .phlebotomist-details-table {
            font-size: 0.85rem;
        }

        .phlebotomist-details-table th,
        .phlebotomist-details-table td {
            padding: 10px 12px;
        }
    }
`;

document.head.appendChild(style);
