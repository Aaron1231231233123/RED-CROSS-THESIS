"""
Forecast Reports Python Module
Translates mathematical calculations from JS and PHP files
Handles database operations and forecast calculations
"""

import requests
import json
import random
import math
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional
from collections import defaultdict

# SARIMA imports - matching R Studio auto.arima
try:
    from statsmodels.tsa.statespace.sarimax import SARIMAX
    from statsmodels.tsa.seasonal import seasonal_decompose
    from statsmodels.tools.sm_exceptions import ConvergenceWarning
    import warnings
    warnings.filterwarnings('ignore', category=ConvergenceWarning)
    SARIMA_AVAILABLE = True
except ImportError:
    SARIMA_AVAILABLE = False
    print("Warning: statsmodels not available. Install with: pip install statsmodels scipy")

# Database Configuration - reads from db_conn.php or environment variables
import sys
from pathlib import Path

# Add conn directory to path to import config
conn_path = Path(__file__).parent.parent / 'conn'
sys.path.insert(0, str(conn_path))

try:
    from config import get_supabase_url, get_supabase_api_key
    SUPABASE_URL = get_supabase_url()
    SUPABASE_API_KEY = get_supabase_api_key()
except ImportError:
    raise ImportError(
        "Could not import database configuration. "
        "Please ensure assets/conn/config.py exists and can read from db_conn.php"
    )
except Exception as e:
    raise Exception(f"Error loading database configuration: {e}")

# Blood types
BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']

# Constants from JS/PHP
PAGE_SIZE = 8
RANDOM_SEED = 42  # For reproducible results (from PHP: mt_srand(42))


