"""
KPI calculations and data aggregations
"""

from datetime import datetime, timedelta
from typing import Dict, List
from collections import defaultdict
from config import BLOOD_TYPES


def calculate_shelf_life_metrics(blood_units: List[Dict]) -> Dict:
    """
    Calculate shelf life metrics (weekly and monthly) - OPTIMIZED
    Blood shelf life is 45 days from collection date (collected_at)
    """
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
    
    # Calculate monthly breakdown
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


def calculate_kpis(forecast_data: List[Dict], monthly_supply: Dict, 
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
        'total_forecasted_demand': round(total_demand),  # Round to whole number
        'total_forecasted_supply': round(total_supply),  # Round to whole number
        'projected_balance': round(total_balance),  # Round to whole number
        'critical_blood_types': most_critical,
        'critical_types_list': list(set(critical_types))
    }


def aggregate_data_by_blood_type(data: List[Dict]) -> Dict:
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

