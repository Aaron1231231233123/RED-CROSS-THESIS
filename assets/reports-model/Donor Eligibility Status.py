"""
Donor Eligibility Status Interactive Pie Chart
===============================================
Creates an interactive pie/donut chart showing donor eligibility status distribution.
Uses real data from the eligibility table in Supabase.
"""

import json
import sys
from typing import Dict, List

import plotly.graph_objects as go

from database import DatabaseConnection


# Eligibility status categories (high-level buckets for the chart)
ELIGIBILITY_STATUSES = ["Eligible", "Temporarily Deferred", "Permanently Deferred"]

# Color palette - green for eligible, yellow for temporary, red for permanent
COLORS = ["#10b981", "#f59e0b", "#ef4444"]


def fetch_eligibility_records() -> List[Dict]:
    """
    Fetch eligibility records from the database and return the latest
    record per donor_id.

    The eligibility table is a history table, so we only want the most
    recent record for each donor when computing current status.
    """
    from datetime import datetime

    db = DatabaseConnection()
    records: List[Dict] = []

    if db.connect():
        try:
            # Order by donor_id then created_at so we can easily keep the last per donor
            endpoint = (
                "eligibility?"
                "select=eligibility_id,donor_id,status,created_at,end_date"
                "&order=donor_id.asc,created_at.asc"
            )
            records = db.supabase_request(endpoint)
        finally:
            db.disconnect()

    latest_by_donor: Dict[int, Dict] = {}

    def _parse_dt(value):
        if not value:
            return None
        try:
            # Handle both naive and UTC ISO strings
            text = str(value)
            if "Z" in text:
                return datetime.fromisoformat(text.replace("Z", "+00:00"))
            return datetime.fromisoformat(text)
        except Exception:
            return None

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

    # Strip helper field before returning
    result: List[Dict] = []
    for rec in latest_by_donor.values():
        rec.pop("_created", None)
        result.append(rec)
    return result


def determine_eligibility_status(record: Dict) -> str:
    """
    Determine eligibility status from record fields.
    Uses ONLY the textual status from the eligibility table, matching
    the actual values stored there:

    - "permanently deferred"  -> Permanently Deferred
    - "temporary deferred"    -> Temporarily Deferred
    - "approved"              -> Eligible
    - "refused"               -> Temporarily Deferred
    """
    status_text = (record.get("status") or "").lower()

    # Permanently deferred / indefinite / ineligible
    if any(kw in status_text for kw in ["permanent", "indefinite", "ineligible"]):
        return "Permanently Deferred"

    # Temporary deferral (including refused)
    if "temporary" in status_text or "deferred" in status_text or "refused" in status_text:
        return "Temporarily Deferred"

    # Approved / eligible
    if "approved" in status_text or status_text == "eligible":
        return "Eligible"

    # Fallback: treat unknown statuses as Eligible so they don't bloat deferred counts
    return "Eligible"


def aggregate_by_eligibility_status(eligibility_records: List[Dict]) -> List[Dict]:
    """
    Aggregate records by eligibility status.
    
    Returns:
        List of dicts with eligibility_status, count, and percentage
    """
    status_counts: Dict[str, int] = {status: 0 for status in ELIGIBILITY_STATUSES}
    
    for record in eligibility_records:
        status = determine_eligibility_status(record)
        status_counts[status] = status_counts.get(status, 0) + 1
    
    total = sum(status_counts.values())
    
    result = []
    for status in ELIGIBILITY_STATUSES:
        count = status_counts.get(status, 0)
        percentage = round((count / total) * 100, 1) if total > 0 else 0
        result.append({
            "eligibility_status": status,
            "count": count,
            "percentage": percentage
        })
    
    return result


