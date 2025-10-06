// Debounced quick search for Phlebotomist Dashboard (Blood Collection)
// Replaces table rows (#bloodCollectionTableBody) with server-rendered HTML

document.addEventListener('DOMContentLoaded', function(){
    const input = document.getElementById('searchInput');
    const tbody = document.getElementById('bloodCollectionTableBody');
    if (!input || !tbody) return;

    // No loading spinner for search input

    const initialHTML = tbody.innerHTML;

    // Delegated click handler to keep buttons working after dynamic updates
    if (!window.__bcSearchDelegated) {
        window.__bcSearchDelegated = true;
        document.addEventListener('click', function(ev){
            const btn = ev.target && (ev.target.closest ? ev.target.closest('.view-donor-btn, .collect-btn') : null);
            if (!btn) return;
            // Allow page-level listeners to handle actions; do not prevent unless necessary
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
            doSearch(this.value.trim());
        }
    });

    function rebindRowHandlers(){
        try {
            // Re-attach row click handlers defined in the page script
            if (typeof attachRowClickHandlers === 'function') attachRowClickHandlers();
        } catch(_) {}
    }

    function selectedStatuses(){
        const s = [];
        const c = document.getElementById('fltStatusCompleted');
        const f = document.getElementById('fltStatusFailed');
        const n = document.getElementById('fltStatusNotStarted');
        if (c && c.checked) s.push('completed');
        if (f && f.checked) s.push('failed');
        if (n && n.checked) s.push('not started');
        return s;
    }

    function doSearch(q){
        if (!q) {
            tbody.innerHTML = initialHTML;
            rebindRowHandlers();
            return;
        }
        // No spinner shown for search

        const status = selectedStatuses();
        if (status.length) {
            // When filters are active, use the filter API so search and filters combine
            fetch('../api/search_func/filter_search_account_blood_collection.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ status, q })
            })
            .then(r => r.text()).then(text => { let res; try { res = JSON.parse(text); } catch(_) { res = null; } return res; })
            .then(res => {
                if (loading) loading.style.display = 'none';
                if (res && res.success && typeof res.html === 'string') {
                    tbody.innerHTML = res.html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-muted">No records found</td></tr>';
                }
                rebindRowHandlers();
            })
            .catch(() => {
                if (loading) loading.style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="7" class="text-danger">Search error. Please try again.</td></tr>';
                rebindRowHandlers();
            });
            return;
        }

        const url = '../api/search_func/search_account_blood_collection.php?q=' + encodeURIComponent(q);
        // Use XHR to avoid any global fetch wrappers that show processing spinners
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('Accept','application/json');
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) return;
            let res = null; try { res = JSON.parse(xhr.responseText); } catch(_) {}
            if (res && res.success && typeof res.html === 'string') {
                tbody.innerHTML = res.html;
            } else {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted">No records found</td></tr>';
            }
            rebindRowHandlers();
        };
        xhr.onerror = function(){
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger">Search error. Please try again.</td></tr>';
            rebindRowHandlers();
        };
        xhr.send();
    }
});


