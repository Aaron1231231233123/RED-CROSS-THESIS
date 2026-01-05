"""
Monthly Blood Requests Trend
============================
Creates an interactive line chart showing monthly blood requests trend.
Uses real data from the blood_requests table in Supabase.
"""

import json
import sys
from collections import defaultdict
from datetime import datetime
from typing import Dict, List

import plotly.graph_objects as go

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


def process_monthly_requests(blood_requests: List[Dict]) -> List[Dict]:
    """
    Process blood requests grouped by month.
    
    Returns:
        List of dicts with year_month and total_requests, sorted by date
    """
    monthly_counts: Dict[str, int] = defaultdict(int)
    
    for request in blood_requests:
        # Get request date
        date_str = request.get("requested_on")
        request_date = parse_date(date_str)
        
        if not request_date:
            continue
        
        # Floor to first day of month
        month_key = request_date.replace(day=1).strftime("%Y-%m-%d")
        monthly_counts[month_key] += 1
    
    # Convert to sorted list
    result = [
        {"year_month": month, "total_requests": count}
        for month, count in sorted(monthly_counts.items())
    ]
    
    return result


def create_line_chart(monthly_data: List[Dict]) -> go.Figure:
    """Create an interactive line chart using Plotly."""
    x_values = [d["year_month"] for d in monthly_data]
    y_values = [d["total_requests"] for d in monthly_data]
    
    # Format hover text
    hover_text = [
        f"Month: {datetime.strptime(d['year_month'], '%Y-%m-%d').strftime('%B %Y')}<br>Requests: {d['total_requests']}"
        for d in monthly_data
    ]
    
    fig = go.Figure(data=[
        go.Scatter(
            x=x_values,
            y=y_values,
            mode="lines+markers",
            line=dict(color="#3b82f6", width=3),
            marker=dict(color="#3b82f6", size=8),
            hovertext=hover_text,
            hoverinfo="text"
        )
    ])
    
    fig.update_layout(
        title=dict(
            text="<b>Monthly Blood Requests Trend</b>",
            font=dict(size=20)
        ),
        xaxis=dict(
            title="<b>Month</b>",
            tickformat="%b %Y",
            gridcolor="#e5e7eb"
        ),
        yaxis=dict(
            title="<b>Number of Requests</b>",
            gridcolor="#e5e7eb"
        ),
        plot_bgcolor="#ffffff",
        paper_bgcolor="#f8fafc",
        hovermode="closest",
        hoverlabel=dict(
            bgcolor="white",
            font_size=12,
            font_color="black",
            bordercolor="#e5e7eb"
        ),
        margin=dict(t=50, b=50, l=60, r=30)
    )
    
    return fig


def get_monthly_requests_trend_data() -> Dict:
    """
    Main function to get monthly blood requests trend data.
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
    
    monthly_data = process_monthly_requests(blood_requests)
    total_requests = sum(d["total_requests"] for d in monthly_data)
    
    # Calculate trend statistics
    if len(monthly_data) >= 2:
        first_month = monthly_data[0]["total_requests"]
        last_month = monthly_data[-1]["total_requests"]
        trend_change = last_month - first_month
        trend_percentage = round((trend_change / first_month) * 100, 1) if first_month > 0 else 0
    else:
        trend_change = 0
        trend_percentage = 0
    
    return {
        "success": True,
        "data": monthly_data,
        "total_requests": total_requests,
        "months_count": len(monthly_data),
        "average_monthly_requests": round(total_requests / len(monthly_data), 1) if monthly_data else 0,
        "trend": {
            "change": trend_change,
            "percentage": trend_percentage,
            "direction": "up" if trend_change > 0 else ("down" if trend_change < 0 else "stable")
        }
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML."""
    blood_requests = fetch_blood_requests()
    
    if not blood_requests:
        print("WARNING: No blood requests found in database", file=sys.stderr)
        return ""
    
    monthly_data = process_monthly_requests(blood_requests)
    
    if not monthly_data:
        print("WARNING: No monthly data to display", file=sys.stderr)
        return ""
    
    fig = create_line_chart(monthly_data)
    
    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")
    
    return fig.to_html(include_plotlyjs="cdn")


def print_summary():
    """Print a formatted summary to console."""
    result = get_monthly_requests_trend_data()
    
    print("\n=== MONTHLY BLOOD REQUESTS TREND ===\n")
    print(f"Total Requests: {result['total_requests']}")
    print(f"Months Tracked: {result['months_count']}")
    print(f"Average Monthly Requests: {result['average_monthly_requests']}")
    
    trend = result.get("trend", {})
    if trend.get("direction") == "up":
        print(f"Trend: ↑ Up {trend['percentage']}%")
    elif trend.get("direction") == "down":
        print(f"Trend: ↓ Down {abs(trend['percentage'])}%")
    else:
        print("Trend: → Stable")
    
    print("\nMonthly Breakdown:")
    print("-" * 35)
    
    for item in result["data"]:
        month_str = datetime.strptime(item["year_month"], "%Y-%m-%d").strftime("%b %Y")
        print(f"{month_str}: {item['total_requests']} requests")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Monthly Blood Requests Trend Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_monthly_requests_trend_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

