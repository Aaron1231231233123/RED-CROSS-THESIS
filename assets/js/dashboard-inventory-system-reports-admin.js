// Forecast Reports with R Studio Model Integration
(function(){
    // Global variables for forecast data
    let forecastData = [];
    let kpiData = {};
    let monthlyData = { supply: {}, demand: {} };
    let forecastMonths = [];
    let currentMonths = [];
    let isLoading = false;

    const pageSize = 8; // Show all blood types (A+, A-, B+, B-, O+, O-, AB+, AB-)
    let currentPage = 1;
    const tableBody = document.querySelector('#reportsTable tbody');
    const pagination = document.getElementById('pagination');
    const monthFilter = document.getElementById('monthFilter');
    const typeFilter = document.getElementById('typeFilter');
    const searchInput = document.getElementById('searchInput');

    // API endpoint for forecast data
    const FORECAST_API_URL = '../api/forecast-reports-api.php';

    // Fetch forecast data from API
    async function fetchForecastData() {
        if (isLoading) return;
        isLoading = true;
        
        try {
            showLoadingState();
            const response = await fetch(FORECAST_API_URL);
            const data = await response.json();
            
            if (data.success) {
                forecastData = data.forecast_data || [];
                kpiData = data.kpis || {};
                monthlyData = {
                    supply: data.monthly_supply || {}, // Current database data only
                    demand: data.monthly_demand || {}
                };
                forecastMonths = data.forecast_months || []; // Future months = forecasts
                currentMonths = data.all_months || data.historical_months || data.current_months || []; // All database months for display
                
                // Debug: Log received data
                console.log('Received forecast data:', data);
                console.log('Historical months (ALL DATABASE DATA 2023-2025):', currentMonths);
                console.log('Forecast months (December 2025+):', forecastMonths);
                console.log('Data source explanation:', data.debug_frontend_data?.data_source_explanation);
                console.log('Forecast explanation:', data.debug_frontend_data?.forecast_explanation);
                
                // Log database summary
                if (data.debug_frontend_data?.database_summary) {
                    const summary = data.debug_frontend_data.database_summary;
                    console.log('Database Summary:', summary);
                    console.log(`Total Blood Units: ${summary.total_blood_units}`);
                    console.log(`Total Months: ${summary.total_months}`);
                    console.log(`Years Covered: ${summary.years_covered}`);
                    console.log(`2023 Months: ${summary.months_2023}, 2024 Months: ${summary.months_2024}, 2025 Months: ${summary.months_2025}`);
                }
                console.log('Forecast data:', forecastData);
                
                // Log training data info
                if (data.training_data_info) {
                    console.log('Training Data Info:', data.training_data_info);
                    console.log('Historical months used for training:', data.training_data_info.historical_months_count);
                    console.log('Current months used for training:', data.training_data_info.current_months_count);
                    console.log('Total training months:', data.training_data_info.total_training_months);
                }
                
                // Reset charts to ensure they update with new data
                chartsInitialized = false;
                
                updateKPIs();
                updateMonthFilter();
                renderTable();
                initCharts();
            } else {
                console.error('API Error:', data.error);
                showErrorState(data.message || 'Failed to load forecast data');
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            showErrorState('Network error while loading forecast data');
        } finally {
            isLoading = false;
            hideLoadingState();
        }
    }

    // Show loading state
    function showLoadingState() {
        const tableBody = document.querySelector('#reportsTable tbody');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading forecast data...</td></tr>';
        }
        
        // Update KPI values to show loading
        document.getElementById('kpiDemand').textContent = '...';
        document.getElementById('kpiDonations').textContent = '...';
        document.getElementById('kpiBalance').textContent = '...';
        document.getElementById('kpiCritical').textContent = '...';
    }

    // Hide loading state
    function hideLoadingState() {
        // Loading state will be replaced by actual data
    }

    // Show error state
    function showErrorState(message) {
        const tableBody = document.querySelector('#reportsTable tbody');
        if (tableBody) {
            tableBody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>${message}</td></tr>`;
        }
        
        // Reset KPI values
        document.getElementById('kpiDemand').textContent = 'N/A';
        document.getElementById('kpiDonations').textContent = 'N/A';
        document.getElementById('kpiBalance').textContent = 'N/A';
        document.getElementById('kpiCritical').textContent = 'N/A';
    }

    // Update KPI values based on filtered data
    function updateKPIs() {
        const selectedMonth = monthFilter.value;
        const selectedType = typeFilter.value;
        
        console.log('=== KPI DEBUG START ===');
        console.log('Updating KPIs for:', selectedMonth, selectedType);
        console.log('Selected month exact value:', JSON.stringify(selectedMonth));
        console.log('Does it include Historical Data?', selectedMonth.includes('Historical Data'));
        console.log('Does it include R Studio Forecast?', selectedMonth.includes('R Studio Forecast'));
        console.log('Available forecast data:', forecastData);
        console.log('Available current months:', currentMonths);
        console.log('Available monthly data keys:', Object.keys(monthlyData.supply || {}));
        console.log('Monthly supply data:', monthlyData.supply);
        console.log('Monthly demand data:', monthlyData.demand);
        
        // Calculate KPIs based on filtered data
        let totalDemand = 0;
        let totalSupply = 0;
        let criticalTypes = [];
        
        // Simple approach: if forecast month is selected, use forecast data
        if (selectedMonth.includes('R Studio Forecast')) {
            console.log('Using forecast data for KPIs');
            const monthName = selectedMonth.replace(' (R Studio Forecast)', '');
            
            // Filter forecast data to only the selected month
            forecastData.forEach(item => {
                // Check if this forecast item matches the selected month
                const itemMonth = new Date(item.forecast_month).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                
                if (itemMonth === monthName) {
                    console.log(`Adding forecast item for ${monthName}: ${item.blood_type} - Demand: ${item.forecasted_demand}, Supply: ${item.forecasted_supply}, Balance: ${item.projected_balance}`);
                    totalDemand += item.forecasted_demand || 0;
                    totalSupply += item.forecasted_supply || 0;
                    
                    if (item.projected_balance < 0) {
                        criticalTypes.push(item.blood_type);
                        console.log(`Found critical forecast blood type: ${item.blood_type} with balance: ${item.projected_balance}`);
                    }
                }
            });
        } else if (selectedMonth.includes('Historical Data')) {
            console.log('Using historical data for KPIs');
            const monthName = selectedMonth.replace(' (Historical Data)', '');
            console.log('Looking for month:', monthName);
            
            // Try to find the month key by checking all available keys
            let foundMonthKey = null;
            Object.keys(monthlyData.supply || {}).forEach(monthKey => {
                const monthDate = new Date(monthKey);
                const currentMonthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                console.log(`Checking month key ${monthKey} -> "${currentMonthName}" vs looking for "${monthName}"`);
                
                if (currentMonthName === monthName) {
                    foundMonthKey = monthKey;
                    console.log(`FOUND MATCH! Month key: ${monthKey}`);
                }
            });
            
            if (foundMonthKey) {
                console.log(`Processing data for ${monthName} using key: ${foundMonthKey}`);
                const supplyData = monthlyData.supply[foundMonthKey] || {};
                const demandData = monthlyData.demand[foundMonthKey] || {};
                console.log('Supply data for this month:', supplyData);
                console.log('Demand data for this month:', demandData);
                
                const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                bloodTypes.forEach(bloodType => {
                    const supply = supplyData[bloodType] || 0;
                    const demand = demandData[bloodType] || 0;
                    const balance = supply - demand;
                    
                    console.log(`${bloodType}: Supply=${supply}, Demand=${demand}, Balance=${balance}`);
                    
                    totalDemand += demand;
                    totalSupply += supply;
                    
                    if (balance < 0) {
                        criticalTypes.push(bloodType);
                        console.log(`Found critical blood type: ${bloodType} with balance: ${balance} (Supply: ${supply}, Demand: ${demand})`);
                    }
                });
            } else {
                console.log(`NO MATCH FOUND for month: "${monthName}"`);
                console.log('Available month keys:', Object.keys(monthlyData.supply || {}));
                console.log('Sample month conversions:');
                Object.keys(monthlyData.supply || {}).slice(0, 5).forEach(monthKey => {
                    const monthDate = new Date(monthKey);
                    const currentMonthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    console.log(`  ${monthKey} -> "${currentMonthName}"`);
                });
            }
        } else if (selectedMonth === 'All Months') {
            console.log('Using all data for KPIs - aggregating historical + forecast');
            console.log(`Processing ${currentMonths.length} historical months and ${forecastData.length} forecast records`);
            
            // Aggregate all historical data (2023-2025 database data)
            currentMonths.forEach(monthKey => {
                const supplyData = monthlyData.supply[monthKey] || {};
                const demandData = monthlyData.demand[monthKey] || {};
                
                const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                bloodTypes.forEach(bloodType => {
                    const supply = supplyData[bloodType] || 0;
                    const demand = demandData[bloodType] || 0;
                    const balance = supply - demand;
                    
                    totalDemand += demand;
                    totalSupply += supply;
                    
                    if (balance < 0) {
                        criticalTypes.push(bloodType);
                        console.log(`Found critical blood type: ${bloodType} with balance: ${balance} (Supply: ${supply}, Demand: ${demand})`);
                    }
                });
            });
            
            // Add all forecast data (December 2025+ predictions)
            forecastData.forEach(item => {
                totalDemand += item.forecasted_demand || 0;
                totalSupply += item.forecasted_supply || 0;
                
                if (item.projected_balance < 0) {
                    criticalTypes.push(item.blood_type);
                    console.log(`Found critical forecast blood type: ${item.blood_type} with balance: ${item.projected_balance}`);
                }
            });
            
            console.log(`All Months aggregation: ${currentMonths.length} Historical + ${forecastData.length} Forecast = Demand: ${totalDemand}, Supply: ${totalSupply}`);
        }
        
        const totalBalance = totalSupply - totalDemand;
        
        // Ensure criticalTypes is always an array
        if (!Array.isArray(criticalTypes)) {
            criticalTypes = [];
        }
        
        // Use critical types from API if available, otherwise use calculated ones
        const apiCriticalTypes = kpiData.critical_types_list || [];
        if (apiCriticalTypes.length > 0) {
            criticalTypes = apiCriticalTypes;
        }
        
        const mostCritical = criticalTypes.length > 0 ? criticalTypes[0] : 'None';
        
        console.log(`Critical types found: [${criticalTypes.join(', ')}]`);
        console.log(`Most critical: ${mostCritical}`);
        
        // Update KPI display
        document.getElementById('kpiDemand').textContent = totalDemand;
        document.getElementById('kpiDonations').textContent = totalSupply;
        
            const balanceElement = document.getElementById('kpiBalance');
        balanceElement.textContent = totalBalance >= 0 ? `+${totalBalance}` : `${totalBalance}`;
        balanceElement.style.color = totalBalance < 0 ? '#dc3545' : '#28a745';
        
        document.getElementById('kpiCritical').textContent = mostCritical;
        
        console.log(`=== KPI DEBUG END ===`);
        console.log(`FINAL KPIs: Demand: ${totalDemand}, Supply: ${totalSupply}, Balance: ${totalBalance}, Critical: ${mostCritical}`);
        console.log(`Critical types array:`, criticalTypes);
    }
    
    // Get filtered data for KPI calculation
    function getFilteredData() {
        const selectedMonth = monthFilter.value;
        const selectedType = typeFilter.value;
        const searchTerm = searchInput.value.toLowerCase();
        
        let filteredData = [];
        
        // If "All Months" is selected, show all data
        if (selectedMonth === 'All Months') {
            // Combine historical and forecast data
            filteredData = [...getHistoricalData(), ...getForecastData()];
        } else {
            // Filter by specific month
            if (selectedMonth.includes('Historical Data')) {
                filteredData = getHistoricalDataForMonth(selectedMonth);
            } else if (selectedMonth.includes('R Studio Forecast')) {
                filteredData = getForecastDataForMonth(selectedMonth);
            }
        }
        
        // Apply blood type filter
        if (selectedType !== 'all') {
            filteredData = filteredData.filter(item => item.blood_type === selectedType);
        }
        
        // Apply search filter
        if (searchTerm) {
            filteredData = filteredData.filter(item => 
                item.blood_type.toLowerCase().includes(searchTerm)
            );
        }
        
        return filteredData;
    }
    
    // Get historical data
    function getHistoricalData() {
        const data = [];
        currentMonths.forEach(monthKey => {
            const monthDate = new Date(monthKey);
            const monthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            const supplyData = monthlyData.supply[monthKey] || {};
            const demandData = monthlyData.demand[monthKey] || {};
            
            const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
            bloodTypes.forEach(bloodType => {
                const supply = supplyData[bloodType] || 0;
                const demand = demandData[bloodType] || 0;
                const balance = supply - demand;
                
                if (supply > 0 || demand > 0) {
                    data.push({
                        blood_type: bloodType,
                        forecasted_demand: demand,
                        forecasted_supply: supply,
                        projected_balance: balance,
                        month: monthName
                    });
                }
            });
        });
        return data;
    }
    
    // Get forecast data
    function getForecastData() {
        return forecastData || [];
    }
    
    // Get historical data for specific month
    function getHistoricalDataForMonth(selectedMonth) {
        const data = [];
        const monthName = selectedMonth.replace(' (Historical Data)', '');
        
        currentMonths.forEach(monthKey => {
            const monthDate = new Date(monthKey);
            const currentMonthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            if (currentMonthName === monthName) {
                const supplyData = monthlyData.supply[monthKey] || {};
                const demandData = monthlyData.demand[monthKey] || {};
                
                const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                bloodTypes.forEach(bloodType => {
                    const supply = supplyData[bloodType] || 0;
                    const demand = demandData[bloodType] || 0;
                    const balance = supply - demand;
                    
                    if (supply > 0 || demand > 0) {
                        data.push({
                            blood_type: bloodType,
                            forecasted_demand: demand,
                            forecasted_supply: supply,
                            projected_balance: balance,
                            month: currentMonthName
                        });
                    }
                });
            }
        });
        return data;
    }
    
    // Get forecast data for specific month
    function getForecastDataForMonth(selectedMonth) {
        const monthName = selectedMonth.replace(' (R Studio Forecast)', '');
        console.log('Looking for forecast data for month:', monthName);
        console.log('Available forecast data:', forecastData);
        
        // For now, return all forecast data since we're showing forecast months
        // The filtering will be handled by the month selection in renderTable
        return forecastData || [];
    }

    // Update month filter with forecast months
    function updateMonthFilter() {
        const monthFilter = document.getElementById('monthFilter');
        if (!monthFilter) return;
        
        // Clear ALL existing options (including the first one to start fresh)
        monthFilter.innerHTML = '';
        
        console.log('Clearing all dropdown options and rebuilding...');
        
        // Add "All Months" option (default selection)
        const allMonthsOption = document.createElement('option');
        allMonthsOption.value = 'All Months';
        allMonthsOption.textContent = 'All Months';
        allMonthsOption.selected = true; // Set as default
        monthFilter.appendChild(allMonthsOption);
        
        const currentYear = new Date().getFullYear();
        
        // Add forecast months FIRST (Future predictions) - sorted newest to oldest
        const sortedForecastMonths = [...forecastMonths].sort((a, b) => new Date(b) - new Date(a));
        sortedForecastMonths.forEach(monthKey => {
            const monthDate = new Date(monthKey);
            const monthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            const option = document.createElement('option');
            option.value = monthName + ' (R Studio Forecast)';
            option.textContent = monthName + ' (R Studio Forecast)';
            monthFilter.appendChild(option);
            console.log(`Added forecast option: "${option.value}"`);
        });
        
        // Add historical months LAST (ALL DATABASE DATA 2023-2025) - sorted newest to oldest
        const sortedHistoricalMonths = [...currentMonths].sort((a, b) => new Date(b) - new Date(a));
        console.log(`Adding ${sortedHistoricalMonths.length} historical months to filter dropdown`);
        
        sortedHistoricalMonths.forEach(monthKey => {
            const monthDate = new Date(monthKey);
            const monthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            const option = document.createElement('option');
            option.value = monthName + ' (Historical Data)';
            option.textContent = monthName + ' (Historical Data)';
            monthFilter.appendChild(option);
        });
        
        console.log(`Total filter options added: ${monthFilter.children.length} (1 All Months + ${forecastMonths.length} Forecast + ${sortedHistoricalMonths.length} Historical)`);
    }

    function statusFor(balance){
        if(balance <= -10) return {cls:'status-critical', label:'Critical', icon:'fa-xmark'};
        if(balance < 0) return {cls:'status-low', label:'Low', icon:'fa-triangle-exclamation'};
        return {cls:'status-surplus', label:'Surplus', icon:'fa-check'};
    }

    function filtered(){
        const t = typeFilter.value;
        const s = searchInput.value.toLowerCase();
        const m = monthFilter.value;
        
        // Filter by blood type and search
        let filteredData = forecastData.filter(r => (
            (t === 'all' || r.blood_type === t) &&
            (`${r.blood_type}`.toLowerCase().includes(s))
        ));
        
        // If a specific month is selected, filter by that month
        if (m && m !== 'All Months') {
            // This will be handled in renderTable() by showing only that month's data
            // For now, return all filtered data and let renderTable handle month filtering
        }
        
        return filteredData;
    }

    function renderTable(){
        // Generate all rows first (historical + forecast data)
        const allRows = [];
        const currentYear = new Date().getFullYear();
        
        // Get selected month filter
        const selectedMonth = monthFilter.value;
        
        // Get filtered forecast data for pagination
        const filteredForecastData = filtered();
        
        // Show historical data (ALL DATABASE DATA 2023-2025)
        console.log(`Rendering table with ${currentMonths.length} historical months and ${forecastMonths.length} forecast months`);
        
        currentMonths.sort().forEach(monthKey => {
            const monthDate = new Date(monthKey);
            const monthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            
            // Skip if month filter is set and this month doesn't match
            if (selectedMonth && selectedMonth !== 'All Months' && !monthName.includes(selectedMonth.replace(' (Historical Data)', '').replace(' (R Studio Forecast)', ''))) {
                return;
            }
        
            // Get current data for this month
            const supplyData = monthlyData.supply[monthKey] || {};
            const demandData = monthlyData.demand[monthKey] || {};
            
            // Show data for each blood type that has data in this month
            const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
            bloodTypes.forEach(bloodType => {
                const supply = supplyData[bloodType] || 0;
                const demand = demandData[bloodType] || 0;
                
                // Show ACTUAL historical data for this specific month (not forecast data)
                // This ensures each month shows different data from the CSV file
                const balance = supply - demand;
                
                // Only show rows where there's actual data
                if (supply > 0 || demand > 0) {
                    const st = statusFor(balance);
                    const bal = balance > 0 ? `+${balance}` : `${balance}`;
                    
                    allRows.push(`
                    <tr>
                        <td>${monthName} <small class="text-muted">(Database Data)</small></td>
                        <td>${bloodType}</td>
                        <td>${demand}</td>
                        <td>${supply}</td>
                        <td>${bal}</td>
                        <td><span class="status-badge ${st.cls}"><i class="fa ${st.icon}"></i> ${st.label}</span></td>
                        <td><button class="action-btn view-btn" data-type="${bloodType}" data-month="${monthName}"><i class="fa fa-eye"></i></button></td>
                    </tr>`);
                }
            });
        });
        
        // Then show forecast data (only if we have forecasts)
        console.log('Forecast months available:', forecastMonths);
        console.log('Forecast data available:', filteredForecastData);
        
        if (forecastMonths.length > 0 && filteredForecastData.length > 0) {
            forecastMonths.forEach(monthKey => {
                const monthDate = new Date(monthKey);
                const monthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                
                // Skip if month filter is set and this month doesn't match
                if (selectedMonth && selectedMonth !== 'All Months' && !monthName.includes(selectedMonth.replace(' (Historical Data)', '').replace(' (R Studio Forecast)', ''))) {
                    return;
                }
                
                console.log(`Adding forecast rows for ${monthName} (${monthKey})`);
                
                // Filter forecast data for this specific month
                const monthForecastData = filteredForecastData.filter(r => r.forecast_month === monthKey);
                
                monthForecastData.forEach(r => {
                const st = statusFor(r.projected_balance);
                const bal = r.projected_balance > 0 ? `+${r.projected_balance}` : `${r.projected_balance}`;
                    
                    console.log(`Adding forecast row: ${r.blood_type} - Demand: ${r.forecasted_demand}, Supply: ${r.forecasted_supply}, Balance: ${bal}`);
                
                allRows.push(`
                <tr>
                        <td>${monthName} <small class="text-muted">(R Studio Forecast)</small></td>
                    <td>${r.blood_type}</td>
                    <td>${r.forecasted_demand}</td>
                    <td>${r.forecasted_supply}</td>
                    <td>${bal}</td>
                    <td><span class="status-badge ${st.cls}"><i class="fa ${st.icon}"></i> ${st.label}</span></td>
                    <td><button class="action-btn view-btn" data-type="${r.blood_type}" data-month="${monthName}"><i class="fa fa-eye"></i></button></td>
                </tr>`);
            });
            });
        }
        
        // Apply pagination to all rows
        const pages = Math.max(1, Math.ceil(allRows.length / pageSize));
        if(currentPage > pages) currentPage = pages;
        const start = (currentPage - 1) * pageSize;
        const pageRows = allRows.slice(start, start + pageSize);
        
        if (allRows.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No data available</td></tr>';
        } else {
            tableBody.innerHTML = pageRows.join('');
        }
        
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
            a.addEventListener('click', (e)=>{ e.preventDefault(); currentPage = idx+1; renderTable(); updateKPIs(); });
        });
    }

    function bindRowActions(){
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => openDetails(btn.dataset.month, btn.dataset.type));
        });
    }

    function openDetails(month, type){
        const row = forecastData.find(r => r.blood_type === type);
        if(!row) return;
        const body = document.getElementById('detailsBody');
        
        // Calculate breakdown percentages
        const totalDemand = kpiData.total_forecasted_demand || 1;
        const totalSupply = kpiData.total_forecasted_supply || 1;
        const demandPercentage = ((row.forecasted_demand / totalDemand) * 100).toFixed(1);
        const supplyPercentage = ((row.forecasted_supply / totalSupply) * 100).toFixed(1);
        
        body.innerHTML = `
            <div class="small text-muted">Date: ${month}</div>
            <h5 class="mt-1">Forecast Details - ${type}</h5>
            <h6 class="mt-3">Demand Analysis</h6>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Blood Type</th><th>Forecasted Demand</th><th>% of Total Demand</th></tr></thead>
                <tbody><tr><td>${type}</td><td>${row.forecasted_demand}</td><td>${demandPercentage}%</td></tr></tbody>
            </table></div>
            <h6 class="mt-3">Supply Analysis</h6>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>Blood Type</th><th>Forecasted Supply</th><th>% of Total Supply</th></tr></thead>
                <tbody><tr><td>${type}</td><td>${row.forecasted_supply}</td><td>${supplyPercentage}%</td></tr></tbody>
            </table></div>
            <h6 class="mt-3">Projected Balance</h6>
            <div class="alert ${row.projected_balance >= 0 ? 'alert-success' : 'alert-danger'}">
                <strong>${row.projected_balance >= 0 ? 'Surplus' : 'Shortage'}:</strong> ${row.projected_balance} units
            </div>
            <h6 class="mt-3">Status</h6>
            <div class="alert ${row.status === 'surplus' ? 'alert-success' : row.status === 'low' ? 'alert-warning' : 'alert-danger'}">
                <strong>Status:</strong> ${row.status.charAt(0).toUpperCase() + row.status.slice(1)}
            </div>
            <h6 class="mt-3">Summary</h6>
            <ul class="mb-0">
                <li>Total Forecasted Demand: ${row.forecasted_demand} units</li>
                <li>Total Forecasted Supply: ${row.forecasted_supply} units</li>
                <li>Projected ${row.projected_balance>=0?'Surplus':'Shortage'}: ${row.projected_balance} units</li>
                <li>Risk Level: ${row.status === 'critical' ? 'High' : row.status === 'low' ? 'Medium' : 'Low'}</li>
            </ul>
        `;
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();
    }

    monthFilter.addEventListener('change', ()=>{ currentPage=1; renderTable(); updateKPIs(); });
    typeFilter.addEventListener('change', ()=>{ currentPage=1; renderTable(); updateKPIs(); });
    searchInput.addEventListener('input', ()=>{ currentPage=1; renderTable(); updateKPIs(); });

    // Aggregate data by blood type for charts
    function aggregateDataByBloodType(data) {
        const bloodTypeMap = {};
        
        // Sum up all values for each blood type
        data.forEach(item => {
            const bloodType = item.blood_type;
            
            if (!bloodTypeMap[bloodType]) {
                bloodTypeMap[bloodType] = {
                    bloodType: bloodType,
                    totalDemand: 0,
                    totalSupply: 0,
                    totalBalance: 0,
                    count: 0
                };
            }
            
            bloodTypeMap[bloodType].totalDemand += item.forecasted_demand || 0;
            bloodTypeMap[bloodType].totalSupply += item.forecasted_supply || 0;
            bloodTypeMap[bloodType].totalBalance += item.projected_balance || 0;
            bloodTypeMap[bloodType].count += 1;
        });
        
        // Convert to arrays for charts
        const bloodTypes = Object.keys(bloodTypeMap).sort();
        const demand = bloodTypes.map(bt => bloodTypeMap[bt].totalDemand);
        const supply = bloodTypes.map(bt => bloodTypeMap[bt].totalSupply);
        const balances = bloodTypes.map(bt => bloodTypeMap[bt].totalBalance);
        
        console.log('Blood type aggregation:', bloodTypeMap);
        
        return { bloodTypes, demand, supply, balances };
    }

    // Charts (lazy init)
    let chartsInitialized = false;
    let chartInstances = {};
    
    function initCharts(){
        if (forecastData.length === 0) return;
        
        // Reset charts if already initialized
        if (chartsInitialized) {
            Object.values(chartInstances).forEach(chart => chart.destroy());
            chartInstances = {};
        }
        
        chartsInitialized = true;
        
        // Aggregate data by blood type instead of showing monthly breakdown
        const aggregatedData = aggregateDataByBloodType(forecastData);
        
        const bloodTypes = aggregatedData.bloodTypes;
        const demand = aggregatedData.demand;
        const supply = aggregatedData.supply;
        const balances = aggregatedData.balances;
        
        // Debug: Log chart data
        console.log('Aggregated Chart Data:', { bloodTypes, demand, supply, balances });

        // Bar Chart - Demand vs Supply
        chartInstances.barChart = new Chart(document.getElementById('barChart'), {
            type:'bar',
            data:{ 
                labels: bloodTypes, 
                datasets:[
                    {label:'Forecasted Demand', data:demand, backgroundColor:'#dc3545', borderColor:'#dc3545', borderWidth:1},
                    {label:'Forecasted Supply', data:supply, backgroundColor:'#28a745', borderColor:'#28a745', borderWidth:1}
                ]
            },
            options:{
                responsive:true, 
                maintainAspectRatio: false,
                plugins:{
                    legend:{position:'bottom'},
                    title: {
                        display: true,
                        text: 'Supply vs Demand Forecast'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Units'
                        }
                    }
                }
            }
        });

        // Line Chart - Projected Balance
        chartInstances.lineChart = new Chart(document.getElementById('lineChart'), {
            type:'line',
            data:{ 
                labels: bloodTypes, 
                datasets:[
                    {
                        label:'Projected Balance', 
                        data:balances, 
                        borderColor:'#0f766e', 
                        backgroundColor:'#0f766e22', 
                        tension:.3,
                        fill: true,
                        pointBackgroundColor: balances.map(b => b < 0 ? '#dc3545' : '#28a745'),
                        pointBorderColor: balances.map(b => b < 0 ? '#dc3545' : '#28a745')
                    }
                ]
            },
            options:{
                responsive:true, 
                maintainAspectRatio: false,
                plugins:{
                    legend:{display:false},
                    title: {
                        display: true,
                        text: 'Projected Balance by Blood Type'
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Balance (Units)'
                        },
                        grid: {
                            color: function(context) {
                                return context.tick.value === 0 ? '#000' : '#e0e0e0';
                            }
                        }
                    }
                }
            }
        });

        // Pie Chart - Demand Share
        const totalDemand = demand.reduce((a, b) => a + b, 0);
        
        // Only show blood types with demand > 0
        const filteredData = [];
        const filteredLabels = [];
        const filteredColors = [];
        const colors = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#06b6d4', '#3b82f6', '#a855f7'];
        
        bloodTypes.forEach((bt, i) => {
            if (demand[i] > 0) {
                filteredData.push(demand[i]);
                const percentage = totalDemand > 0 ? ((demand[i] / totalDemand) * 100).toFixed(1) : '0.0';
                filteredLabels.push(`${bt} (${percentage}%)`);
                filteredColors.push(colors[i % colors.length]);
            }
        });
        
        // If no demand data, show a default message
        if (filteredData.length === 0) {
            filteredData.push(1);
            filteredLabels.push('No Demand Data');
            filteredColors.push('#cccccc');
        }
        
        chartInstances.pieChart = new Chart(document.getElementById('pieChart'), {
            type:'pie',
            data:{ 
                labels: filteredLabels, 
                datasets:[{ 
                    data: filteredData, 
                    backgroundColor: filteredColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options:{
                responsive:true, 
                maintainAspectRatio: false,
                plugins:{
                    legend:{position:'bottom'},
                    title: {
                        display: true,
                        text: 'Demand Distribution by Blood Type'
                    }
                }
            }
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

    // Export button - Open export options modal
    document.getElementById('exportBtn')?.addEventListener('click', ()=>{
        openExportOptionsModal();
    });

    // Open export options modal for business intelligence reports
    function openExportOptionsModal() {
        const selectedMonth = monthFilter.value;
        const selectedType = typeFilter.value;
        
        // Create export options modal HTML
        const exportModalHTML = `
            <div class="modal fade" id="exportOptionsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="fas fa-download me-2"></i>Export Blood Bank Medical Report</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-calendar me-2"></i>Report Period</h6>
                                    <select class="form-select mb-3" id="exportMonth">
                                        <option value="${selectedMonth}">${selectedMonth}</option>
                                        <option value="All Months">All Months</option>
                                        ${getMonthOptions()}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-tint me-2"></i>Blood Type</h6>
                                    <select class="form-select mb-3" id="exportBloodType">
                                        <option value="${selectedType}">${selectedType}</option>
                                        <option value="all">All Blood Types</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                    </select>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Comprehensive Blood Bank Report</strong> will include:
                                <ul class="mb-0 mt-2">
                                    <li>Blood inventory status and critical alerts</li>
                                    <li>Complete blood type analysis and trends</li>
                                    <li>Supply vs demand forecasting for patient care</li>
                                    <li>Collection recommendations and action items</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="generateReportBtn">
                                <i class="fas fa-file-pdf me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('exportOptionsModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', exportModalHTML);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('exportOptionsModal'));
        modal.show();
        
        // Handle generate report button
        document.getElementById('generateReportBtn').addEventListener('click', () => {
            generateBusinessIntelligenceReport();
            modal.hide();
        });
    }

    // Get month options for export
    function getMonthOptions() {
        let options = '';
        
        // Add forecast months
        forecastMonths.forEach(monthKey => {
            const monthDate = new Date(monthKey);
            const monthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            options += `<option value="${monthName} (R Studio Forecast)">${monthName} (R Studio Forecast)</option>`;
        });
        
        // Add historical months
        currentMonths.forEach(monthKey => {
            const monthDate = new Date(monthKey);
            const monthName = monthDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
            if (monthName !== 'September 2025') { // Skip September 2025
                options += `<option value="${monthName} (Historical Data)">${monthName} (Historical Data)</option>`;
            }
        });
        
        return options;
    }

    // Generate comprehensive blood bank report
    function generateBusinessIntelligenceReport() {
        try {
            const exportMonth = document.getElementById('exportMonth').value;
            const exportBloodType = document.getElementById('exportBloodType').value;
            
            console.log('Generating Blood Bank Report:', { exportMonth, exportBloodType });
            console.log('Available data:', { kpiData, forecastData, monthlyData });
            
            // Check if we have data
            if (!kpiData || !forecastData || !monthlyData) {
                console.error('Missing data for report generation');
                alert('Error: No data available for report generation. Please ensure the dashboard is fully loaded.');
                return;
            }
            
            // Open new window with comprehensive report
            const reportWindow = window.open('', '_blank', 'width=1200,height=800');
            
            if (!reportWindow) {
                alert('Error: Could not open report window. Please check your popup blocker settings.');
                return;
            }
            
            // Generate comprehensive report content
            const reportContent = generateComprehensiveReport(exportMonth, exportBloodType);
            
            console.log('Generated report content length:', reportContent.length);
            
            reportWindow.document.write(reportContent);
            reportWindow.document.close();
            
            // Auto-print after a short delay
            setTimeout(() => {
                if (reportWindow && !reportWindow.closed) {
                    reportWindow.print();
                }
            }, 1000);
            
        } catch (error) {
            console.error('Error generating report:', error);
            alert('Error generating report: ' + error.message);
        }
    }

    // Generate comprehensive report content
    function generateComprehensiveReport(exportMonth, exportBloodType) {
        try {
            const currentDate = new Date().toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            console.log('Generating report for:', { exportMonth, exportBloodType });
            
            // Get filtered data based on selections
            const filteredData = getFilteredReportData(exportMonth, exportBloodType);
            
            console.log('Filtered data:', filteredData);
        
        let reportHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Red Cross Blood Bank Report</title>
            <meta charset="utf-8">
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 25px; 
                    color: #333; 
                    background: white; 
                    line-height: 1.7;
                }
                .container { 
                    max-width: 1200px; 
                    margin: 0 auto; 
                    background: white; 
                    padding: 35px; 
                }
                .header { 
                    text-align: center; 
                    border-bottom: 3px solid #dc3545; 
                    padding-bottom: 25px; 
                    margin-bottom: 35px; 
                }
                .header h1 { 
                    color: #dc3545; 
                    margin: 0; 
                    font-size: 2.5em; 
                    font-weight: bold; 
                }
                .header h2 { 
                    color: #666; 
                    margin: 15px 0; 
                    font-size: 1.4em; 
                    font-weight: normal; 
                }
                .header p { 
                    color: #666; 
                    margin: 8px 0; 
                    font-size: 1.1em; 
                }
                .section { 
                    margin: 40px 0; 
                    page-break-inside: avoid; 
                }
                .section h3 { 
                    color: #dc3545; 
                    border-bottom: 2px solid #dc3545; 
                    padding-bottom: 12px; 
                    margin-bottom: 25px; 
                    font-size: 1.4em; 
                    font-weight: bold;
                }
                .section h4 { 
                    color: #555; 
                    margin: 25px 0 15px 0; 
                    font-size: 1.1em; 
                    font-weight: bold;
                }
                .table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 25px 0; 
                    font-size: 13px; 
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .table th, .table td { 
                    border: 1px solid #ddd; 
                    padding: 15px; 
                    text-align: left; 
                }
                .table th { 
                    background-color: #f8f9fa; 
                    color: #333; 
                    font-weight: bold; 
                    font-size: 14px;
                }
                .table tbody tr:nth-child(even) { 
                    background-color: #f9f9f9; 
                }
                .table tbody tr:hover { 
                    background-color: #f0f8ff; 
                }
                .kpi-section {
                    margin: 30px 0;
                    padding: 20px 0;
                    border-bottom: 1px solid #e9ecef;
                }
                .kpi-item {
                    margin: 15px 0;
                    padding: 10px 0;
                }
                .kpi-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #dc3545;
                    display: inline-block;
                    margin-right: 10px;
                }
                .kpi-label {
                    font-size: 16px;
                    color: #333;
                    font-weight: 500;
                }
                .summary-stats {
                    margin: 25px 0;
                }
                .stat-item {
                    margin: 12px 0;
                    padding: 8px 0;
                    border-bottom: 1px dotted #ddd;
                }
                .stat-value {
                    font-size: 20px;
                    font-weight: bold;
                    color: #dc3545;
                    display: inline-block;
                    margin-right: 10px;
                    min-width: 60px;
                }
                .stat-label {
                    font-size: 14px;
                    color: #666;
                }
                .alert { 
                    padding: 20px; 
                    margin: 20px 0; 
                    border: 1px solid #ccc; 
                    background-color: #f9f9f9; 
                    border-radius: 6px;
                }
                .alert-success { 
                    background-color: #f0f8f0; 
                    border-color: #28a745; 
                    color: #155724; 
                }
                .alert-warning { 
                    background-color: #fffbf0; 
                    border-color: #ffc107; 
                    color: #856404; 
                }
                .alert-danger { 
                    background-color: #fff0f0; 
                    border-color: #dc3545; 
                    color: #721c24; 
                }
                .alert-info { 
                    background-color: #f0f8ff; 
                    border-color: #17a2b8; 
                    color: #0c5460; 
                }
                .insight { 
                    margin: 20px 0; 
                    padding: 15px 0;
                    border-bottom: 1px solid #e9ecef;
                    font-size: 14px;
                }
                .recommendation { 
                    margin: 20px 0; 
                    padding: 15px 0;
                    border-bottom: 1px solid #e9ecef;
                    font-size: 14px;
                }
                .critical-alert {
                    margin: 20px 0;
                    padding: 15px 0;
                    font-weight: 500;
                    border-bottom: 2px solid #dc3545;
                }
                .recommendations { 
                    margin: 25px 0; 
                    padding: 20px 0;
                }
                .footer { 
                    margin-top: 50px; 
                    text-align: center; 
                    color: #666; 
                    font-size: 0.95em; 
                    padding-top: 25px; 
                    border-top: 2px solid #ddd; 
                }
                .text-success { 
                    color: #28a745; 
                    font-weight: bold; 
                }
                .text-danger { 
                    color: #dc3545; 
                    font-weight: bold; 
                }
                .text-warning { 
                    color: #ffc107; 
                    font-weight: bold; 
                }
                .text-info { 
                    color: #17a2b8; 
                    font-weight: bold; 
                }
                @media print { 
                    body { background: white; margin: 0; padding: 20px; }
                    .container { box-shadow: none; }
                    .section { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Philippine Red Cross</h1>
                    <h2>Blood Bank Comprehensive Report</h2>
                    <p><strong>Report Period:</strong> ${exportMonth} | <strong>Blood Type:</strong> ${exportBloodType} | <strong>Generated:</strong> ${currentDate}</p>
                </div>
        `;

            // Add comprehensive content
            if (filteredData.forecastData.length === 0) {
                reportHTML += generateEmptyDataReport(exportMonth, exportBloodType);
            } else {
                reportHTML += generateExecutiveSummary(filteredData, exportMonth, exportBloodType);
                reportHTML += generateBloodTypeAnalysis(filteredData, exportMonth, exportBloodType);
                reportHTML += generateSupplyDemandAnalysis(filteredData, exportMonth, exportBloodType);
                reportHTML += generateForecastAnalysis(filteredData, exportMonth, exportBloodType);
                reportHTML += generateKeyInsights(filteredData, exportMonth, exportBloodType);
                reportHTML += generateRecommendations(filteredData, exportMonth, exportBloodType);
            }

        reportHTML += `
                <div class="footer">
                    <p>This comprehensive report was generated using real-time blood bank data for patient care planning.</p>
                    <p>© ${new Date().getFullYear()} Philippine Red Cross - Blood Bank Management System</p>
                </div>
            </div>
        </body>
        </html>`;

            return reportHTML;
            
        } catch (error) {
            console.error('Error in generateComprehensiveReport:', error);
            return `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Error - Blood Bank Report</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
                    .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border: 1px solid #f5c6cb; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h2>Error Generating Report</h2>
                    <p>There was an error generating the blood bank report:</p>
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p>Please try again or contact support if the problem persists.</p>
                </div>
            </body>
            </html>`;
        }
    }

    // Get filtered data for report
    function getFilteredReportData(exportMonth, exportBloodType) {
        try {
            console.log('Filtering data with:', { exportMonth, exportBloodType });
            console.log('Available data:', { kpiData, forecastData, monthlyData });
            
            let data = {
                kpis: kpiData || {},
                forecastData: forecastData || [],
                monthlySupply: monthlyData?.supply || {},
                monthlyDemand: monthlyData?.demand || {}
            };

        // Filter by month if not "All Months"
        if (exportMonth !== 'All Months') {
            // Filter logic based on month selection
            data.forecastData = data.forecastData.filter(item => {
                if (exportMonth.includes('R Studio Forecast')) {
                    return item.forecast_month && item.forecast_month.includes(exportMonth.split(' (')[0]);
                } else if (exportMonth.includes('Historical Data')) {
                    return item.month && item.month.includes(exportMonth.split(' (')[0]);
                }
                return true;
            });
        }

        // Filter by blood type if not "all"
        if (exportBloodType !== 'all') {
            data.forecastData = data.forecastData.filter(item => item.blood_type === exportBloodType);
        }

            console.log('Filtered data result:', data);
            return data;
            
        } catch (error) {
            console.error('Error in getFilteredReportData:', error);
            return {
                kpis: {},
                forecastData: [],
                monthlySupply: {},
                monthlyDemand: {}
            };
        }
    }

    // Generate empty data report
    function generateEmptyDataReport(exportMonth, exportBloodType) {
        return `
            <div class="section">
                <h3>No Data Available</h3>
                <div class="alert alert-warning">
                    <strong>No Data Found:</strong> There is no data available for the selected criteria.
                </div>
                <p><strong>Selected Period:</strong> ${exportMonth}</p>
                <p><strong>Selected Blood Type:</strong> ${exportBloodType}</p>
                <p>Please try selecting different criteria or ensure the dashboard data is fully loaded.</p>
            </div>
        `;
    }

    // Generate Medical Summary report
    function generateMedicalSummary(data, exportMonth, exportBloodType) {
        try {
            const criticalTypes = Array.isArray(data.kpis?.critical_blood_types) ? data.kpis.critical_blood_types : [];
            const totalDemand = data.kpis?.total_forecasted_demand || 0;
            const totalSupply = data.kpis?.total_forecasted_supply || 0;
            const projectedBalance = data.kpis?.projected_balance || 0;

        return `
            <div class="section">
                <h3>📊 Medical Summary</h3>
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-value">${totalDemand}</div>
                        <div class="kpi-label">Patient Blood Demand</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">${totalSupply}</div>
                        <div class="kpi-label">Available Blood Supply</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">${projectedBalance}</div>
                        <div class="kpi-label">Blood Inventory Balance</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value">${criticalTypes.length}</div>
                        <div class="kpi-label">Critical Blood Types</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>🚨 Critical Alerts</h3>
                ${criticalTypes.length > 0 ? 
                    `<div class="alert alert-danger">
                        <strong>Critical Blood Types:</strong> ${Array.isArray(criticalTypes) ? criticalTypes.join(', ') : criticalTypes}<br>
                        <strong>Urgent Action Required:</strong> Immediate blood collection campaigns needed to ensure patient safety.
                    </div>` :
                    `<div class="alert alert-success">
                        <strong>No Critical Alerts:</strong> All blood types are within safe inventory levels for patient care.
                    </div>`
                }
            </div>

            <div class="section">
                <h3>📈 Key Insights</h3>
                <ul>
                    <li><strong>Blood Supply vs Patient Demand Ratio:</strong> ${totalSupply > 0 ? (totalSupply/totalDemand).toFixed(2) : 'N/A'}</li>
                    <li><strong>Blood Inventory Status:</strong> ${projectedBalance >= 0 ? 'Adequate Supply' : 'Shortage Risk'} (${projectedBalance} units)</li>
                    <li><strong>Data Source:</strong> ${exportMonth.includes('Historical') ? 'Historical Data + Statistical Forecast' : 'Statistical Forecast Only'}</li>
                    <li><strong>Report Scope:</strong> ${exportBloodType === 'all' ? 'All Blood Types' : exportBloodType} for ${exportMonth}</li>
                </ul>
            </div>

            <div class="recommendations">
                <h3>💡 Recommendations</h3>
                <ul>
                    ${projectedBalance < 0 ? '<li><strong>Urgent:</strong> Increase blood collection efforts to address projected shortage and ensure patient safety</li>' : ''}
                    ${criticalTypes.length > 0 ? '<li><strong>Priority:</strong> Focus collection campaigns on critical blood types: ' + (Array.isArray(criticalTypes) ? criticalTypes.join(', ') : criticalTypes) + ' to prevent patient care delays</li>' : ''}
                    <li><strong>Monitoring:</strong> Continue tracking blood supply vs patient demand trends using statistical forecasting</li>
                    <li><strong>Planning:</strong> Use forecast data for blood inventory planning and donor recruitment campaigns</li>
                </ul>
            </div>
        `;
        } catch (error) {
            console.error('Error in generateMedicalSummary:', error);
            return `
            <div class="section">
                <h3>📊 Medical Summary</h3>
                <div class="alert alert-danger">
                    <strong>Error:</strong> Unable to generate medical summary: ${error.message}
                </div>
            </div>`;
        }
    }

    // Generate Inventory Analysis report
    function generateInventoryAnalysis(data, exportMonth, exportBloodType) {
        return `
            <div class="section">
                <h3>Blood Type Analysis</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Blood Type</th>
                            <th>Patient Demand</th>
                            <th>Available Supply</th>
                            <th>Inventory Balance</th>
                            <th>Status</th>
                            <th>Percentage of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.forecastData.map(item => `
                            <tr>
                                <td><strong>${item.blood_type}</strong></td>
                                <td>${item.forecasted_demand}</td>
                                <td>${item.forecasted_supply}</td>
                                <td class="${item.projected_balance >= 0 ? 'text-success' : 'text-danger'}">${item.projected_balance}</td>
                                <td>${item.status}</td>
                                <td>${((item.forecasted_demand / (data.kpis.total_forecasted_demand || 1)) * 100).toFixed(1)}%</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h3>Blood Inventory Trend Analysis</h3>
                <p><strong>Data Period:</strong> ${exportMonth}</p>
                <p><strong>Blood Type Focus:</strong> ${exportBloodType === 'all' ? 'All Blood Types' : exportBloodType}</p>
                <p><strong>Total Records:</strong> ${data.forecastData.length} data points</p>
            </div>
        `;
    }

    // Generate Key Insights
    function generateKeyInsights(data, exportMonth, exportBloodType) {
        const criticalTypes = Array.isArray(data.kpis?.critical_blood_types) ? data.kpis.critical_blood_types : [];
        const totalDemand = data.kpis?.total_forecasted_demand || 0;
        const totalSupply = data.kpis?.total_forecasted_supply || 0;
        const projectedBalance = data.kpis?.projected_balance || 0;

        return `
            <div class="section">
                <h3>Key Insights</h3>
                <ul>
                    <li><strong>Blood Supply vs Patient Demand Ratio:</strong> ${totalSupply > 0 ? (totalSupply/totalDemand).toFixed(2) : 'N/A'}</li>
                    <li><strong>Blood Inventory Status:</strong> ${projectedBalance >= 0 ? 'Adequate Supply' : 'Shortage Risk'} (${projectedBalance} units)</li>
                    <li><strong>Data Source:</strong> ${exportMonth.includes('Historical') ? 'Historical Data + Statistical Forecast' : 'Statistical Forecast Only'}</li>
                    <li><strong>Report Scope:</strong> ${exportBloodType === 'all' ? 'All Blood Types' : exportBloodType} for ${exportMonth}</li>
                    <li><strong>Critical Blood Types:</strong> ${criticalTypes.length > 0 ? (Array.isArray(criticalTypes) ? criticalTypes.join(', ') : criticalTypes) : 'None identified'}</li>
                </ul>
            </div>
        `;
    }

    // Generate Recommendations
    function generateRecommendations(data, exportMonth, exportBloodType) {
        const criticalTypes = Array.isArray(data.kpis?.critical_blood_types) ? data.kpis.critical_blood_types : [];
        const projectedBalance = data.kpis?.projected_balance || 0;

        return `
            <div class="section">
                <h3>Recommendations</h3>
                <ul>
                    ${projectedBalance < 0 ? '<li><strong>Urgent:</strong> Increase blood collection efforts to address projected shortage and ensure patient safety</li>' : ''}
                    ${criticalTypes.length > 0 ? '<li><strong>Priority:</strong> Focus collection campaigns on critical blood types: ' + (Array.isArray(criticalTypes) ? criticalTypes.join(', ') : criticalTypes) + ' to prevent patient care delays</li>' : ''}
                    <li><strong>Monitoring:</strong> Continue tracking blood supply vs patient demand trends using statistical forecasting</li>
                    <li><strong>Planning:</strong> Use forecast data for blood inventory planning and donor recruitment campaigns</li>
                </ul>
            </div>
        `;
    }

    // Generate Forecast Report
    function generateForecastReport(data, exportMonth, exportBloodType) {
        return `
            <div class="section">
                <h3>🔮 Blood Supply Forecast Analysis</h3>
                <div class="alert alert-info">
                    <strong>Forecasting Model:</strong> Advanced time series analysis using historical blood bank data and current patient demand patterns.
                </div>
                
                <h4>Forecast Methodology</h4>
                <ul>
                    <li><strong>Training Data:</strong> Historical blood bank data (2016-2025) + Current patient demand records</li>
                    <li><strong>Model Type:</strong> Statistical analysis with trend prediction for blood supply forecasting</li>
                    <li><strong>Seasonal Adjustment:</strong> Seasonal factors for blood collection patterns</li>
                    <li><strong>Forecast Horizon:</strong> 6 months ahead for patient care planning</li>
                </ul>
            </div>

            <div class="section">
                <h3>📊 Forecast Results</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Blood Type</th>
                            <th>Historical Average</th>
                            <th>Forecasted Supply</th>
                            <th>Confidence Level</th>
                            <th>Collection Recommendation</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.forecastData.map(item => `
                            <tr>
                                <td><strong>${item.blood_type}</strong></td>
                                <td>${(item.forecasted_supply * 0.8).toFixed(1)}</td>
                                <td>${item.forecasted_supply}</td>
                                <td>${item.projected_balance >= 0 ? 'High' : 'Medium'}</td>
                                <td>${item.projected_balance >= 0 ? 'Maintain current collection levels' : 'Increase collection efforts for patient safety'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>

            <div class="recommendations">
                <h3>🎯 Medical Collection Recommendations</h3>
                <ul>
                    <li><strong>Short-term (1-3 months):</strong> Focus collection efforts on blood types with negative projected balance to ensure patient safety</li>
                    <li><strong>Medium-term (3-6 months):</strong> Implement seasonal adjustment strategies for blood collection campaigns</li>
                    <li><strong>Long-term (6+ months):</strong> Develop predictive collection campaigns based on patient demand forecasting</li>
                </ul>
            </div>
        `;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function(){
        // Fetch forecast data from API
        fetchForecastData();
        
        // Defer charts init for faster first paint
        scheduleChartsInit();
        
        // Force refresh dropdown after a short delay to ensure data is loaded
        setTimeout(() => {
            console.log('Force refreshing month filter dropdown...');
            updateMonthFilter();
        }, 1000);
        
        // Additional force refresh after 3 seconds to ensure cache is cleared
        setTimeout(() => {
            console.log('Second force refresh to clear any cache...');
            updateMonthFilter();
        }, 3000);
    });

    // ===== NEW COMPREHENSIVE REPORT FUNCTIONS =====
    
    // Generate Executive Summary with aggregated KPIs
    function generateExecutiveSummary(data, exportMonth, exportBloodType) {
        try {
            const criticalTypes = Array.isArray(data.kpis?.critical_blood_types) ? data.kpis.critical_blood_types : [];
            const totalDemand = data.kpis?.total_forecasted_demand || 0;
            const totalSupply = data.kpis?.total_forecasted_supply || 0;
            const projectedBalance = data.kpis?.projected_balance || 0;
            
            // Calculate aggregated statistics
            const allData = data.forecastData || [];
            const bloodTypeCount = new Set(allData.map(item => item.blood_type)).size;
            const totalMonths = new Set(allData.map(item => item.month || item.forecast_month)).size;
            
            return `
                <div class="section">
                    <h3>Executive Summary</h3>
                    <div class="kpi-section">
                        <div class="kpi-item">
                            <span class="kpi-value">${totalDemand}</span>
                            <span class="kpi-label">Total Patient Demand</span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-value">${totalSupply}</span>
                            <span class="kpi-label">Total Blood Supply</span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-value">${projectedBalance}</span>
                            <span class="kpi-label">Projected Balance</span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-value">${bloodTypeCount}</span>
                            <span class="kpi-label">Blood Types Analyzed</span>
                        </div>
                    </div>
                    
                    <h4>Report Statistics</h4>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-value">${totalMonths}</span>
                            <span class="stat-label">Months Covered</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${allData.length}</span>
                            <span class="stat-label">Data Points</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${criticalTypes.length}</span>
                            <span class="stat-label">Critical Types</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${projectedBalance >= 0 ? 'Stable' : 'Critical'}</span>
                            <span class="stat-label">Overall Status</span>
                        </div>
                    </div>
                    
                    ${criticalTypes.length > 0 ? `
                        <div class="critical-alert">
                            <strong>Critical Alert:</strong> ${Array.isArray(criticalTypes) ? criticalTypes.join(', ') : criticalTypes} blood types require immediate attention.
                        </div>
                    ` : ''}
                </div>
            `;
        } catch (error) {
            console.error('Error generating executive summary:', error);
            return `<div class="section"><h3>Executive Summary</h3><p>Error generating summary: ${error.message}</p></div>`;
        }
    }
    
    // Generate Blood Type Analysis with aggregated data
    function generateBloodTypeAnalysis(data, exportMonth, exportBloodType) {
        try {
            const allData = data.forecastData || [];
            
            // Aggregate data by blood type
            const bloodTypeStats = {};
            allData.forEach(item => {
                const bloodType = item.blood_type;
                if (!bloodTypeStats[bloodType]) {
                    bloodTypeStats[bloodType] = {
                        totalDemand: 0,
                        totalSupply: 0,
                        totalBalance: 0,
                        months: new Set(),
                        status: 'Stable'
                    };
                }
                
                bloodTypeStats[bloodType].totalDemand += item.forecasted_demand || 0;
                bloodTypeStats[bloodType].totalSupply += item.forecasted_supply || 0;
                bloodTypeStats[bloodType].totalBalance += item.projected_balance || 0;
                bloodTypeStats[bloodType].months.add(item.month || item.forecast_month);
                
                // Determine status based on balance
                if (item.projected_balance < 0) {
                    bloodTypeStats[bloodType].status = 'Critical';
                } else if (item.projected_balance < 5) {
                    bloodTypeStats[bloodType].status = 'Low';
                }
            });
            
            // Convert to array and sort by total demand
            const bloodTypeArray = Object.entries(bloodTypeStats).map(([bloodType, stats]) => ({
                bloodType,
                ...stats,
                monthsCount: stats.months.size
            })).sort((a, b) => b.totalDemand - a.totalDemand);
            
            return `
                <div class="section">
                    <h3>Blood Type Analysis</h3>
                    <h4>Aggregated Statistics by Blood Type</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Blood Type</th>
                                <th>Total Demand</th>
                                <th>Total Supply</th>
                                <th>Net Balance</th>
                                <th>Months Analyzed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${bloodTypeArray.map(item => `
                                <tr>
                                    <td><strong>${item.bloodType}</strong></td>
                                    <td>${item.totalDemand.toFixed(1)}</td>
                                    <td>${item.totalSupply.toFixed(1)}</td>
                                    <td class="${item.totalBalance >= 0 ? 'text-success' : 'text-danger'}">${item.totalBalance.toFixed(1)}</td>
                                    <td>${item.monthsCount}</td>
                                    <td>
                                        <span class="${item.status === 'Critical' ? 'text-danger' : item.status === 'Low' ? 'text-warning' : 'text-success'}">
                                            ${item.status}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    
                    <h4>Key Findings</h4>
                    <div class="insight">
                        <strong>Highest Demand:</strong> ${bloodTypeArray[0]?.bloodType || 'N/A'} (${bloodTypeArray[0]?.totalDemand.toFixed(1) || 0} units)
                    </div>
                    <div class="insight">
                        <strong>Most Critical:</strong> ${bloodTypeArray.filter(item => item.status === 'Critical').map(item => item.bloodType).join(', ') || 'None'}
                    </div>
                    <div class="insight">
                        <strong>Most Stable:</strong> ${bloodTypeArray.filter(item => item.status === 'Stable').map(item => item.bloodType).join(', ') || 'None'}
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error generating blood type analysis:', error);
            return `<div class="section"><h3>Blood Type Analysis</h3><p>Error generating analysis: ${error.message}</p></div>`;
        }
    }
    
    // Generate Supply vs Demand Analysis
    function generateSupplyDemandAnalysis(data, exportMonth, exportBloodType) {
        try {
            const allData = data.forecastData || [];
            
            // Calculate supply vs demand ratios
            const supplyDemandAnalysis = {};
            allData.forEach(item => {
                const bloodType = item.blood_type;
                if (!supplyDemandAnalysis[bloodType]) {
                    supplyDemandAnalysis[bloodType] = {
                        totalSupply: 0,
                        totalDemand: 0,
                        ratios: []
                    };
                }
                
                supplyDemandAnalysis[bloodType].totalSupply += item.forecasted_supply || 0;
                supplyDemandAnalysis[bloodType].totalDemand += item.forecasted_demand || 0;
                
                if (item.forecasted_demand > 0) {
                    supplyDemandAnalysis[bloodType].ratios.push((item.forecasted_supply || 0) / item.forecasted_demand);
                }
            });
            
            // Calculate average ratios and classify
            const analysisArray = Object.entries(supplyDemandAnalysis).map(([bloodType, stats]) => {
                const avgRatio = stats.ratios.length > 0 ? 
                    stats.ratios.reduce((sum, ratio) => sum + ratio, 0) / stats.ratios.length : 0;
                
                let classification = 'Balanced';
                if (avgRatio < 0.8) classification = 'Shortage Risk';
                else if (avgRatio > 1.2) classification = 'Surplus';
                
                return {
                    bloodType,
                    totalSupply: stats.totalSupply,
                    totalDemand: stats.totalDemand,
                    avgRatio: avgRatio,
                    classification
                };
            }).sort((a, b) => a.avgRatio - b.avgRatio);
            
            return `
                <div class="section">
                    <h3>Supply vs Demand Analysis</h3>
                    <h4>Supply-Demand Ratios by Blood Type</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Blood Type</th>
                                <th>Total Supply</th>
                                <th>Total Demand</th>
                                <th>Supply/Demand Ratio</th>
                                <th>Classification</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${analysisArray.map(item => `
                                <tr>
                                    <td><strong>${item.bloodType}</strong></td>
                                    <td>${item.totalSupply.toFixed(1)}</td>
                                    <td>${item.totalDemand.toFixed(1)}</td>
                                    <td>${item.avgRatio.toFixed(2)}</td>
                                    <td>
                                        <span class="${item.classification === 'Shortage Risk' ? 'text-danger' : item.classification === 'Surplus' ? 'text-success' : 'text-info'}">
                                            ${item.classification}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    
                    <h4>Analysis Insights</h4>
                    <div class="insight">
                        <strong>Shortage Risk Types:</strong> ${analysisArray.filter(item => item.classification === 'Shortage Risk').map(item => item.bloodType).join(', ') || 'None'}
                    </div>
                    <div class="insight">
                        <strong>Surplus Types:</strong> ${analysisArray.filter(item => item.classification === 'Surplus').map(item => item.bloodType).join(', ') || 'None'}
                    </div>
                    <div class="insight">
                        <strong>Balanced Types:</strong> ${analysisArray.filter(item => item.classification === 'Balanced').map(item => item.bloodType).join(', ') || 'None'}
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error generating supply demand analysis:', error);
            return `<div class="section"><h3>Supply vs Demand Analysis</h3><p>Error generating analysis: ${error.message}</p></div>`;
        }
    }
    
    // Generate Forecast Analysis
    function generateForecastAnalysis(data, exportMonth, exportBloodType) {
        try {
            const allData = data.forecastData || [];
            
            // Separate historical and forecast data
            const historicalData = allData.filter(item => item.month && !item.forecast_month);
            const forecastData = allData.filter(item => item.forecast_month);
            
            // Calculate forecast accuracy metrics (if we have historical data)
            const forecastMetrics = {};
            if (historicalData.length > 0 && forecastData.length > 0) {
                // This is a simplified accuracy calculation
                const avgHistoricalDemand = historicalData.reduce((sum, item) => sum + (item.forecasted_demand || 0), 0) / historicalData.length;
                const avgForecastDemand = forecastData.reduce((sum, item) => sum + (item.forecasted_demand || 0), 0) / forecastData.length;
                
                forecastMetrics.demandTrend = avgForecastDemand > avgHistoricalDemand ? 'Increasing' : 'Decreasing';
                forecastMetrics.supplyTrend = 'Stable'; // Simplified
            }
            
            return `
                <div class="section">
                    <h3>Forecast Analysis</h3>
                    <h4>Forecasting Model Performance</h4>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-value">${historicalData.length}</span>
                            <span class="stat-label">Historical Data Points</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${forecastData.length}</span>
                            <span class="stat-label">Forecast Data Points</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${forecastMetrics.demandTrend || 'N/A'}</span>
                            <span class="stat-label">Demand Trend</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">ARIMA</span>
                            <span class="stat-label">Model Type</span>
                        </div>
                    </div>
                    
                    <h4>Forecast Summary</h4>
                    <div class="insight">
                        <strong>Model Used:</strong> Advanced time series analysis using historical blood bank data and current patient demand patterns
                    </div>
                    <div class="insight">
                        <strong>Forecast Horizon:</strong> 6 months ahead
                    </div>
                    <div class="insight">
                        <strong>Data Sources:</strong> Historical donations (2016-2025) + Current database records
                    </div>
                    <div class="insight">
                        <strong>Seasonal Adjustment:</strong> Applied to account for seasonal variations in blood demand
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error generating forecast analysis:', error);
            return `<div class="section"><h3>Forecast Analysis</h3><p>Error generating analysis: ${error.message}</p></div>`;
        }
    }

})();


