"""
Donor Blood Type Distribution Interactive Chart
================================================
Creates an interactive bar chart showing donor blood type distribution.

Definition (to align with active donor universe):
- Use the eligibility table
- Work from the LATEST eligibility row per donor_id
- Include ONLY donors whose latest status is approved/eligible
- Count each donor_id ONCE (no duplicates)
"""

import json
import sys
from typing import Dict, List

import plotly.graph_objects as go

from database import DatabaseConnection
from config import BLOOD_TYPES


# Color palette - red shades for blood types (matching R version)
COLORS = ["#dc2626", "#ef4444", "#f87171", "#fca5a5",
          "#991b1b", "#b91c1c", "#dc2626", "#ef4444"]


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


def fetch_approved_latest_blood_types() -> List[Dict]:
    """
    Fetch one blood_type per donor based on the LATEST eligibility row
    whose status is approved/eligible and blood_type is not null.

    Returns a list of records like: { donor_id, blood_type } with
    each donor_id appearing at most once.
    """
    db = DatabaseConnection()
    records: List[Dict] = []

    if db.connect():
        try:
            endpoint = (
                "eligibility?"
                "select=eligibility_id,donor_id,status,blood_type,created_at"
                "&blood_type=not.is.null"
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
            latest_by_donor[donor_id] = {
                "status": rec.get("status"),
                "blood_type": rec.get("blood_type"),
                "_created": created,
            }
        else:
            prev_created = existing.get("_created")
            if created and (prev_created is None or created > prev_created):
                latest_by_donor[donor_id] = {
                    "status": rec.get("status"),
                    "blood_type": rec.get("blood_type"),
                    "_created": created,
                }

    result: List[Dict] = []
    for donor_id, info in latest_by_donor.items():
        status_text = (info.get("status") or "").lower()
        blood_type = info.get("blood_type")
        if not blood_type:
            continue
        if "approved" in status_text or status_text == "eligible":
            result.append({"donor_id": donor_id, "blood_type": blood_type})

    return result


def aggregate_by_blood_type(donor_records: List[Dict]) -> List[Dict]:
    """
    Aggregate donors by blood type.
    
    Returns:
        List of dicts with blood_type, count, and percentage
    """
    blood_type_counts: Dict[str, int] = {bt: 0 for bt in BLOOD_TYPES}

    for record in donor_records:
        blood_type = record.get("blood_type")

        if blood_type and blood_type in BLOOD_TYPES:
            blood_type_counts[blood_type] += 1
    
    total = sum(blood_type_counts.values())
    
    # Build result list in order: O+, O-, A+, A-, B+, B-, AB+, AB-
    blood_type_order = ["O+", "O-", "A+", "A-", "B+", "B-", "AB+", "AB-"]
    result = []
    
    for bt in blood_type_order:
        count = blood_type_counts.get(bt, 0)
        percentage = round((count / total) * 100, 1) if total > 0 else 0
        result.append({
            "blood_type": bt,
            "count": count,
            "percentage": percentage
        })
    
    return result


def calculate_rh_summary(donor_data: List[Dict]) -> List[Dict]:
    """Calculate Rh factor summary (Positive vs Negative)."""
    positive_count = sum(d["count"] for d in donor_data if d["blood_type"].endswith("+"))
    negative_count = sum(d["count"] for d in donor_data if d["blood_type"].endswith("-"))
    total = positive_count + negative_count
    
    return [
        {
            "rh_factor": "Positive",
            "count": positive_count,
            "percentage": round((positive_count / total) * 100, 1) if total > 0 else 0
        },
        {
            "rh_factor": "Negative",
            "count": negative_count,
            "percentage": round((negative_count / total) * 100, 1) if total > 0 else 0
        }
    ]


def create_bar_chart(donor_data: List[Dict], total_donors: int) -> go.Figure:
    """Create an interactive bar chart using Plotly."""
    blood_types = [d["blood_type"] for d in donor_data]
    counts = [d["count"] for d in donor_data]
    
    fig = go.Figure(data=[
        go.Bar(
            x=blood_types,
            y=counts,
            marker=dict(
                color=COLORS[:len(blood_types)],
                line=dict(color="rgb(139, 0, 0)", width=1.5)
            ),
            hovertemplate=(
                "Blood Type: %{x}<br>"
                "Count: %{y}<br>"
                "<extra></extra>"
            )
        )
    ])
    
    fig.update_layout(
        title=dict(
            text=f"<b>Donor Blood Type Distribution</b><br><sub>Total Donors: {total_donors}</sub>",
            font=dict(size=20)
        ),
        xaxis=dict(
            title="<b>Blood Type</b>",
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


def get_donor_blood_type_distribution_data() -> Dict:
    """
    Main function to get donor blood type distribution data.
    Returns a dictionary with the data for API consumption.
    """
    donor_records = fetch_approved_latest_blood_types()

    if not donor_records:
        return {
            "success": False,
            "message": "No active donors with blood type data found in eligibility table",
            "data": [],
            "total_donors": 0,
            "rh_summary": [],
        }

    donor_data = aggregate_by_blood_type(donor_records)
    total_donors = sum(d["count"] for d in donor_data)
    rh_summary = calculate_rh_summary(donor_data)
    
    return {
        "success": True,
        "data": donor_data,
        "total_donors": total_donors,
        "rh_summary": rh_summary
    }


def generate_chart_html(output_path: str = None) -> str:
    """Generate and optionally save the chart as HTML.

    IMPORTANT: Always create an HTML asset file, even when there is no data,
    so that forecast-asset.php never returns a 404. In the no-data case,
    we write a simple placeholder HTML with a friendly message.
    """
    try:
        donor_records = fetch_approved_latest_blood_types()

        if not donor_records:
            placeholder_html = """<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Donor Blood Type Distribution</title></head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h3 style="margin-top: 0;">Donor Blood Type Distribution</h3>
  <p>No donor blood type data is currently available. Please check back later.</p>
</body></html>"""
            if output_path:
                with open(output_path, "w", encoding="utf-8") as f:
                    f.write(placeholder_html)
                print(f"Placeholder blood type chart saved to: {output_path}", file=sys.stderr)
            return placeholder_html

        donor_data = aggregate_by_blood_type(donor_records)
        total_donors = sum(d["count"] for d in donor_data)

        fig = create_bar_chart(donor_data, total_donors)

        if output_path:
            fig.write_html(output_path)
            print(f"Chart saved to: {output_path}", file=sys.stderr)

        return fig.to_html(include_plotlyjs="cdn")
    except Exception as exc:
        placeholder_html = f"""<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Donor Blood Type Distribution</title></head>
<body style="font-family: Arial, sans-serif; padding: 16px;">
  <h3 style="margin-top: 0;">Donor Blood Type Distribution</h3>
  <p>An error occurred while generating this chart. Please check server logs.</p>
  <pre style="font-size: 11px; color: #6b7280;">{str(exc)}</pre>
</body></html>"""
        if output_path:
            try:
                with open(output_path, "w", encoding="utf-8") as f:
                    f.write(placeholder_html)
                print(f"Error placeholder blood type chart saved to: {output_path}", file=sys.stderr)
            except Exception:
                pass
        return placeholder_html


def print_summary():
    """Print a formatted summary to console (matching R output style)."""
    result = get_donor_blood_type_distribution_data()
    
    print("\n=== DONOR BLOOD TYPE DISTRIBUTION SUMMARY ===\n")
    print(f"Total Donors: {result['total_donors']}\n")
    
    for item in result["data"]:
        print(f"Blood Type {item['blood_type']}: {item['count']} donors ({item['percentage']}%)")
    
    # Rh factor summary
    print("\n=== RH FACTOR SUMMARY ===\n")
    for item in result["rh_summary"]:
        print(f"{item['rh_factor']}: {item['count']} donors ({item['percentage']}%)")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Donor Blood Type Distribution Report")
    parser.add_argument("--json", action="store_true", help="Output as JSON")
    parser.add_argument("--html", type=str, help="Save chart as HTML to specified path")
    args = parser.parse_args()
    
    if args.html:
        generate_chart_html(args.html)
    elif args.json:
        result = get_donor_blood_type_distribution_data()
        print(json.dumps(result, indent=2))
    else:
        print_summary()

