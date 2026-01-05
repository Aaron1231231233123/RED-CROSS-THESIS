"""
Donor Sex Distribution Interactive Pie Chart
=============================================
Creates an interactive pie/donut chart showing donor sex distribution
for the SAME donor set used by the eligibility-based reports.

Definition for Reports dashboard:
- Use donor_ids present in the eligibility table (latest record per donor)
- Join to donor_form to get sex
- One record per donor_id
"""

import json
import sys
from typing import Dict, List, Set

import plotly.graph_objects as go

from database import DatabaseConnection


# Color palette - blue for male, pink for female, green for other
COLORS = ["#3b82f6", "#ec4899", "#10b981"]


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
    This must match the definition used by Total Active Donors so that
    demographic charts (sex, etc.) have the same total.
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
    """Fetch donor forms with sex field, limited to donor_ids in filtered_ids."""
    if not filtered_ids:
        return []

    db = DatabaseConnection()
    donor_forms: List[Dict] = []

    if db.connect():
        try:
            endpoint = (
                "donor_form?"
                "select=donor_id,sex"
            )
            rows = db.supabase_request(endpoint)
        finally:
            db.disconnect()

    # Filter in Python to avoid extremely long IN() URL
    allowed = set(filtered_ids)
    for row in rows:
        donor_id = row.get("donor_id")
        if donor_id in allowed:
            donor_forms.append(row)

    return donor_forms


def normalize_sex(sex_value: str) -> str:
    """Normalize sex value to standard categories."""
    if not sex_value:
        return "Unknown"
    
    sex_lower = sex_value.lower().strip()
    
    if sex_lower in ["male", "m"]:
        return "Male"
    elif sex_lower in ["female", "f"]:
        return "Female"
    elif sex_lower in ["other", "o", "prefer not to say", "non-binary"]:
        return "Other"
    else:
        return "Unknown"


def aggregate_by_sex(donor_forms: List[Dict]) -> List[Dict]:
    """
    Aggregate donors by sex.
    
    Returns:
        List of dicts with sex, count, and percentage sorted by count descending
    """
    sex_counts: Dict[str, int] = {}
    
    for donor in donor_forms:
        sex = normalize_sex(donor.get("sex", ""))
        sex_counts[sex] = sex_counts.get(sex, 0) + 1
    
    total = sum(sex_counts.values())
    
    # Build result list sorted by count descending
    result = []
    for sex, count in sorted(sex_counts.items(), key=lambda x: x[1], reverse=True):
        if sex == "Unknown":
            continue  # Skip unknown for cleaner display
        percentage = round((count / total) * 100, 1) if total > 0 else 0
        result.append({
            "sex": sex,
            "count": count,
            "percentage": percentage
        })
    
    return result


def create_pie_chart(sex_data: List[Dict], total_donors: int) -> go.Figure:
    """Create an interactive pie/donut chart using Plotly."""
    labels = [d["sex"] for d in sex_data]
    values = [d["count"] for d in sex_data]
    
    # Map colors based on sex
    color_map = {"Male": "#3b82f6", "Female": "#ec4899", "Other": "#10b981"}
    chart_colors = [color_map.get(label, "#6b7280") for label in labels]
    
    # Create hover text
    hover_text = [
        f"Sex: {d['sex']}<br>Count: {d['count']}<br>Percentage: {d['percentage']}%"
        for d in sex_data
    ]
    
    fig = go.Figure(data=[
        go.Pie(
            labels=labels,
            values=values,
            hole=0.3,  # Donut chart
            marker=dict(
                colors=chart_colors,
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
            text=f"<b>Donor Sex Distribution</b><br><sub>Total Donors: {total_donors}</sub>",
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
        margin=dict(t=80, b=40, l=40, r=120)
    )
    
    return fig


def get_donor_sex_distribution_data() -> Dict:
    """
    Main function to get donor sex distribution data.
    Returns a dictionary with the data for API consumption.
    """
    donor_ids = fetch_active_donor_ids()
    donor_forms = fetch_donor_forms(donor_ids)

    if not donor_forms:
        return {
            "success": False,
            "message": "No donor forms found for donors with eligibility history",
            "data": [],
            "total_donors": 0,
        }

    sex_data = aggregate_by_sex(donor_forms)
    total_donors = sum(d["count"] for d in sex_data)
    
    return {
        "success": True,
        "data": sex_data,
        "total_donors": total_donors
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML."""
    donor_ids = fetch_active_donor_ids()
    donor_forms = fetch_donor_forms(donor_ids)

    if not donor_forms:
        print("WARNING: No donor forms found for donors with eligibility history", file=sys.stderr)
        return ""

    sex_data = aggregate_by_sex(donor_forms)
    total_donors = sum(d["count"] for d in sex_data)
    
    fig = create_pie_chart(sex_data, total_donors)
    
    if output_path:
        fig.write_html(output_path)
        print(f"Chart saved to: {output_path}")
    
    return fig.to_html(include_plotlyjs="cdn")


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_donor_sex_distribution_data()
    
    print("\n=== DONOR SEX DISTRIBUTION SUMMARY ===\n")
    print(f"Total Donors: {result['total_donors']}\n")
    
    for item in result["data"]:
        print(f"{item['sex']}: {item['count']} donors ({item['percentage']}%)")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Donor Sex Distribution Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donor_sex_distribution_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

