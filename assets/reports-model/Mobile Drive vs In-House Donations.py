"""
Mobile Drive vs In-House Donations
==================================
Creates an interactive pie/donut chart showing donation type distribution.
Categorizes donations as "Mobile Drive" or "In-House" based on registration channel.
Uses real data from the donor_form table in Supabase.
"""

import json
import sys
from typing import Dict, List

import plotly.graph_objects as go

from database import DatabaseConnection


# Color mapping for donation types
COLOR_MAP = {
    "Mobile Drive": "#3b82f6",
    "In-House": "#10b981"
}


def fetch_donor_forms() -> List[Dict]:
    """Fetch donor forms with registration channel from the database."""
    db = DatabaseConnection()
    donor_forms = []
    
    if db.connect():
        try:
            endpoint = (
                "donor_form?"
                "select=donor_id,registration_channel"
            )
            donor_forms = db.supabase_request(endpoint)
        finally:
            db.disconnect()
    
    return donor_forms


def categorize_donation_type(registration_channel: str) -> str:
    """
    Categorize registration channel into donation type.
    Mobile Drive -> Mobile Drive
    All others (PRC Portal, Walk-in, Admin, etc.) -> In-House
    """
    if not registration_channel:
        return "In-House"
    
    channel_lower = registration_channel.lower().strip()
    
    if "mobile" in channel_lower or "drive" in channel_lower:
        return "Mobile Drive"
    
    return "In-House"


def aggregate_by_donation_type(donor_forms: List[Dict]) -> List[Dict]:
    """
    Aggregate donors by donation type.
    
    Returns:
        List of dicts with donation_type, count, and percentage
    """
    type_counts: Dict[str, int] = {"Mobile Drive": 0, "In-House": 0}
    
    for donor in donor_forms:
        channel = donor.get("registration_channel", "")
        donation_type = categorize_donation_type(channel)
        type_counts[donation_type] = type_counts.get(donation_type, 0) + 1
    
    total = sum(type_counts.values())
    
    result = []
    for dtype in ["Mobile Drive", "In-House"]:
        count = type_counts.get(dtype, 0)
        percentage = round((count / total) * 100, 1) if total > 0 else 0
        result.append({
            "donation_type": dtype,
            "count": count,
            "percentage": percentage
        })
    
    return result


def create_pie_chart(donation_data: List[Dict], total_donations: int) -> go.Figure:
    """Create an interactive pie/donut chart using Plotly."""
    labels = [d["donation_type"] for d in donation_data]
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
            text=f"<b>Mobile Drive vs In-House Donations</b><br><sub>Total: {total_donations}</sub>",
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


def get_mobile_vs_inhouse_data() -> Dict:
    """
    Main function to get Mobile Drive vs In-House data.
    Returns a dictionary with the data for API consumption.
    """
    donor_forms = fetch_donor_forms()
    
    if not donor_forms:
        return {
            "success": False,
            "message": "No donor forms found in database",
            "data": [],
            "total_donations": 0
        }
    
    donation_data = aggregate_by_donation_type(donor_forms)
    total_donations = sum(d["count"] for d in donation_data)
    
    return {
        "success": True,
        "data": donation_data,
        "total_donations": total_donations
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML."""
    donor_forms = fetch_donor_forms()
    
    if not donor_forms:
        print("WARNING: No donor forms found in database", file=sys.stderr)
        return ""
    
    donation_data = aggregate_by_donation_type(donor_forms)
    total_donations = sum(d["count"] for d in donation_data)
    
    fig = create_pie_chart(donation_data, total_donations)
    
    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")
    
    return fig.to_html(include_plotlyjs="cdn")


def print_summary():
    """Print a formatted summary to console."""
    result = get_mobile_vs_inhouse_data()
    
    print("\n=== MOBILE DRIVE VS IN-HOUSE DONATIONS ===\n")
    print(f"Total Donations: {result['total_donations']}\n")
    
    for item in result["data"]:
        print(f"{item['donation_type']}: {item['count']} ({item['percentage']}%)")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Mobile Drive vs In-House Donations Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_mobile_vs_inhouse_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

