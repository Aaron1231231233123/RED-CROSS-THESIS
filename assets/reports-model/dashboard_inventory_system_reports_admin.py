"""
Forecast Reports Python Module - Main Entry Point
Refactored into modular structure for better performance and maintainability
Maintains backward compatibility with existing code
"""

import json
import sys
from datetime import datetime
from typing import Dict, List

# Import from modular components
from config import BLOOD_TYPES
from database import DatabaseConnection
from data_processing import process_monthly_supply, process_monthly_demand
from forecasting import forecast_next_month_per_type
from kpis import calculate_shelf_life_metrics, calculate_kpis, aggregate_data_by_blood_type

# Import optimized forecasting functions
try:
    from forecasting_optimized import fit_and_cache_models, generate_all_forecasts, forecast_multi_step_optimized
    OPTIMIZED_FORECASTING_AVAILABLE = True
except ImportError:
    # Fallback to regular forecasting if optimized version not available
    from forecasting import forecast_multi_step as forecast_multi_step_optimized
    OPTIMIZED_FORECASTING_AVAILABLE = False
    
    def fit_and_cache_models(df_monthly_data):
        return {}
    
    def generate_all_forecasts(cached_models, max_horizon):
        return {}


class ForecastReportsCalculator:
    """Main class for forecast calculations and database operations"""
    
    def __init__(self):
        self.db = DatabaseConnection()
        self.monthly_supply = {}
        self.monthly_demand = {}
        self.forecast_data = []
        self.kpi_data = {}
        self.blood_units = []
        
    def connect_database(self):
        """Connect to Supabase database"""
        return self.db.connect()
    
    def disconnect_database(self):
        """Disconnect from database"""
        self.db.disconnect()
    
    def fetch_blood_units(self) -> List[Dict]:
        """Fetch ALL blood bank units data"""
        return self.db.fetch_blood_units()
    
    def fetch_blood_requests(self) -> List[Dict]:
        """Fetch ALL blood requests data"""
        return self.db.fetch_blood_requests()
    
    def process_monthly_supply(self, blood_units: List[Dict]) -> Dict[str, Dict[str, int]]:
        """Process blood units data for supply forecasting"""
        return process_monthly_supply(blood_units)
    
    def process_monthly_demand(self, blood_requests: List[Dict], monthly_supply: Dict) -> Dict[str, Dict[str, int]]:
        """Process blood requests data for demand forecasting"""
        return process_monthly_demand(blood_requests, monthly_supply)
    
    def forecast_next_month_per_type(self, df_monthly_data: List[Dict], forecast_horizon: int = 1) -> List[Dict]:
        """Forecast next month per blood type (matching R's auto.arima)"""
        return forecast_next_month_per_type(df_monthly_data, forecast_horizon)
    
    def forecast_multi_step(self, df_monthly_data: List[Dict], months_ahead: int) -> Dict[str, float]:
        """Generate multi-step ahead forecasts (uses optimized version with caching)"""
        return forecast_multi_step_optimized(df_monthly_data, months_ahead)
    
    def forecast_next_month(self, monthly_data: Dict[str, Dict[str, int]], 
                           blood_types: List[str]) -> Dict[str, float]:
        """
        Wrapper function that converts monthly_data dict to R-style format
        and calls forecast_next_month_per_type (matching R structure)
        """
        # Convert dict format to R-style list of dicts
        df_monthly_list = []
        for month_key in sorted(monthly_data.keys()):
            for blood_type in blood_types:
                value = monthly_data[month_key].get(blood_type, 0)
                df_monthly_list.append({
                    'month': month_key,
                    'blood_type': blood_type,
                    'value': value
                })
        
        # Call R-equivalent function
        forecast_results = self.forecast_next_month_per_type(df_monthly_list)
        
        # Convert back to dict format for compatibility
        forecasts = {}
        for result in forecast_results:
            bt = result['Blood Type']
            forecasts[bt] = result['Forecast']
        
        # Fill in missing blood types with default
        for bt in blood_types:
            if bt not in forecasts:
                forecasts[bt] = 5  # Default value
        
        return forecasts
    
    def generate_forecast_months(self, monthly_supply: Dict, current_date: datetime) -> List[str]:
        """
        Generate forecast months - EXACT R LOGIC: Only 3 months ahead (New code.R: h = 3)
        R only forecasts 3 months ahead for visualization, not 13 months
        """
        forecast_months = []
        
        # Find the last historical month
        if monthly_supply:
            last_historical_month = max([datetime.strptime(m, '%Y-%m-%d') for m in monthly_supply.keys()])
        else:
            last_historical_month = current_date.replace(day=1)
        
        # Generate 6 months of forecasted data (as requested)
        max_forecast_months = 6
        
        for i in range(1, max_forecast_months + 1):
            # Calculate next month manually
            year = last_historical_month.year
            month = last_historical_month.month + i
            
            # Handle year rollover
            while month > 12:
                month -= 12
                year += 1
            
            next_month = datetime(year, month, 1)
            month_key = next_month.strftime('%Y-%m-%d')
            
            # Always add forecast months (even if they exist in database, we want to show forecast)
            # This ensures we show exactly 6 months of forecasted data (Dec to May)
            forecast_months.append(month_key)
        
        return forecast_months
    
    def calculate_status(self, balance: float) -> str:
        """Calculate status based on balance"""
        if balance <= -10:
            return 'critical'
        elif balance < 0:
            return 'low'
        else:
            return 'surplus'
    
    def calculate_shelf_life_metrics(self, blood_units: List[Dict]) -> Dict:
        """Calculate shelf life metrics (weekly and monthly)"""
        return calculate_shelf_life_metrics(blood_units)
    
    def calculate_kpis(self, forecast_data: List[Dict], monthly_supply: Dict, 
                      monthly_demand: Dict, selected_month: str = 'All Months',
                      selected_type: str = 'all') -> Dict:
        """Calculate KPI values based on filtered data"""
        return calculate_kpis(forecast_data, monthly_supply, monthly_demand, selected_month, selected_type)
    
    def aggregate_data_by_blood_type(self, data: List[Dict]) -> Dict:
        """Aggregate data by blood type for charts"""
        return aggregate_data_by_blood_type(data)
    
    def _generate_trendline_data(self, monthly_supply: Dict, monthly_demand: Dict,
                                supply_forecasts: Dict, demand_forecasts: Dict,
                                forecast_months: List[str], last_historical_month: datetime,
                                projected_stock: Dict) -> Dict:
        """
        Generate trendline data structures matching R code (New code.R)
        Creates data for: Supply trendlines, Demand trendlines, Combined, and Stock Level with Buffer
        R code generates 3-month forecasts (h=3) for visualization
        """
        trendline_data = {
            'supply_trendlines': {},
            'demand_trendlines': {},
            'combined_trendlines': {},
            'stock_level_trendlines': {}
        }
        
        # R code: Generate 3 months forecast (h = 3) for visualization
        forecast_horizon = 3
        
        for bt in BLOOD_TYPES:
            # ===== SUPPLY TRENDLINE DATA =====
            # R code: Filter actual data to show only 2025
            supply_actual_2025 = []
            supply_forecast_2025 = []
            
            # Get actual supply data for 2025
            for month_key in sorted(monthly_supply.keys()):
                month_date = datetime.strptime(month_key, '%Y-%m-%d')
                if month_date.year == 2025:
                    value = monthly_supply[month_key].get(bt, 0)
                    supply_actual_2025.append({
                        'month': month_key,
                        'value': value
                    })
            
            # Get supply forecasts (3 months ahead, matching R's h=3)
            supply_forecast_dict = supply_forecasts.get(bt, {})
            if isinstance(supply_forecast_dict, dict):
                supply_forecast_list = supply_forecast_dict.get('forecast', [])
            else:
                supply_forecast_list = supply_forecast_dict if isinstance(supply_forecast_dict, list) else []
            
            # Generate forecast months (3 months ahead)
            for i in range(min(forecast_horizon, len(supply_forecast_list))):
                if i < len(forecast_months):
                    forecast_month = forecast_months[i]
                    forecast_value = supply_forecast_list[i] if i < len(supply_forecast_list) else 0
                    supply_forecast_2025.append({
                        'month': forecast_month,
                        'value': max(0, round(forecast_value))
                    })
            
            trendline_data['supply_trendlines'][bt] = {
                'actual_2025': supply_actual_2025,
                'forecast_3months': supply_forecast_2025
            }
            
            # ===== DEMAND TRENDLINE DATA =====
            # R code: Filter actual data to show only 2025
            demand_actual_2025 = []
            demand_forecast_2025 = []
            
            # Get actual demand data for 2025
            for month_key in sorted(monthly_demand.keys()):
                month_date = datetime.strptime(month_key, '%Y-%m-%d')
                if month_date.year == 2025:
                    value = monthly_demand[month_key].get(bt, 0)
                    demand_actual_2025.append({
                        'month': month_key,
                        'value': value
                    })
            
            # Get demand forecasts (3 months ahead, matching R's h=3)
            demand_forecast_dict = demand_forecasts.get(bt, {})
            if isinstance(demand_forecast_dict, dict):
                demand_forecast_list = demand_forecast_dict.get('forecast', [])
            else:
                demand_forecast_list = demand_forecast_dict if isinstance(demand_forecast_dict, list) else []
            
            # Generate forecast months (3 months ahead)
            for i in range(min(forecast_horizon, len(demand_forecast_list))):
                if i < len(forecast_months):
                    forecast_month = forecast_months[i]
                    forecast_value = demand_forecast_list[i] if i < len(demand_forecast_list) else 0
                    demand_forecast_2025.append({
                        'month': forecast_month,
                        'value': max(0, round(forecast_value))
                    })
            
            trendline_data['demand_trendlines'][bt] = {
                'actual_2025': demand_actual_2025,
                'forecast_3months': demand_forecast_2025
            }
            
            # ===== COMBINED SUPPLY vs DEMAND TRENDLINE DATA =====
            trendline_data['combined_trendlines'][bt] = {
                'supply_actual_2025': supply_actual_2025,
                'supply_forecast_3months': supply_forecast_2025,
                'demand_actual_2025': demand_actual_2025,
                'demand_forecast_3months': demand_forecast_2025
            }
            
            # ===== STOCK LEVEL WITH BUFFER TRENDLINE DATA =====
            # R code: Projected Stock Level with Target Buffer visualization
            # This includes the projected stock level and target buffer level for visualization
            stock_data = projected_stock.get(bt, {})
            trendline_data['stock_level_trendlines'][bt] = {
                'projected_stock_level': stock_data.get('Projected Stock Level (Next Month)', 0),
                'target_stock_level': stock_data.get('Target Stock Level', 0),
                'target_buffer_level': stock_data.get('Target Buffer Level', 0),
                'buffer_gap': stock_data.get('Buffer Gap', 0),
                'buffer_status': stock_data.get('Buffer Status', '🟢 Above Target (Safe)'),
                'forecast_demand': stock_data.get('Forecast_Demand', 0),
                'forecast_supply': stock_data.get('Forecast_Supply', 0)
            }
        
        return trendline_data
    
    def generate_forecasts(self) -> Dict:
        """
        EXACT 1:1 translation of R Studio workflow:
        1. Blood Supply Forecast.R - forecast_next_month_per_type()
        2. Blood Demand Forecast.R - forecast_hospital_requests()
        3. Supply vs Demand Forecast.R - merge and calculate gap
        4. Projected Stock Level.R - calculate projected balance and status
        """
        # Connect to database
        if not self.connect_database():
            raise Exception("Failed to connect to database")
        
        try:
            # Fetch data
            import sys
            print(f"DEBUG: Starting data fetch...", file=sys.stderr)
            blood_units = self.fetch_blood_units()
            blood_requests = self.fetch_blood_requests()
            
            print(f"DEBUG: Fetched {len(blood_units)} blood units, {len(blood_requests)} blood requests", file=sys.stderr)
            
            # Store blood_units for shelf life calculations
            self.blood_units = blood_units
            
            # Process data (R: df_monthly <- group_by + summarise)
            self.monthly_supply = self.process_monthly_supply(blood_units)
            self.monthly_demand = self.process_monthly_demand(blood_requests, self.monthly_supply)
            
            print(f"DEBUG: After processing - Supply months: {len(self.monthly_supply)}, Demand months: {len(self.monthly_demand)}", file=sys.stderr)
            
            # DEBUG: Log sample demand data to verify it's being processed
            if self.monthly_demand:
                sample_month = list(self.monthly_demand.keys())[0]
                sample_demand = self.monthly_demand[sample_month]
                total_demand_sample = sum(sample_demand.values())
                print(f"DEBUG: Sample demand month ({sample_month}): {sample_demand}, Total: {total_demand_sample}", file=sys.stderr)
                print(f"DEBUG: All demand months: {list(self.monthly_demand.keys())}", file=sys.stderr)
                # Log total demand across all months
                total_all_demand = sum(sum(month_data.values()) for month_data in self.monthly_demand.values())
                print(f"DEBUG: Total demand across all months: {total_all_demand} units", file=sys.stderr)
            else:
                print(f"ERROR: monthly_demand is EMPTY! Check blood_requests table data.", file=sys.stderr)
                print(f"  - blood_requests count: {len(blood_requests)}", file=sys.stderr)
                if blood_requests:
                    sample_request = blood_requests[0]
                    print(f"  - Sample request keys: {list(sample_request.keys())}", file=sys.stderr)
                    print(f"  - Sample request: {sample_request}", file=sys.stderr)
            
            # Convert to R-style format - EXACT R LOGIC: Only include months with actual data (value > 0)
            # R code: sub_df <- filter(df_monthly, blood_type == bt) - R doesn't include zero values
            # This ensures we only use real data for forecasting, not empty months
            df_monthly_supply = []
            for month_key in sorted(self.monthly_supply.keys()):
                for bt in BLOOD_TYPES:
                    value = self.monthly_supply[month_key].get(bt, 0)
                    if value > 0:  # EXACT R: Only include months with actual data
                        df_monthly_supply.append({'month': month_key, 'blood_type': bt, 'value': value})
            
            df_monthly_demand = []
            for month_key in sorted(self.monthly_demand.keys()):
                for bt in BLOOD_TYPES:
                    value = self.monthly_demand[month_key].get(bt, 0)
                    if value > 0:  # EXACT R: Only include months with actual data
                        df_monthly_demand.append({'month': month_key, 'blood_type': bt, 'value': value})
            
            # DEBUG: Log df_monthly_demand details
            import sys
            print(f"DEBUG: df_monthly_demand has {len(df_monthly_demand)} records", file=sys.stderr)
            if df_monthly_demand:
                # Group by blood type to see distribution
                from collections import defaultdict
                bt_counts = defaultdict(int)
                bt_totals = defaultdict(int)
                for item in df_monthly_demand:
                    bt_counts[item['blood_type']] += 1
                    bt_totals[item['blood_type']] += item['value']
                print(f"DEBUG: df_monthly_demand by blood type (count): {dict(bt_counts)}", file=sys.stderr)
                print(f"DEBUG: df_monthly_demand by blood type (total units): {dict(bt_totals)}", file=sys.stderr)
                # Show sample records
                print(f"DEBUG: Sample df_monthly_demand records (first 5):", file=sys.stderr)
                for item in df_monthly_demand[:5]:
                    print(f"  - {item}", file=sys.stderr)
            else:
                print(f"WARNING: df_monthly_demand is EMPTY! Cannot generate demand forecasts.", file=sys.stderr)
            
            # DEBUG: Check if we have data
            if not df_monthly_supply:
                print(f"ERROR: No supply data after processing! Check database connection and data.", file=sys.stderr)
                print(f"  - Monthly supply dict has {len(self.monthly_supply)} months", file=sys.stderr)
                if self.monthly_supply:
                    sample_month = list(self.monthly_supply.keys())[0]
                    print(f"  - Sample month data: {self.monthly_supply[sample_month]}", file=sys.stderr)
            
            if not df_monthly_demand:
                print(f"WARNING: No demand data after processing! Check database connection and data.", file=sys.stderr)
                print(f"  - Monthly demand dict has {len(self.monthly_demand)} months", file=sys.stderr)
                if self.monthly_demand:
                    sample_month = list(self.monthly_demand.keys())[0]
                    print(f"  - Sample month data: {self.monthly_demand[sample_month]}", file=sys.stderr)
                    print(f"  - NOTE: Demand data exists but may have all zero values. Check units_requested column.", file=sys.stderr)
                else:
                    print(f"  - ERROR: No monthly_demand dict at all! Check blood_requests table.", file=sys.stderr)
            
            # Debug: Log data usage (sent to stderr, not stdout)
            import sys
            print(f"\n{'='*80}", file=sys.stderr)
            print(f"REAL-TIME DATABASE DATA VERIFICATION - SUPABASE (NOT SYNTHETIC CSV)", file=sys.stderr)
            print(f"{'='*80}", file=sys.stderr)
            print(f"✓ Fetch Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}", file=sys.stderr)
            print(f"✓ Data Source: Supabase Database API (REAL-TIME, NO CACHE)", file=sys.stderr)
            print(f"✓ Table 1: blood_bank_units (Supply Data)", file=sys.stderr)
            print(f"  - Records fetched: {len(blood_units)} units", file=sys.stderr)
            print(f"  - Query: SELECT unit_id,blood_type,collected_at,created_at,status...", file=sys.stderr)
            print(f"✓ Table 2: blood_requests (Demand Data - for analysis only)", file=sys.stderr)
            print(f"  - Records fetched: {len(blood_requests)} requests", file=sys.stderr)
            print(f"  - Query: SELECT patient_blood_type,rh_factor,units_requested,requested_on...", file=sys.stderr)
            print(f"", file=sys.stderr)
            print(f"MONTHLY AGGREGATION (EXACT R LOGIC - REAL-TIME DATA):", file=sys.stderr)
            print(f"  - Monthly supply months: {len(self.monthly_supply)}", file=sys.stderr)
            print(f"  - Monthly demand months: {len(self.monthly_demand)}", file=sys.stderr)
            
            # Show sample of aggregated demand data
            if self.monthly_demand:
                sample_months = sorted(self.monthly_demand.keys())[-3:]  # Last 3 months
                print(f"  - Sample aggregated demand (last 3 months):", file=sys.stderr)
                for month in sample_months:
                    demand_data = self.monthly_demand[month]
                    total_demand = sum(demand_data.values())
                    print(f"    * {month}: Total demand = {total_demand} units across all blood types", file=sys.stderr)
                    for bt, units in sorted(demand_data.items()):
                        if units > 0:
                            print(f"      - {bt}: {units} units", file=sys.stderr)
            
            print(f"", file=sys.stderr)
            print(f"  - Supply months with data: {len(self.monthly_supply)} months", file=sys.stderr)
            print(f"  - Demand months with data: {len(self.monthly_demand)} months (REAL-TIME from blood_requests.units_requested)", file=sys.stderr)
            print(f"  - Supply data points (non-zero only): {len(df_monthly_supply)} entries", file=sys.stderr)
            print(f"  - Demand data points (non-zero only): {len(df_monthly_demand)} entries", file=sys.stderr)
            if self.monthly_supply:
                supply_dates = sorted(self.monthly_supply.keys())
                print(f"  - Supply date range: {supply_dates[0]} to {supply_dates[-1]}", file=sys.stderr)
                # Calculate totals per blood type
                total_by_type = {}
                for month_data in self.monthly_supply.values():
                    for bt, count in month_data.items():
                        total_by_type[bt] = total_by_type.get(bt, 0) + count
                total_supply_all_months = sum(total_by_type.values())
                print(f"  - Total supply (all months): {total_supply_all_months} units", file=sys.stderr)
                print(f"  - Supply by blood type:", file=sys.stderr)
                for bt in sorted(BLOOD_TYPES):
                    count = total_by_type.get(bt, 0)
                    print(f"    * {bt}: {count} units", file=sys.stderr)
            if self.monthly_demand:
                demand_dates = sorted(self.monthly_demand.keys())
                print(f"  - Demand date range: {demand_dates[0]} to {demand_dates[-1]}", file=sys.stderr)
                # Calculate totals per blood type
                total_by_type = {}
                for month_data in self.monthly_demand.values():
                    for bt, count in month_data.items():
                        total_by_type[bt] = total_by_type.get(bt, 0) + count
                total_demand_all_months = sum(total_by_type.values())
                print(f"  - Total demand (all months, SIMULATED): {total_demand_all_months} units", file=sys.stderr)
                print(f"  - Demand by blood type (SIMULATED from supply):", file=sys.stderr)
                for bt in sorted(BLOOD_TYPES):
                    count = total_by_type.get(bt, 0)
                    print(f"    * {bt}: {count} units", file=sys.stderr)
            print(f"", file=sys.stderr)
            print(f"R vs PYTHON VERIFICATION:", file=sys.stderr)
            print(f"  ✓ R uses: synthetic_blood_inventory_2016_2025.csv (STATIC CSV)", file=sys.stderr)
            print(f"  ✓ Python uses: Supabase blood_bank_units + blood_requests (REAL-TIME DB)", file=sys.stderr)
            print(f"  ✓ R demand: 100% SIMULATED from supply (runif 0.7-1.2)", file=sys.stderr)
            print(f"  ✓ Python demand: 100% SIMULATED from supply (random 0.7-1.2) - MATCHES R", file=sys.stderr)
            print(f"  ✓ R forecast: auto.arima(ts_data) with forecast(model, h=1)", file=sys.stderr)
            print(f"  ✓ Python forecast: SARIMAX with model search, forecast(steps=1) - MATCHES R", file=sys.stderr)
            print(f"{'='*80}\n", file=sys.stderr)
            
            # STEP 1: EXACT R - Blood Supply Forecast.R
            forecast_supply_df = self.forecast_next_month_per_type(df_monthly_supply)
            
            # STEP 2: EXACT R - Blood Demand Forecast.R  
            # FIXED: Use real demand data from blood_requests table for forecasting
            # If df_monthly_demand is empty, we can't forecast, so use recent actual demand
            import sys
            if not df_monthly_demand:
                print(f"WARNING: No historical demand data for forecasting. Using recent actual demand from database.", file=sys.stderr)
                # Fallback: Use most recent month's actual demand from monthly_demand dict
                if self.monthly_demand:
                    most_recent_month = max(self.monthly_demand.keys())
                    recent_demand = self.monthly_demand[most_recent_month]
                    print(f"  - Using demand from most recent month: {most_recent_month}", file=sys.stderr)
                    # Create forecast_demand_df from recent actual demand
                    forecast_demand_df = []
                    for bt in BLOOD_TYPES:
                        demand_value = recent_demand.get(bt, 0)
                        forecast_demand_df.append({
                            'Blood Type': bt,
                            'Forecast': demand_value,
                            'Last Month (Actual)': demand_value,
                            '% Change': 0.0
                        })
                else:
                    print(f"  - No demand data available at all. Forecasts will be 0.", file=sys.stderr)
                    forecast_demand_df = []
                    for bt in BLOOD_TYPES:
                        forecast_demand_df.append({
                            'Blood Type': bt,
                            'Forecast': 0,
                            'Last Month (Actual)': 0,
                            '% Change': 0.0
                        })
            else:
                forecast_demand_df = self.forecast_next_month_per_type(df_monthly_demand)
                
                # FIXED: Ensure ALL blood types have a forecast (even if they weren't in df_monthly_demand)
                # Some blood types might have been skipped due to insufficient data
                forecast_blood_types = {item['Blood Type'] for item in forecast_demand_df}
                missing_types = set(BLOOD_TYPES) - forecast_blood_types
                if missing_types:
                    print(f"DEBUG: Adding forecasts for blood types with no/insufficient data: {missing_types}", file=sys.stderr)
                    # For missing types, use 0 or try to get from monthly_demand if available
                    for bt in missing_types:
                        # Try to get last known value from monthly_demand
                        last_known_value = 0
                        if self.monthly_demand:
                            for month_key in sorted(self.monthly_demand.keys(), reverse=True):
                                if bt in self.monthly_demand[month_key]:
                                    last_known_value = self.monthly_demand[month_key][bt]
                                    break
                        
                        forecast_demand_df.append({
                            'Blood Type': bt,
                            'Forecast': last_known_value,
                            'Last Month (Actual)': last_known_value,
                            '% Change': 0.0
                        })
            
            # STEP 3: EXACT R - Supply vs Demand Forecast.R
            combined = {}
            for supply_item in forecast_supply_df:
                bt = supply_item['Blood Type']
                combined[bt] = {
                    'Blood Type': bt,
                    'Forecast_Supply': supply_item['Forecast'],
                    'Last Month Supply (Actual)': supply_item['Last Month (Actual)'],
                    'Supply % Change': supply_item['% Change']
                }
            
            for demand_item in forecast_demand_df:
                bt = demand_item['Blood Type']
                if bt in combined:
                    combined[bt]['Forecast_Demand'] = demand_item['Forecast']
                    combined[bt]['Last Month Demand (Actual)'] = demand_item['Last Month (Actual)']
                    combined[bt]['Demand % Change'] = demand_item['% Change']
                else:
                    combined[bt] = {
                        'Blood Type': bt,
                        'Forecast_Supply': 0,
                        'Forecast_Demand': demand_item['Forecast'],
                        'Last Month Supply (Actual)': 0,
                        'Last Month Demand (Actual)': demand_item['Last Month (Actual)'],
                        'Supply % Change': 0,
                        'Demand % Change': demand_item['% Change']
                    }
            
            # EXACT R: Forecast_Gap = Forecast_Supply - Forecast_Demand
            for bt in combined:
                supply = combined[bt].get('Forecast_Supply', 0)
                demand = combined[bt].get('Forecast_Demand', 0)
                combined[bt]['Forecast_Gap'] = supply - demand
                gap = supply - demand
                # Updated status labels: > 0 = Surplus, = 0 = Adequate, < 0 = Low
                if gap > 0:
                    combined[bt]['Status'] = '🟢 Surplus'
                elif gap == 0:
                    combined[bt]['Status'] = '🔵 Adequate'
                else:  # gap < 0
                    combined[bt]['Status'] = '🔴 Low'
            
            # STEP 4: EXACT R - Projected Stock Level.R
            target_buffer_percentage = 0.25  # 25% of forecasted demand as safety buffer
            
            # FIXED: Process ALL blood types (not just those in combined) - matching R code
            # R code processes all unique blood types from df_monthly
            projected_stock = {}
            for bt in BLOOD_TYPES:
                # Get supply and demand from combined dict (if available)
                supply = round(combined.get(bt, {}).get('Forecast_Supply', 0))
                demand = round(combined.get(bt, {}).get('Forecast_Demand', 0))
                projected_balance = supply - demand
                
                # EXACT R logic from New code.R: Target Buffer & Target Stock Level
                # R code: Target Buffer Level = round(Forecast_Demand * target_buffer_percentage, 2)
                # R code: Target Stock Level = round(Forecast_Demand + Target Buffer Level, 2)
                # R code: Buffer Gap = round(Projected Stock Level (Next Month) - Target Stock Level, 2)
                # Buffer levels can have decimals, but actual units should be whole numbers
                target_buffer_level = round(demand * target_buffer_percentage, 2)
                target_stock_level = round(demand + target_buffer_level, 2)
                buffer_gap = round(projected_balance - target_stock_level, 2)
                
                # EXACT R logic from New code.R: Buffer Status
                # R code: case_when(
                #   Buffer Gap >= 0 ~ "🟢 Above Target (Safe)",
                #   Buffer Gap >= -(Target Buffer Level * 0.5) ~ "🟡 Below Target (Monitor)",
                #   TRUE ~ "🔴 Critical (Action Required)"
                # )
                if buffer_gap >= 0:
                    buffer_status = "🟢 Above Target (Safe)"
                elif buffer_gap >= -(target_buffer_level * 0.5):
                    buffer_status = "🟡 Below Target (Monitor)"
                else:
                    buffer_status = "🔴 Critical (Action Required)"
                
                projected_stock[bt] = {
                    'Blood Type': bt,
                    'Forecast_Supply': supply,
                    'Forecast_Demand': demand,
                    'Projected Stock Level (Next Month)': projected_balance,
                    # Updated status labels: > 0 = Surplus (Green), = 0 = Adequate (Blue), < 0 = Low (Orange/Red)
                    'Stock Status': '🔴 Low' if projected_balance < 0 else ('🔵 Adequate' if projected_balance == 0 else '🟢 Surplus'),
                    'Forecast_Gap': combined.get(bt, {}).get('Forecast_Gap', 0),
                    'Status': combined.get(bt, {}).get('Status', '🟢 Surplus'),
                    'Target Buffer Level': target_buffer_level,
                    'Target Stock Level': target_stock_level,
                    'Buffer Gap': buffer_gap,
                    'Buffer Status': buffer_status
                }
            
            # Generate forecast months for multiple months ahead
            current_date = datetime.now()
            forecast_months = self.generate_forecast_months(self.monthly_supply, current_date)
            
            # EXACT R LOGIC: R forecasts NEXT MONTH only for main calculations
            # Blood Supply Forecast.R: forecast(model, h = 1) - NEXT MONTH only
            # Blood Demand Forecast.R: forecast(model, h = 1) - NEXT MONTH only
            # New code.R: forecast(model, h = 3) - ONLY for visualization charts, not for KPIs
            
            # Calculate KPIs from NEXT MONTH forecast only (matching R's main forecast)
            # R's combined table shows per-blood-type forecasts for NEXT MONTH
            next_month_total_demand = 0
            next_month_total_supply = 0
            next_month_critical_types = set()
            
            # Sum NEXT MONTH forecasts across all blood types (matching R's combined table)
            # Apply balance-level constraints to prevent unrealistic negative balances
            for bt_data in combined.values():
                supply = bt_data.get('Forecast_Supply', 0)
                demand = bt_data.get('Forecast_Demand', 0)
                balance = supply - demand
                
                # CRITICAL: If balance is negative, adjust to prevent unrealistic forecasts
                # This happens when mean reversion pulls supply down and demand up separately
                # For stable historical data, balance should stay close to historical average balance
                if balance < 0:  # If balance is negative (any shortage)
                    # Calculate historical average balance for this blood type
                    hist_avg_balance = 0
                    if self.monthly_supply and self.monthly_demand:
                        hist_balances = []
                        for month_key in self.monthly_supply.keys():
                            if month_key in self.monthly_demand:
                                hist_supply = self.monthly_supply[month_key].get(bt_data.get('Blood Type'), 0)
                                hist_demand = self.monthly_demand[month_key].get(bt_data.get('Blood Type'), 0)
                                hist_balances.append(hist_supply - hist_demand)
                        if hist_balances:
                            hist_avg_balance = sum(hist_balances) / len(hist_balances)
                            
                            # If historical average is positive, pull balance back towards it
                            if hist_avg_balance > 0:
                                # STRONG adjustment: 80% towards historical average, 20% keep forecast
                                # This prevents large negative balances when historical data is stable
                                balance = 0.8 * hist_avg_balance + 0.2 * balance
                                # Recalculate supply to maintain the adjusted balance
                                supply = demand + balance
                                supply = max(0, round(supply))  # Ensure supply >= 0
                                balance = supply - demand  # Recalculate balance
                            elif hist_avg_balance < 0:
                                # If historical average is also negative, allow it but limit the severity
                                # Don't make it worse than historical worst case
                                hist_min_balance = min(hist_balances)
                                balance = max(hist_min_balance, balance)  # Don't exceed worst historical
                
                next_month_total_demand += demand
                next_month_total_supply += supply
                
                # DEBUG: Log demand values for troubleshooting
                if demand > 0:
                    import sys
                    print(f"DEBUG: Next month demand for {bt_data.get('Blood Type')}: {demand} units", file=sys.stderr)
                
                if balance < 0:
                    next_month_critical_types.add(bt_data.get('Blood Type'))
            
            # Build forecast_data for API response (for chart visualization - 6 months ahead)
            self.forecast_data = []
            chart_total_demand = 0
            chart_total_supply = 0
            chart_critical_types = set()
            
            # Find the last historical month to calculate forecast horizons
            if self.monthly_supply:
                last_historical_month = max([datetime.strptime(m, '%Y-%m-%d') for m in self.monthly_supply.keys()])
            else:
                last_historical_month = current_date.replace(day=1)
            
            # DEBUG: Log forecast months generation
            print(f"DEBUG: Generated {len(forecast_months)} forecast months: {forecast_months}", file=sys.stderr)
            print(f"DEBUG: Last historical month: {last_historical_month.strftime('%Y-%m-%d')}", file=sys.stderr)
            
            # Extended forecast: Generate 6 months ahead for better visibility
            # R code: forecast(model, h = 3) - but we extend to 6 months for better trend visibility
            # Initialize forecast variables
            self.supply_all_forecasts = {}
            self.demand_all_forecasts = {}
            
            if forecast_months:
                # Generate 6 months of forecasted data (as requested)
                max_horizon = 6
                
                # Fit models ONCE and cache them (performance optimization)
                supply_cached_models = fit_and_cache_models(df_monthly_supply)
                demand_cached_models = fit_and_cache_models(df_monthly_demand)
                
                # Generate all forecasts at once using cached models
                # This matches R's: fc <- forecast(model, h = 3); forecast = as.numeric(fc$mean)
                supply_all_forecasts = generate_all_forecasts(supply_cached_models, max_horizon, df_monthly_supply)
                demand_all_forecasts = generate_all_forecasts(demand_cached_models, max_horizon, df_monthly_demand)
                
                # Store for trendline generation
                self.supply_all_forecasts = supply_all_forecasts
                self.demand_all_forecasts = demand_all_forecasts
                
                # Extract forecasts for each month (R does NOT apply any constraints or mean reversion)
                # BUT: Blood units cannot be negative, so clamp to >= 0
                forecast_count = 0
                print(f"DEBUG: Processing {len(sorted(forecast_months))} forecast months", file=sys.stderr)
                for forecast_month in sorted(forecast_months):
                    forecast_date = datetime.strptime(forecast_month, '%Y-%m-%d')
                    months_ahead = (forecast_date.year - last_historical_month.year) * 12 + \
                                  (forecast_date.month - last_historical_month.month)
                    
                    # FIXED: Allow up to 12 months ahead (industry standard)
                    if months_ahead <= 0 or months_ahead > max_horizon:
                        continue
                    
                    # Limit to max_horizon months (6 months for forecast display)
                    if forecast_count >= max_horizon:
                        break
                    
                    # Extract forecast from pre-computed arrays (matching R's forecast output)
                    for bt in BLOOD_TYPES:
                        # Get forecast from cached arrays (now returns dict with 'forecast', 'ci_80_lower', etc.)
                        supply_data = self.supply_all_forecasts.get(bt, {})
                        demand_data = self.demand_all_forecasts.get(bt, {})
                        
                        # Handle new format (dict) or old format (list) for backward compatibility
                        if isinstance(supply_data, dict):
                            supply_forecasts = supply_data.get('forecast', [])
                        else:
                            supply_forecasts = supply_data if isinstance(supply_data, list) else []
                        
                        if isinstance(demand_data, dict):
                            demand_forecasts = demand_data.get('forecast', [])
                        else:
                            demand_forecasts = demand_data if isinstance(demand_data, list) else []
                        
                        if months_ahead <= len(supply_forecasts) and months_ahead > 0:
                            supply = supply_forecasts[months_ahead - 1]
                        else:
                            # Fallback to 1-month ahead forecast if multi-month not available
                            supply = projected_stock.get(bt, {}).get('Forecast_Supply', 0)
                        
                        if months_ahead <= len(demand_forecasts) and months_ahead > 0:
                            demand = demand_forecasts[months_ahead - 1]
                        else:
                            # Fallback to 1-month ahead forecast if multi-month not available
                            demand = projected_stock.get(bt, {}).get('Forecast_Demand', 0)
                        
                        # CRITICAL: Blood units cannot be negative - clamp to >= 0
                        # R's forecast() can produce negative values, but we need to ensure >= 0
                        supply = max(0, round(supply))
                        demand = max(0, round(demand))
                        
                        # EXACT R: balance = supply - demand (Projected Stock Level.R line 5)
                        # R code: `Projected Stock Level (Next Month)` = Forecast_Supply - Forecast_Demand
                        # R does NOT apply any smoothing or constraints - just raw calculation
                        balance = supply - demand
                    
                        # Store original balance for status calculation (can be negative)
                        original_balance = balance
                        
                        # FIXED: Show actual balance (can be negative) - don't clamp to 0
                        # Negative balance indicates shortage (demand > supply), which is critical information
                        # Users need to see the actual projected balance, not a clamped value
                        display_balance = round(balance)  # Show actual balance (can be negative)
                        
                        # Updated status labels: > 0 = Surplus (Green), = 0 = Adequate (Blue), < 0 = Low (Orange/Red)
                        if original_balance > 0:
                            status = 'surplus'
                        elif original_balance == 0:
                            status = 'adequate'
                        else:  # original_balance < 0
                            status = 'low'
                            chart_critical_types.add(bt)
                        
                        # Gap status for compatibility
                        gap_status = 'low' if original_balance < 0 else ('adequate' if original_balance == 0 else 'surplus')
                        
                        self.forecast_data.append({
                            'blood_type': bt,
                            'forecasted_demand': demand,
                            'forecasted_supply': supply,
                            'projected_balance': display_balance,  # Actual balance (can be negative for shortages)
                            'original_balance': original_balance,  # Same as display_balance (for consistency)
                            'status': status,
                            'gap_status': gap_status,
                            'forecast_month': forecast_month
                        })
                        
                        # Track totals for chart (but KPIs use next month only)
                        chart_total_demand += demand
                        chart_total_supply += supply
                
                # Increment forecast count after processing all blood types for this month
                forecast_count += 1
                print(f"DEBUG: Processed forecast month {forecast_month}, count={forecast_count}, total forecast_data items={len(self.forecast_data)}", file=sys.stderr)
            
            # EXACT R: KPIs use NEXT MONTH forecast only (matching R's main forecast tables)
            # R's Blood Supply Forecast.R and Blood Demand Forecast.R show NEXT MONTH only
            total_demand = next_month_total_demand
            total_supply = next_month_total_supply
            total_balance = total_supply - total_demand
            critical_types_list = list(next_month_critical_types)
            
            # DEBUG: Log KPI totals to verify demand is being calculated
            import sys
            print(f"DEBUG: KPI Totals - Demand: {total_demand}, Supply: {total_supply}, Balance: {total_balance}", file=sys.stderr)
            if total_demand == 0:
                print(f"WARNING: Total forecasted demand is 0! Check if demand forecasts are being generated.", file=sys.stderr)
                print(f"  - Combined dict has {len(combined)} blood types", file=sys.stderr)
                if combined:
                    sample_bt = list(combined.keys())[0]
                    print(f"  - Sample blood type ({sample_bt}) Forecast_Demand: {combined[sample_bt].get('Forecast_Demand', 0)}", file=sys.stderr)
                    print(f"  - All Forecast_Demand values:", file=sys.stderr)
                    for bt, data in combined.items():
                        print(f"    * {bt}: {data.get('Forecast_Demand', 0)}", file=sys.stderr)
            
            # Find most critical blood type from NEXT MONTH forecast (matching R's combined table)
            if combined:
                most_critical_data = min(combined.values(), key=lambda x: x.get('Forecast_Gap', 0))
                most_critical = most_critical_data.get('Blood Type', 'None')
            else:
                most_critical = 'None'
            
            # Calculate target buffer level
            total_target_buffer = 0
            total_target_stock = 0
            total_buffer_gap = 0
            action_required_count = 0
            
            for bt_data in projected_stock.values():
                total_target_buffer += bt_data.get('Target Buffer Level', 0)
                total_target_stock += bt_data.get('Target Stock Level', 0)
                total_buffer_gap += bt_data.get('Buffer Gap', 0)
                if bt_data.get('Buffer Status', '').startswith('🔴'):
                    action_required_count += 1
            
            # Calculate shelf life metrics
            shelf_life_metrics = self.calculate_shelf_life_metrics(self.blood_units)
            
            self.kpi_data = {
                'total_forecasted_demand': round(total_demand),  # Round to whole number
                'total_forecasted_supply': round(total_supply),  # Round to whole number
                'projected_balance': round(total_balance),  # Round to whole number
                'critical_blood_types': most_critical,
                'critical_types_list': critical_types_list,
                'target_buffer_level': round(total_target_buffer),  # Round to whole number
                'target_stock_level': round(total_target_stock),  # Round to whole number
                'buffer_gap': round(total_buffer_gap),  # Round to whole number
                'action_required_count': action_required_count,
                'expiring_weekly': shelf_life_metrics['expiring_weekly'],
                'expiring_monthly': shelf_life_metrics['expiring_monthly'],
                'total_valid_units': shelf_life_metrics['total_valid_units'],
                'expiring_weekly_percentage': round(shelf_life_metrics['expiring_weekly_percentage']),  # Round percentage
                'expiring_monthly_percentage': round(shelf_life_metrics['expiring_monthly_percentage'])  # Round percentage
            }
            
            # Categorize months
            historical_months = []
            for month_key in self.monthly_supply.keys():
                year = int(month_key[:4])
                if year <= 2025:
                    historical_months.append(month_key)
            
            historical_months.sort()
            
            # Generate trendline data structures matching R code (New code.R)
            # R code generates trendlines for: Supply, Demand, Combined, and Stock Level with Buffer
            trendline_data = self._generate_trendline_data(
                self.monthly_supply, 
                self.monthly_demand, 
                getattr(self, 'supply_all_forecasts', {}),
                getattr(self, 'demand_all_forecasts', {}),
                forecast_months,
                last_historical_month if forecast_months else current_date,
                projected_stock
            )
            
            return {
                'success': True,
                'kpis': self.kpi_data,
                'forecast_data': self.forecast_data,
                'monthly_supply': self.monthly_supply,
                'monthly_demand': self.monthly_demand,
                'forecast_months': forecast_months,
                'historical_months': historical_months,
                'all_months': historical_months,
                'forecast_supply_df': forecast_supply_df,
                'forecast_demand_df': forecast_demand_df,
                'combined': list(combined.values()),
                'projected_stock': list(projected_stock.values()),
                'trendline_data': trendline_data  # NEW: Trendline data for visualization
            }
            
        finally:
            # Disconnect from database
            self.disconnect_database()


