"""
Eligible Donors Today - Summary Card
====================================
Calculates the number of donors eligible to donate today using the
eligibility history table.

Rules:
- Work from the latest eligibility row per donor_id
- Latest status must be "approved" (or equivalent) AND
- Today must be on/after the end_date (3‑month recovery window)
- Temporary / permanent / ineligible / refused donors are not eligible
"""

import json
from datetime import datetime
from typing import Dict, List

from database import DatabaseConnection


def _parse_dt(value):
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
                "select=eligibility_id,donor_id,status,created_at,end_date"
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


def _is_eligible_today(record: Dict, today: datetime) -> bool:
    """
    Determine if a donor is eligible to donate today based on their
    latest eligibility record.
    """
    status_text = (record.get("status") or "").lower()

    # Permanently ineligible / indefinite
    if any(
        kw in status_text
        for kw in ["permanent", "indefinite", "ineligible"]
    ):
        return False

    # Temporary deferral (including refused)
    if "temporary" in status_text or "deferred" in status_text or "refused" in status_text:
        return False

    # Approved / eligible: respect end_date as recovery window boundary
    if "approved" in status_text or status_text == "eligible":
        end_dt = _parse_dt(record.get("end_date"))
        if not end_dt:
            # No end_date – be conservative and treat as not yet eligible
            return False
        return today.date() >= end_dt.date()

    # Any other status: not eligible
    return False


def get_eligible_donors_today() -> Dict:
    """
    Main function to get eligible donors today.
    Returns a dictionary with the data for API consumption.
    """
    records = fetch_latest_eligibility()

    if not records:
        return {
            "success": False,
            "message": "No eligibility records found in database",
            "total_eligible": 0,
            "total_donors": 0,
        }

    today = datetime.now()
    total_eligible = 0

    for rec in records:
        if _is_eligible_today(rec, today):
            total_eligible += 1

    return {
        "success": True,
        "total_eligible": total_eligible,
        "total_donors": len(records),
    }


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_eligible_donors_today()

    print("TOTAL ELIGIBLE DONORS TODAY")
    print("----------------------------")
    print(f"Eligible Donors: {result['total_eligible']}")
    print(f"Total Donors (with eligibility history): {result['total_donors']}")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Eligible Donors Today Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    args = parser.parse_args()

    if args.json:
        result = get_eligible_donors_today()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

