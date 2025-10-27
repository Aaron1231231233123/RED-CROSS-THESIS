// Blood Collection View Modal Controller - Admin
class BloodCollectionViewModalAdmin {
    constructor() {
        this.modal = null;
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initializeModal());
        } else {
            this.initializeModal();
        }
    }

    initializeModal() {
        this.modal = document.getElementById('bloodCollectionViewModalAdmin');
        if (!this.modal) {
            console.warn('[Admin View] Blood collection view modal element not found');
            return;
        }
        console.log('[Admin View] BloodCollectionViewModalAdmin initialized');
    }

    async openModal(collectionData) {
        console.log('[Admin View] Opening view modal with data:', collectionData);
        
        if (collectionData && collectionData.physical_exam_id && !collectionData.blood_collection_id) {
            console.log('[Admin View] Fetching collection data for physical_exam_id:', collectionData.physical_exam_id);
            try {
                const collectionResp = await fetch(`../../assets/php_func/admin/fetch_blood_collection.php?physical_exam_id=${encodeURIComponent(collectionData.physical_exam_id)}`);
                console.log('[Admin View] Fetch response status:', collectionResp.status);
                
                if (!collectionResp.ok) {
                    const errorText = await collectionResp.text();
                    console.error('[Admin View] Fetch failed with status', collectionResp.status, ':', errorText);
                    throw new Error(`HTTP ${collectionResp.status}: ${errorText}`);
                }
                
                const collectionDataArr = await collectionResp.json();
                console.log('[Admin View] Fetched data:', collectionDataArr);
                
                if (collectionDataArr && Array.isArray(collectionDataArr) && collectionDataArr.length > 0) {
                    collectionData = collectionDataArr[0];
                    console.log('[Admin View] Successfully fetched collection data:', collectionData);
                } else {
                    console.error('[Admin View] No collection data found in array:', collectionDataArr);
                    alert('No blood collection data found for this donor');
                    return;
                }
            } catch (e) {
                console.error('[Admin View] Error fetching collection data:', e);
                alert('Error loading blood collection data: ' + e.message);
                return;
            }
        }
        
        if (!collectionData || !collectionData.blood_collection_id) {
            console.error('[Admin View] Invalid collection data');
            alert('Invalid blood collection data');
            return;
        }

        await this.populateModal(collectionData);
        
        if (this.modal) {
            const modalInstance = new bootstrap.Modal(this.modal);
            modalInstance.show();
        }
    }

    async populateModal(collectionData) {
        try {
            if (collectionData.donor_id) {
                const donorResponse = await fetch(`../../assets/php_func/get_screening_details.php?donor_id=${collectionData.donor_id}`);
                if (donorResponse.ok) {
                    const donorData = await donorResponse.json();
                    if (donorData.success && donorData.data) {
                        this.populateDonorInfo(donorData.data);
                    }
                }
            }
            this.populateCollectionDetails(collectionData);
        } catch (error) {
            console.error('[Admin View] Error populating modal:', error);
        }
    }

    populateDonorInfo(donorData) {
        const donorNameEl = document.getElementById('admin-view-donor-name');
        const donorIdEl = document.getElementById('admin-view-donor-id');
        const donorAgeGenderEl = document.getElementById('admin-view-donor-age-gender');
        const donorBloodTypeEl = document.getElementById('admin-view-blood-type');

        if (donorNameEl) {
            const fullName = `${donorData.surname || ''} ${donorData.first_name || ''} ${donorData.middle_name || ''}`.trim();
            donorNameEl.textContent = fullName || '-';
        }
        if (donorIdEl && donorData.donor_form_id) {
            donorIdEl.textContent = donorData.donor_form_id;
        }
        if (donorAgeGenderEl) {
            const age = donorData.age || '-';
            const gender = donorData.gender || '-';
            donorAgeGenderEl.textContent = `${age} / ${gender}`;
        }
        if (donorBloodTypeEl) {
            donorBloodTypeEl.textContent = donorData.blood_type || '-';
        }
    }

    populateCollectionDetails(collectionData) {
        console.log('[Admin View] Populating collection details:', collectionData);

        const collectionDateEl = document.getElementById('admin-view-collection-date');
        if (collectionDateEl && collectionData.start_time) {
            const date = new Date(collectionData.start_time);
            collectionDateEl.value = date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        }

        const bagTypeEl = document.getElementById('admin-view-bag-type');
        if (bagTypeEl) {
            bagTypeEl.value = collectionData.blood_bag_type || collectionData.blood_bag_brand || '-';
        }

        const unitSerialEl = document.getElementById('admin-view-unit-serial');
        if (unitSerialEl) {
            unitSerialEl.value = collectionData.unit_serial_number || '-';
        }

        const statusEl = document.getElementById('admin-view-collection-status');
        if (statusEl) {
            if (collectionData.is_successful === true || collectionData.is_successful === 'true' || collectionData.is_successful === 'YES') {
                statusEl.value = 'Successful';
            } else if (collectionData.is_successful === false || collectionData.is_successful === 'false') {
                statusEl.value = 'Unsuccessful';
            } else {
                statusEl.value = collectionData.status || 'Pending';
            }
        }

        const startTimeEl = document.getElementById('admin-view-start-time');
        if (startTimeEl && collectionData.start_time) {
            const startDate = new Date(collectionData.start_time);
            const hours = startDate.getHours().toString().padStart(2, '0');
            const minutes = startDate.getMinutes().toString().padStart(2, '0');
            startTimeEl.value = `${hours}:${minutes}`;
        }

        const endTimeEl = document.getElementById('admin-view-end-time');
        if (endTimeEl && collectionData.end_time) {
            const endDate = new Date(collectionData.end_time);
            const hours = endDate.getHours().toString().padStart(2, '0');
            const minutes = endDate.getMinutes().toString().padStart(2, '0');
            endTimeEl.value = `${hours}:${minutes}`;
        }

        const amountTakenEl = document.getElementById('admin-view-amount-taken');
        if (amountTakenEl) {
            amountTakenEl.value = collectionData.amount_taken || '1';
        }

        const phlebotomistEl = document.getElementById('admin-view-phlebotomist');
        if (phlebotomistEl) {
            phlebotomistEl.value = collectionData.phlebotomist || '-';
        }

        const reactionEl = document.getElementById('admin-view-donor-reaction');
        if (reactionEl) {
            reactionEl.value = collectionData.donor_reaction || 'None';
        }

        const managementEl = document.getElementById('admin-view-management-done');
        if (managementEl) {
            managementEl.value = collectionData.management_done || 'None';
        }
    }
}

window.bloodCollectionViewModalAdmin = new BloodCollectionViewModalAdmin();

