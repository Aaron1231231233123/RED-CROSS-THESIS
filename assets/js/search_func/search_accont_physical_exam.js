// Debounced quick search for Physician Dashboard (Physical Examination)
// Replaces table rows (#screeningTableBody) with server-rendered HTML

document.addEventListener('DOMContentLoaded', function(){
    const input = document.getElementById('searchInput');
    const tbody = document.getElementById('screeningTableBody');
    if (!input || !tbody) return;

    const loadingId = 'searchLoading';
    let loading = document.getElementById(loadingId);
    if (!loading) {
        const container = document.querySelector('.search-container');
        if (container) {
            loading = document.createElement('div');
            loading.id = loadingId;
            loading.className = 'mt-2 text-muted';
            loading.style.display = 'none';
            loading.textContent = 'Searching...';
            container.appendChild(loading);
        }
    }

    const initialHTML = tbody.innerHTML;

    // Install a single delegated click handler to ensure buttons work after dynamic updates
    if (!window.__peSearchDelegated) {
        window.__peSearchDelegated = true;
        document.addEventListener('click', function(ev){
            const btn = ev.target && (ev.target.closest ? ev.target.closest('.view-btn, .edit-btn') : null);
            if (!btn) return;
            ev.preventDefault();
            ev.stopPropagation();
            try {
                const payload = btn.getAttribute('data-screening');
                const data = payload ? JSON.parse(payload) : null;
                if (!data) throw new Error('No data');
                window.currentScreeningData = data;
                if (typeof openDonorProfileModal === 'function') {
                    openDonorProfileModal(data);
                }
            } catch(_) {
                try { alert('Error selecting this record. Please try again.'); } catch(e) {}
            }
        }, true);
    }

    let t;
    input.addEventListener('input', function(){
        clearTimeout(t);
        const q = this.value.trim();
        t = setTimeout(() => doSearch(q), 400);
    });

    input.addEventListener('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(t);
            // Do not show global processing spinner on Enter; quick search only
            doSearch(this.value.trim());
        }
    });

    function rebindRowHandlers(){
        try {
            if (typeof attachButtonClickHandlers === 'function') attachButtonClickHandlers();
        } catch(_) {}
    }

    function doSearch(q){
        if (!q) {
            if (loading) loading.style.display = 'none';
            tbody.innerHTML = initialHTML;
            rebindRowHandlers();
            return;
        }
        if (loading) loading.style.display = 'block';

        // If filter checkboxes from filter_search_accont_physical_exam.js exist, honor them
        const donor_type = [];
        const fRet = document.getElementById('fltDonorReturning');
        const fNew = document.getElementById('fltDonorNew');
        if (fRet && fRet.checked) donor_type.push('Returning');
        if (fNew && fNew.checked) donor_type.push('New');
        const status = [];
        const fSA = document.getElementById('fltStatusAccepted');
        const fSD = document.getElementById('fltStatusDeferred');
        const fSR = document.getElementById('fltStatusRejected');
        const fSP = document.getElementById('fltStatusPending');
        if (fSP && fSP.checked) status.push('Pending');
        if (fSA && fSA.checked) status.push('Accepted');
        if (fSD && fSD.checked) status.push('Temporarily Deferred');
        if (fSR && fSR.checked) status.push('Rejected');

        const anyFilter = donor_type.length || status.length;
        if (anyFilter) {
            fetch('../api/search_func/filter_search_accont_physical_exam.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ donor_type, status, q })
            })
            .then(r => r.text()).then(text => { try { return JSON.parse(text); } catch(_) { return null; } })
            .then(res => {
                if (loading) loading.style.display = 'none';
                if (res && res.success && typeof res.html === 'string') {
                    tbody.innerHTML = res.html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No records found</td></tr>';
                }
                rebindRowHandlers();
            })
            .catch(() => {
                if (loading) loading.style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="8" class="text-danger">Search error. Please try again.</td></tr>';
                rebindRowHandlers();
            });
            return;
        }

        const url = '../api/search_func/search_accont_physical_exam.php?q=' + encodeURIComponent(q);
        // Avoid triggering global fetch wrapper spinner: use XMLHttpRequest for Enter search
        // If fetch is wrapped globally to show processing spinner, XHR avoids that side effect
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('Accept','application/json');
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) return;
            if (loading) loading.style.display = 'none';
            let res = null; try { res = JSON.parse(xhr.responseText); } catch(_) {}
            if (res && res.success && typeof res.html === 'string') {
                tbody.innerHTML = res.html;
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No records found</td></tr>';
            }
            rebindRowHandlers();
        };
        xhr.onerror = function(){
            if (loading) loading.style.display = 'none';
            tbody.innerHTML = '<tr><td colspan="8" class="text-danger">Search error. Please try again.</td></tr>';
            rebindRowHandlers();
        };
        xhr.send();
    }
});



