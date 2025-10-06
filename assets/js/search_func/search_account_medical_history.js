// Debounced global search for Interviewer Dashboard (Medical History)
// Hits the API endpoint and replaces table rows with returned HTML

document.addEventListener('DOMContentLoaded', function(){
    const input = document.getElementById('searchInput');
		let loading = document.getElementById('searchLoading');
    const tbody = document.getElementById('donorTableBody');
    if (!input || !tbody) return;

		// Ensure a loading indicator exists (align with other search modules)
		if (!loading) {
			const container = document.querySelector('.search-container');
			if (container) {
				loading = document.createElement('div');
				loading.id = 'searchLoading';
				loading.className = 'mt-2 text-muted';
				loading.style.display = 'none';
				loading.textContent = 'Searching...';
				container.appendChild(loading);
			}
		}

    // Cache initial table to allow reset when search is cleared
    const initialHTML = tbody.innerHTML;

    let t;
    input.addEventListener('input', function(){
        clearTimeout(t);
        const q = this.value.trim();
        t = setTimeout(() => doSearch(q), 500);
    });

    // Trigger search when pressing Enter
    input.addEventListener('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(t);
            doSearch(this.value.trim());
        }
    });

    // If the input has a prefilled value, run an initial search after load
    if (input.value && input.value.trim().length > 0) {
        doSearch(input.value.trim());
    }

    function doSearch(q){
        // If blank, restore default table and skip API
        if (!q) {
            if (loading) loading.style.display = 'none';
            tbody.innerHTML = initialHTML;
            return;
        }
        if (loading) loading.style.display = 'block';

        // Combine with current filters if any are selected
        const donor_type = [];
        const fRet = document.getElementById('fltDonorReturning');
        const fNew = document.getElementById('fltDonorNew');
        if (fRet && fRet.checked) donor_type.push('Returning');
        if (fNew && fNew.checked) donor_type.push('New');
        const status = [];
        const fSE = document.getElementById('fltStatusEligible');
        const fSI = document.getElementById('fltStatusIneligible');
        const fSD = document.getElementById('fltStatusDeferred');
        if (fSE && fSE.checked) status.push('Eligible');
        if (fSI && fSI.checked) status.push('Ineligible');
        if (fSD && fSD.checked) status.push('Deferred');
        const via = [];
        const fVM = document.getElementById('fltViaMobile');
        const fVS = document.getElementById('fltViaSystem');
        if (fVM && fVM.checked) via.push('Mobile');
        if (fVS && fVS.checked) via.push('System');

        const anyFilter = donor_type.length || status.length || via.length;
        if (anyFilter) {
            fetch('../api/search_func/filter_search_account_medical_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ donor_type, status, via, q })
            })
            .then(r => r.text())
            .then(text => { let res; try { res = JSON.parse(text); } catch(_) { res = null; } return res; })
            .then(res => {
                if (loading) loading.style.display = 'none';
                if (res && res.success && typeof res.html === 'string') {
                    tbody.innerHTML = res.html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Name can\'t be found</td></tr>';
                }
            })
            .catch(() => {
                if (loading) loading.style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="9" class="text-danger">Search error. Please try again.</td></tr>';
            });
            return;
        }

        // No filters selected: use quick search API
        const url = '../api/search_func/search_account_medical_history.php?q=' + encodeURIComponent(q);
        fetch(url, { headers: { 'Accept': 'application/json' }})
            .then(r => r.text())
            .then(text => { let res; try { res = JSON.parse(text); } catch (e) { res = null; } return res; })
            .then(res => {
                if (loading) loading.style.display = 'none';
                if (res && res.success && typeof res.html === 'string') {
                    tbody.innerHTML = res.html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Name can\'t be found</td></tr>';
                }
            })
            .catch(() => {
                if (loading) loading.style.display = 'none';
                tbody.innerHTML = '<tr><td colspan="9" class="text-danger">Search error. Please try again.</td></tr>';
            });
    }
});


