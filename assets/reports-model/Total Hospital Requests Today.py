"""
Total Hospital Requests Today - Summary Card
============================================
Calculates the total number of hospital blood requests made today.
Uses real data from the blood_requests table in Supabase.
"""

import json
import sys
from datetime import datetime, date
from typing import Dict, List

from database import DatabaseConnection


def fetch_blood_requests() -> List[Dict]:
    """Fetch all blood requests from the database."""
    db = DatabaseConnection()
    blood_requests = []
    
    if db.connect():
        try:
            blood_requests = db.fetch_blood_requests()
        finally:
            db.disconnect()
    
    return blood_requests


def parse_date(date_str: str) -> date:
    """Parse ISO date string to date object."""
    if not date_str:
        return None
    try:
        if "Z" in date_str:
            dt = datetime.fromisoformat(date_str.replace("Z", "+00:00")).replace(tzinfo=None)
        else:
            dt = datetime.fromisoformat(date_str)
        return dt.date()
    except (ValueError, TypeError):
        return None


def calculate_requests_today(blood_requests: List[Dict]) -> Dict:
    """
    Calculate total hospital requests made today.
    
    Criteria:
    - Has hospital_admitted (is a hospital request)
    - requested_on date is today
    
    Returns:
        Dictionary with today's request count and breakdown
    """
    today = date.today()
    
    requests_today = []
    requests_other_days = []
    by_hospital: Dict[str, int] = {}
    by_blood_type: Dict[str, int] = {}
    by_status: Dict[str, int] = {}
    
    for request in blood_requests:
        hospital = request.get("hospital_admitted")
        requested_on = parse_date(request.get("requested_on"))
        blood_type = request.get("patient_blood_type", "Unknown")
        status = request.get("status", "Unknown")
        
        # Only count hospital requests (has hospital_admitted)
        if not hospital:
            continue
        
        request_info = {
            "request_id": request.get("request_id"),
            "hospital": hospital,
            "patient_name": request.get("patient_name"),
            "blood_type": blood_type,
            "units_requested": request.get("units_requested"),
            "status": status,
            "requested_on": request.get("requested_on")
        }
        
        if requested_on == today:
            requests_today.append(request_info)
            
            # Count by hospital
            by_hospital[hospital] = by_hospital.get(hospital, 0) + 1
            
            # Count by blood type
            by_blood_type[blood_type] = by_blood_type.get(blood_type, 0) + 1
            
            # Count by status
            by_status[status] = by_status.get(status, 0) + 1
        else:
            requests_other_days.append(request_info)
    
    return {
        "total_today": len(requests_today),
        "total_all_time": len(blood_requests),
        "date": today.strftime("%Y-%m-%d"),
        "by_hospital": by_hospital,
        "by_blood_type": by_blood_type,
        "by_status": by_status,
        "requests_today": requests_today
    }


def get_hospital_requests_today() -> Dict:
    """
    Main function to get total hospital requests today.
    Returns a dictionary with the data for API consumption.
    """
    blood_requests = fetch_blood_requests()
    
    if not blood_requests:
        return {
            "success": False,
            "message": "No blood requests found in database",
            "total_today": 0,
            "date": date.today().strftime("%Y-%m-%d")
        }
    
    result = calculate_requests_today(blood_requests)
    result["success"] = True
    
    return result


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_hospital_requests_today()
    
    print("TOTAL HOSPITAL REQUESTS TODAY")
    print("------------------------------")
    print(f"Total Requests: {result['total_today']}")
    print(f"Date: {result['date']}")
    
    # Breakdown by hospital
    if result.get("by_hospital"):
        print("\nBy Hospital:")
        for hospital, count in sorted(result["by_hospital"].items(), key=lambda x: x[1], reverse=True):
            print(f"  {hospital}: {count}")
    
    # Breakdown by status
    if result.get("by_status"):
        print("\nBy Status:")
        for status, count in result["by_status"].items():
            print(f"  {status}: {count}")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Total Hospital Requests Today Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--details", action="store_true", help="Include request details in output")
    args = parser.parse_args()
    
    if args.json:
        result = get_hospital_requests_today()
        if not args.details:
            # Remove detailed list for cleaner output
            result.pop("requests_today", None)
        print(json.dumps(result, indent=2))
    else:
        print_summary()

