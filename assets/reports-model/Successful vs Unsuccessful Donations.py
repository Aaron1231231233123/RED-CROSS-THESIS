"""
Successful vs Unsuccessful Donations
====================================
Creates an interactive pie/donut chart showing successful vs unsuccessful donations.
Uses real data from the eligibility or blood_collection tables in Supabase.
"""

import json
import sys
from typing import Dict, List

import plotly.graph_objects as go

from database import DatabaseConnection


# Color mapping for donation status
COLOR_MAP = {
    "Successful": "#10b981",
    "Unsuccessful": "#ef4444"
}


def fetch_eligibility_records() -> List[Dict]:
    """
    Fetch ALL eligibility records to determine donation success.

    IMPORTANT:
    - We intentionally do NOT group by donor_id here; each row in the
      eligibility table is treated as a separate donation attempt.
    - We only select columns that we know exist and are already used
      successfully by other reports (status + created_at).
    """
    db = DatabaseConnection()
    records: List[Dict] = []

    if db.connect():
        try:
            endpoint = (
                "eligibility?"
                "select=eligibility_id,donor_id,status,created_at"
                "&order=created_at.asc"
            )
            records = db.supabase_request(endpoint)
        finally:
            db.disconnect()

    return records


def fetch_blood_collections() -> List[Dict]:
    """Fetch blood collection records as another indicator of success."""
    db = DatabaseConnection()
    collections = []
    
    if db.connect():
        try:
            endpoint = (
                "blood_collection?"
                "select=collection_id,donor_id,collection_date,status"
            )
            collections = db.supabase_request(endpoint)
        finally:
            db.disconnect()
    
    return collections


def determine_donation_status(record: Dict) -> str:
    """
    Determine if a donation attempt was successful or unsuccessful
    using the eligibility table semantics:

    - approved / eligible / completed / collected  -> Successful
    - temporary deferred / permanently deferred / refused / ineligible / rejected / declined -> Unsuccessful

    We rely primarily on the textual status to avoid depending on
    columns that may not exist (like is_eligible in some schemas).
    """
    status = (record.get("status") or "").lower()

    # Clearly successful outcomes
    if any(
        kw in status
        for kw in ["approved", "eligible", "completed", "collected", "successful"]
    ):
        return "Successful"

    # Clearly unsuccessful / deferred / refused outcomes
    if any(
        kw in status
        for kw in [
            "temporary",
            "defer",       # covers deferred, temporarily deferred, permanently deferred
            "permanent",
            "refused",
            "reject",
            "decline",
            "ineligible",
        ]
    ):
        return "Unsuccessful"

    # Fallback: if we can't classify, be conservative and treat as unsuccessful
    return "Unsuccessful"


def aggregate_by_donation_status(eligibility_records: List[Dict], blood_collections: List[Dict]) -> List[Dict]:
    """
    Aggregate records by donation status (Successful vs Unsuccessful).
    
    Returns:
        List of dicts with donation_status, count, and percentage
    """
    status_counts: Dict[str, int] = {"Successful": 0, "Unsuccessful": 0}
    
    # Count from eligibility records
    for record in eligibility_records:
        status = determine_donation_status(record)
        status_counts[status] = status_counts.get(status, 0) + 1
    
    # If we have blood collections but no eligibility records, count those
    if not eligibility_records and blood_collections:
        for collection in blood_collections:
            collection_status = (collection.get("status") or "").lower()
            if collection_status in ["completed", "collected", "successful"]:
                status_counts["Successful"] += 1
            elif collection_status in ["failed", "incomplete", "cancelled"]:
                status_counts["Unsuccessful"] += 1
            else:
                # Default collected blood as successful
                status_counts["Successful"] += 1
    
    total = sum(status_counts.values())
    
    result = []
    for status in ["Successful", "Unsuccessful"]:
        count = status_counts.get(status, 0)
        percentage = round((count / total) * 100, 1) if total > 0 else 0
        result.append({
            "donation_status": status,
            "count": count,
            "percentage": percentage
        })
    
    return result


