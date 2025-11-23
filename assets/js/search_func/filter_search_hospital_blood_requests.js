// Filter and search functionality for Hospital Blood Requests Dashboard
// Adds filter dropdown in Status column header and search functionality with loading modal

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

    // Get the search input
    const existingSearch = filterSearchBar.querySelector('#requestSearchBar');
    
    // Get the status filter dropdown from the filter-search-bar
    const statusFilterDropdown = document.getElementById('statusFilterDropdown');

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

    // Handle status filter dropdown change
    if (statusFilterDropdown) {
        statusFilterDropdown.addEventListener('change', function(e) {
            e.stopPropagation(); // Prevent event bubbling
            applyFilters(false); // Use spinner for status filter
        });
    }

    function applyFilters(showModal = false){
        const status = [];
        
        // Get selected status from dropdown in Status column header
        if (statusFilterDropdown && statusFilterDropdown.value && statusFilterDropdown.value !== 'All Status') {
            status.push(statusFilterDropdown.value);
        }
        // If "All Status" is selected or no dropdown, status array remains empty (shows all)

        const qInput = existingSearch || document.getElementById('requestSearchBar');
        const q = qInput ? (qInput.value || '').trim() : '';

        // Show loading indicator based on source
        const searchSpinner = document.getElementById('searchLoadingSpinner');
        if (showModal) {
            // Show modal for filter "Apply" button (if needed in future)
            if (typeof FilterLoadingModal !== 'undefined') {
                FilterLoadingModal.show();
            }
            if (searchSpinner) searchSpinner.style.display = 'none';
        } else {
            // Show spinner in search bar for search input and status filter
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

