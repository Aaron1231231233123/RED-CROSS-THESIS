"""
Forecast dashboard entry point.
Generates JSON plus refreshed chart assets for the admin UI.
"""

from __future__ import annotations

import json
from datetime import datetime, timedelta
from typing import Dict, List, Tuple

from database import DatabaseConnection
from forecast_workflow import run_forecast_workflow
from projected_stock_analysis import build_projected_stock_table

try:  # Optional dependency (plotnine) for static charts
    from forecast_visualizations import generate_charts
except ModuleNotFoundError:
    generate_charts = None  # type: ignore[assignment]

try:  # Optional dependency (plotly) for interactive dashboards
    from forecast_interactive import generate_interactive_plots
except ModuleNotFoundError:
    generate_interactive_plots = None  # type: ignore[assignment]


def _next_month_details(latest_months: List[Dict]) -> Tuple[str, str]:
    if not latest_months:
        today = datetime.now().replace(day=1)
        return today.strftime("%B %Y"), today.strftime("%Y-%m-%d")

    raw_dates = [datetime.strptime(item["month"], "%Y-%m-%d") for item in latest_months]
    last_month = max(raw_dates)
    year = last_month.year + (1 if last_month.month == 12 else 0)
    month = 1 if last_month.month == 12 else last_month.month + 1
    next_month = datetime(year, month, 1)
    return next_month.strftime("%B %Y"), next_month.strftime("%Y-%m-%d")


def _format_forecast_rows(
    supply_vs_demand: List[Dict], month_label: str, month_key: str
) -> List[Dict]:
    rows = []
    for row in supply_vs_demand:
        rows.append(
            {
                "month_label": month_label,
                "month_key": month_key,
                "blood_type": row["Blood_Type"],
                "forecasted_supply": int(row.get("Forecast_Supply", 0)),
                "forecasted_demand": int(row.get("Forecast_Demand", 0)),
                "gap": int(row.get("Gap", row.get("Forecast_Supply", 0) - row.get("Forecast_Demand", 0))),
                "status": row.get("Status", "Balanced"),
            }
        )
    return rows


def _build_summary(
    supply_vs_demand: List[Dict], projected_stock: List[Dict], shelf_life: Dict
) -> Dict:
    total_supply = sum(row.get("Forecast_Supply", 0) for row in supply_vs_demand)
    total_demand = sum(row.get("Forecast_Demand", 0) for row in supply_vs_demand)
    projected_balance = total_supply - total_demand
    critical = [
        row["Blood_Type"]
        for row in projected_stock
        if row.get("Buffer_Status", "").startswith("🔴")
    ]
    buffer_gap_total = sum(row.get("Buffer_Gap", 0) for row in projected_stock)
    target_stock_total = sum(row.get("Target_Stock", 0) for row in projected_stock)

    return {
        "total_forecasted_supply": int(total_supply),
        "total_forecasted_demand": int(total_demand),
        "projected_balance": int(projected_balance),
        "critical_types": critical,
        "target_stock_level": int(round(target_stock_total)),
        "buffer_gap_total": int(round(buffer_gap_total)),
        "expiring_weekly": int(shelf_life.get("expiring_weekly", 0)),
        "expiring_monthly": int(shelf_life.get("expiring_monthly", 0)),
        "expiring_weekly_percentage": int(
            round(shelf_life.get("expiring_weekly_percentage", 0))
        ),
        "expiring_monthly_percentage": int(
            round(shelf_life.get("expiring_monthly_percentage", 0))
        ),
    }


def _chart_paths() -> Dict[str, str]:
    base = "../../assets/reports-model"
    charts = f"{base}/charts"
    return {
        "supply": f"{charts}/supply_forecast.png",
        "demand": f"{charts}/demand_forecast.png",
        "comparison": f"{charts}/supply_vs_demand.png",
        "projected_stock": f"{charts}/projected_stock.png",
        "interactive_supply": f"{base}/interactive_supply.html",
        "interactive_demand": f"{base}/interactive_demand.html",
        "interactive_combined": f"{base}/interactive_combined.html",
        "projected_stock_html": f"{base}/projected_stock.html",
    }


