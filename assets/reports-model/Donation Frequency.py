"""
Donation Frequency Distribution
===============================
Creates an interactive pie/donut chart showing donation frequency
distribution for ACTIVE donors only.

Definition for Reports dashboard:
- Use the eligibility history table
- Consider ONLY donors whose latest eligibility.status is APPROVED
- For those active donors:
    - "1st Time"  -> only one APPROVED record in history
    - "Repeat"    -> two or more APPROVED records in history
"""

import json
from collections import defaultdict
from typing import Dict, List

import plotly.graph_objects as go

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


def fetch_eligibility_history() -> List[Dict]:
    """
    Fetch full eligibility history ordered by donor_id and created_at.
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

    return records


def aggregate_donation_frequency(records: List[Dict]) -> Dict:
    """
    Aggregate ACTIVE donors by donation frequency using eligibility history.

    Returns:
        {
          "data": [
             { donation_frequency: "1st Time", count, percentage },
             { donation_frequency: "Repeat",   count, percentage },
          ],
          "total_donors": N
        }
    """
    # Track all statuses and approvals per donor
    history_by_donor: Dict[int, List[Dict]] = defaultdict(list)

    for rec in records:
        donor_id = rec.get("donor_id")
        if donor_id is None:
            continue
        history_by_donor[donor_id].append(rec)

    first_time = 0
    repeat = 0

    for donor_id, events in history_by_donor.items():
        # Determine latest status
        latest = max(events, key=lambda r: _parse_dt(r.get("created_at")) or 0)
        status_text = (latest.get("status") or "").lower()

        # Only consider donors whose latest status is currently approved
        if not ("approved" in status_text or status_text == "eligible"):
            continue

        # Count approvals across full history
        approvals = sum(
            1
            for r in events
            if "approved" in (r.get("status") or "").lower()
        )

        if approvals <= 1:
            first_time += 1
        else:
            repeat += 1

    total = first_time + repeat
    if total == 0:
        return {"data": [], "total_donors": 0}

    data = [
        {
            "donation_frequency": "1st Time",
            "count": first_time,
            "percentage": round((first_time / total) * 100, 1),
        },
        {
            "donation_frequency": "Repeat",
            "count": repeat,
            "percentage": round((repeat / total) * 100, 1),
        },
    ]

    return {"data": data, "total_donors": total}


def create_pie_chart(donor_data: List[Dict], total_donors: int) -> go.Figure:
    """Create an interactive pie/donut chart using Plotly."""
    # Color palette - light blue for 1st time, deep blue for repeat
    colors = ["#60a5fa", "#2563eb"]
    
    labels = [d["donation_frequency"] for d in donor_data]
    values = [d["count"] for d in donor_data]
    
    # Create hover text
    hover_text = [
        f"Frequency: {d['donation_frequency']}<br>Count: {d['count']}<br>Percentage: {d['percentage']}%"
        for d in donor_data
    ]
    
    fig = go.Figure(data=[
        go.Pie(
            labels=labels,
            values=values,
            hole=0.3,  # Donut chart
            marker=dict(
                colors=colors,
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
            text=f"<b>Donation Frequency Distribution</b><br><sub>Total Donors: {total_donors}</sub>",
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
        margin=dict(t=80, b=40, l=40, r=150)
    )
    
    return fig


def get_donation_frequency_data() -> Dict:
    """
    Main function to get donation frequency data.
    Returns a dictionary with the data for API consumption.
    """
    records = fetch_eligibility_history()

    if not records:
        return {
            "success": False,
            "message": "No eligibility records found in database",
            "data": [],
            "total_donors": 0,
            "retention_rate": 0,
        }

    result = aggregate_donation_frequency(records)
    donor_data = result["data"]
    total_donors = result["total_donors"]

    # Calculate retention rate
    repeat_count = next(
        (d["count"] for d in donor_data if d["donation_frequency"] == "Repeat"), 0
    )
    retention_rate = round((repeat_count / total_donors) * 100, 1) if total_donors > 0 else 0

    return {
        "success": True,
        "data": donor_data,
        "total_donors": total_donors,
        "retention_rate": retention_rate,
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML."""
    records = fetch_eligibility_history()

    if not records:
        print("WARNING: No eligibility records found in database", file=sys.stderr)
        return ""

    result = aggregate_donation_frequency(records)
    donor_data = result["data"]
    total_donors = result["total_donors"]

    fig = create_pie_chart(donor_data, total_donors)

    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")

    return fig.to_html(include_plotlyjs="cdn")


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_donation_frequency_data()
    
    print("\n=== DONATION FREQUENCY SUMMARY ===\n")
    print(f"Total Donors: {result['total_donors']}\n")
    
    for item in result["data"]:
        print(f"{item['donation_frequency']} Donors: {item['count']} ({item['percentage']}%)")
    
    # Additional insights
    first_time_count = next((d["count"] for d in result["data"] if d["donation_frequency"] == "1st Time"), 0)
    repeat_count = next((d["count"] for d in result["data"] if d["donation_frequency"] == "Repeat"), 0)
    
    print("\n=== DONOR RETENTION INSIGHTS ===\n")
    print(f"First-Time Donors: {first_time_count}")
    print(f"Repeat Donors: {repeat_count}")
    print(f"Retention Rate: {result['retention_rate']}%")
    
    if repeat_count > first_time_count:
        print("\n✓ More repeat donors than first-time donors - Good retention!")
    else:
        print("\n⚠ More first-time donors - Focus on retention strategies.")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Donation Frequency Distribution Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON instead of formatted text")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donation_frequency_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

