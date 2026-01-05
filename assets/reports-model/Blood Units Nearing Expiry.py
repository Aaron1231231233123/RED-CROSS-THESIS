"""
Blood Units Nearing Expiry
==========================
Calculates and displays blood units that are nearing expiry.
Uses real data from the blood_bank_units table in Supabase.
"""

import json
import sys
from datetime import datetime, timedelta
from typing import Dict, List

from database import DatabaseConnection


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


def parse_date(date_str: str) -> datetime:
    """Parse ISO date string to datetime object."""
    if not date_str:
        return None
    try:
        if "Z" in date_str:
            return datetime.fromisoformat(date_str.replace("Z", "+00:00")).replace(tzinfo=None)
        return datetime.fromisoformat(date_str)
    except (ValueError, TypeError):
        return None


def calculate_units_nearing_expiry(blood_units: List[Dict], days_threshold: int = 7) -> Dict:
    """
    Calculate blood units that are nearing expiry within the specified threshold.
    
    Args:
        blood_units: List of blood unit records from database
        days_threshold: Number of days to consider as "nearing expiry" (default: 7)
    
    Returns:
        Dictionary containing:
        - total_nearing_expiry: Count of units expiring within threshold
        - units_by_blood_type: Breakdown by blood type
        - units_detail: Detailed list of expiring units
    """
    today = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
    threshold_date = today + timedelta(days=days_threshold)
    
    units_nearing_expiry = []
    units_by_blood_type: Dict[str, int] = {}
    
    for unit in blood_units:
        # Check status - must be "Valid" or similar active status
        status = (unit.get("status") or "").lower()
        if status in ["expired", "handed_over", "discarded", "disposed"]:
            continue
        
        # Check if already handed over or disposed
        if unit.get("handed_over_at") or unit.get("disposed_at"):
            continue
        
        # Parse expiry date
        expires_at = parse_date(unit.get("expires_at"))
        
        # If no expiry date, calculate from collected_at (assuming 45 days shelf life)
        if not expires_at:
            collected_at = parse_date(unit.get("collected_at") or unit.get("created_at"))
            if collected_at:
                expires_at = collected_at + timedelta(days=45)
            else:
                continue
        
        # Check if within threshold (not already expired, and expiring within X days)
        if expires_at >= today and expires_at <= threshold_date:
            blood_type = unit.get("blood_type", "Unknown")
            days_until_expiry = (expires_at - today).days
            
            units_nearing_expiry.append({
                "unit_id": unit.get("unit_id"),
                "unit_serial_number": unit.get("unit_serial_number"),
                "blood_type": blood_type,
                "expires_at": expires_at.strftime("%Y-%m-%d"),
                "days_until_expiry": days_until_expiry
            })
            
            units_by_blood_type[blood_type] = units_by_blood_type.get(blood_type, 0) + 1
    
    # Sort by expiry date (soonest first)
    units_nearing_expiry.sort(key=lambda x: x["days_until_expiry"])
    
    return {
        "total_nearing_expiry": len(units_nearing_expiry),
        "days_threshold": days_threshold,
        "units_by_blood_type": units_by_blood_type,
        "units_detail": units_nearing_expiry
    }


def get_units_nearing_expiry_summary(days_threshold: int = 7) -> Dict:
    """
    Main function to get blood units nearing expiry.
    Returns a dictionary with the data for API consumption.
    """
    blood_units = fetch_blood_units()
    
    if not blood_units:
        return {
            "success": False,
            "message": "No blood units found in database",
            "total_nearing_expiry": 0,
            "days_threshold": days_threshold,
            "units_by_blood_type": {},
            "units_detail": []
        }
    
    result = calculate_units_nearing_expiry(blood_units, days_threshold)
    result["success"] = True
    
    return result


def print_summary(days_threshold: int = 7):
    """Print a formatted summary to console (matching R output style)."""
    result = get_units_nearing_expiry_summary(days_threshold)
    
    print("BLOOD UNITS NEARING EXPIRY")
    print("---------------------------")
    print(f"Units expiring within {result['days_threshold']} days: {result['total_nearing_expiry']}")
    
    if result["units_by_blood_type"]:
        print("\nBreakdown by Blood Type:")
        for blood_type, count in sorted(result["units_by_blood_type"].items()):
            print(f"  {blood_type}: {count}")
    
    if result["units_detail"]:
        print("\nDetails (soonest first):")
        for unit in result["units_detail"][:10]:  # Show top 10
            print(f"  - {unit['blood_type']}: expires {unit['expires_at']} ({unit['days_until_expiry']} days)")
        
        if len(result["units_detail"]) > 10:
            print(f"  ... and {len(result['units_detail']) - 10} more")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Blood Units Nearing Expiry Report")
    parser.add_argument("--days", type=int, default=7, help="Days threshold for nearing expiry (default: 7)")
    parser.add_argument("--json", action="store_true", help="Output as JSON instead of formatted text")
    args = parser.parse_args()
    
    if args.json:
        result = get_units_nearing_expiry_summary(args.days)
        print(json.dumps(result, indent=2))
    else:
        print_summary(args.days)

