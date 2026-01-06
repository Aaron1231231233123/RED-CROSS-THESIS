"""
Donations by Month Interactive Line Chart
==========================================
Creates an interactive line chart showing blood donations by month.
Includes a dropdown filter for blood type selection.
Uses real data from the blood_bank_units table in Supabase.
"""

import json
import sys
from collections import defaultdict
from datetime import datetime
from typing import Dict, List

import plotly.graph_objects as go

from database import DatabaseConnection
from config import BLOOD_TYPES


# Color mapping for blood types (matching R version)
COLOR_MAP = {
    "All": "#3b82f6",
    "O+": "#ef4444", "O-": "#ef4444",
    "A+": "#10b981", "A-": "#10b981",
    "B+": "#f59e0b", "B-": "#f59e0b",
    "AB+": "#8b5cf6", "AB-": "#8b5cf6"
}


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


def process_donation_data(blood_units: List[Dict], selected_blood_type: str = "All") -> List[Dict]:
    """
    Process donation data grouped by month.
    
    Args:
        blood_units: List of blood unit records
        selected_blood_type: Filter by blood type ("All" for no filter)
    
    Returns:
        List of dicts with year_month and donations count
    """
    monthly_counts: Dict[str, int] = defaultdict(int)
    
    for unit in blood_units:
        blood_type = unit.get("blood_type")
        
        # Apply blood type filter
        if selected_blood_type != "All" and blood_type != selected_blood_type:
            continue
        
        # Get collection date
        date_field = unit.get("collected_at") or unit.get("created_at")
        collected_date = parse_date(date_field)
        
        if not collected_date:
            continue
        
        # Floor to first day of month
        month_key = collected_date.replace(day=1).strftime("%Y-%m-%d")
        monthly_counts[month_key] += 1
    
    # Convert to sorted list
    result = [
        {"year_month": month, "donations": count}
        for month, count in sorted(monthly_counts.items())
    ]
    
    return result


def create_line_chart(blood_units: List[Dict]) -> go.Figure:
    """Create an interactive line chart with dropdown for blood type selection."""
    blood_types = ["All"] + BLOOD_TYPES
    
    fig = go.Figure()
    
    # Add a trace for each blood type
    for i, bt in enumerate(blood_types):
        data = process_donation_data(blood_units, bt)
        
        if not data:
            continue
        
        x_values = [d["year_month"] for d in data]
        y_values = [d["donations"] for d in data]
        
        fig.add_trace(go.Scatter(
            x=x_values,
            y=y_values,
            mode="lines+markers",
            name=bt,
            line=dict(color=COLOR_MAP.get(bt, "#6b7280"), width=3),
            marker=dict(color=COLOR_MAP.get(bt, "#6b7280"), size=8),
            visible=(bt == "All"),  # Only show "All" by default
            hovertemplate="Month: %{x}<br>Donations: %{y}<extra></extra>"
        ))
    
    # Create dropdown buttons
    buttons = []
    for i, bt in enumerate(blood_types):
        visibility = [False] * len(blood_types)
        visibility[i] = True
        buttons.append(dict(
            method="update",
            args=[{"visible": visibility}],
            label=bt
        ))
    
    fig.update_layout(
        title="Blood Donations by Month",
        xaxis=dict(
            title="<b>Month</b>",
            tickformat="%b %Y",
            gridcolor="#e5e7eb"
        ),
        yaxis=dict(
            title="<b>Number of Donations</b>",
            gridcolor="#e5e7eb"
        ),
        plot_bgcolor="#ffffff",
        paper_bgcolor="#f8fafc",
        hovermode="closest",
        updatemenus=[dict(
            y=1.15,
            x=0.1,
            type="dropdown",
            active=0,
            buttons=buttons
        )]
    )
    
    return fig


def get_donations_by_month_data(selected_blood_type: str = "All") -> Dict:
    """
    Main function to get donations by month data.
    Returns a dictionary with the data for API consumption.
    """
    blood_units = fetch_blood_units()
    
    if not blood_units:
        return {
            "success": False,
            "message": "No blood units found in database",
            "data": [],
            "total_donations": 0
        }
    
    data = process_donation_data(blood_units, selected_blood_type)
    total = sum(d["donations"] for d in data)
    
    return {
        "success": True,
        "selected_blood_type": selected_blood_type,
        "data": data,
        "total_donations": total
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML."""
    blood_units = fetch_blood_units()
    
    if not blood_units:
        print("WARNING: No blood units found in database", file=sys.stderr)
        return ""
    
    fig = create_line_chart(blood_units)
    
    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")
    
    return fig.to_html(include_plotlyjs="cdn")


def print_summary(selected_blood_type: str = "All"):
    """Print a formatted summary to console."""
    result = get_donations_by_month_data(selected_blood_type)
    
    print(f"\n=== DONATIONS BY MONTH ({selected_blood_type}) ===\n")
    print(f"Total Donations: {result['total_donations']}\n")
    
    for item in result["data"]:
        month_str = datetime.strptime(item["year_month"], "%Y-%m-%d").strftime("%b %Y")
        print(f"{month_str}: {item['donations']} donations")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Donations by Month Report")
    parser.add_argument("--blood-type", type=str, default="All", help="Filter by blood type")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donations_by_month_data(args.blood_type)
        print(json.dumps(result, indent=2))
    else:
        print_summary(args.blood_type)