def _calculate_shelf_life(blood_units: List[Dict]) -> Dict:
    today = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
    next_week = today + timedelta(days=7)
    next_month = today + timedelta(days=30)

    expiring_weekly = 0
    expiring_monthly = 0
    total_valid_units = 0

    for unit in blood_units:
        collected_at = unit.get("collected_at")
        status = (unit.get("status") or "").lower()
        handed_over = unit.get("handed_over_at")

        if not collected_at or status == "handed_over" or handed_over:
            continue

        try:
            date = datetime.fromisoformat(str(collected_at).replace("Z", "+00:00"))
        except ValueError:
            continue

        if date.tzinfo:
            date = date.replace(tzinfo=None)

        expiration = date + timedelta(days=45)
        if expiration <= today:
            continue

        total_valid_units += 1
        if expiration <= next_week:
            expiring_weekly += 1
        if expiration <= next_month:
            expiring_monthly += 1

    total_valid = total_valid_units or 1
    return {
        "expiring_weekly": expiring_weekly,
        "expiring_monthly": expiring_monthly,
        "expiring_weekly_percentage": round((expiring_weekly / total_valid) * 100),
        "expiring_monthly_percentage": round((expiring_monthly / total_valid) * 100),
        "total_valid_units": total_valid_units,
    }


def _fetch_shelf_data() -> Dict:
    db = DatabaseConnection()
    blood_units: List[Dict] = []
    if db.connect():
        try:
            blood_units = db.fetch_blood_units()
        finally:
            db.disconnect()
    return _calculate_shelf_life(blood_units) if blood_units else {
        "expiring_weekly": 0,
        "expiring_monthly": 0,
        "expiring_weekly_percentage": 0,
        "expiring_monthly_percentage": 0,
        "total_valid_units": 0,
    }


def _refresh_assets(workflow: Dict) -> List[str]:
    errors: List[str] = []
    if generate_charts:
        try:
            generate_charts(workflow_results=workflow)
        except Exception as exc:  # pragma: no cover - diagnostics
            errors.append(f"static_charts: {exc}")
    else:
        errors.append("static_charts: plotnine not installed")

    if generate_interactive_plots:
        try:
            generate_interactive_plots(workflow_results=workflow)
        except Exception as exc:  # pragma: no cover - diagnostics
            errors.append(f"interactive_charts: {exc}")
    else:
        errors.append("interactive_charts: plotly not installed")

    return errors


def generate_dashboard_payload() -> Dict:
    workflow = run_forecast_workflow()
    supply_vs_demand = workflow["supply_vs_demand_df"]
    month_label, month_key = _next_month_details(workflow["df_monthly_donations"])

    projected_stock = build_projected_stock_table(supply_vs_demand)
    shelf_life_metrics = _fetch_shelf_data()
    summary = _build_summary(supply_vs_demand, projected_stock, shelf_life_metrics)
    forecast_rows = _format_forecast_rows(supply_vs_demand, month_label, month_key)

    asset_errors = _refresh_assets(workflow)

    return {
        "success": True,
        "generated_at": datetime.now().isoformat(),
        "summary": summary,
        "forecast_rows": forecast_rows,
        "forecast_supply": workflow["forecast_supply_df"],
        "forecast_demand": workflow["forecast_demand_df"],
        "projected_stock": projected_stock,
        "charts": _chart_paths(),
        "asset_errors": asset_errors,
    }


def main():
    try:
        payload = generate_dashboard_payload()
        print(json.dumps(payload, indent=2))
    except Exception as exc:  # pragma: no cover
        error_payload = {
            "success": False,
            "error": str(exc),
        }
        print(json.dumps(error_payload, indent=2))


if __name__ == "__main__":
    main()

