(() => {
    const API_URL = '../api/forecast-reports-api-python.php';
    const tableBody = document.querySelector('#reportsTable tbody');
    const projectedPanel = document.getElementById('projectedStockPanel');
    const refreshBtn = document.getElementById('refreshReportsBtn');

    const kpiIds = {
        demand: 'kpiDemand',
        supply: 'kpiDonations',
        balance: 'kpiBalance',
        target: 'kpiTargetStock',
        weekly: 'kpiExpiringWeekly',
        monthly: 'kpiExpiringMonthly',
    };

    const chartIds = {
        supply: 'supplyChartImg',
        demand: 'demandChartImg',
        comparison: 'comparisonChartImg',
        projected_stock: 'projectedChartImg',
    };

    const iframeIds = {
        interactive_supply: 'interactiveSupplyFrame',
        interactive_demand: 'interactiveDemandFrame',
        interactive_combined: 'interactiveCombinedFrame',
        projected_stock_html: 'projectedStockFrame',
    };

    function setTableMessage(message, isError = false) {
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 ${isError ? 'text-danger' : ''}">
            ${isError ? '<i class="fas fa-exclamation-triangle me-2"></i>' : '<i class="fas fa-spinner fa-spin me-2"></i>'}
            ${message}
        </td></tr>`;
    }

    function toggleLoading(isLoading) {
        if (isLoading) {
            setTableMessage('Loading forecast data...');
            Object.values(kpiIds).forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '...';
            });
        }
    }

    function updateKpis(summary = {}) {
        const {
            total_forecasted_demand = 0,
            total_forecasted_supply = 0,
            projected_balance = 0,
            target_stock_level = 0,
            expiring_weekly = 0,
            expiring_monthly = 0,
        } = summary;

        const mappings = {
            demand: Math.round(total_forecasted_demand),
            supply: Math.round(total_forecasted_supply),
            balance: Math.round(projected_balance),
            target: Math.round(target_stock_level),
            weekly: Math.round(expiring_weekly),
            monthly: Math.round(expiring_monthly),
        };

        Object.entries(mappings).forEach(([key, value]) => {
            const el = document.getElementById(kpiIds[key]);
            if (el) {
                if (key === 'balance') {
                    el.textContent = value >= 0 ? `+${value}` : value;
                    el.style.color = value < 0 ? '#dc3545' : '#1f2937';
                } else {
                    el.textContent = value.toLocaleString();
                }
            }
        });
    }

    function renderTable(rows = []) {
        if (!tableBody) return;
        if (!rows.length) {
            setTableMessage('No forecast records available');
            return;
        }

        const fragment = document.createDocumentFragment();
        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${row.month_label || 'Next Month'}</td>
                <td>${row.blood_type}</td>
                <td>${row.forecasted_demand?.toLocaleString() ?? 0}</td>
                <td>${row.forecasted_supply?.toLocaleString() ?? 0}</td>
                <td>${row.gap?.toLocaleString() ?? 0}</td>
                <td>${row.status || 'Balanced'}</td>
            `;
            fragment.appendChild(tr);
        });

        tableBody.innerHTML = '';
        tableBody.appendChild(fragment);
    }

    function renderProjectedStock(items = []) {
        if (!projectedPanel) return;
        if (!items.length) {
            projectedPanel.innerHTML = '<p class="text-muted mb-0">No projected stock data.</p>';
            return;
        }

        const list = document.createElement('ul');
        list.className = 'list-group';
        items.forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            const projectedValue = item.Projected_Stock ?? item['Projected Stock Level (Next Month)'] ?? 0;
            li.innerHTML = `
                <span>
                    <strong>${item['Blood_Type']}</strong><br/>
                    <small>Projected: ${projectedValue}</small>
                </span>
                <span>${item['Buffer_Status'] || ''}</span>
            `;
            list.appendChild(li);
        });
        projectedPanel.innerHTML = '';
        projectedPanel.appendChild(list);
    }

    function updateResource(elementId, path) {
        const el = document.getElementById(elementId);
        if (el && path) {
            const cacheBuster = `${path}${path.includes('?') ? '&' : '?'}cb=${Date.now()}`;
            
            // Add error handler for images
            if (el.tagName === 'IMG') {
                el.onerror = function() {
                    this.style.display = 'none';
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'alert alert-warning mt-2';
                    errorMsg.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>Chart image not available. Please click "Refresh Data" to generate charts.`;
                    this.parentNode.appendChild(errorMsg);
                };
                el.onload = function() {
                    // Remove any error messages if image loads successfully
                    const errorMsg = this.parentNode.querySelector('.alert-warning');
                    if (errorMsg) errorMsg.remove();
                };
            }
            
            el.src = cacheBuster;
        }
    }

    function updateCharts(charts = {}) {
        console.log('Updating charts with paths:', charts);
        Object.entries(chartIds).forEach(([key, id]) => {
            if (charts[key]) {
                console.log(`Setting ${id} to ${charts[key]}`);
                updateResource(id, charts[key]);
            } else {
                console.warn(`Chart path missing for ${key} (element: ${id})`);
            }
        });
        Object.entries(iframeIds).forEach(([key, id]) => {
            if (charts[key]) {
                updateResource(id, charts[key]);
            }
        });
    }

    async function loadData(force = false) {
        toggleLoading(true);
        try {
            const url = `${API_URL}?ts=${Date.now()}${force ? `&refresh=${Date.now()}` : ''}`;
            const response = await fetch(url, { cache: 'no-cache' });
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Unable to load forecasts');
            }
            console.log('Forecast data loaded:', data);
            if (data.asset_errors && data.asset_errors.length > 0) {
                console.warn('Asset generation errors:', data.asset_errors);
            }
            updateKpis(data.summary);
            renderTable(data.forecast_rows);
            renderProjectedStock(data.projected_stock);
            updateCharts(data.charts);
        } catch (error) {
            console.error('Error loading forecast data:', error);
            setTableMessage(error.message || 'Failed to load forecast data', true);
        }
    }

    document.addEventListener('DOMContentLoaded', () => loadData());
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => loadData(true));
    }
})();

