"""
Blood Requests by Blood Type
============================
Creates an interactive bar chart showing blood requests grouped by blood type.
Uses real data from the blood_requests table in Supabase.
"""

import json
import sys
from typing import Dict, List

import plotly.graph_objects as go

from database import DatabaseConnection


# Color mapping per blood type (matching R version)
COLOR_MAP = {
    "O+": "#ef4444", "O-": "#ef4444",
    "A+": "#10b981", "A-": "#10b981",
    "B+": "#f59e0b", "B-": "#f59e0b",
    "AB+": "#8b5cf6", "AB-": "#8b5cf6"
}


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


def aggregate_requests_by_blood_type(blood_requests: List[Dict]) -> List[Dict]:
    """
    Aggregate blood requests by patient blood type.
    Returns a list sorted by total requests (descending).
    """
    blood_type_counts: Dict[str, int] = {}
    
    for request in blood_requests:
        blood_type = request.get("patient_blood_type")
        rh_factor = request.get("rh_factor", "")
        
        if not blood_type:
            continue
        
        # Normalize blood type format (e.g., "A" + "+" -> "A+")
        if rh_factor:
            rh_symbol = "+" if rh_factor.lower() in ["positive", "pos", "+", "1"] else "-"
            if not blood_type.endswith(("+", "-")):
                blood_type = f"{blood_type}{rh_symbol}"
        
        blood_type_counts[blood_type] = blood_type_counts.get(blood_type, 0) + 1
    
    # Convert to list and sort by count descending
    result = [
        {"patient_blood_type": bt, "total_requests": count}
        for bt, count in blood_type_counts.items()
    ]
    result.sort(key=lambda x: x["total_requests"], reverse=True)
    
    return result


def create_bar_chart(requests_by_blood: List[Dict]) -> go.Figure:
    """Create an interactive bar chart using Plotly."""
    blood_types = [r["patient_blood_type"] for r in requests_by_blood]
    total_requests = [r["total_requests"] for r in requests_by_blood]
    colors = [COLOR_MAP.get(bt, "#6b7280") for bt in blood_types]
    
    fig = go.Figure(data=[
        go.Bar(
            x=blood_types,
            y=total_requests,
            marker_color=colors,
            hovertemplate="Blood Type: %{x}<br>Requests: %{y}<extra></extra>"
        )
    ])
    
    fig.update_layout(
        title="Blood Requests by Blood Type",
        xaxis_title="<b>Blood Type</b>",
        yaxis_title="<b>Number of Requests</b>",
        plot_bgcolor="#ffffff",
        paper_bgcolor="#f8fafc",
        hoverlabel=dict(
            bgcolor="white",
            font_size=12,
            font_color="black"
        )
    )
    
    return fig


def get_requests_by_blood_type_data() -> Dict:
    """
    Main function to get blood requests by blood type data.
    Returns a dictionary with the data for API consumption.
    """
    blood_requests = fetch_blood_requests()
    
    if not blood_requests:
        return {
            "success": False,
            "message": "No blood requests found in database",
            "data": [],
            "total_requests": 0
        }
    
    requests_by_blood = aggregate_requests_by_blood_type(blood_requests)
    total = sum(r["total_requests"] for r in requests_by_blood)
    
    return {
        "success": True,
        "data": requests_by_blood,
        "total_requests": total
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML."""
    blood_requests = fetch_blood_requests()
    
    if not blood_requests:
        print("WARNING: No blood requests found in database", file=sys.stderr)
        return ""
    
    requests_by_blood = aggregate_requests_by_blood_type(blood_requests)
    fig = create_bar_chart(requests_by_blood)
    
    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")
    
    return fig.to_html(include_plotlyjs="cdn")


if __name__ == "__main__":
    # When run directly, output JSON data
    result = get_requests_by_blood_type_data()
    print(json.dumps(result, indent=2))