def create_pie_chart(donation_data: List[Dict], total_donations: int) -> go.Figure:
    """Create an interactive pie/donut chart using Plotly."""
    labels = [d["donation_status"] for d in donation_data]
    values = [d["count"] for d in donation_data]
    colors = [COLOR_MAP.get(label, "#6b7280") for label in labels]
    
    fig = go.Figure(data=[
        go.Pie(
            labels=labels,
            values=values,
            hole=0.4,  # Donut chart
            marker=dict(
                colors=colors,
                line=dict(color="#ffffff", width=2)
            ),
            textinfo="none",
            hovertemplate=(
                "%{label}<br>"
                "Count: %{value}<br>"
                "Percentage: %{percent}<extra></extra>"
            )
        )
    ])
    
    fig.update_layout(
        title=dict(
            text=f"<b>Successful vs Unsuccessful Donations</b><br><sub>Total: {total_donations}</sub>",
            font=dict(size=20)
        ),
        showlegend=True,
        legend=dict(
            orientation="v",
            x=1,
            y=0.5,
            font=dict(size=12)
        ),
        paper_bgcolor="#f8fafc",
        plot_bgcolor="#ffffff",
        hoverlabel=dict(
            bgcolor="white",
            font_size=12,
            font_color="black",
            bordercolor="#e5e7eb"
        ),
        margin=dict(t=80, b=40, l=40, r=150)
    )
    
    return fig


def get_donation_success_data() -> Dict:
    """
    Main function to get donation success data.
    Returns a dictionary with the data for API consumption.
    """
    eligibility_records = fetch_eligibility_records()
    blood_collections = fetch_blood_collections()
    
    if not eligibility_records and not blood_collections:
        return {
            "success": False,
            "message": "No donation records found in database",
            "data": [],
            "total_donations": 0
        }
    
    donation_data = aggregate_by_donation_status(eligibility_records, blood_collections)
    total_donations = sum(d["count"] for d in donation_data)
    
    # Calculate success rate
    successful = next((d["count"] for d in donation_data if d["donation_status"] == "Successful"), 0)
    success_rate = round((successful / total_donations) * 100, 1) if total_donations > 0 else 0
    
    return {
        "success": True,
        "data": donation_data,
        "total_donations": total_donations,
        "success_rate": success_rate
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML.

    IMPORTANT: Always create an HTML asset file, even when there is no data,
    so that forecast-asset.php never returns a 404. In the no-data case,
    we write a simple placeholder HTML with a friendly message.
    """
    eligibility_records = fetch_eligibility_records()
    blood_collections = fetch_blood_collections()

    if not eligibility_records and not blood_collections:
        placeholder_html = """<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Successful vs Unsuccessful Donations</title></head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h3 style="margin-top: 0;">Successful vs Unsuccessful Donations</h3>
  <p>No donation records are currently available. Please check back later.</p>
</body></html>"""
        if output_path:
            with open(output_path, "w", encoding="utf-8") as f:
                f.write(placeholder_html)
            print(f"Placeholder success chart saved to: {output_path}", file=sys.stderr)
        return placeholder_html

    donation_data = aggregate_by_donation_status(eligibility_records, blood_collections)
    total_donations = sum(d["count"] for d in donation_data)

    fig = create_pie_chart(donation_data, total_donations)

    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")

    return fig.to_html(include_plotlyjs="cdn")


def print_summary():
    """Print a formatted summary to console."""
    result = get_donation_success_data()
    
    print("\n=== SUCCESSFUL VS UNSUCCESSFUL DONATIONS ===\n")
    print(f"Total Donations: {result['total_donations']}")
    print(f"Success Rate: {result['success_rate']}%\n")
    
    for item in result["data"]:
        print(f"{item['donation_status']}: {item['count']} ({item['percentage']}%)")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Successful vs Unsuccessful Donations Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donation_success_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

