(() => {
    const OVERVIEW_API_URL = '../api/reports-overview-api-python.php';
    const FORECAST_API_URL = '../api/forecast-reports-api-python.php';

    const rootEl = document.getElementById('dataReportRoot');
    const contentEl = document.getElementById('dataReportContent');
    const printBtn = document.getElementById('reportPrintBtn');
    const downloadBtn = document.getElementById('reportDownloadBtn');

    const meta = window.__DATA_REPORT_METADATA__ || {};
    const coverageYear = meta.coverageYear || new Date().getFullYear();
    const generatedDate = meta.generatedDate || new Date().toISOString().slice(0, 10);

    // Shared state for interactive pieces on the report page
    const reportState = {
        donationsByMonthByYear: null,
    };

    function formatNumber(value = 0) {
        const num = Number(value) || 0;
        return num.toLocaleString(undefined, { maximumFractionDigits: 1 });
    }

    function formatMonthLabel(isoMonth) {
        if (!isoMonth) return '';
        const d = new Date(isoMonth);
        if (Number.isNaN(d.valueOf())) return isoMonth;
        return d.toLocaleDateString('en-US', { month: 'short' });
    }

    function buildTable(headers, rows) {
        const thead = `
            <thead>
                <tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>
            </thead>`;
        const tbody = `
            <tbody>
                ${rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`).join('')}
            </tbody>`;
        return `<table class="table table-bordered table-sm mb-2">${thead}${tbody}</table>`;
    }

    function updateOverviewKpiCards(kpis = {}) {
        const {
            total_active_donors = 0,
            eligible_donors_today = 0,
            total_blood_units_available = 0,
            units_nearing_expiry = 0,
            total_hospital_requests_today = 0,
        } = kpis || {};

        const mappings = {
            kpiActiveDonors: total_active_donors,
            kpiEligibleToday: eligible_donors_today,
            kpiUnitsAvailable: total_blood_units_available,
            kpiUnitsNearingExpiry: units_nearing_expiry,
            kpiHospitalRequestsToday: total_hospital_requests_today,
        };

        Object.entries(mappings).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = (Number(value) || 0).toLocaleString();
            }
        });
    }

    function updateForecastDemandKpi(summary = {}) {
        const el = document.getElementById('kpiForecastDemand');
        if (!el) return;
        const value = Number(summary.total_forecasted_demand || 0) || 0;
        el.textContent = value.toLocaleString();
    }

    function buildSection(title, tableHtml, extraHtml = '') {
        return `
            <section class="report-section">
                <div class="section-title-row">
                    <h4 class="mb-0">${title}</h4>
                </div>
                ${tableHtml || '<p class="text-muted mb-0">No data available.</p>'}
                ${extraHtml}
            </section>`;
    }

    function extractAgeSection(ageData) {
        const rows = (ageData?.data || []).map(row => [
            row.age_group || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const table = buildTable(['Age Range', 'Count', 'Percentage'], rows);
        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=donor_age" scrolling="no"></iframe>`;
        return buildSection('Donor Age Distribution', table, chart);
    }

    function extractSexSection(sexData) {
        const rows = (sexData?.data || []).map(row => [
            row.sex || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const total = (sexData?.total_donors || 0);
        if (total) {
            rows.push(['Total', formatNumber(total), '100.0']);
        }
        const table = buildTable(['Category', 'Count', 'Percentage'], rows);
        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=donor_sex" scrolling="no"></iframe>`;
        return buildSection('Donor Sex Distribution', table, chart);
    }

    function extractLocationSection(locData) {
        const rows = (locData?.data || []).map((row, idx) => [
            row.location || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`,
            (idx + 1).toString()
        ]);
        const table = buildTable(['Municipality/Barangay', 'Count', 'Percentage', 'Rank'], rows);
        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=donor_location" scrolling="no"></iframe>`;
        return buildSection('Donor Location Distribution', table, chart);
    }

    function extractBloodTypeSection(btData) {
        const rows = (btData?.data || []).map(row => [
            row.blood_type || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const table = buildTable(['Blood Type', 'Count', 'Percentage'], rows);
        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=donor_blood_type" scrolling="no"></iframe>`;
        return buildSection('Donor Blood Type Distribution', table, chart);
    }

    function extractEligibilitySection(eligData) {
        const rows = (eligData?.data || []).map(row => [
            row.eligibility_status || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const table = buildTable(['Status', 'Count', 'Percentage'], rows);
        return buildSection('Donor Eligibility Summary', table);
    }

    function extractDonationsByMonthSection(donationsData) {
        const rawRows = donationsData?.data || [];
        const grouped = {};

        rawRows.forEach(row => {
            if (!row.year_month) return;
            const d = new Date(row.year_month);
            if (Number.isNaN(d.valueOf())) return;
            const y = d.getFullYear();
            if (!grouped[y]) grouped[y] = [];
            grouped[y].push({
                label: formatMonthLabel(row.year_month),
                value: row.donations || 0,
            });
        });

        // Save in shared state so we can update the table on year change
        reportState.donationsByMonthByYear = grouped;

        const years = Object.keys(grouped).sort();
        const defaultYear = years.includes(String(coverageYear))
            ? String(coverageYear)
            : (years[years.length - 1] || '');

        const rows = (grouped[defaultYear] || []).map(r => [
            r.label,
            formatNumber(r.value),
        ]);

        const selectHtml = years.length > 0
            ? `
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="small-note">Year:</span>
                    <select id="donationsByMonthYearSelect" class="form-select form-select-sm w-auto">
                        ${years.map(y => `<option value="${y}" ${y === defaultYear ? 'selected' : ''}>${y}</option>`).join('')}
                    </select>
                </div>`
            : '';

        const tableHtml = `<div id="donationsByMonthTableWrapper">
                ${buildTable(['Month', 'Total Donations'], rows)}
            </div>`;

        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=donations_by_month" scrolling="no"></iframe>`;

        return `
            <section class="report-section">
                <div class="section-title-row">
                    <h4 class="mb-0">Donation Count by Month</h4>
                </div>
                ${selectHtml}
                ${tableHtml}
                ${chart}
            </section>`;
    }

    function extractDonationByYearSection(donationsData) {
        const byYear = {};
        (donationsData?.data || []).forEach(row => {
            if (!row.year_month) return;
            const d = new Date(row.year_month);
            if (Number.isNaN(d.valueOf())) return;
            const y = d.getFullYear();
            byYear[y] = (byYear[y] || 0) + (row.donations || 0);
        });
        const years = Object.keys(byYear).sort();
        const rows = [];
        let prevVal = null;
        years.forEach(y => {
            const val = byYear[y];
            let pct = 0;
            if (prevVal !== null && prevVal !== 0) {
                pct = (val - prevVal) / prevVal;
            }
            // Avoid "-0" display: treat very small absolute values as 0
            const safePct = Math.abs(pct) < 0.0005 ? 0 : pct;
            rows.push([`${y}.0`, formatNumber(val), safePct.toFixed(2)]);
            prevVal = val;
        });
        const table = buildTable(['Year', 'Donations', '% Difference'], rows);
        return buildSection('Donation Count by Year', table);
    }

    function extractDonationsByBloodTypeSection(btData) {
        const rows = (btData?.data || []).map(row => [
            row.blood_type || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const table = buildTable(['Blood Type', 'Total Donations', 'Percentage'], rows);
        return buildSection('Donations by Blood Type', table);
    }

    function extractMobileVsInhouseSection(mobileData) {
        const rows = (mobileData?.data || []).map(row => [
            row.donation_type || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const table = buildTable(['Category', 'Count', 'Percentage'], rows);
        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=mobile_vs_inhouse" scrolling="no"></iframe>`;
        return buildSection('Mobile vs In-House Donations', table, chart);
    }

    function extractSuccessSection(successData) {
        const rows = (successData?.data || []).map(row => [
            row.donation_status || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const table = buildTable(['Category', 'Count', 'Percentage'], rows);
        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=successful_vs_unsuccessful" scrolling="no"></iframe>`;
        return buildSection('Success vs Unsuccessful Donations', table, chart);
    }

    function extractFirstTimeRepeatSection(freqData) {
        const rows = (freqData?.data || []).map(row => [
            row.donation_frequency === '1st Time' ? 'First-Time' : row.donation_frequency || '',
            formatNumber(row.count || 0),
            `${formatNumber(row.percentage || 0)}`
        ]);
        const table = buildTable(['Category', 'Count', 'Percentage'], rows);
        return buildSection('First-Time vs Repeat Donors', table);
    }

    function extractTotalHospitalRequestsSection(requestsTotalsByYear) {
        const perYear = (requestsTotalsByYear && requestsTotalsByYear.per_year) || {};
        const key = String(coverageYear);
        const yearBucket = perYear[key];

        if (!yearBucket) {
            return buildSection('Total Hospital Requests', '<p class="text-muted mb-0">No hospital request data available for this year.</p>');
        }

        const byStatus = yearBucket.by_status || {};
        const total = Number(yearBucket.total || 0);

        const rows = [
            ['Total Requests', formatNumber(total)],
        ];
        Object.entries(byStatus).forEach(([status, count]) => {
            rows.push([status, formatNumber(count || 0)]);
        });

        const table = buildTable(['Metric', 'Count'], rows);
        return buildSection('Total Hospital Requests', table);
    }

    function extractRequestsByBloodTypeSection(requestsByTypeData) {
        const rows = (requestsByTypeData?.data || []).map(row => [
            row.patient_blood_type || '',
            formatNumber(row.total_requests || 0),
        ]);
        const table = buildTable(['Blood Type', 'Units Requested'], rows);
        return buildSection('Requests by Blood Type', table);
    }

    function extractMonthlyRequestsTrendSection(monthlyTrendData) {
        const rows = (monthlyTrendData?.data || [])
            .filter(row => {
                const d = new Date(row.year_month);
                return !Number.isNaN(d.valueOf()) && d.getFullYear() === coverageYear;
            })
            .map(row => [
                formatMonthLabel(row.year_month),
                formatNumber(row.total_requests || 0),
            ]);
        const table = buildTable(['Month', 'Requests'], rows);
        const chart = `<iframe class="chart-frame mt-2" src="../api/forecast-asset.php?asset=monthly_requests_trend" scrolling="no"></iframe>`;
        return buildSection('Monthly Request Trend', table, chart);
    }

    function extractReasonsDeclinedSection(declinedData) {
        const perYear = (declinedData && declinedData.per_year) || {};
        const key = String(coverageYear);
        const rowsSrc = perYear[key] || [];
        if (!rowsSrc.length) {
            const table = '<p class="text-muted mb-0">No declined requests with reasons recorded in the selected period.</p>';
            return buildSection('Reasons for Declined Requests', table);
        }

        const rows = rowsSrc.map(r => [
            r.reason || 'Other',
            formatNumber(r.count || 0),
            `${formatNumber(r.percentage || 0)}`,
        ]);
        const table = buildTable(['Reason', 'Count', 'Percentage'], rows);
        return buildSection('Reasons for Declined Requests', table);
    }

    function extractCurrentStockSection(inventoryAvailable, nearingExpiry) {
        const byType = (inventoryAvailable?.by_blood_type) || {};
        const reservedByType = (nearingExpiry?.units_by_blood_type) || {};
        const rows = Object.keys(byType).map(bt => [
            bt,
            formatNumber(byType[bt] || 0),
            formatNumber(reservedByType[bt] || 0),
        ]);
        const table = buildTable(['Blood Type', 'Units Available', 'Reserved Units'], rows);
        return buildSection('Current Stock per Blood Type', table);
    }

    function extractUnitsCollectedSection(unitsCollectedStatus) {
        const data = (unitsCollectedStatus && unitsCollectedStatus.data) || [];
        if (!data.length) {
            return buildSection('Units Collected', '<p class="text-muted mb-0">No units collected data available.</p>');
        }

        const rows = data
            .filter(row => {
                const y = new Date(row.month).getFullYear();
                return !Number.isNaN(y) && y === coverageYear;
            })
            .map(row => {
            const label = formatMonthLabel(row.month);
            const total = formatNumber(row.total_collected || 0);
            const valid = Number(row.valid || 0);
            const buffer = Number(row.buffer || 0);
            const handed = Number(row.handed_over || 0);
            const disposed = Number(row.disposed || 0);
            const breakdownParts = [];
            if (valid) breakdownParts.push(`Valid: ${formatNumber(valid)}`);
            if (buffer) breakdownParts.push(`Buffer: ${formatNumber(buffer)}`);
            if (handed) breakdownParts.push(`Handed Over: ${formatNumber(handed)}`);
            if (disposed) breakdownParts.push(`Disposed: ${formatNumber(disposed)}`);
            const breakdown = breakdownParts.length ? breakdownParts.join(' • ') : 'No status breakdown';
            return [label, total, breakdown];
        });

        const table = buildTable(['Month', 'Units Collected', 'Status Breakdown'], rows);
        return buildSection('Units Collected', table);
    }

    function extractForecastDemandSection(forecastSummary, projectedStock) {
        const rowsDemand = (forecastSummary?.forecast_rows || []).map(row => [
            row.blood_type || '',
            formatNumber(row.forecasted_demand || 0),
        ]);
        const demandTable = buildTable(['Blood Type', 'Forecasted Units Needed'], rowsDemand);

        const rowsStock = (projectedStock || []).map(row => [
            row.Blood_Type || '',
            formatNumber(row.Projected_Stock ?? row['Projected Stock Level (Next Month)'] ?? 0),
            formatNumber(row.Buffer_Gap ?? row['Buffer Gap'] ?? 0),
        ]);
        const stockTable = buildTable(['Blood Type', 'Predicted Stock', 'Surplus/Deficit'], rowsStock);

        return (
            buildSection('Forecasted Blood Demand (Next Month)', demandTable) +
            buildSection('Forecasted Inventory Projection', stockTable)
        );
    }

    function extractUnitsAllocatedSection(unitsAllocated) {
        const perYear = (unitsAllocated && unitsAllocated.per_year) || {};
        const rowsSrc = perYear[String(coverageYear)] || [];
        if (!rowsSrc.length) {
            return buildSection('Units Allocated to Hospitals', '<p class="text-muted mb-0">No allocated units for this year.</p>');
        }

        const rows = rowsSrc.map(r => [
            r.serial_number || '',
            r.blood_type || '',
            r.hospital || '',
            r.date_allocated || '',
        ]);
        const table = buildTable(['Serial Number', 'Blood Type', 'Hospital', 'Date Allocated'], rows);
        return buildSection('Units Allocated to Hospitals', table);
    }

    function extractUnitsExpiredSection(unitsExpired) {
        const perYear = (unitsExpired && unitsExpired.per_year) || {};
        const rowsSrc = perYear[String(coverageYear)] || [];
        if (!rowsSrc.length) {
            return buildSection('Units Expired', '<p class="text-muted mb-0">No expired units for this year.</p>');
        }

        const rows = rowsSrc.map(r => [
            r.serial_number || '',
            r.blood_type || '',
            r.date_collected || '',
            r.date_expired || '',
        ]);
        const table = buildTable(['Serial Number', 'Blood Type', 'Date Collected', 'Date Expired'], rows);
        return buildSection('Units Expired', table);
    }

    function extractPendingUnitsSection(pendingUnits) {
        const perYear = (pendingUnits && pendingUnits.per_year) || {};
        const rowsSrc = perYear[String(coverageYear)] || [];
        if (!rowsSrc.length) {
            return buildSection('Pending Units for Release', '<p class="text-muted mb-0">No pending units for this year.</p>');
        }

        const rows = rowsSrc.map(r => [
            r.serial_number || '',
            r.blood_type || '',
            r.request_id || '',
            r.status || 'Pending',
        ]);
        const table = buildTable(['Serial Number', 'Blood Type', 'Request ID', 'Status'], rows);
        return buildSection('Pending Units for Release', table);
    }

    async function loadDataAndRender() {
        try {
            const ts = Date.now();
            // Pass coverageYear to the forecast API so it can reuse the same
            // per-year cache file and filter its interactive charts consistently.
            const [overviewRes, forecastRes] = await Promise.all([
                fetch(`${OVERVIEW_API_URL}?ts=${ts}`, { cache: 'no-cache' }),
                fetch(`${FORECAST_API_URL}?ts=${ts}&scope=summary&year=${encodeURIComponent(coverageYear)}`, { cache: 'no-cache' }),
            ]);

            const overviewText = await overviewRes.text();
            const forecastText = await forecastRes.text();

            let overview;
            let forecast;

            try {
                overview = JSON.parse(overviewText);
            } catch {
                console.error('Failed to parse overview JSON:', overviewText);
                overview = { success: false };
            }

            try {
                forecast = JSON.parse(forecastText);
            } catch {
                console.error('Failed to parse forecast JSON:', forecastText);
                forecast = { success: false };
            }

            // Top-level KPIs for the cards at the top of the data report
            if (overview && overview.success !== false) {
                updateOverviewKpiCards(overview.kpis || {});
            }
            if (forecast && forecast.success !== false) {
                updateForecastDemandKpi(forecast.summary || {});
            }

            const sections = overview.sections || {};
            const donorDemo = sections.donor_demographics || {};
            const donationActivity = sections.donation_activity || {};
            const hospitalReq = sections.hospital_requests || {};
            const inventory = sections.inventory || {};

            const ageSection = extractAgeSection(donorDemo.age);
            const sexSection = extractSexSection(donorDemo.sex);
            const locationSection = extractLocationSection(donorDemo.location);
            const bloodTypeSection = extractBloodTypeSection(donorDemo.blood_type);
            const eligibilitySection = extractEligibilitySection(donorDemo.eligibility_status);

        const donationsByMonthSection = extractDonationsByMonthSection(donationActivity.donations_by_month);
            const donationByYearSection = extractDonationByYearSection(donationActivity.donations_by_month);
            const donationsByBloodTypeSection = extractDonationsByBloodTypeSection(donorDemo.blood_type);
            const mobileSection = extractMobileVsInhouseSection(donationActivity.mobile_vs_inhouse);
            const successSection = extractSuccessSection(donationActivity.success_vs_unsuccessful);
            const firstRepeatSection = extractFirstTimeRepeatSection(donorDemo.donation_frequency);

            const totalRequestsSection = extractTotalHospitalRequestsSection(hospitalReq.totals_by_status);
            const requestsByBloodTypeSection = extractRequestsByBloodTypeSection(hospitalReq.requests_by_blood_type);
            const monthlyTrendSection = extractMonthlyRequestsTrendSection(hospitalReq.monthly_trend);
            const reasonsDeclinedSection = extractReasonsDeclinedSection(hospitalReq.declined_reasons);

            const currentStockSection = extractCurrentStockSection(inventory.available_units, inventory.units_nearing_expiry);
            const unitsCollectedSection = extractUnitsCollectedSection(inventory.units_collected_status_monthly || null);
            const unitsAllocatedSection = extractUnitsAllocatedSection(inventory.units_allocated || null);
            const unitsExpiredSection = extractUnitsExpiredSection(inventory.units_expired || null);
            const pendingUnitsSection = extractPendingUnitsSection(inventory.pending_units || null);
            const forecastDemandSection = extractForecastDemandSection(forecast, forecast.projected_stock || []);

            const html =
                // I. DONOR DEMOGRAPHICS
                `<h4 class="report-group-title">I. DONOR DEMOGRAPHICS</h4>` +
                ageSection +
                sexSection +
                locationSection +
                bloodTypeSection +
                eligibilitySection +
                firstRepeatSection +
                // II. DONATION DATA
                `<h4 class="report-group-title mt-3">II. DONATION DATA</h4>` +
                donationsByMonthSection +
                donationByYearSection +
                donationsByBloodTypeSection +
                mobileSection +
                successSection +
                // III. HOSPITAL REQUEST DATA
                `<h4 class="report-group-title mt-3">III. HOSPITAL REQUEST DATA</h4>` +
                totalRequestsSection +
                requestsByBloodTypeSection +
                monthlyTrendSection +
                reasonsDeclinedSection +
                // IV. BLOOD INVENTORY STATUS
                `<h4 class="report-group-title mt-3">IV. BLOOD INVENTORY STATUS</h4>` +
                currentStockSection +
                unitsCollectedSection +
                unitsAllocatedSection +
                unitsExpiredSection +
                pendingUnitsSection +
                // V. FORECASTING DATA
                `<h4 class="report-group-title mt-3">V. FORECASTING DATA</h4>` +
                forecastDemandSection;

            contentEl.innerHTML = html;

            // Wire up Donation Count by Month year selector, if present
            const yearSelect = document.getElementById('donationsByMonthYearSelect');
            const tableWrapper = document.getElementById('donationsByMonthTableWrapper');
            if (yearSelect && tableWrapper && reportState.donationsByMonthByYear) {
                yearSelect.addEventListener('change', () => {
                    const y = yearSelect.value;
                    const rowsForYear = (reportState.donationsByMonthByYear[y] || []).map(r => [
                        r.label,
                        formatNumber(r.value),
                    ]);
                    tableWrapper.innerHTML = buildTable(['Month', 'Total Donations'], rowsForYear);
                });
            }

            // Inject print-only zoom styles into each chart iframe so that
            // the chart content itself is "zoomed out" when printing,
            // without needing to modify the Python chart code.
            const chartFrames = document.querySelectorAll('iframe.chart-frame');
            chartFrames.forEach((frame) => {
                const applyPrintZoom = () => {
                    try {
                        const doc = frame.contentDocument || (frame.contentWindow && frame.contentWindow.document);
                        if (!doc) return;

                        const head = doc.head || doc.getElementsByTagName('head')[0];
                        if (!head) return;

                        // Avoid injecting the style multiple times
                        if (doc.getElementById('data-report-print-zoom-style')) return;

                        const style = doc.createElement('style');
                        style.id = 'data-report-print-zoom-style';
                        style.textContent = `
                            @media print {
                                body {
                                    zoom: 0.8;
                                    -ms-zoom: 0.8;
                                    -webkit-transform: scale(0.8);
                                    -webkit-transform-origin: top left;
                                }
                                /* Hide interactive dropdowns/controls such as
                                   the "All" filter when printing charts. */
                                select {
                                    display: none !important;
                                }
                            }
                        `;
                        head.appendChild(style);
                    } catch (err) {
                        console.error('Failed to apply print zoom to chart iframe', err);
                    }
                };

                // Apply when the iframe loads; if it's already loaded, try immediately.
                frame.addEventListener('load', applyPrintZoom);
                try {
                    if (frame.contentDocument && frame.contentDocument.readyState === 'complete') {
                        applyPrintZoom();
                    }
                } catch {
                    // Ignore access errors; they should not happen for same-origin iframes.
                }
            });

            // We no longer auto-download on load; user triggers PDF via Download button.
        } catch (error) {
            console.error('Error generating data report:', error);
            contentEl.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to generate data report. Please try again.
                </div>`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadDataAndRender();

        if (printBtn) {
            printBtn.addEventListener('click', () => {
                window.print();
            });
        }

        if (downloadBtn) {
            // "Save as PDF" should use the same print styling as regular Print,
            // but we temporarily adjust the document title so the browser's
            // Save-as dialog suggests a helpful default PDF filename like
            // "prc_blood_services_report_filled_2026-01-05.pdf".
            downloadBtn.addEventListener('click', () => {
                const originalTitle = document.title;
                const safeDate = (generatedDate || '').toString().replace(/[^0-9\-]/g, '') || new Date().toISOString().slice(0, 10);
                document.title = `prc_blood_services_report_filled_${safeDate}`;
                window.print();
                // Restore the original title shortly after opening the print dialog.
                setTimeout(() => {
                    document.title = originalTitle;
                }, 1500);
            });
        }
    });
})();


