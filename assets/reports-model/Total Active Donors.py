"""
Total Active Donors - Summary Card
==================================
Calculates the total number of active donors based on the eligibility
history table.

Definition for Reports dashboard:
- Use the latest eligibility row per donor_id
- A donor is "active" if their latest status is APPROVED
- Each donor_id is counted once
"""

import json
from typing import Dict, List

from database import DatabaseConnection


def _parse_dt(value):
    from datetime import datetime

    if not value:
        return None
    try:
        text = str(value)
        if "Z" in text:
            return datetime.fromisoformat(text.replace("Z", "+00:00"))
        return datetime.fromisoformat(text)
    except Exception:
        return None


def fetch_latest_eligibility() -> List[Dict]:
    """
    Fetch the latest eligibility record per donor_id.
    """
    db = DatabaseConnection()
    records: List[Dict] = []

    if db.connect():
        try:
            endpoint = (
                "eligibility?"
                "select=eligibility_id,donor_id,status,created_at"
                "&order=donor_id.asc,created_at.asc"
            )
            records = db.supabase_request(endpoint)
        finally:
            db.disconnect()

    latest_by_donor: Dict[int, Dict] = {}

    for rec in records:
        donor_id = rec.get("donor_id")
        if donor_id is None:
            continue
        created = _parse_dt(rec.get("created_at"))
        existing = latest_by_donor.get(donor_id)
        if existing is None:
            latest_by_donor[donor_id] = {**rec, "_created": created}
        else:
            prev_created = existing.get("_created")
            if created and (prev_created is None or created > prev_created):
                latest_by_donor[donor_id] = {**rec, "_created": created}

    result: List[Dict] = []
    for rec in latest_by_donor.values():
        rec.pop("_created", None)
        result.append(rec)
    return result


def get_active_donors_data() -> Dict:
    """
    Main function to get total active donors for the Reports dashboard.
    Active donors = donors whose latest eligibility.status contains "approved".
    """
    records = fetch_latest_eligibility()

    if not records:
        return {
            "success": False,
            "message": "No eligibility records found in database",
            "total_active": 0,
            "total_donors": 0,
        }

    total_active = 0

    for rec in records:
        status_text = (rec.get("status") or "").lower()
        if "approved" in status_text or status_text == "eligible":
            total_active += 1

    return {
        "success": True,
        "total_active": total_active,
        "total_donors": len(records),
    }


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_active_donors_data()

    print("TOTAL ACTIVE DONORS")
    print("------------------")
    print(f"Active Donors: {result['total_active']}")
    print(f"Total Donors (with eligibility history): {result['total_donors']}")


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Total Active Donors Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    args = parser.parse_args()

    if args.json:
        result = get_active_donors_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

