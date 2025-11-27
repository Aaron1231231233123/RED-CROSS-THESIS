(() => {
    const MODAL_ID = 'forecastExportModal';
    const MONTH_SELECT_ID = `${MODAL_ID}-month`;
    const TYPE_SELECT_ID = `${MODAL_ID}-type`;
    const GENERATE_BTN_ID = `${MODAL_ID}-generate`;
    const MONTH_FORMATTER = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
    const DATA_URL = '../api/forecast-reports-api-python.php';
    const SUMMARY_STORAGE_KEY = 'forecastSummaryPayload';

    const state = {
        dashboardSnapshot: {
            forecastRows: [],
            summary: {},
            projectedStock: [],
        },
        summaryPayload: null,
        isLoadingSummary: false,
    };

    const formatNumber = (value = 0) => {
        const num = Number(value) || 0;
        return num.toLocaleString(undefined, { maximumFractionDigits: 0 });
    };

    const toDate = (value) => {
        if (!value) return null;
        const parsed = new Date(value);
        return Number.isNaN(parsed.valueOf()) ? null : parsed;
    };

    const labelMonth = (value) => {
        const date = toDate(value);
        return date ? MONTH_FORMATTER.format(date) : value || 'Undated';
    };

    async function ensureSummaryDataset(force = false) {
        if (!force && state.summaryPayload) {
            return state.summaryPayload;
        }

        state.isLoadingSummary = true;
        try {
            const response = await fetch(`${DATA_URL}?ts=${Date.now()}&scope=summary`, {
                cache: 'no-cache',
            });
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Unable to load summary data');
            }
            state.summaryPayload = {
                monthlyDonations: data.monthly_donations || [],
                monthlyRequests: data.monthly_requests || [],
                forecastRows: data.forecast_rows || [],
                projectedStock: data.projected_stock || [],
                summary: data.summary || {},
            };
            return state.summaryPayload;
        } finally {
            state.isLoadingSummary = false;
        }
    }

    function ensureModal() {
        if (document.getElementById(MODAL_ID)) return;

        const modalHtml = `
            <div class="modal fade" id="${MODAL_ID}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title">Export Forecast Summary</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Use data up to</label>
                                    <select class="form-select" id="${MONTH_SELECT_ID}"></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Blood Type Focus</label>
                                    <select class="form-select" id="${TYPE_SELECT_ID}"></select>
                                </div>
                            </div>
                            <div class="alert alert-secondary mt-3 mb-0">
                                Data is aggregated from the earliest record up to the month you select, then paired with the latest forecast status.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="${GENERATE_BTN_ID}">
                                Generate Summary
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        document.getElementById(GENERATE_BTN_ID)?.addEventListener('click', () => {
            const monthKey = document.getElementById(MONTH_SELECT_ID)?.value || 'all';
            const bloodType = document.getElementById(TYPE_SELECT_ID)?.value || 'all';
            generateReport({ monthKey, bloodType });
        });
    }

    function collectMonthOptions() {
        const payload = state.summaryPayload;
        if (!payload) return [];

        const keys = new Set();
        const push = row => row?.month && keys.add(row.month);
        (payload.monthlyDonations || []).forEach(push);
        (payload.monthlyRequests || []).forEach(push);
        return Array.from(keys).sort().map(key => [key, labelMonth(key)]);
    }

    function collectBloodTypes() {
        const set = new Set();
        const sourceRows = (state.summaryPayload?.forecastRows && state.summaryPayload.forecastRows.length
            ? state.summaryPayload.forecastRows
            : state.dashboardSnapshot.forecastRows) || [];
        sourceRows.forEach(row => row?.blood_type && set.add(row.blood_type));
        return Array.from(set).sort();
    }

    function populateOptions(context = {}) {
        const monthSelect = document.getElementById(MONTH_SELECT_ID);
        const typeSelect = document.getElementById(TYPE_SELECT_ID);
        if (!monthSelect || !typeSelect) return;

        const months = collectMonthOptions();
        const monthOptions = months.length
            ? months.map(([value, label]) => `<option value="${value}">${label}</option>`).join('')
            : '<option value="all">All months</option>';
        monthSelect.innerHTML = `<option value="all">All months (complete history)</option>${monthOptions}`;

        const types = collectBloodTypes();
        const typeOptions = types.length
            ? types.map(type => `<option value="${type}">${type}</option>`).join('')
            : '';
        typeSelect.innerHTML = `<option value="all">All blood types</option>${typeOptions}`;

        if (context.monthKey && monthSelect.querySelector(`option[value="${context.monthKey}"]`)) {
            monthSelect.value = context.monthKey;
        }
        if (context.bloodType && typeSelect.querySelector(`option[value="${context.bloodType}"]`)) {
            typeSelect.value = context.bloodType;
        }
    }

    function buildTimeline(payload, monthKey, bloodType) {
        const cutoff = monthKey === 'all' ? null : toDate(monthKey);
        const buckets = new Map();

        const digest = (row, targetKey, valueKey) => {
            if (!row?.month) return;
            const rowDate = toDate(row.month);
            if (cutoff && rowDate && rowDate > cutoff) return;
            if (bloodType !== 'all' && row.blood_type !== bloodType) return;
            const key = row.month;
            const bucket = buckets.get(key) || {
                month: key,
                label: labelMonth(key),
                supply: 0,
                demand: 0,
            };
            bucket[targetKey] += Number(row[valueKey]) || 0;
            buckets.set(key, bucket);
        };

        (payload.monthlyDonations || []).forEach(row => digest(row, 'supply', 'units_collected'));
        (payload.monthlyRequests || []).forEach(row => digest(row, 'demand', 'units_requested'));

        // Append forecast snapshot if not already present
        const nextForecast = (payload.forecastRows || []).find(row => {
            if (bloodType !== 'all' && row.blood_type !== bloodType) return false;
            if (monthKey === 'all') return true;
            return row.month_key === monthKey;
        });
        if (nextForecast) {
            const key = nextForecast.month_key;
            if (!cutoff || (key && (!cutoff || toDate(key) <= cutoff))) {
                if (!buckets.has(key)) {
                    buckets.set(key, {
                        month: key,
                        label: labelMonth(key),
                        supply: Number(nextForecast.forecasted_supply) || 0,
                        demand: Number(nextForecast.forecasted_demand) || 0,
                    });
                }
            }
        }

        const rows = Array.from(buckets.values());
        rows.sort((a, b) => toDate(a.month) - toDate(b.month));
        return rows;
    }

    function summarizeTimeline(timeline) {
        if (!timeline.length) {
            return {
                totalSupply: 0,
                totalDemand: 0,
                avgSupply: 0,
                avgDemand: 0,
                latestSupply: 0,
                latestDemand: 0,
                coverageRatio: 0,
            };
        }
        const totalSupply = timeline.reduce((sum, row) => sum + row.supply, 0);
        const totalDemand = timeline.reduce((sum, row) => sum + row.demand, 0);
        const avgSupply = totalSupply / timeline.length;
        const avgDemand = totalDemand / timeline.length;
        const latestSupply = timeline[timeline.length - 1].supply;
        const latestDemand = timeline[timeline.length - 1].demand;
        const coverageRatio = totalDemand ? totalSupply / totalDemand : 0;
        return { totalSupply, totalDemand, avgSupply, avgDemand, latestSupply, latestDemand, coverageRatio };
    }

    function buildTimelineTable(timeline) {
        if (!timeline.length) {
            return '<p class="text-muted mb-0">Historical activity unavailable for this selection.</p>';
        }
        const body = timeline.map(row => {
            const gap = row.supply - row.demand;
            const gapClass = gap < 0 ? 'text-danger' : gap > 0 ? 'text-success' : '';
            const supplyWidth = Math.min(100, row.supply ? (row.supply / (row.supply + row.demand)) * 100 : 0);
            return `
                <tr>
                    <td>${row.label}</td>
                    <td>${formatNumber(row.supply)}</td>
                    <td>${formatNumber(row.demand)}</td>
                    <td class="${gapClass}">${gap >= 0 ? '+' : ''}${formatNumber(gap)}</td>
                    <td>
                        <div class="summary-timeline-bars">
                            <span class="supply" style="width:${supplyWidth}%"></span>
                            <span class="demand" style="width:${100 - supplyWidth}%"></span>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        return `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Month</th>
                            <th>Collected Units</th>
                            <th>Hospital Requests</th>
                            <th>Balance</th>
                            <th>Supply vs Demand</th>
                        </tr>
                    </thead>
                    <tbody>${body}</tbody>
                </table>
            </div>`;
    }

    function buildForecastTable(payload, bloodType) {
        const rows = (payload.forecastRows || []).filter(row => bloodType === 'all' || row.blood_type === bloodType);
        if (!rows.length) {
            return '<p class="text-muted mb-0">No forward forecast was generated for this selection.</p>';
        }
        const totalDemand = rows.reduce((sum, row) => sum + (Number(row.forecasted_demand) || 0), 0);
        const totalSupply = rows.reduce((sum, row) => sum + (Number(row.forecasted_supply) || 0), 0);

        const body = rows.map(row => {
            const demand = Number(row.forecasted_demand) || 0;
            const supply = Number(row.forecasted_supply) || 0;
            const gap = Number(row.gap ?? (supply - demand)) || 0;
            const balanceClass = gap < 0 ? 'text-danger' : gap > 0 ? 'text-success' : 'text-muted';
            return `
                <tr>
                    <td>${row.month_label || 'Next Month'}</td>
                    <td>${row.blood_type}</td>
                    <td>${formatNumber(demand)} (${((demand / (totalDemand || 1)) * 100).toFixed(1)}%)</td>
                    <td>${formatNumber(supply)} (${((supply / (totalSupply || 1)) * 100).toFixed(1)}%)</td>
                    <td class="${balanceClass}">${gap >= 0 ? '+' : ''}${formatNumber(gap)}</td>
                    <td>${row.status || 'Balanced'}</td>
                </tr>`;
        }).join('');

        return `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Forecast Month</th>
                            <th>Blood Type</th>
                            <th>Forecasted Demand</th>
                            <th>Forecasted Donations</th>
                            <th>Projected Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${body}</tbody>
                </table>
            </div>`;
    }

    function buildProjectedStockSection(payload, bloodType) {
        const dataset = payload.projectedStock || [];
        const rows = (bloodType === 'all' ? dataset : dataset.filter(item => item.Blood_Type === bloodType)) || [];
        if (!rows.length) {
            return '<p class="text-muted mb-0">Projected stock posture is not available for this selection.</p>';
        }
        const body = rows.map(item => {
            const projected = Number(item.Projected_Stock ?? item['Projected Stock Level (Next Month)'] ?? 0);
            const target = Number(item.Target_Stock ?? item['Target Stock'] ?? 0);
            const bufferGap = Number(item.Buffer_Gap ?? item['Buffer Gap'] ?? projected - target);
            const status = item.Buffer_Status || (bufferGap < 0 ? 'Critical gap' : 'On target');
            return `
                <tr>
                    <td>${item.Blood_Type}</td>
                    <td>${formatNumber(projected)}</td>
                    <td>${formatNumber(target)}</td>
                    <td class="${bufferGap < 0 ? 'text-danger' : 'text-success'}">${bufferGap >= 0 ? '+' : ''}${formatNumber(bufferGap)}</td>
                    <td>${status}</td>
                </tr>`;
        }).join('');

        return `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Blood Type</th>
                            <th>Projected Stock (Next Month)</th>
                            <th>Target Buffer</th>
                            <th>Buffer Gap</th>
                            <th>Buffer Status</th>
                        </tr>
                    </thead>
                    <tbody>${body}</tbody>
                </table>
            </div>`;
    }

    function buildRecommendations(stats, timeline) {
        const insights = [];
        if (stats.coverageRatio && stats.coverageRatio < 0.9) {
            insights.push('Collections covered less than 90% of hospital requests. Deploy emergency donor mobilization and coordinate hospital rationing plans.');
        } else if (stats.coverageRatio && stats.coverageRatio < 1.05) {
            insights.push('Collections tracked demand closely. Maintain the current drive cadence but prepare an auxiliary drive for sudden surges.');
        } else if (stats.coverageRatio > 0) {
            insights.push('Collections exceeded total requests. Explore inter-facility sharing or platelet conversion before units expire.');
        }
        if (stats.latestDemand > stats.latestSupply) {
            insights.push(`The most recent month closed ${formatNumber(stats.latestDemand - stats.latestSupply)} units short. Alert field recruiters and prioritize drives in historically high-yield barangays for this blood type.`);
        }
        if (timeline.length > 2) {
            const trailingDemand = timeline.slice(-3).reduce((sum, row) => sum + row.demand, 0) / Math.min(3, timeline.length);
            if (stats.avgDemand && trailingDemand > stats.avgDemand * 1.1) {
                insights.push('Hospital requests grew by more than 10% in the last quarter. Secure contingency stock by reallocating compatible low-use types.');
            }
        }
        if (!insights.length) {
            insights.push('Demand and supply stayed within the target buffer. Continue weekly monitoring and refresh this brief after the next hospital census update.');
        }
        return `<ol>${insights.map(item => `<li>${item}</li>`).join('')}</ol>`;
    }

    function buildHistoricalSection(timeline) {
        const stats = summarizeTimeline(timeline);
        const metrics = [
            { label: 'Total collected', value: stats.totalSupply },
            { label: 'Total requested', value: stats.totalDemand },
            { label: 'Avg monthly supply', value: stats.avgSupply },
            { label: 'Avg monthly demand', value: stats.avgDemand },
        ].map(item => `
            <div class="summary-metric-card">
                <div class="label">${item.label}</div>
                <div class="value">${formatNumber(item.value)}</div>
            </div>
        `).join('');

        return `
            <section class="summary-section">
                <h2 class="h5 mb-3">Historical performance</h2>
                <div class="summary-metrics">${metrics}</div>
                <div class="mt-3">${buildTimelineTable(timeline)}</div>
            </section>`;
    }

    function buildReportMarkup({ payload, monthKey, bloodType, timeline }) {
        const stats = summarizeTimeline(timeline);
        const monthDescriptor = monthKey === 'all'
            ? 'entire historical record'
            : `${labelMonth(monthKey)} and earlier`;
        const bloodDescriptor = bloodType === 'all' ? 'all blood types' : bloodType;
        const generatedAt = new Date().toLocaleString();

        const reportHtml = `
            <article class="summary-report">
                <section class="summary-section mb-3">
                    <h1 class="h4 mb-2">Blood Supply Summary</h1>
                    <p class="text-muted mb-1">Scope: ${monthDescriptor}</p>
                    <p class="text-muted mb-1">Focus: ${bloodDescriptor}</p>
                    <p class="text-muted mb-0">Generated: ${generatedAt}</p>
                </section>
                ${buildHistoricalSection(timeline)}
                <section class="summary-section">
                    <h2 class="h5 mb-3">Forward-looking forecast</h2>
                    ${buildForecastTable(payload, bloodType)}
                </section>
                <section class="summary-section">
                    <h2 class="h5 mb-3">Projected stock posture</h2>
                    ${buildProjectedStockSection(payload, bloodType)}
                </section>
                <section class="summary-section">
                    <h2 class="h5 mb-3">Actionable guidance</h2>
                    ${buildRecommendations(stats, timeline)}
                </section>
            </article>`;

        return {
            html: reportHtml,
            contextLabel: `Scope: ${monthDescriptor} • Focus: ${bloodDescriptor}`,
        };
    }

    async function generateReport({ monthKey = 'all', bloodType = 'all' }) {
        const modalEl = document.getElementById(MODAL_ID);
        const modalBody = modalEl?.querySelector('.modal-body');
        const spinner = document.createElement('div');
        spinner.className = 'alert alert-light border mt-3';
        spinner.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparing summary...';
        modalBody?.appendChild(spinner);

        try {
            const payload = await ensureSummaryDataset();
            sessionStorage.setItem(
                SUMMARY_STORAGE_KEY,
                JSON.stringify({
                    payload,
                    selection: { monthKey, bloodType },
                    generatedAt: new Date().toISOString(),
                    snapshot: state.dashboardSnapshot,
                })
            );
            const params = new URLSearchParams();
            if (monthKey && monthKey !== 'all') params.set('month', monthKey);
            if (bloodType && bloodType !== 'all') params.set('type', bloodType);
            const url = `forecast-summary.php${params.toString() ? `?${params}` : ''}`;
            const summaryWindow = window.open(url, '_blank');
            if (!summaryWindow) {
                window.location.href = url;
            }
        } catch (error) {
            console.error('Summary generation failed:', error);
            alert(error.message || 'Unable to generate summary. Please try again.');
        } finally {
            spinner.remove();
            window.bootstrap?.Modal.getInstance(modalEl)?.hide();
        }
    }

    function setDashboardSnapshot(snapshot = {}) {
        state.dashboardSnapshot = {
            forecastRows: snapshot.forecastRows || [],
            summary: snapshot.summary || {},
            projectedStock: snapshot.projectedStock || [],
        };
    }

    function openOptions(context = {}) {
        ensureModal();
        populateOptions(context);
        window.bootstrap?.Modal.getOrCreateInstance(document.getElementById(MODAL_ID))?.show();
    }

    window.ForecastExport = {
        setDashboardSnapshot,
        openOptions,
    };
})();

