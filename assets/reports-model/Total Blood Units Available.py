"""
Total Blood Units Available - Summary Card
==========================================
Calculates the total number of available blood units.
Available units are those with status "Valid", not handed over, and not disposed.
Uses real data from the blood_bank_units table in Supabase.
"""

import json
import sys
from typing import Dict, List

from database import DatabaseConnection
from config import BLOOD_TYPES


def fetch_blood_units() -> List[Dict]:
    """Fetch all blood units from the database."""
    db = DatabaseConnection()
    blood_units = []
    
    if db.connect():
        try:
            blood_units = db.fetch_blood_units()
        finally:
            db.disconnect()
    
    return blood_units


def calculate_available_units(blood_units: List[Dict]) -> Dict:
    """
    Calculate total available blood units.
    
    Criteria for availability:
    - Status is "Valid" (not expired)
    - Not handed over (handed_over_at is null)
    - Not disposed (disposed_at is null)
    
    Returns:
        Dictionary with available units count and breakdown by blood type
    """
    available_units = []
    unavailable_units = []
    by_blood_type: Dict[str, int] = {bt: 0 for bt in BLOOD_TYPES}
    
    for unit in blood_units:
        status = (unit.get("status") or "").lower()
        handed_over_at = unit.get("handed_over_at")
        disposed_at = unit.get("disposed_at")
        blood_type = unit.get("blood_type", "Unknown")
        
        # Check if unit is available
        is_valid = status in ["valid", "available", ""]  # Empty status treated as valid
        is_not_handed_over = handed_over_at is None or handed_over_at == ""
        is_not_disposed = disposed_at is None or disposed_at == ""
        
        is_available = is_valid and is_not_handed_over and is_not_disposed
        
        unit_info = {
            "unit_id": unit.get("unit_id"),
            "unit_serial_number": unit.get("unit_serial_number"),
            "blood_type": blood_type,
            "status": status,
            "is_available": is_available
        }
        
        if is_available:
            available_units.append(unit_info)
            if blood_type in by_blood_type:
                by_blood_type[blood_type] += 1
        else:
            unavailable_units.append(unit_info)
    
    return {
        "total_available": len(available_units),
        "total_unavailable": len(unavailable_units),
        "total_units": len(blood_units),
        "by_blood_type": by_blood_type,
        "available_units": available_units,
        "unavailable_units": unavailable_units
    }


def get_available_units_data() -> Dict:
    """
    Main function to get total available blood units.
    Returns a dictionary with the data for API consumption.
    """
    blood_units = fetch_blood_units()
    
    if not blood_units:
        return {
            "success": False,
            "message": "No blood units found in database",
            "total_available": 0,
            "total_units": 0,
            "by_blood_type": {bt: 0 for bt in BLOOD_TYPES}
        }
    
    result = calculate_available_units(blood_units)
    result["success"] = True
    
    # Calculate availability percentage
    if result["total_units"] > 0:
        result["availability_percentage"] = round(
            (result["total_available"] / result["total_units"]) * 100, 1
        )
    else:
        result["availability_percentage"] = 0
    
    return result


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_available_units_data()
    
    print("TOTAL BLOOD UNITS AVAILABLE")
    print("----------------------------")
    print(f"Available Units: {result['total_available']}")
    print(f"Total Units: {result['total_units']}")
    print(f"Availability Rate: {result.get('availability_percentage', 0)}%")
    
    # Breakdown by blood type
    print("\nBreakdown by Blood Type:")
    for blood_type, count in result.get("by_blood_type", {}).items():
        if count > 0:
            print(f"  {blood_type}: {count}")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Total Blood Units Available Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--details", action="store_true", help="Include unit details in output")
    args = parser.parse_args()
    
    if args.json:
        result = get_available_units_data()
        if not args.details:
            # Remove detailed lists for cleaner output
            result.pop("available_units", None)
            result.pop("unavailable_units", None)
        print(json.dumps(result, indent=2))
    else:
        print_summary()

