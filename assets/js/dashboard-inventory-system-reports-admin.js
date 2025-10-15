// Static interactions for Forecast Reports (no backend)
(function(){
    const mockRows = [
        { month:'September 2025', type:'A+', demand:80, donations:70 },
        { month:'September 2025', type:'O-', demand:40, donations:25 },
        { month:'September 2025', type:'B+', demand:50, donations:60 },
        { month:'October 2025', type:'A+', demand:90, donations:75 },
        { month:'October 2025', type:'O-', demand:55, donations:35 },
        { month:'October 2025', type:'AB+', demand:60, donations:45 },
        { month:'October 2025', type:'B-', demand:30, donations:20 },
        { month:'November 2025', type:'O+', demand:120, donations:100 },
        { month:'November 2025', type:'A-', demand:35, donations:25 },
        { month:'November 2025', type:'AB-', demand:20, donations:28 },
    ].map(r => ({...r, balance: r.donations - r.demand}));

    const pageSize = 3;
    let currentPage = 1;
    const tableBody = document.querySelector('#reportsTable tbody');
    const pagination = document.getElementById('pagination');
    const monthFilter = document.getElementById('monthFilter');
    const typeFilter = document.getElementById('typeFilter');
    const searchInput = document.getElementById('searchInput');

    function statusFor(balance){
        if(balance <= -10) return {cls:'status-critical', label:'Critical', icon:'fa-xmark'};
        if(balance < 0) return {cls:'status-low', label:'Low', icon:'fa-triangle-exclamation'};
        return {cls:'status-surplus', label:'Surplus', icon:'fa-check'};
    }

    function filtered(){
        const m = monthFilter.value;
        const t = typeFilter.value;
        const s = searchInput.value.toLowerCase();
        return mockRows.filter(r => (
            (!m || r.month === m || m === 'All') &&
            (t === 'all' || r.type === t) &&
            (`${r.month} ${r.type}`.toLowerCase().includes(s))
        ));
    }

    function renderTable(){
        const rows = filtered();
        const pages = Math.max(1, Math.ceil(rows.length / pageSize));
        if(currentPage > pages) currentPage = pages;
        const start = (currentPage - 1) * pageSize;
        const pageRows = rows.slice(start, start + pageSize);
        tableBody.innerHTML = pageRows.map(r => {
            const st = statusFor(r.balance);
            const bal = r.balance > 0 ? `+${r.balance}` : `${r.balance}`;
            return `
            <tr>
                <td>${r.month}</td>
                <td>${r.type}</td>
                <td>${r.demand}</td>
                <td>${r.donations}</td>
                <td>${bal}</td>
                <td><span class="status-badge ${st.cls}"><i class="fa ${st.icon}"></i> ${st.label}</span></td>
                <td><button class="action-btn view-btn" data-type="${r.type}" data-month="${r.month}"><i class="fa fa-eye"></i></button></td>
            </tr>`;
        }).join('');
        renderPagination(pages);
        bindRowActions();
    }

    function renderPagination(pages){
        let html = '';
        for(let i=1;i<=pages;i++){
            html += `<li class="page-item ${i===currentPage?'active':''}"><a class="page-link" href="#">${i}</a></li>`;
        }
        pagination.innerHTML = html;
        Array.from(pagination.querySelectorAll('a')).forEach((a, idx)=>{
            a.addEventListener('click', (e)=>{ e.preventDefault(); currentPage = idx+1; renderTable(); });
        });
    }

    function bindRowActions(){
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => openDetails(btn.dataset.month, btn.dataset.type));
        });
    }

    function openDetails(month, type){
        const row = mockRows.find(r => r.month===month && r.type===type);
        if(!row) return;
        const body = document.getElementById('detailsBody');
        body.innerHTML = `
            <div class="small text-muted">Date: ${month}</div>
            <h5 class="mt-1">Forecast Details</h5>
            <h6 class="mt-3">Demand Breakdown</h6>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Hospital Name</th><th>Forecasted Demand</th><th>% of Total Demand</th></tr></thead>
                <tbody><tr><td>-</td><td>${row.demand}</td><td>October</td></tr></tbody>
            </table></div>
            <h6 class="mt-3">Donation Breakdown</h6>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Source</th><th>Forecasted Donations</th><th>Date/Drive</th></tr></thead>
                <tbody>
                    <tr><td>Mobile Drive - Villa Arevalo, Iloilo City</td><td>${Math.round(row.donations*0.4)}</td><td>October</td></tr>
                    <tr><td>Walk-In Donors</td><td>${Math.round(row.donations*0.5)}</td><td>October</td></tr>
                    <tr><td>Patient-Directed</td><td>${Math.round(row.donations*0.1)}</td><td>October</td></tr>
                </tbody>
            </table></div>
            <h6 class="mt-3">Summary</h6>
            <ul class="mb-0">
                <li>Total Demand: ${row.demand}</li>
                <li>Total Donations: ${row.donations}</li>
                <li>Projected ${row.balance>=0?'Surplus':'Shortage'}: ${row.balance}</li>
            </ul>
        `;
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();
    }

    monthFilter.addEventListener('change', ()=>{ currentPage=1; renderTable(); });
    typeFilter.addEventListener('change', ()=>{ currentPage=1; renderTable(); });
    searchInput.addEventListener('input', ()=>{ currentPage=1; renderTable(); });

    // Charts (lazy init)
    let chartsInitialized = false;
    function initCharts(){
        if (chartsInitialized) return;
        chartsInitialized = true;
        const months = ['A+','O-','B+','A+','O-','AB+','B-','O+','A-','AB-'];
        const demand = mockRows.map(r=>r.demand);
        const donations = mockRows.map(r=>r.donations);
        const balances = mockRows.map(r=>r.balance);

        new Chart(document.getElementById('barChart'), {
            type:'bar',
            data:{ labels: months, datasets:[
                {label:'Demand', data:demand, backgroundColor:'#94102299'},
                {label:'Donations', data:donations, backgroundColor:'#1f2937'}
            ]},
            options:{responsive:true, plugins:{legend:{position:'bottom'}}}
        });

        new Chart(document.getElementById('lineChart'), {
            type:'line',
            data:{ labels: months, datasets:[
                {label:'Balance', data:balances, borderColor:'#0f766e', backgroundColor:'#0f766e22', tension:.3}
            ]},
            options:{responsive:true, plugins:{legend:{display:false}}}
        });

        const demandShare = { 'A+':18,'O-':14,'B+':9,'A-':7,'O+':22,'AB+':6,'B-':10,'AB-':14 };
        new Chart(document.getElementById('pieChart'), {
            type:'pie',
            data:{ labels:Object.keys(demandShare), datasets:[{ data:Object.values(demandShare), backgroundColor:['#ef4444','#f97316','#f59e0b','#84cc16','#22c55e','#06b6d4','#3b82f6','#a855f7'] }]},
            options:{responsive:true, plugins:{legend:{position:'bottom'}}}
        });
    }

    // Lazy initialize charts when chart container is visible or when browser is idle
    function scheduleChartsInit(){
        const chartsSection = document.getElementById('barChart');
        if (!chartsSection) return;
        // Use IntersectionObserver to init when visible
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    initCharts();
                    obs.disconnect();
                }
            });
        }, { root: null, rootMargin: '100px', threshold: 0.1 });
        observer.observe(chartsSection);
        // Fallback: requestIdleCallback or timeout
        const idleCb = window.requestIdleCallback || function(cb){ setTimeout(cb, 300); };
        idleCb(() => initCharts());
    }

    // Export button (simple print to PDF)
    document.getElementById('exportBtn')?.addEventListener('click', ()=>{
        window.print();
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function(){
        renderTable();
        // Defer charts init for faster first paint
        scheduleChartsInit();
    });
})();


