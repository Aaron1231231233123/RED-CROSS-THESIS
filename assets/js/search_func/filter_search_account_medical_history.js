// Checkbox filter controller for Interviewer dashboard
// Emits POST JSON to API and replaces the table rows with HTML

document.addEventListener('DOMContentLoaded', function(){
    const tbody = document.getElementById('donorTableBody');
    if (!tbody) return;

    // Build UI dynamically (a simple checklist bar above the table)
    const container = document.querySelector('.search-container');
    if (!container) return;

    const bar = document.createElement('div');
    bar.className = 'mt-2';
    bar.innerHTML = `
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filters</button>
                <div class="dropdown-menu p-3" style="min-width:260px;">
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
                        <input class="form-check-input" type="checkbox" value="Eligible" id="fltStatusEligible">
                        <label class="form-check-label" for="fltStatusEligible">Eligible</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="Ineligible" id="fltStatusIneligible">
                        <label class="form-check-label" for="fltStatusIneligible">Ineligible</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="Deferred" id="fltStatusDeferred">
                        <label class="form-check-label" for="fltStatusDeferred">Deferred</label>
                    </div>
                    <div class="mb-2 fw-bold text-muted">Registered Via</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="Mobile" id="fltViaMobile">
                        <label class="form-check-label" for="fltViaMobile">Mobile</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="System" id="fltViaSystem">
                        <label class="form-check-label" for="fltViaSystem">System</label>
                    </div>
                    <div class="d-grid">
                        <button id="fltRun" class="btn btn-danger btn-sm">Run</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(bar);
    const runBtn = bar.querySelector('#fltRun');
    if (runBtn) runBtn.addEventListener('click', applyFilters);

    // Keep dropdown open when interacting inside; close only when clicking outside
    const dropdownMenu = bar.querySelector('.dropdown-menu');
    if (dropdownMenu) {
        dropdownMenu.addEventListener('click', function(e){ e.stopPropagation(); });
    }

    function applyFilters(){
        const donor_type = [];
        if (document.getElementById('fltDonorReturning').checked) donor_type.push('Returning');
        if (document.getElementById('fltDonorNew').checked) donor_type.push('New');
        const status = [];
        if (document.getElementById('fltStatusEligible').checked) status.push('Eligible');
        if (document.getElementById('fltStatusIneligible').checked) status.push('Ineligible');
        if (document.getElementById('fltStatusDeferred').checked) status.push('Deferred');
        const via = [];
        if (document.getElementById('fltViaMobile').checked) via.push('Mobile');
        if (document.getElementById('fltViaSystem').checked) via.push('System');

        const searchInput = document.getElementById('searchInput');
        const payload = { donor_type, status, via, q: searchInput ? (searchInput.value || '').trim() : '' };
        fetch('../api/search_func/filter_search_account_medical_history.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.text())
        .then(t => { try { return JSON.parse(t); } catch(_) { return null; } })
        .then(res => {
            if (res && res.success && typeof res.html === 'string') {
                tbody.innerHTML = res.html;
            }
        })
        .catch(() => {});
    }
});