def calculate_deferred_summary(eligibility_data: List[Dict]) -> List[Dict]:
    """Calculate Deferred vs Not Deferred summary."""
    eligible_count = next((d["count"] for d in eligibility_data if d["eligibility_status"] == "Eligible"), 0)
    deferred_count = sum(d["count"] for d in eligibility_data if d["eligibility_status"] != "Eligible")
    total = eligible_count + deferred_count
    
    return [
        {
            "deferred": "Not Deferred",
            "count": eligible_count,
            "percentage": round((eligible_count / total) * 100, 1) if total > 0 else 0
        },
        {
            "deferred": "Deferred",
            "count": deferred_count,
            "percentage": round((deferred_count / total) * 100, 1) if total > 0 else 0
        }
    ]


def create_pie_chart(eligibility_data: List[Dict], total_donors: int) -> go.Figure:
    """Create an interactive pie/donut chart using Plotly."""
    labels = [d["eligibility_status"] for d in eligibility_data]
    values = [d["count"] for d in eligibility_data]
    
    # Create hover text
    hover_text = [
        f"Status: {d['eligibility_status']}<br>Count: {d['count']}<br>Percentage: {d['percentage']}%"
        for d in eligibility_data
    ]
    
    fig = go.Figure(data=[
        go.Pie(
            labels=labels,
            values=values,
            hole=0.3,  # Donut chart
            marker=dict(
                colors=COLORS,
                line=dict(color="#ffffff", width=2)
            ),
            hovertext=hover_text,
            hoverinfo="text",
            textposition="inside",
            textinfo="none"
        )
    ])
    
    fig.update_layout(
        title=dict(
            text=f"<b>Donor Eligibility Status Distribution</b><br><sub>Total Donors: {total_donors}</sub>",
            font=dict(size=20)
        ),
        showlegend=True,
        legend=dict(
            orientation="v",
            x=1,
            y=0.5,
            font=dict(size=12)
        ),
        paper_bgcolor="#ffffff",
        plot_bgcolor="#ffffff",
        hoverlabel=dict(
            bgcolor="white",
            font_size=12,
            font_color="black",
            bordercolor="#e5e7eb"
        ),
        margin=dict(t=80, b=40, l=40, r=180)
    )
    
    return fig


def get_donor_eligibility_status_data() -> Dict:
    """
    Main function to get donor eligibility status data.
    Returns a dictionary with the data for API consumption.
    """
    eligibility_records = fetch_eligibility_records()

    if not eligibility_records:
        return {
            "success": False,
            "message": "No eligibility records found in database",
            "data": [],
            "total_donors": 0,
            "deferred_summary": [],
        }

    # PURPOSE: show full distribution of latest eligibility status per donor,
    # including Eligible, Temporarily Deferred, and Permanently Deferred.
    eligibility_data = aggregate_by_eligibility_status(eligibility_records)
    total_donors = sum(d["count"] for d in eligibility_data)
    deferred_summary = calculate_deferred_summary(eligibility_data)
    
    return {
        "success": True,
        "data": eligibility_data,
        "total_donors": total_donors,
        "deferred_summary": deferred_summary
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML."""
    eligibility_records = fetch_eligibility_records()

    if not eligibility_records:
        print("WARNING: No eligibility records found in database", file=sys.stderr)
        return ""

    eligibility_data = aggregate_by_eligibility_status(eligibility_records)
    total_donors = sum(d["count"] for d in eligibility_data)
    
    fig = create_pie_chart(eligibility_data, total_donors)
    
    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")
    
    return fig.to_html(include_plotlyjs="cdn")


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_donor_eligibility_status_data()
    
    print("\n=== DONOR ELIGIBILITY STATUS SUMMARY ===\n")
    print(f"Total Donors: {result['total_donors']}\n")
    
    for item in result["data"]:
        print(f"{item['eligibility_status']}: {item['count']} donors ({item['percentage']}%)")
    
    # Deferred summary
    print("\n=== DEFERRED STATUS SUMMARY ===\n")
    for item in result["deferred_summary"]:
        print(f"{item['deferred']}: {item['count']} donors ({item['percentage']}%)")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Donor Eligibility Status Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donor_eligibility_status_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

