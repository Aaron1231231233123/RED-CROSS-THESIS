(() => {
    const STORAGE_KEY = 'forecastSummaryPayload';
    const DATA_URL = '../api/forecast-reports-api-python.php';

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
        if (!date) return value || 'Undated';
        return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    };

    function readStoredPayload() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            console.warn('Unable to parse stored summary payload:', error);
            return null;
        }
    }

    async function fetchPayloadFromApi() {
        const response = await fetch(`${DATA_URL}?ts=${Date.now()}&scope=summary`, { cache: 'no-cache' });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Failed to load data');
        return {
            payload: {
                monthlyDonations: data.monthly_donations || [],
                monthlyRequests: data.monthly_requests || [],
                forecastRows: data.forecast_rows || [],
                projectedStock: data.projected_stock || [],
                summary: data.summary || {},
            },
            selection: { monthKey: 'all', bloodType: 'all' },
            generatedAt: new Date().toISOString(),
        };
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

        const forecastRow = (payload.forecastRows || []).find(row => {
            if (bloodType !== 'all' && row.blood_type !== bloodType) return false;
            if (monthKey === 'all') return true;
            return row.month_key === monthKey;
        });
        if (forecastRow) {
            const key = forecastRow.month_key;
            if (!cutoff || (key && toDate(key) && toDate(key) <= cutoff)) {
                if (!buckets.has(key)) {
                    buckets.set(key, {
                        month: key,
                        label: labelMonth(key),
                        supply: Number(forecastRow.forecasted_supply) || 0,
                        demand: Number(forecastRow.forecasted_demand) || 0,
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
            return `
                <tr>
                    <td>${row.label}</td>
                    <td>${formatNumber(row.supply)}</td>
                    <td>${formatNumber(row.demand)}</td>
                    <td class="${gapClass}">${gap >= 0 ? '+' : ''}${formatNumber(gap)}</td>
                    <td>${formatNumber(row.supply)} vs ${formatNumber(row.demand)}</td>
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
                            <th>Collected vs Requested</th>
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
        const rows = (bloodType === 'all'
            ? payload.projectedStock
            : (payload.projectedStock || []).filter(item => item.Blood_Type === bloodType)) || [];
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

    function formatMetricValue(label, value) {
        const numeric = Number(value || 0);
        const isAverage = label.toLowerCase().includes('avg');
        const options = isAverage
            ? { minimumFractionDigits: 1, maximumFractionDigits: 1 }
            : { maximumFractionDigits: 0 };
        return numeric.toLocaleString(undefined, options);
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
                <div class="value">${formatMetricValue(item.label, item.value)}</div>
            </div>`).join('');

        return `
            <section class="summary-section">
                <h2 class="h5 mb-3">Historical performance</h2>
                <div class="summary-metrics">${metrics}</div>
                <div class="mt-3">${buildTimelineTable(timeline)}</div>
            </section>`;
    }

    function buildRecommendations(stats, timeline, focusLabel, projectedRows = []) {
        const insights = [];
        const latestRow = timeline[timeline.length - 1] || null;
        const worstDeficit = timeline.reduce((worst, row) => {
            const gap = row.supply - row.demand;
            if (gap < worst.gap) {
                return { gap, label: row.label };
            }
            return worst;
        }, { gap: Infinity, label: null });
        const hasSurplusStock = projectedRows.some(row => {
            const gap = Number(row.Buffer_Gap ?? row['Buffer Gap'] ?? row.BufferGap ?? 0);
            return gap >= 0;
        });
        const criticalTypes = projectedRows
            .filter(row => (row.Buffer_Status ?? row['Buffer Status'] ?? '').toLowerCase().includes('critical'))
            .map(row => row.Blood_Type || row['Blood Type'] || '')
            .filter(Boolean);
        const monitorTypes = projectedRows
            .filter(row => (row.Buffer_Status ?? row['Buffer Status'] ?? '').toLowerCase().includes('monitor'))
            .map(row => row.Blood_Type || row['Blood Type'] || '')
            .filter(Boolean);

        if (stats.coverageRatio && stats.coverageRatio < 0.9) {
            insights.push(`Collections for ${focusLabel} are covering less than 90% of hospital requests. Mobilize emergency donor drives and coordinate with partner hospitals to triage the most critical cases first.`);
        } else if (stats.coverageRatio && stats.coverageRatio < 1.05) {
            insights.push(`Collections for ${focusLabel} are barely keeping pace with demand. Maintain the drive cadence and keep standby teams ready for sudden calamity surges.`);
        } else if (stats.coverageRatio >= 1.05 && stats.latestSupply > stats.latestDemand && hasSurplusStock) {
            insights.push(`Collections now exceed requests for ${focusLabel}. Coordinate with nearby chapters to redistribute surplus units before they age out.`);
        }

        if (stats.latestDemand > stats.latestSupply) {
            insights.push(`The most recent month closed ${formatNumber(stats.latestDemand - stats.latestSupply)} units short. Alert field recruiters and line up rapid-response donors for ${focusLabel}.`);
        }

        if (timeline.length > 2) {
            const trailingDemand = timeline.slice(-3).reduce((sum, row) => sum + row.demand, 0) / Math.min(3, timeline.length);
            if (stats.avgDemand && trailingDemand > stats.avgDemand * 1.1) {
                insights.push(`Hospital requests grew by more than 10% this quarter. Stage contingency stock by reallocating ${focusLabel} units from low-utilization facilities and reinforcing barangay drives.`);
            }
        }

        if (stats.totalDemand === 0) {
            insights.push(`No hospital requests were logged for ${focusLabel} during the selected period. Share this readiness update with partner facilities and keep donors warm so units can be dispatched immediately once a call comes in.`);
        }

        if (worstDeficit.label && worstDeficit.gap < 0) {
            insights.push(`The heaviest shortage occurred in ${worstDeficit.label}, leaving us short by ${formatNumber(Math.abs(worstDeficit.gap))} units. Flag this month in the command briefing and plan a follow-up drive focused on ${focusLabel} donors near high-admitting hospitals.`);
        }

        if (criticalTypes.length) {
            insights.push(`Critical buffer alerts remain for ${criticalTypes.join(', ')}. Coordinate with Iloilo City responders to reserve these units strictly for emergencies and accelerate replacement collections before the buffer runs dry.`);
        } else if (monitorTypes.length) {
            insights.push(`Monitor buffer levels for ${monitorTypes.join(', ')}. Schedule staggered drives with local barangays so these types do not slip into critical range.`);
        }

        if (insights.length < 2) {
            insights.push(`Reinforce donor communications for ${focusLabel}. Weekly SMS reminders and barangay loudspeaker announcements help keep walk-in donors ready for sudden calls.`);
        }

        if (!insights.length) {
            insights.push(`Demand and supply stayed within the target buffer for ${focusLabel}. Continue weekly monitoring, remind barangay partners about ${focusLabel} needs, and refresh this brief after the next hospital census update.`);
        }

        return `<ol>${insights.map(item => `<li>${item}</li>`).join('')}</ol>`;
    }

    function renderSummary(data) {
        const content = document.getElementById('summaryContent');
        const contextLabel = document.getElementById('summaryContextLabel');
        const fallback = document.getElementById('summaryFallback');

        const payload = data.payload;
        const selection = data.selection || { monthKey: 'all', bloodType: 'all' };
        const timeline = buildTimeline(payload, selection.monthKey || 'all', selection.bloodType || 'all');
        const stats = summarizeTimeline(timeline);
        const monthDescriptor = (selection.monthKey && selection.monthKey !== 'all')
            ? `${labelMonth(selection.monthKey)} and earlier`
            : 'entire historical record';
        const bloodDescriptor = selection.bloodType === 'all' ? 'all blood types' : selection.bloodType;

        const focusLabel = selection.bloodType === 'all' ? 'all blood types' : selection.bloodType;

        content.innerHTML = `
            <article class="summary-report">
                <section class="summary-section mb-3">
                    <h1 class="h4 mb-2">Blood Supply Summary</h1>
                    <p class="text-muted mb-1">Scope: ${monthDescriptor}</p>
                    <p class="text-muted mb-1">Focus: ${bloodDescriptor}</p>
                    <p class="text-muted mb-0">Generated: ${new Date(data.generatedAt || Date.now()).toLocaleString()}</p>
                </section>
                ${buildHistoricalSection(timeline)}
                <section class="summary-section">
                    <h2 class="h5 mb-3">Forward-looking forecast</h2>
                    ${buildForecastTable(payload, selection.bloodType || 'all')}
                </section>
                <section class="summary-section">
                    <h2 class="h5 mb-3">Projected stock posture</h2>
                    ${buildProjectedStockSection(payload, selection.bloodType || 'all')}
                </section>
                <section class="summary-section actionable-section">
                    <h2 class="h5 mb-3">Actionable guidance</h2>
                    ${buildRecommendations(stats, timeline, focusLabel, payload.projectedStock || payload.projected_stock || [])}
                </section>
            </article>`;

        if (contextLabel) {
            contextLabel.textContent = `Scope: ${monthDescriptor} • Focus: ${bloodDescriptor}`;
        }
        fallback?.classList.add('d-none');
    }

    async function init() {
        const stored = readStoredPayload();
        if (stored) {
            renderSummary(stored);
            return;
        }

        try {
            const data = await fetchPayloadFromApi();
            renderSummary(data);
        } catch (error) {
            console.error(error);
            const fallback = document.getElementById('summaryFallback');
            fallback?.classList.remove('d-none');
            document.getElementById('summaryContent').innerHTML = '';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('summaryPrintBtn')?.addEventListener('click', () => window.print());
        init();
    });
})();

