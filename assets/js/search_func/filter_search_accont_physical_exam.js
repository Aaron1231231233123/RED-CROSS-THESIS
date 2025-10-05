// Checkbox filter UI and filtered search for Physician Dashboard (Physical Examination)
// Adds a Filters dropdown inside .search-container and posts to API to render rows

document.addEventListener('DOMContentLoaded', function(){
    const tbody = document.getElementById('screeningTableBody');
    if (!tbody) return;

    const container = document.querySelector('.search-container');
    if (!container) return;

    const bar = document.createElement('div');
    bar.className = 'mt-2';
    bar.innerHTML = `
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filters</button>
                <div class="dropdown-menu p-3" style="min-width:280px;">
                    <div class="mb-2 fw-bold text-muted">Donor Type</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="Returning" id="fltDonorReturning">
                        <label class="form-check-label" for="fltDonorReturning">Returning</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="New" id="fltDonorNew">
                        <label class="form-check-label" for="fltDonorNew">New</label>
                    </div>
                    <div class="mb-2 fw-bold text-muted">Status</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="Pending" id="fltStatusPending">
                        <label class="form-check-label" for="fltStatusPending">Pending</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="Accepted" id="fltStatusAccepted">
                        <label class="form-check-label" for="fltStatusAccepted">Accepted</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="Deferred" id="fltStatusDeferred">
                        <label class="form-check-label" for="fltStatusDeferred">Deferred</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="Ineligible" id="fltStatusIneligible">
                        <label class="form-check-label" for="fltStatusIneligible">Ineligible</label>
                    </div>
                    <div class="d-grid">
                        <button id="fltRunPE" class="btn btn-danger btn-sm">Run</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(bar);

    const dropdownMenu = bar.querySelector('.dropdown-menu');
    if (dropdownMenu) dropdownMenu.addEventListener('click', function(e){ e.stopPropagation(); });

    const runBtn = bar.querySelector('#fltRunPE');
    if (runBtn) runBtn.addEventListener('click', applyFilters);

    function rebindRowHandlers(){
        try { if (typeof attachButtonClickHandlers === 'function') attachButtonClickHandlers(); } catch(_) {}
    }

    function applyFilters(){
        const donor_type = [];
        if (document.getElementById('fltDonorReturning').checked) donor_type.push('Returning');
        if (document.getElementById('fltDonorNew').checked) donor_type.push('New');
        const status = [];
        if (document.getElementById('fltStatusPending').checked) status.push('Pending');
        if (document.getElementById('fltStatusAccepted').checked) status.push('Accepted');
        if (document.getElementById('fltStatusDeferred').checked) status.push('Deferred');
        if (document.getElementById('fltStatusIneligible').checked) status.push('Ineligible');
        // No Flags in PE filter
        const qInput = document.getElementById('searchInput');
        const q = qInput ? (qInput.value || '').trim() : '';

        // Use XHR to avoid global fetch spinner side-effects
        const xhr = new XMLHttpRequest();
        // Use absolute URL to avoid any relative path inconsistencies across dashboards
        const apiUrl = '/RED-CROSS-THESIS/public/api/search_func/filter_search_accont_physical_exam.php';
        xhr.open('POST', apiUrl, true);
        xhr.setRequestHeader('Content-Type','application/json');
        xhr.setRequestHeader('Accept','application/json');
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) return;
            let res = null; try { res = JSON.parse(xhr.responseText); } catch(_) {}
            if (res && res.success && typeof res.html === 'string') {
                tbody.innerHTML = res.html;
            } else {
                const msg = (res && res.message) ? String(res.message) : 'No records found';
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted">' + msg + '</td></tr>';
            }
            rebindRowHandlers();
        };
        xhr.onerror = function(){
            tbody.innerHTML = '<tr><td colspan="8" class="text-danger">Search error. Please try again.</td></tr>';
            rebindRowHandlers();
        };
        xhr.send(JSON.stringify({ donor_type, status, q }));
    }
});



