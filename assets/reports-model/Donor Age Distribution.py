"""
Donor Age Distribution Interactive Chart
=========================================
Creates an interactive bar chart showing donor age distribution by age groups.
Uses ONLY donors whose latest eligibility status is approved/eligible, so the
total donors match the Total Active Donors definition.
"""

import json
import sys
from typing import Dict, List, Set

import plotly.graph_objects as go

from database import DatabaseConnection


# Age group definitions
AGE_GROUPS = ["18-25", "26-35", "36-45", "46-55", "56-65", "66+"]

# Color palette for age groups
COLORS = ["#3b82f6", "#8b5cf6", "#ec4899", "#f59e0b", "#10b981", "#6366f1"]


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
    """Fetch donor forms (age data) limited to donor_ids in filtered_ids."""
    if not filtered_ids:
        return []

    db = DatabaseConnection()
    donor_forms: List[Dict] = []

    if db.connect():
        try:
            endpoint = (
                "donor_form?"
                "select=donor_id,age,birthdate"
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


def get_age_group(age: int) -> str:
    """Determine age group for a given age."""
    if age is None:
        return "Unknown"
    if 18 <= age <= 25:
        return "18-25"
    elif 26 <= age <= 35:
        return "26-35"
    elif 36 <= age <= 45:
        return "36-45"
    elif 46 <= age <= 55:
        return "46-55"
    elif 56 <= age <= 65:
        return "56-65"
    elif age >= 66:
        return "66+"
    else:
        return "Unknown"


def aggregate_by_age_group(donor_forms: List[Dict]) -> List[Dict]:
    """
    Aggregate donors by age group.
    
    Returns:
        List of dicts with age_group, count, and percentage
    """
    age_group_counts: Dict[str, int] = {ag: 0 for ag in AGE_GROUPS}
    age_group_counts["Unknown"] = 0
    
    for donor in donor_forms:
        age = donor.get("age")
        
        # Try to parse age as integer
        if age is not None:
            try:
                age = int(age)
            except (ValueError, TypeError):
                age = None
        
        age_group = get_age_group(age)
        age_group_counts[age_group] = age_group_counts.get(age_group, 0) + 1
    
    # Calculate totals (excluding Unknown for percentage calculation)
    total = sum(count for ag, count in age_group_counts.items() if ag != "Unknown")
    
    # Build result list (only include groups with donors)
    result = []
    for ag in AGE_GROUPS:
        count = age_group_counts.get(ag, 0)
        if count > 0 or True:  # Include all groups for consistency
            percentage = round((count / total) * 100, 1) if total > 0 else 0
            result.append({
                "age_group": ag,
                "count": count,
                "percentage": percentage
            })
    
    return result


def create_bar_chart(donor_data: List[Dict], total_donors: int) -> go.Figure:
    """Create an interactive bar chart using Plotly."""
    age_groups = [d["age_group"] for d in donor_data]
    counts = [d["count"] for d in donor_data]
    
    # Map colors to data
    color_map = {ag: COLORS[i % len(COLORS)] for i, ag in enumerate(AGE_GROUPS)}
    bar_colors = [color_map.get(ag, "#6b7280") for ag in age_groups]
    
    fig = go.Figure(data=[
        go.Bar(
            x=age_groups,
            y=counts,
            marker=dict(
                color=bar_colors,
                line=dict(color="rgb(8,48,107)", width=1.5)
            ),
            hovertemplate=(
                "Age Group: %{x}<br>"
                "Count: %{y}<br>"
                "<extra></extra>"
            )
        )
    ])
    
    fig.update_layout(
        title=dict(
            text=f"<b>Donor Age Distribution</b><br><sub>Total Donors: {total_donors}</sub>",
            font=dict(size=20)
        ),
        xaxis=dict(
            title="<b>Age Group</b>",
            tickfont=dict(size=12)
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
        margin=dict(t=80, b=60, l=60, r=30)
    )
    
    return fig


def get_donor_age_distribution_data() -> Dict:
    """
    Main function to get donor age distribution data.
    Returns a dictionary with the data for API consumption.
    """
    donor_ids = fetch_active_donor_ids()
    donor_forms = fetch_donor_forms(donor_ids)

    if not donor_forms:
        return {
            "success": False,
            "message": "No active donors with age data found in database",
            "data": [],
            "total_donors": 0,
        }

    donor_data = aggregate_by_age_group(donor_forms)
    total_donors = sum(d["count"] for d in donor_data)
    
    return {
        "success": True,
        "data": donor_data,
        "total_donors": total_donors
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

        # No data – write a minimal placeholder HTML so the iframe still loads
        if not donor_forms:
            placeholder_html = """<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Donor Age Distribution</title></head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h3 style="margin-top: 0;">Donor Age Distribution</h3>
  <p>No active donors with age data are currently available. Please check back later.</p>
</body></html>"""
            if output_path:
                with open(output_path, "w", encoding="utf-8") as f:
                    f.write(placeholder_html)
                print(f"Placeholder age chart saved to: {output_path}", file=sys.stderr)
            return placeholder_html

        donor_data = aggregate_by_age_group(donor_forms)
        total_donors = sum(d["count"] for d in donor_data)

        fig = create_bar_chart(donor_data, total_donors)

        if output_path:
            fig.write_html(output_path)
            print(f"Chart saved to: {output_path}", file=sys.stderr)

        return fig.to_html(include_plotlyjs="cdn")
    except Exception as exc:
        # On ANY error, still create a safe placeholder HTML so PHP proxy never 404s
        placeholder_html = f"""<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Donor Age Distribution</title></head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h3 style="margin-top: 0;">Donor Age Distribution</h3>
  <p>An error occurred while generating this chart. Please check server logs.</p>
  <pre style="font-size: 11px; color: #6b7280;">{str(exc)}</pre>
</body></html>"""
        if output_path:
            try:
                with open(output_path, "w", encoding="utf-8") as f:
                    f.write(placeholder_html)
                print(f"Error placeholder age chart saved to: {output_path}", file=sys.stderr)
            except Exception:
                pass
        return placeholder_html


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_donor_age_distribution_data()
    
    print("\n=== DONOR AGE DISTRIBUTION SUMMARY ===\n")
    print(f"Total Donors: {result['total_donors']}\n")
    
    for item in result["data"]:
        print(f"Age Group {item['age_group']}: {item['count']} donors ({item['percentage']}%)")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Donor Age Distribution Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donor_age_distribution_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