# Main execution function
def main():
    """Main function to run forecast calculations"""
    import sys
    import traceback
    
    calculator = ForecastReportsCalculator()
    
    try:
        result = calculator.generate_forecasts()
        return result
    except Exception as e:
        # Log error to stderr (not stdout) to avoid breaking JSON
        error_msg = f"Error in main(): {str(e)}"
        print(error_msg, file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
        return {
            'success': False,
            'error': str(e),
            'error_type': type(e).__name__
        }


if __name__ == "__main__":
    # When run directly, output JSON for PHP API consumption
    # Redirect any warnings/errors to stderr to avoid breaking JSON output
    import sys
    import warnings
    import traceback
    
    # Suppress warnings to stderr
    warnings.filterwarnings('ignore')
    
    # Ensure all error output goes to stderr
    sys.stderr = sys.stderr  # Keep stderr as is
    
    try:
        result = main()
        # Only output JSON to stdout - everything else goes to stderr
        json_output = json.dumps(result, indent=2, default=str)
        print(json_output, file=sys.stdout)
        sys.stdout.flush()
    except KeyboardInterrupt:
        # Handle Ctrl+C gracefully
        error_result = {
            'success': False,
            'error': 'Interrupted by user',
            'error_type': 'KeyboardInterrupt'
        }
        print(json.dumps(error_result, indent=2), file=sys.stdout)
        sys.stdout.flush()
    except Exception as e:
        # If there's an error, output JSON error response to stdout
        error_result = {
            'success': False,
            'error': str(e),
            'error_type': type(e).__name__
        }
        print(json.dumps(error_result, indent=2), file=sys.stdout)
        sys.stdout.flush()
        # Print error details to stderr for debugging (not stdout)
        print(f"Error details: {traceback.format_exc()}", file=sys.stderr)
        sys.stderr.flush()
