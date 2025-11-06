// Collection Status filter for Blood Collection Dashboard
// Expects checkbox inputs with ids: fltStatusCompleted, fltStatusFailed, fltStatusNotStarted
// Sends POST to filter_search_account_blood_collection.php and swaps #bloodCollectionTableBody

document.addEventListener('DOMContentLoaded', function(){
    const tbody = document.getElementById('bloodCollectionTableBody');
    const input = document.getElementById('searchInput');
    if (!tbody) return;

    // Ensure loading modal is available
    if (typeof FilterLoadingModal === 'undefined') {
        console.warn('FilterLoadingModal not loaded. Loading modal functionality may not work.');
    }

    // Build a dropdown filter bar like medical history
    const container = document.querySelector('.search-container');
    if (container) {
        const bar = document.createElement('div');
        bar.className = 'mt-2';
        bar.innerHTML = `
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Filters</button>
                    <div class="dropdown-menu p-3" style="min-width:260px;">
                        <div class="mb-2 fw-bold text-muted">Collection Status</div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="completed" id="fltStatusCompleted">
                            <label class="form-check-label" for="fltStatusCompleted">Completed</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="failed" id="fltStatusFailed">
                            <label class="form-check-label" for="fltStatusFailed">Failed</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="not started" id="fltStatusNotStarted">
                            <label class="form-check-label" for="fltStatusNotStarted">Not Started</label>
                        </div>
                        <div class="d-grid">
                            <button id="fltRunBlood" class="btn btn-danger btn-sm">Run</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(bar);
        const dropdownMenu = bar.querySelector('.dropdown-menu');
        if (dropdownMenu) dropdownMenu.addEventListener('click', function(e){ e.stopPropagation(); });
    }

    const fltCompleted = document.getElementById('fltStatusCompleted');
    const fltFailed = document.getElementById('fltStatusFailed');
    const fltNotStarted = document.getElementById('fltStatusNotStarted');
    const runBtn = document.getElementById('fltRunBlood');

    // No loading spinner for filter requests

    function currentStatus(){
        const status = [];
        if (fltCompleted && fltCompleted.checked) status.push('completed');
        if (fltFailed && fltFailed.checked) status.push('failed');
        if (fltNotStarted && fltNotStarted.checked) status.push('not started');
        return status;
    }

    function runFilter(){
        const status = currentStatus();
        const q = input ? input.value.trim() : '';
        if (!status.length && !q) {
            if (typeof Event === 'function' && input) input.dispatchEvent(new Event('input'));
            return;
        }

        // Show loading modal
        if (typeof FilterLoadingModal !== 'undefined') {
            FilterLoadingModal.show();
        }

        fetch('../api/search_func/filter_search_account_blood_collection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ status, q })
        })
        .then(r => r.text())
        .then(text => { let res; try { res = JSON.parse(text); } catch(_) { res = null; } return res; })
        .then(res => {
            if (res && res.success && typeof res.html === 'string') {
                tbody.innerHTML = res.html;
                try { if (typeof attachRowClickHandlers === 'function') attachRowClickHandlers(); } catch(_) {}
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted">No records found</td></tr>';
            }
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger">Filter error. Please try again.</td></tr>';
        })
        .finally(() => {
            // Hide loading modal when done
            if (typeof FilterLoadingModal !== 'undefined') {
                FilterLoadingModal.hide();
            }
        });
    }

    if (runBtn) runBtn.addEventListener('click', runFilter);
});


