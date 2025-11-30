(() => {
    const API_URL = '../api/forecast-reports-api-python.php';
    const tableBody = document.querySelector('#reportsTable tbody');
    const projectedPanel = document.getElementById('projectedStockPanel');
    const refreshBtn = document.getElementById('refreshReportsBtn');
    const detailsModalEl = document.getElementById('detailsModal');
    const detailsBody = document.getElementById('detailsBody');
    const TABLE_COLUMNS = 7;
    const exportBtn = document.getElementById('exportBtn');
    const yearFilterCombined = document.getElementById('yearFilterCombined');
    const yearFilterSupply = document.getElementById('yearFilterSupply');
    const yearFilterDemand = document.getElementById('yearFilterDemand');

    let forecastCache = [];
    let summaryCache = {};
    let activeDetailContext = null;
    let selectedYear = new Date().getFullYear(); // Default to current year

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
        tableBody.innerHTML = `<tr><td colspan="${TABLE_COLUMNS}" class="text-center py-4 ${isError ? 'text-danger' : ''}">
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

        summaryCache = {
            total_forecasted_demand,
            total_forecasted_supply,
            projected_balance,
            target_stock_level,
            expiring_weekly,
            expiring_monthly,
        };

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

    function formatNumber(value = 0) {
        const num = Number(value) || 0;
        return num.toLocaleString();
    }

    function statusBadge(status = 'Balanced') {
        const normalized = (status || '').toLowerCase();
        if (normalized.includes('critical') || normalized.includes('shortage')) {
            return { label: 'Critical shortage', cls: 'status-badge status-low' };
        }
        if (normalized.includes('surplus')) {
            return { label: 'Surplus', cls: 'status-badge status-surplus' };
        }
        return { label: 'Adequate', cls: 'status-badge status-adequate' };
    }

    function renderTable(rows = []) {
        if (!tableBody) return;
        if (!rows.length) {
            setTableMessage('No forecast records available');
            return;
        }

        forecastCache = rows;

        const fragment = document.createDocumentFragment();
        rows.forEach((row, idx) => {
            const tr = document.createElement('tr');
            const badge = statusBadge(row.status || 'Balanced');
            tr.innerHTML = `
                <td>${row.month_label || 'Next Month'}</td>
                <td>${row.blood_type}</td>
                <td>${formatNumber(row.forecasted_demand)}</td>
                <td>${formatNumber(row.forecasted_supply)}</td>
                <td>${formatNumber(row.gap ?? (row.forecasted_supply - row.forecasted_demand))}</td>
                <td><span class="${badge.cls}">${badge.label}</span></td>
                <td><button class="btn btn-outline-danger btn-sm view-details-btn" data-index="${idx}">
                    <i class="fas fa-eye me-1"></i>View
                </button></td>
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

    function formatPercentage(part = 0, whole = 0) {
        if (!whole) return '0%';
        return `${((part / whole) * 100).toFixed(1)}%`;
    }

    function buildSummaryList(row) {
        const balance = row.gap ?? (row.forecasted_supply - row.forecasted_demand);
        const balanceText = `Projected balance: ${balance >= 0 ? '+' : ''}${formatNumber(balance)} units`;
        const normalized = (row.status || '').toLowerCase();
        let riskLevel = 'Moderate';
        if (normalized.includes('surplus')) riskLevel = 'Low';
        if (normalized.includes('shortage') || normalized.includes('critical')) riskLevel = 'High';
        return `
            <ul class="mb-0">
                <li>Total Forecasted Demand: ${formatNumber(row.forecasted_demand)} units</li>
                <li>Total Forecasted Donations: ${formatNumber(row.forecasted_supply)} units</li>
                <li>${balanceText}</li>
                <li>Risk Level: ${riskLevel}</li>
            </ul>
        `;
    }

    function openDetails(row) {
        if (!detailsModalEl || !detailsBody || !row) return;
        const totalDemand = summaryCache.total_forecasted_demand || 0;
        const totalSupply = summaryCache.total_forecasted_supply || 0;
        const demandPct = formatPercentage(row.forecasted_demand, totalDemand);
        const supplyPct = formatPercentage(row.forecasted_supply, totalSupply);
        const balance = row.gap ?? (row.forecasted_supply - row.forecasted_demand);
        const normalized = (row.status || '').toLowerCase();
        const alertClass = normalized.includes('shortage') || balance < 0 ? 'alert-danger'
            : normalized.includes('surplus') ? 'alert-success' : 'alert-secondary';
        const badge = statusBadge(row.status || 'Balanced');
        const statusNarrative = normalized.includes('shortage')
            ? 'Below hospital demand and buffer target.'
            : normalized.includes('surplus')
                ? 'Above projected demand; monitor expiries and redistribute if needed.'
                : 'Buffer level meets the target range.';

        const detailHtml = `
            <div class="small text-muted">Date: ${row.month_label || 'Next Month'}</div>
            <h5 class="mt-1">Forecast Details for ${row.blood_type}</h5>

            <h6 class="mt-3">Demand Breakdown</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Blood Type</th>
                            <th>Forecasted Demand</th>
                            <th>% of Total Demand</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>${row.blood_type}</td>
                            <td>${formatNumber(row.forecasted_demand)}</td>
                            <td>${demandPct}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h6 class="mt-3">Donation Breakdown</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Blood Type</th>
                            <th>Forecasted Donations</th>
                            <th>% of Total Donations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>${row.blood_type}</td>
                            <td>${formatNumber(row.forecasted_supply)}</td>
                            <td>${supplyPct}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h6 class="mt-3">Summary</h6>
            <div class="alert ${alertClass}">
                <strong>Projected balance:</strong> ${balance >= 0 ? '+' : ''}${formatNumber(balance)} units
            </div>
            <div class="mb-3">
                ${buildSummaryList(row)}
            </div>

            <h6 class="mt-3">Status</h6>
            <div class="alert alert-light border d-flex align-items-center gap-2">
                <span class="${badge.cls}">${badge.label}</span>
                <span class="text-muted ms-2">${statusNarrative}</span>
            </div>
        `;
        detailsBody.innerHTML = detailHtml;
        activeDetailContext = {
            monthKey: row.month_key,
            bloodType: row.blood_type,
        };

        const modal = window.bootstrap?.Modal.getOrCreateInstance(detailsModalEl);
        modal?.show();
    }

    async function loadData(force = false) {
        toggleLoading(true);
        try {
            const yearParam = selectedYear ? `&year=${selectedYear}` : '';
            const url = `${API_URL}?ts=${Date.now()}${yearParam}${force ? `&refresh=${Date.now()}` : ''}`;
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
            window.ForecastExport?.setDashboardSnapshot({
                forecastRows: data.forecast_rows || [],
                summary: data.summary || {},
                projectedStock: data.projected_stock || [],
            });
        } catch (error) {
            console.error('Error loading forecast data:', error);
            setTableMessage(error.message || 'Failed to load forecast data', true);
        }
    }

    function initializeYearFilter() {
        const filters = [yearFilterCombined, yearFilterSupply, yearFilterDemand].filter(f => f !== null);
        if (filters.length === 0) return;
        
        const currentYear = new Date().getFullYear();
        // Start from 2023 (or earlier) to include all historical data
        // This ensures we can filter by any year that has data
        const startYear = 2023;
        
        // Populate all year filters
        filters.forEach(filter => {
            // Clear existing options
            filter.innerHTML = '';
            
            // Populate with years from startYear to current year
            for (let year = startYear; year <= currentYear; year++) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === currentYear) {
                    option.selected = true;
                    selectedYear = currentYear;
                }
                filter.appendChild(option);
            }
            
            // Add event listener for year changes
            filter.addEventListener('change', (e) => {
                selectedYear = parseInt(e.target.value, 10);
                // Update all filters to the same year
                filters.forEach(f => {
                    if (f !== filter) {
                        f.value = selectedYear;
                    }
                });
                updateChartTitles(selectedYear);
                // Force refresh to regenerate charts with new year filter
                loadData(true);
            });
        });
    }

    function updateChartTitles(year) {
        const combinedTitle = document.getElementById('combinedChartTitle');
        const supplyTitle = document.getElementById('supplyChartTitle');
        const demandTitle = document.getElementById('demandChartTitle');
        
        if (combinedTitle) {
            combinedTitle.textContent = `${year} Supply vs Demand & 3-Month Forecast`;
        }
        if (supplyTitle) {
            supplyTitle.textContent = `${year} Blood Supply & 3-Month Forecast`;
        }
        if (demandTitle) {
            demandTitle.textContent = `${year} Hospital Demand & 3-Month Forecast`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initializeYearFilter();
        updateChartTitles(selectedYear);
        loadData();
    });
    
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => loadData(true));
    }

    if (tableBody) {
        tableBody.addEventListener('click', (event) => {
            const btn = event.target.closest('.view-details-btn');
            if (!btn) return;
            const idx = Number(btn.dataset.index);
            const row = forecastCache[idx];
            openDetails(row);
        });
    }

    exportBtn?.addEventListener('click', () => {
        if (window.ForecastExport) {
            window.ForecastExport.openOptions(activeDetailContext || {});
        } else {
            alert('Export module is still loading. Please try again in a moment.');
        }
    });
})();