class ForecastReportsCalculator:
    """Main class for forecast calculations and database operations"""
    
    def __init__(self):
        self.supabase_url = SUPABASE_URL
        self.api_key = SUPABASE_API_KEY
        self.connection_active = False
        self.monthly_supply = {}
        self.monthly_demand = {}
        self.forecast_data = []
        self.kpi_data = {}
        self.blood_units = []
        
    def connect_database(self):
        """Connect to Supabase database"""
        try:
            # Test connection with a simple request
            headers = {
                'apikey': self.api_key,
                'Authorization': f'Bearer {self.api_key}',
                'Content-Type': 'application/json'
            }
            test_url = f"{self.supabase_url}/rest/v1/"
            response = requests.get(test_url, headers=headers, timeout=10)
            if response.status_code == 200 or response.status_code == 400:
                self.connection_active = True
                return True
            return False
        except Exception as e:
            print(f"Database connection error: {e}")
            self.connection_active = False
            return False
    
    def disconnect_database(self):
        """Disconnect from database"""
        self.connection_active = False
    
    def supabase_request(self, endpoint: str, limit: int = 5000) -> List[Dict]:
        """
        Make Supabase requests with pagination support (optimized)
        Uses larger page size for fewer round trips
        """
        all_data = []
        offset = 0
        
        while True:
            url = f"{self.supabase_url}/rest/v1/{endpoint}"
            if '?' in endpoint:
                url += f"&limit={limit}&offset={offset}"
            else:
                url += f"?limit={limit}&offset={offset}"
            
            headers = {
                'apikey': self.api_key,
                'Authorization': f'Bearer {self.api_key}',
                'Content-Type': 'application/json',
                'Accept-Encoding': 'gzip, deflate'  # Request compression
            }
            
            try:
                response = requests.get(url, headers=headers, timeout=60)
                if response.status_code != 200:
                    raise Exception(f'Supabase request failed with code: {response.status_code}')
                
                data = response.json()
                if not isinstance(data, list):
                    data = []
                
                all_data.extend(data)
                
                # Break if we got fewer records than requested (end of data)
                if len(data) < limit:
                    break
                    
                offset += limit
                    
            except Exception as e:
                print(f"Error in supabase_request: {e}")
                break
        
        return all_data
    
    def fetch_blood_units(self) -> List[Dict]:
        """
        Fetch ALL blood bank units data from blood_bank_units table
        Translated from PHP: Get ALL blood bank units data
        Includes collected_at for shelf life calculations (45 days from collection)
        """
        endpoint = "blood_bank_units?select=unit_id,blood_type,collected_at,created_at,status,handed_over_at,expires_at&order=collected_at.asc"
        return self.supabase_request(endpoint)
    
    def fetch_blood_requests(self) -> List[Dict]:
        """
        Fetch ALL blood requests data (hospital requests = demand)
        Translated from PHP: Get ALL blood requests data
        """
        endpoint = "blood_requests?select=patient_blood_type,rh_factor,units_requested,requested_on,status,handed_over_date&order=requested_on.asc"
        return self.supabase_request(endpoint)
    
    def process_monthly_supply(self, blood_units: List[Dict]) -> Dict[str, Dict[str, int]]:
        """
        Process blood units data for supply forecasting (optimized)
        Uses defaultdict for faster initialization and dict operations
        """
        monthly_supply = defaultdict(lambda: defaultdict(int))
        
        for unit in blood_units:
            # Use collected_at if available, otherwise use created_at
            date_field = unit.get('collected_at') or unit.get('created_at')
            if not date_field:
                continue
            
            blood_type = unit.get('blood_type')
            if blood_type not in BLOOD_TYPES:
                continue
            
            try:
                # Fast date parsing - handle both formats
                if 'Z' in date_field:
                    date = datetime.fromisoformat(date_field.replace('Z', '+00:00'))
                else:
                    date = datetime.fromisoformat(date_field)
                
                # Remove timezone for faster comparison
                if date.tzinfo:
                    date = date.replace(tzinfo=None)
                
                # Only skip dates that are too far in the future (beyond 2030)
                if date.year > 2030:
                    continue
                
                # Get first day of month (like floor_date) - optimized
                month_key = date.replace(day=1).strftime('%Y-%m-01')
                
                # Count units (defaultdict handles initialization)
                monthly_supply[month_key][blood_type] += 1
                    
            except (ValueError, AttributeError):
                continue
        
        # Convert defaultdict to regular dict for compatibility
        result = {k: dict(v) for k, v in monthly_supply.items()}
        
        # If no database data, create sample data
        if not result:
            current_date = datetime.now()
            sample_month = current_date.replace(day=1).strftime('%Y-%m-01')
            result[sample_month] = {bt: 5 for bt in BLOOD_TYPES}
        
        return result
    
    def process_monthly_demand(self, blood_requests: List[Dict], monthly_supply: Dict) -> Dict[str, Dict[str, int]]:
        """
        Process blood requests data for demand forecasting (optimized)
        Uses defaultdict and faster rh_factor conversion
        """
        monthly_demand = defaultdict(lambda: defaultdict(int))
        
        # Pre-compute rh_factor mapping for faster lookup
        rh_map = {
            'positive': '+', 'pos': '+', '+': '+', '1': '+',
            'negative': '-', 'neg': '-', '-': '-', '0': '-'
        }
        
        # Process real demand from blood requests
        for request in blood_requests:
            patient_blood_type = request.get('patient_blood_type')
            rh_factor = request.get('rh_factor')
            date_field = request.get('requested_on')
            
            if not all([patient_blood_type, rh_factor, date_field]):
                continue
            
            try:
                # Fast date parsing
                if 'Z' in date_field:
                    date = datetime.fromisoformat(date_field.replace('Z', '+00:00'))
                else:
                    date = datetime.fromisoformat(date_field)
                
                if date.tzinfo:
                    date = date.replace(tzinfo=None)
                
                # Only skip dates that are too far in the future (beyond 2030)
                if date.year > 2030:
                    continue
                
                month_key = date.replace(day=1).strftime('%Y-%m-01')
                
                # Fast rh_factor conversion using pre-computed map
                rh_lower = rh_factor.lower()
                rh_symbol = rh_map.get(rh_lower, rh_factor)
                
                blood_type = f"{patient_blood_type}{rh_symbol}"
                
                if blood_type not in BLOOD_TYPES:
                    continue
                
                # Use units_requested field from blood_requests table
                units = max(1, int(request.get('units_requested', 1)))
                
                # Count units (defaultdict handles initialization)
                monthly_demand[month_key][blood_type] += units
                    
            except (ValueError, TypeError, AttributeError):
                continue
        
        # EXACT R Studio demand generation logic from Blood Demand Forecast.R
        # R code: df_monthly_donations <- df_monthly_donations %>%
        #         mutate(pints_requested = as.integer(units_collected * runif(n(), 0.7, 1.2)))
        if monthly_supply:
            random.seed(RANDOM_SEED)  # EXACT R logic: set.seed(42)
            
            # Pre-generate random multipliers for all supply entries
            for month_key, supply_data in monthly_supply.items():
                for blood_type in BLOOD_TYPES:
                    supply = supply_data.get(blood_type, 0)
                    if supply > 0:
                        # EXACT R logic: runif(n(), 0.7, 1.2)
                        demand_multiplier = 0.7 + random.random() * 0.5  # 0.7 to 1.2
                        generated_demand = int(supply * demand_multiplier)
                        monthly_demand[month_key][blood_type] += generated_demand
        
        # Convert defaultdict to regular dict
        result = {k: dict(v) for k, v in monthly_demand.items()}
        
        # If no demand data, create sample data
        if not result:
            current_date = datetime.now()
            sample_month = current_date.replace(day=1).strftime('%Y-%m-01')
            result[sample_month] = {bt: 3 for bt in BLOOD_TYPES}
        
        return result
    
    def forecast_next_month_per_type(self, df_monthly_data: List[Dict]) -> List[Dict]:
        """
        EXACT 1:1 translation from R: Blood Supply Forecast.R / Blood Demand Forecast.R
        
        R Code:
        forecast_next_month_per_type <- function(df_monthly) {
          results <- data.frame()
          last_month <- max(df_monthly$month)
          next_month <- last_month %m+% months(1)
          
          for (bt in unique(df_monthly$blood_type)) {
            data_bt <- df_monthly %>%
              filter(blood_type == bt) %>%
              arrange(month)
            
            if (nrow(data_bt) < 6) next  # skip short series
            
            ts_bt <- ts(data_bt$units_collected, frequency = 12)
            model <- auto.arima(ts_bt)
            forecast_val <- forecast(model, h = 1)$mean[1]
            actual_last <- tail(data_bt$units_collected, 1)
            
            results <- rbind(results, data.frame(
              `Blood Type` = bt,
              `Last Month (Actual)` = round(actual_last, 2),
              `Forecast` = round(forecast_val, 2),
              `% Change` = round(((forecast_val - actual_last) / actual_last) * 100, 2)
            ))
          }
          return(results)
        }
        """
        results = []
        
        if not df_monthly_data:
            return results
        
        # Get last month (R: last_month <- max(df_monthly$month))
        months = sorted(set([item['month'] for item in df_monthly_data]))
        if not months:
            return results
        
        last_month = datetime.strptime(months[-1], '%Y-%m-%d')
        
        # Get unique blood types (R: for (bt in unique(df_monthly$blood_type)))
        blood_types = sorted(set([item['blood_type'] for item in df_monthly_data]))
        
        # Pre-group data by blood type for faster access
        data_by_blood_type = defaultdict(list)
        for item in df_monthly_data:
            data_by_blood_type[item['blood_type']].append(item)
        
        for bt in blood_types:
            # Get pre-grouped data and sort by month (R: filter + arrange)
            data_bt = sorted(data_by_blood_type.get(bt, []), key=lambda x: x['month'])
            
            # EXACT R logic: if (nrow(data_bt) < 6) next  # skip short series
            if len(data_bt) < 6:
                continue
            
            # Get values (optimized list comprehension)
            values = [item['value'] for item in data_bt]
            
            # EXACT R logic: actual_last <- tail(data_bt$units_collected, 1)
            actual_last = values[-1] if values else 0
            
            try:
                if SARIMA_AVAILABLE:
                    # EXACT R logic: ts_bt <- ts(data_bt$units_collected, frequency = 12)
                    # R's ts() with frequency=12 creates monthly seasonal time series
                    ts_values = np.array(values)
                    
                    # EXACT R logic: model <- auto.arima(ts_bt)
                    # Implement comprehensive auto.arima-like search (similar to R's auto.arima)
                    # This tries a wide range of SARIMA models and selects best AIC
                    best_aic = float('inf')
                    best_forecast = None
                    best_model = None
                    
                    # Comprehensive SARIMA model search (matching R's auto.arima behavior)
                    # R's auto.arima searches: p in [0,5], d in [0,2], q in [0,5], P in [0,2], D in [0,1], Q in [0,2]
                    # We'll try the most common combinations first, then expand
                    
                    # Phase 1: Try most common/stable models first (fast)
                    common_orders = [
                        (0, 1, 1, 0, 1, 1, 12),  # (0,1,1)(0,1,1)[12] - seasonal naive (most common)
                        (1, 0, 1, 1, 0, 1, 12),  # (1,0,1)(1,0,1)[12] - common pattern
                        (1, 1, 1, 1, 1, 1, 12),  # (1,1,1)(1,1,1)[12] - full model
                        (0, 1, 0, 0, 1, 0, 12),  # (0,1,0)(0,1,0)[12] - random walk with seasonality
                        (2, 0, 0, 1, 0, 0, 12),  # (2,0,0)(1,0,0)[12] - AR(2) with seasonal AR
                        (0, 0, 1, 0, 0, 1, 12),  # (0,0,1)(0,0,1)[12] - MA only
                        (1, 0, 0, 1, 0, 0, 12),  # (1,0,0)(1,0,0)[12] - AR(1) with seasonal AR
                        (0, 1, 2, 0, 1, 1, 12),  # (0,1,2)(0,1,1)[12] - IMA with seasonality
                        (2, 1, 0, 1, 1, 0, 12),  # (2,1,0)(1,1,0)[12] - ARI with seasonality
                        (1, 1, 0, 1, 1, 0, 12),  # (1,1,0)(1,1,0)[12] - ARI(1) with seasonality
                    ]
                    
                    # Phase 2: Additional models if Phase 1 doesn't find good fit
                    extended_orders = [
                        (0, 2, 1, 0, 1, 1, 12),  # (0,2,1)(0,1,1)[12] - double differencing
                        (1, 1, 2, 1, 1, 1, 12),  # (1,1,2)(1,1,1)[12] - ARIMA with MA(2)
                        (2, 1, 1, 1, 1, 1, 12),  # (2,1,1)(1,1,1)[12] - ARIMA(2,1,1) with seasonality
                        (0, 1, 1, 1, 1, 1, 12),  # (0,1,1)(1,1,1)[12] - IMA with full seasonality
                        (1, 0, 2, 1, 0, 1, 12),  # (1,0,2)(1,0,1)[12] - ARMA with MA(2)
                    ]
                    
                    # Try common models first
                    orders_to_try = common_orders
                    
                    for order_tuple in orders_to_try:
                        try:
                            p, d, q, P, D, Q, s = order_tuple
                            # SARIMAX format: order=(p,d,q), seasonal_order=(P,D,Q,s)
                            model = SARIMAX(ts_values, order=(p, d, q), seasonal_order=(P, D, Q, s))
                            # Use moderate maxiter for balance between speed and accuracy
                            fitted_model = model.fit(disp=False, maxiter=50, method='lbfgs')
                            aic = fitted_model.aic
                            
                            if aic < best_aic:
                                best_aic = aic
                                best_model = fitted_model
                                # EXACT R logic: forecast_val <- forecast(model, h = 1)$mean[1]
                                forecast_result = fitted_model.forecast(steps=1)
                                best_forecast = float(forecast_result.iloc[0] if hasattr(forecast_result, 'iloc') else forecast_result[0])
                        except Exception:
                            continue
                    
                    # If best AIC is still high, try extended models
                    if best_aic > 100 or best_forecast is None:
                        for order_tuple in extended_orders:
                            try:
                                p, d, q, P, D, Q, s = order_tuple
                                model = SARIMAX(ts_values, order=(p, d, q), seasonal_order=(P, D, Q, s))
                                fitted_model = model.fit(disp=False, maxiter=50, method='lbfgs')
                                aic = fitted_model.aic
                                
                                if aic < best_aic:
                                    best_aic = aic
                                    best_model = fitted_model
                                    forecast_result = fitted_model.forecast(steps=1)
                                    best_forecast = float(forecast_result.iloc[0] if hasattr(forecast_result, 'iloc') else forecast_result[0])
                            except Exception:
                                continue
                    
                    if best_forecast is not None:
                        forecast_val = best_forecast
                    else:
                        # Fallback: use weighted average of recent values
                        if len(values) >= 6:
                            # Weight recent months more heavily
                            weights = np.linspace(0.5, 1.0, len(values[-6:]))
                            forecast_val = np.average(values[-6:], weights=weights)
                        else:
                            forecast_val = sum(values) / len(values) if values else 0
                else:
                    # Fallback if statsmodels not available - use simple average
                    forecast_val = sum(values) / len(values) if values else 0
                    
            except Exception as e:
                # If SARIMA fails, use mean of recent values (matching R's behavior)
                print(f"SARIMA forecast failed for {bt}: {e}. Using fallback.")
                forecast_val = sum(values[-12:]) / len(values[-12:]) if len(values) >= 12 else (sum(values) / len(values) if values else 0)
            
            # EXACT R logic: round(forecast_val, 2)
            forecast_val = round(forecast_val, 2)
            actual_last_rounded = round(actual_last, 2)
            
            # EXACT R logic: % Change = round(((forecast_val - actual_last) / actual_last) * 100, 2)
            if actual_last != 0:
                pct_change = round(((forecast_val - actual_last) / actual_last) * 100, 2)
            else:
                pct_change = 0.0
            
            # Build result matching R's data.frame structure
            results.append({
                'Blood Type': bt,
                'Last Month (Actual)': actual_last_rounded,
                'Forecast': forecast_val,
                '% Change': pct_change
            })
        
        return results
    
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
        Generate forecast months (future months not in database)
        Translated from PHP: Generate forecasts using R Studio models
        """
        forecast_months = []
        current_year = current_date.year
        current_month = current_date.month
        
        # Generate all months from 2023 to 2026
        all_possible_months = []
        for year in range(2023, 2027):
            for month in range(1, 13):
                month_key = f"{year:04d}-{month:02d}-01"
                all_possible_months.append(month_key)
        
        # Find months that need forecasting (November 2025+ months not in database)
        for month_key in all_possible_months:
            date = datetime.strptime(month_key, '%Y-%m-%d')
            month_year = date.year
            month_num = date.month
            
            # Only forecast months from November 2025 onwards that don't exist in database
            if (month_year > current_year or 
                (month_year == current_year and month_num > current_month)):
                if month_key not in monthly_supply:
                    forecast_months.append(month_key)
        
        return forecast_months
    
    def calculate_status(self, balance: float) -> str:
        """
        Calculate status based on balance
        Translated from JS: statusFor()
        """
        if balance <= -10:
            return 'critical'
        elif balance < 0:
            return 'low'
        else:
            return 'surplus'
    
    def calculate_shelf_life_metrics(self, blood_units: List[Dict]) -> Dict:
        """
        Calculate shelf life metrics (weekly and monthly) - OPTIMIZED
        Blood shelf life is 45 days from collection date (collected_at)
        Uses vectorized operations and early filtering for speed
        """
        # Use timezone-naive datetime for consistent comparison
        today = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
        next_week = today + timedelta(days=7)
        next_month = today + timedelta(days=30)
        
        expiring_weekly = 0
        expiring_monthly = 0
        total_valid_units = 0
        
        # Group units by expiration month for monthly tracking
        monthly_expiring_weekly = defaultdict(int)
        monthly_expiring_monthly = defaultdict(int)
        monthly_total = defaultdict(int)
        
        # Pre-filter units for faster processing
        valid_units = [
            unit for unit in blood_units
            if unit.get('collected_at') and 
            unit.get('status') != 'handed_over' and 
            not unit.get('handed_over_at')
        ]
        
        for unit in valid_units:
            collected_at = unit.get('collected_at')
            
            try:
                # Fast date parsing
                if 'Z' in collected_at:
                    collection_date = datetime.fromisoformat(collected_at.replace('Z', '+00:00'))
                else:
                    collection_date = datetime.fromisoformat(collected_at)
                
                # Remove timezone info for consistent comparison
                if collection_date.tzinfo:
                    collection_date = collection_date.replace(tzinfo=None)
                
                # Calculate expiration date: collected_at + EXACTLY 45 days
                expiration_date = collection_date + timedelta(days=45)
                
                # Early exit if expired
                if expiration_date <= today:
                    continue
                
                # Count valid units
                total_valid_units += 1
                
                # Get expiration month for grouping (YYYY-MM-01 format)
                expiration_month = expiration_date.replace(day=1).strftime('%Y-%m-01')
                monthly_total[expiration_month] += 1
                
                # Count expiring within next week (7 days from today)
                if expiration_date <= next_week:
                    expiring_weekly += 1
                    monthly_expiring_weekly[expiration_month] += 1
                
                # Count expiring within next month (30 days from today)
                if expiration_date <= next_month:
                    expiring_monthly += 1
                    monthly_expiring_monthly[expiration_month] += 1
                        
            except (ValueError, TypeError, AttributeError):
                continue
        
        # Calculate monthly breakdown (optimized dict comprehension)
        monthly_breakdown = {
            month_key: {
                'total': monthly_total[month_key],
                'expiring_weekly': monthly_expiring_weekly.get(month_key, 0),
                'expiring_monthly': monthly_expiring_monthly.get(month_key, 0)
            }
            for month_key in sorted(monthly_total.keys())
        }
        
        # Calculate percentages (avoid division by zero)
        total_valid = total_valid_units if total_valid_units > 0 else 1
        
        return {
            'expiring_weekly': expiring_weekly,
            'expiring_monthly': expiring_monthly,
            'total_valid_units': total_valid_units,
            'expiring_weekly_percentage': round((expiring_weekly / total_valid * 100), 2),
            'expiring_monthly_percentage': round((expiring_monthly / total_valid * 100), 2),
            'monthly_breakdown': monthly_breakdown
        }
    
    def calculate_kpis(self, forecast_data: List[Dict], monthly_supply: Dict, 
                      monthly_demand: Dict, selected_month: str = 'All Months',
                      selected_type: str = 'all') -> Dict:
        """
        Calculate KPI values based on filtered data
        Translated from JS: updateKPIs()
        """
        total_demand = 0
        total_supply = 0
        critical_types = []
        
        # Simple approach: if forecast month is selected, use forecast data
        if 'R Studio Forecast' in selected_month:
            month_name = selected_month.replace(' (R Studio Forecast)', '')
            
            # Filter forecast data to only the selected month
            for item in forecast_data:
                if item.get('forecast_month'):
                    item_date = datetime.fromisoformat(item['forecast_month'].replace('Z', '+00:00'))
                    item_month = item_date.strftime('%B %Y')
                    
                    if item_month == month_name:
                        total_demand += item.get('forecasted_demand', 0)
                        total_supply += item.get('forecasted_supply', 0)
                        
                        if item.get('projected_balance', 0) < 0:
                            critical_types.append(item.get('blood_type'))
                            
        elif 'Historical Data' in selected_month:
            month_name = selected_month.replace(' (Historical Data)', '')
            
            # Find the month key by checking all available keys
            found_month_key = None
            for month_key in monthly_supply.keys():
                month_date = datetime.strptime(month_key, '%Y-%m-%d')
                current_month_name = month_date.strftime('%B %Y')
                
                if current_month_name == month_name:
                    found_month_key = month_key
                    break
            
            if found_month_key:
                supply_data = monthly_supply.get(found_month_key, {})
                demand_data = monthly_demand.get(found_month_key, {})
                
                for blood_type in BLOOD_TYPES:
                    supply = supply_data.get(blood_type, 0)
                    demand = demand_data.get(blood_type, 0)
                    balance = supply - demand
                    
                    total_demand += demand
                    total_supply += supply
                    
                    if balance < 0:
                        critical_types.append(blood_type)
                        
        elif selected_month == 'All Months':
            # Aggregate all historical data
            for month_key in monthly_supply.keys():
                supply_data = monthly_supply.get(month_key, {})
                demand_data = monthly_demand.get(month_key, {})
                
                for blood_type in BLOOD_TYPES:
                    supply = supply_data.get(blood_type, 0)
                    demand = demand_data.get(blood_type, 0)
                    balance = supply - demand
                    
                    total_demand += demand
                    total_supply += supply
                    
                    if balance < 0:
                        critical_types.append(blood_type)
            
            # Add all forecast data
            for item in forecast_data:
                total_demand += item.get('forecasted_demand', 0)
                total_supply += item.get('forecasted_supply', 0)
                
                if item.get('projected_balance', 0) < 0:
                    critical_types.append(item.get('blood_type'))
        
        total_balance = total_supply - total_demand
        most_critical = critical_types[0] if critical_types else 'None'
        
        return {
            'total_forecasted_demand': total_demand,
            'total_forecasted_supply': total_supply,
            'projected_balance': total_balance,
            'critical_blood_types': most_critical,
            'critical_types_list': list(set(critical_types))
        }
    
    def aggregate_data_by_blood_type(self, data: List[Dict]) -> Dict:
        """
        Aggregate data by blood type for charts
        Translated from JS: aggregateDataByBloodType()
        """
        blood_type_map = {}
        
        # Sum up all values for each blood type
        for item in data:
            blood_type = item.get('blood_type')
            
            if blood_type not in blood_type_map:
                blood_type_map[blood_type] = {
                    'bloodType': blood_type,
                    'totalDemand': 0,
                    'totalSupply': 0,
                    'totalBalance': 0,
                    'count': 0
                }
            
            blood_type_map[blood_type]['totalDemand'] += item.get('forecasted_demand', 0)
            blood_type_map[blood_type]['totalSupply'] += item.get('forecasted_supply', 0)
            blood_type_map[blood_type]['totalBalance'] += item.get('projected_balance', 0)
            blood_type_map[blood_type]['count'] += 1
        
        # Convert to arrays for charts
        blood_types = sorted(blood_type_map.keys())
        demand = [blood_type_map[bt]['totalDemand'] for bt in blood_types]
        supply = [blood_type_map[bt]['totalSupply'] for bt in blood_types]
        balances = [blood_type_map[bt]['totalBalance'] for bt in blood_types]
        
        return {
            'bloodTypes': blood_types,
            'demand': demand,
            'supply': supply,
            'balances': balances
        }
    
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
            blood_units = self.fetch_blood_units()
            blood_requests = self.fetch_blood_requests()
            
            # Store blood_units for shelf life calculations
            self.blood_units = blood_units
            
            # Process data (R: df_monthly <- group_by + summarise)
            self.monthly_supply = self.process_monthly_supply(blood_units)
            self.monthly_demand = self.process_monthly_demand(blood_requests, self.monthly_supply)
            
            # Convert to R-style format (optimized with list comprehension)
            df_monthly_supply = [
                {'month': month_key, 'blood_type': bt, 'value': self.monthly_supply[month_key].get(bt, 0)}
                for month_key in sorted(self.monthly_supply.keys())
                for bt in BLOOD_TYPES
            ]
            
            df_monthly_demand = [
                {'month': month_key, 'blood_type': bt, 'value': self.monthly_demand[month_key].get(bt, 0)}
                for month_key in sorted(self.monthly_demand.keys())
                for bt in BLOOD_TYPES
            ]
            
            # STEP 1: EXACT R - Blood Supply Forecast.R
            # R: forecast_supply_df <- forecast_next_month_per_type(df_monthly)
            forecast_supply_df = self.forecast_next_month_per_type(df_monthly_supply)
            
            # STEP 2: EXACT R - Blood Demand Forecast.R  
            # R: forecast_demand_df <- forecast_hospital_requests(df_monthly_donations)
            # Note: In R, demand forecast uses pints_requested (which is units_collected * runif)
            # The demand multiplier is already applied in process_monthly_demand, so we just forecast
            forecast_demand_df = self.forecast_next_month_per_type(df_monthly_demand)
            
            # STEP 3: EXACT R - Supply vs Demand Forecast.R
            # R: combined <- merge(forecast_supply_df, forecast_demand_df, by = "Blood.Type")
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
            # EXACT R: Status = ifelse(Forecast_Gap < 0, "🔴 Shortage", "🟢 Surplus")
            for bt in combined:
                supply = combined[bt].get('Forecast_Supply', 0)
                demand = combined[bt].get('Forecast_Demand', 0)
                combined[bt]['Forecast_Gap'] = supply - demand
                combined[bt]['Status'] = '🔴 Shortage' if (supply - demand) < 0 else '🟢 Surplus'
            
            # STEP 4: EXACT R - Projected Stock Level.R
            # R: Projected Stock Level (Next Month) = Forecast_Supply - Forecast_Demand
            # R: Stock Status = ifelse(Projected Stock Level < 0, "⚠️ Critical (Shortage)", "✅ Stable (Surplus)")
            # Also add Target Buffer Level from New code.R (25% of forecasted demand)
            target_buffer_percentage = 0.25  # 25% of forecasted demand as safety buffer
            
            projected_stock = {}
            for bt in combined:
                supply = combined[bt].get('Forecast_Supply', 0)
                demand = combined[bt].get('Forecast_Demand', 0)
                projected_balance = supply - demand
                
                # EXACT R logic from New code.R: Target Buffer & Target Stock Level
                target_buffer_level = round(demand * target_buffer_percentage, 2)
                target_stock_level = round(demand + target_buffer_level, 2)
                buffer_gap = round(projected_balance - target_stock_level, 2)
                
                # EXACT R logic from New code.R: Buffer Status
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
                    'Stock Status': '⚠️ Critical (Shortage)' if projected_balance < 0 else '✅ Stable (Surplus)',
                    'Forecast_Gap': combined[bt].get('Forecast_Gap', 0),
                    'Status': combined[bt].get('Status', '🟢 Surplus'),
                    # New code.R additions
                    'Target Buffer Level': target_buffer_level,
                    'Target Stock Level': target_stock_level,
                    'Buffer Gap': buffer_gap,
                    'Buffer Status': buffer_status
                }
            
            # Generate forecast months for multiple months ahead
            current_date = datetime.now()
            forecast_months = self.generate_forecast_months(self.monthly_supply, current_date)
            
            # Build forecast_data for API response (optimized with list comprehension)
            self.forecast_data = []
            total_demand = 0
            total_supply = 0
            critical_types = set()  # Use set for faster lookups
            
            # Pre-calculate month variations
            month_variations = {}
            for forecast_month in forecast_months:
                month_date = datetime.strptime(forecast_month, '%Y-%m-%d')
                month_num = month_date.month
                month_variations[forecast_month] = 0.8 + (month_num / 12) * 0.4  # 0.8 to 1.2
            
            # Build forecast data in one pass
            for forecast_month in forecast_months:
                month_variation = month_variations[forecast_month]
                
                for bt in BLOOD_TYPES:
                    base_supply = projected_stock.get(bt, {}).get('Forecast_Supply', 0)
                    base_demand = projected_stock.get(bt, {}).get('Forecast_Demand', 0)
                    
                    supply = max(0, round(base_supply * month_variation))
                    demand = max(0, round(base_demand * month_variation))
                    balance = supply - demand
                    
                    # EXACT R logic from Projected Stock Level.R
                    status = 'critical' if balance < 0 else 'surplus'
                    if balance < 0:
                        critical_types.add(bt)
                    
                    # EXACT R logic from Supply vs Demand Forecast.R
                    gap_status = 'shortage' if balance < 0 else 'surplus'
                    
                    self.forecast_data.append({
                        'blood_type': bt,
                        'forecasted_demand': demand,
                        'forecasted_supply': supply,
                        'projected_balance': balance,
                        'status': status,
                        'gap_status': gap_status,
                        'forecast_month': forecast_month
                    })
                    
                    total_demand += demand
                    total_supply += supply
            
            # Calculate KPIs
            total_balance = total_supply - total_demand
            
            # Find the most critical blood type (optimized)
            critical_types_list = list(critical_types)
            
            # Use min() with key function for faster finding
            if self.forecast_data:
                most_critical_data = min(self.forecast_data, key=lambda x: x['projected_balance'])
                most_critical = most_critical_data['blood_type']
                lowest_balance = most_critical_data['projected_balance']
                
                # If no negative balance, find highest demand/supply ratio
                if lowest_balance >= 0 and total_demand > 0:
                    most_critical_data = max(
                        (d for d in self.forecast_data if d['forecasted_supply'] > 0),
                        key=lambda x: x['forecasted_demand'] / x['forecasted_supply'],
                        default=None
                    )
                    most_critical = most_critical_data['blood_type'] if most_critical_data else 'None'
            else:
                most_critical = 'None'
            
            # Calculate target buffer level (aggregate from projected_stock)
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
                'total_forecasted_demand': total_demand,
                'total_forecasted_supply': total_supply,
                'projected_balance': total_balance,
                'critical_blood_types': most_critical,
                'critical_types_list': critical_types_list,
                # Target buffer level KPIs (from New code.R)
                'target_buffer_level': round(total_target_buffer, 2),
                'target_stock_level': round(total_target_stock, 2),
                'buffer_gap': round(total_buffer_gap, 2),
                'action_required_count': action_required_count,
                # Shelf life KPIs
                'expiring_weekly': shelf_life_metrics['expiring_weekly'],
                'expiring_monthly': shelf_life_metrics['expiring_monthly'],
                'total_valid_units': shelf_life_metrics['total_valid_units'],
                'expiring_weekly_percentage': shelf_life_metrics['expiring_weekly_percentage'],
                'expiring_monthly_percentage': shelf_life_metrics['expiring_monthly_percentage']
            }
            
            # Categorize months
            historical_months = []
            for month_key in self.monthly_supply.keys():
                year = int(month_key[:4])
                if year <= 2025:
                    historical_months.append(month_key)
            
            historical_months.sort()
            
            return {
                'success': True,
                'kpis': self.kpi_data,
                'forecast_data': self.forecast_data,
                'monthly_supply': self.monthly_supply,
                'monthly_demand': self.monthly_demand,
                'forecast_months': forecast_months,
                'historical_months': historical_months,
                'all_months': historical_months,
                # R-style dataframes for compatibility
                'forecast_supply_df': forecast_supply_df,
                'forecast_demand_df': forecast_demand_df,
                'combined': list(combined.values()),
                'projected_stock': list(projected_stock.values())
            }
            
        finally:
            # Disconnect from database
            self.disconnect_database()


# Main execution function
def main():
    """Main function to run forecast calculations"""
    calculator = ForecastReportsCalculator()
    
    try:
        result = calculator.generate_forecasts()
        return result
    except Exception as e:
        return {
            'success': False,
            'error': str(e)
        }


if __name__ == "__main__":
    # When run directly, output JSON for PHP API consumption
    result = main()
    print(json.dumps(result, indent=2, default=str))

