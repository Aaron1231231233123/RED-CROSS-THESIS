// Filter and search functionality for Hospital Blood Requests Dashboard
// Adds filter dropdown and search functionality with loading modal

document.addEventListener('DOMContentLoaded', function(){
    const tbody = document.getElementById('requestTable');
    if (!tbody) return;

    // Ensure loading modal is available
    if (typeof FilterLoadingModal === 'undefined') {
        console.warn('FilterLoadingModal not loaded. Loading modal functionality may not work.');
    }

    // Get the filter-search-bar container
    const filterSearchBar = document.querySelector('.filter-search-bar');
    if (!filterSearchBar) return;

    // Replace the existing filter dropdown with enhanced version
    const existingFilter = filterSearchBar.querySelector('.filter-dropdown');
    const existingSearch = filterSearchBar.querySelector('#requestSearchBar');
    
    // Create filter dropdown with checkboxes
    const filterContainer = document.createElement('div');
    filterContainer.className = 'dropdown';
    filterContainer.innerHTML = `
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-filter"></i> Filters
        </button>
        <div class="dropdown-menu p-3" style="min-width:280px;">
            <div class="mb-2 fw-bold text-muted">Status</div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="All Status" id="fltStatusAll" checked aria-label="Filter by All Status">
                <label class="form-check-label" for="fltStatusAll">All Status</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="Pending" id="fltStatusPending" aria-label="Filter by Pending status">
                <label class="form-check-label" for="fltStatusPending">Pending</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="Approved" id="fltStatusApproved" aria-label="Filter by Approved status">
                <label class="form-check-label" for="fltStatusApproved">Approved</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="Declined" id="fltStatusDeclined" aria-label="Filter by Declined status">
                <label class="form-check-label" for="fltStatusDeclined">Declined</label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="Completed" id="fltStatusCompleted" aria-label="Filter by Completed status">
                <label class="form-check-label" for="fltStatusCompleted">Completed</label>
            </div>
            <div class="d-grid">
                <button id="fltRunBloodRequests" class="btn btn-danger btn-sm">Run</button>
            </div>
        </div>
    `;

    // Replace existing filter icon and dropdown
    if (existingFilter) {
        existingFilter.parentNode.replaceChild(filterContainer, existingFilter);
    } else {
        // Insert before search input
        const filterIcon = filterSearchBar.querySelector('.filter-icon');
        if (filterIcon) {
            filterIcon.parentNode.insertBefore(filterContainer, filterIcon.nextSibling);
        }
    }

    const dropdownMenu = filterContainer.querySelector('.dropdown-menu');
    if (dropdownMenu) {
        dropdownMenu.addEventListener('click', function(e){ 
            e.stopPropagation(); 
        });
    }

    const runBtn = filterContainer.querySelector('#fltRunBloodRequests');
    if (runBtn) {
        runBtn.addEventListener('click', function() {
            applyFilters(true); // Show modal for filter Run button
        });
    }

    // Handle "All Status" checkbox - uncheck others when checked
    const allStatusCheckbox = document.getElementById('fltStatusAll');
    if (allStatusCheckbox) {
        allStatusCheckbox.addEventListener('change', function() {
            if (this.checked) {
                // Uncheck all other status checkboxes
                ['fltStatusPending', 'fltStatusApproved', 'fltStatusDeclined', 'fltStatusCompleted'].forEach(id => {
                    const cb = document.getElementById(id);
                    if (cb) cb.checked = false;
                });
            }
        });
    }

    // Handle other checkboxes - uncheck "All Status" when any other is checked
    ['fltStatusPending', 'fltStatusApproved', 'fltStatusDeclined', 'fltStatusCompleted'].forEach(id => {
        const cb = document.getElementById(id);
        if (cb) {
            cb.addEventListener('change', function() {
                if (this.checked && allStatusCheckbox) {
                    allStatusCheckbox.checked = false;
                }
            });
        }
    });

    function rebindRowHandlers(){
        // Rebind view/print/handover button handlers
        // The main dashboard uses event delegation, so handlers should work automatically
        // But we trigger an event to notify that table was updated
        try { 
            // Trigger custom event to notify that table was updated
            const event = new CustomEvent('tableUpdated', { 
                detail: { source: 'filter_search' } 
            });
            document.dispatchEvent(event);
        } catch(e) {
            console.warn('Error rebinding row handlers:', e);
        }
    }

    function applyFilters(showModal = false){
        const status = [];
        
        // Check if "All Status" is selected
        const allStatusChecked = allStatusCheckbox && allStatusCheckbox.checked;
        
        if (!allStatusChecked) {
            // Collect checked status filters
            if (document.getElementById('fltStatusPending')?.checked) status.push('Pending');
            if (document.getElementById('fltStatusApproved')?.checked) status.push('Approved');
            if (document.getElementById('fltStatusDeclined')?.checked) status.push('Declined');
            if (document.getElementById('fltStatusCompleted')?.checked) status.push('Completed');
        }
        // If no status selected or "All Status" is checked, status array remains empty (shows all)

        const qInput = existingSearch || document.getElementById('requestSearchBar');
        const q = qInput ? (qInput.value || '').trim() : '';

        // Show loading indicator based on source
        const searchSpinner = document.getElementById('searchLoadingSpinner');
        if (showModal) {
            // Show modal for filter "Run" button
            if (typeof FilterLoadingModal !== 'undefined') {
                FilterLoadingModal.show();
            }
            if (searchSpinner) searchSpinner.style.display = 'none';
        } else {
            // Show spinner in search bar for search input
            if (searchSpinner) searchSpinner.style.display = 'block';
            if (typeof FilterLoadingModal !== 'undefined') {
                FilterLoadingModal.hide();
            }
        }

        // Use XHR to avoid global fetch spinner side-effects
        const xhr = new XMLHttpRequest();
        // Use absolute URL to avoid any relative path inconsistencies
        const apiUrl = '/RED-CROSS-THESIS/public/api/search_func/filter_search_hospital_blood_requests.php';
        xhr.open('POST', apiUrl, true);
        xhr.setRequestHeader('Content-Type','application/json');
        xhr.setRequestHeader('Accept','application/json');
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) return;
            let res = null; 
            try { 
                res = JSON.parse(xhr.responseText); 
            } catch(e) {
                console.error('Error parsing response:', e);
            }
            
            if (res && res.success && typeof res.html === 'string') {
                tbody.innerHTML = res.html;
                rebindRowHandlers();
            } else {
                const msg = (res && res.message) ? String(res.message) : 'No records found';
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">' + msg + '</td></tr>';
                rebindRowHandlers();
            }
            
            // Hide loading indicators when done
            const searchSpinner = document.getElementById('searchLoadingSpinner');
            if (searchSpinner) searchSpinner.style.display = 'none';
            if (typeof FilterLoadingModal !== 'undefined') {
                FilterLoadingModal.hide();
            }
        };
        xhr.onerror = function(){
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Search error. Please try again.</td></tr>';
            rebindRowHandlers();
            
            // Hide loading indicators on error
            const searchSpinner = document.getElementById('searchLoadingSpinner');
            if (searchSpinner) searchSpinner.style.display = 'none';
            if (typeof FilterLoadingModal !== 'undefined') {
                FilterLoadingModal.hide();
            }
        };
        xhr.send(JSON.stringify({ status, q }));
    }

    // Add search functionality with debounce
    if (existingSearch) {
        let searchTimeout;
        existingSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            // Show spinner immediately when user types
            const searchSpinner = document.getElementById('searchLoadingSpinner');
            if (searchSpinner) searchSpinner.style.display = 'block';
            
            searchTimeout = setTimeout(function() {
                applyFilters(false); // Use spinner, not modal for search
            }, 500); // Wait 500ms after user stops typing
        });

        // Also trigger on Enter key
        existingSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                const searchSpinner = document.getElementById('searchLoadingSpinner');
                if (searchSpinner) searchSpinner.style.display = 'block';
                applyFilters(false); // Use spinner, not modal for search
            }
        });
    }
});

