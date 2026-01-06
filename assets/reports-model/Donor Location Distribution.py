"""
Donor Location Distribution Interactive Chart
==============================================
Creates an interactive bar chart showing top 10 donor locations.
Uses ONLY donors whose latest eligibility status is approved/eligible, so the
total donors for this chart matches the active donor universe.
"""

import json
import re
import sys
from typing import Dict, List, Set

import plotly.graph_objects as go

from database import DatabaseConnection


# Color gradient - shades of teal/green (matching R version)
COLORS = ["#0d9488", "#14b8a6", "#2dd4bf", "#5eead4", "#99f6e4",
          "#0f766e", "#115e59", "#134e4a", "#0d9488", "#14b8a6"]


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


def fetch_active_donor_ids() -> Set[int]:
    """
    Fetch unique donor_ids whose LATEST eligibility status is APPROVED/eligible.
    Shared definition with Total Active Donors and other demographic charts.
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
            latest_by_donor[donor_id] = {"status": rec.get("status"), "_created": created}
        else:
            prev_created = existing.get("_created")
            if created and (prev_created is None or created > prev_created):
                latest_by_donor[donor_id] = {"status": rec.get("status"), "_created": created}

    active_ids: Set[int] = set()
    for donor_id, info in latest_by_donor.items():
        status_text = (info.get("status") or "").lower()
        if "approved" in status_text or status_text == "eligible":
            active_ids.add(donor_id)

    return active_ids


def fetch_donor_forms(filtered_ids: Set[int]) -> List[Dict]:
    """Fetch donor forms with address, limited to donor_ids in filtered_ids."""
    if not filtered_ids:
        return []

    db = DatabaseConnection()
    donor_forms: List[Dict] = []

    if db.connect():
        try:
            endpoint = (
                "donor_form?"
                "select=donor_id,permanent_address"
            )
            rows = db.supabase_request(endpoint)
        finally:
            db.disconnect()

    allowed = set(filtered_ids)
    for row in rows:
        donor_id = row.get("donor_id")
        if donor_id in allowed:
            donor_forms.append(row)

    return donor_forms


def extract_location(address: str) -> str:
    """
    Extract city/municipality from permanent address.
    Handles various Philippine address formats.
    """
    if not address:
        return "Unknown"
    
    address = address.strip()
    
    # Common patterns for Philippine addresses
    # Try to extract the municipality/city (usually before the province)
    
    # Pattern 1: "..., City/Municipality, Province"
    # Pattern 2: "Barangay X, City/Municipality, Province"
    
    # Split by comma and analyze parts
    parts = [p.strip() for p in address.split(",")]
    
    if len(parts) >= 2:
        # Look for city/municipality keywords
        for i, part in enumerate(parts):
            part_lower = part.lower()
            # Check if this part looks like a city/municipality
            if "city" in part_lower:
                return part.strip()
            # Check for common Iloilo municipalities
            municipalities = [
                "oton", "pavia", "leganes", "san miguel", "cabatuan",
                "sta. barbara", "santa barbara", "tigbauan", "maasin", "leon",
                "janiuay", "badiangan", "dumangas", "guimbal", "alimodian",
                "miagao", "san joaquin", "anilao", "banate", "barotac nuevo",
                "barotac viejo", "batad", "bingawan", "calinog", "carles",
                "concepcion", "dingle", "dueñas", "estancia", "igbaras",
                "lambunao", "lemery", "mina", "new lucena", "passi",
                "pototan", "san dionisio", "san enrique", "san rafael",
                "sara", "zarraga"
            ]
            for muni in municipalities:
                if muni in part_lower:
                    return part.strip()
        
        # If no municipality found, try second-to-last part (common format)
        if len(parts) >= 3:
            return parts[-2].strip()
        else:
            return parts[-1].strip()
    
    # Return first significant part if can't parse
    return parts[0] if parts else "Unknown"


def aggregate_by_location(donor_forms: List[Dict], top_n: int = 10) -> List[Dict]:
    """
    Aggregate donors by location (city/municipality).
    Returns top N locations sorted by count.
    """
    location_counts: Dict[str, int] = {}
    
    for donor in donor_forms:
        address = donor.get("permanent_address", "")
        location = extract_location(address)
        
        # Normalize location name
        location = location.title() if location else "Unknown"
        
        location_counts[location] = location_counts.get(location, 0) + 1
    
    # Sort by count and get top N
    sorted_locations = sorted(location_counts.items(), key=lambda x: x[1], reverse=True)
    top_locations = sorted_locations[:top_n]
    
    total_all = sum(location_counts.values())
    
    result = []
    for location, count in top_locations:
        percentage = round((count / total_all) * 100, 1) if total_all > 0 else 0
        result.append({
            "location": location,
            "count": count,
            "percentage": percentage
        })
    
    return result


def calculate_area_type_summary(donor_forms: List[Dict], urban_locations: List[str] = None) -> List[Dict]:
    """Calculate Urban vs Rural/Suburban summary."""
    if urban_locations is None:
        urban_locations = ["Iloilo City", "Iloilo"]
    
    urban_count = 0
    rural_count = 0
    
    for donor in donor_forms:
        address = donor.get("permanent_address", "")
        location = extract_location(address)
        
        if any(urban.lower() in location.lower() for urban in urban_locations):
            urban_count += 1
        else:
            rural_count += 1
    
    total = urban_count + rural_count
    
    return [
        {
            "area_type": "Urban",
            "count": urban_count,
            "percentage": round((urban_count / total) * 100, 1) if total > 0 else 0
        },
        {
            "area_type": "Rural/Suburban",
            "count": rural_count,
            "percentage": round((rural_count / total) * 100, 1) if total > 0 else 0
        }
    ]


def create_bar_chart(location_data: List[Dict], total_top: int, total_all: int) -> go.Figure:
    """Create an interactive bar chart using Plotly."""
    locations = [d["location"] for d in location_data]
    counts = [d["count"] for d in location_data]
    
    fig = go.Figure(data=[
        go.Bar(
            x=locations,
            y=counts,
            marker=dict(
                color=COLORS[:len(locations)],
                line=dict(color="rgb(8,51,68)", width=1.5)
            ),
            hovertemplate=(
                "Location: %{x}<br>"
                "Count: %{y}<br>"
                "<extra></extra>"
            )
        )
    ])
    
    fig.update_layout(
        title=dict(
            text=f"<b>Top 10 Donor Location Distribution</b><br><sub>Showing {total_top} out of {total_all} total donors</sub>",
            font=dict(size=20)
        ),
        xaxis=dict(
            title="<b>City/Municipality</b>",
            tickfont=dict(size=11),
            tickangle=-45
        ),
        yaxis=dict(
            title="<b>Number of Donors</b>",
            tickfont=dict(size=12),
            gridcolor="#e5e7eb"
        ),
        plot_bgcolor="#f8fafc",
        paper_bgcolor="#ffffff",
        hovermode="closest",
        hoverlabel=dict(
            bgcolor="white",
            font_size=12,
            font_color="black",
            bordercolor="#e5e7eb"
        ),
        margin=dict(t=100, b=120, l=60, r=30)
    )
    
    return fig


def get_donor_location_distribution_data(top_n: int = 10) -> Dict:
    """
    Main function to get donor location distribution data.
    Returns a dictionary with the data for API consumption.
    """
    donor_ids = fetch_active_donor_ids()
    donor_forms = fetch_donor_forms(donor_ids)

    if not donor_forms:
        return {
            "success": False,
            "message": "No active donors with location data found in database",
            "data": [],
            "total_top": 0,
            "total_all": 0,
            "area_type_summary": [],
        }

    location_data = aggregate_by_location(donor_forms, top_n)
    total_top = sum(d["count"] for d in location_data)
    total_all = len(donor_forms)
    area_type_summary = calculate_area_type_summary(donor_forms)
    
    return {
        "success": True,
        "data": location_data,
        "total_top": total_top,
        "total_all": total_all,
        "coverage_percentage": round((total_top / total_all) * 100, 1) if total_all > 0 else 0,
        "area_type_summary": area_type_summary
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML.

    IMPORTANT: Always create an HTML asset file, even when there is no data,
    so that forecast-asset.php never returns a 404. In the no-data case,
    we write a simple placeholder HTML with a friendly message.
    """
    try:
        donor_ids = fetch_active_donor_ids()
        donor_forms = fetch_donor_forms(donor_ids)

        if not donor_forms:
            placeholder_html = """<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Donor Location Distribution</title></head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h3 style="margin-top: 0;">Donor Location Distribution</h3>
  <p>No active donors with location data are currently available. Please check back later.</p>
</body></html>"""
            if output_path:
                with open(output_path, "w", encoding="utf-8") as f:
                    f.write(placeholder_html)
                print(f"Placeholder location chart saved to: {output_path}", file=sys.stderr)
            return placeholder_html

        location_data = aggregate_by_location(donor_forms, 10)
        total_top = sum(d["count"] for d in location_data)
        total_all = len(donor_forms)

        fig = create_bar_chart(location_data, total_top, total_all)

        if output_path:
            fig.write_html(output_path)
            print(f"Chart saved to: {output_path}", file=sys.stderr)

        return fig.to_html(include_plotlyjs="cdn")
    except Exception as exc:
        placeholder_html = f"""<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Donor Location Distribution</title></head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h3 style="margin-top: 0;">Donor Location Distribution</h3>
  <p>An error occurred while generating this chart. Please check server logs.</p>
  <pre style="font-size: 11px; color: #6b7280;">{str(exc)}</pre>
</body></html>"""
        if output_path:
            try:
                with open(output_path, "w", encoding="utf-8") as f:
                    f.write(placeholder_html)
                print(f"Error placeholder location chart saved to: {output_path}", file=sys.stderr)
            except Exception:
                pass
        return placeholder_html


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_donor_location_distribution_data()
    
    print("\n=== TOP 10 DONOR LOCATION DISTRIBUTION ===\n")
    print(f"Total Donors (All Locations): {result['total_all']}")
    print(f"Total Donors (Top 10): {result['total_top']}")
    print(f"Coverage: {result['coverage_percentage']}%\n")
    
    print("Rank | Location              | Count | Percentage")
    print("-----|----------------------|-------|------------")
    
    for i, item in enumerate(result["data"], 1):
        print(f"{i:<4} | {item['location']:<20} | {item['count']:<5} | {item['percentage']}%")
    
    # Area type summary
    print("\n=== AREA TYPE DISTRIBUTION ===\n")
    for item in result["area_type_summary"]:
        print(f"{item['area_type']}: {item['count']} donors ({item['percentage']}%)")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Donor Location Distribution Report")
    parser.add_argument("--top", type=int, default=10, help="Number of top locations to show")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donor_location_distribution_data(args.top)
        print(json.dumps(result, indent=2))
    else:
        print_summary()

