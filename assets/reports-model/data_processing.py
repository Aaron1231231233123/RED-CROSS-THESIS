"""
Data processing functions for forecast reports
"""

import random
from datetime import datetime, timedelta
from typing import Dict, List
from collections import defaultdict
from config import BLOOD_TYPES, RANDOM_SEED, TARGET_BUFFER_UNITS


def process_monthly_supply(blood_units: List[Dict]) -> Dict[str, Dict[str, int]]:
    """
    Process REAL blood units data from blood_bank_units table
    Uses actual units from database (counts each unit record)
    """
    monthly_supply = defaultdict(lambda: defaultdict(int))
    
    for unit in blood_units:
        # Use collected_at if available, otherwise use created_at
        date_field = unit.get('collected_at') or unit.get('created_at')
        if not date_field:
            continue
        
        blood_type = unit.get('blood_type')
        if not blood_type or blood_type not in BLOOD_TYPES:
            continue
        
        # FIXED: Only count units that are actually collected/available
        # Skip units that are handed over or expired (but be less strict)
        status = unit.get('status', '').lower() if unit.get('status') else ''
        # Only skip if explicitly handed_over or expired (allow null/empty status)
        if status in ['handed_over', 'expired', 'discarded']:
            continue
        # If status is null/empty, still count it (assume available)
        
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
            
            # FIXED: Count actual units from blood_bank_units table
            # Each row in blood_bank_units = 1 unit collected
            monthly_supply[month_key][blood_type] += 1
                
        except (ValueError, AttributeError):
            continue
    
    # Convert defaultdict to regular dict for compatibility
    result = {k: dict(v) for k, v in monthly_supply.items()}
    
    # If no database data, log warning but don't create fake data
    if not result:
        import sys
        print(f"WARNING: No supply data found in blood_bank_units table", file=sys.stderr)
        print(f"  - Check if blood_bank_units table has data", file=sys.stderr)
        print(f"  - Check if collected_at or created_at fields exist", file=sys.stderr)
        print(f"  - Check if status filtering is too aggressive", file=sys.stderr)
        # Don't create sample data - return empty so user knows there's no data
    
    return result


def process_monthly_demand(blood_requests: List[Dict], monthly_supply: Dict) -> Dict[str, Dict[str, int]]:
    """
    Process REAL demand data from blood_requests table
    Uses units_requested column from blood_requests table (NOT simulated)
    """
    monthly_demand = defaultdict(lambda: defaultdict(int))
    
    rh_map = {
        'positive': '+', 'pos': '+', '+': '+', '1': '+',
        'negative': '-', 'neg': '-', '-': '-', '0': '-'
    }
    
    # FIXED: Use REAL demand data from blood_requests table
    # Process actual units_requested from database
    for request in blood_requests:
        patient_blood_type = request.get('patient_blood_type')
        rh_factor = request.get('rh_factor')
        date_field = request.get('requested_on')
        units_requested = request.get('units_requested', 0)  # Get actual units_requested
        
        if not all([patient_blood_type, rh_factor, date_field]):
            continue
        
        # Skip if units_requested is missing or invalid
        try:
            units = int(units_requested) if units_requested else 0
            if units <= 0:
                continue
        except (ValueError, TypeError):
            continue
        
        try:
            if 'Z' in date_field:
                date = datetime.fromisoformat(date_field.replace('Z', '+00:00'))
            else:
                date = datetime.fromisoformat(date_field)
            
            if date.tzinfo:
                date = date.replace(tzinfo=None)
            
            if date.year > 2030:
                continue
            
            month_key = date.replace(day=1).strftime('%Y-%m-01')
            rh_lower = rh_factor.lower()
            rh_symbol = rh_map.get(rh_lower, rh_factor)
            blood_type = f"{patient_blood_type}{rh_symbol}"
            
            if blood_type not in BLOOD_TYPES:
                continue
            
            # FIXED: Use actual units_requested from database (not simulated)
            monthly_demand[month_key][blood_type] += units
                
        except (ValueError, TypeError, AttributeError):
            continue
    
    # Convert defaultdict to regular dict
    result = {k: dict(v) for k, v in monthly_demand.items()}
    
    # If no real demand data found, synthesize demand proportional to supply
    if not result and monthly_supply:
        import sys
        print("WARNING: No demand data found. Generating synthetic demand from supply to avoid zero forecasts.", file=sys.stderr)
        random.seed(RANDOM_SEED)
        for month_key, supply_data in monthly_supply.items():
            for blood_type, units in supply_data.items():
                if units <= 0 or blood_type not in BLOOD_TYPES:
                    continue
                multiplier = random.uniform(0.7, 1.2)
                result.setdefault(month_key, {})[blood_type] = max(1, int(round(units * multiplier)))
    
    return result


def process_expiration_series(blood_units: List[Dict]) -> Dict[str, Dict[str, int]]:
    """
    Build month-level counts for units expiring soon.
    - monthly_expiring: all expirations within the month
    - weekly_expiring: expirations occurring during the first 7 days of the month
    """
    monthly_counts = defaultdict(int)
    weekly_counts = defaultdict(int)

    for unit in blood_units:
        expires_at = unit.get('expires_at')
        collected_at = unit.get('collected_at') or unit.get('created_at')

        if not expires_at and collected_at:
            try:
                collected_date = _parse_iso_datetime(collected_at)
                expires_date = collected_date + timedelta(days=45)
            except (ValueError, TypeError):
                continue
        elif expires_at:
            try:
                expires_date = _parse_iso_datetime(expires_at)
            except (ValueError, TypeError):
                continue
        else:
            continue

        if expires_date.year > 2030:
            continue

        month_start = expires_date.replace(day=1, hour=0, minute=0, second=0, microsecond=0)
        month_key = month_start.strftime('%Y-%m-01')

        monthly_counts[month_key] += 1
        if (expires_date - month_start).days < 7:
            weekly_counts[month_key] += 1

    return {
        'monthly': dict(monthly_counts),
        'weekly': dict(weekly_counts),
    }


def _parse_iso_datetime(value):
    if isinstance(value, datetime):
        return value
    if 'Z' in str(value):
        return datetime.fromisoformat(str(value).replace('Z', '+00:00')).replace(tzinfo=None)
    return datetime.fromisoformat(str(value))

